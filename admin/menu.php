<?php
/**
 * Admin Menu Configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAS_Admin_Menu {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            'Sistema Médico',
            'Sistema Médico',
            'manage_options',
            'medical-appointments',
            array($this, 'dashboard_page'),
            'dashicons-calendar-alt',
            30
        );
        
        // Dashboard
        add_submenu_page(
            'medical-appointments',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'medical-appointments',
            array($this, 'dashboard_page')
        );
        
        // Appointments
        add_submenu_page(
            'medical-appointments',
            'Citas Médicas',
            'Citas Médicas',
            'manage_options',
            'medical-appointments-list',
            array($this, 'appointments_page')
        );
        
        // Appointments Calendar View
        add_submenu_page(
            'medical-appointments',
            'Calendario de Citas',
            'Calendario de Citas',
            'manage_options',
            'medical-appointments-calendar',
            array($this, 'appointments_calendar_page')
        );
        
        // Boxes
        add_submenu_page(
            'medical-appointments',
            'Boxes de Atención',
            'Boxes',
            'manage_options',
            'medical-boxes',
            array($this, 'boxes_page')
        );
        
        // Rentals
        add_submenu_page(
            'medical-appointments',
            'Arriendos',
            'Arriendos',
            'manage_options',
            'medical-rentals',
            array($this, 'rentals_page')
        );
        
        // Rentals Calendar View
        add_submenu_page(
            'medical-appointments',
            'Calendario de Arriendos',
            'Calendario de Arriendos',
            'manage_options',
            'medical-rentals-calendar',
            array($this, 'rentals_calendar_page')
        );
        
        // Monthly Rentals
        add_submenu_page(
            'medical-appointments',
            'Arriendos Mensuales',
            'Arriendos Mensuales',
            'manage_options',
            'mas-monthly-rentals',
            array($this, 'monthly_rentals_page')
        );
        
        // Promociones submenu after Rentals Calendar
        add_submenu_page(
            'medical-appointments',
            'Promociones',
            'Promociones',
            'manage_options',
            'medical-promotions',
            array($this, 'promotions_page')
        );
        
        // Professionals
        add_submenu_page(
            'medical-appointments',
            'Profesionales',
            'Profesionales',
            'manage_options',
            'medical-professionals',
            array($this, 'professionals_page')
        );
        
        // Servicios submenu after Professionals
        add_submenu_page(
            'medical-appointments',
            'Servicios',
            'Servicios',
            'manage_options',
            'medical-services',
            array($this, 'services_page')
        );
        
        // Reports
        add_submenu_page(
            'medical-appointments',
            'Reportes',
            'Reportes',
            'manage_options',
            'medical-reports',
            array($this, 'reports_page')
        );
        
        // Settings
        add_submenu_page(
            'medical-appointments',
            'Configuración',
            'Configuración',
            'manage_options',
            'medical-settings',
            array($this, 'settings_page')
        );
        
        // Maintenance submenu
        add_submenu_page(
            'medical-appointments',
            'Mantenimiento',
            'Mantenimiento',
            'manage_options',
            'mas-maintenance',
            array($this, 'maintenance_page')
        );
        
        // Specialties
        add_submenu_page(
            'medical-appointments',
            'Especialidades/Servicios',
            'Especialidades',
            'manage_options',
            'medical-specialties',
            array($this, 'specialties_page')
        );
        
        // Professional menu (for professionals/collaborators)
        add_menu_page(
            'Mi Panel',
            'Mi Panel',
            'edit_posts',
            'mas-professional-dashboard',
            array($this, 'professional_dashboard_page'),
            'dashicons-businessman',
            31
        );
        
        // Professional Dashboard
        add_submenu_page(
            'mas-professional-dashboard',
            'Dashboard',
            'Dashboard',
            'edit_posts',
            'mas-professional-dashboard',
            array($this, 'professional_dashboard_page')
        );
        
        // My Appointments
        add_submenu_page(
            'mas-professional-dashboard',
            'Mis Citas',
            'Mis Citas',
            'edit_posts',
            'mas-my-appointments',
            array($this, 'my_appointments_page')
        );
        
        // My Rentals
        add_submenu_page(
            'mas-professional-dashboard',
            'Mis Arriendos',
            'Mis Arriendos',
            'edit_posts',
            'mas-my-rentals',
            array($this, 'my_rentals_page')
        );
        
        // Rent Box
        add_submenu_page(
            'mas-professional-dashboard',
            'Arrendar Box',
            'Arrendar Box',
            'edit_posts',
            'mas-rent-box',
            array($this, 'rent_box_page')
        );
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'medical-') === false && strpos($hook, 'mas-') === false && $hook !== 'toplevel_page_medical-appointments') {
            return;
        }
        
        wp_enqueue_style('mas-admin-css', MAS_PLUGIN_URL . 'assets/css/admin.css', array(), MAS_VERSION);
        wp_enqueue_script('mas-admin-js', MAS_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), MAS_VERSION, true);
        
        wp_localize_script('mas-admin-js', 'masAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mas_admin_nonce'),
            'strings' => array(
                'confirm_delete' => '¿Está seguro de eliminar este elemento?',
                'error' => 'Ha ocurrido un error. Por favor intente nuevamente.',
                'success' => 'Operación completada exitosamente.',
            )
        ));
    }
    
    public function dashboard_page() {
        require_once MAS_PLUGIN_DIR . 'admin/dashboard.php';
    }
    
    public function appointments_page() {
        require_once MAS_PLUGIN_DIR . 'admin/appointments.php';
    }
    
    public function appointments_calendar_page() {
        require_once MAS_PLUGIN_DIR . 'admin/appointments-calendar.php';
    }
    
    public function boxes_page() {
        require_once MAS_PLUGIN_DIR . 'admin/boxes.php';
    }
    
    public function rentals_page() {
        require_once MAS_PLUGIN_DIR . 'admin/rentals.php';
    }
    
    public function rentals_calendar_page() {
        require_once MAS_PLUGIN_DIR . 'admin/rentals-calendar.php';
    }
    
    public function professionals_page() {
        require_once MAS_PLUGIN_DIR . 'admin/professionals.php';
    }
    
    public function reports_page() {
        require_once MAS_PLUGIN_DIR . 'admin/reports.php';
    }
    
    public function settings_page() {
        require_once MAS_PLUGIN_DIR . 'admin/settings.php';
    }
    
    public function specialties_page() {
        require_once MAS_PLUGIN_DIR . 'admin/specialties.php';
    }
    
    // Promotions page method
    public function promotions_page() {
        require_once MAS_PLUGIN_DIR . 'admin/promotions.php';
    }
    
    // Services page method
    public function services_page() {
        require_once MAS_PLUGIN_DIR . 'admin/services.php';
    }
    
    // Maintenance page method
    public function maintenance_page() {
        require_once MAS_PLUGIN_DIR . 'admin/maintenance.php';
    }
    
    // Monthly Rentals page method
    public function monthly_rentals_page() {
        require_once MAS_PLUGIN_DIR . 'admin/monthly-rentals.php';
    }
    
    public function professional_dashboard_page() {
        require_once MAS_PLUGIN_DIR . 'admin/professional-dashboard.php';
    }
    
    public function my_appointments_page() {
        require_once MAS_PLUGIN_DIR . 'admin/my-appointments.php';
    }
    
    public function my_rentals_page() {
        require_once MAS_PLUGIN_DIR . 'admin/my-rentals.php';
    }
    
    public function rent_box_page() {
        require_once MAS_PLUGIN_DIR . 'admin/rent-box.php';
    }
}

new MAS_Admin_Menu();
