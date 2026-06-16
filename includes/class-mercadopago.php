<?php
/**
 * Clase para gestionar integración con MercadoPago Checkout
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAS_MercadoPago {
    
    private $access_token;
    private $public_key;
    
    public function __construct() {
       $this->access_token = get_option('mas_mp_access_token', '');
       $this->public_key = get_option('mas_mp_public_key', '');
        if (empty($this->access_token) || empty($this->public_key)) {
            error_log('[MAS MP] ADVERTENCIA: Credenciales de MercadoPago no configuradas.');
        }
    }
    
    /**
     * Obtener la Public Key
     */
    public function get_public_key() {
        return $this->public_key;
    }
    
    /**
     * Crear preferencia de pago para arriendos (Checkout Bricks)
     */
    public function create_rental_preference($rental_data) {
        error_log('[MAS MP BRICKS] ========== INICIANDO CREACION DE PREFERENCIA ==========');
        error_log('[MAS MP BRICKS] Datos de entrada: ' . json_encode($rental_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        $url = 'https://api.mercadopago.com/checkout/preferences';
        
        $items = array();
        foreach ($rental_data['rentals'] as $rental) {
            $items[] = array(
                'id' => 'rental_' . uniqid(),
                'title' => sprintf(
                    'Arriendo Box %s - %s %s',
                    $rental['box_name'],
                    $rental['rental_date'],
                    $rental['start_time']
                ),
                'description' => sprintf('Arriendo de box para el %s a las %s', $rental['rental_date'], $rental['start_time']),
                'quantity' => 1,
                'unit_price' => floatval($rental['price']),
                'currency_id' => 'CLP'
            );
        }
        
        $site_url = get_site_url();
        
        $preference_data = array(
            'items' => $items,
            'notification_url' => $site_url . '/mercadopago-webhook.php',
            'back_urls' => array(
                'success' => $site_url . '/pago-exitoso/',
                'failure' => $site_url . '/pago-fallido/',
                'pending' => $site_url . '/pago-pendiente/'
            ),
            'auto_return' => 'approved',
            'expires' => false,
            'external_reference' => $rental_data['reference_id'],
            'payer' => array(
                'name' => $rental_data['professional_name'],
                'email' => $rental_data['professional_email'],
            ),
            'payment_methods' => array(
                'installments' => 1
            ),
            'statement_descriptor' => 'ARRIENDO BOX'
        );
        
        error_log('[MAS MP BRICKS] URL de notificación: ' . $preference_data['notification_url']);
        error_log('[MAS MP BRICKS] External reference: ' . $preference_data['external_reference']);
        error_log('[MAS MP BRICKS] Datos enviados: ' . json_encode($preference_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json',
                'X-Idempotency-Key' => $rental_data['reference_id']
            ),
            'body' => json_encode($preference_data, JSON_UNESCAPED_SLASHES),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('[MAS MP BRICKS] ERROR: ' . $response->get_error_message());
            error_log('[MAS MP BRICKS] ========== FIN (ERROR) ==========');
            return false;
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        error_log('[MAS MP BRICKS] Código HTTP: ' . $http_code);
        error_log('[MAS MP BRICKS] Respuesta completa: ' . json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        $init_point = isset($body['init_point']) ? $body['init_point'] : (isset($body['sandbox_init_point']) ? $body['sandbox_init_point'] : null);
        
        if (isset($body['id']) && $init_point) {
            error_log('[MAS MP BRICKS] ✓ Preferencia creada: ' . $body['id']);
            error_log('[MAS MP BRICKS] ✓ External Reference: ' . ($body['external_reference'] ?? 'N/A'));
            error_log('[MAS MP BRICKS] ✓ Notification URL configurada: ' . ($body['notification_url'] ?? 'N/A'));
            error_log('[MAS MP BRICKS] ✓ Init Point: ' . $init_point);
            error_log('[MAS MP BRICKS] ========== FIN (EXITOSO) ==========');
            
            return array(
                'preference_id' => $body['id'],
                'init_point' => $init_point,
                'external_reference' => $body['external_reference']
            );
        }
        
        error_log('[MAS MP BRICKS] ERROR: Respuesta inválida de MercadoPago');
        if (isset($body['message'])) {
            error_log('[MAS MP BRICKS] Mensaje: ' . $body['message']);
        }
        if (isset($body['cause'])) {
            error_log('[MAS MP BRICKS] Causa: ' . json_encode($body['cause']));
        }
        error_log('[MAS MP BRICKS] ========== FIN (ERROR) ==========');
        return false;
    }
    
    /**
     * Crear preferencia de pago para citas médicas
     */
    public function create_appointment_preference($appointment_data) {
        error_log('[MAS MP APPOINTMENT] ========== INICIANDO CREACION DE PREFERENCIA DE CITA ==========');
        error_log('[MAS MP APPOINTMENT] Datos de entrada: ' . json_encode($appointment_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        $url = 'https://api.mercadopago.com/checkout/preferences';
        
        $items = array(
            array(
                'id' => 'appointment_' . $appointment_data['appointment_id'],
                'title' => sprintf(
                    'Cita: %s - %s con %s',
                    $appointment_data['service_name'],
                    date('d/m/Y', strtotime($appointment_data['appointment_date'])),
                    $appointment_data['professional_name']
                ),
                'quantity' => 1,
                'unit_price' => floatval($appointment_data['service_price']),
                'currency_id' => 'CLP'
            )
        );
        
        $site_url = get_site_url();
        $external_ref = $appointment_data['external_reference'];
        
        error_log('[MAS MP APPOINTMENT] External reference a usar: ' . $external_ref);
        
        $preference_data = array(
            'items' => $items,
            'back_urls' => array(
                'success' => $site_url . '/pago-exitoso/',
                'failure' => $site_url . '/pago-fallido/',
                'pending' => $site_url . '/pago-pendiente/'
            ),
            'auto_return' => 'approved',
            'expires' => false,
            'external_reference' => $external_ref,
            'notification_url' => $site_url . '/mercadopago-webhook.php',
            'payer' => array(
                'name' => $appointment_data['patient_name'],
                'email' => $appointment_data['patient_email']
            ),
            'statement_descriptor' => 'CITA MEDICA'
        );
        
        error_log('[MAS MP APPOINTMENT] URL de notificación: ' . $preference_data['notification_url']);
        error_log('[MAS MP APPOINTMENT] Datos enviados a MP: ' . json_encode($preference_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json',
                'X-Idempotency-Key' => $external_ref
            ),
            'body' => json_encode($preference_data, JSON_UNESCAPED_SLASHES),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('[MAS MP APPOINTMENT] ERROR: ' . $response->get_error_message());
            error_log('[MAS MP APPOINTMENT] ========== FIN (ERROR WP) ==========');
            return false;
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        error_log('[MAS MP APPOINTMENT] Código HTTP: ' . $http_code);
        error_log('[MAS MP APPOINTMENT] Respuesta completa: ' . json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        $init_point = isset($body['init_point']) ? $body['init_point'] : (isset($body['sandbox_init_point']) ? $body['sandbox_init_point'] : null);
        
        if (isset($body['id']) && $init_point) {
            error_log('[MAS MP APPOINTMENT] ✓ Preferencia creada: ' . $body['id']);
            error_log('[MAS MP APPOINTMENT] ✓ External Reference enviado: ' . $external_ref);
            error_log('[MAS MP APPOINTMENT] ✓ External Reference en respuesta: ' . ($body['external_reference'] ?? 'N/A'));
            error_log('[MAS MP APPOINTMENT] ✓ Init Point: ' . $init_point);
            error_log('[MAS MP APPOINTMENT] ========== FIN (EXITOSO) ==========');
            
            return array(
                'preference_id' => $body['id'],
                'init_point' => $init_point,
                'external_reference' => $external_ref
            );
        }
        
        error_log('[MAS MP APPOINTMENT] ERROR: Respuesta inválida de MercadoPago');
        if (isset($body['message'])) {
            error_log('[MAS MP APPOINTMENT] Mensaje: ' . $body['message']);
        }
        if (isset($body['cause'])) {
            error_log('[MAS MP APPOINTMENT] Causa: ' . json_encode($body['cause']));
        }
        error_log('[MAS MP APPOINTMENT] ========== FIN (ERROR) ==========');
        return false;
    }
    
    /**
     * Obtener información de un pago
     */
    public function get_payment_info($payment_id) {
        $url = 'https://api.mercadopago.com/v1/payments/' . $payment_id;
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('[MAS MP] Error al obtener pago: ' . $response->get_error_message());
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        return $body;
    }
    
    /**
     * Obtener el Access Token (para uso interno del webhook)
     */
    public function get_access_token() {
        return $this->access_token;
    }
}
