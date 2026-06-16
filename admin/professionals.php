<?php
/**
 * Página de gestión de profesionales
 */

if (!defined('ABSPATH')) {
    exit;
}

$mas_professionals = new MAS_Professionals();
$mas_services = new MAS_Services();
$all_services = $mas_services->get_services('active');

// Procesar acciones
if (isset($_GET['action']) && isset($_GET['id'])) {
    $professional_id = intval($_GET['id']);
    
    if ($_GET['action'] === 'delete' && check_admin_referer('mas_delete_professional_' . $professional_id)) {
        if ($mas_professionals->delete_professional($professional_id)) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Profesional eliminado exitosamente.', 'medical-appointments') . '</p></div>';
        }
    }
    
    if ($_GET['action'] === 'toggle_status' && check_admin_referer('mas_toggle_professional_' . $professional_id)) {
        $professional = $mas_professionals->get_professional($professional_id);
        $new_status = $professional->status === 'active' ? 'inactive' : 'active';
        
        if ($mas_professionals->update_professional($professional_id, array('status' => $new_status))) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Estado actualizado exitosamente.', 'medical-appointments') . '</p></div>';
        }
    }
}

// Guardar/actualizar profesional
if (isset($_POST['mas_save_professional']) && check_admin_referer('mas_professional_nonce')) {
    $professional_id = isset($_POST['professional_id']) ? intval($_POST['professional_id']) : 0;
    
    error_log('[MAS] Procesando formulario de profesional. ID: ' . $professional_id);
    
    if ($professional_id > 0) {
        // Actualizar profesional existente
        $data = array(
            'email' => sanitize_email($_POST['email']),
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'run' => sanitize_text_field($_POST['run']),
            'specialty' => sanitize_text_field($_POST['specialty']),
            'license_number' => sanitize_text_field($_POST['license_number']),
            'phone' => sanitize_text_field($_POST['phone']),
            'bio' => sanitize_textarea_field($_POST['bio']),
            'status' => sanitize_text_field($_POST['status'])
        );
        
        if (!empty($_POST['username'])) {
            $data['username'] = sanitize_user($_POST['username']);
        }
        
        if (!empty($_POST['password'])) {
            $data['password'] = $_POST['password'];
        }
        
        if (isset($_POST['image_url'])) {
            $data['image_url'] = !empty($_POST['image_url']) ? esc_url_raw($_POST['image_url']) : '';
        }
        
        if ($mas_professionals->update_professional($professional_id, $data)) {
            if (isset($_POST['services']) && is_array($_POST['services'])) {
                global $wpdb;
                $rel_table = $wpdb->prefix . 'mas_professional_services';
                
                // Remove old relationships
                $wpdb->delete($rel_table, array('professional_id' => $professional_id));
                
                // Add new relationships
                foreach ($_POST['services'] as $service_id) {
                    $wpdb->insert($rel_table, array(
                        'professional_id' => $professional_id,
                        'service_id' => intval($service_id)
                    ));
                }
            }
            
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Profesional actualizado exitosamente.', 'medical-appointments') . '</p></div>';
            echo '<script>setTimeout(function(){ window.location.href = "' . admin_url('admin.php?page=medical-professionals') . '"; }, 1500);</script>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Error al actualizar el profesional.', 'medical-appointments') . '</p></div>';
        }
    } else {
        // Crear nuevo profesional
        $data = array(
            'username' => sanitize_user($_POST['username']),
            'email' => sanitize_email($_POST['email']),
            'password' => $_POST['password'],
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'run' => sanitize_text_field($_POST['run']),
            'specialty' => sanitize_text_field($_POST['specialty']),
            'license_number' => sanitize_text_field($_POST['license_number']),
            'phone' => sanitize_text_field($_POST['phone']),
            'bio' => sanitize_textarea_field($_POST['bio'])
        );
        
        if (!empty($_POST['image_url'])) {
            $data['image_url'] = esc_url_raw($_POST['image_url']);
        }
        
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Todos los campos obligatorios deben ser completados.', 'medical-appointments') . '</p></div>';
        } elseif (username_exists($data['username'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('El nombre de usuario ya está en uso.', 'medical-appointments') . '</p></div>';
        } elseif (email_exists($data['email'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('El email ya está en uso.', 'medical-appointments') . '</p></div>';
        } else {
            $result = $mas_professionals->create_professional($data);
            
            if ($result) {
                if (isset($_POST['services']) && is_array($_POST['services'])) {
                    global $wpdb;
                    $rel_table = $wpdb->prefix . 'mas_professional_services';
                    
                    foreach ($_POST['services'] as $service_id) {
                        $wpdb->insert($rel_table, array(
                            'professional_id' => $result,
                            'service_id' => intval($service_id)
                        ));
                    }
                }
                
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Profesional creado exitosamente.', 'medical-appointments') . '</p></div>';
                echo '<script>setTimeout(function(){ window.location.href = "' . admin_url('admin.php?page=medical-professionals') . '"; }, 1500);</script>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Error al crear el profesional. Verifique los logs del servidor para más detalles.', 'medical-appointments') . '</p></div>';
            }
        }
    }
}

// Obtener profesional para editar
$editing_professional = null;
$professional_services = array();
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editing_professional = $mas_professionals->get_professional(intval($_GET['id']));
    
    if ($editing_professional) {
        global $wpdb;
        $rel_table = $wpdb->prefix . 'mas_professional_services';
        $professional_services = $wpdb->get_col($wpdb->prepare(
            "SELECT service_id FROM $rel_table WHERE professional_id = %d",
            $editing_professional->id
        ));
    }
}

