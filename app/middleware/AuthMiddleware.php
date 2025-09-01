<?php
// File: app/middleware/AuthMiddleware.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class AuthMiddleware
{
    /**
     * Verify login and (optionally) the user's role.
     * @param array|null $roles e.g. ['Admin','Manager']
     */
    public static function check(array $roles = null): void
    {
        // prevent redirect loops
        $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        // Must be logged in
        if (empty($_SESSION['user_id'])) {
            if ($currentPath !== '/login') {
                redirect('/login');
            }
            exit;
        }

        // If roles specified, enforce them
        if ($roles !== null) {
            $userRole = $_SESSION['role_name'] ?? null;
            if (!$userRole || !in_array($userRole, $roles, true)) {
                $_SESSION['flash'] = [
                    'type'    => 'danger',
                    'message' => 'You do not have permission to access this page.',
                ];
                if ($currentPath !== '/dashboard') {
                    redirect('/dashboard');
                }
                exit;
            }
        }
    }
}
