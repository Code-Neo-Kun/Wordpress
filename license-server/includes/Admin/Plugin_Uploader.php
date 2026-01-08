<?php

/**
 * Plugin Uploader
 */

namespace LicenseServer\Admin;

use LicenseServer\Models\PluginVersion;

class Plugin_Uploader
{

    public static function render_page()
    {
        global $wpdb;

        // Handle file upload
        if (isset($_FILES['plugin_file']) && wp_verify_nonce($_POST['ls_nonce'] ?? '', 'upload_plugin')) {
            self::handle_upload();
        }

        // Get all plugins
        $plugins = $wpdb->get_results(
            "SELECT DISTINCT plugin_slug FROM {$wpdb->prefix}ls_plugin_versions ORDER BY plugin_slug"
        );

?>
        <div class="wrap">
            <h1><?php echo esc_html('Upload Plugin Versions'); ?></h1>

            <?php if (isset($_GET['success'])) : ?>
                <div class="notice notice-success">
                    <p><?php echo esc_html('Plugin version uploaded successfully'); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="ls-upload-form">
                <?php wp_nonce_field('upload_plugin', 'ls_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th>Plugin Slug</th>
                        <td>
                            <input type="text" name="plugin_slug" placeholder="e.g., my-awesome-plugin" required>
                            <p class="description">Unique identifier for the plugin</p>
                        </td>
                    </tr>

                    <tr>
                        <th>Version</th>
                        <td>
                            <input type="text" name="version" placeholder="1.0.0" pattern="\d+\.\d+(\.\d+)?" required>
                            <p class="description">Semantic versioning (e.g., 1.0.0)</p>
                        </td>
                    </tr>

                    <tr>
                        <th>Plugin File (.zip)</th>
                        <td>
                            <input type="file" name="plugin_file" accept=".zip" required>
                            <p class="description">Upload the plugin zip file</p>
                        </td>
                    </tr>

                    <tr>
                        <th>Download URL</th>
                        <td>
                            <input type="url" name="download_url" placeholder="https://..." required>
                            <p class="description">Permanent URL to download this version (users will be redirected here)</p>
                        </td>
                    </tr>

                    <tr>
                        <th>Changelog</th>
                        <td>
                            <textarea name="changelog" rows="5" placeholder="List of changes..."></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th>Requires PHP</th>
                        <td>
                            <input type="text" name="requires_php" placeholder="7.4.0">
                            <p class="description">Minimum PHP version (optional)</p>
                        </td>
                    </tr>

                    <tr>
                        <th>Requires WordPress</th>
                        <td>
                            <input type="text" name="requires_wp" placeholder="5.0">
                            <p class="description">Minimum WordPress version (optional)</p>
                        </td>
                    </tr>

                    <tr>
                        <th>Tested Up To</th>
                        <td>
                            <input type="text" name="tested_up_to" placeholder="6.4">
                            <p class="description">Latest WordPress version tested (optional)</p>
                        </td>
                    </tr>
                </table>

                <input type="submit" class="button button-primary" value="Upload Version">
            </form>

            <h2><?php echo esc_html('Plugin Versions'); ?></h2>

            <?php if (! empty($plugins)) : ?>
                <div class="ls-plugins-accordion">
                    <?php foreach ($plugins as $plugin) : ?>
                        <?php
                        $versions = PluginVersion::get_all($plugin->plugin_slug);
                        ?>
                        <div class="ls-plugin-section">
                            <h3><?php echo esc_html($plugin->plugin_slug); ?>
                                <span class="count">(<?php echo count($versions); ?> versions)</span>
                            </h3>

                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th>Version</th>
                                        <th>Released</th>
                                        <th>Requires PHP</th>
                                        <th>Requires WP</th>
                                        <th>File Size</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($versions as $version) : ?>
                                        <tr>
                                            <td><code><?php echo esc_html($version->version); ?></code></td>
                                            <td><?php echo esc_html(date_i18n('M d, Y', strtotime($version->released_at))); ?></td>
                                            <td><?php echo esc_html($version->requires_php ?: '-'); ?></td>
                                            <td><?php echo esc_html($version->requires_wp ?: '-'); ?></td>
                                            <td><?php echo esc_html(self::format_bytes($version->file_size)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p><?php echo esc_html('No plugin versions uploaded yet.'); ?></p>
            <?php endif; ?>
        </div>
<?php
    }

    private static function handle_upload()
    {
        $plugin_slug = sanitize_text_field($_POST['plugin_slug'] ?? '');
        $version = sanitize_text_field($_POST['version'] ?? '');
        $download_url = esc_url_raw($_POST['download_url'] ?? '');
        $changelog = wp_kses_post($_POST['changelog'] ?? '');

        // Validate inputs
        if (empty($plugin_slug) || empty($version) || empty($download_url)) {
            wp_die('Missing required fields');
        }

        // Create plugin version
        $result = PluginVersion::create([
            'plugin_slug'   => $plugin_slug,
            'version'       => $version,
            'download_url'  => $download_url,
            'changelog'     => $changelog,
            'requires_php'  => $_POST['requires_php'] ?? '',
            'requires_wp'   => $_POST['requires_wp'] ?? '',
            'tested_up_to'  => $_POST['tested_up_to'] ?? '',
        ]);

        if ($result) {
            wp_redirect(add_query_arg('success', '1'));
        } else {
            wp_die('Failed to upload version');
        }
    }

    private static function format_bytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
