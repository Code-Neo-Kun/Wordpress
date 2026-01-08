<?php

/**
 * Admin Dashboard
 */

namespace LicenseServer\Admin;

use LicenseServer\Models\License;

class Admin_Dashboard
{

    public static function render()
    {
        global $wpdb;

        // Get statistics
        $total_licenses = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ls_licenses"
        );

        $active_licenses = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ls_licenses WHERE status = 'active'"
        );

        $total_users = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}ls_licenses"
        );

        $total_activations = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ls_activated_domains WHERE deactivated_at IS NULL"
        );

        $expiring_soon = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ls_licenses 
                 WHERE status = 'active' 
                 AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)"
            )
        );

        // Recent licenses
        $recent = $wpdb->get_results(
            "SELECT l.*, u.user_email 
             FROM {$wpdb->prefix}ls_licenses l 
             LEFT JOIN {$wpdb->prefix}users u ON l.user_id = u.ID 
             ORDER BY l.created_at DESC 
             LIMIT 5"
        );

?>
        <div class="wrap">
            <h1><?php echo esc_html('License Server Dashboard'); ?></h1>

            <div class="ls-stats-grid">
                <div class="ls-stat-card">
                    <h3><?php echo esc_html($total_licenses); ?></h3>
                    <p><?php echo esc_html('Total Licenses'); ?></p>
                </div>

                <div class="ls-stat-card">
                    <h3><?php echo esc_html($active_licenses); ?></h3>
                    <p><?php echo esc_html('Active Licenses'); ?></p>
                </div>

                <div class="ls-stat-card">
                    <h3><?php echo esc_html($total_activations); ?></h3>
                    <p><?php echo esc_html('Active Domains'); ?></p>
                </div>

                <div class="ls-stat-card alert">
                    <h3><?php echo esc_html($expiring_soon); ?></h3>
                    <p><?php echo esc_html('Expiring Soon (30d)'); ?></p>
                </div>
            </div>

            <h2><?php echo esc_html('Recent Licenses'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html('License Key'); ?></th>
                        <th><?php echo esc_html('User'); ?></th>
                        <th><?php echo esc_html('Status'); ?></th>
                        <th><?php echo esc_html('Plan'); ?></th>
                        <th><?php echo esc_html('Expires'); ?></th>
                        <th><?php echo esc_html('Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $license) : ?>
                        <tr>
                            <td><code><?php echo esc_html(substr($license->license_key, 0, 4) . '-****-****-****'); ?></code></td>
                            <td><?php echo esc_html($license->user_email ?? 'N/A'); ?></td>
                            <td>
                                <span class="status status-<?php echo esc_attr($license->status); ?>">
                                    <?php echo esc_html(ucfirst($license->status)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $license->plan_type))); ?></td>
                            <td><?php echo esc_html($license->expires_at ? date_i18n('M d, Y', strtotime($license->expires_at)) : 'Lifetime'); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=license-server-licenses&action=edit&license_id=' . $license->id)); ?>" class="button button-small">
                                    <?php echo esc_html('Edit'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="ls-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=license-server-licenses')); ?>" class="button button-primary">
                    <?php echo esc_html('Manage All Licenses'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=license-server-upload')); ?>" class="button button-secondary">
                    <?php echo esc_html('Upload Plugin Versions'); ?>
                </a>
            </div>
        </div>
<?php
    }
}
