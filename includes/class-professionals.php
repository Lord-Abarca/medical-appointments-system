<?php
/**
 * Clase para gestionar profesionales
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAS_Professionals {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'mas_professionals';
    }
    
    /**
     * Crear un nuevo profesional
     */
    public function create_professional($data) {
        global $wpdb;
        
        error_log('[MAS] Intentando crear profesional con username: ' . $data['username']);
        
        $user_data = array(
            'user_login' => $data['username'],
            'user_email' => $data['email'],
            'user_pass' => $data['password'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'display_name' => $data['first_name'] . ' ' . $data['last_name'],
            'role' => 'contributor'
        );
        
        $user_id = wp_insert_user($user_data);
        
        if (is_wp_error($user_id)) {
            error_log('[MAS] Error al crear usuario de WordPress: ' . $user_id->get_error_message());
            return false;
        }
        
        error_log('[MAS] Usuario de WordPress creado con ID: ' . $user_id);
        
        // Crear registro de profesional
        $insert_data = array(
            'user_id' => $user_id,
            'run' => isset($data['run']) ? $data['run'] : '',
            'specialty' => isset($data['specialty']) ? $data['specialty'] : '',
            'license_number' => isset($data['license_number']) ? $data['license_number'] : '',
            'phone' => isset($data['phone']) ? $data['phone'] : '',
            'bio' => isset($data['bio']) ? $data['bio'] : '',
            'status' => 'active'
        );
        
        if (isset($data['image_url']) && !empty($data['image_url'])) {
            $insert_data['image_url'] = $data['image_url'];
        }
        
        $result = $wpdb->insert($this->table_name, $insert_data);
        
        if ($result) {
            $professional_id = $wpdb->insert_id;
            
            if (isset($data['services']) && is_array($data['services'])) {
                $this->update_professional_services($professional_id, $data['services']);
            }
            
            error_log('[MAS] Profesional creado exitosamente con ID: ' . $professional_id);
            return $professional_id;
        }
        
        error_log('[MAS] Error al insertar profesional en la base de datos: ' . $wpdb->last_error);
        return false;
    }
    
    /**
     * Actualizar un profesional
     */
    public function update_professional($id, $data) {
        global $wpdb;
        
        $professional = $this->get_professional($id);
        
        if (!$professional) {
            return false;
        }
        
        // Actualizar usuario de WordPress si es necesario
        if (isset($data['email']) || isset($data['first_name']) || isset($data['last_name']) || isset($data['username']) || isset($data['password'])) {
            $user_data = array('ID' => $professional->user_id);
            
            if (isset($data['email'])) {
                $user_data['user_email'] = $data['email'];
            }
            
            if (isset($data['username']) && !empty($data['username'])) {
                $user_data['user_login'] = $data['username'];
            }
            
            if (isset($data['password']) && !empty($data['password'])) {
                $user_data['user_pass'] = $data['password'];
            }
            
            if (isset($data['first_name'])) {
                $user_data['first_name'] = $data['first_name'];
            }
            
            if (isset($data['last_name'])) {
                $user_data['last_name'] = $data['last_name'];
            }
            
            if (isset($data['first_name']) || isset($data['last_name'])) {
                $first_name = isset($data['first_name']) ? $data['first_name'] : $professional->first_name;
                $last_name = isset($data['last_name']) ? $data['last_name'] : $professional->last_name;
                $user_data['display_name'] = $first_name . ' ' . $last_name;
            }
            
            wp_update_user($user_data);
        }
        
        // Actualizar datos del profesional
        $update_data = array();
        
        if (isset($data['run'])) {
            $update_data['run'] = $data['run'];
        }
        
        if (isset($data['specialty'])) {
            $update_data['specialty'] = $data['specialty'];
        }
        
        if (isset($data['license_number'])) {
            $update_data['license_number'] = $data['license_number'];
        }
        
        if (isset($data['phone'])) {
            $update_data['phone'] = $data['phone'];
        }
        
        if (isset($data['bio'])) {
            $update_data['bio'] = $data['bio'];
        }
        
        if (isset($data['status'])) {
            $update_data['status'] = $data['status'];
        }
        
        if (isset($data['image_url'])) {
            $update_data['image_url'] = $data['image_url'];
        }
        
        if (!empty($update_data)) {
            $wpdb->update(
                $this->table_name,
                $update_data,
                array('id' => $id)
            );
        }
        
        if (isset($data['services']) && is_array($data['services'])) {
            $this->update_professional_services($id, $data['services']);
        }
        
        return true;
    }
    
    /**
     * Actualizar servicios de un profesional
     */
    public function update_professional_services($professional_id, $service_ids) {
        global $wpdb;
        $table_prof_services = $wpdb->prefix . 'mas_professional_services';
        
        // Eliminar servicios existentes
        $wpdb->delete($table_prof_services, array('professional_id' => $professional_id));
        
        // Insertar nuevos servicios
        foreach ($service_ids as $service_id) {
            $wpdb->insert($table_prof_services, array(
                'professional_id' => $professional_id,
                'service_id' => intval($service_id)
            ));
        }
        
        return true;
    }
    
    /**
     * Obtener servicios de un profesional
     */
    public function get_professional_services($professional_id) {
        global $wpdb;
        $table_prof_services = $wpdb->prefix . 'mas_professional_services';
        
        return $wpdb->get_col($wpdb->prepare(
            "SELECT service_id FROM $table_prof_services WHERE professional_id = %d",
            $professional_id
        ));
    }
    
    /**
     * Obtener un profesional por ID
     */
    public function get_professional($id) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT p.*, u.user_login, u.user_email, u.display_name,
                    um1.meta_value as first_name,
                    um2.meta_value as last_name
            FROM {$this->table_name} p
            LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
            LEFT JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = 'first_name'
            LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = 'last_name'
            WHERE p.id = %d",
            $id
        );
        
        return $wpdb->get_row($query);
    }
    
    /**
     * Obtener profesional por user_id
     */
    public function get_professional_by_user_id($user_id) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT p.*, u.user_login, u.user_email, u.display_name,
                    um1.meta_value as first_name,
                    um2.meta_value as last_name
            FROM {$this->table_name} p
            LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
            LEFT JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = 'first_name'
            LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = 'last_name'
            WHERE p.user_id = %d",
            $user_id
        );
        
        return $wpdb->get_row($query);
    }
    
    /**
     * Obtener todos los profesionales
     */
    public function get_professionals($status = 'active') {
        global $wpdb;
        
        $query = "SELECT p.*, u.user_login, u.user_email, u.display_name,
                         um1.meta_value as first_name,
                         um2.meta_value as last_name
                  FROM {$this->table_name} p
                  LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
                  LEFT JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = 'first_name'
                  LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = 'last_name'";
        
        if ($status) {
            $query .= $wpdb->prepare(" WHERE p.status = %s", $status);
        }
        
        $query .= " ORDER BY u.display_name ASC";
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Eliminar un profesional
     */
    public function delete_professional($id) {
        global $wpdb;
        
        $professional = $this->get_professional($id);
        
        if (!$professional) {
            return false;
        }
        
        // Solo verificar citas/arriendos con fecha futura o actual
        $today = current_time('Y-m-d');
        
        $has_future_appointments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}mas_appointments 
            WHERE professional_id = %d 
            AND status IN ('pending', 'scheduled') 
            AND appointment_date >= %s",
            $id,
            $today
        ));
        
        $has_future_rentals = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}mas_box_rentals 
            WHERE professional_id = %d 
            AND status IN ('pending', 'active') 
            AND rental_date >= %s",
            $id,
            $today
        ));
        
        if ($has_future_appointments > 0 || $has_future_rentals > 0) {
            error_log('[MAS] No se puede eliminar profesional con citas o arriendos futuros activos');
            return false;
        }
        
        // Eliminar servicios asociados
        $wpdb->delete($wpdb->prefix . 'mas_professional_services', array('professional_id' => $id));
        
        // Eliminar usuario de WordPress
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        wp_delete_user($professional->user_id);
        
        // Eliminar registro de profesional
        return $wpdb->delete($this->table_name, array('id' => $id));
    }
    
    /**
     * Obtener estadísticas de un profesional
     */
    public function get_professional_stats($professional_id, $date_from = null, $date_to = null) {
        global $wpdb;
        
        $where_date = '';
        if ($date_from && $date_to) {
            $where_date = $wpdb->prepare(' AND appointment_date BETWEEN %s AND %s', $date_from, $date_to);
        }
        
        $appointments_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}mas_appointments 
            WHERE professional_id = %d" . $where_date,
            $professional_id
        ));
        
        $where_rental_date = '';
        if ($date_from && $date_to) {
            $where_rental_date = $wpdb->prepare(' AND rental_date BETWEEN %s AND %s', $date_from, $date_to);
        }
        
        $rentals_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}mas_box_rentals 
            WHERE professional_id = %d" . $where_rental_date,
            $professional_id
        ));
        
        return array(
            'appointments' => $appointments_count,
            'rentals' => $rentals_count
        );
    }
    
    /**
     * Registrar rol personalizado para profesionales
     */
    public static function register_professional_role() {
        add_role(
            'mas_professional',
            __('Profesional de Salud', 'medical-appointments'),
            array(
                'read' => true,
                'edit_posts' => false,
                'delete_posts' => false
            )
        );
    }
}

// Registrar rol al activar el plugin
add_action('init', array('MAS_Professionals', 'register_professional_role'));
