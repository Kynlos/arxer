<?php
/**
 * Advanced Search Utilities for Arxer
 *
 * This module provides enhanced search functionality including field-specific search,
 * date filtering, category filtering, and sorting options.
 */

/**
 * Parse advanced search query with field-specific search operators
 *
 * Supports: author:"Name", title:"words", abstract:"text", date>2020, date<2022, etc.
 *
 * @param string $query The user's search query
 * @return array Parsed query parts
 */
function parse_advanced_query($query) {
    $parsed = [
        'basic' => [],      // Regular search terms
        'author' => [],     // Author search
        'title' => [],      // Title search
        'abstract' => [],   // Abstract search
        'date_from' => '',  // Date range start
        'date_to' => '',    // Date range end
        'category' => [],   // ArXiv categories
    ];
    
    // Extract quoted sections first
    $quoted_pattern = '/([a-z]+:)?"([^"]+)"/';
    preg_match_all($quoted_pattern, $query, $quoted_matches, PREG_SET_ORDER);
    
    // Process quoted terms and remove them from the query
    foreach ($quoted_matches as $match) {
        $full_match = $match[0];
        $field = isset($match[1]) ? rtrim($match[1], ':') : 'basic';
        $term = $match[2];
        
        if (isset($parsed[$field])) {
            $parsed[$field][] = $term;
        } else {
            $parsed['basic'][] = $term;
        }
        
        // Remove processed term from query
        $query = str_replace($full_match, '', $query);
    }
    
    // Process date ranges
    if (preg_match('/date>([0-9]{4})/', $query, $date_from)) {
        $parsed['date_from'] = $date_from[1];
        $query = str_replace($date_from[0], '', $query);
    }
    
    if (preg_match('/date<([0-9]{4})/', $query, $date_to)) {
        $parsed['date_to'] = $date_to[1];
        $query = str_replace($date_to[0], '', $query);
    }
    
    // Process field-specific terms without quotes
    $field_pattern = '/([a-z]+):([^\s"]+)/';
    preg_match_all($field_pattern, $query, $field_matches, PREG_SET_ORDER);
    
    foreach ($field_matches as $match) {
        $full_match = $match[0];
        $field = $match[1];
        $term = $match[2];
        
        if (isset($parsed[$field])) {
            $parsed[$field][] = $term;
        } else {
            $parsed['basic'][] = $term;
        }
        
        // Remove processed term from query
        $query = str_replace($full_match, '', $query);
    }
    
    // Remaining terms are basic search terms
    $remaining_terms = array_filter(explode(' ', $query), 'trim');
    $parsed['basic'] = array_merge($parsed['basic'], $remaining_terms);
    
    return $parsed;
}

/**
 * Build ArXiv API query from parsed query parts
 *
 * @param array $parsed_query Parsed query from parse_advanced_query
 * @return string ArXiv API query string
 */
