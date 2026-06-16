<?php
/**
 * Página de gestión de boxes
 */

if (!defined('ABSPATH')) {
    exit;
}

$mas_boxes = new MAS_Boxes();

// Procesar acciones
if (isset($_GET['action']) && isset($_GET['id'])) {
    $box_id = intval($_GET['id']);
    
    if ($_GET['action'] === 'delete' && check_admin_referer('mas_delete_box_' . $box_id)) {
        if ($mas_boxes->delete_box($box_id)) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Box eliminado exitosamente.', 'medical-appointments') . '</p></div>';
        }
    }
}

// Guardar/actualizar box
if (isset($_POST['mas_save_box']) && check_admin_referer('mas_box_nonce')) {
    $box_id = isset($_POST['box_id']) ? intval($_POST['box_id']) : 0;
    
    error_log('[MAS] Procesando formulario de box. ID: ' . $box_id);
    
    $data = array(
        'box_name' => sanitize_text_field($_POST['box_name']),
        'box_number' => sanitize_text_field($_POST['box_number']),
        'description' => sanitize_textarea_field($_POST['description']),
        'price_per_hour' => floatval($_POST['price_per_hour']),
        'status' => sanitize_text_field($_POST['status'])
    );
    
    if (isset($_POST['image_url'])) {
        $data['image_url'] = !empty($_POST['image_url']) ? esc_url_raw($_POST['image_url']) : '';
    }
    
    if (empty($data['box_name']) || empty($data['box_number'])) {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('El nombre y número de box son obligatorios.', 'medical-appointments') . '</p></div>';
    } else {
        if ($box_id > 0) {
            if ($mas_boxes->update_box($box_id, $data)) {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Box actualizado exitosamente.', 'medical-appointments') . '</p></div>';
                echo '<script>setTimeout(function(){ window.location.href = "' . admin_url('admin.php?page=medical-boxes') . '"; }, 1500);</script>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Error al actualizar el box.', 'medical-appointments') . '</p></div>';
            }
        } else {
            $result = $mas_boxes->create_box($data);
            
            if ($result) {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Box creado exitosamente.', 'medical-appointments') . '</p></div>';
                echo '<script>setTimeout(function(){ window.location.href = "' . admin_url('admin.php?page=medical-boxes') . '"; }, 1500);</script>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Error al crear el box. Verifique que el número de box no esté en uso.', 'medical-appointments') . '</p></div>';
            }
        }
    }
}

// Obtener box para editar
$editing_box = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editing_box = $mas_boxes->get_box(intval($_GET['id']));
}

// Obtener todos los boxes
$boxes = $mas_boxes->get_boxes('');
?>

