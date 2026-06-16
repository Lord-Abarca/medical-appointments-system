<?php
/**
 * Formulario público de arriendo de boxes
 * Shortcode: [mas_box_rental]
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verificar si el usuario está logueado
if (!is_user_logged_in()) {
    echo '<div class="mas-login-required">';
    echo '<p>Debe <a href="' . wp_login_url(get_permalink()) . '">iniciar sesión</a> para arrendar un box.</p>';
    echo '</div>';
    return;
}

// Cargar boxes disponibles
global $wpdb;
$boxes_table = $wpdb->prefix . 'mas_boxes';
$boxes = $wpdb->get_results("SELECT * FROM $boxes_table WHERE status = 'active' ORDER BY box_name ASC");
?>

<div class="mas-rental-form-wrapper">
    <div class="mas-wizard-container">
        <!-- Improved header with modern design -->
        <div class="mas-wizard-header-modern">
            <div class="mas-wizard-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 9L12 2L21 9V20C21 20.5304 20.7893 21.0391 20.4142 21.4142C20.0391 21.7893 19.5304 22 19 22H5C4.46957 22 3.96086 21.7893 3.58579 21.4142C3.21071 21.0391 3 20.5304 3 20V9Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M9 22V12H15V22" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h2 class="mas-wizard-title">Arrendar Box de Atención</h2>
            <p class="mas-wizard-subtitle">Seleccione box, fecha y horarios para su arriendo</p>
        </div>
        
        <?php if (empty($boxes)): ?>
            <div class="mas-empty-state">
                <svg width="100" height="100" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                    <path d="M12 8V12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M12 16H12.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <p class="mas-no-boxes">No hay boxes disponibles en este momento.</p>
            </div>
        <?php else: ?>
        
        <!-- Improved form with modern multi-step design -->
        <form id="mas-rental-form" class="mas-form-modern">
            <div class="mas-form-card">
                <div class="mas-form-section">
                    <h3 class="mas-section-title">Seleccionar Box</h3>
                    
                    <div class="mas-form-field mas-full-width">
                        <label for="box_id" class="mas-label-modern">
                            Box <span class="required">*</span>
                        </label>
                        <div class="mas-input-wrapper">
                            <svg class="mas-input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M3 9L12 2L21 9V20C21 20.5304 20.7893 21.0391 20.4142 21.4142C20.0391 21.7893 19.5304 22 19 22H5C4.46957 22 3.96086 21.7893 3.58579 21.4142C3.21071 21.0391 3 20.5304 3 20V9Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <select id="box_id" name="box_id" class="mas-input-modern" required>
                                <option value="">Seleccione un box</option>
                                <?php foreach ($boxes as $box): ?>
                                    <option value="<?php echo esc_attr($box->id); ?>" 
                                            data-price="<?php echo esc_attr($box->price_per_hour); ?>"
                                            data-name="<?php echo esc_attr($box->box_name); ?>">
                                        <?php echo esc_html($box->box_name); ?> - 
                                        $<?php echo number_format($box->price_per_hour, 0, ',', '.'); ?>/hora
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="mas-form-section">
                    <h3 class="mas-section-title">Fecha y Horarios</h3>
                    
                    <div class="mas-form-field">
                        <label for="rental_date" class="mas-label-modern">
                            Fecha <span class="required">*</span>
                        </label>
                        <div class="mas-input-wrapper">
                            <svg class="mas-input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M19 4H5C3.89543 4 3 4.89543 3 6V20C3 21.1046 3.89543 22 5 22H19C20.1046 22 21 21.1046 21 20V6C21 4.89543 20.1046 4 19 4Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M16 2V6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M8 2V6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M3 10H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <input type="date" id="rental_date" name="rental_date" class="mas-input-modern"
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mas-form-field mas-full-width" id="mas-promotions-field" style="display: none;">
                        <label for="promotion_id" class="mas-label-modern">
                            <svg class="mas-input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Promoción disponible
                        </label>
                        <div class="mas-input-wrapper">
                            <select id="promotion_id" name="promotion_id" class="mas-input-modern">
                                <option value="">Sin promoción (precio normal)</option>
                            </select>
                        </div>
                        <p class="mas-help-text-modern" id="promotion-description"></p>
                    </div>
                    
                    <div class="mas-form-field mas-full-width">
                        <label class="mas-label-modern">Horarios Disponibles <span class="required">*</span></label>
                        <p class="mas-help-text-modern">Seleccione uno o varios horarios haciendo clic en ellos</p>
                        
                        <div id="mas-available-slots-container" class="mas-slots-modern-container" style="display: none;">
                            <div id="mas-time-slots" class="mas-time-slots-modern"></div>
                            <div class="mas-selection-summary">
                                <div class="mas-summary-item">
                                    <span class="mas-summary-label">Horarios seleccionados:</span>
                                    <span class="mas-summary-value" id="mas-selected-count">0</span>
                                </div>
                                <div class="mas-summary-item" id="mas-promotion-discount" style="display: none;">
                                    <span class="mas-summary-label">Descuento:</span>
                                    <span class="mas-summary-value mas-summary-discount">-$<span id="mas-discount-amount">0</span></span>
                                </div>
                                <div class="mas-summary-item">
                                    <span class="mas-summary-label">Total a pagar:</span>
                                    <span class="mas-summary-value mas-summary-price">$<span id="mas-total-amount">0</span></span>
                                </div>
                            </div>
                        </div>
                        
                        <div id="mas-loading-rental-slots" class="mas-loading-indicator" style="display: none;">
                            <span class="mas-spinner"></span> Cargando horarios disponibles...
                        </div>
                        
                        <div id="mas-no-slots-message" class="mas-info-message-modern" style="display: none;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                <path d="M12 8V12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M12 16H12.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                            No hay horarios disponibles para esta fecha.
                        </div>
                    </div>
                    
                    <div class="mas-form-field mas-full-width">
                        <label for="rental_notes" class="mas-label-modern">Notas</label>
                        <textarea id="rental_notes" name="rental_notes" class="mas-textarea-modern" rows="3" 
                                  placeholder="Comentarios adicionales (opcional)"></textarea>
                    </div>
                </div>
                
                <input type="hidden" id="selected_time_slots" name="selected_time_slots">
                <input type="hidden" id="selected_promotion_id" name="selected_promotion_id" value="">
                
                <div class="mas-form-actions-modern">
                    <button type="submit" class="mas-btn-modern mas-btn-primary-modern" id="mas-submit-rental" disabled>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <path d="M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M12 5L19 12L12 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Proceder al Pago
                    </button>
                </div>
                
                <div id="mas-rental-message" class="mas-message-modern" style="display: none;"></div>
            </div>
        </form>
        
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    let selectedSlots = [];
    let pricePerHour = 0;
    let currentBoxId = null;
    let activePromotions = [];
    let selectedPromotion = null;
    let regularPrice = 0;
    let discountAmount = 0;
    
    // Cuando se selecciona un box
    $('#box_id').on('change', function() {
        currentBoxId = $(this).val();
        const $selected = $(this).find(':selected');
        pricePerHour = parseFloat($selected.data('price')) || 0;
        
        selectedSlots = [];
        selectedPromotion = null;
        $('#promotion_id').val('');
        $('#mas-promotions-field').hide();
        updateSelectedInfo();
        
        const date = $('#rental_date').val();
        if (date && currentBoxId) {
            loadActivePromotions();
            loadAvailableSlots(currentBoxId, date);
        }
    });
    
    // Cuando se selecciona una fecha
    $('#rental_date').on('change', function() {
        const date = $(this).val();
        
        if (!currentBoxId) {
            alert('Por favor seleccione un box primero');
            return;
        }
        
        loadActivePromotions();
        loadAvailableSlots(currentBoxId, date);
    });
    
    // Cuando se selecciona una promoción
    $('#promotion_id').on('change', function() {
        const promotionId = $(this).val();
        
        if (promotionId) {
            selectedPromotion = activePromotions.find(p => p.id == promotionId);
        } else {
            selectedPromotion = null;
        }
        
        updateSelectedInfo();
    });
    
    // Cargar promociones activas
    function loadActivePromotions() {
        $.ajax({
            url: masPublic.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mas_get_active_promotions',
                nonce: masPublic.nonce
            },
            success: function(response) {
                if (response.success && response.data.promotions && response.data.promotions.length > 0) {
                    activePromotions = response.data.promotions;
                    renderPromotionsDropdown(activePromotions);
                } else {
                    activePromotions = [];
                    $('#mas-promotions-field').hide();
                }
            }
        });
    }
    
    // Renderizar dropdown de promociones
    function renderPromotionsDropdown(promotions) {
        const $select = $('#promotion_id');
        $select.html('<option value="">Sin promoción (precio normal)</option>');
        
        promotions.forEach(function(promo) {
            const option = $('<option></option>')
                .val(promo.id)
                .text(`${promo.name} - ${promo.blocks_quantity} bloques por $${parseInt(promo.package_price).toLocaleString('es-CL')} (${Math.round(promo.discount_percentage)}% desc.)`);
            $select.append(option);
        });
        
        $('#mas-promotions-field').show();
    }
    
    function loadAvailableSlots(boxId, date) {
        const $container = $('#mas-available-slots-container');
        const $loading = $('#mas-loading-rental-slots');
        const $noSlots = $('#mas-no-slots-message');
        const $slotsGrid = $('#mas-time-slots');
        
        console.log('[v0] Cargando slots - boxId:', boxId, 'date:', date);
        console.log('[v0] URL AJAX:', masPublic.ajaxUrl);
        console.log('[v0] Nonce:', masPublic.nonce);
        
        $container.hide();
        $noSlots.hide();
        $loading.show();
        $slotsGrid.empty();
        selectedSlots = [];
        updateSelectedInfo();
        
        $.ajax({
            url: masPublic.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mas_get_box_available_slots',
                nonce: masPublic.nonce,
                box_id: boxId,
                date: date
            },
            success: function(response) {
                $loading.hide();
                
                if (response.success && response.data.slots && response.data.slots.length > 0) {
                    response.data.slots.forEach(function(slot) {
                        const $slotBtn = $('<button></button>')
                            .attr('type', 'button')
                            .addClass('mas-time-slot')
                            .attr('data-time', slot.time)
                            .text(slot.formatted)
                            .on('click', function(e) {
                                e.preventDefault();
                                toggleSlot($(this));
                            });
                        
                        $slotsGrid.append($slotBtn);
                    });
                    
                    $container.show();
                } else {
                    $noSlots.show();
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $loading.hide();
                alert('Error al cargar horarios disponibles');
            }
        });
    }
    
    function toggleSlot($btn) {
        const time = $btn.data('time');
        
        if ($btn.hasClass('selected')) {
            $btn.removeClass('selected');
            selectedSlots = selectedSlots.filter(t => t !== time);
        } else {
            $btn.addClass('selected');
            selectedSlots.push(time);
        }
        
        updateSelectedInfo();
    }
    
    function updateSelectedInfo() {
        const count = selectedSlots.length;
        regularPrice = count * pricePerHour;
        let finalPrice = regularPrice;
        discountAmount = 0;
        
        if (selectedPromotion && count >= selectedPromotion.blocks_quantity) {
            const fullPackages = Math.floor(count / selectedPromotion.blocks_quantity);
            const remainingBlocks = count % selectedPromotion.blocks_quantity;
            
            const packageTotal = fullPackages * parseFloat(selectedPromotion.package_price);
            const remainingTotal = remainingBlocks * pricePerHour;
            
            finalPrice = packageTotal + remainingTotal;
            discountAmount = regularPrice - finalPrice;
        }
        
        $('#mas-selected-count').text(count);
        $('#mas-total-amount').text(Math.round(finalPrice).toLocaleString('es-CL'));
        
        if (discountAmount > 0) {
            $('#mas-discount-amount').text(Math.round(discountAmount).toLocaleString('es-CL'));
            $('#mas-promotion-discount').show();
        } else {
            $('#mas-promotion-discount').hide();
        }
        
        $('#selected_time_slots').val(selectedSlots.join(','));
        $('#selected_promotion_id').val(selectedPromotion ? selectedPromotion.id : '');
        $('#mas-submit-rental').prop('disabled', count === 0);
    }
    
    // Enviar formulario
    $('#mas-rental-form').on('submit', function(e) {
        e.preventDefault();
        
        if (selectedSlots.length === 0) {
            alert('Por favor seleccione al menos un horario');
            return;
        }
        
        const $submitBtn = $('#mas-submit-rental');
        const $message = $('#mas-rental-message');
        
        $submitBtn.prop('disabled', true).text('Procesando...');
        $message.hide();
        
        $.ajax({
            url: masPublic.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mas_create_multiple_rentals',
                nonce: masPublic.nonce,
                box_id: currentBoxId,
                rental_date: $('#rental_date').val(),
                time_slots: selectedSlots.join(','),
                notes: $('#rental_notes').val(),
                promotion_id: selectedPromotion ? selectedPromotion.id : '',
                blocks_used: selectedSlots.length,
                discount_applied: discountAmount
            },
            success: function(response) {
                if (response.success && response.data.init_point) {
                    window.location.href = response.data.init_point;
                } else {
                    $message
                        .removeClass('mas-message-success')
                        .addClass('mas-message-error')
                        .html(response.data.message || 'Error al procesar el arriendo')
                        .show();
                    
                    $submitBtn.prop('disabled', false).text('Proceder al Pago');
                }
            },
            error: function() {
                $message
                    .removeClass('mas-message-success')
                    .addClass('mas-message-error')
                    .html('Error de conexión. Por favor intente nuevamente.')
                    .show();
                
                $submitBtn.prop('disabled', false).text('Proceder al Pago');
            }
        });
    });
});
</script>
