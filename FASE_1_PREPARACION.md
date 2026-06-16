# 🚀 PLAN DE IMPLEMENTACIÓN - FASE 1: PREPARACIÓN
## Reescritura Completa con Arquitectura Moderna y Seguridad

**Duración:** 1-2 semanas  
**Objetivo:** Estructura base + Bootstrap del plugin sin tocar lógica existente  
**Resultado:** Plugin funcionando igual, pero con estructura escalable

---

## 📋 CHECKLIST FASE 1

- [ ] 1.1 Crear estructura de carpetas PSR-4
- [ ] 1.2 Configurar composer.json
- [ ] 1.3 Crear bootstrap principal (Plugin.php)
- [ ] 1.4 Crear Service Container (DI)
- [ ] 1.5 Crear configuración centralizada (Config.php)
- [ ] 1.6 Crear Logger strutcurado
- [ ] 1.7 Crear Validator centralizado
- [ ] 1.8 Crear Exceptions personalizadas
- [ ] 1.9 Crear Helpers de seguridad
- [ ] 1.10 Actualizar plugin.php para usar nuevas clases
- [ ] 1.11 Hacer backup de código original
- [ ] 1.12 Testing: Verificar que funcione como antes

---

## 📁 PASO 1.1: Estructura de Carpetas

### Crear estructura completa
```bash
# Desde la raíz del plugin
mkdir -p src/{Core,API,Services,Repositories,Models,Exceptions,Utils,Email,Migrations,Traits}
mkdir -p tests/{Unit,Integration,Fixtures}
mkdir -p templates/emails
mkdir -p docs

touch src/Core/Plugin.php
touch src/Core/ServiceContainer.php
touch src/Core/Config.php
touch src/Utils/Logger.php
touch src/Utils/Validator.php
touch src/Utils/Sanitizer.php
touch src/Exceptions/MASException.php
touch src/Traits/Singleton.php
touch composer.json
touch .env.example
touch tests/bootstrap.php
```

---

## 📦 PASO 1.2: composer.json

```json
{
    "name": "yerco-abarca/medical-appointments-system",
    "description": "Sistema completo de citas médicas y arriendo de boxes para WordPress",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "authors": [
        {
            "name": "Yerco Abraham Abarca Cortes",
            "email": "yerco.abarca@gmail.com"
        }
    ],
    "require": {
        "php": ">=7.4"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.0",
        "mockery/mockery": "^1.5",
        "squizlabs/php_codesniffer": "^3.7"
    },
    "autoload": {
        "psr-4": {
            "MAS\\": "src/"
        },
        "files": [
            "src/helpers.php"
        ]
    },
    "autoload-dev": {
        "psr-4": {
            "MAS\\Tests\\": "tests/"
        }
    },
    "scripts": {
        "test": "phpunit",
        "lint": "phpcs --standard=PSR2 src/",
        "fix": "phpcbf --standard=PSR2 src/"
    }
}
```

---

## 🎯 PASO 1.3: src/Core/Plugin.php

