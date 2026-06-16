<?php
/**
 * Reports Page - Modern Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$appointments_table = $wpdb->prefix . 'mas_appointments';
$rentals_table = $wpdb->prefix . 'mas_box_rentals';
$professionals_table = $wpdb->prefix . 'mas_professionals';
$services_table = $wpdb->prefix . 'mas_services';
$boxes_table = $wpdb->prefix . 'mas_boxes';
$specialties_table = $wpdb->prefix . 'mas_specialties';
$users_table = $wpdb->prefix . 'users';

$start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-t');

// Appointments statistics
$total_appointments = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $appointments_table WHERE appointment_date BETWEEN %s AND %s",
    $start_date, $end_date
));

$total_appointments_income = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(amount_paid) FROM $appointments_table 
    WHERE payment_status = 'paid' AND appointment_date BETWEEN %s AND %s",
    $start_date, $end_date
));

$paid_appointments = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $appointments_table 
    WHERE payment_status = 'paid' AND appointment_date BETWEEN %s AND %s",
    $start_date, $end_date
));

$pending_appointments = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $appointments_table WHERE status = 'pending' AND appointment_date BETWEEN %s AND %s",
    $start_date, $end_date
));

$scheduled_appointments = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $appointments_table WHERE status = 'scheduled' AND appointment_date BETWEEN %s AND %s",
    $start_date, $end_date
));

$cancelled_appointments = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $appointments_table WHERE status = 'cancelled' AND appointment_date BETWEEN %s AND %s",
    $start_date, $end_date
));

$isapre_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $appointments_table 
    WHERE health_insurance != 'FONASA' AND health_insurance != '' AND health_insurance IS NOT NULL
    AND appointment_date BETWEEN %s AND %s",
    $start_date, $end_date
));

$fonasa_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $appointments_table 
    WHERE health_insurance = 'FONASA' AND appointment_date BETWEEN %s AND %s",
    $start_date, $end_date
));

// Rentals statistics
$total_rentals = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $rentals_table WHERE rental_date BETWEEN %s AND %s",
    $start_date, $end_date
));

$total_rental_income = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(total_price) FROM $rentals_table 
    WHERE payment_status = 'paid' AND rental_date BETWEEN %s AND %s",
    $start_date, $end_date
));

$active_rentals = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $rentals_table 
    WHERE status = 'active' AND rental_date BETWEEN %s AND %s",
    $start_date, $end_date
));

$pending_rentals = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $rentals_table 
    WHERE status = 'pending' AND rental_date BETWEEN %s AND %s",
    $start_date, $end_date
));

$payment_methods_appointments = $wpdb->get_results($wpdb->prepare(
    "SELECT payment_method, COUNT(*) as total, SUM(amount_paid) as total_amount
    FROM $appointments_table
    WHERE payment_status = 'paid' AND appointment_date BETWEEN %s AND %s
    GROUP BY payment_method",
    $start_date, $end_date
));

$payment_methods_rentals = $wpdb->get_results($wpdb->prepare(
    "SELECT 'arriendo' as source, payment_method, COUNT(*) as total, SUM(total_price) as total_amount
    FROM $rentals_table
    WHERE payment_status = 'paid' AND rental_date BETWEEN %s AND %s
    GROUP BY payment_method",
    $start_date, $end_date
));

// Combine payment methods
$payment_methods_combined = array();
foreach ($payment_methods_appointments as $pm) {
    $method = $pm->payment_method ?: 'Sin especificar';
    if (!isset($payment_methods_combined[$method])) {
        $payment_methods_combined[$method] = array('total' => 0, 'amount' => 0);
    }
    $payment_methods_combined[$method]['total'] += $pm->total;
    $payment_methods_combined[$method]['amount'] += $pm->total_amount;
}

foreach ($payment_methods_rentals as $pm) {
    $method = $pm->payment_method ?: 'Sin especificar';
    if (!isset($payment_methods_combined[$method])) {
        $payment_methods_combined[$method] = array('total' => 0, 'amount' => 0);
    }
    $payment_methods_combined[$method]['total'] += $pm->total;
    $payment_methods_combined[$method]['amount'] += $pm->total_amount;
}

$total_income = ($total_appointments_income ?: 0) + ($total_rental_income ?: 0);

$total_patients = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT patient_email) FROM $appointments_table WHERE appointment_date BETWEEN %s AND %s",
    $start_date, $end_date
));

?>

<div class="wrap mas-reports-wrap">
    <div class="mas-reports-header">
        <h1>Reportes del Sistema</h1>
        <div class="mas-header-actions">
            <button class="button button-primary" onclick="window.print()">
                <span class="dashicons dashicons-printer"></span> Imprimir
            </button>
        </div>
    </div>
    
    <!-- Modern filter section -->
    <div class="mas-filter-card">
        <form method="get" action="" class="mas-filter-form">
            <input type="hidden" name="page" value="medical-reports">
            <div class="mas-filter-inputs">
                <div class="mas-input-group">
                    <label for="start_date">Fecha Inicio</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                </div>
                <div class="mas-input-group">
                    <label for="end_date">Fecha Fin</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
                </div>
                <div class="mas-filter-actions">
                    <button type="submit" class="button button-primary">Filtrar</button>
                    <a href="?page=medical-reports" class="button">Limpiar</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Hero income card -->
    <div class="mas-hero-card">
        <div class="mas-hero-icon">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.41 16.09V20h-2.67v-1.93c-1.71-.36-3.16-1.46-3.27-3.4h1.96c.1 1.05.82 1.87 2.65 1.87 1.96 0 2.4-.98 2.4-1.59 0-.83-.44-1.61-2.67-2.14-2.48-.6-4.18-1.62-4.18-3.67 0-1.72 1.39-2.84 3.11-3.21V4h2.67v1.95c1.86.45 2.79 1.86 2.85 3.39H14.3c-.05-1.11-.64-1.87-2.22-1.87-1.5 0-2.4.68-2.4 1.64 0 .84.65 1.39 2.67 1.91s4.18 1.39 4.18 3.91c-.01 1.83-1.38 2.83-3.12 3.16z" fill="currentColor"/>
            </svg>
        </div>
        <div class="mas-hero-content">
            <h2>Ingresos Totales</h2>
            <div class="mas-hero-amount">$<?php echo number_format($total_income, 0, ',', '.'); ?></div>
            <div class="mas-hero-period">
                Del <?php echo date('d/m/Y', strtotime($start_date)); ?> al <?php echo date('d/m/Y', strtotime($end_date)); ?>
            </div>
        </div>
    </div>

    <!-- KPI Cards Grid -->
    <div class="mas-kpi-grid">
        <div class="mas-kpi-card mas-kpi-blue">
            <div class="mas-kpi-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                    <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z" fill="currentColor"/>
                </svg>
            </div>
            <div class="mas-kpi-content">
                <div class="mas-kpi-label">Total Pacientes</div>
                <div class="mas-kpi-value"><?php echo number_format($total_patients); ?></div>
            </div>
        </div>

        <div class="mas-kpi-card mas-kpi-green">
            <div class="mas-kpi-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                    <path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm-2 14l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z" fill="currentColor"/>
                </svg>
            </div>
            <div class="mas-kpi-content">
                <div class="mas-kpi-label">ISAPRE</div>
                <div class="mas-kpi-value"><?php echo number_format($isapre_count); ?></div>
            </div>
        </div>

        <div class="mas-kpi-card mas-kpi-teal">
            <div class="mas-kpi-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                    <path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm0 4c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm6 12H6v-1.4c0-2 4-3.1 6-3.1s6 1.1 6 3.1V19z" fill="currentColor"/>
                </svg>
            </div>
            <div class="mas-kpi-content">
                <div class="mas-kpi-label">FONASA</div>
                <div class="mas-kpi-value"><?php echo number_format($fonasa_count); ?></div>
            </div>
        </div>

        <div class="mas-kpi-card mas-kpi-purple">
            <div class="mas-kpi-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                    <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z" fill="currentColor"/>
                </svg>
            </div>
            <div class="mas-kpi-content">
                <div class="mas-kpi-label">Total Citas</div>
                <div class="mas-kpi-value"><?php echo number_format($total_appointments); ?></div>
            </div>
        </div>

        <div class="mas-kpi-card mas-kpi-orange">
            <div class="mas-kpi-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                    <path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z" fill="currentColor"/>
                </svg>
            </div>
            <div class="mas-kpi-content">
                <div class="mas-kpi-label">Ingresos Citas</div>
                <div class="mas-kpi-value">$<?php echo number_format($total_appointments_income ?: 0, 0, ',', '.'); ?></div>
            </div>
        </div>

        <div class="mas-kpi-card mas-kpi-success">
            <div class="mas-kpi-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" fill="currentColor"/>
                </svg>
            </div>
            <div class="mas-kpi-content">
                <div class="mas-kpi-label">Citas Pagadas</div>
                <div class="mas-kpi-value"><?php echo number_format($paid_appointments); ?></div>
            </div>
        </div>

        <div class="mas-kpi-card mas-kpi-warning">
            <div class="mas-kpi-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="currentColor"/>
                </svg>
            </div>
            <div class="mas-kpi-content">
                <div class="mas-kpi-label">Citas Agendadas</div>
                <div class="mas-kpi-value"><?php echo number_format($scheduled_appointments); ?></div>
            </div>
        </div>

        <div class="mas-kpi-card mas-kpi-pending">
            <div class="mas-kpi-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                    <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z" fill="currentColor"/>
                </svg>
            </div>
            <div class="mas-kpi-content">
                <div class="mas-kpi-label">Citas Pendientes</div>
                <div class="mas-kpi-value"><?php echo number_format($pending_appointments); ?></div>
            </div>
        </div>

        <div class="mas-kpi-card mas-kpi-danger">
            <div class="mas-kpi-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" fill="currentColor"/>
                </svg>
            </div>
            <div class="mas-kpi-content">
                <div class="mas-kpi-label">Citas Canceladas</div>
                <div class="mas-kpi-value"><?php echo number_format($cancelled_appointments); ?></div>
            </div>
        </div>

        <div class="mas-kpi-card mas-kpi-indigo">
            <div class="mas-kpi-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                    <path d="M20 6h-2.18c.11-.31.18-.65.18-1a2.996 2.996 0 0 0-5.5-1.65l-.5.67-.5-.68C10.96 2.54 10.05 2 9 2 7.34 2 6 3.34 6 5c0 .35.07.69.18 1H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-5-2c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zM9 4c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm11 15H4v-2h16v2zm0-5H4V8h5.08L7 10.83 8.62 12 11 8.76l1-1.36 1 1.36L15.38 12 17 10.83 14.92 8H20v6z" fill="currentColor"/>
                </svg>
            </div>
            <div class="mas-kpi-content">
                <div class="mas-kpi-label">Total Arriendos</div>
                <div class="mas-kpi-value"><?php echo number_format($total_rentals); ?></div>
            </div>
        </div>

        <div class="mas-kpi-card mas-kpi-cyan">
            <div class="mas-kpi-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                    <path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z" fill="currentColor"/>
                </svg>
            </div>
            <div class="mas-kpi-content">
                <div class="mas-kpi-label">Ingresos Arriendos</div>
                <div class="mas-kpi-value">$<?php echo number_format($total_rental_income ?: 0, 0, ',', '.'); ?></div>
            </div>
        </div>

        <div class="mas-kpi-card mas-kpi-success">
            <div class="mas-kpi-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" fill="currentColor"/>
                </svg>
            </div>
            <div class="mas-kpi-content">
                <div class="mas-kpi-label">Arriendos Confirmados</div>
                <div class="mas-kpi-value"><?php echo number_format($active_rentals); ?></div>
            </div>
        </div>

        <div class="mas-kpi-card mas-kpi-pending">
            <div class="mas-kpi-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                    <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z" fill="currentColor"/>
                </svg>
            </div>
            <div class="mas-kpi-content">
                <div class="mas-kpi-label">Arriendos Pendientes</div>
                <div class="mas-kpi-value"><?php echo number_format($pending_rentals); ?></div>
            </div>
        </div>
    </div>

    <!-- Payment Methods Section -->
    <?php if (!empty($payment_methods_combined)): ?>
    <div class="mas-section-card">
        <div class="mas-section-header">
            <h2>Métodos de Pago</h2>
            <button class="mas-export-btn" onclick="exportToExcel('payment-methods-table', 'metodos_pago')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path d="M19 12v7H5v-7H3v7c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-7h-2zm-6 .67l2.59-2.58L17 11.5l-5 5-5-5 1.41-1.41L11 12.67V3h2z" fill="currentColor"/>
                </svg>
                Excel
            </button>
        </div>
        <div class="mas-payment-grid">
            <?php foreach ($payment_methods_combined as $method => $data): ?>
                <div class="mas-payment-card">
                    <div class="mas-payment-icon">
                        <?php 
                        $icons = array(
                            'mercadopago' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z" fill="currentColor"/></svg>',
                            'transferencia' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z" fill="currentColor"/></svg>',
                            'pos' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z" fill="currentColor"/></svg>',
                            'efectivo' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z" fill="currentColor"/></svg>'
                        );
                        echo $icons[strtolower($method)] ?? $icons['efectivo'];
                        ?>
                    </div>
                    <h3><?php echo ucfirst($method); ?></h3>
                    <div class="mas-payment-count"><?php echo number_format($data['total']); ?> pagos</div>
                    <div class="mas-payment-amount">$<?php echo number_format($data['amount'], 0, ',', '.'); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Data Tables Section with Pagination -->
    <div class="mas-section-card">
        <div class="mas-section-header">
            <h2>Detalle de Citas</h2>
            <button class="mas-export-btn" onclick="exportAllData('appointments')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path d="M19 12v7H5v-7H3v7c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-7h-2zm-6 .67l2.59-2.58L17 11.5l-5 5-5-5 1.41-1.41L11 12.67V3h2z" fill="currentColor"/>
                </svg>
                Excel
            </button>
        </div>
        <div class="mas-pagination-controls">
            <div class="mas-per-page">
                <label>Mostrar:</label>
                <select onchange="changePerPage('appointments', this.value)">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span>registros</span>
            </div>
            <div class="mas-pagination-nav" id="appointments-pagination"></div>
        </div>
        <div class="mas-table-container">
            <table class="mas-data-table" id="appointments-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Paciente</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Profesional</th>
                        <th>Servicio</th>
                        <th>Previsión</th>
                        <th>Estado</th>
                        <th>Pago</th>
                        <th>Monto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $appointments = $wpdb->get_results($wpdb->prepare(
                        "SELECT a.*, u.display_name as professional_name, s.service_name
                        FROM $appointments_table a
                        LEFT JOIN $professionals_table p ON a.professional_id = p.id
                        LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
                        LEFT JOIN $services_table s ON a.service_id = s.id
                        WHERE a.appointment_date BETWEEN %s AND %s
                        ORDER BY a.appointment_date DESC, a.appointment_time DESC",
                        $start_date, $end_date
                    ));
                    
                    if ($appointments):
                        foreach ($appointments as $apt):
                    ?>
                        <tr>
                            <td><?php echo $apt->id; ?></td>
                            <td><?php echo esc_html($apt->patient_name); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($apt->appointment_date)); ?></td>
                            <td><?php echo date('H:i', strtotime($apt->appointment_time)); ?></td>
                            <td><?php echo esc_html($apt->professional_name ?: 'Sin asignar'); ?></td>
                            <td><?php echo esc_html($apt->service_name); ?></td>
                            <td><?php echo esc_html($apt->health_insurance ?: 'N/A'); ?></td>
                            <td><span class="mas-badge mas-badge-<?php echo $apt->status; ?>"><?php echo ucfirst($apt->status); ?></span></td>
                            <td><span class="mas-badge mas-badge-<?php echo $apt->payment_status; ?>"><?php echo ucfirst($apt->payment_status); ?></span></td>
                            <td><strong>$<?php echo number_format($apt->amount_paid ?: 0, 0, ',', '.'); ?></strong></td>
                        </tr>
                    <?php 
                        endforeach;
                    else: 
                    ?>
                        <tr class="no-data"><td colspan="10">No hay citas en el período seleccionado</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Rentals Table -->
    <div class="mas-section-card">
        <div class="mas-section-header">
            <h2>Detalle de Arriendos</h2>
            <button class="mas-export-btn" onclick="exportAllData('rentals')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path d="M19 12v7H5v-7H3v7c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-7h-2zm-6 .67l2.59-2.58L17 11.5l-5 5-5-5 1.41-1.41L11 12.67V3h2z" fill="currentColor"/>
                </svg>
                Excel
            </button>
        </div>
        <div class="mas-pagination-controls">
            <div class="mas-per-page">
                <label>Mostrar:</label>
                <select onchange="changePerPage('rentals', this.value)">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span>registros</span>
            </div>
            <div class="mas-pagination-nav" id="rentals-pagination"></div>
        </div>
        <div class="mas-table-container">
            <table class="mas-data-table" id="rentals-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Box</th>
                        <th>Profesional</th>
                        <th>Fecha</th>
                        <th>Hora Inicio</th>
                        <th>Hora Fin</th>
                        <th>Horas</th>
                        <th>Estado</th>
                        <th>Pago</th>
                        <th>Monto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rentals = $wpdb->get_results($wpdb->prepare(
                        "SELECT r.*, b.box_name, u.display_name as professional_name
                        FROM $rentals_table r
                        LEFT JOIN $boxes_table b ON r.box_id = b.id
                        LEFT JOIN $professionals_table p ON r.professional_id = p.id
                        LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
                        WHERE r.rental_date BETWEEN %s AND %s
                        ORDER BY r.rental_date DESC, r.start_time DESC",
                        $start_date, $end_date
                    ));
                    
                    if ($rentals):
                        foreach ($rentals as $rental):
                    ?>
                        <tr>
                            <td><?php echo $rental->id; ?></td>
                            <td><?php echo esc_html($rental->box_name); ?></td>
                            <td><?php echo esc_html($rental->professional_name); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($rental->rental_date)); ?></td>
                            <td><?php echo date('H:i', strtotime($rental->start_time)); ?></td>
                            <td><?php echo date('H:i', strtotime($rental->end_time)); ?></td>
                            <td><?php echo number_format($rental->total_hours, 1); ?>h</td>
                            <td><span class="mas-badge mas-badge-<?php echo $rental->status; ?>"><?php echo ucfirst($rental->status); ?></span></td>
                            <td><span class="mas-badge mas-badge-<?php echo $rental->payment_status; ?>"><?php echo ucfirst($rental->payment_status); ?></span></td>
                            <td><strong>$<?php echo number_format($rental->total_price, 0, ',', '.'); ?></strong></td>
                        </tr>
                    <?php 
                        endforeach;
                    else: 
                    ?>
                        <tr class="no-data"><td colspan="10">No hay arriendos en el período seleccionado</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Professionals Summary Table -->
    <div class="mas-section-card">
        <div class="mas-section-header">
            <h2>Resumen por Profesional</h2>
            <button class="mas-export-btn" onclick="exportAllData('professionals')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path d="M19 12v7H5v-7H3v7c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-7h-2zm-6 .67l2.59-2.58L17 11.5l-5 5-5-5 1.41-1.41L11 12.67V3h2z" fill="currentColor"/>
                </svg>
                Excel
            </button>
        </div>
        <div class="mas-pagination-controls">
            <div class="mas-per-page">
                <label>Mostrar:</label>
                <select onchange="changePerPage('professionals', this.value)">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span>registros</span>
            </div>
            <div class="mas-pagination-nav" id="professionals-pagination"></div>
        </div>
        <div class="mas-table-container">
            <table class="mas-data-table" id="professionals-table">
                <thead>
                    <tr>
                        <th>Profesional</th>
                        <th>Especialidad</th>
                        <th>Citas</th>
                        <th>Arriendos</th>
                        <th>Ingresos Citas</th>
                        <th>Ingresos Arriendos</th>
                        <th>Total Ingresos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $professionals_stats = $wpdb->get_results($wpdb->prepare(
                        "SELECT p.id, u.display_name as name, p.specialty,
                        COUNT(DISTINCT a.id) as total_appointments,
                        COUNT(DISTINCT r.id) as total_rentals,
                        SUM(CASE WHEN a.payment_status = 'paid' THEN a.amount_paid ELSE 0 END) as appointments_income,
                        SUM(CASE WHEN r.payment_status = 'paid' THEN r.total_price ELSE 0 END) as rentals_income
                        FROM $professionals_table p
                        LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
                        LEFT JOIN $appointments_table a ON p.id = a.professional_id AND a.appointment_date BETWEEN %s AND %s
                        LEFT JOIN $rentals_table r ON p.id = r.professional_id AND r.rental_date BETWEEN %s AND %s
                        WHERE p.status = 'active'
                        GROUP BY p.id
                        ORDER BY (appointments_income + rentals_income) DESC",
                        $start_date, $end_date, $start_date, $end_date
                    ));
                    
                    if ($professionals_stats):
                        foreach ($professionals_stats as $prof):
                            $total = ($prof->appointments_income ?: 0) + ($prof->rentals_income ?: 0);
                    ?>
                        <tr>
                            <td><?php echo esc_html($prof->name); ?></td>
                            <td><?php echo esc_html($prof->specialty ?: 'N/A'); ?></td>
                            <td><?php echo number_format($prof->total_appointments); ?></td>
                            <td><?php echo number_format($prof->total_rentals); ?></td>
                            <td>$<?php echo number_format($prof->appointments_income ?: 0, 0, ',', '.'); ?></td>
                            <td>$<?php echo number_format($prof->rentals_income ?: 0, 0, ',', '.'); ?></td>
                            <td><strong>$<?php echo number_format($total, 0, ',', '.'); ?></strong></td>
                        </tr>
                    <?php 
                        endforeach;
                    else: 
                    ?>
                        <tr class="no-data"><td colspan="7">No hay profesionales activos</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Services Summary Table -->
    <div class="mas-section-card">
        <div class="mas-section-header">
            <h2>Resumen por Servicio</h2>
            <button class="mas-export-btn" onclick="exportAllData('services')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path d="M19 12v7H5v-7H3v7c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-7h-2zm-6 .67l2.59-2.58L17 11.5l-5 5-5-5 1.41-1.41L11 12.67V3h2z" fill="currentColor"/>
                </svg>
                Excel
            </button>
        </div>
        <div class="mas-pagination-controls">
            <div class="mas-per-page">
                <label>Mostrar:</label>
                <select onchange="changePerPage('services', this.value)">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span>registros</span>
            </div>
            <div class="mas-pagination-nav" id="services-pagination"></div>
        </div>
        <div class="mas-table-container">
            <table class="mas-data-table" id="services-table">
                <thead>
                    <tr>
                        <th>Servicio</th>
                        <th>Precio Base</th>
                        <th>Total Citas</th>
                        <th>Citas Pagadas</th>
                        <th>Ingresos Generados</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $services_stats = $wpdb->get_results($wpdb->prepare(
                        "SELECT s.id, s.service_name, s.price,
                        COUNT(a.id) as total_appointments,
                        SUM(CASE WHEN a.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_appointments,
                        SUM(CASE WHEN a.payment_status = 'paid' THEN a.amount_paid ELSE 0 END) as total_income
                        FROM $services_table s
                        LEFT JOIN $appointments_table a ON s.id = a.service_id AND a.appointment_date BETWEEN %s AND %s
                        WHERE s.status = 'active'
                        GROUP BY s.id
                        ORDER BY total_income DESC",
                        $start_date, $end_date
                    ));
                    
                    if ($services_stats):
                        foreach ($services_stats as $service):
                    ?>
                        <tr>
                            <td><?php echo esc_html($service->service_name); ?></td>
                            <td>$<?php echo number_format($service->price, 0, ',', '.'); ?></td>
                            <td><?php echo number_format($service->total_appointments); ?></td>
                            <td><?php echo number_format($service->paid_appointments); ?></td>
                            <td><strong>$<?php echo number_format($service->total_income ?: 0, 0, ',', '.'); ?></strong></td>
                        </tr>
                    <?php 
                        endforeach;
                    else: 
                    ?>
                        <tr class="no-data"><td colspan="5">No hay servicios activos</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Boxes Summary Table -->
    <div class="mas-section-card">
        <div class="mas-section-header">
            <h2>Resumen por Box</h2>
            <button class="mas-export-btn" onclick="exportAllData('boxes')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path d="M19 12v7H5v-7H3v7c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-7h-2zm-6 .67l2.59-2.58L17 11.5l-5 5-5-5 1.41-1.41L11 12.67V3h2z" fill="currentColor"/>
                </svg>
                Excel
            </button>
        </div>
        <div class="mas-pagination-controls">
            <div class="mas-per-page">
                <label>Mostrar:</label>
                <select onchange="changePerPage('boxes', this.value)">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span>registros</span>
            </div>
            <div class="mas-pagination-nav" id="boxes-pagination"></div>
        </div>
        <div class="mas-table-container">
            <table class="mas-data-table" id="boxes-table">
                <thead>
                    <tr>
                        <th>Box</th>
                        <th>Numero</th>
                        <th>Precio/Hora</th>
                        <th>Total Arriendos</th>
                        <th>Horas Totales</th>
                        <th>Ingresos Generados</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $boxes_stats = $wpdb->get_results($wpdb->prepare(
                        "SELECT b.id, b.box_name, b.box_number, b.price_per_hour,
                        COUNT(r.id) as total_rentals,
                        SUM(r.total_hours) as total_hours,
                        SUM(CASE WHEN r.payment_status = 'paid' THEN r.total_price ELSE 0 END) as total_income
                        FROM $boxes_table b
                        LEFT JOIN $rentals_table r ON b.id = r.box_id AND r.rental_date BETWEEN %s AND %s
                        WHERE b.status = 'active'
                        GROUP BY b.id
                        ORDER BY total_income DESC",
                        $start_date, $end_date
                    ));
                    
                    if ($boxes_stats):
                        foreach ($boxes_stats as $box):
                    ?>
                        <tr>
                            <td><?php echo esc_html($box->box_name); ?></td>
                            <td><?php echo esc_html($box->box_number); ?></td>
                            <td>$<?php echo number_format($box->price_per_hour, 0, ',', '.'); ?></td>
                            <td><?php echo number_format($box->total_rentals); ?></td>
                            <td><?php echo number_format($box->total_hours ?: 0, 1); ?>h</td>
                            <td><strong>$<?php echo number_format($box->total_income ?: 0, 0, ',', '.'); ?></strong></td>
                        </tr>
                    <?php 
                        endforeach;
                    else: 
                    ?>
                        <tr class="no-data"><td colspan="6">No hay boxes activos</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Users Table -->
    <div class="mas-section-card">
        <div class="mas-section-header">
            <h2>Usuarios del Sistema</h2>
            <button class="mas-export-btn" onclick="exportAllData('users')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path d="M19 12v7H5v-7H3v7c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-7h-2zm-6 .67l2.59-2.58L17 11.5l-5 5-5-5 1.41-1.41L11 12.67V3h2z" fill="currentColor"/>
                </svg>
                Excel
            </button>
        </div>
        <div class="mas-pagination-controls">
            <div class="mas-per-page">
                <label>Mostrar:</label>
                <select onchange="changePerPage('users', this.value)">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span>registros</span>
            </div>
            <div class="mas-pagination-nav" id="users-pagination"></div>
        </div>
        <div class="mas-table-container">
            <table class="mas-data-table" id="users-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Registro</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $users = $wpdb->get_results(
                        "SELECT u.ID, u.user_login, u.user_email, u.user_registered,
                        GROUP_CONCAT(DISTINCT m.meta_value SEPARATOR ', ') as roles
                        FROM $users_table u
                        LEFT JOIN {$wpdb->prefix}usermeta m ON u.ID = m.user_id AND m.meta_key = '{$wpdb->prefix}capabilities'
                        GROUP BY u.ID
                        ORDER BY u.user_registered DESC"
                    );
                    
                    if ($users):
                        foreach ($users as $user):
                            // Extract role from serialized data
                            $roles_data = maybe_unserialize($user->roles);
                            $role_names = is_array($roles_data) ? array_keys($roles_data) : array();
                            $role_display = !empty($role_names) ? implode(', ', $role_names) : 'N/A';
                    ?>
                        <tr>
                            <td><?php echo $user->ID; ?></td>
                            <td><?php echo esc_html($user->user_login); ?></td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td><?php echo esc_html($role_display); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($user->user_registered)); ?></td>
                        </tr>
                    <?php 
                        endforeach;
                    else: 
                    ?>
                        <tr class="no-data"><td colspan="5">No hay usuarios registrados</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<style>
/* Modern Dashboard Styles */
.mas-reports-wrap {
    max-width: 1400px;
    margin: 20px auto;
    padding: 0 20px;
}

