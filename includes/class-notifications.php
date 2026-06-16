<?php
/**
 * Notifications and Email Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAS_Notifications {
    
    /**
     * Send appointment confirmation email
     */
    public static function send_appointment_confirmation($appointment_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'mas_appointments';
        
        $appointment = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, p.id as professional_id, p.user_id as professional_user_id, 
             u.display_name as professional_name, u.user_email as professional_email,
             s.name as service_name
             FROM $table a
             LEFT JOIN {$wpdb->prefix}mas_professionals p ON a.professional_id = p.id
             LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
             LEFT JOIN {$wpdb->prefix}mas_services s ON a.service_id = s.id
             WHERE a.id = %d",
            $appointment_id
        ));
        
        if (!$appointment) {
            return false;
        }
        
        $settings = get_option('mas_settings', array());
        
        if (empty($settings['enable_notifications'])) {
            return false;
        }
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        // 1. Enviar email al paciente
        $patient_message = self::get_email_template('appointment_scheduled', array(
            'patient_name' => $appointment->patient_name,
            'date' => date('d/m/Y', strtotime($appointment->appointment_date)),
            'time' => date('H:i', strtotime($appointment->appointment_time)),
            'professional_name' => $appointment->professional_name ?: 'Por asignar',
            'service_name' => $appointment->service_name ?: 'Consulta general',
            'voucher_url' => ''
        ));
        
        $patient_sent = wp_mail($appointment->patient_email, 'Cita Agendada - Confirmación', $patient_message, $headers);
        
        // 2. Enviar email al profesional (si está asignado)
        if ($appointment->professional_email) {
            $professional_message = '
                <html>
                <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 10px 10px 0 0;">
                        <h2 style="color: white; margin: 0; font-size: 24px;">📋 Nueva Cita Agendada</h2>
                    </div>
                    <div style="background: white; padding: 30px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 10px 10px;">
                        <p style="font-size: 16px; color: #374151;">Hola <strong>' . esc_html($appointment->professional_name) . '</strong>,</p>
                        <p style="color: #6b7280;">Tienes una nueva cita agendada en el sistema.</p>
                        
                        <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0;">
                            <h3 style="color: #1f2937; margin: 0 0 15px 0; font-size: 18px;">Detalles de la Cita</h3>
                            <p style="margin: 8px 0; color: #374151;"><strong>👤 Paciente:</strong> ' . esc_html($appointment->patient_name) . '</p>
                            <p style="margin: 8px 0; color: #374151;"><strong>📧 Email:</strong> ' . esc_html($appointment->patient_email) . '</p>
                            <p style="margin: 8px 0; color: #374151;"><strong>📱 Teléfono:</strong> ' . esc_html($appointment->patient_phone) . '</p>
                            <p style="margin: 8px 0; color: #374151;"><strong>📅 Fecha:</strong> ' . date('d/m/Y', strtotime($appointment->appointment_date)) . '</p>
                            <p style="margin: 8px 0; color: #374151;"><strong>🕐 Hora:</strong> ' . date('H:i', strtotime($appointment->appointment_time)) . '</p>
                            <p style="margin: 8px 0; color: #374151;"><strong>🏥 Servicio:</strong> ' . esc_html($appointment->service_name) . '</p>
                        </div>
                        
                        <div style="background: #dcfce7; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #10b981;">
                            <p style="margin: 0; color: #065f46; font-size: 14px;">
                                <strong>✅ Estado:</strong> La cita ha sido confirmada y pagada.
                            </p>
                        </div>
                        
                        <div style="text-align: center; margin: 30px 0;">
                            <a href="' . admin_url('admin.php?page=mas-appointments') . '" 
                               style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                                      color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; 
                                      font-weight: bold; font-size: 16px;">
                                Ver en Panel de Administración
                            </a>
                        </div>
                        
                        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
                        
                        <p style="color: #9ca3af; font-size: 12px; margin: 0;">
                            Este es un correo automático del sistema.
                        </p>
                    </div>
                </body>
                </html>';
            
            wp_mail($appointment->professional_email, 'Nueva Cita Agendada', $professional_message, $headers);
        }
        
        // 3. Enviar notificación al administrador
        $admin_email = get_option('admin_email');
        $admin_message = '
            <html>
            <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 30px; border-radius: 10px 10px 0 0;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <h2 style="color: white; margin: 0; font-size: 24px;">Nueva Cita en el Sistema</h2>
                    </div>
                </div>
                <div style="background: white; padding: 30px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 10px 10px;">
                    <p style="font-size: 16px; color: #374151;">Se ha registrado una nueva cita en el sistema.</p>
                    
                    <div style="background: #fef3c7; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b;">
                        <h3 style="color: #92400e; margin: 0 0 15px 0; font-size: 18px;">
                            👨‍⚕️ ' . esc_html($appointment->professional_name ?: 'Profesional no asignado') . '
                        </h3>
                        <p style="margin: 5px 0; color: #92400e;"><strong>📧 Email:</strong> ' . esc_html($appointment->professional_email ?: 'N/A') . '</p>
                    </div>
                    
                    <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <h3 style="color: #1f2937; margin: 0 0 15px 0; font-size: 18px;">Información de la Cita</h3>
                        <p style="margin: 8px 0; color: #374151;"><strong>👤 Paciente:</strong> ' . esc_html($appointment->patient_name) . '</p>
                        <p style="margin: 8px 0; color: #374151;"><strong>📧 Email Paciente:</strong> ' . esc_html($appointment->patient_email) . '</p>
                        <p style="margin: 8px 0; color: #374151;"><strong>📱 Teléfono:</strong> ' . esc_html($appointment->patient_phone) . '</p>
                        <p style="margin: 8px 0; color: #374151;"><strong>📅 Fecha:</strong> ' . date('d/m/Y', strtotime($appointment->appointment_date)) . '</p>
                        <p style="margin: 8px 0; color: #374151;"><strong>🕐 Hora:</strong> ' . date('H:i', strtotime($appointment->appointment_time)) . '</p>
                        <p style="margin: 8px 0; color: #374151;"><strong>🏥 Servicio:</strong> ' . esc_html($appointment->service_name) . '</p>
                    </div>
                    
                    <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center;">
                        <p style="color: white; font-size: 24px; font-weight: bold; margin: 0;">
                            💰 Monto Total: $' . number_format($appointment->amount_paid ?: 0, 0, ',', '.') . '
                        </p>
                        <p style="color: rgba(255,255,255,0.9); font-size: 14px; margin: 10px 0 0 0;">
                            ID Pago: ' . esc_html($appointment->payment_id ?: 'N/A') . '<br>
                            Método: ' . esc_html(strtoupper($appointment->payment_method ?: 'N/A')) . '
                        </p>
                    </div>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . admin_url('admin.php?page=mas-appointments') . '" 
                           style="display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); 
                                  color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; 
                                  font-weight: bold; font-size: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            Ver Citas en Panel de Administración
                        </a>
                    </div>
                    
                    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
                    
                    <p style="color: #9ca3af; font-size: 12px; margin: 0;">
                        Este es un correo automático del sistema.
                    </p>
                </div>
            </body>
            </html>';
        
        wp_mail($admin_email, 'Nueva Cita Agendada - NovaEspacio', $admin_message, $headers);
        
        return $patient_sent;
    }
    
    /**
     * Send appointment status change notification
     */
    public static function send_status_change_notification($appointment_id, $new_status) {
        global $wpdb;
        $table = $wpdb->prefix . 'mas_appointments';
        
        $appointment = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, s.service_name, p.user_id
            FROM $table a
            LEFT JOIN {$wpdb->prefix}mas_services s ON a.service_id = s.id
            LEFT JOIN {$wpdb->prefix}mas_professionals p ON a.professional_id = p.id
            WHERE a.id = %d",
            $appointment_id
        ));
        
        if (!$appointment) {
            return false;
        }
        
        $professional_name = 'Profesional por Confirmar';
        if ($appointment->user_id) {
            $user = get_userdata($appointment->user_id);
            if ($user) {
                $professional_name = $user->display_name;
            }
        }
        
        $service_name = $appointment->service_name ?? 'Servicio Médico';
        
        $status_labels = array(
            'pending' => 'Pendiente',
            'confirmed' => 'Confirmada',
            'scheduled' => 'Confirmada',
            'cancelled' => 'Cancelada',
            'completed' => 'Completada'
        );
        
        $to = $appointment->patient_email;
        $subject = 'Confirmación de Cita - ' . get_bloginfo('name');
        
        $voucher_url = '';
        if ($appointment->mp_payment_id) {
            // Assuming MAS_MercadoPago class is available and has get_payment_info method
            // You might need to include this class or ensure it's loaded elsewhere.
            if (class_exists('MAS_MercadoPago')) {
                $mas_mp = new MAS_MercadoPago();
                $payment_info = $mas_mp->get_payment_info($appointment->mp_payment_id);
                if ($payment_info && isset($payment_info['transaction_details']['external_resource_url'])) {
                    $voucher_url = $payment_info['transaction_details']['external_resource_url'];
                }
            }
        }
        
        $message = self::get_email_template('appointment_confirmed', array(
            'patient_name' => $appointment->patient_name,
            'date' => date('d/m/Y', strtotime($appointment->appointment_date)),
            'time' => date('H:i', strtotime($appointment->appointment_time)),
            'professional_name' => $professional_name,
            'service_name' => $service_name,
            'voucher_url' => $voucher_url
        ));
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        return wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Send admin notification for new appointment
     */
    public static function send_admin_notification($appointment_id) {
        $settings = get_option('mas_settings', array());
        
        if (empty($settings['enable_notifications'])) {
            return false;
        }
        
        $admin_email = $settings['admin_email'] ?? get_option('admin_email');
        
        if (empty($admin_email)) {
            return false;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'mas_appointments';
        
        $appointment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $appointment_id
        ));
        
        if (!$appointment) {
            return false;
        }
        
        $subject = 'Nueva Cita Agendada';
        
        $message = self::get_email_template('admin_new_appointment', array(
            'patient_name' => $appointment->patient_name,
            'patient_email' => $appointment->patient_email,
            'patient_phone' => $appointment->patient_phone,
            'appointment_date' => date('d/m/Y', strtotime($appointment->appointment_date)),
            'appointment_time' => date('H:i', strtotime($appointment->appointment_time)),
            'admin_url' => admin_url('admin.php?page=medical-appointments-list')
        ));
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        return wp_mail($admin_email, $subject, $message, $headers);
    }
    
    /**
     * Send rental confirmation email to professional
     */
    public static function send_rental_confirmation($rental_id) {
        global $wpdb;
        $rentals_table = $wpdb->prefix . 'mas_box_rentals';
        $professionals_table = $wpdb->prefix . 'mas_professionals';
        $boxes_table = $wpdb->prefix . 'mas_boxes';
        
        $rental = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, b.box_name, b.box_number, p.user_id 
            FROM $rentals_table r
            JOIN $boxes_table b ON r.box_id = b.id
            JOIN $professionals_table p ON r.professional_id = p.id
            WHERE r.id = %d",
            $rental_id
        ));
        
        if (!$rental) {
            return false;
        }
        
        $user = get_userdata($rental->user_id);
        
        if (!$user) {
            return false;
        }
        
        $voucher_url = '';
        if ($rental->mp_payment_id) {
            if (class_exists('MAS_MercadoPago')) {
                $mas_mp = new MAS_MercadoPago();
                $payment_info = $mas_mp->get_payment_info($rental->mp_payment_id);
                if ($payment_info && isset($payment_info['transaction_details']['external_resource_url'])) {
                    $voucher_url = $payment_info['transaction_details']['external_resource_url'];
                }
            }
        }
        
        $to = $user->user_email;
        $subject = 'Confirmación de Arriendo de Box - ' . get_bloginfo('name');
        
        $message = self::get_email_template('rental_confirmation', array(
            'professional_name' => $user->display_name,
            'box_name' => $rental->box_name,
            'box_number' => $rental->box_number,
            'rental_date' => date('d/m/Y', strtotime($rental->rental_date)),
            'start_time' => date('H:i', strtotime($rental->start_time)),
            'end_time' => date('H:i', strtotime($rental->end_time)),
            'total_price' => number_format($rental->total_price, 0, ',', '.'),
            'voucher_url' => $voucher_url
        ));
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        return wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Send admin notification for new rental
     */
    public static function send_admin_rental_notification($rental_id) {
        $settings = get_option('mas_settings', array());
        
        if (empty($settings['enable_notifications'])) {
            return false;
        }
        
        $admin_email = $settings['admin_email'] ?? get_option('admin_email');
        
        if (empty($admin_email)) {
            return false;
        }
        
        global $wpdb;
        $rentals_table = $wpdb->prefix . 'mas_box_rentals';
        $professionals_table = $wpdb->prefix . 'mas_professionals';
        $boxes_table = $wpdb->prefix . 'mas_boxes';
        
        $rental = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, b.box_name, b.box_number, p.user_id 
            FROM $rentals_table r
            JOIN $boxes_table b ON r.box_id = b.id
            JOIN $professionals_table p ON r.professional_id = p.id
            WHERE r.id = %d",
            $rental_id
        ));
        
        if (!$rental) {
            return false;
        }
        
        $user = get_userdata($rental->user_id);
        
        if (!$user) {
            return false;
        }
        
        $subject = 'Nuevo Arriendo de Box - ' . get_bloginfo('name');
        
        $message = self::get_email_template('admin_new_rental', array(
            'professional_name' => $user->display_name,
            'professional_email' => $user->user_email,
            'box_name' => $rental->box_name,
            'box_number' => $rental->box_number,
            'rental_date' => date('d/m/Y', strtotime($rental->rental_date)),
            'start_time' => date('H:i', strtotime($rental->start_time)),
            'end_time' => date('H:i', strtotime($rental->end_time)),
            'total_price' => number_format($rental->total_price, 0, ',', '.'),
            'payment_id' => $rental->mp_payment_id,
            'payment_method' => $rental->payment_method,
            'admin_url' => admin_url('admin.php?page=medical-appointments-rentals')
        ));
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        return wp_mail($admin_email, $subject, $message, $headers);
    }
    
    /**
     * Send payment rejected notification
     */
    public static function send_payment_rejected($type, $id, $rejection_reason = '') {
        global $wpdb;
        
        if ($type === 'appointment') {
            $table = $wpdb->prefix . 'mas_appointments';
            $appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
            if (!$appointment) return false;
            
            $to = $appointment->patient_email;
            $subject = 'Pago Rechazado - Cita';
            $message = self::get_email_template('payment_rejected_appointment', array(
                'patient_name' => $appointment->patient_name,
                'appointment_date' => date('d/m/Y', strtotime($appointment->appointment_date)),
                'appointment_time' => date('H:i', strtotime($appointment->appointment_time)),
                'rejection_reason' => $rejection_reason ?: 'El banco o emisor de la tarjeta rechazó la transacción'
            ));
        } else {
            $rentals_table = $wpdb->prefix . 'mas_box_rentals';
            $professionals_table = $wpdb->prefix . 'mas_professionals';
            $rental = $wpdb->get_row($wpdb->prepare(
                "SELECT r.*, p.user_id FROM $rentals_table r 
                JOIN $professionals_table p ON r.professional_id = p.id 
                WHERE r.id = %d", $id
            ));
            if (!$rental) return false;
            
            $user = get_userdata($rental->user_id);
            if (!$user) return false;
            
            $to = $user->user_email;
            $subject = 'Pago Rechazado - Arriendo de Box';
            $message = self::get_email_template('payment_rejected_rental', array(
                'professional_name' => $user->display_name,
                'rental_date' => date('d/m/Y', strtotime($rental->rental_date)),
                'start_time' => date('H:i', strtotime($rental->start_time)),
                'rejection_reason' => $rejection_reason ?: 'El banco o emisor de la tarjeta rechazó la transacción'
            ));
        }
        
        return wp_mail($to, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    }
    
    /**
     * Send payment pending notification (with bank transfer details)
     */
    public static function send_payment_pending($type, $id) {
        global $wpdb;
        $settings = get_option('mas_settings', array());
        
        $bank_details = isset($settings['bank_account_info']) ? $settings['bank_account_info'] : 
            'Banco: Banco de Chile<br>Cuenta Corriente: 123456789<br>RUT: 12.345.678-9<br>Email: pagos@novaespacio.cl';
        
        if ($type === 'appointment') {
            $table = $wpdb->prefix . 'mas_appointments';
            $appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
            if (!$appointment) return false;
            
            $to = $appointment->patient_email;
            $subject = 'Pago Pendiente - Instrucciones para Transferencia';
            $message = self::get_email_template('payment_pending_appointment', array(
                'patient_name' => $appointment->patient_name,
                'appointment_date' => date('d/m/Y', strtotime($appointment->appointment_date)),
                'appointment_time' => date('H:i', strtotime($appointment->appointment_time)),
                'bank_details' => $bank_details,
                'reference' => 'CITA-' . $appointment->id
            ));
        } else {
            $rentals_table = $wpdb->prefix . 'mas_box_rentals';
            $professionals_table = $wpdb->prefix . 'mas_professionals';
            $rental = $wpdb->get_row($wpdb->prepare(
                "SELECT r.*, p.user_id FROM $rentals_table r 
                JOIN $professionals_table p ON r.professional_id = p.id 
                WHERE r.id = %d", $id
            ));
            if (!$rental) return false;
            
            $user = get_userdata($rental->user_id);
            if (!$user) return false;
            
            $to = $user->user_email;
            $subject = 'Pago Pendiente - Instrucciones para Transferencia';
            $message = self::get_email_template('payment_pending_rental', array(
                'professional_name' => $user->display_name,
                'rental_date' => date('d/m/Y', strtotime($rental->rental_date)),
                'start_time' => date('H:i', strtotime($rental->start_time)),
                'bank_details' => $bank_details,
                'reference' => 'ARRIENDO-' . $rental->id
            ));
        }
        
        return wp_mail($to, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    }
    
    /**
     * Send payment in process notification
     */
    public static function send_payment_in_process($type, $id) {
        global $wpdb;
        
        if ($type === 'appointment') {
            $table = $wpdb->prefix . 'mas_appointments';
            $appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
            if (!$appointment) return false;
            
            $to = $appointment->patient_email;
            $subject = 'Pago en Revisión - Cita';
            $message = self::get_email_template('payment_in_process', array(
                'name' => $appointment->patient_name,
                'type' => 'cita',
                'date' => date('d/m/Y', strtotime($appointment->appointment_date)),
                'time' => date('H:i', strtotime($appointment->appointment_time))
            ));
        } else {
            $rentals_table = $wpdb->prefix . 'mas_box_rentals';
            $professionals_table = $wpdb->prefix . 'mas_professionals';
            $rental = $wpdb->get_row($wpdb->prepare(
                "SELECT r.*, p.user_id FROM $rentals_table r 
                JOIN $professionals_table p ON r.professional_id = p.id 
                WHERE r.id = %d", $id
            ));
            if (!$rental) return false;
            
            $user = get_userdata($rental->user_id);
            if (!$user) return false;
            
            $to = $user->user_email;
            $subject = 'Pago en Revisión - Arriendo de Box';
            $message = self::get_email_template('payment_in_process', array(
                'name' => $user->display_name,
                'type' => 'arriendo de box',
                'date' => date('d/m/Y', strtotime($rental->rental_date)),
                'time' => date('H:i', strtotime($rental->start_time))
            ));
        }
        
        return wp_mail($to, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    }
    
    /**
     * Send payment cancelled notification
     */
    public static function send_payment_cancelled($type, $id) {
        global $wpdb;
        
        if ($type === 'appointment') {
            $table = $wpdb->prefix . 'mas_appointments';
            $appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
            if (!$appointment) return false;
            
            $to = $appointment->patient_email;
            $subject = 'Reserva Cancelada - Cita';
            $message = self::get_email_template('payment_cancelled', array(
                'name' => $appointment->patient_name,
                'type' => 'cita',
                'date' => date('d/m/Y', strtotime($appointment->appointment_date)),
                'time' => date('H:i', strtotime($appointment->appointment_time))
            ));
        } else {
            $rentals_table = $wpdb->prefix . 'mas_box_rentals';
            $professionals_table = $wpdb->prefix . 'mas_professionals';
            $rental = $wpdb->get_row($wpdb->prepare(
                "SELECT r.*, p.user_id FROM $rentals_table r 
                JOIN $professionals_table p ON r.professional_id = p.id 
                WHERE r.id = %d", $id
            ));
            if (!$rental) return false;
            
            $user = get_userdata($rental->user_id);
            if (!$user) return false;
            
            $to = $user->user_email;
            $subject = 'Reserva Cancelada - Arriendo de Box';
            $message = self::get_email_template('payment_cancelled', array(
                'name' => $user->display_name,
                'type' => 'arriendo de box',
                'date' => date('d/m/Y', strtotime($rental->rental_date)),
                'time' => date('H:i', strtotime($rental->start_time))
            ));
        }
        
        return wp_mail($to, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    }
    
    /**
     * Send payment refunded notification
     */
    public static function send_payment_refunded($type, $id) {
        global $wpdb;
        
        if ($type === 'appointment') {
            $table = $wpdb->prefix . 'mas_appointments';
            $appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
            if (!$appointment) return false;
            
            $to = $appointment->patient_email;
            $subject = 'Reembolso Procesado - Cita ';
            $message = self::get_email_template('payment_refunded', array(
                'name' => $appointment->patient_name,
                'type' => 'cita ',
                'date' => date('d/m/Y', strtotime($appointment->appointment_date)),
                'time' => date('H:i', strtotime($appointment->appointment_time))
            ));
        } else {
            $rentals_table = $wpdb->prefix . 'mas_box_rentals';
            $professionals_table = $wpdb->prefix . 'mas_professionals';
            $rental = $wpdb->get_row($wpdb->prepare(
                "SELECT r.*, p.user_id FROM $rentals_table r 
                JOIN $professionals_table p ON r.professional_id = p.id 
                WHERE r.id = %d", $id
            ));
            if (!$rental) return false;
            
            $user = get_userdata($rental->user_id);
            if (!$user) return false;
            
            $to = $user->user_email;
            $subject = 'Reembolso Procesado - Arriendo de Box';
            $message = self::get_email_template('payment_refunded', array(
                'name' => $user->display_name,
                'type' => 'arriendo de box',
                'date' => date('d/m/Y', strtotime($rental->rental_date)),
                'time' => date('H:i', strtotime($rental->start_time))
            ));
        }
        
        return wp_mail($to, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    }
    
    /**
     * Send payment chargeback notification
     */
    public static function send_payment_chargeback($type, $id) {
        global $wpdb;
        
        if ($type === 'appointment') {
            $table = $wpdb->prefix . 'mas_appointments';
            $appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
            if (!$appointment) return false;
            
            $to = $appointment->patient_email;
            $subject = 'Contracargo Aplicado - Cita';
            $message = self::get_email_template('payment_chargeback', array(
                'name' => $appointment->patient_name,
                'type' => 'cita'
            ));
        } else {
            $rentals_table = $wpdb->prefix . 'mas_box_rentals';
            $professionals_table = $wpdb->prefix . 'mas_professionals';
            $rental = $wpdb->get_row($wpdb->prepare(
                "SELECT r.*, p.user_id FROM $rentals_table r 
                JOIN $professionals_table p ON r.professional_id = p.id 
                WHERE r.id = %d", $id
            ));
            if (!$rental) return false;
            
            $user = get_userdata($rental->user_id);
            if (!$user) return false;
            
            $to = $user->user_email;
            $subject = 'Contracargo Aplicado - Arriendo de Box';
            $message = self::get_email_template('payment_chargeback', array(
                'name' => $user->display_name,
                'type' => 'arriendo de box'
            ));
        }
        
        return wp_mail($to, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    }
    
    /**
     * Send payment in mediation notification
     */
    public static function send_payment_in_mediation($type, $id) {
        global $wpdb;
        
        if ($type === 'appointment') {
            $table = $wpdb->prefix . 'mas_appointments';
            $appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
            if (!$appointment) return false;
            
            $to = $appointment->patient_email;
            $subject = 'Pago en Disputa - Cita';
            $message = self::get_email_template('payment_in_mediation', array(
                'name' => $appointment->patient_name,
                'type' => 'cita'
            ));
        } else {
            $rentals_table = $wpdb->prefix . 'mas_box_rentals';
            $professionals_table = $wpdb->prefix . 'mas_professionals';
            $rental = $wpdb->get_row($wpdb->prepare(
                "SELECT r.*, p.user_id FROM $rentals_table r 
                JOIN $professionals_table p ON r.professional_id = p.id 
                WHERE r.id = %d", $id
            ));
            if (!$rental) return false;
            
            $user = get_userdata($rental->user_id);
            if (!$user) return false;
            
            $to = $user->user_email;
            $subject = 'Pago en Disputa - Arriendo de Box';
            $message = self::get_email_template('payment_in_mediation', array(
                'name' => $user->display_name,
                'type' => 'arriendo de box'
            ));
        }
        
        return wp_mail($to, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    }
    
    /**
     * Send payment authorized notification
     */
    public static function send_payment_authorized($type, $id) {
        global $wpdb;
        
        if ($type === 'appointment') {
            $table = $wpdb->prefix . 'mas_appointments';
            $appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
            if (!$appointment) return false;
            
            $to = $appointment->patient_email;
            $subject = 'Pago Autorizado - Cita';
            $message = self::get_email_template('payment_authorized', array(
                'name' => $appointment->patient_name,
                'type' => 'cita',
                'date' => date('d/m/Y', strtotime($appointment->appointment_date)),
                'time' => date('H:i', strtotime($appointment->appointment_time))
            ));
        } else {
            $rentals_table = $wpdb->prefix . 'mas_box_rentals';
            $professionals_table = $wpdb->prefix . 'mas_professionals';
            $rental = $wpdb->get_row($wpdb->prepare(
                "SELECT r.*, p.user_id FROM $rentals_table r 
                JOIN $professionals_table p ON r.professional_id = p.id 
                WHERE r.id = %d", $id
            ));
            if (!$rental) return false;
            
            $user = get_userdata($rental->user_id);
            if (!$user) return false;
            
            $to = $user->user_email;
            $subject = 'Pago Autorizado - Arriendo de Box';
            $message = self::get_email_template('payment_authorized', array(
                'name' => $user->display_name,
                'type' => 'arriendo de box',
                'date' => date('d/m/Y', strtotime($rental->rental_date)),
                'time' => date('H:i', strtotime($rental->start_time))
            ));
        }
        
        return wp_mail($to, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    }
    
    /**
     * Get email template
     */
    private static function get_email_template($template_name, $data) {
        $templates = array(
            'appointment_confirmation' => '
                <html>
                <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 10px 10px 0 0;">
                        <h2 style="color: white; margin: 0; font-size: 24px;">📋 Solicitud de Cita</h2>
                    </div>
                    <div style="background: white; padding: 30px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 10px 10px;">
                        <p style="font-size: 16px; color: #374151;">Hola <strong>{patient_name}</strong>,</p>
                        <p style="color: #6b7280;">Tu solicitud de cita ha sido registrada exitosamente.</p>
                        
                        <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0;">
                            <h3 style="color: #1f2937; margin: 0 0 15px 0; font-size: 18px;">Detalles de la Solicitud</h3>
                            <p style="margin: 8px 0; color: #374151;"><strong>📅 Fecha:</strong> {appointment_date}</p>
                            <p style="margin: 8px 0; color: #374151;"><strong>🕐 Hora:</strong> {appointment_time}</p>
                            <p style="margin: 8px 0; color: #374151;"><strong>📊 Estado:</strong> {status}</p>
                        </div>
                        
                        <div style="background: #e0e7ff; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #6366f1;">
                            <p style="margin: 0; color: #3730a3; font-size: 14px;">
                                <strong>ℹ️ Importante:</strong> Recibirás una notificación cuando tu cita sea confirmada por nuestro equipo.
                            </p>
                        </div>
                        
                        <p style="color: #6b7280; font-size: 14px; margin-top: 30px;">
                            Gracias por confiar en nosotros.
                        </p>
                        
                        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
                        
                        <p style="color: #9ca3af; font-size: 12px; margin: 0;">
                            Este es un correo automático, por favor no respondas a este mensaje.
                        </p>
                    </div>
                </body>
                </html>
            ',
            'appointment_scheduled' => '
                <html>
                <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                    <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 30px; border-radius: 10px 10px 0 0;">
                        <h2 style="color: white; margin: 0; font-size: 24px;">✓ Cita Agendada</h2>
                    </div>
                    <div style="background: white; padding: 30px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 10px 10px;">
                        <p style="font-size: 16px; color: #374151;">Hola <strong>{patient_name}</strong>,</p>
                        <p style="color: #6b7280;">Tu pago ha sido procesado exitosamente y tu cita ha sido agendada.</p>
                        
                        <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0;">
                            <h3 style="color: #1f2937; margin: 0 0 15px 0; font-size: 18px;">Detalles de la Cita</h3>
                            <p style="margin: 8px 0; color: #374151;"><strong>📅 Fecha:</strong> {date}</p>
                            <p style="margin: 8px 0; color: #374151;"><strong>🕐 Hora:</strong> {time}</p>
                            <p style="margin: 8px 0; color: #374151;"><strong>👨‍⚕️ Profesional:</strong> {professional_name}</p>
                            <p style="margin: 8px 0; color: #374151;"><strong>🏥 Servicio:</strong> {service_name}</p>
                        </div>
                        
                        {voucher_section}
                        
                        <div style="background: #dcfce7; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #10b981;">
                            <p style="margin: 0; color: #065f46; font-size: 14px;">
                                <strong>✅ Confirmación:</strong> Tu cita está confirmada y lista. Te esperamos!
                            </p>
                        </div>
                        
                        <div style="background: #fef3c7; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b;">
                            <p style="margin: 0; color: #92400e; font-size: 14px;">
                                <strong>⚠️ Importante:</strong> Por favor llega 10 minutos antes de tu cita.
                            </p>
                        </div>
                        
                        <p style="color: #6b7280; font-size: 14px; margin-top: 30px;">
                            Si necesitas cancelar o reagendar tu cita, por favor contáctanos con anticipación.
                        </p>
                        
                        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
                        
                        <p style="color: #9ca3af; font-size: 12px; margin: 0;">
                            Este es un correo automático, por favor no respondas a este mensaje.
                        </p>
                    </div>
                </body>
                </html>
            ',
            'appointment_scheduled_pending' => '
                <html>
                <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                    <div style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 30px; border-radius: 10px 10px 0 0;">
                        <h2 style="color: white; margin: 0; font-size: 24px;">✓ Cita Agendada</h2>
                    </div>
                    <div style="background: white; padding: 30px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 10px 10px;">
                        <p style="font-size: 16px; color: #374151;">Hola <strong>{patient_name}</strong>,</p>
                        <p style="color: #6b7280;">Tu pago ha sido procesado exitosamente y tu cita ha sido agendada.</p>
                        
                        <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0;">
                            <h3 style="color: #1f2937; margin: 0 0 15px 0; font-size: 18px;">Detalles de la Cita</h3>
                            <p style="margin: 8px 0; color: #374151;"><strong>📅 Fecha:</strong> {date}</p>
                            <p style="margin: 8px 0; color: #374151;"><strong>🕐 Hora:</strong> {time}</p>
                            <p style="margin: 8px 0; color: #374151;"><strong>🏥 Servicio:</strong> {service_name}</p>
                        </div>
                        
                        {voucher_section}
                        
                        <div style="background: #dbeafe; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #3b82f6;">
                            <p style="margin: 0; color: #1e40af; font-size: 14px;">
                                <strong>ℹ️ Asignación Pendiente:</strong> Pronto te asignaremos un profesional y te enviaremos la confirmación completa.
                            </p>
                        </div>
                        
                        <div style="background: #fef3c7; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b;">
                            <p style="margin: 0; color: #92400e; font-size: 14px;">
                                <strong>⚠️ Importante:</strong> Por favor llega 10 minutos antes de tu cita.
                            </p>
                        </div>
                        
                        <p style="color: #6b7280; font-size: 14px; margin-top: 30px;">
                            Si necesitas cancelar o reagendar tu cita, por favor contáctanos con anticipación.
                        </p>
                        
                        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
                        
                        <p style="color: #9ca3af; font-size: 12px; margin: 0;">
                            Este es un correo automático, por favor no respondas a este mensaje.
                        </p>
                    </div>
                </body>
                </html>
            ',
            'appointment_confirmed' => '
                <html>
                <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 10px 10px 0 0;">
                        <h2 style="color: white; margin: 0; font-size: 24px;">✓ Cita Confirmada</h2>
                    </div>
                    <div style="background: white; padding: 30px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 10px 10px;">
                        <p style="font-size: 16px; color: #374151;">Hola <strong>{patient_name}</strong>,</p>
                        <p style="color: #6b7280;">Tu cita ha sido confirmada exitosamente.</p>
                        
                        <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0;">
                            <h3 style="color: #1f2937; margin: 0 0 15px 0; font-size: 18px;">Detalles de la Cita</h3>
                            <p style="margin: 8px 0; color: #374151;"><strong>📅 Fecha:</strong> {date}</p>
                            <p style="margin: 8px 0; color: #374151;"><strong>🕐 Hora:</strong> {time}</p>
                            <p style="margin: 8px 0; color: #374151;"><strong>👨‍⚕️ Profesional:</strong> {professional_name}</p>
                            <p style="margin: 8px 0; color: #374151;"><strong>🏥 Servicio:</strong> {service_name}</p>
                        </div>
                        
                        {voucher_section}
                        
                        <div style="background: #fef3c7; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b;">
                            <p style="margin: 0; color: #92400e; font-size: 14px;">
                                <strong>⚠️ Importante:</strong> Por favor llega 10 minutos antes de tu cita.
                            </p>
                        </div>
                        
                        <p style="color: #6b7280; font-size: 14px; margin-top: 30px;">
                            Si necesitas cancelar o reagendar tu cita, por favor contáctanos con anticipación.
                        </p>
                        
                        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
                        
                        <p style="color: #9ca3af; font-size: 12px; margin: 0;">
                            Este es un correo automático, por favor no respondas a este mensaje.
                        </p>
                    </div>
                </body>
                </html>
            ',
            'status_change' => '
                <html>
                <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                    <div style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 30px; border-radius: 10px 10px 0 0;">
                        <h2 style="color: white; margin: 0; font-size: 24px;">🔄 Actualización de Cita</h2>
                    </div>
                    <div style="background: white; padding: 30px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 10px 10px;">
                        <p style="font-size: 16px; color: #374151;">Hola <strong>{patient_name}</strong>,</p>
                        <p style="color: #6b7280;">El estado de tu cita ha sido actualizado.</p>
                        
                        <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0;">
                            <h3 style="color: #1f2937; margin: 0 0 15px 0; font-size: 18px;">Detalles de la Cita</h3>
                            <p style="margin: 8px 0; color: #374151;"><strong>📅 Fecha:</strong> {appointment_date}</p>
                            <p style="margin: 8px 0; color: #374151;"><strong>🕐 Hora:</strong> {appointment_time}</p>
                            <p style="margin: 8px 0; color: #374151;"><strong>📊 Nuevo Estado:</strong> {new_status}</p>
                        </div>
                        
                        <div style="background: #dbeafe; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #3b82f6;">
                            <p style="margin: 0; color: #1e40af; font-size: 14px;">
                                <strong>ℹ️ Nota:</strong> Si tienes alguna pregunta, no dudes en contactarnos.
                            </p>
                        </div>
                        
                        <p style="color: #6b7280; font-size: 14px; margin-top: 30px;">
                            Gracias por tu confianza.
                        </p>
                        
                        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
                        
                        <p style="color: #9ca3af; font-size: 12px; margin: 0;">
                            Este es un correo automático, por favor no respondas a este mensaje.
                        </p>
                    </div>
                </body>
                </html>
            ',
            'admin_new_appointment' => '
                <html>
                <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                    <div style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); padding: 30px; border-radius: 10px 10px 0 0;">
                        <h2 style="color: white; margin: 0; font-size: 24px;">🔔 Nueva Cita Agendada</h2>
                    </div>
                    <div style="background: white; padding: 30px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 10px 10px;">
                        <p style="font-size: 16px; color: #374151;">Se ha registrado una nueva cita en el sistema.</p>
                        
                        <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0;">
                            <h3 style="color: #1f2937; margin: 0 0 15px 0; font-size: 18px;">Información del Paciente</h3>
                            <p style="margin: 8px 0; color: #374151;"><strong>👤 Nombre:</strong> {patient_name}</p>
                            <p style="margin: 8px 0; color: #374151;"><strong>📧 Email:</strong> {patient_email}</p>
                            <p style="margin: 8px 0; color: #374151;"><strong>📞 Teléfono:</strong> {patient_phone}</p>
                            <p style="margin: 8px 0; color: #374151;"><strong>📅 Fecha:</strong> {appointment_date}</p>
                            <p style="margin: 8px 0; color: #374151;"><strong>🕐 Hora:</strong> {appointment_time}</p>
                        </div>
                        
                        <div style="text-align: center; margin: 30px 0;">
                            <a href="{admin_url}" style="display: inline-block; background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">
                                Ver en Panel de Administración
                            </a>
                        </div>
                        
                        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
                        
                        <p style="color: #9ca3af; font-size: 12px; margin: 0;">
                            Este es un correo automático del sistema.
                        </p>
                    </div>
                </body>
                </html>
            ',
            'admin_new_rental' => '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background-color: #f8fafc; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">
    
    <!-- Main Container -->
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f8fafc;">
        <tr>
            <td style="padding: 40px 20px;" align="center">
                
                <!-- Email Card -->
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">
                    
                    <!-- Header with notification icon -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 48px 32px; text-align: center;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td align="center">
                                        <!-- Notification Icon Circle -->
                                        <div style="width: 72px; height: 72px; background-color: rgba(255, 255, 255, 0.2); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px; backdrop-filter: blur(10px);">
                                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                <path d="M13.73 21a2 2 0 0 1-3.46 0" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </div>
                                        <h1 style="margin: 0; font-size: 32px; font-weight: 700; color: #ffffff; letter-spacing: -0.5px; line-height: 1.2;">Nuevo Arriendo de Box</h1>
                                        <p style="margin: 12px 0 0 0; font-size: 16px; color: rgba(255, 255, 255, 0.9); font-weight: 500;">Se ha registrado un nuevo arriendo en el sistema</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 40px 32px;">
                            
                            <!-- Alert Message -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #dbeafe; border-left: 4px solid #3b82f6; border-radius: 8px; margin: 0 0 32px 0;">
                                <tr>
                                    <td style="padding: 20px 20px;">
                                        <p style="margin: 0 0 8px 0; font-size: 14px; color: #1e40af; font-weight: 700;">🔔 Notificación Administrativa</p>
                                        <p style="margin: 0; font-size: 14px; color: #1e3a8a; line-height: 1.6;">
                                            Un profesional ha completado el proceso de pago y arriendo de box. Revisa los detalles a continuación.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Professional Info Card -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background: linear-gradient(to bottom, #fefce8 0%, #fef9c3 100%); border-radius: 12px; border: 1px solid #fde047; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 24px;">
                                        <p style="margin: 0 0 6px 0; font-size: 12px; color: #854d0e; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600;">👩‍⚕️ Datos del Profesional</p>
                                        <p style="margin: 0 0 8px 0; font-size: 22px; color: #422006; font-weight: 700; line-height: 1.3;">{professional_name}</p>
                                        <p style="margin: 0; font-size: 15px; color: #713f12; font-weight: 500;">📧 {professional_email}</p>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Details Card -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background: linear-gradient(to bottom, #f8fafc 0%, #f1f5f9 100%); border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 32px;">
                                <tr>
                                    <td style="padding: 28px 24px;">
                                        
                                        <!-- Box Information -->
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                            <tr>
                                                <td style="padding-bottom: 20px; border-bottom: 1px solid #e2e8f0;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                                        <tr>
                                                            <td>
                                                                <p style="margin: 0 0 6px 0; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600;">Box Médico Arrendado</p>
                                                                <p style="margin: 0; font-size: 22px; color: #0f172a; font-weight: 700; line-height: 1.3;">{box_name}</p>
                                                            </td>
                                                            <td align="right" valign="middle">
                                                                <span style="display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; padding: 6px 16px; border-radius: 20px; font-size: 14px; font-weight: 600; white-space: nowrap;">Box #{box_number}</span>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            
                                            <!-- Date & Time -->
                                            <tr>
                                                <td style="padding: 20px 0; border-bottom: 1px solid #e2e8f0;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                                        <tr>
                                                            <td width="50%" style="padding-right: 12px;">
                                                                <p style="margin: 0 0 6px 0; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600;">📅 Fecha</p>
                                                                <p style="margin: 0; font-size: 16px; color: #0f172a; font-weight: 600;">{rental_date}</p>
                                                            </td>
                                                            <td width="50%" style="padding-left: 12px; border-left: 1px solid #e2e8f0;">
                                                                <p style="margin: 0 0 6px 0; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600;">⏰ Horario</p>
                                                                <p style="margin: 0; font-size: 16px; color: #0f172a; font-weight: 600;">{start_time} - {end_time}</p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            
                                            <!-- Payment Info -->
                                            <tr>
                                                <td style="padding-top: 20px;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                                        <tr>
                                                            <td width="50%" style="padding-right: 12px;">
                                                                <p style="margin: 0 0 6px 0; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600;">💳 ID Pago</p>
                                                                <p style="margin: 0; font-size: 14px; color: #0f172a; font-weight: 600; font-family: monospace;">{payment_id}</p>
                                                            </td>
                                                            <td width="50%" style="padding-left: 12px; border-left: 1px solid #e2e8f0;">
                                                                <p style="margin: 0 0 6px 0; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600;">💳 Método</p>
                                                                <p style="margin: 0; font-size: 14px; color: #0f172a; font-weight: 600;">{payment_method}</p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Total Amount Card -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background: linear-gradient(135deg, #059669 0%, #047857 100%); border-radius: 10px; margin-bottom: 32px;">
                                <tr>
                                    <td style="padding: 24px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                            <tr>
                                                <td>
                                                    <p style="margin: 0 0 4px 0; font-size: 13px; color: rgba(255, 255, 255, 0.9); font-weight: 500;">Monto Total Recibido</p>
                                                    <p style="margin: 0; font-size: 36px; color: #ffffff; font-weight: 800; letter-spacing: -1px; line-height: 1;">${total_price}</p>
                                                </td>
                                                <td align="right" valign="middle">
                                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="opacity: 0.3;">
                                                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Action Button -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 32px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{admin_url}" style="display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; padding: 16px 40px; text-decoration: none; border-radius: 10px; font-weight: 700; font-size: 16px; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.3); transition: all 0.3s ease;">
                                            Ver en Panel de Administración →
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; padding: 32px; text-align: center; border-top: 1px solid #e2e8f0;">
                            <p style="margin: 0 0 8px 0; color: #64748b; font-size: 14px; line-height: 1.6;">
                                <strong>Sistema de Gestión Médica</strong>
                            </p>
                            <p style="margin: 0; color: #94a3b8; font-size: 12px; line-height: 1.5;">
                                Este es un correo automático del sistema. Por favor no responder.
                            </p>
                        </td>
                    </tr>
                    
                </table>
                
            </td>
        </tr>
    </table>
    
</body>
</html>
',
            'rental_confirmation' => '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background-color: #f8fafc; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">
    
    <!-- Main Container -->
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f8fafc;">
        <tr>
            <td style="padding: 40px 20px;" align="center">
                
                <!-- Email Card -->
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">
                    
                    <!-- Header with success icon -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%); padding: 48px 32px; text-align: center;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td align="center">
                                        <!-- Success Icon Circle -->
                                        <div style="width: 72px; height: 72px; background-color: rgba(255, 255, 255, 0.2); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px; backdrop-filter: blur(10px);">
                                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M20 6L9 17L4 12" stroke="#ffffff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </div>
                                        <h1 style="margin: 0; font-size: 32px; font-weight: 700; color: #ffffff; letter-spacing: -0.5px; line-height: 1.2;">Arriendo Confirmado</h1>
                                        <p style="margin: 12px 0 0 0; font-size: 16px; color: rgba(255, 255, 255, 0.9); font-weight: 500;">Tu box médico está reservado y listo</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 40px 32px;">
                            
                            <!-- Greeting -->
                            <p style="margin: 0 0 8px 0; font-size: 16px; color: #64748b; line-height: 1.5;">Hola,</p>
                            <p style="margin: 0 0 24px 0; font-size: 20px; color: #0f172a; font-weight: 600; line-height: 1.4;">Dr(a). {professional_name}</p>
                            <p style="margin: 0 0 32px 0; font-size: 15px; color: #475569; line-height: 1.7;">Tu arriendo de box médico ha sido procesado exitosamente. A continuación encontrarás todos los detalles de tu reserva:</p>
                            
                            <!-- Details Card -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background: linear-gradient(to bottom, #f8fafc 0%, #f1f5f9 100%); border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 32px;">
                                <tr>
                                    <td style="padding: 28px 24px;">
                                        
                                        <!-- Box Information -->
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                            <tr>
                                                <td style="padding-bottom: 20px; border-bottom: 1px solid #e2e8f0;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                                        <tr>
                                                            <td>
                                                                <p style="margin: 0 0 6px 0; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600;">Box Médico</p>
                                                                <p style="margin: 0; font-size: 22px; color: #0f172a; font-weight: 700; line-height: 1.3;">{box_name}</p>
                                                            </td>
                                                            <td align="right" valign="middle">
                                                                <span style="display: inline-block; background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%); color: #ffffff; padding: 6px 16px; border-radius: 20px; font-size: 14px; font-weight: 600; white-space: nowrap;">Box #{box_number}</span>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            
                                            <!-- Date & Time -->
                                            <tr>
                                                <td style="padding: 20px 0; border-bottom: 1px solid #e2e8f0;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                                        <tr>
                                                            <td width="50%" style="padding-right: 12px;">
                                                                <p style="margin: 0 0 6px 0; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600;">📅 Fecha</p>
                                                                <p style="margin: 0; font-size: 16px; color: #0f172a; font-weight: 600;">{rental_date}</p>
                                                            </td>
                                                            <td width="50%" style="padding-left: 12px; border-left: 1px solid #e2e8f0;">
                                                                <p style="margin: 0 0 6px 0; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600;">⏰ Horario</p>
                                                                <p style="margin: 0; font-size: 16px; color: #0f172a; font-weight: 600;">{start_time} - {end_time}</p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            
                                            <!-- Price -->
                                            <tr>
                                                <td style="padding-top: 24px;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background: linear-gradient(135deg, #059669 0%, #047857 100%); border-radius: 10px; padding: 20px 24px;">
                                                        <tr>
                                                            <td>
                                                                <p style="margin: 0 0 4px 0; font-size: 13px; color: rgba(255, 255, 255, 0.9); font-weight: 500;">Total Pagado</p>
                                                                <p style="margin: 0; font-size: 36px; color: #ffffff; font-weight: 800; letter-spacing: -1px; line-height: 1;">${total_price}</p>
                                                            </td>
                                                            <td align="right" valign="middle">
                                                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="opacity: 0.3;">
                                                                    <path d="M20 6L9 17L4 12" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                                </svg>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Voucher Button (if available) -->
                            {voucher_button}
                            
                            <!-- Important Notice -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 8px; margin: 32px 0;">
                                <tr>
                                    <td style="padding: 20px 20px;">
                                        <p style="margin: 0 0 8px 0; font-size: 14px; color: #1e40af; font-weight: 700;">💡 Información Importante</p>
                                        <p style="margin: 0; font-size: 14px; color: #1e3a8a; line-height: 1.6;">
                                            Por favor, llega 10 minutos antes de tu horario reservado. Si necesitas cancelar o modificar tu arriendo, contáctanos con al menos 24 horas de anticipación.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Support Section -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top: 32px;">
                                <tr>
                                    <td style="text-align: center; padding: 24px 0; border-top: 1px solid #e2e8f0;">
                                        <p style="margin: 0 0 12px 0; font-size: 13px; color: #64748b;">¿Necesitas ayuda?</p>
                                        <a href="mailto:soporte@novaespacio.cl" style="display: inline-block; color: #0f766e; text-decoration: none; font-weight: 600; font-size: 14px;">soporte@novaespacio.cl</a>
                                    </td>
                                </tr>
                            </table>
                            
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #0f172a; padding: 32px; text-align: center;">
                            <p style="margin: 0 0 8px 0; font-size: 15px; color: #ffffff; font-weight: 600;">NovaEspacio</p>
                            <p style="margin: 0 0 16px 0; font-size: 13px; color: #94a3b8; line-height: 1.6;">Soluciones profesionales para el sector médico</p>
                            <p style="margin: 0; font-size: 12px; color: #64748b; line-height: 1.5;">Este es un correo automático. Por favor, no respondas a este mensaje.</p>
                        </td>
                    </tr>
                    
                </table>
                
            </td>
        </tr>
    </table>
    
</body>
</html>
            ',
            'payment_rejected_appointment' => '
                <html>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;">
                        <h2 style="color: #d32f2f;">❌ Pago Rechazado</h2>
                        <p>Estimado/a <strong>{patient_name}</strong>,</p>
                        <p>Lamentablemente su pago para la cita ha sido rechazado.</p>
                        <div style="background: #ffebee; padding: 15px; border-left: 4px solid #d32f2f; margin: 20px 0;">
                            <p><strong>Fecha de Cita:</strong> {appointment_date}</p>
                            <p><strong>Hora:</strong> {appointment_time}</p>
                            <p><strong>Motivo del Rechazo:</strong> {rejection_reason}</p>
                        </div>
                        <p><strong>Posibles razones del rechazo:</strong></p>
                        <ul>
                            <li>Fondos insuficientes en la cuenta</li>
                            <li>Límite de crédito excedido</li>
                            <li>Tarjeta bloqueada o vencida</li>
                            <li>Datos incorrectos de la tarjeta</li>
                        </ul>
                        <p><strong>¿Qué puede hacer?</strong></p>
                        <ul>
                            <li>Verificar con su banco el motivo específico</li>
                            <li>Intentar con otro método de pago</li>
                            <li>Contactarse con nosotros para opciones alternativas</li>
                        </ul>
                        <p>Su reserva ha sido cancelada. Puede agendar nuevamente cuando esté listo.</p>
                    </div>
                </body>
                </html>
            ',
            'payment_rejected_rental' => '
                <html>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;">
                        <h2 style="color: #d32f2f;">❌ Pago Rechazado</h2>
                        <p>Estimado/a profesional,</p>
                        <p>Lamentablemente su pago para el arriendo de box ha sido rechazado.</p>
                        <div style="background: #ffebee; padding: 15px; border-left: 4px solid #d32f2f; margin: 20px 0;">
                            <p><strong>Fecha de Arriendo:</strong> {rental_date}</p>
                            <p><strong>Hora:</strong> {start_time}</p>
                            <p><strong>Motivo del Rechazo:</strong> {rejection_reason}</p>
                        </div>
                        <p>Su reserva ha sido cancelada. Por favor contacte con su banco o intente con otro método de pago.</p>
                    </div>
                </body>
                </html>
            ',
            'payment_pending_appointment' => '
                <html>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;">
                        <h2 style="color: #ff9800;">⏳ Pago Pendiente - Instrucciones de Transferencia</h2>
                        <p>Estimado/a <strong>{patient_name}</strong>,</p>
                        <p>Su reserva está pendiente de pago. Para completar su cita, realice una transferencia bancaria a:</p>
                        <div style="background: #fff3e0; padding: 15px; border-left: 4px solid #ff9800; margin: 20px 0;">
                            <p><strong>Cita Agendada:</strong></p>
                            <p>Fecha: {appointment_date}</p>
                            <p>Hora: {appointment_time}</p>
                        </div>
                        <div style="background: #e3f2fd; padding: 15px; border-left: 4px solid #2196f3; margin: 20px 0;">
                            <p><strong>📋 Datos Bancarios:</strong></p>
                            {bank_details}
                            <p style="margin-top: 15px;"><strong>Referencia:</strong> {reference}</p>
                        </div>
                        <p><strong>Importante:</strong> Envíe el comprobante de transferencia a nuestro correo para confirmar su cita.</p>
                        <p>Una vez verificado el pago, recibirá la confirmación de su cita.</p>
                    </div>
                </body>
                </html>
            ',
            'payment_pending_rental' => '
                <html>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;">
                        <h2 style="color: #ff9800;">⏳ Pago Pendiente - Instrucciones de Transferencia</h2>
                        <p>Estimado/a profesional,</p>
                        <p>Su reserva de box está pendiente de pago. Para completar su arriendo, realice una transferencia bancaria a:</p>
                        <div style="background: #fff3e0; padding: 15px; border-left: 4px solid #ff9800; margin: 20px 0;">
                            <p><strong>Arriendo Reservado:</strong></p>
                            <p>Fecha: {rental_date}</p>
                            <p>Hora: {start_time}</p>
                        </div>
                        <div style="background: #e3f2fd; padding: 15px; border-left: 4px solid #2196f3; margin: 20px 0;">
                            <p><strong>📋 Datos Bancarios:</strong></p>
                            {bank_details}
                            <p style="margin-top: 15px;"><strong>Referencia:</strong> {reference}</p>
                        </div>
                        <p><strong>Importante:</strong> Envíe el comprobante de transferencia para confirmar su arriendo.</p>
                    </div>
                </body>
                </html>
            ',
            'payment_in_process' => '
                <html>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;">
                        <h2 style="color: #2196f3;">🔍 Pago en Revisión</h2>
                        <p>Estimado/a <strong>{name}</strong>,</p>
                        <p>Su pago está siendo revisado por la entidad financiera.</p>
                        <div style="background: #e3f2fd; padding: 15px; border-left: 4px solid #2196f3; margin: 20px 0;">
                            <p><strong>Reserva:</strong> {type}</p>
                            <p><strong>Fecha:</strong> {date}</p>
                            <p><strong>Hora:</strong> {time}</p>
                        </div>
                        <p><strong>¿Por qué está en revisión?</strong></p>
                        <ul>
                            <li>Verificación de seguridad adicional del banco</li>
                            <li>Validación de datos de la transacción</li>
                            <li>Proceso de autenticación en curso</li>
                        </ul>
                        <p>Este proceso puede tomar de algunos minutos hasta 48 horas. Le notificaremos cuando el pago sea confirmado.</p>
                        <p>Su reserva está protegida durante este periodo.</p>
                    </div>
                </body>
                </html>
            ',
            'payment_cancelled' => '
                <html>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;">
                        <h2 style="color: #757575;">🚫 Reserva Cancelada</h2>
                        <p>Estimado/a <strong>{name}</strong>,</p>
                        <p>Su reserva ha sido cancelada porque el pago fue cancelado o no se completó.</p>
                        <div style="background: #f5f5f5; padding: 15px; border-left: 4px solid #757575; margin: 20px 0;">
                            <p><strong>Reserva Cancelada:</strong> {type}</p>
                            <p><strong>Fecha:</strong> {date}</p>
                            <p><strong>Hora:</strong> {time}</p>
                        </div>
                        <p><strong>Motivos comunes de cancelación:</strong></p>
                        <ul>
                            <li>El pago fue cancelado manualmente</li>
                            <li>Se cerró la ventana de pago sin completar la transacción</li>
                            <li>Tiempo de sesión expirado</li>
                        </ul>
                        <p>Puede realizar una nueva reserva cuando lo desee. Estamos a su disposición.</p>
                    </div>
                </body>
                </html>
            ',
            'payment_refunded' => '
                <html>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;">
                        <h2 style="color: #4caf50;">💰 Reembolso Procesado</h2>
                        <p>Estimado/a <strong>{name}</strong>,</p>
                        <p>Le informamos que su pago ha sido reembolsado exitosamente.</p>
                        <div style="background: #e8f5e9; padding: 15px; border-left: 4px solid #4caf50; margin: 20px 0;">
                            <p><strong>Reserva:</strong> {type}</p>
                            <p><strong>Fecha:</strong> {date}</p>
                            <p><strong>Hora:</strong> {time}</p>
                        </div>
                        <p><strong>Información del Reembolso:</strong></p>
                        <ul>
                            <li>El dinero será devuelto al mismo medio de pago utilizado</li>
                            <li>El tiempo de acreditación depende de su banco (generalmente 5-10 días hábiles)</li>
                            <li>Recibirá una notificación de su banco cuando el dinero esté disponible</li>
                        </ul>
                        <p>La reserva ha sido cancelada debido al reembolso.</p>
                        <p>Si tiene alguna consulta sobre el reembolso, no dude en contactarnos.</p>
                    </div>
                </body>
                </html>
            ',
            'payment_chargeback' => '
                <html>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;">
                        <h2 style="color: #ff5722;">⚠️ Contracargo Aplicado</h2>
                        <p>Estimado/a <strong>{name}</strong>,</p>
                        <p>Hemos recibido notificación de un contracargo (chargeback) desde su banco para su {type}.</p>
                        <div style="background: #fbe9e7; padding: 15px; border-left: 4px solid #ff5722; margin: 20px 0;">
                            <p><strong>¿Qué es un contracargo?</strong></p>
                            <p>Un contracargo ocurre cuando usted o su banco revierten el pago de una transacción.</p>
                        </div>
                        <p><strong>Consecuencias:</strong></p>
                        <ul>
                            <li>La reserva ha sido cancelada automáticamente</li>
                            <li>El monto será devuelto a su cuenta según procesos bancarios</li>
                            <li>Este evento queda registrado en nuestro sistema</li>
                        </ul>
                        <p><strong>Si usted no solicitó este contracargo:</strong></p>
                        <p>Por favor contacte a su banco inmediatamente y a nuestro equipo para aclarar la situación.</p>
                        <p>Si tiene dudas sobre el cargo, estamos disponibles para ayudarle antes de proceder con un contracargo.</p>
                    </div>
                </body>
                </html>
            ',
            'payment_in_mediation' => '
                <html>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;">
                        <h2 style="color: #ff9800;">⚖️ Pago en Disputa/Mediación</h2>
                        <p>Estimado/a <strong>{name}</strong>,</p>
                        <p>Le informamos que su pago para {type} se encuentra en proceso de mediación.</p>
                        <div style="background: #fff3e0; padding: 15px; border-left: 4px solid #ff9800; margin: 20px 0;">
                            <p><strong>¿Qué significa esto?</strong></p>
                            <p>Una disputa o reclamo ha sido iniciado sobre esta transacción y está siendo evaluado por MercadoPago.</p>
                        </div>
                        <p><strong>Proceso de Mediación:</strong></p>
                        <ul>
                            <li>Mercado Pago revisará ambas partes de la transacción</li>
                            <li>Puede tomar varios días hábiles resolver la disputa</li>
                            <li>Se le notificará cuando haya una resolución</li>
                        </ul>
                        <p><strong>Durante este tiempo:</strong></p>
                        <ul>
                            <li>Su reserva permanece en estado pendiente</li>
                            <li>No se procesarán cambios hasta que se resuelva la disputa</li>
                            <li>Puede comunicarse con nosotros para más información</li>
                        </ul>
                        <p>Nuestro equipo está disponible para ayudarle a resolver esta situación de la mejor manera.</p>
                    </div>
                </body>
                </html>
            ',
            'payment_authorized' => '
                <html>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;">
                        <h2 style="color: #2196f3;">✓ Pago Autorizado</h2>
                        <p>Estimado/a <strong>{name}</strong>,</p>
                        <p>Su pago ha sido autorizado y está pendiente de captura final.</p>
                        <div style="background: #e3f2fd; padding: 15px; border-left: 4px solid #2196f3; margin: 20px 0;">
                            <p><strong>Reserva:</strong> {type}</p>
                            <p><strong>Fecha:</strong> {date}</p>
                            <p><strong>Hora:</strong> {time}</p>
                        </div>
                        <p><strong>¿Qué significa "autorizado"?</strong></p>
                        <ul>
                            <li>Su banco ha aprobado la transacción</li>
                            <li>El monto está reservado en su cuenta/tarjeta</li>
                            <li>El cargo final se procesará en las próximas horas</li>
                        </ul>
                        <p>Su reserva está garantizada. Recibirá la confirmación completa cuando el pago sea capturado.</p>
                        <p>Este proceso es automático y no requiere ninguna acción de su parte.</p>
                    </div>
                </body>
                </html>
            '
        );
        
        $template = isset($templates[$template_name]) ? $templates[$template_name] : $templates['appointment_confirmation'];
        
        $voucher_section = '';
        if (!empty($data['voucher_url'])) {
            $voucher_section = '
                <div style="background: #f0f9ff; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #0ea5e9;">
                    <h3 style="color: #0369a1; margin: 0 0 10px 0; font-size: 16px;">📄 Comprobante de Pago</h3>
                    <p style="margin: 10px 0;">Tu pago ha sido procesado correctamente.</p>
                    <a href="' . esc_url($data['voucher_url']) . '" 
                       style="display: inline-block; background: #0ea5e9; color: white; padding: 12px 24px; 
                              text-decoration: none; border-radius: 6px; margin-top: 10px; font-weight: bold;">
                        Descargar Voucher de Pago
                    </a>
                </div>';
        }

        // This part needs to be adjusted to handle the voucher_button specifically for rental_confirmation
        // It's currently using voucher_section which might not be the intended replacement
        // For rental_confirmation, a button is expected within the new template.
        $voucher_button_html = '';
        if ($template_name === 'rental_confirmation' && !empty($data['voucher_url'])) {
            $voucher_button_html = '
                <a href="' . esc_url($data['voucher_url']) . '" 
                   style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; 
                          text-decoration: none; border-radius: 8px; margin-top: 20px; font-weight: bold; font-size: 16px;">
                    Ver Comprobante de Pago
                </a>';
        }
        
        $template = str_replace('{voucher_section}', $voucher_section, $template);
        $template = str_replace('{voucher_button}', $voucher_button_html, $template);
        
        foreach ($data as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        
        return $template;
    }
    
    /**
     * Send reminder notifications (to be called by cron)
     */
    public static function send_appointment_reminders() {
        global $wpdb;
        $table = $wpdb->prefix . 'mas_appointments';
        
        // Get appointments for tomorrow
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        $appointments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE appointment_date = %s AND status = 'scheduled'",
            $tomorrow
        ));
        
        foreach ($appointments as $appointment) {
            $to = $appointment->patient_email;
            $subject = 'Recordatorio de Cita';
            
            $message = self::get_email_template('appointment_confirmation', array(
                'patient_name' => $appointment->patient_name,
                'appointment_date' => date('d/m/Y', strtotime($appointment->appointment_date)),
                'appointment_time' => date('H:i', strtotime($appointment->appointment_time)),
                'status' => 'Confirmada'
            ));
            
            $headers = array('Content-Type: text/html; charset=UTF-8');
            wp_mail($to, $subject, $message, $headers);
        }
    }
}
