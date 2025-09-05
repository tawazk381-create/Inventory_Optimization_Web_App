<?php
// File: app/controllers/DashboardController.php

declare(strict_types=1);

require_once __DIR__ . '/../core/Controller.php';

class DashboardController extends Controller
{
    protected string $base;

    public function __construct()
    {
        parent::__construct();

        // ✅ Normalize BASE_PATH (remove /public if present)
        $this->base = rtrim(str_replace('/public', '', BASE_PATH), '/');
    }

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
                'url'   => $this->base . '/items',
                'icon'  => '📦'
            ];
            $widgets[] = [
                'title' => 'Stock Entry',
                'desc'  => 'Record stock manually or via scanner',
                'url'   => $this->base . '/stock-movements/entry',
                'icon'  => '📝'
            ];
            $widgets[] = [
                'title' => 'Suppliers',
                'desc'  => 'Manage supplier information',
                'url'   => $this->base . '/suppliers',
                'icon'  => '🚚'
            ];
            $widgets[] = [
                'title' => 'Warehouses',
                'desc'  => 'View and manage warehouses',
                'url'   => $this->base . '/warehouses',
                'icon'  => '🏭'
            ];
        }

        // Manager and Admin extras
        if (in_array($role, ['Manager','Admin'])) {
            $widgets[] = [
                'title' => 'Reports',
                'desc'  => 'Generate inventory reports',
                'url'   => $this->base . '/reports',
                'icon'  => '📊'
            ];
            $widgets[] = [
                'title' => 'Optimization',
                'desc'  => 'Run inventory optimization',
                'url'   => $this->base . '/optimizations/view',
                'icon'  => '⚙️'
            ];
            $widgets[] = [
                'title' => 'Classification',
                'desc'  => 'ABC / XYZ classification',
                'url'   => $this->base . '/classification',
                'icon'  => '📂'
            ];
        }

        // Admin only
        if ($role === 'Admin') {
            $widgets[] = [
                'title' => 'User Management',
                'desc'  => 'Create and manage system users',
                'url'   => $this->base . '/users/manage',
                'icon'  => '👥'
            ];
        }

        $title = "Dashboard - " . htmlspecialchars($role, ENT_QUOTES, 'UTF-8');

        $this->view('dashboard/index', compact('widgets', 'role', 'title'));
    }
}
