<?php
/**
 * Arxer - ArXiv Paper Browser (PHP Version)
 *
 * This is the main entry point for the Arxer web application.
 */

require_once 'config.php';
require_once 'paper_explainer.php';
require_once 'ai_providers.php';
require_once 'search_utils.php';
require_once 'favorites.php';
require_once 'paper_recommendations.php';

// Initialize variables
$search_results = [];
$error_message = '';
$query = '';
$max_results = 10;
$include_summaries = false;
$provider_name = null;
$sort_by = 'relevance';

// Handle search form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['query'])) {
    $raw_query = trim($_POST['query']);
    $max_results = isset($_POST['max_results']) ? intval($_POST['max_results']) : 10;
    $include_summaries = isset($_POST['include_summaries']) && $_POST['include_summaries'] === 'on';
    $provider_name = $_POST['provider'] ?? null;
    $sort_by = $_POST['sort_by'] ?? 'relevance';
    $categories = isset($_POST['categories']) ? $_POST['categories'] : [];
    
    if (!empty($raw_query)) {
        // Parse advanced search query
        $parsed_query = parse_advanced_query($raw_query);
        
        // Add selected categories to the query if any
        if (!empty($categories)) {
            $parsed_query['category'] = array_merge($parsed_query['category'], $categories);
        }
        
        // Build the ArXiv API query
        $arxiv_query = build_arxiv_query($parsed_query);
        
        // If no query was built (e.g., only empty fields), use the raw query
        if (empty($arxiv_query)) {
            // Check for special field terms like "astrophysics"
            $lower_query = strtolower($raw_query);
            if ($lower_query === 'astrophysics') {
                $arxiv_query = 'cat:astro-ph*';
            } elseif ($lower_query === 'physics') {
                $arxiv_query = 'cat:physics*';
            } elseif ($lower_query === 'mathematics' || $lower_query === 'math') {
                $arxiv_query = 'cat:math*';
            } elseif ($lower_query === 'computer science' || $lower_query === 'cs') {
                $arxiv_query = 'cat:cs*';
            } else {
                $arxiv_query = 'all:' . $raw_query;
            }
        }
        
        // Search ArXiv with the built query
        $search_results = search_arxiv($arxiv_query, $max_results);
        
        // Additional filtering for criteria not supported by ArXiv API
        $filters = [
            'date_from' => $parsed_query['date_from'],
            'date_to' => $parsed_query['date_to'],
            'categories' => $categories
        ];
        
        // Apply any client-side filters
        $search_results = filter_search_results($search_results, $filters);
        
        // Sort results based on user preference
        if (!empty($search_results)) {
            sort_search_results($search_results, $sort_by);
        }
        
        // Generate summaries if requested
        if ($include_summaries && !empty($search_results)) {
            foreach ($search_results as &$paper) {
                $provider = get_ai_provider($provider_name);
                $paper['summary'] = $provider->generate_summary($paper['abstract']);
            }
        }
        
        // Store the original query for display
        $query = $raw_query;
        
        // JS will save search history to localStorage
    } else {
        $error_message = 'Please enter a search query.';
    }
}

/**
 * Sort search results based on selected criteria
 *
 * @param array &$papers Array of paper results to sort
 * @param string $sort_by Sorting criteria
 */
function sort_search_results(&$papers, $sort_by) {
    switch ($sort_by) {
        case 'date-desc': // Newest first
            usort($papers, function($a, $b) {
                return strcmp($b['date'] ?? '', $a['date'] ?? '');
            });
            break;
            
        case 'date-asc': // Oldest first
            usort($papers, function($a, $b) {
                return strcmp($a['date'] ?? '', $b['date'] ?? '');
            });
            break;
            
        case 'title': // Alphabetical by title
            usort($papers, function($a, $b) {
                return strcmp($a['title'] ?? '', $b['title'] ?? '');
            });
            break;
            
        case 'relevance': // Default order from ArXiv API
        default:
            // No sorting needed, ArXiv API sorts by relevance by default
            break;
    }
}

/**
 * Search ArXiv for papers matching the query
 *
 * @param string $query The search query
 * @param int $max_results Maximum number of results to return
 * @return array Array of paper data
 */
function search_arxiv($query, $max_results = 10) {
    $papers = [];
    
    try {
        // URL encode the query
        $encoded_query = urlencode($query);
        
        // Build the ArXiv API URL
        $url = "http://export.arxiv.org/api/query?" .
               "search_query={$encoded_query}&" .
               "max_results={$max_results}&" .
               "sortBy=relevance";
        
        // Fetch results from ArXiv API
        $response = file_get_contents($url);
        
        if ($response === false) {
            throw new Exception("Failed to connect to ArXiv API");
        }
        
        // Parse the XML response
        $xml = simplexml_load_string($response);
        
        if ($xml === false) {
            throw new Exception("Failed to parse ArXiv API response");
        }
        
        // Get entries
        $entries = $xml->entry;
        
        // Process each entry
        foreach ($entries as $entry) {
            // Extract namespaces
            $namespaces = $entry->getNamespaces(true);
            
            // Get authors
            $author_names = [];
            foreach ($entry->author as $author) {
                $author_names[] = (string)$author->name;
            }
            
            // Format publish date
            $published = new DateTime((string)$entry->published);
            
            // Extract categories directly from ArXiv response
            $arxiv_categories = [];
            // Try to get primary category
            if (isset($entry->children('http://arxiv.org/schemas/atom')->primary_category)) {
                $primary_cat = (string)$entry->children('http://arxiv.org/schemas/atom')->primary_category->attributes()->term;
                $arxiv_categories[] = $primary_cat;
            }
            
            // Get all categories
            if (isset($entry->category)) {
                foreach ($entry->category as $category) {
                    $cat = (string)$category->attributes()->term;
                    if (!in_array($cat, $arxiv_categories)) {
                        $arxiv_categories[] = $cat;
                    }
                }
            }
            
            // Get inferred categories based on title/abstract
            $inferred_categories = get_paper_categories(trim((string)$entry->title), trim((string)$entry->summary));
            
            // Create the paper object
            $papers[] = [
                'title' => trim((string)$entry->title),
                'authors' => implode(", ", $author_names),
                'abstract' => trim((string)$entry->summary),
                'link' => (string)$entry->id,
                'date' => $published->format('Y-m-d'),
                'categories' => array_unique(array_merge($arxiv_categories, $inferred_categories)),
                'summary' => '' // Will be filled if summaries are requested
            ];
        }
        
        return $papers;
        
    } catch (Exception $e) {
        error_log("Error searching ArXiv: " . $e->getMessage());
        return [];
    }
}

/**
 * Get categories for a paper based on content
 *
 * @param string $title Paper title
 * @param string $abstract Paper abstract
 * @return array Categories
 */
function get_paper_categories($title, $abstract) {
    // Define categories and their associated keywords
    $categories = [
        "Machine Learning" => ["machine learning", "neural network", "deep learning", "ai", "artificial intelligence",
                           "reinforcement learning", "supervised", "unsupervised", "classification", "regression"],
        "Computer Vision" => ["computer vision", "image", "object detection", "segmentation", "recognition",
                           "convolutional", "cnn", "gan", "generative adversarial"],
        "Natural Language Processing" => ["nlp", "natural language", "text", "language model", "transformer",
                                       "bert", "gpt", "word embedding", "sentiment", "translation"],
        "Robotics" => ["robot", "autonomous", "navigation", "manipulation", "control system", "actuator", "sensor"],
        "Quantum Computing" => ["quantum", "qubit", "entanglement", "superposition", "quantum algorithm",
                             "quantum error", "quantum gate"],
        "Physics" => ["physics", "particle", "quantum field", "relativity", "mechanics", "thermodynamics",
                   "electromagnetism", "cosmology", "astrophysics"],
        "Mathematics" => ["mathematics", "theorem", "proof", "algebra", "geometry", "topology", "calculus",
                       "optimization", "probability", "statistics"],
        "Biology" => ["biology", "gene", "protein", "cell", "molecular", "organism"],
        "Materials Science" => ["material", "polymer", "composite", "alloy", "nanomaterial"],
        "Dark Matter" => ["dark matter", "dark energy", "wimp", "axion", "detector", "neutrino"]
    ];
    
    $content = strtolower($title . " " . $abstract);
    $paper_categories = [];
    
    foreach ($categories as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $paper_categories[] = $category;
                break; // Found a match for this category, move to next category
            }
        }
    }
    
    // If no categories found, mark as 'Other'
    if (empty($paper_categories)) {
        $paper_categories[] = 'Other';
    }
    
    return $paper_categories;
}

/**
 * Save search to history
 *
 * @param string $query The search query
 * @param array $papers The search results
 * @param int $max_history Maximum number of history entries to keep
 */
function save_search_history($query, $papers, $max_history = 20) {
    // Load existing history
    $history = get_search_history();
    
    // Create new history entry
    $entry = [
        'query' => $query,
        'timestamp' => date('Y-m-d H:i:s'),
        'num_results' => count($papers),
        'papers' => array_slice($papers, 0, 5) // Only store the first 5 papers to save space
    ];
    
    // Add to history and keep only the most recent entries
    array_unshift($history, $entry);
    $history = array_slice($history, 0, $max_history);
    
    // Save to file
    try {
        file_put_contents(HISTORY_FILE, json_encode($history, JSON_PRETTY_PRINT));
    } catch (Exception $e) {
        error_log("Warning: Could not save search history: " . $e->getMessage());
    }
}

/**
 * Get search history
 *
 * @return array Search history
 */
function get_search_history() {
    if (!file_exists(HISTORY_FILE)) {
        return [];
    }
    
    try {
        $content = file_get_contents(HISTORY_FILE);
        $history = json_decode($content, true);
        
        if (!is_array($history)) {
            return [];
        }
        
        // Sort by timestamp, most recent first
        usort($history, function($a, $b) {
            return strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? '');
        });
        
        return $history;
    } catch (Exception $e) {
        error_log("Error loading search history: " . $e->getMessage());
        return [];
    }
}

/**
 * Create a citation for a paper
 *
 * @param array $paper The paper data
 * @param string $format Citation format
 * @return string Formatted citation
 */