<div class="wrap mas-boxes-page">
    <h1 class="wp-heading-inline"><?php _e('Gestión de Boxes', 'medical-appointments'); ?></h1>
    <!-- Added resetAndOpenModal function to clear form fields -->
    <a href="#" class="page-title-action" onclick="event.preventDefault(); resetAndOpenModal();">
        <?php _e('Agregar Nuevo', 'medical-appointments'); ?>
    </a>
    <hr class="wp-header-end">
    
    <!-- Tabla de boxes -->
    <?php if (!empty($boxes)) : ?>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <!-- Added image column -->
                    <th style="width: 80px;"><?php _e('Imagen', 'medical-appointments'); ?></th>
                    <th><?php _e('Número', 'medical-appointments'); ?></th>
                    <th><?php _e('Nombre', 'medical-appointments'); ?></th>
                    <th><?php _e('Descripción', 'medical-appointments'); ?></th>
                    <th><?php _e('Precio por Hora', 'medical-appointments'); ?></th>
                    <th><?php _e('Estado', 'medical-appointments'); ?></th>
                    <th><?php _e('Acciones', 'medical-appointments'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($boxes as $box) : ?>
                    <tr>
                        <!-- Display box image thumbnail -->
                        <td>
                            <?php if (!empty($box->image_url)) : ?>
                                <img src="<?php echo esc_url($box->image_url); ?>" alt="<?php echo esc_attr($box->box_name); ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                            <?php else : ?>
                                <div style="width: 60px; height: 60px; background: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                    <span class="dashicons dashicons-format-image" style="color: #ccc; font-size: 24px;"></span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo esc_html($box->box_number); ?></strong></td>
                        <td><?php echo esc_html($box->box_name); ?></td>
                        <td><?php echo esc_html($box->description); ?></td>
                        <td><strong>$<?php echo number_format($box->price_per_hour, 0, ',', '.'); ?></strong></td>
                        <td>
                            <span class="mas-status-badge mas-status-<?php echo esc_attr($box->status); ?>">
                                <?php echo $box->status === 'active' ? __('Activo', 'medical-appointments') : __('Inactivo', 'medical-appointments'); ?>
                            </span>
                        </td>
                        <td class="mas-table-actions">
                            <a href="<?php echo admin_url('admin.php?page=medical-boxes&action=edit&id=' . $box->id); ?>" class="button button-small">
                                <?php _e('Editar', 'medical-appointments'); ?>
                            </a>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=medical-boxes&action=delete&id=' . $box->id), 'mas_delete_box_' . $box->id); ?>" class="button button-small" onclick="return confirm('<?php _e('¿Está seguro de eliminar este box?', 'medical-appointments'); ?>')">
                                <?php _e('Eliminar', 'medical-appointments'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p class="mas-no-data"><?php _e('No hay boxes registrados.', 'medical-appointments'); ?></p>
    <?php endif; ?>
</div>

<!-- Modal de edición/creación -->
<div id="mas-box-modal" class="mas-modal-overlay" style="display: none;">
    <div class="mas-modal mas-modal-enhanced">
        <!-- Updated modal header with modern design and icon -->
        <div class="mas-modal-header mas-modal-header-gradient">
            <div class="mas-modal-title-wrapper">
                <svg class="mas-modal-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <h2><?php echo $editing_box ? __('Editar Box', 'medical-appointments') : __('Nuevo Box', 'medical-appointments'); ?></h2>
            </div>
            <button type="button" class="mas-modal-close" data-modal-close>&times;</button>
        </div>
        
        <form method="post" action="">
            <?php wp_nonce_field('mas_box_nonce'); ?>
            <input type="hidden" name="box_id" value="<?php echo $editing_box ? esc_attr($editing_box->id) : ''; ?>">
            <input type="hidden" name="image_url" id="box_image_url" value="<?php echo $editing_box && !empty($editing_box->image_url) ? esc_attr($editing_box->image_url) : ''; ?>">
            
            <!-- Updated modal body with enhanced styling -->
            <div class="mas-modal-body mas-modal-body-enhanced">
                <div class="mas-form-section mas-form-section-enhanced">
                    <div class="mas-section-icon-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h3><?php _e('Información del Box', 'medical-appointments'); ?></h3>
                    </div>
                    
                    <!-- Added image upload field -->
                    <div class="mas-form-field mas-form-field-enhanced">
                        <label><?php _e('Imagen del Box', 'medical-appointments'); ?></label>
                        <div class="mas-image-upload-container">
                            <div id="box_image_preview" class="mas-image-preview">
                                <?php if ($editing_box && !empty($editing_box->image_url)) : ?>
                                    <img src="<?php echo esc_url($editing_box->image_url); ?>" alt="Preview">
                                <?php else : ?>
                                    <span class="dashicons dashicons-format-image"></span>
                                    <p><?php _e('Sin imagen', 'medical-appointments'); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="mas-image-upload-buttons">
                                <button type="button" class="button" id="upload_box_image_button">
                                    <?php _e('Seleccionar Imagen', 'medical-appointments'); ?>
                                </button>
                                <button type="button" class="button" id="remove_box_image_button" style="<?php echo (!$editing_box || empty($editing_box->image_url)) ? 'display:none;' : ''; ?>">
                                    <?php _e('Eliminar Imagen', 'medical-appointments'); ?>
                                </button>
                            </div>
                        </div>
                        <span class="description"><?php _e('Imagen recomendada: 800x600px', 'medical-appointments'); ?></span>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Número de Box', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <input type="text" name="box_number" value="<?php echo $editing_box ? esc_attr($editing_box->box_number) : ''; ?>" required placeholder="Ej: B-01, Box 1, etc.">
                        </div>
                        
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Nombre del Box', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <input type="text" name="box_name" value="<?php echo $editing_box ? esc_attr($editing_box->box_name) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="mas-form-field mas-form-field-enhanced">
                        <label><?php _e('Descripción', 'medical-appointments'); ?></label>
                        <textarea name="description" rows="3" placeholder="Breve descripción del box"><?php echo $editing_box ? esc_textarea($editing_box->description) : ''; ?></textarea>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Precio por Hora (CLP)', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <input type="number" name="price_per_hour" value="<?php echo $editing_box ? esc_attr($editing_box->price_per_hour) : ''; ?>" min="0" step="100" required>
                        </div>
                        
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Estado', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <select name="status" required>
                                <option value="active" <?php echo ($editing_box && $editing_box->status == 'active') ? 'selected' : ''; ?>><?php _e('Activo', 'medical-appointments'); ?></option>
                                <option value="inactive" <?php echo ($editing_box && $editing_box->status == 'inactive') ? 'selected' : ''; ?>><?php _e('Inactivo', 'medical-appointments'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Updated modal footer with enhanced styling and icon button -->
            <div class="mas-modal-footer mas-modal-footer-enhanced">
                <button type="button" class="button mas-button-cancel" data-modal-close><?php _e('Cancelar', 'medical-appointments'); ?></button>
                <button type="submit" name="mas_save_box" class="button button-primary mas-button-save">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <?php _e('Guardar Box', 'medical-appointments'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Added image upload JavaScript -->
<script>
jQuery(document).ready(function($) {
    if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
        console.error('[MAS] WordPress media library not loaded');
        return;
    }
    
    var mediaUploader;
    
    $('#upload_box_image_button').on('click', function(e) {
        e.preventDefault();
        
        console.log('[v0] Upload button clicked');
        
        // If the uploader object has already been created, reopen the dialog
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        // Create the media frame
        mediaUploader = wp.media({
            title: '<?php _e('Seleccionar Imagen del Box', 'medical-appointments'); ?>',
            button: {
                text: '<?php _e('Usar esta imagen', 'medical-appointments'); ?>'
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });
        
        // When an image is selected, run a callback
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            console.log('[v0] Image selected:', attachment.url);
            
            $('#box_image_url').val(attachment.url);
            $('#box_image_preview').html('<img src="' + attachment.url + '" alt="Preview">');
            $('#remove_box_image_button').show();
        });
        
        // Open the uploader dialog
        mediaUploader.open();
    });
    
    // Remove image button handler
    $('#remove_box_image_button').on('click', function(e) {
        e.preventDefault();
        $('#box_image_url').val('');
        $('#box_image_preview').html('<span class="dashicons dashicons-format-image"></span><p><?php _e('Sin imagen', 'medical-appointments'); ?></p>');
        $(this).hide();
    });
    
    // Modal controls
    $('[data-modal-close]').on('click', function() {
        $('#mas-box-modal').fadeOut(200);
    });
});

function resetAndOpenModal() {
    // Clear all input fields
    jQuery('input[name="box_id"]').val('');
    jQuery('input[name="box_number"]').val('');
    jQuery('input[name="box_name"]').val('');
    jQuery('textarea[name="description"]').val('');
    jQuery('input[name="price_per_hour"]').val('');
    jQuery('select[name="status"]').val('active');
    
    // Clear image
    jQuery('#box_image_url').val('');
    jQuery('#box_image_preview').html('<span class="dashicons dashicons-format-image"></span><p><?php _e('Sin imagen', 'medical-appointments'); ?></p>');
    jQuery('#remove_box_image_button').hide();
    
    // Update modal title
    jQuery('#mas-box-modal .mas-modal-header h2').text('<?php _e('Nuevo Box', 'medical-appointments'); ?>');
    
    // Open modal
    jQuery('#mas-box-modal').fadeIn(200);
}
</script>

<style>
/* Added styles for image upload */
.mas-image-upload-container {
    display: flex;
    gap: 20px;
    align-items: flex-start;
}

.mas-image-preview {
    width: 200px;
    height: 150px;
    border: 2px dashed #ddd;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #f9f9f9;
    overflow: hidden;
}

.mas-image-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.mas-image-preview .dashicons {
    font-size: 48px;
    color: #ccc;
}

.mas-image-preview p {
    margin: 8px 0 0 0;
    color: #999;
    font-size: 12px;
}

.mas-image-upload-buttons {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* Added styles for enhanced modal */
.mas-modal-enhanced {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    padding: 20px;
    width: 500px;
    margin: 0 auto;
}

.mas-modal-header-gradient {
    background: linear-gradient(135deg, #6a11cb, #2575fc);
    color: #fff;
    padding: 10px 20px;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
}

.mas-modal-title-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
}

.mas-modal-icon {
    margin-right: 8px;
}

.mas-modal-body-enhanced {
    margin-top: 20px;
}

.mas-form-section-enhanced {
    border-bottom: 1px solid #ddd;
    padding-bottom: 20px;
}

.mas-section-icon-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}

.mas-form-field-enhanced {
    margin-bottom: 20px;
}

.mas-form-field-enhanced label {
    display: block;
    margin-bottom: 5px;
}

.mas-form-field-enhanced input,
.mas-form-field-enhanced textarea,
.mas-form-field-enhanced select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.mas-modal-footer-enhanced {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.mas-button-cancel {
    background-color: #f0f0f0;
    color: #333;
    border-color: #ddd;
}

.mas-button-save {
    background-color: #2575fc;
    color: #fff;
    border-color: #2575fc;
}

.mas-button-save svg {
    margin-right: 5px;
}
</style>

<?php if ($editing_box) : ?>
<script>
jQuery(document).ready(function($) {
    $('#mas-box-modal').fadeIn(200);
});
</script>
<?php endif; ?>
