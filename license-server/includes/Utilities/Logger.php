<?php

/**
 * Logger Utility
 */

namespace LicenseServer\Utilities;

class Logger
{

    private static $log_file = null;

    public static function init()
    {
        self::$log_file = LS_PLUGIN_DIR . 'logs/';
        if (! is_dir(self::$log_file)) {
            wp_mkdir_p(self::$log_file);
        }
    }

    public static function log($message, $type = 'info', $context = [])
    {
        self::init();

        $log_file = self::$log_file . gmdate('Y-m-d') . '.log';
        $timestamp = gmdate('Y-m-d H:i:s');
        $level = strtoupper($type);

        $log_message = sprintf(
            "[%s] [%s] %s",
            $timestamp,
            $level,
            $message
        );

        if (! empty($context)) {
            $log_message .= ' ' . wp_json_encode($context);
        }

        error_log($log_message . "\n", 3, $log_file);
    }

    public static function info($message, $context = [])
    {
        self::log($message, 'info', $context);
    }

    public static function error($message, $context = [])
    {
        self::log($message, 'error', $context);
    }

    public static function warning($message, $context = [])
    {
        self::log($message, 'warning', $context);
    }

    public static function debug($message, $context = [])
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::log($message, 'debug', $context);
        }
    }
}
