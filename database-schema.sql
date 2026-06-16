-- =====================================================
-- Sistema de Citas Médicas y Arriendo de Boxes
-- Script de Creación de Base de Datos
-- =====================================================

-- Tabla de Citas Médicas
CREATE TABLE IF NOT EXISTS wpjo_mas_appointments (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    patient_name varchar(255) NOT NULL,
    patient_email varchar(255) NOT NULL,
    patient_phone varchar(50) NOT NULL,
    patient_rut varchar(20) DEFAULT NULL,
    appointment_date date NOT NULL,
    appointment_time time NOT NULL,
    duration int(11) DEFAULT 60,
    professional_id bigint(20) DEFAULT NULL,
    service_id bigint(20) DEFAULT NULL,
    status varchar(20) DEFAULT 'pending',
    payment_status varchar(20) DEFAULT 'pending',
    amount_paid decimal(10,2) DEFAULT NULL,
    payment_method varchar(50) DEFAULT NULL,
    payment_id varchar(255) DEFAULT NULL,
    preference_id varchar(255) DEFAULT NULL,
    mp_payment_id varchar(255) DEFAULT NULL,
    notes text,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY appointment_date (appointment_date),
    KEY professional_id (professional_id),
    KEY service_id (service_id),
    KEY status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Boxes de Atención
CREATE TABLE IF NOT EXISTS wpjo_mas_boxes (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    box_name varchar(255) NOT NULL,
    box_number varchar(50) NOT NULL,
    description text,
    image_url varchar(500) DEFAULT NULL,
    price_per_hour decimal(10,2) NOT NULL DEFAULT 0.00,
    status varchar(20) DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY box_number (box_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Arriendos de Boxes
CREATE TABLE IF NOT EXISTS wpjo_mas_box_rentals (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    box_id bigint(20) NOT NULL,
    professional_id bigint(20) NOT NULL,
    rental_date date NOT NULL,
    start_time time NOT NULL,
    end_time time NOT NULL,
    total_hours decimal(5,2) NOT NULL,
    total_price decimal(10,2) NOT NULL,
    status varchar(20) DEFAULT 'pending',
    payment_status varchar(20) DEFAULT 'pending',
    payment_id varchar(255) DEFAULT NULL,
    preference_id varchar(255) DEFAULT NULL,
    mp_payment_id varchar(255) DEFAULT NULL,
    notes text,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY box_id (box_id),
    KEY professional_id (professional_id),
    KEY rental_date (rental_date),
    KEY payment_status (payment_status),
    CONSTRAINT fk_rental_box FOREIGN KEY (box_id) REFERENCES wp_mas_boxes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Profesionales (metadata adicional)
CREATE TABLE IF NOT EXISTS wpjo_mas_professionals (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    run varchar(20) DEFAULT NULL,
    specialty varchar(255) DEFAULT NULL,
    license_number varchar(100) DEFAULT NULL,
    phone varchar(50) DEFAULT NULL,
    image_url varchar(500) DEFAULT NULL,
    bio text,
    status varchar(20) DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Servicios
CREATE TABLE IF NOT EXISTS wpjo_mas_services (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    service_name varchar(255) NOT NULL,
    description text,
    duration int(11) DEFAULT 60,
    price decimal(10,2) NOT NULL DEFAULT 0.00,
    status varchar(20) DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de relación profesionales-servicios
CREATE TABLE IF NOT EXISTS wpjo_mas_professional_services (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    professional_id bigint(20) NOT NULL,
    service_id bigint(20) NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY prof_service (professional_id, service_id),
    KEY professional_id (professional_id),
    KEY service_id (service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Especialidades
CREATE TABLE IF NOT EXISTS wpjo_mas_specialties (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    description text,
    price decimal(10,2) NOT NULL DEFAULT 0.00,
    status varchar(20) DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de relación profesionales-especialidades
CREATE TABLE IF NOT EXISTS wpjo_mas_professional_specialties (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    professional_id bigint(20) NOT NULL,
    specialty_id bigint(20) NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY prof_specialty (professional_id, specialty_id),
    KEY professional_id (professional_id),
    KEY specialty_id (specialty_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Datos de Ejemplo (Opcional)
-- =====================================================

-- Insertar boxes de ejemplo
INSERT INTO wpjo_mas_boxes (box_name, box_number, description, image_url, price_per_hour, status) VALUES
('Box Consulta 1', 'BOX-001', 'Box de consulta equipado con camilla y escritorio', 'https://example.com/box1.jpg', 15000.00, 'active'),
('Box Consulta 2', 'BOX-002', 'Box de consulta con equipamiento básico', 'https://example.com/box2.jpg', 15000.00, 'active'),
('Box Terapia 1', 'BOX-003', 'Box amplio para sesiones de terapia', 'https://example.com/box3.jpg', 18000.00, 'active'),
('Box Terapia 2', 'BOX-004', 'Box con ambiente acogedor para terapias', 'https://example.com/box4.jpg', 18000.00, 'active'),
('Sala Grupal', 'BOX-005', 'Sala amplia para terapias grupales', 'https://example.com/box5.jpg', 25000.00, 'active');

-- Insertar servicios de ejemplo
INSERT INTO wp_mas_services (service_name, description, duration, price, status) VALUES
('Psicología Clínica', 'Atención psicológica general', 60, 30000.00, 'active'),
('Psiquiatría', 'Evaluación y tratamiento psiquiátrico', 60, 30000.00, 'active'),
('Psicología Infantil', 'Atención especializada para niños y adolescentes', 60, 30000.00, 'active'),
('Terapia Familiar', 'Terapia para familias y parejas', 60, 30000.00, 'active'),
('Neuropsicología', 'Evaluación y rehabilitación neuropsicológica', 60, 30000.00, 'active'),
('Terapia Ocupacional', 'Rehabilitación y terapia ocupacional', 60, 30000.00, 'active');

-- =====================================================
-- Índices Adicionales para Optimización
-- =====================================================

-- Índice compuesto para búsquedas de disponibilidad de boxes
CREATE INDEX idx_rental_availability ON wpjo_mas_box_rentals (box_id, rental_date, start_time, end_time);

-- Índice para búsquedas de citas por fecha y hora
CREATE INDEX idx_appointment_datetime ON wpjo_mas_appointments (appointment_date, appointment_time);

-- Índice para búsquedas de arriendos por estado de pago
CREATE INDEX idx_rental_payment ON wpjo_mas_box_rentals (payment_status, status);

-- =====================================================
-- Tablas de Promociones y Fechas Bloqueadas
-- =====================================================

-- Tabla de Promociones de Bloques
CREATE TABLE IF NOT EXISTS wpjo_mas_promotions (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    description text,
    blocks_quantity int(11) NOT NULL COMMENT 'Cantidad de bloques incluidos en el paquete',
    package_price decimal(10,2) NOT NULL COMMENT 'Precio total del paquete',
    discount_percentage decimal(5,2) DEFAULT NULL COMMENT 'Porcentaje de descuento calculado',
    status enum('active','inactive') DEFAULT 'active',
    start_date date DEFAULT NULL,
    end_date date DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY status (status),
    KEY start_date (start_date),
    KEY end_date (end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de relación arriendos-promociones
CREATE TABLE IF NOT EXISTS wpjo_mas_rental_promotions (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    rental_id bigint(20) NOT NULL,
    promotion_id bigint(20) NOT NULL,
    blocks_used int(11) NOT NULL COMMENT 'Bloques usados de esta promoción',
    discount_applied decimal(10,2) NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY rental_id (rental_id),
    KEY promotion_id (promotion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de fechas bloqueadas
CREATE TABLE IF NOT EXISTS wpjo_mas_blocked_dates (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    blocked_date date NOT NULL,
    reason text,
    blocks_appointments tinyint(1) DEFAULT 1,
    blocks_rentals tinyint(1) DEFAULT 1,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY blocked_date (blocked_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Notas de Instalación
-- =====================================================

-- IMPORTANTE: 
-- 1. Reemplace 'wp_' con el prefijo de su instalación de WordPress si es diferente
-- 2. Este script crea las tablas con datos de ejemplo
-- 3. Los precios están en pesos chilenos (CLP)
-- 4. Asegúrese de tener los permisos necesarios para crear tablas
-- 5. El plugin creará estas tablas automáticamente al activarse
-- 6. Este archivo SQL es solo para referencia o instalación manual

-- Para ejecutar este script en WordPress:
-- 1. Vaya a phpMyAdmin o su gestor de base de datos
-- 2. Seleccione la base de datos de WordPress
-- 3. Ejecute este script completo
-- 4. Verifique que las tablas se hayan creado correctamente

-- Verificar creación de tablas:
-- SHOW TABLES LIKE 'wp_mas_%';

-- Ver estructura de una tabla:
-- DESCRIBE wp_mas_appointments;