.mas-reports-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.mas-reports-header h1 {
    font-size: 32px;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.mas-header-actions {
    display: flex;
    gap: 10px;
}

/* Filter Card */
.mas-filter-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.mas-filter-form {
    display: flex;
    align-items: flex-end;
    gap: 15px;
    flex-wrap: wrap;
}

.mas-filter-inputs {
    display: flex;
    gap: 15px;
    flex: 1;
    flex-wrap: wrap;
    align-items: flex-end;
}

.mas-input-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.mas-input-group label {
    font-size: 14px;
    font-weight: 600;
    color: #475569;
}

.mas-input-group input {
    padding: 10px 14px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
}

.mas-input-group input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.mas-filter-actions {
    display: flex;
    gap: 10px;
}

/* Hero Income Card */
.mas-hero-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    padding: 40px;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    gap: 30px;
    box-shadow: 0 20px 50px rgba(102, 126, 234, 0.25);
    color: white;
}

.mas-hero-icon {
    flex-shrink: 0;
    width: 80px;
    height: 80px;
    background: rgba(255,255,255,0.15);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10px);
}

.mas-hero-icon svg {
    color: white;
}

.mas-hero-content h2 {
    margin: 0 0 10px 0;
    font-size: 18px;
    font-weight: 600;
    opacity: 0.95;
}