```php
<?php
/**
 * Plugin Bootstrap Class
 * 
 * @package MAS
 * @since 4.0.0
 */

namespace MAS\Core;

use MAS\Utils\Logger;
use MAS\Exceptions\MASException;

/**
 * Clase principal del plugin
 */
class Plugin {
    
    use \MAS\Traits\Singleton;
    
    /**
     * Container de servicios
     */
    private ServiceContainer $container;
    
    /**
     * Logger
     */
    private Logger $logger;
    
    /**
     * Versión del plugin
     */
    const VERSION = '4.0.0';
    
    /**
     * Slug del plugin
     */
    const SLUG = 'medical-appointments-system';
    
    /**
     * Constructor privado (Singleton)
     */
    private function __construct() {
        $this->initialize();
    }
    
    /**
     * Inicialización del plugin
     */
    private function initialize(): void {
        try {
            // Cargar configuración
            Config::load();
            
            // Inicializar container
            $this->container = new ServiceContainer();
            $this->logger = $this->container->get('logger');
            
            // Registrar hooks
            $this->registerHooks();
            
            $this->logger->info('Plugin inicializado correctamente', [
                'version' => self::VERSION,
                'php' => PHP_VERSION
            ]);
            
        } catch (\Exception $e) {
            $this->handleBootstrapError($e);
        }
    }
    
    /**
     * Registrar hooks principales
     */
    private function registerHooks(): void {
        // Activation/Deactivation
        register_activation_hook(MAS_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(MAS_PLUGIN_FILE, [$this, 'deactivate']);
        
        // Init hooks
        add_action('plugins_loaded', [$this, 'onPluginsLoaded'], 10);
        add_action('init', [$this, 'onInit'], 10);
        add_action('admin_init', [$this, 'onAdminInit'], 10);
        
        // AJAX hooks (migrations desde admin-ajax.php)
        add_action('wp_ajax_mas_*', [$this, 'handleAjax'], 10, 0);
        add_action('wp_ajax_nopriv_mas_*', [$this, 'handleAjax'], 10, 0);
    }
    
    /**
     * Hook: plugins_loaded
     */
    public function onPluginsLoaded(): void {
        // Cargar traducciones
        load_plugin_textdomain(
            'medical-appointments-system',
            false,
            dirname(MAS_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Hook: init
     */
    public function onInit(): void {
        // Registrar CPTs, taxonomías, shortcodes
        // Enqueue public assets
        if (!is_admin()) {
            $this->container->get('asset_manager')->enqueueFrontend();
        }
    }
    
    /**
     * Hook: admin_init
     */
    public function onAdminInit(): void {
        // Registrar admin assets
        if (is_admin()) {
            $this->container->get('asset_manager')->enqueueAdmin();
        }
    }
    
    /**
     * Manejar AJAX calls
     */
    public function handleAjax(): void {
        $action = sanitize_text_field($_POST['action'] ?? '');
        
        // Remover prefijo 'mas_'
        $handler = str_replace('mas_', '', $action);
        
        try {
            // Verificar nonce
            $this->container->get('security')->verifyNonce();
            
            // Delegar a handler
            $controller = $this->container->get('ajax_controller');
            $controller->handle($handler);
            
        } catch (MASException $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('AJAX Error', [
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            wp_send_json_error(['message' => 'Error procesando solicitud']);
        }
        wp_die();
    }
    
    /**
     * Activación del plugin
     */
    public function activate(): void {
        try {
            // Crear tablas si no existen
            $this->container->get('db_manager')->createTables();
            
            // Agregar capacidades de roles
            $this->container->get('role_manager')->registerRoles();
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            $this->logger->info('Plugin activado');
            
        } catch (\Exception $e) {
            $this->logger->error('Error en activación', ['error' => $e->getMessage()]);
            wp_die('Error activando plugin: ' . $e->getMessage());
        }
    }
    
    /**
     * Desactivación del plugin
     */
    public function deactivate(): void {
        try {
            flush_rewrite_rules();
            $this->logger->info('Plugin desactivado');
        } catch (\Exception $e) {
            $this->logger->error('Error en desactivación', ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Manejar errores de bootstrap
     */
    private function handleBootstrapError(\Exception $e): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            wp_die('Error en MAS: ' . $e->getMessage());
        }
        // En producción, solo loguear silenciosamente
        error_log('[MAS ERROR] ' . $e->getMessage());
    }
    
    /**
     * Obtener container de servicios
     */
    public function getContainer(): ServiceContainer {
        return $this->container;
    }
    
    /**
     * Obtener logger
     */
    public function getLogger(): Logger {
        return $this->logger;
    }
}
```

---

## 🏗️ PASO 1.4: src/Core/ServiceContainer.php

