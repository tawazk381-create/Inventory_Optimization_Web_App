<?php
// File: app/controllers/DashboardController.php

declare(strict_types=1);

require_once __DIR__ . '/../core/Controller.php';

class DashboardController extends Controller
{
    public function index(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $role = $_SESSION['role_name'] ?? 'Guest';

        $widgets = [];

        // Common widgets for Staff, Manager, Admin
        if (in_array($role, ['Staff','Manager','Admin'])) {
            $widgets[] = [
                'title' => 'Items',
                'desc'  => 'View and manage items',
                'url'   => BASE_PATH . '/items',
                'icon'  => '📦'
            ];
            $widgets[] = [
                'title' => 'Stock Entry',
                'desc'  => 'Record stock manually or via scanner',
                'url'   => BASE_PATH . '/items',
                'icon'  => '📝'
            ];
            $widgets[] = [
                'title' => 'Suppliers',
                'desc'  => 'Manage supplier information',
                'url'   => BASE_PATH . '/suppliers',
                'icon'  => '🚚'
            ];
            $widgets[] = [
                'title' => 'Warehouses',
                'desc'  => 'View and manage warehouses',
                'url'   => BASE_PATH . '/warehouses',
                'icon'  => '🏭'
            ];
        }

        // Manager and Admin extras
        if (in_array($role, ['Manager','Admin'])) {
            $widgets[] = [
                'title' => 'Reports',
                'desc'  => 'Generate inventory reports',
                'url'   => BASE_PATH . '/reports',
                'icon'  => '📊'
            ];
            $widgets[] = [
                'title' => 'Optimization',
                'desc'  => 'Run inventory optimization',
                'url'   => BASE_PATH . '/optimizations/view',
                'icon'  => '⚙️'
            ];
            $widgets[] = [
                'title' => 'Classification',
                'desc'  => 'ABC / XYZ classification',
                'url'   => BASE_PATH . '/classification',
                'icon'  => '📂'
            ];
        }

        // Admin only
        if ($role === 'Admin') {
            $widgets[] = [
                'title' => 'User Management',
                'desc'  => 'Create and manage system users',
                'url'   => BASE_PATH . '/users/register',
                'icon'  => '👥'
            ];
        }

        $title = "Dashboard - " . htmlspecialchars($role);

        // ✅ Use Controller::view() instead of global render()
        $this->view('dashboard/index', compact('widgets', 'role', 'title'));
    }
}
