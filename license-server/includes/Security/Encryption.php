<?php

/**
 * Encryption Utilities
 */

namespace LicenseServer\Security;

class Encryption
{

    /**
     * Hash a license key
     */
    public static function hash_license_key($key)
    {
        return hash(LS_HASH_ALGO, $key . get_option('siteurl'));
    }

    /**
     * Generate a random license key
     */
    public static function generate_license_key($length = 32)
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $key = '';

        for ($i = 0; $i < $length; $i++) {
            $key .= $characters[wp_rand(0, strlen($characters) - 1)];
        }

        // Format as XXXX-XXXX-XXXX-XXXX
        return substr($key, 0, 4) . '-' .
            substr($key, 4, 4) . '-' .
            substr($key, 8, 4) . '-' .
            substr($key, 12, 4);
    }

    /**
     * Encrypt data
     */
    public static function encrypt($data)
    {
        $key = wp_salt('auth');
        $data = wp_json_encode($data);
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt data
     */
    public static function decrypt($data)
    {
        try {
            $key = wp_salt('auth');
            $data = base64_decode($data);
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
            return json_decode($decrypted, true);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Generate API token
     */
    public static function generate_api_token()
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Generate activation token
     */
    public static function generate_activation_token()
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Generate UUID v4
     */
    public static function generate_uuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            wp_rand(0, 0xffff),
            wp_rand(0, 0xffff),
            wp_rand(0, 0xffff),
            wp_rand(0, 0x0fff) | 0x4000,
            wp_rand(0, 0x3fff) | 0x8000,
            wp_rand(0, 0xffff),
            wp_rand(0, 0xffff),
            wp_rand(0, 0xffff)
        );
    }
}