function create_paper_citation($paper, $format = "bibtex") {
    try {
        // Safely get paper properties with default values
        $title = $paper['title'] ?? 'Unknown Title';
        $authors = $paper['authors'] ?? 'Unknown Authors';
        $date = $paper['date'] ?? '2023';
        $link = $paper['link'] ?? '';
        $abstract = $paper['abstract'] ?? '';
        $arxiv_id = preg_match('/arxiv\.org\/abs\/([0-9v.]+)/', $link, $matches) ? $matches[1] : '';
        
        // Extract year from date
        if (preg_match('/\b(19|20)\d{2}\b/', $date, $year_match)) {
            $year = $year_match[0];
        } else {
            $year = '2023'; // Default year
        }
        
        // Get author last name for the key and format authors for different citation styles
        $author_parts = explode(",", $authors);
        $first_author = $author_parts[0] ?? 'Unknown';
        $key_name = preg_replace('/[^\w]/', '', explode(' ', $first_author)[count(explode(' ', $first_author)) - 1] ?? 'Unknown');
        
        // Parse authors for different citation styles
        $parsed_authors = parse_authors($authors);
        
        // Current date for accessed dates
        $current_date = date('d F Y');
        $short_date = date('d/m/Y'); // For OU-Harvard format
        
        // Generate citation based on format
        switch (strtolower($format)) {
            case 'bibtex':
                // Clean title for BibTeX
                $clean_title = str_replace(['&', '_'], ['\\&', '\\_'], $title);
                return "@article{" . $key_name . $year . ",\n" .
                       "  title = {" . $clean_title . "},\n" .
                       "  author = {" . $authors . "},\n" .
                       "  journal = {arXiv preprint},\n" .
                       "  year = {" . $year . "},\n" .
                       "  url = {" . $link . "},\n" .
                       "  eprint = {" . $arxiv_id . "},\n" .
                       "  archivePrefix = {arXiv}\n" .
                       "}";

            case 'ris':
                // RIS format for EndNote, Reference Manager
                $ris = "TY  - JOUR\r\n";
                $ris .= "T1  - " . $title . "\r\n";
                foreach ($author_parts as $author) {
                    $ris .= "AU  - " . trim($author) . "\r\n";
                }
                $ris .= "Y1  - " . $year . "\r\n";
                $ris .= "JO  - arXiv preprint\r\n";
                $ris .= "UR  - " . $link . "\r\n";
                $ris .= "LA  - en\r\n";
                $ris .= "ER  - ";
                return $ris;
                
            case 'apa':
                return "{$parsed_authors['apa']} ({$year}). {$title}. arXiv preprint. {$link}";
                
            case 'mla':
                return "{$parsed_authors['mla']}. \"{$title}.\" arXiv preprint, {$year}. Web.";
                
            case 'chicago':
                return "{$parsed_authors['chicago']}. \"{$title}.\" arXiv preprint ({$year}). {$link}.";
                
            case 'harvard':
                return "{$parsed_authors['harvard']} ({$year}) '{$title}', arXiv preprint, Available at: {$link} (Accessed: {$current_date}).";
                
            case 'ou-harvard':
                // Open University Harvard style
                return "{$parsed_authors['harvard']} ({$year}) '{$title}', arXiv [Online]. Available at: {$link} (Accessed: {$short_date}).";

            case 'ieee':
                return "{$parsed_authors['ieee']}, \"{$title},\" arXiv preprint arXiv:{$arxiv_id}, {$year}.";

            case 'vancouver':
                return "{$parsed_authors['vancouver']}. {$title}. arXiv preprint arXiv:{$arxiv_id}. {$year}.";
                
            case 'ama':
                return "{$parsed_authors['ama']}. {$title}. arXiv preprint. Published online {$date}. {$link}";

            case 'cse':
                return "{$parsed_authors['cse']}. {$year}. {$title}. arXiv preprint arXiv:{$arxiv_id}.";

            default:
                return "{$authors} - {$title} - {$year} - {$link}";
        }
    } catch (Exception $e) {
        error_log("Error generating citation: " . $e->getMessage());
        return "Error generating citation: " . $e->getMessage();
    }
}

/**
 * Parse authors string into various citation formats
 *
 * @param string $authors Comma-separated authors string
 * @return array Formatted authors for different citation styles
 */
function parse_authors($authors) {
    $author_parts = explode(",", $authors);
    $num_authors = count($author_parts);
    
    // Trim each author name
    $author_parts = array_map('trim', $author_parts);
    
    // APA style: First author Last, F. M., & subsequent authors Last, F. M.
    $apa = '';
    foreach ($author_parts as $i => $author) {
        $name_parts = explode(' ', $author);
        $last_name = end($name_parts);
        $initials = '';
        
        // Get initials from all parts except the last (which is the last name)
        for ($j = 0; $j < count($name_parts) - 1; $j++) {
            if (!empty($name_parts[$j])) {
                $initials .= strtoupper(substr($name_parts[$j], 0, 1)) . '. ';
            }
        }
        
        if ($i === 0) {
            $apa .= $last_name . ', ' . $initials;
        } elseif ($i === $num_authors - 1) {
            $apa .= '& ' . $last_name . ', ' . $initials;
        } else {
            $apa .= $last_name . ', ' . $initials . ', ';
        }
    }
    
    // MLA style: First author Last, First M., and subsequent authors
    $mla = '';
    foreach ($author_parts as $i => $author) {
        $name_parts = explode(' ', $author);
        $last_name = end($name_parts);
        $first_name = count($name_parts) > 1 ? implode(' ', array_slice($name_parts, 0, -1)) : '';
        
        if ($i === 0) {
            $mla .= $last_name . ', ' . $first_name;
        } elseif ($i === $num_authors - 1) {
            $mla .= ' and ' . $first_name . ' ' . $last_name;
        } else {
            $mla .= ', ' . $first_name . ' ' . $last_name;
        }
    }
    
    // Chicago style: First author Last, First, and subsequent authors
    $chicago = '';
    foreach ($author_parts as $i => $author) {
        $name_parts = explode(' ', $author);
        $last_name = end($name_parts);
        $first_name = count($name_parts) > 1 ? implode(' ', array_slice($name_parts, 0, -1)) : '';
        
        if ($i === 0) {
            $chicago .= $last_name . ', ' . $first_name;
        } elseif ($i === $num_authors - 1) {
            $chicago .= ' and ' . $first_name . ' ' . $last_name;
        } else {
            $chicago .= ', ' . $first_name . ' ' . $last_name;
        }
    }
    
    // Harvard style: First author Last, F. and subsequent authors Last, F.
    $harvard = '';
    foreach ($author_parts as $i => $author) {
        $name_parts = explode(' ', $author);
        $last_name = end($name_parts);
        $initials = '';
        
        // Get initials
        for ($j = 0; $j < count($name_parts) - 1; $j++) {
            if (!empty($name_parts[$j])) {
                $initials .= strtoupper(substr($name_parts[$j], 0, 1)) . '. ';
            }
        }
        
        if ($i === 0) {
            $harvard .= $last_name . ', ' . $initials;
        } elseif ($i === $num_authors - 1) {
            $harvard .= 'and ' . $last_name . ', ' . $initials;
        } else {
            $harvard .= ', ' . $last_name . ', ' . $initials;
        }
    }
    
    // IEEE style: F. M. Last, F. M. Last, and F. M. Last
    $ieee = '';
    foreach ($author_parts as $i => $author) {
        $name_parts = explode(' ', $author);
        $last_name = end($name_parts);
        $initials = '';
        
        // Get initials
        for ($j = 0; $j < count($name_parts) - 1; $j++) {
            if (!empty($name_parts[$j])) {
                $initials .= strtoupper(substr($name_parts[$j], 0, 1)) . '. ';
            }
        }
        
        if ($i === 0) {
            $ieee .= $initials . $last_name;
        } elseif ($i === $num_authors - 1) {
            $ieee .= ', and ' . $initials . $last_name;
        } else {
            $ieee .= ', ' . $initials . $last_name;
        }
    }
    
    // Vancouver style: Last FM, Last FM, Last FM.
    $vancouver = '';
    foreach ($author_parts as $i => $author) {
        $name_parts = explode(' ', $author);
        $last_name = end($name_parts);
        $initials = '';
        
        // Get initials without periods or spaces
        for ($j = 0; $j < count($name_parts) - 1; $j++) {
            if (!empty($name_parts[$j])) {
                $initials .= strtoupper(substr($name_parts[$j], 0, 1));
            }
        }
        
        if ($i === 0) {
            $vancouver .= $last_name . ' ' . $initials;
        } else {
            $vancouver .= ', ' . $last_name . ' ' . $initials;
        }
    }
    
    // AMA style: Last FM, Last FM, Last FM, et al.
    $ama = '';
    $max_ama_authors = 6;
    $shown_authors = min($num_authors, $max_ama_authors);
    
    for ($i = 0; $i < $shown_authors; $i++) {
        $author = $author_parts[$i];
        $name_parts = explode(' ', $author);
        $last_name = end($name_parts);
        $initials = '';
        
        // Get initials without periods or spaces
        for ($j = 0; $j < count($name_parts) - 1; $j++) {
            if (!empty($name_parts[$j])) {
                $initials .= strtoupper(substr($name_parts[$j], 0, 1));
            }
        }
        
        if ($i === 0) {
            $ama .= $last_name . ' ' . $initials;
        } else {
            $ama .= ', ' . $last_name . ' ' . $initials;
        }
    }
    
    if ($num_authors > $max_ama_authors) {
        $ama .= ', et al';
    }
    
    // CSE style: Last FM, Last FM, Last FM
    $cse = '';
    foreach ($author_parts as $i => $author) {
        $name_parts = explode(' ', $author);
        $last_name = end($name_parts);
        $initials = '';
        
        // Get initials without spaces
        for ($j = 0; $j < count($name_parts) - 1; $j++) {
            if (!empty($name_parts[$j])) {
                $initials .= strtoupper(substr($name_parts[$j], 0, 1));
            }
        }
        
        if ($i === 0) {
            $cse .= $last_name . ' ' . $initials;
        } else {
            $cse .= ', ' . $last_name . ' ' . $initials;
        }
    }
    
    return [
        'apa' => rtrim($apa),
        'mla' => rtrim($mla),
        'chicago' => rtrim($chicago),
        'harvard' => rtrim($harvard),
        'ieee' => rtrim($ieee),
        'vancouver' => rtrim($vancouver),
        'ama' => rtrim($ama),
        'cse' => rtrim($cse)
    ];
}

