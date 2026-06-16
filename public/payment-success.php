<?php
/**
 * Template para página de pago exitoso
 */

if (!defined('ABSPATH')) {
    exit;
} 

// Obtener parámetros de MercadoPago
$collection_id = isset($_GET['collection_id']) ? sanitize_text_field($_GET['collection_id']) : '';
$collection_status = isset($_GET['collection_status']) ? sanitize_text_field($_GET['collection_status']) : '';
$payment_id = isset($_GET['payment_id']) ? sanitize_text_field($_GET['payment_id']) : '';
$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$external_reference = isset($_GET['external_reference']) ? sanitize_text_field($_GET['external_reference']) : '';
$payment_type = isset($_GET['payment_type']) ? sanitize_text_field($_GET['payment_type']) : '';
$preference_id = isset($_GET['preference_id']) ? sanitize_text_field($_GET['preference_id']) : '';

$payment_processed = false;
$processing_error = '';

if ($payment_id && $status === 'approved') {
    global $wpdb;
    
    error_log('[MAS SUCCESS PAGE] Procesando pago directamente: ' . $payment_id);
    
    // Obtener access token de MercadoPago
    $access_token = get_option('mas_mp_access_token');
    
    if (empty($access_token)) {
        $processing_error = 'No hay access token configurado';
        error_log('[MAS SUCCESS PAGE] Error: ' . $processing_error);
    } else {
        // Consultar información del pago en MercadoPago
        $url = "https://api.mercadopago.com/v1/payments/{$payment_id}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        error_log('[MAS SUCCESS PAGE] Respuesta MP API (HTTP ' . $http_code . ')');
        
        if ($http_code === 200) {
            $payment = json_decode($response, true);
            
            if ($payment && isset($payment['external_reference'])) {
                $external_ref = $payment['external_reference'];
                $payment_status = $payment['status'];
                $payment_method = isset($payment['payment_method_id']) ? $payment['payment_method_id'] : '';
                $amount = isset($payment['transaction_amount']) ? $payment['transaction_amount'] : 0;
                
                error_log('[MAS SUCCESS PAGE] External reference: ' . $external_ref);
                error_log('[MAS SUCCESS PAGE] Payment status: ' . $payment_status);
                
                // Verificar si ya fue procesado
                $already_processed = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}mas_box_rentals 
                    WHERE reference_id = %s AND mp_payment_id = %s",
                    $external_ref, $payment_id
                ));
                
                if ($already_processed > 0) {
                    $payment_processed = true;
                    error_log('[MAS SUCCESS PAGE] Pago ya procesado anteriormente');
                } elseif (strpos($external_ref, 'RENT') === 0) {
                    // Procesar arriendos
                    $rentals = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}mas_box_rentals WHERE reference_id = %s",
                        $external_ref
                    ));
                    
                    if ($rentals && count($rentals) > 0) {
                        error_log('[MAS SUCCESS PAGE] Actualizando ' . count($rentals) . ' arriendos');
                        
                        // Actualizar todos los arriendos
                        $updated_count = $wpdb->update(
                            $wpdb->prefix . 'mas_box_rentals',
                            array(
                                'status' => 'active',
                                'payment_status' => 'paid',
                                'mp_payment_id' => $payment_id,
                                'payment_method' => $payment_method
                            ),
                            array('reference_id' => $external_ref),
                            array('%s', '%s', '%s', '%s'),
                            array('%s')
                        );
                        
                        if ($updated_count > 0) {
                            $payment_processed = true;
                            error_log('[MAS SUCCESS PAGE] ' . $updated_count . ' arriendos actualizados exitosamente');
                            
                            // Enviar email de confirmación
                            $first_rental = $rentals[0];
                            $professional = $wpdb->get_row($wpdb->prepare(
                                "SELECT p.*, u.user_email, u.display_name 
                                FROM {$wpdb->prefix}mas_professionals p
                                LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
                                WHERE p.id = %d",
                                $first_rental->professional_id
                            ));
                            
                            if ($professional && $professional->user_email) {
                                $rentals_details = '';
                                foreach ($rentals as $rental) {
                                    $rentals_details .= "- Fecha: " . date('d/m/Y', strtotime($rental->rental_date)) . 
                                                      " | Hora: " . date('H:i', strtotime($rental->start_time)) . 
                                                      " - " . date('H:i', strtotime($rental->end_time)) . "\n";
                                }
                                
                                $subject = 'Confirmación de Arriendo de Box';
                                $message = "Hola {$professional->display_name},\n\n";
                                $message .= "Tu arriendo de box ha sido confirmado.\n\n";
                                $message .= "Detalles de los bloques arrendados:\n";
                                $message .= $rentals_details;
                                $message .= "\nMétodo de pago: " . strtoupper($payment_method) . "\n";
                                $message .= "ID de transacción: " . $payment_id . "\n\n";
                                $message .= "Gracias por tu preferencia.";
                                
                                if (class_exists('MAS_Notifications')) {
                                    foreach ($rentals as $rental) {
                                        // Enviar email de confirmación al profesional con template HTML
                                        MAS_Notifications::send_rental_confirmation($rental->id);
                                        // Enviar notificación al administrador
                                        MAS_Notifications::send_admin_rental_notification($rental->id);
                                    }
                                    error_log('[MAS SUCCESS PAGE] Emails y notificaciones enviadas para ' . count($rentals) . ' arriendos');
                                }
                            }
                        } else {
                            $processing_error = 'Error al actualizar arriendos: ' . $wpdb->last_error;
                            error_log('[MAS SUCCESS PAGE] ' . $processing_error);
                        }
                    } else {
                        $processing_error = 'No se encontraron arriendos con referencia: ' . $external_ref;
                        error_log('[MAS SUCCESS PAGE] ' . $processing_error);
                    }
                } elseif (strpos($external_ref, 'APPT') === 0) {
                    // Procesar cita médica
                    $appointment = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}mas_appointments 
                        WHERE external_reference = %s",
                        $external_ref
                    ));
                    
                    if ($appointment) {
                        $updated = $wpdb->update(
                            $wpdb->prefix . 'mas_appointments',
                            array(
                                'status' => 'scheduled',
                                'payment_status' => 'paid',
                                'payment_method' => $payment_method,
                                'payment_id' => $payment_id,
                                'amount_paid' => $amount
                            ),
                            array('external_reference' => $external_ref),
                            array('%s', '%s', '%s', '%s', '%f'),
                            array('%s')
                        );
                        
                        if ($updated !== false && $updated > 0) {
                            error_log('[MAS SUCCESS PAGE] Cita actualizada exitosamente. Referencia: ' . $external_ref);
                            
                            if (class_exists('MAS_Notifications')) {
                                // Obtener el ID de la cita para enviar el email con template HTML
                                $appointment = $wpdb->get_row($wpdb->prepare(
                                    "SELECT * FROM {$wpdb->prefix}mas_appointments WHERE external_reference = %s",
                                    $external_ref
                                ));
                                
                                if ($appointment) {
                                    MAS_Notifications::send_appointment_confirmation($appointment->id);
                                    error_log('[MAS SUCCESS PAGE] Email de confirmación enviado para cita ID: ' . $appointment->id);
                                }
                            }
                        } else {
                            $processing_error = 'Error al actualizar cita: ' . $wpdb->last_error;
                            error_log('[MAS SUCCESS PAGE] ' . $processing_error);
                        }
                    } else {
                        $processing_error = 'No se encontró la cita con referencia: ' . $external_ref;
                        error_log('[MAS SUCCESS PAGE] ' . $processing_error);
                    }
                }
            } else {
                $processing_error = 'Error al decodificar respuesta de MercadoPago';
                error_log('[MAS SUCCESS PAGE] ' . $processing_error);
            }
        } else {
            $processing_error = 'Error al consultar pago en MercadoPago (HTTP ' . $http_code . ')';
            error_log('[MAS SUCCESS PAGE] ' . $processing_error);
        }
    }
}