.mas-hero-amount {
    font-size: 56px;
    font-weight: 800;
    margin: 0;
    line-height: 1;
    letter-spacing: -1px;
}

.mas-hero-period {
    margin: 15px 0 0 0;
    font-size: 16px;
    opacity: 0.9;
}

/* KPI Grid */
.mas-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.mas-kpi-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border-left: 4px solid;
}

.mas-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

.mas-kpi-icon {
    flex-shrink: 0;
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: currentColor;
    color: white;
    opacity: 0.9;
}

.mas-kpi-content {
    flex: 1;
}

.mas-kpi-label {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.mas-kpi-value {
    font-size: 32px;
    font-weight: 800;
    color: #1e293b;
    line-height: 1;
}

/* KPI Colors */
.mas-kpi-blue { border-color: #3b82f6; }
.mas-kpi-blue .mas-kpi-icon { background: #3b82f6; }

.mas-kpi-green { border-color: #10b981; }
.mas-kpi-green .mas-kpi-icon { background: #10b981; }

.mas-kpi-teal { border-color: #14b8a6; }
.mas-kpi-teal .mas-kpi-icon { background: #14b8a6; }

.mas-kpi-purple { border-color: #8b5cf6; }
.mas-kpi-purple .mas-kpi-icon { background: #8b5cf6; }

.mas-kpi-orange { border-color: #f59e0b; }
.mas-kpi-orange .mas-kpi-icon { background: #f59e0b; }

.mas-kpi-success { border-color: #059669; }
.mas-kpi-success .mas-kpi-icon { background: #059669; }

.mas-kpi-warning { border-color: #eab308; }
.mas-kpi-warning .mas-kpi-icon { background: #eab308; }

.mas-kpi-pending { border-color: #6366f1; }
.mas-kpi-pending .mas-kpi-icon { background: #6366f1; }

.mas-kpi-danger { border-color: #ef4444; }
.mas-kpi-danger .mas-kpi-icon { background: #ef4444; }

.mas-kpi-indigo { border-color: #6366f1; }
.mas-kpi-indigo .mas-kpi-icon { background: #6366f1; }

.mas-kpi-cyan { border-color: #06b6d4; }
.mas-kpi-cyan .mas-kpi-icon { background: #06b6d4; }

/* Section Card */
.mas-section-card {
    background: white;
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.mas-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.mas-section-header h2 {
    font-size: 22px;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.mas-export-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    background: #10b981;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.mas-export-btn:hover {
    background: #059669;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

/* Payment Grid */
.mas-payment-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
}

.mas-payment-card {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    transition: all 0.3s ease;
    border: 2px solid #e2e8f0;
}

.mas-payment-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.1);
    border-color: #3b82f6;
}

.mas-payment-icon {
    margin-bottom: 15px;
    color: #3b82f6;
}

.mas-payment-card h3 {
    margin: 10px 0;
    font-size: 16px;
    font-weight: 700;
    color: #1e293b;
}

.mas-payment-count {
    font-size: 14px;
    color: #64748b;
    margin: 8px 0;
}

.mas-payment-amount {
    font-size: 28px;
    font-weight: 800;
    color: #3b82f6;
    margin: 12px 0 0 0;
}

/* Data Table */
.mas-table-container {
    overflow-x: auto;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.mas-data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.mas-data-table thead {
    background: #f8fafc;
}

.mas-data-table th {
    padding: 14px 16px;
    text-align: left;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e2e8f0;
}

.mas-data-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #e2e8f0;
    color: #1e293b;
}

.mas-data-table tbody tr:hover {
    background: #f8fafc;
}

.mas-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.mas-badge-pending {
    background: #fef3c7;
    color: #92400e;
}

.mas-badge-scheduled {
    background: #dbeafe;
    color: #1e40af;
}

.mas-badge-confirmed {
    background: #d1fae5;
    color: #065f46;
}

.mas-badge-cancelled {
    background: #fee2e2;
    color: #991b1b;
}

.mas-badge-paid {
    background: #d1fae5;
    color: #065f46;
}

/* Pagination Controls */
.mas-pagination-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding: 12px 16px;
    background: #f8fafc;
    border-radius: 8px;
    flex-wrap: wrap;
    gap: 15px;
}

.mas-per-page {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #475569;
}

.mas-per-page label {
    font-weight: 600;
}

.mas-per-page select {
    padding: 6px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    background: white;
}

.mas-per-page select:focus {
    outline: none;
    border-color: #3b82f6;
}

.mas-pagination-nav {
    display: flex;
    align-items: center;
    gap: 8px;
}

.mas-pagination-nav button {
    padding: 8px 14px;
    border: 2px solid #e2e8f0;
    background: white;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    color: #475569;
}

.mas-pagination-nav button:hover:not(:disabled) {
    border-color: #3b82f6;
    background: #eff6ff;
    color: #3b82f6;
}

.mas-pagination-nav button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.mas-pagination-nav .mas-page-info {
    padding: 8px 16px;
    background: #3b82f6;
    color: white;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
}

.mas-pagination-info {
    margin-top: 15px;
    padding: 12px;
    background: #eff6ff;
    border-radius: 8px;
    color: #1e40af;
    font-size: 14px;
    text-align: center;
}

/* Responsive */
@media (max-width: 768px) {
    .mas-reports-wrap {
        padding: 0 15px;
    }
    
    .mas-hero-card {
        flex-direction: column;
        text-align: center;
        padding: 30px 20px;
    }
    
    .mas-hero-amount {
        font-size: 42px;
    }
    
    .mas-kpi-grid {
        grid-template-columns: 1fr;
    }
    
    .mas-filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .mas-filter-inputs {
        flex-direction: column;
    }
    
    .mas-filter-actions {
        width: 100%;
    }
    
    .mas-filter-actions button,
    .mas-filter-actions a {
        flex: 1;
    }
    
    .mas-section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .mas-payment-grid {
        grid-template-columns: 1fr;
    }
}

@media print {
    .mas-export-btn,
    .mas-header-actions,
    .mas-filter-card {
        display: none !important;
    }
}
</style>

<script>
// Pagination state for each table
const paginationState = {
    appointments: { currentPage: 1, perPage: 25, allRows: [] },
    rentals: { currentPage: 1, perPage: 25, allRows: [] },
    professionals: { currentPage: 1, perPage: 25, allRows: [] },
    services: { currentPage: 1, perPage: 25, allRows: [] },
    boxes: { currentPage: 1, perPage: 25, allRows: [] },
    users: { currentPage: 1, perPage: 25, allRows: [] }
};

// Table IDs mapping
const tableIds = {
    appointments: 'appointments-table',
    rentals: 'rentals-table',
    professionals: 'professionals-table',
    services: 'services-table',
    boxes: 'boxes-table',
    users: 'users-table'
};

// File names for export
const exportNames = {
    appointments: 'citas',
    rentals: 'arriendos',
    professionals: 'profesionales',
    services: 'servicios',
    boxes: 'boxes',
    users: 'usuarios'
};

// Initialize pagination for all tables
document.addEventListener('DOMContentLoaded', function() {
    Object.keys(paginationState).forEach(tableKey => {
        initializePagination(tableKey);
    });
});

function initializePagination(tableKey) {
    const table = document.getElementById(tableIds[tableKey]);
    if (!table) return;
    
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr:not(.no-data)'));
    
    // Store all rows
    paginationState[tableKey].allRows = rows;
    
    // Apply initial pagination
    applyPagination(tableKey);
}

function applyPagination(tableKey) {
    const state = paginationState[tableKey];
    const table = document.getElementById(tableIds[tableKey]);
    if (!table || state.allRows.length === 0) return;
    
    const tbody = table.querySelector('tbody');
    const totalRows = state.allRows.length;
    const totalPages = Math.ceil(totalRows / state.perPage);
    
    // Ensure current page is valid
    if (state.currentPage > totalPages) state.currentPage = totalPages;
    if (state.currentPage < 1) state.currentPage = 1;
    
    const startIndex = (state.currentPage - 1) * state.perPage;
    const endIndex = Math.min(startIndex + state.perPage, totalRows);
    
    // Clear tbody and add only visible rows
    tbody.innerHTML = '';
    for (let i = startIndex; i < endIndex; i++) {
        tbody.appendChild(state.allRows[i].cloneNode(true));
    }
    
    // Update pagination controls
    updatePaginationControls(tableKey, totalRows, totalPages);
}

function updatePaginationControls(tableKey, totalRows, totalPages) {
    const paginationNav = document.getElementById(tableKey + '-pagination');
    if (!paginationNav) return;
    
    const state = paginationState[tableKey];
    const startRecord = ((state.currentPage - 1) * state.perPage) + 1;
    const endRecord = Math.min(state.currentPage * state.perPage, totalRows);
    
    paginationNav.innerHTML = `
        <button onclick="goToPage('${tableKey}', 1)" ${state.currentPage === 1 ? 'disabled' : ''}>
            Primera
        </button>
        <button onclick="goToPage('${tableKey}', ${state.currentPage - 1})" ${state.currentPage === 1 ? 'disabled' : ''}>
            Anterior
        </button>
        <span class="mas-page-info">
            ${startRecord}-${endRecord} de ${totalRows} | Pag. ${state.currentPage} de ${totalPages}
        </span>
        <button onclick="goToPage('${tableKey}', ${state.currentPage + 1})" ${state.currentPage >= totalPages ? 'disabled' : ''}>
            Siguiente
        </button>
        <button onclick="goToPage('${tableKey}', ${totalPages})" ${state.currentPage >= totalPages ? 'disabled' : ''}>
            Ultima
        </button>
    `;
}

function goToPage(tableKey, page) {
    paginationState[tableKey].currentPage = page;
    applyPagination(tableKey);
}

function changePerPage(tableKey, value) {
    paginationState[tableKey].perPage = parseInt(value);
    paginationState[tableKey].currentPage = 1;
    applyPagination(tableKey);
}

// Export ALL data (not just visible rows)
function exportAllData(tableKey) {
    const state = paginationState[tableKey];
    const table = document.getElementById(tableIds[tableKey]);
    if (!table) return;
    
    // Generate CSV with UTF-8 BOM for proper encoding in Excel
    let csv = '\uFEFF'; // UTF-8 BOM
    
    // Add header row
    const headerRow = table.querySelector('thead tr');
    if (headerRow) {
        const headerCols = headerRow.querySelectorAll('th');
        const headerData = [];
        headerCols.forEach(col => {
            let text = col.textContent.trim().replace(/\s+/g, ' ');
            text = text.replace(/"/g, '""');
            if (text.includes(';') || text.includes('\n') || text.includes('"')) {
                text = '"' + text + '"';
            }
            headerData.push(text);
        });
        csv += headerData.join(';') + '\n';
    }
    
    // Add ALL data rows (from stored allRows, not just visible)
    state.allRows.forEach(row => {
        const cols = row.querySelectorAll('td');
        const rowData = [];
        cols.forEach(col => {
            let text = col.textContent.trim().replace(/\s+/g, ' ');
            text = text.replace(/"/g, '""');
            if (text.includes(';') || text.includes('\n') || text.includes('"')) {
                text = '"' + text + '"';
            }
            rowData.push(text);
        });
        csv += rowData.join(';') + '\n';
    });
    
    // Create blob with UTF-8 encoding
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = exportNames[tableKey] + '_' + new Date().toISOString().slice(0,10) + '.csv';
    link.click();
    URL.revokeObjectURL(url);
}

// Legacy function for tables without pagination (like payment methods)
function exportTableToExcel(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = '\uFEFF';
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        cols.forEach(col => {
            let text = col.textContent.trim().replace(/\s+/g, ' ');
            text = text.replace(/"/g, '""');
            if (text.includes(';') || text.includes('\n') || text.includes('"')) {
                text = '"' + text + '"';
            }
            rowData.push(text);
        });
        csv += rowData.join(';') + '\n';
    });
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename + '_' + new Date().toISOString().slice(0,10) + '.csv';
    link.click();
    URL.revokeObjectURL(url);
}

// Alias for backwards compatibility
function exportToExcel(tableId, filename) {
    exportTableToExcel(tableId, filename);
}
</script>