```php
<?php
/**
 * Dependency Injection Container
 * 
 * @package MAS\Core
 */

namespace MAS\Core;

use MAS\Utils\Logger;
use MAS\Utils\Validator;
use MAS\Utils\Sanitizer;

/**
 * Container para inyección de dependencias
 */
class ServiceContainer {
    
    /**
     * Servicios registrados
     */
    private array $services = [];
    
    /**
     * Instancias singleton
     */
    private array $singletons = [];
    
    /**
     * Constructor - Registrar servicios
     */
    public function __construct() {
        $this->registerCoreServices();
    }
    
    /**
     * Registrar servicios del core
     */
    private function registerCoreServices(): void {
        // Logger
        $this->singleton('logger', function () {
            return new Logger();
        });
        
        // Validator
        $this->singleton('validator', function () {
            return new Validator();
        });
        
        // Sanitizer
        $this->singleton('sanitizer', function () {
            return new Sanitizer();
        });
        
        // Config
        $this->singleton('config', function () {
            return Config::getInstance();
        });
        
        // Security
        $this->singleton('security', function () {
            return new \MAS\Utils\Security();
        });
        
        // Database Manager
        $this->singleton('db_manager', function () {
            return new \MAS\Database\DatabaseManager();
        });
        
        // Asset Manager
        $this->singleton('asset_manager', function () {
            return new \MAS\Utils\AssetManager();
        });
    }
    
    /**
     * Registrar servicio singleton
     */
    public function singleton(string $name, callable $resolver): void {
        $this->services[$name] = $resolver;
    }
    
    /**
     * Obtener servicio
     */
    public function get(string $name) {
        if (!isset($this->services[$name])) {
            throw new \Exception("Servicio '{$name}' no registrado");
        }
        
        // Si es singleton y ya existe, retornar instancia
        if (isset($this->singletons[$name])) {
            return $this->singletons[$name];
        }
        
        // Crear instancia
        $instance = call_user_func($this->services[$name]);
        
        // Guardar singleton
        $this->singletons[$name] = $instance;
        
        return $instance;
    }
    
    /**
     * Verificar si servicio existe
     */
    public function has(string $name): bool {
        return isset($this->services[$name]);
    }
}
```

---

## ⚙️ PASO 1.5: src/Core/Config.php

```php
<?php
/**
 * Configuración centralizada
 * 
 * @package MAS\Core
 */

namespace MAS\Core;

use MAS\Traits\Singleton;

/**
 * Gestión de configuración
 */
class Config {
    
    use Singleton;
    
    /**
     * Valores de configuración
     */
    private array $config = [];
    
    /**
     * Cargar configuración
     */
    public static function load(): void {
        $instance = self::getInstance();
        $instance->loadDefaults();
        $instance->loadFromEnv();
        $instance->loadFromOptions();
    }
    
    /**
     * Cargar valores por defecto
     */
    private function loadDefaults(): void {
        $this->config = [
            // General
            'plugin_version' => '4.0.0',
            'plugin_slug' => 'medical-appointments-system',
            'text_domain' => 'medical-appointments-system',
            
            // Database
            'db_prefix' => 'mas_',
            
            // Appointments
            'appointment_duration' => 60, // minutos
            'min_booking_notice' => 1, // horas antes
            'appointment_color' => '#3498db',
            
            // Rentals
            'rental_slot_duration' => 60, // minutos
            'rental_color' => '#2ecc71',
            
            // Pagos
            'payment_provider' => 'mercadopago',
            'payment_timeout' => 30, // segundos
            
            // Email
            'from_email' => get_option('admin_email'),
            'from_name' => get_bloginfo('name'),
            'email_enabled' => true,
            
            // Security
            'enable_rut_validation' => true,
            'require_email_verification' => false,
            'session_timeout' => 3600,
            
            // Cache
            'cache_enabled' => true,
            'cache_ttl' => 3600,
            
            // Logging
            'log_level' => 'info', // debug, info, warn, error
            'log_to_file' => true,
            'log_to_db' => false,
        ];
    }
    
    /**
     * Cargar desde archivo .env
     */
    private function loadFromEnv(): void {
        $env_file = dirname(MAS_PLUGIN_DIR) . '/.env';
        
        if (!file_exists($env_file)) {
            return;
        }
        
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (strpos($line, '=') === false || strpos($line, '#') === 0) {
                continue;
            }
            
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            $this->config[strtolower($key)] = $value;
        }
    }
    
    /**
     * Cargar desde WordPress options
     */
    private function loadFromOptions(): void {
        $options = get_option('mas_settings', []);
        
        foreach ($options as $key => $value) {
            $this->config[$key] = $value;
        }
    }
    
    /**
     * Obtener valor de configuración
     */
    public function get(string $key, $default = null) {
        return $this->config[$key] ?? $default;
    }
    
    /**
     * Establecer valor de configuración
     */
    public function set(string $key, $value): void {
        $this->config[$key] = $value;
    }
    
    /**
     * Obtener todas las configuraciones
     */
    public function getAll(): array {
        return $this->config;
    }
}
```

