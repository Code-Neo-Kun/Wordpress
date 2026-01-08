<?php
// Verify nonce for security
if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'exgrip_ai_chat')) {
    wp_send_json_error('Security verification failed');
    wp_die();
}

// Get configuration
$api_key = get_option('exgrip_gemini_api_key');
$model = get_option('exgrip_gemini_model', 'gemini-2.0-flash');
$system_prompt = get_option('exgrip_system_prompt');
$max_tokens = intval(get_option('exgrip_max_tokens', 1000));
$temperature = floatval(get_option('exgrip_temperature', 0.7));
$stop_sequences_str = get_option('exgrip_stop_sequences', '');
$context_window = intval(get_option('exgrip_context_window', 5));

// Validation
if (empty($api_key)) {
    wp_send_json_error('API key not configured. Please configure settings.');
    wp_die();
}

$user_message = sanitize_text_field($_POST['message']);
if (empty($user_message)) {
    wp_send_json_error('Empty message');
    wp_die();
}

// Parse stop sequences
$stop_sequences = array_filter(array_map('trim', explode("\n", $stop_sequences_str)));

// Process chat history for context window
$chat_history = isset($_POST['history']) ? wp_unslash($_POST['history']) : [];
if (is_string($chat_history)) {
    $chat_history = json_decode($chat_history, true);
}
$chat_history = is_array($chat_history) ? array_slice($chat_history, -$context_window) : [];

// Build contents array with system prompt and history
$contents = [];

// Add previous messages to context
foreach ($chat_history as $msg) {
    $contents[] = [
        'role' => isset($msg['role']) ? $msg['role'] : 'user',
        'parts' => [
            ['text' => $msg['text']]
        ]
    ];
}

// Add current user message
$contents[] = [
    'role' => 'user',
    'parts' => [
        ['text' => $user_message]
    ]
];

// Build API payload
$payload = [
    'contents' => $contents,
    'generationConfig' => [
        'maxOutputTokens' => $max_tokens,
        'temperature' => $temperature,
    ]
];

// Add system instruction (for gemini-1.5-pro and newer)
if (!empty($system_prompt)) {
    $payload['systemInstruction'] = [
        'parts' => [
            ['text' => $system_prompt]
        ]
    ];
}

// Add stop sequences if configured
if (!empty($stop_sequences)) {
    $payload['generationConfig']['stopSequences'] = $stop_sequences;
}

// Make API request
$response = wp_remote_post(
    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}",
    [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => wp_json_encode($payload),
        'timeout' => 30
    ]
);

// Handle connection errors
if (is_wp_error($response)) {
    wp_send_json_error('Connection error: ' . esc_html($response->get_error_message()));
    wp_die();
}

$status_code = wp_remote_retrieve_response_code($response);
$body = json_decode(wp_remote_retrieve_body($response), true);

// Log response for debugging
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Gemini API Response (' . $status_code . '): ' . wp_json_encode($body));
}

// Handle API errors
if ($status_code !== 200) {
    $error_msg = 'API Error';

    if (isset($body['error'])) {
        if (is_array($body['error'])) {
            $error_msg = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown API error';
        } else {
            $error_msg = $body['error'];
        }
    }

    // Check for specific error conditions
    if (strpos($error_msg, 'API key') !== false || strpos($error_msg, 'invalid') !== false) {
        wp_send_json_error('Invalid API key. Please check your configuration.');
    } elseif (strpos($error_msg, 'quota') !== false || strpos($error_msg, 'RESOURCE_EXHAUSTED') !== false) {
        wp_send_json_error('API quota exceeded. Please try again later.');
    } else {
        wp_send_json_error('API Error: ' . esc_html($error_msg));
    }
    wp_die();
}

// Extract response text
if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
    $ai_response = $body['candidates'][0]['content']['parts'][0]['text'];
    wp_send_json_success([
        'message' => esc_html($ai_response),
        'tokens_used' => isset($body['usageMetadata']) ? $body['usageMetadata'] : null
    ]);
} else {
    wp_send_json_error('Unexpected API response format');
}

wp_die();
