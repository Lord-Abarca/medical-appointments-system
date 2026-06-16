<?php
/**
 * Página de gestión de citas en el admin
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die(__('Lo siento, no tienes permiso para acceder a esta página.', 'medical-appointments'));
}

$mas_appointments = new MAS_Appointments();
$mas_professionals = new MAS_Professionals();

// Procesar acciones
if (isset($_GET['action']) && isset($_GET['id'])) {
    $appointment_id = intval($_GET['id']);
    
    if ($_GET['action'] === 'delete' && check_admin_referer('mas_delete_appointment_' . $appointment_id)) {
        if ($mas_appointments->delete_appointment($appointment_id)) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Cita eliminada exitosamente.', 'medical-appointments') . '</p></div>';
        }
    }
}

// Guardar/actualizar cita
if (isset($_POST['mas_save_appointment']) && check_admin_referer('mas_appointment_nonce')) {
    $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
    
    $data = array(
        'patient_name' => sanitize_text_field($_POST['patient_name']),
        'patient_email' => sanitize_email($_POST['patient_email']),
        'patient_phone' => sanitize_text_field($_POST['patient_phone']),
        'patient_rut' => sanitize_text_field($_POST['patient_rut']),
        'health_insurance' => sanitize_text_field($_POST['health_insurance']),
        'appointment_date' => sanitize_text_field($_POST['appointment_date']),
        'appointment_time' => sanitize_text_field($_POST['appointment_time']),
        'professional_id' => (!empty($_POST['professional_id']) && $_POST['professional_id'] !== '') ? intval($_POST['professional_id']) : null,
        'status' => sanitize_text_field($_POST['status']),
        'payment_status' => sanitize_text_field($_POST['payment_status']),
        'payment_method' => !empty($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : null,
        'notes' => sanitize_textarea_field($_POST['notes'])
    );
    
    if ($appointment_id > 0) {
        if ($mas_appointments->update_appointment($appointment_id, $data)) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Cita actualizada exitosamente.', 'medical-appointments') . '</p></div>';
        }
    } else {
        if ($mas_appointments->create_appointment($data)) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Cita creada exitosamente.', 'medical-appointments') . '</p></div>';
        }
    }
}

// Obtener cita para editar
$editing_appointment = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editing_appointment = $mas_appointments->get_appointment(intval($_GET['id']));
}

// Filtros
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 25;
if (!in_array($per_page, [25, 50, 100])) {
    $per_page = 25;
}
$offset = ($current_page - 1) * $per_page;

// Obtener total de citas
global $wpdb;
$where_clauses = array('1=1');
$where_values = array();

if (!empty($status_filter)) {
    $where_clauses[] = 'status = %s';
    $where_values[] = $status_filter;
}
if (!empty($date_from)) {
    $where_clauses[] = 'appointment_date >= %s';
    $where_values[] = $date_from;
}
if (!empty($date_to)) {
    $where_clauses[] = 'appointment_date <= %s';
    $where_values[] = $date_to;
}

$where_sql = implode(' AND ', $where_clauses);
if (!empty($where_values)) {
    $where_sql = $wpdb->prepare($where_sql, ...$where_values);
}

$total_appointments = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}mas_appointments WHERE {$where_sql}");
$total_pages = ceil($total_appointments / $per_page);

// Obtener citas con paginación
$appointments = $mas_appointments->get_appointments(array(
    'status' => $status_filter,
    'date_from' => $date_from,
    'date_to' => $date_to,
    'limit' => $per_page,
    'offset' => $offset
));

$professionals = $mas_professionals->get_professionals('active');
?>

<div class="wrap mas-appointments-page">
    <h1 class="wp-heading-inline"><?php _e('Gestión de Citas', 'medical-appointments'); ?></h1>
    <a href="#" class="page-title-action" onclick="event.preventDefault(); resetAndOpenModal();">
        <?php _e('Agregar Nueva', 'medical-appointments'); ?>
    </a>
    <hr class="wp-header-end">
    
    <!-- Filtros -->
    <div class="mas-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="medical-appointments-list">
            
            <select name="status" class="mas-filter-select">
                <option value=""><?php _e('Todos los estados', 'medical-appointments'); ?></option>
                <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php _e('Pendiente', 'medical-appointments'); ?></option>
                <option value="scheduled" <?php selected($status_filter, 'scheduled'); ?>><?php _e('Agendado', 'medical-appointments'); ?></option>
                <option value="completed" <?php selected($status_filter, 'completed'); ?>><?php _e('Completado', 'medical-appointments'); ?></option>
                <option value="cancelled" <?php selected($status_filter, 'cancelled'); ?>><?php _e('Cancelado', 'medical-appointments'); ?></option>
                <option value="interno" <?php selected($status_filter, 'interno'); ?>><?php _e('Interno', 'medical-appointments'); ?></option>
            </select>
            
            <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" placeholder="<?php _e('Desde', 'medical-appointments'); ?>">
            <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" placeholder="<?php _e('Hasta', 'medical-appointments'); ?>">
            
            <button type="submit" class="button"><?php _e('Filtrar', 'medical-appointments'); ?></button>
            <a href="<?php echo admin_url('admin.php?page=medical-appointments-list'); ?>" class="button"><?php _e('Limpiar', 'medical-appointments'); ?></a>
        </form>
    </div>
    
    <!-- Controles de paginación superiores -->
    <?php if ($total_appointments > 0) : ?>
        <div class="tablenav top">
            <div class="alignleft actions">
                <label for="per-page-selector" class="screen-reader-text"><?php _e('Citas por página', 'medical-appointments'); ?></label>
                <select name="per_page" id="per-page-selector" onchange="changePerPage(this.value)">
                    <option value="25" <?php selected($per_page, 25); ?>><?php _e('25 por página', 'medical-appointments'); ?></option>
                    <option value="50" <?php selected($per_page, 50); ?>><?php _e('50 por página', 'medical-appointments'); ?></option>
                    <option value="100" <?php selected($per_page, 100); ?>><?php _e('100 por página', 'medical-appointments'); ?></option>
                </select>
            </div>
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php 
                    $showing_from = $offset + 1;
                    $showing_to = min($offset + $per_page, $total_appointments);
                    printf(__('Mostrando %d-%d de %d citas', 'medical-appointments'), $showing_from, $showing_to, $total_appointments);
                    ?>
                </span>
                <?php if ($total_pages > 1) : ?>
                    <span class="pagination-links">
                        <?php if ($current_page > 1) : ?>
                            <a class="first-page button" href="<?php echo add_query_arg(array('paged' => 1, 'per_page' => $per_page)); ?>">
                                <span class="screen-reader-text"><?php _e('Primera página', 'medical-appointments'); ?></span>
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                            <a class="prev-page button" href="<?php echo add_query_arg(array('paged' => $current_page - 1, 'per_page' => $per_page)); ?>">
                                <span class="screen-reader-text"><?php _e('Página anterior', 'medical-appointments'); ?></span>
                                <span aria-hidden="true">&lsaquo;</span>
                            </a>
                        <?php else : ?>
                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>
                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
                        <?php endif; ?>
                        
                        <span class="paging-input">
                            <label for="current-page-selector" class="screen-reader-text"><?php _e('Página actual', 'medical-appointments'); ?></label>
                            <input class="current-page" id="current-page-selector" type="text" 
                                   name="paged" value="<?php echo $current_page; ?>" 
                                   size="<?php echo strlen($total_pages); ?>" 
                                   aria-describedby="table-paging">
                            <span class="tablenav-paging-text"> <?php _e('de', 'medical-appointments'); ?> 
                                <span class="total-pages"><?php echo $total_pages; ?></span>
                            </span>
                        </span>
                        
                        <?php if ($current_page < $total_pages) : ?>
                            <a class="next-page button" href="<?php echo add_query_arg(array('paged' => $current_page + 1, 'per_page' => $per_page)); ?>">
                                <span class="screen-reader-text"><?php _e('Página siguiente', 'medical-appointments'); ?></span>
                                <span aria-hidden="true">&rsaquo;</span>
                            </a>
                            <a class="last-page button" href="<?php echo add_query_arg(array('paged' => $total_pages, 'per_page' => $per_page)); ?>">
                                <span class="screen-reader-text"><?php _e('Última página', 'medical-appointments'); ?></span>
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        <?php else : ?>
                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
            <br class="clear">
        </div>
    <?php endif; ?>
    
    <!-- Tabla de citas -->
    <?php if (!empty($appointments)) : ?>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php _e('ID', 'medical-appointments'); ?></th>
                    <th><?php _e('Paciente', 'medical-appointments'); ?></th>
                    <th><?php _e('Contacto', 'medical-appointments'); ?></th>
                    <th><?php _e('Previsión', 'medical-appointments'); ?></th>
                    <th><?php _e('Fecha', 'medical-appointments'); ?></th>
                    <th><?php _e('Hora', 'medical-appointments'); ?></th>
                    <th><?php _e('Profesional', 'medical-appointments'); ?></th>
                    <th><?php _e('Estado', 'medical-appointments'); ?></th>
                    <th><?php _e('Estado de Pago', 'medical-appointments'); ?></th>
                    <th><?php _e('Acciones', 'medical-appointments'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $appointment) : ?>
                    <tr>
                        <td><strong>#<?php echo esc_html($appointment->id); ?></strong></td>
                        <td>
                            <strong><?php echo esc_html($appointment->patient_name); ?></strong>
                            <?php if ($appointment->patient_rut) : ?>
                                <br><small><?php echo esc_html($appointment->patient_rut); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html($appointment->patient_phone); ?><br>
                            <small><?php echo esc_html($appointment->patient_email); ?></small>
                        </td>
                        <td>
                            <?php if ($appointment->health_insurance) : ?>
                                <span class="mas-badge">
                                    <?php echo $appointment->health_insurance === 'isapre' ? 'Isapre' : 'Fonasa'; ?>
                                </span>
                            <?php else : ?>
                                <em><?php _e('No especificada', 'medical-appointments'); ?></em>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($appointment->appointment_date)); ?></td>
                        <td><?php echo esc_html($appointment->appointment_time); ?></td>
                        <td>
                            <?php if ($appointment->professional_name) : ?>
                                <?php echo esc_html($appointment->professional_name); ?>
                            <?php else : ?>
                                <em><?php _e('Sin asignar', 'medical-appointments'); ?></em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="mas-status-badge mas-status-<?php echo esc_attr($appointment->status); ?>">
                                <?php 
                                $statuses = array(
                                    'pending' => __('Pendiente', 'medical-appointments'),
                                    'scheduled' => __('Agendado', 'medical-appointments'),
                                    'completed' => __('Completado', 'medical-appointments'),
                                    'cancelled' => __('Cancelado', 'medical-appointments'),
                                    'interno' => __('Interno', 'medical-appointments')
                                );
                                echo esc_html($statuses[$appointment->status]);
                                ?>
                            </span>
                        </td>
                        <td>
                            <span class="mas-status-badge mas-status-<?php echo esc_attr($appointment->payment_status); ?>">
                                <?php 
                                $payment_statuses = array(
                                    'pending' => __('Pendiente', 'medical-appointments'),
                                    'paid' => __('Pagado', 'medical-appointments'),
                                    'cancelled' => __('Cancelado', 'medical-appointments'),
                                    'refunded' => __('Reembolsado', 'medical-appointments'),
                                    'interno' => __('Interno', 'medical-appointments')
                                );
                                echo esc_html($payment_statuses[$appointment->payment_status]);
                                ?>
                            </span>
                        </td>
                        <td class="mas-table-actions">
                            <a href="<?php echo admin_url('admin.php?page=medical-appointments-list&action=edit&id=' . $appointment->id); ?>" class="button button-small">
                                <?php _e('Editar', 'medical-appointments'); ?>
                            </a>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=medical-appointments-list&action=delete&id=' . $appointment->id), 'mas_delete_appointment_' . $appointment->id); ?>" class="button button-small" onclick="return confirm('<?php _e('¿Está seguro de eliminar esta cita?', 'medical-appointments'); ?>')">
                                <?php _e('Eliminar', 'medical-appointments'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Controles de paginación inferiores -->
        <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php printf(__('Mostrando %d-%d de %d citas', 'medical-appointments'), $showing_from, $showing_to, $total_appointments); ?>
                    </span>
                    <span class="pagination-links">
                        <?php if ($current_page > 1) : ?>
                            <a class="first-page button" href="<?php echo add_query_arg(array('paged' => 1, 'per_page' => $per_page)); ?>">
                                <span class="screen-reader-text"><?php _e('Primera página', 'medical-appointments'); ?></span>
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                            <a class="prev-page button" href="<?php echo add_query_arg(array('paged' => $current_page - 1, 'per_page' => $per_page)); ?>">
                                <span class="screen-reader-text"><?php _e('Página anterior', 'medical-appointments'); ?></span>
                                <span aria-hidden="true">&lsaquo;</span>
                            </a>
                        <?php else : ?>
                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>
                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
                        <?php endif; ?>
                        
                        <span class="paging-input">
                            <?php _e('Página', 'medical-appointments'); ?> 
                            <span class="current-page"><?php echo $current_page; ?></span> 
                            <?php _e('de', 'medical-appointments'); ?> 
                            <span class="total-pages"><?php echo $total_pages; ?></span>
                        </span>
                        
                        <?php if ($current_page < $total_pages) : ?>
                            <a class="next-page button" href="<?php echo add_query_arg(array('paged' => $current_page + 1, 'per_page' => $per_page)); ?>">
                                <span class="screen-reader-text"><?php _e('Página siguiente', 'medical-appointments'); ?></span>
                                <span aria-hidden="true">&rsaquo;</span>
                            </a>
                            <a class="last-page button" href="<?php echo add_query_arg(array('paged' => $total_pages, 'per_page' => $per_page)); ?>">
                                <span class="screen-reader-text"><?php _e('Última página', 'medical-appointments'); ?></span>
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        <?php else : ?>
                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>
                        <?php endif; ?>
                    </span>
                </div>
                <br class="clear">
            </div>
        <?php endif; ?>
    <?php else : ?>
        <p class="mas-no-data"><?php _e('No se encontraron citas.', 'medical-appointments'); ?></p>
    <?php endif; ?>
</div>

<!-- Modal de edición/creación -->
<div id="mas-appointment-modal" class="mas-modal-overlay" style="display: none;">
    <div class="mas-modal mas-modal-enhanced">
        <div class="mas-modal-header mas-modal-header-gradient">
            <div class="mas-modal-title-wrapper">
                <svg class="mas-modal-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <h2><?php echo $editing_appointment ? __('Editar Cita Médica', 'medical-appointments') : __('Nueva Cita Médica', 'medical-appointments'); ?></h2>
            </div>
            <button type="button" class="mas-modal-close" data-modal-close>&times;</button>
        </div>
        
        <form method="post" action="">
            <?php wp_nonce_field('mas_appointment_nonce'); ?>
            <input type="hidden" name="appointment_id" value="<?php echo $editing_appointment ? esc_attr($editing_appointment->id) : ''; ?>">
            
            <div class="mas-modal-body mas-modal-body-enhanced">
                <div class="mas-form-section mas-form-section-enhanced">
                    <div class="mas-section-icon-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2m8-10a4 4 0 100-8 4 4 0 000 8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h3><?php _e('Información del Paciente', 'medical-appointments'); ?></h3>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Nombre Completo', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <input type="text" name="patient_name" value="<?php echo $editing_appointment ? esc_attr($editing_appointment->patient_name) : ''; ?>" required placeholder="Ej: Juan Pérez">
                        </div>
                        
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('RUT', 'medical-appointments'); ?></label>
                            <input type="text" name="patient_rut" value="<?php echo $editing_appointment ? esc_attr($editing_appointment->patient_rut) : ''; ?>" class="mas-rut-input" placeholder="12.345.678-9">
                        </div>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Email', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <input type="email" name="patient_email" value="<?php echo $editing_appointment ? esc_attr($editing_appointment->patient_email) : ''; ?>" required placeholder="correo@ejemplo.com">
                        </div>
                        
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Teléfono', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <input type="tel" name="patient_phone" value="<?php echo $editing_appointment ? esc_attr($editing_appointment->patient_phone) : ''; ?>" required placeholder="+56 9 1234 5678">
                        </div>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Previsión de Salud', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <select name="health_insurance" required>
                                <option value=""><?php _e('Seleccione previsión', 'medical-appointments'); ?></option>
                                <option value="isapre" <?php echo ($editing_appointment && $editing_appointment->health_insurance == 'isapre') ? 'selected' : ''; ?>><?php _e('Isapre', 'medical-appointments'); ?></option>
                                <option value="fonasa" <?php echo ($editing_appointment && $editing_appointment->health_insurance == 'fonasa') ? 'selected' : ''; ?>><?php _e('Fonasa', 'medical-appointments'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="mas-form-section mas-form-section-enhanced">
                    <div class="mas-section-icon-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h3><?php _e('Detalles de la Cita', 'medical-appointments'); ?></h3>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Fecha', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <input type="date" 
                                   id="admin_appointment_date" 
                                   name="appointment_date" 
                                   value="<?php echo $editing_appointment ? esc_attr($editing_appointment->appointment_date) : ''; ?>" 
                                   min="<?php echo date('Y-m-d'); ?>"
                                   required>
                        </div>
                        
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Hora', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <select name="appointment_time" id="admin_appointment_time" required disabled>
                                <option value=""><?php _e('Primero seleccione una fecha', 'medical-appointments'); ?></option>
                            </select>
                            <div id="admin-loading-slots" class="mas-loading-indicator" style="display: none; margin-top: 8px;">
                                <span style="display: inline-block; width: 16px; height: 16px; border: 2px solid #f3f3f3; border-top: 2px solid #0073aa; border-radius: 50%; animation: spin 1s linear infinite;"></span>
                                <span style="margin-left: 8px;"><?php _e('Cargando horarios disponibles...', 'medical-appointments'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Profesional', 'medical-appointments'); ?></label>
                            <select name="professional_id">
                                <option value=""><?php _e('Sin asignar', 'medical-appointments'); ?></option>
                                <?php foreach ($professionals as $professional) : ?>
                                    <option value="<?php echo esc_attr($professional->id); ?>" <?php echo ($editing_appointment && $editing_appointment->professional_id == $professional->id) ? 'selected' : ''; ?>>
                                        <?php echo esc_html($professional->display_name); ?>
                                        <?php if ($professional->specialty) : ?>
                                            - <?php echo esc_html($professional->specialty); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Estado', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <select name="status" required>
                                <option value="pending" <?php echo ($editing_appointment && $editing_appointment->status == 'pending') ? 'selected' : ''; ?>><?php _e('Pendiente', 'medical-appointments'); ?></option>
                                <option value="scheduled" <?php echo ($editing_appointment && $editing_appointment->status == 'scheduled') ? 'selected' : ''; ?>><?php _e('Agendado', 'medical-appointments'); ?></option>
                                <option value="completed" <?php echo ($editing_appointment && $editing_appointment->status == 'completed') ? 'selected' : ''; ?>><?php _e('Completado', 'medical-appointments'); ?></option>
                                <option value="cancelled" <?php echo ($editing_appointment && $editing_appointment->status == 'cancelled') ? 'selected' : ''; ?>><?php _e('Cancelado', 'medical-appointments'); ?></option>
                                <option value="interno" <?php echo ($editing_appointment && $editing_appointment->status == 'interno') ? 'selected' : ''; ?>><?php _e('Interno', 'medical-appointments'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Estado de Pago', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <select name="payment_status" required>
                                <option value="pending" <?php echo ($editing_appointment && $editing_appointment->payment_status == 'pending') ? 'selected' : ''; ?>><?php _e('Pendiente', 'medical-appointments'); ?></option>
                                <option value="paid" <?php echo ($editing_appointment && $editing_appointment->payment_status == 'paid') ? 'selected' : ''; ?>><?php _e('Pagado', 'medical-appointments'); ?></option>
                                <option value="cancelled" <?php echo ($editing_appointment && $editing_appointment->payment_status == 'cancelled') ? 'selected' : ''; ?>><?php _e('Cancelado', 'medical-appointments'); ?></option>
                                <option value="refunded" <?php echo ($editing_appointment && $editing_appointment->payment_status == 'refunded') ? 'selected' : ''; ?>><?php _e('Reembolsado', 'medical-appointments'); ?></option>
                                <option value="interno" <?php echo ($editing_appointment && $editing_appointment->payment_status == 'interno') ? 'selected' : ''; ?>><?php _e('Interno', 'medical-appointments'); ?></option>
                            </select>
                        </div>
                        
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Método de Pago', 'medical-appointments'); ?></label>
                            <select name="payment_method">
                                <option value=""><?php _e('No especificado', 'medical-appointments'); ?></option>
                                <option value="transferencia" <?php echo ($editing_appointment && $editing_appointment->payment_method == 'transferencia') ? 'selected' : ''; ?>><?php _e('Transferencia Bancaria', 'medical-appointments'); ?></option>
                                <option value="pos" <?php echo ($editing_appointment && $editing_appointment->payment_method == 'pos') ? 'selected' : ''; ?>><?php _e('POS (Tarjeta)', 'medical-appointments'); ?></option>
                                <option value="efectivo" <?php echo ($editing_appointment && $editing_appointment->payment_method == 'efectivo') ? 'selected' : ''; ?>><?php _e('Efectivo', 'medical-appointments'); ?></option>
                                <option value="mercadopago" <?php echo ($editing_appointment && $editing_appointment->payment_method == 'mercadopago') ? 'selected' : ''; ?>><?php _e('MercadoPago', 'medical-appointments'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mas-form-field mas-form-field-enhanced">
                        <label><?php _e('Notas', 'medical-appointments'); ?></label>
                        <textarea name="notes" rows="4" placeholder="Ingrese notas adicionales sobre la cita..."><?php echo $editing_appointment ? esc_textarea($editing_appointment->notes) : ''; ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="mas-modal-footer mas-modal-footer-enhanced">
                <button type="button" class="button mas-button-cancel" data-modal-close><?php _e('Cancelar', 'medical-appointments'); ?></button>
                <button type="submit" name="mas_save_appointment" class="button button-primary mas-button-save">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <?php _e('Guardar Cita', 'medical-appointments'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($editing_appointment) : ?>
<script>
jQuery(document).ready(function($) {
    $('#mas-appointment-modal').fadeIn(200);
});
</script>
<?php endif; ?>

<script type="text/javascript">
jQuery(document).ready(function($) {
    window.resetAndOpenModal = function() {
        $('#mas-appointment-modal form')[0].reset();
        $('input[name="appointment_id"]').val('');
        $('input[name="patient_name"]').val('');
        $('input[name="patient_rut"]').val('');
        $('input[name="patient_email"]').val('');
        $('input[name="patient_phone"]').val('');
        $('select[name="health_insurance"]').val('');
        $('input[name="appointment_date"]').val('');
        $('#admin_appointment_time').html('<option value=""><?php _e('Primero seleccione una fecha', 'medical-appointments'); ?></option>').prop('disabled', true);
        $('select[name="professional_id"]').val('');
        $('select[name="status"]').val('pending');
        $('select[name="payment_status"]').val('pending');
        $('select[name="payment_method"]').val('');
        $('textarea[name="notes"]').val('');
        $('#mas-appointment-modal').fadeIn(200);
    };
    
    $('#admin_appointment_date').on('change', function() {
        const date = $(this).val();
        const $timeSelect = $('#admin_appointment_time');
        const $loading = $('#admin-loading-slots');
        
        if (!date) return;
        
        $timeSelect.prop('disabled', true).html('<option value=""><?php _e('Cargando...', 'medical-appointments'); ?></option>');
        $loading.show();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mas_get_available_slots',
                nonce: '<?php echo wp_create_nonce('mas_public_nonce'); ?>',
                date: date
            },
            success: function(response) {
                $loading.hide();
                
                if (response.success && response.data.length > 0) {
                    $timeSelect.html('<option value=""><?php _e('Seleccione un horario', 'medical-appointments'); ?></option>');
                    
                    response.data.forEach(function(slot) {
                        $timeSelect.append(
                            $('<option></option>')
                                .attr('value', slot.time)
                                .text(slot.formatted)
                        );
                    });
                    
                    <?php if ($editing_appointment) : ?>
                    // If editing, try to select the existing time
                    $timeSelect.val('<?php echo esc_js($editing_appointment->appointment_time); ?>');
                    <?php endif; ?>
                    
                    $timeSelect.prop('disabled', false);
                } else {
                    $timeSelect.html('<option value=""><?php _e('No hay horarios disponibles para esta fecha', 'medical-appointments'); ?></option>');
                }
            },
            error: function() {
                $loading.hide();
                $timeSelect.html('<option value=""><?php _e('Error al cargar horarios', 'medical-appointments'); ?></option>');
            }
        });
    });
    
    <?php if ($editing_appointment) : ?>
    $('#admin_appointment_date').trigger('change');
    <?php endif; ?>
    
    // Modal controls
    $('.mas-modal-close, [data-modal-close]').on('click', function() {
        $('#mas-appointment-modal').fadeOut(200);
    });
    
    $('#mas-appointment-modal').on('click', function(e) {
        if ($(e.target).is('#mas-appointment-modal')) {
            $(this).fadeOut(200);
        }
    });
});
</script>

<script>
function changePerPage(perPage) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', perPage);
    url.searchParams.set('paged', '1');
    window.location.href = url.toString();
}
</script>

<style>
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>
