<?php
if (!defined('ABSPATH')) {
    exit;
}

class FWAG_File_Protection
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
        add_action('init', array($this, 'protect_uploads'));
        add_filter('upload_dir', array($this, 'custom_upload_directory'));
        add_action('template_redirect', array($this, 'handle_file_access'));
        add_filter('wp_get_attachment_url', array($this, 'filter_attachment_url'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function enqueue_admin_scripts($hook)
    {
        if ($hook === 'upload.php' || $hook === 'post.php' || $hook === 'post-new.php') {
            wp_enqueue_script('fwag-file-protection', FWAG_PLUGIN_URL . 'assets/file-protection.js', array('jquery'), FWAG_VERSION, true);
        }
    }

    public function protect_uploads()
    {
        // Create protected uploads directory
        $protected_dir = $this->get_protected_uploads_dir();
        if (!file_exists($protected_dir)) {
            wp_mkdir_p($protected_dir);
        }

        // Create .htaccess file for protection
        $htaccess_file = $protected_dir . '.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "Order Deny,Allow\nDeny from all\n";
            file_put_contents($htaccess_file, $htaccess_content);
        }
    }

    public function custom_upload_directory($uploads)
    {
        // This would be used when uploading protected files
        // For now, we'll handle this in the metabox
        return $uploads;
    }

    public function handle_file_access()
    {
        if (!isset($_GET['fwag_file'])) {
            return;
        }

        $file_id = intval($_GET['fwag_file']);
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

        if (!$file_id) {
            wp_die(__('Invalid file request', 'fw-access-guard'));
        }

        // Verify token
        $expected_token = $this->generate_file_token($file_id);
        if (!wp_verify_nonce($token, 'fwag_file_' . $file_id)) {
            wp_die(__('Access denied', 'fw-access-guard'));
        }

        // Check if file is protected
        if (!$this->is_file_protected($file_id)) {
            wp_die(__('File not found', 'fw-access-guard'));
        }

        // Check user access
        $rules = FWAG_Rules::get_instance();
        if (!$rules->user_has_access()) {
            wp_die(__('Access denied', 'fw-access-guard'));
        }

        // Log access attempt
        do_action('fwag_content_access_attempt', $file_id, get_current_user_id(), 'granted');

        // Serve the file
        $this->serve_protected_file($file_id);
    }

    private function generate_file_token($file_id)
    {
        return wp_create_nonce('fwag_file_' . $file_id);
    }

    public function is_file_protected($file_id)
    {
        $protected = get_post_meta($file_id, '_fwag_file_protected', true);
        return $protected === '1';
    }

    public function filter_attachment_url($url, $post_id)
    {
        if (!$this->is_file_protected($post_id)) {
            return $url;
        }

        // Return protected URL
        $token = $this->generate_file_token($post_id);
        return add_query_arg(array(
            'fwag_file' => $post_id,
            'token' => $token
        ), home_url('/'));
    }

    private function serve_protected_file($file_id)
    {
        $file_path = get_attached_file($file_id);

        if (!file_exists($file_path)) {
            wp_die(__('File not found', 'fw-access-guard'));
        }

        // Get file info
        $file_size = filesize($file_path);
        $file_name = basename($file_path);
        $mime_type = mime_content_type($file_path);

        // Clear any output buffers
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Length: ' . $file_size);
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        // Read and output file
        readfile($file_path);
        exit;
    }

    public function get_protected_uploads_dir()
    {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/protected/';
    }

    public function get_protected_uploads_url()
    {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/protected/';
    }

    public function move_to_protected($file_id)
    {
        $current_path = get_attached_file($file_id);
        $protected_dir = $this->get_protected_uploads_dir();

        if (!file_exists($current_path)) {
            return false;
        }

        $file_name = basename($current_path);
        $new_path = $protected_dir . $file_name;

        if (rename($current_path, $new_path)) {
            // Update attachment metadata
            update_post_meta($file_id, '_wp_attached_file', 'protected/' . $file_name);
            update_post_meta($file_id, '_fwag_file_protected', '1');
            return true;
        }

        return false;
    }

    public function move_from_protected($file_id)
    {
        $protected_dir = $this->get_protected_uploads_dir();
        $file_name = get_post_meta($file_id, '_wp_attached_file', true);

        if (strpos($file_name, 'protected/') !== 0) {
            return false;
        }

        $protected_path = $protected_dir . basename($file_name);
        $upload_dir = wp_upload_dir();
        $new_path = $upload_dir['basedir'] . '/' . str_replace('protected/', '', $file_name);

        if (rename($protected_path, $new_path)) {
            // Update attachment metadata
            update_post_meta($file_id, '_wp_attached_file', str_replace('protected/', '', $file_name));
            delete_post_meta($file_id, '_fwag_file_protected');
            return true;
        }

        return false;
    }

    public function get_protected_files()
    {
        $args = array(
            'post_type' => 'attachment',
            'meta_query' => array(
                array(
                    'key' => '_fwag_file_protected',
                    'value' => '1',
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1
        );

        return get_posts($args);
    }

    public function ajax_toggle_file_protection()
    {
        check_ajax_referer('fwag_file_protection_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_die(__('Access denied', 'fw-access-guard'));
        }

        $file_id = intval($_POST['file_id']);
        $protect = isset($_POST['protect']) ? boolval($_POST['protect']) : false;

        if ($protect) {
            $result = $this->move_to_protected($file_id);
        } else {
            $result = $this->move_from_protected($file_id);
        }

        if ($result) {
            wp_send_json_success(array(
                'message' => $protect ? __('File protected successfully', 'fw-access-guard') : __('File unprotected successfully', 'fw-access-guard')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to update file protection', 'fw-access-guard')
            ));
        }
    }
}

FWAG_File_Protection::get_instance();
