<?php
/**
 * Research Chatbot for Arxer
 *
 * This module provides an AI-powered research assistant that can answer questions
 * about papers, explain concepts, and help with literature reviews.
 */

require_once 'config.php';
require_once 'ai_providers.php';

/**
 * Process a research question and generate a response
 *
 * @param string $question The user's research question
 * @param array|null $context_papers Papers to use as context (optional)
 * @param string|null $provider_name AI provider to use
 * @return string The AI-generated response
 */
function get_research_chat_response($question, $context_papers = null, $provider_name = null) {
    if (empty($question)) {
        return "Please ask a research question.";
    }
    
    // Get the AI provider
    $provider = get_ai_provider($provider_name);
    
    // Build context from papers if provided
    $context = "";
    if (!empty($context_papers)) {
        $context = "I'll answer based on these papers:\n";
        
        // Debug log
        error_log('Building context from ' . count($context_papers) . ' papers');
        
        foreach ($context_papers as $i => $paper) {
            if (isset($paper['title']) && isset($paper['authors']) && isset($paper['abstract'])) {
                $context .= "Paper " . ($i + 1) . ": {$paper['title']}\n";
                $context .= "Authors: {$paper['authors']}\n";
                $context .= "Abstract: {$paper['abstract']}\n\n";
                // Debug log
                error_log('Added paper: ' . substr($paper['title'], 0, 50) . '...');
            } else {
                error_log('Warning: Paper at index ' . $i . ' has missing fields: ' . json_encode($paper));
            }
        }
    } else {
        error_log('No context papers provided');
    }
    
    // Debug log final context
    error_log('Context length: ' . strlen($context) . ' characters');
    
    // Create the prompt for the research assistant
    $prompt = "You are ResearchGPT, an AI research assistant specialized in scientific literature.\n" .
             "Answer the following research question with accurate, helpful information.\n" .
             "Cite relevant papers when possible and be specific about what each paper contributes.\n\n";
    
    if (!empty($context)) {
        $prompt .= "Context information:\n{$context}\n";
    }
    
    $prompt .= "Question: {$question}\n\n";
    $prompt .= "Response:";
    
    // Log the prompt being sent to the AI
    error_log('Sending prompt with length: ' . strlen($prompt) . ' characters');
    error_log('Prompt start: ' . substr($prompt, 0, 100) . '...');
    
    // Generate the response using the AI provider with more tokens and slightly higher temperature
    $response = $provider->generate_custom_content($prompt, 1500, 0.4);
    
    // Format the response for display
    $formatted_response = format_chat_response($response);
    
    return $formatted_response;
}

/**
 * Format the chat response with proper HTML and citation styling
 *
 * @param string $text The raw response text
 * @return string HTML formatted response
 */
function format_chat_response($text) {
    if (empty($text)) {
        return "<p>No response generated.</p>";
    }
    
    // Convert line breaks to paragraphs
    $text = "<p>" . str_replace("\n\n", "</p><p>", $text) . "</p>";
    
    // Format inline citations [Author, Year]
    $text = preg_replace('/\[([^\]]+)\]/', '<span class="inline-citation">[$1]</span>', $text);
    
    // Format numbered references like [1], [2], etc.
    $text = preg_replace('/\[(\d+)\]/', '<span class="reference-number">[$1]</span>', $text);
    
    // Handle bullet points
    $text = preg_replace_callback('/<p>\s*-\s*(.+?)<\/p>/s', function($matches) {
        return "<ul><li>{$matches[1]}</li></ul>";
    }, $text);
    
    // Combine consecutive list items
    $text = preg_replace('/<\/ul>\s*<ul>/', '', $text);
    
    // Format references section if present
    if (preg_match('/References:|Bibliography:|Sources:/i', $text)) {
        $text = preg_replace('/<p>(References:|Bibliography:|Sources:)<\/p>/i', '<h4>$1</h4><div class="references">', $text);
        $text .= '</div>';
    }
    
    return "<div class='research-chat-response'>" . $text . "</div>";
}

/**
 * Handle AJAX requests for research chat responses
 *
 * @return bool True if handled, false otherwise
 */
function handle_chat_request() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'chat') {
        // Get the question from the POST request
        $question = $_POST['question'] ?? '';
        
        // Get context papers if provided
        $context_papers = [];
        if (isset($_POST['context_papers'])) {
            $context_papers = json_decode($_POST['context_papers'], true);
            // Log for debugging
            error_log('Context papers received: ' . print_r($context_papers, true));
        }
        
        // Get the provider name if specified
        $provider_name = $_POST['provider'] ?? null;
        
        // Generate the response
        $response = get_research_chat_response($question, $context_papers, $provider_name);
        
        // Return the response
        echo $response;
        exit;
    }
    
    return false;
}

// Automatically handle chat requests
handle_chat_request();