<?php

/**
 * Plugin Name: Exgrip AI Chatbot
 * Description: Gemini-powered chatbot to provide exact Exgrip item codes.
 * Version: 1.0
 * Author: Exgrip-Neo
 */

if (!defined('ABSPATH')) exit;

// Enqueue frontend files
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'exgrip-chatbot-css',
        plugin_dir_url(__FILE__) . 'public/chatbot.css'
    );

    wp_enqueue_script(
        'exgrip-chatbot-js',
        plugin_dir_url(__FILE__) . 'public/chatbot.js',
        ['jquery'],
        null,
        true
    );

    wp_localize_script('exgrip-chatbot-js', 'ExgripAI', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('exgrip_ai_chat')
    ]);
});

// Render chatbot UI
add_action('wp_footer', function () {
    include plugin_dir_path(__FILE__) . 'public/chat-ui.php';
});

// Admin menu
add_action('admin_menu', function () {
    add_menu_page(
        'Exgrip AI Chatbot',
        'Exgrip AI',
        'manage_options',
        'exgrip-ai',
        'exgrip_ai_settings_page',
        'dashicons-format-chat'
    );
});

require_once plugin_dir_path(__FILE__) . 'admin/settings-page.php';

// AJAX handlers
add_action('wp_ajax_exgrip_ai_query', 'exgrip_ai_query');
add_action('wp_ajax_nopriv_exgrip_ai_query', 'exgrip_ai_query');

function exgrip_ai_query()
{
    require plugin_dir_path(__FILE__) . 'api/gemini-handler.php';
    wp_die();
}
