<?php

/**
 * Plugin Name: License Server
 * Description: Central license management server for paid WordPress plugins
 * Version: 1.0.0
 * Author: Your Company
 * License: GPL-2.0+
 * Text Domain: license-server
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.0
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LS_PLUGIN_FILE', __FILE__);
define('LS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LS_VERSION', '1.0.0');
define('LS_DB_VERSION', 1);

// Autoloader
spl_autoload_register(function ($class) {
    if (strpos($class, 'LicenseServer\\') === 0) {
        $file = LS_PLUGIN_DIR . 'includes/' . str_replace('\\', '/', substr($class, 14)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Main plugin class
class License_Server
{
    private static $instance = null;

    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    private function define_constants()
    {
        // Security constants
        define('LS_HASH_ALGO', 'sha256');
        define('LS_API_VERSION', 'v1');
        define('LS_MAX_DOMAINS_DEFAULT', 5);
        define('LS_LICENSE_VALIDITY_DAYS', 365);
    }

    private function includes()
    {
        // Core database
        require_once LS_PLUGIN_DIR . 'includes/Database/Schema.php';
        require_once LS_PLUGIN_DIR . 'includes/Database/Installer.php';

        // Security
        require_once LS_PLUGIN_DIR . 'includes/Security/Encryption.php';
        require_once LS_PLUGIN_DIR . 'includes/Security/Validation.php';
        require_once LS_PLUGIN_DIR . 'includes/Security/RateLimiter.php';

        // Models
        require_once LS_PLUGIN_DIR . 'includes/Models/License.php';
        require_once LS_PLUGIN_DIR . 'includes/Models/PluginVersion.php';
        require_once LS_PLUGIN_DIR . 'includes/Models/ActivatedDomain.php';

        // API
        require_once LS_PLUGIN_DIR . 'includes/API/REST_API.php';

        // Admin
        require_once LS_PLUGIN_DIR . 'includes/Admin/Admin_Dashboard.php';
        require_once LS_PLUGIN_DIR . 'includes/Admin/License_Manager.php';
        require_once LS_PLUGIN_DIR . 'includes/Admin/Plugin_Uploader.php';

        // Frontend
        require_once LS_PLUGIN_DIR . 'includes/Frontend/User_Dashboard.php';
        require_once LS_PLUGIN_DIR . 'includes/Frontend/Account_Endpoints.php';

        // Utilities
        require_once LS_PLUGIN_DIR . 'includes/Utilities/Logger.php';
        require_once LS_PLUGIN_DIR . 'includes/Utilities/Email_Handler.php';
    }

    private function init_hooks()
    {
        // Activation/Deactivation
        register_activation_hook(LS_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(LS_PLUGIN_FILE, [$this, 'deactivate']);

        // Init
        add_action('init', [$this, 'load_textdomain']);
        add_action('wp_loaded', [$this, 'on_wp_loaded']);

        // Admin
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // REST API
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Frontend
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }

    public function activate()
    {
        \LicenseServer\Database\Installer::install();
    }

    public function deactivate()
    {
        // Cleanup on deactivation if needed
    }

    public function load_textdomain()
    {
        load_plugin_textdomain('license-server', false, dirname(plugin_basename(LS_PLUGIN_FILE)) . '/languages');
    }

    public function on_wp_loaded()
    {
        // Check for security headers
        if (! headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
        }
    }

    public function register_admin_menu()
    {
        add_menu_page(
            esc_html__('License Server', 'license-server'),
            esc_html__('License Server', 'license-server'),
            'manage_options',
            'license-server',
            [$this, 'render_admin_dashboard'],
            'dashicons-shield'
        );

        add_submenu_page(
            'license-server',
            esc_html__('Manage Licenses', 'license-server'),
            esc_html__('Licenses', 'license-server'),
            'manage_options',
            'license-server-licenses',
            ['LicenseServer\Admin\License_Manager', 'render_page']
        );

        add_submenu_page(
            'license-server',
            esc_html__('Upload Plugins', 'license-server'),
            esc_html__('Upload Plugins', 'license-server'),
            'manage_options',
            'license-server-upload',
            ['LicenseServer\Admin\Plugin_Uploader', 'render_page']
        );
    }

    public function render_admin_dashboard()
    {
        \LicenseServer\Admin\Admin_Dashboard::render();
    }

    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'license-server') === false) {
            return;
        }

        wp_enqueue_style('license-server-admin', LS_PLUGIN_URL . 'assets/admin/css/admin.css', [], LS_VERSION);
        wp_enqueue_script('license-server-admin', LS_PLUGIN_URL . 'assets/admin/js/admin.js', ['jquery'], LS_VERSION, true);
        wp_localize_script('license-server-admin', 'lsAdmin', [
            'apiUrl'  => rest_url('license-server/v1/'),
            'nonce'   => wp_create_nonce('ls_admin_nonce'),
        ]);
    }

    public function register_rest_routes()
    {
        \LicenseServer\API\REST_API::register_routes();
    }

    public function enqueue_frontend_assets()
    {
        wp_enqueue_style('license-server-frontend', LS_PLUGIN_URL . 'assets/frontend/css/frontend.css', [], LS_VERSION);
        wp_enqueue_script('license-server-frontend', LS_PLUGIN_URL . 'assets/frontend/js/frontend.js', ['jquery'], LS_VERSION, true);
    }
}

// Initialize plugin
function license_server_init()
{
    License_Server::getInstance();
}
add_action('plugins_loaded', 'license_server_init');

// Global helper function
function license_server()
{
    return License_Server::getInstance();
}
