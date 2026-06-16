<?php
/**
 * Formulario público de reserva de citas
 * Shortcode: [mas_appointment_form]
 */

if (!defined('ABSPATH')) {
    exit;
}

// Cargar servicios disponibles
global $wpdb;
$services_table = $wpdb->prefix . 'mas_services';
$services = $wpdb->get_results("SELECT * FROM $services_table WHERE status = 'active' ORDER BY service_name ASC");

$settings = get_option('mas_settings', array());
$require_rut = isset($settings['require_rut']) ? (bool)$settings['require_rut'] : false;
$allow_professional_selection = isset($settings['allow_patient_professional_selection']) ? (bool)$settings['allow_patient_professional_selection'] : false;
?>

<div class="mas-appointment-form-wrapper">
    <div class="mas-wizard-container">
        <!-- Improved header with modern design -->
        <div class="mas-wizard-header-modern">
            <div class="mas-wizard-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 3H5C3.89543 3 3 3.89543 3 5V19C3 20.1046 3.89543 21 5 21H19C20.1046 21 21 20.1046 21 19V5C21 3.89543 20.1046 3 19 3Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M16 2V6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M8 2V6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M3 10H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h2 class="mas-wizard-title">Reserva tu Cita</h2>
            <p class="mas-wizard-subtitle">Complete el formulario para agendar su atención</p>
        </div>
        
        <!-- Improved form container with modern styling -->
        <form id="mas-appointment-form" class="mas-form-modern" data-require-rut="<?php echo $require_rut ? '1' : '0'; ?>" data-professional-selection="<?php echo $allow_professional_selection ? '1' : '0'; ?>">
            <div class="mas-form-card">
                <div class="mas-form-section">
                    <h3 class="mas-section-title">Información Personal</h3>
                    
                    <div class="mas-form-grid">
                        <div class="mas-form-field">
                            <label for="patient_name" class="mas-label-modern">
                                Nombre Completo <span class="required">*</span>
                            </label>
                            <div class="mas-input-wrapper">
                                <svg class="mas-input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                                    <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M12 11C14.2091 11 16 9.20914 16 7C16 4.79086 14.2091 3 12 3C9.79086 3 8 4.79086 8 7C8 9.20914 9.79086 11 12 11Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <input type="text" id="patient_name" name="patient_name" class="mas-input-modern" placeholder="Ingrese su nombre completo" required>
                            </div>
                        </div>
                        
                        <div class="mas-form-field">
                            <label for="patient_email" class="mas-label-modern">
                                Email <span class="required">*</span>
                            </label>
                            <div class="mas-input-wrapper">
                                <svg class="mas-input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                                    <path d="M4 4H20C21.1 4 22 4.9 22 6V18C22 19.1 21.1 20 20 20H4C2.9 20 2 19.1 2 18V6C2 4.9 2.9 4 4 4Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M22 6L12 13L2 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <input type="email" id="patient_email" name="patient_email" class="mas-input-modern" placeholder="correo@ejemplo.com" required>
                            </div>
                        </div>
                        
                        <div class="mas-form-field">
                            <label for="patient_phone" class="mas-label-modern">
                                Teléfono <span class="required">*</span>
                            </label>
                            <div class="mas-input-wrapper">
                                <svg class="mas-input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                                    <path d="M22 16.92V19.92C22.0011 20.1985 21.9441 20.4742 21.8325 20.7293C21.7209 20.9845 21.5573 21.2136 21.3521 21.4019C21.1468 21.5901 20.9046 21.7335 20.6407 21.8227C20.3769 21.9119 20.0974 21.9451 19.82 21.92C16.7428 21.5856 13.7869 20.5341 11.19 18.85C8.77382 17.3147 6.72533 15.2662 5.18999 12.85C3.49997 10.2412 2.44824 7.27099 2.11999 4.18C2.095 3.90347 2.12787 3.62476 2.21649 3.36162C2.30512 3.09849 2.44756 2.85669 2.63476 2.65162C2.82196 2.44655 3.0498 2.28271 3.30379 2.17052C3.55777 2.05833 3.83233 2.00026 4.10999 2H7.10999C7.5953 1.99522 8.06579 2.16708 8.43376 2.48353C8.80173 2.79999 9.04207 3.23945 9.10999 3.72C9.23662 4.68007 9.47144 5.62273 9.80999 6.53C9.94454 6.88792 9.97366 7.27691 9.8939 7.65088C9.81415 8.02485 9.62886 8.36811 9.35999 8.64L8.08999 9.91C9.51355 12.4135 11.5864 14.4864 14.09 15.91L15.36 14.64C15.6319 14.3711 15.9751 14.1858 16.3491 14.1061C16.7231 14.0263 17.1121 14.0555 17.47 14.19C18.3773 14.5286 19.3199 14.7634 20.28 14.89C20.7658 14.9585 21.2094 15.2032 21.5265 15.5775C21.8437 15.9518 22.0122 16.4296 22 16.92Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <input type="tel" id="patient_phone" name="patient_phone" class="mas-input-modern" placeholder="+56 9 1234 5678" required>
                            </div>
                        </div>
                        
                        <div class="mas-form-field">
                            <label for="patient_rut" class="mas-label-modern">
                                RUT <?php echo $require_rut ? '<span class="required">*</span>' : '(opcional)'; ?>
                            </label>
                            <div class="mas-input-wrapper">
                                <svg class="mas-input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                                    <path d="M14 2H6C5.46957 2 4.96086 2.21071 4.58579 2.58579C4.21071 2.96086 4 3.46957 4 4V20C4 20.5304 4.21071 21.0391 4.58579 21.4142C4.96086 21.7893 5.46957 22 6 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V8L14 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M14 2V8H20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <input type="text" id="patient_rut" name="patient_rut" class="mas-input-modern mas-rut-input" placeholder="12.345.678-9" <?php echo $require_rut ? 'required' : ''; ?>>
                            </div>
                        </div>
                        
                        <div class="mas-form-field">
                            <label for="health_insurance" class="mas-label-modern">
                                Previsión de Salud <span class="required">*</span>
                            </label>
                            <div class="mas-input-wrapper">
                                <svg class="mas-input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                                    <path d="M22 12H18L15 21L9 3L6 12H2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M22 6L12 13L2 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <select id="health_insurance" name="health_insurance" class="mas-input-modern" required>
                                    <option value="">Seleccione su previsión</option>
                                    <option value="isapre">Isapre</option>
                                    <option value="fonasa">Fonasa</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mas-form-section">
                    <h3 class="mas-section-title">Detalles de la Cita</h3>
                    
                    <div class="mas-form-grid">
                        <div class="mas-form-field mas-full-width">
                            <label for="service_id" class="mas-label-modern">
                                Servicio <span class="required">*</span>
                            </label>
                            <div class="mas-input-wrapper">
                                <svg class="mas-input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                                    <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M12 16V12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M12 8H12.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <select id="service_id" name="service_id" class="mas-input-modern" required>
                                    <option value="">Seleccione un servicio</option>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?php echo esc_attr($service->id); ?>" 
                                                data-price="<?php echo esc_attr($service->price); ?>"
                                                data-duration="<?php echo esc_attr($service->duration); ?>">
                                            <?php echo esc_html($service->service_name); ?> - 
                                            $<?php echo number_format($service->price, 0, ',', '.'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <?php if ($allow_professional_selection): ?>
                        <div class="mas-form-field mas-full-width" id="professional-field">
                            <label for="professional_id" class="mas-label-modern">
                                Profesional <span class="required">*</span>
                            </label>
                            <div class="mas-input-wrapper">
                                <svg class="mas-input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                                    <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M12 11C14.2091 11 16 9.20914 16 7C16 4.79086 14.2091 3 12 3C9.79086 3 8 4.79086 8 7C8 9.20914 9.79086 11 12 11Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <select id="professional_id" name="professional_id" class="mas-input-modern" required disabled>
                                    <option value="">Primero seleccione un servicio</option>
                                </select>
                            </div>
                            <div id="mas-loading-professionals" class="mas-loading-indicator" style="display: none;">
                                <span class="mas-spinner"></span> Cargando profesionales...
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mas-form-field">
                            <label for="appointment_date" class="mas-label-modern">
                                Fecha de la Cita <span class="required">*</span>
                            </label>
                            <div class="mas-input-wrapper">
                                <svg class="mas-input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                                    <path d="M19 4H5C3.89543 4 3 4.89543 3 6V20C3 21.1046 3.89543 22 5 22H19C20.1046 22 21 21.1046 21 20V6C21 4.89543 20.1046 4 19 4Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M16 2V6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M8 2V6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M3 10H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <input type="date" id="appointment_date" name="appointment_date" class="mas-input-modern"
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mas-form-field">
                            <label for="appointment_time" class="mas-label-modern">
                                Hora Disponible <span class="required">*</span>
                            </label>
                            <div class="mas-input-wrapper">
                                <svg class="mas-input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M12 6V12L16 14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <select id="appointment_time" name="appointment_time" class="mas-input-modern" required disabled>
                                    <option value="">Primero seleccione una fecha</option>
                                </select>
                            </div>
                            <div id="mas-loading-slots" class="mas-loading-indicator" style="display: none;">
                                <span class="mas-spinner"></span> Cargando horarios disponibles...
                            </div>
                        </div>
                        
                        <div class="mas-form-field mas-full-width">
                            <label for="notes" class="mas-label-modern">Notas o Comentarios</label>
                            <textarea id="notes" name="notes" class="mas-textarea-modern" rows="3" 
                                      placeholder="¿Hay algo que debamos saber?"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="mas-form-actions-modern">
                    <button type="submit" class="mas-btn-modern mas-btn-primary-modern" id="mas-submit-appointment">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <path d="M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M12 5L19 12L12 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Reservar Cita
                    </button>
                </div>
                
                <div id="mas-appointment-message" class="mas-message-modern" style="display: none;"></div>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    let selectedService = null;
    const requireRut = $('#mas-appointment-form').data('require-rut') === 1;
    const allowProfessionalSelection = $('#mas-appointment-form').data('professional-selection') === 1;
    let isSubmitting = false;
    
    // Cuando se selecciona un servicio
    $('#service_id').on('change', function() {
        selectedService = $(this).find(':selected');
        
        // Si está habilitada la selección de profesional, cargar profesionales por servicio
        if (allowProfessionalSelection) {
            const serviceId = $(this).val();
            const $professionalSelect = $('#professional_id');
            const $loading = $('#mas-loading-professionals');
            const $dateInput = $('#appointment_date');
            const $timeSelect = $('#appointment_time');
            
            // Resetear campos dependientes
            $dateInput.val('');
            $timeSelect.prop('disabled', true).html('<option value="">Primero seleccione profesional y fecha</option>');
            
            if (!serviceId) {
                $professionalSelect.prop('disabled', true).html('<option value="">Primero seleccione un servicio</option>');
                return;
            }
            
            $professionalSelect.prop('disabled', true).html('<option value="">Cargando...</option>');
            $loading.show();
            
            $.ajax({
                url: masPublic.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mas_get_professionals_by_service',
                    nonce: masPublic.nonce,
                    service_id: serviceId
                },
                success: function(response) {
                    $loading.hide();
                    
                    if (response.success && response.data.length > 0) {
                        $professionalSelect.html('<option value="">Seleccione un profesional</option>');
                        
                        response.data.forEach(function(professional) {
                            $professionalSelect.append(
                                $('<option></option>')
                                    .attr('value', professional.id)
                                    .text(professional.name + (professional.specialty ? ' - ' + professional.specialty : ''))
                            );
                        });
                        
                        $professionalSelect.prop('disabled', false);
                    } else {
                        $professionalSelect.html('<option value="">No hay profesionales disponibles para este servicio</option>');
                    }
                },
                error: function() {
                    $loading.hide();
                    $professionalSelect.html('<option value="">Error al cargar profesionales</option>');
                }
            });
        }
    });
    
    // Cuando se selecciona un profesional (solo si está habilitada la opción)
    if (allowProfessionalSelection) {
        $('#professional_id').on('change', function() {
            const professionalId = $(this).val();
            const $dateInput = $('#appointment_date');
            const $timeSelect = $('#appointment_time');
            
            // Resetear campos dependientes
            $dateInput.val('');
            $timeSelect.prop('disabled', true).html('<option value="">Primero seleccione una fecha</option>');
            
            if (!professionalId) {
                return;
            }
            
            // El campo de fecha ya está disponible, el usuario puede seleccionar
            // La validación de días disponibles se hará al cargar los slots
        });
    }
    
    // Cuando se selecciona una fecha
    $('#appointment_date').on('change', function() {
        const date = $(this).val();
        const $timeSelect = $('#appointment_time');
        const $loading = $('#mas-loading-slots');
        
        if (!date) return;
        
        // Si la selección de profesional está habilitada, verificar que haya un profesional seleccionado
        let professionalId = null;
        if (allowProfessionalSelection) {
            professionalId = $('#professional_id').val();
            if (!professionalId) {
                $timeSelect.html('<option value="">Primero seleccione un profesional</option>');
                return;
            }
        }
        
        $timeSelect.prop('disabled', true).html('<option value="">Cargando...</option>');
        $loading.show();
        
        // Preparar datos para la petición
        let requestData = {
            action: 'mas_get_available_slots',
            nonce: masPublic.nonce,
            date: date
        };
        
        // Si hay selección de profesional, usar el endpoint específico
        if (allowProfessionalSelection && professionalId) {
            requestData.action = 'mas_get_professional_available_slots';
            requestData.professional_id = professionalId;
        }
        
        $.ajax({
            url: masPublic.ajaxUrl,
            type: 'POST',
            data: requestData,
            success: function(response) {
                $loading.hide();
                
                if (response.success && response.data.length > 0) {
                    $timeSelect.html('<option value="">Seleccione un horario</option>');
                    
                    response.data.forEach(function(slot) {
                        $timeSelect.append(
                            $('<option></option>')
                                .attr('value', slot.time)
                                .text(slot.formatted)
                        );
                    });
                    
                    $timeSelect.prop('disabled', false);
                } else {
                    $timeSelect.html('<option value="">No hay horarios disponibles para esta fecha</option>');
                }
            },
            error: function() {
                $loading.hide();
                $timeSelect.html('<option value="">Error al cargar horarios</option>');
            }
        });
    });
    
    // Enviar formulario
    $('#mas-appointment-form').on('submit', function(e) {
        e.preventDefault();
        
        if (isSubmitting) {
            console.log('[v0] Envío ya en proceso, ignorando doble clic');
            return false;
        }
        
        const $form = $(this);
        const $submitBtn = $('#mas-submit-appointment');
        const $message = $('#mas-appointment-message');
        
        const rutValue = $('#patient_rut').val().trim();
        if (requireRut && !rutValue) {
            showMessage('El RUT es requerido', 'error');
            return;
        }
        
        // Validar formato de RUT solo si se ingresó un valor
        if (rutValue && !validateRUT(rutValue)) {
            showMessage('El formato del RUT no es válido', 'error');
            return;
        }
        
        // Validar que se haya seleccionado un servicio
        if (!$('#service_id').val()) {
            showMessage('Por favor seleccione un servicio', 'error');
            return;
        }
        
        // Validar que se haya seleccionado un profesional (si la opción está habilitada)
        if (allowProfessionalSelection && !$('#professional_id').val()) {
            showMessage('Por favor seleccione un profesional', 'error');
            return;
        }
        
        // Validar que se haya seleccionado un horario
        if (!$('#appointment_time').val()) {
            showMessage('Por favor seleccione un horario', 'error');
            return;
        }
        
        if (!$('#health_insurance').val()) {
            showMessage('Por favor seleccione su previsión de salud', 'error');
            return;
        }
        
        isSubmitting = true;
        $submitBtn.prop('disabled', true).text('Procesando...');
        $message.hide();
        
        // Enviar datos directamente, no dentro de un objeto anidado
        const formData = {
            action: 'mas_create_appointment_preference',
            nonce: masPublic.nonce,
            patient_name: $('#patient_name').val(),
            patient_email: $('#patient_email').val(),
            patient_phone: $('#patient_phone').val(),
            patient_rut: rutValue,
            service_id: $('#service_id').val(),
            professional_id: allowProfessionalSelection ? ($('#professional_id').val() || 0) : 0,
            appointment_date: $('#appointment_date').val(),
            appointment_time: $('#appointment_time').val(),
            health_insurance: $('#health_insurance').val(),
            notes: $('#notes').val()
        };
        
        console.log('[v0] Enviando datos de cita:', formData);
        
        $.ajax({
            url: masPublic.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                console.log('[v0] Respuesta del servidor:', response);
                
                if (response.success && response.data.init_point) {
                    // Redirigir a MercadoPago
                    console.log('[v0] Redirigiendo a MercadoPago:', response.data.init_point);
                    window.location.href = response.data.init_point;
                } else {
                    isSubmitting = false;
                    showMessage(response.data.message || 'Error al crear la preferencia de pago', 'error');
                    $submitBtn.prop('disabled', false).text('Reservar Cita');
                }
            },
            error: function(xhr, status, error) {
                console.error('[v0] Error AJAX:', error);
                isSubmitting = false;
                showMessage('Error de conexión. Por favor intente nuevamente.', 'error');
                $submitBtn.prop('disabled', false).text('Reservar Cita');
            }
        });
    });
    
    function showMessage(message, type) {
        const $message = $('#mas-appointment-message');
        $message
            .removeClass('mas-message-success mas-message-error')
            .addClass('mas-message-' + type)
            .html(message)
            .show();
        
        $('html, body').animate({
            scrollTop: $message.offset().top - 100
        }, 500);
    }
    
    function validateRUT(rut) {
        rut = rut.replace(/\./g, '').replace(/-/g, '');
        
        if (rut.length < 2) return false;
        
        const body = rut.slice(0, -1);
        const dv = rut.slice(-1).toUpperCase();
        
        let suma = 0;
        let multiplo = 2;
        
        for (let i = body.length - 1; i >= 0; i--) {
            suma += parseInt(body.charAt(i)) * multiplo;
            multiplo = multiplo < 7 ? multiplo + 1 : 2;
        }
        
        let dvEsperado = 11 - (suma % 11);
        dvEsperado = dvEsperado === 11 ? '0' : dvEsperado === 10 ? 'K' : dvEsperado.toString();
        
        return dv === dvEsperado;
    }
    
    $('#patient_rut').on('input', function() {
        let value = $(this).val().replace(/\./g, '').replace(/-/g, '');
        if (value.length > 1) {
            const rut = value.slice(0, -1);
            const dv = value.slice(-1);
            $(this).val(rut.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + '-' + dv);
        }
    });
});
</script>
