<?php

/**
 * Activated Domain Model
 */

namespace LicenseServer\Models;

class ActivatedDomain
{

    public static function check_in($license_id, $domain, $plugin_slug)
    {
        global $wpdb;

        $result = $wpdb->update(
            $wpdb->prefix . 'ls_activated_domains',
            ['last_check_in' => current_time('mysql')],
            [
                'license_id'  => (int) $license_id,
                'plugin_slug' => sanitize_text_field($plugin_slug),
                'domain'      => sanitize_text_field($domain),
            ]
        );

        return (bool) $result;
    }

    public static function get_by_token($token)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ls_activated_domains 
                 WHERE activation_token = %s AND deactivated_at IS NULL",
                sanitize_text_field($token)
            )
        );
    }

    public static function verify_activation($license_id, $domain, $plugin_slug)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ls_activated_domains 
                 WHERE license_id = %d AND domain = %s AND plugin_slug = %s AND deactivated_at IS NULL",
                (int) $license_id,
                sanitize_text_field($domain),
                sanitize_text_field($plugin_slug)
            )
        );
    }

    public static function get_stale_activations($days = 30)
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ls_activated_domains 
                 WHERE last_check_in < DATE_SUB(NOW(), INTERVAL %d DAY) 
                 AND deactivated_at IS NULL",
                (int) $days
            )
        );
    }
}
