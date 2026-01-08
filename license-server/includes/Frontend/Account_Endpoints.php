<?php

/**
 * Account Endpoints
 */

namespace LicenseServer\Frontend;

class Account_Endpoints
{

    public static function init()
    {
        add_action('wp_ajax_deactivate_domain', [__CLASS__, 'deactivate_domain']);
        add_action('wp_ajax_renew_license', [__CLASS__, 'renew_license']);
    }

    public static function deactivate_domain()
    {
        check_ajax_referer('ls_frontend_nonce');

        if (! is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not logged in']);
        }

        $domain = isset($_POST['domain']) ? sanitize_text_field($_POST['domain']) : '';
        $plugin_slug = isset($_POST['plugin']) ? sanitize_text_field($_POST['plugin']) : '';
        $license_id = isset($_POST['license_id']) ? (int) $_POST['license_id'] : 0;

        $license = new \LicenseServer\Models\License($license_id);

        // Verify ownership
        if ($license->get_user_id() !== get_current_user_id()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $result = $license->deactivate_domain($domain, $plugin_slug);

        if ($result) {
            wp_send_json_success(['message' => 'Domain deactivated successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to deactivate domain']);
        }
    }

    public static function renew_license()
    {
        check_ajax_referer('ls_frontend_nonce');

        if (! is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not logged in']);
        }

        $license_id = isset($_POST['license_id']) ? (int) $_POST['license_id'] : 0;
        $license = new \LicenseServer\Models\License($license_id);

        // Verify ownership
        if ($license->get_user_id() !== get_current_user_id()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $result = $license->renew(365);

        if ($result) {
            wp_send_json_success([
                'message' => 'License renewed successfully',
                'expires_at' => $license->get_expires_at(),
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to renew license']);
        }
    }
}

add_action('wp_loaded', ['LicenseServer\Frontend\Account_Endpoints', 'init']);
