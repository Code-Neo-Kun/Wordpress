<?php
if (!defined('ABSPATH')) {
    exit;
}

class FWAG_Access_Logging
{
    private static $instance = null;
    private $table_name;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'fwag_access_logs';

        // Only add hooks if access logging is enabled
        if (get_option('fwag_enable_access_logging', false)) {
            add_action('init', array($this, 'create_log_table'));
            add_action('fwag_content_access_attempt', array($this, 'log_access_attempt'), 10, 3);
            add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
            add_action('admin_menu', array($this, 'add_logs_menu'));
        }
    }

    public function create_log_table()
    {
        if (get_option('fwag_logs_table_created')) {
            return;
        }

        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            user_ip varchar(45) NOT NULL,
            user_agent text,
            access_type varchar(20) NOT NULL,
            access_result varchar(20) NOT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            session_id varchar(64),
            referrer text,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY user_id (user_id),
            KEY access_type (access_type),
            KEY timestamp (timestamp)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);

        if (!empty($result)) {
            update_option('fwag_logs_table_created', true);
        }
    }

    public function log_access_attempt($post_id, $user_id, $access_result)
    {
        if (!get_option('fwag_enable_access_logging', false)) {
            return;
        }

        global $wpdb;

        // Ensure table exists
        $this->create_log_table();

        $data = array(
            'post_id' => $post_id,
            'user_id' => $user_id,
            'user_ip' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'access_type' => is_user_logged_in() ? 'authenticated' : 'anonymous',
            'access_result' => $access_result,
            'session_id' => session_id(),
            'referrer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''
        );

        $result = $wpdb->insert($this->table_name, $data);

        // Log any database errors for debugging
        if ($result === false) {
            error_log('FW Access Guard: Failed to log access attempt - ' . $wpdb->last_error);
        }
    }

    public function ajax_log_access_attempt()
    {
        check_ajax_referer('fwag_teaser_nonce', 'nonce');

        $post_id = intval($_POST['post_id']);
        $action = sanitize_text_field($_POST['action_type']);

        if (!$post_id) {
            wp_die(__('Invalid post ID', 'fw-access-guard'));
        }

        $user_id = get_current_user_id();
        $access_result = $action === 'granted' ? 'granted' : 'denied';

        do_action('fwag_content_access_attempt', $post_id, $user_id, $access_result);

        wp_send_json_success();
    }

    private function get_client_ip()
    {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public function add_dashboard_widget()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'fwag_access_logs_widget',
            __('FW Access Guard - Recent Activity', 'fw-access-guard'),
            array($this, 'dashboard_widget_content')
        );
    }

    public function dashboard_widget_content()
    {
        global $wpdb;

        // Ensure table exists
        $this->create_log_table();

        $logs = $wpdb->get_results($wpdb->prepare("
            SELECT l.*, p.post_title
            FROM {$this->table_name} l
            LEFT JOIN {$wpdb->posts} p ON l.post_id = p.ID
            ORDER BY l.timestamp DESC
            LIMIT 10
        "));

        $logs = $logs ?: array();

        if (empty($logs)) {
            echo '<p>' . __('No access attempts logged yet.', 'fw-access-guard') . '</p>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . __('Time', 'fw-access-guard') . '</th>';
        echo '<th>' . __('Content', 'fw-access-guard') . '</th>';
        echo '<th>' . __('User', 'fw-access-guard') . '</th>';
        echo '<th>' . __('Result', 'fw-access-guard') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($logs as $log) {
            $time = human_time_diff(strtotime($log->timestamp)) . ' ' . __('ago', 'fw-access-guard');
            $content_title = $log->post_title ?: sprintf(__('Post ID: %d', 'fw-access-guard'), $log->post_id);
            $user_data = $log->user_id ? get_userdata($log->user_id) : null;
            $user_info = $user_data ? $user_data->display_name : __('Anonymous', 'fw-access-guard');
            $result_class = $log->access_result === 'granted' ? 'success' : 'error';
            $result_text = $log->access_result === 'granted' ? __('Granted', 'fw-access-guard') : __('Denied', 'fw-access-guard');

            echo '<tr>';
            echo '<td>' . esc_html($time) . '</td>';
            echo '<td>' . esc_html($content_title) . '</td>';
            echo '<td>' . esc_html($user_info) . '</td>';
            echo '<td><span class="fwag-log-result fwag-log-' . $result_class . '">' . esc_html($result_text) . '</span></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p><a href="' . admin_url('admin.php?page=fwag-access-logs') . '">' . __('View all logs', 'fw-access-guard') . '</a></p>';
    }

    public function add_logs_menu()
    {
        add_submenu_page(
            'options-general.php',
            __('Access Logs', 'fw-access-guard'),
            __('Access Logs', 'fw-access-guard'),
            'manage_options',
            'fwag-access-logs',
            array($this, 'logs_page_content')
        );
    }

    public function logs_page_content()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Access denied', 'fw-access-guard'));
        }

        // Ensure table exists
        $this->create_log_table();

        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 50;
        $offset = ($current_page - 1) * $per_page;

        global $wpdb;

        // Handle filters
        $where_clauses = array();
        $post_filter = isset($_GET['post_filter']) ? intval($_GET['post_filter']) : '';
        $user_filter = isset($_GET['user_filter']) ? intval($_GET['user_filter']) : '';
        $result_filter = isset($_GET['result_filter']) ? sanitize_text_field($_GET['result_filter']) : '';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

        if ($post_filter) {
            $where_clauses[] = $wpdb->prepare("l.post_id = %d", $post_filter);
        }
        if ($user_filter) {
            $where_clauses[] = $wpdb->prepare("l.user_id = %d", $user_filter);
        }
        if ($result_filter) {
            $where_clauses[] = $wpdb->prepare("l.access_result = %s", $result_filter);
        }
        if ($date_from) {
            $where_clauses[] = $wpdb->prepare("DATE(l.timestamp) >= %s", $date_from);
        }
        if ($date_to) {
            $where_clauses[] = $wpdb->prepare("DATE(l.timestamp) <= %s", $date_to);
        }

        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

        // Get total count
        $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} l {$where_sql}");
        $total_logs = $total_logs ?: 0;

        // Get logs
        $logs = $wpdb->get_results($wpdb->prepare("
            SELECT l.*, p.post_title, u.display_name as user_name, u.user_email
            FROM {$this->table_name} l
            LEFT JOIN {$wpdb->posts} p ON l.post_id = p.ID
            LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
            {$where_sql}
            ORDER BY l.timestamp DESC
            LIMIT %d OFFSET %d
        ", $per_page, $offset));

        $logs = $logs ?: array();

        $total_pages = ceil($total_logs / $per_page);

?>
        <div class="wrap">
            <h1><?php esc_html_e('FW Access Guard - Access Logs', 'fw-access-guard'); ?></h1>

            <!-- Filters -->
            <div class="fwag-logs-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="fwag-access-logs">
                    <div class="fwag-filter-row">
                        <select name="post_filter">
                            <option value=""><?php esc_html_e('All Content', 'fw-access-guard'); ?></option>
                            <?php
                            $posts = get_posts(array('numberposts' => 1000, 'post_type' => 'any', 'orderby' => 'title', 'order' => 'ASC'));
                            foreach ($posts as $post) {
                                printf(
                                    '<option value="%d" %s>%s</option>',
                                    $post->ID,
                                    selected($post_filter, $post->ID, false),
                                    esc_html($post->post_title ?: __('(no title)', 'fw-access-guard'))
                                );
                            }
                            ?>
                        </select>

                        <select name="user_filter">
                            <option value=""><?php esc_html_e('All Users', 'fw-access-guard'); ?></option>
                            <?php
                            $users = get_users(array('number' => 1000, 'orderby' => 'display_name', 'order' => 'ASC'));
                            foreach ($users as $user) {
                                printf(
                                    '<option value="%d" %s>%s</option>',
                                    $user->ID,
                                    selected($user_filter, $user->ID, false),
                                    esc_html($user->display_name)
                                );
                            }
                            ?>
                        </select>

                        <select name="result_filter">
                            <option value=""><?php esc_html_e('All Results', 'fw-access-guard'); ?></option>
                            <option value="granted" <?php selected($result_filter, 'granted'); ?>><?php esc_html_e('Granted', 'fw-access-guard'); ?></option>
                            <option value="denied" <?php selected($result_filter, 'denied'); ?>><?php esc_html_e('Denied', 'fw-access-guard'); ?></option>
                        </select>

                        <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" placeholder="<?php esc_attr_e('From Date', 'fw-access-guard'); ?>">
                        <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" placeholder="<?php esc_attr_e('To Date', 'fw-access-guard'); ?>">

                        <button type="submit" class="button"><?php esc_html_e('Filter', 'fw-access-guard'); ?></button>
                        <a href="<?php echo admin_url('admin.php?page=fwag-access-logs'); ?>" class="button"><?php esc_html_e('Clear', 'fw-access-guard'); ?></a>
                    </div>
                </form>
            </div>

            <!-- Logs Table -->
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Time', 'fw-access-guard'); ?></th>
                        <th><?php esc_html_e('Content', 'fw-access-guard'); ?></th>
                        <th><?php esc_html_e('User', 'fw-access-guard'); ?></th>
                        <th><?php esc_html_e('IP Address', 'fw-access-guard'); ?></th>
                        <th><?php esc_html_e('Result', 'fw-access-guard'); ?></th>
                        <th><?php esc_html_e('Details', 'fw-access-guard'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6"><?php esc_html_e('No logs found.', 'fw-access-guard'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log->timestamp); ?></td>
                                <td>
                                    <?php if ($log->post_title): ?>
                                        <a href="<?php echo get_edit_post_link($log->post_id); ?>"><?php echo esc_html($log->post_title); ?></a>
                                    <?php else: ?>
                                        <?php printf(__('Post ID: %d', 'fw-access-guard'), $log->post_id); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($log->user_name): ?>
                                        <a href="<?php echo get_edit_user_link($log->user_id); ?>"><?php echo esc_html($log->user_name); ?></a>
                                        <br><small><?php echo esc_html($log->user_email); ?></small>
                                    <?php else: ?>
                                        <?php esc_html_e('Anonymous', 'fw-access-guard'); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($log->user_ip); ?></td>
                                <td>
                                    <span class="fwag-log-result fwag-log-<?php echo $log->access_result === 'granted' ? 'success' : 'error'; ?>">
                                        <?php echo $log->access_result === 'granted' ? __('Granted', 'fw-access-guard') : __('Denied', 'fw-access-guard'); ?>
                                    </span>
                                </td>
                                <td>
                                    <small>
                                        <?php echo esc_html($log->access_type); ?>
                                        <?php if ($log->referrer): ?>
                                            <br><?php printf(__('From: %s', 'fw-access-guard'), esc_url($log->referrer)); ?>
                                        <?php endif; ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="fwag-pagination">
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo; Previous', 'fw-access-guard'),
                        'next_text' => __('Next &raquo;', 'fw-access-guard'),
                        'total' => $total_pages,
                        'current' => $current_page
                    ));
                    ?>
                </div>
            <?php endif; ?>
        </div>

        <style>
            .fwag-logs-filters {
                background: #fff;
                padding: 20px;
                margin: 20px 0;
                border: 1px solid #ddd;
            }

            .fwag-filter-row {
                display: flex;
                gap: 10px;
                align-items: center;
                flex-wrap: wrap;
            }

            .fwag-filter-row select,
            .fwag-filter-row input {
                min-width: 150px;
            }

            .fwag-log-result {
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: bold;
                text-transform: uppercase;
            }

            .fwag-log-success {
                background: #d4edda;
                color: #155724;
            }

            .fwag-log-error {
                background: #f8d7da;
                color: #721c24;
            }

            .fwag-pagination {
                margin-top: 20px;
                text-align: center;
            }
        </style>
<?php
    }

    public function get_access_stats($days = 30)
    {
        global $wpdb;

        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT
                COUNT(*) as total_attempts,
                SUM(CASE WHEN access_result = 'granted' THEN 1 ELSE 0 END) as granted_attempts,
                SUM(CASE WHEN access_result = 'denied' THEN 1 ELSE 0 END) as denied_attempts,
                COUNT(DISTINCT post_id) as unique_content,
                COUNT(DISTINCT user_id) as unique_users
            FROM {$this->table_name}
            WHERE timestamp >= %s
        ", $date));

        return $stats;
    }

    public function cleanup_old_logs($days = 90)
    {
        global $wpdb;

        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $wpdb->query($wpdb->prepare("
            DELETE FROM {$this->table_name}
            WHERE timestamp < %s
        ", $date));
    }
}

FWAG_Access_Logging::get_instance();
