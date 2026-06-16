<?php
/**
 * Export and Backup Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAS_Export {
    
    /**
     * Export appointments to CSV/Excel
     */
    public static function export_appointments_csv($filters = array()) {
        global $wpdb;
        $appointments_table = $wpdb->prefix . 'mas_appointments';
        $professionals_table = $wpdb->prefix . 'mas_professionals';
        $services_table = $wpdb->prefix . 'mas_services';
        
        $query = "SELECT 
                    a.id,
                    a.patient_name,
                    a.patient_email,
                    a.patient_phone,
                    a.patient_rut,
                    a.appointment_date,
                    a.appointment_time,
                    a.duration,
                    a.status,
                    a.payment_status,
                    a.payment_method,
                    a.amount_paid,
                    a.mp_payment_id,
                    a.notes,
                    a.created_at,
                    p.name as professional_name,
                    p.specialty,
                    s.name as service_name
                  FROM $appointments_table a
                  LEFT JOIN $professionals_table p ON a.professional_id = p.id
                  LEFT JOIN $services_table s ON a.service_id = s.id
                  WHERE 1=1";
        
        if (!empty($filters['start_date'])) {
            $query .= $wpdb->prepare(" AND a.appointment_date >= %s", $filters['start_date']);
        }
        
        if (!empty($filters['end_date'])) {
            $query .= $wpdb->prepare(" AND a.appointment_date <= %s", $filters['end_date']);
        }
        
        if (!empty($filters['status'])) {
            $query .= $wpdb->prepare(" AND a.status = %s", $filters['status']);
        }
        
        $query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
        
        $appointments = $wpdb->get_results($query, ARRAY_A);
        
        if (empty($appointments)) {
            wp_die('No hay datos para exportar en el rango de fechas seleccionado.');
        }
        
        $filename = 'citas_medicas_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        // UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, array(
            'ID',
            'Nombre Paciente',
            'Email',
            'Teléfono',
            'RUT',
            'Fecha',
            'Hora',
            'Duración (min)',
            'Profesional',
            'Especialidad',
            'Servicio',
            'Estado Cita',
            'Estado Pago',
            'Método Pago',
            'Monto Pagado',
            'ID Pago MP',
            'Notas',
            'Fecha Creación'
        ));
        
        // Data
        foreach ($appointments as $appointment) {
            fputcsv($output, array(
                $appointment['id'],
                $appointment['patient_name'],
                $appointment['patient_email'],
                $appointment['patient_phone'],
                $appointment['patient_rut'],
                $appointment['appointment_date'],
                $appointment['appointment_time'],
                $appointment['duration'],
                $appointment['professional_name'] ?: 'No asignado',
                $appointment['specialty'] ?: '-',
                $appointment['service_name'] ?: '-',
                $appointment['status'],
                $appointment['payment_status'],
                $appointment['payment_method'] ?: '-',
                $appointment['amount_paid'] ?: '0',
                $appointment['mp_payment_id'] ?: '-',
                $appointment['notes'],
                $appointment['created_at']
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export rentals to CSV/Excel
     */
    public static function export_rentals_csv($filters = array()) {
        global $wpdb;
        $rentals_table = $wpdb->prefix . 'mas_box_rentals';
        $boxes_table = $wpdb->prefix . 'mas_boxes';
        $professionals_table = $wpdb->prefix . 'mas_professionals';
        
        $query = "SELECT 
                    r.id,
                    r.start_date,
                    r.end_date,
                    r.start_time,
                    r.end_time,
                    r.total_hours,
                    r.total_price,
                    r.status,
                    r.payment_status,
                    r.payment_method,
                    r.mp_payment_id,
                    r.notes,
                    r.created_at,
                    b.box_name,
                    b.hourly_rate,
                    p.name as professional_name,
                    p.email as professional_email
                  FROM $rentals_table r
                  LEFT JOIN $boxes_table b ON r.box_id = b.id
                  LEFT JOIN $professionals_table p ON r.professional_id = p.id
                  WHERE 1=1";
        
        if (!empty($filters['start_date'])) {
            $query .= $wpdb->prepare(" AND r.start_date >= %s", $filters['start_date']);
        }
        
        if (!empty($filters['end_date'])) {
            $query .= $wpdb->prepare(" AND r.start_date <= %s", $filters['end_date']);
        }
        
        $query .= " ORDER BY r.start_date DESC, r.start_time DESC";
        
        $rentals = $wpdb->get_results($query, ARRAY_A);
        
        if (empty($rentals)) {
            wp_die('No hay datos de arriendos para exportar en el rango de fechas seleccionado.');
        }
        
        $filename = 'arriendos_boxes_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, array(
            'ID',
            'Box',
            'Tarifa por Hora',
            'Profesional',
            'Email Profesional',
            'Fecha Inicio',
            'Fecha Fin',
            'Hora Inicio',
            'Hora Fin',
            'Total Horas',
            'Precio Total',
            'Estado',
            'Estado Pago',
            'Método Pago',
            'ID Pago MP',
            'Notas',
            'Fecha Creación'
        ));
        
        foreach ($rentals as $rental) {
            fputcsv($output, array(
                $rental['id'],
                $rental['box_name'] ?: '-',
                $rental['hourly_rate'] ?: '0',
                $rental['professional_name'] ?: '-',
                $rental['professional_email'] ?: '-',
                $rental['start_date'],
                $rental['end_date'],
                $rental['start_time'],
                $rental['end_time'],
                $rental['total_hours'],
                $rental['total_price'],
                $rental['status'],
                $rental['payment_status'],
                $rental['payment_method'] ?: '-',
                $rental['mp_payment_id'] ?: '-',
                $rental['notes'],
                $rental['created_at']
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export full report with all data
     */
    public static function export_full_report($filters = array()) {
        global $wpdb;
        
        $start_date = $filters['start_date'] ?? date('Y-m-01');
        $end_date = $filters['end_date'] ?? date('Y-m-t');
        
        $filename = 'reporte_completo_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Summary section
        fputcsv($output, array('REPORTE COMPLETO DEL SISTEMA'));
        fputcsv($output, array('Período:', date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date))));
        fputcsv($output, array(''));
        
        // Appointments summary
        $appointments_table = $wpdb->prefix . 'mas_appointments';
        $total_appointments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $appointments_table WHERE appointment_date BETWEEN %s AND %s",
            $start_date, $end_date
        ));
        
        $total_appointments_income = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount_paid) FROM $appointments_table 
            WHERE payment_status = 'paid' AND appointment_date BETWEEN %s AND %s",
            $start_date, $end_date
        ));
        
        fputcsv($output, array('RESUMEN DE CITAS MÉDICAS'));
        fputcsv($output, array('Total de Citas:', $total_appointments));
        fputcsv($output, array('Ingresos por Citas:', '$' . number_format($total_appointments_income ?: 0, 0, ',', '.')));
        fputcsv($output, array(''));
        
        // Rentals summary
        $rentals_table = $wpdb->prefix . 'mas_box_rentals';
        $total_rentals = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $rentals_table WHERE start_date BETWEEN %s AND %s",
            $start_date, $end_date
        ));
        
        $total_rental_income = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total_price) FROM $rentals_table 
            WHERE payment_status = 'paid' AND start_date BETWEEN %s AND %s",
            $start_date, $end_date
        ));
        
        fputcsv($output, array('RESUMEN DE ARRIENDOS'));
        fputcsv($output, array('Total de Arriendos:', $total_rentals));
        fputcsv($output, array('Ingresos por Arriendos:', '$' . number_format($total_rental_income ?: 0, 0, ',', '.')));
        fputcsv($output, array(''));
        
        $total_income = ($total_appointments_income ?: 0) + ($total_rental_income ?: 0);
        fputcsv($output, array('TOTAL INGRESOS:', '$' . number_format($total_income, 0, ',', '.')));
        fputcsv($output, array(''));
        fputcsv($output, array(''));
        
        fclose($output);
        exit;
    }
    
    /**
     * Backup database tables
     */
    public static function backup_database() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'mas_appointments',
            $wpdb->prefix . 'mas_boxes',
            $wpdb->prefix . 'mas_box_rentals',
            $wpdb->prefix . 'mas_professionals',
            $wpdb->prefix . 'mas_services'
        );
        
        $backup_data = array();
        
        foreach ($tables as $table) {
            $data = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
            $backup_data[$table] = $data;
        }
        
        $filename = 'backup_mas_' . date('Y-m-d_H-i-s') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        echo json_encode($backup_data, JSON_PRETTY_PRINT);
        exit;
    }
}
