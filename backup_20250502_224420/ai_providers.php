<?php
/**
 * AI Providers for Arxer
 *
 * This file contains different AI provider implementations for generating
 * paper summaries and explanations
 */

require_once 'config.php';

/**
 * Base abstract class for AI providers
 */
abstract class AIProvider {
    /**
     * Generate a summary of the abstract
     *
     * @param string $abstract The paper abstract
     * @return string The summary
     */
    abstract public function generate_summary($abstract);
    
    /**
     * Generate custom content based on a prompt
     *
     * @param string $prompt The prompt to use
     * @param int $max_tokens Maximum tokens to generate
     * @param float $temperature Temperature for generation
     * @return string The generated content
     */
    public function generate_custom_content($prompt, $max_tokens = 800, $temperature = 0.3) {
        // Default implementation
        return "Custom content generation not implemented for this provider.\nPrompt: " . substr($prompt, 0, 100) . "...";
    }
    
    /**
     * Create a simple summary by truncating the abstract
     *
     * @param string $abstract The paper abstract
     * @return string The truncated summary
     */
    public function truncate_abstract($abstract) {
        if (empty($abstract)) {
            return "No abstract available";
        }
        
        // Split the abstract into sentences
        $sentences = explode('. ', $abstract);
        
        // Take the first 2-3 sentences or 150 characters, whichever is shorter
        if (count($sentences) > 3) {
            $summary = implode('. ', array_slice($sentences, 0, 3)) . '.';
        } else {
            $summary = $abstract;
        }
        
        // Truncate if still too long
        if (strlen($summary) > 200) {
            $summary = substr($summary, 0, 197) . '...';
        }
        
        return $summary;
    }
}

/**
 * Local LM Studio provider
 */
class LocalLMStudio extends AIProvider {
    private $endpoint;
    private $model;
    private $temperature;
    private $max_tokens;
    
    /**
     * Constructor
     *
     * @param string $endpoint API endpoint URL
     * @param string $model Model name
     * @param float $temperature Temperature setting
     * @param int $max_tokens Maximum tokens to generate
     */
    public function __construct($endpoint = "http://localhost:1234", $model = "tinyllama-1.1b-chat-v1.0", $temperature = 0.3, $max_tokens = 150) {
        $this->endpoint = $endpoint;
        $this->model = $model;
        $this->temperature = $temperature;
        $this->max_tokens = $max_tokens;
    }
    
    /**
     * Generate a summary using local LM Studio server
     *
     * @param string $abstract The paper abstract
     * @return string The generated summary
     */
    public function generate_summary($abstract) {
        if (empty($abstract)) {
            return "No abstract available";
        }
        
        try {
            // Create the prompt
            $prompt = "Summarize the following scientific paper abstract in 2-3 sentences.\n" .
                     "Focus on the key findings and implications. Be direct and concise.\n\n" .
                     "ABSTRACT: {$abstract}\n\n" .
                     "SUMMARY:";
            
            // Prepare the request
            $data = [
                "model" => $this->model,
                "messages" => [
                    ["role" => "system", "content" => "You are a helpful assistant that summarizes scientific papers concisely."],
                    ["role" => "user", "content" => $prompt]
                ],
                "temperature" => $this->temperature,
                "max_tokens" => $this->max_tokens,
                "stream" => false
            ];
            
            // Make the request to the local server
            $ch = curl_init("{$this->endpoint}/v1/chat/completions");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            
            if ($err) {
                error_log("Error connecting to LM Studio: {$err}");
                return $this->truncate_abstract($abstract);
            }
            
            $response_data = json_decode($response, true);
            if (isset($response_data['choices'][0]['message']['content'])) {
                return trim($response_data['choices'][0]['message']['content']);
            } else {
                error_log("Unexpected response format from LM Studio: " . json_encode($response_data));
                return $this->truncate_abstract($abstract);
            }
            
        } catch (Exception $e) {
            error_log("Error generating summary: " . $e->getMessage());
            return $this->truncate_abstract($abstract);
        }
    }
    
    /**
     * Generate custom content using local LM Studio server
     *
     * @param string $prompt The prompt to use
     * @param int $max_tokens Maximum tokens to generate
     * @param float|null $temperature Temperature for generation
     * @return string The generated content
     */
    public function generate_custom_content($prompt, $max_tokens = 800, $temperature = null) {
        if (empty($prompt)) {
            return "No prompt provided";
        }
        
        // Use provided temperature or default to the instance's temperature
        if ($temperature === null) {
            $temperature = $this->temperature;
        }
        
        try {
            // Prepare the request
            $data = [
                "model" => $this->model,
                "messages" => [
                    ["role" => "system", "content" => "You are a helpful assistant that explains scientific concepts clearly."],
                    ["role" => "user", "content" => $prompt]
                ],
                "temperature" => $temperature,
                "max_tokens" => $max_tokens,
                "stream" => false
            ];
            
            // Make the request to the local server
            $ch = curl_init("{$this->endpoint}/v1/chat/completions");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            
            if ($err) {
                error_log("Error connecting to LM Studio for custom content: {$err}");
                return "Error generating content: {$err}";
            }
            
            $response_data = json_decode($response, true);
            if (isset($response_data['choices'][0]['message']['content'])) {
                return trim($response_data['choices'][0]['message']['content']);
            } else {
                $error = "Unexpected response format from LM Studio";
                error_log($error . ": " . json_encode($response_data));
                return "Error generating content: {$error}";
            }
            
        } catch (Exception $e) {
            error_log("Error generating custom content: " . $e->getMessage());
            return "Error generating content: " . $e->getMessage();
        }
    }
}

