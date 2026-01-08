<?php

/**
 * Reusable Plugin License Class
 * 
 * Include this in your paid plugins to add licensing support.
 * 
 * Usage:
 * $license = new Plugin_License( 'my-plugin-slug', 'https://license-server.com' );
 * if ( $license->is_active() ) {
 *     // Show features
 * }
 */

class Plugin_License
{

    private $plugin_slug;
    private $server_url;
    private $license_key;
    private $site_url;
    private $transient_key;

    public function __construct($plugin_slug, $server_url)
    {
        $this->plugin_slug = sanitize_text_field($plugin_slug);
        $this->server_url = rtrim($server_url, '/');
        $this->site_url = untrailingslashit(home_url());
        $this->transient_key = 'plugin_license_' . $this->plugin_slug;
        $this->license_key = get_option('plugin_license_key_' . $this->plugin_slug);
    }

    /**
     * Set license key
     */
    public function set_license_key($key)
    {
        if (! $this->is_valid_key_format($key)) {
            return false;
        }

        $this->license_key = $key;
        update_option('plugin_license_key_' . $this->plugin_slug, $key);

        // Activate on server
        $activated = $this->activate_on_server();

        if ($activated) {
            // Clear cache
            delete_transient($this->transient_key);
        }

        return $activated;
    }

    /**
     * Get license key
     */
    public function get_license_key()
    {
        return $this->license_key;
    }

    /**
     * Check if license is active
     */
    public function is_active()
    {
        if (! $this->license_key) {
            return false;
        }

        // Check cache first
        $cached = get_transient($this->transient_key);
        if (false !== $cached) {
            return $cached['valid'];
        }

        // Verify with server
        return $this->verify_with_server();
    }

    /**
     * Deactivate license
     */
    public function deactivate()
    {
        if (! $this->license_key) {
            return false;
        }

        $response = wp_remote_post(
            $this->server_url . '/wp-json/license-server/v1/deactivate',
            [
                'body' => wp_json_encode([
                    'license_key' => $this->license_key,
                    'plugin_slug' => $this->plugin_slug,
                    'domain'      => $this->get_domain(),
                ]),
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => 15,
            ]
        );

        if (is_wp_error($response)) {
            return false;
        }

        delete_option('plugin_license_key_' . $this->plugin_slug);
        delete_transient($this->transient_key);

        return true;
    }

    /**
     * Check for updates
     */
    public function check_updates($current_version)
    {
        if (! $this->is_active()) {
            return false;
        }

        $response = wp_remote_post(
            $this->server_url . '/wp-json/license-server/v1/check-update',
            [
                'body' => wp_json_encode([
                    'license_key'     => $this->license_key,
                    'plugin_slug'     => $this->plugin_slug,
                    'current_version' => $current_version,
                ]),
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => 15,
            ]
        );

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (! $body['success'] || ! $body['has_update']) {
            return false;
        }

        return [
            'version'     => $body['new_version'],
            'download'    => $body['download_url'],
            'changelog'   => $body['changelog'],
            'requires_wp' => $body['requires_wp'],
            'requires_php' => $body['requires_php'],
        ];
    }

    /**
     * Get license info
     */
    public function get_info()
    {
        if (! $this->license_key) {
            return null;
        }

        $cached = get_transient($this->transient_key);
        if (false !== $cached) {
            return $cached['info'];
        }

        $this->verify_with_server();
        $cached = get_transient($this->transient_key);

        return $cached ? $cached['info'] : null;
    }

    /**
     * Check if license is expiring soon
     */
    public function is_expiring_soon($days = 30)
    {
        $info = $this->get_info();

        if (! $info || ! $info['expires_at']) {
            return false;
        }

        $expires_timestamp = strtotime($info['expires_at']);
        $soon_timestamp = strtotime("+$days days");

        return $expires_timestamp < $soon_timestamp;
    }

    /**
     * Get days until expiration
     */
    public function get_days_until_expiration()
    {
        $info = $this->get_info();

        if (! $info || ! $info['expires_at']) {
            return null;
        }

        $expires_timestamp = strtotime($info['expires_at']);
        $now_timestamp = current_time('timestamp');
        $days = ceil(($expires_timestamp - $now_timestamp) / DAY_IN_SECONDS);

        return max(0, $days);
    }

    /**
     * Private methods
     */

    private function verify_with_server()
    {
        if (! $this->license_key) {
            return false;
        }

        $response = wp_remote_post(
            $this->server_url . '/wp-json/license-server/v1/verify',
            [
                'body' => wp_json_encode([
                    'license_key' => $this->license_key,
                    'plugin_slug' => $this->plugin_slug,
                    'domain'      => $this->get_domain(),
                ]),
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => 15,
            ]
        );

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (! $body['success']) {
            return false;
        }

        // Cache for 24 hours
        set_transient(
            $this->transient_key,
            [
                'valid' => true,
                'info'  => $body,
            ],
            DAY_IN_SECONDS
        );

        return true;
    }

    private function activate_on_server()
    {
        $response = wp_remote_post(
            $this->server_url . '/wp-json/license-server/v1/activate',
            [
                'body' => wp_json_encode([
                    'license_key' => $this->license_key,
                    'plugin_slug' => $this->plugin_slug,
                    'domain'      => $this->get_domain(),
                ]),
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => 15,
            ]
        );

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (! isset($body['success']) || ! $body['success']) {
            // Log error
            update_option('plugin_license_error_' . $this->plugin_slug, $body['error'] ?? 'Unknown error');
            return false;
        }

        delete_option('plugin_license_error_' . $this->plugin_slug);
        return true;
    }

    private function get_domain()
    {
        return parse_url($this->site_url, PHP_URL_HOST) ?: $this->site_url;
    }

    private function is_valid_key_format($key)
    {
        return preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $key);
    }
}
