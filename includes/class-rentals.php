<?php
/**
 * Clase para gestionar arriendos de boxes
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAS_Rentals {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'mas_box_rentals';
    }
    
    /**
     * Crear un nuevo arriendo
     */
    public function create_rental($data) {
        global $wpdb;
        
        $mas_boxes = new MAS_Boxes();
        if (!$mas_boxes->is_box_available($data['box_id'], $data['rental_date'], $data['start_time'], $data['end_time'])) {
            error_log('[MAS] Box no disponible: Box ID ' . $data['box_id'] . ', Fecha: ' . $data['rental_date'] . ', Hora: ' . $data['start_time'] . '-' . $data['end_time']);
            return false;
        }
        
        // Calcular horas y precio total
        $start = strtotime($data['start_time']);
        $end = strtotime($data['end_time']);
        $hours = ($end - $start) / 3600;
        
        $box = $mas_boxes->get_box($data['box_id']);
        if (!$box) {
            error_log('[MAS] Box no encontrado: ' . $data['box_id']);
            return false;
        }
        
        $total_price = $hours * $box->price_per_hour;
        
        $insert_data = array(
            'box_id' => $data['box_id'],
            'professional_id' => $data['professional_id'],
            'rental_date' => $data['rental_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'total_hours' => $hours,
            'total_price' => $total_price,
            'status' => isset($data['status']) ? $data['status'] : 'active',
            'payment_status' => isset($data['payment_status']) ? $data['payment_status'] : 'pending',
            'payment_method' => isset($data['payment_method']) ? $data['payment_method'] : null,
            'reference_id' => isset($data['reference_id']) ? $data['reference_id'] : null,
            'external_reference' => isset($data['external_reference']) ? $data['external_reference'] : null,
            'preference_id' => isset($data['preference_id']) ? $data['preference_id'] : null,
            'payment_url' => isset($data['payment_url']) ? $data['payment_url'] : null,
            'notes' => isset($data['notes']) ? $data['notes'] : ''
        );
        
        $result = $wpdb->insert($this->table_name, $insert_data);
        
        if ($result) {
            error_log('[MAS] Arriendo creado exitosamente: ID ' . $wpdb->insert_id);
            return $wpdb->insert_id;
        }
        
        error_log('[MAS] Error al crear arriendo: ' . $wpdb->last_error);
        return false;
    }
    
    /**
     * Actualizar un arriendo
     */
    public function update_rental($id, $data) {
        global $wpdb;
        
        $update_data = array();
        
        if (isset($data['box_id'])) {
            $update_data['box_id'] = $data['box_id'];
        }
        
        if (isset($data['professional_id'])) {
            $update_data['professional_id'] = $data['professional_id'];
        }
        
        if (isset($data['status'])) {
            $update_data['status'] = $data['status'];
        }
        
        if (isset($data['payment_status'])) {
            $update_data['payment_status'] = $data['payment_status'];
        }
        
        if (isset($data['payment_method'])) {
            $update_data['payment_method'] = $data['payment_method'];
        }
        
        if (isset($data['reference_id'])) {
            $update_data['reference_id'] = $data['reference_id'];
        }
        
        if (isset($data['external_reference'])) {
            $update_data['external_reference'] = $data['external_reference'];
        }
        
        if (isset($data['preference_id'])) {
            $update_data['preference_id'] = $data['preference_id'];
        }
        
        if (isset($data['mp_payment_id'])) {
            $update_data['mp_payment_id'] = $data['mp_payment_id'];
        }
        
        if (isset($data['payment_url'])) {
            $update_data['payment_url'] = $data['payment_url'];
        }
        
        if (isset($data['notes'])) {
            $update_data['notes'] = $data['notes'];
        }
        
        // Si se cambia fecha, horario o box, verificar disponibilidad
        if (isset($data['rental_date']) || isset($data['start_time']) || isset($data['end_time']) || isset($data['box_id'])) {
            $rental = $this->get_rental($id);
            
            $box_id = isset($data['box_id']) ? $data['box_id'] : $rental->box_id;
            $rental_date = isset($data['rental_date']) ? $data['rental_date'] : $rental->rental_date;
            $start_time = isset($data['start_time']) ? $data['start_time'] : $rental->start_time;
            $end_time = isset($data['end_time']) ? $data['end_time'] : $rental->end_time;
            
            // Verificar disponibilidad del box (nuevo o existente)
            $mas_boxes = new MAS_Boxes();
            if (!$mas_boxes->is_box_available($box_id, $rental_date, $start_time, $end_time, $id)) {
                error_log('[MAS] Box no disponible al actualizar arriendo: Box ID ' . $box_id . ', Fecha: ' . $rental_date . ', Hora: ' . $start_time . '-' . $end_time);
                return false;
            }
            
            $start = strtotime($start_time);
            $end = strtotime($end_time);
            $hours = ($end - $start) / 3600;
            
            $box = $mas_boxes->get_box($rental->box_id);
            if (!$box) {
                error_log('[MAS] Box no encontrado al actualizar arriendo: ' . $rental->box_id);
                return false;
            }
            
            $total_price = $hours * $box->price_per_hour;
            
            $update_data['rental_date'] = $rental_date;
            $update_data['start_time'] = $start_time;
            $update_data['end_time'] = $end_time;
            $update_data['total_hours'] = $hours;
            $update_data['total_price'] = $total_price;
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
            error_log('[MAS] Arriendo actualizado exitosamente: ID ' . $id);
            return true;
        }
        
        error_log('[MAS] Error al actualizar arriendo: ' . $wpdb->last_error);
        return false;
    }
    
    /**
     * Obtener un arriendo por ID
     */
    public function get_rental($id) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT r.*, b.box_name, b.box_number, p.user_id, u.display_name as professional_name
            FROM {$this->table_name} r
            LEFT JOIN {$wpdb->prefix}mas_boxes b ON r.box_id = b.id
            LEFT JOIN {$wpdb->prefix}mas_professionals p ON r.professional_id = p.id
            LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
            WHERE r.id = %d",
            $id
        );
        
        return $wpdb->get_row($query);
    }
    
    /**
     * Obtener arriendos con filtros
     */
    public function get_rentals($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'box_id' => '',
            'professional_id' => '',
            'date_from' => '',
            'date_to' => '',
            'status' => 'active',
            'hide_monthly' => false,
            'orderby' => 'rental_date',
            'order' => 'DESC',
            'limit' => 50,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        
        if (!empty($args['box_id'])) {
            $where[] = $wpdb->prepare('r.box_id = %d', $args['box_id']);
        }
        
        if (!empty($args['professional_id'])) {
            $where[] = $wpdb->prepare('r.professional_id = %d', $args['professional_id']);
        }
        
        if (!empty($args['date_from'])) {
            $where[] = $wpdb->prepare('r.rental_date >= %s', $args['date_from']);
        }
        
        if (!empty($args['date_to'])) {
            $where[] = $wpdb->prepare('r.rental_date <= %s', $args['date_to']);
        }
        
        if (!empty($args['status'])) {
            $where[] = $wpdb->prepare('r.status = %s', $args['status']);
        }
        
        if ($args['hide_monthly']) {
            $where[] = '(r.monthly_rental_id IS NULL OR r.monthly_rental_id = 0)';
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT r.*, b.box_name, b.box_number, p.user_id, u.display_name as professional_name
                  FROM {$this->table_name} r
                  LEFT JOIN {$wpdb->prefix}mas_boxes b ON r.box_id = b.id
                  LEFT JOIN {$wpdb->prefix}mas_professionals p ON r.professional_id = p.id
                  LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
                  WHERE {$where_clause}
                  ORDER BY {$args['orderby']} {$args['order']}
                  LIMIT {$args['limit']} OFFSET {$args['offset']}";
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Eliminar un arriendo
     */
    public function delete_rental($id) {
        global $wpdb;
        $result = $wpdb->delete($this->table_name, array('id' => $id));
        
        if ($result) {
            error_log('[MAS] Arriendo eliminado exitosamente: ID ' . $id);
            return true;
        }
        
        error_log('[MAS] Error al eliminar arriendo: ' . $wpdb->last_error);
        return false;
    }
    
    /**
     * Obtener estadísticas de arriendos
     */
    public function get_statistics($date_from = null, $date_to = null) {
        global $wpdb;
        
        $where = array("status = 'active'");
        
        if ($date_from) {
            $where[] = $wpdb->prepare('rental_date >= %s', $date_from);
        }
        
        if ($date_to) {
            $where[] = $wpdb->prepare('rental_date <= %s', $date_to);
        }
        
        $where_clause = implode(' AND ', $where);
        
        $stats = array(
            'total_rentals' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}"),
            'total_hours' => $wpdb->get_var("SELECT SUM(total_hours) FROM {$this->table_name} WHERE {$where_clause}"),
            'total_revenue' => $wpdb->get_var("SELECT SUM(total_price) FROM {$this->table_name} WHERE {$where_clause}")
        );
        
        return $stats;
    }
}
