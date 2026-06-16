<?php
/**
 * Página de gestión de arriendos
 */

if (!defined('ABSPATH')) {
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

$mas_rentals = new MAS_Rentals();
$mas_boxes = new MAS_Boxes();
$mas_professionals = new MAS_Professionals();

// Procesar acciones
if (isset($_GET['action']) && isset($_GET['id'])) {
    $rental_id = intval($_GET['id']);
    
    if ($_GET['action'] === 'delete' && check_admin_referer('mas_delete_rental_' . $rental_id)) {
        if ($mas_rentals->delete_rental($rental_id)) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Arriendo eliminado exitosamente.', 'medical-appointments') . '</p></div>';
        }
    }
    
    if ($_GET['action'] === 'cancel' && check_admin_referer('mas_cancel_rental_' . $rental_id)) {
        if ($mas_rentals->update_rental($rental_id, array('status' => 'cancelled'))) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Arriendo cancelado exitosamente.', 'medical-appointments') . '</p></div>';
        }
    }
}

// Guardar/actualizar arriendo
if (isset($_POST['mas_save_rental']) && check_admin_referer('mas_rental_nonce')) {
    $rental_id = isset($_POST['rental_id']) ? intval($_POST['rental_id']) : 0;
    
    error_log('[MAS] Form submission detected');
    error_log('[MAS] POST data: ' . print_r($_POST, true));
    
    $rental_time = isset($_POST['rental_time']) ? sanitize_text_field($_POST['rental_time']) : '';
    error_log('[MAS] rental_time value: ' . $rental_time);
    
    $time_slots = !empty($rental_time) ? explode(',', $rental_time) : array();
    error_log('[MAS] time_slots array: ' . print_r($time_slots, true));
    
    if (empty($time_slots)) {
        error_log('[MAS] ERROR: No time slots provided');
        echo '<div class="notice notice-error is-dismissible"><p>' . __('Error: Debe seleccionar al menos un horario.', 'medical-appointments') . '</p></div>';
    } else {
        $block_duration = get_option('mas_box_rental_block_duration', 60); // in minutes
        $box_id = intval($_POST['box_id']);
        $professional_id = intval($_POST['professional_id']);
        $rental_date = sanitize_text_field($_POST['rental_date']);
        $status = sanitize_text_field($_POST['status']);
        $payment_status = sanitize_text_field($_POST['payment_status']);
        $payment_method = !empty($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : null;
        $notes = sanitize_textarea_field($_POST['notes']);
        
        // Get box price
        global $wpdb;
        $box = $wpdb->get_row($wpdb->prepare(
            "SELECT price_per_hour FROM {$wpdb->prefix}mas_boxes WHERE id = %d",
            $box_id
        ));
        $price_per_block = $box ? floatval($box->price_per_hour) : 0;
        
        // Check if editing or creating
        if ($rental_id > 0) {
            // EDITING: Update only the selected rental
            sort($time_slots);
            $start_time = reset($time_slots);
            $last_slot = end($time_slots);
            $end_timestamp = strtotime($last_slot) + ($block_duration * 60);
            $end_time = date('H:i', $end_timestamp);
            
            $data = array(
                'box_id' => $box_id,
                'professional_id' => $professional_id,
                'rental_date' => $rental_date,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'status' => $status,
                'payment_status' => $payment_status,
                'payment_method' => $payment_method,
                'notes' => $notes
            );
            
            if ($mas_rentals->update_rental($rental_id, $data)) {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Arriendo actualizado exitosamente.', 'medical-appointments') . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Error: El box no está disponible en ese horario.', 'medical-appointments') . '</p></div>';
            }
        } else {
            // CREATING: Create one rental per slot
            $created_count = 0;
            $failed_count = 0;
            
            // Generate reference_id for this group of rentals
            $reference_id = 'RENT' . time(); // Temporary, will be updated after first insert
            
            foreach ($time_slots as $index => $time_slot) {
                $start_time = trim($time_slot);
                $end_timestamp = strtotime($start_time) + ($block_duration * 60);
                $end_time = date('H:i', $end_timestamp);
                
                $data = array(
                    'box_id' => $box_id,
                    'professional_id' => $professional_id,
                    'rental_date' => $rental_date,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'total_hours' => $block_duration / 60,
                    'total_price' => $price_per_block,
                    'status' => $status,
                    'payment_status' => $payment_status,
                    'payment_method' => $payment_method,
                    'notes' => $notes
                );
                
                // Add reference_id after first rental is created
                if ($index > 0) {
                    $data['reference_id'] = $reference_id;
                    $data['external_reference'] = $reference_id;
                }
                
                $result = $mas_rentals->create_rental($data);
                
                if ($result) {
                    // Update reference_id using the first rental ID
                    if ($index === 0) {
                        $reference_id = 'RENT' . $result;
                        $mas_rentals->update_rental($result, array(
                            'reference_id' => $reference_id,
                            'external_reference' => $reference_id
                        ));
                    }
                    $created_count++;
                    error_log('[MAS] Created rental ID: ' . $result . ' for slot: ' . $start_time);
                } else {
                    $failed_count++;
                    error_log('[MAS] Failed to create rental for slot: ' . $start_time);
                }
            }
            
            if ($created_count > 0) {
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     sprintf(__('Se crearon %d arriendo(s) exitosamente.', 'medical-appointments'), $created_count) . 
                     '</p></div>';
                
                if ($failed_count > 0) {
                    echo '<div class="notice notice-warning is-dismissible"><p>' . 
                         sprintf(__('Advertencia: %d horario(s) no pudieron ser reservados (no disponibles).', 'medical-appointments'), $failed_count) . 
                         '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Error: No se pudo crear ningún arriendo. Los horarios seleccionados no están disponibles.', 'medical-appointments') . '</p></div>';
            }
        }
    }
}

