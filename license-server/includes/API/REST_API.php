<?php

/**
 * REST API Routes
 */

namespace LicenseServer\API;

use LicenseServer\Security\Validation;
use LicenseServer\Security\RateLimiter;
use LicenseServer\Models\License;

class REST_API
{

    public static function register_routes()
    {
        // License verification
        register_rest_route('license-server/v1', '/verify', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'verify_license'],
            'permission_callback' => [__CLASS__, 'api_permission_check'],
        ]);

        // Domain activation
        register_rest_route('license-server/v1', '/activate', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'activate_domain'],
            'permission_callback' => [__CLASS__, 'api_permission_check'],
        ]);

        // Domain deactivation
        register_rest_route('license-server/v1', '/deactivate', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'deactivate_domain'],
            'permission_callback' => [__CLASS__, 'api_permission_check'],
        ]);

        // Check updates
        register_rest_route('license-server/v1', '/check-update', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'check_update'],
            'permission_callback' => [__CLASS__, 'api_permission_check'],
        ]);

        // Download plugin
        register_rest_route('license-server/v1', '/download', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'download_plugin'],
            'permission_callback' => [__CLASS__, 'api_permission_check'],
        ]);

        // Renew license (user action)
        register_rest_route('license-server/v1', '/renew', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'renew_license'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * API permission check
     */
    public static function api_permission_check()
    {
        return true; // Rate limiting and license key validation handles security
    }

    /**
     * Verify license endpoint
     */
    public static function verify_license(\WP_REST_Request $request)
    {
        $params = $request->get_json_params();

        // Rate limiting
        $client_id = RateLimiter::get_client_id();
        if (RateLimiter::is_limited($client_id, 'verify_license', 30, 3600)) {
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'Rate limit exceeded'],
                429
            );
        }

        $license_key = isset($params['license_key']) ? trim($params['license_key']) : '';
        $plugin_slug = isset($params['plugin_slug']) ? trim($params['plugin_slug']) : '';
        $domain = isset($params['domain']) ? trim($params['domain']) : '';

        // Validate inputs
        if (! Validation::is_valid_license_key($license_key)) {
            RateLimiter::increment($client_id, 'verify_license', 3600);
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'Invalid license key format'],
                400
            );
        }

        if (! Validation::is_valid_plugin_slug($plugin_slug)) {
            RateLimiter::increment($client_id, 'verify_license', 3600);
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'Invalid plugin slug'],
                400
            );
        }

        if (! Validation::is_valid_domain($domain)) {
            RateLimiter::increment($client_id, 'verify_license', 3600);
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'Invalid domain'],
                400
            );
        }

        // Find license
        $license = License::find_by_key($license_key);
        if (! $license) {
            RateLimiter::increment($client_id, 'verify_license', 3600);
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'License not found'],
                404
            );
        }

        // Verify license is valid
        if (! $license->is_valid()) {
            RateLimiter::increment($client_id, 'verify_license', 3600);
            return new \WP_REST_Response(
                [
                    'success' => false,
                    'error'   => 'License is not active',
                    'status'  => $license->get_status(),
                ],
                403
            );
        }

        // Check if plugin is assigned
        $plugins = $license->get_plugins();
        if (! in_array($plugin_slug, $plugins, true)) {
            RateLimiter::increment($client_id, 'verify_license', 3600);
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'Plugin not assigned to license'],
                403
            );
        }

        RateLimiter::increment($client_id, 'verify_license', 3600);
        $license->log_activation('check_in', $plugin_slug, $domain, 'success');

        return new \WP_REST_Response([
            'success'         => true,
            'license_id'      => $license->get_id(),
            'status'          => $license->get_status(),
            'plan_type'       => $license->get_plan_type(),
            'expires_at'      => $license->get_expires_at(),
            'max_domains'     => $license->get_max_domains(),
            'active_domains'  => count($license->get_active_domains()),
            'plugins'         => $plugins,
        ]);
    }

    /**
     * Activate domain endpoint
     */
    public static function activate_domain(\WP_REST_Request $request)
    {
        $params = $request->get_json_params();

        $license_key = isset($params['license_key']) ? trim($params['license_key']) : '';
        $plugin_slug = isset($params['plugin_slug']) ? trim($params['plugin_slug']) : '';
        $domain = isset($params['domain']) ? trim($params['domain']) : '';

        // Validate
        if (
            ! Validation::is_valid_license_key($license_key) ||
            ! Validation::is_valid_plugin_slug($plugin_slug) ||
            ! Validation::is_valid_domain($domain)
        ) {
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'Invalid parameters'],
                400
            );
        }

        $license = License::find_by_key($license_key);
        if (! $license) {
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'License not found'],
                404
            );
        }

        $result = $license->activate_domain($domain, $plugin_slug, [
            'ip' => RateLimiter::get_client_id(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
        ]);

        if (! $result['success']) {
            return new \WP_REST_Response($result, 403);
        }

        return new \WP_REST_Response($result);
    }

    /**
     * Deactivate domain endpoint
     */
    public static function deactivate_domain(\WP_REST_Request $request)
    {
        $params = $request->get_json_params();

        $license_key = isset($params['license_key']) ? trim($params['license_key']) : '';
        $plugin_slug = isset($params['plugin_slug']) ? trim($params['plugin_slug']) : '';
        $domain = isset($params['domain']) ? trim($params['domain']) : '';

        if (! Validation::is_valid_license_key($license_key)) {
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'Invalid license key'],
                400
            );
        }

        $license = License::find_by_key($license_key);
        if (! $license) {
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'License not found'],
                404
            );
        }

        $result = $license->deactivate_domain($domain, $plugin_slug);

        return new \WP_REST_Response([
            'success' => $result,
            'message' => $result ? 'Domain deactivated successfully' : 'Failed to deactivate domain',
        ]);
    }

    /**
     * Check update endpoint
     */
    public static function check_update(\WP_REST_Request $request)
    {
        $params = $request->get_json_params();

        $license_key = isset($params['license_key']) ? trim($params['license_key']) : '';
        $plugin_slug = isset($params['plugin_slug']) ? trim($params['plugin_slug']) : '';
        $current_version = isset($params['current_version']) ? trim($params['current_version']) : '';

        // Validate
        if (
            ! Validation::is_valid_license_key($license_key) ||
            ! Validation::is_valid_plugin_slug($plugin_slug)
        ) {
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'Invalid parameters'],
                400
            );
        }

        $license = License::find_by_key($license_key);
        if (! $license || ! $license->is_valid()) {
            return new \WP_REST_Response(
                ['success' => false, 'has_update' => false],
                403
            );
        }

        // Get latest version
        $latest = \LicenseServer\Models\PluginVersion::get_latest($plugin_slug);
        if (! $latest) {
            return new \WP_REST_Response([
                'success'     => true,
                'has_update'  => false,
                'message'     => 'No versions found',
            ]);
        }

        // Check if update is available
        $has_update = version_compare($latest->version, $current_version, '>');

        return new \WP_REST_Response([
            'success'        => true,
            'has_update'     => $has_update,
            'current'        => $current_version,
            'new_version'    => $latest->version,
            'download_url'   => $has_update ? $latest->download_url : '',
            'changelog'      => $has_update ? $latest->changelog : '',
            'requires_php'   => $latest->requires_php,
            'requires_wp'    => $latest->requires_wp,
            'tested_up_to'   => $latest->tested_up_to,
        ]);
    }

    /**
     * Download plugin endpoint
     */
    public static function download_plugin(\WP_REST_Request $request)
    {
        $params = $request->get_query_params();

        $license_key = isset($params['key']) ? trim($params['key']) : '';
        $plugin_slug = isset($params['plugin']) ? trim($params['plugin']) : '';
        $version = isset($params['version']) ? trim($params['version']) : '';

        if (! Validation::is_valid_license_key($license_key)) {
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'Invalid license key'],
                401
            );
        }

        $license = License::find_by_key($license_key);
        if (! $license || ! $license->is_valid()) {
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'License is not valid'],
                403
            );
        }

        $plugin_version = \LicenseServer\Models\PluginVersion::get_by_version($plugin_slug, $version);
        if (! $plugin_version) {
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'Version not found'],
                404
            );
        }

        // Redirect to download
        wp_redirect(esc_url_raw($plugin_version->download_url));
        exit;
    }

    /**
     * Renew license endpoint
     */
    public static function renew_license(\WP_REST_Request $request)
    {
        $params = $request->get_json_params();

        $license_key = isset($params['license_key']) ? trim($params['license_key']) : '';
        $license = License::find_by_key($license_key);

        if (! $license) {
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'License not found'],
                404
            );
        }

        if (get_current_user_id() !== $license->get_user_id() && ! current_user_can('manage_options')) {
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'Unauthorized'],
                403
            );
        }

        $result = $license->renew(365);

        return new \WP_REST_Response([
            'success'    => $result,
            'expires_at' => $license->get_expires_at(),
        ]);
    }
}
