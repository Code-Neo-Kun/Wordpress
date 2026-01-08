<?php
if (!defined('ABSPATH')) {
    exit;
}

class FWAG_Redirect
{
    private static $instance = null;
    private $cookie_name = 'fwag_redirect_check';

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function should_redirect()
    {
        $redirect_enabled = get_option('fwag_redirect_enabled', false);

        if (!$redirect_enabled) {
            return false;
        }

        if ($this->is_safe_page()) {
            return false;
        }

        if (isset($_COOKIE[$this->cookie_name])) {
            return false;
        }

        return true;
    }

    public function perform_redirect()
    {
        $redirect_url = get_option('fwag_redirect_url', '');
        $redirect_type = get_option('fwag_redirect_type', 302);

        if (empty($redirect_url)) {
            $redirect_url = wp_login_url(get_permalink());
        }

        setcookie($this->cookie_name, '1', time() + 300, COOKIEPATH, COOKIE_DOMAIN);

        wp_redirect($redirect_url, $redirect_type);
        exit;
    }

    private function is_safe_page()
    {
        global $pagenow;

        if (is_admin()) {
            return true;
        }

        if (wp_doing_ajax()) {
            return true;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        $safe_pages = array('wp-login.php', 'wp-register.php');
        if (in_array($pagenow, $safe_pages)) {
            return true;
        }

        $login_url = wp_login_url();
        $register_url = wp_registration_url();
        $current_url = home_url($_SERVER['REQUEST_URI']);

        if ($current_url === $login_url || $current_url === $register_url) {
            return true;
        }

        return false;
    }
}
