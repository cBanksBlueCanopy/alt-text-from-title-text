<?php

/**
 * Plugin Name: Alt Text Updater
 * Plugin URI: https://github.com/cBanksBlueCanopy/alt-text-from-title-text
 * Description: Updates missing alt text with title text if exists, or the filename for all media library images
 * Version: 1.1.0
 * Author: Chris Banks
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: alt-text-updater
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Alt_Text_Updater {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_update_alt_texts', array($this, 'ajax_update_alt_texts'));
    }

    public function add_admin_menu() {
        add_media_page(
            'Alt Text Updater',
            'Alt Text Updater',
            'manage_options',
            'alt-text-updater',
            array($this, 'admin_page')
        );
    }

    public function enqueue_scripts($hook) {
        if ($hook !== 'media_page_alt-text-updater') {
            return;
        }

        wp_enqueue_style(
            'alt-text-updater-style',
            plugins_url('assets/style.css', __FILE__)
        );

        wp_enqueue_script(
            'alt-text-updater-script',
            plugins_url('assets/script.js', __FILE__),
            array('jquery'),
            '1.0.0',
            true
        );

        wp_localize_script('alt-text-updater-script', 'altTextUpdater', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('alt_text_updater_nonce')
        ));
    }

    public function admin_page() {
    ?>
        <div class="wrap">
            <h1>Alt Text Updater</h1>
            <div class="alt-text-updater-container">
                <div class="card">
                    <h2>Update Missing Alt Text</h2>
                    <p>This tool will scan your media library and update any images that have:</p>
                    <ul>
                        <li>Missing or empty alt text</li>
                    </ul>

                    <p><strong>How it works:</strong></p>
                    <ul>
                        <li>If a title text is present, the alt text will be set to match the title text</li>
                        <li>If no title text exists, both the title and alt text will be generated from the filename</li>
                    </ul>

                    <p><strong>Filename formatting:</strong> The plugin intelligently formats filenames by replacing underscores and hyphens with spaces, adding spaces before capital letters, and applying proper title case.</p>

                    <p><strong>Examples:</strong></p>
                    <ul>
                        <li><code>my-beach-photo.jpg</code> → "My Beach Photo"</li>
                        <li><code>ProductImage_Final.png</code> → "Product Image Final"</li>
                        <li><code>SummerVacation2024.jpg</code> → "Summer Vacation 2024"</li>
                    </ul>

                    <button id="start-update" class="button button-primary button-large">
                        Start Update
                    </button>

                    <div id="progress-container" style="display:none; margin-top: 20px;">
                        <div class="progress-bar">
                            <div id="progress-fill"></div>
                        </div>
                        <p id="progress-text">Processing...</p>
                    </div>

                    <div id="results-container" style="display:none; margin-top: 20px;">
                        <h3>Results</h3>
                        <div id="results-content"></div>
                    </div>
                </div>
            </div>
        </div>
<?php
    }

    /**
     * Generate formatted text from filename
     * 
     * @param int $attachment_id The attachment ID
     * @return string Formatted text from filename
     */
    private function generate_text_from_filename($attachment_id) {
        $file_path = get_attached_file($attachment_id);

        if (!$file_path) {
            return '';
        }

        // Get filename without extension
        $filename = pathinfo($file_path, PATHINFO_FILENAME);

        if (empty($filename)) {
            return '';
        }

        // Replace underscores and hyphens with spaces
        $text = str_replace(array('_', '-'), ' ', $filename);

        // Add spaces before capital letters (but not at the start)
        $text = preg_replace('/(?<!^)(?=[A-Z])/', ' ', $text);

        // Clean up multiple spaces
        $text = preg_replace('/\s+/', ' ', $text);

        // Trim and capitalize first letter of each word
        $text = trim($text);
        $text = ucwords(strtolower($text));

        return $text;
    }

    public function ajax_update_alt_texts() {
        check_ajax_referer('alt_text_updater_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );

        $attachments = get_posts($args);
        $updated_count = 0;
        $skipped_count = 0;
        $total_count = count($attachments);

        foreach ($attachments as $attachment_id) {
            $current_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            $title = get_the_title($attachment_id);

            // If title is empty, generate from filename
            if (empty($title)) {
                $title = $this->generate_text_from_filename($attachment_id);

                // Update the post title if we generated one from filename
                if (!empty($title)) {
                    wp_update_post(array(
                        'ID' => $attachment_id,
                        'post_title' => $title
                    ));
                }
            }

            // If alt text is empty and title exists
            if (empty($current_alt) && !empty($title)) {
                update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($title));
                $updated_count++;
            } else {
                $skipped_count++;
            }
        }

        wp_send_json_success(array(
            'total' => $total_count,
            'updated' => $updated_count,
            'skipped' => $skipped_count
        ));
    }
}

// Initialize the plugin
new Alt_Text_Updater();
