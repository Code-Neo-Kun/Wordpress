<?php
if (!defined('ABSPATH')) {
    exit;
}

class FWAG_Template_Block
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
        add_filter('template_include', array($this, 'maybe_override_template'), 99);
    }

    public function maybe_override_template($template)
    {
        if (is_admin() || wp_doing_ajax()) {
            return $template;
        }

        $rules = FWAG_Rules::get_instance();
        if ($rules->is_content_blocked()) {
            $redirect_handler = FWAG_Redirect::get_instance();
            if ($redirect_handler->should_redirect()) {
                $redirect_handler->perform_redirect();
                exit;
            }

            // Check if content teasers are enabled and should be shown
            $teaser_enabled = get_option('fwag_enable_content_teaser', '0') === '1';
            if ($teaser_enabled && class_exists('FWAG_Content_Teaser')) {
                $teaser = FWAG_Content_Teaser::get_instance();
                if ($teaser->should_show_teaser()) {
                    return FWAG_PLUGIN_DIR . 'core/teaser-template.php';
                }
            }

            return FWAG_PLUGIN_DIR . 'core/blocked-template.php';
        }

        return $template;
    }
}

FWAG_Template_Block::get_instance();
