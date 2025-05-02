# Arxer Changelog

## [1.0.4] - 2024-05-04

### Added
- New AI Tutor feature that focuses on helping users learn concepts and understand papers
- Specialized tutoring system prompt that uses the Socratic method to guide learning
- Paper selection for tutoring context from user collections
- Persistent tutoring session history with localStorage
- Enhanced response formatting specifically designed for educational content

### Changed
- Updated navigation in header and all page templates to include AI Tutor across all pages
- Added consistent AI Tutor link to both mobile and desktop navigation in index.php, chat.php, knowledge_graph.php, and favorites.php
- Improved session management for both chat and tutoring features
- Enhanced styling for educational content with special formatting for questions, examples, and key concepts

## [1.0.3] - 2024-05-03

### Added
- AI provider selection persistence across all pages using localStorage
- Better handling for AI model selection in paper explanation feature
- Enhanced AI response styling with themed colors, better formatting, and improved readability

### Fixed
- Fixed AI provider selection not persisting in index.php when using the settings modal
- Improved AI explanation formatting with better typography and page layout
- Fixed issues with paperData not being properly passed to the paper_explainer.php
- Removed chat-like language and extraneous content from AI explanations
- Added null checks for elements in favorites rendering to prevent JavaScript errors
- Fixed Tailwind configuration in all pages for better consistency and error handling

### Changed
- Completely redesigned AI explanation output styling with better section headers, lists, and highlighting
- Enhanced visual styling across all pages with consistent headers, footers and navigation
- Improved paper explanation prompt to generate clearer, more structured content
- Added styling for emphasized text in paper explanations for clearer communication
- Improved code organization and maintainability with better error handling

## [1.0.2] - 2024-05-02

### Added
- Support for Gemma 3 4B-IT-QAT model as a local provider option
- Model selection persistence across browser sessions
- Visual indicator when viewing past chat sessions

### Fixed
- Chat history now groups related conversations properly
- Multiple interactions within 30 minutes are now shown as a single conversation
- Fixed chat history restore functionality
- Added validation to prevent errors with invalid chat data
- Added localStorage availability check with user warning
- Improved debugging logs throughout the application
- Fixed JavaScript syntax errors in chat.php and knowledge_graph.php
- Fixed Tailwind configuration to prevent errors when CDN is not accessible
- Fixed "Cannot read properties of null" errors in view_favorites.php
- Removed duplicate footer in chat.php

### Changed
- Chat sessions now retain timestamp of last interaction
- Updated history display to show most recent sessions first
- Improved error handling for chat history storage
- Enhanced session restore with better visual indicators
- Consistent header and footer styling across all pages
- Switched from static Tailwind CSS to CDN script for better compatibility
- Added alternative polyfill for better compatibility with MathJax
- Improved AI response formatting with better styled lists, paragraphs and text highlights

## [Unreleased]