<?php
/**
 * Página de configuración del sistema
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle blocked date actions
if (isset($_POST['mas_add_blocked_date']) && check_admin_referer('mas_blocked_date_nonce')) {
    global $wpdb;
    $blocked_dates_table = $wpdb->prefix . 'mas_blocked_dates';
    
    $data = array(
        'blocked_date' => sanitize_text_field($_POST['blocked_date']),
        'reason' => sanitize_text_field($_POST['reason']),
        'blocks_appointments' => isset($_POST['blocks_appointments']) ? 1 : 0,
        'blocks_rentals' => isset($_POST['blocks_rentals']) ? 1 : 0
    );
    
    $result = $wpdb->insert($blocked_dates_table, $data);
    
    if ($result) {
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Fecha bloqueada agregada exitosamente.', 'medical-appointments') . '</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('Error al agregar fecha bloqueada. La fecha puede estar ya bloqueada.', 'medical-appointments') . '</p></div>';
    }
}

// Delete blocked date
if (isset($_GET['delete_blocked_date']) && check_admin_referer('mas_delete_blocked_date_' . $_GET['delete_blocked_date'])) {
    global $wpdb;
    $blocked_dates_table = $wpdb->prefix . 'mas_blocked_dates';
    
    if ($wpdb->delete($blocked_dates_table, array('id' => intval($_GET['delete_blocked_date'])))) {
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Fecha bloqueada eliminada exitosamente.', 'medical-appointments') . '</p></div>';
    }
}

// Guardar configuración
if (isset($_POST['mas_save_settings']) && check_admin_referer('mas_settings_nonce')) {
    error_log('[MAS Settings] Starting to save settings...');
    
    $schedule_by_day = array();
    $days_map = array(1 => 'monday', 2 => 'tuesday', 3 => 'wednesday', 4 => 'thursday', 5 => 'friday', 6 => 'saturday', 0 => 'sunday');
    
    foreach ($days_map as $day_num => $day_key) {
        if (isset($_POST['working_days']) && in_array($day_num, $_POST['working_days'])) {
            $schedule_by_day[$day_num] = array(
                'enabled' => true,
                'start_time' => sanitize_text_field($_POST["start_time_{$day_key}"]),
                'end_time' => sanitize_text_field($_POST["end_time_{$day_key}"])
            );
        } else {
            $schedule_by_day[$day_num] = array(
                'enabled' => false,
                'start_time' => '09:00',
                'end_time' => '18:00'
            );
        }
    }
    
    $settings = array(
        'slot_duration' => intval($_POST['slot_duration']),
        'start_time' => sanitize_text_field($_POST['start_time']), // Kept for backward compatibility or general default
        'end_time' => sanitize_text_field($_POST['end_time']), // Kept for backward compatibility or general default
        'schedule_by_day' => $schedule_by_day,
        'working_days' => isset($_POST['working_days']) ? array_map('sanitize_text_field', $_POST['working_days']) : array(),
        'booking_advance_days' => intval($_POST['booking_advance_days']),
        'min_booking_notice' => intval($_POST['min_booking_notice']),
        'enable_notifications' => isset($_POST['enable_notifications']) ? 1 : 0,
        'admin_email' => sanitize_email($_POST['admin_email']),
        'max_appointments_per_slot' => intval($_POST['max_appointments_per_slot']),
        'require_rut' => isset($_POST['require_rut']) ? 1 : 0,
        'auto_confirm_appointments' => isset($_POST['auto_confirm_appointments']) ? 1 : 0,
        'allow_patient_professional_selection' => isset($_POST['allow_patient_professional_selection']) ? 1 : 0
    );
    
    error_log('[MAS Settings] Settings to save: ' . print_r($settings, true));
    
    $result = update_option('mas_settings', $settings);
    
    error_log('[MAS Settings] Update result: ' . ($result ? 'SUCCESS' : 'FAILED (or no change)'));
    
    // Verify the save
    $saved_settings = get_option('mas_settings');
    error_log('[MAS Settings] Verified saved settings: ' . print_r($saved_settings, true));
    
    echo '<div class="notice notice-success is-dismissible"><p><strong>' . __('✓ Configuración guardada exitosamente.', 'medical-appointments') . '</strong></p>';
    echo '<p style="font-size: 12px; color: #666;">' . __('Última actualización: ', 'medical-appointments') . date('d/m/Y H:i:s') . '</p></div>';
}

if (isset($_POST['mas_save_mp_credentials']) && check_admin_referer('mas_mp_credentials_nonce')) {
    $mp_access_token = sanitize_text_field($_POST['mp_access_token']);
    $mp_public_key = sanitize_text_field($_POST['mp_public_key']);
    $mp_webhook_secret = sanitize_text_field($_POST['mp_webhook_secret']);
    
    if (!empty($mp_access_token) && !empty($mp_public_key)) {
        update_option('mas_mp_access_token', $mp_access_token);
        update_option('mas_mp_public_key', $mp_public_key);
        update_option('mas_mp_webhook_secret', $mp_webhook_secret);
        echo '<div class="notice notice-success is-dismissible"><p><strong>' . __('✓ Credenciales de MercadoPago guardadas exitosamente.', 'medical-appointments') . '</strong></p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p><strong>' . __('Error: Debe completar los campos obligatorios (Access Token y Public Key).', 'medical-appointments') . '</strong></p></div>';
    }
}

if (isset($_POST['mas_delete_mp_credentials']) && check_admin_referer('mas_mp_delete_nonce')) {
    delete_option('mas_mp_access_token');
    delete_option('mas_mp_public_key');
    delete_option('mas_mp_webhook_secret');
    echo '<div class="notice notice-success is-dismissible"><p><strong>' . __('✓ Credenciales de MercadoPago eliminadas exitosamente.', 'medical-appointments') . '</strong></p></div>';
}

$settings = get_option('mas_settings', array());
$defaults = array(
    'slot_duration' => 60,
    'start_time' => '09:00',
    'end_time' => '18:00',
    'schedule_by_day' => array(), // Added for new schedule_by_day setting
    'working_days' => array('1', '2', '3', '4', '5'),
    'booking_advance_days' => 30,
    'min_booking_notice' => 24,
    'enable_notifications' => 1,
    'admin_email' => get_option('admin_email'),
    'max_appointments_per_slot' => 1,
    'require_rut' => 1,
    'auto_confirm_appointments' => 0,
    'allow_patient_professional_selection' => 0
);

$settings = wp_parse_args($settings, $defaults);

$mp_access_token = get_option('mas_mp_access_token', '');
$mp_public_key = get_option('mas_mp_public_key', '');
$mp_webhook_secret = get_option('mas_mp_webhook_secret', '');
$has_mp_credentials = !empty($mp_access_token) && !empty($mp_public_key);

$days_of_week = array(
    '1' => __('Lunes', 'medical-appointments'),
    '2' => __('Martes', 'medical-appointments'),
    '3' => __('Miércoles', 'medical-appointments'),
    '4' => __('Jueves', 'medical-appointments'),
    '5' => __('Viernes', 'medical-appointments'),
    '6' => __('Sábado', 'medical-appointments'),
    '7' => __('Domingo', 'medical-appointments')
);

// Get blocked dates
global $wpdb;
$blocked_dates_table = $wpdb->prefix . 'mas_blocked_dates';
$blocked_dates = $wpdb->get_results("SELECT * FROM $blocked_dates_table ORDER BY blocked_date ASC");
?>

<style>
    .mas-settings-page .mas-settings-container {
        background: #fff;
        padding: 20px;
        margin-top: 20px;
        border: 1px solid #ccd0d4;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
    }
    .mas-settings-page .mas-form-section,
    .mas-settings-page .mas-settings-section {
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid #e5e5e5;
    }
    .mas-settings-page .mas-form-section:last-child,
    .mas-settings-page .mas-settings-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    .mas-settings-page h2 {
        margin-top: 0;
        padding-bottom: 10px;
        border-bottom: 2px solid #0073aa;
    }
    .schedule-day-row select:disabled,
    .schedule-day-row input[type="checkbox"]:disabled + span {
        opacity: 0.5;
        cursor: not-allowed;
    }
</style>

<div class="wrap mas-settings-page">
    <h1><?php _e('Configuración del Sistema', 'medical-appointments'); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('mas_settings_nonce'); ?>
        
        <div class="mas-settings-container">
            
            <!-- Nueva sección de Configuración de MercadoPago -->
            <div class="mas-form-section">
                <h2><?php _e('Configuración de MercadoPago', 'medical-appointments'); ?></h2>
                <p class="description"><?php _e('Configure sus credenciales de MercadoPago para procesar pagos. Puede obtenerlas desde su', 'medical-appointments'); ?> <a href="https://www.mercadopago.cl/developers/panel/app" target="_blank">Panel de Desarrolladores de MercadoPago</a>.</p>
                
                <?php if ($has_mp_credentials) : ?>
                    <!-- Mostrar credenciales existentes -->
                    <div style="background: #f0f9ff; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0;">
                        <h3 style="margin-top: 0; color: #0073aa;"><?php _e('✓ Credenciales Configuradas', 'medical-appointments'); ?></h3>
                        <table class="form-table" style="margin: 0;">
                            <tr>
                                <th scope="row" style="padding-left: 0;"><?php _e('Access Token:', 'medical-appointments'); ?></th>
                                <td>
                                    <code style="background: #fff; padding: 5px 10px; border-radius: 3px; display: inline-block;">
                                        <?php echo substr($mp_access_token, 0, 20) . '...' . substr($mp_access_token, -10); ?>
                                    </code>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row" style="padding-left: 0;"><?php _e('Public Key:', 'medical-appointments'); ?></th>
                                <td>
                                    <code style="background: #fff; padding: 5px 10px; border-radius: 3px; display: inline-block;">
                                        <?php echo substr($mp_public_key, 0, 20) . '...' . substr($mp_public_key, -10); ?>
                                    </code>
                                </td>
                            </tr>
                            <!-- Mostrar estado de clave secreta -->
                            <tr>
                                <th scope="row" style="padding-left: 0;"><?php _e('Clave Secreta Webhook:', 'medical-appointments'); ?></th>
                                <td>
                                    <?php if (!empty($mp_webhook_secret)) : ?>
                                        <code style="background: #fff; padding: 5px 10px; border-radius: 3px; display: inline-block;">
                                            <?php echo substr($mp_webhook_secret, 0, 10) . '...' . substr($mp_webhook_secret, -10); ?>
                                        </code>
                                        <span style="color: #46b450; margin-left: 10px;">✓ Configurada</span>
                                    <?php else : ?>
                                        <span style="color: #dc3232;">⚠️ No configurada (opcional pero recomendada)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                        
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                            <button type="button" onclick="document.getElementById('mp-edit-form').style.display='block'; this.style.display='none';" class="button button-secondary">
                                <?php _e('✏️ Editar Credenciales', 'medical-appointments'); ?>
                            </button>
                            <button type="button" onclick="if(confirm('¿Está seguro de eliminar las credenciales de MercadoPago?')) { document.getElementById('mp-delete-form').submit(); }" class="button button-link-delete" style="margin-left: 10px;">
                                <?php _e('🗑️ Eliminar Credenciales', 'medical-appointments'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Formulario de edición (oculto por defecto) -->
                    <div id="mp-edit-form" style="display: none; background: #fff; border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 5px;">
                        <h3><?php _e('Editar Credenciales de MercadoPago', 'medical-appointments'); ?></h3>
                        <form method="post" action="">
                            <?php wp_nonce_field('mas_mp_credentials_nonce'); ?>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="mp_access_token"><?php _e('Access Token', 'medical-appointments'); ?> <span style="color: red;">*</span></label>
                                    </th>
                                    <td>
                                        <input type="text" name="mp_access_token" id="mp_access_token" value="<?php echo esc_attr($mp_access_token); ?>" class="large-text" required>
                                        <p class="description">
                                            <?php _e('Token de acceso de MercadoPago. MercadoPago detecta automáticamente si son credenciales de prueba o producción.', 'medical-appointments'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="mp_public_key"><?php _e('Public Key', 'medical-appointments'); ?> <span style="color: red;">*</span></label>
                                    </th>
                                    <td>
                                        <input type="text" name="mp_public_key" id="mp_public_key" value="<?php echo esc_attr($mp_public_key); ?>" class="large-text" required>
                                        <p class="description"><?php _e('Clave pública de MercadoPago (debe coincidir con el Access Token)', 'medical-appointments'); ?></p>
                                    </td>
                                </tr>
                                <!-- Agregado campo para clave secreta del webhook -->
                                <tr>
                                    <th scope="row">
                                        <label for="mp_webhook_secret"><?php _e('Clave Secreta Webhook', 'medical-appointments'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="mp_webhook_secret" id="mp_webhook_secret" value="<?php echo esc_attr($mp_webhook_secret); ?>" class="large-text" placeholder="23706c6ba3434d52f83c7e486d6ce14e0b012119af3603f031dbad39610a17a4">
                                        <p class="description">
                                            <?php _e('Clave secreta generada por MercadoPago al configurar el webhook. Opcional pero altamente recomendada para validar que las notificaciones provienen realmente de MercadoPago.', 'medical-appointments'); ?>
                                            <br>
                                            <strong><?php _e('¿Dónde encontrarla?', 'medical-appointments'); ?></strong> <?php _e('Panel de MercadoPago → Tu aplicación → Webhooks → verás la clave secreta después de crear el webhook.', 'medical-appointments'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
                                <h4 style="margin-top: 0;"><?php _e('⚠️ Configuración del Webhook (IMPORTANTE)', 'medical-appointments'); ?></h4>
                                <p><?php _e('Para que los pagos se procesen automáticamente, debes configurar el webhook en el panel de MercadoPago:', 'medical-appointments'); ?></p>
                                <ol style="margin: 10px 0;">
                                    <li><?php _e('Ve a:', 'medical-appointments'); ?> <a href="https://www.mercadopago.cl/developers/panel" target="_blank">https://www.mercadopago.cl/developers/panel</a></li>
                                    <li><?php _e('Selecciona tu aplicación', 'medical-appointments'); ?></li>
                                    <li><?php _e('Ve a "Webhooks" en el menú', 'medical-appointments'); ?></li>
                                    <li><?php _e('Haz clic en "Configurar notificaciones" o "Agregar webhook"', 'medical-appointments'); ?></li>
                                    <li><?php _e('Ingresa esta URL:', 'medical-appointments'); ?>
                                        <div style="background: #fff; padding: 10px; border-radius: 3px; margin: 10px 0; font-family: monospace;">
                                            <strong><?php echo get_site_url(); ?>/mercadopago-webhook.php</strong>
                                            <button type="button" onclick="navigator.clipboard.writeText('<?php echo get_site_url(); ?>/mercadopago-webhook.php'); alert('URL copiada al portapapeles');" class="button button-small" style="margin-left: 10px;">
                                                <?php _e('📋 Copiar', 'medical-appointments'); ?>
                                            </button>
                                        </div>
                                    </li>
                                    <li><?php _e('Selecciona el evento: "Pagos" o "payment"', 'medical-appointments'); ?></li>
                                    <li><?php _e('Guarda la configuración', 'medical-appointments'); ?></li>
                                </ol>
                                <p><strong style="color: #856404;"><?php _e('NOTA:', 'medical-appointments'); ?></strong> <?php _e('Si usas credenciales de PRUEBA, configura el webhook en modo PRUEBA/TEST en el panel de MercadoPago. Si usas credenciales de PRODUCCIÓN, configúralo en modo PRODUCCIÓN.', 'medical-appointments'); ?></p>
                            </div>
                            
                            <p class="submit">
                                <input type="submit" name="mas_save_mp_credentials" class="button button-primary" value="<?php _e('Actualizar Credenciales', 'medical-appointments'); ?>">
                                <button type="button" onclick="document.getElementById('mp-edit-form').style.display='none';" class="button button-secondary">
                                    <?php _e('Cancelar', 'medical-appointments'); ?>
                                </button>
                            </p>
                        </form>
                    </div>
                    
                    <!-- Formulario oculto para eliminar -->
                    <form id="mp-delete-form" method="post" action="" style="display: none;">
                        <?php wp_nonce_field('mas_mp_delete_nonce'); ?>
                        <input type="hidden" name="mas_delete_mp_credentials" value="1">
                    </form>
                    
                <?php else : ?>
                    <!-- Formulario para agregar credenciales -->
                    <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
                        <p style="margin: 0;"><strong><?php _e('⚠️ No hay credenciales configuradas', 'medical-appointments'); ?></strong></p>
                        <p style="margin: 5px 0 0 0;"><?php _e('Para procesar pagos con MercadoPago, debe configurar sus credenciales.', 'medical-appointments'); ?></p>
                    </div>
                    
                    <form method="post" action="" style="background: #fff; border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 5px;">
                        <?php wp_nonce_field('mas_mp_credentials_nonce'); ?>
                        <h3><?php _e('Agregar Credenciales de MercadoPago', 'medical-appointments'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="mp_access_token"><?php _e('Access Token', 'medical-appointments'); ?> <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <input type="text" name="mp_access_token" id="mp_access_token" class="large-text" placeholder="APP_USR-xxxxxxxx... o TEST-xxxxxxxx..." required>
                                    <p class="description">
                                        <?php _e('Token de acceso de MercadoPago. Obtén tus credenciales desde:', 'medical-appointments'); ?>
                                        <a href="https://www.mercadopago.cl/developers/panel/app" target="_blank">Panel de Desarrolladores</a>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="mp_public_key"><?php _e('Public Key', 'medical-appointments'); ?> <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <input type="text" name="mp_public_key" id="mp_public_key" class="large-text" placeholder="APP_USR-xxxxxxxx... o TEST-xxxxxxxx..." required>
                                    <p class="description"><?php _e('Clave pública de MercadoPago (debe coincidir con el Access Token)', 'medical-appointments'); ?></p>
                                </td>
                            </tr>
                            <!-- Agregado campo para clave secreta del webhook -->
                            <tr>
                                <th scope="row">
                                    <label for="mp_webhook_secret"><?php _e('Clave Secreta Webhook', 'medical-appointments'); ?></label>
                                </th>
                                <td>
                                    <input type="text" name="mp_webhook_secret" id="mp_webhook_secret" class="large-text" placeholder="Opcional - Se genera al configurar el webhook en MercadoPago">
                                    <p class="description">
                                        <?php _e('Clave secreta del webhook (opcional). La obtendrás después de configurar el webhook en el panel de MercadoPago.', 'medical-appointments'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        
                        <div style="background: #e7f3ff; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0;">
                            <h4 style="margin-top: 0;"><?php _e('📝 Después de guardar las credenciales', 'medical-appointments'); ?></h4>
                            <p><?php _e('Deberás configurar el webhook en el panel de MercadoPago para que los pagos se procesen automáticamente. Las instrucciones aparecerán después de guardar.', 'medical-appointments'); ?></p>
                        </div>
                        
                        <p class="submit">
                            <input type="submit" name="mas_save_mp_credentials" class="button button-primary" value="<?php _e('Guardar Credenciales', 'medical-appointments'); ?>">
                        </p>
                    </form>
                <?php endif; ?>
                
                <div style="background: #e7f3ff; border: 1px solid #b3d9ff; padding: 15px; margin-top: 20px; border-radius: 5px;">
                    <h4 style="margin-top: 0;"><?php _e('ℹ️ ¿Dónde obtener las credenciales?', 'medical-appointments'); ?></h4>
                    <ol style="margin: 10px 0 0 20px;">
                        <li><?php _e('Ingresa a tu cuenta de MercadoPago', 'medical-appointments'); ?></li>
                        <li><?php _e('Ve a', 'medical-appointments'); ?> <strong>Desarrolladores → Credenciales</strong></li>
                        <li><?php _e('Copia el Access Token y la Public Key', 'medical-appointments'); ?></li>
                        <li><?php _e('Pégalos en los campos de arriba', 'medical-appointments'); ?></li>
                    </ol>
                    <p style="margin: 10px 0 0 0;">
                        <a href="https://www.mercadopago.cl/developers/panel/app" target="_blank" class="button button-secondary">
                            <?php _e('Ir al Panel de MercadoPago', 'medical-appointments'); ?> →
                        </a>
                    </p>
                    
                    <!-- Add webhook configuration instructions -->
                    <hr style="margin: 20px 0; border: none; border-top: 1px solid #b3d9ff;">
                    <h4 style="margin-top: 0; color: #d63638;"><?php _e('🔴 CRÍTICO: Configurar Webhook en MercadoPago', 'medical-appointments'); ?></h4>
                    <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 10px 0; border-radius: 3px;">
                        <p style="margin: 0 0 10px 0; font-weight: bold; font-size: 14px;">
                            <?php _e('⚠️ Sin esta configuración, los pagos NO se confirmarán automáticamente', 'medical-appointments'); ?>
                        </p>
                        <p style="margin: 0;">
                            <?php _e('MercadoPago necesita saber a dónde enviar las notificaciones de pago. Sigue estos pasos:', 'medical-appointments'); ?>
                        </p>
                    </div>
                    
                    <div style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin: 15px 0; border-radius: 5px;">
                        <h5 style="margin-top: 0; color: #0073aa;"><?php _e('Pasos para configurar el Webhook:', 'medical-appointments'); ?></h5>
                        <ol style="margin: 10px 0 10px 20px; line-height: 1.8;">
                            <li>
                                <strong><?php _e('Ve al Panel de MercadoPago:', 'medical-appointments'); ?></strong><br>
                                <a href="https://www.mercadopago.cl/developers/panel" target="_blank" style="text-decoration: none;">
                                    https://www.mercadopago.cl/developers/panel →
                                </a>
                            </li>
                            <li>
                                <strong style="color: #d63638;"><?php _e('MUY IMPORTANTE:', 'medical-appointments'); ?></strong><br>
                                <?php if (isset($mp_test_mode) && $mp_test_mode) : ?>
                                    <span style="background: #fff3cd; padding: 5px 10px; border-radius: 3px; display: inline-block; margin: 5px 0;">
                                        <?php _e('Tienes "Modo Prueba" activado → Cambia al modo', 'medical-appointments'); ?> <strong><?php _e('PRUEBA', 'medical-appointments'); ?></strong> <?php _e('en el panel (toggle arriba a la derecha)', 'medical-appointments'); ?>
                                    </span>
                                <?php else : ?>
                                    <span style="background: #d4edda; padding: 5px 10px; border-radius: 3px; display: inline-block; margin: 5px 0;">
                                        <?php _e('Tienes "Modo Producción" activado → Cambia al modo', 'medical-appointments'); ?> <strong><?php _e('PRODUCCIÓN', 'medical-appointments'); ?></strong> <?php _e('en el panel (toggle arriba a la derecha)', 'medical-appointments'); ?>
                                    </span>
                                <?php endif; ?>
                            </li>
                            <li>
                                <strong><?php _e('Ve a "Webhooks" en el menú lateral', 'medical-appointments'); ?></strong>
                            </li>
                            <li>
                                <strong><?php _e('Haz clic en "Crear webhook" o "Configurar notificaciones"', 'medical-appointments'); ?></strong>
                            </li>
                            <li>
                                <strong><?php _e('Agrega esta URL EXACTA:', 'medical-appointments'); ?></strong><br>
                                <div style="background: #fff; padding: 10px; border: 2px solid #0073aa; border-radius: 5px; margin: 5px 0; font-family: monospace; font-size: 13px;">
                                    <?php echo get_site_url(); ?>/mercadopago-webhook.php
                                </div>
                                <button type="button" onclick="navigator.clipboard.writeText('<?php echo get_site_url(); ?>/mercadopago-webhook.php'); alert('✓ URL copiada al portapapeles');" class="button button-small" style="margin-top: 5px;">
                                    <?php _e('📋 Copiar URL', 'medical-appointments'); ?>
                                </button>
                            </li>
                            <li>
                                <strong><?php _e('Selecciona el evento:', 'medical-appointments'); ?></strong> <code>payment</code> <?php _e('o', 'medical-appointments'); ?> <code>Pagos</code>
                            </li>
                            <li>
                                <strong><?php _e('Guarda los cambios', 'medical-appointments'); ?></strong>
                            </li>
                        </ol>
                    </div>
                    
                    <div style="background: #e7f3ff; border-left: 4px solid #0073aa; padding: 15px; margin: 15px 0; border-radius: 3px;">
                        <h5 style="margin-top: 0;"><?php _e('🔍 ¿Cómo verificar que funciona?', 'medical-appointments'); ?></h5>
                        <ol style="margin: 10px 0 0 20px; line-height: 1.8;">
                            <li><?php _e('Haz un arriendo de prueba', 'medical-appointments'); ?></li>
                            <li><?php _e('Completa el pago en MercadoPago', 'medical-appointments'); ?></li>
                            <li><?php _e('Ve a', 'medical-appointments'); ?> <strong><?php _e('Sistema Médico → Logs de Webhook', 'medical-appointments'); ?></strong></li>
                            <li><?php _e('Deberías ver una entrada con el pago recibido', 'medical-appointments'); ?></li>
                        </ol>
                        <p style="margin: 10px 0 0 0;">
                            <a href="<?php echo admin_url('admin.php?page=mas-webhook-logs'); ?>" class="button button-secondary">
                                <?php _e('Ver Logs de Webhook', 'medical-appointments'); ?> →
                            </a>
                        </p>
                    </div>
                    
                    <div style="background: #ffebee; border-left: 4px solid #d63638; padding: 15px; margin: 15px 0; border-radius: 3px;">
                        <h5 style="margin-top: 0; color: #d63638;"><?php _e('❌ Problemas comunes:', 'medical-appointments'); ?></h5>
                        <ul style="margin: 10px 0 0 20px; line-height: 1.8;">
                            <li>
                                <strong><?php _e('El webhook no se ejecuta:', 'medical-appointments'); ?></strong><br>
                                <?php _e('Verifica que configuraste el webhook en el MISMO modo que tus credenciales (Prueba o Producción)', 'medical-appointments'); ?>
                            </li>
                            <li>
                                <strong><?php _e('No aparece en los logs:', 'medical-appointments'); ?></strong><br>
                                <?php _e('MercadoPago no está enviando la notificación. Revisa la configuración en el panel de MercadoPago.', 'medical-appointments'); ?>
                            </li>
                            <li>
                                <strong><?php _e('Error de credenciales:', 'medical-appointments'); ?></strong><br>
                                <?php _e('Asegúrate de que el Access Token y Public Key sean del mismo tipo (ambos de prueba o ambos de producción)', 'medical-appointments'); ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
    </form> 
    <form method="post" action="">
        <?php wp_nonce_field('mas_settings_nonce'); ?>
            <!-- Configuración de Horarios -->
            <div class="mas-form-section">
                <h2><?php _e('Configuración de Horarios', 'medical-appointments'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="slot_duration"><?php _e('Duración de bloques (minutos)', 'medical-appointments'); ?></label>
                        </th>
                        <td>
                            <select name="slot_duration" id="slot_duration" class="regular-text">
                                <option value="15" <?php selected($settings['slot_duration'], 15); ?>>15 minutos</option>
                                <option value="30" <?php selected($settings['slot_duration'], 30); ?>>30 minutos</option>
                                <option value="45" <?php selected($settings['slot_duration'], 45); ?>>45 minutos</option>
                                <option value="60" <?php selected($settings['slot_duration'], 60); ?>>60 minutos</option>
                                <option value="90" <?php selected($settings['slot_duration'], 90); ?>>90 minutos</option>
                                <option value="120" <?php selected($settings['slot_duration'], 120); ?>>120 minutos</option>
                            </select>
                            <p class="description"><?php _e('Duración de cada bloque de atención', 'medical-appointments'); ?></p>
                        </td>
                    </tr>
                    
                    <!-- Added per-day schedule configuration -->
                    <tr>
                        <th scope="row">
                            <?php _e('Horarios por día', 'medical-appointments'); ?>
                        </th>
                        <td>
                            <div id="schedule-by-day-container" style="background: #f9fafb; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;">
                                <?php
                                $days_map = array(
                                    1 => array('key' => 'monday', 'name' => __('Lunes', 'medical-appointments')),
                                    2 => array('key' => 'tuesday', 'name' => __('Martes', 'medical-appointments')),
                                    3 => array('key' => 'wednesday', 'name' => __('Miércoles', 'medical-appointments')),
                                    4 => array('key' => 'thursday', 'name' => __('Jueves', 'medical-appointments')),
                                    5 => array('key' => 'friday', 'name' => __('Viernes', 'medical-appointments')),
                                    6 => array('key' => 'saturday', 'name' => __('Sábado', 'medical-appointments')),
                                    0 => array('key' => 'sunday', 'name' => __('Domingo', 'medical-appointments'))
                                );
                                
                                foreach ($days_map as $day_num => $day_info) :
                                    // Check if the day is marked as working in the saved settings or defaults to the general working_days array
                                    $is_enabled = in_array($day_num, $settings['working_days']);
                                    
                                    // Get specific schedule for this day, or use general settings if not found, or defaults
                                    $day_schedule = isset($settings['schedule_by_day'][$day_num]) ? $settings['schedule_by_day'][$day_num] : array(
                                        'enabled' => $is_enabled,
                                        'start_time' => $settings['start_time'], // Fallback to general start_time
                                        'end_time' => $settings['end_time']     // Fallback to general end_time
                                    );
                                    
                                    // If the day is not enabled, ensure it uses default times for the disabled state
                                    if (!$is_enabled) {
                                        $day_schedule['start_time'] = '09:00'; // Default when disabled
                                        $day_schedule['end_time'] = '18:00';   // Default when disabled
                                    }
                                ?>
                                    <div class="schedule-day-row" style="display: flex; align-items: center; gap: 16px; margin-bottom: 16px; padding: 12px; background: white; border-radius: 6px; border: 1px solid #e5e7eb;">
                                        <label style="min-width: 120px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                                            <input type="checkbox" 
                                                   name="working_days[]" 
                                                   value="<?php echo esc_attr($day_num); ?>" 
                                                   class="day-enabled-checkbox"
                                                   data-day="<?php echo esc_attr($day_info['key']); ?>"
                                                   <?php checked($is_enabled); ?>>
                                            <span><?php echo esc_html($day_info['name']); ?></span>
                                        </label>
                                        
                                        <div class="time-selectors" style="display: flex; align-items: center; gap: 12px; flex: 1;">
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <label style="font-size: 13px; color: #6b7280;"><?php _e('Inicio:', 'medical-appointments'); ?></label>
                                                <select name="start_time_<?php echo esc_attr($day_info['key']); ?>" 
                                                        id="start_time_<?php echo esc_attr($day_info['key']); ?>"
                                                        class="time-select start-time-select"
                                                        data-day="<?php echo esc_attr($day_info['key']); ?>"
                                                        style="padding: 6px 25px; border: 1px solid #d1d5db; border-radius: 6px;"
                                                        <?php disabled(!$is_enabled); ?>>
                                                    <?php
                                                    // Generate time options based on slot_duration
                                                    $start_hour = strtotime('00:00');
                                                    $end_hour = strtotime('23:59');
                                                    $slot_minutes = $settings['slot_duration'];
                                                    
                                                    for ($time = $start_hour; $time <= $end_hour; $time += ($slot_minutes * 60)) {
                                                        $time_string = date('H:i', $time);
                                                        $selected = ($day_schedule['start_time'] == $time_string) ? 'selected' : '';
                                                        echo '<option value="' . esc_attr($time_string) . '" ' . $selected . '>' . esc_html($time_string) . '</option>';
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                            
                                            <span style="color: #9ca3af;">—</span>
                                            
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <label style="font-size: 13px; color: #6b7280;"><?php _e('Término:', 'medical-appointments'); ?></label>
                                                <select name="end_time_<?php echo esc_attr($day_info['key']); ?>" 
                                                        id="end_time_<?php echo esc_attr($day_info['key']); ?>"
                                                        class="time-select end-time-select"
                                                        data-day="<?php echo esc_attr($day_info['key']); ?>"
                                                        style="padding: 6px 25px; border: 1px solid #d1d5db; border-radius: 6px;"
                                                        <?php disabled(!$is_enabled); ?>>
                                                    <?php
                                                    for ($time = $start_hour; $time <= $end_hour; $time += ($slot_minutes * 60)) {
                                                        $time_string = date('H:i', $time);
                                                        $selected = ($day_schedule['end_time'] == $time_string) ? 'selected' : '';
                                                        echo '<option value="' . esc_attr($time_string) . '" ' . $selected . '>' . esc_html($time_string) . '</option>';
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="description" style="margin-top: 12px;">
                                <?php _e('Marque cada día y configure sus horarios de inicio y término. Los selectores se actualizan automáticamente según la duración del bloque configurada.', 'medical-appointments'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Configuración de Reservas -->
            <div class="mas-settings-section">
                <h2><?php _e('Configuración de Reservas', 'medical-appointments'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="booking_advance_days"><?php _e('Días de anticipación máxima', 'medical-appointments'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="booking_advance_days" id="booking_advance_days" value="<?php echo esc_attr($settings['booking_advance_days']); ?>" min="1" max="365" class="small-text">
                            <p class="description"><?php _e('Número máximo de días con anticipación para agendar', 'medical-appointments'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="min_booking_notice"><?php _e('Aviso mínimo (horas)', 'medical-appointments'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="min_booking_notice" id="min_booking_notice" value="<?php echo esc_attr($settings['min_booking_notice']); ?>" min="0" max="168" class="small-text">
                            <p class="description"><?php _e('Horas mínimas de anticipación requeridas para agendar', 'medical-appointments'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="max_appointments_per_slot"><?php _e('Citas por bloque', 'medical-appointments'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="max_appointments_per_slot" id="max_appointments_per_slot" value="<?php echo esc_attr($settings['max_appointments_per_slot']); ?>" min="1" max="10" class="small-text">
                            <p class="description"><?php _e('Número máximo de citas permitidas por bloque horario', 'medical-appointments'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="require_rut">
                                <input type="checkbox" name="require_rut" id="require_rut" value="1" <?php checked($settings['require_rut'], 1); ?>>
                                <?php _e('Requerir RUT', 'medical-appointments'); ?>
                            </label>
                        </th>
                        <td>
                            <p class="description"><?php _e('Hacer obligatorio el campo RUT en el formulario de agendamiento', 'medical-appointments'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="auto_confirm_appointments">
                                <input type="checkbox" name="auto_confirm_appointments" id="auto_confirm_appointments" value="1" <?php checked($settings['auto_confirm_appointments'], 1); ?>>
                                <?php _e('Confirmar automáticamente', 'medical-appointments'); ?>
                            </label>
                        </th>
                        <td>
                            <p class="description"><?php _e('Las citas se confirman automáticamente sin necesidad de asignar profesional', 'medical-appointments'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="allow_patient_professional_selection">
                                <input type="checkbox" name="allow_patient_professional_selection" id="allow_patient_professional_selection" value="1" <?php checked($settings['allow_patient_professional_selection'], 1); ?>>
                                <?php _e('Permitir selección de profesional', 'medical-appointments'); ?>
                            </label>
                        </th>
                        <td>
                            <p class="description"><?php _e('Permitir que los pacientes escojan al profesional al agendar su cita', 'medical-appointments'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Configuración de Notificaciones -->
            <div class="mas-settings-section">
                <h2><?php _e('Configuración de Notificaciones', 'medical-appointments'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="enable_notifications">
                                <input type="checkbox" name="enable_notifications" id="enable_notifications" value="1" <?php checked($settings['enable_notifications'], 1); ?>>
                                <?php _e('Habilitar notificaciones', 'medical-appointments'); ?>
                            </label>
                        </th>
                        <td>
                            <p class="description"><?php _e('Enviar notificaciones por email para citas y arriendos', 'medical-appointments'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="admin_email"><?php _e('Email del administrador', 'medical-appointments'); ?></label>
                        </th>
                        <td>
                            <input type="email" name="admin_email" id="admin_email" value="<?php echo esc_attr($settings['admin_email']); ?>" class="regular-text">
                            <p class="description"><?php _e('Email donde se recibirán las notificaciones administrativas', 'medical-appointments'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Shortcodes -->
            <div class="mas-settings-section">
                <h2><?php _e('Shortcodes Disponibles', 'medical-appointments'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Formulario de Citas', 'medical-appointments'); ?></th>
                        <td>
                            <code>[mas_appointment_form]</code>
                            <p class="description"><?php _e('Muestra el formulario de agendamiento de citas', 'medical-appointments'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Arriendo de Boxes', 'medical-appointments'); ?></th>
                        <td>
                            <code>[mas_box_rental]</code>
                            <p class="description"><?php _e('Muestra el formulario de arriendo de boxes', 'medical-appointments'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <p class="submit">
            <input type="submit" name="mas_save_settings" class="button button-primary" value="<?php _e('Guardar Configuración', 'medical-appointments'); ?>">
        </p>
    </form>
    
    <!-- Separated blocked dates form from main settings form to fix nested forms issue -->
    <div class="mas-settings-container" style="margin-top: 30px;">
        <!-- Blocked Dates Section -->
        <div class="mas-settings-section">
            <h2><?php _e('Fechas Bloqueadas', 'medical-appointments'); ?></h2>
            <p class="description"><?php _e('Bloquee fechas específicas para evitar reservas y citas en días festivos o no laborables', 'medical-appointments'); ?></p>
            
            <!-- Add blocked date form (now independent) -->
            <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3><?php _e('Agregar Nueva Fecha Bloqueada', 'medical-appointments'); ?></h3>
                <form method="post" action="" style="display: grid; grid-template-columns: 1fr 2fr 1fr 1fr 1fr; gap: 10px; align-items: end;">
                    <?php wp_nonce_field('mas_blocked_date_nonce'); ?>
                    <div>
                        <label for="blocked_date" style="display: block; margin-bottom: 5px;"><strong><?php _e('Fecha', 'medical-appointments'); ?></strong></label>
                        <input type="date" name="blocked_date" id="blocked_date" required style="width: 100%;">
                    </div>
                    <div>
                        <label for="reason" style="display: block; margin-bottom: 5px;"><strong><?php _e('Motivo', 'medical-appointments'); ?></strong></label>
                        <input type="text" name="reason" id="reason" placeholder="<?php _e('Ej: Feriado Nacional', 'medical-appointments'); ?>" style="width: 100%;">
                    </div>
                    <div>
                        <label style="display: block;">
                            <input type="checkbox" name="blocks_appointments" value="1" checked>
                            <?php _e('Bloquear Citas', 'medical-appointments'); ?>
                        </label>
                    </div>
                    <div>
                        <label style="display: block;">
                            <input type="checkbox" name="blocks_rentals" value="1" checked>
                            <?php _e('Bloquear Arriendos', 'medical-appointments'); ?>
                        </label>
                    </div>
                    <div>
                        <button type="submit" name="mas_add_blocked_date" class="button button-primary" style="width: 100%;">
                            <?php _e('Agregar', 'medical-appointments'); ?>
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- List of blocked dates -->
            <?php if (!empty($blocked_dates)) : ?>
                <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                    <thead>
                        <tr>
                            <th><?php _e('Fecha', 'medical-appointments'); ?></th>
                            <th><?php _e('Motivo', 'medical-appointments'); ?></th>
                            <th><?php _e('Bloquea Citas', 'medical-appointments'); ?></th>
                            <th><?php _e('Bloquea Arriendos', 'medical-appointments'); ?></th>
                            <th><?php _e('Acciones', 'medical-appointments'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($blocked_dates as $blocked_date) : ?>
                            <tr>
                                <td><strong><?php 
                                    echo date_i18n('l, j', strtotime($blocked_date->blocked_date)) . ' de ' . 
                                         date_i18n('F', strtotime($blocked_date->blocked_date)) . ' de ' . 
                                         date_i18n('Y', strtotime($blocked_date->blocked_date)); 
                                ?></strong></td>
                                <td><?php echo esc_html($blocked_date->reason); ?></td>
                                <td>
                                    <?php if ($blocked_date->blocks_appointments) : ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                    <?php else : ?>
                                        <span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($blocked_date->blocks_rentals) : ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                    <?php else : ?>
                                        <span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo wp_nonce_url(add_query_arg('delete_blocked_date', $blocked_date->id), 'mas_delete_blocked_date_' . $blocked_date->id); ?>" 
                                       class="button button-small" 
                                       onclick="return confirm('<?php _e('¿Está seguro de eliminar esta fecha bloqueada?', 'medical-appointments'); ?>')">
                                        <?php _e('Eliminar', 'medical-appointments'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p style="color: #666; font-style: italic;"><?php _e('No hay fechas bloqueadas configuradas.', 'medical-appointments'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (current_user_can('manage_options') && isset($_GET['debug'])) : ?>
        <div style="margin-top: 20px; padding: 20px; background: #f0f0f0; border: 1px solid #ccc; border-radius: 5px;">
            <h3>Información de Debug</h3>
            
            <h4>Valores Actuales en Base de Datos:</h4>
            <pre style="background: white; padding: 15px; overflow: auto;"><?php 
                $current_settings = get_option('mas_settings', array());
                print_r($current_settings); 
            ?></pre>
            
            <h4>Consulta SQL para verificar manualmente:</h4>
            <code style="display: block; background: white; padding: 15px; margin: 10px 0;">
                SELECT * FROM <?php echo $wpdb->prefix; ?>options WHERE option_name = 'mas_settings';
            </code>
            
            <h4>Post Data (si se envió formulario):</h4>
            <pre style="background: white; padding: 15px; overflow: auto;"><?php 
                if ($_POST && isset($_POST['mas_save_settings'])) {
                    print_r($_POST);
                } else {
                    echo "No se ha enviado el formulario aún.";
                }
            ?></pre>
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    $('.day-enabled-checkbox').on('change', function() {
        const day = $(this).data('day');
        const isEnabled = $(this).is(':checked');
        const row = $(this).closest('.schedule-day-row');
        
        row.find('.time-select').prop('disabled', !isEnabled);
        
        if (isEnabled) {
            row.css('opacity', '10');
        } else {
            row.css('opacity', '0.5');
        }
    });
    
    $('#slot_duration').on('change', function() {
        const slotMinutes = parseInt($(this).val());
        
        // Regenerate all time selects
        $('.time-select').each(function() {
            const currentValue = $(this).val();
            const isStartTime = $(this).hasClass('start-time-select');
            
            // Clear and rebuild options
            $(this).empty();
            
            let start = new Date('2000-01-01 00:00:00');
            let end = new Date('2000-01-01 23:59:00');
            
            for (let time = start; time <= end; time.setMinutes(time.getMinutes() + slotMinutes)) {
                const hours = String(time.getHours()).padStart(2, '0');
                const minutes = String(time.getMinutes()).padStart(2, '0');
                const timeString = `${hours}:${minutes}`;
                
                const option = $('<option></option>')
                    .attr('value', timeString)
                    .text(timeString);
                
                // Check if this newly generated time matches the previous value
                if (timeString === currentValue) {
                    option.attr('selected', 'selected');
                }
                
                $(this).append(option);
            }
        });
        
        console.log('[v0] Time selects regenerated for slot duration:', slotMinutes);
    });
    
    // Initialize disabled state on page load
    $('.day-enabled-checkbox').each(function() {
        $(this).trigger('change');
    });
});
</script>
