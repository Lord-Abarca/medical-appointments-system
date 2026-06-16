<?php
/**
 * Template para página de pago fallido
 */

if (!defined('ABSPATH')) {
    exit;
}

$payment_id = isset($_GET['payment_id']) ? sanitize_text_field($_GET['payment_id']) : '';
$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$collection_status = isset($_GET['collection_status']) ? sanitize_text_field($_GET['collection_status']) : '';

// Si payment_id, status y collection_status son null o vacíos, es una cancelación voluntaria
if (($payment_id === 'null' || empty($payment_id)) && 
    ($status === 'null' || empty($status)) && 
    ($collection_status === 'null' || empty($collection_status))) {
    // Redirigir a la página de cancelación
    $redirect_url = add_query_arg($_GET, home_url('/pago-cancelado/'));
    wp_redirect($redirect_url);
    exit;
}

$external_reference = isset($_GET['external_reference']) ? sanitize_text_field($_GET['external_reference']) : '';

$is_appointment = strpos($external_reference, 'APPT') === 0;
$is_logged_in = is_user_logged_in();

if (!empty($external_reference)) {
    global $wpdb;
    
    if ($is_appointment) {
        // Eliminar cita fallida
        $table = $wpdb->prefix . 'mas_appointments';
        $deleted = $wpdb->delete(
            $table,
            array(
                'external_reference' => $external_reference,
                'status' => 'pending'
            ),
            array('%s', '%s')
        );
        
        error_log("[MAS PAYMENT FAILURE] Cita eliminada - Reference: {$external_reference}, Registros eliminados: {$deleted}");
    } else {
        // Eliminar arriendos fallidos
        $table = $wpdb->prefix . 'mas_box_rentals';
        $deleted = $wpdb->delete(
            $table,
            array(
                'external_reference' => $external_reference,
                'payment_status' => 'pending'
            ),
            array('%s', '%s')
        );
        
        error_log("[MAS PAYMENT FAILURE] Arriendos eliminados - Reference: {$external_reference}, Registros eliminados: {$deleted}");
    }
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pago Fallido - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        .payment-failure-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 40px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .failure-icon {
            font-size: 64px;
            color: #f23d4f;
            margin-bottom: 20px;
        }
        .payment-failure-container h1 {
            color: #f23d4f;
            margin-bottom: 10px;
        }
        .info-box {
            background: #fff4f4;
            border-left: 4px solid #f23d4f;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
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
    <div class="payment-failure-container">
        <div class="failure-icon">✕</div>
        <h1>Pago No Completado</h1>
        <p>Lo sentimos, no pudimos procesar tu pago.</p>
        
        <div class="info-box">
            <h3>¿Qué puedes hacer?</h3>
            <ul style="text-align: left; padding-left: 20px;">
                <li>Verifica que tengas fondos suficientes en tu tarjeta</li>
                <li>Asegúrate de ingresar los datos correctamente</li>
                <li>Intenta con otro método de pago</li>
                <li>Contacta a tu banco si el problema persiste</li>
            </ul>
        </div>
        
        <div class="action-buttons">
            <?php 
            if ($is_logged_in): 
                // Usuario autenticado - Mostrar opciones del panel
                if ($is_appointment): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mas-appointments-calendar')); ?>" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Intentar Agendar Cita
                    </a>
                <?php else: ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mas-rent-box')); ?>" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Intentar Arrendar Nuevamente
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
        
        <p style="margin-top: 30px; color: #666; font-size: 14px;">
            Si necesitas ayuda, contáctanos:<br>
            <strong>Email:</strong> <?php echo get_option('admin_email'); ?>
        </p>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>