// Get provider list from config
$config = load_config();
$providers = $config['providers'] ?? [];
$default_provider = $config['default_provider'] ?? 'local';

// Get API keys (redacted for UI)
$api_keys = [];
foreach ($providers as $name => $provider) {
    if (isset($provider['api_key']) && !empty($provider['api_key'])) {
        $api_keys[$name] = '••••' . substr($provider['api_key'], -4);
    } else {
        $api_keys[$name] = '';
    }
}

// Include the HTML part below
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arxer - ArXiv Paper Browser</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- MathJax for LaTeX rendering -->
    <script type="text/javascript" id="MathJax-script" async
        src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js">
    </script>
    <script>
        window.MathJax = {
            tex: {
                inlineMath: [['$', '$'], ['\\(', '\\)']],
                displayMath: [['$$', '$$'], ['\\[', '\\]']],
                processEscapes: true
            },
            options: {
                skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code'],
                ignoreHtmlClass: 'tex2jax_ignore',
                processHtmlClass: 'tex2jax_process'
            }
        };
    </script>
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
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
        .paper-card {
            background-color: #423D33;
            border: 1px solid #504A40;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.2s ease-in-out;
        }
        .paper-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .more-btn, .citation-toggle, .related-toggle {
            cursor: pointer;
            color: #F59E0B;
        }
        .more-btn:hover, .citation-toggle:hover, .related-toggle:hover {
            color: #FBBF24;
        }
        main {
            flex: 1;
        }
        /* Mobile menu styles */
        .mobile-menu-btn {
            display: none;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .desktop-nav {
                display: none;
            }
            .mobile-menu-btn {
                display: block;
            }
            .mobile-menu {
                display: none;
                position: fixed;
                top: 60px;
                left: 0;
                right: 0;
                background-color: #423D33;
                padding: 1rem;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                z-index: 50;
                border-bottom: 1px solid #504A40;
            }
            .mobile-menu.active {
                display: block;
            }
            .mobile-menu a {
                display: block;
                padding: 0.75rem;
                border-bottom: 1px solid rgba(80, 74, 64, 0.5);
            }
            .mobile-menu a:last-child {
                border-bottom: none;
            }
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 50;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.7);
        }
        .modal-content {
            background-color: #504A40;
            margin: 5% auto;
            padding: 16px;
            border: 1px solid #625D52;
            border-radius: 0.5rem;
            width: 95%;
            max-width: 800px;
            max-height: 85vh;
            overflow-y: auto;
        }
        
        @media (min-width: 640px) {
            .modal-content {
                width: 80%;
                padding: 20px;
            }
        }
        .close {
            color: #A39E93;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close:hover, .close:focus {
            color: #F59E0B;
            text-decoration: none;
            cursor: pointer;
        }
        .category {
            display: inline-block;
            background-color: #625D52;
            color: #FDE68A;
            padding: 0.2rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 0.25rem;
            margin-right: 0.25rem;
            margin-bottom: 0.25rem;
            transition: all 0.2s ease;
        }
        
        .category:hover {
            background-color: #504A40;
        }
        
        @media (max-width: 640px) {
            .categories {
                flex-wrap: wrap;
                margin-bottom: 0.5rem;
            }
            
            .paper-card h3 {
                font-size: 1rem;
                line-height: 1.4;
            }
        }
        .date-badge {
            background-color: #423D33;
            padding: 0.2rem 0.4rem;
            border-radius: 0.25rem;
            white-space: nowrap;
        }
        .explanation h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #FCD34D;
            margin-bottom: 1rem;
        }
        .explanation h4 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #F59E0B;
            margin-top: 1rem;
            margin-bottom: 0.5rem;
        }
        .explanation p {
            margin-bottom: 0.75rem;
            line-height: 1.5;
        }
        .explanation ul {
            list-style-type: disc;
            margin-left: 1.5rem;
            margin-bottom: 0.75rem;
        }
        .explanation li {
            margin-bottom: 0.25rem;
        }
        .explanation .error {
            border-left: 4px solid #EF4444;
            padding-left: 1rem;
        }
        .math-display {
            overflow-x: auto;
            margin: 1rem 0;
            padding: 0.5rem 0;
        }
        /* Custom MathJax styling */
        .MathJax {
            overflow-x: auto;
            overflow-y: hidden;
            padding: 2px 0;
        }
        /* Code block styling */
        pre.code-block {
            background-color: #2D2A24;
            padding: 1rem;
            border-radius: 0.375rem;
            overflow-x: auto;
            font-family: monospace;
            margin: 1rem 0;
            white-space: pre-wrap;
            color: #E8E6E1;
        }
        code {
            font-family: monospace;
            background-color: #2D2A24;
            padding: 0.1rem 0.3rem;
            border-radius: 0.25rem;
            font-size: 0.9em;
        }
        /* Citation styling */
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
        /* Related papers styling */
        .related-papers {
            background-color: #504A40;
            border-radius: 0.5rem;
            padding: 1rem;
            margin: 0.75rem 0;
            border-left: 4px solid #F59E0B;
        }
        .related-papers ul {
            list-style-type: disc;
            margin-left: 1.5rem;
        }
        .related-papers li {
            margin-bottom: 0.5rem;
        }
        .related-papers .citation {
            background-color: rgba(245, 158, 11, 0.2);
            border: 1px solid rgba(245, 158, 11, 0.4);
        }
        .related-papers .paper-description {
            display: block;
            margin-left: 1.5rem;
            margin-top: 0.25rem;
            font-style: italic;
        }
        
        /* Spinner animation styles */
        .spinner-box {
            width: 150px;
            height: 150px;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: transparent;
        }
        
        .circle-border {
            width: 80px;
            height: 80px;
            padding: 3px;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 50%;
            background: rgb(245, 158, 11);
            background: linear-gradient(0deg, rgba(245, 158, 11, 0.1) 33%, rgba(245, 158, 11, 1) 100%);
            animation: spin 1.5s linear 0s infinite;
        }
        
        .circle-core {
            width: 100%;
            height: 100%;
            background-color: #27241D;
            border-radius: 50%;
        }
        
        @keyframes spin {
            from {
                transform: rotate(0);
            }
            to {
                transform: rotate(359deg);
            }
        }
        
        /* Options dropdown styling */
        #options-dropdown {
            display: none;
        }
        
        #options-dropdown:not(.hidden) {
            display: block !important;
        }
    </style>
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
                    <h2 class="text-lg text-amber-400 hidden sm:block mr-4">Search</h2>
                    <button id="mobile-menu-btn" class="p-1 text-amber-400 sm:hidden focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
            <!-- Mobile menu -->
            <div id="mobile-menu" class="sm:hidden hidden mt-2 py-2 border-t border-warmgray-700">
                <a href="index.php" class="block py-2 px-2 text-amber-400 font-medium">
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
            </div>
            <div class="mt-4">
                <form method="POST" action="" id="search-form" class="flex flex-col gap-2">
                    <div class="flex flex-col sm:flex-row gap-2">
                        <div class="relative flex-grow">
                            <input type="text" name="query" value="<?php echo htmlspecialchars($query ?? ''); ?>" placeholder="Search ArXiv papers..." 
                               class="w-full p-2 rounded bg-warmgray-700 text-warmgray-100 focus:outline-none focus:ring-2 focus:ring-amber-500 pr-10 text-sm md:text-base"
                               style="background-color: #504A40 !important; color: #E5E7EB !important;">
                            <button type="button" id="search-help-btn" class="absolute right-3 top-2 text-warmgray-400 hover:text-amber-400">
                                <i class="fas fa-question-circle"></i>
                            </button>
                            <div id="search-help-tooltip" class="hidden absolute right-0 mt-1 z-20 w-full max-w-xs sm:max-w-sm md:max-w-md">
                                <?php echo get_advanced_search_help(); ?>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="flex-grow sm:flex-grow-0 px-4 py-2 bg-amber-600 text-white rounded hover:bg-amber-500 transition duration-200 text-sm md:text-base">
                                <i class="fas fa-search mr-1"></i> Search
                            </button>
                            <div class="relative">
                                <button type="button" id="options-btn" class="px-3 py-2 bg-warmgray-700 text-amber-400 rounded hover:bg-warmgray-600">
                                    <i class="fas fa-cog"></i>
                                </button>
                                <div id="options-dropdown" class="hidden absolute right-0 mt-2 w-72 sm:w-80 bg-warmgray-800 border border-warmgray-600 rounded shadow-lg z-50" style="display: none;">
                                    <div class="p-3">
                                        <h3 class="text-amber-400 font-medium mb-2">Search Options</h3>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div class="col-span-1">
                                                <div class="mb-2">
                                                    <label class="flex items-center text-warmgray-200">
                                                        <input type="checkbox" name="include_summaries" <?php echo isset($include_summaries) && $include_summaries ? 'checked' : ''; ?> class="mr-2">
                                                        Generate AI summaries
                                                    </label>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="block text-warmgray-200 mb-1">Max results:</label>
                                                    <select name="max_results" class="w-full p-1 bg-warmgray-700 text-warmgray-200 rounded border border-warmgray-600" style="background-color: #504A40 !important; color: #E5E7EB !important;">
                                                        <option value="5" <?php echo $max_results == 5 ? 'selected' : ''; ?> class="bg-warmgray-700 text-warmgray-200">5 papers</option>
                                                        <option value="10" <?php echo $max_results == 10 ? 'selected' : ''; ?> class="bg-warmgray-700 text-warmgray-200">10 papers</option>
                                                        <option value="20" <?php echo $max_results == 20 ? 'selected' : ''; ?> class="bg-warmgray-700 text-warmgray-200">20 papers</option>
                                                        <option value="50" <?php echo $max_results == 50 ? 'selected' : ''; ?> class="bg-warmgray-700 text-warmgray-200">50 papers</option>
                                                    </select>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="block text-warmgray-200 mb-1">Sort results by:</label>
                                                    <select name="sort_by" class="w-full p-1 bg-warmgray-700 text-warmgray-200 rounded border border-warmgray-600" style="background-color: #504A40 !important; color: #E5E7EB !important;">
                                                        <option value="relevance" <?php echo ($sort_by ?? 'relevance') == 'relevance' ? 'selected' : ''; ?> class="bg-warmgray-700 text-warmgray-200">Relevance</option>
                                                        <option value="date-desc" <?php echo ($sort_by ?? '') == 'date-desc' ? 'selected' : ''; ?> class="bg-warmgray-700 text-warmgray-200">Date (Newest first)</option>
                                                        <option value="date-asc" <?php echo ($sort_by ?? '') == 'date-asc' ? 'selected' : ''; ?> class="bg-warmgray-700 text-warmgray-200">Date (Oldest first)</option>
                                                        <option value="title" <?php echo ($sort_by ?? '') == 'title' ? 'selected' : ''; ?> class="bg-warmgray-700 text-warmgray-200">Title (A-Z)</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-span-1">
                                                <div class="mb-2">
                                                    <label class="block text-warmgray-200 mb-1">Filter by research areas:</label>
                                                    <div class="max-h-40 overflow-y-auto space-y-1 p-1 bg-warmgray-700 rounded text-sm">
                                                        <?php 
                                                        $arxiv_categories = get_arxiv_categories();
                                                        foreach ($arxiv_categories as $group => $categories): 
                                                        ?>
                                                            <div class="font-semibold text-amber-300 text-xs pt-1"><?php echo htmlspecialchars($group); ?></div>
                                                            <?php foreach ($categories as $cat_id => $cat_name): ?>
                                                                <div class="ml-2">
                                                                    <label class="flex items-center text-warmgray-200">
                                                                        <input type="checkbox" name="categories[]" value="<?php echo htmlspecialchars($cat_id); ?>" class="mr-1 h-3 w-3">
                                                                        <span class="text-xs"><?php echo htmlspecialchars($cat_name); ?></span>
                                                                    </label>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="block text-warmgray-200 mb-1">AI Provider:</label>
                                                    <select name="provider" class="w-full p-1 bg-warmgray-700 text-warmgray-200 rounded border border-warmgray-600" style="background-color: #504A40 !important; color: #E5E7EB !important;">
                                                        <?php foreach ($providers as $name => $provider): ?>
                                                            <option value="<?php echo htmlspecialchars($name); ?>" <?php echo $name === $default_provider ? 'selected' : ''; ?> class="bg-warmgray-700 text-warmgray-200">
                                                                <?php echo htmlspecialchars($name); ?> <?php echo !empty($api_keys[$name]) ? '(' . $api_keys[$name] . ')' : ''; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </header>

    <!-- Main Navigation Bar - Desktop Only -->
    <nav class="hidden sm:block bg-warmgray-800 border-b border-warmgray-700 py-2 px-4">
        <div class="container mx-auto flex items-center justify-between">
            <div class="flex space-x-4">
                <a href="index.php" class="text-amber-400 hover:text-amber-300 px-3 py-1 rounded hover:bg-warmgray-700">
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
            </div>
            <div>
                <span class="text-warmgray-400 text-sm">Powered by <span class="text-amber-400">AI</span></span>
            </div>
        </div>
    </nav>



    <main class="container mx-auto px-4 py-4 flex-grow">
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-900 text-red-100 p-3 sm:p-4 rounded mb-4 sm:mb-6 text-sm sm:text-base">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($query)): ?>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-4">
                <h2 class="text-lg sm:text-xl text-warmgray-200">
                    Search results for: <span class="text-amber-400"><?php echo format_query_for_display($query); ?></span>
                    <span class="block sm:inline text-sm text-warmgray-400">(<?php echo count($search_results); ?> papers found)</span>
                </h2>
                <a href="view_favorites.php" class="px-3 py-1 bg-warmgray-700 text-amber-400 rounded hover:bg-warmgray-600 text-sm sm:text-base">
                    <i class="fas fa-folder mr-1"></i> <span class="hidden sm:inline">My Collections</span><span class="sm:hidden">Collections</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- Collection Selection Modal -->
        <div id="collection-modal" class="modal">
            <div class="modal-content w-full max-w-md">
                <span class="close">&times;</span>
                <h2 class="text-xl font-semibold text-amber-400 mb-4">Add to Collection</h2>
                <div id="collections-list" class="max-h-60 overflow-y-auto mb-4">
                    <div class="space-y-2" id="collections-options">
                        <!-- Will be populated via JavaScript -->
                        <div class="flex items-center p-2 bg-warmgray-700 rounded hover:bg-warmgray-600 cursor-pointer">
                            <i class="fas fa-star text-amber-400 mr-2"></i>
                            <span class="text-warmgray-200">Loading collections...</span>
                        </div>
                    </div>
                </div>
                <div class="flex justify-between items-center">
                    <button id="new-collection-btn" class="px-3 py-1 bg-warmgray-700 text-amber-400 rounded hover:bg-warmgray-600">
                        <i class="fas fa-plus mr-1"></i> New Collection
                    </button>
                    <button id="cancel-add-collection" class="px-3 py-1 bg-warmgray-800 text-warmgray-300 rounded hover:bg-warmgray-700">
                        Cancel
                    </button>
                </div>
                <input type="hidden" id="collection-paper-index" value="">
            </div>
        </div>

        <!-- New Collection Form -->
        <div id="new-collection-form-modal" class="modal">
            <div class="modal-content w-full max-w-md">
                <span class="close">&times;</span>
                <h2 class="text-xl font-semibold text-amber-400 mb-4">Create New Collection</h2>
                <form id="new-collection-form">
                    <div class="mb-4">
                        <label for="new-collection-name" class="block text-warmgray-200 mb-1">Collection Name:</label>
                        <input type="text" id="new-collection-name" class="w-full p-2 rounded bg-warmgray-700 text-warmgray-100 border border-warmgray-600 focus:outline-none focus:ring-1 focus:ring-amber-500" required>
                    </div>
                    <div class="mb-4">
                        <label for="new-collection-description" class="block text-warmgray-200 mb-1">Description (optional):</label>
                        <textarea id="new-collection-description" class="w-full p-2 rounded bg-warmgray-700 text-warmgray-100 border border-warmgray-600 focus:outline-none focus:ring-1 focus:ring-amber-500" rows="2"></textarea>
                    </div>
                    <div class="flex justify-end space-x-2">
                        <button type="button" id="cancel-new-collection" class="px-3 py-2 bg-warmgray-800 text-warmgray-300 rounded hover:bg-warmgray-700">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded hover:bg-amber-500">
                            <i class="fas fa-save mr-1"></i> Create Collection
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div id="results" class="mt-4">
            <?php if (empty($search_results) && !empty($query)): ?>
                <div class="paper-card p-6">
                    <p class="text-center text-warmgray-400">No papers found matching your query.</p>
                    <p class="text-center text-warmgray-400 mt-2">Try different keywords or use the advanced search options.</p>
                    <div class="mt-4 flex justify-center">
                        <div class="bg-warmgray-700 rounded-lg p-4 max-w-lg">
                            <h3 class="text-amber-400 text-lg font-semibold mb-2">Search Tips</h3>
                            <ul class="list-disc text-warmgray-300 pl-5 space-y-1">
                                <li>Use specific technical terms instead of general concepts</li>
                                <li>Try searching by author: <code class="bg-warmgray-800 px-1 rounded">author:"John Smith"</code></li>
                                <li>Search in paper titles: <code class="bg-warmgray-800 px-1 rounded">title:"neural networks"</code></li>
                                <li>Filter by date: <code class="bg-warmgray-800 px-1 rounded">date>2020</code></li>
                                <li>Use quotes for exact phrases: <code class="bg-warmgray-800 px-1 rounded">"machine learning"</code></li>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php elseif (!empty($search_results)): ?>
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                    <div class="lg:col-span-3">
                        <!-- Save search history is done in the main script -->
                        <?php foreach ($search_results as $index => $paper): ?>
                            <div class="paper-card mb-4">
                                <div class="flex flex-col sm:flex-row sm:justify-between gap-2">
                                    <h3 class="text-lg font-semibold text-amber-400"><?php echo htmlspecialchars($paper['title']); ?></h3>
                                    <div class="flex items-center justify-between sm:justify-end">
                                        <span class="text-xs font-medium text-amber-300 mr-2 date-badge">
                                            <i class="far fa-calendar-alt mr-1"></i> <?php echo htmlspecialchars($paper['date']); ?>
                                        </span>
                                        <div class="flex space-x-3">
                                            <a href="<?php echo htmlspecialchars($paper['link']); ?>" target="_blank" class="flex-shrink-0 p-1" title="View on ArXiv">
                                                <i class="fas fa-external-link-alt text-amber-400 hover:text-amber-300"></i>
                                            </a>
                                            <button class="add-collection-btn flex-shrink-0 p-1" data-paper-index="<?php echo $index; ?>" title="Add to Collection">
                                                <i class="fas fa-folder-plus text-amber-400 hover:text-amber-300"></i>
                                            </button>
                                            <button class="explain-btn flex-shrink-0 p-1" data-paper-index="<?php echo $index; ?>" onclick="explainPaperByIndex(<?php echo $index; ?>)" title="AI Explanation">
                                                <i class="fas fa-brain text-amber-400 hover:text-amber-300"></i>
                                            </button>
                                            <script>
                                                // Store paper data for explanation and favorites
                                                if (!window.paperData) window.paperData = [];
                                                window.paperData[<?php echo $index; ?>] = {
                                                    title: <?php echo json_encode($paper['title']); ?>,
                                                    abstract: <?php echo json_encode($paper['abstract']); ?>,
                                                    authors: <?php echo json_encode($paper['authors']); ?>,
                                                    link: <?php echo json_encode($paper['link']); ?>,
                                                    date: <?php echo json_encode($paper['date']); ?>,
                                                    categories: <?php echo json_encode($paper['categories']); ?>
                                                };
                                            </script>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-sm text-warmgray-300 mt-1 line-clamp-1 sm:line-clamp-none"><?php echo htmlspecialchars($paper['authors']); ?></p>
                                <div class="categories mb-2 flex flex-wrap">
                                    <?php foreach (array_slice($paper['categories'], 0, 3) as $category): ?>
                                        <span class="category inline-block text-xs bg-warmgray-700 text-amber-300 px-2 py-1 rounded mr-1 mb-1"><?php echo htmlspecialchars($category); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($paper['categories']) > 3): ?>
                                        <span class="text-xs text-warmgray-400">+<?php echo count($paper['categories']) - 3; ?> more</span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-2">
                                    <?php
                                    $abstract = $paper['abstract'];
                                    $condensed_abstract = strlen($abstract) > 150 ? substr($abstract, 0, 150) . '...' : $abstract;
                                    $has_more = strlen($abstract) > 150;
                                    ?>
                                    <div id="condensed-<?php echo $index; ?>" class="text-sm text-warmgray-400 leading-relaxed"><?php echo htmlspecialchars($condensed_abstract); ?></div>
                                    <div id="full-<?php echo $index; ?>" class="text-sm text-warmgray-400 leading-relaxed" style="display:none"><?php echo htmlspecialchars($abstract); ?></div>
                                    <?php if ($has_more): ?>
                                        <div class="text-right mt-1">
                                            <span id="more-btn-<?php echo $index; ?>" class="more-btn inline-block bg-warmgray-700 hover:bg-warmgray-600 px-2 py-1 rounded-full text-xs transition" onclick="toggleAbstract(<?php echo $index; ?>)">
                                                <i class="fas fa-chevron-down mr-1"></i> more...
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($paper['summary'])): ?>
                                    <div class="mt-3 bg-warmgray-700 p-2 rounded border-l-2 border-amber-400">
                                        <div class="flex items-center mb-1">
                                            <i class="fas fa-robot text-amber-400 mr-1"></i>
                                            <span class="text-xs font-semibold text-amber-300">AI Summary:</span>
                                        </div>
                                        <div class="text-sm text-warmgray-300"><?php echo htmlspecialchars($paper['summary']); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-3 flex flex-wrap justify-between items-center gap-2">
                                    <button class="citation-toggle text-xs font-semibold bg-warmgray-700 hover:bg-warmgray-600 text-amber-300 px-2 py-1 rounded flex items-center transition"
                                            onclick="toggleCitation('citation-container-<?php echo $index; ?>')">
                                        <i class="fas fa-quote-right mr-1"></i> <span>Citations</span>
                                    </button>
                                    <div class="flex flex-wrap gap-2">
                                        <a href="https://scholar.google.com/scholar?q=<?php echo urlencode($paper['title']); ?>" target="_blank" class="text-xs font-semibold bg-warmgray-700 hover:bg-warmgray-600 text-amber-300 px-2 py-1 rounded flex items-center transition">
                                            <i class="fas fa-graduation-cap mr-1"></i> <span class="hidden xs:inline">Google</span> Scholar
                                        </a>
                                        <a href="https://www.semanticscholar.org/search?q=<?php echo urlencode($paper['title']); ?>" target="_blank" class="text-xs font-semibold bg-warmgray-700 hover:bg-warmgray-600 text-amber-300 px-2 py-1 rounded flex items-center transition">
                                            <i class="fas fa-book mr-1"></i> Semantic <span class="hidden xs:inline">Scholar</span>
                                        </a>
                                    </div>
                                </div>
                                <div id="citation-container-<?php echo $index; ?>" class="citation-container mt-2" style="display: none;">
                                    <div class="flex justify-between items-center mb-1 mt-2">
                                        <select id="citation-format-<?php echo $index; ?>" class="citation-format text-xs p-1 rounded bg-warmgray-700 text-warmgray-200 border-warmgray-600"
                                                onchange="updateCitation('citation-<?php echo $index; ?>', this.value, <?php echo $index; ?>)">
                                            <optgroup label="Export Formats">
                                                <option value="bibtex" class="bg-warmgray-700 text-warmgray-200">BibTeX</option>
                                                <option value="ris" class="bg-warmgray-700 text-warmgray-200">RIS (EndNote/Zotero)</option>
                                            </optgroup>
                                            <optgroup label="Citation Styles">
                                                <option value="apa" class="bg-warmgray-700 text-warmgray-200">APA</option>
                                                <option value="mla" class="bg-warmgray-700 text-warmgray-200">MLA</option>
                                                <option value="chicago" class="bg-warmgray-700 text-warmgray-200">Chicago</option>
                                                <option value="harvard" class="bg-warmgray-700 text-warmgray-200">Harvard</option>
                                                <option value="ou-harvard" class="bg-warmgray-700 text-warmgray-200">OU-Harvard</option>
                                                <option value="ieee" class="bg-warmgray-700 text-warmgray-200">IEEE</option>
                                                <option value="vancouver" class="bg-warmgray-700 text-warmgray-200">Vancouver</option>
                                                <option value="ama" class="bg-warmgray-700 text-warmgray-200">AMA</option>
                                                <option value="cse" class="bg-warmgray-700 text-warmgray-200">CSE</option>
                                            </optgroup>
                                        </select>
                                        <div class="flex space-x-2">
                                            <button class="copy-btn text-xs text-amber-300 hover:text-amber-200"
                                                    onclick="copyToClipboard('citation-<?php echo $index; ?>')">
                                                <i class="fas fa-copy mr-1"></i> Copy
                                            </button>
                                            <button class="download-btn text-xs text-amber-300 hover:text-amber-200"
                                                    onclick="downloadCitation('citation-<?php echo $index; ?>', document.getElementById('citation-format-<?php echo $index; ?>').value, '<?php echo htmlspecialchars(str_replace(' ', '_', $paper['title']), ENT_QUOTES); ?>')">
                                                <i class="fas fa-download mr-1"></i> Download
                                            </button>
                                        </div>
                                    </div>
                                    <pre id="citation-<?php echo $index; ?>" class="text-xs overflow-x-auto p-2 rounded bg-warmgray-700 text-warmgray-200"><?php echo htmlspecialchars(create_paper_citation($paper, 'bibtex')); ?></pre>
                                    
                                    <!-- Store all citation formats as data attributes -->
                                    <div id="all-citations-<?php echo $index; ?>" style="display: none;" 
                                         data-bibtex="<?php echo htmlspecialchars(create_paper_citation($paper, 'bibtex')); ?>"
                                         data-ris="<?php echo htmlspecialchars(create_paper_citation($paper, 'ris')); ?>"
                                         data-apa="<?php echo htmlspecialchars(create_paper_citation($paper, 'apa')); ?>"
                                         data-mla="<?php echo htmlspecialchars(create_paper_citation($paper, 'mla')); ?>"
                                         data-chicago="<?php echo htmlspecialchars(create_paper_citation($paper, 'chicago')); ?>"
                                         data-harvard="<?php echo htmlspecialchars(create_paper_citation($paper, 'harvard')); ?>"
                                         data-ou-harvard="<?php echo htmlspecialchars(create_paper_citation($paper, 'ou-harvard')); ?>"
                                         data-ieee="<?php echo htmlspecialchars(create_paper_citation($paper, 'ieee')); ?>"
                                         data-vancouver="<?php echo htmlspecialchars(create_paper_citation($paper, 'vancouver')); ?>"
                                         data-ama="<?php echo htmlspecialchars(create_paper_citation($paper, 'ama')); ?>"
                                         data-cse="<?php echo htmlspecialchars(create_paper_citation($paper, 'cse')); ?>">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="lg:col-span-1">
                        <!-- Paper Recommendations Sidebar -->
                        <?php if (!empty($search_results) && count($search_results) > 0): ?>
                            <?php 
                            // Get the first paper for recommendations
                            $reference_paper = $search_results[0];
                            $similar_papers = get_similar_papers($reference_paper, 3);
                            $also_viewed = get_users_also_viewed($reference_paper, 3);
                            $trending_papers = get_trending_papers('', 3);
                            ?>
                            
                            <!-- Similar Papers -->
                            <?php if (!empty($similar_papers)): ?>
                            <div class="bg-warmgray-700 rounded-lg p-3 mb-4">
                                <h3 class="text-amber-400 font-semibold mb-2 flex items-center">
                                    <i class="fas fa-lightbulb mr-2"></i> Similar Papers
                                </h3>
                                <div class="space-y-3">
                                    <?php foreach ($similar_papers as $rec_paper): ?>
                                        <div class="bg-warmgray-800 rounded p-2 hover:bg-warmgray-600 transition-colors">
                                            <a href="<?php echo htmlspecialchars($rec_paper['link']); ?>" target="_blank" class="block">
                                                <h4 class="text-sm font-semibold text-amber-300 mb-1"><?php echo htmlspecialchars($rec_paper['title']); ?></h4>
                                                <p class="text-xs text-warmgray-400"><?php echo htmlspecialchars($rec_paper['authors']); ?></p>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Users Also Viewed -->
                            <?php if (!empty($also_viewed)): ?>
                            <div class="bg-warmgray-700 rounded-lg p-3 mb-4">
                                <h3 class="text-amber-400 font-semibold mb-2 flex items-center">
                                    <i class="fas fa-users mr-2"></i> Related Research
                                </h3>
                                <div class="space-y-3">
                                    <?php foreach ($also_viewed as $rec_paper): ?>
                                        <div class="bg-warmgray-800 rounded p-2 hover:bg-warmgray-600 transition-colors">
                                            <a href="<?php echo htmlspecialchars($rec_paper['link']); ?>" target="_blank" class="block">
                                                <h4 class="text-sm font-semibold text-amber-300 mb-1"><?php echo htmlspecialchars($rec_paper['title']); ?></h4>
                                                <p class="text-xs text-warmgray-400"><?php echo htmlspecialchars($rec_paper['authors']); ?></p>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Trending Papers -->
                            <?php if (!empty($trending_papers)): ?>
                            <div class="bg-warmgray-700 rounded-lg p-3 mb-4">
                                <h3 class="text-amber-400 font-semibold mb-2 flex items-center">
                                    <i class="fas fa-chart-line mr-2"></i> Trending Papers
                                </h3>
                                <div class="space-y-3">
                                    <?php foreach ($trending_papers as $rec_paper): ?>
                                        <div class="bg-warmgray-800 rounded p-2 hover:bg-warmgray-600 transition-colors">
                                            <a href="<?php echo htmlspecialchars($rec_paper['link']); ?>" target="_blank" class="block">
                                                <h4 class="text-sm font-semibold text-amber-300 mb-1"><?php echo htmlspecialchars($rec_paper['title']); ?></h4>
                                                <p class="text-xs text-warmgray-400"><?php echo htmlspecialchars($rec_paper['authors']); ?></p>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Research Tools -->
                            <div class="bg-warmgray-700 rounded-lg p-3">
                                <h3 class="text-amber-400 font-semibold mb-2 flex items-center">
                                    <i class="fas fa-toolbox mr-2"></i> Research Tools
                                </h3>
                                <div class="space-y-2">
                                    <a href="https://scholar.google.com" target="_blank" class="flex items-center p-2 bg-warmgray-800 rounded hover:bg-warmgray-600 text-warmgray-200">
                                        <i class="fas fa-graduation-cap text-amber-400 mr-2"></i> Google Scholar
                                    </a>
                                    <a href="https://www.semanticscholar.org" target="_blank" class="flex items-center p-2 bg-warmgray-800 rounded hover:bg-warmgray-600 text-warmgray-200">
                                        <i class="fas fa-book text-amber-400 mr-2"></i> Semantic Scholar
                                    </a>
                                    <a href="https://www.connectedpapers.com" target="_blank" class="flex items-center p-2 bg-warmgray-800 rounded hover:bg-warmgray-600 text-warmgray-200">
                                        <i class="fas fa-project-diagram text-amber-400 mr-2"></i> Connected Papers
                                    </a>
                                    <a href="https://app.dimensions.ai" target="_blank" class="flex items-center p-2 bg-warmgray-800 rounded hover:bg-warmgray-600 text-warmgray-200">
                                        <i class="fas fa-dice-d20 text-amber-400 mr-2"></i> Dimensions
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif (empty($query)): ?>
                <div class="text-center py-10">
                    <div class="text-amber-400 text-4xl mb-4"><i class="fas fa-search"></i></div>
                    <h2 class="text-2xl text-warmgray-200 mb-2">Search for ArXiv Papers</h2>
                    <p class="text-warmgray-400 mb-6">Enter a search query above to find scientific papers from ArXiv.</p>
                    
                    <div class="max-w-2xl mx-auto bg-warmgray-800 rounded-lg p-4 text-left">
                        <h3 class="text-amber-400 text-lg font-semibold mb-2">Advanced Search Examples:</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm text-warmgray-300">
                            <div>
                                <p class="mb-1"><strong>Search by author:</strong></p>
                                <code class="bg-warmgray-700 p-1 rounded block">author:"Geoffrey Hinton" neural networks</code>
                            </div>
                            <div>
                                <p class="mb-1"><strong>Search in title:</strong></p>
                                <code class="bg-warmgray-700 p-1 rounded block">title:"transformer" language model</code>
                            </div>
                            <div>
                                <p class="mb-1"><strong>Search by date range:</strong></p>
                                <code class="bg-warmgray-700 p-1 rounded block">date>2020 date<2023 "deep learning"</code>
                            </div>
                            <div>
                                <p class="mb-1"><strong>Search in abstract:</strong></p>
                                <code class="bg-warmgray-700 p-1 rounded block">abstract:"reinforcement learning" robotics</code>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="fixed inset-0 bg-black bg-opacity-70 z-50 hidden flex items-center justify-center">
        <div class="text-center">
            <div class="spinner-box">
                <div class="circle-border">
                    <div class="circle-core"></div>
                </div>
            </div>
            <p class="mt-4 text-amber-400 text-xl font-semibold">Searching ArXiv...</p>
        </div>
    </div>
    
    <!-- Search History Modal -->
    <div id="history-modal" class="modal">
        <div class="modal-content w-full max-w-4xl">
            <span class="close">&times;</span>
            <h2 class="text-xl font-semibold text-amber-400 mb-4">Search History</h2>
            <div id="history-list" class="max-h-96 overflow-y-auto">
                <!-- Will be populated via JavaScript -->
                <p class="text-center text-warmgray-400 py-4">Loading search history...</p>
            </div>
        </div>
    </div>

    <!-- Explanation Modal -->
    <div id="explanationModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="explanation-title" class="text-xl font-semibold text-amber-400 mb-4">Paper Explanation</h2>
            <div id="explanation-loading" class="flex items-center justify-center py-8" style="display: none;">
                <div class="spinner mr-3 h-6 w-6 text-white">
                    <svg class="animate-spin h-6 w-6 text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <span class="text-lg text-amber-400">Generating explanation...</span>
            </div>
            <div id="explanation-content" class="text-warmgray-200">
                <!-- Explanation content will be loaded here -->
            </div>
        </div>
    </div>



    <script>
        // Save search history if we have results
        <?php if (!empty($search_results) && !empty($query)): ?>
        function saveSearchToLocalStorage() {
            const query = <?php echo json_encode($query); ?>;
            const results = <?php echo json_encode($search_results); ?>;
            
            // Get existing history from localStorage
            let history = JSON.parse(localStorage.getItem('arxer_history')) || [];
            
            // Create history entry
            const entry = {
                query: query,
                timestamp: new Date().toISOString(),
                num_results: results.length,
                papers: results.slice(0, 5) // Save only first 5 papers to save space
            };
            
            // Add to history and keep only last 20 entries
            history.unshift(entry);
            history = history.slice(0, 20);
            
            // Save to localStorage
            localStorage.setItem('arxer_history', JSON.stringify(history));
        }
        <?php endif; ?>
        
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($search_results) && !empty($query)): ?>
            // Save search history
            saveSearchToLocalStorage();
            <?php endif; ?>
            
            // Show loading overlay when search form is submitted
            const searchForm = document.getElementById('search-form');
            const loadingOverlay = document.getElementById('loading-overlay');
            
            if (searchForm) {
                searchForm.addEventListener('submit', function() {
                    loadingOverlay.classList.remove('hidden');
                });
            }
            
            // History button handling
            const historyBtn = document.getElementById('history-btn');
            if (historyBtn) {
                historyBtn.addEventListener('click', function() {
                    showSearchHistory();
                });
            }
            
            // Search help tooltip handling
            const searchHelpBtn = document.getElementById('search-help-btn');
            const searchHelpTooltip = document.getElementById('search-help-tooltip');
            
            if (searchHelpBtn && searchHelpTooltip) {
                searchHelpBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    searchHelpTooltip.classList.toggle('hidden');
                });
                
                // Close the tooltip when clicking outside
                document.addEventListener('click', function(e) {
                    if (!searchHelpBtn.contains(e.target) && !searchHelpTooltip.contains(e.target)) {
                        searchHelpTooltip.classList.add('hidden');
                    }
                });
            }
            
            // Toggle the options dropdown
            const optionsBtn = document.getElementById('options-btn');
            const optionsDropdown = document.getElementById('options-dropdown');
            
            if (optionsBtn && optionsDropdown) {
                optionsBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation(); // Prevent event bubbling
                    
                    // Force show/hide rather than toggle
                    if (optionsDropdown.classList.contains('hidden')) {
                        optionsDropdown.classList.remove('hidden');
                        optionsDropdown.style.display = 'block'; // Ensure it's visible
                    } else {
                        optionsDropdown.classList.add('hidden');
                        optionsDropdown.style.display = 'none';
                    }
                });
            }

            // Close the dropdown when clicking outside
            document.addEventListener('click', function(e) {
                const dropdown = document.getElementById('options-dropdown');
                const button = document.getElementById('options-btn');
                
                if (dropdown && button) {
                    // Close dropdown when clicking outside the button or dropdown
                    if (!button.contains(e.target) && !dropdown.contains(e.target)) {
                        dropdown.classList.add('hidden');
                        dropdown.style.display = 'none';
                    }
                }
            });

            // Collection functionality
            setupCollectionModals();

            // Modal handling
            setupModalHandling();
        });
        
        function setupCollectionModals() {
            // Collection modals
            const collectionModal = document.getElementById('collection-modal');
            const newCollectionFormModal = document.getElementById('new-collection-form-modal');
            const collectionPaperIndex = document.getElementById('collection-paper-index');
            
            // Add to collection buttons
            const addCollectionBtns = document.querySelectorAll('.add-collection-btn');
            addCollectionBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const paperIndex = this.getAttribute('data-paper-index');
                    collectionPaperIndex.value = paperIndex;
                    loadCollectionsForModal();
                    collectionModal.style.display = 'block';
                });
            });
            
            // Cancel buttons
            document.getElementById('cancel-add-collection').addEventListener('click', function() {
                collectionModal.style.display = 'none';
            });
            
            document.getElementById('cancel-new-collection').addEventListener('click', function() {
                newCollectionFormModal.style.display = 'none';
            });
            
            // New collection button in collection modal
            document.getElementById('new-collection-btn').addEventListener('click', function() {
                collectionModal.style.display = 'none';
                newCollectionFormModal.style.display = 'block';
            });
            
            // New collection form submission
            document.getElementById('new-collection-form').addEventListener('submit', function(e) {
                e.preventDefault();
                const name = document.getElementById('new-collection-name').value;
                const description = document.getElementById('new-collection-description').value;
                
                if (!name || name.trim() === '') {
                    alert('Please enter a collection name');
                    return;
                }
                
                // Create new collection
                createCollection(name.trim(), description);
                
                // Hide modal
                newCollectionFormModal.style.display = 'none';
                
                // Show collection selection modal again
                loadCollectionsForModal();
                collectionModal.style.display = 'block';
            });
        }
        
        function loadCollectionsForModal() {
            // Get collections from localStorage
            let collections = JSON.parse(localStorage.getItem('arxer_favorites')) || {};
            
            // Ensure default collection exists
            if (!collections.default) {
                collections.default = [];
            }
            
            const collectionsOptions = document.getElementById('collections-options');
            collectionsOptions.innerHTML = '';
            
            // Add default collection
            const defaultCollection = document.createElement('div');
            defaultCollection.className = 'flex items-center p-2 bg-warmgray-700 rounded hover:bg-warmgray-600 cursor-pointer';
            defaultCollection.innerHTML = `
                <i class="fas fa-star text-amber-400 mr-2"></i>
                <span class="text-warmgray-200">Favorites (${collections.default.length} papers)</span>
            `;
            defaultCollection.addEventListener('click', function() {
                addToCollection('default');
            });
            collectionsOptions.appendChild(defaultCollection);
            
            // Add other collections
            for (const key in collections) {
                if (key === 'default') continue;
                
                const collectionDiv = document.createElement('div');
                collectionDiv.className = 'flex items-center p-2 bg-warmgray-700 rounded hover:bg-warmgray-600 cursor-pointer';
                collectionDiv.innerHTML = `
                    <i class="fas fa-folder text-amber-400 mr-2"></i>
                    <span class="text-warmgray-200">${key} (${collections[key].length} papers)</span>
                `;
                collectionDiv.addEventListener('click', function() {
                    addToCollection(key);
                });
                collectionsOptions.appendChild(collectionDiv);
            }
        }
        
        function createCollection(name, description = '') {
            // Get existing collections
            let collections = JSON.parse(localStorage.getItem('arxer_favorites')) || {};
            
            // Create new collection if it doesn't exist
            if (!collections[name]) {
                collections[name] = [];
                
                // Save to localStorage
                localStorage.setItem('arxer_favorites', JSON.stringify(collections));
                return true;
            }
            
            return false; // Collection already exists
        }
        
        function addToCollection(collectionName) {
            const paperIndex = document.getElementById('collection-paper-index').value;
            if (!paperIndex || !window.paperData || !window.paperData[paperIndex]) {
                alert('Error: Paper data not found!');
                return;
            }
            
            const paper = window.paperData[paperIndex];
            
            // Get existing collections
            let collections = JSON.parse(localStorage.getItem('arxer_favorites')) || {};
            
            // Ensure the collection exists
            if (!collections[collectionName]) {
                collections[collectionName] = [];
            }
            
            // Check if paper already exists in the collection
            const exists = collections[collectionName].some(p => p.link === paper.link);
            if (exists) {
                alert(`This paper is already in the "${collectionName}" collection.`);
                document.getElementById('collection-modal').style.display = 'none';
                return;
            }
            
            // Add paper to collection
            collections[collectionName].push({
                title: paper.title,
                authors: paper.authors,
                abstract: paper.abstract,
                link: paper.link,
                date: paper.date,
                categories: paper.categories || [],
                added: new Date().toISOString()
            });
            
            // Save to localStorage
            localStorage.setItem('arxer_favorites', JSON.stringify(collections));
            
            // Hide modal
            document.getElementById('collection-modal').style.display = 'none';
            
            // Show confirmation
            alert(`Paper added to "${collectionName === 'default' ? 'Favorites' : collectionName}" collection.`);
        }
        
        function setupModalHandling() {
            // Get all modals and close buttons
            const modals = document.querySelectorAll('.modal');
            const closeBtns = document.querySelectorAll('.modal .close');
            
            // Add click event to all close buttons
            closeBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const modal = this.closest('.modal');
                    modal.style.display = 'none';
                });
            });
            
            // Close modal when clicking outside content
            modals.forEach(function(modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.style.display = 'none';
                    }
                });
            });
            
            // Explanation modal
            const explanationModal = document.getElementById('explanationModal');
            if (explanationModal) {
                const closeBtn = explanationModal.querySelector('.close');
                closeBtn.onclick = function() {
                    explanationModal.style.display = 'none';
                }
            }
        }
        
        function showSearchHistory() {
            // Get search history from localStorage
            let history = [];
            try {
                history = JSON.parse(localStorage.getItem('arxer_history')) || [];
            } catch (e) {
                console.error("Error loading history:", e);
            }
            
            // Get history list container
            const historyList = document.getElementById('history-list');
            historyList.innerHTML = '';
            
            // Show history items or empty message
            if (history.length === 0) {
                historyList.innerHTML = '<p class="text-center text-warmgray-400 py-4">No search history found.</p>';
                document.getElementById('history-modal').style.display = 'block';
                return;
            }
            
            // Sort by timestamp (most recent first)
            history.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
            
            // Create history items
            history.forEach((item, index) => {
                const date = new Date(item.timestamp);
                const formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
                
                const historyItem = document.createElement('div');
                historyItem.className = 'bg-warmgray-700 rounded-lg p-3 mb-3';
                
                let papersHTML = '';
                if (item.papers && item.papers.length > 0) {
                    papersHTML = '<div class="mt-2 space-y-2">';
                    item.papers.slice(0, 3).forEach(paper => {
                        papersHTML += `
                            <div class="bg-warmgray-800 p-2 rounded">
                                <a href="${paper.link}" target="_blank" class="block">
                                    <h4 class="text-sm font-semibold text-amber-300">${paper.title}</h4>
                                    <p class="text-xs text-warmgray-400">${paper.authors}</p>
                                </a>
                            </div>
                        `;
                    });
                    papersHTML += '</div>';
                }
                
                historyItem.innerHTML = `
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="flex items-center">
                                <i class="fas fa-search text-amber-400 mr-2"></i>
                                <span class="font-semibold text-amber-300">${item.query}</span>
                            </div>
                            <div class="text-xs text-warmgray-400 mt-1">
                                <span><i class="fas fa-clock mr-1"></i> ${formattedDate}</span>
                                <span class="ml-2"><i class="fas fa-file-alt mr-1"></i> ${item.num_results} results</span>
                            </div>
                        </div>
                        <button class="repeat-search-btn px-2 py-1 bg-amber-600 text-white text-xs rounded hover:bg-amber-500" data-query="${item.query}">
                            <i class="fas fa-redo-alt mr-1"></i> Search Again
                        </button>
                    </div>
                    ${papersHTML}
                `;
                
                // Add repeat search button event
                historyItem.querySelector('.repeat-search-btn').addEventListener('click', function() {
                    const query = this.getAttribute('data-query');
                    document.querySelector('input[name="query"]').value = query;
                    document.getElementById('search-form').submit();
                    document.getElementById('history-modal').style.display = 'none';
                });
                
                historyList.appendChild(historyItem);
            });
            
            // Show the modal
            document.getElementById('history-modal').style.display = 'block';
        }

        // Function to toggle abstract between short and full versions
        function toggleAbstract(index) {
            const condensed = document.getElementById('condensed-' + index);
            const full = document.getElementById('full-' + index);
            const btn = document.getElementById('more-btn-' + index);

            if (condensed.style.display !== 'none') {
                condensed.style.display = 'none';
                full.style.display = 'block';
                btn.innerHTML = '<i class="fas fa-chevron-up mr-1"></i> less...';
            } else {
                condensed.style.display = 'block';
                full.style.display = 'none';
                btn.innerHTML = '<i class="fas fa-chevron-down mr-1"></i> more...';
            }
        }

        // Function to toggle citation container
        function toggleCitation(containerId) {
            const container = document.getElementById(containerId);
            container.style.display = container.style.display === 'none' ? 'block' : 'none';
        }

        // Function to update citation based on selected format
        function updateCitation(citationId, format, index) {
            const allCitations = document.getElementById('all-citations-' + index);
            const citation = document.getElementById(citationId);
            citation.textContent = allCitations.getAttribute('data-' + format);
        }

        // Function to copy text to clipboard
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            const textArea = document.createElement('textarea');
            textArea.value = element.textContent;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            
            // Show a temporary notification
            const btn = element.parentNode.querySelector('.copy-btn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check mr-1"></i> Copied!';
            setTimeout(function() {
                btn.innerHTML = originalText;
            }, 2000);
        }
        
        // Function to download citation as a file
        function downloadCitation(elementId, format, title) {
            const element = document.getElementById(elementId);
            const content = element.textContent;
            
            // Create file extension and MIME type based on format
            let extension, mimeType;
            switch(format) {
                case 'bibtex':
                    extension = 'bib';
                    mimeType = 'application/x-bibtex';
                    break;
                case 'ris':
                    extension = 'ris';
                    mimeType = 'application/x-research-info-systems';
                    break;
                default:
                    extension = 'txt';
                    mimeType = 'text/plain';
            }
            
            // Create filename
            const filename = `${title}.${extension}`;
            
            // Create a download link
            const blob = new Blob([content], { type: mimeType });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            
            // Clean up
            setTimeout(function() {
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }, 0);
            
            // Show a temporary notification
            const btn = element.parentNode.querySelector('.download-btn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check mr-1"></i> Downloaded!';
            setTimeout(function() {
                btn.innerHTML = originalText;
            }, 2000);
        }

        // Function to explain paper by index
        function explainPaperByIndex(index) {
            if (!window.paperData || !window.paperData[index]) {
                alert('Error: Paper data not found!');
                return;
            }
            
            const paper = window.paperData[index];
            explainPaper(paper.title, paper.abstract, paper.authors);
        }
        
        // Function to explain paper
        function explainPaper(paperTitle, paperAbstract, paperAuthors) {
            // Show the modal
            const modal = document.getElementById('explanationModal');
            modal.style.display = 'block';
            
            // Set the title and show loading indicator
            document.getElementById('explanation-title').textContent = 'Explaining: ' + paperTitle;
            document.getElementById('explanation-loading').style.display = 'flex';
            document.getElementById('explanation-content').style.display = 'none';
            
            // Create form data for the POST request
            const formData = new FormData();
            formData.append('action', 'explain');
            formData.append('title', paperTitle);
            formData.append('abstract', paperAbstract);
            formData.append('authors', paperAuthors);
            
            // Send POST request to paper_explainer.php
            fetch('paper_explainer.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(data => {
                // Hide loading indicator and show content
                document.getElementById('explanation-loading').style.display = 'none';
                const contentEl = document.getElementById('explanation-content');
                contentEl.innerHTML = data;
                contentEl.style.display = 'block';
                
                // Render LaTeX formulas
                if (window.MathJax && window.MathJax.typeset) {
                    try {
                        window.MathJax.typeset([contentEl]);
                    } catch (e) {
                        console.error('Error rendering LaTeX:', e);
                    }
                }
            })
            .catch(error => {
                // Handle errors
                document.getElementById('explanation-loading').style.display = 'none';
                document.getElementById('explanation-content').innerHTML = 
                    '<div class="explanation error">'+
                    '<h3>Explanation of: ' + paperTitle + '</h3>' +
                    '<h4>Error</h4>' +
                    '<p>There was an error generating the explanation: ' + error.message + '</p>' +
                    '<p>Make sure your local LM server (LM Studio) is running and accessible.</p>'+
                    '</div>';
                document.getElementById('explanation-content').style.display = 'block';
                console.error('Error explaining paper:', error);
            });
        }

        // Function to add paper to favorites by index
        function addToFavoritesByIndex(index) {
            if (!window.paperData || !window.paperData[index]) {
                alert('Error: Paper data not found!');
                return;
            }
            
            const paper = window.paperData[index];
            addToFavorites(paper.link, paper.title, paper.abstract, paper.authors, paper.date);
        }
        
        // Function to add paper to favorites using localStorage
        function addToFavorites(paperLink, paperTitle, paperAbstract, paperAuthors, paperDate) {
            // Get existing favorites from localStorage
            let favorites = JSON.parse(localStorage.getItem('arxer_favorites')) || {};
            let collection = 'default';
            
            // Create collection if it doesn't exist
            if (!favorites[collection]) {
                favorites[collection] = [];
            }
            
            // Check if paper already exists in favorites
            const exists = favorites[collection].some(paper => paper.link === paperLink);
            if (exists) {
                alert('This paper is already in your favorites.');
                return;
            }
            
            // Create paper object
            const paper = {
                title: paperTitle,
                authors: paperAuthors,
                abstract: paperAbstract,
                link: paperLink,
                date: paperDate,
                added: new Date().toISOString()
            };
            
            // Add to favorites
            favorites[collection].push(paper);
            
            // Save to localStorage
            localStorage.setItem('arxer_favorites', JSON.stringify(favorites));
            
            // Show success message
            alert('Paper added to favorites!');
            
            // Update star icon
            const btn = event.target.closest('.favorite-btn');
            if (btn) {
                const icon = btn.querySelector('i');
                icon.classList.remove('far');
                icon.classList.add('fas');
                icon.style.color = '#FCD34D';
            }
        }
        
        // Function to view favorites
        function viewFavorites() {
            // Get favorites from localStorage
            const favorites = JSON.parse(localStorage.getItem('arxer_favorites')) || {};
            const collection = 'default';
            const papers = favorites[collection] || [];
            
            if (papers.length === 0) {
                alert('You have no favorite papers saved.');
                return;
            }
            
            // Create a modal to display favorites
            const modal = document.createElement('div');
            modal.style.position = 'fixed';
            modal.style.top = '0';
            modal.style.left = '0';
            modal.style.width = '100%';
            modal.style.height = '100%';
            modal.style.backgroundColor = 'rgba(0,0,0,0.7)';
            modal.style.zIndex = '1000';
            modal.style.display = 'flex';
            modal.style.justifyContent = 'center';
            modal.style.alignItems = 'center';
            
            const content = document.createElement('div');
            content.style.backgroundColor = '#423D33';
            content.style.borderRadius = '0.5rem';
            content.style.padding = '1.5rem';
            content.style.width = '80%';
            content.style.maxWidth = '800px';
            content.style.maxHeight = '80vh';
            content.style.overflowY = 'auto';
            
            // Add close button
            const closeBtn = document.createElement('button');
            closeBtn.innerHTML = '&times;';
            closeBtn.style.float = 'right';
            closeBtn.style.fontSize = '1.5rem';
            closeBtn.style.fontWeight = 'bold';
            closeBtn.style.color = '#A39E93';
            closeBtn.style.cursor = 'pointer';
            closeBtn.style.border = 'none';
            closeBtn.style.background = 'none';
            closeBtn.onclick = () => document.body.removeChild(modal);
            
            content.appendChild(closeBtn);
            
            // Add title
            const title = document.createElement('h2');
            title.textContent = 'Your Favorite Papers';
            title.style.color = '#F59E0B';
            title.style.marginBottom = '1rem';
            title.style.clear = 'both';
            
            content.appendChild(title);
            
            // Add papers
            papers.forEach(paper => {
                const paperDiv = document.createElement('div');
                paperDiv.className = 'paper-card';
                paperDiv.style.marginBottom = '1rem';
                
                paperDiv.innerHTML = `
                    <h3 style="font-size: 1.125rem; font-weight: 600; color: #FCD34D; margin-bottom: 0.5rem;">${paper.title}</h3>
                    <p style="font-size: 0.875rem; color: #B8B2A7;">${paper.authors}</p>
                    <p style="font-size: 0.875rem; color: #A39E93; margin-top: 0.5rem;">${paper.abstract.substring(0, 150)}${paper.abstract.length > 150 ? '...' : ''}</p>
                    <div style="display: flex; justify-content: space-between; margin-top: 0.5rem;">
                        <span style="font-size: 0.75rem; color: #857F72;">Added: ${new Date(paper.added).toLocaleDateString()}</span>
                        <a href="${paper.link}" target="_blank" style="color: #F59E0B;">View Paper</a>
                    </div>
                `;
                
                content.appendChild(paperDiv);
            });
            
            modal.appendChild(content);
            document.body.appendChild(modal);
        }
        
        // Removed saveSearchHistory function - now using saveSearchToLocalStorage
        
        // Function to view search history
        function viewHistory() {
            // Get history from localStorage
            const history = JSON.parse(localStorage.getItem('arxer_history')) || [];
            
            if (history.length === 0) {
                alert('You have no search history yet.');
                return;
            }
            
            // Create a modal to display history
            const modal = document.createElement('div');
            modal.style.position = 'fixed';
            modal.style.top = '0';
            modal.style.left = '0';
            modal.style.width = '100%';
            modal.style.height = '100%';
            modal.style.backgroundColor = 'rgba(0,0,0,0.7)';
            modal.style.zIndex = '1000';
            modal.style.display = 'flex';
            modal.style.justifyContent = 'center';
            modal.style.alignItems = 'center';
            
            const content = document.createElement('div');
            content.style.backgroundColor = '#423D33';
            content.style.borderRadius = '0.5rem';
            content.style.padding = '1.5rem';
            content.style.width = '80%';
            content.style.maxWidth = '800px';
            content.style.maxHeight = '80vh';
            content.style.overflowY = 'auto';
            
            // Add close button
            const closeBtn = document.createElement('button');
            closeBtn.innerHTML = '&times;';
            closeBtn.style.float = 'right';
            closeBtn.style.fontSize = '1.5rem';
            closeBtn.style.fontWeight = 'bold';
            closeBtn.style.color = '#A39E93';
            closeBtn.style.cursor = 'pointer';
            closeBtn.style.border = 'none';
            closeBtn.style.background = 'none';
            closeBtn.onclick = () => document.body.removeChild(modal);
            
            content.appendChild(closeBtn);
            
            // Add title
            const title = document.createElement('h2');
            title.textContent = 'Your Search History';
            title.style.color = '#F59E0B';
            title.style.marginBottom = '1rem';
            title.style.clear = 'both';
            
            content.appendChild(title);
            
            // Add history entries
            history.forEach(entry => {
                const entryDiv = document.createElement('div');
                entryDiv.className = 'paper-card';
                entryDiv.style.marginBottom = '1rem';
                
                const date = new Date(entry.timestamp).toLocaleString();
                
                entryDiv.innerHTML = `
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <h3 style="font-size: 1.125rem; font-weight: 600; color: #FCD34D;">${entry.query}</h3>
                        <span style="font-size: 0.75rem; color: #857F72;">${date}</span>
                    </div>
                    <p style="font-size: 0.875rem; color: #A39E93;">${entry.num_results} papers found</p>
                    <button onclick="document.querySelector('input[name=\'query\']').value = '${entry.query}'; document.querySelector('form').submit();" 
                            style="margin-top: 0.5rem; padding: 0.25rem 0.5rem; background-color: #504A40; color: #F59E0B; border: none; border-radius: 0.25rem; cursor: pointer;">
                        Search Again
                    </button>
                `;
                
                content.appendChild(entryDiv);
            });
            
            modal.appendChild(content);
            document.body.appendChild(modal);
        }
    </script>
    


    <script>
        // Add explanatory tooltips to new navigation items
        document.addEventListener('DOMContentLoaded', function() {
            const researchAssistantLink = document.querySelector('a[href="chat.php"]');
            if (researchAssistantLink) {
                researchAssistantLink.setAttribute('title', 'Chat with AI about papers and research concepts');
            }
            
            const knowledgeGraphLink = document.querySelector('a[href="knowledge_graph.php"]');
            if (knowledgeGraphLink) {
                knowledgeGraphLink.setAttribute('title', 'Visualize connections between papers and concepts');
            }
            
            // Mobile menu toggle
            const mobileMenuBtn = document.getElementById('mobile-menu-btn');
            const mobileMenu = document.getElementById('mobile-menu');
            
            if (mobileMenuBtn && mobileMenu) {
                mobileMenuBtn.addEventListener('click', function() {
                    mobileMenu.classList.toggle('hidden');
                });
            }
            
            // Mobile history button
            const mobileHistoryBtn = document.getElementById('mobile-history-btn');
            if (mobileHistoryBtn) {
                mobileHistoryBtn.addEventListener('click', function() {
                    // Trigger the same action as the desktop history button
                    showSearchHistory();
                });
            }
        });
    </script>
    
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
                    <a href="knowledge_graph.php" class="text-xs sm:text-sm text-warmgray-400 hover:text-amber-400">Knowledge Graph</a>
                    <a href="view_favorites.php" class="text-xs sm:text-sm text-warmgray-400 hover:text-amber-400">Collections</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>