$all_services = $mas_services->get_services('active');

// Obtener todos los profesionales con paginación
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 25;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Get total count
global $wpdb;
$table = $wpdb->prefix . 'mas_professionals';
$total_professionals = $wpdb->get_var("SELECT COUNT(*) FROM $table");
$total_pages = ceil($total_professionals / $per_page);

// Get paginated professionals
$professionals = $mas_professionals->get_professionals('', $per_page, $offset);
?>

<div class="wrap mas-professionals-page">
    <h1 class="wp-heading-inline"><?php _e('Gestión de Profesionales', 'medical-appointments'); ?></h1>
    <!-- Updated button to use new resetAndOpenModal function -->
    <a href="#" class="page-title-action" onclick="event.preventDefault(); resetAndOpenModal();">
        <?php _e('Agregar Nuevo', 'medical-appointments'); ?>
    </a>
    <hr class="wp-header-end">
    
    <!-- Adding pagination controls above table -->
    <?php if (!empty($professionals)) : ?>
        <div class="tablenav top">
            <div class="alignleft actions">
                <label for="per-page-selector"><?php _e('Mostrar', 'medical-appointments'); ?>:</label>
                <select id="per-page-selector" onchange="window.location.href='<?php echo admin_url('admin.php?page=medical-professionals&per_page='); ?>' + this.value;">
                    <option value="25" <?php selected($per_page, 25); ?>>25</option>
                    <option value="50" <?php selected($per_page, 50); ?>>50</option>
                    <option value="100" <?php selected($per_page, 100); ?>>100</option>
                </select>
            </div>
            
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php 
                    $start = $offset + 1;
                    $end = min($offset + $per_page, $total_professionals);
                    printf(__('Mostrando %d-%d de %d profesionales', 'medical-appointments'), $start, $end, $total_professionals);
                    ?>
                </span>
                <?php if ($total_pages > 1) : ?>
                    <span class="pagination-links">
                        <?php if ($current_page > 1) : ?>
                            <a class="first-page button" href="<?php echo admin_url('admin.php?page=medical-professionals&paged=1&per_page=' . $per_page); ?>">
                                <span aria-hidden="true">«</span>
                            </a>
                            <a class="prev-page button" href="<?php echo admin_url('admin.php?page=medical-professionals&paged=' . ($current_page - 1) . '&per_page=' . $per_page); ?>">
                                <span aria-hidden="true">‹</span>
                            </a>
                        <?php else : ?>
                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
                        <?php endif; ?>
                        
                        <span class="paging-input">
                            <label for="current-page-selector" class="screen-reader-text"><?php _e('Página actual', 'medical-appointments'); ?></label>
                            <input class="current-page" id="current-page-selector" type="text" name="paged" value="<?php echo $current_page; ?>" size="<?php echo strlen($total_pages); ?>" aria-describedby="table-paging">
                            <span class="tablenav-paging-text"> de <span class="total-pages"><?php echo $total_pages; ?></span></span>
                        </span>
                        
                        <?php if ($current_page < $total_pages) : ?>
                            <a class="next-page button" href="<?php echo admin_url('admin.php?page=medical-professionals&paged=' . ($current_page + 1) . '&per_page=' . $per_page); ?>">
                                <span aria-hidden="true">›</span>
                            </a>
                            <a class="last-page button" href="<?php echo admin_url('admin.php?page=medical-professionals&paged=' . $total_pages . '&per_page=' . $per_page); ?>">
                                <span aria-hidden="true">»</span>
                            </a>
                        <?php else : ?>
                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        
        <!--Tabla de profesionales -->
        <div style="overflow-x: auto; margin-bottom: 20px;">
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <!-- Add image column -->
                    <th style="width: 80px; min-width: 80px;"><?php _e('Imagen', 'medical-appointments'); ?></th>
                    <th style="width: 60px; min-width: 60px;"><?php _e('ID', 'medical-appointments'); ?></th>
                    <th style="width: 180px; min-width: 180px;"><?php _e('Nombre', 'medical-appointments'); ?></th>
                    <th style="width: 200px; min-width: 200px;"><?php _e('Email', 'medical-appointments'); ?></th>
                    <th style="width: 120px; min-width: 120px;"><?php _e('Teléfono', 'medical-appointments'); ?></th>
                    <th style="width: 150px; min-width: 150px;"><?php _e('Especialidad', 'medical-appointments'); ?></th>
                    <th style="width: 120px; min-width: 120px;"><?php _e('N° Licencia', 'medical-appointments'); ?></th>
                    <th style="width: 120px; min-width: 120px;"><?php _e('RUN', 'medical-appointments'); ?></th>
                    <th style="width: 100px; min-width: 100px;"><?php _e('Estado', 'medical-appointments'); ?></th>
                    <th style="width: 250px; min-width: 250px;"><?php _e('Acciones', 'medical-appointments'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($professionals as $professional) : ?>
                    <tr>
                        <!-- Display professional image -->
                        <td>
                            <?php if (!empty($professional->image_url)) : ?>
                                <img src="<?php echo esc_url($professional->image_url); ?>" alt="<?php echo esc_attr($professional->display_name); ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 50%;">
                            <?php else : ?>
                                <div style="width: 60px; height: 60px; background: #f0f0f0; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <span class="dashicons dashicons-admin-users" style="color: #ccc; font-size: 24px;"></span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><strong>#<?php echo esc_html($professional->id); ?></strong></td>
                        <td>
                            <strong><?php echo esc_html($professional->display_name); ?></strong><br>
                            <small><?php echo esc_html($professional->user_login); ?></small>
                        </td>
                        <td><?php echo esc_html($professional->user_email); ?></td>
                        <td><?php echo esc_html($professional->phone); ?></td>
                        <td><?php echo esc_html($professional->specialty); ?></td>
                        <td><?php echo esc_html($professional->license_number); ?></td>
                        <td><?php echo esc_html($professional->run); ?></td>
                        <td>
                            <span class="mas-status-badge mas-status-<?php echo esc_attr($professional->status); ?>">
                                <?php echo $professional->status === 'active' ? __('Activo', 'medical-appointments') : __('Inactivo', 'medical-appointments'); ?>
                            </span>
                        </td>
                        <td class="mas-table-actions">
                            <a href="<?php echo admin_url('admin.php?page=medical-professionals&action=edit&id=' . $professional->id); ?>" class="button button-small">
                                <?php _e('Editar', 'medical-appointments'); ?>
                            </a>
                            <button type="button" class="button button-small mas-schedule-btn" data-professional-id="<?php echo $professional->id; ?>" data-professional-name="<?php echo esc_attr($professional->display_name); ?>">
                                <?php _e('Horarios', 'medical-appointments'); ?>
                            </button>
                            <button type="button" class="button button-small mas-services-btn" data-professional-id="<?php echo $professional->id; ?>" data-professional-name="<?php echo esc_attr($professional->display_name); ?>">
                                <?php _e('Servicios', 'medical-appointments'); ?>
                            </button>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=medical-professionals&action=toggle_status&id=' . $professional->id), 'mas_toggle_professional_' . $professional->id); ?>" class="button button-small">
                                <?php echo $professional->status === 'active' ? __('Desactivar', 'medical-appointments') : __('Activar', 'medical-appointments'); ?>
                            </a>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=medical-professionals&action=delete&id=' . $professional->id), 'mas_delete_professional_' . $professional->id); ?>" class="button button-small" onclick="return confirm('<?php _e('¿Está seguro de eliminar este profesional? Esta acción no se puede deshacer.', 'medical-appointments'); ?>')">
                                <?php _e('Eliminar', 'medical-appointments'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <!-- Closing wrapper div for overflow -->
        
        <!-- Adding pagination controls below table -->
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php printf(__('Mostrando %d-%d de %d profesionales', 'medical-appointments'), $start, $end, $total_professionals); ?>
                </span>
                <?php if ($total_pages > 1) : ?>
                    <span class="pagination-links">
                        <?php if ($current_page > 1) : ?>
                            <a class="first-page button" href="<?php echo admin_url('admin.php?page=medical-professionals&paged=1&per_page=' . $per_page); ?>">
                                <span aria-hidden="true">«</span>
                            </a>
                            <a class="prev-page button" href="<?php echo admin_url('admin.php?page=medical-professionals&paged=' . ($current_page - 1) . '&per_page=' . $per_page); ?>">
                                <span aria-hidden="true">‹</span>
                            </a>
                        <?php else : ?>
                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
                        <?php endif; ?>
                        
                        <span class="paging-input">
                            <label for="current-page-selector-bottom" class="screen-reader-text"><?php _e('Página actual', 'medical-appointments'); ?></label>
                            <input class="current-page" id="current-page-selector-bottom" type="text" name="paged" value="<?php echo $current_page; ?>" size="<?php echo strlen($total_pages); ?>">
                            <span class="tablenav-paging-text"> de <span class="total-pages"><?php echo $total_pages; ?></span></span>
                        </span>
                        
                        <?php if ($current_page < $total_pages) : ?>
                            <a class="next-page button" href="<?php echo admin_url('admin.php?page=medical-professionals&paged=' . ($current_page + 1) . '&per_page=' . $per_page); ?>">
                                <span aria-hidden="true">›</span>
                            </a>
                            <a class="last-page button" href="<?php echo admin_url('admin.php?page=medical-professionals&paged=' . $total_pages . '&per_page=' . $per_page); ?>">
                                <span aria-hidden="true">»</span>
                            </a>
                        <?php else : ?>
                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    <?php else : ?>
        <p class="mas-no-data"><?php _e('No hay profesionales registrados.', 'medical-appointments'); ?></p>
    <?php endif; ?>
</div>

<!-- Modal de edición/creación -->
<div id="mas-professional-modal" class="mas-modal-overlay" style="display: none;">
    <div class="mas-modal mas-modal-enhanced" style="max-width: 700px;">
        <!-- Updated modal header with modern design and icon -->
        <div class="mas-modal-header mas-modal-header-gradient">
            <div class="mas-modal-title-wrapper">
                <svg class="mas-modal-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2m8-10a4 4 0 100-8 4 4 0 000 8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <!-- Dynamic modal title -->
                <h2 id="modal-title"><?php echo $editing_professional ? __('Editar Profesional', 'medical-appointments') : __('Nuevo Profesional', 'medical-appointments'); ?></h2>
            </div>
            <button type="button" class="mas-modal-close" data-modal-close>&times;</button>
        </div>
        
        <form method="post" action="" id="professional-form">
            <?php wp_nonce_field('mas_professional_nonce'); ?>
            <input type="hidden" name="professional_id" id="professional_id" value="<?php echo $editing_professional ? esc_attr($editing_professional->id) : ''; ?>">
            <input type="hidden" name="image_url" id="professional_image_url" value="<?php echo $editing_professional && !empty($editing_professional->image_url) ? esc_attr($editing_professional->image_url) : ''; ?>">
            
            <!-- Updated modal body with enhanced styling -->
            <div class="mas-modal-body mas-modal-body-enhanced">
                <!-- Show credentials section for both create and edit -->
                <div class="mas-form-section mas-form-section-enhanced" id="credentials-section">
                    <div class="mas-section-icon-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h3><?php _e('Credenciales de Acceso', 'medical-appointments'); ?></h3>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Nombre de Usuario', 'medical-appointments'); ?> <span id="username-required" class="mas-required">*</span></label>
                            <input type="text" name="username" id="username" value="<?php echo $editing_professional ? esc_attr($editing_professional->user_login) : ''; ?>" <?php echo !$editing_professional ? 'required' : ''; ?>>
                            <span class="description" id="username-description">
                                <?php echo $editing_professional ? __('Dejar en blanco para mantener el usuario actual', 'medical-appointments') : __('Solo letras, números y guiones bajos', 'medical-appointments'); ?>
                            </span>
                        </div>
                        
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Contraseña', 'medical-appointments'); ?> <span id="password-required" class="mas-required">*</span></label>
                            <input type="password" name="password" id="password" <?php echo !$editing_professional ? 'required' : ''; ?>>
                            <span class="description" id="password-description">
                                <?php echo $editing_professional ? __('Dejar en blanco para mantener la contraseña actual', 'medical-appointments') : __('Mínimo 8 caracteres', 'medical-appointments'); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="mas-form-section mas-form-section-enhanced">
                    <div class="mas-section-icon-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h3><?php _e('Información Personal', 'medical-appointments'); ?></h3>
                    </div>
                    
                    <!-- Add professional image upload -->
                    <div class="mas-form-field mas-form-field-enhanced">
                        <label><?php _e('Imagen del Profesional', 'medical-appointments'); ?></label>
                        <!-- Added proper container styling to prevent full-width image -->
                        <div class="mas-image-upload-container" style="display: flex; gap: 20px; align-items: center;">
                            <div id="professional_image_preview" class="mas-image-preview" style="width: 120px; height: 120px; border-radius: 50%; border: 2px dashed #ddd; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f9f9f9; overflow: hidden; flex-shrink: 0;">
                                <?php if ($editing_professional && !empty($editing_professional->image_url)) : ?>
                                    <img src="<?php echo esc_url($editing_professional->image_url); ?>" alt="Preview" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                <?php else : ?>
                                    <span class="dashicons dashicons-admin-users" style="font-size: 48px; color: #ccc;"></span>
                                    <p style="margin: 8px 0 0 0; color: #999; font-size: 12px;"><?php _e('Sin imagen', 'medical-appointments'); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="mas-image-upload-buttons" style="display: flex; flex-direction: column; gap: 8px;">
                                <button type="button" class="button" id="upload_professional_image_button">
                                    <?php _e('Seleccionar Imagen', 'medical-appointments'); ?>
                                </button>
                                <button type="button" class="button" id="remove_professional_image_button" style="<?php echo (!$editing_professional || empty($editing_professional->image_url)) ? 'display:none;' : ''; ?>">
                                    <?php _e('Eliminar Imagen', 'medical-appointments'); ?>
                                </button>
                            </div>
                        </div>
                        <span class="description"><?php _e('Imagen recomendada: 300x300px', 'medical-appointments'); ?></span>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Nombre', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <input type="text" name="first_name" id="first_name" value="<?php echo $editing_professional ? esc_attr($editing_professional->first_name) : ''; ?>" required>
                        </div>
                        
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Apellido', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <input type="text" name="last_name" id="last_name" value="<?php echo $editing_professional ? esc_attr($editing_professional->last_name) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Email', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                            <input type="email" name="email" id="email" value="<?php echo $editing_professional ? esc_attr($editing_professional->user_email) : ''; ?>" required placeholder="correo@ejemplo.com">
                        </div>
                        
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Teléfono', 'medical-appointments'); ?></label>
                            <input type="tel" name="phone" id="phone" value="<?php echo $editing_professional ? esc_attr($professional->phone) : ''; ?>" placeholder="+56 9 1234 5678">
                        </div>
                    </div>
                    
                    <!-- Added RUN field after personal information -->
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('RUN', 'medical-appointments'); ?></label>
                            <input type="text" name="run" id="run" value="<?php echo $editing_professional ? esc_attr($professional->run) : ''; ?>" placeholder="12.345.678-9">
                            <span class="description"><?php _e('RUT del profesional', 'medical-appointments'); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="mas-form-section mas-form-section-enhanced">
                    <div class="mas-section-icon-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h3><?php _e('Información Profesional', 'medical-appointments'); ?></h3>
                    </div>
                    
                    <!-- Multiple services selection instead of specialties -->
                    <div class="mas-form-field mas-form-field-enhanced">
                        <label><?php _e('Servicios', 'medical-appointments'); ?></label>
                        <div id="services-list" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                            <?php if (!empty($all_services)) : ?>
                                <?php foreach ($all_services as $service) : ?>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="checkbox" name="services[]" value="<?php echo esc_attr($service->id); ?>" 
                                            <?php checked(in_array($service->id, $professional_services)); ?>>
                                        <?php echo esc_html($service->service_name); ?> - $<?php echo number_format($service->price, 0, ',', '.'); ?>
                                        (<?php echo esc_html($service->duration); ?> min)
                                    </label>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <p><?php _e('No hay servicios disponibles. Por favor, créelos primero en la sección de Servicios.', 'medical-appointments'); ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="description"><?php _e('Seleccione uno o más servicios que ofrece este profesional', 'medical-appointments'); ?></span>
                    </div>
                    
                    <div class="mas-form-row">
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Especialidad Principal', 'medical-appointments'); ?></label>
                            <input type="text" name="specialty" id="specialty" value="<?php echo $editing_professional ? esc_attr($professional->specialty) : ''; ?>" placeholder="Ej: Psicología Clínica">
                            <span class="description"><?php _e('Para referencia', 'medical-appointments'); ?></span>
                        </div>
                        
                        <div class="mas-form-field mas-form-field-enhanced">
                            <label><?php _e('Número de Licencia', 'medical-appointments'); ?></label>
                            <input type="text" name="license_number" id="license_number" value="<?php echo $editing_professional ? esc_attr($professional->license_number) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="mas-form-field mas-form-field-enhanced">
                        <label><?php _e('Biografía', 'medical-appointments'); ?></label>
                        <textarea name="bio" id="bio" rows="4" placeholder="Breve descripción profesional"><?php echo $editing_professional ? esc_textarea($professional->bio) : ''; ?></textarea>
                    </div>
                    
                    <!-- Status field now has ID for easy manipulation -->
                    <div class="mas-form-field mas-form-field-enhanced" id="status-field" style="<?php echo !$editing_professional ? 'display:none;' : ''; ?>">
                        <label><?php _e('Estado', 'medical-appointments'); ?> <span class="mas-required">*</span></label>
                        <select name="status" id="status">
                            <option value="active" <?php echo ($editing_professional && $editing_professional->status == 'active') ? 'selected' : ''; ?>><?php _e('Activo', 'medical-appointments'); ?></option>
                            <option value="inactive" <?php echo ($editing_professional && $editing_professional->status == 'inactive') ? 'selected' : ''; ?>><?php _e('Inactivo', 'medical-appointments'); ?></option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Updated modal footer with enhanced styling and icon button -->
            <div class="mas-modal-footer mas-modal-footer-enhanced">
                <button type="button" class="button mas-button-cancel" data-modal-close><?php _e('Cancelar', 'medical-appointments'); ?></button>
                <button type="submit" name="mas_save_professional" class="button button-primary mas-button-save">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <?php _e('Guardar Profesional', 'medical-appointments'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add professional image upload JavaScript -->
<script>
jQuery(document).ready(function($) {
    if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
        console.error('[MAS] WordPress media library not loaded');
        return;
    }
    
    var mediaUploader;
    
    $('#upload_professional_image_button').on('click', function(e) {
        e.preventDefault();
        
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        mediaUploader = wp.media({
            title: '<?php _e('Seleccionar Imagen del Profesional', 'medical-appointments'); ?>',
            button: {
                text: '<?php _e('Usar esta imagen', 'medical-appointments'); ?>'
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });
        
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            
            $('#professional_image_url').val(attachment.url);
            $('#professional_image_preview').html('<img src="' + attachment.url + '" alt="Preview" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">');
            $('#remove_professional_image_button').show();
        });
        
        mediaUploader.open();
    });
    
    $('#remove_professional_image_button').on('click', function(e) {
        e.preventDefault();
        $('#professional_image_url').val('');
        $('#professional_image_preview').html('<span class="dashicons dashicons-admin-users" style="font-size: 48px; color: #ccc;"></span><p style="margin: 8px 0 0 0; color: #999; font-size: 12px;"><?php _e('Sin imagen', 'medical-appointments'); ?></p>');
        $(this).hide();
    });
    
    $('[data-modal-close]').on('click', function() {
        $('#mas-professional-modal').fadeOut(200);
    });
});

function resetAndOpenModal() {
    var $ = jQuery;
    
    // Reset form
    $('#professional-form')[0].reset();
    $('#professional_id').val('');
    
    // Clear text inputs
    $('#first_name').val('');
    $('#last_name').val('');
    $('#email').val('');
    $('#phone').val('');
    $('#run').val('');
    $('#username').val('');
    $('#password').val('');
    $('#specialty').val('');
    $('#bio').val('');
    
    // Reset image
    $('#professional_image_url').val('');
    $('#professional_image_preview').html('<span class="dashicons dashicons-admin-users" style="font-size: 48px; color: #ccc;"></span><p style="margin: 8px 0 0 0; color: #999; font-size: 12px;"><?php _e('Sin imagen', 'medical-appointments'); ?></p>');
    $('#remove_professional_image_button').hide();
    
    // Uncheck all services
    $('input[name="services[]"]').prop('checked', false);
    
    // Update modal title
    $('#modal-title').text('<?php _e('Nuevo Profesional', 'medical-appointments'); ?>');
    
    // Set credentials fields as required for new professional
    $('#username').prop('required', true);
    $('#password').prop('required', true);
    $('#username-required').show();
    $('#password-required').show();
    $('#username-description').text('<?php _e('Solo letras, números y guiones bajos', 'medical-appointments'); ?>');
    $('#password-description').text('<?php _e('Mínimo 8 caracteres', 'medical-appointments'); ?>');
    
    // Hide status field for new professional
    $('#status-field').hide();
    
    // Open modal
    $('#mas-professional-modal').fadeIn(200);
}
</script>

<?php if ($editing_professional) : ?>
<script>
jQuery(document).ready(function($) {
    $('#mas-professional-modal').fadeIn(200);
    
    $('#modal-title').text('<?php _e('Editar Profesional', 'medical-appointments'); ?>');
    $('#username').prop('required', false);
    $('#password').prop('required', false);
    $('#username-required').hide();
    $('#password-required').hide();
    $('#username-description').text('<?php _e('Dejar en blanco para mantener el usuario actual', 'medical-appointments'); ?>');
    $('#password-description').text('<?php _e('Dejar en blanco para mantener la contraseña actual', 'medical-appointments'); ?>');
    $('#status-field').show();
});
</script>
<?php endif; ?>

<!-- Modal de Horarios del Profesional -->
<div id="mas-schedule-modal" class="mas-modal-overlay" style="display: none;">
    <div class="mas-modal mas-modal-enhanced" style="max-width: 700px;">
        <div class="mas-modal-header mas-modal-header-gradient">
            <div class="mas-modal-title-wrapper">
                <svg class="mas-modal-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <h2 id="schedule-modal-title"><?php _e('Horarios de Atención', 'medical-appointments'); ?></h2>
            </div>
            <button type="button" class="mas-modal-close" onclick="closeScheduleModal()">&times;</button>
        </div>
        
        <form id="mas-schedule-form" method="post">
            <?php wp_nonce_field('mas_save_schedule_action', 'mas_schedule_nonce'); ?>
            <input type="hidden" name="action" value="mas_save_professional_schedule">
            <input type="hidden" name="professional_id" id="schedule_professional_id" value="">
            
            <div class="mas-modal-body mas-modal-body-enhanced">
                <div class="mas-form-section mas-form-section-enhanced">
                    <div class="mas-section-icon-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h3><?php _e('Configurar Días y Horarios de Atención', 'medical-appointments'); ?></h3>
                    </div>
                    <p class="mas-help-text"><?php _e('Selecciona los días que el profesional atiende y configura su horario de inicio y fin.', 'medical-appointments'); ?></p>
                    
                    <div class="mas-schedule-grid">
                        <?php
                        $days = array(
                            1 => 'Lunes',
                            2 => 'Martes',
                            3 => 'Miércoles',
                            4 => 'Jueves',
                            5 => 'Viernes',
                            6 => 'Sábado',
                            7 => 'Domingo'
                        );
                        foreach ($days as $day_num => $day_name):
                        ?>
                        <div class="mas-schedule-day" data-day="<?php echo $day_num; ?>">
                            <label class="mas-day-checkbox">
                                <input type="checkbox" name="schedule[<?php echo $day_num; ?>][is_enabled]" value="1" class="day-enabled-checkbox" data-day="<?php echo $day_num; ?>">
                                <span class="mas-day-name"><?php echo $day_name; ?></span>
                            </label>
                            <div class="mas-day-times">
                                <input type="time" name="schedule[<?php echo $day_num; ?>][start_time]" value="09:00" class="day-start-time" data-day="<?php echo $day_num; ?>" disabled>
                                <span>a</span>
                                <input type="time" name="schedule[<?php echo $day_num; ?>][end_time]" value="18:00" class="day-end-time" data-day="<?php echo $day_num; ?>" disabled>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mas-quick-actions" style="margin-top: 16px; display: flex; gap: 10px;">
                        <button type="button" class="button" id="select-weekdays"><?php _e('Seleccionar Lun-Vie', 'medical-appointments'); ?></button>
                        <button type="button" class="button" id="clear-all-days"><?php _e('Limpiar Todo', 'medical-appointments'); ?></button>
                    </div>
                </div>
            </div>
            
            <div class="mas-modal-footer mas-modal-footer-enhanced">
                <button type="button" class="button mas-btn-cancel" onclick="closeScheduleModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 18L18 6M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <?php _e('Cancelar', 'medical-appointments'); ?>
                </button>
                <button type="submit" class="button button-primary mas-btn-submit">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <?php _e('Guardar Horarios', 'medical-appointments'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Servicios del Profesional -->
<div id="mas-services-modal" class="mas-modal-overlay" style="display: none;">
    <div class="mas-modal mas-modal-enhanced" style="max-width: 600px;">
        <div class="mas-modal-header mas-modal-header-gradient">
            <div class="mas-modal-title-wrapper">
                <svg class="mas-modal-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <h2 id="services-modal-title"><?php _e('Servicios del Profesional', 'medical-appointments'); ?></h2>
            </div>
            <button type="button" class="mas-modal-close" onclick="closeServicesModal()">&times;</button>
        </div>
        
        <form id="mas-services-form" method="post">
            <?php wp_nonce_field('mas_save_services_action', 'mas_services_nonce'); ?>
            <input type="hidden" name="action" value="mas_save_professional_services">
            <input type="hidden" name="professional_id" id="services_professional_id" value="">
            
            <div class="mas-modal-body mas-modal-body-enhanced">
                <div class="mas-form-section mas-form-section-enhanced">
                    <div class="mas-section-icon-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h3><?php _e('Seleccionar Servicios que Ofrece', 'medical-appointments'); ?></h3>
                    </div>
                    <p class="mas-help-text"><?php _e('Marca los servicios que este profesional puede realizar.', 'medical-appointments'); ?></p>
                    
                    <div class="mas-services-grid">
                        <?php if (!empty($all_services)): ?>
                            <?php foreach ($all_services as $service): ?>
                            <label class="mas-service-checkbox">
                                <input type="checkbox" name="services[]" value="<?php echo $service->id; ?>" class="service-checkbox">
                                <span class="mas-service-info">
                                    <span class="mas-service-name"><?php echo esc_html($service->service_name); ?></span>
                                    <span class="mas-service-price">$<?php echo number_format($service->price, 0, ',', '.'); ?></span>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="mas-no-services"><?php _e('No hay servicios disponibles. Primero crea servicios en la sección de Servicios.', 'medical-appointments'); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($all_services)): ?>
                    <div class="mas-quick-actions" style="margin-top: 16px; display: flex; gap: 10px;">
                        <button type="button" class="button" id="select-all-services"><?php _e('Seleccionar Todos', 'medical-appointments'); ?></button>
                        <button type="button" class="button" id="clear-all-services"><?php _e('Limpiar Todo', 'medical-appointments'); ?></button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mas-modal-footer mas-modal-footer-enhanced">
                <button type="button" class="button mas-btn-cancel" onclick="closeServicesModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 18L18 6M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <?php _e('Cancelar', 'medical-appointments'); ?>
                </button>
                <button type="submit" class="button button-primary mas-btn-submit">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <?php _e('Guardar Servicios', 'medical-appointments'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Services Modal Styles */
.mas-services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
    margin-top: 12px;
}

.mas-service-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.mas-service-checkbox:hover {
    border-color: #667eea;
    background: #f8f9ff;
}

.mas-service-checkbox:has(input:checked) {
    border-color: #667eea;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
}

.mas-service-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    flex-shrink: 0;
}

