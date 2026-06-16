<?php
/**
 * Página de administración de arriendos mensuales
 */

if (!defined('ABSPATH')) {
    exit;
}

// Las clases ya están cargadas desde el archivo principal del plugin

// Procesar formulario de creación
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if (!wp_verify_nonce($_POST['_wpnonce'], 'mas_monthly_rental_action')) {
        wp_die('Acción no autorizada');
    }
    
    if ($_POST['action'] === 'create_monthly_rental') {
        $weekdays = isset($_POST['weekdays']) ? array_map('intval', $_POST['weekdays']) : array();
        
        if (empty($weekdays)) {
            $message = 'Debes seleccionar al menos un día de la semana.';
            $message_type = 'error';
        } else {
            $data = array(
                'box_id' => intval($_POST['box_id']),
                'professional_id' => intval($_POST['professional_id']),
                'month' => intval($_POST['month']),
                'year' => intval($_POST['year']),
                'weekdays' => $weekdays,
                'start_time' => sanitize_text_field($_POST['start_time']),
                'end_time' => sanitize_text_field($_POST['end_time']),
                'monthly_price' => floatval($_POST['monthly_price']),
                'notes' => sanitize_textarea_field($_POST['notes'])
            );
            
            $result = MAS_Monthly_Rentals::create($data);
            
            if (is_wp_error($result)) {
                if ($result->get_error_code() === 'conflicts') {
                    $conflicts = $result->get_error_data();
                    $conflict_dates = array_map(function($c) { return $c['date']; }, $conflicts);
                    $message = 'Hay conflictos con arriendos existentes en las siguientes fechas: ' . implode(', ', $conflict_dates);
                } else {
                    $message = $result->get_error_message();
                }
                $message_type = 'error';
            } else {
                $message = "Arriendo mensual creado exitosamente. Se generaron {$result['individual_rentals']} registros individuales.";
                $message_type = 'success';
            }
        }
    }
    
    if ($_POST['action'] === 'cancel_monthly_rental') {
        $id = intval($_POST['rental_id']);
        $result = MAS_Monthly_Rentals::cancel($id);
        
        if (is_wp_error($result)) {
            $message = $result->get_error_message();
            $message_type = 'error';
        } else {
            $message = "Arriendo mensual cancelado. Se cancelaron {$result['cancelled_individual_rentals']} registros individuales.";
            $message_type = 'success';
        }
    }
    
    if ($_POST['action'] === 'delete_monthly_rental') {
        $id = intval($_POST['rental_id']);
        $result = MAS_Monthly_Rentals::delete($id);
        
        if ($result) {
            $message = 'Arriendo mensual eliminado exitosamente.';
            $message_type = 'success';
        } else {
            $message = 'Error al eliminar el arriendo mensual.';
            $message_type = 'error';
        }
    }
}

// Obtener datos para el formulario
$mas_boxes = new MAS_Boxes();
$mas_professionals = new MAS_Professionals();
$boxes = $mas_boxes->get_boxes('active');
$professionals = $mas_professionals->get_professionals('active');

// Paginación
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 25;
$current_page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
$offset = ($current_page - 1) * $per_page;

// Filtros
$filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$filter_box = isset($_GET['box_id']) ? intval($_GET['box_id']) : '';
$filter_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Obtener arriendos mensuales
$args = array(
    'status' => $filter_status,
    'box_id' => $filter_box,
    'year' => $filter_year,
    'limit' => $per_page,
    'offset' => $offset
);

$monthly_rentals = MAS_Monthly_Rentals::get_all($args);
$total_rentals = MAS_Monthly_Rentals::count($args);
$total_pages = ceil($total_rentals / $per_page);

