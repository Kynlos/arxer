<?php
/**
 * Paper Recommendations for Arxer
 *
 * This module provides paper recommendation functionality based on semantic similarity,
 * user reading history, and popular papers in related categories.
 */

require_once 'config.php';

/**
 * Get similar papers based on content similarity
 *
 * @param array $paper The reference paper
 * @param int $limit Maximum number of recommendations
 * @return array List of recommended papers
 */
function get_similar_papers($paper, $limit = 3) {
    if (empty($paper) || empty($paper['abstract'])) {
        return [];
    }
    
    // Get keywords from the paper abstract and title
    $keywords = extract_keywords($paper['title'] . ' ' . $paper['abstract']);
    
    if (empty($keywords)) {
        return [];
    }
    
    // Build a search query based on the most important keywords
    $query = implode(' ', array_slice($keywords, 0, 5));
    
    // Search ArXiv for papers matching these keywords
    $similar_papers = search_arxiv($query, $limit + 1);
    
    // Remove the reference paper from the results if present
    $similar_papers = array_filter($similar_papers, function($p) use ($paper) {
        return $p['link'] !== $paper['link'];
    });
    
    // Return the top papers
    return array_slice($similar_papers, 0, $limit);
}

/**
 * Get papers that users also viewed based on local popularity
 *
 * @param array $paper The reference paper
 * @param int $limit Maximum number of recommendations
 * @return array List of recommended papers
 */
function get_users_also_viewed($paper, $limit = 3) {
    // In a real application, this would use a database of user reading patterns
    // For now, we'll simulate it using local favorites and history
    
    $history = [];
    $favorites = [];
    
    // Try to get history and favorites from localStorage via JavaScript
    echo '<script>
        window.arxerGetRecentHistory = function() {
            try {
                return JSON.parse(localStorage.getItem("arxer_history")) || [];
            } catch (e) {
                console.error("Error getting history:", e);
                return [];
            }
        };
        
        window.arxerGetAllFavorites = function() {
            try {
                const favs = JSON.parse(localStorage.getItem("arxer_favorites")) || {};
                let allPapers = [];
                for (const collection in favs) {
                    allPapers = allPapers.concat(favs[collection] || []);
                }
                return allPapers;
            } catch (e) {
                console.error("Error getting favorites:", e);
                return [];
            }
        };
    </script>';
    
    // For now, return papers in the same categories
    if (empty($paper['categories'])) {
        return [];
    }
    
    // Use the categories to find related papers
    $category_query = implode(' OR ', $paper['categories']);
    $related_papers = search_arxiv($category_query, $limit + 1);
    
    // Remove the reference paper from the results
    $related_papers = array_filter($related_papers, function($p) use ($paper) {
        return $p['link'] !== $paper['link'];
    });
    
    return array_slice($related_papers, 0, $limit);
}

/**
 * Extract important keywords from text
 *
 * @param string $text Text to analyze
 * @return array List of keywords
 */
function extract_keywords($text) {
    // Remove common punctuation and convert to lowercase
    $text = strtolower(preg_replace('/[^\w\s]/', ' ', $text));
    
    // Split into words
    $words = preg_split('/\s+/', $text);
    
    // Common academic stopwords to remove
    $stopwords = [
        'the', 'and', 'in', 'of', 'to', 'a', 'is', 'that', 'for', 'on', 'by',
        'with', 'as', 'this', 'we', 'are', 'be', 'or', 'an', 'it', 'can',
        'from', 'at', 'which', 'such', 'have', 'been', 'has', 'was', 'were',
        'they', 'their', 'these', 'those', 'then', 'than', 'not', 'but', 'also',
        'paper', 'study', 'research', 'method', 'result', 'results', 'show',
        'using', 'used', 'use', 'based', 'approach', 'data'
    ];
    
    // Filter out stopwords and short words
    $words = array_filter($words, function($word) use ($stopwords) {
        return !in_array($word, $stopwords) && strlen($word) > 2;
    });
    
    // Count word frequency
    $word_counts = array_count_values($words);
    
    // Sort by frequency
    arsort($word_counts);
    
    // Return top keywords
    return array_keys(array_slice($word_counts, 0, 10));
}

/**
 * Get trending papers based on a specific research area
 *
 * @param string $area Research area or category
 * @param int $limit Maximum number of papers
 * @return array List of trending papers
 */
function get_trending_papers($area = '', $limit = 5) {
    // In a real application, this would use an API for trending papers
    // For now, we'll use recent papers with most relevant results
    
    $query = empty($area) ? 'computer science' : $area;
    $sort_by = 'date-desc'; // Sort by most recent
    
    $papers = search_arxiv($query, $limit * 2);
    
    // Filter and sort papers (in a real system, this would be based on download/citation metrics)
    usort($papers, function($a, $b) {
        // Sort by date for now
        return strcmp($b['date'] ?? '', $a['date'] ?? '');
    });
    
    return array_slice($papers, 0, $limit);
}