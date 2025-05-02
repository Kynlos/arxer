<?php
/**
 * Configuration management for Arxer
 */

// Configuration file path
define('CONFIG_FILE', __DIR__ . '/../ai_config.json');

// History file path
$user_home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? __DIR__;
define('HISTORY_FILE', $user_home . '/.arxer_history.json');

// Favorites file path
define('FAVORITES_FILE', __DIR__ . '/.arxer_favorites.json');

/**
 * Load AI configuration from JSON file
 *
 * @return array Configuration array
 */
function load_config() {
    $default_config = [
        "default_provider" => "local",
        "providers" => [
            "local" => [
                "type" => "local",
                "endpoint" => "http://localhost:1234",
                "model" => "tinyllama-1.1b-chat-v1.0",
                "temperature" => 0.3,
                "max_tokens" => 2048
            ],
            "gemma" => [
                "type" => "local",
                "endpoint" => "http://localhost:1234",
                "model" => "gemma-3-4b-it-qat",
                "temperature" => 0.3,
                "max_tokens" => 4096
            ]
        ]
    ];
    
    if (file_exists(CONFIG_FILE)) {
        $config = json_decode(file_get_contents(CONFIG_FILE), true);
        if ($config) {
            return $config;
        }
    }
    
    return $default_config;
}

/**
 * Get an AI provider configuration
 *
 * @param string|null $provider_name The name of the provider to use (default from config if null)
 * @return array Provider configuration
 */
function get_provider_config($provider_name = null) {
    $config = load_config();
    
    // If no provider specified, use the default
    if (!$provider_name) {
        $provider_name = $config['default_provider'] ?? 'local';
    }
    
    // Get the provider configuration
    $providers = $config['providers'] ?? [];
    if (!isset($providers[$provider_name])) {
        $provider_name = $config['default_provider'] ?? 'local';
    }
    
    return $providers[$provider_name] ?? $providers['local'] ?? [
        "type" => "local",
        "endpoint" => "http://localhost:1234",
        "model" => "tinyllama-1.1b-chat-v1.0",
        "temperature" => 0.3,
        "max_tokens" => 2048
    ];
}

/**
 * Get all AI providers configuration
 *
 * @return array All provider configurations
 */
function get_ai_providers_config() {
    $config = load_config();
    return $config['providers'] ?? [
        "local" => [
            "type" => "local",
            "endpoint" => "http://localhost:1234",
            "model" => "tinyllama-1.1b-chat-v1.0",
            "temperature" => 0.3,
            "max_tokens" => 2048
        ]
    ];
}

/**
 * Get API keys configuration
 *
 * @return array API keys for providers
 */
function get_api_keys_config() {
    $config = load_config();
    $providers = $config['providers'] ?? [];
    $api_keys = [];
    
    foreach ($providers as $name => $provider) {
        if (isset($provider['api_key'])) {
            // Only show first 3 and last 3 characters of API key for security
            $api_key = $provider['api_key'];
            $api_keys[$name] = substr($api_key, 0, 3) . '...' . substr($api_key, -3);
        } else {
            $api_keys[$name] = '';
        }
    }
    
    return $api_keys;
}

/**
 * Get default provider name
 *
 * @return string Default provider name
 */
function get_default_provider() {
    $config = load_config();
    return $config['default_provider'] ?? 'local';
}