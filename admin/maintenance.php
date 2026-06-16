<?php
/**
 * System Maintenance Page
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$tables_prefix = $wpdb->prefix . 'mas_';

// Handle maintenance actions
if (isset($_POST['mas_maintenance_action']) && check_admin_referer('mas_maintenance_nonce')) {
    $action = sanitize_text_field($_POST['mas_maintenance_action']);
    $message = '';
    $message_type = 'success';
    
    switch ($action) {
        case 'optimize_tables':
            $tables = array(
                $tables_prefix . 'appointments',
                $tables_prefix . 'boxes',
                $tables_prefix . 'box_rentals',
                $tables_prefix . 'professionals',
                $tables_prefix . 'services',
                $tables_prefix . 'specialties',
                $tables_prefix . 'promotions'
            );
            foreach ($tables as $table) {
                $wpdb->query("OPTIMIZE TABLE $table");
            }
            $message = 'Tablas optimizadas exitosamente.';
            break;
            
        case 'clear_logs':
            $wpdb->query("TRUNCATE TABLE {$tables_prefix}debug_logs");
            $wpdb->query("TRUNCATE TABLE {$tables_prefix}webhook_logs");
            $message = 'Logs limpiados exitosamente.';
            break;
            
        case 'clear_old_appointments':
            $date_limit = date('Y-m-d', strtotime('-6 months'));
            $deleted = $wpdb->delete(
                $tables_prefix . 'appointments',
                array('status' => 'completed', 'appointment_date' => $date_limit),
                array('%s', '%s')
            );
            $message = "Se eliminaron $deleted citas completadas de hace más de 6 meses.";
            break;
            
        case 'regenerate_permalinks':
            flush_rewrite_rules();
            $message = 'Enlaces permanentes regenerados exitosamente.';
            break;
    }
    
    if ($message) {
        echo "<div class='notice notice-{$message_type} is-dismissible'><p><strong>{$message}</strong></p></div>";
    }
}

// Get database stats
$appointments_count = $wpdb->get_var("SELECT COUNT(*) FROM {$tables_prefix}appointments");
$rentals_count = $wpdb->get_var("SELECT COUNT(*) FROM {$tables_prefix}box_rentals");
$professionals_count = $wpdb->get_var("SELECT COUNT(*) FROM {$tables_prefix}professionals");
$debug_logs_count = $wpdb->get_var("SELECT COUNT(*) FROM {$tables_prefix}debug_logs");
$webhook_logs_count = $wpdb->get_var("SELECT COUNT(*) FROM {$tables_prefix}webhook_logs");

$db_size_query = $wpdb->get_results("
    SELECT 
        SUM(data_length + index_length) as size_bytes
    FROM information_schema.TABLES 
    WHERE table_schema = DATABASE()
    AND table_name LIKE '{$tables_prefix}%'
");
$db_size_mb = round($db_size_query[0]->size_bytes / 1024 / 1024, 2);
?>

<div class="wrap mas-maintenance-page">
    <style>
        .mas-maintenance-page {
            background: #f8f9fa;
            margin: 20px 0 20px -20px;
            padding: 30px 40px;
            min-height: calc(100vh - 32px);
        }
        
        .mas-maintenance-header {
            margin-bottom: 32px;
        }
        
        .mas-maintenance-header h1 {
            font-size: 32px;
            font-weight: 600;
            color: #1a1a1a;
            margin: 0 0 8px 0;
        }
        
        .mas-maintenance-header p {
            font-size: 15px;
            color: #6b7280;
            margin: 0;
        }
        
        .mas-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        
        .mas-stat-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.2s ease;
        }
        
        .mas-stat-card:hover {
            border-color: #3b82f6;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
        }
        
        .mas-stat-label {
            font-size: 13px;
            font-weight: 500;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .mas-stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
        }
        
        .mas-maintenance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
        }
        
        .mas-maintenance-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
            transition: all 0.2s ease;
        }
        
        .mas-maintenance-card:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
        }
        
        .mas-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .mas-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .mas-card-icon.blue {
            background: #eff6ff;
            color: #3b82f6;
        }
        
        .mas-card-icon.green {
            background: #f0fdf4;
            color: #22c55e;
        }
        
        .mas-card-icon.amber {
            background: #fffbeb;
            color: #f59e0b;
        }
        
        .mas-card-icon.red {
            background: #fef2f2;
            color: #ef4444;
        }
        
        .mas-card-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a1a;
            margin: 0;
        }
        
        .mas-card-description {
            font-size: 14px;
            color: #6b7280;
            line-height: 1.5;
            margin: 0 0 20px 0;
        }
        
        .mas-action-button {
            width: 100%;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .mas-action-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3);
        }
        
        .mas-action-button:active {
            transform: translateY(0);
        }
        
        .mas-action-button.danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }
        
        .mas-action-button.danger:hover {
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.3);
        }
        
        .mas-action-button.warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        
        .mas-action-button.warning:hover {
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.3);
        }
        
        .mas-warning-text {
            background: #fef3c7;
            border-left: 3px solid #f59e0b;
            padding: 12px;
            margin-top: 12px;
            border-radius: 6px;
            font-size: 13px;
            color: #92400e;
        }
    </style>
    
    <div class="mas-maintenance-header">
        <h1>Mantenimiento del Sistema</h1>
        <p>Herramientas de administración y optimización del sistema médico</p>
    </div>
    
    <!-- Stats Grid -->
    <div class="mas-stats-grid">
        <div class="mas-stat-card">
            <div class="mas-stat-label">Citas Médicas</div>
            <div class="mas-stat-value"><?php echo number_format($appointments_count); ?></div>
        </div>
        <div class="mas-stat-card">
            <div class="mas-stat-label">Arriendos</div>
            <div class="mas-stat-value"><?php echo number_format($rentals_count); ?></div>
        </div>
        <div class="mas-stat-card">
            <div class="mas-stat-label">Profesionales</div>
            <div class="mas-stat-value"><?php echo number_format($professionals_count); ?></div>
        </div>
        <div class="mas-stat-card">
            <div class="mas-stat-label">Tamaño BD</div>
            <div class="mas-stat-value"><?php echo $db_size_mb; ?> MB</div>
        </div>
    </div>
    
    <!-- Maintenance Actions Grid -->
    <div class="mas-maintenance-grid">
        
        <!-- Database Optimization -->
        <div class="mas-maintenance-card">
            <div class="mas-card-header">
                <div class="mas-card-icon blue">🗄️</div>
                <h3 class="mas-card-title">Optimizar Base de Datos</h3>
            </div>
            <p class="mas-card-description">
                Optimiza todas las tablas del sistema para mejorar el rendimiento y reducir el espacio utilizado.
            </p>
            <form method="post">
                <?php wp_nonce_field('mas_maintenance_nonce'); ?>
                <input type="hidden" name="mas_maintenance_action" value="optimize_tables">
                <button type="submit" class="mas-action-button" onclick="return confirm('¿Desea optimizar las tablas de la base de datos?')">
                    <span>⚡</span> Optimizar Tablas
                </button>
            </form>
        </div>
        
        <!-- Clear Logs -->
        <div class="mas-maintenance-card">
            <div class="mas-card-header">
                <div class="mas-card-icon amber">📋</div>
                <h3 class="mas-card-title">Limpiar Logs</h3>
            </div>
            <p class="mas-card-description">
                Elimina todos los registros de logs de depuración y webhooks. Logs actuales: <?php echo number_format($debug_logs_count + $webhook_logs_count); ?>.
            </p>
            <form method="post">
                <?php wp_nonce_field('mas_maintenance_nonce'); ?>
                <input type="hidden" name="mas_maintenance_action" value="clear_logs">
                <button type="submit" class="mas-action-button warning" onclick="return confirm('¿Está seguro de eliminar todos los logs?')">
                    <span>🗑️</span> Limpiar Logs
                </button>
            </form>
        </div>
        
        <!-- Clear Old Appointments -->
        <div class="mas-maintenance-card">
            <div class="mas-card-header">
                <div class="mas-card-icon red">📅</div>
                <h3 class="mas-card-title">Limpiar Citas Antiguas</h3>
            </div>
            <p class="mas-card-description">
                Elimina citas completadas de hace más de 6 meses para reducir el tamaño de la base de datos.
            </p>
            <form method="post">
                <?php wp_nonce_field('mas_maintenance_nonce'); ?>
                <input type="hidden" name="mas_maintenance_action" value="clear_old_appointments">
                <button type="submit" class="mas-action-button danger" onclick="return confirm('¿Desea eliminar las citas completadas de hace más de 6 meses? Esta acción no se puede deshacer.')">
                    <span>⚠️</span> Eliminar Citas Antiguas
                </button>
            </form>
            <div class="mas-warning-text">
                ⚠️ Esta acción es permanente y no se puede deshacer
            </div>
        </div>
        
        <!-- Regenerate Permalinks -->
        <div class="mas-maintenance-card">
            <div class="mas-card-header">
                <div class="mas-card-icon green">🔗</div>
                <h3 class="mas-card-title">Regenerar Enlaces</h3>
            </div>
            <p class="mas-card-description">
                Regenera las reglas de reescritura de WordPress para resolver problemas con URLs del sistema.
            </p>
            <form method="post">
                <?php wp_nonce_field('mas_maintenance_nonce'); ?>
                <input type="hidden" name="mas_maintenance_action" value="regenerate_permalinks">
                <button type="submit" class="mas-action-button">
                    <span>🔄</span> Regenerar Enlaces
                </button>
            </form>
        </div>
        
    </div>
</div>
