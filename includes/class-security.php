<?php
/**
 * Security and Validation Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAS_Security {
    
    /**
     * Validate RUT chileno
     */
    public static function validate_rut($rut) {
        // Remove dots and hyphens
        $rut = preg_replace('/[^k0-9]/i', '', $rut);
        $rut = strtoupper($rut);
        
        if (strlen($rut) < 2) {
            return false;
        }
        
        $dv = substr($rut, -1);
        $number = substr($rut, 0, -1);
        
        // Calculate verification digit
        $sum = 0;
        $multiplier = 2;
        
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $sum += $number[$i] * $multiplier;
            $multiplier = $multiplier == 7 ? 2 : $multiplier + 1;
        }
        
        $calculated_dv = 11 - ($sum % 11);
        
        if ($calculated_dv == 11) {
            $calculated_dv = '0';
        } elseif ($calculated_dv == 10) {
            $calculated_dv = 'K';
        } else {
            $calculated_dv = (string)$calculated_dv;
        }
        
        return $dv === $calculated_dv;
    }
    
    /**
     * Validate email format
     */
    public static function validate_email($email) {
        return is_email($email);
    }
    
    /**
     * Validate phone number (Chilean format)
     */
    public static function validate_phone($phone) {
        // Remove spaces and special characters
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Chilean phone numbers: 9 digits for mobile, 8 for landline
        if (strlen($phone) >= 8 && strlen($phone) <= 12) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Sanitize appointment data
     */
    public static function sanitize_appointment_data($data) {
        return array(
            'patient_name' => sanitize_text_field($data['patient_name']),
            'patient_email' => sanitize_email($data['patient_email']),
            'patient_phone' => sanitize_text_field($data['patient_phone']),
            'patient_rut' => sanitize_text_field($data['patient_rut']),
            'appointment_date' => sanitize_text_field($data['appointment_date']),
            'appointment_time' => sanitize_text_field($data['appointment_time']),
            'notes' => sanitize_textarea_field($data['notes'] ?? '')
        );
    }
    
    /**
     * Validate date format and range
     */
    public static function validate_date($date, $min_days_ahead = 0, $max_days_ahead = 365) {
        $date_obj = DateTime::createFromFormat('Y-m-d', $date);
        
        if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
            return false;
        }
        
        $today = new DateTime();
        $min_date = (clone $today)->modify("+{$min_days_ahead} days");
        $max_date = (clone $today)->modify("+{$max_days_ahead} days");
        
        return $date_obj >= $min_date && $date_obj <= $max_date;
    }
    
    /**
     * Validate time format
     */
    public static function validate_time($time) {
        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time);
    }
    
    /**
     * Check if user has permission
     */
    public static function check_admin_permission() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tiene permisos para acceder a esta página.', 'medical-appointments'));
        }
    }
    
    /**
     * Verify nonce for AJAX requests
     */
    public static function verify_ajax_nonce($action = 'mas_admin_nonce') {
        if (!check_ajax_referer($action, 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Sesión expirada. Por favor recargue la página.', 'medical-appointments')
            ));
            wp_die();
        }
    }
    
    /**
     * Prevent SQL injection in custom queries
     */
    public static function prepare_query($query, $params) {
        global $wpdb;
        return $wpdb->prepare($query, $params);
    }
    
    /**
     * Rate limiting for appointments
     */
    public static function check_rate_limit($email, $limit = 5, $period = 3600) {
        $transient_key = 'mas_rate_limit_' . md5($email);
        $attempts = get_transient($transient_key);
        
        if ($attempts === false) {
            set_transient($transient_key, 1, $period);
            return true;
        }
        
        if ($attempts >= $limit) {
            return false;
        }
        
        set_transient($transient_key, $attempts + 1, $period);
        return true;
    }
    
    /**
     * Sanitize file uploads
     */
    public static function validate_file_upload($file) {
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'application/pdf');
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowed_types)) {
            return array('error' => __('Tipo de archivo no permitido.', 'medical-appointments'));
        }
        
        if ($file['size'] > $max_size) {
            return array('error' => __('El archivo es demasiado grande. Máximo 5MB.', 'medical-appointments'));
        }
        
        return array('success' => true);
    }
    
    /**
     * Log security events
     */
    public static function log_security_event($event_type, $details = array()) {
        if (!get_option('mas_enable_security_logs', true)) {
            return;
        }
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'event_type' => $event_type,
            'user_id' => get_current_user_id(),
            'ip_address' => self::get_client_ip(),
            'details' => $details
        );
        
        $logs = get_option('mas_security_logs', array());
        array_unshift($logs, $log_entry);
        
        // Keep only last 100 entries
        $logs = array_slice($logs, 0, 100);
        
        update_option('mas_security_logs', $logs);
    }
    
    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip);
    }
    
    /**
     * Encrypt sensitive data
     */
    public static function encrypt_data($data) {
        if (!function_exists('openssl_encrypt')) {
            return $data;
        }
        
        $key = self::get_encryption_key();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        
        return base64_encode($encrypted . '::' . $iv);
    }
    
    /**
     * Decrypt sensitive data
     */
    public static function decrypt_data($data) {
        if (!function_exists('openssl_decrypt')) {
            return $data;
        }
        
        $key = self::get_encryption_key();
        list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
        
        return openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, $iv);
    }
    
    /**
     * Get or create encryption key
     */
    private static function get_encryption_key() {
        $key = get_option('mas_encryption_key');
        
        if (!$key) {
            $key = wp_generate_password(32, true, true);
            update_option('mas_encryption_key', $key);
        }
        
        return $key;
    }
}