.mas-service-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.mas-service-name {
    font-weight: 500;
    color: #374151;
    font-size: 14px;
}

.mas-service-checkbox:has(input:checked) .mas-service-name {
    color: #667eea;
    font-weight: 600;
}

.mas-service-price {
    font-size: 12px;
    color: #6b7280;
}

.mas-no-services {
    grid-column: 1 / -1;
    text-align: center;
    color: #6b7280;
    padding: 20px;
}

/* Schedule Modal Styles */
.mas-schedule-grid {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.mas-schedule-day {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.mas-schedule-day:has(input[type="checkbox"]:checked) {
    border-color: #667eea;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
}

.mas-day-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    min-width: 140px;
}

.mas-day-checkbox input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.mas-day-name {
    font-weight: 500;
    color: #374151;
}

.mas-day-checkbox input[type="checkbox"]:checked + .mas-day-name {
    color: #667eea;
    font-weight: 600;
}

.mas-day-times {
    display: flex;
    align-items: center;
    gap: 8px;
}

.mas-day-times input[type="time"] {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.2s ease;
}

.mas-day-times input[type="time"]:disabled {
    background: #f3f4f6;
    color: #9ca3af;
    cursor: not-allowed;
}

.mas-day-times input[type="time"]:not(:disabled):focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
    outline: none;
}

