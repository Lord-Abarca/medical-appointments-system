<?php
/**
 * Arrendar Box - Vista del profesional
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verificar que sea un colaborador
if (!current_user_can('edit_posts')) {
    wp_die(__('No tienes permisos suficientes para acceder a esta página.', 'medical-appointments'));
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Obtener profesional asociado
global $wpdb;
$professional = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}mas_professionals WHERE user_id = %d",
    $user_id
));

if (!$professional) {
    wp_die(__('No se encontró tu perfil de profesional. Contacta al administrador.', 'medical-appointments'));
}

// Obtener boxes disponibles
$mas_boxes = new MAS_Boxes();
$boxes = $mas_boxes->get_boxes('active');

$settings = get_option('mas_settings', array());
$min_date = date('Y-m-d');
$max_date = date('Y-m-d', strtotime('+' . ($settings['booking_advance_days'] ?? 30) . ' days'));
?>

<div class="wrap mas-rent-box-wrap">
    <div class="mas-page-header">
        <div class="mas-page-title-wrapper">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <h1><?php _e('Arrendar Box Médico', 'medical-appointments'); ?></h1>
        </div>
        <p class="mas-page-subtitle"><?php _e('Selecciona un box y los horarios que deseas arrendar', 'medical-appointments'); ?></p>
    </div>
    
    <div class="mas-rental-form-container-enhanced">
        <?php if (!empty($boxes)) : ?>
            <div class="mas-boxes-grid-enhanced">
                <?php foreach ($boxes as $box) : ?>
                    <div class="mas-box-card-enhanced" data-box-id="<?php echo esc_attr($box->id); ?>">
                        <div class="mas-box-image-wrapper">
                            <?php if ($box->image_url) : ?>
                                <img src="<?php echo esc_url($box->image_url); ?>" alt="<?php echo esc_attr($box->box_name); ?>" class="mas-box-image-enhanced">
                            <?php else : ?>
                                <div class="mas-box-placeholder-enhanced">
                                    <svg width="60" height="60" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                            <?php endif; ?>
                            <div class="mas-box-badge">Box N° <?php echo esc_html($box->box_number); ?></div>
                        </div>
                        
                        <div class="mas-box-content">
                            <h3 class="mas-box-title"><?php echo esc_html($box->box_name); ?></h3>
                            <p class="mas-box-description"><?php echo esc_html($box->description); ?></p>
                            
                            <div class="mas-box-price-card">
                                <div class="mas-price-wrapper">
                                    <span class="mas-price-amount">$<?php echo number_format($box->price_per_hour, 0, ',', '.'); ?></span>
                                    <span class="mas-price-period">/ hora</span>
                                </div>
                            </div>
                            
                            <button type="button" class="mas-select-box-btn-enhanced" data-box-id="<?php echo esc_attr($box->id); ?>" data-box-price="<?php echo esc_attr($box->price_per_hour); ?>" data-box-name="<?php echo esc_attr($box->box_name); ?>" data-box-number="<?php echo esc_attr($box->box_number); ?>">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <?php _e('Seleccionar Box', 'medical-appointments'); ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div id="mas-rental-booking-form-enhanced" style="display:none;">
                <div class="mas-booking-card">
                    <div class="mas-booking-header">
                        <h2><?php _e('Seleccionar Fecha y Horarios', 'medical-appointments'); ?></h2>
                        <p><?php _e('Elige los horarios disponibles para tu arriendo', 'medical-appointments'); ?></p>
                    </div>
                    
                    <div class="mas-form-section-box">
                        <label class="mas-label-enhanced">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php _e('Fecha de Arriendo', 'medical-appointments'); ?>
                        </label>
                        <input type="date" id="rental_date" class="mas-input-enhanced" min="<?php echo esc_attr($min_date); ?>" max="<?php echo esc_attr($max_date); ?>">
                    </div>
                    
                    <div class="mas-form-section-box" id="promotions-section" style="display:none;">
                        <label class="mas-label-enhanced">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php _e('Promoción', 'medical-appointments'); ?>
                        </label>
                        <select id="promotion_id" class="mas-input-enhanced">
                            <option value=""><?php _e('Sin promoción (precio normal)', 'medical-appointments'); ?></option>
                        </select>
                        <p class="mas-helper-text" id="promotion-info"></p>
                    </div>
                    
                    <div class="mas-form-section-box">
                        <label class="mas-label-enhanced">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php _e('Horarios Disponibles', 'medical-appointments'); ?>
                        </label>
                        <p class="mas-helper-text"><?php _e('Selecciona uno o varios horarios haciendo clic', 'medical-appointments'); ?></p>
                        <div id="available-slots" class="mas-slots-grid-enhancedd">
                            <p class="mas-info-message"><?php _e('Seleccione una fecha para ver los horarios disponibles', 'medical-appointments'); ?></p>
                        </div>
                    </div>
                    
                    <div class="mas-form-section-box">
                        <label class="mas-label-enhanced">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php _e('Notas (opcional)', 'medical-appointments'); ?>
                        </label>
                        <textarea id="rental_notes" class="mas-textarea-enhanced" rows="3" placeholder="<?php _e('Información adicional sobre el arriendo...', 'medical-appointments'); ?>"></textarea>
                    </div>
                    
                    <div class="mas-rental-summary-enhanced" style="display:none;">
                        <div class="mas-summary-header">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <h3><?php _e('Resumen del Arriendo', 'medical-appointments'); ?></h3>
                        </div>
                        <div class="mas-summary-content-enhanced">
                            <div class="mas-summary-row-enhanced">
                                <span class="mas-summary-label-enhanced"><?php _e('Box:', 'medical-appointments'); ?></span>
                                <span class="mas-summary-value-enhanced" id="summary-box"></span>
                            </div>
                            <div class="mas-summary-row-enhanced">
                                <span class="mas-summary-label-enhanced"><?php _e('Fecha:', 'medical-appointments'); ?></span>
                                <span class="mas-summary-value-enhanced" id="summary-date"></span>
                            </div>
                            <div class="mas-summary-row-enhanced">
                                <span class="mas-summary-label-enhanced"><?php _e('Horarios seleccionados:', 'medical-appointments'); ?></span>
                                <span class="mas-summary-value-enhanced" id="summary-slots">0</span>
                            </div>
                            <div class="mas-summary-row-enhanced" id="summary-promotion-row" style="display:none;">
                                <span class="mas-summary-label-enhanced"><?php _e('Promoción:', 'medical-appointments'); ?></span>
                                <span class="mas-summary-value-enhanced" id="summary-promotion"></span>
                            </div>
                            <div class="mas-summary-row-enhanced" id="summary-discount-row" style="display:none;">
                                <span class="mas-summary-label-enhanced"><?php _e('Descuento:', 'medical-appointments'); ?></span>
                                <span class="mas-summary-value-enhanced mas-text-success" id="summary-discount">$0</span>
                            </div>
                            <div class="mas-summary-row-enhanced mas-summary-total-enhanced">
                                <span class="mas-summary-label-enhanced"><?php _e('Total:', 'medical-appointments'); ?></span>
                                <span class="mas-summary-value-enhanced" id="summary-total">$0</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mas-form-actions-enhanced">
                        <button type="button" id="mas-back-to-boxes" class="mas-button-secondary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M15 19l-7-7 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php _e('Volver a Boxes', 'medical-appointments'); ?>
                        </button>
                        <button type="button" id="mas-proceed-to-payment" class="mas-button-primary-enhanced" style="display:none;">
                            <img src="https://http2.mlstatic.com/storage/logos-api-admin/a5f047d0-9be0-11ec-aad4-c3381f368aaf-m.svg" alt="Mercado Pago" style="height: 18px;">
                            <?php _e('Proceder al Pago', 'medical-appointments'); ?>
                        </button>
                    </div>
                </div>
            </div>
        <?php else : ?>
            <div class="mas-empty-state">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <p><?php _e('No hay boxes disponibles en este momento.', 'medical-appointments'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    let selectedBox = null;
    let selectedDate = null;
    let selectedSlots = [];
    let boxPrice = 0;
    let activePromotions = [];
    let selectedPromotion = null;
    window.availableBoxes = <?php echo json_encode($boxes); ?>;
    
    function restrictDatePicker() {
        const $dateInput = $('#rental_date');
        
        $dateInput.on('input change', function() {
            const selectedDate = $(this).val();
            if (selectedDate) {
                selectedSlots = [];
                selectedPromotion = null;
                $('#promotion_id').val('');
                $('.mas-rental-summary-enhanced').hide();
                $('#mas-proceed-to-payment').hide();
            }
        });
    }
    
    restrictDatePicker();
    
    $('.mas-select-box-btn-enhanced').on('click', function() {
        const boxId = $(this).data('box-id');
        const boxName = $(this).data('box-name');
        const boxNumber = $(this).data('box-number');
        
        selectedBox = {
            id: boxId,
            name: boxName + ' - N° ' + boxNumber
        };
        boxPrice = parseFloat($(this).data('box-price'));
        
        $('.mas-boxes-grid-enhanced').hide();
        $('#mas-rental-booking-form-enhanced').show();
        $('#summary-box').text(selectedBox.name);
        $('#box_id').val(boxId);
        loadActivePromotions();
    });
    
    $('#mas-back-to-boxes').on('click', function() {
        $('#mas-rental-booking-form-enhanced').hide();
        $('.mas-boxes-grid-enhanced').show();
        selectedBox = null;
        selectedDate = null;
        selectedSlots = [];
        selectedPromotion = null;
        $('#rental_date').val('');
        $('#rental_notes').val('');
        $('#promotion_id').val('');
        $('#promotions-section').hide();
        $('#available-slots').html('<p class="mas-info-message"><?php _e('Seleccione una fecha para ver los horarios disponibles', 'medical-appointments'); ?></p>');
        $('.mas-rental-summary-enhanced').hide();
        $('#mas-proceed-to-payment').hide();
    });
    
    $('#rental_date').on('change', function() {
        selectedDate = $(this).val();
        selectedSlots = [];
        selectedPromotion = null;
        $('#promotion_id').val('');
        
        if (selectedDate) {
            loadActivePromotions();
            loadAvailableSlots();
        }
    });
    
    $('#promotion_id').on('change', function() {
        const promotionId = $(this).val();
        
        if (promotionId) {
            selectedPromotion = activePromotions.find(p => p.id == promotionId);
            $('#promotion-info').text(selectedPromotion ? selectedPromotion.description : '');
        } else {
            selectedPromotion = null;
            $('#promotion-info').text('');
        }
        
        updateSummary();
    });
    
    function loadActivePromotions() {
        $.ajax({
            url: masAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mas_get_active_promotions',
                nonce: masAdmin.nonce
            },
            success: function(response) {
                if (response.success && response.data.promotions && response.data.promotions.length > 0) {
                    activePromotions = response.data.promotions;
                    renderPromotionsDropdown(activePromotions);
                } else {
                    activePromotions = [];
                    $('#promotions-section').hide();
                }
            }
        });
    }
    
    function renderPromotionsDropdown(promotions) {
        const $select = $('#promotion_id');
        $select.html('<option value=""><?php _e('Sin promoción (precio normal)', 'medical-appointments'); ?></option>');
        
        promotions.forEach(function(promo) {
            const option = $('<option></option>')
                .val(promo.id)
                .text(`${promo.name} - ${promo.blocks_quantity} bloques por $${parseInt(promo.package_price).toLocaleString('es-CL')} (${Math.round(promo.discount_percentage)}% desc.)`);
            $select.append(option);
        });
        
        $('#promotions-section').show();
    }
    
    function loadAvailableSlots() {
        console.log('[v0] === loadAvailableSlots called ===');
        
        const selectedDate = $('#rental_date').val();
        const selectedBoxId = $('#box_id').val();
        
        console.log('[v0] Date:', selectedDate, 'Box ID:', selectedBoxId);
        
        if (!selectedDate || !selectedBoxId) {
            console.log('[v0] Missing date or box, aborting');
            $('#available-slots').html('<div class="mas-info-message"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><?php _e('Por favor seleccione fecha y box', 'medical-appointments'); ?></div>');
            return;
        }
        
        if (typeof masAdmin === 'undefined') {
            console.error('[v0] ERROR: masAdmin is not defined!');
            $('#available-slots').html('<div class="mas-error-message">Error: masAdmin no está definido</div>');
            return;
        }
        
        console.log('[v0] masAdmin is defined:', masAdmin);
        
        const selectedBox = window.availableBoxes.find(b => b.id == selectedBoxId);
        
        if (!selectedBox) {
            console.log('[v0] Box not found in availableBoxes');
            $('#available-slots').html('<div class="mas-error-message"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><?php _e('Box no encontrado', 'medical-appointments'); ?></div>');
            return;
        }
        
        $('#available-slots').html('<div class="mas-loading"><svg class="mas-spinner" viewBox="0 0 50 50"><circle cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg><?php _e('Cargando horarios disponibles...', 'medical-appointments'); ?></div>');
        
        console.log('[v0] Making AJAX request with:', {
            action: 'mas_get_box_available_slots',
            date: selectedDate,
            box_id: selectedBox.id,
            nonce: masAdmin.nonce
        });
        
        $.ajax({
            url: masAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mas_get_box_available_slots',
                nonce: masAdmin.nonce,
                date: selectedDate,
                box_id: selectedBox.id
            },
            success: function(response) {
                console.log('[v0] ===AJAX SUCCESS===');
                console.log('[v0] Full response:', response);
                console.log('[v0] response.success:', response.success);
                console.log('[v0] response.data:', response.data);
                
                if (response.success) {
                    const slots = Array.isArray(response.data) ? response.data : (response.data.slots || []);
                    console.log('[v0] Slots extracted:', slots);
                    
                    if (response.data.blocked) {
                        console.log('[v0] Date is blocked');
                        $('#available-slots').html('<div class="mas-error-message"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' + (response.data.message || '<?php _e('Esta fecha no está disponible para arriendos', 'medical-appointments'); ?>') + '</div>');
                    } else if (slots && slots.length > 0) {
                        console.log('[v0] Rendering', slots.length, 'slots');
                        renderSlots(slots);
                    } else {
                        console.log('[v0] No slots available');
                        $('#available-slots').html('<div class="mas-info-message"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><?php _e('No hay horarios disponibles para esta fecha y box', 'medical-appointments'); ?></div>');
                    }
                } else {
                    console.log('[v0] AJAX error - response.success is false:', response);
                    $('#available-slots').html('<div class="mas-error-message"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' + (response.data && response.data.message ? response.data.message : '<?php _e('Error al cargar horarios', 'medical-appointments'); ?>') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.log('[v0] ===AJAX ERROR===');
                console.log('[v0] xhr:', xhr);
                console.log('[v0] status:', status);
                console.log('[v0] error:', error);
                console.log('[v0] responseText:', xhr.responseText);
                $('#available-slots').html('<div class="mas-error-message"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><?php _e('Error al cargar horarios. Intenta nuevamente.', 'medical-appointments'); ?></div>');
            }
        });
    }
    
    function renderSlots(slots) {
        let html = '<div class="mas-slots-grid-enhanced">';
        slots.forEach(function(slot) {
            html += `<button type="button" class="mas-slot-btn" data-time="${slot.time}">${slot.formatted}</button>`;
        });
        html += '</div>';
        $('#available-slots').html(html);
        
        $('.mas-slot-btn').on('click', function() {
            const time = $(this).data('time');
            
            if ($(this).hasClass('selected')) {
                $(this).removeClass('selected');
                selectedSlots = selectedSlots.filter(s => s !== time);
            } else {
                $(this).addClass('selected');
                selectedSlots.push(time);
            }
            
            updateSummary();
        });
    }
    
    function updateSummary() {
        if (selectedSlots.length > 0) {
            const regularPrice = selectedSlots.length * boxPrice;
            let finalPrice = regularPrice;
            let discountAmount = 0;
            
            if (selectedPromotion && selectedSlots.length >= selectedPromotion.blocks_quantity) {
                const fullPackages = Math.floor(selectedSlots.length / selectedPromotion.blocks_quantity);
                const remainingBlocks = selectedSlots.length % selectedPromotion.blocks_quantity;
                
                const packageTotal = fullPackages * parseFloat(selectedPromotion.package_price);
                const remainingTotal = remainingBlocks * boxPrice;
                
                finalPrice = packageTotal + remainingTotal;
                discountAmount = regularPrice - finalPrice;
            }
            
            $('#summary-slots').text(selectedSlots.length);
            $('#summary-date').text(new Date(selectedDate + 'T00:00:00').toLocaleDateString('es-CL'));
            $('#summary-total').text('$' + Math.round(finalPrice).toLocaleString('es-CL'));
            
            if (selectedPromotion && discountAmount > 0) {
                $('#summary-promotion').text(selectedPromotion.name);
                $('#summary-discount').text('-$' + Math.round(discountAmount).toLocaleString('es-CL'));
                $('#summary-promotion-row').show();
                $('#summary-discount-row').show();
            } else {
                $('#summary-promotion-row').hide();
                $('#summary-discount-row').hide();
            }
            
            $('.mas-rental-summary-enhanced').show();
            $('#mas-proceed-to-payment').show();
        } else {
            $('.mas-rental-summary-enhanced').hide();
            $('#mas-proceed-to-payment').hide();
        }
    }
    
    $('#mas-proceed-to-payment').on('click', function(e) {
        e.preventDefault();
        
        if (selectedSlots.length === 0) {
            alert('<?php _e('Por favor selecciona al menos un horario', 'medical-appointments'); ?>');
            return;
        }
        
        const $btn = $(this);
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="mas-spinner" style="display: inline-block; width: 16px; height: 16px; border: 2px solid #fff; border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite;"></span> <?php _e('Procesando...', 'medical-appointments'); ?>');
        
        let discountApplied = 0;
        let blocksUsed = selectedSlots.length;
        
        if (selectedPromotion && blocksUsed >= selectedPromotion.blocks_quantity) {
            const regularPrice = blocksUsed * boxPrice;
            const fullPackages = Math.floor(blocksUsed / selectedPromotion.blocks_quantity);
            const remainingBlocks = blocksUsed % selectedPromotion.blocks_quantity;
            const finalPrice = (fullPackages * parseFloat(selectedPromotion.package_price)) + (remainingBlocks * boxPrice);
            discountApplied = regularPrice - finalPrice;
        }
        
        $.ajax({
            url: masAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mas_create_multiple_rentals',
                nonce: masAdmin.nonce,
                box_id: selectedBox.id,
                professional_id: <?php echo $professional->id; ?>,
                user_id: <?php echo $user_id; ?>,
                rental_date: selectedDate,
                time_slots: selectedSlots.join(','),
                notes: $('#rental_notes').val(),
                promotion_id: selectedPromotion ? selectedPromotion.id : '',
                blocks_used: blocksUsed,
                discount_applied: discountApplied
            },
            success: function(response) {
                console.log('[v0] Payment response:', response);
                
                if (response.success && response.data.init_point) {
                    window.location.href = response.data.init_point;
                } else {
                    alert('<?php _e('Error:', 'medical-appointments'); ?> ' + (response.data && response.data.message ? response.data.message : '<?php _e('Error desconocido', 'medical-appointments'); ?>'));
                    $btn.prop('disabled', false).html(originalHtml);
                }
            },
            error: function(xhr, status, error) {
                console.log('[v0] Payment error:', {xhr: xhr, status: status, error: error});
                alert('<?php _e('Error al procesar el arriendo. Por favor intenta nuevamente.', 'medical-appointments'); ?>');
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    });
});
</script>

<style>
.mas-rental-form-container-enhanced {
    margin-top: 20px;
}

.mas-boxes-grid-enhanced {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.mas-box-card-enhanced {
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.3s;
}

.mas-box-card-enhanced:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.mas-box-image-wrapper {
    position: relative;
    width: 100%;
    height: 200px;
    overflow: hidden;
}

.mas-box-image-enhanced {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.mas-box-placeholder-enhanced {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
}

.mas-box-badge {
    position: absolute;
    bottom: 0;
    left: 0;
    background: rgba(0, 0, 0, 0.7);
    color: #fff;
    padding: 5px 10px;
    font-size: 14px;
    border-top-right-radius: 8px;
}

.mas-box-content {
    padding: 20px;
}

.mas-box-title {
    margin: 0 0 10px 0;
    font-size: 20px;
    color: #2c3e50;
}

.mas-box-description {
    color: #95a5a6;
    margin: 0 0 15px 0;
    font-size: 14px;
    line-height: 1.5;
}

.mas-box-price-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
    margin-bottom: 15px;
}

.mas-price-wrapper {
    display: flex;
    align-items: center;
}

.mas-price-amount {
    font-size: 24px;
    font-weight: bold;
    color: #27ae60;
    margin-right: 8px;
}

.mas-price-period {
    color: #7f8c8d;
    font-size: 14px;
}

.mas-select-box-btn-enhanced {
    width: 100%;
    padding: 12px;
    background: #3498db;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
}

.mas-select-box-btn-enhanced:hover {
    background: #2980b9;
}

#mas-rental-booking-form-enhanced {
    background: #fff;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

#mas-rental-booking-form-enhanced h2 {
    margin-top: 0;
    margin-bottom: 20px;
}

.mas-form-section-box {
    margin-bottom: 20px;
}

.mas-label-enhanced {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    font-weight: 600;
    color: #2c3e50;
}

.mas-label-enhanced svg {
    margin-right: 8px;
}

.mas-input-enhanced,
.mas-textarea-enhanced {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
}

.mas-slots-grid-enhanced {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 10px;
}

.mas-slot-btn {
    padding: 12px;
    background: #ecf0f1;
    border: 2px solid #bdc3c7;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
}

.mas-slot-btn:hover {
    background: #3498db;
    color: white;
    border-color: #3498db;
}

.mas-slot-btn.selected {
    background: #27ae60;
    color: white;
    border-color: #27ae60;
}

.mas-rental-summary-enhanced {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
}

.mas-summary-header {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.mas-summary-header svg {
    margin-right: 8px;
}

.mas-summary-content-enhanced {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}

.mas-summary-row-enhanced {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e0e0e0;
}

.mas-summary-row-enhanced.mas-summary-full {
    display: block;
    margin-bottom: 15px;
}

.mas-summary-label-enhanced {
    color: #7f8c8d;
    font-size: 14px;
}

.mas-summary-value-enhanced {
    font-size: 16px;
    font-weight: 600;
}

.mas-summary-total-enhanced {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 0;
    border-top: 2px solid #27ae60;
    font-size: 18px;
    color: #27ae60;
}

.mas-total-label {
    color: #2c3e50;
}

.mas-total-amount {
    font-weight: bold;
}

.mas-form-actions-enhanced {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}

.mas-button-secondary {
    padding: 12px 20px;
    background: #bdc3c7;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
}

.mas-button-secondary:hover {
    background: #a9b0b9;
}

.mas-button-primary-enhanced {
    padding: 12px 20px;
    background: #27ae60;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
}

.mas-button-primary-enhanced:hover {
    background: #229954;
}

.mas-info-message,
.mas-error-message {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px;
    border-radius: 6px;
    font-size: 14px;
}

.mas-info-message {
    background: #e8f4fd;
    color: #2980b9;
    border: 1px solid #bee5eb;
}

.mas-error-message {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.mas-info-message svg,
.mas-error-message svg {
    flex-shrink: 0;
}

.mas-loading {
    text-align: center;
    padding: 30px;
    color: #7f8c8d;
}

.mas-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.mas-empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

@media (max-width: 768px) {
    .mas-boxes-grid-enhanced {
        grid-template-columns: 1fr;
    }
}
</style>

<input type="hidden" id="box_id">
