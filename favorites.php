<?php
/**
 * Enhanced Favorites and Collections for Arxer
 *
 * This module enables users to organize papers into collections, tag papers,
 * and manage their saved papers more effectively.
 */

require_once 'config.php';

/**
 * Get a user's paper collections
 * 
 * @return array Collections data
 */
function get_collections() {
    // In this implementation, collections are stored in localStorage
    // For a real web application, this would use a database
    return [];
}

/**
 * Create HTML for displaying paper collections and favorites
 * 
 * @return string HTML for favorites view
 */
function render_favorites_page() {
    ob_start();
    
    ?><!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Arxer - My Collections</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
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
                background-color: #504A40 !important;
                color: #E5E7EB !important;
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
            .collection-card {
                background-color: #504A40;
                border: 1px solid #625D52;
                border-radius: 0.5rem;
                padding: 1rem;
                margin-bottom: 1rem;
                transition: all 0.2s ease-in-out;
            }
            .collection-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
                margin: 10% auto;
                padding: 20px;
                border: 1px solid #625D52;
                border-radius: 0.5rem;
                width: 80%;
                max-width: 500px;
            }
            .close {
                color: #A39E93;
                float: right;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }
            .close:hover {
                color: #F59E0B;
            }
            .tag {
                display: inline-block;
                background-color: #625D52;
                color: #FDE68A;
                padding: 0.2rem 0.5rem;
                font-size: 0.75rem;
                border-radius: 0.25rem;
                margin-right: 0.5rem;
                margin-bottom: 0.5rem;
            }
            .date-badge {
                background-color: #423D33;
                padding: 0.2rem 0.4rem;
                border-radius: 0.25rem;
                white-space: nowrap;
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
                        <h2 class="text-lg text-amber-400 hidden sm:block mr-4">My Collections</h2>
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
                    <a href="view_favorites.php" class="block py-2 px-2 text-amber-400 font-medium">
                        <i class="fas fa-folder mr-2"></i>My Collections
                    </a>
                    <a href="chat.php" class="block py-2 px-2 text-warmgray-300 hover:text-amber-400">
                        <i class="fas fa-robot mr-2"></i>Research Assistant
                    </a>
                    <a href="knowledge_graph.php" class="block py-2 px-2 text-warmgray-300 hover:text-amber-400">
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
                    <a href="view_favorites.php" class="text-amber-400 hover:text-amber-300 px-3 py-1 rounded hover:bg-warmgray-700">
                        <i class="fas fa-folder mr-1"></i> Collections
                    </a>
                    <a href="chat.php" class="text-warmgray-300 hover:text-amber-300 px-3 py-1 rounded hover:bg-warmgray-700">
                        <i class="fas fa-robot mr-1"></i> Research Assistant
                    </a>
                    <a href="knowledge_graph.php" class="text-warmgray-300 hover:text-amber-300 px-3 py-1 rounded hover:bg-warmgray-700">
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
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
                <h1 class="text-xl sm:text-2xl font-bold text-amber-400">My Collections</h1>
                <div class="flex space-x-2">
                    <button id="new-collection-btn" class="px-3 py-1 bg-amber-600 text-white rounded hover:bg-amber-500 text-sm sm:text-base">
                        <i class="fas fa-plus mr-1"></i> New Collection
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" id="collections-container">
                <!-- Collections will be populated here via JavaScript -->
                <div class="collection-card bg-warmgray-700 flex flex-col items-center justify-center p-6 cursor-pointer" id="default-collection">
                    <i class="fas fa-star text-amber-400 text-4xl mb-3"></i>
                    <h3 class="text-xl font-semibold text-amber-400">Favorites</h3>
                    <p class="text-warmgray-300 text-center mt-2">Your default collection of favorite papers</p>
                </div>
            </div>

            <div class="mt-8" id="collection-content">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-amber-300" id="current-collection-name">Favorites</h2>
                    <div class="flex space-x-2">
                        <button id="export-collection-btn" class="px-3 py-1 bg-teal-700 text-white rounded hover:bg-teal-600">
                            <i class="fas fa-file-export mr-1"></i> Export
                        </button>
                        <button id="edit-collection-btn" class="px-3 py-1 bg-warmgray-700 text-amber-400 rounded hover:bg-warmgray-600">
                            <i class="fas fa-edit mr-1"></i> Edit
                        </button>
                    </div>
                </div>

                <div id="papers-container">
                    <!-- Papers will be populated here via JavaScript -->
                    <p class="text-center text-warmgray-400 py-8" id="empty-collection-message" style="display: none;">
                        No papers in this collection yet. Add papers from the search results.
                    </p>
                </div>
            </div>
        </main>

        <!-- New Collection Modal -->
        <div id="collection-modal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2 id="collection-modal-title" class="text-xl font-semibold text-amber-400 mb-4">New Collection</h2>
                <form id="collection-form">
                    <div class="mb-4">
                        <label for="collection-name" class="block text-warmgray-200 mb-1">Collection Name:</label>
                        <input type="text" id="collection-name" class="w-full p-2 rounded bg-warmgray-800 text-warmgray-100 border border-warmgray-600 focus:outline-none focus:border-amber-500" required>
                    </div>
                    <div class="mb-4">
                        <label for="collection-description" class="block text-warmgray-200 mb-1">Description (optional):</label>
                        <textarea id="collection-description" class="w-full p-2 rounded bg-warmgray-800 text-warmgray-100 border border-warmgray-600 focus:outline-none focus:border-amber-500" rows="3"></textarea>
                    </div>
                    <input type="hidden" id="collection-id" value="">
                    <div class="flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded hover:bg-amber-500 transition duration-200">
                            <i class="fas fa-save mr-1"></i> Save Collection
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Paper Tag Modal -->
        <div id="tag-modal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2 class="text-xl font-semibold text-amber-400 mb-4">Manage Tags</h2>
                <div id="current-tags" class="mb-4">
                    <label class="block text-warmgray-200 mb-1">Current Tags:</label>
                    <div id="tags-container" class="mb-2">
                        <!-- Tags will be populated here -->
                    </div>
                </div>
                <div class="mb-4">
                    <label for="new-tag" class="block text-warmgray-200 mb-1">Add Tag:</label>
                    <div class="flex">
                        <input type="text" id="new-tag" class="flex-grow p-2 mr-2 rounded bg-warmgray-800 text-warmgray-100 border border-warmgray-600 focus:outline-none focus:border-amber-500">
                        <button id="add-tag-btn" class="px-3 py-1 bg-amber-600 text-white rounded hover:bg-amber-500">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                <input type="hidden" id="tag-paper-index" value="">
                <div class="flex justify-end">
                    <button id="save-tags-btn" class="px-4 py-2 bg-amber-600 text-white rounded hover:bg-amber-500 transition duration-200">
                        <i class="fas fa-save mr-1"></i> Save Tags
                    </button>
                </div>
            </div>
        </div>

        <!-- Export Modal -->
        <div id="export-modal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2 class="text-xl font-semibold text-amber-400 mb-4">Export Collection</h2>
                <div class="mb-4">
                    <label for="export-format" class="block text-warmgray-200 mb-1">Format:</label>
                    <select id="export-format" class="w-full p-2 rounded bg-warmgray-800 text-warmgray-100 border border-warmgray-600 focus:outline-none focus:border-amber-500">
                        <option value="bibtex">BibTeX</option>
                        <option value="ris">RIS (EndNote/Zotero)</option>
                        <option value="csv">CSV</option>
                        <option value="json">JSON</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="export-options" class="block text-warmgray-200 mb-1">Options:</label>
                    <div class="flex flex-col space-y-2">
                        <label class="flex items-center text-warmgray-300">
                            <input type="checkbox" id="include-abstracts" class="mr-2" checked>
                            Include abstracts
                        </label>
                        <label class="flex items-center text-warmgray-300">
                            <input type="checkbox" id="include-tags" class="mr-2" checked>
                            Include tags
                        </label>
                        <label class="flex items-center text-warmgray-300">
                            <input type="checkbox" id="include-notes" class="mr-2">
                            Include notes
                        </label>
                    </div>
                </div>
                <div class="flex justify-end space-x-2">
                    <button id="copy-export-btn" class="px-3 py-2 bg-warmgray-700 text-amber-400 rounded hover:bg-warmgray-600">
                        <i class="fas fa-copy mr-1"></i> Copy
                    </button>
                    <button id="download-export-btn" class="px-4 py-2 bg-amber-600 text-white rounded hover:bg-amber-500 transition duration-200">
                        <i class="fas fa-download mr-1"></i> Download
                    </button>
                </div>
            </div>
        </div>

        <script>
            // Initialize collections and favorites from localStorage
            let collections = {};
            let currentCollection = 'default';
            let paperData = {};

            // Load collections on page load
            document.addEventListener('DOMContentLoaded', function() {
                // Mobile menu toggle
                const mobileMenuBtn = document.getElementById('mobile-menu-btn');
                const mobileMenu = document.getElementById('mobile-menu');
                
                if (mobileMenuBtn && mobileMenu) {
                    mobileMenuBtn.addEventListener('click', function() {
                        mobileMenu.classList.toggle('hidden');
                    });
                }
                
                // Load collections and set up UI
                loadCollections();
                showCollection('default');
                setupEventListeners();
            });

            function loadCollections() {
                try {
                    // Get collections from localStorage
                    collections = JSON.parse(localStorage.getItem('arxer_favorites')) || {};
                    
                    // Ensure the default collection exists
                    if (!collections.default) {
                        collections.default = [];
                    }
                    
                    // Render collection cards
                    renderCollectionCards();
                } catch (e) {
                    console.error("Error loading collections:", e);
                    collections = { default: [] };
                }
            }

            function renderCollectionCards() {
                const container = document.getElementById('collections-container');
                const defaultCard = document.getElementById('default-collection');
                
                // Keep the default card and remove other collection cards
                const cards = container.querySelectorAll('.collection-card:not(#default-collection)');
                cards.forEach(card => card.remove());
                
                // Add custom collection cards
                for (const key in collections) {
                    if (key === 'default') continue;
                    
                    const card = document.createElement('div');
                    card.className = 'collection-card';
                    card.setAttribute('data-collection', key);
                    card.innerHTML = `
                        <div class="flex justify-between items-start">
                            <h3 class="text-xl font-semibold text-amber-400">${key}</h3>
                            <div class="flex space-x-1">
                                <button class="text-warmgray-300 hover:text-amber-400 edit-collection-btn" data-collection="${key}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="text-warmgray-300 hover:text-red-400 delete-collection-btn" data-collection="${key}">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>
                        <p class="text-warmgray-300 text-sm mt-2">
                            ${collections[key].length} papers
                        </p>
                    `;
                    
                    // Add click event to show collection
                    card.addEventListener('click', function(e) {
                        // Don't trigger if clicking on the edit or delete buttons
                        if (e.target.closest('.edit-collection-btn') || e.target.closest('.delete-collection-btn')) {
                            return;
                        }
                        showCollection(key);
                    });
                    
                    // Add edit button event
                    const editBtn = card.querySelector('.edit-collection-btn');
                    if (editBtn) {
                        editBtn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            editCollection(key);
                        });
                    }
                    
                    // Add delete button event
                    const deleteBtn = card.querySelector('.delete-collection-btn');
                    if (deleteBtn) {
                        deleteBtn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            if (confirm(`Are you sure you want to delete the collection "${key}"? This cannot be undone.`)) {
                                deleteCollection(key);
                            }
                        });
                    }
                    
                    container.appendChild(card);
                }
                
                // Set up default collection click handler
                defaultCard.addEventListener('click', function() {
                    showCollection('default');
                });
            }

            function showCollection(collectionKey) {
                // Highlight the selected collection
                document.querySelectorAll('.collection-card').forEach(card => {
                    if ((card.id === 'default-collection' && collectionKey === 'default') ||
                        card.getAttribute('data-collection') === collectionKey) {
                        card.classList.add('bg-warmgray-700');
                        card.classList.remove('bg-warmgray-800');
                    } else {
                        card.classList.remove('bg-warmgray-700');
                        card.classList.add('bg-warmgray-800');
                    }
                });
                
                // Update current collection name
                document.getElementById('current-collection-name').textContent = 
                    collectionKey === 'default' ? 'Favorites' : collectionKey;
                
                // Store current collection
                currentCollection = collectionKey;
                
                // Render papers for this collection
                renderPapers(collectionKey);
                
                // Show/hide edit button for default collection
                document.getElementById('edit-collection-btn').style.display = 
                    collectionKey === 'default' ? 'none' : 'block';
            }

            function renderPapers(collectionKey) {
                const container = document.getElementById('papers-container');
                const emptyMessage = document.getElementById('empty-collection-message');
                container.innerHTML = '';
                
                const papers = collections[collectionKey] || [];
                
                if (papers.length === 0) {
                    if (emptyMessage) {
                        emptyMessage.style.display = 'block';
                    }
                    return;
                }
                
                if (emptyMessage) {
                    emptyMessage.style.display = 'none';
                }
                
                // Render each paper
                papers.forEach((paper, index) => {
                    const paperCard = document.createElement('div');
                    paperCard.className = 'paper-card mb-4';
                    paperCard.innerHTML = `
                        <div class="flex justify-between">
                            <h3 class="text-lg font-semibold text-amber-400">${paper.title}</h3>
                            <div class="flex items-center">
                                <span class="text-xs font-medium text-amber-300 mr-2 date-badge">
                                    <i class="far fa-calendar-alt mr-1"></i> ${paper.date || 'Unknown date'}
                                </span>
                                <div class="flex space-x-2">
                                    <a href="${paper.link}" target="_blank" class="flex-shrink-0">
                                        <i class="fas fa-external-link-alt text-amber-400 hover:text-amber-300"></i>
                                    </a>
                                    <button class="tag-btn flex-shrink-0" data-paper-index="${index}">
                                        <i class="fas fa-tag text-amber-400 hover:text-amber-300"></i>
                                    </button>
                                    <button class="remove-btn flex-shrink-0" data-paper-index="${index}">
                                        <i class="fas fa-trash-alt text-amber-400 hover:text-amber-300"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p class="text-sm text-warmgray-300">${paper.authors}</p>
                    `;
                    
                    // Add tags if available
                    if (paper.tags && paper.tags.length > 0) {
                        const tagsDiv = document.createElement('div');
                        tagsDiv.className = 'mt-2 flex flex-wrap';
                        paper.tags.forEach(tag => {
                            const tagSpan = document.createElement('span');
                            tagSpan.className = 'tag';
                            tagSpan.innerHTML = `<i class="fas fa-tag mr-1"></i> ${tag}`;
                            tagsDiv.appendChild(tagSpan);
                        });
                        paperCard.appendChild(tagsDiv);
                    }
                    
                    // Add the abstract (if available) with show/hide toggle
                    if (paper.abstract) {
                        const abstractDiv = document.createElement('div');
                        abstractDiv.className = 'mt-2';
                        const abstractText = paper.abstract.length > 150 
                            ? paper.abstract.substring(0, 150) + '...' 
                            : paper.abstract;
                        abstractDiv.innerHTML = `
                            <div class="condensed-abstract text-sm text-warmgray-400">${abstractText}</div>
                            <div class="full-abstract text-sm text-warmgray-400" style="display:none">${paper.abstract}</div>
                            ${paper.abstract.length > 150 ? 
                                `<div class="text-right">
                                    <span class="more-btn text-xs font-semibold text-amber-300 cursor-pointer">
                                        <i class="fas fa-chevron-down mr-1"></i> more...
                                    </span>
                                </div>` : ''}
                        `;
                        paperCard.appendChild(abstractDiv);
                        
                        // Add toggle functionality
                        const moreBtn = abstractDiv.querySelector('.more-btn');
                        if (moreBtn) {
                            moreBtn.addEventListener('click', function() {
                                const condensed = abstractDiv.querySelector('.condensed-abstract');
                                const full = abstractDiv.querySelector('.full-abstract');
                                
                                if (condensed.style.display !== 'none') {
                                    condensed.style.display = 'none';
                                    full.style.display = 'block';
                                    moreBtn.innerHTML = '<i class="fas fa-chevron-up mr-1"></i> less...';
                                } else {
                                    condensed.style.display = 'block';
                                    full.style.display = 'none';
                                    moreBtn.innerHTML = '<i class="fas fa-chevron-down mr-1"></i> more...';
                                }
                            });
                        }
                    }
                    
                    // Add button event listeners
                    const tagBtn = paperCard.querySelector('.tag-btn');
                    tagBtn.addEventListener('click', function() {
                        openTagModal(parseInt(this.getAttribute('data-paper-index')));
                    });
                    
                    const removeBtn = paperCard.querySelector('.remove-btn');
                    removeBtn.addEventListener('click', function() {
                        if (confirm('Remove this paper from the collection?')) {
                            removeFromCollection(
                                currentCollection, 
                                parseInt(this.getAttribute('data-paper-index'))
                            );
                        }
                    });
                    
                    container.appendChild(paperCard);
                });
                
                // Store paperData for reference in modals
                paperData = papers;
            }

            function createNewCollection() {
                // Reset form
                document.getElementById('collection-form').reset();
                document.getElementById('collection-id').value = '';
                document.getElementById('collection-modal-title').textContent = 'New Collection';
                
                // Open modal
                document.getElementById('collection-modal').style.display = 'block';
            }

            function editCollection(collectionKey) {
                document.getElementById('collection-name').value = collectionKey;
                document.getElementById('collection-id').value = collectionKey;
                document.getElementById('collection-modal-title').textContent = 'Edit Collection';
                
                // Open modal
                document.getElementById('collection-modal').style.display = 'block';
            }

            function deleteCollection(collectionKey) {
                if (collectionKey === 'default') return;
                
                // Remove collection
                delete collections[collectionKey];
                
                // Save to localStorage
                localStorage.setItem('arxer_favorites', JSON.stringify(collections));
                
                // Update UI
                renderCollectionCards();
                showCollection('default');
            }

            function saveCollection(name, description = '') {
                const oldName = document.getElementById('collection-id').value;
                
                // Validate name
                if (!name || name.trim() === '') {
                    alert('Please enter a collection name');
                    return false;
                }
                
                name = name.trim();
                
                // Prevent overwriting default
                if (name.toLowerCase() === 'default' && oldName !== 'default') {
                    alert('"Default" is a reserved collection name. Please choose a different name.');
                    return false;
                }
                
                // If editing an existing collection
                if (oldName && oldName !== name) {
                    // Transfer papers to new collection name
                    collections[name] = collections[oldName] || [];
                    // Delete old collection
                    if (oldName !== 'default') {
                        delete collections[oldName];
                    }
                } else if (!collections[name]) {
                    // Create new collection
                    collections[name] = [];
                }
                
                // Save to localStorage
                localStorage.setItem('arxer_favorites', JSON.stringify(collections));
                
                // Update UI
                renderCollectionCards();
                showCollection(name);
                
                return true;
            }

            function openTagModal(paperIndex) {
                const paper = paperData[paperIndex];
                if (!paper) return;
                
                // Set paper index for reference
                document.getElementById('tag-paper-index').value = paperIndex;
                
                // Clear existing tags
                const tagsContainer = document.getElementById('tags-container');
                tagsContainer.innerHTML = '';
                
                // Add current tags
                if (paper.tags && paper.tags.length > 0) {
                    paper.tags.forEach(tag => {
                        const tagDiv = document.createElement('div');
                        tagDiv.className = 'tag';
                        tagDiv.innerHTML = `
                            ${tag}
                            <button class="ml-1 text-warmgray-400 hover:text-red-400 delete-tag-btn" data-tag="${tag}">
                                <i class="fas fa-times"></i>
                            </button>
                        `;
                        tagsContainer.appendChild(tagDiv);
                        
                        // Add delete button event
                        tagDiv.querySelector('.delete-tag-btn').addEventListener('click', function() {
                            tagDiv.remove();
                        });
                    });
                } else {
                    tagsContainer.innerHTML = '<p class="text-warmgray-400">No tags yet</p>';
                }
                
                // Clear new tag input
                document.getElementById('new-tag').value = '';
                
                // Open modal
                document.getElementById('tag-modal').style.display = 'block';
            }

            function saveTags() {
                const paperIndex = parseInt(document.getElementById('tag-paper-index').value);
                if (isNaN(paperIndex)) return;
                
                // Get current tags from UI
                const tagsContainer = document.getElementById('tags-container');
                const tagElements = tagsContainer.querySelectorAll('.tag');
                const tags = Array.from(tagElements).map(el => {
                    // Extract just the text content, ignoring the button
                    return el.textContent.trim();
                });
                
                // Update paper data
                if (paperData[paperIndex]) {
                    paperData[paperIndex].tags = tags;
                    
                    // Save to localStorage
                    collections[currentCollection] = paperData;
                    localStorage.setItem('arxer_favorites', JSON.stringify(collections));
                    
                    // Update UI
                    renderPapers(currentCollection);
                }
                
                // Close modal
                document.getElementById('tag-modal').style.display = 'none';
            }

            function addTag() {
                const tagInput = document.getElementById('new-tag');
                const tag = tagInput.value.trim();
                
                if (!tag) return;
                
                // Add tag to UI
                const tagsContainer = document.getElementById('tags-container');
                
                // Remove "no tags" message if present
                const noTagsMsg = tagsContainer.querySelector('p');
                if (noTagsMsg) {
                    noTagsMsg.remove();
                }
                
                const tagDiv = document.createElement('div');
                tagDiv.className = 'tag';
                tagDiv.innerHTML = `
                    ${tag}
                    <button class="ml-1 text-warmgray-400 hover:text-red-400 delete-tag-btn" data-tag="${tag}">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                tagsContainer.appendChild(tagDiv);
                
                // Add delete button event
                tagDiv.querySelector('.delete-tag-btn').addEventListener('click', function() {
                    tagDiv.remove();
                });
                
                // Clear input
                tagInput.value = '';
                tagInput.focus();
            }

            function removeFromCollection(collectionKey, paperIndex) {
                if (!collections[collectionKey]) return;
                
                // Remove paper
                collections[collectionKey].splice(paperIndex, 1);
                
                // Save to localStorage
                localStorage.setItem('arxer_favorites', JSON.stringify(collections));
                
                // Update UI
                renderPapers(collectionKey);
            }

            function exportCollection() {
                // Open the export modal
                document.getElementById('export-modal').style.display = 'block';
            }

            function generateExport(format) {
                const papers = collections[currentCollection] || [];
                const includeAbstracts = document.getElementById('include-abstracts').checked;
                const includeTags = document.getElementById('include-tags').checked;
                const includeNotes = document.getElementById('include-notes').checked;
                
                if (papers.length === 0) {
                    return "No papers in this collection.";
                }
                
                switch (format) {
                    case 'bibtex':
                        return papers.map(paper => {
                            // Extract year from date
                            let year = '2023';
                            if (paper.date && paper.date.match(/\b(19|20)\d{2}\b/)) {
                                year = paper.date.match(/\b(19|20)\d{2}\b/)[0];
                            }
                            
                            // Extract author last name for key
                            const authorParts = (paper.authors || '').split(',');
                            const firstAuthor = authorParts[0] || 'Unknown';
                            const keyName = firstAuthor.split(' ').pop() || 'Unknown';
                            
                            // Clean title for BibTeX
                            const cleanTitle = (paper.title || '').replace(/&/g, '\\&').replace(/_/g, '\\_');
                            
                            // Generate BibTeX entry
                            let entry = `@article{${keyName}${year},\n`;
                            entry += `  title = {${cleanTitle}},\n`;
                            entry += `  author = {${paper.authors || 'Unknown'}},\n`;
                            entry += `  journal = {arXiv preprint},\n`;
                            entry += `  year = {${year}},\n`;
                            entry += `  url = {${paper.link || ''}},\n`;
                            
                            if (includeAbstracts && paper.abstract) {
                                const cleanAbstract = paper.abstract.replace(/&/g, '\\&').replace(/_/g, '\\_');
                                entry += `  abstract = {${cleanAbstract}},\n`;
                            }
                            
                            if (includeTags && paper.tags && paper.tags.length > 0) {
                                entry += `  keywords = {${paper.tags.join(', ')}},\n`;
                            }
                            
                            if (includeNotes && paper.notes) {
                                const cleanNotes = paper.notes.replace(/&/g, '\\&').replace(/_/g, '\\_');
                                entry += `  note = {${cleanNotes}},\n`;
                            }
                            
                            // Remove trailing comma and add closing brace
                            entry = entry.replace(/,\n$/, '\n');
                            entry += "}";
                            
                            return entry;
                        }).join('\n\n');
                        
                    case 'ris':
                        return papers.map(paper => {
                            let entry = 'TY  - JOUR\r\n';
                            entry += `TI  - ${paper.title || 'Unknown Title'}\r\n`;
                            
                            // Add authors
                            const authors = (paper.authors || '').split(',');
                            authors.forEach(author => {
                                if (author.trim()) {
                                    entry += `AU  - ${author.trim()}\r\n`;
                                }
                            });
                            
                            // Extract year from date
                            let year = '2023';
                            if (paper.date && paper.date.match(/\b(19|20)\d{2}\b/)) {
                                year = paper.date.match(/\b(19|20)\d{2}\b/)[0];
                            }
                            
                            entry += `PY  - ${year}\r\n`;
                            entry += 'JO  - arXiv preprint\r\n';
                            entry += `UR  - ${paper.link || ''}\r\n`;
                            
                            if (includeAbstracts && paper.abstract) {
                                entry += `AB  - ${paper.abstract}\r\n`;
                            }
                            
                            if (includeTags && paper.tags && paper.tags.length > 0) {
                                paper.tags.forEach(tag => {
                                    entry += `KW  - ${tag}\r\n`;
                                });
                            }
                            
                            if (includeNotes && paper.notes) {
                                entry += `N1  - ${paper.notes}\r\n`;
                            }
                            
                            entry += 'ER  - ';
                            return entry;
                        }).join('\r\n\r\n');
                        
                    case 'csv':
                        // Start with headers
                        let csv = 'Title,Authors,Year,URL';
                        if (includeAbstracts) csv += ',Abstract';
                        if (includeTags) csv += ',Tags';
                        if (includeNotes) csv += ',Notes';
                        csv += '\n';
                        
                        // Add paper data
                        papers.forEach(paper => {
                            // Escape commas in fields
                            const title = `"${(paper.title || '').replace(/"/g, '""')}"`,
                                authors = `"${(paper.authors || '').replace(/"/g, '""')}"`,
                                link = `"${(paper.link || '').replace(/"/g, '""')}"`,
                                abstract = `"${(paper.abstract || '').replace(/"/g, '""')}"`,
                                tags = `"${(paper.tags || []).join(', ').replace(/"/g, '""')}"`,
                                notes = `"${(paper.notes || '').replace(/"/g, '""')}"`,
                                year = paper.date && paper.date.match(/\b(19|20)\d{2}\b/) ? 
                                    paper.date.match(/\b(19|20)\d{2}\b/)[0] : '2023';
                                
                            // Build CSV line
                            let line = `${title},${authors},${year},${link}`;
                            if (includeAbstracts) line += `,${abstract}`;
                            if (includeTags) line += `,${tags}`;
                            if (includeNotes) line += `,${notes}`;
                            csv += line + '\n';
                        });
                        
                        return csv;
                        
                    case 'json':
                        const exportData = papers.map(paper => {
                            const result = {
                                title: paper.title,
                                authors: paper.authors,
                                date: paper.date,
                                link: paper.link
                            };
                            
                            if (includeAbstracts && paper.abstract) {
                                result.abstract = paper.abstract;
                            }
                            
                            if (includeTags && paper.tags) {
                                result.tags = paper.tags;
                            }
                            
                            if (includeNotes && paper.notes) {
                                result.notes = paper.notes;
                            }
                            
                            return result;
                        });
                        
                        return JSON.stringify(exportData, null, 2);
                        
                    default:
                        return 'Unsupported export format';
                }
            }

            function setupEventListeners() {
                // New collection button
                document.getElementById('new-collection-btn').addEventListener('click', createNewCollection);
                
                // Export collection button
                document.getElementById('export-collection-btn').addEventListener('click', exportCollection);
                
                // Edit collection button
                document.getElementById('edit-collection-btn').addEventListener('click', function() {
                    editCollection(currentCollection);
                });
                
                // Collection form submission
                document.getElementById('collection-form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    const name = document.getElementById('collection-name').value;
                    const description = document.getElementById('collection-description').value;
                    
                    if (saveCollection(name, description)) {
                        document.getElementById('collection-modal').style.display = 'none';
                    }
                });
                
                // Add tag button
                document.getElementById('add-tag-btn').addEventListener('click', addTag);
                
                // New tag input enter key
                document.getElementById('new-tag').addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        addTag();
                    }
                });
                
                // Save tags button
                document.getElementById('save-tags-btn').addEventListener('click', saveTags);
                
                // Export buttons
                document.getElementById('copy-export-btn').addEventListener('click', function() {
                    const format = document.getElementById('export-format').value;
                    const exportText = generateExport(format);
                    
                    // Copy to clipboard
                    const textArea = document.createElement('textarea');
                    textArea.value = exportText;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    
                    // Show feedback
                    this.innerHTML = '<i class="fas fa-check mr-1"></i> Copied!';
                    setTimeout(() => {
                        this.innerHTML = '<i class="fas fa-copy mr-1"></i> Copy';
                    }, 2000);
                });
                
                document.getElementById('download-export-btn').addEventListener('click', function() {
                    const format = document.getElementById('export-format').value;
                    const exportText = generateExport(format);
                    
                    // Get file extension and MIME type
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
                        case 'csv':
                            extension = 'csv';
                            mimeType = 'text/csv';
                            break;
                        case 'json':
                            extension = 'json';
                            mimeType = 'application/json';
                            break;
                        default:
                            extension = 'txt';
                            mimeType = 'text/plain';
                    }
                    
                    // Create filename
                    const collectionName = currentCollection === 'default' ? 'Favorites' : currentCollection;
                    const filename = `arxer_${collectionName.replace(/\s+/g, '_')}.${extension}`;
                    
                    // Download file
                    const blob = new Blob([exportText], { type: mimeType });
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
                    
                    // Show feedback
                    this.innerHTML = '<i class="fas fa-check mr-1"></i> Downloaded!';
                    setTimeout(() => {
                        this.innerHTML = '<i class="fas fa-download mr-1"></i> Download';
                    }, 2000);
                });
                
                // Close buttons for modals
                document.querySelectorAll('.modal .close').forEach(function(closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        document.querySelectorAll('.modal').forEach(function(modal) {
                            modal.style.display = 'none';
                        });
                    });
                });
                
                // Close modal when clicking outside
                document.querySelectorAll('.modal').forEach(function(modal) {
                    modal.addEventListener('click', function(e) {
                        if (e.target === this) {
                            this.style.display = 'none';
                        }
                    });
                });
            }
        </script>

        <footer class="mt-6 py-4 px-3 bg-warmgray-800 border-t border-warmgray-700">
            <div class="container mx-auto">
                <div class="flex flex-col sm:flex-row justify-between items-center">
                    <div class="mb-2 sm:mb-0">
                        <p class="text-xs sm:text-sm text-warmgray-400 text-center sm:text-left">&copy; <?php echo date('Y'); ?> Arxer - Advanced ArXiv Research Assistant</p>
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
    </body>
    </html>
    <?php
    
    $html = ob_get_clean();
    return $html;
}

/**
 * Handle the viewing of favorites
 */
function view_favorites() {
    echo render_favorites_page();
    exit;
}

/**
 * Add a tag to a paper in a specific collection
 * 
 * @param string $collection_name Collection name
 * @param int $paper_index Paper index in collection
 * @param string $tag Tag to add
 * @return bool Success status
 */
function add_tag_to_paper($collection_name, $paper_index, $tag) {
    // This would connect to a database in a real application
    // For now, return success
    return true;
}

/**
 * Remove a tag from a paper
 * 
 * @param string $collection_name Collection name
 * @param int $paper_index Paper index in collection
 * @param string $tag Tag to remove
 * @return bool Success status
 */
function remove_tag_from_paper($collection_name, $paper_index, $tag) {
    // This would connect to a database in a real application
    // For now, return success
    return true;
}