---

## 📝 PASO 1.6: src/Utils/Logger.php

```php
<?php
/**
 * Logger estructurado
 * 
 * @package MAS\Utils
 */

namespace MAS\Utils;

/**
 * Sistema de logging con niveles
 */
class Logger {
    
    /**
     * Niveles de logging
     */
    const LEVELS = [
        'debug' => 0,
        'info' => 1,
        'warn' => 2,
        'error' => 3,
        'critical' => 4,
    ];
    
    /**
     * Nivel mínimo a loguear
     */
    private string $minLevel = 'info';
    
    /**
     * File handler
     */
    private ?resource $fileHandle = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->minLevel = \MAS\Core\Config::getInstance()->get('log_level', 'info');
        
        if (\MAS\Core\Config::getInstance()->get('log_to_file')) {
            $this->initFileHandler();
        }
    }
    
    /**
     * Inicializar file handler
     */
    private function initFileHandler(): void {
        $logDir = WP_CONTENT_DIR . '/logs/mas';
        
        if (!is_dir($logDir)) {
            wp_mkdir_p($logDir);
        }
        
        $logFile = $logDir . '/plugin-' . date('Y-m-d') . '.log';
        $this->fileHandle = @fopen($logFile, 'a');
    }
    
    /**
     * Log genérico
     */
    private function log(string $level, string $message, array $context = []): void {
        // Verificar nivel mínimo
        if (self::LEVELS[$level] < self::LEVELS[$this->minLevel]) {
            return;
        }
        
        $formatted = $this->formatMessage($level, $message, $context);
        
        // Loguear a archivo
        if ($this->fileHandle) {
            fwrite($this->fileHandle, $formatted . PHP_EOL);
        }
        
        // Loguear a WordPress error_log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($formatted);
        }
        
        // Loguear a database (opcional)
        if (\MAS\Core\Config::getInstance()->get('log_to_db')) {
            $this->logToDatabase($level, $message, $context);
        }
    }
    
    /**
     * Formatear mensaje
     */
    private function formatMessage(string $level, string $message, array $context = []): string {
        $timestamp = date('Y-m-d H:i:s');
        $level = strtoupper($level);
        $context_str = !empty($context) ? ' | ' . json_encode($context) : '';
        
        return "[$timestamp] [$level] $message$context_str";
    }
    
    /**
     * Debug level
     */
    public function debug(string $message, array $context = []): void {
        $this->log('debug', $message, $context);
    }
    
    /**
     * Info level
     */
    public function info(string $message, array $context = []): void {
        $this->log('info', $message, $context);
    }
    
    /**
     * Warn level
     */
    public function warn(string $message, array $context = []): void {
        $this->log('warn', $message, $context);
    }
    
    /**
     * Error level
     */
    public function error(string $message, array $context = []): void {
        $this->log('error', $message, $context);
    }
    
    /**
     * Critical level
     */
    public function critical(string $message, array $context = []): void {
        $this->log('critical', $message, $context);
    }
    
    /**
     * Loguear a base de datos
     */
    private function logToDatabase(string $level, string $message, array $context): void {
        global $wpdb;
        
        $wpdb->insert($wpdb->prefix . 'mas_logs', [
            'level' => $level,
            'message' => $message,
            'context' => json_encode($context),
            'timestamp' => current_time('mysql'),
        ], ['%s', '%s', '%s', '%s']);
    }
    
    /**
     * Destructor - cerrar archivo
     */
    public function __destruct() {
        if ($this->fileHandle) {
            fclose($this->fileHandle);
        }
    }
}
```

---

## 🔐 PASO 1.7: src/Utils/Security.php

