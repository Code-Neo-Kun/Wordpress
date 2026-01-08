<?php

/**
 * License Manager
 */

namespace LicenseServer\Admin;

use LicenseServer\Models\License;
use LicenseServer\Security\Validation;

class License_Manager
{

    public static function render_page()
    {
        global $wpdb;

        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';

        if ($action === 'edit' && isset($_GET['license_id'])) {
            self::render_edit((int) $_GET['license_id']);
        } elseif ($action === 'new') {
            self::render_new();
        } else {
            self::render_list();
        }
    }

    public static function render_list()
    {
        global $wpdb;

        // Handle bulk actions
        if (isset($_POST['action']) && isset($_POST['license_ids'])) {
            self::handle_bulk_action();
        }

        // Get licenses with pagination
        $per_page = 20;
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $offset = ($paged - 1) * $per_page;

        $licenses = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, u.user_email 
                 FROM {$wpdb->prefix}ls_licenses l 
                 LEFT JOIN {$wpdb->prefix}users u ON l.user_id = u.ID 
                 ORDER BY l.created_at DESC 
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ls_licenses");
        $total_pages = ceil($total / $per_page);

?>
        <div class="wrap">
            <h1>
                <?php echo esc_html('Manage Licenses'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=license-server-licenses&action=new')); ?>" class="page-title-action">
                    <?php echo esc_html('Add New License'); ?>
                </a>
            </h1>

            <?php if (! empty($_GET['message'])) : ?>
                <div class="notice notice-success">
                    <p><?php echo esc_html(sanitize_text_field($_GET['message'])); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" class="ls-license-list">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th width="30"><input type="checkbox" id="select-all"></th>
                            <th><?php echo esc_html('License Key'); ?></th>
                            <th><?php echo esc_html('User'); ?></th>
                            <th><?php echo esc_html('Status'); ?></th>
                            <th><?php echo esc_html('Plan'); ?></th>
                            <th><?php echo esc_html('Domains'); ?></th>
                            <th><?php echo esc_html('Expires'); ?></th>
                            <th><?php echo esc_html('Actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($licenses as $license) : ?>
                            <tr>
                                <td><input type="checkbox" name="license_ids[]" value="<?php echo esc_attr($license->id); ?>"></td>
                                <td><code><?php echo esc_html(substr($license->license_key, 0, 4) . '-****-****-****'); ?></code></td>
                                <td><?php echo esc_html($license->user_email ?? 'N/A'); ?></td>
                                <td><span class="status status-<?php echo esc_attr($license->status); ?>"><?php echo esc_html(ucfirst($license->status)); ?></span></td>
                                <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $license->plan_type))); ?></td>
                                <td><?php echo esc_html($license->active_domains . '/' . $license->max_domains); ?></td>
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

                <div class="ls-bulk-actions">
                    <select name="action">
                        <option value="">Bulk Actions</option>
                        <option value="suspend">Suspend</option>
                        <option value="activate">Activate</option>
                        <option value="cancel">Cancel</option>
                    </select>
                    <input type="submit" class="button" value="Apply">
                </div>
            </form>

