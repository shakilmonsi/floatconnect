<?php
/**
 * Uninstall FloatConnect
 * 
 * @package FloatConnect
 */

// Exit if accessed directly or not from WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('floatconnect_options');

// For multisite
// For multisite
if (is_multisite()) {
    global $wpdb;
    $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
    $original_blog_id = get_current_blog_id();
    
    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        delete_option('floatconnect_options');
    }
    
    switch_to_blog($original_blog_id);
}

// Clean up any transients
delete_transient('floatconnect_cache');