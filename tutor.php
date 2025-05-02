<?php
/**
 * AI Tutor Page for Arxer
 *
 * This page provides a specialized tutoring interface where the AI helps users learn
 * about scientific concepts through guided exploration rather than just answering questions.
 */

require_once 'config.php';
require_once 'ai_providers.php';
require_once 'favorites.php';

// Get config
$config = load_config();

// Get AI providers
$providers = $config['providers'] ?? [
    "local" => [
        "type" => "local",
        "endpoint" => "http://localhost:1234",
        "model" => "tinyllama-1.1b-chat-v1.0",
        "temperature" => 0.3,
        "max_tokens" => 2048
    ]
];

// Get API keys for display
$api_keys = [];
foreach ($providers as $name => $provider) {
    if (isset($provider['api_key'])) {
        $api_key = $provider['api_key'];
        $api_keys[$name] = substr($api_key, 0, 3) . '...' . substr($api_key, -3);
    } else {
        $api_keys[$name] = '';
    }
}

// Get default provider
$default_provider = $config['default_provider'] ?? 'local';

/**
 * Process a tutoring question and generate a teaching-focused response
 *
 * @param string $question The user's question
 * @param array|null $context_papers Papers to use as context (optional)
 * @param string|null $provider_name AI provider to use
 * @return string The AI-generated response
 */
function get_tutor_response($question, $context_papers = null, $provider_name = null) {
    if (empty($question)) {
        return "Please ask a question about the paper or concept you'd like to learn about.";
    }
    
    // Get the AI provider
    $provider = get_ai_provider($provider_name);
    
    // Build context from papers if provided
    $context = "";
    if (!empty($context_papers)) {
        $context = "I'll provide tutoring based on these papers:\n";
        
        foreach ($context_papers as $i => $paper) {
            if (isset($paper['title']) && isset($paper['authors']) && isset($paper['abstract'])) {
                $context .= "Paper " . ($i + 1) . ": {$paper['title']}\n";
                $context .= "Authors: {$paper['authors']}\n";
                $context .= "Abstract: {$paper['abstract']}\n\n";
            }
        }
    }
    
    // Create the specialized tutoring prompt
    $prompt = "You are ScienceTutor, an AI teaching assistant specializing in helping students understand scientific papers and concepts.\n" .
             "Your goal is not to simply answer questions, but to guide the student through understanding the material themselves.\n" .
             "Follow these tutoring principles:\n" .
             "1. Use the Socratic method - ask thoughtful questions that lead the student to discover answers.\n" .
             "2. Break down complex concepts into manageable parts.\n" .
             "3. Provide analogies and examples to illustrate difficult concepts.\n" .
             "4. Identify core principles and foundational knowledge the student needs.\n" .
             "5. Encourage critical thinking by asking 'why' and 'how' questions.\n" .
             "6. When explaining mathematical concepts, explain the intuition before the formalism.\n" .
             "7. Avoid giving complete solutions immediately - help the student work through the problem.\n\n";
    
    if (!empty($context)) {
        $prompt .= "Context information:\n{$context}\n";
    }
    
    $prompt .= "Student's question: {$question}\n\n";
    $prompt .= "Your teaching response:";
    
    // Generate the response using the AI provider with more tokens and moderate temperature for creativity
    $response = $provider->generate_custom_content($prompt, 1500, 0.5);
    
    // Format the response for display with the specialized tutoring format
    $formatted_response = format_tutor_response($response);
    
    return $formatted_response;
}

/**
 * Format the tutoring response with proper HTML and educational styling
 *
 * @param string $text The raw response text
 * @return string HTML formatted response
 */
