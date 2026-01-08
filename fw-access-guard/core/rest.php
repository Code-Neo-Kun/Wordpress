<?php
if (!defined('ABSPATH')) {
    exit;
}

class FWAG_REST
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
        add_action('rest_api_init', array($this, 'register_routes'));
        add_filter('rest_prepare_post', array($this, 'filter_rest_content'), 10, 3);
        add_filter('rest_prepare_page', array($this, 'filter_rest_content'), 10, 3);
    }

    public function register_routes()
    {
        register_rest_route('fwag/v1', '/auth', array(
            'methods' => 'GET',
            'callback' => array($this, 'check_auth'),
            'permission_callback' => '__return_true'
        ));
    }

    public function check_auth()
    {
        $rules = FWAG_Rules::get_instance();

        return array(
            'loggedIn' => is_user_logged_in(),
            'allowed' => $rules->user_has_access()
        );
    }

    public function filter_rest_content($response, $post, $request)
    {
        if (!$this->should_block_rest_content($post->ID)) {
            return $response;
        }

        $rules = FWAG_Rules::get_instance();

        if (!$rules->user_has_access()) {
            return new WP_Error(
                'fwag_forbidden',
                __('You do not have permission to access this content.', 'fw-access-guard'),
                array('status' => 403)
            );
        }

        return $response;
    }

    private function should_block_rest_content($post_id)
    {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        $override_enabled = get_post_meta($post_id, '_fwag_override_enabled', true);
        if ($override_enabled === '1') {
            $is_protected = get_post_meta($post_id, '_fwag_is_protected', true);
            return $is_protected === '1';
        }

        $protected_pages = get_option('fwag_protected_pages', array());
        if (!is_array($protected_pages)) {
            $protected_pages = array();
        }
        if (is_string(get_option('fwag_protected_pages', ''))) {
            $pages_string = get_option('fwag_protected_pages', '');
            $protected_pages = array_map('intval', array_filter(explode(',', $pages_string)));
        }

        if (in_array($post_id, $protected_pages)) {
            return true;
        }

        $protected_post_types = get_option('fwag_protected_post_types', array());
        if (!is_array($protected_post_types)) {
            $protected_post_types = array();
        }

        if (in_array($post->post_type, $protected_post_types)) {
            return true;
        }

        return false;
    }
}

FWAG_REST::get_instance();
