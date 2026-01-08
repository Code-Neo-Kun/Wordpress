<?php
if (!defined('ABSPATH')) {
    exit;
}

class FWAG_Admin_Metabox
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
        add_action('add_meta_boxes', array($this, 'add_metabox'));
        add_action('save_post', array($this, 'save_metabox'));
    }

    public function add_metabox()
    {
        $post_types = get_post_types(array('public' => true));

        foreach ($post_types as $post_type) {
            add_meta_box(
                'fwag_access_control',
                __('FW Access Guard', 'fw-access-guard'),
                array($this, 'render_metabox'),
                $post_type,
                'side',
                'default'
            );
        }
    }

    public function render_metabox($post)
    {
        wp_nonce_field('fwag_metabox', 'fwag_metabox_nonce');

        $override_enabled = get_post_meta($post->ID, '_fwag_override_enabled', true);
        $is_protected = get_post_meta($post->ID, '_fwag_is_protected', true);

        // Teaser options
        $teaser_enabled = get_post_meta($post->ID, '_fwag_teaser_enabled', true);
        $teaser_length = get_post_meta($post->ID, '_fwag_teaser_length', true);
        $teaser_text = get_post_meta($post->ID, '_fwag_teaser_text', true);
        $teaser_button_text = get_post_meta($post->ID, '_fwag_teaser_button_text', true);

        // Time restrictions
        $time_restrictions_enabled = get_post_meta($post->ID, '_fwag_time_restrictions_enabled', true);
        $start_date = get_post_meta($post->ID, '_fwag_start_date', true);
        $end_date = get_post_meta($post->ID, '_fwag_end_date', true);
        $start_time = get_post_meta($post->ID, '_fwag_start_time', true);
        $end_time = get_post_meta($post->ID, '_fwag_end_time', true);
        $allowed_days = get_post_meta($post->ID, '_fwag_allowed_days', true);
        $timezone = get_post_meta($post->ID, '_fwag_timezone', true);

        // User-specific access
        $user_restrictions_enabled = get_post_meta($post->ID, '_fwag_user_restrictions_enabled', true);
        $user_restriction_type = get_post_meta($post->ID, '_fwag_user_restriction_type', true);
        $allowed_users = get_post_meta($post->ID, '_fwag_allowed_users', true);
        $blocked_users = get_post_meta($post->ID, '_fwag_blocked_users', true);

        // File protection (for attachments)
        $file_protected = get_post_meta($post->ID, '_fwag_file_protected', true);

        // Determine protection status
        $protection_active = $is_protected === '1';
        $protection_status = $protection_active ? 'active' : 'inactive';
        $protection_icon = $protection_active ? '🔒' : '🔓';
        $protection_text = $protection_active ? __('Protected', 'fw-access-guard') : __('Not Protected', 'fw-access-guard');
?>
        <div class="fwag-metabox">
            <div class="fwag-metabox-header">
                <span class="fwag-status-badge fwag-status-<?php echo esc_attr($protection_status); ?> fwag-pulse">
                    <?php echo $protection_icon; ?> <?php echo esc_html($protection_text); ?>
                </span>
            </div>

            <div class="fwag-metabox-content">
                <div class="fwag-metabox-section fwag-feature-toggle">
                    <input type="checkbox" name="fwag_override_enabled" value="1" <?php checked($override_enabled, '1'); ?>>
                    <label class="fwag-feature-toggle-label">
                        <?php esc_html_e('Override global settings', 'fw-access-guard'); ?>
                        <small><?php esc_html_e('Use custom protection rules for this item', 'fw-access-guard'); ?></small>
                    </label>
                </div>

                <div id="fwag_protection_option" class="fwag-metabox-option" <?php echo $override_enabled ? '' : 'style="display:none;"'; ?>>
                    <div class="fwag-metabox-section fwag-feature-toggle">
                        <input type="checkbox" name="fwag_is_protected" value="1" <?php checked($is_protected, '1'); ?>>
                        <label class="fwag-feature-toggle-label">
                            <?php esc_html_e('Protect this content', 'fw-access-guard'); ?>
                            <small><?php esc_html_e('Restrict access based on user roles', 'fw-access-guard'); ?></small>
                        </label>
                    </div>
                </div>

                <?php if (get_option('fwag_enable_content_teaser', '0') === '1'): ?>
                    <div class="fwag-metabox-section">
                        <h4><?php esc_html_e('Content Teaser', 'fw-access-guard'); ?></h4>
                        <div class="fwag-metabox-toggle">
                            <input type="checkbox" name="fwag_teaser_enabled" value="1" <?php checked($teaser_enabled, '1'); ?>>
                            <span><?php esc_html_e('Show teaser instead of blocking', 'fw-access-guard'); ?></span>
                        </div>
                        <div id="fwag_teaser_options" class="fwag-metabox-option" <?php echo $teaser_enabled ? '' : 'style="display:none;"'; ?>>
                            <p>
                                <label><?php esc_html_e('Teaser Length (words):', 'fw-access-guard'); ?></label>
                                <input type="number" name="fwag_teaser_length" value="<?php echo esc_attr($teaser_length ?: 200); ?>" min="10" max="1000">
                            </p>
                            <p>
                                <label><?php esc_html_e('Custom Teaser Text:', 'fw-access-guard'); ?></label>
                                <textarea name="fwag_teaser_text" rows="3" style="width:100%;"><?php echo esc_textarea($teaser_text); ?></textarea>
                            </p>
                            <p>
                                <label><?php esc_html_e('Button Text:', 'fw-access-guard'); ?></label>
                                <input type="text" name="fwag_teaser_button_text" value="<?php echo esc_attr($teaser_button_text ?: __('Read More', 'fw-access-guard')); ?>" style="width:100%;">
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (get_option('fwag_enable_time_restrictions', '0') === '1'): ?>
                    <div class="fwag-metabox-section">
                        <h4><?php esc_html_e('Time Restrictions', 'fw-access-guard'); ?></h4>
                        <div class="fwag-metabox-toggle">
                            <input type="checkbox" name="fwag_time_restrictions_enabled" value="1" <?php checked($time_restrictions_enabled, '1'); ?>>
                            <span><?php esc_html_e('Enable time-based restrictions', 'fw-access-guard'); ?></span>
                        </div>
                        <div id="fwag_time_options" class="fwag-metabox-option" <?php echo $time_restrictions_enabled ? '' : 'style="display:none;"'; ?>>
                            <p>
                                <label><?php esc_html_e('Start Date:', 'fw-access-guard'); ?></label>
                                <input type="date" name="fwag_start_date" value="<?php echo esc_attr($start_date); ?>">
                            </p>
                            <p>
                                <label><?php esc_html_e('End Date:', 'fw-access-guard'); ?></label>
                                <input type="date" name="fwag_end_date" value="<?php echo esc_attr($end_date); ?>">
                            </p>
                            <p>
                                <label><?php esc_html_e('Start Time:', 'fw-access-guard'); ?></label>
                                <input type="time" name="fwag_start_time" value="<?php echo esc_attr($start_time); ?>">
                            </p>
                            <p>
                                <label><?php esc_html_e('End Time:', 'fw-access-guard'); ?></label>
                                <input type="time" name="fwag_end_time" value="<?php echo esc_attr($end_time); ?>">
                            </p>
                            <p>
                                <label><?php esc_html_e('Allowed Days:', 'fw-access-guard'); ?></label><br>
                                <?php
                                $days = array(
                                    0 => __('Sunday', 'fw-access-guard'),
                                    1 => __('Monday', 'fw-access-guard'),
                                    2 => __('Tuesday', 'fw-access-guard'),
                                    3 => __('Wednesday', 'fw-access-guard'),
                                    4 => __('Thursday', 'fw-access-guard'),
                                    5 => __('Friday', 'fw-access-guard'),
                                    6 => __('Saturday', 'fw-access-guard')
                                );
                                $allowed_days = is_array($allowed_days) ? $allowed_days : array();
                                foreach ($days as $key => $day) {
                                    echo '<label><input type="checkbox" name="fwag_allowed_days[]" value="' . $key . '" ' . checked(in_array($key, $allowed_days), true, false) . '> ' . $day . '</label><br>';
                                }
                                ?>
                            </p>
                            <p>
                                <label><?php esc_html_e('Timezone:', 'fw-access-guard'); ?></label>
                                <select name="fwag_timezone">
                                    <?php
                                    $timezones = timezone_identifiers_list();
                                    $current_tz = $timezone ?: wp_timezone_string();
                                    foreach ($timezones as $tz) {
                                        echo '<option value="' . esc_attr($tz) . '" ' . selected($tz, $current_tz, false) . '>' . esc_html($tz) . '</option>';
                                    }
                                    ?>
                                </select>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (get_option('fwag_enable_user_specific_access', '0') === '1'): ?>
                    <div class="fwag-metabox-section">
                        <h4><?php esc_html_e('User-Specific Access', 'fw-access-guard'); ?></h4>
                        <div class="fwag-metabox-toggle">
                            <input type="checkbox" name="fwag_user_restrictions_enabled" value="1" <?php checked($user_restrictions_enabled, '1'); ?>>
                            <span><?php esc_html_e('Enable user-specific restrictions', 'fw-access-guard'); ?></span>
                        </div>
                        <div id="fwag_user_options" class="fwag-metabox-option" <?php echo $user_restrictions_enabled ? '' : 'style="display:none;"'; ?>>
                            <p>
                                <label><?php esc_html_e('Restriction Type:', 'fw-access-guard'); ?></label><br>
                                <label><input type="radio" name="fwag_user_restriction_type" value="whitelist" <?php checked($user_restriction_type, 'whitelist'); ?>> <?php esc_html_e('Whitelist (only selected users)', 'fw-access-guard'); ?></label><br>
                                <label><input type="radio" name="fwag_user_restriction_type" value="blacklist" <?php checked($user_restriction_type, 'blacklist'); ?>> <?php esc_html_e('Blacklist (block selected users)', 'fw-access-guard'); ?></label>
                            </p>
                            <div id="fwag_allowed_users_section" style="<?php echo ($user_restriction_type === 'whitelist') ? '' : 'display:none;'; ?>">
                                <p>
                                    <label><?php esc_html_e('Allowed Users:', 'fw-access-guard'); ?></label>
                                    <select name="fwag_allowed_users[]" multiple class="fwag-user-select" style="width:100%;min-height:100px;">
                                        <?php
                                        $allowed_users = is_array($allowed_users) ? $allowed_users : array();
                                        foreach ($allowed_users as $user_id) {
                                            $user = get_userdata($user_id);
                                            if ($user) {
                                                echo '<option value="' . $user_id . '" selected>' . esc_html($user->display_name . ' (' . $user->user_email . ')') . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </p>
                            </div>
                            <div id="fwag_blocked_users_section" style="<?php echo ($user_restriction_type === 'blacklist') ? '' : 'display:none;'; ?>">
                                <p>
                                    <label><?php esc_html_e('Blocked Users:', 'fw-access-guard'); ?></label>
                                    <select name="fwag_blocked_users[]" multiple class="fwag-user-select" style="width:100%;min-height:100px;">
                                        <?php
                                        $blocked_users = is_array($blocked_users) ? $blocked_users : array();
                                        foreach ($blocked_users as $user_id) {
                                            $user = get_userdata($user_id);
                                            if ($user) {
                                                echo '<option value="' . $user_id . '" selected>' . esc_html($user->display_name . ' (' . $user->user_email . ')') . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (get_option('fwag_enable_file_protection', '0') === '1' && $post->post_type === 'attachment'): ?>
                    <div class="fwag-metabox-section">
                        <h4><?php esc_html_e('File Protection', 'fw-access-guard'); ?></h4>
                        <div class="fwag-metabox-toggle">
                            <input type="checkbox" name="fwag_file_protected" value="1" <?php checked($file_protected, '1'); ?>>
                            <span><?php esc_html_e('Protect this file', 'fw-access-guard'); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <script>
                jQuery(document).ready(function($) {
                    $('input[name="fwag_override_enabled"]').on('change', function() {
                        if ($(this).is(':checked')) {
                            $('#fwag_protection_option').slideDown(300);
                        } else {
                            $('#fwag_protection_option').slideUp(300);
                        }
                    });

                    $('input[name="fwag_teaser_enabled"]').on('change', function() {
                        if ($(this).is(':checked')) {
                            $('#fwag_teaser_options').slideDown(300);
                        } else {
                            $('#fwag_teaser_options').slideUp(300);
                        }
                    });

                    $('input[name="fwag_time_restrictions_enabled"]').on('change', function() {
                        if ($(this).is(':checked')) {
                            $('#fwag_time_options').slideDown(300);
                        } else {
                            $('#fwag_time_options').slideUp(300);
                        }
                    });

                    $('input[name="fwag_user_restrictions_enabled"]').on('change', function() {
                        if ($(this).is(':checked')) {
                            $('#fwag_user_options').slideDown(300);
                        } else {
                            $('#fwag_user_options').slideUp(300);
                        }
                    });

                    $('input[name="fwag_user_restriction_type"]').on('change', function() {
                        if ($(this).val() === 'whitelist') {
                            $('#fwag_allowed_users_section').show();
                            $('#fwag_blocked_users_section').hide();
                        } else {
                            $('#fwag_allowed_users_section').hide();
                            $('#fwag_blocked_users_section').show();
                        }
                    });
                });
            </script>
    <?php
    }

    public function save_metabox($post_id)
    {
        if (!isset($_POST['fwag_metabox_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['fwag_metabox_nonce'], 'fwag_metabox')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Basic protection settings
        $override_enabled = isset($_POST['fwag_override_enabled']) ? '1' : '0';
        $is_protected = isset($_POST['fwag_is_protected']) ? '1' : '0';

        update_post_meta($post_id, '_fwag_override_enabled', $override_enabled);
        update_post_meta($post_id, '_fwag_is_protected', $is_protected);

        // Teaser settings
        $teaser_enabled = isset($_POST['fwag_teaser_enabled']) ? '1' : '0';
        $teaser_length = isset($_POST['fwag_teaser_length']) ? intval($_POST['fwag_teaser_length']) : 200;
        $teaser_text = isset($_POST['fwag_teaser_text']) ? sanitize_textarea_field($_POST['fwag_teaser_text']) : '';
        $teaser_button_text = isset($_POST['fwag_teaser_button_text']) ? sanitize_text_field($_POST['fwag_teaser_button_text']) : '';

        update_post_meta($post_id, '_fwag_teaser_enabled', $teaser_enabled);
        update_post_meta($post_id, '_fwag_teaser_length', $teaser_length);
        update_post_meta($post_id, '_fwag_teaser_text', $teaser_text);
        update_post_meta($post_id, '_fwag_teaser_button_text', $teaser_button_text);

        // Time restriction settings
        $time_restrictions_enabled = isset($_POST['fwag_time_restrictions_enabled']) ? '1' : '0';
        $start_date = isset($_POST['fwag_start_date']) ? sanitize_text_field($_POST['fwag_start_date']) : '';
        $end_date = isset($_POST['fwag_end_date']) ? sanitize_text_field($_POST['fwag_end_date']) : '';
        $start_time = isset($_POST['fwag_start_time']) ? sanitize_text_field($_POST['fwag_start_time']) : '';
        $end_time = isset($_POST['fwag_end_time']) ? sanitize_text_field($_POST['fwag_end_time']) : '';
        $allowed_days = isset($_POST['fwag_allowed_days']) ? array_map('intval', $_POST['fwag_allowed_days']) : array();
        $timezone = isset($_POST['fwag_timezone']) ? sanitize_text_field($_POST['fwag_timezone']) : '';

        update_post_meta($post_id, '_fwag_time_restrictions_enabled', $time_restrictions_enabled);
        update_post_meta($post_id, '_fwag_start_date', $start_date);
        update_post_meta($post_id, '_fwag_end_date', $end_date);
        update_post_meta($post_id, '_fwag_start_time', $start_time);
        update_post_meta($post_id, '_fwag_end_time', $end_time);
        update_post_meta($post_id, '_fwag_allowed_days', $allowed_days);
        update_post_meta($post_id, '_fwag_timezone', $timezone);

        // User-specific access settings
        $user_restrictions_enabled = isset($_POST['fwag_user_restrictions_enabled']) ? '1' : '0';
        $user_restriction_type = isset($_POST['fwag_user_restriction_type']) ? sanitize_text_field($_POST['fwag_user_restriction_type']) : 'whitelist';
        $allowed_users = isset($_POST['fwag_allowed_users']) ? array_map('intval', $_POST['fwag_allowed_users']) : array();
        $blocked_users = isset($_POST['fwag_blocked_users']) ? array_map('intval', $_POST['fwag_blocked_users']) : array();

        update_post_meta($post_id, '_fwag_user_restrictions_enabled', $user_restrictions_enabled);
        update_post_meta($post_id, '_fwag_user_restriction_type', $user_restriction_type);
        update_post_meta($post_id, '_fwag_allowed_users', $allowed_users);
        update_post_meta($post_id, '_fwag_blocked_users', $blocked_users);

        // File protection settings
        if (isset($_POST['fwag_file_protected'])) {
            $file_protected = '1';
            update_post_meta($post_id, '_fwag_file_protected', $file_protected);
        } else {
            delete_post_meta($post_id, '_fwag_file_protected');
        }
    }
}

FWAG_Admin_Metabox::get_instance();
