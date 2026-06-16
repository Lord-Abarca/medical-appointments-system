<?php
/**
 * Manejadores AJAX adicionales para el admin
 */

if (!defined('ABSPATH')) {
    exit;
}

// Actualizar estado de cita
add_action('wp_ajax_mas_update_appointment_status', 'mas_ajax_update_appointment_status');
function mas_ajax_update_appointment_status() {
    check_ajax_referer('mas_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permisos insuficientes', 'medical-appointments')));
    }
    
    $appointment_id = intval($_POST['appointment_id']);
    $status = sanitize_text_field($_POST['status']);
    
    $mas_appointments = new MAS_Appointments();
    $result = $mas_appointments->update_appointment($appointment_id, array('status' => $status));
    
    if ($result) {
        wp_send_json_success(array('message' => __('Estado actualizado', 'medical-appointments')));
    } else {
        wp_send_json_error(array('message' => __('Error al actualizar', 'medical-appointments')));
    }
}

// Eliminar cita
add_action('wp_ajax_mas_delete_appointment', 'mas_ajax_delete_appointment');
function mas_ajax_delete_appointment() {
    check_ajax_referer('mas_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permisos insuficientes', 'medical-appointments')));
    }
    
    $id = intval($_POST['id']);
    
    $mas_appointments = new MAS_Appointments();
    $result = $mas_appointments->delete_appointment($id);
    
    if ($result) {
        wp_send_json_success(array('message' => __('Cita eliminada', 'medical-appointments')));
    } else {
        wp_send_json_error(array('message' => __('Error al eliminar', 'medical-appointments')));
    }
}

// Eliminar box
add_action('wp_ajax_mas_delete_box', 'mas_ajax_delete_box');
function mas_ajax_delete_box() {
    check_ajax_referer('mas_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permisos insuficientes', 'medical-appointments')));
    }
    
    $id = intval($_POST['id']);
    
    $mas_boxes = new MAS_Boxes();
    $result = $mas_boxes->delete_box($id);
    
    if ($result) {
        wp_send_json_success(array('message' => __('Box eliminado', 'medical-appointments')));
    } else {
        wp_send_json_error(array('message' => __('Error al eliminar', 'medical-appointments')));
    }
}

// Eliminar arriendo
add_action('wp_ajax_mas_delete_rental', 'mas_ajax_delete_rental');
function mas_ajax_delete_rental() {
    check_ajax_referer('mas_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permisos insuficientes', 'medical-appointments')));
    }
    
    $id = intval($_POST['id']);
    
    $mas_rentals = new MAS_Rentals();
    $result = $mas_rentals->delete_rental($id);
    
    if ($result) {
        wp_send_json_success(array('message' => __('Arriendo eliminado', 'medical-appointments')));
    } else {
        wp_send_json_error(array('message' => __('Error al eliminar', 'medical-appointments')));
    }
}

// Eliminar profesional
add_action('wp_ajax_mas_delete_professional', 'mas_ajax_delete_professional');
function mas_ajax_delete_professional() {
    check_ajax_referer('mas_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permisos insuficientes', 'medical-appointments')));
    }
    
    $id = intval($_POST['id']);
    
    $mas_professionals = new MAS_Professionals();
    $result = $mas_professionals->delete_professional($id);
    
    if ($result) {
        wp_send_json_success(array('message' => __('Profesional eliminado', 'medical-appointments')));
    } else {
        wp_send_json_error(array('message' => __('Error al eliminar', 'medical-appointments')));
    }
}

add_action('wp_ajax_mas_toggle_professional_status', 'mas_ajax_toggle_professional_status');
function mas_ajax_toggle_professional_status() {
    check_ajax_referer('mas_toggle_professional_status', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Permisos insuficientes', 'medical-appointments'));
    }
    
    $professional_id = intval($_POST['professional_id']);
    
    if (!$professional_id) {
        wp_send_json_error(__('ID de profesional inválido', 'medical-appointments'));
    }
    
    $mas_professionals = new MAS_Professionals();
    $professional = $mas_professionals->get_professional($professional_id);
    
    if (!$professional) {
        wp_send_json_error(__('Profesional no encontrado', 'medical-appointments'));
    }
    
    $new_status = $professional->status === 'active' ? 'inactive' : 'active';
    
    $result = $mas_professionals->update_professional($professional_id, array('status' => $new_status));
    
    if ($result !== false) {
        wp_send_json_success(array(
            'message' => __('Estado actualizado exitosamente', 'medical-appointments'),
            'new_status' => $new_status
        ));
    } else {
        wp_send_json_error(__('Error al actualizar el estado', 'medical-appointments'));
    }
}

