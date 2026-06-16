<?php
/**
 * Calendario de Arriendos de Boxes - Mejorado
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die(__('No tienes permisos suficientes para acceder a esta página.', 'medical-appointments'));
}
?>

<div class="wrap mas-calendar-page">
    <h1><?php _e('Calendario de Arriendos de Boxes', 'medical-appointments'); ?></h1>
    <p class="description"><?php _e('Visualización mensual de todos los arriendos de boxes médicos', 'medical-appointments'); ?></p>
    
    <!-- Added legend for rental status colors -->
    <div class="mas-calendar-legend">
        <div class="mas-legend-item">
            <span class="mas-legend-color" style="background-color: #9b59b6;"></span>
            <span>Pendiente</span>
        </div>
        <div class="mas-legend-item">
            <span class="mas-legend-color" style="background-color: #27ae60;"></span>
            <span>Activo</span>
        </div>
        <div class="mas-legend-item">
            <span class="mas-legend-color" style="background-color: #e74c3c;"></span>
            <span>Cancelado</span>
        </div>
        <div class="mas-legend-item">
            <span class="mas-legend-color" style="background-color: #3498db;"></span>
            <span>Completado</span>
        </div>
        <div class="mas-legend-item">
            <span class="mas-legend-color" style="background-color: #f39c12;"></span>
            <span>Interno</span>
        </div>
    </div>
    
    <div id="mas-rentals-calendar" class="mas-fullcalendar-container"></div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.10/locales/es.global.min.js"></script>

<style>
.mas-calendar-page {
    max-width: 1400px;
}

/* Improved legend design matching appointments calendar */
.mas-calendar-legend {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin: 20px 0;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 8px;
    border: 1px solid #e5e5e5;
}

.mas-legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #23282d;
}

.mas-legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    border: 1px solid rgba(0,0,0,0.1);
}

/* Enhanced calendar container */
.mas-fullcalendar-container {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-top: 20px;
}

/* Improved event styling */
.fc-event {
    cursor: pointer;
    transition: all 0.2s ease;
    border-radius: 4px !important;
    padding: 2px 5px !important;
}

.fc-event:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.fc-event-pending {
    background-color: #9b59b6 !important;
    border-color: #8e44ad !important;
}

.fc-event-active {
    background-color: #27ae60 !important;
    border-color: #229954 !important;
}

.fc-event-cancelled {
    background-color: #e74c3c !important;
    border-color: #c0392b !important;
}

.fc-event-completed {
    background-color: #3498db !important;
    border-color: #2980b9 !important;
}

.fc-event-interno {
    background-color: #f39c12 !important;
    border-color: #e67e22 !important;
}

