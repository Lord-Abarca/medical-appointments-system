<?php
/**
 * Clase para gestionar boxes de atención
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAS_Boxes {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'mas_boxes';
    }
    
    /**
     * Crear un nuevo box
     */
    public function create_box($data) {
        global $wpdb;
        
        error_log('[MAS] Intentando crear box con número: ' . $data['box_number']);
        
        $insert_data = array(
            'box_name' => $data['box_name'],
            'box_number' => $data['box_number'],
            'description' => isset($data['description']) ? $data['description'] : '',
            'price_per_hour' => $data['price_per_hour'],
            'status' => 'active'
        );
        
        if (isset($data['image_url'])) {
            $insert_data['image_url'] = $data['image_url'];
        }
        
        $result = $wpdb->insert($this->table_name, $insert_data);
        
        if ($result) {
            $box_id = $wpdb->insert_id;
            error_log('[MAS] Box creado exitosamente con ID: ' . $box_id);
            return $box_id;
        }
        
        error_log('[MAS] Error al insertar box en la base de datos: ' . $wpdb->last_error);
        return false;
    }
    
    /**
     * Actualizar un box
     */
    public function update_box($id, $data) {
        global $wpdb;
        
        $update_data = array();
        
        if (isset($data['box_name'])) {
            $update_data['box_name'] = $data['box_name'];
        }
        
        if (isset($data['box_number'])) {
            $update_data['box_number'] = $data['box_number'];
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = $data['description'];
        }
        
        if (isset($data['price_per_hour'])) {
            $update_data['price_per_hour'] = $data['price_per_hour'];
        }
        
        if (isset($data['status'])) {
            $update_data['status'] = $data['status'];
        }
        
        if (isset($data['image_url'])) {
            $update_data['image_url'] = $data['image_url'];
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => $id)
        );
    }
    
    /**
     * Obtener un box por ID
     */
    public function get_box($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Obtener todos los boxes
     */
    public function get_boxes($status = 'active') {
        global $wpdb;
        
        if ($status) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE status = %s ORDER BY box_number ASC",
                $status
            ));
        }
        
        return $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY box_number ASC");
    }
    
    /**
     * Eliminar un box
     */
    public function delete_box($id) {
        global $wpdb;
        return $wpdb->delete($this->table_name, array('id' => $id));
    }
    
    /**
     * Verificar si un box está disponible en una fecha y horario
     */
    public function is_box_available($box_id, $date, $start_time, $end_time, $exclude_rental_id = null) {
        global $wpdb;
        $rentals_table = $wpdb->prefix . 'mas_box_rentals';
        
        $query = "SELECT COUNT(*) FROM {$rentals_table}
            WHERE box_id = %d
            AND rental_date = %s
            AND status IN ('pending', 'active', 'completed', 'interno')
            AND (
                (start_time < %s AND end_time > %s)
                OR (start_time < %s AND end_time > %s)
                OR (start_time >= %s AND end_time <= %s)
            )";
        
        $params = array(
            $box_id,
            $date,
            $end_time,
            $start_time,
            $end_time,
            $end_time,
            $start_time,
            $end_time
        );
        
        if ($exclude_rental_id) {
            $query .= " AND id != %d";
            $params[] = $exclude_rental_id;
        }
        
        $count = $wpdb->get_var($wpdb->prepare($query, $params));
        
        return $count == 0;
    }
    
    /**
     * Nuevo método para obtener horarios disponibles de un box para una fecha
     * Utiliza la configuración de horarios por día de la semana (schedule_by_day)
     */
    public function get_available_rental_slots($box_id, $date, $exclude_rental_id = null) {
        global $wpdb;
        
        $blocked_dates_table = $wpdb->prefix . 'mas_blocked_dates';
        $is_blocked = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $blocked_dates_table 
            WHERE blocked_date = %s AND blocks_rentals = 1",
            $date
        ));
        
        if ($is_blocked > 0) {
            error_log('[MAS] Fecha bloqueada para arriendos: ' . $date);
            return array();
        }
        
        $settings = get_option('mas_settings');
        
        $day_of_week = date('w', strtotime($date));
        
        error_log('[MAS] Verificando slots para box: ' . $box_id . ', fecha: ' . $date . ', día: ' . $day_of_week . ', exclude_rental_id: ' . $exclude_rental_id);
        
        
        if (isset($settings['schedule_by_day']) && is_array($settings['schedule_by_day'])) {
            
            if (!isset($settings['schedule_by_day'][$day_of_week]) || 
                !isset($settings['schedule_by_day'][$day_of_week]['enabled']) || 
                !$settings['schedule_by_day'][$day_of_week]['enabled']) {
                error_log('[MAS] Día no habilitado para arriendos: ' . $day_of_week);
                return array();
            }
            
            $start_time = $settings['schedule_by_day'][$day_of_week]['start_time'];
            $end_time = $settings['schedule_by_day'][$day_of_week]['end_time'];
        } else {
            error_log('[MAS] Usando configuración global (legacy)');
            $start_time = isset($settings['start_time']) ? $settings['start_time'] : '09:00';
            $end_time = isset($settings['end_time']) ? $settings['end_time'] : '18:00';
            $working_days = isset($settings['working_days']) ? $settings['working_days'] : array('1', '2', '3', '4', '5');
            
            if (!in_array($day_of_week, $working_days)) {
                error_log('[MAS] Día no laboral para arriendos: ' . $day_of_week);
                return array();
            }
        }
        
        $slot_duration = isset($settings['slot_duration']) ? $settings['slot_duration'] : 60;
        
        error_log('[MAS] Generando slots: inicio=' . $start_time . ', término=' . $end_time . ', duración=' . $slot_duration);
        
        $slots = array();
        $current_time = strtotime($start_time);
        $end_timestamp = strtotime($end_time);
        
        while ($current_time < $end_timestamp) {
            $time_slot = date('H:i', $current_time);
            $next_time = date('H:i', $current_time + ($slot_duration * 60));
            
            if ($this->is_box_available($box_id, $date, $time_slot, $next_time, $exclude_rental_id)) {
                $slots[] = array(
                    'time' => $time_slot,
                    'formatted' => date('H:i', $current_time),
                    'available' => true
                );
            }
            
            $current_time = strtotime("+{$slot_duration} minutes", $current_time);
        }
        
        error_log('[MAS] Slots disponibles para box ' . $box_id . ' en ' . $date . ': ' . count($slots));
        
        return $slots;
    }
}
