<?php
/**
 * Plugin Name: Sistema de Citas Médicas y Arriendo de Boxes
 * Description: Sistema completo para gestión de citas médicas, arriendo de boxes y gestión de profesionales de salud mental
 * Version: 3.0.0
 * Author: Yerco Abraham Abarca Cortes
 * License: GPL v2 or later
 * Text Domain: medical-appointments-system
 * Domain Path: /Español Latino
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('MAS_VERSION', '1.0.0');
define('MAS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MAS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MAS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader para clases
spl_autoload_register(function ($class) {
    $prefix = 'MAS_';
    $base_dir = MAS_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Clase principal del plugin
class Medical_Appointments_System {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        require_once MAS_PLUGIN_DIR . 'includes/class-professionals.php';
        require_once MAS_PLUGIN_DIR . 'includes/class-professional-schedules.php';
        require_once MAS_PLUGIN_DIR . 'includes/class-appointments.php';
        require_once MAS_PLUGIN_DIR . 'includes/class-services.php';
        require_once MAS_PLUGIN_DIR . 'includes/class-boxes.php';
        require_once MAS_PLUGIN_DIR . 'includes/class-rentals.php';
        require_once MAS_PLUGIN_DIR . 'includes/class-monthly-rentals.php';
        require_once MAS_PLUGIN_DIR . 'includes/class-notifications.php';
        require_once MAS_PLUGIN_DIR . 'includes/class-mercadopago.php';
        require_once MAS_PLUGIN_DIR . 'includes/class-promotions.php';
        
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init'));
        add_action('init', array($this, 'register_virtual_pages'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_filter('template_include', array($this, 'load_payment_templates'));
        
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_scripts'));
        
        add_action('mas_daily_reminders', array('MAS_Notifications', 'send_appointment_reminders'));
        
        add_action('mas_cleanup_abandoned_payments', array($this, 'cleanup_abandoned_payments'));
        
        add_action('admin_post_mas_export_appointments', array($this, 'handle_export_appointments'));
        add_action('admin_post_mas_export_rentals', array($this, 'handle_export_rentals'));
        add_action('admin_post_mas_backup_database', array($this, 'handle_backup_database'));
        
        add_action('admin_post_export_appointments_excel', array($this, 'handle_export_appointments_excel'));
        add_action('admin_post_export_rentals_excel', array($this, 'handle_export_rentals_excel'));
        add_action('admin_post_export_full_report', array($this, 'handle_export_full_report'));
        
        // AJAX handlers
        add_action('wp_ajax_mas_get_available_slots', array($this, 'ajax_get_appointment_slots'));
        add_action('wp_ajax_nopriv_mas_get_available_slots', array($this, 'ajax_get_appointment_slots'));
        add_action('wp_ajax_mas_book_appointment', array($this, 'ajax_book_appointment'));
        add_action('wp_ajax_nopriv_mas_book_appointment', array($this, 'ajax_book_appointment'));
        
        // Nuevo handler AJAX para crear preferencia de cita
        add_action('wp_ajax_mas_create_appointment_preference', array($this, 'ajax_create_appointment_preference'));
        add_action('wp_ajax_nopriv_mas_create_appointment_preference', array($this, 'ajax_create_appointment_preference'));

        add_action('wp_ajax_mas_get_box_available_slots', array($this, 'ajax_get_available_rental_slots'));
        add_action('wp_ajax_nopriv_mas_get_box_available_slots', array($this, 'ajax_get_available_rental_slots'));
        add_action('wp_ajax_mas_create_multiple_rentals', array($this, 'ajax_create_multiple_rentals'));
        add_action('wp_ajax_nopriv_mas_create_multiple_rentals', array($this, 'ajax_create_multiple_rentals'));
        add_action('wp_ajax_mas_create_rental', array($this, 'ajax_create_rental'));
        add_action('wp_ajax_nopriv_mas_create_rental', array($this, 'ajax_create_rental'));
        add_action('wp_ajax_mas_check_box_availability', array($this, 'ajax_check_box_availability'));
        add_action('wp_ajax_nopriv_mas_check_box_availability', array($this, 'ajax_check_box_availability'));
        
        add_action('wp_ajax_mas_create_mp_preference', array($this, 'ajax_create_mp_preference'));
        add_action('wp_ajax_nopriv_mas_create_mp_preference', array($this, 'ajax_create_mp_preference'));
        add_action('wp_ajax_mas_mp_webhook', array($this, 'handle_mp_webhook'));
        add_action('wp_ajax_nopriv_mas_mp_webhook', array($this, 'handle_mp_webhook'));
        
        // AJAX handler for payment verification (manual check)
        add_action('wp_ajax_mas_verify_payment', array($this, 'ajax_verify_payment'));
        // add_action('wp_ajax_nopriv_mas_verify_payment', array($this, 'ajax_verify_payment')); // Usually admin-only, but can be public if needed
        
        // Add webhook logs AJAX handler
        add_action('wp_ajax_mas_get_webhook_log_details', array($this, 'ajax_get_webhook_log_details'));
        
        // Add AJAX handler for active promotions
        add_action('wp_ajax_mas_get_active_promotions', array($this, 'ajax_get_active_promotions'));
        add_action('wp_ajax_nopriv_mas_get_active_promotions', array($this, 'ajax_get_active_promotions'));
        
        // AJAX handlers for professional schedules
        add_action('wp_ajax_mas_get_professional_schedule', array($this, 'ajax_get_professional_schedule'));
        add_action('wp_ajax_mas_save_professional_schedule', array($this, 'ajax_save_professional_schedule'));
        add_action('wp_ajax_mas_get_professionals_by_service', array($this, 'ajax_get_professionals_by_service'));
        
        // AJAX handlers for professional services
        add_action('wp_ajax_mas_get_professional_services', array($this, 'ajax_get_professional_services'));
        add_action('wp_ajax_mas_save_professional_services', array($this, 'ajax_save_professional_services'));
        add_action('wp_ajax_nopriv_mas_get_professionals_by_service', array($this, 'ajax_get_professionals_by_service'));
        add_action('wp_ajax_mas_get_professional_available_slots', array($this, 'ajax_get_professional_available_slots'));
        add_action('wp_ajax_nopriv_mas_get_professional_available_slots', array($this, 'ajax_get_professional_available_slots'));
        
        add_action('wp_ajax_mas_delete_pending_payment', array($this, 'ajax_delete_pending_payment'));

        // ADDING ADMIN ACTION FOR REWRITE RULES REGENERATION
        add_action('admin_post_mas_regenerate_rewrites', array($this, 'regenerate_rewrite_rules'));
        
        require_once MAS_PLUGIN_DIR . 'admin/ajax-handlers.php';
        
        // Shortcodes
        add_shortcode('mas_appointment_form', array($this, 'appointment_form_shortcode'));
        add_shortcode('mas_box_rental', array($this, 'box_rental_shortcode'));
        // ADDING shortcode for professionals grid display
        add_shortcode('mas_professionals_grid', array($this, 'professionals_grid_shortcode'));
    }
    
    public function activate() {
        $this->create_tables();
        $this->create_default_options();
        
        if (!wp_next_scheduled('mas_daily_reminders')) {
            wp_schedule_event(time(), 'daily', 'mas_daily_reminders');
        }
        
        if (!wp_next_scheduled('mas_cleanup_abandoned_payments')) {
            wp_schedule_event(time(), 'mas_every_15_minutes', 'mas_cleanup_abandoned_payments');
        }
        
        $this->register_virtual_pages();
        
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('mas_daily_reminders');
        
        wp_clear_scheduled_hook('mas_cleanup_abandoned_payments');
        
        flush_rewrite_rules();
    }
    
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Tabla de citas
        $table_appointments = $wpdb->prefix . 'mas_appointments';
        $sql_appointments = "CREATE TABLE $table_appointments (
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
            health_insurance varchar(255) DEFAULT NULL, -- Campo agregado para seguro de salud
            status varchar(20) DEFAULT 'pending',
            payment_status varchar(20) DEFAULT 'pending',
            amount_paid decimal(10,2) DEFAULT NULL,
            payment_method varchar(50) DEFAULT NULL,
            payment_id varchar(255) DEFAULT NULL,
            preference_id varchar(255) DEFAULT NULL,
            mp_payment_id varchar(255) DEFAULT NULL,
            payment_url varchar(255) DEFAULT NULL, -- Campo agregado para URL de pago
            external_reference varchar(255) DEFAULT NULL, -- Campo agregado para referencia externa
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY appointment_date (appointment_date),
            KEY professional_id (professional_id),
            KEY service_id (service_id),
            KEY status (status),
            KEY payment_status (payment_status)
        ) $charset_collate;";
        
        // Tabla de boxes
        $table_boxes = $wpdb->prefix . 'mas_boxes';
        $sql_boxes = "CREATE TABLE $table_boxes (
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
        ) $charset_collate;";
        
        // Tabla de arriendos de boxes
        $table_rentals = $wpdb->prefix . 'mas_box_rentals';
        $sql_rentals = "CREATE TABLE $table_rentals (
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
            external_reference varchar(255) DEFAULT NULL,
            payment_url varchar(255) DEFAULT NULL,
            notes text,
            promotion_id bigint(20) DEFAULT NULL, -- Store promotion ID
            reference_id varchar(50) DEFAULT NULL, -- Campo para referencia consolidada
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY box_id (box_id),
            KEY professional_id (professional_id),
            KEY rental_date (rental_date),
            KEY payment_status (payment_status),
            KEY reference_id (reference_id) -- Add index for reference_id
        ) $charset_collate;";
        
        // Tabla de profesionales (metadata adicional)
        $table_professionals = $wpdb->prefix . 'mas_professionals';
        $sql_professionals = "CREATE TABLE $table_professionals (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
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
        ) $charset_collate;";
        
        // Tabla de servicios
        $table_services = $wpdb->prefix . 'mas_services';
        $sql_services = "CREATE TABLE $table_services (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            service_name varchar(255) NOT NULL,
            description text,
            duration int(11) DEFAULT 60,
            price decimal(10,2) NOT NULL DEFAULT 0.00,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Tabla de relación profesionales-servicios
        $table_prof_services = $wpdb->prefix . 'mas_professional_services';
        $sql_prof_services = "CREATE TABLE $table_prof_services (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            professional_id bigint(20) NOT NULL,
            service_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY prof_service (professional_id, service_id),
            KEY professional_id (professional_id),
            KEY service_id (service_id)
        ) $charset_collate;";
        
        // Tabla de especialidades (para compatibilidad)
        $table_specialties = $wpdb->prefix . 'mas_specialties';
        $sql_specialties = "CREATE TABLE $table_specialties (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            price decimal(10,2) NOT NULL DEFAULT 0.00,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Tabla de relación profesionales-especialidades
        $table_prof_specialties = $wpdb->prefix . 'mas_professional_specialties';
        $sql_prof_specialties = "CREATE TABLE $table_prof_specialties (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            professional_id bigint(20) NOT NULL,
            specialty_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY prof_specialty (professional_id, specialty_id),
            KEY professional_id (professional_id),
            KEY specialty_id (specialty_id)
        ) $charset_collate;";

        // Tabla de fechas bloqueadas
        $table_blocked_dates = $wpdb->prefix . 'mas_blocked_dates';
        $sql_blocked_dates = "CREATE TABLE $table_blocked_dates (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            blocked_date date NOT NULL,
            reason text,
            blocks_appointments tinyint(1) DEFAULT 1,
            blocks_rentals tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY blocked_date (blocked_date)
        ) $charset_collate;";
        
        // Tabla para logs de webhooks
        $table_webhook_logs = $wpdb->prefix . 'mas_webhook_logs';
        $sql_webhook_logs = "CREATE TABLE $table_webhook_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            topic varchar(50) DEFAULT NULL,
            type varchar(50) DEFAULT NULL,
            mp_id varchar(255) DEFAULT NULL,
            data_id varchar(255) DEFAULT NULL,
            request_method varchar(10) NOT NULL,
            get_params text,
            post_params text,
            raw_input text,
            headers text,
            decoded_json text,
            processing_status varchar(50) DEFAULT 'received',
            processing_message text,
            processed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY mp_id (mp_id),
            KEY topic (topic),
            KEY type (type),
            KEY processing_status (processing_status)
        ) $charset_collate;";
        
        dbDelta($sql_appointments);
        dbDelta($sql_boxes);
        dbDelta($sql_rentals);
        dbDelta($sql_professionals);
        dbDelta($sql_services);
        dbDelta($sql_prof_services);
        dbDelta($sql_specialties);
        dbDelta($sql_prof_specialties);
        dbDelta($sql_blocked_dates);
        dbDelta($sql_webhook_logs);
        
        $services_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_services");
        if ($services_count == 0) {
            $default_services = array(
                array('Psicología Clínica', 'Atención psicológica general', 60, 30000.00),
                array('Psiquiatría', 'Evaluación y tratamiento psiquiátrico', 60, 30000.00),
                array('Psicología Infantil', 'Atención especializada para niños y adolescentes', 60, 30000.00),
                array('Terapia Familiar', 'Terapia para familias y parejas', 60, 30000.00),
                array('Neuropsicología', 'Evaluación y rehabilitación neuropsicológica', 60, 30000.00),
                array('Terapia Ocupacional', 'Rehabilitación y terapia ocupacional', 60, 30000.00)
            );
            
            foreach ($default_services as $service) {
                $wpdb->insert($table_services, array(
                    'service_name' => $service[0],
                    'description' => $service[1],
                    'duration' => $service[2],
                    'price' => $service[3],
                    'status' => 'active'
                ));
            }
        }
    }
    
    private function create_default_options() {
        $default_settings = array(
            'slot_duration' => 60,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'working_days' => array('1', '2', '3', '4', '5'),
            'booking_advance_days' => 30,
            'min_booking_notice' => 24,
            'enable_notifications' => true,
            'admin_email' => get_option('admin_email')
        );
        
        add_option('mas_settings', $default_settings);
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('medical-appointments', false, dirname(MAS_PLUGIN_BASENAME) . '/languages');
    }
    
    // Registrar páginas virtuales de pago
    public function register_virtual_pages() {
        add_rewrite_rule('^pago-exitoso/?$', 'index.php?mas_payment_page=success', 'top');
        add_rewrite_rule('^pago-fallido/?$', 'index.php?mas_payment_page=failure', 'top');
        add_rewrite_rule('^pago-pendiente/?$', 'index.php?mas_payment_page=pending', 'top');
        add_rewrite_rule('^pago-cancelado/?$', 'index.php?mas_payment_page=cancelled', 'top');
    }

    // Agregar query vars para páginas de pago
    public function add_query_vars($vars) {
        $vars[] = 'mas_payment_page';
        return $vars;
    }

    // Cargar templates de páginas de pago
    public function load_payment_templates($template) {
        $payment_page = get_query_var('mas_payment_page');
        
        if ($payment_page) {
            $plugin_dir = plugin_dir_path(__FILE__);
            
            switch ($payment_page) {
                case 'success':
                    return $plugin_dir . 'public/payment-success.php';
                case 'failure':
                    return $plugin_dir . 'public/payment-failure.php';
                case 'pending':
                    return $plugin_dir . 'public/payment-pending.php';
                case 'cancelled':
                    return $plugin_dir . 'public/payment-cancelled.php';
            }
        }
        
        return $template;
    }

    public function init() {
        // Registrar custom post types si es necesario
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Sistema Médico', 'medical-appointments'),
            __('Sistema Médico', 'medical-appointments'),
            'manage_options',
            'medical-appointments',
            array($this, 'render_dashboard'),
            'dashicons-calendar-alt',
            30
        );
        
        add_submenu_page(
            'medical-appointments',
            __('Dashboard', 'medical-appointments'),
            __('Dashboard', 'medical-appointments'),
            'manage_options',
            'medical-appointments',
            array($this, 'render_dashboard')
        );
        
        add_submenu_page(
            'medical-appointments',
            __('Citas Médicas', 'medical-appointments'),
            __('Citas Médicas', 'medical-appointments'),
            'manage_options',
            'medical-appointments-list',
            array($this, 'render_appointments_page')
        );
        
        add_submenu_page(
            'medical-appointments',
            __('Calendario de Citas', 'medical-appointments'),
            __('Calendario de Citas', 'medical-appointments'),
            'manage_options',
            'medical-appointments-calendar',
            array($this, 'render_appointments_calendar')
        );
        
        add_submenu_page(
            'medical-appointments',
            __('Arriendos - Box', 'medical-appointments'),
            __('Arriendos - Box', 'medical-appointments'),
            'manage_options',
            'medical-rentals',
            array($this, 'render_rentals_page')
        );
        
        add_submenu_page(
            'medical-appointments',
            __('Calendario de Arriendos - Box', 'medical-appointments'),
            __('Calendario de Arriendos - Box', 'medical-appointments'),
            'manage_options',
            'medical-rentals-calendar',
            array($this, 'render_rentals_calendar')
        );
        
        add_submenu_page(
            'medical-appointments',
            __('Arriendos Mensuales', 'medical-appointments'),
            __('Arriendos Mensuales', 'medical-appointments'),
            'manage_options',
            'mas-monthly-rentals',
            array($this, 'render_monthly_rentals_page')
        );
        
        add_submenu_page(
            'medical-appointments',
            __('Boxes de Atención', 'medical-appointments'),
            __('Boxes de Atención', 'medical-appointments'),
            'manage_options',
            'medical-boxes',
            array($this, 'render_boxes_page')
        );
        
        add_submenu_page(
            'medical-appointments',
            __('Promociones', 'medical-appointments'),
            __('Promociones', 'medical-appointments'),
            'manage_options',
            'medical-promotions',
            array($this, 'render_promotions_page')
        );
        
        add_submenu_page(
            'medical-appointments',
            __('Profesionales', 'medical-appointments'),
            __('Profesionales', 'medical-appointments'),
            'manage_options',
            'medical-professionals',
            array($this, 'render_professionals_page')
        );
        
        add_submenu_page(
            'medical-appointments',
            __('Servicios', 'medical-appointments'),
            __('Servicios', 'medical-appointments'),
            'manage_options',
            'medical-services',
            array($this, 'render_services_page')
        );
        
        add_submenu_page(
            'medical-appointments',
            __('Reportes', 'medical-appointments'),
            __('Reportes', 'medical-appointments'),
            'manage_options',
            'medical-reports',
            array($this, 'render_reports_page')
        );
        
        add_submenu_page(
            'medical-appointments',
            __('Configuración', 'medical-appointments'),
            __('Configuración', 'medical-appointments'),
            'manage_options',
            'medical-settings',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'medical-appointments',
            __('Mantenimiento', 'medical-appointments'),
            __('Mantenimiento', 'medical-appointments'),
            'manage_options',
            'mas-maintenance',
            array($this, 'render_maintenance_page')
        );
        
        //add_submenu_page(
        //    'medical-appointments',
        //    __('Logs de Webhook', 'medical-appointments'),
        //    __('Logs de Webhook', 'medical-appointments'),
        //    'manage_options',
        //    'mas-webhook-logs',
        //    array($this, 'render_webhook_logs_page')
        //);
        
        add_submenu_page(
            null, // null = hidden from menu
            __('Comprobante de Pago', 'medical-appointments'),
            __('Comprobante de Pago', 'medical-appointments'),
            'read', // Anyone can view their payment receipt
            'mas-payment-receipt',
            array($this, 'render_payment_receipt')
        );
        
        if (current_user_can('edit_posts') && !current_user_can('manage_options')) {
            add_menu_page(
                __('Mi Dashboard', 'medical-appointments'),
                __('Mi Dashboard', 'medical-appointments'),
                'edit_posts',
                'mas-professional-dashboard',
                array($this, 'render_professional_dashboard'),
                'dashicons-businessman',
                25
            );
            
            add_submenu_page(
                'mas-professional-dashboard',
                __('Mis Citas', 'medical-appointments'),
                __('Mis Citas', 'medical-appointments'),
                'edit_posts',
                'mas-my-appointments',
                array($this, 'render_my_appointments')
            );
            
            add_submenu_page(
                'mas-professional-dashboard',
                __('Mis Arriendos', 'medical-appointments'),
                __('Mis Arriendos', 'medical-appointments'),
                'edit_posts',
                'mas-my-rentals',
                array($this, 'render_my_rentals')
            );
            
            add_submenu_page(
                'mas-professional-dashboard',
                __('Arrendar Box', 'medical-appointments'),
                __('Arrendar Box', 'medical-appointments'),
                'edit_posts',
                'mas-rent-box',
                array($this, 'render_rent_box_page')
            );
        }
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'medical-') === false && strpos($hook, 'mas-') === false) {
            return;
        }
        
        wp_enqueue_style('mas-admin-css', MAS_PLUGIN_URL . 'assets/css/admin.css', array(), MAS_VERSION);
        wp_enqueue_script('mas-admin-js', MAS_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), MAS_VERSION, true);
        
        if (strpos($hook, 'medical-boxes') !== false || strpos($hook, 'medical-professionals') !== false) {
            wp_enqueue_media();
        }
        
        // Enqueue scripts for webhook logs page
        if ($hook === 'toplevel_page_medical-appointments' && isset($_GET['page']) && $_GET['page'] === 'mas-webhook-logs') {
            wp_enqueue_style('mas-webhook-logs-css', MAS_PLUGIN_URL . 'assets/css/webhook-logs.css', array(), MAS_VERSION);
            wp_enqueue_script('mas-webhook-logs-js', MAS_PLUGIN_URL . 'assets/js/webhook-logs.js', array('jquery', 'wp-util'), MAS_VERSION, true);
            wp_localize_script('mas-webhook-logs-js', 'masWebhookLogs', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mas_webhook_logs')
            ));
        }
        
        // Localize for public and admin AJAX calls
        wp_localize_script('mas-admin-js', 'masAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mas_admin_nonce')
        ));
    }
    
    public function enqueue_public_scripts() {
        wp_enqueue_style('mas-public-css', MAS_PLUGIN_URL . 'assets/css/public.css', array(), MAS_VERSION);
        wp_enqueue_script('mas-public-js', MAS_PLUGIN_URL . 'assets/js/public.js', array('jquery'), MAS_VERSION, true);
        
        wp_enqueue_script('mercadopago-sdk', 'https://sdk.mercadopago.com/js/v2', array(), null, true);
        
        $mas_mp = new MAS_MercadoPago();
        
        // Localize for public AJAX calls
        wp_localize_script('mas-public-js', 'masPublic', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mas_public_nonce'),
            'mpPublicKey' => $mas_mp->get_public_key()
        ));
    }
    
    // Métodos de renderizado
    public function render_dashboard() {
        include MAS_PLUGIN_DIR . 'admin/dashboard.php';
    }
    
    public function render_appointments_page() {
        include MAS_PLUGIN_DIR . 'admin/appointments.php';
    }
    
    public function render_appointments_calendar() {
        include MAS_PLUGIN_DIR . 'admin/appointments-calendar.php';
    }
    
    public function render_rentals_calendar() {
        include MAS_PLUGIN_DIR . 'admin/rentals-calendar.php';
    }
    
    public function render_boxes_page() {
        include MAS_PLUGIN_DIR . 'admin/boxes.php';
    }
    
    public function render_rentals_page() {
        include MAS_PLUGIN_DIR . 'admin/rentals.php';
    }
    
    public function render_monthly_rentals_page() {
        include MAS_PLUGIN_DIR . 'admin/monthly-rentals.php';
    }
    
    public function render_professionals_page() {
        include MAS_PLUGIN_DIR . 'admin/professionals.php';
    }
    
    public function render_services_page() {
        include MAS_PLUGIN_DIR . 'admin/services.php';
    }
    public function render_promotions_page() {
        include MAS_PLUGIN_DIR . 'admin/promotions.php';
    }
    
    public function render_professional_dashboard() {
        include MAS_PLUGIN_DIR . 'admin/professional-dashboard.php';
    }
    
    public function render_my_appointments() {
        include MAS_PLUGIN_DIR . 'admin/my-appointments.php';
    }
    
    public function render_my_rentals() {
        include MAS_PLUGIN_DIR . 'admin/my-rentals.php';
    }
    
    public function render_rent_box_page() {
        include MAS_PLUGIN_DIR . 'admin/rent-box.php';
    }
    
    public function render_reports_page() {
        include MAS_PLUGIN_DIR . 'admin/reports.php';
    }
    
    public function render_settings_page() {
        include MAS_PLUGIN_DIR . 'admin/settings.php';
    }
    
    public function render_payment_receipt() {
        include MAS_PLUGIN_DIR . 'admin/payment-receipt.php';
    }
    
    // ADDING METHOD TO REGENERATE REWRITE RULES
    public function regenerate_rewrite_rules() {
        $this->register_virtual_pages();
        flush_rewrite_rules();
        
        wp_redirect(add_query_arg(array(
            'page' => 'mas-maintenance',
            'rewrite_flushed' => '1'
        ), admin_url('admin.php')));
        exit;
    }

    /**
     * Render maintenance page for manual cleanup and diagnostics
     */
    public function render_maintenance_page() {
        // Handle manual cleanup trigger
        if (isset($_POST['run_cleanup']) && check_admin_referer('mas_manual_cleanup', 'mas_cleanup_nonce')) {
            $cleanup_result = $this->cleanup_abandoned_payments();
            echo '<div class="notice notice-success is-dismissible" style="border-left: 4px solid #10b981; padding: 12px 15px;">
                    <p style="margin: 0;"><strong>✓ Limpieza ejecutada correctamente.</strong> Se eliminaron ' . $cleanup_result['appointments_deleted'] . ' citas y ' . $cleanup_result['rentals_deleted'] . ' arriendos abandonados.</p>
                  </div>';
        }
        
        // Handle cron re-registration
        if (isset($_POST['register_cron']) && check_admin_referer('mas_register_cron', 'mas_cron_nonce')) {
            wp_clear_scheduled_hook('mas_cleanup_abandoned_payments');
            if (!wp_next_scheduled('mas_cleanup_abandoned_payments')) {
                wp_schedule_event(time(), 'mas_every_15_minutes', 'mas_cleanup_abandoned_payments');
            }
            echo '<div class="notice notice-success is-dismissible" style="border-left: 4px solid #10b981; padding: 12px 15px;">
                    <p style="margin: 0;"><strong>✓ Cron job re-registrado.</strong> La limpieza automática está ahora programada correctamente.</p>
                  </div>';
        }
        
        global $wpdb;
        
        // Get cron info
        $next_cleanup = wp_next_scheduled('mas_cleanup_abandoned_payments');
        $next_cleanup_date = $next_cleanup ? date('Y-m-d H:i:s', $next_cleanup) : 'No programado';
        
        // Count abandoned records
        $cutoff_time = date('Y-m-d H:i:s', strtotime('-15 minutes'));
        
        $abandoned_appointments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}mas_appointments 
            WHERE payment_status = 'pending' 
            AND mp_payment_id IS NULL
            AND created_at < %s",
            $cutoff_time
        ));
        
        $abandoned_rentals = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}mas_box_rentals 
            WHERE payment_status = 'pending' 
            AND (mp_payment_id IS NULL OR mp_payment_id = '')
            AND created_at < %s",
            $cutoff_time
        ));
        
        ?>
        <div class="wrap mas-maintenance-wrap">
            <div class="mas-maintenance-header">
                <h1>🔧 Mantenimiento del Sistema</h1>
                <p>Gestiona la limpieza automática de pagos abandonados y mantén el sistema optimizado</p>
            </div>
            
            <div class="mas-maintenance-grid">
                <div class="mas-stat-card">
                    <div class="mas-stat-icon purple">⏰</div>
                    <div class="mas-stat-label">Próxima Ejecución</div>
                    <div class="mas-stat-value" style="font-size: 16px; margin-top: 4px;">
                        <?php echo esc_html($next_cleanup_date); ?>
                    </div>
                </div>
                
                <div class="mas-stat-card">
                    <div class="mas-stat-icon amber">📅</div>
                    <div class="mas-stat-label">Citas Abandonadas</div>
                    <div class="mas-stat-value">
                        <?php echo esc_html($abandoned_appointments); ?>
                    </div>
                </div>
                
                <div class="mas-stat-card">
                    <div class="mas-stat-icon red">🏢</div>
                    <div class="mas-stat-label">Arriendos Abandonados</div>
                    <div class="mas-stat-value">
                        <?php echo esc_html($abandoned_rentals); ?>
                    </div>
                </div>
                
                <div class="mas-stat-card">
                    <div class="mas-stat-icon blue">⚙️</div>
                    <div class="mas-stat-label">Umbral de Limpieza</div>
                    <div class="mas-stat-value" style="font-size: 20px; margin-top: 8px;">
                        15 minutos
                    </div>
                </div>
            </div>
            
            <div class="mas-action-section">
                <h2>Acciones de Mantenimiento</h2>
                
                <div class="mas-action-grid">
                    <div class="mas-action-card">
                        <h3>🧹 Limpieza Manual</h3>
                        <p class="description">
                            Ejecuta inmediatamente la limpieza de pagos abandonados (citas y arriendos con más de 15 minutos sin confirmación).
                        </p>
                        <form method="post" style="margin: 0;">
                            <?php wp_nonce_field('mas_manual_cleanup', 'mas_cleanup_nonce'); ?>
                            <button type="submit" name="run_cleanup" class="mas-btn mas-btn-primary">
                                <span>▶️</span> Ejecutar Limpieza
                            </button>
                        </form>
                    </div>
                    
                    <div class="mas-action-card">
                        <h3>🔄 Re-registrar Cron Job</h3>
                        <p class="description">
                            Si la limpieza automática no está funcionando, re-registra la tarea programada del sistema.
                        </p>
                        <form method="post" style="margin: 0;">
                            <?php wp_nonce_field('mas_register_cron', 'mas_cron_nonce'); ?>
                            <button type="submit" name="register_cron" class="mas-btn mas-btn-warning">
                                <span>🔧</span> Re-registrar Cron
                            </button>
                        </form>
                    </div>

                    <!-- ADDING NEW CARD FOR REWRITE RULES -->
                    <div class="mas-action-card">
                        <h3>🔗 Regenerar URLs</h3>
                        <p class="description">
                            Regenera las reglas de URL para las páginas de pago. Útil si las páginas /pago-exitoso/ no cargan correctamente.
                        </p>
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin: 0;">
                            <input type="hidden" name="action" value="mas_regenerate_rewrites">
                            <?php wp_nonce_field('mas_regenerate_rewrites'); ?>
                            <button type="submit" class="mas-btn mas-btn-purple">
                                <span>🔄</span> Regenerar Reglas de URL
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="mas-info-section">
                <h3>ℹ️ Información de Debug</h3>
                <p style="color: #1e40af; font-size: 14px; margin-bottom: 12px;">
                    Los logs de ejecución de las tareas automáticas se guardan en el archivo de error de PHP. Busca líneas que comiencen con <code>[MAS]</code> o <code>[MAS Cleanup]</code>.
                </p>
                <p style="color: #1e40af; font-size: 14px; font-weight: 600; margin-bottom: 8px;">
                    Ubicación típica del log:
                </p>
                <ul>
                    <li><code>wp-content/debug.log</code> (si WP_DEBUG_LOG está activo en wp-config.php)</li>
                    <li>Log de errores de PHP configurado en tu servidor web</li>
                </ul>
            </div>
        </div>
        
        <style>
            .mas-maintenance-wrap {
                max-width: 1200px;
                margin: 20px 0;
            }
            .mas-maintenance-header {
                margin-bottom: 30px;
            }
            .mas-maintenance-header h1 {
                font-size: 28px;
                font-weight: 600;
                color: #1e293b;
                margin-bottom: 8px;
            }
            .mas-maintenance-header p {
                color: #64748b;
                font-size: 14px;
                margin: 0;
            }
            .mas-maintenance-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            .mas-stat-card {
                background: white;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 24px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
                transition: all 0.2s;
            }
            .mas-stat-card:hover {
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                transform: translateY(-2px);
            }
            .mas-stat-icon {
                width: 48px;
                height: 48px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 16px;
                font-size: 24px;
            }
            .mas-stat-icon.blue {
                background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                color: white;
            }
            .mas-stat-icon.amber {
                background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
                color: white;
            }
            .mas-stat-icon.red {
                background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
                color: white;
            }
            .mas-stat-icon.purple {
                background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
                color: white;
            }
            .mas-stat-label {
                font-size: 13px;
                color: #64748b;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 8px;
            }
            .mas-stat-value {
                font-size: 32px;
                font-weight: 700;
                color: #1e293b;
                line-height: 1;
            }
            .mas-action-section {
                background: white;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 32px;
                margin-bottom: 20px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }
            .mas-action-section h2 {
                font-size: 20px;
                font-weight: 600;
                color: #1e293b;
                margin-bottom: 24px;
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .mas-action-section h2::before {
                content: '';
                width: 4px;
                height: 24px;
                background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                border-radius: 2px;
            }
            .mas-action-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
                gap: 20px;
            }
            .mas-action-card {
                border: 2px solid #e2e8f0;
                border-radius: 12px;
                padding: 24px;
                background: #f8fafc;
                transition: all 0.2s;
            }
            .mas-action-card:hover {
                border-color: #cbd5e1;
                background: white;
            }
            .mas-action-card h3 {
                font-size: 16px;
                font-weight: 600;
                color: #1e293b;
                margin-bottom: 8px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .mas-action-card .description {
                color: #64748b;
                font-size: 14px;
                line-height: 1.6;
                margin-bottom: 16px;
            }
            .mas-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 24px;
                border: none;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
                text-decoration: none;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .mas-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }
            .mas-btn-primary {
                background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                color: white;
            }
            .mas-btn-warning {
                background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
                color: white;
            }
            .mas-btn-purple {
                background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
                color: white;
            }
            .mas-btn-purple:hover {
                background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
                box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
            }
            .mas-info-section {
                background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
                border: 1px solid #bfdbfe;
                border-radius: 12px;
                padding: 24px;
                margin-top: 20px;
            }
            .mas-info-section h3 {
                font-size: 16px;
                font-weight: 600;
                color: #1e40af;
                margin-bottom: 12px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .mas-info-section ul {
                margin: 12px 0 0 0;
                padding-left: 20px;
            }
            .mas-info-section li {
                color: #1e40af;
                font-size: 13px;
                margin-bottom: 8px;
                line-height: 1.6;
            }
            .mas-info-section code {
                background: white;
                padding: 2px 8px;
                border-radius: 4px;
                font-size: 12px;
                color: #1e40af;
                border: 1px solid #bfdbfe;
            }
        </style>
        <?php
    }

    /**
     * Render the webhook logs page
     */
    public function render_webhook_logs_page() {
        // This page is rendered by the include in enqueue_admin_scripts
        // The actual content is in admin/webhook-logs.php
        require_once plugin_dir_path(__FILE__) . 'admin/webhook-logs.php';
    }
    
    // AJAX handlers
    public function ajax_get_appointment_slots() {
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
            wp_send_json_success(array());
        } else {
            wp_send_json_success($slots);
        }
    }
    
    public function ajax_book_appointment() {
        check_ajax_referer('mas_public_nonce', 'nonce');
        
        $data = array(
            'patient_name' => sanitize_text_field($_POST['patient_name'] ?? ''),
            'patient_email' => sanitize_email($_POST['patient_email'] ?? ''),
            'patient_phone' => sanitize_text_field($_POST['patient_phone'] ?? ''),
            'patient_rut' => sanitize_text_field($_POST['patient_rut'] ?? ''),
            'appointment_date' => sanitize_text_field($_POST['appointment_date'] ?? ''),
            'appointment_time' => sanitize_text_field($_POST['appointment_time'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
        );
        
        // Validaciones básicas
        if (empty($data['patient_name']) || empty($data['patient_email']) || 
            empty($data['patient_phone']) || empty($data['appointment_date']) || 
            empty($data['appointment_time'])) {
            wp_send_json_error(array('message' => __('Todos los campos son requeridos', 'medical-appointments')));
            return;
        }
        
        // Validate email
        if (!is_email($data['patient_email'])) {
            wp_send_json_error(array('message' => __('Email inválido', 'medical-appointments')));
            return;
        }
        
        // Validate RUT if provided
        if (!empty($data['patient_rut']) && !validate_chilean_rut($data['patient_rut'])) {
            wp_send_json_error(array('message' => __('RUT chileno inválido', 'medical-appointments')));
            return;
        }
        
        $mas_appointments = new MAS_Appointments();
        $result = $mas_appointments->create_appointment($data);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Cita agendada exitosamente', 'medical-appointments')));
        } else {
            wp_send_json_error(array('message' => __('Error al agendar la cita. El horario puede no estar disponible.', 'medical-appointments')));
        }
    }
    
    public function ajax_create_appointment_preference() {
        check_ajax_referer('mas_public_nonce', 'nonce');
        
        global $wpdb;
        
        error_log('[MAS APPOINTMENT] ========== INICIANDO PROCESO DE CREAR PREFERENCIA DE CITA ==========');
        error_log('[MAS APPOINTMENT] Datos POST recibidos: ' . json_encode($_POST, JSON_PRETTY_PRINT));
        
        // Validar datos requeridos
        $patient_name = sanitize_text_field($_POST['patient_name'] ?? '');
        $patient_email = sanitize_email($_POST['patient_email'] ?? '');
        $patient_phone = sanitize_text_field($_POST['patient_phone'] ?? '');
        $patient_rut = sanitize_text_field($_POST['patient_rut'] ?? '');
        $service_id = intval($_POST['service_id'] ?? 0);
        $appointment_date = sanitize_text_field($_POST['appointment_date'] ?? '');
        $appointment_time = sanitize_text_field($_POST['appointment_time'] ?? '');
        $health_insurance = sanitize_text_field($_POST['health_insurance'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        
        if (empty($patient_name) || empty($patient_email) || empty($patient_phone) || 
            empty($service_id) || empty($appointment_date) || empty($appointment_time)) {
            error_log('[MAS APPOINTMENT] ERROR: Campos requeridos faltantes');
            wp_send_json_error(array('message' => 'Todos los campos son requeridos'));
            return;
        }
        
        // Validar email
        if (!is_email($patient_email)) {
            error_log('[MAS APPOINTMENT] ERROR: Email inválido');
            wp_send_json_error(array('message' => 'Email inválido'));
            return;
        }
        
        // Validar RUT si se proporciona
        if (!empty($patient_rut) && !validate_chilean_rut($patient_rut)) {
            error_log('[MAS APPOINTMENT] ERROR: RUT inválido');
            wp_send_json_error(array('message' => 'RUT chileno inválido'));
            return;
        }
        
        // Obtener información del servicio
        $services_table = $wpdb->prefix . 'mas_services';
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $services_table WHERE id = %d AND status = 'active'",
            $service_id
        ));
        
        if (!$service) {
            error_log('[MAS APPOINTMENT] ERROR: Servicio no encontrado o inactivo. Service ID: ' . $service_id);
            wp_send_json_error(array('message' => 'Servicio no válido o inactivo'));
            return;
        }
        
        error_log('[MAS APPOINTMENT] Servicio encontrado: ' . $service->service_name . ' - Precio: ' . $service->price);
        
        // Crear la cita en estado "pending" con payment_status "pending"
        $appointments_table = $wpdb->prefix . 'mas_appointments';
        // CHANGE: Generate short external_reference for appointments (max 12 chars)
        $external_reference = 'APPT' . time(); // Keep it short, let's re-evaluate max length. For now, this is a placeholder.
        // If the intention is to have a unique ID, using the appointment ID generated later is better.
        // Let's defer the external_reference generation until after the appointment_id is known.

        
        $insert_result = $wpdb->insert(
            $appointments_table,
            array(
                'patient_name' => $patient_name,
                'patient_email' => $patient_email,
                'patient_phone' => $patient_phone,
                'patient_rut' => !empty($patient_rut) ? $patient_rut : null,
                'service_id' => $service_id,
                'professional_id' => 0, // Asignar profesional después si es necesario
                'appointment_date' => $appointment_date,
                'appointment_time' => $appointment_time,
                'health_insurance' => !empty($health_insurance) ? $health_insurance : null,
                'notes' => !empty($notes) ? $notes : null,
                'status' => 'pending',
                'payment_status' => 'pending',
                // 'external_reference' => $external_reference, // Will be set after ID is generated
                'created_at' => current_time('mysql', 1)
            ),
            array('%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if (!$insert_result) {
            error_log('[MAS APPOINTMENT] ERROR al insertar cita en BD: ' . $wpdb->last_error);
            wp_send_json_error(array('message' => 'Error interno al crear la cita'));
            return;
        }
        
        $appointment_id = $wpdb->insert_id;
        error_log('[MAS APPOINTMENT] Cita creada con ID: ' . $appointment_id);
        
        // CHANGE: Generate short external_reference for appointments (max 12 chars) using the newly created ID
        $external_reference = 'APPT' . $appointment_id; // This should be unique enough if ID is sequential. Max length check might be needed for DB constraints.
        
        error_log('[MAS APPOINTMENT] External reference generado: ' . $external_reference);
        
        // CHANGE: Update appointment with new short reference
        $wpdb->update(
            $appointments_table,
            array('external_reference' => $external_reference),
            array('id' => $appointment_id),
            array('%s'),
            array('%d')
        );
        
        // Crear preferencia de MercadoPago
        $mercadopago = new MAS_MercadoPago();
        $appointment_data = array(
            'appointment_id' => $appointment_id,
            'service_name' => $service->service_name,
            'service_price' => floatval($service->price),
            'appointment_date' => $appointment_date,
            'professional_name' => 'Profesional Asignado',
            'patient_name' => $patient_name,
            'patient_email' => $patient_email,
            'external_reference' => $external_reference // Pasar el mismo reference generado
        );
        
        error_log('[MAS APPOINTMENT] Llamando a create_appointment_preference con external_reference: ' . $external_reference);
        
        $preference = $mercadopago->create_appointment_preference($appointment_data);
        
        if (!$preference || empty($preference['init_point']) || empty($preference['preference_id'])) {
            // Eliminar la cita creada si falla la preferencia
            $wpdb->delete($appointments_table, array('id' => $appointment_id), array('%d'));
            error_log('[MAS APPOINTMENT] ERROR al crear preferencia. Cita eliminada. MP Response: ' . print_r($preference, true));
            error_log('[MAS APPOINTMENT] ========== FIN (ERROR EN PREFERENCIA) ==========');
            wp_send_json_error(array('message' => 'Error al generar la pasarela de pago.'));
            return;
        }
        
        error_log('[MAS APPOINTMENT] Preferencia creada exitosamente. Preference ID: ' . $preference['preference_id']);
        
        // Actualizar la cita con la información de pago
        $update_result = $wpdb->update(
            $appointments_table,
            array(
                'payment_url' => $preference['init_point'],
                'preference_id' => $preference['preference_id'],
                'amount_paid' => floatval($service->price)
            ),
            array('id' => $appointment_id),
            array('%s', '%s', '%f'),
            array('%d')
        );
        
        if ($update_result === false) {
            error_log('[MAS APPOINTMENT] ERROR al actualizar cita con datos de pago: ' . $wpdb->last_error);
        } else {
            error_log('[MAS APPOINTMENT] Cita actualizada con payment_url y preference_id');
        }
        
        error_log('[MAS APPOINTMENT] ========== FIN (EXITOSO) ==========');
        
        wp_send_json_success(array(
            'init_point' => $preference['init_point'],
            'appointment_id' => $appointment_id,
            'external_reference' => $external_reference
        ));
    }

    // AJAX: Obtener slots disponibles para arriendos de boxes
    public function ajax_get_available_rental_slots() {
        try {
            $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
            
            // Intentar verificar primero con el nonce del admin
            if (!wp_verify_nonce($nonce, 'mas_admin_nonce')) {
                // Si falla, intentar con el nonce público
                if (!wp_verify_nonce($nonce, 'mas_public_nonce')) {
                     // Si ambos fallan, intentar con el nonce específico para obtener slots
                    if (!wp_verify_nonce($nonce, 'mas_get_slots_nonce')) {
                        error_log('[MAS] Verificación de nonce falló para get_available_rental_slots');
                        wp_send_json_error(array('message' => __('Verificación de seguridad falló', 'medical-appointments')));
                        return;
                    }
                }
            }
            
            $date = sanitize_text_field($_POST['date']);
            $box_id = intval($_POST['box_id']);
            $exclude_rental_id = isset($_POST['exclude_rental_id']) ? intval($_POST['exclude_rental_id']) : null;
            
            error_log('[MAS] Cargando slots para box_id: ' . $box_id . ' fecha: ' . $date . ' exclude_rental_id: ' . $exclude_rental_id);
            
            if (!class_exists('MAS_Boxes')) {
                require_once(plugin_dir_path(__FILE__) . 'includes/class-boxes.php');
            }
            
            global $wpdb;
            
            $blocked_dates_table = $wpdb->prefix . 'mas_blocked_dates';
            $is_blocked = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $blocked_dates_table 
                WHERE blocked_date = %s AND blocks_rentals = 1",
                $date
            ));
            
            if ($is_blocked > 0) {
                error_log('[MAS] Fecha bloqueada para arriendos: ' . $date);
                wp_send_json_success(array(
                    'blocked' => true,
                    'message' => __('Esta fecha no está disponible para arriendos', 'medical-appointments')
                ));
                return;
            }
            
            $mas_boxes = new MAS_Boxes();
            $box = $mas_boxes->get_box($box_id);
            
            if (!$box) {
                error_log('[MAS] Box no encontrado: ' . $box_id);
                wp_send_json_error(array('message' => __('Box no encontrado', 'medical-appointments')));
                return;
            }
            
            $slots = $mas_boxes->get_available_rental_slots($box_id, $date, $exclude_rental_id);
            
            error_log('[MAS] Slots disponibles encontrados: ' . count($slots));
            
            wp_send_json_success(array('slots' => $slots));
        } catch (Exception $e) {
            error_log('[MAS] Error en ajax_get_available_rental_slots: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        }
    }
    
    public function ajax_create_multiple_rentals() {
        $nonce_valid = false;
        if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'mas_admin_nonce')) {
            $nonce_valid = true;
        } elseif (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'mas_public_nonce')) {
            $nonce_valid = true;
        }
        
        if (!$nonce_valid) {
            wp_send_json_error(array('message' => 'Seguridad: Nonce inválido'));
            return;
        }
        
        $box_id = intval($_POST['box_id']);
        $professional_id = isset($_POST['professional_id']) ? intval($_POST['professional_id']) : get_current_user_id();
        $rental_date = sanitize_text_field($_POST['rental_date']);
        
        $time_slots_raw = isset($_POST['time_slots']) ? $_POST['time_slots'] : '';
        if (is_string($time_slots_raw)) {
            $time_slots = array_filter(array_map('trim', explode(',', $time_slots_raw)));
        } else {
            $time_slots = is_array($time_slots_raw) ? $time_slots_raw : array();
        }
        
        $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';
        
        $promotion_id = isset($_POST['promotion_id']) ? intval($_POST['promotion_id']) : 0;
        $discount_applied = isset($_POST['discount_applied']) ? floatval($_POST['discount_applied']) : 0;
        
        if (empty($box_id) || empty($professional_id) || empty($rental_date) || empty($time_slots)) {
            wp_send_json_error(array('message' => 'Datos incompletos. Por favor complete todos los campos requeridos.'));
            return;
        }
        
        // Get box information
        global $wpdb;
        $box = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mas_boxes WHERE id = %d",
            $box_id
        ));
        
        if (!$box) {
            wp_send_json_error(array('message' => 'Box no encontrado'));
            return;
        }
        
        // Get settings
        $settings = get_option('mas_settings');
        $slot_duration = isset($settings['slot_duration']) ? intval($settings['slot_duration']) : 60;
        $price_per_block = floatval($box->price_per_hour);
        $total_blocks = count($time_slots);
        
        $subtotal = $price_per_block * $total_blocks;
        $total_price = $subtotal - $discount_applied;
        
        if ($promotion_id > 0) {
            error_log("[MAS RENTAL] Aplicando promoción ID: {$promotion_id}");
            error_log("[MAS RENTAL] Subtotal: {$subtotal}, Descuento: {$discount_applied}, Total final: {$total_price}");
        }
        
        $rental_ids = array();
        $created_rentals_details = array();
        
        require_once plugin_dir_path(__FILE__) . 'includes/class-rentals.php';
        $mas_rentals = new MAS_Rentals();
        
        // Create first rental without reference_id to get the ID
        $first_time_slot = sanitize_text_field($time_slots[0]);
        $end_time_timestamp = strtotime($first_time_slot) + ($slot_duration * 60);
        $end_time = date('H:i', $end_time_timestamp);

        $first_rental_data = array(
            'box_id' => $box_id,
            'professional_id' => $professional_id,
            'rental_date' => $rental_date,
            'start_time' => $first_time_slot,
            'end_time' => $end_time,
            'total_hours' => $slot_duration / 60,
            'total_price' => $price_per_block,
            'status' => 'pending',
            'payment_status' => 'pending',
            'notes' => $notes,
            'promotion_id' => $promotion_id > 0 ? $promotion_id : null,
        );
        
        $first_rental_id = $mas_rentals->create_rental($first_rental_data);
        
        if (!$first_rental_id) {
            wp_send_json_error(array('message' => 'Error al crear el primer arriendo'));
            return;
        }
        
        // Generate reference_id using first rental ID (max 12 chars, no symbols)
        $reference_id = 'RENT' . $first_rental_id;
        
        // Update first rental with reference_id
        $mas_rentals->update_rental($first_rental_id, array(
            'reference_id' => $reference_id,
            'external_reference' => $reference_id
        ));
        
        $rental_ids[] = $first_rental_id;
        $created_rentals_details[] = array(
            'rental_id' => $first_rental_id,
            'date' => $rental_date,
            'time' => $first_time_slot . ' - ' . $end_time
        );
        
        // Create remaining rentals with reference_id
        for ($i = 1; $i < count($time_slots); $i++) {
            $time_slot = $time_slots[$i];
            $start_time = sanitize_text_field($time_slot);
            $end_time_timestamp = strtotime($start_time) + ($slot_duration * 60);
            $end_time = date('H:i', $end_time_timestamp);

            $rental_data = array(
                'box_id' => $box_id,
                'professional_id' => $professional_id,
                'rental_date' => $rental_date,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'total_hours' => $slot_duration / 60,
                'total_price' => $price_per_block,
                'status' => 'pending',
                'payment_status' => 'pending',
                'reference_id' => $reference_id,
                'external_reference' => $reference_id,
                'notes' => $notes,
                'promotion_id' => $promotion_id > 0 ? $promotion_id : null,
            );
            
            $rental_id = $mas_rentals->create_rental($rental_data);
            
            if ($rental_id) {
                $rental_ids[] = $rental_id;
                $created_rentals_details[] = array(
                    'rental_id' => $rental_id,
                    'date' => $rental_date,
                    'time' => $start_time . ' - ' . $end_time
                );
            }
        }
        
        if (empty($rental_ids)) {
            wp_send_json_error(array('message' => 'Error al crear los arriendos'));
            return;
        }
        
        // Fetch professional details for email notification
        $professional_details = $wpdb->get_row($wpdb->prepare(
            "SELECT u.display_name, u.user_email 
             FROM {$wpdb->prefix}mas_professionals p
             JOIN {$wpdb->prefix}users u ON p.user_id = u.ID
             WHERE p.id = %d",
            $professional_id
        ));
        
        $professional_name = $professional_details ? $professional_details->display_name : __('Desconocido', 'medical-appointments');
        $professional_email = $professional_details ? $professional_details->user_email : '';

        // Create MercadoPago preference
        require_once plugin_dir_path(__FILE__) . 'includes/class-mercadopago.php';
        $mercadopago = new MAS_MercadoPago();
        
        // Prepare rental data for MercadoPago
        $rental_data_for_mp = array(
            'rentals' => array(),
            'reference_id' => $reference_id,
            'professional_name' => $professional_name,
            'professional_email' => $professional_email,
            'total_price' => $total_price,
            'discount_applied' => $discount_applied
        );
        
        require_once plugin_dir_path(__FILE__) . 'includes/class-boxes.php';
        $mas_boxes = new MAS_Boxes();

        // This price is what will be sent to MercadoPago for each item, so it should reflect the total price divided by the number of rentals.
        $price_per_rental_discounted = $total_blocks > 0 ? $total_price / $total_blocks : 0;

        // Add each rental info
        foreach ($rental_ids as $rental_id) {
            $rental = $mas_rentals->get_rental($rental_id);
            $box = $mas_boxes->get_box($rental->box_id);
            
            $rental_data_for_mp['rentals'][] = array(
                'box_name' => $box->box_name,
                'rental_date' => $rental->rental_date,
                'start_time' => $rental->start_time,
                // Use discounted price per rental for each item in MP preference
                'price' => round($price_per_rental_discounted, 2) // Ensure price is rounded to 2 decimal places
            );
        }
        
        $preference = $mercadopago->create_rental_preference($rental_data_for_mp);
        
        if ($preference && isset($preference['init_point']) && isset($preference['preference_id'])) {
            foreach ($rental_ids as $rental_id) {
                $mas_rentals->update_rental($rental_id, array(
                    'preference_id' => $preference['preference_id'],
                    'payment_url' => $preference['init_point'],
                    'external_reference' => $preference['external_reference'],
                    // The total_price in the rental record itself can remain as the original price,
                    // or be updated to the discounted price if that's the desired logic for record keeping.
                    // For now, let's update it to the final paid amount that will be charged.
                    'total_price' => round($price_per_rental_discounted, 2) 
                ));
            }

            wp_send_json_success(array(
                'message' => 'Arriendos creados exitosamente',
                'rental_ids' => $rental_ids,
                'init_point' => $preference['init_point'],
                'total' => $total_price // This is the final discounted total
            ));
        } else {
            // Delete the created rentals if payment preference fails
            foreach ($rental_ids as $rental_id) {
                $mas_rentals->delete_rental($rental_id);
            }
            
            error_log('[MAS] Error: No se pudo crear la preferencia de pago de MercadoPago. Respuesta: ' . print_r($preference, true));
            wp_send_json_error(array('message' => 'Error al crear la preferencia de pago. Verifique la configuración de MercadoPago.'));
        }
    }
    
    public function ajax_create_rental() {
        check_ajax_referer('mas_public_nonce', 'nonce');
        
        $data = array(
            'box_id' => intval($_POST['box_id']),
            'professional_id' => intval($_POST['professional_id']),
            'rental_date' => sanitize_text_field($_POST['rental_date']),
            'start_time' => sanitize_text_field($_POST['start_time']),
            'end_time' => sanitize_text_field($_POST['end_time']),
            'notes' => sanitize_textarea_field($_POST['notes'])
        );
        
        $mas_rentals = new MAS_Rentals();
        $result = $mas_rentals->create_rental($data);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Arriendo creado exitosamente', 'medical-appointments')));
        } else {
            wp_send_json_error(array('message' => __('El box no está disponible en ese horario', 'medical-appointments')));
        }
    }
    
    public function ajax_check_box_availability() {
        check_ajax_referer('mas_public_nonce', 'nonce');
        
        $box_id = intval($_POST['box_id']);
        $date = sanitize_text_field($_POST['date']);
        $start_time = sanitize_text_field($_POST['start_time']);
        $end_time = sanitize_text_field($_POST['end_time']);
        
        $mas_boxes = new MAS_Boxes();
        $available = $mas_boxes->is_box_available($box_id, $date, $start_time, $end_time);
        
        wp_send_json_success(array('available' => $available));
    }
    
    /**
     * Crear preferencia de pago en MercadoPago
     */
    public function ajax_create_mp_preference() {
        check_ajax_referer('mas_public_nonce', 'nonce');
        
        $reference_id = sanitize_text_field($_POST['reference_id']);
        $rentals = json_decode(stripslashes($_POST['rentals']), true);
        
        $current_user = wp_get_current_user();
        
        $rental_data = array(
            'rentals' => $rentals,
            'reference_id' => $reference_id,
            'professional_name' => $current_user->display_name,
            'professional_email' => $current_user->user_email
        );
        
        $mas_mp = new MAS_MercadoPago();
        $preference = $mas_mp->create_rental_preference($rental_data);
        
        if ($preference) {
            wp_send_json_success($preference);
        } else {
            wp_send_json_error(array('message' => __('Error al crear preferencia de pago', 'medical-appointments')));
        }
    }
    
    /**
     * Manejar webhook de MercadoPago
     */
    public function handle_mp_webhook() {
        error_log('[MAS WEBHOOK] ========== WEBHOOK CALLED ==========');
        error_log('[MAS WEBHOOK] Request Method: ' . $_SERVER['REQUEST_METHOD']);
        error_log('[MAS WEBHOOK] GET params: ' . print_r($_GET, true));
        error_log('[MAS WEBHOOK] POST params: ' . print_r($_POST, true));
        error_log('[MAS WEBHOOK] Headers: ' . print_r(getallheaders(), true));
        
        // Get raw input
        $raw_input = file_get_contents('php://input');
        error_log('[MAS WEBHOOK] Raw input: ' . $raw_input);
        
        // Try to decode JSON
        $json_data = json_decode($raw_input, true);
        if ($json_data) {
            error_log('[MAS WEBHOOK] JSON decoded: ' . print_r($json_data, true));
        }
        
        // Verify this is actually being called
        error_log('[MAS WEBHOOK] Function handle_mp_webhook is executing');
        
        $topic = isset($_GET['topic']) ? sanitize_text_field($_GET['topic']) : 
                 (isset($_POST['topic']) ? sanitize_text_field($_POST['topic']) : 
                 (isset($json_data['topic']) ? sanitize_text_field($json_data['topic']) : null));
        
        $id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : 
              (isset($_POST['id']) ? sanitize_text_field($_POST['id']) : 
              (isset($json_data['id']) ? sanitize_text_field($json_data['id']) : null));
        
        $data_id = isset($_GET['data.id']) ? sanitize_text_field($_GET['data.id']) : 
                   (isset($_POST['data_id']) ? sanitize_text_field($_POST['data_id']) : 
                   (isset($json_data['data']['id']) ? sanitize_text_field($json_data['data']['id']) : null));
        
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : null;
        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 
                (isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 
                (isset($json_data['type']) ? sanitize_text_field($json_data['type']) : null));

        $get_params = $_GET;
        $post_params = $_POST;
        $headers = getallheaders();
        
        error_log('[MAS WEBHOOK] Parsed values - Topic: ' . $topic . ', ID: ' . $id . ', Data ID: ' . $data_id . ', Type: ' . $type);
        
        status_header(200);
        header('Content-Type: application/json');
        
        // If this is just a test ping from MercadoPago, respond and exit
        if (!$topic && !$type && !$id && !$data_id) {
            error_log('[MAS WEBHOOK] Test ping detected, responding with success');
            // Log the ping as a test event
            $this->log_webhook_event(
                'ping', 'test', null, null, 
                $_SERVER['REQUEST_METHOD'], 
                $get_params, $post_params, $raw_input, $headers, json_encode($json_data)
            );
            echo json_encode(['status' => 'ok', 'message' => 'webhook endpoint active']);
            exit;
        }
        
        // Log the incoming webhook data before processing
        $log_id = $this->log_webhook_event(
            $topic, $type, $id, $data_id, 
            $_SERVER['REQUEST_METHOD'], 
            $get_params, $post_params, $raw_input, $headers, json_encode($json_data)
        );

        if (!$log_id) {
            error_log('[MAS WEBHOOK] ERROR: Failed to log webhook event.');
            echo json_encode(['status' => 'error', 'message' => 'Failed to log webhook event']);
            exit;
        }
        
        $processing_status = 'error';
        $processing_message = 'Unknown notification type';

        if ($topic === 'payment' || $type === 'payment') {
            $payment_id = $data_id ?: $id;
            error_log('[MAS WEBHOOK] Processing payment notification - Payment ID: ' . $payment_id);
            
            if (!$payment_id) {
                error_log('[MAS WEBHOOK] ERROR: No payment ID found');
                $processing_message = 'No payment ID found';
            } else {
                $result = $this->process_payment_notification($payment_id);
                if ($result['success']) {
                    $processing_status = 'success';
                    $processing_message = 'Payment processed';
                } else {
                    $processing_status = 'error';
                    $processing_message = $result['message'];
                }
            }
            $this->update_webhook_log_status($log_id, $processing_status, $processing_message);
            echo json_encode(['status' => $processing_status, 'message' => $processing_message]);
            exit;
        }
        
        if ($topic === 'merchant_order' || $type === 'merchant_order') {
            $order_id = $data_id ?: $id;
            error_log('[MAS WEBHOOK] Processing merchant order notification - Order ID: ' . $order_id);
            
            if (!$order_id) {
                error_log('[MAS WEBHOOK] ERROR: No order ID found');
                $processing_message = 'No order ID found';
            } else {
                $result = $this->process_order_notification($order_id);
                if ($result['success']) {
                    $processing_status = 'success';
                    $processing_message = 'Order processed';
                } else {
                    $processing_status = 'error';
                    $processing_message = $result['message'];
                }
            }
            $this->update_webhook_log_status($log_id, $processing_status, $processing_message);
            echo json_encode(['status' => $processing_status, 'message' => $processing_message]);
            exit;
        }
        
        $this->update_webhook_log_status($log_id, $processing_status, $processing_message);
        echo json_encode(['status' => 'ok', 'message' => 'Notification received but not processed']);
        exit;
    }
    
    /**
     * Log webhook event details to the database
     */
    private function log_webhook_event($topic, $type, $mp_id, $data_id, $request_method, $get_params, $post_params, $raw_input, $headers, $decoded_json) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mas_webhook_logs';
        
        // Ensure arrays are converted to strings for DB insertion
        $get_params_str = is_array($get_params) ? json_encode($get_params) : $get_params;
        $post_params_str = is_array($post_params) ? json_encode($post_params) : $post_params;
        $headers_str = is_array($headers) ? json_encode($headers) : $headers;

        $inserted = $wpdb->insert($table_name, array(
            'topic' => sanitize_text_field($topic),
            'type' => sanitize_text_field($type),
            'mp_id' => sanitize_text_field($mp_id),
            'data_id' => sanitize_text_field($data_id),
            'request_method' => sanitize_text_field($request_method),
            'get_params' => $get_params_str,
            'post_params' => $post_params_str,
            'raw_input' => $raw_input,
            'headers' => $headers_str,
            'decoded_json' => $decoded_json,
            'processing_status' => 'received',
            'created_at' => current_time('mysql', 1) // GMT time
        ));
        
        if ($inserted) {
            return $wpdb->insert_id;
        } else {
            error_log('[MAS WEBHOOK LOG] Failed to insert webhook log: ' . $wpdb->last_error);
            return false;
        }
    }

    /**
     * Update the status of a logged webhook event
     */
    private function update_webhook_log_status($log_id, $status, $message) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mas_webhook_logs';
        
        $wpdb->update($table_name, array(
            'processing_status' => sanitize_text_field($status),
            'processing_message' => sanitize_textarea_field($message),
            'processed_at' => current_time('mysql', 1) // GMT time
        ), array('id' => $log_id));
    }

    /**
     * Verify and process payment manually (fallback when webhook doesn't trigger)
     */
    public function ajax_verify_payment() {
        // Check nonce from both admin and public contexts
        $nonce_valid = false;
        if (isset($_POST['nonce'])) {
            if (wp_verify_nonce($_POST['nonce'], 'mas_admin_nonce')) { // Assuming admin nonce might be used for manual verification
                $nonce_valid = true;
            } elseif (wp_verify_nonce($_POST['nonce'], 'mas_public_nonce')) { // Assuming public nonce might be used if a user manually retries
                $nonce_valid = true;
            }
        }

        if (!$nonce_valid) {
            error_log('[MAS VERIFY] Error: Nonce inválido');
            wp_send_json_error(array('message' => 'Seguridad: Nonce inválido'));
            return;
        }
        
        $payment_id = isset($_POST['payment_id']) ? sanitize_text_field($_POST['payment_id']) : '';
        $external_reference = isset($_POST['external_reference']) ? sanitize_text_field($_POST['external_reference']) : '';
        
        error_log('[MAS VERIFY] Verificación manual de pago iniciada - Payment ID: ' . $payment_id . ' - Ref: ' . $external_reference);
        
        if (empty($payment_id) || empty($external_reference)) {
            error_log('[MAS VERIFY] Error: Parámetros faltantes');
            wp_send_json_error(array('message' => 'Parámetros faltantes'));
            return;
        }
        
        global $wpdb;
        
        // Check if payment is already processed by looking at the status in our DB
        $already_processed = false;
        if (strpos($external_reference, 'RENT') === 0) { // Updated check to match 'RENT' prefix
            $table_rentals = $wpdb->prefix . 'mas_box_rentals';
            $current_payment_status = $wpdb->get_var($wpdb->prepare(
                "SELECT payment_status FROM $table_rentals WHERE reference_id = %s LIMIT 1",
                $external_reference
            ));
            error_log('[MAS VERIFY] Estado actual del arriendo en DB: ' . $current_payment_status);
            if ($current_payment_status === 'paid') {
                $already_processed = true;
            }
        } elseif (strpos($external_reference, 'APPT') === 0) { // Updated check to match 'APPT' prefix
            // This logic now depends on the updated external_reference format for appointments.
            // It should start with 'APPT' and be followed by the appointment ID.
            $appointment_id_str = str_replace('APPT', '', $external_reference);
            $appointment_id = intval($appointment_id_str);
            if ($appointment_id > 0) {
                $table_appointments = $wpdb->prefix . 'mas_appointments';
                $current_payment_status = $wpdb->get_var($wpdb->prepare(
                    "SELECT payment_status FROM $table_appointments WHERE id = %d",
                    $appointment_id
                ));
                error_log('[MAS VERIFY] Estado actual de la cita en DB: ' . $current_payment_status);
                if ($current_payment_status === 'paid') {
                    $already_processed = true;
                }
            } else {
                 error_log('[MAS VERIFY] Error: ID de cita inválido extraído de la referencia: ' . $external_reference);
            }
        } else {
            error_log('[MAS VERIFY] Advertencia: Referencia externa desconocida para verificación: ' . $external_reference);
        }
        
        if ($already_processed) {
            error_log('[MAS VERIFY] Pago ya procesado anteriormente en nuestra base de datos');
            wp_send_json_success(array('message' => 'Pago ya procesado', 'already_processed' => true));
            return;
        }
        
        // Payment not processed yet, get info from MercadoPago and process it
        error_log('[MAS VERIFY] Pago no procesado en DB, obteniendo información de MercadoPago...');
        
        $mas_mp = new MAS_MercadoPago();
        $access_token = $mas_mp->get_access_token();
        
        if (empty($access_token)) {
            error_log('[MAS VERIFY] Error: No hay access token configurado en MercadoPago');
            wp_send_json_error(array('message' => 'Configuración de MercadoPago incompleta'));
            return;
        }
        
        // Get payment info from MercadoPago using the provided payment_id
        $payment_info = $mas_mp->get_payment_info($payment_id);
        
        if (!$payment_info) {
            error_log('[MAS VERIFY] Error: No se pudo obtener información del pago desde MercadoPago.');
            wp_send_json_error(array('message' => 'Error al consultar MercadoPago.'));
            return;
        }
        
        error_log('[MAS VERIFY] Información del pago obtenida de MercadoPago: ' . print_r($payment_info, true));
        
        $payment_status = $payment_info['status'] ?? null;
        $payment_method = $payment_info['payment_method_id'] ?? '';
        
        if (!$payment_status) {
            error_log('[MAS VERIFY] Error: Estado del pago no encontrado en la respuesta de MercadoPago.');
            wp_send_json_error(array('message' => 'Estado del pago no disponible.'));
            return;
        }
        
        // Process the payment status update
        error_log('[MAS VERIFY] Procesando actualización de pago con estado: ' . $payment_status);
        $update_result = $this->update_payment_status($external_reference, $payment_status, $payment_id, $payment_method);
        
        if ($update_result['success']) {
            wp_send_json_success(array(
                'message' => 'Pago verificado y procesado correctamente',
                'status' => $payment_status,
                'payment_method' => $payment_method
            ));
        } else {
            error_log('[MAS VERIFY] Error al actualizar el estado del pago en la base de datos: ' . $update_result['message']);
            wp_send_json_error(array('message' => 'Error al procesar el pago: ' . $update_result['message']));
        }
    }
    
    /**
     * Process payment notification from MercadoPago webhook
     */
    private function process_payment_notification($payment_id) {
        error_log('[MAS MP Webhook] Procesando notificación de pago: ' . $payment_id);
        
        $mas_mp = new MAS_MercadoPago();
        $payment_info = $mas_mp->get_payment_info($payment_id);
        
        if (!$payment_info) {
            error_log('[MAS MP Webhook] No se pudo obtener información del pago.');
            return ['success' => false, 'message' => 'No se pudo obtener información del pago.'];
        }
        
        error_log('[MAS MP Webhook] Info del pago: ' . print_r($payment_info, true));
        
        $external_reference = isset($payment_info['external_reference']) ? $payment_info['external_reference'] : null;
        $payment_status = isset($payment_info['status']) ? $payment_info['status'] : null;
        $payment_method = isset($payment_info['payment_method_id']) ? $payment_info['payment_method_id'] : '';
        
        error_log('[MAS MP Webhook] Pago procesado: ' . $payment_id . ' - Estado: ' . $payment_status . ' - Ref: ' . $external_reference);
        
        if ($external_reference && $payment_status) {
            return $this->update_payment_status($external_reference, $payment_status, $payment_id, $payment_method);
        } else {
            error_log('[MAS MP Webhook] ERROR: Referencia externa o estado de pago faltantes.');
            return ['success' => false, 'message' => 'Referencia externa o estado de pago faltantes.'];
        }
    }
    
    /**
     * Process merchant order notification from MercadoPago webhook
     */
    private function process_order_notification($merchant_order_id) {
        error_log('[MAS MP Webhook] Procesando notificación de merchant order: ' . $merchant_order_id);
        
        $mas_mp = new MAS_MercadoPago();
        
        // Get merchant order info
        $merchant_order = $mas_mp->get_merchant_order_info($merchant_order_id);
        
        if (!$merchant_order) {
            error_log('[MAS MP Webhook] Merchant order inválida o no encontrada.');
            return ['success' => false, 'message' => 'Merchant order inválida o no encontrada.'];
        }
        
        error_log('[MAS MP Webhook] Merchant Order: ' . print_r($merchant_order, true));
        
        $external_reference = isset($merchant_order['external_reference']) ? $merchant_order['external_reference'] : null;
        $order_status = isset($merchant_order['order_status']) ? $merchant_order['order_status'] : null;
        
        // Check if there are payments associated with this order
        if (empty($merchant_order['payments'])) {
            error_log('[MAS MP Webhook] Orden sin pagos aún - Ref: ' . $external_reference . ' - Estado: ' . $order_status);
            return ['success' => false, 'message' => 'Orden sin pagos asociados.'];
        }
        
        // Get first payment from the order for status update
        $first_payment = $merchant_order['payments'][0];
        $payment_id = isset($first_payment['id']) ? $first_payment['id'] : null;
        $payment_status = isset($first_payment['status']) ? $first_payment['status'] : null;
        $payment_method = isset($first_payment['payment_method_id']) ? $first_payment['payment_method_id'] : '';
        
        if ($external_reference && $payment_status && $payment_id) {
            return $this->update_payment_status($external_reference, $payment_status, $payment_id, $payment_method);
        } else {
            error_log('[MAS MP Webhook] ERROR: Referencia externa, ID de pago o estado de pago faltantes para merchant order.');
            return ['success' => false, 'message' => 'Datos de pago faltantes para la orden.'];
        }
    }
    
    /**
     * Update payment status in our database for appointments or rentals
     * Returns an array with 'success' (bool) and 'message' (string)
     */
    private function update_payment_status($external_reference, $payment_status, $payment_id, $payment_method = '') {
        global $wpdb;
        
        error_log('[MAS Payment Status] Actualizando estado de pago - Ref: ' . $external_reference . ' - Estado MP: ' . $payment_status . ' - Payment ID: ' . $payment_id);
        
        $updated = false;
        $message = '';
        
        // Detect if it's rental or appointment
        if (strpos($external_reference, 'RENT') === 0) { // Updated check to match 'RENT' prefix
            $table_rentals = $wpdb->prefix . 'mas_box_rentals';
            
            // Fetch rental IDs associated with this reference that are not yet paid
            $rental_ids_to_update = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM $table_rentals WHERE reference_id = %s AND payment_status != 'paid'",
                $external_reference
            ));
            
            if (empty($rental_ids_to_update)) {
                error_log('[MAS Payment Status] Ningún arriendo pendiente encontrado para la referencia: ' . $external_reference);
                return ['success' => false, 'message' => 'No hay arriendos pendientes para esta referencia.'];
            }
            
            $base_update_data = array(
                'mp_payment_id' => sanitize_text_field($payment_id),
            );
            
            if (!empty($payment_method)) {
                $base_update_data['payment_method'] = sanitize_text_field($payment_method);
            }
            
            if ($payment_status === 'approved' || $payment_status === 'authorized') { // 'authorized' might be used for some methods
                $update_data = array_merge($base_update_data, array(
                    'payment_status' => 'paid',
                    'status' => 'active' // Assuming active rental once paid
                ));
                
                $affected_rows = 0;
                foreach ($rental_ids_to_update as $rental_id) {
                    $wpdb->update(
                        $table_rentals,
                        $update_data,
                        array('id' => $rental_id),
                        array('%s', '%s', '%s', '%s'), // $update_data formats
                        array('%d') // $where formats
                    );
                    if ($wpdb->last_error) {
                        error_log('[MAS Payment Status] DB Error updating rental ' . $rental_id . ': ' . $wpdb->last_error);
                    } else {
                        $affected_rows++;
                    }
                }
                
                error_log(sprintf('[MAS Payment Status] Arriendos actualizados (paid): %d filas afectadas para ref %s', $affected_rows, $external_reference));
                $updated = true;
                $message = 'Arriendos marcados como pagados.';
                
                // Send confirmation emails
                if ($affected_rows > 0) {
                    foreach ($rental_ids_to_update as $rental_id) {
                        $this->send_rental_confirmation_email($rental_id, $payment_id, $payment_method);
                    }
                }
            } elseif ($payment_status === 'rejected' || $payment_status === 'cancelled') {
                $update_data = array_merge($base_update_data, array(
                    'payment_status' => 'failed',
                    'status' => 'cancelled'
                ));
                
                $affected_rows = 0;
                foreach ($rental_ids_to_update as $rental_id) {
                     $wpdb->update(
                        $table_rentals,
                        $update_data,
                        array('id' => $rental_id),
                        array('%s', '%s', '%s', '%s'), // $update_data formats
                        array('%d') // $where formats
                    );
                    if ($wpdb->last_error) {
                        error_log('[MAS Payment Status] DB Error updating rental ' . $rental_id . ': ' . $wpdb->last_error);
                    } else {
                        $affected_rows++;
                    }
                }
                error_log(sprintf('[MAS Payment Status] Arriendos marcados como fallidos/cancelados: %d filas afectadas para ref %s', $affected_rows, $external_reference));
                $updated = true;
                $message = 'Arriendos marcados como fallidos.';
            } elseif ($payment_status === 'pending' || $payment_status === 'in_process') {
                $update_data = array_merge($base_update_data, array(
                    'payment_status' => 'pending'
                ));
                
                $affected_rows = 0;
                 foreach ($rental_ids_to_update as $rental_id) {
                     $wpdb->update(
                        $table_rentals,
                        $update_data,
                        array('id' => $rental_id),
                        array('%s', '%s', '%s'), // $update_data formats
                        array('%d') // $where formats
                    );
                    if ($wpdb->last_error) {
                        error_log('[MAS Payment Status] DB Error updating rental ' . $rental_id . ': ' . $wpdb->last_error);
                    } else {
                        $affected_rows++;
                    }
                }
                error_log(sprintf('[MAS Payment Status] Arriendos marcados como pendientes: %d filas afectadas para ref %s', $affected_rows, $external_reference));
                $updated = true;
                $message = 'Arriendos marcados como pendientes.';
            } else {
                error_log('[MAS Payment Status] Estado de pago desconocido de MercadoPago para arriendo: ' . $payment_status);
                $message = 'Estado de pago desconocido de MercadoPago.';
            }

        } elseif (strpos($external_reference, 'APPT') === 0) { // Updated check to match 'APPT' prefix
            $appointment_id_str = str_replace('APPT', '', $external_reference);
            $appointment_id = intval($appointment_id_str);
            $table_appointments = $wpdb->prefix . 'mas_appointments';
            
            if ($appointment_id <= 0) {
                error_log('[MAS Payment Status] ERROR: Invalid appointment ID extracted from reference: ' . $external_reference);
                return ['success' => false, 'message' => 'ID de cita inválido.'];
            }

            // Check if already processed for this specific appointment
            $current_status = $wpdb->get_var($wpdb->prepare(
                "SELECT payment_status FROM $table_appointments WHERE id = %d",
                $appointment_id
            ));
            
            if ($current_status === 'paid') {
                error_log('[MAS Payment Status] Cita ' . $appointment_id . ' ya está marcada como pagada.');
                return ['success' => false, 'message' => 'La cita ya ha sido procesada.'];
            }

            $update_data = array(
                'mp_payment_id' => sanitize_text_field($payment_id),
            );
            
            if (!empty($payment_method)) {
                $update_data['payment_method'] = sanitize_text_field($payment_method);
            }

            if ($payment_status === 'approved' || $payment_status === 'authorized') {
                $update_data['payment_status'] = 'paid';
                $update_data['status'] = 'confirmed'; // Assuming confirmed appointment once paid
                
                $wpdb->update(
                    $table_appointments,
                    $update_data,
                    array('id' => $appointment_id),
                    array('%s', '%s', '%s', '%s'), // $update_data formats
                    array('%d') // $where formats
                );
                
                if ($wpdb->last_error) {
                    error_log('[MAS Payment Status] DB Error updating appointment ' . $appointment_id . ': ' . $wpdb->last_error);
                    return ['success' => false, 'message' => 'Error al actualizar la base de datos.'];
                }
                
                error_log('[MAS Payment Status] Cita confirmada - ID: ' . $appointment_id);
                $updated = true;
                $message = 'Cita marcada como pagada.';
                
                // Send confirmation email
                $this->send_appointment_confirmation_email($appointment_id, $payment_id, $payment_method);
                
            } elseif ($payment_status === 'rejected' || $payment_status === 'cancelled') {
                $update_data['payment_status'] = 'failed';
                $update_data['status'] = 'cancelled';
                
                $wpdb->update(
                    $table_appointments,
                    $update_data,
                    array('id' => $appointment_id),
                    array('%s', '%s', '%s', '%s'), // $update_data formats
                    array('%d') // $where formats
                );
                 if ($wpdb->last_error) {
                    error_log('[MAS Payment Status] DB Error updating appointment ' . $appointment_id . ': ' . $wpdb->last_error);
                    return ['success' => false, 'message' => 'Error al actualizar la base de datos.'];
                }
                error_log('[MAS Payment Status] Cita marcada como fallida/cancelada - ID: ' . $appointment_id);
                $updated = true;
                $message = 'Cita marcada como fallida.';
            } elseif ($payment_status === 'pending' || $payment_status === 'in_process') {
                $update_data['payment_status'] = 'pending';
                
                $wpdb->update(
                    $table_appointments,
                    $update_data,
                    array('id' => $appointment_id),
                    array('%s', '%s', '%s'), // $update_data formats
                    array('%d') // $where formats
                );
                 if ($wpdb->last_error) {
                    error_log('[MAS Payment Status] DB Error updating appointment ' . $appointment_id . ': ' . $wpdb->last_error);
                    return ['success' => false, 'message' => 'Error al actualizar la base de datos.'];
                }
                error_log('[MAS Payment Status] Cita marcada como pendiente - ID: ' . $appointment_id);
                $updated = true;
                $message = 'Cita marcada como pendiente.';
            } else {
                error_log('[MAS Payment Status] Estado de pago desconocido de MercadoPago para cita: ' . $payment_status);
                $message = 'Estado de pago desconocido de MercadoPago.';
            }
        } else {
            error_log('[MAS Payment Status] ERROR: Referencia externa desconocida: ' . $external_reference);
            return ['success' => false, 'message' => 'Referencia externa desconocida.'];
        }
        
        return ['success' => $updated, 'message' => $message];
    }
    
    /**
     * Send confirmation email for a rental
     */
    private function send_rental_confirmation_email($rental_id, $payment_id, $payment_method) {
        global $wpdb;
        $table_rentals = $wpdb->prefix . 'mas_box_rentals';
        $table_boxes = $wpdb->prefix . 'mas_boxes';
        $table_users = $wpdb->prefix . 'users';
        
        // Get professional's user ID first
        $professional_user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}mas_professionals WHERE id = %d",
            $wpdb->get_var($wpdb->prepare("SELECT professional_id FROM $table_rentals WHERE id = %d", $rental_id))
        ));

        if (!$professional_user_id) {
            error_log('[MAS Email] No se encontró user_id para el profesional del arriendo ID: ' . $rental_id);
            return false;
        }

        $rental = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, b.box_name, u.user_email, u.display_name 
            FROM $table_rentals r
            LEFT JOIN $table_boxes b ON r.box_id = b.id
            LEFT JOIN $table_users u ON r.professional_id = (SELECT p.id FROM {$wpdb->prefix}mas_professionals p WHERE p.user_id = u.ID AND p.id = r.professional_id)
            WHERE r.id = %d",
            $rental_id
        ));

        // Fallback to using the professional_id directly if the join with users table fails to get display_name/user_email
        if (!$rental || !$rental->user_email) {
            $professional_user_data = get_userdata($professional_user_id);
            if ($professional_user_data) {
                $rental->display_name = $professional_user_data->display_name;
                $rental->user_email = $professional_user_data->user_email;
            } else {
                 error_log('[MAS Email] No se pudo obtener datos de usuario para el profesional del arriendo ID: ' . $rental_id);
            }
        }

        if (!$rental || !$rental->user_email) {
            error_log('[MAS Email] No se pudo obtener datos de email para enviar email de confirmación de arriendo ID: ' . $rental_id);
            return false;
        }
        
        $subject = 'Confirmación de Arriendo de Box';
        $message = "Hola " . esc_html($rental->display_name) . ",\n\n";
        $message .= "Tu arriendo de box ha sido confirmado.\n\n";
        $message .= "Detalles:\n";
        $message .= "- Box: " . esc_html($rental->box_name) . "\n";
        $message .= "- Fecha: " . date('d/m/Y', strtotime($rental->rental_date)) . "\n";
        $message .= "- Hora: " . date('H:i', strtotime($rental->start_time)) . " - " . date('H:i', strtotime($rental->end_time)) . "\n";
        if ($payment_method) {
            $message .= "- Método de pago: " . strtoupper(esc_html($payment_method)) . "\n";
        }
        $message .= "- ID de transacción: " . esc_html($payment_id) . "\n\n";
        $message .= "Gracias por tu preferencia.";
        
        $sent = wp_mail($rental->user_email, $subject, $message);
        
        if ($sent) {
            error_log('[MAS Email] Email de confirmación de arriendo enviado a: ' . $rental->user_email . ' para ID: ' . $rental_id);
        } else {
            error_log('[MAS Email] Falló el envío del email de confirmación de arriendo a: ' . $rental->user_email . ' para ID: ' . $rental_id);
        }
        return $sent;
    }

    /**
     * Send confirmation email for an appointment
     */
    private function send_appointment_confirmation_email($appointment_id, $payment_id, $payment_method) {
        global $wpdb;
        $table_appointments = $wpdb->prefix . 'mas_appointments';
        
        $appointment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_appointments WHERE id = %d",
            $appointment_id
        ));
        
        if (!$appointment || !$appointment->patient_email) {
            error_log('[MAS Email] No se pudo obtener datos para enviar email de confirmación de cita ID: ' . $appointment_id);
            return false;
        }
        
        $subject = 'Confirmación de Cita Médica';
        $message = "Hola " . esc_html($appointment->patient_name) . ",\n\n";
        $message .= "Tu cita médica ha sido confirmada.\n\n";
        $message .= "Detalles:\n";
        $message .= "- Fecha: " . date('d/m/Y', strtotime($appointment->appointment_date)) . "\n";
        $message .= "- Hora: " . date('H:i', strtotime($appointment->appointment_time)) . "\n";
        if ($payment_method) {
            $message .= "- Método de pago: " . strtoupper(esc_html($payment_method)) . "\n";
        }
        $message .= "\nGracias por tu preferencia.";
        
        $sent = wp_mail($appointment->patient_email, $subject, $message);
        
        if ($sent) {
            error_log('[MAS Email] Email de confirmación de cita enviado a: ' . $appointment->patient_email . ' para ID: ' . $appointment_id);
        } else {
            error_log('[MAS Email] Falló el envío del email de confirmación de cita a: ' . $appointment->patient_email . ' para ID: ' . $appointment_id);
        }
        return $sent;
    }

    /**
     * AJAX handler to delete pending rentals associated with an external_reference
     */
    public function ajax_delete_pending_payment() {
        // Verify nonce
        check_ajax_referer('mas_delete_pending_payment', 'nonce');
        
        // Verify user is authenticated
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Debes iniciar sesión para realizar esta acción.', 'medical-appointments')));
            return;
        }
        
        $external_reference = isset($_POST['external_reference']) ? sanitize_text_field($_POST['external_reference']) : '';
        
        if (empty($external_reference)) {
            wp_send_json_error(array('message' => __('Referencia externa inválida.', 'medical-appointments')));
            return;
        }
        
        global $wpdb;
        $table_rentals = $wpdb->prefix . 'mas_box_rentals';
        
        // Get current user's professional ID
        $current_user_id = get_current_user_id();
        $professional = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}mas_professionals WHERE user_id = %d",
            $current_user_id
        ));
        
        if (!$professional) {
            wp_send_json_error(array('message' => __('No se encontró tu perfil de profesional.', 'medical-appointments')));
            return;
        }
        
        // Verify that the rentals belong to this professional and are pending
        $rentals_to_delete = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM $table_rentals 
            WHERE external_reference = %s 
            AND professional_id = %d 
            AND payment_status = 'pending'",
            $external_reference,
            $professional->id
        ));
        
        if (empty($rentals_to_delete)) {
            wp_send_json_error(array('message' => __('No se encontraron arriendos pendientes con esa referencia o no tienes permisos.', 'medical-appointments')));
            return;
        }
        
        // Delete all rentals with this external_reference
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_rentals 
            WHERE external_reference = %s 
            AND professional_id = %d 
            AND payment_status = 'pending'",
            $external_reference,
            $professional->id
        ));
        
        if ($deleted === false) {
            error_log('[MAS] Error al eliminar arriendos pendientes con referencia: ' . $external_reference);
            wp_send_json_error(array('message' => __('Error al eliminar los arriendos pendientes.', 'medical-appointments')));
            return;
        }
        
        error_log(sprintf(
            '[MAS] Usuario %d eliminó %d arriendo(s) pendiente(s) con referencia: %s',
            $current_user_id,
            $deleted,
            $external_reference
        ));
        
        wp_send_json_success(array(
            'message' => sprintf(
                __('Se eliminaron %d arriendo(s) pendiente(s) exitosamente.', 'medical-appointments'),
                $deleted
            ),
            'deleted_count' => $deleted
        ));
    }


    /**
     * AJAX handler to get details of a specific webhook log entry
     */
    public function ajax_get_webhook_log_details() {
        check_ajax_referer('mas_webhook_logs', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permiso denegado'));
        }
        
        $log_id = intval($_POST['log_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'mas_webhook_logs';
        
        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $log_id
        ));
        
        if (!$log) {
            wp_send_json_error(array('message' => 'Registro de log no encontrado'));
        }
        
        // Format JSON for better display
        // Attempt to decode and re-encode with pretty print; handle potential JSON errors
        $log->get_params_formatted = $this->format_json_for_display($log->get_params);
        $log->post_params_formatted = $this->format_json_for_display($log->post_params);
        $log->headers_formatted = $this->format_json_for_display($log->headers);
        $log->decoded_json_formatted = $this->format_json_for_display($log->decoded_json);
        
        // Add formatted versions of params to the log object
        $log->get_params = $log->get_params_formatted;
        $log->post_params = $log->post_params_formatted;
        $log->headers = $log->headers_formatted;
        $log->decoded_json = $log->decoded_json_formatted;
        
        wp_send_json_success($log);
    }

    /**
     * Helper to safely format JSON strings for display
     */
    private function format_json_for_display($json_string) {
        if (empty($json_string)) {
            return '';
        }
        $data = json_decode($json_string, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        // If not valid JSON, return as is, perhaps with highlighting if needed, or just plain text
        return esc_html($json_string);
    }

    // AJAX handler for active promotions
    public function ajax_get_active_promotions() {
        try {
            $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
            
            // Intentar verificar primero con el nonce del admin
            if (!wp_verify_nonce($nonce, 'mas_admin_nonce')) {
                // Si falla, intentar con el nonce público
                if (!wp_verify_nonce($nonce, 'mas_public_nonce')) {
                    wp_send_json_error(array('message' => __('Verificación de seguridad falló', 'medical-appointments')));
                    return;
                }
            }
            
            if (!class_exists('MAS_Promotions')) {
                require_once(plugin_dir_path(__FILE__) . 'includes/class-promotions.php');
            }
            
            $mas_promotions = new MAS_Promotions();
            $promotions = $mas_promotions->get_active_promotions();
            
            wp_send_json_success(array(
                'promotions' => $promotions
            ));
        } catch (Exception $e) {
            error_log('[MAS] Error en ajax_get_active_promotions: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        }
    }
    
    /**
     * AJAX: Obtener horarios de un profesional
     */
    public function ajax_get_professional_schedule() {
        if (!wp_verify_nonce($_POST['nonce'], 'mas_get_schedule_nonce')) {
            wp_send_json_error('Verificación de seguridad fallida');
        }
        
        $professional_id = intval($_POST['professional_id']);
        
        if (!$professional_id) {
            wp_send_json_error('ID de profesional no válido');
        }
        
        $schedule = MAS_Professional_Schedules::get_schedule($professional_id);
        wp_send_json_success($schedule);
    }
    
    /**
     * AJAX: Guardar horarios de un profesional
     */
    public function ajax_save_professional_schedule() {
        if (!wp_verify_nonce($_POST['mas_schedule_nonce'], 'mas_save_schedule_action')) {
            wp_send_json_error('Verificación de seguridad fallida');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tiene permisos para realizar esta acción');
        }
        
        $professional_id = intval($_POST['professional_id']);
        $schedule_data = isset($_POST['schedule']) ? $_POST['schedule'] : array();
        
        if (!$professional_id) {
            wp_send_json_error('ID de profesional no válido');
        }
        
        $result = MAS_Professional_Schedules::save_schedule($professional_id, $schedule_data);
        
        if ($result) {
            wp_send_json_success('Horarios guardados correctamente');
        } else {
            wp_send_json_error('Error al guardar los horarios');
        }
    }
    
    /**
     * AJAX: Obtener servicios de un profesional
     */
    public function ajax_get_professional_services() {
        if (!wp_verify_nonce($_POST['nonce'], 'mas_get_services_nonce')) {
            wp_send_json_error('Verificación de seguridad fallida');
        }
        
        $professional_id = intval($_POST['professional_id']);
        
        if (!$professional_id) {
            wp_send_json_error('ID de profesional no válido');
        }
        
        global $wpdb;
        $services = $wpdb->get_col($wpdb->prepare(
            "SELECT service_id FROM {$wpdb->prefix}mas_professional_services WHERE professional_id = %d",
            $professional_id
        ));
        
        wp_send_json_success($services);
    }
    
    /**
     * AJAX: Guardar servicios de un profesional
     */
    public function ajax_save_professional_services() {
        if (!wp_verify_nonce($_POST['mas_services_nonce'], 'mas_save_services_action')) {
            wp_send_json_error('Verificación de seguridad fallida');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tiene permisos para realizar esta acción');
        }
        
        $professional_id = intval($_POST['professional_id']);
        $services = isset($_POST['services']) ? array_map('intval', $_POST['services']) : array();
        
        if (!$professional_id) {
            wp_send_json_error('ID de profesional no válido');
        }
        
        global $wpdb;
        
        // Eliminar servicios anteriores
        $wpdb->delete(
            $wpdb->prefix . 'mas_professional_services',
            array('professional_id' => $professional_id),
            array('%d')
        );
        
        // Insertar nuevos servicios
        foreach ($services as $service_id) {
            $wpdb->insert(
                $wpdb->prefix . 'mas_professional_services',
                array(
                    'professional_id' => $professional_id,
                    'service_id' => $service_id,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%s')
            );
        }
        
        wp_send_json_success('Servicios guardados correctamente');
    }
    
    /**
     * AJAX: Obtener profesionales por servicio
     */
    public function ajax_get_professionals_by_service() {
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        
        if (!wp_verify_nonce($nonce, 'mas_public_nonce')) {
            wp_send_json_error('Verificación de seguridad fallida');
        }
        
        $service_id = intval($_POST['service_id']);
        
        if (!$service_id) {
            wp_send_json_error('ID de servicio no válido');
        }
        
        global $wpdb;
        
        // Obtener profesionales activos que ofrecen el servicio seleccionado y tienen horarios configurados
        $professionals = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT p.id, u.display_name as name, p.specialty
             FROM {$wpdb->prefix}mas_professionals p
             JOIN {$wpdb->users} u ON p.user_id = u.ID
             JOIN {$wpdb->prefix}mas_professional_services pserv ON p.id = pserv.professional_id AND pserv.service_id = %d
             JOIN {$wpdb->prefix}mas_professional_schedules ps ON p.id = ps.professional_id AND ps.is_enabled = 1
             WHERE p.status = 'active'
             ORDER BY u.display_name ASC",
            $service_id
        ));
        
        wp_send_json_success($professionals);
    }
    
    /**
     * AJAX: Obtener slots disponibles de un profesional para una fecha
     */
    public function ajax_get_professional_available_slots() {
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        
        if (!wp_verify_nonce($nonce, 'mas_public_nonce')) {
            wp_send_json_error('Verificación de seguridad fallida');
        }
        
        $date = sanitize_text_field($_POST['date']);
        $professional_id = intval($_POST['professional_id']);
        
        if (!$date || !$professional_id) {
            wp_send_json_error('Datos incompletos');
        }
        
        // Obtener el día de la semana (1=Lunes, 7=Domingo)
        $day_of_week = date('N', strtotime($date));
        
        // Obtener horario del profesional para este día
        $schedule = MAS_Professional_Schedules::get_day_schedule($professional_id, $day_of_week);
        
        if (!$schedule || !$schedule->is_enabled) {
            wp_send_json_success(array()); // No trabaja este día
        }
        
        // Generar slots basados en el horario del profesional
        $settings = get_option('mas_settings', array());
        $slot_duration = isset($settings['slot_duration']) ? intval($settings['slot_duration']) : 30;
        
        $slots = array();
        $start = strtotime($schedule->start_time);
        $end = strtotime($schedule->end_time);
        
        // Obtener citas existentes para este profesional en esta fecha
        global $wpdb;
        $existing_appointments = $wpdb->get_col($wpdb->prepare(
            "SELECT appointment_time FROM {$wpdb->prefix}mas_appointments 
             WHERE professional_id = %d AND appointment_date = %s AND status NOT IN ('cancelled', 'rejected')",
            $professional_id,
            $date
        ));
        
        while ($start < $end) {
            $time = date('H:i:s', $start);
            $time_short = date('H:i', $start);
            
            // Verificar si el slot no está ocupado
            if (!in_array($time, $existing_appointments)) {
                $slots[] = array(
                    'time' => $time,
                    'formatted' => $time_short
                );
            }
            
            $start += $slot_duration * 60;
        }
        
        wp_send_json_success($slots);
    }

    // Shortcodes
    public function appointment_form_shortcode($atts) {
        ob_start();
        include MAS_PLUGIN_DIR . 'public/appointment-form.php';
        return ob_get_clean();
    }
    
    public function box_rental_shortcode($atts) {
        ob_start();
        include MAS_PLUGIN_DIR . 'public/box-rental-form.php';
        return ob_get_clean();
    }
    
    // ADDING shortcode function to display active professionals grid
    public function professionals_grid_shortcode($atts) {
        ob_start();
        include MAS_PLUGIN_DIR . 'public/professionals-grid.php';
        return ob_get_clean();
    }
    
    // Export handlers
    public function handle_export_appointments() {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }
        
        $filters = array(
            'start_date' => isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '',
            'end_date' => isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '',
            'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : ''
        );
        
        MAS_Export::export_appointments_csv($filters);
    }
    
    public function handle_export_rentals() {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }
        
        $filters = array(
            'start_date' => isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '',
            'end_date' => isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : ''
        );
        
        MAS_Export::export_rentals_csv($filters);
    }
    
    public function handle_backup_database() {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }
        MAS_Export::backup_database();
    }
    
    /**
     * Handle appointments export from reports page
     */
    public function handle_export_appointments_excel() {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }
        
        $filters = array(
            'start_date' => isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '',
            'end_date' => isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : ''
        );
        
        // Assuming MAS_Export class exists and has this method
        if (class_exists('MAS_Export') && method_exists('MAS_Export', 'export_appointments_csv')) {
             MAS_Export::export_appointments_csv($filters);
        } else {
            error_log('[MAS Export] MAS_Export class or export_appointments_csv method not found.');
            wp_die('Error de exportación: Clase o método no encontrado.');
        }
    }
    
    /**
     * Handle rentals export from reports page
     */
    public function handle_export_rentals_excel() {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }
        
        $filters = array(
            'start_date' => isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '',
            'end_date' => isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : ''
        );
        
        // Assuming MAS_Export class exists and has this method
        if (class_exists('MAS_Export') && method_exists('MAS_Export', 'export_rentals_csv')) {
            MAS_Export::export_rentals_csv($filters);
        } else {
            error_log('[MAS Export] MAS_Export class or export_rentals_csv method not found.');
            wp_die('Error de exportación: Clase o método no encontrado.');
        }
    }
    
    /**
     * Handle full report export
     */
    public function handle_export_full_report() {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }
        
        $filters = array(
            'start_date' => isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '',
            'end_date' => isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : ''
        );
        
        // Assuming MAS_Export class exists and has this method
        if (class_exists('MAS_Export') && method_exists('MAS_Export', 'export_full_report')) {
            MAS_Export::export_full_report($filters);
        } else {
            error_log('[MAS Export] MAS_Export class or export_full_report method not found.');
            wp_die('Error de exportación: Clase o método no encontrado.');
        }
    }

    /**
     * Cleanup abandoned payments older than 15 minutes
     * This runs automatically every 15 minutes via WP-Cron
     */
    public function cleanup_abandoned_payments() {
        global $wpdb;
        
        // Define cutoff time for abandoned payments (e.g., 15 minutes ago)
        $cutoff_time = date('Y-m-d H:i:s', strtotime('-15 minutes'));
        
        error_log('[MAS Cleanup] ========== LIMPIEZA AUTOMÁTICA INICIADA ==========');
        error_log('[MAS Cleanup] Hora actual: ' . date('Y-m-d H:i:s'));
        error_log('[MAS Cleanup] Eliminando registros anteriores a: ' . $cutoff_time);
        
        // Count abandoned appointments before deletion
        $appointments_count_before = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}mas_appointments 
            WHERE payment_status = 'pending' 
            AND mp_payment_id IS NULL -- Not processed by MP yet
            AND created_at < %s",
            $cutoff_time
        ));
        
        // Count abandoned rentals before deletion
        $rentals_count_before = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}mas_box_rentals 
            WHERE payment_status = 'pending' 
            AND (mp_payment_id IS NULL OR mp_payment_id = '') -- Not processed by MP yet
            AND created_at < %s",
            $cutoff_time
        ));
        
        error_log('[MAS Cleanup] Citas abandonadas encontradas para limpieza: ' . $appointments_count_before);
        error_log('[MAS Cleanup] Arriendos abandonados encontrados para limpieza: ' . $rentals_count_before);
        
        // Delete abandoned appointments
        $appointments_deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}mas_appointments 
            WHERE payment_status = 'pending' 
            AND mp_payment_id IS NULL
            AND created_at < %s",
            $cutoff_time
        ));
        
        // Delete abandoned rentals
        $rentals_deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}mas_box_rentals 
            WHERE payment_status = 'pending' 
            AND (mp_payment_id IS NULL OR mp_payment_id = '')
            AND created_at < %s",
            $cutoff_time
        ));
        
        error_log(sprintf(
            '[MAS Cleanup] RESULTADO DE LIMPIEZA: %d citas eliminadas, %d arriendos eliminados',
            $appointments_deleted === false ? 0 : $appointments_deleted, // query() returns false on error
            $rentals_deleted === false ? 0 : $rentals_deleted
        ));
        error_log('[MAS Cleanup] ========== LIMPIEZA AUTOMÁTICA FINALIZADA ==========');
        
        // Return values for feedback (e.g., on maintenance page)
        return array(
            'appointments_deleted' => $appointments_deleted === false ? 0 : $appointments_deleted,
            'rentals_deleted' => $rentals_deleted === false ? 0 : $rentals_deleted
        );
    }

    /**
     * Add custom cron schedule interval for 15 minutes
     */
    public function add_cron_schedules($schedules) {
        if (!isset($schedules['mas_every_15_minutes'])) {
            $schedules['mas_every_15_minutes'] = array(
                'interval' => 15 * 60, // 15 minutes in seconds
                'display'  => __('Cada 15 minutos', 'medical-appointments')
            );
        }
        return $schedules;
    }
}

/**
 * Validar RUT chileno
 */
function validate_chilean_rut($rut) {
    // Remover puntos y guión
    $rut = preg_replace('/[^k0-9]/i', '', $rut);
    $rut = strtoupper($rut);
    
    if (strlen($rut) < 2) {
        return false;
    }
    
    // Separar cuerpo y dígito verificador
    $body = substr($rut, 0, -1);
    $dv = substr($rut, -1);
    
    // Calcular dígito verificador esperado
    $sum = 0;
    $multiplier = 2;
    
    for ($i = strlen($body) - 1; $i >= 0; $i--) {
        $sum += intval($body[$i]) * $multiplier;
        $multiplier = $multiplier < 7 ? $multiplier + 1 : 2;
    }
    
    $expected_dv = 11 - ($sum % 11);
    $expected_dv = $expected_dv == 11 ? '0' : ($expected_dv == 10 ? 'K' : strval($expected_dv));
    
    return $dv === $expected_dv;
}

// Inicializar el plugin
function mas_init() {
    return Medical_Appointments_System::get_instance();
}

add_action('plugins_loaded', 'mas_init');
?>