.mas-day-times span {
    color: #6b7280;
    font-size: 13px;
}

.mas-quick-actions button {
    font-size: 13px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Abrir modal de horarios
    $('.mas-schedule-btn').on('click', function() {
        var professionalId = $(this).data('professional-id');
        var professionalName = $(this).data('professional-name');
        
        $('#schedule_professional_id').val(professionalId);
        $('#schedule-modal-title').text('Horarios de ' + professionalName);
        
        // Limpiar formulario
        $('.day-enabled-checkbox').prop('checked', false);
        $('.day-start-time, .day-end-time').prop('disabled', true).val('');
        $('.day-start-time').val('09:00');
        $('.day-end-time').val('18:00');
        
        // Cargar horarios existentes via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mas_get_professional_schedule',
                professional_id: professionalId,
                nonce: '<?php echo wp_create_nonce('mas_get_schedule_nonce'); ?>'
            },
            success: function(response) {
                if (response.success && response.data) {
                    $.each(response.data, function(day, schedule) {
                        var checkbox = $('.day-enabled-checkbox[data-day="' + day + '"]');
                        var startTime = $('.day-start-time[data-day="' + day + '"]');
                        var endTime = $('.day-end-time[data-day="' + day + '"]');
                        
                        if (schedule.is_enabled == 1) {
                            checkbox.prop('checked', true);
                            startTime.prop('disabled', false).val(schedule.start_time.substring(0, 5));
                            endTime.prop('disabled', false).val(schedule.end_time.substring(0, 5));
                        }
                    });
                }
                $('#mas-schedule-modal').fadeIn(200);
            },
            error: function() {
                $('#mas-schedule-modal').fadeIn(200);
            }
        });
    });
    
    // Toggle time inputs cuando se marca/desmarca un día
    $('.day-enabled-checkbox').on('change', function() {
        var day = $(this).data('day');
        var isChecked = $(this).is(':checked');
        
        $('.day-start-time[data-day="' + day + '"], .day-end-time[data-day="' + day + '"]')
            .prop('disabled', !isChecked);
    });
    
    // Seleccionar Lunes a Viernes
    $('#select-weekdays').on('click', function() {
        for (var day = 1; day <= 5; day++) {
            $('.day-enabled-checkbox[data-day="' + day + '"]').prop('checked', true);
            $('.day-start-time[data-day="' + day + '"], .day-end-time[data-day="' + day + '"]')
                .prop('disabled', false);
        }
    });
    
    // Limpiar todo
    $('#clear-all-days').on('click', function() {
        $('.day-enabled-checkbox').prop('checked', false);
        $('.day-start-time, .day-end-time').prop('disabled', true);
    });
    
    // Guardar horarios via AJAX
    $('#mas-schedule-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('Horarios guardados correctamente');
                    closeScheduleModal();
                } else {
                    alert('Error al guardar: ' + (response.data || 'Error desconocido'));
                }
            },
            error: function() {
                alert('Error de conexión');
            }
        });
    });
});