// Años para filtro
$current_year = date('Y');
$years = range($current_year - 1, $current_year + 2);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Arriendos Mensuales</h1>
    <button type="button" class="page-title-action" onclick="openMonthlyModal()">Nuevo Arriendo Mensual</button>
    <hr class="wp-header-end">
    
    <?php if ($message): ?>
        <div class="notice notice-<?php echo $message_type === 'success' ? 'success' : 'error'; ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Filtros -->
    <div class="tablenav top">
        <form method="get" action="">
            <input type="hidden" name="page" value="mas-monthly-rentals">
            
            <select name="status">
                <option value="">Todos los estados</option>
                <option value="active" <?php selected($filter_status, 'active'); ?>>Activo</option>
                <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>>Cancelado</option>
                <option value="completed" <?php selected($filter_status, 'completed'); ?>>Completado</option>
            </select>
            
            <select name="box_id">
                <option value="">Todos los boxes</option>
                <?php foreach ($boxes as $box): ?>
                    <option value="<?php echo $box->id; ?>" <?php selected($filter_box, $box->id); ?>>
                        <?php echo esc_html($box->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="year">
                <?php foreach ($years as $y): ?>
                    <option value="<?php echo $y; ?>" <?php selected($filter_year, $y); ?>><?php echo $y; ?></option>
                <?php endforeach; ?>
            </select>
            
            <select name="per_page">
                <option value="25" <?php selected($per_page, 25); ?>>25 por página</option>
                <option value="50" <?php selected($per_page, 50); ?>>50 por página</option>
                <option value="100" <?php selected($per_page, 100); ?>>100 por página</option>
            </select>
            
            <input type="submit" class="button" value="Filtrar">
        </form>
        
        <div class="tablenav-pages">
            <span class="displaying-num">
                <?php 
                $start = $offset + 1;
                $end = min($offset + $per_page, $total_rentals);
                echo "Mostrando {$start}-{$end} de {$total_rentals} arriendos mensuales";
                ?>
            </span>
        </div>
    </div>
    
    <!-- Tabla de arriendos mensuales -->
    <table class="wp-list-table widefat striped">
        <thead>
            <tr>
                <th style="width: 50px;">ID</th>
                <th>Box</th>
                <th>Profesional</th>
                <th>Período</th>
                <th>Días</th>
                <th>Horario</th>
                <th>Precio Mensual</th>
                <th>Estado</th>
                <th style="width: 150px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($monthly_rentals)): ?>
                <tr>
                    <td colspan="9">No hay arriendos mensuales registrados.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($monthly_rentals as $rental): ?>
                    <tr>
                        <td><?php echo $rental->id; ?></td>
                        <td>
                            <strong><?php echo esc_html($rental->box_name); ?></strong>
                            <?php if ($rental->box_number): ?>
                                <br><small>N° <?php echo esc_html($rental->box_number); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html($rental->professional_name); ?>
                            <br><small><?php echo esc_html($rental->professional_email); ?></small>
                        </td>
                        <td>
                            <strong><?php echo MAS_Monthly_Rentals::get_month_name($rental->month); ?> <?php echo $rental->year; ?></strong>
                        </td>
                        <td>
                            <?php echo MAS_Monthly_Rentals::format_weekdays($rental->weekdays); ?>
                        </td>
                        <td>
                            <?php echo date('H:i', strtotime($rental->start_time)); ?> - <?php echo date('H:i', strtotime($rental->end_time)); ?>
                        </td>
                        <td>
                            <?php if ($rental->monthly_price > 0): ?>
                                $<?php echo number_format($rental->monthly_price, 0, ',', '.'); ?>
                            <?php else: ?>
                                <span style="color: #999;">No definido</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $status_colors = array(
                                'active' => '#28a745',
                                'cancelled' => '#dc3545',
                                'completed' => '#6c757d'
                            );
                            $status_labels = array(
                                'active' => 'Activo',
                                'cancelled' => 'Cancelado',
                                'completed' => 'Completado'
                            );
                            $color = isset($status_colors[$rental->status]) ? $status_colors[$rental->status] : '#6c757d';
                            $label = isset($status_labels[$rental->status]) ? $status_labels[$rental->status] : $rental->status;
                            ?>
                            <span style="background: <?php echo $color; ?>; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px;">
                                <?php echo $label; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($rental->status === 'active'): ?>
                                <form method="post" style="display: inline;" onsubmit="return confirm('¿Estás seguro de cancelar este arriendo mensual? Se cancelarán todos los registros individuales asociados.');">
                                    <?php wp_nonce_field('mas_monthly_rental_action'); ?>
                                    <input type="hidden" name="action" value="cancel_monthly_rental">
                                    <input type="hidden" name="rental_id" value="<?php echo $rental->id; ?>">
                                    <button type="submit" class="button button-small" style="color: #dc3545;">Cancelar</button>
                                </form>
                            <?php endif; ?>
                            
                            <form method="post" style="display: inline;" onsubmit="return confirm('¿Estás seguro de ELIMINAR este arriendo mensual? Esta acción no se puede deshacer.');">
                                <?php wp_nonce_field('mas_monthly_rental_action'); ?>
                                <input type="hidden" name="action" value="delete_monthly_rental">
                                <input type="hidden" name="rental_id" value="<?php echo $rental->id; ?>">
                                <button type="submit" class="button button-small" style="color: #dc3545;">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Paginación inferior -->
    <?php if ($total_pages > 1): ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php
            $base_url = add_query_arg(array(
                'page' => 'mas-monthly-rentals',
                'per_page' => $per_page,
                'status' => $filter_status,
                'box_id' => $filter_box,
                'year' => $filter_year
            ), admin_url('admin.php'));
            ?>
            
            <?php if ($current_page > 1): ?>
                <a class="button" href="<?php echo add_query_arg('paged', 1, $base_url); ?>">« Primera</a>
                <a class="button" href="<?php echo add_query_arg('paged', $current_page - 1, $base_url); ?>">‹ Anterior</a>
            <?php endif; ?>
            
            <span class="paging-input">
                Página <?php echo $current_page; ?> de <?php echo $total_pages; ?>
            </span>
            
            <?php if ($current_page < $total_pages): ?>
                <a class="button" href="<?php echo add_query_arg('paged', $current_page + 1, $base_url); ?>">Siguiente ›</a>
                <a class="button" href="<?php echo add_query_arg('paged', $total_pages, $base_url); ?>">Última »</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal para nuevo arriendo mensual -->
<div id="mas-new-monthly-modal" class="mas-modal-overlay" style="display: none;">
    <div class="mas-modal mas-modal-enhanced" style="max-width: 700px;">
        <div class="mas-modal-header mas-modal-header-gradient">
            <div class="mas-modal-title-wrapper">
                <svg class="mas-modal-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <h2>Nuevo Arriendo Mensual</h2>
            </div>
            <button type="button" class="mas-modal-close" onclick="closeMonthlyModal()">&times;</button>
        </div>
        
        <form method="post" action="">
            <?php wp_nonce_field('mas_monthly_rental_action'); ?>
            <input type="hidden" name="action" value="create_monthly_rental">
            
            <div class="mas-modal-body mas-modal-body-enhanced">
                <!-- Sección: Información del Arriendo -->
                <div class="mas-form-section mas-form-section-enhanced">
                    <div class="mas-section-icon-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h3>Información del Arriendo</h3>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label>Box <span class="mas-required">*</span></label>
                            <select name="box_id" id="box_id" required>
                                <option value="">Seleccionar box</option>
                                <?php foreach ($boxes as $box): ?>
                                    <option value="<?php echo $box->id; ?>">
                                        <?php echo esc_html($box->box_number . ' - ' . $box->box_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label>Profesional <span class="mas-required">*</span></label>
                            <select name="professional_id" id="professional_id" required>
                                <option value="">Seleccionar profesional</option>
                                <?php foreach ($professionals as $pro): ?>
                                    <option value="<?php echo $pro->id; ?>">
                                        <?php echo esc_html($pro->display_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Sección: Período -->
                <div class="mas-form-section mas-form-section-enhanced">
                    <div class="mas-section-icon-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h3>Período del Arriendo</h3>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label>Mes <span class="mas-required">*</span></label>
                            <select name="month" required>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php selected($m, date('n')); ?>>
                                        <?php echo MAS_Monthly_Rentals::get_month_name($m); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label>Año <span class="mas-required">*</span></label>
                            <select name="year" required>
                                <?php foreach ($years as $y): ?>
                                    <option value="<?php echo $y; ?>" <?php selected($y, $current_year); ?>><?php echo $y; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mas-form-field mas-form-field-enhanced mas-full-width">
                        <label>Días de la Semana <span class="mas-required">*</span></label>
                        <p class="mas-help-text">Selecciona los días en que el profesional arrendará el box</p>
                        <div class="mas-weekdays-grid">
                            <?php
                            $weekday_names = array(
                                1 => 'Lunes',
                                2 => 'Martes',
                                3 => 'Miércoles',
                                4 => 'Jueves',
                                5 => 'Viernes',
                                6 => 'Sábado',
                                7 => 'Domingo'
                            );
                            foreach ($weekday_names as $num => $name):
                            ?>
                                <label class="mas-weekday-checkbox">
                                    <input type="checkbox" name="weekdays[]" value="<?php echo $num; ?>">
                                    <span class="mas-weekday-label"><?php echo $name; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Sección: Horario y Precio -->
                <div class="mas-form-section mas-form-section-enhanced">
                    <div class="mas-section-icon-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h3>Horario y Precio</h3>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label>Hora Inicio <span class="mas-required">*</span></label>
                            <input type="time" name="start_time" value="09:00" required>
                        </div>
                        
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label>Hora Término <span class="mas-required">*</span></label>
                            <input type="time" name="end_time" value="18:00" required>
                        </div>
                        
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label>Precio Mensual</label>
                            <input type="number" name="monthly_price" id="monthly_price" min="0" step="1000" placeholder="Ej: 200000">
                            <p class="mas-help-text">Dejar en 0 si no aplica precio fijo</p>
                        </div>
                    </div>
                    
                    <div class="mas-form-field mas-form-field-enhanced mas-full-width">
                        <label>Notas u Observaciones</label>
                        <textarea name="notes" id="notes" rows="3" placeholder="Observaciones adicionales sobre este arriendo mensual..."></textarea>
                    </div>
                </div>
                
                <!-- Sección: Estado y Pago -->
                <div class="mas-form-section mas-form-section-enhanced">
                    <div class="mas-section-icon-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h3>Estado y Pago</h3>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label>Estado <span class="mas-required">*</span></label>
                            <select name="status" required>
                                <option value="pending">Pendiente</option>
                                <option value="active" selected>Activo</option>
                                <option value="completed">Completado</option>
                                <option value="cancelled">Cancelado</option>
                            </select>
                        </div>
                        
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label>Estado de Pago <span class="mas-required">*</span></label>
                            <select name="payment_status" required>
                                <option value="pending" selected>Pendiente</option>
                                <option value="paid">Pagado</option>
                                <option value="failed">Fallido</option>
                                <option value="refunded">Reembolsado</option>
                                <option value="interno">Interno</option>
                            </select>
                        </div>
                        
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label>Método de Pago</label>
                            <select name="payment_method">
                                <option value="">No especificado</option>
                                <option value="transferencia">Transferencia Bancaria</option>
                                <option value="pos">POS (Tarjeta)</option>
                                <option value="efectivo">Efectivo</option>
                                <option value="mercadopago">MercadoPago</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mas-modal-footer mas-modal-footer-enhanced">
                <button type="button" class="button mas-btn-cancel" onclick="closeMonthlyModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 18L18 6M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Cancelar
                </button>
                <button type="submit" class="button button-primary mas-btn-submit">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Crear Arriendo Mensual
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Modal Overlay */
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
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    max-width: 700px;
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

/* Modal Header con Gradiente */
.mas-modal-header-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px 24px;
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.mas-modal-title-wrapper {
    display: flex;
    align-items: center;
    gap: 12px;
}

.mas-modal-title-wrapper h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    color: white;
}

.mas-modal-icon {
    opacity: 0.9;
}

.mas-modal-close {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    font-size: 24px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.mas-modal-close:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

/* Modal Body */
.mas-modal-body-enhanced {
    padding: 24px;
}

/* Form Sections */
.mas-form-section-enhanced {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid #e9ecef;
}

.mas-form-section-enhanced:last-child {
    margin-bottom: 0;
}

.mas-section-icon-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid #667eea;
}

.mas-section-icon-title svg {
    color: #667eea;
}

.mas-section-icon-title h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #2c3338;
}

/* Form Fields */
.mas-form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.mas-form-field-enhanced {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.mas-form-field-enhanced label {
    font-weight: 600;
    font-size: 13px;
    color: #2c3338;
}

.mas-form-field-enhanced select,
.mas-form-field-enhanced input[type="text"],
.mas-form-field-enhanced input[type="number"],
.mas-form-field-enhanced input[type="time"],
.mas-form-field-enhanced input[type="date"],
.mas-form-field-enhanced textarea {
    padding: 10px 12px;
    border: 1px solid #dcdcde;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.2s ease;
    width: 100%;
    box-sizing: border-box;
}

.mas-form-field-enhanced select:focus,
.mas-form-field-enhanced input:focus,
.mas-form-field-enhanced textarea:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
    outline: none;
}

.mas-required {
    color: #dc3545;
}

.mas-help-text {
    margin: 4px 0;
    color: #666;
    font-size: 12px;
}

.mas-full-width {
    grid-column: 1 / -1;
}

/* Weekdays Grid */
.mas-weekdays-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 10px;
    margin-top: 8px;
}

