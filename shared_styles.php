<?php
/**
 * Shared styles for Arxer application
 * This file contains common style elements used across all pages
 */
?>
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
    
    .container {
        max-width: 1200px;
    }
    
    /* Paper card styling */
    .paper-card {
        border: 1px solid #504A40;
        transition: all 0.2s ease;
    }
    
    .paper-card:hover {
        border-color: #F59E0B;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
    
    /* Collection styling */
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
    
    /* Custom checkboxes */
    .custom-checkbox {
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
    
    .custom-checkbox:checked {
        background-color: #F59E0B;
        border-color: #F59E0B;
    }
    
    .custom-checkbox:checked::after {
        content: '✓';
        position: absolute;
        top: 0;
        left: 3px;
        color: #FFFFFF;
        font-size: 12px;
        font-weight: bold;
    }
    
    /* Category tag styling */
    .category-tag {
        background-color: rgba(80, 74, 64, 0.5);
        color: #E5E7EB;
        font-size: 0.75rem;
        padding: 0.1rem 0.5rem;
        border-radius: 0.25rem;
        margin-right: 0.25rem;
        margin-bottom: 0.25rem;
        display: inline-block;
        border: 1px solid #625D52;
    }
    
    /* Button styling */
    .btn-primary {
        background-color: #F59E0B;
        color: #1C1917;
        font-weight: 600;
        border-radius: 0.25rem;
        padding: 0.5rem 1rem;
        transition: background-color 0.2s ease;
    }
    
    .btn-primary:hover {
        background-color: #D97706;
    }
    
    .btn-secondary {
        background-color: #504A40;
        color: #E5E7EB;
        font-weight: 600;
        border-radius: 0.25rem;
        padding: 0.5rem 1rem;
        transition: background-color 0.2s ease;
    }
    
    .btn-secondary:hover {
        background-color: #625D52;
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