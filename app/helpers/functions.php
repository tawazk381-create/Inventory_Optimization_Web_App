<?php 
// File: app/helpers/functions.php

declare(strict_types=1);

/**
 * Generate a full URL based on BASE_PATH
 */
function base_path(string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    return (defined('BASE_PATH') ? BASE_PATH : '') . $path;
}

/**
 * Redirect helper with loop protection and safety
 */
function redirect(string $url): void
{
    // Normalize current request URI
    $current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

    // Allow only relative paths unless explicitly absolute
    if (strpos($url, 'http') !== 0) {
        $url = base_path($url);
    }

    // ✅ Prevent infinite loop
    if ($current === parse_url($url, PHP_URL_PATH)) {
        return;
    }

    header('Location: ' . $url, true, 302);
    exit;
}

/**
 * CSRF helpers
 */
function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    // ✅ Use consistent field name: csrf_token
    return '<input type="hidden" name="csrf_token" value="' 
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $token = $_POST['csrf_token'] ?? '';
        if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(419);
            echo "CSRF token mismatch.";
            exit;
        }
    }
}

/**
 * Flash messaging
 */
function flash(string $key, string $message = null)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if ($message === null) {
        $msg = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $msg;
    }

    $_SESSION['flash'][$key] = $message;
}

/**
 * Escape output safely for HTML
 */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Retrieve old form input after validation error
 */
function old(string $key, $default = '')
{
    return htmlspecialchars($_POST[$key] ?? $default, ENT_QUOTES, 'UTF-8');
}
