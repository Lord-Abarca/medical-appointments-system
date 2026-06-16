<?php
/**
 * Template para página de pago pendiente
 */

if (!defined('ABSPATH')) {
    exit;
}

$external_reference = isset($_GET['external_reference']) ? sanitize_text_field($_GET['external_reference']) : '';
$payment_id = isset($_GET['payment_id']) ? sanitize_text_field($_GET['payment_id']) : '';
$is_appointment = strpos($external_reference, 'APPOINTMENT_') === 0;

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pago Pendiente - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        .payment-pending-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 40px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .pending-icon {
            font-size: 64px;
            color: #ff9800;
            margin-bottom: 20px;
        }
        .payment-pending-container h1 {
            color: #ff9800;
            margin-bottom: 10px;
        }
        .info-box {
            background: #fff9e6;
            border-left: 4px solid #ff9800;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        .payment-details {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .btn-primary {
            background: #009ee3;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            cursor: pointer;
        }
        .btn-primary:hover {
            background: #007ab8;
        }
    </style>
</head>
<body>
    <div class="payment-pending-container">
        <div class="pending-icon">⏳</div>
        <h1>Pago Pendiente</h1>
        <p>Tu pago está siendo procesado.</p>
        
        <?php if ($payment_id): ?>
        <div class="payment-details">
            <p><strong>ID de Pago:</strong> #<?php echo esc_html($payment_id); ?></p>
            <p><strong>Referencia:</strong> <?php echo esc_html($external_reference); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h3>¿Qué significa esto?</h3>
            <p>Tu pago está siendo verificado. Este proceso puede tomar:</p>
            <ul style="padding-left: 20px;">
                <li><strong>Tarjeta:</strong> Hasta 48 horas</li>
                <li><strong>Transferencia:</strong> 1-2 días hábiles</li>
                <li><strong>Efectivo:</strong> Hasta 3 días hábiles</li>
            </ul>
        </div>
        
        <div style="background: #e7f7ff; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <p style="margin: 0; color: #0066cc;">
                <strong>📧 Te notificaremos</strong><br>
                Recibirás un correo cuando se confirme tu pago y tu <?php echo $is_appointment ? 'cita' : 'arriendo'; ?> esté confirmado.
            </p>
        </div>
        
        <a href="<?php echo home_url(); ?>" class="btn-primary">Volver al Inicio</a>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>