function build_arxiv_query($parsed_query) {
    $query_parts = [];
    
    // Check for common research field names and map them to appropriate categories
    $field_mappings = [
        'astrophysics' => 'cat:astro-ph*',
        'physics' => 'cat:physics*',
        'mathematics' => 'cat:math*',
        'computer science' => 'cat:cs*',
        'quantitative biology' => 'cat:q-bio*',
        'quantitative finance' => 'cat:q-fin*',
        'statistics' => 'cat:stat*',
        'electrical engineering' => 'cat:eess*',
        'economics' => 'cat:econ*',
    ];
    
    // Check if any basic terms match field names
    $remaining_basic = [];
    $added_field_query = false;
    
    if (!empty($parsed_query['basic'])) {
        foreach ($parsed_query['basic'] as $term) {
            $term_lower = strtolower($term);
            if (isset($field_mappings[$term_lower])) {
                // If it's a recognized field, add its category mapping
                $query_parts[] = $field_mappings[$term_lower];
                $added_field_query = true;
            } else {
                // Otherwise keep it as a regular search term
                $remaining_basic[] = $term;
            }
        }
        
        // Replace the original basic terms with the filtered list
        $parsed_query['basic'] = $remaining_basic;
    }
    
    // Process basic terms (all fields)
    if (!empty($parsed_query['basic'])) {
        $basic_terms = [];
        foreach ($parsed_query['basic'] as $term) {
            // If term contains spaces and isn't already quoted, add quotes
            if (strpos($term, ' ') !== false && substr($term, 0, 1) !== '"' && substr($term, -1) !== '"') {
                $term = '"' . $term . '"';
            }
            $basic_terms[] = 'all:' . $term;
        }
        $query_parts[] = implode(' AND ', $basic_terms);
    }
    
    // Process author search
    if (!empty($parsed_query['author'])) {
        $author_terms = [];
        foreach ($parsed_query['author'] as $term) {
            // If term contains spaces and isn't already quoted, add quotes
            if (strpos($term, ' ') !== false && substr($term, 0, 1) !== '"' && substr($term, -1) !== '"') {
                $term = '"' . $term . '"';
            }
            $author_terms[] = 'au:' . $term;
        }
        $query_parts[] = implode(' AND ', $author_terms);
    }
    
    // Process title search
    if (!empty($parsed_query['title'])) {
        $title_terms = [];
        foreach ($parsed_query['title'] as $term) {
            // If term contains spaces and isn't already quoted, add quotes
            if (strpos($term, ' ') !== false && substr($term, 0, 1) !== '"' && substr($term, -1) !== '"') {
                $term = '"' . $term . '"';
            }
            $title_terms[] = 'ti:' . $term;
        }
        $query_parts[] = implode(' AND ', $title_terms);
    }
    
    // Process abstract search
    if (!empty($parsed_query['abstract'])) {
        $abstract_terms = [];
        foreach ($parsed_query['abstract'] as $term) {
            // If term contains spaces and isn't already quoted, add quotes
            if (strpos($term, ' ') !== false && substr($term, 0, 1) !== '"' && substr($term, -1) !== '"') {
                $term = '"' . $term . '"';
            }
            $abstract_terms[] = 'abs:' . $term;
        }
        $query_parts[] = implode(' AND ', $abstract_terms);
    }
    
    // Process category search
    if (!empty($parsed_query['category'])) {
        $category_terms = [];
        foreach ($parsed_query['category'] as $term) {
            // Clean category ID
            $term = trim($term);
            $category_terms[] = 'cat:' . $term;
        }
        if (count($category_terms) > 1) {
            $query_parts[] = '(' . implode(' OR ', $category_terms) . ')';
        } else {
            $query_parts[] = $category_terms[0];
        }
    }
    
    // Build date range if needed
    if (!empty($parsed_query['date_from']) || !empty($parsed_query['date_to'])) {
        if (!empty($parsed_query['date_from']) && !empty($parsed_query['date_to'])) {
            $query_parts[] = 'submittedDate:[' . $parsed_query['date_from'] . ' TO ' . $parsed_query['date_to'] . ']';
        } elseif (!empty($parsed_query['date_from'])) {
            $query_parts[] = 'submittedDate:[' . $parsed_query['date_from'] . ' TO 9999]';
        } else {
            $query_parts[] = 'submittedDate:[0 TO ' . $parsed_query['date_to'] . ']';
        }
    }
    
    // Combine all query parts with AND
    return implode(' AND ', $query_parts);
}

/**
 * Filter search results based on additional criteria not supported by ArXiv API
 *
 * @param array $papers Paper results to filter
 * @param array $filters Additional filtering criteria
 * @return array Filtered papers
 */
function filter_search_results($papers, $filters) {
    if (empty($filters)) {
        return $papers;
    }
    
    return array_filter($papers, function($paper) use ($filters) {
        // Filter by date range
        if (isset($filters['date_from']) && !empty($filters['date_from'])) {
            $paper_year = date('Y', strtotime($paper['date']));
            if ($paper_year < $filters['date_from']) {
                return false;
            }
        }
        
        if (isset($filters['date_to']) && !empty($filters['date_to'])) {
            $paper_year = date('Y', strtotime($paper['date']));
            if ($paper_year > $filters['date_to']) {
                return false;
            }
        }
        
        // Filter by categories
        if (isset($filters['categories']) && !empty($filters['categories'])) {
            $match = false;
            foreach ($filters['categories'] as $category) {
                if (in_array($category, $paper['categories'])) {
                    $match = true;
                    break;
                }
            }
            if (!$match) {
                return false;
            }
        }
        
        return true;
    });
}

