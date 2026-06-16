<?php
if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!current_user_can('manage_options')) {
    wp_die(__('No tienes permiso para acceder a esta página.'));
}

global $wpdb;
$table_name = $wpdb->prefix . 'mas_webhook_logs';

// Handle log deletion
if (isset($_POST['delete_logs']) && check_admin_referer('mas_delete_webhook_logs')) {
    $days = intval($_POST['days_old']);
    if ($days > 0) {
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE received_at < %s",
            $date
        ));
        echo '<div class="notice notice-success"><p>Se eliminaron ' . $deleted . ' registros.</p></div>';
    }
}

// Get filters
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$processed_filter = isset($_GET['processed']) ? intval($_GET['processed']) : -1;
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

// Build query
$where = array('1=1');
$where_values = array();

if (!empty($status_filter)) {
    $where[] = 'status = %s';
    $where_values[] = $status_filter;
}

if ($processed_filter >= 0) {
    $where[] = 'processed = %d';
    $where_values[] = $processed_filter;
}

if (!empty($date_from)) {
    $where[] = 'received_at >= %s';
    $where_values[] = $date_from . ' 00:00:00';
}

if (!empty($date_to)) {
    $where[] = 'received_at <= %s';
    $where_values[] = $date_to . ' 23:59:59';
}

$where_clause = implode(' AND ', $where);

if (!empty($where_values)) {
    $query = $wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY received_at DESC LIMIT 100",
        $where_values
    );
} else {
    $query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY received_at DESC LIMIT 100";
}

$logs = $wpdb->get_results($query);

