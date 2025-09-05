<?php 
// File: app/controllers/AuthController.php

declare(strict_types=1);

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';

class AuthController extends Controller
{
    protected $auth;
    protected string $base;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new Auth();

        // ✅ Normalize BASE_PATH (remove /public if present)
        $this->base = rtrim(str_replace('/public', '', BASE_PATH), '/');
    }

    /** Show login form */
    public function showLogin(): void
    {
        if ($this->auth->check()) {
            redirect($this->base . '/dashboard');
        }

        $this->view('auth/login');
    }

    /** Handle login POST */
    public function login(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->verifyCsrfToken();

        $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            flash('error', 'Email and password are required.');
            redirect($this->base . '/login');
        }

        if (!$this->auth->attempt($email, $password)) {
            usleep(500000); // slow brute force
            flash('error', 'Invalid email or password, or account locked.');
            redirect($this->base . '/login');
        }

        $user = $this->auth->user();
        if (!$user) {
            flash('error', 'Login failed. Please try again.');
            redirect($this->base . '/login');
        }

        // Fetch role
        $role = 'Staff';
        if (!empty($user['role_id'])) {
            $roleStmt = $this->db->prepare("SELECT name FROM roles WHERE id = :id LIMIT 1");
            $roleStmt->execute(['id' => $user['role_id']]);
            $role = $roleStmt->fetchColumn() ?: 'Staff';
        }
        $_SESSION['role_name'] = $role;

        // Success message
        $safeName = htmlspecialchars($user['name'] ?? 'User', ENT_QUOTES, 'UTF-8');
        flash('success', 'Welcome ' . $safeName);

        // ✅ Redirect to dashboard (no /public)
        redirect($this->base . '/dashboard');
    }

    /** Logout */
    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->auth->logout();

        unset($_SESSION['csrf_token']); // regen CSRF after logout

        redirect($this->base . '/login');
    }
}
