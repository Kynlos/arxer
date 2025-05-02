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
            "Provide a comprehensive explanation with the following sections:\n" .
            "1. Overview - A brief summary of what the paper is about\n" .
            "2. Key Concepts - The main ideas and terminology used\n" .
            "3. Methodology - How the research was conducted (if mentioned)\n" .
            "4. Findings - The main results and discoveries\n" .
            "5. Significance - Why this research matters and its implications\n" .
            "6. Related Areas - How this connects to other research fields\n";

        // Generate the explanation using the AI provider
        $ai_explanation = $provider->generate_custom_content($prompt, 2048);

        // Format the explanation as HTML with sections
        $html_explanation = '';
        $current_section = '';

        // Parse the AI response into sections
        $sections = [];
        $current_section_text = '';
        $current_section_title = 'Overview';

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
            $html_explanation .= "<h4>{$title}</h4>\n<p>";

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
                        $html_explanation .= "<ul>\n";
                        foreach ($list_items as $item) {
                            $html_explanation .= "<li>{$item}</li>\n";
                        }
                        $html_explanation .= "</ul>\n";
                    } else {
                        // Regular paragraph
                        $formatted_paragraph = str_replace("\n", "<br>\n", $paragraph);
                        $html_explanation .= $formatted_paragraph;
                    }

                    // Add paragraph break if not the last paragraph
                    if ($i < count($paragraphs) - 1) {
                        $html_explanation .= "</p>\n<p>";
                    }
                }
            }

            $html_explanation .= "</p>\n";
        }

        // If no sections were found, format the entire text as a single section
        if (empty($sections)) {
            $html_explanation = "<h4>Overview</h4>\n<p>"
                . str_replace("\n\n", "</p>\n<p>", str_replace("\n", "<br>\n", $ai_explanation))
                . "</p>\n";
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
        $html_explanation = preg_replace('/<h4>(Related\s*(?:Papers|Areas|Work|Research))<\/h4>\s*<p>([\s\S]*?)<\/p>/i', '<h4>$1</h4><div class="related-papers">$2</div>', $html_explanation);
        
        // Format bullet points in related papers nicely
        $html_explanation = preg_replace_callback('/- \\citet{([^}]+)}:? ?(.*?)(?:\n|$)/', function($matches) {
            $citation = trim($matches[1]);
            $description = isset($matches[2]) ? trim($matches[2]) : '';
            $result = '<li><span class="citation">' . $citation . '</span>';
            if (!empty($description)) {
                $result .= '<span class="paper-description">' . $description . '</span>';
            }
            return $result;
        }, $html_explanation);
        $html_explanation = preg_replace_callback('/<div class="related-papers">([\s\S]*?)<\/div>/s', function($matches) {
            // Replace bullet points with proper list items
            $content = preg_replace('/- ([^\n]+)/', '<li>$1</li>', $matches[1]);
            // Wrap content in a ul if it contains list items
            if (strpos($content, '<li>') !== false) {
                $content = '<ul>' . $content . '</ul>';
            }
            return '<div class="related-papers">' . $content . '</div>';
        }, $html_explanation);
        
        // Wrap everything in a container
        $explanation = "<div class='explanation'>\n" .
            "<h3>Explanation of: {$title}</h3>\n" .
            $html_explanation .
            "<p><em>This explanation was generated by AI and may not be completely accurate.</em></p>\n" .
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