```php
<?php
/**
 * Utilidades de seguridad
 * 
 * @package MAS\Utils
 */

namespace MAS\Utils;

use MAS\Exceptions\MASException;

/**
 * Gestión de seguridad
 */
class Security {
    
    /**
     * Verificar nonce
     */
    public function verifyNonce(
        string $nonceParam = 'nonce',
        string $action = 'mas_action',
        string $post = '_POST'
    ): void {
        $nonce = $this->getNonce($nonceParam, $post);
        
        if (empty($nonce)) {
            throw new MASException('Nonce inválido', 403);
        }
        
        if (!wp_verify_nonce($nonce, $action)) {
            throw new MASException('Verificación de seguridad falló', 403);
        }
    }
    
    /**
     * Obtener nonce
     */
    private function getNonce(string $param, string $post): ?string {
        if ($post === '_POST') {
            return sanitize_text_field($_POST[$param] ?? '');
        } elseif ($post === '_GET') {
            return sanitize_text_field($_GET[$param] ?? '');
        }
        return null;
    }
    
    /**
     * Generar nonce
     */
    public function generateNonce(string $action = 'mas_action'): string {
        return wp_create_nonce($action);
    }
    
    /**
     * Verificar capacidad (capability)
     */
    public function checkCapability(string $capability): void {
        if (!current_user_can($capability)) {
            throw new MASException("No tienes permiso: $capability", 403);
        }
    }
    
    /**
     * Verificar que es AJAX
     */
    public function checkAjax(): void {
        if (!wp_doing_ajax()) {
            throw new MASException('Esta acción requiere AJAX', 400);
        }
    }
    
    /**
     * Validar RUT chileno
     */
    public function validateRUT(string $rut): bool {
        $rut = preg_replace('/[^0-9k]/', '', strtolower($rut));
        
        if (strlen($rut) < 8) {
            return false;
        }
        
        $num = substr($rut, 0, -1);
        $dv = substr($rut, -1);
        
        $sum = 0;
        $multiplier = 2;
        
        for ($i = strlen($num) - 1; $i >= 0; $i--) {
            $sum += intval($num[$i]) * $multiplier;
            $multiplier = $multiplier === 7 ? 2 : $multiplier + 1;
        }
        
        $calculated_dv = 11 - ($sum % 11);
        
        if ($calculated_dv === 11) {
            $calculated_dv = 0;
        } elseif ($calculated_dv === 10) {
            $calculated_dv = 'k';
        } else {
            $calculated_dv = strval($calculated_dv);
        }
        
        return $dv === strval($calculated_dv);
    }
    
    /**
     * Validar email
     */
    public function validateEmail(string $email): bool {
        return is_email($email) !== false;
    }
    
    /**
     * Validar URL
     */
    public function validateUrl(string $url): bool {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Sanitizar HTML pero permitir tags seguros
     */
    public function sanitizeHtml(string $html): string {
        $allowed_tags = [
            'a' => ['href' => true, 'title' => true],
            'br' => [],
            'em' => [],
            'strong' => [],
            'p' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'h1' => [],
            'h2' => [],
            'h3' => [],
        ];
        
        return wp_kses($html, $allowed_tags);
    }
}
```

---

## ✔️ PASO 1.8: src/Utils/Validator.php

