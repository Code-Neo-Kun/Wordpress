<?php

/**
 * Plugin Name: FW Access Guard
 * Plugin URI: https://example.com/fw-access-guard
 * Description: Role-based content access control with server-side blocking, login overlay, REST protection, and headless compatibility.
 * Version: 1.0.0
 * Author: Neo kun
 * Author URI: https://example.com
 * License: GPL v2 or later
 * Text Domain: fw-access-guard
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FWAG_VERSION', '1.0.0');
define('FWAG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FWAG_PLUGIN_URL', plugin_dir_url(__FILE__));

class FW_Access_Guard
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->includes();
        $this->init_hooks();
    }

    private function includes()
    {
        require_once FWAG_PLUGIN_DIR . 'core/rules.php';
        require_once FWAG_PLUGIN_DIR . 'core/template-block.php';
        require_once FWAG_PLUGIN_DIR . 'core/redirect.php';
        require_once FWAG_PLUGIN_DIR . 'core/rest.php';
        require_once FWAG_PLUGIN_DIR . 'core/shortcode.php';

        // Load advanced features conditionally
        if (get_option('fwag_enable_content_teaser', '0') === '1') {
            require_once FWAG_PLUGIN_DIR . 'core/content-teaser.php';
        }
        if (get_option('fwag_enable_time_restrictions', '0') === '1') {
            require_once FWAG_PLUGIN_DIR . 'core/time-restrictions.php';
        }
        if (get_option('fwag_enable_user_specific_access', '0') === '1') {
            require_once FWAG_PLUGIN_DIR . 'core/user-specific-access.php';
        }
        if (get_option('fwag_enable_access_logging', '0') === '1') {
            require_once FWAG_PLUGIN_DIR . 'core/access-logging.php';
        }
        if (get_option('fwag_enable_file_protection', '0') === '1') {
            require_once FWAG_PLUGIN_DIR . 'core/file-protection.php';
        }

        if (is_admin()) {
            require_once FWAG_PLUGIN_DIR . 'admin/settings.php';
            require_once FWAG_PLUGIN_DIR . 'admin/metabox.php';
        }
    }

    private function init_hooks()
    {
        add_action('init', array($this, 'register_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // AJAX handlers for new features (conditional)
        if (get_option('fwag_enable_content_teaser', '0') === '1') {
            add_action('wp_ajax_fwag_load_full_content', array($this, 'ajax_load_full_content'));
            add_action('wp_ajax_nopriv_fwag_load_full_content', array($this, 'ajax_load_full_content'));
        }
        if (get_option('fwag_enable_access_logging', '0') === '1') {
            add_action('wp_ajax_fwag_log_access_attempt', array($this, 'ajax_log_access_attempt'));
            add_action('wp_ajax_nopriv_fwag_log_access_attempt', array($this, 'ajax_log_access_attempt'));
        }
        if (get_option('fwag_enable_user_specific_access', '0') === '1') {
            add_action('wp_ajax_fwag_search_users', array($this, 'ajax_search_users'));
        }
        if (get_option('fwag_enable_file_protection', '0') === '1') {
            add_action('wp_ajax_fwag_toggle_file_protection', array($this, 'ajax_toggle_file_protection'));
            add_action('wp_ajax_fwag_check_file_protection', array($this, 'ajax_check_file_protection'));
        }
    }

    public function register_assets()
    {
        wp_register_style('fwag-guard', FWAG_PLUGIN_URL . 'assets/guard.css', array(), FWAG_VERSION);
        wp_register_script('fwag-guard', FWAG_PLUGIN_URL . 'assets/guard.js', array(), FWAG_VERSION, true);

        // Register new feature scripts
        wp_register_script('fwag-teaser', FWAG_PLUGIN_URL . 'assets/teaser.js', array('jquery'), FWAG_VERSION, true);
        wp_register_script('fwag-time-restrictions', FWAG_PLUGIN_URL . 'assets/time-restrictions.js', array('jquery'), FWAG_VERSION, true);
        wp_register_script('fwag-user-search', FWAG_PLUGIN_URL . 'assets/user-search.js', array('jquery'), FWAG_VERSION, true);
        wp_register_script('fwag-file-protection', FWAG_PLUGIN_URL . 'assets/file-protection.js', array('jquery'), FWAG_VERSION, true);
    }

    public function enqueue_assets()
    {
        if (FWAG_Rules::get_instance()->is_content_blocked()) {
            wp_enqueue_style('fwag-guard');
            wp_enqueue_script('fwag-guard');
        }

        // Enqueue teaser script if content teasers are enabled
        $teaser_enabled = get_option('fwag_enable_content_teaser', '0') === '1';
        if ($teaser_enabled && class_exists('FWAG_Content_Teaser')) {
            wp_enqueue_script('fwag-teaser');
        }

        // Enqueue time restrictions script if time restrictions are enabled
        $time_restrictions_enabled = get_option('fwag_enable_time_restrictions', '0') === '1';
        if ($time_restrictions_enabled && class_exists('FWAG_Time_Restrictions')) {
            wp_enqueue_script('fwag-time-restrictions');
        }

        // Enqueue user search script if user-specific access is enabled
        $user_access_enabled = get_option('fwag_enable_user_specific_access', '0') === '1';
        if ($user_access_enabled && class_exists('FWAG_User_Specific_Access')) {
            wp_enqueue_script('fwag-user-search');
        }

        // Enqueue file protection script if file protection is enabled
        $file_protection_enabled = get_option('fwag_enable_file_protection', '0') === '1';
        if ($file_protection_enabled && class_exists('FWAG_File_Protection')) {
            wp_enqueue_script('fwag-file-protection');
        }
    }

    public function enqueue_admin_assets($hook)
    {
        if ($hook === 'settings_page_fw-access-guard') {
            wp_enqueue_style('fwag-admin', FWAG_PLUGIN_URL . 'assets/admin.css', array(), FWAG_VERSION);
            wp_enqueue_script('fwag-admin', FWAG_PLUGIN_URL . 'assets/admin.js', array('jquery'), FWAG_VERSION, true);
        }

        // Enqueue admin scripts for post edit pages
        if (in_array($hook, array('post.php', 'post-new.php'))) {
            $user_access_enabled = get_option('fwag_enable_user_specific_access', '0') === '1';
            if ($user_access_enabled && class_exists('FWAG_User_Specific_Access')) {
                wp_enqueue_script('fwag-user-search', FWAG_PLUGIN_URL . 'assets/user-search.js', array('jquery'), FWAG_VERSION, true);
            }

            $file_protection_enabled = get_option('fwag_enable_file_protection', '0') === '1';
            if ($file_protection_enabled && class_exists('FWAG_File_Protection')) {
                wp_enqueue_script('fwag-file-protection', FWAG_PLUGIN_URL . 'assets/file-protection.js', array('jquery'), FWAG_VERSION, true);
            }
        }
    }

    public function ajax_load_full_content()
    {
        if (get_option('fwag_enable_content_teaser', '0') === '1' && class_exists('FWAG_Content_Teaser')) {
            $teaser = FWAG_Content_Teaser::get_instance();
            $teaser->ajax_load_full_content();
        }
    }

    public function ajax_log_access_attempt()
    {
        if (get_option('fwag_enable_access_logging', '0') === '1' && class_exists('FWAG_Access_Logging')) {
            $logging = FWAG_Access_Logging::get_instance();
            $logging->ajax_log_access_attempt();
        }
    }

    public function ajax_search_users()
    {
        if (get_option('fwag_enable_user_specific_access', '0') === '1' && class_exists('FWAG_User_Specific_Access')) {
            $user_access = FWAG_User_Specific_Access::get_instance();
            $user_access->ajax_search_users();
        }
    }

    public function ajax_toggle_file_protection()
    {
        if (get_option('fwag_enable_file_protection', '0') === '1' && class_exists('FWAG_File_Protection')) {
            $file_protection = FWAG_File_Protection::get_instance();
            $file_protection->ajax_toggle_file_protection();
        }
    }

    public function ajax_check_file_protection()
    {
        if (get_option('fwag_enable_file_protection', '0') === '1') {
            $file_id = intval($_POST['file_id']);
            $protected = get_post_meta($file_id, '_fwag_file_protected', true) === '1';
            wp_send_json(array('protected' => $protected));
        }
    }
}

function fwag_init()
{
    FW_Access_Guard::get_instance();
}
add_action('plugins_loaded', 'fwag_init');
