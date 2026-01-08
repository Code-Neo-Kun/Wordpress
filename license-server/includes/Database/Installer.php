<?php

/**
 * Database Installer
 */

namespace LicenseServer\Database;

class Installer
{

    public static function install()
    {
        global $wpdb;

        $wpdb->show_errors();

        // Get all table definitions
        $tables = Schema::get_tables();

        foreach ($tables as $table_name => $sql) {
            $sql = str_replace('{PREFIX}', $wpdb->prefix . 'ls_', $sql);
            $wpdb->query($sql);
        }

        // Update DB version
        update_option('license_server_db_version', LS_DB_VERSION);
    }

    public static function check_and_update()
    {
        $db_version = (int) get_option('license_server_db_version', 0);

        if ($db_version < LS_DB_VERSION) {
            self::install();
        }
    }
}

// Hook for automatic update checking
add_action('admin_init', ['\LicenseServer\Database\Installer', 'check_and_update']);
