<?php

/**
 * Exgrip AI Chatbot - Uninstall
 * Cleans up all plugin data when uninstalled
 */

// Exit if accessed directly
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// List of options to delete
$plugin_options = [
    'exgrip_gemini_api_key',
    'exgrip_gemini_model',
    'exgrip_system_prompt',
    'exgrip_max_tokens',
    'exgrip_temperature',
    'exgrip_stop_sequences',
    'exgrip_context_window',
];

// Delete all plugin options
foreach ($plugin_options as $option) {
    delete_option($option);
}

// Delete any transients (if using caching)
global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%exgrip_%' AND option_name LIKE '%transient%'"
);

// Log that plugin was uninstalled
error_log('Exgrip AI Chatbot plugin uninstalled - all data removed');
