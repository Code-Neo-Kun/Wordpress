<?php
if (!defined('ABSPATH')) {
    exit;
}

class FWAG_User_Specific_Access
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
        add_filter('fwag_user_has_access', array($this, 'check_user_specific_access'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function enqueue_admin_scripts($hook)
    {
        if ($hook === 'post.php' || $hook === 'post-new.php') {
            wp_enqueue_script('fwag-user-search', FWAG_PLUGIN_URL . 'assets/user-search.js', array('jquery'), FWAG_VERSION, true);
            wp_localize_script('fwag-user-search', 'fwagUserSearch', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fwag_user_search_nonce'),
                'searchPlaceholder' => __('Search users...', 'fw-access-guard'),
                'noUsersFound' => __('No users found', 'fw-access-guard')
            ));
        }
    }

    public function check_user_specific_access($has_access, $post_id = null)
    {
        $post_id = $post_id ?: get_the_ID();
        if (!$post_id) {
            return $has_access;
        }

        $user_restrictions_enabled = get_post_meta($post_id, '_fwag_user_restrictions_enabled', true);
        if ($user_restrictions_enabled !== '1') {
            return $has_access;
        }

        if (!is_user_logged_in()) {
            return false;
        }

        $current_user_id = get_current_user_id();
        $restriction_type = get_post_meta($post_id, '_fwag_user_restriction_type', true);
        $allowed_users = get_post_meta($post_id, '_fwag_allowed_users', true);
        $blocked_users = get_post_meta($post_id, '_fwag_blocked_users', true);

        $allowed_users = is_array($allowed_users) ? $allowed_users : array();
        $blocked_users = is_array($blocked_users) ? $blocked_users : array();

        // Check if user is explicitly blocked
        if (in_array($current_user_id, $blocked_users)) {
            return false;
        }

        // Check restriction type
        if ($restriction_type === 'whitelist') {
            // Only allowed users can access
            return in_array($current_user_id, $allowed_users);
        } elseif ($restriction_type === 'blacklist') {
            // All users can access except blocked ones
            return !in_array($current_user_id, $blocked_users);
        }

        return $has_access;
    }

    public function ajax_search_users()
    {
        check_ajax_referer('fwag_user_search_nonce', 'nonce');

        $search = sanitize_text_field($_POST['search']);
        $exclude = isset($_POST['exclude']) ? array_map('intval', $_POST['exclude']) : array();

        $args = array(
            'search' => '*' . $search . '*',
            'search_columns' => array('user_login', 'user_email', 'display_name'),
            'number' => 10,
            'exclude' => $exclude,
            'fields' => array('ID', 'user_login', 'display_name', 'user_email')
        );

        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();

        $results = array();
        foreach ($users as $user) {
            $results[] = array(
                'id' => $user->ID,
                'text' => sprintf('%s (%s)', $user->display_name, $user->user_email),
                'login' => $user->user_login,
                'email' => $user->user_email
            );
        }

        wp_send_json($results);
    }

    public function get_user_restriction_message($post_id)
    {
        $restriction_type = get_post_meta($post_id, '_fwag_user_restriction_type', true);
        $allowed_users = get_post_meta($post_id, '_fwag_allowed_users', true);
        $blocked_users = get_post_meta($post_id, '_fwag_blocked_users', true);

        if ($restriction_type === 'whitelist' && !empty($allowed_users)) {
            $count = count($allowed_users);
            return sprintf(_n('This content is restricted to %d specific user.', 'This content is restricted to %d specific users.', $count, 'fw-access-guard'), $count);
        } elseif ($restriction_type === 'blacklist' && !empty($blocked_users)) {
            $count = count($blocked_users);
            return sprintf(_n('This content is blocked for %d specific user.', 'This content is blocked for %d specific users.', $count, 'fw-access-guard'), $count);
        }

        return __('This content has user-specific access restrictions.', 'fw-access-guard');
    }

    public function is_content_user_restricted($post_id = null)
    {
        $post_id = $post_id ?: get_the_ID();
        if (!$post_id) {
            return false;
        }

        $user_restrictions_enabled = get_post_meta($post_id, '_fwag_user_restrictions_enabled', true);
        return $user_restrictions_enabled === '1';
    }

    public function get_allowed_users($post_id)
    {
        $allowed_users = get_post_meta($post_id, '_fwag_allowed_users', true);
        return is_array($allowed_users) ? $allowed_users : array();
    }

    public function get_blocked_users($post_id)
    {
        $blocked_users = get_post_meta($post_id, '_fwag_blocked_users', true);
        return is_array($blocked_users) ? $blocked_users : array();
    }
}

FWAG_User_Specific_Access::get_instance();