// Obtener estadísticas del dashboard
add_action('wp_ajax_mas_get_dashboard_stats', 'mas_ajax_get_dashboard_stats');
function mas_ajax_get_dashboard_stats() {
    check_ajax_referer('mas_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permisos insuficientes', 'medical-appointments')));
    }
    
    $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : date('Y-m-01');
    $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : date('Y-m-t');
    
    $mas_appointments = new MAS_Appointments();
    $mas_rentals = new MAS_Rentals();
    
    $appointment_stats = $mas_appointments->get_statistics($date_from, $date_to);
    $rental_stats = $mas_rentals->get_statistics($date_from, $date_to);
    
    wp_send_json_success(array(
        'appointments' => $appointment_stats,
        'rentals' => $rental_stats
    ));
}

// Agregar endpoints de datos del calendario para citas
add_action('wp_ajax_mas_get_appointments_calendar', 'mas_ajax_get_appointments_calendar');
function mas_ajax_get_appointments_calendar() {
    check_ajax_referer('mas_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permisos insuficientes', 'medical-appointments')));
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'mas_appointments';
    
    $start = sanitize_text_field($_POST['start']);
    $end = sanitize_text_field($_POST['end']);
    
    $appointments = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name 
        WHERE appointment_date BETWEEN %s AND %s 
        ORDER BY appointment_date, appointment_time",
        $start, $end
    ));
    
    $events = array();
    foreach ($appointments as $appointment) {
        $status_labels = array(
            'pending' => __('Pendiente', 'medical-appointments'),
            'scheduled' => __('Agendada', 'medical-appointments'),
            'cancelled' => __('Cancelada', 'medical-appointments'),
            'completed' => __('Completada', 'medical-appointments'),
            'interno' => __('Interno', 'medical-appointments')
        );
        
        $colors = array(
            'pending' => array('bg' => '#9b59b6', 'border' => '#8e44ad'),
            'scheduled' => array('bg' => '#27ae60', 'border' => '#229954'),
            'cancelled' => array('bg' => '#e74c3c', 'border' => '#c0392b'),
            'completed' => array('bg' => '#3498db', 'border' => '#2980b9'),
            'interno' => array('bg' => '#f39c12', 'border' => '#e67e22')
        );
        
        $color = $colors[$appointment->status] ?? $colors['pending'];
        
        $timestamp = strtotime($appointment->appointment_date);
        $formatted_date = date_i18n('l, j', $timestamp) . ' de ' . date_i18n('F', $timestamp) . ' de ' . date_i18n('Y', $timestamp);
        
        $events[] = array(
            'id' => $appointment->id,
            'title' => $appointment->patient_name,
            'start' => $appointment->appointment_date . 'T' . $appointment->appointment_time,
            'end' => date('Y-m-d\TH:i:s', strtotime($appointment->appointment_date . ' ' . $appointment->appointment_time . ' +' . $appointment->duration . ' minutes')),
            'backgroundColor' => $color['bg'],
            'borderColor' => $color['border'],
            'extendedProps' => array(
                'patient_name' => $appointment->patient_name,
                'patient_email' => $appointment->patient_email,
                'patient_phone' => $appointment->patient_phone,
                'appointment_time' => date('H:i', strtotime($appointment->appointment_time)),
                'formatted_date' => $formatted_date,
                'status' => $appointment->status,
                'status_label' => $status_labels[$appointment->status] ?? $appointment->status,
                'notes' => $appointment->notes,
                'edit_url' => admin_url('admin.php?page=medical-appointments-list&action=edit&id=' . $appointment->id)
            )
        );
    }
    
    wp_send_json_success($events);
}

