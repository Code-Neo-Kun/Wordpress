<?php

/**
 * License Model
 */

namespace LicenseServer\Models;

use LicenseServer\Security\Encryption;
use LicenseServer\Security\Validation;

class License
{

    private $id;
    private $license_key;
    private $user_id;
    private $status;
    private $plan_type;
    private $max_domains;
    private $created_at;
    private $expires_at;
    private $meta;

    public function __construct($id = null)
    {
        if ($id) {
            $this->load($id);
        }
    }

    /**
     * Load license from database
     */
    public function load($id)
    {
        global $wpdb;

        $license = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ls_licenses WHERE id = %d",
                $id
            )
        );

        if (! $license) {
            return false;
        }

        $this->id = (int) $license->id;
        $this->license_key = $license->license_key;
        $this->user_id = (int) $license->user_id;
        $this->status = $license->status;
        $this->plan_type = $license->plan_type;
        $this->max_domains = (int) $license->max_domains;
        $this->created_at = $license->created_at;
        $this->expires_at = $license->expires_at;
        $this->meta = maybe_unserialize($license->meta);

        return true;
    }

    /**
     * Find by license key
     */
    public static function find_by_key($key)
    {
        global $wpdb;

        if (! Validation::is_valid_license_key($key)) {
            return null;
        }

        $hash = Encryption::hash_license_key($key);

        $license = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ls_licenses WHERE license_key_hash = %s",
                $hash
            )
        );

        if (! $license) {
            return null;
        }

        return new self((int) $license->id);
    }

    /**
     * Find by user and plugin
     */
    public static function find_by_user_and_plugin($user_id, $plugin_slug)
    {
        global $wpdb;

        $licenses = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.id FROM {$wpdb->prefix}ls_licenses l 
                 INNER JOIN {$wpdb->prefix}ls_license_plugins lp ON l.id = lp.license_id 
                 WHERE l.user_id = %d AND lp.plugin_slug = %s AND l.status IN ('active', 'inactive')",
                $user_id,
                sanitize_text_field($plugin_slug)
            )
        );

        return array_map(function ($l) {
            return new self((int) $l->id);
        }, $licenses);
    }

    /**
     * Create new license
     */
    public static function create($args = [])
    {
        global $wpdb;

        $defaults = [
            'user_id'      => get_current_user_id(),
            'plan_type'    => 'single',
            'max_domains'  => LS_MAX_DOMAINS_DEFAULT,
            'expires_at'   => date('Y-m-d H:i:s', strtotime('+' . LS_LICENSE_VALIDITY_DAYS . ' days')),
        ];

        $args = wp_parse_args($args, $defaults);

        $license_key = Encryption::generate_license_key();
        $license_hash = Encryption::hash_license_key($license_key);
        $created_ip = \LicenseServer\Security\RateLimiter::get_client_id();

        $result = $wpdb->insert(
            $wpdb->prefix . 'ls_licenses',
            [
                'license_key'       => $license_key,
                'license_key_hash'  => $license_hash,
                'user_id'           => (int) $args['user_id'],
                'status'            => 'active',
                'plan_type'         => sanitize_text_field($args['plan_type']),
                'max_domains'       => (int) $args['max_domains'],
                'created_at'        => current_time('mysql'),
                'expires_at'        => $args['expires_at'],
                'created_ip'        => $created_ip,
            ]
        );

        if (! $result) {
            return false;
        }

        $license = new self($wpdb->insert_id);
        $license->log_history('created', null, [
            'user_id'    => $args['user_id'],
            'plan_type'  => $args['plan_type'],
        ], 'Created via admin or WooCommerce');

        do_action('license_server_license_created', $license);

        return $license;
    }

    /**
     * Add plugin to license
     */
    public function add_plugin($plugin_slug)
    {
        global $wpdb;

        if (! Validation::is_valid_plugin_slug($plugin_slug)) {
            return false;
        }

        $result = $wpdb->insert(
            $wpdb->prefix . 'ls_license_plugins',
            [
                'license_id'  => $this->id,
                'plugin_slug' => sanitize_text_field($plugin_slug),
                'assigned_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s']
        );

        if ($result) {
            do_action('license_server_plugin_added', $this->id, $plugin_slug);
        }

        return (bool) $result;
    }

    /**
     * Remove plugin from license
     */
    public function remove_plugin($plugin_slug)
    {
        global $wpdb;

        $result = $wpdb->update(
            $wpdb->prefix . 'ls_license_plugins',
            ['removed_at' => current_time('mysql')],
            [
                'license_id'  => $this->id,
                'plugin_slug' => sanitize_text_field($plugin_slug),
            ]
        );

        if ($result) {
            do_action('license_server_plugin_removed', $this->id, $plugin_slug);
        }

        return (bool) $result;
    }

    /**
     * Get assigned plugins
     */
    public function get_plugins()
    {
        global $wpdb;

        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT plugin_slug FROM {$wpdb->prefix}ls_license_plugins 
                 WHERE license_id = %d AND removed_at IS NULL",
                $this->id
            )
        );
    }

    /**
     * Activate domain
     */
    public function activate_domain($domain, $plugin_slug, $meta = [])
    {
        global $wpdb;

        if (! Validation::is_valid_domain($domain)) {
            return ['success' => false, 'error' => 'Invalid domain'];
        }

        $domain = Validation::sanitize_domain($domain);

        // Check if license is valid
        if (! $this->is_valid()) {
            return ['success' => false, 'error' => 'License is not active'];
        }

        // Check domain limit
        if ($this->get_active_domain_count() >= $this->max_domains) {
            return ['success' => false, 'error' => 'Domain limit reached'];
        }

        // Check if plugin is assigned
        if (! in_array($plugin_slug, $this->get_plugins(), true)) {
            return ['success' => false, 'error' => 'Plugin not assigned to license'];
        }

        $activation_token = Encryption::generate_activation_token();
        $install_uuid = Encryption::generate_uuid();
        $ip_address = \LicenseServer\Security\RateLimiter::get_client_id();

        // Try to insert, or update if exists
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ls_activated_domains 
                 WHERE license_id = %d AND plugin_slug = %s AND domain = %s AND deactivated_at IS NULL",
                $this->id,
                sanitize_text_field($plugin_slug),
                $domain
            )
        );

        if ($existing) {
            // Update existing activation
            $wpdb->update(
                $wpdb->prefix . 'ls_activated_domains',
                [
                    'last_check_in'    => current_time('mysql'),
                    'activation_token' => $activation_token,
                ],
                ['id' => $existing->id]
            );
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'ls_activated_domains',
                [
                    'license_id'       => $this->id,
                    'plugin_slug'      => sanitize_text_field($plugin_slug),
                    'domain'           => $domain,
                    'ip_address'       => $ip_address,
                    'activated_at'     => current_time('mysql'),
                    'activation_token' => $activation_token,
                    'install_uuid'     => $install_uuid,
                    'meta'             => maybe_serialize($meta),
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );
        }

        $this->log_activation('activate', $plugin_slug, $domain, 'success');
        do_action('license_server_domain_activated', $this->id, $plugin_slug, $domain);

        return [
            'success'            => true,
            'activation_token'   => $activation_token,
            'install_uuid'       => $install_uuid,
            'domains_remaining'  => $this->max_domains - $this->get_active_domain_count(),
        ];
    }

    /**
     * Deactivate domain
     */
    public function deactivate_domain($domain, $plugin_slug)
    {
        global $wpdb;

        $domain = Validation::sanitize_domain($domain);

        $result = $wpdb->update(
            $wpdb->prefix . 'ls_activated_domains',
            ['deactivated_at' => current_time('mysql')],
            [
                'license_id'  => $this->id,
                'plugin_slug' => sanitize_text_field($plugin_slug),
                'domain'      => $domain,
            ]
        );

        if ($result) {
            $this->log_activation('deactivate', $plugin_slug, $domain, 'success');
            do_action('license_server_domain_deactivated', $this->id, $plugin_slug, $domain);
        }

        return (bool) $result;
    }

    /**
     * Get active domains
     */
    public function get_active_domains($plugin_slug = null)
    {
        global $wpdb;

        if ($plugin_slug) {
            $plugin_slug = sanitize_text_field($plugin_slug);
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}ls_activated_domains 
                     WHERE license_id = %d AND deactivated_at IS NULL AND plugin_slug = %s",
                    $this->id,
                    $plugin_slug
                )
            );
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ls_activated_domains 
                 WHERE license_id = %d AND deactivated_at IS NULL",
                $this->id
            )
        );
    }

    /**
     * Get active domain count
     */
    public function get_active_domain_count()
    {
        return count($this->get_active_domains());
    }

    /**
     * Check if license is valid
     */
    public function is_valid()
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->expires_at && strtotime($this->expires_at) < current_time('timestamp')) {
            return false;
        }

        return true;
    }

    /**
     * Suspend license
     */
    public function suspend($reason = '')
    {
        global $wpdb;

        $result = $wpdb->update(
            $wpdb->prefix . 'ls_licenses',
            [
                'status'          => 'suspended',
                'suspended_at'    => current_time('mysql'),
                'suspension_reason' => sanitize_text_field($reason),
            ],
            ['id' => $this->id]
        );

        if ($result) {
            $this->status = 'suspended';
            $this->log_history('suspended', 'active', 'suspended', $reason);
            do_action('license_server_license_suspended', $this->id, $reason);
        }

        return (bool) $result;
    }

    /**
     * Renew license
     */
    public function renew($days = LS_LICENSE_VALIDITY_DAYS)
    {
        global $wpdb;

        $new_expiry = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));
        $old_expiry = $this->expires_at;

        $result = $wpdb->update(
            $wpdb->prefix . 'ls_licenses',
            [
                'status'                    => 'active',
                'expires_at'                => $new_expiry,
                'renewal_reminder_sent'     => 0,
                'suspended_at'              => null,
                'suspension_reason'         => null,
            ],
            ['id' => $this->id]
        );

        if ($result) {
            $this->status = 'active';
            $this->expires_at = $new_expiry;
            $this->log_history('renewed', $old_expiry, $new_expiry, 'License renewed');
            do_action('license_server_license_renewed', $this->id, $new_expiry);
        }

        return (bool) $result;
    }

    /**
     * Log activation
     */
    public function log_activation($action, $plugin_slug, $domain, $status = 'success', $reason = '')
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'ls_activation_logs',
            [
                'license_id'  => $this->id,
                'plugin_slug' => sanitize_text_field($plugin_slug),
                'domain'      => $domain,
                'action'      => sanitize_text_field($action),
                'status'      => sanitize_text_field($status),
                'reason'      => sanitize_text_field($reason),
                'ip_address'  => \LicenseServer\Security\RateLimiter::get_client_id(),
                'user_agent'  => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(substr($_SERVER['HTTP_USER_AGENT'], 0, 500)) : '',
                'created_at'  => current_time('mysql'),
            ]
        );
    }

    /**
     * Log history
     */
    public function log_history($change_type, $old_value = null, $new_value = null, $reason = '')
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'ls_license_history',
            [
                'license_id'  => $this->id,
                'change_type' => sanitize_text_field($change_type),
                'old_value'   => maybe_serialize($old_value),
                'new_value'   => maybe_serialize($new_value),
                'reason'      => sanitize_text_field($reason),
                'changed_by'  => get_current_user_id(),
                'ip_address'  => \LicenseServer\Security\RateLimiter::get_client_id(),
                'created_at'  => current_time('mysql'),
            ]
        );
    }

    /**
     * Get by ID
     */
    public function get_id()
    {
        return $this->id;
    }

    public function get_license_key()
    {
        return $this->license_key;
    }

    public function get_user_id()
    {
        return $this->user_id;
    }

    public function get_status()
    {
        return $this->status;
    }

    public function get_plan_type()
    {
        return $this->plan_type;
    }

    public function get_max_domains()
    {
        return $this->max_domains;
    }

    public function get_created_at()
    {
        return $this->created_at;
    }

    public function get_expires_at()
    {
        return $this->expires_at;
    }

    public function get_meta($key = null)
    {
        if ($key) {
            return $this->meta[$key] ?? null;
        }
        return $this->meta;
    }
}