function format_tutor_response($text) {
    if (empty($text)) {
        return "<p>No response generated.</p>";
    }
    
    // Process LaTeX expressions first (protect them from other formatting)
    $latexPlaceholders = [];
    $latexCounter = 0;
    
    // Replace LaTeX expressions with placeholders
    $text = preg_replace_callback('/\\cite[tp]\{([^\}]+)\}/', function($match) use (&$latexPlaceholders, &$latexCounter) {
        $placeholder = "__LATEX_PLACEHOLDER_{$latexCounter}__";
        $latexPlaceholders[] = ['placeholder' => $placeholder, 'content' => $match[0]];
        $latexCounter++;
        return $placeholder;
    }, $text);
    
    // Handle numbered lists
    // This regex looks for lines starting with numbers followed by period or parenthesis
    $text = preg_replace('/(^|\n)\s*(\d+)[.)\s]\s*([^\n]+)/', '$1<li><strong>$2.</strong> $3</li>', $text);
    
    // Wrap adjacent list items in <ol> tags
    $hasOrderedList = strpos($text, '<li>') !== false;
    if ($hasOrderedList) {
        // Group consecutive list items
        $listGroups = [];
        $currentGroup = [];
        $lines = explode("\n", $text);
        
        foreach ($lines as $line) {
            if (strpos($line, '<li>') !== false) {
                $currentGroup[] = $line;
            } else {
                if (count($currentGroup) > 0) {
                    $listGroups[] = $currentGroup;
                    $currentGroup = [];
                }
                $listGroups[] = $line;
            }
        }
        
        if (count($currentGroup) > 0) {
            $listGroups[] = $currentGroup;
        }
        
        // Process each group
        $text = '';
        foreach ($listGroups as $group) {
            if (is_array($group)) {
                $text .= "<ol class=\"list-decimal pl-8 my-4 space-y-2\">" . implode('', $group) . "</ol>";
            } else {
                $text .= $group . "\n";
            }
        }
    }
    
    // Format paragraphs (lines separated by blank lines)
    if (!$hasOrderedList) {
        $paragraphs = explode("\n\n", $text);
        $text = '';
        foreach ($paragraphs as $p) {
            // Skip if paragraph is already wrapped in HTML tags
            if (preg_match('/^\s*<[a-z]+[^>]*>/i', $p)) {
                $text .= $p . "\n";
            } else {
                $text .= "<p class=\"mb-4\">" . str_replace("\n", " ", $p) . "</p>\n";
            }
        }
    } else {
        // For text with lists, find and wrap non-list content in paragraphs
        $parts = preg_split('/(<ol[^>]*>.*?<\/ol>)/s', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $text = '';
        foreach ($parts as $part) {
            if (strpos($part, '<ol') === 0) {
                $text .= $part;
            } else {
                // Split by newlines and wrap each line in a paragraph if not empty
                $lines = explode("\n\n", $part);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!$line) continue;
                    if (preg_match('/^\s*<[a-z]+[^>]*>/i', $line)) {
                        $text .= $line . "\n";
                    } else {
                        $text .= "<p class=\"mb-4\">" . str_replace("\n", " ", $line) . "</p>\n";
                    }
                }
            }
        }
    }
    
    // Format questions the tutor asks to stand out
    $text = preg_replace('/(<p[^>]*>)([^?<>]+\?)(<\/p>)/i', '$1<span class="tutor-question">$2</span>$3', $text);
    
    // Format important concepts in bold
    $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
    
    // Add special styling for examples or analogies
    if (preg_match('/(Example:|Analogy:|For instance:)/i', $text)) {
        $text = preg_replace('/(<p[^>]*>)(Example:|Analogy:|For instance:)\s*(.+?)(<\/p>)/is', 
                          '<div class="example-box"><strong>$2</strong> $3</div>', $text);
    }
    
    // Restore LaTeX expressions
    foreach ($latexPlaceholders as $item) {
        $styledContent = "<span class=\"citation\">{$item['content']}</span>";
        $text = str_replace($item['placeholder'], $styledContent, $text);
    }
    
    // Handle paper references (e.g., Paper 1, Paper 2)
    $text = preg_replace('/\b(Paper\s+\d+)\b/', '<strong class="text-amber-400">$1</strong>', $text);
    
    // Handle block quotes
    $text = preg_replace_callback('/\n\s*>\s*([^\n]+)(\n\s*>\s*[^\n]+)*/', function($match) {
        $content = preg_replace('/\n\s*>\s*/', "\n", $match[0]);
        return "<blockquote class=\"pl-4 border-l-4 border-amber-500 mb-4 italic text-warmgray-300\">$content</blockquote>";
    }, $text);
    
    // Enhance bold text for better visibility
    $text = preg_replace('/<strong>([^<]+)<\/strong>/', '<strong class="text-amber-400">$1</strong>', $text);
    
    return "<div class='tutor-response'>" . $text . "</div>";
}

