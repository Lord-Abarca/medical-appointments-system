<?php
/**
 * Clase para gestionar citas médicas
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAS_Appointments {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'mas_appointments';
    }
    
    /**
     * Crear una nueva cita
     */
    public function create_appointment($data) {
        global $wpdb;
        
        error_log('[MAS] Intentando crear cita para fecha: ' . $data['appointment_date'] . ' hora: ' . $data['appointment_time']);
        
        $send_notification = isset($data['send_notification']) ? $data['send_notification'] : true;
        
        $is_available = $this->is_slot_available($data['appointment_date'], $data['appointment_time']);
        error_log('[MAS] Slot disponible: ' . ($is_available ? 'SI' : 'NO'));
        
        if (!$is_available) {
            error_log('[MAS] Slot not available for date: ' . $data['appointment_date'] . ' time: ' . $data['appointment_time']);
            return false;
        }
        
        $settings = get_option('mas_settings');
        $duration = isset($settings['slot_duration']) ? $settings['slot_duration'] : 60;
        
        $amount_paid = null;
        if (!empty($data['service_id'])) {
            $service = $wpdb->get_row($wpdb->prepare(
                "SELECT price FROM {$wpdb->prefix}mas_services WHERE id = %d",
                $data['service_id']
            ));
            if ($service) {
                $amount_paid = $service->price;
            }
        }
        
        $insert_data = array(
            'patient_name' => $data['patient_name'],
            'patient_email' => $data['patient_email'],
            'patient_phone' => $data['patient_phone'],
            'patient_rut' => isset($data['patient_rut']) ? $data['patient_rut'] : '',
            'health_insurance' => isset($data['health_insurance']) ? $data['health_insurance'] : '',
            'appointment_date' => $data['appointment_date'],
            'appointment_time' => $data['appointment_time'],
            'duration' => $duration,
            'professional_id' => isset($data['professional_id']) ? $data['professional_id'] : null,
            'service_id' => isset($data['service_id']) ? $data['service_id'] : null,
            'status' => isset($data['status']) ? $data['status'] : 'pending',
            'payment_status' => isset($data['payment_status']) ? $data['payment_status'] : 'pending',
            'payment_method' => isset($data['payment_method']) ? $data['payment_method'] : null,
            'amount_paid' => $amount_paid,
            'notes' => isset($data['notes']) ? $data['notes'] : ''
        );
        
        error_log('[MAS] Datos a insertar: ' . print_r($insert_data, true));
        
        $result = $wpdb->insert($this->table_name, $insert_data);
        
        if ($result) {
            $appointment_id = $wpdb->insert_id;
            
            if ($send_notification) {
                $this->send_appointment_notification($appointment_id);
            }
            
            error_log('[MAS] Appointment created successfully with ID: ' . $appointment_id);
            return $appointment_id;
        }
        
        error_log('[MAS] Failed to insert appointment. Error: ' . $wpdb->last_error);
        return false;
    }
    
    /**
     * Crear una nueva cita desde el admin (permite sobrescribir horarios)
     */
    public function create_appointment_admin($data) {
        global $wpdb;
        
        $settings = get_option('mas_settings');
        $duration = isset($settings['slot_duration']) ? $settings['slot_duration'] : 60;
        
        $amount_paid = null;
        if (!empty($data['service_id'])) {
            $service = $wpdb->get_row($wpdb->prepare(
                "SELECT price FROM {$wpdb->prefix}mas_services WHERE id = %d",
                $data['service_id']
            ));
            if ($service) {
                $amount_paid = $service->price;
            }
        }
        
        $insert_data = array(
            'patient_name' => $data['patient_name'],
            'patient_email' => $data['patient_email'],
            'patient_phone' => $data['patient_phone'],
            'patient_rut' => isset($data['patient_rut']) ? $data['patient_rut'] : '',
            'health_insurance' => isset($data['health_insurance']) ? $data['health_insurance'] : '',
            'appointment_date' => $data['appointment_date'],
            'appointment_time' => $data['appointment_time'],
            'duration' => $duration,
            'professional_id' => isset($data['professional_id']) ? $data['professional_id'] : null,
            'service_id' => isset($data['service_id']) ? $data['service_id'] : null,
            'status' => isset($data['status']) ? $data['status'] : 'pending',
            'payment_status' => isset($data['payment_status']) ? $data['payment_status'] : 'pending',
            'payment_method' => isset($data['payment_method']) ? $data['payment_method'] : null,
            'amount_paid' => $amount_paid,
            'notes' => isset($data['notes']) ? $data['notes'] : ''
        );
        
        $result = $wpdb->insert($this->table_name, $insert_data);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Actualizar una cita
     */
    public function update_appointment($id, $data) {
        global $wpdb;
        
        $update_data = array();
        
        if (isset($data['service_id'])) {
            $update_data['service_id'] = $data['service_id'];
            
            // Get new service price
            $service = $wpdb->get_row($wpdb->prepare(
                "SELECT price FROM {$wpdb->prefix}mas_services WHERE id = %d",
                $data['service_id']
            ));
            if ($service) {
                $update_data['amount_paid'] = $service->price;
            }
        }
        
        if (array_key_exists('professional_id', $data)) {
            $update_data['professional_id'] = $data['professional_id'];
        }
        
        if (isset($data['patient_name'])) {
            $update_data['patient_name'] = $data['patient_name'];
        }
        
        if (isset($data['patient_email'])) {
            $update_data['patient_email'] = $data['patient_email'];
        }
        
        if (isset($data['patient_phone'])) {
            $update_data['patient_phone'] = $data['patient_phone'];
        }
        
        if (isset($data['patient_rut'])) {
            $update_data['patient_rut'] = $data['patient_rut'];
        }
        
        if (isset($data['health_insurance'])) {
            $update_data['health_insurance'] = $data['health_insurance'];
        }
        
        if (isset($data['status'])) {
            $update_data['status'] = $data['status'];
        }
        
        if (isset($data['appointment_date'])) {
            $update_data['appointment_date'] = $data['appointment_date'];
        }
        
        if (isset($data['appointment_time'])) {
            $update_data['appointment_time'] = $data['appointment_time'];
        }
        
        if (isset($data['payment_status'])) {
            $update_data['payment_status'] = $data['payment_status'];
        }
        
        if (isset($data['payment_method'])) {
            $update_data['payment_method'] = $data['payment_method'];
        }
        
        if (isset($data['amount_paid'])) {
            $update_data['amount_paid'] = floatval($data['amount_paid']);
        }
        
        if (isset($data['notes'])) {
            $update_data['notes'] = $data['notes'];
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => $id)
        );
        
        if ($result !== false) {
            // Enviar notificación de actualización
            $this->send_update_notification($id);
            return true;
        }
        
        return false;
    }
    
    /**
     * Obtener una cita por ID
     */
    public function get_appointment($id) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT a.*, p.user_id, u.display_name as professional_name, s.service_name, s.price
            FROM {$this->table_name} a
            LEFT JOIN {$wpdb->prefix}mas_professionals p ON a.professional_id = p.id
            LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
            LEFT JOIN {$wpdb->prefix}mas_services s ON a.service_id = s.id
            WHERE a.id = %d",
            $id
        );
        
        return $wpdb->get_row($query);
    }
    
    /**
     * Obtener todas las citas con filtros
     */
    public function get_appointments($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => '',
            'date_from' => '',
            'date_to' => '',
            'professional_id' => '',
            'service_id' => '',
            'orderby' => 'appointment_date',
            'order' => 'ASC',
            'limit' => 50,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        
        if (!empty($args['status'])) {
            $where[] = $wpdb->prepare('a.status = %s', $args['status']);
        }
        
        if (!empty($args['date_from'])) {
            $where[] = $wpdb->prepare('a.appointment_date >= %s', $args['date_from']);
        }
        
        if (!empty($args['date_to'])) {
            $where[] = $wpdb->prepare('a.appointment_date <= %s', $args['date_to']);
        }
        
        if (!empty($args['professional_id'])) {
            $where[] = $wpdb->prepare('a.professional_id = %d', $args['professional_id']);
        }
        
        if (!empty($args['service_id'])) {
            $where[] = $wpdb->prepare('a.service_id = %d', $args['service_id']);
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT a.*, p.user_id, u.display_name as professional_name, s.service_name, s.price
                  FROM {$this->table_name} a
                  LEFT JOIN {$wpdb->prefix}mas_professionals p ON a.professional_id = p.id
                  LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
                  LEFT JOIN {$wpdb->prefix}mas_services s ON a.service_id = s.id
                  WHERE {$where_clause}
                  ORDER BY {$args['orderby']} {$args['order']}
                  LIMIT {$args['limit']} OFFSET {$args['offset']}";
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Verificar si un horario está disponible
     */
    public function is_slot_available($date, $time, $exclude_id = null) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name}
            WHERE appointment_date = %s
            AND appointment_time = %s
            AND status IN ('pending', 'scheduled')",
            $date,
            $time
        );
        
        if ($exclude_id) {
            $query .= $wpdb->prepare(' AND id != %d', $exclude_id);
        }
        
        $count = $wpdb->get_var($query);
        
        error_log('[MAS] Checking slot availability - Date: ' . $date . ', Time: ' . $time . ', Count: ' . $count);
        error_log('[MAS] SQL Query: ' . $query);
        
        return intval($count) === 0;
    }
    
    /**
     * Obtener horarios disponibles para una fecha
     */
    public function get_available_slots($date) {
        global $wpdb;
        
        $blocked_dates_table = $wpdb->prefix . 'mas_blocked_dates';
        $is_blocked = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $blocked_dates_table 
            WHERE blocked_date = %s AND blocks_appointments = 1",
            $date
        ));
        
        if ($is_blocked > 0) {
            error_log('[MAS] Fecha bloqueada para citas: ' . $date);
            return array();
        }
        
        $settings = get_option('mas_settings');
        
        $day_of_week = date('N', strtotime($date)); // 1 (Monday) to 7 (Sunday)
        
        // Check if schedule_by_day exists, otherwise use global settings
        if (isset($settings['schedule_by_day']) && is_array($settings['schedule_by_day'])) {
            // Check if this day is enabled
            if (!isset($settings['schedule_by_day'][$day_of_week]) || 
                !isset($settings['schedule_by_day'][$day_of_week]['enabled']) || 
                !$settings['schedule_by_day'][$day_of_week]['enabled']) {
                error_log('[MAS] Día no habilitado: ' . $day_of_week);
                return array();
            }
            
            // Get day-specific schedule
            $start_time = $settings['schedule_by_day'][$day_of_week]['start_time'];
            $end_time = $settings['schedule_by_day'][$day_of_week]['end_time'];
        } else {
            // Fallback to global settings for backwards compatibility
            $start_time = isset($settings['start_time']) ? $settings['start_time'] : '09:00';
            $end_time = isset($settings['end_time']) ? $settings['end_time'] : '18:00';
            $working_days = isset($settings['working_days']) ? $settings['working_days'] : array('1', '2', '3', '4', '5');
            
            // Check if day is in working days
            if (!in_array($day_of_week, $working_days)) {
                return array();
            }
        }
        
        $slot_duration = isset($settings['slot_duration']) ? $settings['slot_duration'] : 60;
        $min_booking_notice = isset($settings['min_booking_notice']) ? intval($settings['min_booking_notice']) : 0;
        
        $slots = array();
        $current_time = strtotime($start_time);
        $end_timestamp = strtotime($end_time);
        
        $now = current_time('timestamp');
        $min_booking_timestamp = strtotime("+{$min_booking_notice} hours", $now);
        
        while ($current_time < $end_timestamp) {
            $time_slot = date('H:i', $current_time);
            
            $slot_datetime = strtotime($date . ' ' . $time_slot);
            
            $meets_min_notice = ($slot_datetime >= $min_booking_timestamp);
            
            if ($meets_min_notice && $this->is_slot_available($date, $time_slot)) {
                $slots[] = array(
                    'time' => $time_slot,
                    'formatted' => date('H:i', $current_time),
                    'available' => true
                );
            }
            
            $current_time = strtotime("+{$slot_duration} minutes", $current_time);
        }
        
        return $slots;
    }
    
    /**
     * Eliminar una cita
     */
    public function delete_appointment($id) {
        global $wpdb;
        return $wpdb->delete($this->table_name, array('id' => $id));
    }
    
    /**
     * Enviar notificación de nueva cita
     */
    private function send_appointment_notification($appointment_id) {
        $settings = get_option('mas_settings');
        
        if (!isset($settings['enable_notifications']) || !$settings['enable_notifications']) {
            return;
        }
        
        MAS_Notifications::send_appointment_confirmation($appointment_id);
        MAS_Notifications::send_admin_notification($appointment_id);
    }
    
    /**
     * Enviar notificación de actualización de cita
     */
    private function send_update_notification($appointment_id) {
        $appointment = $this->get_appointment($appointment_id);
        $settings = get_option('mas_settings');
        
        if (!isset($settings['enable_notifications']) || !$settings['enable_notifications']) {
            return;
        }
        
        if ($appointment->status == 'scheduled' && $appointment->professional_name) {
            MAS_Notifications::send_status_change_notification($appointment_id, 'confirmed');
        } elseif ($appointment->status == 'cancelled') {
            MAS_Notifications::send_status_change_notification($appointment_id, 'cancelled');
        } elseif ($appointment->status == 'completed') {
            MAS_Notifications::send_status_change_notification($appointment_id, 'completed');
        }
    }
    
    /**
     * Obtener estadísticas de citas
     */
    public function get_statistics($date_from = null, $date_to = null) {
        global $wpdb;
        
        $where = array('1=1');
        
        if ($date_from) {
            $where[] = $wpdb->prepare('appointment_date >= %s', $date_from);
        }
        
        if ($date_to) {
            $where[] = $wpdb->prepare('appointment_date <= %s', $date_to);
        }
        
        $where_clause = implode(' AND ', $where);
        
        $stats = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}"),
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause} AND status = 'pending'"),
            'scheduled' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause} AND status = 'scheduled'"),
            'cancelled' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause} AND status = 'cancelled'"),
            'completed' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause} AND status = 'completed'")
        );
        
        return $stats;
    }
}
