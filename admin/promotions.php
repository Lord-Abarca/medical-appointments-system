<?php
/**
 * Promotions Management Page
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-promotions.php';

$mas_promotions = new MAS_Promotions();

// Handle form submission
if (isset($_POST['mas_save_promotion']) && check_admin_referer('mas_promotion_nonce')) {
    $promotion_id = isset($_POST['promotion_id']) ? absint($_POST['promotion_id']) : 0;
    
    $promotion_data = array(
        'name' => sanitize_text_field($_POST['name']),
        'description' => sanitize_textarea_field($_POST['description']),
        'blocks_quantity' => absint($_POST['blocks_quantity']),
        'package_price' => floatval($_POST['package_price']),
        'discount_percentage' => floatval($_POST['discount_percentage']),
        'status' => sanitize_text_field($_POST['status']),
        'start_date' => !empty($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null,
        'end_date' => !empty($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null,
    );
    
    if ($promotion_id > 0) {
        $result = $mas_promotions->update_promotion($promotion_id, $promotion_data);
        if (!is_wp_error($result)) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Promoción actualizada exitosamente', 'medical-appointments') . '</p></div>';
        }
    } else {
        $result = $mas_promotions->create_promotion($promotion_data);
        if (!is_wp_error($result)) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Promoción creada exitosamente', 'medical-appointments') . '</p></div>';
        }
    }
}

// Handle delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    check_admin_referer('delete_promotion_' . $_GET['id']);
    $mas_promotions->delete_promotion($_GET['id']);
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Promoción eliminada', 'medical-appointments') . '</p></div>';
}

// Get promotion for editing
$editing_promotion = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editing_promotion = $mas_promotions->get_promotion($_GET['id']);
}

// Get all promotions
$promotions = $mas_promotions->get_all_promotions();
?>

<div class="wrap mas-admin-wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Promociones de Arriendo', 'medical-appointments'); ?>
    </h1>
    
    <button type="button" class="page-title-action" onclick="resetAndOpenPromotionModal()">
        <?php _e('Agregar Nueva', 'medical-appointments'); ?>
    </button>
    
    <hr class="wp-header-end">
    
    <div class="mas-promotions-list">
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Nombre', 'medical-appointments'); ?></th>
                    <th><?php _e('Bloques', 'medical-appointments'); ?></th>
                    <th><?php _e('Precio Paquete', 'medical-appointments'); ?></th>
                    <th><?php _e('Descuento', 'medical-appointments'); ?></th>
                    <th><?php _e('Vigencia', 'medical-appointments'); ?></th>
                    <th><?php _e('Estado', 'medical-appointments'); ?></th>
                    <th><?php _e('Acciones', 'medical-appointments'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($promotions)) : ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 30px;">
                            <?php _e('No hay promociones registradas', 'medical-appointments'); ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($promotions as $promotion) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($promotion->name); ?></strong>
                                <?php if ($promotion->description) : ?>
                                    <br><small><?php echo esc_html(wp_trim_words($promotion->description, 10)); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($promotion->blocks_quantity); ?> bloques</td>
                            <td>$<?php echo number_format($promotion->package_price, 0, ',', '.'); ?></td>
                            <td>
                                <?php if ($promotion->discount_percentage > 0) : ?>
                                    <span class="mas-badge mas-badge-success"><?php echo number_format($promotion->discount_percentage, 1); ?>% OFF</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($promotion->start_date && $promotion->end_date) : ?>
                                    <?php echo date('d/m/Y', strtotime($promotion->start_date)); ?> - <?php echo date('d/m/Y', strtotime($promotion->end_date)); ?>
                                <?php elseif ($promotion->start_date) : ?>
                                    Desde <?php echo date('d/m/Y', strtotime($promotion->start_date)); ?>
                                <?php elseif ($promotion->end_date) : ?>
                                    Hasta <?php echo date('d/m/Y', strtotime($promotion->end_date)); ?>
                                <?php else : ?>
                                    Sin límite
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($promotion->status === 'active') : ?>
                                    <span class="mas-badge mas-badge-success"><?php _e('Activa', 'medical-appointments'); ?></span>
                                <?php else : ?>
                                    <span class="mas-badge mas-badge-secondary"><?php _e('Inactiva', 'medical-appointments'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small" onclick='editPromotion(<?php echo json_encode($promotion); ?>)'>
                                    <?php _e('Editar', 'medical-appointments'); ?>
                                </button>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=medical-promotions&action=delete&id=' . $promotion->id), 'delete_promotion_' . $promotion->id); ?>" 
                                   class="button button-small button-link-delete" 
                                   onclick="return confirm('<?php _e('¿Estás seguro de eliminar esta promoción?', 'medical-appointments'); ?>')">
                                    <?php _e('Eliminar', 'medical-appointments'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Promotion Modal -->
<div id="mas-promotion-modal" class="mas-modal" style="display: <?php echo $editing_promotion ? 'flex' : 'none'; ?>;">
    <div class="mas-modal-content mas-modal-content-enhanced">
        <div class="mas-modal-header mas-modal-header-enhanced">
            <div class="mas-modal-header-content">
                <svg class="mas-modal-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <h2 id="modal-title"><?php _e('Agregar Promoción', 'medical-appointments'); ?></h2>
            </div>
            <button type="button" class="mas-modal-close" data-modal-close>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
        
        <form method="post" action="">
            <?php wp_nonce_field('mas_promotion_nonce'); ?>
            <input type="hidden" name="promotion_id" value="<?php echo $editing_promotion ? esc_attr($editing_promotion->id) : ''; ?>">
            
            <div class="mas-modal-body mas-modal-body-enhanced">
                <div class="mas-form-section mas-form-section-enhanced">
                    <div class="mas-section-icon-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h3><?php _e('Información de la Promoción', 'medical-appointments'); ?></h3>
                    </div>
                    
                    <div class="mas-form-field mas-form-field-enhanced">
                        <label><?php _e('Nombre de la Promoción', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                        <input type="text" name="name" placeholder="Ej: Paquete 4 Bloques" value="<?php echo $editing_promotion ? esc_attr($editing_promotion->name) : ''; ?>" required>
                    </div>
                    
                    <div class="mas-form-field mas-form-field-enhanced">
                        <label><?php _e('Descripción', 'medical-appointments'); ?></label>
                        <textarea name="description" rows="3" placeholder="Descripción de la promoción..."><?php echo $editing_promotion ? esc_textarea($editing_promotion->description) : ''; ?></textarea>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Cantidad de Bloques', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <input type="number" name="blocks_quantity" id="blocks_quantity" min="1" placeholder="Ej: 4" value="<?php echo $editing_promotion ? esc_attr($editing_promotion->blocks_quantity) : ''; ?>" required>
                            <small class="mas-field-description"><?php _e('Número de bloques incluidos en el paquete', 'medical-appointments'); ?></small>
                        </div>
                        
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Precio del Paquete', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <input type="number" name="package_price" id="package_price" min="0" step="0.01" placeholder="Ej: 35000" value="<?php echo $editing_promotion ? esc_attr($editing_promotion->package_price) : ''; ?>" required>
                            <small class="mas-field-description"><?php _e('Precio total del paquete', 'medical-appointments'); ?></small>
                        </div>
                    </div>
                    
                    <div class="mas-form-field mas-form-field-enhanced">
                        <label><?php _e('Descuento (%)', 'medical-appointments'); ?></label>
                        <input type="number" name="discount_percentage" id="discount_percentage" min="0" max="100" step="0.01" placeholder="Ej: 15" value="<?php echo $editing_promotion ? esc_attr($editing_promotion->discount_percentage) : ''; ?>">
                        <small class="mas-field-description"><?php _e('Se calcula automáticamente si se deja en blanco', 'medical-appointments'); ?></small>
                    </div>
                </div>
                
                <div class="mas-form-section mas-form-section-enhanced">
                    <div class="mas-section-icon-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h3><?php _e('Vigencia y Estado', 'medical-appointments'); ?></h3>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Fecha de Inicio', 'medical-appointments'); ?></label>
                            <input type="date" name="start_date" value="<?php echo $editing_promotion ? esc_attr($editing_promotion->start_date) : ''; ?>">
                            <small class="mas-field-description"><?php _e('Dejar en blanco para sin límite', 'medical-appointments'); ?></small>
                        </div>
                        
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Fecha de Fin', 'medical-appointments'); ?></label>
                            <input type="date" name="end_date" value="<?php echo $editing_promotion ? esc_attr($editing_promotion->end_date) : ''; ?>">
                            <small class="mas-field-description"><?php _e('Dejar en blanco para sin límite', 'medical-appointments'); ?></small>
                        </div>
                    </div>
                    
                    <div class="mas-form-field mas-form-field-enhanced">
                        <label><?php _e('Estado', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                        <select name="status" required>
                            <option value="active" <?php echo (!$editing_promotion || $editing_promotion->status === 'active') ? 'selected' : ''; ?>>
                                <?php _e('Activa', 'medical-appointments'); ?>
                            </option>
                            <option value="inactive" <?php echo ($editing_promotion && $editing_promotion->status === 'inactive') ? 'selected' : ''; ?>>
                                <?php _e('Inactiva', 'medical-appointments'); ?>
                            </option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="mas-modal-footer mas-modal-footer-enhanced">
                <button type="button" class="button mas-button-cancel" data-modal-close><?php _e('Cancelar', 'medical-appointments'); ?></button>
                <button type="submit" name="mas_save_promotion" class="button button-primary mas-button-save">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <?php _e('Guardar Promoción', 'medical-appointments'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Modal controls
    $('[data-modal-close]').on('click', function() {
        $('#mas-promotion-modal').fadeOut(200);
    });
    
    // Close modal when clicking outside
    $('#mas-promotion-modal').on('click', function(e) {
        if ($(e.target).is('#mas-promotion-modal')) {
            $(this).fadeOut(200);
        }
    });
    
    window.resetAndOpenPromotionModal = function() {
        // Clear all form fields
        $('input[name="promotion_id"]').val('');
        $('input[name="name"]').val('');
        $('textarea[name="description"]').val('');
        $('input[name="blocks_quantity"]').val('');
        $('input[name="package_price"]').val('');
        $('input[name="discount_percentage"]').val('');
        $('input[name="start_date"]').val('');
        $('input[name="end_date"]').val('');
        $('select[name="status"]').val('active');
        
        $('#modal-title').text('<?php _e('Agregar Promoción', 'medical-appointments'); ?>');
        $('#mas-promotion-modal').fadeIn(200);
    };
    
    window.editPromotion = function(promotion) {
        $('input[name="promotion_id"]').val(promotion.id);
        $('input[name="name"]').val(promotion.name);
        $('textarea[name="description"]').val(promotion.description || '');
        $('input[name="blocks_quantity"]').val(promotion.blocks_quantity);
        $('input[name="package_price"]').val(promotion.package_price);
        $('input[name="discount_percentage"]').val(promotion.discount_percentage || '');
        $('input[name="start_date"]').val(promotion.start_date || '');
        $('input[name="end_date"]').val(promotion.end_date || '');
        $('select[name="status"]').val(promotion.status);
        
        $('#modal-title').text('<?php _e('Editar Promoción', 'medical-appointments'); ?>');
        $('#mas-promotion-modal').fadeIn(200);
    };
});
</script>

<style>
.mas-promotions-list {
    margin-top: 20px;
}

.mas-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.mas-badge-success {
    background: #d4edda;
    color: #155724;
}

.mas-badge-secondary {
    background: #e2e3e5;
    color: #383d41;
}

.mas-field-description {
    display: block;
    margin-top: 4px;
    color: #666;
    font-size: 13px;
}
</style>
