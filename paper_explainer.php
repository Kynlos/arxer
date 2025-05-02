<?php
/**
 * Paper Explainer for Arxer
 *
 * This module handles generating detailed explanations of scientific papers
 * using AI providers.
 */

require_once 'ai_providers.php';

/**
 * Generate a detailed explanation of a paper using AI
 *
 * @param array $paper The paper data (title, abstract, etc.)
 * @param string|null $provider_name The AI provider to use
 * @return string HTML-formatted explanation
 */
function generate_paper_explanation($paper, $provider_name = null) {
    if (empty($paper) || empty($paper['abstract'])) {
        return "<p>Cannot generate explanation: No abstract available.</p>";
    }

    $title = $paper['title'] ?? 'Unknown Title';
    $abstract = $paper['abstract'] ?? '';
    $authors = $paper['authors'] ?? 'Unknown Authors';

    try {
        // Get the AI provider
        $provider = get_ai_provider($provider_name);

        // Create the prompt for the explanation
        $prompt = "Explain the following scientific paper in detail.\n" .
            "Focus on the key concepts, methodology, findings, and significance.\n" .
            "Structure your explanation with clear sections.\n\n" .
            "TITLE: {$title}\n" .
            "AUTHORS: {$authors}\n" .
            "ABSTRACT: {$abstract}\n\n" .
            "Provide a comprehensive explanation with the following sections. Use clear formatting with paragraph breaks between ideas:\n" .
            "1. Overview - A brief summary of what the paper is about\n" .
            "2. Key Concepts - The main ideas and terminology used\n" .
            "3. Methodology - How the research was conducted (if mentioned)\n" .
            "4. Findings - The main results and discoveries\n" .
            "5. Significance - Why this research matters and its implications\n" .
            "6. Related Areas - How this connects to other research fields\n\n" .
            "IMPORTANT: Keep answers direct and to the point. DO NOT phrase your response like a conversation (no \"Let me explain\" or \"Would you like me to\"). " .
            "Format your response clearly with separate paragraphs for different ideas. Use proper formatting for lists. Do not ask questions at the end.";

        // Generate the explanation using the AI provider
        $ai_explanation = $provider->generate_custom_content($prompt, 2048);

        // Format the explanation as HTML with sections
        $html_explanation = '';
        $current_section = '';

        // Parse the AI response into sections
        $sections = [];
        $current_section_text = '';
        $current_section_title = 'Overview';
        
        // Clean up the AI explanation first: remove any "Do you want me to" questions that might be at the end
        $ai_explanation = preg_replace('/\s*Do you want me to.*?$/s', '', $ai_explanation);
        // Remove any "Let me" or "Okay" or similar intros
        $ai_explanation = preg_replace('/^\s*(Let me|Okay|Sure|I will)[^\n]*\n/i', '', $ai_explanation);

        foreach (explode("\n", $ai_explanation) as $line) {
            $line_stripped = trim($line);

            // Check if this is a section header
            if ((substr($line_stripped, -1) === ':' && strlen($line_stripped) < 50) ||
                substr($line_stripped, 0, 1) === '#' ||
                preg_match('/^[1-6]\.\s+\w+/', $line_stripped) || // Section numbers like "1. Introduction"
                preg_match('/^Section\s+\d+[:\.]/', $line_stripped) || // Sections like "Section 1: Overview" or "Section 1. Overview"
                preg_match('/^(Overview|Key Concepts|Methodology|Findings|Significance|Related Areas|Conclusion)/i', $line_stripped)) {

                // Save the previous section if it exists
                if (!empty($current_section_text)) {
                    $sections[] = [$current_section_title, $current_section_text];
                    $current_section_text = '';
                }

                // Extract the new section title
                if (substr($line_stripped, -1) === ':') {
                    $current_section_title = substr($line_stripped, 0, -1);
                } elseif (substr($line_stripped, 0, 1) === '#') {
                    $current_section_title = trim(substr($line_stripped, 1));
                } elseif (preg_match('/^[1-6]\.\s+(.+)$/', $line_stripped, $matches)) {
                    // For section headers like "1. Introduction", capture the entire title after the number
                    $current_section_title = trim($matches[1]);
                } elseif (preg_match('/^Section\s+\d+[:\.]\s*(.+)$/i', $line_stripped, $matches)) {
                    // For section headers like "Section 1: Overview" or "Section 1. Overview", use the part after the colon/period
                    $current_section_title = trim($matches[1]);
                } else {
                    $current_section_title = $line_stripped;
                }
            } else {
                // Add to the current section text
                if (!empty($line_stripped)) {
                    $current_section_text .= $line . "\n";
                }
            }
        }

        // Add the last section
        if (!empty($current_section_text)) {
            $sections[] = [$current_section_title, $current_section_text];
        }

        // Format sections as HTML
        foreach ($sections as [$title, $content]) {
            $html_explanation .= "<h4 class='text-lg font-semibold text-amber-400 mt-6 mb-3'>{$title}</h4>\n";

            // Format the content
            $paragraphs = explode("\n\n", $content);
            foreach ($paragraphs as $i => $paragraph) {
                if (!empty(trim($paragraph))) {
                    // Check if this is a list item
                    if (preg_match('/^\s*-|\*/m', $paragraph)) {
                        // Format as a list
                        $list_items = array_filter(
                            array_map('trim', preg_split('/\n\s*-|\*/m', $paragraph)),
                            function($item) { return !empty($item); }
                        );
                        $html_explanation .= "<ul class='ml-6 space-y-2 my-4 list-disc'>\n";
                        foreach ($list_items as $item) {
                            $html_explanation .= "<li class='pl-2'>{$item}</li>\n";
                        }
                        $html_explanation .= "</ul>\n";
                    } else if (preg_match('/^\s*\d+\./', $paragraph)) {
                        // This looks like a numbered list
                        $list_items = preg_split('/\n\s*\d+\.\s*/m', $paragraph);
                        $list_items = array_filter(array_map('trim', $list_items));
                        
                        if (!empty($list_items)) {
                            $html_explanation .= "<ol class='ml-6 space-y-2 my-4 list-decimal'>\n";
                            foreach ($list_items as $item) {
                                if (!empty($item)) {
                                    $html_explanation .= "<li class='pl-2'>{$item}</li>\n";
                                }
                            }
                            $html_explanation .= "</ol>\n";
                        }
                    } else {
                        // Regular paragraph - apply some formatting to make it look better
                        // Replace single newlines with spaces for better flow
                        $formatted_paragraph = trim(preg_replace('/(?<!\n)\n(?!\n)/', ' ', $paragraph)); 
                        
                        // Emphasize text marked with asterisks or that appears to be emphasized
                        $formatted_paragraph = preg_replace('/\*([^\*]+)\*/', '<strong class="text-amber-400">$1</strong>', $formatted_paragraph);
                        $formatted_paragraph = preg_replace('/\b(absolutely|crucial|essential|vital|critical)\b/i', '<strong class="text-amber-400">$1</strong>', $formatted_paragraph);
                        
                        $html_explanation .= "<p class='mb-4 leading-relaxed'>".$formatted_paragraph."</p>\n";
                    }

                    // No need for paragraph breaks now that we're properly closing each paragraph
                }
            }

            // No longer need to close the outer paragraph since each paragraph is closed properly
        }

        // If no sections were found, format the entire text as a single section
        if (empty($sections)) {
            $html_explanation = "<h4 class='text-lg font-semibold text-amber-400 mt-6 mb-3'>Overview</h4>\n";
            
            // Split into paragraphs and format each one properly
            $paragraphs = explode("\n\n", $ai_explanation);
            foreach ($paragraphs as $paragraph) {
                $paragraph = trim($paragraph);
                if (!empty($paragraph)) {
                    // Replace single newlines with spaces
                    $formatted_paragraph = preg_replace('/(?<!\n)\n(?!\n)/', ' ', $paragraph);
                    // Format emphasized text
                    $formatted_paragraph = preg_replace('/\*([^\*]+)\*/', '<strong class="text-amber-400">$1</strong>', $formatted_paragraph);
                    $html_explanation .= "<p class='mb-4 leading-relaxed'>{$formatted_paragraph}</p>\n";
                }
            }
        }

        // Format LaTeX equations for better rendering
        $html_explanation = preg_replace('/\\begin{equation}(.*?)\\end{equation}/s', '<div class="math-display">\\begin{equation}$1\\end{equation}</div>', $html_explanation);
        
        // Handle code blocks (typically indented or between backticks)
        $html_explanation = preg_replace('/<p>(\s*```[\s\S]*?```\s*)<\/p>/s', '<pre class="code-block">$1</pre>', $html_explanation);
        $html_explanation = preg_replace('/<p>(\s*\{\{\{[\s\S]*?\}\}\}\s*)<\/p>/s', '<pre class="code-block">$1</pre>', $html_explanation);
        
        // Replace any remaining inline code with <code> tags
        $html_explanation = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html_explanation);
        
        // Directly handle LaTeX citations without complex regex
        // Use a simpler approach based on string manipulation
        
        // First find all citation patterns
        if (preg_match_all('/\\citet{([^}]+)}/', $html_explanation, $matches, PREG_OFFSET_CAPTURE)) {
            foreach (array_reverse($matches[0]) as $i => $match) {
                list($full_match, $pos) = $match;
                $citation = $matches[1][$i][0];
                
                // Format the citation
                $citations = explode(',', $citation);
                $formatted = [];
                foreach ($citations as $c) {
                    $formatted[] = '<span class="citation">' . trim($c) . '</span>';
                }
                $replacement = implode(', ', $formatted);
                
                // Replace in the string
                $html_explanation = substr($html_explanation, 0, $pos) . 
                                   $replacement . 
                                   substr($html_explanation, $pos + strlen($full_match));
            }
        }
        
        // Handle citep pattern (parenthetical citations)
        if (preg_match_all('/\\citep{([^}]+)}/', $html_explanation, $matches, PREG_OFFSET_CAPTURE)) {
            foreach (array_reverse($matches[0]) as $i => $match) {
                list($full_match, $pos) = $match;
                $citation = $matches[1][$i][0];
                
                // Format the citation
                $citations = explode(',', $citation);
                $formatted = [];
                foreach ($citations as $c) {
                    $formatted[] = '<span class="citation">' . trim($c) . '</span>';
                }
                $replacement = '(' . implode(', ', $formatted) . ')';
                
                // Replace in the string
                $html_explanation = substr($html_explanation, 0, $pos) . 
                                   $replacement . 
                                   substr($html_explanation, $pos + strlen($full_match));
            }
        }
        
        // Format related papers section specially
        $html_explanation = preg_replace('/<h4 class=[^>]+>(Related\s*(?:Papers|Areas|Work|Research))<\/h4>\s*<p([^>]*)>([\s\S]*?)<\/p>/i', '<h4 class="text-lg font-semibold text-amber-400 mt-6 mb-3">$1</h4><div class="related-papers bg-warmgray-700 p-4 rounded-lg my-4 border-l-4 border-amber-400">$3</div>', $html_explanation);
        
        // Format bullet points in related papers nicely
        $html_explanation = preg_replace_callback('/- \\citet{([^}]+)}:? ?(.*?)(?:\n|$)/', function($matches) {
            $citation = trim($matches[1]);
            $description = isset($matches[2]) ? trim($matches[2]) : '';
            $result = '<li class="mb-2"><span class="citation bg-amber-900/30 text-amber-300 px-2 py-1 rounded font-mono text-sm">' . $citation . '</span>';
            if (!empty($description)) {
                $result .= '<span class="paper-description block ml-6 mt-1 text-warmgray-300 italic">' . $description . '</span>';
            }
            return $result;
        }, $html_explanation);
        $html_explanation = preg_replace_callback('/<div class="related-papers [^"]*">([\s\S]*?)<\/div>/s', function($matches) {
            // Replace bullet points with proper list items
            $content = preg_replace('/- ([^\n]+)/', '<li class="mb-2">$1</li>', $matches[1]);
            // Wrap content in a ul if it contains list items
            if (strpos($content, '<li') !== false) {
                $content = '<ul class="ml-4 list-disc space-y-1 mt-2">' . $content . '</ul>';
            }
            return '<div class="related-papers bg-warmgray-700 p-4 rounded-lg my-4 border-l-4 border-amber-400">' . $content . '</div>';
        }, $html_explanation);
        
        // Wrap everything in a container
        $explanation = "<div class='explanation bg-warmgray-800 p-5 rounded-lg'>\n" .
            "<h3 class='text-xl font-bold text-amber-400 mb-4'>Explanation of: {$title}</h3>\n" .
            $html_explanation .
            "<p class='text-xs text-warmgray-400 mt-4 italic text-right'>This explanation was generated by AI and may not be completely accurate.</p>\n" .
            "</div>";

        return $explanation;

    } catch (Exception $e) {
        error_log("Error generating AI explanation: " . $e->getMessage());

        // Fallback to a simple explanation if AI generation fails
        return "<div class='explanation error'>\n" .
            "<h3>Explanation of: {$title}</h3>\n" .
            "<h4>Overview</h4>\n" .
            "<p>This paper discusses concepts related to {$title}. The AI explanation could not be generated at this time.</p>\n" .
            "<h4>Key Points</h4>\n" .
            "<p>- The paper explores important concepts in this field<br>\n" .
            "- It likely presents novel methods or findings<br>\n" .
            "- The research contributes to the advancement of knowledge in this area</p>\n" .
            "<h4>Significance</h4>\n" .
            "<p>Understanding this paper can help researchers build upon existing knowledge and develop new approaches.</p>\n" .
            "<p><em>Note: The AI explanation feature encountered an error: {$e->getMessage()}</em></p>\n" .
            "</div>";
    }
}

/**
 * Handle AJAX requests for paper explanations
 *
 * This function checks if the current request is for a paper explanation
 * and returns the appropriate response.
 *
 * @return bool True if handled, false otherwise
 */
function handle_explanation_request() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'explain') {
        // Get the paper data from the POST request
        $paper = [
            'title' => $_POST['title'] ?? 'Unknown Title',
            'authors' => $_POST['authors'] ?? 'Unknown Authors',
            'abstract' => $_POST['abstract'] ?? '',
            'link' => $_POST['link'] ?? '#'
        ];
        
        // Get the provider name if specified
        $provider_name = $_POST['provider'] ?? null;
        
        // Generate the explanation
        $explanation = generate_paper_explanation($paper, $provider_name);
        
        // Return the explanation
        echo $explanation;
        exit;
    }
    
    return false;
}

// Automatically handle explanation requests
handle_explanation_request();