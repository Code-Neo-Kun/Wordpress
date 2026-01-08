<?php

/**
 * Database Schema Definition
 */

namespace LicenseServer\Database;

class Schema
{

    public static function get_tables()
    {
        return [
            'licenses'              => self::get_licenses_table(),
            'license_plugins'       => self::get_license_plugins_table(),
            'activated_domains'     => self::get_activated_domains_table(),
            'plugin_versions'       => self::get_plugin_versions_table(),
            'activation_logs'       => self::get_activation_logs_table(),
            'license_history'       => self::get_license_history_table(),
        ];
    }

    public static function get_licenses_table()
    {
        return "
            CREATE TABLE IF NOT EXISTS `{PREFIX}licenses` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `license_key` VARCHAR(64) NOT NULL UNIQUE,
                `license_key_hash` VARCHAR(64) NOT NULL UNIQUE,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `status` ENUM('active', 'inactive', 'suspended', 'expired', 'cancelled') NOT NULL DEFAULT 'active',
                `plan_type` ENUM('single', 'bundle', 'lifetime') NOT NULL DEFAULT 'single',
                `max_domains` INT UNSIGNED NOT NULL DEFAULT 1,
                `active_domains` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                `expires_at` DATETIME NULL,
                `suspended_at` DATETIME NULL,
                `suspension_reason` VARCHAR(255) NULL,
                `renewal_reminder_sent` TINYINT(1) NOT NULL DEFAULT 0,
                `meta` LONGTEXT NULL,
                `created_ip` VARCHAR(45) NULL,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_key` (`license_key_hash`),
                KEY `user_id` (`user_id`),
                KEY `status` (`status`),
                KEY `expires_at` (`expires_at`),
                KEY `created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
    }

    public static function get_license_plugins_table()
    {
        return "
            CREATE TABLE IF NOT EXISTS `{PREFIX}license_plugins` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `license_id` BIGINT UNSIGNED NOT NULL,
                `plugin_slug` VARCHAR(255) NOT NULL,
                `enabled_features` LONGTEXT NULL,
                `assigned_at` DATETIME NOT NULL,
                `removed_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_assignment` (`license_id`, `plugin_slug`),
                KEY `plugin_slug` (`plugin_slug`),
                FOREIGN KEY (`license_id`) REFERENCES `{PREFIX}licenses` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
    }

    public static function get_activated_domains_table()
    {
        return "
            CREATE TABLE IF NOT EXISTS `{PREFIX}activated_domains` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `license_id` BIGINT UNSIGNED NOT NULL,
                `plugin_slug` VARCHAR(255) NOT NULL,
                `domain` VARCHAR(255) NOT NULL,
                `ip_address` VARCHAR(45) NULL,
                `site_url` VARCHAR(2048) NULL,
                `activated_at` DATETIME NOT NULL,
                `last_check_in` DATETIME NULL,
                `deactivated_at` DATETIME NULL,
                `activation_token` VARCHAR(64) NULL,
                `install_uuid` VARCHAR(36) NULL,
                `meta` LONGTEXT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_activation` (`license_id`, `plugin_slug`, `domain`),
                KEY `license_id` (`license_id`),
                KEY `plugin_slug` (`plugin_slug`),
                KEY `domain` (`domain`),
                KEY `last_check_in` (`last_check_in`),
                FOREIGN KEY (`license_id`) REFERENCES `{PREFIX}licenses` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
    }

    public static function get_plugin_versions_table()
    {
        return "
            CREATE TABLE IF NOT EXISTS `{PREFIX}plugin_versions` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `plugin_slug` VARCHAR(255) NOT NULL,
                `version` VARCHAR(20) NOT NULL,
                `download_url` VARCHAR(2048) NOT NULL,
                `file_hash` VARCHAR(64) NULL,
                `file_size` BIGINT UNSIGNED NULL,
                `changelog` LONGTEXT NULL,
                `requires_php` VARCHAR(10) NULL,
                `requires_wp` VARCHAR(10) NULL,
                `tested_up_to` VARCHAR(10) NULL,
                `released_at` DATETIME NOT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_version` (`plugin_slug`, `version`),
                KEY `plugin_slug` (`plugin_slug`),
                KEY `released_at` (`released_at`),
                KEY `version` (`version`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
    }

    public static function get_activation_logs_table()
    {
        return "
            CREATE TABLE IF NOT EXISTS `{PREFIX}activation_logs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `license_id` BIGINT UNSIGNED NOT NULL,
                `plugin_slug` VARCHAR(255) NOT NULL,
                `domain` VARCHAR(255) NOT NULL,
                `action` ENUM('activate', 'deactivate', 'check_in', 'update_check') NOT NULL,
                `status` ENUM('success', 'failed', 'pending') NOT NULL DEFAULT 'success',
                `reason` VARCHAR(255) NULL,
                `ip_address` VARCHAR(45) NULL,
                `user_agent` VARCHAR(500) NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `license_id` (`license_id`),
                KEY `domain` (`domain`),
                KEY `action` (`action`),
                KEY `created_at` (`created_at`),
                FOREIGN KEY (`license_id`) REFERENCES `{PREFIX}licenses` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
    }

    public static function get_license_history_table()
    {
        return "
            CREATE TABLE IF NOT EXISTS `{PREFIX}license_history` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `license_id` BIGINT UNSIGNED NOT NULL,
                `change_type` ENUM('created', 'activated', 'suspended', 'expired', 'renewed', 'upgraded', 'downgraded', 'cancelled', 'refunded') NOT NULL,
                `old_value` LONGTEXT NULL,
                `new_value` LONGTEXT NULL,
                `reason` VARCHAR(255) NULL,
                `changed_by` BIGINT UNSIGNED NULL,
                `ip_address` VARCHAR(45) NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `license_id` (`license_id`),
                KEY `change_type` (`change_type`),
                KEY `created_at` (`created_at`),
                FOREIGN KEY (`license_id`) REFERENCES `{PREFIX}licenses` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
    }
}
