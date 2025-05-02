<?php
/**
 * Shared header for Arxer application
 * This file contains the common header used across all pages
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arxer - <?php echo $page_title ?? 'ArXiv Paper Browser'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <?php include 'shared_styles.php'; ?>
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
                    <h2 class="text-lg text-amber-400 hidden sm:block mr-4"><?php echo $page_title ?? 'ArXiv Paper Browser'; ?></h2>
                    <button id="mobile-menu-btn" class="p-1 text-amber-400 sm:hidden focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
            <!-- Mobile menu -->
            <div id="mobile-menu" class="sm:hidden hidden mt-2 py-2 border-t border-warmgray-700">
                <a href="index.php" class="block py-2 px-2 <?php echo $current_page === 'search' ? 'text-amber-400' : 'text-warmgray-300 hover:text-amber-400'; ?>">
                    <i class="fas fa-search mr-2"></i>Search
                </a>
                <a href="view_favorites.php" class="block py-2 px-2 <?php echo $current_page === 'collections' ? 'text-amber-400' : 'text-warmgray-300 hover:text-amber-400'; ?>">
                    <i class="fas fa-folder mr-2"></i>My Collections
                </a>
                <a href="chat.php" class="block py-2 px-2 <?php echo $current_page === 'chat' ? 'text-amber-400' : 'text-warmgray-300 hover:text-amber-400'; ?>">
                    <i class="fas fa-robot mr-2"></i>Research Assistant
                </a>
                <a href="tutor.php" class="block py-2 px-2 <?php echo $current_page === 'tutor' ? 'text-amber-400' : 'text-warmgray-300 hover:text-amber-400'; ?>">
                    <i class="fas fa-graduation-cap mr-2"></i>AI Tutor
                </a>
                <a href="knowledge_graph.php" class="block py-2 px-2 <?php echo $current_page === 'knowledge_graph' ? 'text-amber-400' : 'text-warmgray-300 hover:text-amber-400'; ?>">
                    <i class="fas fa-project-diagram mr-2"></i>Knowledge Graph
                </a>
            </div>
        </div>
    </header>

    <!-- Main Navigation Bar - Desktop Only -->
    <nav class="hidden sm:block bg-warmgray-800 border-b border-warmgray-700 py-2 px-4">
        <div class="container mx-auto flex items-center justify-between">
            <div class="flex space-x-4">
                <a href="index.php" class="<?php echo $current_page === 'search' ? 'text-amber-400' : 'text-warmgray-300 hover:text-amber-300'; ?> px-3 py-1 rounded hover:bg-warmgray-700">
                    <i class="fas fa-search mr-1"></i> Search
                </a>
                <a href="view_favorites.php" class="<?php echo $current_page === 'collections' ? 'text-amber-400' : 'text-warmgray-300 hover:text-amber-300'; ?> px-3 py-1 rounded hover:bg-warmgray-700">
                    <i class="fas fa-folder mr-1"></i> Collections
                </a>
                <a href="chat.php" class="<?php echo $current_page === 'chat' ? 'text-amber-400' : 'text-warmgray-300 hover:text-amber-300'; ?> px-3 py-1 rounded hover:bg-warmgray-700">
                    <i class="fas fa-robot mr-1"></i> Research Assistant
                </a>
                <a href="tutor.php" class="<?php echo $current_page === 'tutor' ? 'text-amber-400' : 'text-warmgray-300 hover:text-amber-300'; ?> px-3 py-1 rounded hover:bg-warmgray-700">
                    <i class="fas fa-graduation-cap mr-1"></i> AI Tutor
                </a>
                <a href="knowledge_graph.php" class="<?php echo $current_page === 'knowledge_graph' ? 'text-amber-400' : 'text-warmgray-300 hover:text-amber-300'; ?> px-3 py-1 rounded hover:bg-warmgray-700">
                    <i class="fas fa-project-diagram mr-1"></i> Knowledge Graph
                </a>
            </div>
            <div>
                <span class="text-warmgray-400 text-sm">Powered by <span class="text-amber-400">AI</span></span>
            </div>
        </div>
    </nav>