// Get statistics
$total_logs = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
$processed_logs = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE processed = 1");
$failed_logs = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE processed = 1 AND error_message IS NOT NULL");
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <svg width="24" height="24" fill="none" stroke="currentColor" style="vertical-align: middle; margin-right: 8px;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        Logs de Webhook MercadoPago
    </h1>
    
    <div class="mas-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
        <div class="mas-stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="color: #666; font-size: 14px;">Total Peticiones</div>
            <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo number_format($total_logs); ?></div>
        </div>
        <div class="mas-stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="color: #666; font-size: 14px;">Procesadas</div>
            <div style="font-size: 32px; font-weight: bold; color: #00a32a;"><?php echo number_format($processed_logs); ?></div>
        </div>
        <div class="mas-stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="color: #666; font-size: 14px;">Con Errores</div>
            <div style="font-size: 32px; font-weight: bold; color: #d63638;"><?php echo number_format($failed_logs); ?></div>
        </div>
    </div>
    
    <div class="mas-filters-section" style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <form method="get" action="">
            <input type="hidden" name="page" value="mas-webhook-logs">
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Estado</label>
                    <select name="status" style="width: 100%;">
                        <option value="">Todos</option>
                        <option value="approved" <?php selected($status_filter, 'approved'); ?>>Aprobado</option>
                        <option value="pending" <?php selected($status_filter, 'pending'); ?>>Pendiente</option>
                        <option value="rejected" <?php selected($status_filter, 'rejected'); ?>>Rechazado</option>
                        <option value="ping" <?php selected($status_filter, 'ping'); ?>>Ping</option>
                        <option value="error" <?php selected($status_filter, 'error'); ?>>Error</option>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Procesado</label>
                    <select name="processed" style="width: 100%;">
                        <option value="-1">Todos</option>
                        <option value="1" <?php selected($processed_filter, 1); ?>>Sí</option>
                        <option value="0" <?php selected($processed_filter, 0); ?>>No</option>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Fecha Desde</label>
                    <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" style="width: 100%;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Fecha Hasta</label>
                    <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" style="width: 100%;">
                </div>
            </div>
            
            <div style="margin-top: 15px;">
                <button type="submit" class="button button-primary">Filtrar</button>
                <a href="?page=mas-webhook-logs" class="button">Limpiar Filtros</a>
            </div>
        </form>
    </div>
    
    <div class="mas-delete-section" style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h3>Limpiar Logs Antiguos</h3>
        <form method="post" action="">
            <?php wp_nonce_field('mas_delete_webhook_logs'); ?>
            <p>
                <label>Eliminar registros más antiguos que: </label>
                <input type="number" name="days_old" value="30" min="1" max="365" style="width: 80px;"> días
                <button type="submit" name="delete_logs" class="button button-secondary" onclick="return confirm('¿Está seguro de eliminar estos registros?')">Eliminar</button>
            </p>
        </form>
    </div>
    
    <div class="mas-logs-table" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha/Hora</th>
                    <th>Payment ID</th>
                    <th>External Reference</th>
                    <th>Estado</th>
                    <th>Procesado</th>
                    <th>Tiempo (s)</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            <p style="color: #666;">No hay logs registrados</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log->id); ?></td>
                            <td><?php echo esc_html(date('d/m/Y H:i:s', strtotime($log->received_at))); ?></td>
                            <td><?php echo esc_html($log->payment_id ?: '-'); ?></td>
                            <td><?php echo esc_html($log->external_reference ?: '-'); ?></td>
                            <td>
                                <?php
                                $status_badge_colors = array(
                                    'approved' => '#00a32a',
                                    'pending' => '#dba617',
                                    'rejected' => '#d63638',
                                    'ping' => '#2271b1',
                                    'error' => '#d63638'
                                );
                                $badge_color = isset($status_badge_colors[$log->status]) ? $status_badge_colors[$log->status] : '#666';
                                ?>
                                <span style="display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500; background: <?php echo $badge_color; ?>; color: white;">
                                    <?php echo esc_html($log->status ?: 'N/A'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($log->processed): ?>
                                    <span style="color: #00a32a;">✓ Sí</span>
                                <?php else: ?>
                                    <span style="color: #d63638;">✗ No</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $log->processing_time ? number_format($log->processing_time, 4) : '-'; ?></td>
                            <td>
                                <button class="button button-small view-log-details" data-log-id="<?php echo $log->id; ?>">Ver Detalles</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal for log details -->
<div id="log-details-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 8px; max-width: 900px; max-height: 90vh; overflow-y: auto; width: 90%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0;">Detalles del Log</h2>
            <button class="button" onclick="document.getElementById('log-details-modal').style.display='none'">Cerrar</button>
        </div>
        <div id="log-details-content"></div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('.view-log-details').on('click', function() {
        var logId = $(this).data('log-id');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mas_get_webhook_log_details',
                log_id: logId,
                nonce: '<?php echo wp_create_nonce('mas_webhook_logs'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var log = response.data;
                    var html = '<div style="font-family: monospace; font-size: 13px;">';
                    
                    html += '<h3>Información General</h3>';
                    html += '<table class="widefat"><tbody>';
                    html += '<tr><td><strong>ID:</strong></td><td>' + log.id + '</td></tr>';
                    html += '<tr><td><strong>Recibido:</strong></td><td>' + log.received_at + '</td></tr>';
                    html += '<tr><td><strong>Método:</strong></td><td>' + log.request_method + '</td></tr>';
                    html += '<tr><td><strong>URI:</strong></td><td>' + log.request_uri + '</td></tr>';
                    html += '<tr><td><strong>Payment ID:</strong></td><td>' + (log.payment_id || 'N/A') + '</td></tr>';
                    html += '<tr><td><strong>External Reference:</strong></td><td>' + (log.external_reference || 'N/A') + '</td></tr>';
                    html += '<tr><td><strong>Estado:</strong></td><td>' + (log.status || 'N/A') + '</td></tr>';
                    html += '<tr><td><strong>Procesado:</strong></td><td>' + (log.processed ? 'Sí' : 'No') + '</td></tr>';
                    html += '<tr><td><strong>Tiempo:</strong></td><td>' + (log.processing_time || 'N/A') + ' segundos</td></tr>';
                    if (log.error_message) {
                        html += '<tr><td><strong>Error:</strong></td><td style="color: red;">' + log.error_message + '</td></tr>';
                    }
                    html += '</tbody></table>';
                    
                    html += '<h3>GET Parameters</h3>';
                    html += '<pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto;">' + log.get_params + '</pre>';
                    
                    html += '<h3>POST Parameters</h3>';
                    html += '<pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto;">' + log.post_params + '</pre>';
                    
                    html += '<h3>Raw Body</h3>';
                    html += '<pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto;">' + (log.raw_body || 'Empty') + '</pre>';
                    
                    html += '<h3>Headers</h3>';
                    html += '<pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto;">' + log.headers + '</pre>';
                    
                    html += '</div>';
                    
                    $('#log-details-content').html(html);
                    $('#log-details-modal').css('display', 'flex');
                }
            }
        });
    });
    
    // Close modal on background click
    $('#log-details-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
});
</script>
