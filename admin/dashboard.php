<?php
/**
 * Dashboard principal del sistema
 */

if (!defined('ABSPATH')) {
    exit;
}

$mas_appointments = new MAS_Appointments();
$mas_rentals = new MAS_Rentals();
$mas_boxes = new MAS_Boxes();
$mas_professionals = new MAS_Professionals();

// Obtener estadísticas del mes actual
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t');

$appointment_stats = $mas_appointments->get_statistics($current_month_start, $current_month_end);
$rental_stats = $mas_rentals->get_statistics($current_month_start, $current_month_end);

// Obtener citas pendientes
$pending_appointments = $mas_appointments->get_appointments(array(
    'status' => 'pending',
    'limit' => 10
));

// Obtener próximas citas
$upcoming_appointments = $mas_appointments->get_appointments(array(
    'status' => 'scheduled',
    'date_from' => date('Y-m-d'),
    'limit' => 10
));

$total_boxes = count($mas_boxes->get_boxes('active'));
$total_professionals = count($mas_professionals->get_professionals('active'));
?>

<div class="wrap mas-dashboard">
    <h1><?php _e('Panel de Control - Sistema de Citas Médicas', 'medical-appointments'); ?></h1>
    
    <!-- Estadísticas Generales -->
    <div class="mas-stats-grid">
        <div class="mas-stat-card">
            <div class="mas-stat-icon">
                <span class="dashicons dashicons-calendar-alt"></span>
            </div>
            <div class="mas-stat-content">
                <h3><?php echo esc_html($appointment_stats['total']); ?></h3>
                <p><?php _e('Citas este mes', 'medical-appointments'); ?></p>
            </div>
        </div>
        
        <div class="mas-stat-card mas-stat-pending">
            <div class="mas-stat-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="mas-stat-content">
                <h3><?php echo esc_html($appointment_stats['pending']); ?></h3>
                <p><?php _e('Citas pendientes', 'medical-appointments'); ?></p>
            </div>
        </div>
        
        <div class="mas-stat-card mas-stat-success">
            <div class="mas-stat-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="mas-stat-content">
                <h3><?php echo esc_html($appointment_stats['scheduled']); ?></h3>
                <p><?php _e('Citas agendadas', 'medical-appointments'); ?></p>
            </div>
        </div>
        
        <div class="mas-stat-card mas-stat-info">
            <div class="mas-stat-icon">
                <span class="dashicons dashicons-building"></span>
            </div>
            <div class="mas-stat-content">
                <h3><?php echo esc_html($rental_stats['total_rentals']); ?></h3>
                <p><?php _e('Arriendos este mes', 'medical-appointments'); ?></p>
            </div>
        </div>
        
        <div class="mas-stat-card">
            <div class="mas-stat-icon">
                <span class="dashicons dashicons-admin-home"></span>
            </div>
            <div class="mas-stat-content">
                <h3><?php echo esc_html($total_boxes); ?></h3>
                <p><?php _e('Boxes activos', 'medical-appointments'); ?></p>
            </div>
        </div>
        
        <div class="mas-stat-card">
            <div class="mas-stat-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="mas-stat-content">
                <h3><?php echo esc_html($total_professionals); ?></h3>
                <p><?php _e('Profesionales', 'medical-appointments'); ?></p>
            </div>
        </div>
        
        <div class="mas-stat-card mas-stat-revenue">
            <div class="mas-stat-icon">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div class="mas-stat-content">
                <h3>$<?php echo number_format($rental_stats['total_revenue'], 0, ',', '.'); ?></h3>
                <p><?php _e('Ingresos por arriendos', 'medical-appointments'); ?></p>
            </div>
        </div>
        
        <div class="mas-stat-card">
            <div class="mas-stat-icon">
                <span class="dashicons dashicons-backup"></span>
            </div>
            <div class="mas-stat-content">
                <h3><?php echo number_format($rental_stats['total_hours'], 1); ?></h3>
                <p><?php _e('Horas arrendadas', 'medical-appointments'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Secciones de Información -->
    <div class="mas-dashboard-sections">
        <!-- Citas Pendientes -->
        <div class="mas-dashboard-section">
            <div class="mas-section-header">
                <h2><?php _e('Citas Pendientes de Asignación', 'medical-appointments'); ?></h2>
                <a href="<?php echo admin_url('admin.php?page=medical-appointments-list&status=pending'); ?>" class="button button-secondary">
                    <?php _e('Ver todas', 'medical-appointments'); ?>
                </a>
            </div>
            
            <?php if (!empty($pending_appointments)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Paciente', 'medical-appointments'); ?></th>
                            <th><?php _e('Fecha', 'medical-appointments'); ?></th>
                            <th><?php _e('Hora', 'medical-appointments'); ?></th>
                            <th><?php _e('Contacto', 'medical-appointments'); ?></th>
                            <th><?php _e('Acciones', 'medical-appointments'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_appointments as $appointment) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($appointment->patient_name); ?></strong></td>
                                <td><?php echo date('d/m/Y', strtotime($appointment->appointment_date)); ?></td>
                                <td><?php echo esc_html($appointment->appointment_time); ?></td>
                                <td>
                                    <?php echo esc_html($appointment->patient_phone); ?><br>
                                    <small><?php echo esc_html($appointment->patient_email); ?></small>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=medical-appointments-list&action=edit&id=' . $appointment->id); ?>" class="button button-small">
                                        <?php _e('Asignar', 'medical-appointments'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p class="mas-no-data"><?php _e('No hay citas pendientes de asignación.', 'medical-appointments'); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Próximas Citas -->
        <div class="mas-dashboard-section">
            <div class="mas-section-header">
                <h2><?php _e('Próximas Citas Agendadas', 'medical-appointments'); ?></h2>
                <a href="<?php echo admin_url('admin.php?page=medical-appointments-list&status=scheduled'); ?>" class="button button-secondary">
                    <?php _e('Ver todas', 'medical-appointments'); ?>
                </a>
            </div>
            
            <?php if (!empty($upcoming_appointments)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Paciente', 'medical-appointments'); ?></th>
                            <th><?php _e('Fecha', 'medical-appointments'); ?></th>
                            <th><?php _e('Hora', 'medical-appointments'); ?></th>
                            <th><?php _e('Profesional', 'medical-appointments'); ?></th>
                            <th><?php _e('Acciones', 'medical-appointments'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming_appointments as $appointment) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($appointment->patient_name); ?></strong></td>
                                <td><?php echo date('d/m/Y', strtotime($appointment->appointment_date)); ?></td>
                                <td><?php echo esc_html($appointment->appointment_time); ?></td>
                                <td><?php echo esc_html($appointment->professional_name); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=medical-appointments-list&action=edit&id=' . $appointment->id); ?>" class="button button-small">
                                        <?php _e('Editar', 'medical-appointments'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p class="mas-no-data"><?php _e('No hay citas agendadas próximamente.', 'medical-appointments'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Enlaces Rápidos -->
    <div class="mas-quick-links">
        <h2><?php _e('Accesos Rápidos', 'medical-appointments'); ?></h2>
        <div class="mas-quick-links-grid">
            <a href="<?php echo admin_url('admin.php?page=medical-appointments-list'); ?>" class="mas-quick-link">
                <span class="dashicons dashicons-calendar-alt"></span>
                <span><?php _e('Gestionar Citas', 'medical-appointments'); ?></span>
            </a>
            <a href="<?php echo admin_url('admin.php?page=medical-boxes'); ?>" class="mas-quick-link">
                <span class="dashicons dashicons-admin-home"></span>
                <span><?php _e('Gestionar Boxes', 'medical-appointments'); ?></span>
            </a>
            <a href="<?php echo admin_url('admin.php?page=medical-rentals'); ?>" class="mas-quick-link">
                <span class="dashicons dashicons-building"></span>
                <span><?php _e('Ver Arriendos', 'medical-appointments'); ?></span>
            </a>
            <a href="<?php echo admin_url('admin.php?page=medical-professionals'); ?>" class="mas-quick-link">
                <span class="dashicons dashicons-groups"></span>
                <span><?php _e('Gestionar Profesionales', 'medical-appointments'); ?></span>
            </a>
            <a href="<?php echo admin_url('admin.php?page=medical-settings'); ?>" class="mas-quick-link">
                <span class="dashicons dashicons-admin-settings"></span>
                <span><?php _e('Configuración', 'medical-appointments'); ?></span>
            </a>
        </div>
    </div>
</div>