// Obtener arriendo para editar
$editing_rental = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editing_rental = $mas_rentals->get_rental(intval($_GET['id']));
}

// Filtros
$box_filter = isset($_GET['box_id']) ? intval($_GET['box_id']) : '';
$professional_filter = isset($_GET['professional_id']) ? intval($_GET['professional_id']) : '';
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
$hide_monthly = isset($_GET['hide_monthly']) ? intval($_GET['hide_monthly']) : 1; // Ocultar mensuales por defecto

$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 25;
if (!in_array($per_page, [25, 50, 100])) {
    $per_page = 25;
}
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Get total count for pagination
global $wpdb;
$where_conditions = ['1=1'];
$where_params = [];

if ($box_filter) {
    $where_conditions[] = 'r.box_id = %d';
    $where_params[] = $box_filter;
}
if ($professional_filter) {
    $where_conditions[] = 'r.professional_id = %d';
    $where_params[] = $professional_filter;
}
if ($date_from) {
    $where_conditions[] = 'r.rental_date >= %s';
    $where_params[] = $date_from;
}
if ($date_to) {
    $where_conditions[] = 'r.rental_date <= %s';
    $where_params[] = $date_to;
}
if ($hide_monthly) {
    $where_conditions[] = '(r.monthly_rental_id IS NULL OR r.monthly_rental_id = 0)';
}

$where_sql = implode(' AND ', $where_conditions);
$count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}mas_box_rentals r WHERE " . $where_sql;
if (!empty($where_params)) {
    $total_items = $wpdb->get_var($wpdb->prepare($count_query, $where_params));
} else {
    $total_items = $wpdb->get_var($count_query);
}
$total_pages = ceil($total_items / $per_page);


// Obtener arriendos con paginación
$rentals = $mas_rentals->get_rentals(array(
    'box_id' => $box_filter,
    'professional_id' => $professional_filter,
    'date_from' => $date_from,
    'date_to' => $date_to,
    'status' => '',
    'hide_monthly' => $hide_monthly,
    'limit' => $per_page,
    'offset' => $offset
));

$boxes = $mas_boxes->get_boxes('active');
$professionals = $mas_professionals->get_professionals('active');

// Calcular estadísticas
$stats = $mas_rentals->get_statistics($date_from, $date_to);

$statuses = array(
    'pending' => 'Pendiente',
    'active' => 'Activo',
    'completed' => 'Completado',
    'cancelled' => 'Cancelado',
    'interno' => 'Interno'
);

$payment_statuses = array(
    'pending' => 'Pendiente',
    'paid' => 'Pagado',
    'failed' => 'Fallido',
    'refunded' => 'Reembolsado',
    'interno' => 'Interno'
);
?>