function closeScheduleModal() {
    jQuery('#mas-schedule-modal').fadeOut(200);
}

function closeServicesModal() {
    jQuery('#mas-services-modal').fadeOut(200);
}

// Services Modal JavaScript
jQuery(document).ready(function($) {
    // Abrir modal de servicios
    $('.mas-services-btn').on('click', function() {
        var professionalId = $(this).data('professional-id');
        var professionalName = $(this).data('professional-name');
        
        $('#services_professional_id').val(professionalId);
        $('#services-modal-title').text('Servicios de ' + professionalName);
        
        // Limpiar checkboxes
        $('.service-checkbox').prop('checked', false);
        
        // Cargar servicios asignados via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mas_get_professional_services',
                professional_id: professionalId,
                nonce: '<?php echo wp_create_nonce('mas_get_services_nonce'); ?>'
            },
            success: function(response) {
                if (response.success && response.data) {
                    $.each(response.data, function(index, serviceId) {
                        $('.service-checkbox[value="' + serviceId + '"]').prop('checked', true);
                    });
                }
                $('#mas-services-modal').fadeIn(200);
            },
            error: function() {
                $('#mas-services-modal').fadeIn(200);
            }
        });
    });
    
    // Seleccionar todos los servicios
    $('#select-all-services').on('click', function() {
        $('.service-checkbox').prop('checked', true);
    });
    
    // Limpiar todos los servicios
    $('#clear-all-services').on('click', function() {
        $('.service-checkbox').prop('checked', false);
    });
    
    // Guardar servicios via AJAX
    $('#mas-services-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('Servicios guardados correctamente');
                    closeServicesModal();
                } else {
                    alert('Error al guardar: ' + (response.data || 'Error desconocido'));
                }
            },
            error: function() {
                alert('Error de conexión');
            }
        });
    });
});
</script>