```php
<?php
/**
 * Validador centralizado
 * 
 * @package MAS\Utils
 */

namespace MAS\Utils;

use MAS\Exceptions\ValidationException;

/**
 * Validación de datos
 */
class Validator {
    
    /**
     * Errores de validación
     */
    private array $errors = [];
    
    /**
     * Validar requeridos
     */
    public function required(string $field, $value): self {
        if (empty($value)) {
            $this->addError($field, "$field es requerido");
        }
        return $this;
    }
    
    /**
     * Validar email
     */
    public function email(string $field, string $value): self {
        if (!is_email($value)) {
            $this->addError($field, "$field debe ser un email válido");
        }
        return $this;
    }
    
    /**
     * Validar número
     */
    public function numeric(string $field, $value): self {
        if (!is_numeric($value)) {
            $this->addError($field, "$field debe ser un número");
        }
        return $this;
    }
    
    /**
     * Validar teléfono chileno
     */
    public function chileanPhone(string $field, string $value): self {
        $phone = preg_replace('/[^0-9]/', '', $value);
        
        if (strlen($phone) < 9) {
            $this->addError($field, "$field debe tener al menos 9 dígitos");
        }
        
        return $this;
    }
    
    /**
     * Validar fecha
     */
    public function date(string $field, string $value, string $format = 'Y-m-d'): self {
        $date = \DateTime::createFromFormat($format, $value);
        
        if (!$date || $date->format($format) !== $value) {
            $this->addError($field, "$field debe estar en formato $format");
        }
        
        return $this;
    }
    
    /**
     * Validar fecha futura
     */
    public function futureDate(string $field, string $value): self {
        $this->date($field, $value);
        
        if (strtotime($value) <= time()) {
            $this->addError($field, "$field debe ser una fecha futura");
        }
        
        return $this;
    }
    
    /**
     * Validar rango
     */
    public function between(string $field, $value, int $min, int $max): self {
        if ($value < $min || $value > $max) {
            $this->addError($field, "$field debe estar entre $min y $max");
        }
        return $this;
    }
    
    /**
     * Validar longitud mínima
     */
    public function minLength(string $field, string $value, int $min): self {
        if (strlen($value) < $min) {
            $this->addError($field, "$field debe tener mínimo $min caracteres");
        }
        return $this;
    }
    
    /**
     * Validar longitud máxima
     */
    public function maxLength(string $field, string $value, int $max): self {
        if (strlen($value) > $max) {
            $this->addError($field, "$field debe tener máximo $max caracteres");
        }
        return $this;
    }
    
    /**
     * Validar que exista en base de datos
     */
    public function exists(string $field, $value, string $table, string $column): self {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE $column = %s",
            $value
        ));
        
        if (!$result) {
            $this->addError($field, "$field no existe");
        }
        
        return $this;
    }
    
    /**
     * Validar que sea único
     */
    public function unique(string $field, $value, string $table, string $column, $exclude_id = null): self {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE $column = %s",
            $value
        );
        
        if ($exclude_id) {
            $query .= $wpdb->prepare(" AND id != %d", $exclude_id);
        }
        
        $result = $wpdb->get_var($query);
        
        if ($result) {
            $this->addError($field, "$field ya existe");
        }
        
        return $this;
    }
    
    /**
     * Agregar error
     */
    private function addError(string $field, string $message): void {
        $this->errors[$field][] = $message;
    }
    
    /**
     * Verificar si hay errores
     */
    public function fails(): bool {
        return !empty($this->errors);
    }
    
    /**
     * Obtener errores
     */
    public function getErrors(): array {
        return $this->errors;
    }
    
    /**
     * Lanzar excepción si hay errores
     */
    public function throwIfFails(): void {
        if ($this->fails()) {
            throw new ValidationException('Validación fallida', $this->errors);
        }
    }
}
```

---

## ❌ PASO 1.9: src/Exceptions/MASException.php

```php
<?php
/**
 * Excepciones personalizadas
 * 
 * @package MAS\Exceptions
 */

namespace MAS\Exceptions;

/**
 * Excepción base del plugin
 */
class MASException extends \Exception {
    
    /**
     * Contexto adicional
     */
    protected array $context = [];
    
    /**
     * Constructor
     */
    public function __construct(string $message = "", int $code = 0, array $context = [], \Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }
    
    /**
     * Obtener contexto
     */
    public function getContext(): array {
        return $this->context;
    }
}

/**
 * Excepción de validación
 */
class ValidationException extends MASException {
    
    /**
     * Errores de validación
     */
    private array $errors;
    
    /**
     * Constructor
     */
    public function __construct(string $message = "Validación fallida", array $errors = [], int $code = 422) {
        parent::__construct($message, $code);
        $this->errors = $errors;
    }
    
    /**
     * Obtener errores
     */
    public function getErrors(): array {
        return $this->errors;
    }
}

/**
 * Excepción de pagos
 */
class PaymentException extends MASException {}

/**
 * Excepción de citas
 */
class AppointmentException extends MASException {}

/**
 * Excepción de arriendos
 */
class RentalException extends MASException {}

/**
 * Excepción de base de datos
 */
class DatabaseException extends MASException {}
```

---

## 🔄 PASO 1.10: src/Traits/Singleton.php

