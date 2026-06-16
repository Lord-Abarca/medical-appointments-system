<?php
/**
 * Página de gestión de servicios
 */

if (!defined('ABSPATH')) {
    exit;
}

$mas_services = new MAS_Services();

// Procesar acciones
if (isset($_GET['action']) && isset($_GET['id'])) {
    $service_id = intval($_GET['id']);
    
    if ($_GET['action'] === 'delete' && check_admin_referer('mas_delete_service_' . $service_id)) {
        if ($mas_services->delete_service($service_id)) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Servicio eliminado exitosamente.', 'medical-appointments') . '</p></div>';
        }
    }
}

// Guardar/actualizar servicio
if (isset($_POST['mas_save_service']) && check_admin_referer('mas_service_nonce')) {
    $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
    
    error_log('[MAS] Procesando formulario de servicio. ID: ' . $service_id);
    
    $data = array(
        'service_name' => sanitize_text_field($_POST['service_name']),
        'description' => sanitize_textarea_field($_POST['description']),
        'duration' => intval($_POST['duration']),
        'price' => floatval($_POST['price']),
        'status' => sanitize_text_field($_POST['status'])
    );
    
    if (empty($data['service_name']) || empty($data['price'])) {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('El nombre y precio del servicio son obligatorios.', 'medical-appointments') . '</p></div>';
    } else {
        if ($service_id > 0) {
            if ($mas_services->update_service($service_id, $data)) {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Servicio actualizado exitosamente.', 'medical-appointments') . '</p></div>';
                echo '<script>setTimeout(function(){ window.location.href = "' . admin_url('admin.php?page=medical-services') . '"; }, 1500);</script>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Error al actualizar el servicio.', 'medical-appointments') . '</p></div>';
            }
        } else {
            $result = $mas_services->create_service($data);
            
            if ($result) {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Servicio creado exitosamente.', 'medical-appointments') . '</p></div>';
                echo '<script>setTimeout(function(){ window.location.href = "' . admin_url('admin.php?page=medical-services') . '"; }, 1500);</script>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Error al crear el servicio.', 'medical-appointments') . '</p></div>';
            }
        }
    }
}

// Obtener servicio para editar
$editing_service = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editing_service = $mas_services->get_service(intval($_GET['id']));
}

// Obtener todos los servicios
$services = $mas_services->get_services('');
?>

