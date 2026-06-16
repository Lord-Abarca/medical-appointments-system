<?php
/**
 * Clase para gestionar horarios de atención de profesionales
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAS_Professional_Schedules {
    
    private static $table_name;
    
    /**
     * Inicializar nombre de tabla
     */
    public static function init() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'mas_professional_schedules';
    }
    
    /**
     * Obtener horarios de un profesional
     */
    public static function get_schedule($professional_id) {
        global $wpdb;
        self::init();
        
        $schedules = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::$table_name . " 
             WHERE professional_id = %d 
             ORDER BY day_of_week ASC",
            $professional_id
        ));
        
        // Indexar por día de la semana
        $indexed = array();
        foreach ($schedules as $schedule) {
            $indexed[$schedule->day_of_week] = $schedule;
        }
        
        return $indexed;
    }
    
    /**
     * Guardar horarios de un profesional
     */
    public static function save_schedule($professional_id, $schedules) {
        global $wpdb;
        self::init();
        
        // Eliminar horarios existentes
        $wpdb->delete(self::$table_name, array('professional_id' => $professional_id), array('%d'));
        
        // Insertar nuevos horarios
        foreach ($schedules as $day => $schedule) {
            if (!empty($schedule['is_enabled'])) {
                $wpdb->insert(
                    self::$table_name,
                    array(
                        'professional_id' => $professional_id,
                        'day_of_week' => $day,
                        'start_time' => $schedule['start_time'],
                        'end_time' => $schedule['end_time'],
                        'is_enabled' => 1
                    ),
                    array('%d', '%d', '%s', '%s', '%d')
                );
            }
        }
        
        return true;
    }
    
    /**
     * Verificar si un profesional trabaja un día específico
     */
    public static function works_on_day($professional_id, $day_of_week) {
        global $wpdb;
        self::init();
        
        $schedule = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::$table_name . " 
             WHERE professional_id = %d AND day_of_week = %d AND is_enabled = 1",
            $professional_id,
            $day_of_week
        ));
        
        return !empty($schedule);
    }
    
    /**
     * Obtener horario de un día específico
     */
    public static function get_day_schedule($professional_id, $day_of_week) {
        global $wpdb;
        self::init();
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::$table_name . " 
             WHERE professional_id = %d AND day_of_week = %d AND is_enabled = 1",
            $professional_id,
            $day_of_week
        ));
    }
    
    /**
     * Obtener días de trabajo de un profesional
     */
    public static function get_working_days($professional_id) {
        global $wpdb;
        self::init();
        
        $days = $wpdb->get_col($wpdb->prepare(
            "SELECT day_of_week FROM " . self::$table_name . " 
             WHERE professional_id = %d AND is_enabled = 1 
             ORDER BY day_of_week ASC",
            $professional_id
        ));
        
        return array_map('intval', $days);
    }
    
    /**
     * Obtener slots disponibles para un profesional en una fecha
     */
    public static function get_available_slots($professional_id, $date) {
        global $wpdb;
        self::init();
        
        // Obtener día de la semana (1=Lunes, 7=Domingo)
        $day_of_week = date('N', strtotime($date));
        
        // Obtener horario del profesional para ese día
        $schedule = self::get_day_schedule($professional_id, $day_of_week);
        
        if (!$schedule) {
            return array(); // El profesional no trabaja ese día
        }
        
        // Obtener configuración general del sistema
        $settings = get_option('mas_settings', array());
        $slot_duration = isset($settings['appointment_duration']) ? intval($settings['appointment_duration']) : 30;
        
        // Generar slots
        $slots = array();
        $start = new DateTime($schedule->start_time);
        $end = new DateTime($schedule->end_time);
        
        while ($start < $end) {
            $slot_start = $start->format('H:i:s');
            $start->modify("+{$slot_duration} minutes");
            $slot_end = $start->format('H:i:s');
            
            if ($start <= $end) {
                $slots[] = array(
                    'start_time' => $slot_start,
                    'end_time' => $slot_end,
                    'available' => true
                );
            }
        }
        
        // Filtrar slots ocupados por citas existentes
        $appointments_table = $wpdb->prefix . 'mas_appointments';
        $existing = $wpdb->get_results($wpdb->prepare(
            "SELECT start_time, end_time FROM $appointments_table 
             WHERE professional_id = %d 
             AND appointment_date = %s 
             AND status IN ('pending', 'scheduled', 'confirmed')",
            $professional_id,
            $date
        ));
        
        foreach ($slots as &$slot) {
            foreach ($existing as $appointment) {
                if ($slot['start_time'] < $appointment->end_time && $slot['end_time'] > $appointment->start_time) {
                    $slot['available'] = false;
                    break;
                }
            }
        }
        
        // Devolver solo slots disponibles
        return array_filter($slots, function($slot) {
            return $slot['available'];
        });
    }
    
    /**
     * Verificar si un profesional tiene horarios configurados
     */
    public static function has_schedule($professional_id) {
        global $wpdb;
        self::init();
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::$table_name . " 
             WHERE professional_id = %d AND is_enabled = 1",
            $professional_id
        ));
        
        return $count > 0;
    }
    
    /**
     * Obtener nombre del día de la semana
     */
    public static function get_day_name($day_number) {
        $days = array(
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo'
        );
        return isset($days[$day_number]) ? $days[$day_number] : '';
    }
    
    /**
     * Obtener fechas disponibles para un profesional en un rango
     */
    public static function get_available_dates($professional_id, $start_date, $end_date) {
        $working_days = self::get_working_days($professional_id);
        
        if (empty($working_days)) {
            return array();
        }
        
        $available_dates = array();
        $current = new DateTime($start_date);
        $end = new DateTime($end_date);
        
        while ($current <= $end) {
            $day_of_week = (int) $current->format('N');
            if (in_array($day_of_week, $working_days)) {
                $date_str = $current->format('Y-m-d');
                // Verificar que haya al menos un slot disponible
                $slots = self::get_available_slots($professional_id, $date_str);
                if (!empty($slots)) {
                    $available_dates[] = $date_str;
                }
            }
            $current->modify('+1 day');
        }
        
        return $available_dates;
    }
}
