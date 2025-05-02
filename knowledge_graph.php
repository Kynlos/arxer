<?php
/**
 * Knowledge Graph Visualization for Arxer
 *
 * This module provides visualization of paper relationships and citation networks
 * to help researchers discover connections between papers and topics.
 */

require_once 'config.php';
require_once 'search_utils.php';
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
 * Generate a knowledge graph for a set of papers
 *
 * @param array $papers Array of papers to visualize
 * @param string|null $provider_name AI provider to use
 * @return array The graph data structure (nodes and edges)
 */
function generate_knowledge_graph($papers, $provider_name = null) {
    if (empty($papers)) {
        return ["nodes" => [], "edges" => []];
    }
    
    // Get the AI provider
    $provider = get_ai_provider($provider_name);
    
    // Initialize graph structure
    $graph = [
        "nodes" => [],
        "edges" => []
    ];
    
    // Core papers (from the input)
    $paper_ids = [];  // To track papers we've already processed
    $concepts = [];   // Key concepts extracted from papers
    
    // Process each paper to create nodes
    foreach ($papers as $idx => $paper) {
        $paper_id = "p" . $idx;
        $paper_ids[$paper['title']] = $paper_id;
        
        // Add paper as a node
        $graph["nodes"][] = [
            "id" => $paper_id,
            "label" => truncate_text($paper['title'], 40),
            "title" => $paper['title'],
            "type" => "paper",
            "authors" => $paper['authors'],
            "link" => $paper['link'],
            "size" => 25,
            "color" => "#f59e0b"  // amber color for papers
        ];
        
        // Extract key concepts from this paper
        $paper_concepts = extract_paper_concepts($paper, $provider);
        
        // Add each concept and connect to paper
        foreach ($paper_concepts as $concept) {
            $concept_id = sanitize_concept_id($concept);
            
            // Add concept if it doesn't exist yet
            if (!isset($concepts[$concept_id])) {
                $concept_node = [
                    "id" => $concept_id,
                    "label" => $concept,
                    "type" => "concept",
                    "size" => 15,
                    "color" => "#3b82f6"  // blue color for concepts
                ];
                $graph["nodes"][] = $concept_node;
                $concepts[$concept_id] = true;
            }
            
            // Add edge between paper and concept
            $graph["edges"][] = [
                "from" => $paper_id,
                "to" => $concept_id,
                "width" => 2
            ];
        }
    }
    
    // Add paper-to-paper connections based on similarity
    add_paper_connections($graph, $papers, $paper_ids);
    
    return $graph;
}

/**
 * Extract key concepts from a paper using AI
 * 
 * @param array $paper The paper data
 * @param AIProvider $provider The AI provider to use
 * @return array List of key concepts
 */
function extract_paper_concepts($paper, $provider) {
    // Default concepts in case AI fails
    $default_concepts = [];
    
    // Extract basic concepts from the title and abstract
    $text = $paper['title'] . " " . $paper['abstract'];
    preg_match_all('/\b[A-Z][a-z]+(\s+[A-Z][a-z]+){0,2}\b/', $text, $matches);
    foreach ($matches[0] as $term) {
        if (strlen($term) > 4 && !in_array($term, $default_concepts)) {
            $default_concepts[] = $term;
        }
    }
    
    // Extract categories as concepts
    if (!empty($paper['categories'])) {
        foreach ($paper['categories'] as $category) {
            $default_concepts[] = format_category_name($category);
        }
    }
    
    // Use AI to extract more sophisticated concepts
    try {
        $prompt = "Extract 3-5 key research concepts from this scientific paper.\n" .
                "List only the concepts, each on a new line. No explanations or numbering.\n\n" .
                "TITLE: {$paper['title']}\n" .
                "ABSTRACT: {$paper['abstract']}";
        
        $response = $provider->generate_custom_content($prompt, 200, 0.2);
        if (!empty($response)) {
            $ai_concepts = array_map('trim', explode("\n", $response));
            $ai_concepts = array_filter($ai_concepts, function($concept) {
                return !empty($concept) && strlen($concept) <= 50;
            });
            
            // If AI returned usable concepts, use those instead
            if (count($ai_concepts) >= 2) {
                return array_slice($ai_concepts, 0, 5);
            }
        }
    } catch (Exception $e) {
        error_log("Error extracting concepts with AI: " . $e->getMessage());
    }
    
    // Fall back to default concepts if AI failed
    return array_slice($default_concepts, 0, 5);
}