<div class="wrap mas-services-page">
    <h1 class="wp-heading-inline"><?php _e('Gestión de Servicios', 'medical-appointments'); ?></h1>
    <a href="#" class="page-title-action" onclick="event.preventDefault(); resetAndOpenModal();">
        <?php _e('Agregar Nuevo', 'medical-appointments'); ?>
    </a>
    <hr class="wp-header-end">
    
    <p class="description">
        <?php _e('Los servicios se pueden asignar a profesionales y seleccionar al agendar citas médicas.', 'medical-appointments'); ?>
    </p>
    
    <!-- Tabla de servicios -->
    <?php if (!empty($services)) : ?>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th style="width: 60px;"><?php _e('ID', 'medical-appointments'); ?></th>
                    <th><?php _e('Nombre del Servicio', 'medical-appointments'); ?></th>
                    <th><?php _e('Descripción', 'medical-appointments'); ?></th>
                    <th style="width: 100px;"><?php _e('Duración', 'medical-appointments'); ?></th>
                    <th style="width: 120px;"><?php _e('Precio', 'medical-appointments'); ?></th>
                    <th style="width: 100px;"><?php _e('Estado', 'medical-appointments'); ?></th>
                    <th style="width: 180px;"><?php _e('Acciones', 'medical-appointments'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $service) : ?>
                    <tr>
                        <td><strong>#<?php echo esc_html($service->id); ?></strong></td>
                        <td><strong><?php echo esc_html($service->service_name); ?></strong></td>
                        <td><?php echo esc_html($service->description); ?></td>
                        <td><?php echo esc_html($service->duration); ?> min</td>
                        <td><strong>$<?php echo number_format($service->price, 0, ',', '.'); ?></strong></td>
                        <td>
                            <span class="mas-status-badge mas-status-<?php echo esc_attr($service->status); ?>">
                                <?php echo $service->status === 'active' ? __('Activo', 'medical-appointments') : __('Inactivo', 'medical-appointments'); ?>
                            </span>
                        </td>
                        <td class="mas-table-actions">
                            <a href="<?php echo admin_url('admin.php?page=medical-services&action=edit&id=' . $service->id); ?>" class="button button-small">
                                <?php _e('Editar', 'medical-appointments'); ?>
                            </a>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=medical-services&action=delete&id=' . $service->id), 'mas_delete_service_' . $service->id); ?>" class="button button-small" onclick="return confirm('<?php _e('¿Está seguro de eliminar este servicio?', 'medical-appointments'); ?>')">
                                <?php _e('Eliminar', 'medical-appointments'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p class="mas-no-data"><?php _e('No hay servicios registrados.', 'medical-appointments'); ?></p>
    <?php endif; ?>
</div>

<!-- Modal de edición/creación -->
<div id="mas-service-modal" class="mas-modal-overlay" style="display: none;">
    <div class="mas-modal mas-modal-enhanced">
        <div class="mas-modal-header mas-modal-header-gradient">
            <div class="mas-modal-title-wrapper">
                <svg class="mas-modal-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <h2><?php echo $editing_service ? __('Editar Servicio', 'medical-appointments') : __('Nuevo Servicio', 'medical-appointments'); ?></h2>
            </div>
            <button type="button" class="mas-modal-close" data-modal-close>&times;</button>
        </div>
        
        <form method="post" action="" id="service-form">
            <?php wp_nonce_field('mas_service_nonce'); ?>
            <input type="hidden" name="service_id" id="service_id" value="<?php echo $editing_service ? esc_attr($editing_service->id) : ''; ?>">
            
            <div class="mas-modal-body mas-modal-body-enhanced">
                <div class="mas-form-section mas-form-section-enhanced">
                    <div class="mas-section-icon-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h3><?php _e('Información del Servicio', 'medical-appointments'); ?></h3>
                    </div>
                    
                    <div class="mas-form-field mas-form-field-enhanced">
                        <label><?php _e('Nombre del Servicio', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                        <input type="text" name="service_name" id="service_name" value="<?php echo $editing_service ? esc_attr($editing_service->service_name) : ''; ?>" required placeholder="Ej: Psicología Clínica, Psiquiatría, etc.">
                    </div>
                    
                    <div class="mas-form-field mas-form-field-enhanced">
                        <label><?php _e('Descripción', 'medical-appointments'); ?></label>
                        <textarea name="description" id="service_description" rows="3" placeholder="Breve descripción del servicio"><?php echo $editing_service ? esc_textarea($editing_service->description) : ''; ?></textarea>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Duración (minutos)', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <input type="number" name="duration" id="service_duration" value="<?php echo $editing_service ? esc_attr($editing_service->duration) : '60'; ?>" min="15" step="15" required>
                        </div>
                        
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Precio (CLP)', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <input type="number" name="price" id="service_price" value="<?php echo $editing_service ? esc_attr($editing_service->price) : ''; ?>" min="0" step="100" required>
                        </div>
                    </div>
                    
                    <div class="mas-form-field mas-form-field-enhanced">
                        <label><?php _e('Estado', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                        <select name="status" id="service_status" required>
                            <option value="active" <?php echo ($editing_service && $editing_service->status == 'active') ? 'selected' : ''; ?>><?php _e('Activo', 'medical-appointments'); ?></option>
                            <option value="inactive" <?php echo ($editing_service && $editing_service->status == 'inactive') ? 'selected' : ''; ?>><?php _e('Inactivo', 'medical-appointments'); ?></option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="mas-modal-footer mas-modal-footer-enhanced">
                <button type="button" class="button mas-button-cancel" data-modal-close><?php _e('Cancelar', 'medical-appointments'); ?></button>
                <button type="submit" name="mas_save_service" class="button button-primary mas-button-save">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <?php _e('Guardar', 'medical-appointments'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    window.resetAndOpenModal = function() {
        $('#service_id').val('');
        $('#service_name').val('');
        $('#service_description').val('');
        $('#service_duration').val('60');
        $('#service_price').val('');
        $('#service_status').val('active');
        $('#mas-service-modal').fadeIn(200);
    };
    
    // Modal controls
    $('[data-modal-close]').on('click', function() {
        $('#mas-service-modal').fadeOut(200);
    });
});
</script>

<?php if ($editing_service) : ?>
<script>
jQuery(document).ready(function($) {
    $('#mas-service-modal').fadeIn(200);
});
</script>
<?php endif; ?>
