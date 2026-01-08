<?php

/**
 * User Dashboard (Frontend)
 */

namespace LicenseServer\Frontend;

use LicenseServer\Models\License;

class User_Dashboard
{

    public static function init()
    {
        add_shortcode('license_dashboard', [__CLASS__, 'render_dashboard']);
    }

    public static function render_dashboard()
    {
        if (! is_user_logged_in()) {
            return '<p>' . esc_html('Please log in to view your licenses.') . '</p>';
        }

        $user_id = get_current_user_id();
        global $wpdb;

        // Get user's licenses
        $licenses = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ls_licenses WHERE user_id = %d ORDER BY created_at DESC",
                $user_id
            )
        );

        ob_start();
?>
        <div class="ls-user-dashboard">
            <h2><?php echo esc_html('My Licenses'); ?></h2>

            <?php if (empty($licenses)) : ?>
                <p><?php echo esc_html('You don\'t have any licenses yet.'); ?></p>
            <?php else : ?>
                <div class="ls-licenses-container">
                    <?php foreach ($licenses as $license) : ?>
                        <?php self::render_license_card($license); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php
        return ob_get_clean();
    }

    private static function render_license_card($license_data)
    {
        $license = new License($license_data->id);
        $expires_at = $license->get_expires_at();
        $is_expired = $expires_at && strtotime($expires_at) < current_time('timestamp');
        $days_left = $expires_at ? ceil((strtotime($expires_at) - current_time('timestamp')) / DAY_IN_SECONDS) : null;

    ?>
        <div class="ls-license-card">
            <div class="ls-card-header">
                <h3><?php echo esc_html('License ' . substr($license->get_license_key(), 0, 4) . '-****-****-****'); ?></h3>
                <span class="status status-<?php echo esc_attr($license->get_status()); ?>">
                    <?php echo esc_html(ucfirst($license->get_status())); ?>
                </span>
            </div>

            <div class="ls-card-body">
                <div class="ls-card-row">
                    <span class="label"><?php echo esc_html('Plan Type:'); ?></span>
                    <span class="value"><?php echo esc_html(ucfirst(str_replace('_', ' ', $license->get_plan_type()))); ?></span>
                </div>

                <div class="ls-card-row">
                    <span class="label"><?php echo esc_html('Domain Limit:'); ?></span>
                    <span class="value"><?php echo esc_html($license->get_active_domain_count() . '/' . $license->get_max_domains()); ?></span>
                </div>

                <?php if ($expires_at) : ?>
                    <div class="ls-card-row <?php echo $is_expired ? 'expired' : ($days_left <= 30 ? 'expiring-soon' : ''); ?>">
                        <span class="label"><?php echo esc_html('Expires:'); ?></span>
                        <span class="value">
                            <?php echo esc_html(date_i18n('M d, Y', strtotime($expires_at))); ?>
                            <?php if ($days_left) : ?>
                                <small>(<?php echo esc_html($days_left . ' days left'); ?>)</small>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php else : ?>
                    <div class="ls-card-row">
                        <span class="label"><?php echo esc_html('Validity:'); ?></span>
                        <span class="value"><?php echo esc_html('Lifetime'); ?></span>
                    </div>
                <?php endif; ?>

                <div class="ls-card-row">
                    <span class="label"><?php echo esc_html('Assigned Plugins:'); ?></span>
                    <div class="value">
                        <ul class="ls-plugins-list">
                            <?php foreach ($license->get_plugins() as $plugin) : ?>
                                <li><?php echo esc_html($plugin); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <div class="ls-card-row">
                    <span class="label"><?php echo esc_html('Active Domains:'); ?></span>
                    <div class="value">
                        <?php if ($license->get_active_domains()) : ?>
                            <ul class="ls-domains-list">
                                <?php foreach ($license->get_active_domains() as $domain) : ?>
                                    <li>
                                        <?php echo esc_html($domain->domain); ?>
                                        <small>(<?php echo esc_html($domain->plugin_slug); ?>)</small>
                                        <a href="#" class="deactivate-domain" data-domain="<?php echo esc_attr($domain->domain); ?>" data-plugin="<?php echo esc_attr($domain->plugin_slug); ?>" data-license-id="<?php echo esc_attr($license->get_id()); ?>">Remove</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p><?php echo esc_html('No active domains'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="ls-card-actions">
                <a href="#" class="button button-small copy-license-key" data-key="<?php echo esc_attr($license->get_license_key()); ?>">
                    <?php echo esc_html('Copy License Key'); ?>
                </a>

                <?php if ($is_expired) : ?>
                    <a href="#" class="button button-primary button-small renew-license" data-license-id="<?php echo esc_attr($license->get_id()); ?>">
                        <?php echo esc_html('Renew License'); ?>
                    </a>
                <?php endif; ?>

                <a href="#" class="button button-small view-details" data-license-id="<?php echo esc_attr($license->get_id()); ?>">
                    <?php echo esc_html('More Details'); ?>
                </a>
            </div>
        </div>
<?php
    }
}

// Initialize on wp_loaded
add_action('wp_loaded', ['LicenseServer\Frontend\User_Dashboard', 'init']);