/**
 * Format a category ID into a readable name
 * 
 * @param string $category Category ID
 * @return string Formatted category name
 */
function format_category_name($category) {
    $category = str_replace('cs.', 'Computer Science: ', $category);
    $category = str_replace('math.', 'Mathematics: ', $category);
    $category = str_replace('physics.', 'Physics: ', $category);
    $category = str_replace('astro-ph', 'Astrophysics', $category);
    $category = str_replace('cond-mat', 'Condensed Matter', $category);
    $category = str_replace('quant-ph', 'Quantum Physics', $category);
    
    return ucwords($category);
}

/**
 * Add connections between papers based on similarity
 * 
 * @param array &$graph The graph to update
 * @param array $papers The papers to connect
 * @param array $paper_ids Mapping of paper titles to IDs
 */
function add_paper_connections(&$graph, $papers, $paper_ids) {
    // For each paper pair, calculate similarity and add an edge if they are similar
    for ($i = 0; $i < count($papers); $i++) {
        for ($j = $i + 1; $j < count($papers); $j++) {
            $similarity = calculate_paper_similarity($papers[$i], $papers[$j]);
            
            // If similarity is above threshold, add an edge
            if ($similarity > 0.3) {  // Threshold for showing a connection
                $graph["edges"][] = [
                    "from" => $paper_ids[$papers[$i]['title']],
                    "to" => $paper_ids[$papers[$j]['title']],
                    "width" => $similarity * 5,  // Edge width based on similarity
                    "color" => ["opacity" => $similarity]
                ];
            }
        }
    }
}

/**
 * Calculate similarity between two papers
 * 
 * @param array $paper1 First paper
 * @param array $paper2 Second paper
 * @return float Similarity score (0-1)
 */
function calculate_paper_similarity($paper1, $paper2) {
    // Simple Jaccard similarity based on words in title and abstract
    $text1 = strtolower($paper1['title'] . ' ' . $paper1['abstract']);
    $text2 = strtolower($paper2['title'] . ' ' . $paper2['abstract']);
    
    // Tokenize and filter
    $words1 = array_filter(preg_split('/\W+/', $text1), function($word) {
        return strlen($word) > 3;  // Filter out short words
    });
    
    $words2 = array_filter(preg_split('/\W+/', $text2), function($word) {
        return strlen($word) > 3;  // Filter out short words
    });
    
    // Calculate Jaccard similarity
    $intersection = array_intersect($words1, $words2);
    $union = array_unique(array_merge($words1, $words2));
    
    if (count($union) === 0) {
        return 0;
    }
    
    return count($intersection) / count($union);
}

/**
 * Sanitize a concept to create a valid ID
 * 
 * @param string $concept The concept text
 * @return string Sanitized ID
 */
function sanitize_concept_id($concept) {
    // Remove special characters and spaces, convert to lowercase
    $id = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $concept));
    return 'c_' . $id;  // Prefix with 'c_' to ensure it starts with a letter
}

/**
 * Truncate text to a specific length
 * 
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @return string Truncated text
 */
function truncate_text($text, $length = 30) {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length - 3) . '...';
}

/**
 * Handle AJAX requests for knowledge graph generation
 */
function handle_knowledge_graph_request() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_graph') {
        // Get papers data from the POST request
        $papers = isset($_POST['papers']) ? json_decode($_POST['papers'], true) : [];
        
        // Get the provider name if specified
        $provider_name = $_POST['provider'] ?? null;
        
        // Generate the graph
        $graph = generate_knowledge_graph($papers, $provider_name);
        
        // Return the JSON encoded graph data
        header('Content-Type: application/json');
        echo json_encode($graph);
        exit;
    }
}