/**
 * Generate a help tooltip for advanced search syntax
 *
 * @return string HTML for search help tooltip
 */
function get_advanced_search_help() {
    return '<div class="bg-warmgray-700 p-4 rounded-lg shadow-lg">
        <h3 class="text-amber-400 text-lg font-semibold mb-2">Advanced Search Syntax</h3>
        <div class="space-y-2 text-sm">
            <p class="text-warmgray-300">Use these operators for targeted searching:</p>
            <ul class="list-disc pl-5 text-warmgray-300 space-y-1">
                <li><code class="bg-warmgray-800 px-1 rounded">author:"John Smith"</code> - Search for papers by a specific author</li>
                <li><code class="bg-warmgray-800 px-1 rounded">title:"quantum computing"</code> - Search in paper titles</li>
                <li><code class="bg-warmgray-800 px-1 rounded">abstract:"deep learning"</code> - Search in paper abstracts</li>
                <li><code class="bg-warmgray-800 px-1 rounded">date>2020</code> - Papers published after 2020</li>
                <li><code class="bg-warmgray-800 px-1 rounded">date<2022</code> - Papers published before 2022</li>
                <li><code class="bg-warmgray-800 px-1 rounded">neural networks author:"Hinton"</code> - Combined search</li>
            </ul>
            <p class="text-warmgray-300 mt-2">Use quotes for phrases: <code class="bg-warmgray-800 px-1 rounded">"reinforcement learning"</code></p>
        </div>
    </div>';
}

/**
 * Get common ArXiv categories for filtering
 *
 * @return array Category groups with their subcategories
 */
function get_arxiv_categories() {
    return [
        'Computer Science' => [
            'cs.AI' => 'Artificial Intelligence',
            'cs.CL' => 'Computation and Language',
            'cs.CV' => 'Computer Vision',
            'cs.LG' => 'Machine Learning',
            'cs.NE' => 'Neural and Evolutionary Computing',
            'cs.RO' => 'Robotics',
            'cs.IR' => 'Information Retrieval',
            'cs.SE' => 'Software Engineering',
            'cs.CY' => 'Computers and Society',
        ],
        'Physics' => [
            'physics.comp-ph' => 'Computational Physics',
            'physics.optics' => 'Optics',
            'physics.med-ph' => 'Medical Physics',
            'astro-ph' => 'Astrophysics',
            'cond-mat' => 'Condensed Matter',
            'quant-ph' => 'Quantum Physics',
        ],
        'Mathematics' => [
            'math.NA' => 'Numerical Analysis',
            'math.OC' => 'Optimization and Control',
            'math.PR' => 'Probability',
            'math.ST' => 'Statistics Theory',
            'stat.ML' => 'Machine Learning',
        ],
        'Biology' => [
            'q-bio.GN' => 'Genomics',
            'q-bio.MN' => 'Molecular Networks',
            'q-bio.NC' => 'Neurons and Cognition',
            'q-bio.QM' => 'Quantitative Methods',
        ],
    ];
}

/**
 * Format search query for display, highlighting advanced operators
 * 
 * @param string $query Search query
 * @return string Formatted query with highlighted operators
 */
function format_query_for_display($query) {
    // Highlight field operators
    $query = preg_replace('/(author|title|abstract):/', '<span class="text-amber-400">$1:</span>', $query);
    
    // Highlight date operators
    $query = preg_replace('/(date[<>][0-9]{4})/', '<span class="text-amber-400">$1</span>', $query);
    
    // Highlight quoted phrases
    $query = preg_replace('/"([^"]+)"/', '"<span class="text-amber-300">$1</span>"', $query);
    
    return $query;
}