/* Modern modal design for rentals */
.mas-rental-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.mas-rental-modal-content {
    background: white;
    border-radius: 12px;
    max-width: 650px;
    width: 90%;
    max-height: 85vh;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from {
        transform: translateY(20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.mas-rental-modal-header {
    padding: 25px;
    border-bottom: 1px solid #e5e5e5;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
    color: white;
}

.mas-rental-modal-header h2 {
    margin: 0;
    font-size: 22px;
    font-weight: 600;
}

.mas-modal-close-btn {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    font-size: 28px;
    cursor: pointer;
    padding: 0;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.mas-modal-close-btn:hover {
    background: rgba(255,255,255,0.3);
    transform: rotate(90deg);
}

.mas-rental-modal-body {
    padding: 25px;
    overflow-y: auto;
    max-height: calc(85vh - 170px);
}

.mas-detail-row {
    display: flex;
    padding: 15px 0;
    border-bottom: 1px solid #f0f0f0;
}

.mas-detail-row:last-child {
    border-bottom: none;
}

.mas-detail-label {
    font-weight: 600;
    color: #23282d;
    width: 140px;
    flex-shrink: 0;
}

.mas-detail-value {
    color: #646970;
    flex: 1;
}

/* Enhanced status badges matching appointments */
.mas-status-badge {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.mas-status-pending {
    background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
    color: white;
}

.mas-status-active {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
}

.mas-status-cancelled {
    background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
    color: white;
}

.mas-status-completed {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
}

.mas-status-interno {
    background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
    color: white;
}

.mas-payment-pending {
    background: #ffeaa7;
    color: #d63031;
}

.mas-payment-paid {
    background: #55efc4;
    color: #00b894;
}

.mas-payment-approved {
    background: #55efc4;
    color: #00b894;
}

.mas-payment-failed {
    background: #ff7675;
    color: #d63031;
}

.mas-payment-rejected {
    background: #ff7675;
    color: #d63031;
}

.mas-rental-modal-footer {
    padding: 20px 25px;
    border-top: 1px solid #e5e5e5;
    background: #f9f9f9;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.mas-btn-primary {
    background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
    color: white;
    padding: 10px 24px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: all 0.2s;
}

.mas-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(155, 89, 182, 0.4);
    color: white;
}

.mas-price-highlight {
    font-size: 18px;
    font-weight: 700;
    color: #9b59b6;
}

/* Responsive design */
@media (max-width: 768px) {
    .mas-calendar-legend {
        gap: 10px;
    }
    
    .mas-legend-item {
        font-size: 12px;
    }
    
    .mas-detail-row {
        flex-direction: column;
        gap: 5px;
    }
    
    .mas-detail-label {
        width: 100%;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    var calendar;
    var calendarEl = document.getElementById('mas-rentals-calendar');
    
    calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'es',
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
        },
        buttonText: {
            today: 'Hoy',
            month: 'Mes',
            week: 'Semana',
            day: 'Día',
            list: 'Lista'
        },
        slotMinTime: '08:00:00',
        slotMaxTime: '20:00:00',
        allDaySlot: false,
        height: 'auto',
        slotEventOverlap: false,
        eventOverlap: false,
        displayEventEnd: true,
        events: function(info, successCallback, failureCallback) {
            $.ajax({
                url: masAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mas_get_rentals_calendar',
                    nonce: masAdmin.nonce,
                    start: info.startStr.split('T')[0],
                    end: info.endStr.split('T')[0]
                },
                success: function(response) {
                    if (response.success) {
                        successCallback(response.data);
                    } else {
                        failureCallback();
                    }
                },
                error: function() {
                    failureCallback();
                }
            });
        },
        eventClick: function(info) {
            showRentalDetails(info.event);
        },
        eventDidMount: function(info) {
            var status = info.event.extendedProps.status;
            $(info.el).addClass('fc-event-' + status);
        }
    });
    
    calendar.render();
    
    function showRentalDetails(event) {
        var props = event.extendedProps;
        
        var modalHtml = `
            <div class="mas-rental-modal">
                <div class="mas-rental-modal-content">
                    <div class="mas-rental-modal-header">
                        <h2>Detalles del Arriendo</h2>
                        <button class="mas-modal-close-btn">&times;</button>
                    </div>
                    <div class="mas-rental-modal-body">
                        <div class="mas-detail-row">
                            <div class="mas-detail-label">Box:</div>
                            <div class="mas-detail-value">${props.box_name}</div>
                        </div>
                        <div class="mas-detail-row">
                            <div class="mas-detail-label">Profesional:</div>
                            <div class="mas-detail-value">${props.professional_name || 'No asignado'}</div>
                        </div>
                        <div class="mas-detail-row">
                            <div class="mas-detail-label">Fecha:</div>
                            <div class="mas-detail-value">${props.formatted_date}</div>
                        </div>
                        <div class="mas-detail-row">
                            <div class="mas-detail-label">Horario:</div>
                            <div class="mas-detail-value">${props.start_time} - ${props.end_time}</div>
                        </div>
                        <div class="mas-detail-row">
                            <div class="mas-detail-label">Horas Totales:</div>
                            <div class="mas-detail-value">${props.total_hours} horas</div>
                        </div>
                        <div class="mas-detail-row">
                            <div class="mas-detail-label">Total a Pagar:</div>
                            <div class="mas-detail-value">
                                <span class="mas-price-highlight">$${props.formatted_price}</span>
                            </div>
                        </div>
                        <div class="mas-detail-row">
                            <div class="mas-detail-label">Estado:</div>
                            <div class="mas-detail-value">
                                <span class="mas-status-badge mas-status-${props.status}">${props.status_label}</span>
                            </div>
                        </div>
                        <div class="mas-detail-row">
                            <div class="mas-detail-label">Estado de Pago:</div>
                            <div class="mas-detail-value">
                                <span class="mas-status-badge mas-payment-${props.payment_status}">${props.payment_status_label}</span>
                            </div>
                        </div>
                        ${props.notes ? `
                        <div class="mas-detail-row">
                            <div class="mas-detail-label">Notas:</div>
                            <div class="mas-detail-value">${props.notes}</div>
                        </div>
                        ` : ''}
                    </div>
                    <div class="mas-rental-modal-footer">
                        <a href="${props.edit_url}" class="mas-btn-primary">Ver/Editar Arriendo</a>
                    </div>
                </div>
            </div>
        `;
        
        var $modal = $(modalHtml);
        $('body').append($modal);
        
        // Close modal on click
        $modal.find('.mas-modal-close-btn').on('click', function() {
            $modal.fadeOut(200, function() {
                $modal.remove();
            });
        });
        
        $modal.on('click', function(e) {
            if ($(e.target).hasClass('mas-rental-modal')) {
                $modal.fadeOut(200, function() {
                    $modal.remove();
                });
            }
        });
    }
});
</script>