// Agregar endpoints de datos del calendario para arriendos
add_action('wp_ajax_mas_get_rentals_calendar', 'mas_ajax_get_rentals_calendar');
function mas_ajax_get_rentals_calendar() {
    check_ajax_referer('mas_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permisos insuficientes', 'medical-appointments')));
    }
    
    global $wpdb;
    $rentals_table = $wpdb->prefix . 'mas_box_rentals';
    $boxes_table = $wpdb->prefix . 'mas_boxes';
    $professionals_table = $wpdb->prefix . 'mas_professionals';
    $users_table = $wpdb->prefix . 'users';
    
    $start = sanitize_text_field($_POST['start']);
    $end = sanitize_text_field($_POST['end']);
    
    $rentals = $wpdb->get_results($wpdb->prepare(
        "SELECT r.*, b.box_name, b.box_number, 
                u.display_name as professional_name
        FROM $rentals_table r
        LEFT JOIN $boxes_table b ON r.box_id = b.id
        LEFT JOIN $professionals_table p ON r.professional_id = p.id
        LEFT JOIN $users_table u ON p.user_id = u.ID
        WHERE r.rental_date BETWEEN %s AND %s 
        ORDER BY r.rental_date, r.start_time",
        $start, $end
    ));
    
    $events = array();
    foreach ($rentals as $rental) {
        $status_labels = array(
            'pending' => __('Pendiente', 'medical-appointments'),
            'active' => __('Activo', 'medical-appointments'),
            'cancelled' => __('Cancelado', 'medical-appointments'),
            'completed' => __('Completado', 'medical-appointments'),
            'interno' => __('Interno', 'medical-appointments')
        );
        
        $payment_labels = array(
            'pending' => __('Pendiente', 'medical-appointments'),
            'approved' => __('Aprobado', 'medical-appointments'),
            'paid' => __('Pagado', 'medical-appointments'),
            'rejected' => __('Rechazado', 'medical-appointments'),
            'failed' => __('Fallido', 'medical-appointments'),
            'interno' => __('Interno', 'medical-appointments')
        );
        
        $colors = array(
            'pending' => array('bg' => '#9b59b6', 'border' => '#8e44ad'),
            'active' => array('bg' => '#27ae60', 'border' => '#229954'),
            'cancelled' => array('bg' => '#e74c3c', 'border' => '#c0392b'),
            'completed' => array('bg' => '#3498db', 'border' => '#2980b9'),
            'interno' => array('bg' => '#f39c12', 'border' => '#e67e22')
        );
        
        $color = $colors[$rental->status] ?? $colors['pending'];
        
        $events[] = array(
            'id' => $rental->id,
            'title' => ($rental->box_name ?? 'Box') . ' - ' . ($rental->professional_name ?? 'Profesional'),
            'start' => $rental->rental_date . 'T' . $rental->start_time,
            'end' => $rental->rental_date . 'T' . $rental->end_time,
            'backgroundColor' => $color['bg'],
            'borderColor' => $color['border'],
            'extendedProps' => array(
                'box_name' => $rental->box_name . ' (' . $rental->box_number . ')',
                'professional_name' => $rental->professional_name,
                'start_time' => date('H:i', strtotime($rental->start_time)),
                'end_time' => date('H:i', strtotime($rental->end_time)),
                'total_hours' => $rental->total_hours,
                'formatted_price' => number_format($rental->total_price, 0, ',', '.'),
                'formatted_date' => date_i18n('l, j', strtotime($rental->rental_date)) . ' de ' . date_i18n('F', strtotime($rental->rental_date)) . ' de ' . date_i18n('Y', strtotime($rental->rental_date)),
                'status' => $rental->status,
                'status_label' => $status_labels[$rental->status] ?? $rental->status,
                'payment_status' => $rental->payment_status,
                'payment_status_label' => $payment_labels[$rental->payment_status] ?? $rental->payment_status,
                'notes' => $rental->notes,
                'edit_url' => admin_url('admin.php?page=medical-rentals&action=edit&id=' . $rental->id)
            )
        );
    }
    
    wp_send_json_success($events);
}

// Función para validar RUT chileno
// La función ya existe en medical-appointments-system.php

if (!function_exists('mas_ajax_get_available_slots')) {
    add_action('wp_ajax_mas_get_available_slots', 'mas_ajax_get_available_slots');
    add_action('wp_ajax_nopriv_mas_get_available_slots', 'mas_ajax_get_available_slots');
    
    function mas_ajax_get_available_slots() {
        $nonce_valid = false;
        if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'mas_admin_nonce')) {
            $nonce_valid = true;
        } elseif (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'mas_public_nonce')) {
            $nonce_valid = true;
        }
        
        if (!$nonce_valid) {
            wp_send_json_error(array('message' => 'Nonce inválido'));
            return;
        }
        
        $date = sanitize_text_field($_POST['date']);
        
        if (empty($date)) {
            wp_send_json_error(array('message' => 'Fecha requerida'));
            return;
        }
        
        $mas_appointments = new MAS_Appointments();
        $slots = $mas_appointments->get_available_slots($date);
        
        if (empty($slots)) {
            wp_send_json_success(array('slots' => array(), 'message' => 'No hay horarios disponibles para esta fecha'));
        } else {
            wp_send_json_success(array('slots' => $slots));
        }
    }
}

?>
