<?php
/**
 * Mis Arriendos - Vista del profesional
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

// Filtros
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$payment_filter = isset($_GET['payment']) ? sanitize_text_field($_GET['payment']) : '';
$date_filter = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';

// Construir query
$where = array("r.professional_id = {$professional->id}");

if ($status_filter) {
    $where[] = $wpdb->prepare("r.status = %s", $status_filter);
}

if ($payment_filter) {
    $where[] = $wpdb->prepare("r.payment_status = %s", $payment_filter);
}

if ($date_filter) {
    $where[] = $wpdb->prepare("r.rental_date = %s", $date_filter);
}

$where_clause = implode(' AND ', $where);

$rentals = $wpdb->get_results(
    "SELECT r.*, b.box_name, b.box_number
    FROM {$wpdb->prefix}mas_box_rentals r
    LEFT JOIN {$wpdb->prefix}mas_boxes b ON r.box_id = b.id
    WHERE {$where_clause}
    ORDER BY r.rental_date DESC, r.start_time DESC
    LIMIT 100"
);
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Mis Arriendos', 'medical-appointments'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=mas-rent-box'); ?>" class="page-title-action"><?php _e('Arrendar Box', 'medical-appointments'); ?></a>
    
    <div class="mas-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="mas-my-rentals">
            
            <select name="status">
                <option value=""><?php _e('Todos los estados', 'medical-appointments'); ?></option>
                <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php _e('Pendiente', 'medical-appointments'); ?></option>
                <option value="active" <?php selected($status_filter, 'active'); ?>><?php _e('Activo', 'medical-appointments'); ?></option>
                <option value="completed" <?php selected($status_filter, 'completed'); ?>><?php _e('Completado', 'medical-appointments'); ?></option>
                <option value="cancelled" <?php selected($status_filter, 'cancelled'); ?>><?php _e('Cancelado', 'medical-appointments'); ?></option>
            </select>
            
            <select name="payment">
                <option value=""><?php _e('Estado de pago', 'medical-appointments'); ?></option>
                <option value="pending" <?php selected($payment_filter, 'pending'); ?>><?php _e('Pendiente', 'medical-appointments'); ?></option>
                <option value="paid" <?php selected($payment_filter, 'paid'); ?>><?php _e('Pagado', 'medical-appointments'); ?></option>
                <option value="failed" <?php selected($payment_filter, 'failed'); ?>><?php _e('Fallido', 'medical-appointments'); ?></option>
            </select>
            
            <input type="date" name="date" value="<?php echo esc_attr($date_filter); ?>" placeholder="<?php _e('Fecha', 'medical-appointments'); ?>">
            
            <button type="submit" class="button"><?php _e('Filtrar', 'medical-appointments'); ?></button>
            <a href="<?php echo admin_url('admin.php?page=mas-my-rentals'); ?>" class="button"><?php _e('Limpiar', 'medical-appointments'); ?></a>
        </form>
    </div>
    
    <div class="mas-table-container">
        <?php if (!empty($rentals)) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'medical-appointments'); ?></th>
                        <th><?php _e('Box', 'medical-appointments'); ?></th>
                        <th><?php _e('Fecha', 'medical-appointments'); ?></th>
                        <th><?php _e('Horario', 'medical-appointments'); ?></th>
                        <th><?php _e('Horas', 'medical-appointments'); ?></th>
                        <th><?php _e('Total', 'medical-appointments'); ?></th>
                        <th><?php _e('Estado', 'medical-appointments'); ?></th>
                        <th><?php _e('Pago', 'medical-appointments'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rentals as $rental) : ?>
                        <tr>
                            <td><?php echo esc_html($rental->id); ?></td>
                            <td>
                                <strong><?php echo esc_html($rental->box_name); ?></strong><br>
                                <small><?php echo esc_html($rental->box_number); ?></small>
                            </td>
                            <td><?php echo date_i18n('d/m/Y', strtotime($rental->rental_date)); ?></td>
                            <td><?php echo esc_html($rental->start_time . ' - ' . $rental->end_time); ?></td>
                            <td><?php echo esc_html($rental->total_hours); ?></td>
                            <td>$<?php echo number_format($rental->total_price, 0, ',', '.'); ?></td>
                            <td>
                                <span class="mas-status-badge mas-status-<?php echo esc_attr($rental->status); ?>">
                                    <?php echo esc_html(ucfirst($rental->status)); ?>
                                </span>
                            </td>
                            <td>
                                <span class="mas-status-badge mas-payment-<?php echo esc_attr($rental->payment_status); ?>">
                                    <?php echo esc_html(ucfirst($rental->payment_status)); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <div class="mas-no-results">
                <p><?php _e('No se encontraron arriendos.', 'medical-appointments'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=mas-rent-box'); ?>" class="button button-primary"><?php _e('Arrendar un Box', 'medical-appointments'); ?></a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.mas-filters {
    background: #fff;
    padding: 15px;
    margin: 20px 0;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.mas-filters form {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.mas-filters select,
.mas-filters input[type="date"] {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.mas-table-container {
    background: #fff;
    padding: 20px;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow-x: auto;
}

.mas-no-results {
    text-align: center;
    padding: 40px;
    color: #7f8c8d;
}

.mas-no-results p {
    margin-bottom: 20px;
}

.mas-status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.mas-status-pending,
.mas-payment-pending {
    background: #ffeaa7;
    color: #d63031;
}

.mas-status-active {
    background: #55efc4;
    color: #00b894;
}

.mas-status-completed {
    background: #74b9ff;
    color: #0984e3;
}

.mas-status-cancelled {
    background: #dfe6e9;
    color: #636e72;
}

.mas-payment-paid {
    background: #55efc4;
    color: #00b894;
}

.mas-payment-failed {
    background: #ff7675;
    color: #d63031;
}
</style>