<div class="wrap mas-rentals-page">
    <h1 class="wp-heading-inline"><?php _e('Gestión de Arriendos', 'medical-appointments'); ?></h1>
    <!-- Corregir el botón para abrir el modal correctamente -->
    <a href="#" class="page-title-action mas-add-rental-btn" onclick="event.preventDefault(); openAddModal();">
        <?php _e('Agregar Nuevo', 'medical-appointments'); ?>
    </a>
    <hr class="wp-header-end">
    
    <!-- Estadísticas -->
    <div class="mas-stats-grid" style="margin-bottom: 20px;">
        <div class="mas-stat-card">
            <div class="mas-stat-icon">
                <span class="dashicons dashicons-building"></span>
            </div>
            <div class="mas-stat-content">
                <h3><?php echo esc_html($stats['total_rentals']); ?></h3>
                <p><?php _e('Total Arriendos', 'medical-appointments'); ?></p>
            </div>
        </div>
        
        <div class="mas-stat-card">
            <div class="mas-stat-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="mas-stat-content">
                <h3><?php echo number_format($stats['total_hours'], 1); ?></h3>
                <p><?php _e('Horas Totales', 'medical-appointments'); ?></p>
            </div>
        </div>
        
        <div class="mas-stat-card mas-stat-revenue">
            <div class="mas-stat-icon">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div class="mas-stat-content">
                <h3>$<?php echo number_format($stats['total_revenue'], 0, ',', '.'); ?></h3>
                <p><?php _e('Ingresos Totales', 'medical-appointments'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="mas-filters">
        <form method="get" action="">
            <!-- Corregir el nombre de la página en el formulario -->
            <input type="hidden" name="page" value="medical-rentals">
            
            <select name="box_id" class="mas-filter-select">
                <option value=""><?php _e('Todos los boxes', 'medical-appointments'); ?></option>
                <?php foreach ($boxes as $box) : ?>
                    <option value="<?php echo esc_attr($box->id); ?>" <?php selected($box_filter, $box->id); ?>>
                        <?php echo esc_html($box->box_number . ' - ' . $box->box_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="professional_id" class="mas-filter-select">
                <option value=""><?php _e('Todos los profesionales', 'medical-appointments'); ?></option>
                <?php foreach ($professionals as $professional) : ?>
                    <option value="<?php echo esc_attr($professional->id); ?>" <?php selected($professional_filter, $professional->id); ?>>
                        <?php echo esc_html($professional->display_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" placeholder="<?php _e('Desde', 'medical-appointments'); ?>">
            <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" placeholder="<?php _e('Hasta', 'medical-appointments'); ?>">
            
            <label style="display: inline-flex; align-items: center; gap: 5px; margin-left: 10px;">
                <input type="checkbox" name="hide_monthly" value="1" <?php checked($hide_monthly, 1); ?>>
                <?php _e('Ocultar mensuales', 'medical-appointments'); ?>
            </label>
            
            <button type="submit" class="button"><?php _e('Filtrar', 'medical-appointments'); ?></button>
            <a href="<?php echo admin_url('admin.php?page=medical-rentals'); ?>" class="button"><?php _e('Limpiar', 'medical-appointments'); ?></a>
        </form>
    </div>
    
    <!-- Adding pagination controls above table -->
    <?php if (!empty($rentals)) : ?>
        <div class="mas-pagination-controls" style="display: flex; justify-content: space-between; align-items: center; margin: 20px 0 10px 0; padding: 15px; background: #f9f9f9; border-radius: 4px;">
            <div class="mas-per-page-selector">
                <label style="margin-right: 10px; font-weight: 600;"><?php _e('Mostrar:', 'medical-appointments'); ?></label>
                <select id="mas-per-page-select" onchange="changePerPage(this.value)" style="padding: 5px 10px; border-radius: 3px; border: 1px solid #ccc;">
                    <option value="25" <?php selected($per_page, 25); ?>>25</option>
                    <option value="50" <?php selected($per_page, 50); ?>>50</option>
                    <option value="100" <?php selected($per_page, 100); ?>>100</option>
                </select>
                <span style="margin-left: 10px; color: #666;">
                    <?php printf(__('Mostrando %d-%d de %d arriendos', 'medical-appointments'), 
                        $offset + 1, 
                        min($offset + $per_page, $total_items), 
                        $total_items
                    ); ?>
                </span>
            </div>
            
            <?php if ($total_pages > 1) : ?>
                <div class="mas-pagination-numbers">
                    <?php
                    $base_url = add_query_arg(array(
                        'page' => 'medical-rentals',
                        'box_id' => $box_filter,
                        'professional_id' => $professional_filter,
                        'date_from' => $date_from,
                        'date_to' => $date_to,
                        'per_page' => $per_page
                    ), admin_url('admin.php'));
                    
                    // Previous button
                    if ($current_page > 1) {
                        echo '<a href="' . esc_url(add_query_arg('paged', $current_page - 1, $base_url)) . '" class="button">&laquo; ' . __('Anterior', 'medical-appointments') . '</a> ';
                    }
                    
                    // Page numbers
                    $range = 2;
                    for ($i = 1; $i <= $total_pages; $i++) {
                        if ($i == 1 || $i == $total_pages || ($i >= $current_page - $range && $i <= $current_page + $range)) {
                            if ($i == $current_page) {
                                echo '<span class="button button-primary" style="margin: 0 2px;">' . $i . '</span> ';
                            } else {
                                echo '<a href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '" class="button" style="margin: 0 2px;">' . $i . '</a> ';
                            }
                        } elseif ($i == $current_page - $range - 1 || $i == $current_page + $range + 1) {
                            echo '<span style="margin: 0 5px;">...</span> ';
                        }
                    }
                    
                    // Next button
                    if ($current_page < $total_pages) {
                        echo '<a href="' . esc_url(add_query_arg('paged', $current_page + 1, $base_url)) . '" class="button">' . __('Siguiente', 'medical-appointments') . ' &raquo;</a>';
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <!--Tabla de arriendos -->
    <?php if (!empty($rentals)) : ?>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php _e('ID', 'medical-appointments'); ?></th>
                    <th><?php _e('Box', 'medical-appointments'); ?></th>
                    <th><?php _e('Profesional', 'medical-appointments'); ?></th>
                    <th><?php _e('Fecha', 'medical-appointments'); ?></th>
                    <th><?php _e('Horario', 'medical-appointments'); ?></th>
                    <th><?php _e('Horas', 'medical-appointments'); ?></th>
                    <th><?php _e('Total', 'medical-appointments'); ?></th>
                    <th><?php _e('Estado', 'medical-appointments'); ?></th>
                    <th><?php _e('Acciones', 'medical-appointments'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rentals as $rental) : ?>
                    <tr>
                        <td><strong>#<?php echo esc_html($rental->id); ?></strong></td>
                        <td>
                            <strong><?php echo esc_html($rental->box_number); ?></strong><br>
                            <small><?php echo esc_html($rental->box_name); ?></small>
                        </td>
                        <td><?php echo esc_html($rental->professional_name); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($rental->rental_date)); ?></td>
                        <td>
                            <?php echo esc_html($rental->start_time); ?> - <?php echo esc_html($rental->end_time); ?>
                        </td>
                        <td><?php echo number_format($rental->total_hours, 1); ?> hrs</td>
                        <td><strong>$<?php echo number_format($rental->total_price, 0, ',', '.'); ?></strong></td>
                        <td>
                            <span class="mas-status-badge mas-status-<?php echo esc_attr($rental->status); ?>">
                                <?php 
                                echo isset($statuses[$rental->status]) ? __($statuses[$rental->status], 'medical-appointments') : esc_html(ucfirst($rental->status));
                                ?>
                            </span>
                            <?php if (isset($rental->payment_status)) : ?>
                                <br>
                                <span class="mas-payment-badge mas-payment-<?php echo esc_attr($rental->payment_status); ?>">
                                    <?php 
                                    echo isset($payment_statuses[$rental->payment_status]) ? __($payment_statuses[$rental->payment_status], 'medical-appointments') : esc_html(ucfirst($rental->payment_status));
                                    ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="mas-table-actions">
                            <!-- <a href="<?php echo admin_url('admin.php?page=medical-rentals&action=edit&id=' . $rental->id); ?>" class="button button-small"> -->
                            <a href="#" class="button button-small mas-edit-rental" 
                               data-id="<?php echo $rental->id; ?>"
                               data-box-id="<?php echo $rental->box_id; ?>"
                               data-professional-id="<?php echo $rental->professional_id; ?>"
                               data-date="<?php echo $rental->rental_date; ?>"
                               data-start-time="<?php echo $rental->start_time; ?>"
                               data-end-time="<?php echo $rental->end_time; ?>"
                               data-notes="<?php echo esc_attr($rental->notes); ?>"
                               data-status="<?php echo $rental->status; ?>"
                               data-payment-status="<?php echo $rental->payment_status; ?>"
                               data-payment-method="<?php echo $rental->payment_method; ?>"
                               onclick="event.preventDefault(); openEditModalWithData(this.dataset);">
                                <?php _e('Editar', 'medical-appointments'); ?>
                            </a>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=medical-rentals&action=cancel&id=' . $rental->id), 'mas_cancel_rental_' . $rental->id); ?>" class="button button-small" onclick="return confirm('<?php _e('¿Está seguro de cancelar este arriendo?', 'medical-appointments'); ?>')">
                                <?php _e('Cancelar', 'medical-appointments'); ?>
                            </a>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=medical-rentals&action=delete&id=' . $rental->id), 'mas_delete_rental_' . $rental->id); ?>" class="button button-small" onclick="return confirm('<?php _e('¿Está seguro de eliminar este arriendo?', 'medical-appointments'); ?>')">
                                <?php _e('Eliminar', 'medical-appointments'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Adding pagination controls below table -->
        <?php if ($total_pages > 1) : ?>
            <div class="mas-pagination-controls" style="display: flex; justify-content: center; align-items: center; margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                <div class="mas-pagination-numbers">
                    <?php
                    // Previous button
                    if ($current_page > 1) {
                        echo '<a href="' . esc_url(add_query_arg('paged', $current_page - 1, $base_url)) . '" class="button">&laquo; ' . __('Anterior', 'medical-appointments') . '</a> ';
                    }
                    
                    // Page numbers
                    for ($i = 1; $i <= $total_pages; $i++) {
                        if ($i == 1 || $i == $total_pages || ($i >= $current_page - $range && $i <= $current_page + $range)) {
                            if ($i == $current_page) {
                                echo '<span class="button button-primary" style="margin: 0 2px;">' . $i . '</span> ';
                            } else {
                                echo '<a href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '" class="button" style="margin: 0 2px;">' . $i . '</a> ';
                            }
                        } elseif ($i == $current_page - $range - 1 || $i == $current_page + $range + 1) {
                            echo '<span style="margin: 0 5px;">...</span> ';
                        }
                    }
                    
                    // Next button
                    if ($current_page < $total_pages) {
                        echo '<a href="' . esc_url(add_query_arg('paged', $current_page + 1, $base_url)) . '" class="button">' . __('Siguiente', 'medical-appointments') . ' &raquo;</a>';
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <p class="mas-no-data"><?php _e('No se encontraron arriendos.', 'medical-appointments'); ?></p>
    <?php endif; ?>
</div>

<!-- Modal de AGREGAR NUEVO Arriendo -->
<div id="mas-rental-add-modal" class="mas-modal-overlay" style="display: none;">
    <div class="mas-modal mas-modal-enhanced">
        <div class="mas-modal-header mas-modal-header-gradient">
            <div class="mas-modal-title-wrapper">
                <svg class="mas-modal-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <h2>Nuevo Arriendo de Box</h2>
            </div>
            <button type="button" class="mas-modal-close" onclick="closeAddModal()">&times;</button>
        </div>
        
        <form method="post" action="">
            <?php wp_nonce_field('mas_rental_nonce'); ?>
            
            <div class="mas-modal-body mas-modal-body-enhanced">
                <div class="mas-form-section mas-form-section-enhanced">
                    <div class="mas-section-icon-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h3><?php _e('Información del Arriendo', 'medical-appointments'); ?></h3>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Box', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <select name="box_id" id="add_box_id" required>
                                <option value=""><?php _e('Seleccione un box', 'medical-appointments'); ?></option>
                                <?php foreach ($boxes as $box) : ?>
                                    <option value="<?php echo esc_attr($box->id); ?>" 
                                            data-price="<?php echo esc_attr($box->price_per_hour); ?>">
                                        <?php echo esc_html($box->box_number . ' - ' . $box->box_name . ' ($' . number_format($box->price_per_hour, 0, ',', '.') . '/hr)'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Profesional', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <select name="professional_id" required>
                                <option value=""><?php _e('Seleccione un profesional', 'medical-appointments'); ?></option>
                                <?php foreach ($professionals as $professional) : ?>
                                    <option value="<?php echo esc_attr($professional->id); ?>">
                                        <?php echo esc_html($professional->display_name); ?>
                                        <?php if ($professional->specialty) : ?>
                                            - <?php echo esc_html($professional->specialty); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="mas-form-section mas-form-section-enhanced">
                    <div class="mas-section-icon-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h3><?php _e('Fecha y Horario', 'medical-appointments'); ?></h3>
                    </div>
                    
                    <div class="mas-form-field mas-form-field-enhanced">
                        <label><?php _e('Fecha', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                        <input type="date" 
                               id="add_rental_date" 
                               name="rental_date" 
                               min="<?php echo date('Y-m-d'); ?>"
                               required>
                    </div>
                    
                    <input type="hidden" id="add_rental_time" name="rental_time" value="">
                    
                    <div class="mas-form-field mas-form-field-enhanced mas-full-width">
                        <label class="mas-label-modern">
                            <svg class="mas-label-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M12 8V12L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            Horarios Disponibles <span class="required">*</span>
                        </label>
                        
                        <div id="add-rental-time-slots-container" style="display: none;">
                            <div id="add-rental-time-slots" class="mas-time-slots-grid-admin"></div>
                        </div>
                        
                        <div id="add-rental-loading-slots" class="mas-loading-indicator" style="display: none; margin-top: 8px; padding: 12px; background: #f0f0f1; border-radius: 4px; text-align: center;">
                            <span style="display: inline-block; width: 16px; height: 16px; border: 2px solid #f3f3f3; border-top: 2px solid #0073aa; border-radius: 50%; animation: spin 1s linear infinite;"></span>
                            <span style="margin-left: 8px; color: #666;"><?php _e('Cargando horarios disponibles...', 'medical-appointments'); ?></span>
                        </div>
                        
                        <div id="add-rental-no-slots" class="mas-info-message" style="display: none; padding: 12px; background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; border-radius: 4px; margin-top: 8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" style="vertical-align: middle; margin-right: 6px;">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                <path d="M12 8V12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M12 16H12.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                            <?php _e('No hay horarios disponibles para esta fecha y box', 'medical-appointments'); ?>
                        </div>
                    </div>
                    
                    <div class="mas-form-field mas-form-field-enhanced">
                        <label><?php _e('Notas', 'medical-appointments'); ?></label>
                        <textarea name="notes" rows="3" placeholder="Ingrese notas adicionales sobre el arriendo..."></textarea>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Estado', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <select name="status" required>
                                <option value="pending"><?php _e('Pendiente', 'medical-appointments'); ?></option>
                                <option value="active" selected><?php _e('Activo', 'medical-appointments'); ?></option>
                                <option value="completed"><?php _e('Completado', 'medical-appointments'); ?></option>
                                <option value="cancelled"><?php _e('Cancelado', 'medical-appointments'); ?></option>
                                <option value="interno"><?php _e('Interno', 'medical-appointments'); ?></option>
                            </select>
                        </div>
                        
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Estado de Pago', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <select name="payment_status" required>
                                <option value="pending" selected><?php _e('Pendiente', 'medical-appointments'); ?></option>
                                <option value="paid"><?php _e('Pagado', 'medical-appointments'); ?></option>
                                <option value="failed"><?php _e('Fallido', 'medical-appointments'); ?></option>
                                <option value="refunded"><?php _e('Reembolsado', 'medical-appointments'); ?></option>
                                <option value="interno"><?php _e('Interno', 'medical-appointments'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Método de Pago', 'medical-appointments'); ?></label>
                            <select name="payment_method">
                                <option value=""><?php _e('No especificado', 'medical-appointments'); ?></option>
                                <option value="transferencia"><?php _e('Transferencia Bancaria', 'medical-appointments'); ?></option>
                                <option value="pos"><?php _e('POS (Tarjeta)', 'medical-appointments'); ?></option>
                                <option value="efectivo"><?php _e('Efectivo', 'medical-appointments'); ?></option>
                                <option value="mercadopago"><?php _e('MercadoPago', 'medical-appointments'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mas-modal-footer mas-modal-footer-enhanced">
                <button type="button" class="button mas-button-cancel" onclick="closeAddModal()"><?php _e('Cancelar', 'medical-appointments'); ?></button>
                <button type="submit" name="mas_save_rental" class="button button-primary mas-button-save">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <?php _e('Guardar Arriendo', 'medical-appointments'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de EDITAR Arriendo -->
<div id="mas-rental-edit-modal" class="mas-modal-overlay" style="display: none;">
    <div class="mas-modal mas-modal-enhanced">
        <div class="mas-modal-header mas-modal-header-gradient">
            <div class="mas-modal-title-wrapper">
                <svg class="mas-modal-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <h2>Editar Arriendo de Box</h2>
            </div>
            <button type="button" class="mas-modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        
        <form method="post" action="">
            <?php wp_nonce_field('mas_rental_nonce'); ?>
            <input type="hidden" id="edit_rental_id" name="rental_id" value="">
            
            <div class="mas-modal-body mas-modal-body-enhanced">
                <div class="mas-form-section mas-form-section-enhanced">
                    <div class="mas-section-icon-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h3><?php _e('Información del Arriendo', 'medical-appointments'); ?></h3>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Box', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <select name="box_id" id="edit_box_id" required>
                                <option value=""><?php _e('Seleccione un box', 'medical-appointments'); ?></option>
                                <?php foreach ($boxes as $box) : ?>
                                    <option value="<?php echo esc_attr($box->id); ?>" 
                                            data-price="<?php echo esc_attr($box->price_per_hour); ?>">
                                        <?php echo esc_html($box->box_number . ' - ' . $box->box_name . ' ($' . number_format($box->price_per_hour, 0, ',', '.') . '/hr)'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Profesional', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <select name="professional_id" id="edit_professional_id" required>
                                <option value=""><?php _e('Seleccione un profesional', 'medical-appointments'); ?></option>
                                <?php foreach ($professionals as $professional) : ?>
                                    <option value="<?php echo esc_attr($professional->id); ?>">
                                        <?php echo esc_html($professional->display_name); ?>
                                        <?php if ($professional->specialty) : ?>
                                            - <?php echo esc_html($professional->specialty); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="mas-form-section mas-form-section-enhanced">
                    <div class="mas-section-icon-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h3><?php _e('Fecha y Horario', 'medical-appointments'); ?></h3>
                    </div>
                    
                    <div class="mas-form-field mas-form-field-enhanced">
                        <label><?php _e('Fecha', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                        <input type="date" 
                               id="edit_rental_date" 
                               name="rental_date" 
                               min="<?php echo date('Y-m-d'); ?>"
                               required>
                    </div>
                    
                    <input type="hidden" id="edit_rental_time" name="rental_time" value="">
                    
                    <div class="mas-form-field mas-form-field-enhanced mas-full-width">
                        <label class="mas-label-modern">
                            <svg class="mas-label-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M12 8V12L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            Horarios Disponibles <span class="required">*</span>
                        </label>
                        
                        <div id="edit-rental-time-slots-container" style="display: none;">
                            <div id="edit-rental-time-slots" class="mas-time-slots-grid-admin"></div>
                        </div>
                        
                        <div id="edit-rental-loading-slots" class="mas-loading-indicator" style="display: none; margin-top: 8px; padding: 12px; background: #f0f0f1; border-radius: 4px; text-align: center;">
                            <span style="display: inline-block; width: 16px; height: 16px; border: 2px solid #f3f3f3; border-top: 2px solid #0073aa; border-radius: 50%; animation: spin 1s linear infinite;"></span>
                            <span style="margin-left: 8px; color: #666;"><?php _e('Cargando horarios disponibles...', 'medical-appointments'); ?></span>
                        </div>
                        
                        <div id="edit-rental-no-slots" class="mas-info-message" style="display: none; padding: 12px; background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; border-radius: 4px; margin-top: 8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" style="vertical-align: middle; margin-right: 6px;">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                <path d="M12 8V12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M12 16H12.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                            <?php _e('No hay horarios disponibles para esta fecha y box', 'medical-appointments'); ?>
                        </div>
                    </div>
                    
                    <div class="mas-form-field mas-form-field-enhanced">
                        <label><?php _e('Notas', 'medical-appointments'); ?></label>
                        <textarea name="notes" id="edit_notes" rows="3" placeholder="Ingrese notas adicionales sobre el arriendo..."></textarea>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Estado', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <select name="status" id="edit_status" required>
                                <option value="pending"><?php _e('Pendiente', 'medical-appointments'); ?></option>
                                <option value="active"><?php _e('Activo', 'medical-appointments'); ?></option>
                                <option value="completed"><?php _e('Completado', 'medical-appointments'); ?></option>
                                <option value="cancelled"><?php _e('Cancelado', 'medical-appointments'); ?></option>
                                <option value="interno"><?php _e('Interno', 'medical-appointments'); ?></option>
                            </select>
                        </div>
                        
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Estado de Pago', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <select name="payment_status" id="edit_payment_status" required>
                                <option value="pending"><?php _e('Pendiente', 'medical-appointments'); ?></option>
                                <option value="paid"><?php _e('Pagado', 'medical-appointments'); ?></option>
                                <option value="failed"><?php _e('Fallido', 'medical-appointments'); ?></option>
                                <option value="refunded"><?php _e('Reembolsado', 'medical-appointments'); ?></option>
                                <option value="interno"><?php _e('Interno', 'medical-appointments'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Método de Pago', 'medical-appointments'); ?></label>
                            <select name="payment_method" id="edit_payment_method">
                                <option value=""><?php _e('No especificado', 'medical-appointments'); ?></option>
                                <option value="transferencia"><?php _e('Transferencia Bancaria', 'medical-appointments'); ?></option>
                                <option value="pos"><?php _e('POS (Tarjeta)', 'medical-appointments'); ?></option>
                                <option value="efectivo"><?php _e('Efectivo', 'medical-appointments'); ?></option>
                                <option value="mercadopago"><?php _e('MercadoPago', 'medical-appointments'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mas-modal-footer mas-modal-footer-enhanced">
                <button type="button" class="button mas-button-cancel" onclick="closeEditModal()"><?php _e('Cancelar', 'medical-appointments'); ?></button>
                <button type="submit" name="mas_save_rental" class="button button-primary mas-button-save">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <?php _e('Guardar Arriendo', 'medical-appointments'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($editing_rental): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const rentalData = {
        id: '<?php echo $editing_rental->id; ?>',
        boxId: '<?php echo $editing_rental->box_id; ?>',
        professionalId: '<?php echo $editing_rental->professional_id; ?>',
        date: '<?php echo $editing_rental->rental_date; ?>',
        startTime: '<?php echo $editing_rental->start_time; ?>',
        endTime: '<?php echo $editing_rental->end_time; ?>',
        notes: '<?php echo esc_js($editing_rental->notes); ?>',
        status: '<?php echo $editing_rental->status; ?>',
        paymentStatus: '<?php echo $editing_rental->payment_status; ?>',
        paymentMethod: '<?php echo $editing_rental->payment_method; ?>'
    };
    openEditModalWithData(rentalData);
});
</script>
<?php endif; ?>

<script>
var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';

jQuery(document).ready(function($) {
    let addSelectedSlots = [];
    let editSelectedSlots = [];
    let editExcludeRentalId = 0;
    
    
    // ========== ADD MODAL FUNCTIONS ==========
    window.openAddModal = function() {
        $('#mas-rental-add-modal').fadeIn(200);
        $('body').css('overflow', 'hidden');
    }
    
    window.closeAddModal = function() {
        $('#mas-rental-add-modal').fadeOut(200);
        $('body').css('overflow', '');
        resetAddForm();
    }
    
    function resetAddForm() {
        addSelectedSlots = [];
        $('#add_box_id').val('');
        $('#add_rental_date').val('');
        $('#add_rental_time').val('');
        $('#add-rental-time-slots-container').hide();
        $('#add-rental-time-slots').empty();
        $('#add-rental-no-slots').hide();
        $('#add-rental-loading-slots').hide();
    }
    
    function loadAddAvailableSlots() {
        const boxId = $('#add_box_id').val();
        const date = $('#add_rental_date').val();
        
        // Resetear slots seleccionados al cambiar box o fecha
        addSelectedSlots = [];
        updateAddRentalTime();
        
        if (!boxId || !date) {
            $('#add-rental-time-slots-container').hide();
            return;
        }
        
        $('#add-rental-loading-slots').show();
        $('#add-rental-time-slots-container').hide();
        $('#add-rental-no-slots').hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mas_get_box_available_slots',
                box_id: boxId,
                date: date,
                exclude_rental_id: 0,
                nonce: '<?php echo wp_create_nonce('mas_get_slots_nonce'); ?>'
            },
            success: function(response) {
                $('#add-rental-loading-slots').hide();
                
                let slots = [];
                if (response.success && response.data) {
                    // Check if response.data is an array (direct format) or has slots property
                    slots = Array.isArray(response.data) ? response.data : (response.data.slots || []);
                }
                
                if (slots.length > 0) {
                    $('#add-rental-time-slots-container').show();
                    $('#add-rental-time-slots').empty();
                    
                    slots.forEach(function(slot) {
                        const isDisabled = !slot.available;
                        const btn = $('<button>')
                            .attr('type', 'button')
                            .addClass('mas-time-slot-btn-admin')
                            .attr('data-time', slot.time)
                            .attr('data-modal', 'add')
                            .prop('disabled', isDisabled)
                            .text(slot.time);
                        
                        if (isDisabled) {
                            btn.addClass('disabled');
                        }
                        
                        $('#add-rental-time-slots').append(btn);
                    });
                } else {
                    $('#add-rental-no-slots').show();
                }
            },
            error: function() {
                $('#add-rental-loading-slots').hide();
                $('#add-rental-no-slots').show();
            }
        });
    }
    
    // ========== EDIT MODAL FUNCTIONS ==========
    window.openEditModalWithData = function(rentalData) {
        const rental = rentalData; // Use the object directly
        editExcludeRentalId = parseInt(rental.id);
        
        $('#edit_rental_id').val(rental.id);
        $('#edit_box_id').val(rental.boxId);
        $('#edit_professional_id').val(rental.professionalId);
        $('#edit_rental_date').val(rental.date);
        $('#edit_notes').val(rental.notes || '');
        $('#edit_status').val(rental.status);
        $('#edit_payment_status').val(rental.paymentStatus);
        $('#edit_payment_method').val(rental.paymentMethod || '');
        
        $('#mas-rental-edit-modal').fadeIn(200);
        $('body').css('overflow', 'hidden');
        
        loadEditAvailableSlotsWithPreselection(rental.startTime, rental.endTime);
    }
    
    window.closeEditModal = function() {
        $('#mas-rental-edit-modal').fadeOut(200);
        $('body').css('overflow', '');
        resetEditForm();
    }
    
    function resetEditForm() {
        editSelectedSlots = [];
        editExcludeRentalId = 0;
        $('#edit_rental_id').val('');
        $('#edit_box_id').val('');
        $('#edit_rental_date').val('');
        $('#edit_rental_time').val('');
        $('#edit-rental-time-slots-container').hide();
        $('#edit-rental-time-slots').empty();
        $('#edit-rental-no-slots').hide();
        $('#edit-rental-loading-slots').hide();
    }
    
    function loadEditAvailableSlotsWithPreselection(startTime, endTime) {
        const boxId = $('#edit_box_id').val();
        const date = $('#edit_rental_date').val();
        
        // Resetear slots antes de cargar con preselección
        editSelectedSlots = [];
        
        if (!boxId || !date) {
            $('#edit-rental-time-slots-container').hide();
            return;
        }
        
        $('#edit-rental-loading-slots').show();
        $('#edit-rental-time-slots-container').hide();
        $('#edit-rental-no-slots').hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mas_get_box_available_slots',
                box_id: boxId,
                date: date,
                exclude_rental_id: editExcludeRentalId,
                nonce: '<?php echo wp_create_nonce('mas_get_slots_nonce'); ?>'
            },
            success: function(response) {
                $('#edit-rental-loading-slots').hide();
                
                let slots = [];
                if (response.success && response.data) {
                    // Check if response.data is an array (direct format) or has slots property
                    slots = Array.isArray(response.data) ? response.data : (response.data.slots || []);
                }
                
                if (slots.length > 0) {
                    $('#edit-rental-time-slots-container').show();
                    $('#edit-rental-time-slots').empty();
                    
                    slots.forEach(function(slot) {
                        const isDisabled = !slot.available;
                        const btn = $('<button>')
                            .attr('type', 'button')
                            .addClass('mas-time-slot-btn-admin')
                            .attr('data-time', slot.time)
                            .attr('data-modal', 'edit')
                            .prop('disabled', isDisabled)
                            .text(slot.time);
                        
                        if (isDisabled) {
                            btn.addClass('disabled');
                        }
                        
                        $('#edit-rental-time-slots').append(btn);
                    });
                    
                    // Pre-select slots based on start_time and end_time
                    if (startTime && endTime) {
                        const blockDuration = parseInt('<?php $s = get_option("mas_settings", array()); echo isset($s["slot_duration"]) ? $s["slot_duration"] : 60; ?>');
                        const expectedSlots = [];
                        
                        const start = new Date('2000-01-01 ' + startTime);
                        const end = new Date('2000-01-01 ' + endTime);
                        let current = new Date(start);
                        
                        while (current < end) {
                            const timeStr = current.toTimeString().substring(0, 5);
                            expectedSlots.push(timeStr);
                            current.setMinutes(current.getMinutes() + blockDuration);
                        }
                        
                        expectedSlots.forEach(function(time) {
                            const btn = $('#edit-rental-time-slots button[data-time="' + time + '"]');
                            if (btn.length && !btn.prop('disabled')) {
                                btn.addClass('selected');
                                if (!editSelectedSlots.includes(time)) {
                                    editSelectedSlots.push(time);
                                }
                            }
                        });
                        
                        editSelectedSlots.sort();
                        updateEditRentalTime();
                    }
                } else {
                    $('#edit-rental-no-slots').show();
                }
            },
            error: function() {
                $('#edit-rental-loading-slots').hide();
                $('#edit-rental-no-slots').show();
            }
        });
    }
    
    function loadEditAvailableSlots() {
        const boxId = $('#edit_box_id').val();
        const date = $('#edit_rental_date').val();
        
        // Resetear slots seleccionados al cambiar box o fecha
        editSelectedSlots = [];
        updateEditRentalTime();
        
        if (!boxId || !date) {
            $('#edit-rental-time-slots-container').hide();
            return;
        }
        
        $('#edit-rental-loading-slots').show();
        $('#edit-rental-time-slots-container').hide();
        $('#edit-rental-no-slots').hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mas_get_box_available_slots',
                box_id: boxId,
                date: date,
                exclude_rental_id: editExcludeRentalId,
                nonce: '<?php echo wp_create_nonce('mas_get_slots_nonce'); ?>'
            },
            success: function(response) {
                $('#edit-rental-loading-slots').hide();
                
                let slots = [];
                if (response.success && response.data) {
                    // Check if response.data is an array (direct format) or has slots property
                    slots = Array.isArray(response.data) ? response.data : (response.data.slots || []);
                }
                
                if (slots.length > 0) {
                    $('#edit-rental-time-slots-container').show();
                    $('#edit-rental-time-slots').empty();
                    
                    slots.forEach(function(slot) {
                        const isDisabled = !slot.available;
                        const btn = $('<button>')
                            .attr('type', 'button')
                            .addClass('mas-time-slot-btn-admin')
                            .attr('data-time', slot.time)
                            .attr('data-modal', 'edit')
                            .prop('disabled', isDisabled)
                            .text(slot.time);
                        
                        if (isDisabled) {
                            btn.addClass('disabled');
                        }
                        
                        $('#edit-rental-time-slots').append(btn);
                    });
                } else {
                    $('#edit-rental-no-slots').show();
                }
            },
            error: function() {
                $('#edit-rental-loading-slots').hide();
                $('#edit-rental-no-slots').show();
            }
        });
    }
    
    // ========== SHARED FUNCTIONS ==========
    function updateAddRentalTime() {
        $('#add_rental_time').val(addSelectedSlots.join(','));
    }
    
    function updateEditRentalTime() {
        $('#edit_rental_time').val(editSelectedSlots.join(','));
    }
    
    // ========== EVENT LISTENERS ==========
    
    // Add modal events
    $('#add_box_id, #add_rental_date').on('change', loadAddAvailableSlots);
    
    // Edit modal events
    $('#edit_box_id, #edit_rental_date').on('change', loadEditAvailableSlots);
    
    // Slot selection for both modals
    $(document).on('click', '.mas-time-slot-btn-admin', function() {
        if ($(this).prop('disabled')) return;
        
        const time = $(this).data('time');
        const modal = $(this).data('modal');
        
        if (modal === 'add') {
            $(this).toggleClass('selected');
            const index = addSelectedSlots.indexOf(time);
            
            if (index > -1) {
                addSelectedSlots.splice(index, 1);
            } else {
                addSelectedSlots.push(time);
            }
            
            addSelectedSlots.sort();
            updateAddRentalTime();
        } else if (modal === 'edit') {
            $(this).toggleClass('selected');
            const index = editSelectedSlots.indexOf(time);
            
            if (index > -1) {
                editSelectedSlots.splice(index, 1);
            } else {
                editSelectedSlots.push(time);
            }
            
            editSelectedSlots.sort();
            updateEditRentalTime();
        }
    });
    
    // Form validation
    $('form').on('submit', function(e) {
        const isAddModal = $(this).closest('#mas-rental-add-modal').length > 0;
        const isEditModal = $(this).closest('#mas-rental-edit-modal').length > 0;
        
        if (isAddModal && addSelectedSlots.length === 0) {
            e.preventDefault();
            alert('<?php _e('Por favor seleccione al menos un horario', 'medical-appointments'); ?>');
            return false;
        }
        
        if (isEditModal && editSelectedSlots.length === 0) {
            e.preventDefault();
            alert('<?php _e('Por favor seleccione al menos un horario', 'medical-appointments'); ?>');
            return false;
        }
    });
    
    // Close modals on overlay click
    $('.mas-modal-overlay').on('click', function(e) {
        if (e.target === this) {
            if ($(this).attr('id') === 'mas-rental-add-modal') {
                closeAddModal();
            } else if ($(this).attr('id') === 'mas-rental-edit-modal') {
                closeEditModal();
            }
        }
    });
    
    // Make functions globally available
    // window.openAddModal = openAddModal; // Already defined globally
    // window.closeAddModal = closeAddModal; // Already defined globally
    // window.openEditModalWithData = openEditModalWithData; // Already defined globally
    // window.closeEditModal = closeEditModal; // Already defined globally
    
    // Edit button handler
    // Removed duplicate event listener, using the inline onclick in the TD
    
    // Add new button handler
    // Removed duplicate event listener, using the inline onclick in the A tag
});
</script>

<style>
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Added styles for time slot buttons grid */
.mas-time-slots-grid-admin {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 8px;
    margin-top: 8px;
}

.mas-time-slot-btn-admin {
    padding: 10px 16px;
    background: #f0f0f1;
    border: 2px solid #dcdcde;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    color: #2c3338;
    transition: all 0.2s ease;
    text-align: center;
}

.mas-time-slot-btn-admin:hover {
    background: #e8e8e9;
    border-color: #0073aa;
    transform: translateY(-2px);
    box-shadow: 0 2px 8px rgba(0, 115, 170, 0.2);
}

.mas-time-slot-btn-admin.selected {
    background: linear-gradient(135deg, #0073aa 0%, #005177 100%);
    border-color: #0073aa;
    color: white;
    font-weight: 600;
}

.mas-time-slot-btn-admin.selected:hover {
    background: linear-gradient(135deg, #005177 0%, #003d5c 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 115, 170, 0.4);
}

.mas-rental-preview-enhanced {
    transition: all 0.3s ease;
}

.mas-rental-preview-enhanced:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
}

.mas-help-text {
    margin: 4px 0 12px;
    color: #666;
    font-size: 13px;
}

.mas-full-width {
    grid-column: 1 / -1;
}

/* CHANGE: Adding modal overlay and modal styles to make them float */
.mas-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
}

.mas-modal {
    background: white;
    border-radius: 8px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    max-width: 900px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
}

.mas-modal-enhanced {
    animation: modalFadeIn 0.3s ease;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}
</style>

<script>
function changePerPage(perPage) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', perPage);
    url.searchParams.delete('paged'); // Reset to first page when changing per_page
    window.location.href = url.toString();
}
</script>
