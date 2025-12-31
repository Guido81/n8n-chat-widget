<?php
/**
 * Uninstall script for n8n Chat Widget
 * 
 * This file is executed when the plugin is deleted from WordPress.
 * It cleans up all plugin data from the database.
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('n8n_chat_settings');

// For multisite installations
if (is_multisite()) {
    global $wpdb;
    $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
    
    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        delete_option('n8n_chat_settings');
        restore_current_blog();
    }
}

// Clear any cached data
wp_cache_flush();

