<?php
if (!defined('ABSPATH')) {
    exit;
}

class FWAG_Shortcode
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
        add_shortcode('fwag_protected', array($this, 'protected_content_shortcode'));
        add_shortcode('fwag_login_link', array($this, 'login_link_shortcode'));
    }

    public function protected_content_shortcode($atts, $content = null)
    {
        $rules = FWAG_Rules::get_instance();

        if ($rules->user_has_access()) {
            return do_shortcode($content);
        }

        return '';
    }

    public function login_link_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'text' => __('Log In', 'fw-access-guard'),
            'redirect' => ''
        ), $atts);

        $redirect_url = !empty($atts['redirect']) ? $atts['redirect'] : get_permalink();
        $login_url = wp_login_url($redirect_url);
        $login_url = apply_filters('fwag_login_url', $login_url);

        return sprintf(
            '<a href="%s" class="fwag-login-link">%s</a>',
            esc_url($login_url),
            esc_html($atts['text'])
        );
    }
}

FWAG_Shortcode::get_instance();
