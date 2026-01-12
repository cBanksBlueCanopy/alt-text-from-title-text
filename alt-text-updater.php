<?php

/**
 * Plugin Name: Alt Text Updater
 * Plugin URI: https://github.com/cBanksBlueCanopy/alt-text-from-title-text
 * Description: Updates missing alt text with title text if exists, or the filename for all media library images
 * Version: 1.2.0
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
                        <li>Alt text is applied to the parent image and automatically applies to all generated sizes (thumbnail, medium, large, etc.)</li>
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
        $text = ucfirst(strtolower($text));

        return $text;
    }

    /**
     * Get all image sizes for an attachment
     * 
     * @param int $attachment_id The attachment ID
     * @return array Array of available image sizes
     */
    private function get_image_sizes_for_attachment($attachment_id) {
        $metadata = wp_get_attachment_metadata($attachment_id);
        $sizes = array('full'); // Always include full size

        if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
            $sizes = array_merge($sizes, array_keys($metadata['sizes']));
        }

        return $sizes;
    }

    /**
     * Verify that image metadata is properly set
     * 
     * @param int $attachment_id The attachment ID
     * @return bool Whether metadata exists
     */
    private function verify_image_metadata($attachment_id) {
        $metadata = wp_get_attachment_metadata($attachment_id);
        
        // If metadata doesn't exist or is corrupted, try to regenerate it
        if (empty($metadata) || !is_array($metadata)) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $file_path = get_attached_file($attachment_id);
            
            if ($file_path && file_exists($file_path)) {
                $metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
                wp_update_attachment_metadata($attachment_id, $metadata);
                return !empty($metadata);
            }
            return false;
        }

        return true;
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
        $total_sizes_processed = 0;
        $metadata_issues = 0;

        foreach ($attachments as $attachment_id) {
            // Verify and regenerate metadata if needed
            if (!$this->verify_image_metadata($attachment_id)) {
                $metadata_issues++;
            }

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

            //Check for invalid characters within the title.
            if (!empty($title) && str_contains($title, '-')) {
                $title = sanitize_text_field($title);
                $finalTitleText = str_replace(array('-', '_'), ' ', $title);

                if (!empty($title)) {
                    wp_update_post(array(
                        'ID' => $attachment_id,
                        'post_title' => $finalTitleText
                    ));

                    $updated_count++;
                }
            }


            //Check for invalid characters within the alt text
            if (!empty($current_alt) && str_contains($current_alt, '-')) {
                $alt = sanitize_text_field($current_alt);
                $finalAltText = str_replace(array('-', '_'), ' ', $alt);
                update_post_meta($attachment_id, '_wp_attachment_image_alt', $finalAltText);
                $updated_count++;
            }


            // If alt text is empty and title exists
            if (empty($current_alt) && !empty($title)) {
                // Update alt text - this applies to all image sizes automatically
                $altText = sanitize_text_field($title);
                $finalAltText = str_replace(array('-', '_'), ' ', $altText);
                update_post_meta($attachment_id, '_wp_attachment_image_alt', $finalAltText);
                
                // Get count of image sizes for this attachment
                $image_sizes = $this->get_image_sizes_for_attachment($attachment_id);
                $total_sizes_processed += count($image_sizes);
                
                $updated_count++;
            } else {
                $skipped_count++;
            }
        }

        wp_send_json_success(array(
            'total' => $total_count,
            'updated' => $updated_count,
            'skipped' => $skipped_count,
            'total_sizes' => $total_sizes_processed,
            'metadata_issues' => $metadata_issues
        ));
    }
}

// Initialize the plugin
new Alt_Text_Updater();