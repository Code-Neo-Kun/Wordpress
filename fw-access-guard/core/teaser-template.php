<?php

/**
 * Teaser Template for FW Access Guard
 *
 * This template shows a teaser of the content when content teasers are enabled
 * and the user doesn't have access to the full content.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Get the teaser content
$teaser_enabled = get_option('fwag_enable_content_teaser', '0') === '1';
if ($teaser_enabled && class_exists('FWAG_Content_Teaser')) {
    $teaser = FWAG_Content_Teaser::get_instance();
    $teaser_content = $teaser->get_teaser_content();

    if ($teaser_content) {
        echo $teaser_content;
    } else {
        // Fallback to blocked template if no teaser content
        include FWAG_PLUGIN_DIR . 'core/blocked-template.php';
    }
} else {
    // Fallback if teaser not available
    include FWAG_PLUGIN_DIR . 'core/blocked-template.php';
}

get_footer();
