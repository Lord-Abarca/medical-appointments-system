<?php
/**
 * Dashboard para profesionales (colaboradores)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verificar que sea un colaborador
if (!current_user_can('edit_posts')) {
    wp_die(__('No tienes permisos suficientes para acceder a esta página.', 'medical-appointments'));
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Obtener profesional asociado
global $wpdb;
$professional = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}mas_professionals WHERE user_id = %d",
    $user_id
));

if (!$professional) {
    wp_die(__('No se encontró tu perfil de profesional. Contacta al administrador.', 'medical-appointments'));
}

error_log('[MAS] Professional Dashboard - User ID: ' . $user_id);
error_log('[MAS] Professional Dashboard - Professional ID: ' . $professional->id);

// Obtener estadísticas del profesional
$today = date('Y-m-d');
$this_month_start = date('Y-m-01');
$this_month_end = date('Y-m-t');

// Citas del profesional
$appointments_today = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}mas_appointments 
    WHERE professional_id = %d AND appointment_date = %s AND status IN ('pending', 'scheduled')",
    $professional->id, $today
));

$appointments_month = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}mas_appointments 
    WHERE professional_id = %d AND appointment_date BETWEEN %s AND %s",
    $professional->id, $this_month_start, $this_month_end
));

// Arriendos del profesional
$rentals_today = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}mas_box_rentals 
    WHERE professional_id = %d AND rental_date = %s AND status IN ('pending', 'active')",
    $professional->id, $today
));

$rentals_month = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}mas_box_rentals 
    WHERE professional_id = %d AND rental_date BETWEEN %s AND %s",
    $professional->id, $this_month_start, $this_month_end
));

$pending_rentals_query = $wpdb->prepare(
    "SELECT 
        external_reference,
        payment_url,
        MIN(created_at) as created_at,
        MIN(rental_date) as rental_date,
        SUM(total_price) as total_price,
        COUNT(*) as items_count
     FROM {$wpdb->prefix}mas_box_rentals 
     WHERE professional_id = %d 
     AND payment_status = 'pending' 
     AND payment_url IS NOT NULL 
     AND payment_url != ''
     AND external_reference IS NOT NULL
     AND external_reference != ''
     GROUP BY external_reference, payment_url
     ORDER BY MIN(created_at) DESC",
    $professional->id
);
error_log('[MAS] Professional Dashboard - Query executed: ' . str_replace("\n", " ", $pending_rentals_query));
$pending_rentals_with_payment = $wpdb->get_results($pending_rentals_query);
error_log('[MAS] Professional Dashboard - Pending rentals found: ' . count($pending_rentals_with_payment));

// Próximas citas
$upcoming_appointments = $wpdb->get_results($wpdb->prepare(
    "SELECT a.*, s.service_name 
    FROM {$wpdb->prefix}mas_appointments a
    LEFT JOIN {$wpdb->prefix}mas_services s ON a.service_id = s.id
    WHERE a.professional_id = %d 
    AND a.appointment_date >= %s 
    AND a.status IN ('pending', 'scheduled')
    ORDER BY a.appointment_date, a.appointment_time
    LIMIT 25",
    $professional->id, $today
));

// Próximos arriendos
$upcoming_rentals = $wpdb->get_results($wpdb->prepare(
    "SELECT r.*, b.box_name, b.box_number
    FROM {$wpdb->prefix}mas_box_rentals r
    LEFT JOIN {$wpdb->prefix}mas_boxes b ON r.box_id = b.id
    WHERE r.professional_id = %d 
    AND r.rental_date >= %s 
    AND r.status IN ('pending', 'active', 'pending_payment')
    ORDER BY r.rental_date, r.start_time
    LIMIT 25",
    $professional->id, $today
));

// Obtener servicios del profesional
$services = $wpdb->get_results($wpdb->prepare(
    "SELECT s.* FROM {$wpdb->prefix}mas_services s
    INNER JOIN {$wpdb->prefix}mas_professional_services ps ON s.id = ps.service_id
    WHERE ps.professional_id = %d AND s.status = 'active'",
    $professional->id
));

$promotions_class = new MAS_Promotions();
$active_promotions = $promotions_class->get_active_promotions();
?>

<div class="wrap mas-professional-dashboard">
    <h1><?php printf(__('Bienvenido, %s', 'medical-appointments'), $current_user->display_name); ?></h1>
    
    <div class="mas-dashboard-stats">
        <div class="mas-stat-box">
            <div class="mas-stat-icon">
                <span class="dashicons dashicons-calendar-alt"></span>
            </div>
            <div class="mas-stat-content">
                <h3><?php echo $appointments_today; ?></h3>
                <p><?php _e('Citas Hoy', 'medical-appointments'); ?></p>
            </div>
        </div>
        
        <div class="mas-stat-box">
            <div class="mas-stat-icon">
                <span class="dashicons dashicons-calendar"></span>
            </div>
            <div class="mas-stat-content">
                <h3><?php echo $appointments_month; ?></h3>
                <p><?php _e('Citas Este Mes', 'medical-appointments'); ?></p>
            </div>
        </div>
        
        <div class="mas-stat-box">
            <div class="mas-stat-icon">
                <span class="dashicons dashicons-building"></span>
            </div>
            <div class="mas-stat-content">
                <h3><?php echo $rentals_today; ?></h3>
                <p><?php _e('Arriendos Hoy', 'medical-appointments'); ?></p>
            </div>
        </div>
        
        <div class="mas-stat-box">
            <div class="mas-stat-icon">
                <span class="dashicons dashicons-store"></span>
            </div>
            <div class="mas-stat-content">
                <h3><?php echo $rentals_month; ?></h3>
                <p><?php _e('Arriendos Este Mes', 'medical-appointments'); ?></p>
            </div>
        </div>
    </div>
    
    <?php // Agregar sección de promociones activas ?>
    <?php if (!empty($active_promotions)) : ?>
        <div class="mas-promotions-showcase">
            <div class="mas-section-header">
                <h2><?php _e('Promociones Disponibles', 'medical-appointments'); ?></h2>
            </div>
            <div class="mas-promotions-grid">
                <?php foreach ($active_promotions as $promo) : ?>
                    <div class="mas-promotion-card">
                        <div class="mas-promotion-badge">
                            <span class="dashicons dashicons-tag"></span>
                            <span class="discount-label"><?php echo number_format($promo->discount_percentage, 0); ?>% OFF</span>
                        </div>
                        <div class="mas-promotion-content">
                            <h3><?php echo esc_html($promo->name); ?></h3>
                            <p class="promo-description"><?php echo esc_html($promo->description); ?></p>
                            <div class="promo-details">
                                <div class="promo-blocks">
                                    <span class="dashicons dashicons-clock"></span>
                                    <strong><?php echo $promo->blocks_quantity; ?></strong> bloques
                                </div>
                                <div class="promo-price">
                                    <span class="price-label">Precio paquete:</span>
                                    <strong class="price-value">$<?php echo number_format($promo->package_price, 0, ',', '.'); ?></strong>
                                </div>
                            </div>
                            <?php if ($promo->end_date) : ?>
                                <div class="promo-expiry">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    Válido hasta: <?php echo date_i18n('d/m/Y', strtotime($promo->end_date)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="mas-promotion-action">
                            <a href="<?php echo admin_url('admin.php?page=mas-rent-box'); ?>" class="button button-primary button-large">
                                <span class="dashicons dashicons-building"></span>
                                <?php _e('Arrendar Box', 'medical-appointments'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="mas-dashboard-grid">
        <div class="mas-dashboard-section">
            <div class="mas-section-header">
                <h2><?php _e('Próximas Citas', 'medical-appointments'); ?></h2>
                <a href="<?php echo admin_url('admin.php?page=mas-my-appointments'); ?>" class="button">
                    <?php _e('Ver Todas', 'medical-appointments'); ?>
                </a>
            </div>
            <div class="mas-section-content">
                <?php if (!empty($upcoming_appointments)) : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Paciente', 'medical-appointments'); ?></th>
                                <th><?php _e('Servicio', 'medical-appointments'); ?></th>
                                <th><?php _e('Fecha', 'medical-appointments'); ?></th>
                                <th><?php _e('Hora', 'medical-appointments'); ?></th>
                                <th><?php _e('Estado', 'medical-appointments'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_appointments as $appointment) : ?>
                                <tr>
                                    <td><?php echo esc_html($appointment->patient_name); ?></td>
                                    <td><?php echo esc_html($appointment->service_name ?: 'N/A'); ?></td>
                                    <td><?php echo date_i18n('d/m/Y', strtotime($appointment->appointment_date)); ?></td>
                                    <td><?php echo esc_html($appointment->appointment_time); ?></td>
                                    <td>
                                        <span class="mas-status-badge mas-status-<?php echo esc_attr($appointment->status); ?>">
                                            <?php echo esc_html(ucfirst($appointment->status)); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p class="mas-no-data"><?php _e('No tienes citas próximas.', 'medical-appointments'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mas-dashboard-section">
            <div class="mas-section-header">
                <h2><?php _e('Próximos Arriendos', 'medical-appointments'); ?></h2>
                <a href="<?php echo admin_url('admin.php?page=mas-my-rentals'); ?>" class="button">
                    <?php _e('Ver Todos', 'medical-appointments'); ?>
                </a>
            </div>
            <div class="mas-section-content">
                <?php if (!empty($upcoming_rentals)) : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Box', 'medical-appointments'); ?></th>
                                <th><?php _e('Fecha', 'medical-appointments'); ?></th>
                                <th><?php _e('Horario', 'medical-appointments'); ?></th>
                                <th><?php _e('Total', 'medical-appointments'); ?></th>
                                <th><?php _e('Estado', 'medical-appointments'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_rentals as $rental) : ?>
                                <tr>
                                    <td><?php echo esc_html($rental->box_name . ' (' . $rental->box_number . ')'); ?></td>
                                    <td><?php echo date_i18n('d/m/Y', strtotime($rental->rental_date)); ?></td>
                                    <td><?php echo esc_html($rental->start_time . ' - ' . $rental->end_time); ?></td>
                                    <td>$<?php echo number_format($rental->total_price, 0, ',', '.'); ?></td>
                                    <td>
                                        <span class="mas-status-badge mas-status-<?php echo esc_attr($rental->status); ?>">
                                            <?php echo esc_html(ucfirst($rental->status)); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p class="mas-no-data"><?php _e('No tienes arriendos próximos.', 'medical-appointments'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mas-dashboard-section">
            <div class="mas-section-header">
                <h2><?php _e('Mis Servicios', 'medical-appointments'); ?></h2>
            </div>
            <div class="mas-section-content">
                <?php if (!empty($services)) : ?>
                    <div class="mas-services-list">
                        <?php foreach ($services as $service) : ?>
                            <div class="mas-service-item">
                                <h4><?php echo esc_html($service->service_name); ?></h4>
                                <p><?php echo esc_html($service->description); ?></p>
                                <div class="mas-service-meta">
                                    <span><strong><?php _e('Duración:', 'medical-appointments'); ?></strong> <?php echo $service->duration; ?> min</span>
                                    <span><strong><?php _e('Precio:', 'medical-appointments'); ?></strong> $<?php echo number_format($service->price, 0, ',', '.'); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p class="mas-no-data"><?php _e('No tienes servicios asignados. Contacta al administrador.', 'medical-appointments'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mas-dashboard-section">
            <div class="mas-section-header">
                <h2><?php _e('Acciones Rápidas', 'medical-appointments'); ?></h2>
            </div>
            <div class="mas-section-content">
                <div class="mas-quick-actions">
                    <a href="<?php echo admin_url('admin.php?page=mas-rent-box'); ?>" class="mas-action-button">
                        <span class="dashicons dashicons-building"></span>
                        <?php _e('Arrendar Box', 'medical-appointments'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=mas-my-appointments'); ?>" class="mas-action-button">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php _e('Ver Mis Citas', 'medical-appointments'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=mas-my-rentals'); ?>" class="mas-action-button">
                        <span class="dashicons dashicons-store"></span>
                        <?php _e('Ver Mis Arriendos', 'medical-appointments'); ?>
                    </a>
                </div>
            </div>
        </div>
        
        <?php if (!empty($pending_rentals_with_payment)) : ?>
            <div class="mas-dashboard-section">
                <div class="mas-section-header">
                    <h2><?php _e('Arriendos Pendientes de Pago', 'medical-appointments'); ?></h2>
                </div>
                <div class="mas-section-content">
                    <p><?php printf(__('Tienes %d pago(s) pendiente(s).', 'medical-appointments'), count($pending_rentals_with_payment)); ?></p>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Referencia', 'medical-appointments'); ?></th>
                                <th><?php _e('Fecha Creación', 'medical-appointments'); ?></th>
                                <th><?php _e('Fecha Arriendo', 'medical-appointments'); ?></th>
                                <th><?php _e('Bloques', 'medical-appointments'); ?></th>
                                <th><?php _e('Total', 'medical-appointments'); ?></th>
                                <th><?php _e('Acción', 'medical-appointments'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_rentals_with_payment as $rental) : ?>
                                <tr>
                                    <td><code><?php echo esc_html($rental->external_reference); ?></code></td>
                                    <td><?php echo date_i18n('d/m/Y H:i', strtotime($rental->created_at)); ?></td>
                                    <td><?php echo date_i18n('d/m/Y', strtotime($rental->rental_date)); ?></td>
                                    <td><?php echo intval($rental->items_count); ?> bloque(s)</td>
                                    <td>$<?php echo number_format($rental->total_price, 0, ',', '.'); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url($rental->payment_url); ?>" class="button button-primary button-small" target="_blank">
                                            <?php _e('Pagar Ahora', 'medical-appointments'); ?>
                                        </a>
                                        <!-- Added delete button for pending payments -->
                                        <button class="button button-small mas-delete-pending-payment" 
                                                data-reference="<?php echo esc_attr($rental->external_reference); ?>"
                                                style="margin-left: 5px;">
                                            <?php _e('Eliminar', 'medical-appointments'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.mas-professional-dashboard {
    margin: 20px 0;
}

.mas-dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

.mas-stat-box {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 15px;
}

.mas-stat-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.mas-stat-icon .dashicons {
    font-size: 30px;
    width: 30px;
    height: 30px;
    color: white;
}

.mas-stat-content h3 {
    margin: 0;
    font-size: 32px;
    color: #2c3e50;
}

.mas-stat-content p {
    margin: 5px 0 0 0;
    color: #7f8c8d;
    font-size: 14px;
}

.mas-dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-top: 30px;
}

.mas-dashboard-section {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

.mas-section-header {
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.mas-section-header h2 {
    margin: 0;
    font-size: 18px;
}

.mas-section-content {
    padding: 20px;
}

.mas-no-data {
    text-align: center;
    color: #7f8c8d;
    padding: 30px;
}

.mas-status-badge {
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.mas-status-pending {
    background: #ffeaa7;
    color: #d63031;
}

.mas-status-scheduled,
.mas-status-active {
    background: #55efc4;
    color: #00b894;
}

.mas-status-cancelled {
    background: #dfe6e9;
    color: #636e72;
}

.mas-status-pending_payment {
    background: #fdcb6e;
    color: #e17055;
}

.mas-services-list {
    display: grid;
    gap: 15px;
}

.mas-service-item {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 4px solid #667eea;
}

.mas-service-item h4 {
    margin: 0 0 8px 0;
    color: #2c3e50;
}

.mas-service-item p {
    margin: 0 0 10px 0;
    color: #7f8c8d;
    font-size: 14px;
}

.mas-service-meta {
    display: flex;
    gap: 20px;
    font-size: 13px;
    color: #95a5a6;
}

.mas-quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}

.mas-action-button {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 30px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    transition: transform 0.3s;
    gap: 10px;
}

.mas-action-button:hover {
    transform: translateY(-2px);
    color: white;
}

.mas-action-button .dashicons {
    font-size: 40px;
    width: 40px;
    height: 40px;
}

.mas-promotions-showcase {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.mas-promotions-showcase .mas-section-header {
    margin-bottom: 20px;
    border-bottom: 2px solid #0073aa;
    padding-bottom: 10px;
}

.mas-promotions-showcase .mas-section-header h2 {
    margin: 0;
    color: #0073aa;
    font-size: 20px;
}

.mas-promotions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.mas-promotion-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    padding: 20px;
    color: #fff;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.mas-promotion-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.2);
}

.mas-promotion-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    pointer-events: none;
}

.mas-promotion-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 15px;
    background: rgba(255,255,255,0.2);
    padding: 8px 12px;
    border-radius: 20px;
    width: fit-content;
}

.mas-promotion-badge .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
}

.mas-promotion-badge .discount-label {
    font-weight: bold;
    font-size: 16px;
}

.mas-promotion-content h3 {
    margin: 0 0 10px 0;
    font-size: 22px;
    color: #fff;
}

.promo-description {
    margin: 0 0 15px 0;
    opacity: 0.95;
    line-height: 1.5;
}

.promo-details {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin: 15px 0;
    padding: 15px;
    background: rgba(255,255,255,0.15);
    border-radius: 8px;
}

.promo-blocks,
.promo-price {
    display: flex;
    align-items: center;
    gap: 8px;
}

.promo-blocks .dashicons {
    width: 20px;
    height: 20px;
}

.promo-price {
    flex-direction: column;
    align-items: flex-start;
}

.price-label {
    font-size: 12px;
    opacity: 0.9;
}

.price-value {
    font-size: 24px;
    font-weight: bold;
}

.promo-expiry {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 10px;
    font-size: 13px;
    opacity: 0.9;
}

.promo-expiry .dashicons {
    width: 16px;
    height: 16px;
    font-size: 16px;
}

.mas-promotion-action {
    margin-top: 20px;
}

.mas-promotion-action .button {
    width: 100%;
    justify-content: center;
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    color: #667eea;
    border: none;
    font-weight: bold;
    padding: 12px 20px;
    font-size: 16px;
}

.mas-promotion-action .button:hover {
    background: #f0f0f0;
    color: #764ba2;
}

.mas-promotion-action .button .dashicons {
    width: 20px;
    height: 20px;
    font-size: 20px;
}
</style>

<!-- Added JavaScript to handle delete pending payment -->
<script>
jQuery(document).ready(function($) {
    $('.mas-delete-pending-payment').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('<?php _e('¿Estás seguro de eliminar este pago pendiente? Se eliminarán todos los bloques asociados.', 'medical-appointments'); ?>')) {
            return;
        }
        
        const button = $(this);
        const reference = button.data('reference');
        const row = button.closest('tr');
        
        button.prop('disabled', true).text('<?php _e('Eliminando...', 'medical-appointments'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mas_delete_pending_payment',
                nonce: '<?php echo wp_create_nonce('mas_delete_pending_payment'); ?>',
                external_reference: reference
            },
            success: function(response) {
                if (response.success) {
                    row.fadeOut(400, function() {
                        $(this).remove();
                        
                        const remainingRows = $('.mas-delete-pending-payment').length;
                        if (remainingRows === 0) {
                            location.reload();
                        }
                    });
                } else {
                    alert(response.data.message || '<?php _e('Error al eliminar el pago pendiente', 'medical-appointments'); ?>');
                    button.prop('disabled', false).text('<?php _e('Eliminar', 'medical-appointments'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('Error de conexión', 'medical-appointments'); ?>');
                button.prop('disabled', false).text('<?php _e('Eliminar', 'medical-appointments'); ?>');
            }
        });
    });
});
</script>
