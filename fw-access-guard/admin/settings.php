<?php
if (!defined('ABSPATH')) {
    exit;
}

class FWAG_Admin_Settings
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_settings_page()
    {
        add_options_page(
            __('FW Access Guard', 'fw-access-guard'),
            __('FW Access Guard', 'fw-access-guard'),
            'manage_options',
            'fw-access-guard',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings()
    {
        register_setting('fwag_settings', 'fwag_allowed_roles', array(
            'type' => 'array',
            'default' => array(),
            'sanitize_callback' => array($this, 'sanitize_roles')
        ));

        register_setting('fwag_settings', 'fwag_protected_pages', array(
            'type' => 'array',
            'default' => array(),
            'sanitize_callback' => array($this, 'sanitize_ids')
        ));

        register_setting('fwag_settings', 'fwag_protected_post_types', array(
            'type' => 'array',
            'default' => array(),
            'sanitize_callback' => array($this, 'sanitize_post_types')
        ));

        register_setting('fwag_settings', 'fwag_url_patterns', array(
            'type' => 'array',
            'default' => array(),
            'sanitize_callback' => array($this, 'sanitize_patterns')
        ));

        register_setting('fwag_settings', 'fwag_overlay_title', array(
            'type' => 'string',
            'default' => __('Access Restricted', 'fw-access-guard'),
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_setting('fwag_settings', 'fwag_overlay_message', array(
            'type' => 'string',
            'default' => __('This content is restricted. Please log in to access.', 'fw-access-guard'),
            'sanitize_callback' => 'sanitize_textarea_field'
        ));

        register_setting('fwag_settings', 'fwag_overlay_message_unauthorized', array(
            'type' => 'string',
            'default' => __('You do not have permission to access this content.', 'fw-access-guard'),
            'sanitize_callback' => 'sanitize_textarea_field'
        ));

        register_setting('fwag_settings', 'fwag_button_label', array(
            'type' => 'string',
            'default' => __('Log In', 'fw-access-guard'),
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_setting('fwag_settings', 'fwag_blur_level', array(
            'type' => 'integer',
            'default' => 5,
            'sanitize_callback' => 'absint'
        ));

        register_setting('fwag_settings', 'fwag_overlay_opacity', array(
            'type' => 'number',
            'default' => 0.95,
            'sanitize_callback' => array($this, 'sanitize_opacity')
        ));

        register_setting('fwag_settings', 'fwag_logo', array(
            'type' => 'integer',
            'default' => 0,
            'sanitize_callback' => 'absint'
        ));

        register_setting('fwag_settings', 'fwag_redirect_enabled', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ));

        register_setting('fwag_settings', 'fwag_redirect_url', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'esc_url_raw'
        ));

        register_setting('fwag_settings', 'fwag_redirect_type', array(
            'type' => 'integer',
            'default' => 302,
            'sanitize_callback' => array($this, 'sanitize_redirect_type')
        ));

        // New feature settings
        register_setting('fwag_settings', 'fwag_enable_content_teaser', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ));

        register_setting('fwag_settings', 'fwag_enable_time_restrictions', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ));

        register_setting('fwag_settings', 'fwag_enable_user_specific_access', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ));

        register_setting('fwag_settings', 'fwag_enable_access_logging', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ));

        register_setting('fwag_settings', 'fwag_enable_file_protection', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ));

        register_setting('fwag_settings', 'fwag_log_retention_days', array(
            'type' => 'integer',
            'default' => 30,
            'sanitize_callback' => 'absint'
        ));
    }

    public function sanitize_roles($input)
    {
        if (!is_array($input)) {
            return array();
        }
        return array_map('sanitize_key', $input);
    }

    public function sanitize_ids($input)
    {
        if (!is_array($input)) {
            return array();
        }
        return array_map('absint', $input);
    }

    public function sanitize_post_types($input)
    {
        if (!is_array($input)) {
            return array();
        }
        return array_map('sanitize_key', $input);
    }

    public function sanitize_patterns($input)
    {
        if (!is_array($input)) {
            return array();
        }
        return array_map('sanitize_text_field', $input);
    }

    public function sanitize_opacity($input)
    {
        $value = floatval($input);
        if ($value < 0) {
            return 0;
        }
        if ($value > 1) {
            return 1;
        }
        return $value;
    }

    public function sanitize_redirect_type($input)
    {
        $allowed = array(301, 302);
        $value = absint($input);
        return in_array($value, $allowed) ? $value : 302;
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['settings-updated'])) {
            add_settings_error('fwag_messages', 'fwag_message', __('Settings Saved', 'fw-access-guard'), 'updated');
        }

        settings_errors('fwag_messages');