/**
 * OpenAI API provider
 */
class OpenAIProvider extends AIProvider {
    private $api_key;
    private $model;
    private $temperature;
    private $max_tokens;
    
    /**
     * Constructor
     *
     * @param string $api_key OpenAI API key
     * @param string $model Model name
     * @param float $temperature Temperature setting
     * @param int $max_tokens Maximum tokens to generate
     */
    public function __construct($api_key, $model = "gpt-3.5-turbo", $temperature = 0.3, $max_tokens = 150) {
        $this->api_key = $api_key;
        $this->model = $model;
        $this->temperature = $temperature;
        $this->max_tokens = $max_tokens;
    }
    
    /**
     * Generate a summary using OpenAI API
     *
     * @param string $abstract The paper abstract
     * @return string The generated summary
     */
    public function generate_summary($abstract) {
        if (empty($abstract) || empty($this->api_key)) {
            return $this->truncate_abstract($abstract);
        }
        
        try {
            // Create the prompt
            $prompt = "Summarize the following scientific paper abstract in 2-3 sentences.\n" .
                     "Focus on the key findings and implications. Be direct and concise.\n\n" .
                     "ABSTRACT: {$abstract}\n\n" .
                     "SUMMARY:";
            
            // Prepare the request
            $data = [
                "model" => $this->model,
                "messages" => [
                    ["role" => "system", "content" => "You are a helpful assistant that summarizes scientific papers concisely."],
                    ["role" => "user", "content" => $prompt]
                ],
                "temperature" => $this->temperature,
                "max_tokens" => $this->max_tokens
            ];
            
            // Make the request to the OpenAI API
            $ch = curl_init("https://api.openai.com/v1/chat/completions");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->api_key
            ]);
            
            $response = curl_exec($ch);
            $err = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($err || $status != 200) {
                error_log("OpenAI API error (status {$status}): {$err}");
                return $this->truncate_abstract($abstract);
            }
            
            $response_data = json_decode($response, true);
            if (isset($response_data['choices'][0]['message']['content'])) {
                return trim($response_data['choices'][0]['message']['content']);
            } else {
                error_log("Unexpected response format from OpenAI: " . json_encode($response_data));
                return $this->truncate_abstract($abstract);
            }
            
        } catch (Exception $e) {
            error_log("Error generating summary with OpenAI: " . $e->getMessage());
            return $this->truncate_abstract($abstract);
        }
    }
    
    /**
     * Generate custom content using OpenAI API
     *
     * @param string $prompt The prompt to use
     * @param int $max_tokens Maximum tokens to generate
     * @param float|null $temperature Temperature for generation
     * @return string The generated content
     */
    public function generate_custom_content($prompt, $max_tokens = 800, $temperature = null) {
        if (empty($prompt) || empty($this->api_key)) {
            return "No prompt provided or API key missing";
        }
        
        // Use provided temperature or default to the instance's temperature
        if ($temperature === null) {
            $temperature = $this->temperature;
        }
        
        try {
            // Prepare the request
            $data = [
                "model" => $this->model,
                "messages" => [
                    ["role" => "system", "content" => "You are a helpful assistant that explains scientific concepts clearly."],
                    ["role" => "user", "content" => $prompt]
                ],
                "temperature" => $temperature,
                "max_tokens" => $max_tokens
            ];
            
            // Make the request to the OpenAI API
            $ch = curl_init("https://api.openai.com/v1/chat/completions");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->api_key
            ]);
            
            $response = curl_exec($ch);
            $err = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($err || $status != 200) {
                error_log("OpenAI API error for custom content (status {$status}): {$err}");
                return "Error generating content: HTTP {$status} - {$err}";
            }
            
            $response_data = json_decode($response, true);
            if (isset($response_data['choices'][0]['message']['content'])) {
                return trim($response_data['choices'][0]['message']['content']);
            } else {
                $error = "Unexpected response format from OpenAI";
                error_log($error . ": " . json_encode($response_data));
                return "Error generating content: {$error}";
            }
            
        } catch (Exception $e) {
            error_log("Error generating custom content with OpenAI: " . $e->getMessage());
            return "Error generating content: " . $e->getMessage();
        }
    }
}

/**
 * Get an AI provider instance based on configuration
 *
 * @param array|string|null $provider Provider configuration or name
 * @return AIProvider The provider instance
 */
function get_ai_provider($provider = null) {
    // If a string is provided, assume it's a provider name and load the config
    if (is_string($provider)) {
        $provider = get_provider_config($provider);
    } elseif ($provider === null) {
        $provider = get_provider_config();
    }
    
    $type = $provider['type'] ?? 'local';
    
    switch ($type) {
        case 'openai':
            return new OpenAIProvider(
                $provider['api_key'] ?? '',
                $provider['model'] ?? 'gpt-3.5-turbo',
                $provider['temperature'] ?? 0.3,
                $provider['max_tokens'] ?? 150
            );
        
        case 'local':
        default:
            return new LocalLMStudio(
                $provider['endpoint'] ?? 'http://localhost:1234',
                $provider['model'] ?? 'tinyllama-1.1b-chat-v1.0',
                $provider['temperature'] ?? 0.3,
                $provider['max_tokens'] ?? 150
            );
    }
}