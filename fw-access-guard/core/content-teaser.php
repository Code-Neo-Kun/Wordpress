<?php
if (!defined('ABSPATH')) {
    exit;
}

class FWAG_Content_Teaser
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
        add_filter('the_content', array($this, 'maybe_show_teaser'), 20);
        add_filter('get_the_excerpt', array($this, 'maybe_show_teaser_excerpt'), 20, 2);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function enqueue_scripts()
    {
        if (is_singular() && $this->should_show_teaser()) {
            wp_enqueue_script('fwag-teaser', FWAG_PLUGIN_URL . 'assets/teaser.js', array('jquery'), FWAG_VERSION, true);
            wp_localize_script('fwag-teaser', 'fwagTeaser', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fwag_teaser_nonce'),
                'loadingText' => __('Loading...', 'fw-access-guard')
            ));
        }
    }

    public function maybe_show_teaser($content)
    {
        if (!$this->should_show_teaser()) {
            return $content;
        }

        global $post;
        $teaser_enabled = get_post_meta($post->ID, '_fwag_teaser_enabled', true);
        $teaser_length = get_post_meta($post->ID, '_fwag_teaser_length', true);
        $teaser_text = get_post_meta($post->ID, '_fwag_teaser_text', true);

        if ($teaser_enabled !== '1') {
            return $content;
        }

        // Get teaser content
        if (!empty($teaser_text)) {
            $teaser_content = wp_kses_post($teaser_text);
        } else {
            $teaser_length = $teaser_length ? intval($teaser_length) : 200;
            $teaser_content = wp_trim_words(strip_shortcodes($content), $teaser_length, '...');
        }

        // Build teaser HTML
        $teaser_html = '<div class="fwag-teaser-content">';
        $teaser_html .= $teaser_content;
        $teaser_html .= '</div>';

        // Add read more button
        $button_text = get_post_meta($post->ID, '_fwag_teaser_button_text', true);
        $button_text = $button_text ?: __('Read More', 'fw-access-guard');

        $teaser_html .= '<div class="fwag-teaser-actions">';
        $teaser_html .= '<button class="fwag-read-more-btn" data-post-id="' . esc_attr($post->ID) . '">' . esc_html($button_text) . '</button>';
        $teaser_html .= '</div>';

        // Add overlay container
        $teaser_html .= '<div class="fwag-teaser-overlay" style="display: none;">';
        $teaser_html .= '<div class="fwag-teaser-login-form">';
        $teaser_html .= $this->get_login_form();
        $teaser_html .= '</div>';
        $teaser_html .= '</div>';

        return $teaser_html;
    }

    public function maybe_show_teaser_excerpt($excerpt, $post)
    {
        if (!$this->should_show_teaser($post->ID)) {
            return $excerpt;
        }

        $teaser_enabled = get_post_meta($post->ID, '_fwag_teaser_enabled', true);
        if ($teaser_enabled !== '1') {
            return $excerpt;
        }

        $teaser_text = get_post_meta($post->ID, '_fwag_teaser_text', true);
        if (!empty($teaser_text)) {
            return wp_kses_post($teaser_text);
        }

        return $excerpt;
    }

    private function should_show_teaser($post_id = null)
    {
        if (is_admin() || wp_doing_ajax()) {
            return false;
        }

        $post_id = $post_id ?: get_the_ID();
        if (!$post_id) {
            return false;
        }

        $rules = FWAG_Rules::get_instance();
        return $rules->is_content_blocked();
    }

    private function get_login_form()
    {
        ob_start();
?>
        <div class="fwag-login-form-container">
            <h3><?php esc_html_e('Login Required', 'fw-access-guard'); ?></h3>
            <p><?php esc_html_e('Please log in to read the full content.', 'fw-access-guard'); ?></p>

            <?php wp_login_form(array(
                'redirect' => get_permalink(),
                'form_id' => 'fwag-teaser-login',
                'label_username' => __('Username or Email', 'fw-access-guard'),
                'label_password' => __('Password', 'fw-access-guard'),
                'label_remember' => __('Remember Me', 'fw-access-guard'),
                'label_log_in' => __('Log In', 'fw-access-guard'),
                'remember' => true
            )); ?>

            <div class="fwag-login-links">
                <a href="<?php echo wp_lostpassword_url(); ?>"><?php esc_html_e('Lost your password?', 'fw-access-guard'); ?></a>
                <?php if (get_option('users_can_register')): ?>
                    <a href="<?php echo wp_registration_url(); ?>"><?php esc_html_e('Register', 'fw-access-guard'); ?></a>
                <?php endif; ?>
            </div>
        </div>
<?php
        return ob_get_clean();
    }

    public function ajax_load_full_content()
    {
        check_ajax_referer('fwag_teaser_nonce', 'nonce');

        $post_id = intval($_POST['post_id']);
        if (!$post_id || !current_user_can('read_post', $post_id)) {
            wp_die(__('Access denied', 'fw-access-guard'));
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_die(__('Post not found', 'fw-access-guard'));
        }

        // Get full content
        setup_postdata($post);
        $content = apply_filters('the_content', $post->post_content);
        wp_reset_postdata();

        wp_send_json_success(array(
            'content' => $content
        ));
    }
}

FWAG_Content_Teaser::get_instance();