.mas-weekday-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    background: white;
    border: 2px solid #dcdcde;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.mas-weekday-checkbox:hover {
    border-color: #667eea;
    background: #f8f9ff;
}

.mas-weekday-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.mas-weekday-checkbox input[type="checkbox"]:checked + .mas-weekday-label {
    color: #667eea;
    font-weight: 600;
}

.mas-weekday-checkbox:has(input:checked) {
    border-color: #667eea;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
}

.mas-weekday-label {
    font-size: 13px;
    color: #2c3338;
}

/* Modal Footer */
.mas-modal-footer-enhanced {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 20px 24px;
    background: #f8f9fa;
    border-top: 1px solid #e9ecef;
    border-radius: 0 0 12px 12px;
}

.mas-btn-cancel,
.mas-btn-submit {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 500;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.mas-btn-cancel {
    background: white;
    border: 1px solid #dcdcde;
    color: #50575e;
}

.mas-btn-cancel:hover {
    background: #f6f7f7;
    border-color: #c3c4c7;
}

.mas-btn-submit {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
}

.mas-btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.tablenav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 10px 0;
}

.tablenav form {
    display: flex;
    gap: 8px;
    align-items: center;
}
</style>

<script>
function openMonthlyModal() {
    document.getElementById('mas-new-monthly-modal').style.display = 'flex';
}

function closeMonthlyModal() {
    document.getElementById('mas-new-monthly-modal').style.display = 'none';
}

// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('mas-new-monthly-modal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeMonthlyModal();
            }
        });
    }
    
    // Close with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeMonthlyModal();
        }
    });
});
</script>
