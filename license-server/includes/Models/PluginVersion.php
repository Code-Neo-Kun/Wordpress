<?php

/**
 * Plugin Version Model
 */

namespace LicenseServer\Models;

use LicenseServer\Security\Validation;

class PluginVersion
{

    public static function create($args = [])
    {
        global $wpdb;

        $defaults = [
            'plugin_slug'   => '',
            'version'       => '',
            'download_url'  => '',
            'changelog'     => '',
        ];

        $args = wp_parse_args($args, $defaults);

        if (
            ! Validation::is_valid_plugin_slug($args['plugin_slug']) ||
            ! Validation::is_valid_version($args['version'])
        ) {
            return false;
        }

        $file_hash = '';
        $file_size = 0;

        if (! empty($args['file_path']) && file_exists($args['file_path'])) {
            $file_hash = hash_file('sha256', $args['file_path']);
            $file_size = filesize($args['file_path']);
        }

        $result = $wpdb->insert(
            $wpdb->prefix . 'ls_plugin_versions',
            [
                'plugin_slug'   => sanitize_text_field($args['plugin_slug']),
                'version'       => sanitize_text_field($args['version']),
                'download_url'  => esc_url_raw($args['download_url']),
                'file_hash'     => $file_hash,
                'file_size'     => $file_size,
                'changelog'     => wp_kses_post($args['changelog']),
                'requires_php'  => ! empty($args['requires_php']) ? sanitize_text_field($args['requires_php']) : null,
                'requires_wp'   => ! empty($args['requires_wp']) ? sanitize_text_field($args['requires_wp']) : null,
                'tested_up_to'  => ! empty($args['tested_up_to']) ? sanitize_text_field($args['tested_up_to']) : null,
                'released_at'   => current_time('mysql'),
                'created_at'    => current_time('mysql'),
            ]
        );

        return (bool) $result;
    }

    public static function get_latest($plugin_slug)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ls_plugin_versions 
                 WHERE plugin_slug = %s 
                 ORDER BY released_at DESC 
                 LIMIT 1",
                sanitize_text_field($plugin_slug)
            )
        );
    }

    public static function get_updates_since($plugin_slug, $current_version)
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ls_plugin_versions 
                 WHERE plugin_slug = %s 
                 ORDER BY released_at DESC",
                sanitize_text_field($plugin_slug)
            )
        );
    }

    public static function get_by_version($plugin_slug, $version)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ls_plugin_versions 
                 WHERE plugin_slug = %s AND version = %s",
                sanitize_text_field($plugin_slug),
                sanitize_text_field($version)
            )
        );
    }

    public static function get_all($plugin_slug)
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ls_plugin_versions 
                 WHERE plugin_slug = %s 
                 ORDER BY released_at DESC",
                sanitize_text_field($plugin_slug)
            )
        );
    }
}
