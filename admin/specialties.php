<?php
/**
 * Página de gestión de especialidades/servicios
 */

if (!defined('ABSPATH')) {
    exit;
}

// Guardar/actualizar especialidad
if (isset($_POST['mas_save_specialty']) && check_admin_referer('mas_specialty_nonce')) {
    $specialty_id = isset($_POST['specialty_id']) ? intval($_POST['specialty_id']) : 0;
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'mas_specialties';
    
    $data = array(
        'name' => sanitize_text_field($_POST['name']),
        'description' => sanitize_textarea_field($_POST['description']),
        'price' => floatval($_POST['price']),
        'duration' => intval($_POST['duration']),
        'status' => sanitize_text_field($_POST['status'])
    );
    
    if ($specialty_id > 0) {
        $result = $wpdb->update($table_name, $data, array('id' => $specialty_id));
        $message = __('Especialidad actualizada exitosamente.', 'medical-appointments');
    } else {
        $result = $wpdb->insert($table_name, $data);
        $message = __('Especialidad creada exitosamente.', 'medical-appointments');
    }
    
    if ($result !== false) {
        echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
        echo '<script>setTimeout(function(){ window.location.href = "' . admin_url('admin.php?page=medical-specialties') . '"; }, 1500);</script>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('Error al guardar la especialidad.', 'medical-appointments') . '</p></div>';
    }
}

// Eliminar especialidad
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $specialty_id = intval($_GET['id']);
    if (check_admin_referer('mas_delete_specialty_' . $specialty_id)) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mas_specialties';
        
        if ($wpdb->delete($table_name, array('id' => $specialty_id))) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Especialidad eliminada exitosamente.', 'medical-appointments') . '</p></div>';
        }
    }
}

// Obtener especialidad para editar
$editing_specialty = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mas_specialties';
    $editing_specialty = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['id'])));
}

// Obtener todas las especialidades
global $wpdb;
$table_name = $wpdb->prefix . 'mas_specialties';
$specialties = $wpdb->get_results("SELECT * FROM $table_name ORDER BY name ASC");
?>