```php
<?php
/**
 * Trait Singleton
 * 
 * @package MAS\Traits
 */

namespace MAS\Traits;

/**
 * Patrón Singleton
 */
trait Singleton {
    
    /**
     * Instancia única
     */
    private static ?self $instance = null;
    
    /**
     * Obtener instancia única
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Prevenir clonación
     */
    private function __clone() {}
    
    /**
     * Prevenir unserialize
     */
    public function __wakeup() {
        throw new \Exception("No se puede deserializar Singleton");
    }
}
```

---

## 📄 PASO 1.11: .env.example

```env
# ===================================
# Medical Appointments System Config
# ===================================

# General
PLUGIN_VERSION=4.0.0
PLUGIN_SLUG=medical-appointments-system

# Database
DB_PREFIX=wp_mas_

# Appointments
APPOINTMENT_DURATION=60
MIN_BOOKING_NOTICE=1
APPOINTMENT_COLOR=#3498db

# Rentals
RENTAL_SLOT_DURATION=60
RENTAL_COLOR=#2ecc71

# MercadoPago
MP_ACCESS_TOKEN=YOUR_TOKEN_HERE
MP_PUBLIC_KEY=YOUR_PUBLIC_KEY_HERE
MP_ENVIRONMENT=sandbox

# Email
FROM_EMAIL=info@example.com
FROM_NAME=Medical System
EMAIL_ENABLED=true

# Security
ENABLE_RUT_VALIDATION=true
REQUIRE_EMAIL_VERIFICATION=false
SESSION_TIMEOUT=3600

# Cache
CACHE_ENABLED=true
CACHE_TTL=3600

# Logging
LOG_LEVEL=info
LOG_TO_FILE=true
LOG_TO_DB=false
```

---

## 🎯 PASO 1.12: Actualizar plugin.php Principal

El archivo `medical-appointments-system.php` debe quedar así:

```php
<?php
/**
 * Plugin Name: Sistema de Citas Médicas y Arriendo de Boxes
 * Description: Sistema completo para gestión de citas médicas, arriendo de boxes y gestión de profesionales
 * Version: 4.0.0
 * Author: Yerco Abraham Abarca Cortes
 * License: GPL v2 or later
 * Text Domain: medical-appointments-system
 * Domain Path: /languages
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes
define('MAS_PLUGIN_FILE', __FILE__);
define('MAS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MAS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MAS_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('MAS_VERSION', '4.0.0');

// Cargar autoloader
if (!file_exists(MAS_PLUGIN_DIR . 'vendor/autoload.php')) {
    wp_die('Por favor ejecute: composer install');
}

require_once MAS_PLUGIN_DIR . 'vendor/autoload.php';

// Bootstrap del plugin
try {
    $plugin = \MAS\Core\Plugin::getInstance();
} catch (Exception $e) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        wp_die('Error inicializando plugin MAS: ' . $e->getMessage());
    }
}
```

---

## 📋 CHECKLIST FINAL FASE 1

Antes de pasar a la Fase 2, verifica:

- [ ] Todas las carpetas creadas
- [ ] `composer.json` en raíz
- [ ] Todos los archivos .php creados (12 archivos)
- [ ] `.env.example` creado
- [ ] `plugin.php` actualizado
- [ ] Ejecutar `composer install` (crear autoload)
- [ ] Probar que plugin se activa sin errores
- [ ] Verificar que funciona como antes (AJAX handlers aún en archivo original)
- [ ] Guardar en git con mensaje "Fase 1: Preparación - Estructura base"

---

## ✅ PRÓXIMO PASO

Una vez completada la Fase 1:

1. Crea rama: `git checkout -b feature/phase-2-services`
2. Espera documento de **Fase 2: Servicios**
3. Comenzar a refactorizar lógica de negocio

---

## 🆘 TROUBLESHOOTING

### "Clase no encontrada"
```bash
composer dump-autoload
```

### "archivo no existe"
Verifica ruta exacta en PSR-4 autoload

### Plugin no se activa
Revisa WordPress debug log en `/wp-content/debug.log`

---

**¡Fin Fase 1! Próximo paso: Fase 2 - Servicios**
