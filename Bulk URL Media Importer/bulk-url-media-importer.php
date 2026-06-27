<?php
/**
 * Plugin Name: Bulk URL Media Importer
 * Description: Import images/videos from URLs via CSV/TXT or manual entry, create a custom post type log with thumbnails and star ratings.
 * Version: 3.10
 * Author: Muhammad Fawad Ali
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) exit;

class Bulk_URL_Media_Importer {

    private static $instance = null;
    const CPT_SLUG = 'media_import_log';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_custom_post_type'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_bulk_media_import_process', array($this, 'handle_ajax_import'));
        add_action('wp_ajax_bulk_media_get_logs_table', array($this, 'ajax_get_logs_table'));
        add_action('wp_ajax_update_imported_file_rating', array($this, 'handle_update_rating'));
        add_action('admin_post_delete_all_import_logs', array($this, 'handle_delete_all_logs'));
        add_action('wp_ajax_delete_single_import_log', array($this, 'handle_delete_single_log'));
    }

    public function register_custom_post_type() {
        $labels = array(
            'name'               => 'Media Import Logs',
            'singular_name'      => 'Import Log',
            'menu_name'          => 'Media Importer Logs',
            'name_admin_bar'     => 'Import Log',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Log',
            'new_item'           => 'New Log',
            'edit_item'          => 'Edit Log',
            'view_item'          => 'View Log',
            'all_items'          => 'All Import Logs',
            'search_items'       => 'Search Logs',
            'not_found'          => 'No logs found.',
        );
        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'show_in_admin_bar'  => false,
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array('title', 'thumbnail', 'custom-fields'),
            'show_in_rest'       => false,
        );
        register_post_type(self::CPT_SLUG, $args);
    }

    public function add_admin_menu() {
        add_media_page(
            'Bulk URL Media Importer',
            'Bulk URL Importer',
            'manage_options',
            'bulk-url-media-importer',
            array($this, 'render_admin_page')
        );
    }

    public function enqueue_assets($hook) {
        if ('media_page_bulk-url-media-importer' !== $hook) return;
        wp_enqueue_style('dashicons');
        wp_enqueue_style(
            'bulk-media-importer-css',
            plugin_dir_url(__FILE__) . 'assets/css/admin.css',
            array(),
            '3.10'
        );
        wp_enqueue_script(
            'bulk-media-importer-js',
            plugin_dir_url(__FILE__) . 'assets/js/admin.js',
            array('jquery'),
            '3.10',
            true
        );
        wp_localize_script('bulk-media-importer-js', 'bulk_media_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('bulk_media_import_nonce'),
        ));
    }

    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>Bulk URL Media Importer</h1>

            <div class="bm-tabs">
                <button class="bm-tablink active" data-tab="manualurls"><span class="dashicons dashicons-edit"></span> Manual URLs</button>
                <button class="bm-tablink" data-tab="fileupload"><span class="dashicons dashicons-upload"></span> File Upload</button>
            </div>

            <!-- Manual URLs Tab -->
            <div id="manualurls" class="bm-tabcontent" style="display:block;">
                <div class="bm-upload-section">
                    <h2><span class="dashicons dashicons-edit"></span> Enter URLs Manually</h2>
                    <p>Paste one URL per line (max 100 URLs at a time).</p>
                    <textarea id="bm-urls-textarea" rows="8" placeholder="https://example.com/image1.jpg&#10;https://example.com/image2.png"></textarea>
                    <div class="bm-button-wrap">
                        <button id="bm-start-import-manual" class="button button-primary">Start Import</button>
                    </div>
                </div>
            </div>

            <!-- File Upload Tab -->
            <div id="fileupload" class="bm-tabcontent" style="display:none;">
                <div class="bm-upload-section">
                    <h2><span class="dashicons dashicons-upload"></span> Upload CSV / TXT File</h2>
                    <p>Each line should contain one URL. For CSV, the first column must be the URL.</p>
                    <input type="file" id="bm-file-upload" accept=".csv,.txt">
                    <div class="bm-button-wrap">
                        <button id="bm-start-import-file" class="button button-primary">Start Import</button>
                    </div>
                </div>
            </div>

            <div id="bm-progress-container" style="display:none;">
                <h2><span class="dashicons dashicons-update"></span> Import Progress</h2>
                <div class="progress-bar"><div class="progress-bar-fill" style="width:0%;"></div></div>
                <p><span id="bm-status-message">Processing...</span></p>
                <div id="bm-log"></div>
            </div>

            <!-- Imported files list -->
            <div class="bm-imported-list-section">
                <h2><span class="dashicons dashicons-format-gallery"></span> Imported Files (Logs with Thumbnail & Rating)</h2>
                <div id="bm-logs-table-wrapper">
                    <?php $this->display_imported_files_from_cpt(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function display_imported_files_from_cpt($echo = true) {
        $posts = get_posts(array(
            'post_type'      => self::CPT_SLUG,
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));
        ob_start();
        if (empty($posts)) {
            echo '<p>No imported files found yet.</p>';
        } else {
            ?>
            <table class="wp-list-table widefat fixed striped" id="imported-logs-table">
                <thead>
                    <tr><th>Thumbnail</th><th>File Name</th><th>File Size</th><th>File Type</th><th>Source URL</th><th>Star Rating</th><th>Import Date</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $log_post):
                        $attachment_id = get_post_thumbnail_id($log_post);
                        $thumbnail = $attachment_id ? wp_get_attachment_image($attachment_id, array(50,50), true) : '<span class="dashicons dashicons-format-image"></span>';
                        $file_size = get_post_meta($log_post->ID, '_file_size', true);
                        $file_type = get_post_meta($log_post->ID, '_file_type', true);
                        $source_url = get_post_meta($log_post->ID, '_source_url', true);
                        $rating = intval(get_post_meta($log_post->ID, '_rating', true));
                        if ($rating < 0) $rating = 0;
                        if ($rating > 5) $rating = 5;
                        ?>
                        <tr data-logid="<?php echo $log_post->ID; ?>">
                            <td class="bm-thumb-col"><?php echo $thumbnail; ?></td>
                            <td><?php echo esc_html($log_post->post_title); ?></td>
                            <td><?php echo esc_html($file_size); ?></td>
                            <td><?php echo esc_html($file_type); ?></td>
                            <td><a href="<?php echo esc_url($source_url); ?>" target="_blank">View</a></td>
                            <td class="bm-rating-col">
                                <div class="bm-star-rating" data-logid="<?php echo $log_post->ID; ?>" data-rating="<?php echo $rating; ?>">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="dashicons dashicons-star-empty" data-star="<?php echo $i; ?>"></span>
                                    <?php endfor; ?>
                                </div>
                            </td>
                            <td><?php echo get_the_date('Y-m-d H:i:s', $log_post); ?></td>
                            <td><button class="button button-small delete-single-log" data-id="<?php echo $log_post->ID; ?>">Delete</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="delete_all_import_logs">
                <?php wp_nonce_field('delete_all_logs_action', 'delete_all_nonce'); ?>
                <button type="submit" class="button button-secondary" style="margin-top:10px;">Delete All Logs</button>
            </form>
            <?php
        }
        $html = ob_get_clean();
        if ($echo) {
            echo $html;
        } else {
            return $html;
        }
    }

    public function ajax_get_logs_table() {
        check_ajax_referer('bulk_media_import_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        $html = $this->display_imported_files_from_cpt(false);
        wp_send_json_success(array('html' => $html));
    }

    public function handle_ajax_import() {
        error_reporting(0);
        check_ajax_referer('bulk_media_import_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');

        $step = isset($_POST['step']) ? intval($_POST['step']) : 0;
        $source_type = isset($_POST['source_type']) ? sanitize_text_field($_POST['source_type']) : 'file';

        if ($step === 0) {
            $invalid_count = 0;
            if ($source_type === 'file') {
                if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                    wp_send_json_error('File upload error.');
                }
                $uploaded_file = $_FILES['file']['tmp_name'];
                $result = $this->extract_urls_from_file($uploaded_file);
                $urls = $result['valid'];
                $invalid_count = $result['invalid_count'];
            } else {
                $raw_urls = isset($_POST['urls']) ? sanitize_textarea_field($_POST['urls']) : '';
                $all_urls = array_filter(array_map('trim', explode("\n", $raw_urls)));
                $urls = array();
                foreach ($all_urls as $url) {
                    if (filter_var($url, FILTER_VALIDATE_URL)) {
                        $urls[] = $url;
                    } else {
                        $invalid_count++;
                    }
                }
                $urls = array_unique($urls);
            }

            if (count($urls) > 100) {
                $urls = array_slice($urls, 0, 100);
            }

            if (empty($urls)) {
                wp_send_json_error(sprintf('No valid URLs found. %d invalid URLs were skipped.', $invalid_count));
            }

            update_option('bulk_media_import_session', array(
                'total'           => count($urls),
                'urls'            => $urls,
                'processed'       => 0,
                'log'             => array(),
                'status'          => 'running',
                'invalid_skipped' => $invalid_count
            ));
            wp_send_json_success(array('step' => 1, 'total' => count($urls)));
        } 
        elseif ($step === 1) {
            $session = get_option('bulk_media_import_session', array());
            if (empty($session) || $session['status'] !== 'running') {
                wp_send_json_error('No active import session.');
            }
            $urls = $session['urls'];
            $total = $session['total'];
            $processed = $session['processed'];
            if ($processed < $total) {
                $current_url = $urls[$processed];
                $result = $this->import_media_from_url($current_url);
                $log_entry = array(
                    'url'           => $current_url,
                    'status'        => $result['status'],
                    'message'       => $result['message'],
                    'file_name'     => $result['file_name'] ?? '',
                    'file_size'     => $result['file_size'] ?? '',
                    'file_type'     => $result['file_type'] ?? '',
                    'attachment_id' => $result['attachment_id'] ?? 0,
                    'timestamp'     => current_time('mysql')
                );
                $log_id = 0;
                if ($result['status'] === 'success' && !empty($result['attachment_id'])) {
                    $log_id = $this->create_cpt_log_entry($result, $current_url);
                }
                $session['log'][] = $log_entry;
                $session['processed'] = $processed + 1;
                update_option('bulk_media_import_session', $session);

                $response_data = array(
                    'step'          => 1,
                    'processed'     => $session['processed'],
                    'total'         => $total,
                    'log_entry'     => $log_entry,
                    'log_id'        => $log_id,
                    'post_title'    => $log_entry['file_name'],
                    'file_size'     => $log_entry['file_size'],
                    'file_type'     => $log_entry['file_type'],
                    'source_url'    => $current_url,
                    'timestamp'     => $log_entry['timestamp'],
                    'attachment_id' => $result['attachment_id'] ?? 0,
                    'thumbnail_url' => $result['attachment_id'] ? wp_get_attachment_image_url($result['attachment_id'], array(50,50)) : ''
                );
                wp_send_json_success($response_data);
            } else {
                $session['status'] = 'completed';
                update_option('bulk_media_import_session', $session);
                $successes = 0;
                $errors = 0;
                foreach ($session['log'] as $entry) {
                    if ($entry['status'] === 'success') $successes++;
                    else $errors++;
                }
                $invalid_skipped = isset($session['invalid_skipped']) ? $session['invalid_skipped'] : 0;
                wp_send_json_success(array(
                    'step'      => 2,
                    'message'   => sprintf(
                        'Import completed! ✅ Success: %d | ❌ Failed (e.g., 404): %d | 🚫 Skipped (invalid format): %d',
                        $successes,
                        $errors,
                        $invalid_skipped
                    ),
                    'successes' => $successes,
                    'errors'    => $errors,
                    'invalid_skipped' => $invalid_skipped
                ));
            }
        }
        wp_die();
    }

    private function extract_urls_from_file($file_path) {
        $valid_urls = array();
        $invalid_count = 0;
        $content = file_get_contents($file_path);
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            if (strpos($line, ',') !== false) {
                $parts = str_getcsv($line);
                $url = isset($parts[0]) ? trim($parts[0]) : '';
            } else {
                $url = $line;
            }
            if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                $valid_urls[] = $url;
            } else {
                $invalid_count++;
            }
        }
        return array('valid' => array_unique($valid_urls), 'invalid_count' => $invalid_count);
    }

    private function import_media_from_url($url) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Pre-check content type
        $headers = @get_headers($url, 1);
        if ($headers && isset($headers['Content-Type'])) {
            $content_type = is_array($headers['Content-Type']) ? $headers['Content-Type'][0] : $headers['Content-Type'];
            if (strpos($content_type, 'image/') === false && strpos($content_type, 'video/') === false) {
                return array(
                    'status'        => 'error',
                    'message'       => 'Skipped: URL does not point to a supported image or video file.',
                    'file_name'     => '',
                    'file_size'     => '',
                    'file_type'     => '',
                    'attachment_id' => 0
                );
            }
        }

        $attachment_id = @media_sideload_image($url, 0, null, 'id');
        
        if (is_wp_error($attachment_id)) {
            $error_code = $attachment_id->get_error_code();
            $error_msg = wp_strip_all_tags($attachment_id->get_error_message());
            if (strlen($error_msg) > 100) {
                $error_msg = substr($error_msg, 0, 100) . '...';
            }
            if ($error_code === 'image_sideload_failed') {
                $error_msg = 'The URL could not be imported (maybe not a valid media file).';
            } elseif ($error_code === 'http_request_failed') {
                $error_msg = 'Failed to reach the server (check the URL or network).';
            }
            return array(
                'status'        => 'error',
                'message'       => sprintf('Failed: %s', $error_msg),
                'file_name'     => '',
                'file_size'     => '',
                'file_type'     => '',
                'attachment_id' => 0
            );
        }
        
        $file_path = get_attached_file($attachment_id);
        $file_size = file_exists($file_path) ? size_format(filesize($file_path)) : 'N/A';
        $file_type = get_post_mime_type($attachment_id);
        $file_name = basename($file_path);
        update_post_meta($attachment_id, '_source_url', $url);
        return array(
            'status'        => 'success',
            'message'       => 'Imported successfully',
            'file_name'     => $file_name,
            'file_size'     => $file_size,
            'file_type'     => $file_type,
            'attachment_id' => $attachment_id
        );
    }

    private function create_cpt_log_entry($result, $source_url) {
        $post_data = array(
            'post_title'  => sanitize_text_field($result['file_name']),
            'post_type'   => self::CPT_SLUG,
            'post_status' => 'publish',
            'meta_input'  => array(
                '_source_url' => esc_url_raw($source_url),
                '_file_size'  => $result['file_size'],
                '_file_type'  => $result['file_type'],
                '_rating'     => 0,
            )
        );
        $post_id = wp_insert_post($post_data);
        if ($post_id && !is_wp_error($post_id) && !empty($result['attachment_id'])) {
            set_post_thumbnail($post_id, $result['attachment_id']);
        }
        return $post_id;
    }

    public function handle_update_rating() {
        check_ajax_referer('bulk_media_import_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        $log_id = intval($_POST['log_id']);
        $rating = intval($_POST['rating']);
        if ($log_id && $rating >= 0 && $rating <= 5) {
            update_post_meta($log_id, '_rating', $rating);
            wp_send_json_success(array('message' => 'Rating updated.'));
        }
        wp_send_json_error('Invalid data.');
    }

    public function handle_delete_all_logs() {
        if (!wp_verify_nonce($_POST['delete_all_nonce'], 'delete_all_logs_action')) wp_die('Security check failed.');
        if (!current_user_can('manage_options')) wp_die('Unauthorized.');
        $posts = get_posts(array('post_type' => self::CPT_SLUG, 'posts_per_page' => -1, 'fields' => 'ids'));
        foreach ($posts as $pid) {
            wp_delete_post($pid, true);
        }
        wp_redirect(add_query_arg(array('page' => 'bulk-url-media-importer'), admin_url('upload.php')));
        exit;
    }

    public function handle_delete_single_log() {
        check_ajax_referer('bulk_media_import_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized.');
        $log_id = intval($_POST['log_id']);
        if ($log_id && wp_delete_post($log_id, true)) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }
}

Bulk_URL_Media_Importer::get_instance();