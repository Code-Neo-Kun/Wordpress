<?php
if (!defined('ABSPATH')) {
    exit;
}

class FWAG_Rules
{
    private static $instance = null;
    private $is_blocked = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function is_content_blocked()
    {
        if ($this->is_blocked !== null) {
            return $this->is_blocked;
        }

        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            $this->is_blocked = false;
            return false;
        }

        if (!is_singular() && !is_page() && !is_single()) {
            $this->is_blocked = false;
            return false;
        }

        global $post;
        if (!$post) {
            $this->is_blocked = false;
            return false;
        }

        $override_enabled = get_post_meta($post->ID, '_fwag_override_enabled', true);
        if ($override_enabled === '1') {
            $is_protected = get_post_meta($post->ID, '_fwag_is_protected', true);
            $this->is_blocked = ($is_protected === '1') && !$this->user_has_access();
            return $this->is_blocked;
        }

        $protected_pages = get_option('fwag_protected_pages', array());
        if (!is_array($protected_pages)) {
            $protected_pages = array();
        }
        if (is_string(get_option('fwag_protected_pages', ''))) {
            $pages_string = get_option('fwag_protected_pages', '');
            $protected_pages = array_map('intval', array_filter(explode(',', $pages_string)));
        }

        if (in_array($post->ID, $protected_pages)) {
            $this->is_blocked = !$this->user_has_access();
            return $this->is_blocked;
        }

        $url_patterns = get_option('fwag_url_patterns', array());
        if (!is_array($url_patterns)) {
            $url_patterns = array();
        }
        if (is_string(get_option('fwag_url_patterns', ''))) {
            $patterns_string = get_option('fwag_url_patterns', '');
            $url_patterns = array_filter(explode("\n", $patterns_string));
        }

        $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        foreach ($url_patterns as $pattern) {
            $pattern = trim($pattern);
            if (empty($pattern)) {
                continue;
            }
            $regex = str_replace('*', '.*', preg_quote($pattern, '/'));
            if (preg_match('/^' . $regex . '$/i', $current_path)) {
                $this->is_blocked = !$this->user_has_access();
                return $this->is_blocked;
            }
        }

        $protected_post_types = get_option('fwag_protected_post_types', array());
        if (!is_array($protected_post_types)) {
            $protected_post_types = array();
        }

        if (in_array($post->post_type, $protected_post_types)) {
            $this->is_blocked = !$this->user_has_access();
            return $this->is_blocked;
        }

        $this->is_blocked = false;
        return false;
    }

    public function user_has_access()
    {
        if (!is_user_logged_in()) {
            return false;
        }

        global $post;
        $post_id = $post ? $post->ID : 0;

        // Check time restrictions if enabled
        $time_restrictions_enabled = get_option('fwag_enable_time_restrictions', '0') === '1';
        if ($time_restrictions_enabled && class_exists('FWAG_Time_Restrictions')) {
            $time_restrictions = FWAG_Time_Restrictions::get_instance();
            if (!$time_restrictions->check_time_access($post_id)) {
                return apply_filters('fwag_user_has_access', false, $post_id);
            }
        }

        // Check user-specific access if enabled
        $user_access_enabled = get_option('fwag_enable_user_specific_access', '0') === '1';
        if ($user_access_enabled && class_exists('FWAG_User_Specific_Access')) {
            $user_access = FWAG_User_Specific_Access::get_instance();
            if (!$user_access->check_user_access($post_id)) {
                return apply_filters('fwag_user_has_access', false, $post_id);
            }
        }

        $allowed_roles = get_option('fwag_allowed_roles', array());
        if (empty($allowed_roles)) {
            return apply_filters('fwag_user_has_access', true, $post_id);
        }

        $user = wp_get_current_user();
        $user_roles = (array) $user->roles;

        foreach ($user_roles as $role) {
            if (in_array($role, $allowed_roles)) {
                return apply_filters('fwag_user_has_access', true, $post_id);
            }
        }

        return apply_filters('fwag_user_has_access', false, $post_id);
    }

    public function get_block_reason()
    {
        if (!is_user_logged_in()) {
            return 'not_logged_in';
        }
        return 'unauthorized';
    }
}