// Determinar si es cita o arriendo
$is_appointment = strpos($external_reference, 'APPT') === 0;
$is_rental = strpos($external_reference, 'RENT') === 0;

$is_logged_in = is_user_logged_in();

// Obtener URL del comprobante si existe
$voucher_url = '';
if ($payment_id) {
    if (class_exists('MAS_MercadoPago')) {
        $mas_mp = new MAS_MercadoPago();
        $payment_info = $mas_mp->get_payment_info($payment_id);
        if ($payment_info && isset($payment_info['transaction_details']['external_resource_url'])) {
            $voucher_url = $payment_info['transaction_details']['external_resource_url'];
        }
    }
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pago Exitoso - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        .payment-success-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 40px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .success-icon {
            font-size: 64px;
            color: #00a650;
            margin-bottom: 20px;
        }
        .payment-success-container h1 {
            color: #00a650;
            margin-bottom: 10px;
        }
        .payment-details {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: left;
        }
        .payment-details p {
            margin: 10px 0;
            line-height: 1.6;
        }
        .payment-details strong {
            display: inline-block;
            width: 180px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 28px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            min-width: 160px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(20, 184, 166, 0.2);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%);
            box-shadow: 0 4px 8px rgba(20, 184, 166, 0.3);
            transform: translateY(-1px);
            color: white;
        }
        
        .btn-secondary {
            background: white;
            color: #334155;
            border: 2px solid #e2e8f0;
        }
        
        .btn-secondary:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
            transform: translateY(-1px);
            color: white;
        }
        .processing-status {
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border: 1px solid #ffc107;
        }
        .processing-status.success {
            background: #d4edda;
            border-color: #28a745;
        }
        .processing-status.error {
            background: #f8d7da;
            border-color: #dc3545;
        }
        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 32px;
        }
    </style>