// Handle AJAX requests for tutoring responses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tutor') {
    // Get the question from the POST request
    $question = $_POST['question'] ?? '';
    
    // Get context papers if provided
    $context_papers = [];
    if (isset($_POST['context_papers'])) {
        $context_papers = json_decode($_POST['context_papers'], true);
    }
    
    // Get the provider name if specified
    $provider_name = $_POST['provider'] ?? null;
    
    // Generate the response
    $response = get_tutor_response($question, $context_papers, $provider_name);
    
    // Return the response
    echo $response;
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arxer - AI Tutor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- MathJax for LaTeX rendering -->
    <script type="text/javascript" id="MathJax-script" async
        src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js">
    </script>
    <style>
    /* Reset browser defaults for form elements */
    input, select, option, textarea {
        all: unset;
        box-sizing: border-box;
        background-color: #504A40;
        color: #E5E7EB;
        border: 1px solid #625D52;
        border-radius: 0.25rem;
        padding: 0.5rem;
        font-family: inherit;
        font-size: inherit;
    }
    
    /* Tutor Response Styling */
    .tutor-response p {
        margin-bottom: 1rem;
        line-height: 1.6;
    }
    
    .tutor-response strong {
        color: #F59E0B;
        font-weight: 600;
    }
    
    .tutor-response ol {
        margin: 1rem 0 1.5rem 1.5rem;
        list-style-type: decimal;
    }
    
    .tutor-response ul {
        margin: 1rem 0 1.5rem 1.5rem;
        list-style-type: disc;
    }
    
    .tutor-response li {
        margin-bottom: 0.5rem;
        padding-left: 0.5rem;
    }
    
    .tutor-response a {
        color: #5EEAD4;
        text-decoration: underline;
    }
    
    .tutor-response blockquote {
        margin: 1rem 0;
        padding-left: 1rem;
        border-left: 4px solid #F59E0B;
        color: #A39E93;
        font-style: italic;
    }
    
    .tutor-question {
        color: #5EEAD4; /* Different color to distinguish from chat */
        font-weight: 600;
        display: block;
        margin-bottom: 0.5rem;
    }
    
    .example-box {
        background-color: rgba(80, 74, 64, 0.5);
        border-left: 4px solid #5EEAD4; /* Different color to distinguish from chat */
        padding: 0.75rem;
        margin: 1rem 0;
        border-radius: 0.25rem;
    }
    
    body {
    background-color: #1C1917;
    color: #E5E7EB;
    font-family: system-ui, -apple-system, sans-serif;
    }
        /* Global fixes for form elements */
        select, input, textarea, option {
            background-color: #504A40 !important;
            color: #E5E7EB !important;
        }
        
        select option {
            background-color: #504A40 !important;
            color: #E5E7EB !important;
        }
        
        /* Fix for Chrome and Safari */
        @media screen and (-webkit-min-device-pixel-ratio:0) { 
            select, select option, input, textarea {
                background-color: #504A40 !important;
                color: #E5E7EB !important;
            }
        }
        
        /* Fix for Firefox */
        @-moz-document url-prefix() {
            select, select option, input, textarea {
                background-color: #504A40 !important;
                color: #E5E7EB !important;
            }
            
            select {
                -moz-appearance: none !important;
                text-indent: 0.01px;
                text-overflow: '';
            }
        }
        .typing-indicator p::after {
            content: '.';
            animation: typing 1s infinite;
        }
        @keyframes typing {
            0% { content: '.'; }
            33% { content: '..'; }
            66% { content: '...'; }
        }
        .container {
            max-width: 1200px;
        }
        .chat-container {
            height: calc(100vh - 160px);
        }
        /* Formatted tutor response styles */
        blockquote {
            border-left: 3px solid #F59E0B;
            padding-left: 1rem;
            margin: 1rem 0;
            font-style: italic;
            background: rgba(80, 74, 64, 0.3);
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
        }
        .citation {
            font-weight: 600;
            color: #F59E0B;
            display: inline-block;
            background-color: rgba(245, 158, 11, 0.1);
            padding: 0.1rem 0.5rem;
            border-radius: 0.25rem;
            margin: 0 0.1rem;
            border: 1px solid rgba(245, 158, 11, 0.3);
            font-family: monospace;
        }
        ol {
            list-style: none;
            padding-left: 1rem;
            margin: 0.75rem 0;
        }
        ol li {
            margin-bottom: 0.5rem;
            line-height: 1.5;
        }
        p {
            margin-bottom: 0.75rem;
            line-height: 1.5;
        }
        /* Collection and paper selection styling */
        .collection-header {
            padding: 0.5rem 0.5rem;
            border-radius: 0.25rem;
            transition: background-color 0.2s ease;
        }
        .collection-header:hover {
            background-color: rgba(80, 74, 64, 0.5);
        }
        .collection-papers {
            padding: 0.25rem;
            border-left: 2px solid rgba(245, 158, 11, 0.5);
            margin-left: 0.75rem;
        }
        .paper-checkbox, .collection-checkbox {
            flex-shrink: 0;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            width: 16px;
            height: 16px;
            border: 1px solid #857F72;
            border-radius: 3px;
            outline: none;
            background-color: #504A40;
            cursor: pointer;
            position: relative;
            vertical-align: middle;
        }
        
        .paper-checkbox:checked, .collection-checkbox:checked {
            background-color: #F59E0B;
            border-color: #F59E0B;
        }
        
        .paper-checkbox:checked::after, .collection-checkbox:checked::after {
            content: '\2713';
            position: absolute;
            top: 0;
            left: 3px;
            color: #FFFFFF;
            font-size: 12px;
            font-weight: bold;
        }
        
        .collection-checkbox:indeterminate {
            background-color: #857F72;
            border-color: #857F72;
        }
        
        .collection-checkbox:indeterminate::after {
            content: '-';
            position: absolute;
            top: 0;
            left: 5px;
            color: #FFFFFF;
            font-size: 12px;
            font-weight: bold;
        }
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;  
            overflow: hidden;
            max-width: calc(100% - 1.5rem);
        }
    </style>
    <script>
    tailwind.config = {
        theme: {
        screens: {
        'xs': '480px',
    'sm': '640px',
    'md': '768px',
    'lg': '1024px',
    'xl': '1280px',
    '2xl': '1536px',
    },
    extend: {
    colors: {
    warmgray: {
    50: '#FAF9F7',
    100: '#E8E6E1',
    200: '#D3CEC4',
    300: '#B8B2A7',
    400: '#A39E93',
        500: '#857F72',
            600: '#625D52',
                700: '#504A40',
                    800: '#423D33',
                        900: '#27241D',
                        },
                        amber: {
                            50: '#FFFBEB',
                            100: '#FEF3C7',
                            200: '#FDE68A',
                            300: '#FCD34D',
                            400: '#FBBF24',
                            500: '#F59E0B',
                            600: '#D97706',
                            700: '#B45309',
                            800: '#92400E',
                            900: '#78350F',
                        },
                        teal: {
                            50: '#F0FDFA',
                            100: '#CCFBF1',
                            200: '#99F6E4',
                            300: '#5EEAD4',
                            400: '#2DD4BF',
                            500: '#14B8A6',
                            600: '#0D9488',
                            700: '#0F766E',
                            800: '#115E59',
                            900: '#134E4A',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen flex flex-col">
    <header class="bg-warmgray-900 shadow-md">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <a href="index.php" class="flex items-center">
                    <i class="fas fa-atom text-amber-400 text-3xl mr-2"></i>
                    <h1 class="text-xl font-bold text-warmgray-50">Arxer</h1>
                </a>
                <div class="flex items-center">
                    <h2 class="text-lg text-amber-400 hidden sm:block mr-4">AI Tutor</h2>
                    <button id="mobile-menu-btn" class="p-1 text-amber-400 sm:hidden focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
            <!-- Mobile menu -->
            <div id="mobile-menu" class="sm:hidden hidden mt-2 py-2 border-t border-warmgray-700">
                <a href="index.php" class="block py-2 px-2 text-warmgray-300 hover:text-amber-400">
                    <i class="fas fa-search mr-2"></i>Search
                </a>
                <a href="view_favorites.php" class="block py-2 px-2 text-warmgray-300 hover:text-amber-400">
                    <i class="fas fa-folder mr-2"></i>My Collections
                </a>
                <a href="chat.php" class="block py-2 px-2 text-warmgray-300 hover:text-amber-400">
                    <i class="fas fa-robot mr-2"></i>Research Assistant
                </a>
                <a href="knowledge_graph.php" class="block py-2 px-2 text-warmgray-300 hover:text-amber-400">
                    <i class="fas fa-project-diagram mr-2"></i>Knowledge Graph
                </a>
                <a href="tutor.php" class="block py-2 px-2 text-amber-400 font-medium">
                    <i class="fas fa-graduation-cap mr-2"></i>AI Tutor
                </a>
            </div>
        </div>
    </header>
    
    <!-- Main Navigation Bar - Desktop Only -->
    <nav class="hidden sm:block bg-warmgray-800 border-b border-warmgray-700 py-2 px-4">
        <div class="container mx-auto flex items-center justify-between">
            <div class="flex space-x-4">
                <a href="index.php" class="text-warmgray-300 hover:text-amber-300 px-3 py-1 rounded hover:bg-warmgray-700">
                    <i class="fas fa-search mr-1"></i> Search
                </a>
                <a href="view_favorites.php" class="text-warmgray-300 hover:text-amber-300 px-3 py-1 rounded hover:bg-warmgray-700">
                    <i class="fas fa-folder mr-1"></i> Collections
                </a>
                <a href="chat.php" class="text-warmgray-300 hover:text-amber-300 px-3 py-1 rounded hover:bg-warmgray-700">
                    <i class="fas fa-robot mr-1"></i> Research Assistant
                </a>
                <a href="knowledge_graph.php" class="text-warmgray-300 hover:text-amber-300 px-3 py-1 rounded hover:bg-warmgray-700">
                    <i class="fas fa-project-diagram mr-1"></i> Knowledge Graph
                </a>
                <a href="tutor.php" class="text-amber-400 hover:text-amber-300 px-3 py-1 rounded hover:bg-warmgray-700">
                    <i class="fas fa-graduation-cap mr-1"></i> AI Tutor
                </a>
            </div>
            <div>
                <span class="text-warmgray-400 text-sm">Powered by <span class="text-amber-400">AI</span></span>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-4 py-4 flex-grow">
        <div class="flex flex-col lg:flex-row gap-4 h-full">
            <!-- Tutor Area -->
            <div class="flex flex-col w-full lg:w-3/4 bg-warmgray-800 rounded-lg p-4 chat-container">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-amber-400">AI Tutor</h2>
                    <div>
                        <select id="provider-select" name="provider" class="bg-warmgray-700 text-warmgray-200 rounded p-1 border border-warmgray-600 text-sm" style="background-color: #504A40 !important; color: #E5E7EB !important;">
                            <?php foreach ($providers as $name => $provider): ?>
                                <option value="<?php echo htmlspecialchars($name); ?>" <?php echo $name === $default_provider ? 'selected' : ''; ?> class="bg-warmgray-700 text-warmgray-200" style="background-color: #504A40 !important; color: #E5E7EB !important;">
                                    <?php echo htmlspecialchars($name); ?> <?php echo !empty($api_keys[$name]) ? '(' . substr($api_keys[$name], 0, 3) . '...' . substr($api_keys[$name], -3) . ')' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Messages Container -->
                <div id="tutor-messages" class="flex-grow overflow-y-auto mb-4 bg-warmgray-900 rounded-lg p-4">
                    <div class="bg-warmgray-700 p-3 rounded-lg mb-4 text-warmgray-200">
                        <p class="mb-2">👋 <span class="text-amber-400">AI Tutor</span> here! I'll help you learn about scientific concepts by:</p>
                        <ul class="list-disc ml-6 space-y-1">
                            <li>Breaking down complex papers into understandable components</li>
                            <li>Asking guiding questions to help you understand concepts</li>
                            <li>Providing helpful analogies and examples</li>
                            <li>Walking you through difficult mathematical formulations</li>
                            <li>Helping you connect ideas across different papers</li>
                        </ul>
                        <p class="mt-2">Try asking me about a paper you're struggling with, or load papers from your collections to discuss!</p>
                    </div>
                </div>
                
                <!-- Input Area -->
                <div class="flex space-x-2">
                    <input type="text" id="tutor-input" placeholder="Ask about a paper or concept you want to understand..." 
                           class="flex-grow p-3 bg-warmgray-700 text-warmgray-100 rounded border border-warmgray-600 focus:outline-none focus:ring-1 focus:ring-amber-500" 
                           style="background-color: #504A40 !important; color: #E5E7EB !important;">
                    <button id="send-question" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-warmgray-900 font-semibold rounded">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="w-full lg:w-1/4 bg-warmgray-800 rounded-lg p-4 space-y-4">
                <!-- Context Selection -->
                <div>
                    <h3 class="text-lg font-semibold text-amber-400 mb-2">Study Materials</h3>
                    <div class="bg-warmgray-700 p-3 rounded-lg space-y-2">
                        <div>
                            <label class="flex items-center text-warmgray-200 cursor-pointer">
                                <input type="radio" name="context-type" value="none" checked class="mr-2">
                                <span>No specific papers</span>
                            </label>
                        </div>
                        <div>
                            <label class="flex items-center text-warmgray-200 cursor-pointer">
                                <input type="radio" name="context-type" value="favorites" class="mr-2">
                                <span>My collections</span>
                            </label>
                            <div id="favorites-selector" class="hidden ml-5 mt-2 max-h-32 overflow-y-auto space-y-1">
                                <!-- Will be populated via JavaScript -->
                                <div class="text-sm text-warmgray-400">Loading collections...</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Sessions -->
                <div>
                    <h3 class="text-lg font-semibold text-amber-400 mb-2">Recent Sessions</h3>
                    <div class="bg-warmgray-700 p-3 rounded-lg max-h-64 overflow-y-auto" id="recent-sessions">
                        <div class="text-sm text-warmgray-400 italic">
                            Your recent tutoring sessions will appear here
                        </div>
                    </div>
                </div>
                
                <!-- Tutoring Tips -->
                <div class="bg-warmgray-700 p-3 rounded-lg">
                    <h3 class="text-md font-semibold text-amber-400 mb-1">Tutoring Tips</h3>
                    <ul class="text-sm text-warmgray-300 space-y-1 list-disc list-inside">
                        <li>Ask "how" and "why" questions for deeper understanding</li>
                        <li>Request step-by-step explanations of difficult concepts</li>
                        <li>Ask for real-world analogies to complex ideas</li>
                        <li>Try to explain concepts back to solidify understanding</li>
                        <li>Focus on one paper or concept at a time for better learning</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="mt-6 py-4 px-3 bg-warmgray-800 border-t border-warmgray-700">
        <div class="container mx-auto">
            <div class="flex flex-col sm:flex-row justify-between items-center">
                <div class="mb-2 sm:mb-0">
                    <p class="text-xs sm:text-sm text-warmgray-400 text-center sm:text-left">&copy; <?php echo date('Y'); ?> Arxer - Advanced ArXiv Research Assistant</p>
                    <p class="text-xs text-warmgray-500 mt-1">Using local AI: <?php echo htmlspecialchars($providers[$default_provider]['endpoint'] ?? 'Not configured'); ?></p>
                </div>
                <div class="flex space-x-3">
                    <a href="index.php" class="text-xs sm:text-sm text-warmgray-400 hover:text-amber-400">Search</a>
                    <a href="chat.php" class="text-xs sm:text-sm text-warmgray-400 hover:text-amber-400">Assistant</a>
                    <a href="tutor.php" class="text-xs sm:text-sm text-warmgray-400 hover:text-amber-400">AI Tutor</a>
                    <a href="knowledge_graph.php" class="text-xs sm:text-sm text-warmgray-400 hover:text-amber-400">Knowledge Graph</a>
                    <a href="view_favorites.php" class="text-xs sm:text-sm text-warmgray-400 hover:text-amber-400">Collections</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Use a more reliable CDN or local fallback -->
<script src="https://cdn.jsdelivr.net/npm/es6-promise@4/dist/es6-promise.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/es6-promise@4/dist/es6-promise.auto.min.js"></script>
    <script>
        window.MathJax = {
            tex: {
                inlineMath: [['$', '$'], ['\\(', '\\)']],
                displayMath: [['$$', '$$'], ['\\[', '\\]']],
                processEscapes: true,
                macros: {
                    citet: ['{\\text{#1}}', 1],
                    citep: ['{\\text{#1}}', 1]
                }
            },
            options: {
                skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code'],
                ignoreHtmlClass: 'tex2jax_ignore',
                processHtmlClass: 'tex2jax_process'
            },
            startup: {
                typeset: true
            }
        };
    </script>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    
    <script>
        // Initialize variables
        let contextPapers = [];
        let selectedContext = 'none';
        let tutorSessions = [];
        
        // DOM elements
        const tutorInput = document.getElementById('tutor-input');
        const sendButton = document.getElementById('send-question');
        const tutorMessages = document.getElementById('tutor-messages');
        const contextRadios = document.querySelectorAll('input[name="context-type"]');
        const favoritesSelector = document.getElementById('favorites-selector');
        const recentSessions = document.getElementById('recent-sessions');
        
        // Load favorites for context selection
        function loadFavorites() {
            try {
                const favorites = JSON.parse(localStorage.getItem('arxer_favorites')) || {};
                favoritesSelector.innerHTML = '';
                
                if (Object.keys(favorites).length === 0) {
                    favoritesSelector.innerHTML = `<div class="text-sm text-warmgray-400">No collections found</div>`;
                    return;
                }
                
                for (const collection in favorites) {
                    const collectionDiv = document.createElement('div');
                    collectionDiv.className = 'mb-2';
                    collectionDiv.innerHTML = `
                        <div class="mb-2">
                        <div class="flex items-center justify-between text-warmgray-200 cursor-pointer collection-header" data-collection="${collection}">
                            <div class="flex items-center">
                                    <input type="checkbox" name="collection" value="${collection}" class="mr-2 collection-checkbox">
                                        <span class="font-medium">${collection} (${favorites[collection].length} papers)</span>
                                    </div>
                                    <i class="fas fa-chevron-down expand-icon"></i>
                                </div>
                                <div class="collection-papers hidden ml-4 mt-2 max-h-40 overflow-y-auto space-y-1" data-collection="${collection}">
                                    ${favorites[collection].map((paper, index) => `
                                        <label class="flex items-start text-sm text-warmgray-300 cursor-pointer pb-1 ml-1 border-b border-warmgray-600 last:border-0">
                                            <input type="checkbox" name="paper" data-collection="${collection}" data-index="${index}" class="mr-2 mt-1 paper-checkbox">
                                            <span class="line-clamp-2 pt-0.5">${paper.title}</span>
                                        </label>
                                    `).join('')}
                                </div>
                            </div>
                    `;
                    favoritesSelector.appendChild(collectionDiv);
                    
                    // Add event listeners for collection interaction
                    const collectionHeader = collectionDiv.querySelector('.collection-header');
                    const checkbox = collectionDiv.querySelector('.collection-checkbox');
                    const expandIcon = collectionDiv.querySelector('.expand-icon');
                    const paperList = collectionDiv.querySelector('.collection-papers');
                    const paperCheckboxes = collectionDiv.querySelectorAll('.paper-checkbox');
                    
                    // Toggle paper list visibility when clicking the header
                    collectionHeader.addEventListener('click', function(e) {
                        // Prevent checkbox click from triggering twice
                        if (e.target === checkbox) return;
                        
                        const collection = this.dataset.collection;
                        const paperList = document.querySelector(`.collection-papers[data-collection="${collection}"]`);
                        const icon = this.querySelector('.expand-icon');
                        
                        paperList.classList.toggle('hidden');
                        icon.classList.toggle('fa-chevron-down');
                        icon.classList.toggle('fa-chevron-up');
                    });
                    
                    // Handle collection checkbox selection
                    checkbox.addEventListener('change', function() {
                        const collection = this.value;
                        const paperCheckboxes = document.querySelectorAll(`.paper-checkbox[data-collection="${collection}"]`);
                        
                        // Check/uncheck all papers in this collection
                        paperCheckboxes.forEach(box => {
                            box.checked = this.checked;
                        });
                        
                        updateContextPapers();
                    });
                    
                    // Add event listeners to paper checkboxes
                    paperCheckboxes.forEach(paperBox => {
                        paperBox.addEventListener('change', function() {
                            updateContextPapers();
                            
                            // Update collection checkbox state
                            const collection = this.dataset.collection;
                            const collectionBox = document.querySelector(`input[name="collection"][value="${collection}"]`);
                            const allPaperBoxes = document.querySelectorAll(`.paper-checkbox[data-collection="${collection}"]`);
                            const checkedPaperBoxes = document.querySelectorAll(`.paper-checkbox[data-collection="${collection}"]:checked`);
                            
                            // Determine if collection checkbox should be checked/indeterminate
                            if (checkedPaperBoxes.length === 0) {
                                collectionBox.checked = false;
                                collectionBox.indeterminate = false;
                            } else if (checkedPaperBoxes.length === allPaperBoxes.length) {
                                collectionBox.checked = true;
                                collectionBox.indeterminate = false;
                            } else {
                                collectionBox.checked = false;
                                collectionBox.indeterminate = true;
                            }
                        });
                    });
                }
            } catch (e) {
                console.error('Error loading favorites:', e);
                favoritesSelector.innerHTML = `<div class="text-sm text-warmgray-400">Error loading collections</div>`;
            }
        }
        
        // Update context papers based on selection
        function updateContextPapers() {
            contextPapers = [];
            selectedContext = document.querySelector('input[name="context-type"]:checked').value;
            console.log('Selected context type:', selectedContext);
            
            if (selectedContext === 'favorites') {
                const favorites = JSON.parse(localStorage.getItem('arxer_favorites')) || {};
                
                // First check for individually selected papers
                const selectedPaperCheckboxes = document.querySelectorAll('input[name="paper"]:checked');
                console.log('Selected individual papers:', selectedPaperCheckboxes.length);
                
                if (selectedPaperCheckboxes.length > 0) {
                    // User has selected individual papers
                    selectedPaperCheckboxes.forEach(function(checkbox) {
                        const collection = checkbox.dataset.collection;
                        const index = parseInt(checkbox.dataset.index);
                        
                        if (favorites[collection] && favorites[collection][index]) {
                            contextPapers.push(favorites[collection][index]);
                        }
                    });
                } else {
                    // No individual papers selected, fall back to whole collections
                    const selectedCollections = document.querySelectorAll('input[name="collection"]:checked');
                    console.log('Selected collections (whole):', selectedCollections.length);
                    
                    selectedCollections.forEach(function(checkbox) {
                        const collection = checkbox.value;
                        console.log('Processing collection:', collection);
                        if (favorites[collection]) {
                            const papers = favorites[collection];
                            console.log(`Adding ${papers.length} papers from collection: ${collection}`);
                            contextPapers = contextPapers.concat(papers);
                        }
                    });
                }
                
                console.log('Total context papers after selection:', contextPapers.length);
            }
            
            // Add a status message
            const statusDiv = document.createElement('div');
            statusDiv.className = 'bg-warmgray-700 p-2 rounded-lg mb-4 text-sm text-warmgray-300';
            
            if (contextPapers.length > 0) {
                // Show more detailed information about the selected papers
                let papersList = '';
                if (contextPapers.length <= 5) {
                    // Show all paper titles if there are few papers
                    papersList = contextPapers.map(paper => `<li class="ml-5 text-xs truncate">• ${paper.title}</li>`).join('');
                } else {
                    // Show just the first 3 papers and a count if there are many
                    papersList = contextPapers.slice(0, 3).map(paper => `<li class="ml-5 text-xs truncate">• ${paper.title}</li>`).join('');
                    papersList += `<li class="ml-5 text-xs">• ...and ${contextPapers.length - 3} more</li>`;
                }
                
                statusDiv.innerHTML = `
                    <p><i class="fas fa-info-circle text-amber-400 mr-1"></i> Using ${contextPapers.length} papers as study materials</p>
                    <ul class="mt-1">${papersList}</ul>
                `;
            } else {
                statusDiv.innerHTML = `<p><i class="fas fa-info-circle text-amber-400 mr-1"></i> No paper context selected</p>`;
            }
            
            // Remove any existing status messages
            const existingStatus = tutorMessages.querySelector('.context-status');
            if (existingStatus) {
                tutorMessages.removeChild(existingStatus);
            }
            
            statusDiv.classList.add('context-status');
            tutorMessages.appendChild(statusDiv);
            tutorMessages.scrollTop = tutorMessages.scrollHeight;
        }
        
        // Load tutor session history
        function loadTutorSessions() {
            try {
                const savedSessions = localStorage.getItem('arxer_tutor_sessions');
                console.log('Loading saved tutor sessions:', savedSessions ? 'found' : 'not found');
                
                if (savedSessions) {
                    tutorSessions = JSON.parse(savedSessions);
                    console.log('Loaded', tutorSessions.length, 'tutor sessions');
                } else {
                    tutorSessions = [];
                    console.log('No tutor sessions found, starting fresh');
                }
                
                updateTutorSessionsDisplay();
            } catch (e) {
                console.error('Error loading tutor sessions:', e);
                tutorSessions = [];
                updateTutorSessionsDisplay();
            }
        }
        
        // Update tutor sessions display
        function updateTutorSessionsDisplay() {
            recentSessions.innerHTML = '';
            
            if (!tutorSessions || tutorSessions.length === 0) {
                recentSessions.innerHTML = `<div class="text-sm text-warmgray-400 italic">Your recent tutoring sessions will appear here</div>`;
                return;
            }
            
            console.log('Displaying', Math.min(tutorSessions.length, 5), 'recent sessions');
            
            // Display most recent sessions first
            tutorSessions.slice(0, 5).forEach(function(session, index) {
                if (!session || !session.firstQuestion) {
                    console.error('Invalid session entry:', session);
                    return;
                }
                
                const sessionDiv = document.createElement('div');
                sessionDiv.className = 'p-2 hover:bg-warmgray-600 rounded cursor-pointer mb-1';
                sessionDiv.innerHTML = `
                    <div class="text-sm font-medium text-amber-400 truncate">${escapeHtml(session.firstQuestion)}</div>
                    <div class="text-xs text-warmgray-400">${new Date(session.timestamp).toLocaleString()}</div>
                `;
                recentSessions.appendChild(sessionDiv);
                
                // Add event listener to load this session
                sessionDiv.addEventListener('click', function() {
                    loadTutorSession(session);
                });
            });
        }
        
        // Load a specific tutor session
        function loadTutorSession(session) {
            console.log('Loading tutor session:', session.id);
            if (!session || !session.messages || !Array.isArray(session.messages)) {
                console.error('Invalid session data:', session);
                return;
            }
            
            // Clear current messages
            tutorMessages.innerHTML = ``;
            
            // Add the welcome message back
            tutorMessages.innerHTML = `
                <div class="bg-warmgray-700 p-3 rounded-lg mb-4 text-warmgray-200">
                    <p class="mb-2">👋 <span class="text-amber-400">AI Tutor</span> here! I'll help you learn about scientific concepts by:</p>
                    <ul class="list-disc ml-6 space-y-1">
                        <li>Breaking down complex papers into understandable components</li>
                        <li>Asking guiding questions to help you understand concepts</li>
                        <li>Providing helpful analogies and examples</li>
                        <li>Walking you through difficult mathematical formulations</li>
                        <li>Helping you connect ideas across different papers</li>
                    </ul>
                    <p class="mt-2">Try asking me about a paper you're struggling with, or load papers from your collections to discuss!</p>
                </div>
            `;
            
            // Add session info
            const sessionInfo = document.createElement('div');
            sessionInfo.className = 'bg-amber-700 text-warmgray-100 p-2 rounded-lg mb-4 text-xs';
            sessionInfo.innerHTML = `<p><i class="fas fa-history mr-1"></i> Viewing session from ${new Date(session.timestamp).toLocaleString()}</p>`;
            tutorMessages.appendChild(sessionInfo);
            
            // Add the messages from this session
            session.messages.forEach(function(message) {
                if (!message || !message.role || !message.content) {
                    console.error('Invalid message:', message);
                    return;
                }
                
                if (message.role === 'user') {
                    const userMsg = document.createElement('div');
                    userMsg.className = 'bg-amber-500 text-warmgray-900 p-3 rounded-lg mb-4 ml-auto max-w-3xl';
                    userMsg.innerHTML = `<p>${escapeHtml(message.content)}</p>`;
                    tutorMessages.appendChild(userMsg);
                } else {
                    const aiMsg = document.createElement('div');
                    aiMsg.className = 'bg-warmgray-700 p-3 rounded-lg mb-4 text-warmgray-200 max-w-3xl tutor-response';
                    // Use the stored formatted HTML
                    const content = message.content;
                    aiMsg.innerHTML = content;
                    tutorMessages.appendChild(aiMsg);
                    
                    // Process any LaTeX in restored messages
                    if (window.MathJax) {
                        MathJax.typesetPromise([aiMsg]).catch(function(err) {
                            console.error('MathJax error in history restore:', err);
                        });
                    }
                }
            });
            
            // Restore context papers if available
            if (session.contextPapers && session.contextPapers.length > 0) {
                contextPapers = session.contextPapers;
                
                // Add status message about context
                const statusDiv = document.createElement('div');
                statusDiv.className = 'bg-warmgray-700 p-2 rounded-lg mb-4 text-sm text-warmgray-300 context-status';
                statusDiv.innerHTML = `<p><i class="fas fa-info-circle text-amber-400 mr-1"></i> Using ${contextPapers.length} papers from previous session as context</p>`;
                tutorMessages.appendChild(statusDiv);
            }
            
            tutorMessages.scrollTop = tutorMessages.scrollHeight;
        }
        
        // Send tutor question
        function sendTutorQuestion() {
            const question = tutorInput.value.trim();
            
            if (question === '') return;
            
            // Add user message to chat
            const userMsg = document.createElement('div');
            userMsg.className = 'bg-amber-500 text-warmgray-900 p-3 rounded-lg mb-4 ml-auto max-w-3xl';
            userMsg.innerHTML = `<p>${escapeHtml(question)}</p>`;
            tutorMessages.appendChild(userMsg);
            
            // Clear input
            tutorInput.value = '';
            
            // Add typing indicator
            const typingIndicator = document.createElement('div');
            typingIndicator.className = 'bg-warmgray-700 p-3 rounded-lg mb-4 text-warmgray-200 max-w-3xl typing-indicator';
            typingIndicator.innerHTML = '<p>Thinking</p>';
            tutorMessages.appendChild(typingIndicator);
            tutorMessages.scrollTop = tutorMessages.scrollHeight;
            
            // Get the AI provider
            const provider = document.querySelector('#provider-select')?.value || 'default';
            
            // Send request to server
            const formData = new FormData();
            formData.append('action', 'tutor');
            formData.append('question', question);
            formData.append('provider', provider);
            
            // Add context papers if available
            if (contextPapers.length > 0) {
                console.log('Sending context papers:', contextPapers);
                formData.append('context_papers', JSON.stringify(contextPapers));
                
                // Add visual indicator that context is being used
                const contextInfo = document.createElement('div');
                contextInfo.className = 'bg-amber-700 text-warmgray-100 p-2 rounded-lg mb-4 text-xs';
                contextInfo.innerHTML = `<p><i class="fas fa-info-circle mr-1"></i> Using ${contextPapers.length} papers as study material</p>`;
                tutorMessages.appendChild(contextInfo);
                tutorMessages.scrollTop = tutorMessages.scrollHeight;
            } else {
                console.log('No context papers to send');
            }
            
            fetch('tutor.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Remove typing indicator
                tutorMessages.removeChild(typingIndicator);
                
                // Add AI response
                const aiMsg = document.createElement('div');
                aiMsg.className = 'bg-warmgray-700 p-3 rounded-lg mb-4 text-warmgray-200 max-w-3xl tutor-response';
                aiMsg.innerHTML = html;
                tutorMessages.appendChild(aiMsg);
                tutorMessages.scrollTop = tutorMessages.scrollHeight;
                
                // Update tutor history
                updateTutorHistory(question, html);
                
                // Render any LaTeX in the response
                if (window.MathJax) {
                    // Reset MathJax first to handle the newly formatted text
                    MathJax.typesetClear([aiMsg]);
                    MathJax.typesetPromise([aiMsg]).catch(function(err) {
                        console.error('MathJax error:', err);
                    });
                }
            })
            .catch(error => {
                // Remove typing indicator
                tutorMessages.removeChild(typingIndicator);
                
                // Show error message
                const errorMsg = document.createElement('div');
                errorMsg.className = 'bg-red-900 text-warmgray-200 p-3 rounded-lg mb-4 max-w-3xl';
                errorMsg.innerHTML = `<p>Error: ${error.message || 'Could not generate response'}</p>`;
                tutorMessages.appendChild(errorMsg);
                tutorMessages.scrollTop = tutorMessages.scrollHeight;
            });
        }
        
        // Update tutor history
        function updateTutorHistory(question, response) {
            const now = new Date();
            
            // Check if we have an active session from the last 30 minutes
            let currentSession = null;
            if (tutorSessions.length > 0) {
                const lastSession = tutorSessions[0];
                const lastTime = new Date(lastSession.timestamp);
                const timeDiff = now - lastTime; // difference in milliseconds
                
                // If last session is less than 30 minutes old, append to it
                if (timeDiff < 30 * 60 * 1000) {
                    currentSession = lastSession;
                    console.log('Adding to existing session from', lastTime.toLocaleString());
                }
            }
            
            // Create a new session if needed
            if (!currentSession) {
                currentSession = {
                    id: Date.now().toString(),
                    timestamp: now.toISOString(),
                    firstQuestion: question,
                    contextPapers: contextPapers,
                    messages: []
                };
                tutorSessions.unshift(currentSession); // Add to beginning
                console.log('Created new tutor session');
            } else {
                // Update the timestamp of the current session
                currentSession.timestamp = now.toISOString();
            }
            
            // Add messages to the session
            currentSession.messages.push(
                { role: 'user', content: question },
                { role: 'assistant', content: response }
            );
            
            // Limit history to 10 sessions
            if (tutorSessions.length > 10) {
                tutorSessions = tutorSessions.slice(0, 10);
            }
            
            // Save to localStorage
            localStorage.setItem('arxer_tutor_sessions', JSON.stringify(tutorSessions));
            console.log('Tutor sessions saved:', tutorSessions.length, 'sessions with', 
                        currentSession.messages.length/2, 'exchanges in current session');
            
            // Update display
            updateTutorSessionsDisplay();
        }
        
        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Save preferred provider to localStorage
        function savePreferredProvider(provider) {
            try {
                localStorage.setItem('arxer_preferred_provider', provider);
                console.log('Saved preferred provider:', provider);
            } catch (e) {
                console.error('Error saving preferred provider:', e);
            }
        }
        
        // Load preferred provider from localStorage
        function loadPreferredProvider() {
            try {
                const provider = localStorage.getItem('arxer_preferred_provider');
                if (provider) {
                    console.log('Loading preferred provider:', provider);
                    const select = document.getElementById('provider-select');
                    
                    // Check if this provider exists in options
                    const option = Array.from(select.options).find(opt => opt.value === provider);
                    if (option) {
                        select.value = provider;
                        console.log('Set provider to', provider);
                    } else {
                        console.log('Provider not found in options:', provider);
                    }
                } else {
                    console.log('No preferred provider found');
                }
            } catch (e) {
                console.error('Error loading preferred provider:', e);
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded - Initializing UI');
            
            // Check localStorage availability
            const testKey = '__test_storage__';
            try {
                localStorage.setItem(testKey, testKey);
                localStorage.removeItem(testKey);
                console.log('localStorage is available');
            } catch (e) {
                console.error('localStorage is not available:', e);
                // Show warning to user
                const warningDiv = document.createElement('div');
                warningDiv.className = 'bg-red-800 text-white p-3 rounded-lg mb-4';
                warningDiv.innerHTML = '<p><i class="fas fa-exclamation-triangle"></i> Warning: Local storage is not available. Tutoring history will not be saved.</p>';
                document.querySelector('main .container').prepend(warningDiv);
            }
            
            // Load favorites and tutor history
            loadFavorites();
            loadTutorSessions();
            
            // Load preferred provider
            loadPreferredProvider();
            
            // Mobile menu toggle
            const mobileMenuBtn = document.getElementById('mobile-menu-btn');
            const mobileMenu = document.getElementById('mobile-menu');
            
            if (mobileMenuBtn && mobileMenu) {
                mobileMenuBtn.addEventListener('click', function() {
                    mobileMenu.classList.toggle('hidden');
                });
            }
            
            // Send button click
            sendButton.addEventListener('click', sendTutorQuestion);
            
            // Enter key in input
            tutorInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    sendTutorQuestion();
                }
            });
            
            // Context type change
            contextRadios.forEach(function(radio) {
                radio.addEventListener('change', function() {
                    selectedContext = this.value;
                    if (selectedContext === 'favorites') {
                        favoritesSelector.classList.remove('hidden');
                    } else {
                        favoritesSelector.classList.add('hidden');
                    }
                    updateContextPapers();
                });
            });
            
            // Provider selection change
            const providerSelect = document.getElementById('provider-select');
            if (providerSelect) {
                providerSelect.addEventListener('change', function() {
                    const selectedProvider = this.value;
                    console.log('Provider changed to:', selectedProvider);
                    savePreferredProvider(selectedProvider);
                });
            }
        });
    </script>
</body>
</html>