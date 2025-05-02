<?php
/**
 * Dedicated Research Chat Page for Arxer
 *
 * This page provides a focused interface for chatting with the AI research assistant
 * about papers and scientific concepts.
 */

require_once 'config.php';
require_once 'ai_providers.php';
require_once 'research_chat.php';
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arxer - Research Assistant</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
        /* Formatted AI response styles */
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
            content: '✓';
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
                        }
                    }
                }
            }
        };
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
                    <h2 class="text-lg text-amber-400 hidden sm:block mr-4">Research Assistant</h2>
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
                <a href="chat.php" class="block py-2 px-2 text-amber-400 font-medium">
                    <i class="fas fa-robot mr-2"></i>Research Assistant
                </a>
                <a href="knowledge_graph.php" class="block py-2 px-2 text-warmgray-300 hover:text-amber-400">
                    <i class="fas fa-project-diagram mr-2"></i>Knowledge Graph
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
                <a href="chat.php" class="text-amber-400 hover:text-amber-300 px-3 py-1 rounded hover:bg-warmgray-700">
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
        <div class="flex flex-col lg:flex-row gap-4 h-full">
            <!-- Chat Area -->
            <div class="flex flex-col w-full lg:w-3/4 bg-warmgray-800 rounded-lg p-4 chat-container">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-amber-400">Research Assistant</h2>
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
                <div id="chat-messages" class="flex-grow overflow-y-auto mb-4 bg-warmgray-900 rounded-lg p-4">
                    <div class="bg-warmgray-700 p-3 rounded-lg mb-4 text-warmgray-200">
                        <p class="mb-2">👋 <span class="text-amber-400">Research Assistant</span> here! I can help you with:</p>
                        <ul class="list-disc ml-6 space-y-1">
                            <li>Answering questions about scientific papers</li>
                            <li>Explaining complex research concepts</li>
                            <li>Summarizing topics across multiple papers</li>
                            <li>Finding connections between different research areas</li>
                            <li>Suggesting promising research directions</li>
                        </ul>
                        <p class="mt-2">Try asking me about a specific research topic or load papers from your collections to discuss!</p>
                    </div>
                </div>
                
                <!-- Input Area -->
                <div class="flex space-x-2">
                    <input type="text" id="chat-input" placeholder="Ask about research topics or papers..." 
                           class="flex-grow p-3 bg-warmgray-700 text-warmgray-100 rounded border border-warmgray-600 focus:outline-none focus:ring-1 focus:ring-amber-500" 
                           style="background-color: #504A40 !important; color: #E5E7EB !important;">
                    <button id="send-chat" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-warmgray-900 font-semibold rounded">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="w-full lg:w-1/4 bg-warmgray-800 rounded-lg p-4 space-y-4">
                <!-- Context Selection -->
                <div>
                    <h3 class="text-lg font-semibold text-amber-400 mb-2">Chat Context</h3>
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
                
                <!-- Recent Chats -->
                <div>
                    <h3 class="text-lg font-semibold text-amber-400 mb-2">Recent Chats</h3>
                    <div class="bg-warmgray-700 p-3 rounded-lg max-h-64 overflow-y-auto" id="recent-chats">
                        <div class="text-sm text-warmgray-400 italic">
                            Your recent conversations will appear here
                        </div>
                    </div>
                </div>
                
                <!-- Chat Tips -->
                <div class="bg-warmgray-700 p-3 rounded-lg">
                    <h3 class="text-md font-semibold text-amber-400 mb-1">Tips</h3>
                    <ul class="text-sm text-warmgray-300 space-y-1 list-disc list-inside">
                        <li>Be specific in your questions</li>
                        <li>Use papers from your collections as context</li>
                        <li>Ask for explanations of complex concepts</li>
                        <li>Request comparisons between different papers</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-warmgray-900 py-4 px-4 mt-4">
        <div class="container mx-auto text-center text-warmgray-400 text-sm">
            <p>Arxer - Advanced ArXiv Research Assistant</p>
        </div>
    </footer>

    <script src="https://polyfill.io/v3/polyfill.min.js?features=es6"></script>
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
        let chatHistory = [];
        
        // DOM elements
        const chatInput = document.getElementById('chat-input');
        const sendButton = document.getElementById('send-chat');
        const chatMessages = document.getElementById('chat-messages');
        const contextRadios = document.querySelectorAll('input[name="context-type"]');
        const favoritesSelector = document.getElementById('favorites-selector');
        const recentChats = document.getElementById('recent-chats');
        
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
                    <p><i class="fas fa-info-circle text-amber-400 mr-1"></i> Using ${contextPapers.length} papers as context</p>
                    <ul class="mt-1">${papersList}</ul>
                `;
            } else {
                statusDiv.innerHTML = `<p><i class="fas fa-info-circle text-amber-400 mr-1"></i> No paper context selected</p>`;
            }
            
            // Remove any existing status messages
            const existingStatus = chatMessages.querySelector('.context-status');
            if (existingStatus) {
                chatMessages.removeChild(existingStatus);
            }
            
            statusDiv.classList.add('context-status');
            chatMessages.appendChild(statusDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Load chat history
        function loadChatHistory() {
            try {
                const savedHistory = localStorage.getItem('arxer_chat_history');
                console.log('Loading saved chat history:', savedHistory ? 'found' : 'not found');
                
                if (savedHistory) {
                    chatHistory = JSON.parse(savedHistory);
                    console.log('Loaded', chatHistory.length, 'chat sessions');
                } else {
                    chatHistory = [];
                    console.log('No chat history found, starting fresh');
                }
                
                updateChatHistoryDisplay();
            } catch (e) {
                console.error('Error loading chat history:', e);
                chatHistory = [];
                updateChatHistoryDisplay();
            }
        }
        
        // Update chat history display
        function updateChatHistoryDisplay() {
            recentChats.innerHTML = '';
            
            if (!chatHistory || chatHistory.length === 0) {
                recentChats.innerHTML = `<div class="text-sm text-warmgray-400 italic">Your recent conversations will appear here</div>`;
                return;
            }
            
            console.log('Displaying', Math.min(chatHistory.length, 5), 'recent chats');
            
            // Display most recent chats first
            chatHistory.slice(0, 5).forEach(function(chat, index) {
                if (!chat || !chat.firstQuestion) {
                    console.error('Invalid chat entry:', chat);
                    return;
                }
                
                const chatDiv = document.createElement('div');
                chatDiv.className = 'p-2 hover:bg-warmgray-600 rounded cursor-pointer mb-1';
                chatDiv.innerHTML = `
                    <div class="text-sm font-medium text-amber-400 truncate">${escapeHtml(chat.firstQuestion)}</div>
                    <div class="text-xs text-warmgray-400">${new Date(chat.timestamp).toLocaleString()}</div>
                `;
                recentChats.appendChild(chatDiv);
                
                // Add event listener to load this chat
                chatDiv.addEventListener('click', function() {
                    loadChatSession(chat);
                });
            });
        }
        
        // Load a specific chat session
        function loadChatSession(chat) {
            console.log('Loading chat session:', chat.id);
            if (!chat || !chat.messages || !Array.isArray(chat.messages)) {
                console.error('Invalid chat data:', chat);
                return;
            }
            
            // Clear current messages
            chatMessages.innerHTML = ``;
            
            // Add the welcome message back
            chatMessages.innerHTML = `
                <div class="bg-warmgray-700 p-3 rounded-lg mb-4 text-warmgray-200">
                    <p class="mb-2">👋 <span class="text-amber-400">Research Assistant</span> here! I can help you with:</p>
                    <ul class="list-disc ml-6 space-y-1">
                        <li>Answering questions about scientific papers</li>
                        <li>Explaining complex research concepts</li>
                        <li>Summarizing topics across multiple papers</li>
                        <li>Finding connections between different research areas</li>
                        <li>Suggesting promising research directions</li>
                    </ul>
                    <p class="mt-2">Try asking me about a specific research topic or load papers from your collections to discuss!</p>
                </div>
            `;
            
            // Add session info
            const sessionInfo = document.createElement('div');
            sessionInfo.className = 'bg-amber-700 text-warmgray-100 p-2 rounded-lg mb-4 text-xs';
            sessionInfo.innerHTML = `<p><i class="fas fa-history mr-1"></i> Viewing chat from ${new Date(chat.timestamp).toLocaleString()}</p>`;
            chatMessages.appendChild(sessionInfo);
            
            // Add the messages from this chat
            chat.messages.forEach(function(message) {
                if (!message || !message.role || !message.content) {
                    console.error('Invalid message:', message);
                    return;
                }
                
                if (message.role === 'user') {
                    const userMsg = document.createElement('div');
                    userMsg.className = 'bg-amber-500 text-warmgray-900 p-3 rounded-lg mb-4 ml-auto max-w-3xl';
                    userMsg.innerHTML = `<p>${escapeHtml(message.content)}</p>`;
                    chatMessages.appendChild(userMsg);
                } else {
                    const aiMsg = document.createElement('div');
                    aiMsg.className = 'bg-warmgray-700 p-3 rounded-lg mb-4 text-warmgray-200 max-w-3xl';
                    // Use the stored formatted HTML if available, otherwise format it on the fly
                    const content = message.content;
                    aiMsg.innerHTML = content;
                    chatMessages.appendChild(aiMsg);
                    
                    // Process any LaTeX in restored messages
                    if (window.MathJax) {
                        MathJax.typesetPromise([aiMsg]).catch(function(err) {
                            console.error('MathJax error in history restore:', err);
                        });
                    }
                }
            });
            
            // Restore context papers if available
            if (chat.contextPapers && chat.contextPapers.length > 0) {
                contextPapers = chat.contextPapers;
                
                // Add status message about context
                const statusDiv = document.createElement('div');
                statusDiv.className = 'bg-warmgray-700 p-2 rounded-lg mb-4 text-sm text-warmgray-300 context-status';
                statusDiv.innerHTML = `<p><i class="fas fa-info-circle text-amber-400 mr-1"></i> Using ${contextPapers.length} papers from previous session as context</p>`;
                chatMessages.appendChild(statusDiv);
            }
            
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Send chat message
        function sendChatMessage() {
            const question = chatInput.value.trim();
            
            if (question === '') return;
            
            // Add user message to chat
            const userMsg = document.createElement('div');
            userMsg.className = 'bg-amber-500 text-warmgray-900 p-3 rounded-lg mb-4 ml-auto max-w-3xl';
            userMsg.innerHTML = `<p>${escapeHtml(question)}</p>`;
            chatMessages.appendChild(userMsg);
            
            // Clear input
            chatInput.value = '';
            
            // Add typing indicator
            const typingIndicator = document.createElement('div');
            typingIndicator.className = 'bg-warmgray-700 p-3 rounded-lg mb-4 text-warmgray-200 max-w-3xl typing-indicator';
            typingIndicator.innerHTML = '<p>Thinking</p>';
            chatMessages.appendChild(typingIndicator);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            
            // Get the AI provider
            const provider = document.querySelector('select[name="provider"]')?.value || 'default';
            
            // Send request to server
            const formData = new FormData();
            formData.append('action', 'chat');
            formData.append('question', question);
            formData.append('provider', provider);
            
            // Add context papers if available
            if (contextPapers.length > 0) {
                console.log('Sending context papers:', contextPapers);
                formData.append('context_papers', JSON.stringify(contextPapers));
                
                // Add visual indicator that context is being used
                const contextInfo = document.createElement('div');
                contextInfo.className = 'bg-amber-700 text-warmgray-100 p-2 rounded-lg mb-4 text-xs';
                contextInfo.innerHTML = `<p><i class="fas fa-info-circle mr-1"></i> Using ${contextPapers.length} papers as context</p>`;
                chatMessages.appendChild(contextInfo);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            } else {
                console.log('No context papers to send');
            }
            
            fetch('research_chat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Remove typing indicator
                chatMessages.removeChild(typingIndicator);
                
                // Format the AI response before displaying
                const formattedHtml = formatAIResponse(html);
                
                // Add AI response
                const aiMsg = document.createElement('div');
                aiMsg.className = 'bg-warmgray-700 p-3 rounded-lg mb-4 text-warmgray-200 max-w-3xl';
                aiMsg.innerHTML = formattedHtml;
                chatMessages.appendChild(aiMsg);
                chatMessages.scrollTop = chatMessages.scrollHeight;
                
                // Update chat history
                updateChatHistory(question, formattedHtml);
                
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
                chatMessages.removeChild(typingIndicator);
                
                // Show error message
                const errorMsg = document.createElement('div');
                errorMsg.className = 'bg-red-900 text-warmgray-200 p-3 rounded-lg mb-4 max-w-3xl';
                errorMsg.innerHTML = `<p>Error: ${error.message || 'Could not generate response'}</p>`;
                chatMessages.appendChild(errorMsg);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            });
        }
        
        // Update chat history
        function updateChatHistory(question, response) {
            const now = new Date();
            
            // Check if we have an active session from the last 30 minutes
            let currentSession = null;
            if (chatHistory.length > 0) {
                const lastSession = chatHistory[0];
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
                chatHistory.unshift(currentSession); // Add to beginning
                console.log('Created new chat session');
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
            if (chatHistory.length > 10) {
                chatHistory = chatHistory.slice(0, 10);
            }
            
            // Save to localStorage
            localStorage.setItem('arxer_chat_history', JSON.stringify(chatHistory));
            console.log('Chat history saved:', chatHistory.length, 'sessions with', 
                        currentSession.messages.length/2, 'exchanges in current session');
            
            // Update display
            updateChatHistoryDisplay();
        }
        
        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Format AI response with proper HTML structure
        function formatAIResponse(text) {
            if (!text) return '';
            
            // Process LaTeX expressions first (protect them from other formatting)
            const latexPlaceholders = [];
            let latexCounter = 0;
            
            // Replace LaTeX expressions with placeholders
            text = text.replace(/\\cite[tp]\{([^\}]+)\}/g, (match, cite) => {
                const placeholder = `__LATEX_PLACEHOLDER_${latexCounter}__`;
                latexPlaceholders.push({placeholder, content: match});
                latexCounter++;
                return placeholder;
            });
            
            // Handle numbered lists
            // This regex looks for lines starting with numbers followed by period or parenthesis
            text = text.replace(/(^|\n)(\d+)[.)\s]\s*([^\n]+)/g, (match, newline, number, content) => {
                return `${newline}<li><strong>${number}.</strong> ${content}</li>`;
            });
            
            // Wrap adjacent list items in <ol> tags
            text = text.replace(/(<li>.*?<\/li>)(\s*)(<li>)/g, '$1$3');
            text = text.replace(/(<li>.*?)(?=\n(?!<li>)|$)/g, '<ol>$1</ol>');
            
            // Format paragraphs (lines separated by blank lines)
            const paragraphs = text.split(/\n\s*\n/);
            text = paragraphs.map(p => {
                // Skip if paragraph is already wrapped in HTML tags
                if (p.match(/^\s*<[a-z]+[^>]*>/i)) {
                    return p;
                }
                return `<p>${p.replace(/\n/g, ' ')}</p>`;
            }).join('\n');
            
            // Restore LaTeX expressions
            latexPlaceholders.forEach(({placeholder, content}) => {
                const styledContent = `<span class="citation">${content}</span>`;
                text = text.replace(placeholder, styledContent);
            });
            
            // Handle paper references (e.g., Paper 1, Paper 2)
            text = text.replace(/\b(Paper\s+\d+)\b/g, '<strong>$1</strong>');
            
            // Handle block quotes
            text = text.replace(/\n\s*>\s*([^\n]+)(\n\s*>\s*[^\n]+)*/g, (match) => {
                const content = match.replace(/\n\s*>\s*/g, '\n');
                return `<blockquote>${content}</blockquote>`;
            });
            
            return text;
        }
        
        // Event listeners
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
                warningDiv.innerHTML = '<p><i class="fas fa-exclamation-triangle"></i> Warning: Local storage is not available. Chat history will not be saved.</p>';
                document.querySelector('main .container').prepend(warningDiv);
            }
            
            // Load favorites and chat history
            loadFavorites();
            loadChatHistory();
            
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
            sendButton.addEventListener('click', sendChatMessage);
            
            // Enter key in input
            chatInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    sendChatMessage();
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