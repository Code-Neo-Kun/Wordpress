<?php

/**
 * Rate Limiter
 */

namespace LicenseServer\Security;

class RateLimiter
{

    /**
     * Check if action is rate limited
     */
    public static function is_limited($identifier, $action, $limit = 10, $window = 3600)
    {
        $cache_key = 'ls_rate_limit_' . sanitize_key($action) . '_' . sanitize_key($identifier);
        $count = (int) wp_cache_get($cache_key);

        if ($count >= $limit) {
            return true;
        }

        return false;
    }

    /**
     * Increment action counter
     */
    public static function increment($identifier, $action, $window = 3600)
    {
        $cache_key = 'ls_rate_limit_' . sanitize_key($action) . '_' . sanitize_key($identifier);
        $count = (int) wp_cache_get($cache_key);
        wp_cache_set($cache_key, $count + 1, '', $window);
    }

    /**
     * Get remaining attempts
     */
    public static function get_remaining($identifier, $action, $limit = 10)
    {
        $cache_key = 'ls_rate_limit_' . sanitize_key($action) . '_' . sanitize_key($identifier);
        $count = (int) wp_cache_get($cache_key);
        return max(0, $limit - $count);
    }

    /**
     * Get client identifier (IP)
     */
    public static function get_client_id()
    {
        // Check for shared internet
        if (! empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return sanitize_text_field(trim($ip));
    }
}
