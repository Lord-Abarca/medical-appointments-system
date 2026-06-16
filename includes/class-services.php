<?php
/**
 * Clase para gestionar servicios
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAS_Services {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'mas_services';
    }
    
    /**
     * Crear un nuevo servicio
     */
    public function create_service($data) {
        global $wpdb;
        
        error_log('[MAS] Intentando crear servicio: ' . $data['service_name']);
        
        $insert_data = array(
            'service_name' => $data['service_name'],
            'description' => isset($data['description']) ? $data['description'] : '',
            'duration' => isset($data['duration']) ? intval($data['duration']) : 60,
            'price' => floatval($data['price']),
            'status' => 'active'
        );
        
        $result = $wpdb->insert($this->table_name, $insert_data);
        
        if ($result) {
            $service_id = $wpdb->insert_id;
            error_log('[MAS] Servicio creado exitosamente con ID: ' . $service_id);
            return $service_id;
        }
        
        error_log('[MAS] Error al insertar servicio en la base de datos: ' . $wpdb->last_error);
        return false;
    }
    
    /**
     * Actualizar un servicio
     */
    public function update_service($id, $data) {
        global $wpdb;
        
        $update_data = array();
        
        if (isset($data['service_name'])) {
            $update_data['service_name'] = $data['service_name'];
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = $data['description'];
        }
        
        if (isset($data['duration'])) {
            $update_data['duration'] = intval($data['duration']);
        }
        
        if (isset($data['price'])) {
            $update_data['price'] = floatval($data['price']);
        }
        
        if (isset($data['status'])) {
            $update_data['status'] = $data['status'];
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
     * Obtener un servicio por ID
     */
    public function get_service($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Obtener todos los servicios
     */
    public function get_services($status = 'active') {
        global $wpdb;
        
        if ($status) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE status = %s ORDER BY service_name ASC",
                $status
            ));
        }
        
        return $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY service_name ASC");
    }
    
    /**
     * Eliminar un servicio
     */
    public function delete_service($id) {
        global $wpdb;
        
        // Check if service is being used by professionals or appointments
        $prof_services_table = $wpdb->prefix . 'mas_professional_services';
        $appointments_table = $wpdb->prefix . 'mas_appointments';
        
        $prof_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prof_services_table} WHERE service_id = %d",
            $id
        ));
        
        $appt_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$appointments_table} WHERE service_id = %d",
            $id
        ));
        
        if ($prof_count > 0 || $appt_count > 0) {
            // Don't delete, just deactivate
            return $this->update_service($id, array('status' => 'inactive'));
        }
        
        return $wpdb->delete($this->table_name, array('id' => $id));
    }
    
    /**
     * Obtener servicios de un profesional
     */
    public function get_professional_services($professional_id) {
        global $wpdb;
        $prof_services_table = $wpdb->prefix . 'mas_professional_services';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.* FROM {$this->table_name} s
            INNER JOIN {$prof_services_table} ps ON s.id = ps.service_id
            WHERE ps.professional_id = %d AND s.status = 'active'
            ORDER BY s.service_name ASC",
            $professional_id
        ));
    }
}
