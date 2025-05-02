<?php
/**
 * Favorites and Collections Viewer for Arxer
 *
 * This file handles displaying and managing favorites and collections
 */

require_once 'config.php';
require_once 'favorites.php';

// If this page is accessed directly, show the favorites view
if (basename($_SERVER['SCRIPT_FILENAME']) == basename(__FILE__)) {
    view_favorites();
}