?>
        <div class="wrap fwag-admin-wrap">
            <div class="fwag-admin-header">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <p><?php esc_html_e('Configure role-based content access control with customizable overlays and redirects.', 'fw-access-guard'); ?></p>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('fwag_settings'); ?>

                <div class="fwag-settings-section">
                    <div class="fwag-section-header">
                        <h2><?php esc_html_e('Access Control', 'fw-access-guard'); ?></h2>
                    </div>
                    <div class="fwag-section-content">
                        <?php $this->render_access_control_section(); ?>
                    </div>
                </div>

                <div class="fwag-settings-section">
                    <div class="fwag-section-header">
                        <h2><?php esc_html_e('Overlay Customization', 'fw-access-guard'); ?></h2>
                    </div>
                    <div class="fwag-section-content">
                        <?php $this->render_overlay_customization_section(); ?>
                    </div>
                </div>

                <div class="fwag-settings-section">
                    <div class="fwag-section-header">
                        <h2><?php esc_html_e('Redirect Settings', 'fw-access-guard'); ?></h2>
                    </div>
                    <div class="fwag-section-content">
                        <?php $this->render_redirect_section(); ?>
                    </div>
                </div>

                <div class="fwag-settings-section">
                    <div class="fwag-section-header">
                        <h2><?php esc_html_e('Advanced Features', 'fw-access-guard'); ?></h2>
                    </div>
                    <div class="fwag-section-content">
                        <?php $this->render_advanced_features_section(); ?>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 30px;">
                    <?php submit_button(__('Save Settings', 'fw-access-guard'), 'primary fwag-submit-button', 'submit', false); ?>
                </div>
            </form>
        </div>
    <?php
    }

    private function render_access_control_section()
    {
        $allowed_roles = get_option('fwag_allowed_roles', array());
        $protected_pages = get_option('fwag_protected_pages', array());
        $protected_post_types = get_option('fwag_protected_post_types', array());
        $url_patterns = get_option('fwag_url_patterns', array());
        $roles = wp_roles()->roles;
    ?>
        <div class="fwag-form-group">
            <label><?php esc_html_e('Allowed Roles', 'fw-access-guard'); ?></label>
            <p class="description"><?php esc_html_e('Select which user roles have access to protected content.', 'fw-access-guard'); ?></p>
            <div class="fwag-checkbox-group">
                <?php foreach ($roles as $role_key => $role) : ?>
                    <div class="fwag-checkbox-item">
                        <input type="checkbox" name="fwag_allowed_roles[]" value="<?php echo esc_attr($role_key); ?>" <?php checked(in_array($role_key, $allowed_roles)); ?>>
                        <span><?php echo esc_html($role['name']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="fwag-form-group">
            <label for="fwag_protected_pages"><?php esc_html_e('Protected Pages (IDs)', 'fw-access-guard'); ?></label>
            <p class="description"><?php esc_html_e('Enter specific page IDs to protect, separated by commas.', 'fw-access-guard'); ?></p>
            <input type="text" id="fwag_protected_pages" name="fwag_protected_pages" value="<?php echo esc_attr(implode(',', $protected_pages)); ?>" class="fwag-input regular-text">
        </div>

        <div class="fwag-form-group">
            <label><?php esc_html_e('Protected Post Types', 'fw-access-guard'); ?></label>
            <p class="description"><?php esc_html_e('Select which post types should be protected by default.', 'fw-access-guard'); ?></p>
            <div class="fwag-checkbox-group">
                <?php
                $post_types = get_post_types(array('public' => true), 'objects');
                foreach ($post_types as $post_type) :
                ?>
                    <div class="fwag-checkbox-item">
                        <input type="checkbox" name="fwag_protected_post_types[]" value="<?php echo esc_attr($post_type->name); ?>" <?php checked(in_array($post_type->name, $protected_post_types)); ?>>
                        <span><?php echo esc_html($post_type->label); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="fwag-form-group">
            <label for="fwag_url_patterns"><?php esc_html_e('URL Patterns', 'fw-access-guard'); ?></label>
            <p class="description"><?php esc_html_e('Enter URL patterns to protect (one per line). Use wildcards like /members/* or /private/*.', 'fw-access-guard'); ?></p>
            <textarea id="fwag_url_patterns" name="fwag_url_patterns" rows="5" class="fwag-textarea"><?php echo esc_textarea(implode("\n", $url_patterns)); ?></textarea>
        </div>
    <?php
    }

    private function render_overlay_customization_section()
    {
        $overlay_title = get_option('fwag_overlay_title', __('Access Restricted', 'fw-access-guard'));
        $overlay_message = get_option('fwag_overlay_message', __('This content is restricted. Please log in to access.', 'fw-access-guard'));
        $overlay_message_unauthorized = get_option('fwag_overlay_message_unauthorized', __('You do not have permission to access this content.', 'fw-access-guard'));
        $button_label = get_option('fwag_button_label', __('Log In', 'fw-access-guard'));
        $blur_level = get_option('fwag_blur_level', 5);
        $overlay_opacity = get_option('fwag_overlay_opacity', 0.95);
        $logo_id = get_option('fwag_logo', 0);
        $logo_url = $logo_id ? wp_get_attachment_url($logo_id) : '';
    ?>
        <h2><?php esc_html_e('Overlay Customization', 'fw-access-guard'); ?></h2>
        <div class="fwag-form-group">
            <label for="fwag_overlay_title"><?php esc_html_e('Overlay Title', 'fw-access-guard'); ?></label>
            <p class="description"><?php esc_html_e('The main heading displayed in the access restriction overlay.', 'fw-access-guard'); ?></p>
            <input type="text" id="fwag_overlay_title" name="fwag_overlay_title" value="<?php echo esc_attr($overlay_title); ?>" class="fwag-input regular-text">
        </div>

        <div class="fwag-form-group">
            <label for="fwag_overlay_message"><?php esc_html_e('Message (Not Logged In)', 'fw-access-guard'); ?></label>
            <p class="description"><?php esc_html_e('Message shown to users who are not logged in.', 'fw-access-guard'); ?></p>
            <textarea id="fwag_overlay_message" name="fwag_overlay_message" rows="3" class="fwag-textarea"><?php echo esc_textarea($overlay_message); ?></textarea>
        </div>

        <div class="fwag-form-group">
            <label for="fwag_overlay_message_unauthorized"><?php esc_html_e('Message (Unauthorized)', 'fw-access-guard'); ?></label>
            <p class="description"><?php esc_html_e('Message shown to logged-in users without access permissions.', 'fw-access-guard'); ?></p>
            <textarea id="fwag_overlay_message_unauthorized" name="fwag_overlay_message_unauthorized" rows="3" class="fwag-textarea"><?php echo esc_textarea($overlay_message_unauthorized); ?></textarea>
        </div>

        <div class="fwag-form-group">
            <label for="fwag_button_label"><?php esc_html_e('Button Label', 'fw-access-guard'); ?></label>
            <p class="description"><?php esc_html_e('Text displayed on the login button.', 'fw-access-guard'); ?></p>
            <input type="text" id="fwag_button_label" name="fwag_button_label" value="<?php echo esc_attr($button_label); ?>" class="fwag-input regular-text">
        </div>

        <div class="fwag-form-group">
            <label><?php esc_html_e('Visual Effects', 'fw-access-guard'); ?></label>
            <p class="description"><?php esc_html_e('Customize the appearance of the restriction overlay.', 'fw-access-guard'); ?></p>
            <div class="fwag-input-group">
                <div>
                    <label for="fwag_blur_level" style="display: block; font-weight: 500; margin-bottom: 5px;"><?php esc_html_e('Background Blur', 'fw-access-guard'); ?></label>
                    <input type="number" id="fwag_blur_level" name="fwag_blur_level" value="<?php echo esc_attr($blur_level); ?>" min="0" max="20" step="1" class="fwag-input fwag-number-input">
                    <span class="description"><?php esc_html_e('pixels', 'fw-access-guard'); ?></span>
                </div>
                <div>
                    <label for="fwag_overlay_opacity" style="display: block; font-weight: 500; margin-bottom: 5px;"><?php esc_html_e('Overlay Opacity', 'fw-access-guard'); ?></label>
                    <input type="number" id="fwag_overlay_opacity" name="fwag_overlay_opacity" value="<?php echo esc_attr($overlay_opacity); ?>" min="0" max="1" step="0.05" class="fwag-input fwag-number-input">
                    <span class="description"><?php esc_html_e('0.0 - 1.0', 'fw-access-guard'); ?></span>
                </div>
            </div>
        </div>

        <div class="fwag-preview-container">
            <div class="fwag-preview-settings">
                <span class="fwag-preview-label"><?php esc_html_e('Settings', 'fw-access-guard'); ?></span>
                <div class="fwag-form-group">
                    <label><?php esc_html_e('Title', 'fw-access-guard'); ?></label>
                    <input type="text" id="fwag_overlay_title_preview" placeholder="<?php esc_attr_e('Access Restricted', 'fw-access-guard'); ?>" class="fwag-input">
                </div>
                <div class="fwag-form-group">
                    <label><?php esc_html_e('Message', 'fw-access-guard'); ?></label>
                    <textarea placeholder="<?php esc_attr_e('This content is restricted', 'fw-access-guard'); ?>" class="fwag-input"></textarea>
                </div>
            </div>
            <div class="fwag-preview-panel">
                <span class="fwag-preview-label"><?php esc_html_e('Live Preview', 'fw-access-guard'); ?></span>
                <div id="fwag-overlay-preview">
                    <div class="fwag-sample-content"></div>
                    <div class="overlay-content">
                        <h2 class="overlay-title"><?php esc_html_e('Access Restricted', 'fw-access-guard'); ?></h2>
                        <p class="overlay-message"><?php esc_html_e('This content is restricted. Please log in to access.', 'fw-access-guard'); ?></p>
                        <button class="overlay-button"><?php esc_html_e('Log In', 'fw-access-guard'); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="fwag-form-group">
            <label><?php esc_html_e('Logo', 'fw-access-guard'); ?></label>
            <p class="description"><?php esc_html_e('Upload a logo to display in the access restriction overlay.', 'fw-access-guard'); ?></p>
            <div class="fwag-media-upload">
                <input type="hidden" name="fwag_logo" id="fwag_logo" value="<?php echo esc_attr($logo_id); ?>">
                <button type="button" class="fwag-upload-button" id="fwag_upload_logo"><?php esc_html_e('Upload Logo', 'fw-access-guard'); ?></button>
                <button type="button" class="fwag-remove-button" id="fwag_remove_logo" <?php echo $logo_id ? '' : 'style="display:none;"'; ?>><?php esc_html_e('Remove', 'fw-access-guard'); ?></button>
                <div id="fwag_logo_preview">
                    <?php if ($logo_url) : ?>
                        <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" class="fwag-logo-preview">
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                var mediaUploader;
                $('#fwag_upload_logo').on('click', function(e) {
                    e.preventDefault();
                    if (mediaUploader) {
                        mediaUploader.open();
                        return;
                    }
                    mediaUploader = wp.media({
                        title: '<?php esc_html_e('Choose Logo', 'fw-access-guard'); ?>',
                        button: {
                            text: '<?php esc_html_e('Use this logo', 'fw-access-guard'); ?>'
                        },
                        multiple: false
                    });
                    mediaUploader.on('select', function() {
                        var attachment = mediaUploader.state().get('selection').first().toJSON();
                        $('#fwag_logo').val(attachment.id);
                        $('#fwag_logo_preview').html('<img src="' + attachment.url + '" style="max-width:200px;height:auto;">');
                        $('#fwag_remove_logo').show();
                    });
                    mediaUploader.open();
                });
                $('#fwag_remove_logo').on('click', function(e) {
                    e.preventDefault();
                    $('#fwag_logo').val('');
                    $('#fwag_logo_preview').html('');
                    $(this).hide();
                });
            });
        </script>
    <?php
    }

    private function render_redirect_section()
    {
        $redirect_enabled = get_option('fwag_redirect_enabled', false);
        $redirect_url = get_option('fwag_redirect_url', '');
        $redirect_type = get_option('fwag_redirect_type', 302);
    ?>
        <div class="fwag-form-group">
            <label><?php esc_html_e('Enable Redirect', 'fw-access-guard'); ?></label>
            <p class="description"><?php esc_html_e('Redirect blocked users to a specific page instead of showing the overlay.', 'fw-access-guard'); ?></p>
            <label class="fwag-checkbox-item" style="display: inline-flex; width: auto;">
                <input type="checkbox" name="fwag_redirect_enabled" value="1" <?php checked($redirect_enabled); ?>>
                <span><?php esc_html_e('Redirect blocked users instead of showing overlay', 'fw-access-guard'); ?></span>
            </label>
        </div>

        <div class="fwag-form-group">
            <label for="fwag_redirect_url"><?php esc_html_e('Redirect URL', 'fw-access-guard'); ?></label>
            <p class="description"><?php esc_html_e('URL to redirect blocked users to. Leave empty to redirect to login page.', 'fw-access-guard'); ?></p>
            <input type="url" id="fwag_redirect_url" name="fwag_redirect_url" value="<?php echo esc_attr($redirect_url); ?>" class="fwag-input regular-text">
        </div>

        <div class="fwag-form-group">
            <label for="fwag_redirect_type"><?php esc_html_e('Redirect Type', 'fw-access-guard'); ?></label>
            <p class="description"><?php esc_html_e('Choose the HTTP redirect type.', 'fw-access-guard'); ?></p>
            <select id="fwag_redirect_type" name="fwag_redirect_type" class="fwag-select">
                <option value="302" <?php selected($redirect_type, 302); ?>><?php esc_html_e('302 Temporary', 'fw-access-guard'); ?></option>
                <option value="301" <?php selected($redirect_type, 301); ?>><?php esc_html_e('301 Permanent', 'fw-access-guard'); ?></option>
            </select>
        </div>
    <?php
    }

    private function render_advanced_features_section()
    {
        $enable_content_teaser = get_option('fwag_enable_content_teaser', false);
        $enable_time_restrictions = get_option('fwag_enable_time_restrictions', false);
        $enable_user_specific_access = get_option('fwag_enable_user_specific_access', false);
        $enable_access_logging = get_option('fwag_enable_access_logging', false);
        $enable_file_protection = get_option('fwag_enable_file_protection', false);
        $log_retention_days = get_option('fwag_log_retention_days', 30);
    ?>
        <div class="fwag-form-group">
            <label><?php esc_html_e('Content Teasers', 'fw-access-guard'); ?></label>
            <p class="description"><?php esc_html_e('Show partial content previews instead of blocking access completely.', 'fw-access-guard'); ?></p>
            <label class="fwag-checkbox-item" style="display: inline-flex; width: auto;">
                <input type="checkbox" name="fwag_enable_content_teaser" value="1" <?php checked($enable_content_teaser); ?>>
                <span><?php esc_html_e('Enable content teasers', 'fw-access-guard'); ?></span>
            </label>
        </div>

        <div class="fwag-form-group">
            <label><?php esc_html_e('Time-Based Restrictions', 'fw-access-guard'); ?></label>
            <p class="description"><?php esc_html_e('Restrict content access based on date and time ranges.', 'fw-access-guard'); ?></p>
            <label class="fwag-checkbox-item" style="display: inline-flex; width: auto;">
                <input type="checkbox" name="fwag_enable_time_restrictions" value="1" <?php checked($enable_time_restrictions); ?>>
                <span><?php esc_html_e('Enable time-based restrictions', 'fw-access-guard'); ?></span>
            </label>
        </div>

        <div class="fwag-form-group">
            <label><?php esc_html_e('User-Specific Access', 'fw-access-guard'); ?></label>
            <p class="description"><?php esc_html_e('Grant or deny access to specific users regardless of their roles.', 'fw-access-guard'); ?></p>
            <label class="fwag-checkbox-item" style="display: inline-flex; width: auto;">
                <input type="checkbox" name="fwag_enable_user_specific_access" value="1" <?php checked($enable_user_specific_access); ?>>
                <span><?php esc_html_e('Enable user-specific access control', 'fw-access-guard'); ?></span>
            </label>
        </div>

        <div class="fwag-form-group">
            <label><?php esc_html_e('Access Logging', 'fw-access-guard'); ?></label>
            <p class="description"><?php esc_html_e('Log all access attempts for security monitoring and analytics.', 'fw-access-guard'); ?></p>
            <label class="fwag-checkbox-item" style="display: inline-flex; width: auto;">
                <input type="checkbox" name="fwag_enable_access_logging" value="1" <?php checked($enable_access_logging); ?>>
                <span><?php esc_html_e('Enable access logging', 'fw-access-guard'); ?></span>
            </label>
        </div>

        <div class="fwag-form-group">
            <label><?php esc_html_e('File Protection', 'fw-access-guard'); ?></label>
            <p class="description"><?php esc_html_e('Protect uploaded files from direct access, serving them only to authorized users.', 'fw-access-guard'); ?></p>
            <label class="fwag-checkbox-item" style="display: inline-flex; width: auto;">
                <input type="checkbox" name="fwag_enable_file_protection" value="1" <?php checked($enable_file_protection); ?>>
                <span><?php esc_html_e('Enable file protection', 'fw-access-guard'); ?></span>
            </label>
        </div>

        <div class="fwag-form-group">
            <label for="fwag_log_retention_days"><?php esc_html_e('Log Retention (Days)', 'fw-access-guard'); ?></label>
            <p class="description"><?php esc_html_e('Number of days to keep access logs before automatic cleanup.', 'fw-access-guard'); ?></p>
            <input type="number" id="fwag_log_retention_days" name="fwag_log_retention_days" value="<?php echo esc_attr($log_retention_days); ?>" min="1" max="365" class="fwag-input small-text">
        </div>
<?php
    }
}

FWAG_Admin_Settings::get_instance();
