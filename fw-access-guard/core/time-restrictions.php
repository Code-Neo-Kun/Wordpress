<?php
if (!defined('ABSPATH')) {
    exit;
}

class FWAG_Time_Restrictions
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
        add_filter('fwag_user_has_access', array($this, 'check_time_restrictions'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function enqueue_admin_scripts($hook)
    {
        if ($hook === 'post.php' || $hook === 'post-new.php') {
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
            wp_enqueue_script('fwag-time-restrictions', FWAG_PLUGIN_URL . 'assets/time-restrictions.js', array('jquery', 'jquery-ui-datepicker'), FWAG_VERSION, true);
        }
    }

    public function check_time_restrictions($has_access, $post_id = null)
    {
        if (!$has_access) {
            return false;
        }

        $post_id = $post_id ?: get_the_ID();
        if (!$post_id) {
            return $has_access;
        }

        $time_restrictions_enabled = get_post_meta($post_id, '_fwag_time_restrictions_enabled', true);
        if ($time_restrictions_enabled !== '1') {
            return $has_access;
        }

        $start_date = get_post_meta($post_id, '_fwag_start_date', true);
        $end_date = get_post_meta($post_id, '_fwag_end_date', true);
        $start_time = get_post_meta($post_id, '_fwag_start_time', true);
        $end_time = get_post_meta($post_id, '_fwag_end_time', true);
        $allowed_days = get_post_meta($post_id, '_fwag_allowed_days', true);
        $timezone = get_post_meta($post_id, '_fwag_timezone', true);

        $timezone = $timezone ?: wp_timezone();
        $now = new DateTime('now', $timezone);

        // Check date restrictions
        if (!empty($start_date)) {
            $start = new DateTime($start_date, $timezone);
            if ($now < $start) {
                return false;
            }
        }

        if (!empty($end_date)) {
            $end = new DateTime($end_date . ' 23:59:59', $timezone);
            if ($now > $end) {
                return false;
            }
        }

        // Check time restrictions
        if (!empty($start_time) || !empty($end_time)) {
            $current_time = $now->format('H:i');

            if (!empty($start_time) && $current_time < $start_time) {
                return false;
            }

            if (!empty($end_time) && $current_time > $end_time) {
                return false;
            }
        }

        // Check day restrictions
        if (!empty($allowed_days) && is_array($allowed_days)) {
            $current_day = $now->format('w'); // 0 = Sunday, 6 = Saturday
            if (!in_array($current_day, $allowed_days)) {
                return false;
            }
        }

        return $has_access;
    }

    public function get_restriction_message($post_id)
    {
        $start_date = get_post_meta($post_id, '_fwag_start_date', true);
        $end_date = get_post_meta($post_id, '_fwag_end_date', true);
        $start_time = get_post_meta($post_id, '_fwag_start_time', true);
        $end_time = get_post_meta($post_id, '_fwag_end_time', true);
        $allowed_days = get_post_meta($post_id, '_fwag_allowed_days', true);
        $timezone = get_post_meta($post_id, '_fwag_timezone', true);

        $timezone = $timezone ?: wp_timezone();
        $now = new DateTime('now', $timezone);

        $messages = array();

        if (!empty($start_date)) {
            $start = new DateTime($start_date, $timezone);
            if ($now < $start) {
                $messages[] = sprintf(__('Content will be available starting %s', 'fw-access-guard'), $start->format('F j, Y'));
            }
        }

        if (!empty($end_date)) {
            $end = new DateTime($end_date, $timezone);
            if ($now > $end) {
                $messages[] = sprintf(__('Content was no longer available after %s', 'fw-access-guard'), $end->format('F j, Y'));
            }
        }

        if (!empty($start_time) || !empty($end_time)) {
            $time_restriction = '';
            if (!empty($start_time) && !empty($end_time)) {
                $time_restriction = sprintf(__('between %s and %s', 'fw-access-guard'), $start_time, $end_time);
            } elseif (!empty($start_time)) {
                $time_restriction = sprintf(__('after %s', 'fw-access-guard'), $start_time);
            } elseif (!empty($end_time)) {
                $time_restriction = sprintf(__('before %s', 'fw-access-guard'), $end_time);
            }

            if ($time_restriction) {
                $messages[] = sprintf(__('Content is only available %s', 'fw-access-guard'), $time_restriction);
            }
        }

        if (!empty($allowed_days) && is_array($allowed_days)) {
            $day_names = array(
                0 => __('Sunday', 'fw-access-guard'),
                1 => __('Monday', 'fw-access-guard'),
                2 => __('Tuesday', 'fw-access-guard'),
                3 => __('Wednesday', 'fw-access-guard'),
                4 => __('Thursday', 'fw-access-guard'),
                5 => __('Friday', 'fw-access-guard'),
                6 => __('Saturday', 'fw-access-guard')
            );

            $allowed_day_names = array_map(function ($day) use ($day_names) {
                return $day_names[$day];
            }, $allowed_days);

            $messages[] = sprintf(__('Content is only available on: %s', 'fw-access-guard'), implode(', ', $allowed_day_names));
        }

        return implode('. ', $messages);
    }

    public function is_content_time_restricted($post_id = null)
    {
        $post_id = $post_id ?: get_the_ID();
        if (!$post_id) {
            return false;
        }

        $time_restrictions_enabled = get_post_meta($post_id, '_fwag_time_restrictions_enabled', true);
        return $time_restrictions_enabled === '1';
    }
}

FWAG_Time_Restrictions::get_instance();