<div class="wrap mas-specialties-page">
    <h1 class="wp-heading-inline"><?php _e('Gestión de Especialidades/Servicios', 'medical-appointments'); ?></h1>
    <a href="#" class="page-title-action" onclick="event.preventDefault(); jQuery('#mas-specialty-modal').fadeIn(200);">
        <?php _e('Agregar Nueva', 'medical-appointments'); ?>
    </a>
    <hr class="wp-header-end">
    
    <!-- Tabla de especialidades -->
    <?php if (!empty($specialties)) : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Nombre', 'medical-appointments'); ?></th>
                    <th><?php _e('Descripción', 'medical-appointments'); ?></th>
                    <th><?php _e('Precio', 'medical-appointments'); ?></th>
                    <th><?php _e('Duración (min)', 'medical-appointments'); ?></th>
                    <th><?php _e('Estado', 'medical-appointments'); ?></th>
                    <th><?php _e('Acciones', 'medical-appointments'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($specialties as $specialty) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($specialty->name); ?></strong></td>
                        <td><?php echo esc_html($specialty->description); ?></td>
                        <td><strong>$<?php echo number_format($specialty->price, 0, ',', '.'); ?></strong></td>
                        <td><?php echo esc_html($specialty->duration); ?> min</td>
                        <td>
                            <span class="mas-status-badge mas-status-<?php echo esc_attr($specialty->status); ?>">
                                <?php echo $specialty->status === 'active' ? __('Activa', 'medical-appointments') : __('Inactiva', 'medical-appointments'); ?>
                            </span>
                        </td>
                        <td class="mas-table-actions">
                            <a href="<?php echo admin_url('admin.php?page=medical-specialties&action=edit&id=' . $specialty->id); ?>" class="button button-small">
                                <?php _e('Editar', 'medical-appointments'); ?>
                            </a>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=medical-specialties&action=delete&id=' . $specialty->id), 'mas_delete_specialty_' . $specialty->id); ?>" class="button button-small" onclick="return confirm('<?php _e('¿Está seguro de eliminar esta especialidad?', 'medical-appointments'); ?>')">
                                <?php _e('Eliminar', 'medical-appointments'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p class="mas-no-data"><?php _e('No hay especialidades registradas.', 'medical-appointments'); ?></p>
    <?php endif; ?>
</div>

<!-- Modal de edición/creación -->
<div id="mas-specialty-modal" class="mas-modal-overlay" style="display: none;">
    <div class="mas-modal">
        <div class="mas-modal-header">
            <h2><?php echo $editing_specialty ? __('Editar Especialidad', 'medical-appointments') : __('Nueva Especialidad', 'medical-appointments'); ?></h2>
            <button type="button" class="mas-modal-close" data-modal-close>&times;</button>
        </div>
        
        <form method="post" action="">
            <?php wp_nonce_field('mas_specialty_nonce'); ?>
            <input type="hidden" name="specialty_id" value="<?php echo $editing_specialty ? esc_attr($editing_specialty->id) : ''; ?>">
            
            <div class="mas-modal-body">
                <div class="mas-form-field">
                    <label><?php _e('Nombre de la Especialidad/Servicio', 'medical-appointments'); ?> *</label>
                    <input type="text" name="name" value="<?php echo $editing_specialty ? esc_attr($editing_specialty->name) : ''; ?>" required>
                </div>
                
                <div class="mas-form-field">
                    <label><?php _e('Descripción', 'medical-appointments'); ?></label>
                    <textarea name="description" rows="3"><?php echo $editing_specialty ? esc_textarea($editing_specialty->description) : ''; ?></textarea>
                </div>
                
                <div class="mas-form-row">
                    <div class="mas-form-field">
                        <label><?php _e('Precio (CLP)', 'medical-appointments'); ?> *</label>
                        <input type="number" name="price" value="<?php echo $editing_specialty ? esc_attr($editing_specialty->price) : ''; ?>" min="0" step="100" required>
                    </div>
                    
                    <div class="mas-form-field">
                        <label><?php _e('Duración (minutos)', 'medical-appointments'); ?> *</label>
                        <select name="duration" required>
                            <option value="30" <?php echo ($editing_specialty && $editing_specialty->duration == 30) ? 'selected' : ''; ?>>30 minutos</option>
                            <option value="45" <?php echo ($editing_specialty && $editing_specialty->duration == 45) ? 'selected' : ''; ?>>45 minutos</option>
                            <option value="60" <?php echo ($editing_specialty && $editing_specialty->duration == 60) ? 'selected' : ''; ?>>60 minutos</option>
                            <option value="90" <?php echo ($editing_specialty && $editing_specialty->duration == 90) ? 'selected' : ''; ?>>90 minutos</option>
                            <option value="120" <?php echo ($editing_specialty && $editing_specialty->duration == 120) ? 'selected' : ''; ?>>120 minutos</option>
                        </select>
                    </div>
                </div>
                
                <div class="mas-form-field">
                    <label><?php _e('Estado', 'medical-appointments'); ?> *</label>
                    <select name="status" required>
                        <option value="active" <?php echo ($editing_specialty && $editing_specialty->status == 'active') ? 'selected' : ''; ?>><?php _e('Activa', 'medical-appointments'); ?></option>
                        <option value="inactive" <?php echo ($editing_specialty && $editing_specialty->status == 'inactive') ? 'selected' : ''; ?>><?php _e('Inactiva', 'medical-appointments'); ?></option>
                    </select>
                </div>
            </div>
            
            <div class="mas-modal-footer">
                <button type="button" class="button" data-modal-close><?php _e('Cancelar', 'medical-appointments'); ?></button>
                <button type="submit" name="mas_save_specialty" class="button button-primary"><?php _e('Guardar', 'medical-appointments'); ?></button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('[data-modal-close]').on('click', function() {
        $('#mas-specialty-modal').fadeOut(200);
    });
});
</script>

<?php if ($editing_specialty) : ?>
<script>
jQuery(document).ready(function($) {
    $('#mas-specialty-modal').fadeIn(200);
});
</script>
<?php endif; ?>
