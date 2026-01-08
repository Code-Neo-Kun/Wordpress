<?php
function exgrip_ai_settings_page()
{
    // Capability check
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }

    if (isset($_POST['save_settings'])) {
        // Nonce verification
        if (!isset($_POST['exgrip_ai_nonce']) || !wp_verify_nonce($_POST['exgrip_ai_nonce'], 'exgrip_ai_settings')) {
            wp_die('Security check failed');
        }

        update_option('exgrip_gemini_api_key', sanitize_text_field($_POST['api_key']));
        update_option('exgrip_gemini_model', sanitize_text_field($_POST['model']));
        update_option('exgrip_system_prompt', wp_kses_post($_POST['system_prompt']));
        update_option('exgrip_max_tokens', intval($_POST['max_tokens']));
        update_option('exgrip_temperature', floatval($_POST['temperature']));
        update_option('exgrip_stop_sequences', sanitize_textarea_field($_POST['stop_sequences']));
        update_option('exgrip_context_window', intval($_POST['context_window']));

        echo '<div class="updated"><p>Settings saved successfully.</p></div>';
    }

    $api_key = get_option('exgrip_gemini_api_key', '');
    $model = get_option('exgrip_gemini_model', 'gemini-2.0-flash');
    $prompt = get_option('exgrip_system_prompt', '');
    $max_tokens = get_option('exgrip_max_tokens', 1000);
    $temperature = get_option('exgrip_temperature', 0.7);
    $stop_sequences = get_option('exgrip_stop_sequences', '');
    $context_window = get_option('exgrip_context_window', 5);

    $available_models = [
        'gemini-2.5-flash' => 'Gemini 2.5 Flash (Latest)',
        'gemini-2.0-flash' => 'Gemini 2.0 Flash',
        'gemini-1.5-flash' => 'Gemini 1.5 Flash',
        'gemini-1.5-pro'   => 'Gemini 1.5 Pro',
    ];
?>
    <div class="wrap">
        <h1>Exgrip AI Chatbot Settings</h1>
        <form method="post">
            <?php wp_nonce_field('exgrip_ai_settings', 'exgrip_ai_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th>Gemini API Key</th>
                    <td>
                        <input type="password" name="api_key" value="<?php echo esc_attr($api_key); ?>" style="width: 400px;" required>
                        <p class="description">Get your API key from <a href="https://ai.google.dev/" target="_blank">Google AI Studio</a></p>
                    </td>
                </tr>
                <tr>
                    <th>Gemini Model</th>
                    <td>
                        <select name="model" style="width: 300px;">
                            <?php foreach ($available_models as $model_id => $model_name): ?>
                                <option value="<?php echo esc_attr($model_id); ?>" <?php selected($model, $model_id); ?>>
                                    <?php echo esc_html($model_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Select which Gemini model to use for responses.</p>
                    </td>
                </tr>
                <tr>
                    <th>Max Output Tokens</th>
                    <td>
                        <input type="number" name="max_tokens" value="<?php echo esc_attr($max_tokens); ?>" min="100" max="4096" style="width: 150px;">
                        <p class="description">Maximum length of AI responses (100-4096). Lower values save tokens and costs. Default: 1000</p>
                    </td>
                </tr>
                <tr>
                    <th>Temperature</th>
                    <td>
                        <input type="number" name="temperature" value="<?php echo esc_attr($temperature); ?>" min="0" max="2" step="0.1" style="width: 150px;">
                        <p class="description">Controls response creativity (0.0-2.0):<br>
                            • <strong>0.0-0.3</strong> = More factual & concise (for technical info)<br>
                            • <strong>0.7</strong> = Balanced (recommended)<br>
                            • <strong>1.0+</strong> = More creative & varied
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>Stop Sequences</th>
                    <td>
                        <textarea name="stop_sequences" rows="4" style="width: 100%; font-family: monospace;"><?php echo esc_textarea($stop_sequences); ?></textarea>
                        <p class="description">Enter one stop sequence per line. Model stops generating text when it encounters these. Examples: "User:", "---", etc.</p>
                    </td>
                </tr>
                <tr>
                    <th>Context Window (Messages)</th>
                    <td>
                        <input type="number" name="context_window" value="<?php echo esc_attr($context_window); ?>" min="1" max="20" style="width: 150px;">
                        <p class="description">Number of previous messages to include in context (1-20). Reduces token usage by limiting chat history. Default: 5</p>
                    </td>
                </tr>
                <tr>
                    <th>System Prompt</th>
                    <td>
                        <textarea name="system_prompt" rows="15" style="width: 100%;"><?php echo esc_textarea($prompt); ?></textarea>
                        <p class="description"><strong>Tip:</strong> Write concise prompts. Example: "You are a technical support bot. Always provide answers in 2-3 sentences. Be factual and specific."</p>
                    </td>
                </tr>
            </table>
            <p>
                <input type="submit" name="save_settings" class="button-primary" value="Save Settings">
            </p>
        </form>
    </div>
<?php }
