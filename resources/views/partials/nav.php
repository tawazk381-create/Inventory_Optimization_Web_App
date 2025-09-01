<?php
// File: resources/views/partials/nav.php
// Navigation links have been removed to prevent duplicate menus.
// The main navigation is already handled in layouts/main.php.
// This file is intentionally left minimal.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!-- Navigation removed: main navigation is provided by layouts/main.php -->