</head>
<body>
    <div class="payment-success-container">
        <div class="success-icon">✓</div>
        <h1>¡Pago Exitoso!</h1>
        <p>Tu pago ha sido procesado correctamente.</p>
        
        <?php if ($payment_processed): ?>
            <div class="processing-status success">
                <strong>✓ Confirmación Procesada</strong><br>
                Tu <?php echo $is_appointment ? 'cita' : 'arriendo'; ?> ha sido confirmado y activado exitosamente.
            </div>
        <?php elseif ($processing_error): ?>
            <div class="processing-status error">
                <strong>⚠ Error al Procesar</strong><br>
                <?php echo esc_html($processing_error); ?><br>
                <small>No te preocupes, tu pago fue exitoso. El sistema procesará automáticamente tu <?php echo $is_appointment ? 'cita' : 'arriendo'; ?> en breve.</small>
            </div>
        <?php endif; ?>
        
        <div class="payment-details">
            <h3><?php echo $is_appointment ? 'Detalles de la Cita' : 'Detalles del Arriendo'; ?></h3>
            
            <?php if ($payment_id): ?>
                <p><strong>ID de Pago:</strong> #<?php echo esc_html($payment_id); ?></p>
            <?php endif; ?>
            
            <?php if ($status): ?>
                <p><strong>Estado:</strong> <?php echo $status === 'approved' ? 'Aprobado' : esc_html($status); ?></p>
            <?php endif; ?>
            
            <?php if ($payment_type): ?>
                <p><strong>Método de Pago:</strong> 
                    <?php 
                    $payment_methods = array(
                        'credit_card' => 'Tarjeta de Crédito',
                        'debit_card' => 'Tarjeta de Débito',
                        'bank_transfer' => 'Transferencia Bancaria',
                        'ticket' => 'Efectivo',
                        'account_money' => 'Dinero en Cuenta'
                    );
                    echo isset($payment_methods[$payment_type]) ? $payment_methods[$payment_type] : esc_html($payment_type);
                    ?>
                </p>
            <?php endif; ?>
            
            <p><strong>Referencia:</strong> <?php echo esc_html($external_reference); ?></p>
        </div>
        
        <div style="background: #e7f7ff; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <p style="margin: 0; color: #0066cc;">
                <strong>📧 Confirmación Enviada</strong><br>
                Hemos enviado un correo electrónico con los detalles de tu <?php echo $is_appointment ? 'cita' : 'arriendo'; ?>
            </p>
        </div>
        
        <div class="action-buttons">
            <?php if ($voucher_url): ?>
                <a href="<?php echo esc_url($voucher_url); ?>" target="_blank" class="btn btn-success">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Descargar Comprobante
                </a>
            <?php endif; ?>
            
            <?php 
            if ($is_logged_in): 
                // Usuario autenticado - Mostrar botones del panel profesional
                if ($is_appointment): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mas-appointments')); ?>" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 01-2 2z" />
                        </svg>
                        Ver Mis Citas
                    </a>
                <?php else: ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mas-my-rentals')); ?>" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a1 1 0 00-1-1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Ver Mis Arriendos
                    </a>
                <?php endif; ?>
                
                <a href="<?php echo esc_url(admin_url('admin.php?page=mas-professional-dashboard')); ?>" class="btn btn-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Ir al Dashboard
                </a>
            <?php else: 
                // Usuario NO autenticado - Mostrar solo botón público
                ?>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Volver al Inicio
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>