            <?php if ($total_pages > 1) : ?>
                <div class="tablenav">
                    <?php
                    echo paginate_links([
                        'base'    => add_query_arg('paged', '%#%'),
                        'format'  => '',
                        'current' => $paged,
                        'total'   => $total_pages,
                    ]);
                    ?>
                </div>
            <?php endif; ?>
        </div>
    <?php
    }

    public static function render_edit($license_id)
    {
        $license = new License($license_id);

        if (! $license->get_id()) {
            wp_die('License not found');
        }

        // Handle form submission
        if (isset($_POST['action']) && wp_verify_nonce($_POST['ls_nonce'] ?? '', 'edit_license')) {
            self::handle_edit_form($license);
        }

        $plugins = $license->get_plugins();
        $domains = $license->get_active_domains();

    ?>
        <div class="wrap">
            <h1><?php echo esc_html('Edit License'); ?></h1>

            <form method="post" class="ls-edit-form">
                <?php wp_nonce_field('edit_license', 'ls_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th>License Key</th>
                        <td>
                            <code><?php echo esc_html($license->get_license_key()); ?></code>
                            <p class="description">Full key shown only here for security</p>
                        </td>
                    </tr>

                    <tr>
                        <th>User</th>
                        <td>
                            <?php
                            $user = get_user_by('id', $license->get_user_id());
                            if ($user) {
                                echo esc_html($user->user_email);
                            }
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th>Status</th>
                        <td>
                            <select name="status">
                                <option value="active" <?php selected($license->get_status(), 'active'); ?>>Active</option>
                                <option value="inactive" <?php selected($license->get_status(), 'inactive'); ?>>Inactive</option>
                                <option value="suspended" <?php selected($license->get_status(), 'suspended'); ?>>Suspended</option>
                                <option value="cancelled" <?php selected($license->get_status(), 'cancelled'); ?>>Cancelled</option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th>Plan Type</th>
                        <td>
                            <select name="plan_type">
                                <option value="single" <?php selected($license->get_plan_type(), 'single'); ?>>Single</option>
                                <option value="bundle" <?php selected($license->get_plan_type(), 'bundle'); ?>>Bundle</option>
                                <option value="lifetime" <?php selected($license->get_plan_type(), 'lifetime'); ?>>Lifetime</option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th>Max Domains</th>
                        <td>
                            <input type="number" name="max_domains" value="<?php echo esc_attr($license->get_max_domains()); ?>" min="1">
                        </td>
                    </tr>

                    <tr>
                        <th>Expires At</th>
                        <td>
                            <input type="datetime-local" name="expires_at" value="<?php echo esc_attr($license->get_expires_at() ? date('Y-m-d\TH:i', strtotime($license->get_expires_at())) : ''); ?>">
                            <p class="description">Leave empty for lifetime license</p>
                        </td>
                    </tr>

                    <tr>
                        <th>Assigned Plugins</th>
                        <td>
                            <div class="ls-plugins-list">
                                <?php foreach ($plugins as $plugin) : ?>
                                    <div class="ls-plugin-item">
                                        <span><?php echo esc_html($plugin); ?></span>
                                        <a href="#" class="remove-plugin" data-plugin="<?php echo esc_attr($plugin); ?>">Remove</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="ls-add-plugin">
                                <input type="text" id="new-plugin-slug" placeholder="Enter plugin slug">
                                <button type="button" class="button" id="add-plugin-btn">Add Plugin</button>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th>Active Domains</th>
                        <td>
                            <ul>
                                <?php foreach ($domains as $domain) : ?>
                                    <li>
                                        <?php echo esc_html($domain->domain); ?>
                                        <small>(<?php echo esc_html($domain->plugin_slug); ?>)</small>
                                        <?php if ($domain->last_check_in) : ?>
                                            - Last check-in: <?php echo esc_html(human_time_diff(strtotime($domain->last_check_in), current_time('timestamp'))) . ' ago'; ?>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                    </tr>
                </table>

                <input type="hidden" name="action" value="update">
                <input type="submit" class="button button-primary" value="Update License">
                <a href="<?php echo esc_url(admin_url('admin.php?page=license-server-licenses')); ?>" class="button">Cancel</a>
            </form>
        </div>
    <?php
    }

    public static function render_new()
    {
    ?>
        <div class="wrap">
            <h1><?php echo esc_html('Create New License'); ?></h1>

            <form method="post" class="ls-new-form">
                <?php wp_nonce_field('new_license', 'ls_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th>User Email</th>
                        <td>
                            <input type="email" name="user_email" required>
                            <p class="description">Email of the license owner</p>
                        </td>
                    </tr>

                    <tr>
                        <th>Plan Type</th>
                        <td>
                            <select name="plan_type">
                                <option value="single">Single</option>
                                <option value="bundle">Bundle</option>
                                <option value="lifetime">Lifetime</option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th>Max Domains</th>
                        <td>
                            <input type="number" name="max_domains" value="1" min="1">
                        </td>
                    </tr>

                    <tr>
                        <th>Validity (Days)</th>
                        <td>
                            <input type="number" name="validity_days" value="365" min="1">
                            <p class="description">Days until license expires (0 for lifetime)</p>
                        </td>
                    </tr>

                    <tr>
                        <th>Initial Plugins</th>
                        <td>
                            <input type="text" name="plugins" placeholder="plugin-slug-1, plugin-slug-2">
                            <p class="description">Comma-separated plugin slugs</p>
                        </td>
                    </tr>
                </table>

                <input type="hidden" name="action" value="create">
                <input type="submit" class="button button-primary" value="Create License">
                <a href="<?php echo esc_url(admin_url('admin.php?page=license-server-licenses')); ?>" class="button">Cancel</a>
            </form>
        </div>
<?php
    }

    public static function handle_edit_form($license)
    {
        $status = sanitize_text_field($_POST['status'] ?? '');
        $plan_type = sanitize_text_field($_POST['plan_type'] ?? '');
        $max_domains = (int) ($_POST['max_domains'] ?? 1);
        $expires_at = isset($_POST['expires_at']) && ! empty($_POST['expires_at'])
            ? date('Y-m-d H:i:s', strtotime($_POST['expires_at']))
            : null;

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'ls_licenses',
            [
                'status'     => $status,
                'plan_type'  => $plan_type,
                'max_domains' => $max_domains,
                'expires_at' => $expires_at,
            ],
            ['id' => $license->get_id()]
        );

        wp_redirect(add_query_arg('message', 'License updated successfully'));
        exit;
    }

    public static function handle_bulk_action()
    {
        $action = sanitize_text_field($_POST['action'] ?? '');
        $license_ids = isset($_POST['license_ids']) ? array_map('intval', $_POST['license_ids']) : [];

        foreach ($license_ids as $license_id) {
            $license = new License($license_id);

            if ('suspend' === $action) {
                $license->suspend('Suspended via admin');
            } elseif ('activate' === $action) {
                // Update status to active
                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'ls_licenses',
                    ['status' => 'active'],
                    ['id' => $license_id]
                );
            }
        }
    }
}