// Check if this is an AJAX request or a page load
if (isset($_POST['action'])) {
    handle_knowledge_graph_request();
} else {
    // Show the knowledge graph page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Arxer - Knowledge Graph</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/vis/4.21.0/vis.min.css">
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
            #graph-container {
                height: calc(100vh - 250px);
                min-height: 500px;
            }
            .container {
                max-width: 1200px;
            }
            .vis-network:focus {
            outline: none;
            }
        /* Custom checkbox styling */
        input[type="checkbox"] {
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
        
        input[type="checkbox"]:checked {
            background-color: #F59E0B;
            border-color: #F59E0B;
        }
        
        input[type="checkbox"]:checked::after {
            content: '\2713';
            position: absolute;
            top: 0;
            left: 3px;
            color: #FFFFFF;
            font-size: 12px;
            font-weight: bold;
        }
        
        input[type="checkbox"]:indeterminate {
            background-color: #857F72;
            border-color: #857F72;
        }
        
        input[type="checkbox"]:indeterminate::after {
            content: '-';
            position: absolute;
            top: 0;
            left: 5px;
            color: #FFFFFF;
            font-size: 12px;
            font-weight: bold;
        }
        
        /* Better alignment for checkbox labels */
        label span {
            padding-top: 2px;
            display: inline-block;
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
                        <h2 class="text-lg text-amber-400 hidden sm:block mr-4">Knowledge Graph</h2>
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
                    <a href="knowledge_graph.php" class="block py-2 px-2 text-amber-400 font-medium">
                        <i class="fas fa-project-diagram mr-2"></i>Knowledge Graph
                    </a>
                    <a href="tutor.php" class="block py-2 px-2 text-warmgray-300 hover:text-amber-400">
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
                    <a href="knowledge_graph.php" class="text-amber-400 hover:text-amber-300 px-3 py-1 rounded hover:bg-warmgray-700">
                        <i class="fas fa-project-diagram mr-1"></i> Knowledge Graph
                    </a>
                    <a href="tutor.php" class="text-warmgray-300 hover:text-amber-300 px-3 py-1 rounded hover:bg-warmgray-700">
                        <i class="fas fa-graduation-cap mr-1"></i> AI Tutor
                    </a>
                </div>
                <div>
                    <span class="text-warmgray-400 text-sm">Powered by <span class="text-amber-400">AI</span></span>
                </div>
            </div>
        </nav>

        <main class="container mx-auto px-4 py-4 flex-grow">
            <div class="flex flex-col lg:flex-row gap-4">
                <!-- Graph Visualization Area -->
                <div class="w-full lg:w-3/4 bg-warmgray-800 rounded-lg p-4">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold text-amber-400">Research Knowledge Graph</h2>
                        <div>
                            <select id="graph-type" class="bg-warmgray-700 text-warmgray-200 rounded p-1 border border-warmgray-600 text-sm" style="background-color: #504A40 !important; color: #E5E7EB !important;">
                                <option value="concepts" class="bg-warmgray-700 text-warmgray-200" style="background-color: #504A40 !important; color: #E5E7EB !important;">Concept Map</option>
                                <option value="papers" class="bg-warmgray-700 text-warmgray-200" style="background-color: #504A40 !important; color: #E5E7EB !important;">Paper Connections</option>
                                <option value="combined" class="bg-warmgray-700 text-warmgray-200" style="background-color: #504A40 !important; color: #E5E7EB !important;">Combined View</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="graph-container" class="bg-warmgray-900 rounded-lg">
                        <!-- Graph will be rendered here -->
                        <div class="flex items-center justify-center h-full text-warmgray-400" id="graph-placeholder">
                            <div class="text-center">
                                <i class="fas fa-project-diagram text-4xl mb-2"></i>
                                <p>Select papers to visualize connections</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-between mt-4">
                        <div>
                            <button id="reset-view" class="px-3 py-1 bg-warmgray-700 text-warmgray-200 rounded hover:bg-warmgray-600">
                                <i class="fas fa-sync-alt mr-1"></i> Reset View
                            </button>
                        </div>
                        <div>
                            <select name="provider" class="bg-warmgray-700 text-warmgray-200 rounded p-1 border border-warmgray-600 text-sm" style="background-color: #504A40 !important; color: #E5E7EB !important;">
                                <?php foreach ($providers as $name => $provider): ?>
                                    <option value="<?php echo htmlspecialchars($name); ?>" <?php echo $name === $default_provider ? 'selected' : ''; ?> class="bg-warmgray-700 text-warmgray-200" style="background-color: #504A40 !important; color: #E5E7EB !important;">
                                        <?php echo htmlspecialchars($name); ?> <?php echo !empty($api_keys[$name]) ? '(' . substr($api_keys[$name], 0, 3) . '...' . substr($api_keys[$name], -3) . ')' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Control Panel -->
                <div class="w-full lg:w-1/4 bg-warmgray-800 rounded-lg p-4 space-y-4">
                    <!-- Paper Selection -->
                    <div>
                        <h3 class="text-lg font-semibold text-amber-400 mb-2">Paper Sources</h3>
                        <div class="bg-warmgray-700 p-3 rounded-lg space-y-2">
                            <div>
                                <label class="flex items-center text-warmgray-200 cursor-pointer">
                                    <input type="radio" name="paper-source" value="favorites" checked class="mr-2">
                                    <span>My Collections</span>
                                </label>
                                <div id="favorites-selector" class="ml-5 mt-2 max-h-32 overflow-y-auto space-y-1">
                                    <!-- Will be populated via JavaScript -->
                                    <div class="text-sm text-warmgray-400">Loading collections...</div>
                                </div>
                            </div>
                            <div>
                                <label class="flex items-center text-warmgray-200 cursor-pointer">
                                    <input type="radio" name="paper-source" value="search" class="mr-2">
                                    <span>Recent Search Results</span>
                                </label>
                            </div>
                            <div>
                                <label class="flex items-center text-warmgray-200 cursor-pointer">
                                    <input type="radio" name="paper-source" value="custom" class="mr-2">
                                    <span>Research Area</span>
                                </label>
                                <div id="custom-area" class="hidden ml-5 mt-2">
                                    <input type="text" id="research-area" placeholder="e.g., quantum computing" 
                                        class="w-full p-2 text-sm bg-warmgray-600 text-warmgray-100 rounded border border-warmgray-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition duration-200"
                                        style="background-color: #504A40 !important; color: #E5E7EB !important;">
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button id="generate-graph" class="w-full px-4 py-2 bg-amber-500 hover:bg-amber-600 text-warmgray-900 font-semibold rounded">
                                <i class="fas fa-magic mr-1"></i> Generate Graph
                            </button>
                        </div>
                    </div>
                    
                    <!-- Legend -->
                    <div class="bg-warmgray-700 p-3 rounded-lg">
                        <h3 class="text-md font-semibold text-amber-400 mb-2">Legend</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex items-center">
                                <span class="w-3 h-3 rounded-full bg-amber-400 inline-block mr-2"></span>
                                <span class="text-warmgray-200">Papers</span>
                            </div>
                            <div class="flex items-center">
                                <span class="w-3 h-3 rounded-full bg-blue-500 inline-block mr-2"></span>
                                <span class="text-warmgray-200">Concepts</span>
                            </div>
                            <div class="flex items-center">
                                <div class="h-0.5 w-8 bg-warmgray-400 mr-2"></div>
                                <span class="text-warmgray-200">Connections (thicker = stronger relation)</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tips -->
                    <div class="bg-warmgray-700 p-3 rounded-lg">
                        <h3 class="text-md font-semibold text-amber-400 mb-1">Tips</h3>
                        <ul class="text-sm text-warmgray-300 space-y-1 list-disc list-inside">
                            <li>Click on papers to open them</li>
                            <li>Drag nodes to rearrange the graph</li>
                            <li>Use mouse wheel to zoom in/out</li>
                            <li>Compare multiple collections to see overlapping concepts</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>

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

        <script src="https://cdnjs.cloudflare.com/ajax/libs/vis/4.21.0/vis.min.js"></script>
        
        <script>
            // DOM elements
            const graphContainer = document.getElementById('graph-container');
            const graphPlaceholder = document.getElementById('graph-placeholder');
            const generateGraphBtn = document.getElementById('generate-graph');
            const paperSourceRadios = document.querySelectorAll('input[name="paper-source"]');
            const favoritesSelector = document.getElementById('favorites-selector');
            const customArea = document.getElementById('custom-area');
            const researchArea = document.getElementById('research-area');
            const resetViewBtn = document.getElementById('reset-view');
            const graphTypeSelect = document.getElementById('graph-type');
            
            // Network and graph data
            let network = null;
            let graphData = null;
            let selectedPapers = [];
            
            // Load favorites for selection
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
                            <label class="flex items-center text-warmgray-200 cursor-pointer hover:bg-warmgray-600 rounded px-2 py-1">
                            <input type="checkbox" name="collection" value="${collection}" class="mr-2">
                            <span class="font-medium">${collection} (${favorites[collection].length} papers)</span>
                            </label>
                        `;
                        favoritesSelector.appendChild(collectionDiv);
                    }
                } catch (e) {
                    console.error('Error loading favorites:', e);
                    favoritesSelector.innerHTML = `<div class="text-sm text-warmgray-400">Error loading collections</div>`;
                }
            }
            
            // Get papers based on selection
            function getSelectedPapers() {
                const paperSource = document.querySelector('input[name="paper-source"]:checked').value;
                
                if (paperSource === 'favorites') {
                    // Get papers from selected collections
                    const selectedCollections = document.querySelectorAll('input[name="collection"]:checked');
                    if (selectedCollections.length === 0) {
                        alert('Please select at least one collection');
                        return [];
                    }
                    
                    const favorites = JSON.parse(localStorage.getItem('arxer_favorites')) || {};
                    let papers = [];
                    
                    selectedCollections.forEach(function(checkbox) {
                        const collection = checkbox.value;
                        if (favorites[collection]) {
                            papers = papers.concat(favorites[collection]);
                        }
                    });
                    
                    return papers;
                } else if (paperSource === 'search') {
                    // Get papers from recent search results
                    const searchHistory = JSON.parse(localStorage.getItem('arxer_history')) || [];
                    if (searchHistory.length === 0 || !searchHistory[0].papers) {
                        alert('No recent search results found');
                        return [];
                    }
                    
                    return searchHistory[0].papers;
                } else if (paperSource === 'custom') {
                    // Will fetch papers based on research area
                    const area = researchArea.value.trim();
                    if (!area) {
                        alert('Please enter a research area');
                        return [];
                    }
                    
                    // Here we would normally fetch papers from ArXiv API
                    // For now, just return an empty array and show message
                    alert('This feature requires server-side integration to fetch papers from ArXiv. Coming soon!');
                    return [];
                }
                
                return [];
            }
            
            // Generate the knowledge graph
            function generateGraph() {
                selectedPapers = getSelectedPapers();
                
                if (selectedPapers.length === 0) {
                    return;
                }
                
                // Show loading state
                graphPlaceholder.innerHTML = '<div class="flex items-center justify-center h-full"><div class="text-center"><i class="fas fa-spinner fa-spin text-4xl mb-2"></i><p>Generating knowledge graph...</p></div></div>';
                graphPlaceholder.style.display = 'flex';
                
                // Get the AI provider
                const provider = document.querySelector('select[name="provider"]')?.value || 'default';
                
                // Send request to server
                const formData = new FormData();
                formData.append('action', 'generate_graph');
                formData.append('papers', JSON.stringify(selectedPapers));
                formData.append('provider', provider);
                
                fetch('knowledge_graph.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    // Hide placeholder
                    graphPlaceholder.style.display = 'none';
                    
                    // Store graph data
                    graphData = data;
                    
                    // Initialize visualization
                    initializeNetwork(data);
                })
                .catch(error => {
                    graphPlaceholder.innerHTML = `<div class="flex items-center justify-center h-full"><div class="text-center text-red-500"><i class="fas fa-exclamation-circle text-4xl mb-2"></i><p>Error: ${error.message || 'Could not generate graph'}</p></div></div>`;
                    graphPlaceholder.style.display = 'flex';
                });
            }
            
            // Initialize or update the network visualization
            function initializeNetwork(data) {
                // If network already exists, destroy it
                if (network) {
                    network.destroy();
                    network = null;
                }
                
                // Filter data based on graph type
                const graphType = graphTypeSelect.value;
                let filteredData = {
                    nodes: [...data.nodes],
                    edges: [...data.edges]
                };
                
                if (graphType === 'papers') {
                    // Only show paper-to-paper connections
                    filteredData.nodes = data.nodes.filter(node => node.type === 'paper');
                    filteredData.edges = data.edges.filter(edge => {
                        const sourceNode = data.nodes.find(n => n.id === edge.from);
                        const targetNode = data.nodes.find(n => n.id === edge.to);
                        return sourceNode && targetNode && sourceNode.type === 'paper' && targetNode.type === 'paper';
                    });
                } else if (graphType === 'concepts') {
                    // Only show paper-to-concept connections
                    filteredData.edges = data.edges.filter(edge => {
                        const sourceNode = data.nodes.find(n => n.id === edge.from);
                        const targetNode = data.nodes.find(n => n.id === edge.to);
                        return sourceNode && targetNode && 
                              ((sourceNode.type === 'paper' && targetNode.type === 'concept') ||
                               (sourceNode.type === 'concept' && targetNode.type === 'paper'));
                    });
                }
                
                // Create visualization dataset
                const visData = {
                    nodes: new vis.DataSet(filteredData.nodes),
                    edges: new vis.DataSet(filteredData.edges)
                };
                
                // Configuration options
                const options = {
                    nodes: {
                        shape: 'dot',
                        font: {
                            color: '#E5E7EB',
                            face: 'Roboto, Arial'
                        },
                        borderWidth: 2,
                        shadow: true
                    },
                    edges: {
                        width: 2,
                        shadow: true,
                        smooth: {
                            type: 'continuous'
                        }
                    },
                    physics: {
                        stabilization: true,
                        barnesHut: {
                            gravitationalConstant: -3000,
                            springConstant: 0.04,
                            springLength: 150
                        }
                    },
                    interaction: {
                        hover: true,
                        tooltipDelay: 200,
                        zoomView: true,
                        dragView: true
                    }
                };
                
                // Create the network
                network = new vis.Network(graphContainer, visData, options);
                
                // Handle click events
                network.on('click', function(properties) {
                    if (properties.nodes.length > 0) {
                        const nodeId = properties.nodes[0];
                        const node = filteredData.nodes.find(n => n.id === nodeId);
                        
                        if (node && node.type === 'paper') {
                            // Open paper link in new tab
                            window.open(node.link, '_blank');
                        }
                    }
                });
            }
            
            // Event listeners
            document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileMenuBtn = document.getElementById('mobile-menu-btn');
            const mobileMenu = document.getElementById('mobile-menu');
            
            if (mobileMenuBtn && mobileMenu) {
                mobileMenuBtn.addEventListener('click', function() {
                    mobileMenu.classList.toggle('hidden');
                });
            }
            
            // Load favorites
            loadFavorites();
                
                // Paper source selection change
                paperSourceRadios.forEach(function(radio) {
                    radio.addEventListener('change', function() {
                        if (this.value === 'favorites') {
                            favoritesSelector.classList.remove('hidden');
                            customArea.classList.add('hidden');
                        } else if (this.value === 'custom') {
                            favoritesSelector.classList.add('hidden');
                            customArea.classList.remove('hidden');
                        } else {
                            favoritesSelector.classList.add('hidden');
                            customArea.classList.add('hidden');
                        }
                    });
                });
                
                // Generate graph button
                generateGraphBtn.addEventListener('click', generateGraph);
                
                // Reset view button
                resetViewBtn.addEventListener('click', function() {
                    if (network) {
                        network.fit();
                    }
                });
                
                // Graph type change
                graphTypeSelect.addEventListener('change', function() {
                    if (graphData) {
                        initializeNetwork(graphData);
                    }
                });
            });
        </script>
    </body>
    </html>
    <?php
}