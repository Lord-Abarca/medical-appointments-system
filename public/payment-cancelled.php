<?php
/**
 * Template para página de pago cancelado
 */

if (!defined('ABSPATH')) {
    exit;
}

// Obtener parámetros de la URL
$external_reference = isset($_GET['external_reference']) ? sanitize_text_field($_GET['external_reference']) : '';
$preference_id = isset($_GET['preference_id']) ? sanitize_text_field($_GET['preference_id']) : '';
$is_appointment = strpos($external_reference, 'APPT') === 0;

$is_logged_in = is_user_logged_in();

if ($external_reference) {
    global $wpdb;
    
    // Determinar tabla según el tipo
    if ($is_appointment) {
        $table = $wpdb->prefix . 'mas_appointments';
    } else {
        $table = $wpdb->prefix . 'mas_box_rentals';
    }
    
    // Eliminar todos los registros pendientes con este external_reference
    $deleted = $wpdb->delete(
        $table,
        array(
            'external_reference' => $external_reference,
            'payment_status' => 'pending'
        ),
        array('%s', '%s')
    );
    
    // Log para debugging
    if ($deleted !== false) {
        error_log(sprintf(
            '[MAS] Payment cancelled - Deleted %d %s with external_reference: %s',
            $deleted,
            $is_appointment ? 'appointments' : 'rentals',
            $external_reference
        ));
    }
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pago Cancelado - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .payment-cancelled-container {
            max-width: 600px;
            width: 100%;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 48px 40px;
            text-align: center;
        }
        
        .cancelled-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 32px;
            background: linear-gradient(135deg, #fef3c7 0%, #fffbeb 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f59e0b;
        }
        
        .cancelled-icon svg {
            width: 44px;
            height: 44px;
            stroke-width: 2.5;
        }
        
        .payment-cancelled-container h1 {
            font-size: 32px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 16px 0;
            letter-spacing: -0.02em;
        }
        
        .cancelled-description {
            font-size: 18px;
            line-height: 1.7;
            color: #475569;
            margin: 0 0 32px 0;
        }
        
        .info-box {
            background: #fffbeb;
            border: 1px solid #fef3c7;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 32px;
        }
        
        .info-box p {
            margin: 0;
            font-size: 15px;
            color: #334155;
            line-height: 1.6;
        }
        
        .reference-code {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #475569;
            background: white;
            padding: 8px 12px;
            border-radius: 6px;
            margin-top: 12px;
            display: inline-block;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 32px;
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
        
        .help-section {
            margin-top: 32px;
            padding-top: 32px;
            border-top: 1px solid #f1f5f9;
        }
        
        .help-section p {
            font-size: 14px;
            color: #475569;
            margin: 0;
        }
        
        .help-section a {
            color: #0d9488;
            text-decoration: none;
            font-weight: 500;
        }
        
        .help-section a:hover {
            color: #0f766e;
            text-decoration: underline;
        }
        
        @media (max-width: 640px) {
            .payment-cancelled-container {
                padding: 32px 24px;
            }
            
            .payment-cancelled-container h1 {
                font-size: 26px;
            }
            
            .cancelled-description {
                font-size: 16px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="payment-cancelled-container">
        <div class="cancelled-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
        </div>
        
        <h1>Pago Cancelado</h1>
        
        <p class="cancelled-description">
            Has cancelado el proceso de pago. No se realizó ningún cargo a tu cuenta.
        </p>
        
        <?php if ($external_reference): ?>
        <div class="info-box">
            <p>
                <strong>Tu <?php echo $is_appointment ? 'cita' : 'reserva'; ?> ha sido eliminada del sistema.</strong><br>
                Puedes crear una nueva <?php echo $is_appointment ? 'cita' : 'reserva'; ?> cuando estés listo.
            </p>
            <span class="reference-code"><?php echo esc_html($external_reference); ?></span>
        </div>
        <?php endif; ?>
        
        <div class="action-buttons">
            <?php 
            if ($is_logged_in): 
                // Usuario autenticado - Mostrar opciones del panel
                if ($is_appointment): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mas-appointments-calendar')); ?>" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Agendar Nueva Cita
                    </a>
                <?php else: ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mas-rent-box')); ?>" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Intentar Nuevamente
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
        
        <div class="help-section">
            <p>
                ¿Necesitas ayuda? Contáctanos: <a href="mailto:<?php echo get_option('admin_email'); ?>"><?php echo get_option('admin_email'); ?></a>
            </p>
        </div>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>
