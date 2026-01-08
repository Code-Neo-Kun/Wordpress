<?php

/**
 * Validation Utilities
 */

namespace LicenseServer\Security;

class Validation
{

    /**
     * Validate license key format
     */
    public static function is_valid_license_key($key)
    {
        return ! empty($key) &&
            preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $key);
    }

    /**
     * Validate domain name
     */
    public static function is_valid_domain($domain)
    {
        // Remove http/https
        $domain = preg_replace('#^https?://#', '', $domain);

        // Extract host from URL (remove path and query)
        $domain = parse_url($domain, PHP_URL_HOST) ?: $domain;

        return ! empty($domain) &&
            filter_var('http://' . $domain, FILTER_VALIDATE_URL);
    }

    /**
     * Validate plugin slug
     */
    public static function is_valid_plugin_slug($slug)
    {
        return ! empty($slug) &&
            preg_match('/^[a-z0-9\-]+$/', $slug) &&
            strlen($slug) <= 255;
    }

    /**
     * Validate version format
     */
    public static function is_valid_version($version)
    {
        return preg_match('/^\d+\.\d+(\.\d+)?(-[a-z0-9]+)?$/i', $version);
    }

    /**
     * Validate email
     */
    public static function is_valid_email($email)
    {
        return is_email($email);
    }

    /**
     * Sanitize domain
     */
    public static function sanitize_domain($domain)
    {
        $domain = trim($domain);
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#/$#', '', $domain);
        $domain = parse_url('http://' . $domain, PHP_URL_HOST) ?: $domain;
        return strtolower($domain);
    }

    /**
     * Sanitize plugin slug
     */
    public static function sanitize_plugin_slug($slug)
    {
        return sanitize_title($slug);
    }

    /**
     * Verify license key matches hash
     */
    public static function verify_license_key_hash($key, $hash)
    {
        return hash_equals(Encryption::hash_license_key($key), $hash);
    }
}
