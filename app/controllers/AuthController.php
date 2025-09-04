<?php 
// File: app/controllers/AuthController.php

declare(strict_types=1);

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';

class AuthController extends Controller
{
    protected $auth;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new Auth();
    }

    /** Show login form */
    public function showLogin(): void
    {
        // ✅ If already logged in, do not show login form again
        if ($this->auth->check()) {
            redirect('/dashboard');
        }

        $this->view('auth/login');
    }

    /** Handle login POST */
    public function login(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // ✅ Use global CSRF verifier
        verify_csrf();

        // Sanitize inputs
        $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            flash('error', 'Email and password are required.');
            redirect('/login');
        }

        // ✅ Use Auth service to handle login securely
        if (!$this->auth->attempt($email, $password)) {
            // Throttle brute force attempts
            usleep(500000); // 0.5 sec delay
            flash('error', 'Invalid email or password, or account locked.');
            redirect('/login');
        }

        // ✅ At this point, login succeeded — only because Auth::attempt returned true
        $user = $this->auth->user();

        // Defensive: double-check $user is not null
        if (!$user) {
            flash('error', 'Login failed. Please try again.');
            redirect('/login');
        }

        // Store role name for convenience
        $role = 'Staff';
        if (!empty($user['role_id'])) {
            $roleStmt = $this->db->prepare("SELECT name FROM roles WHERE id = :id LIMIT 1");
            $roleStmt->execute(['id' => $user['role_id']]);
            $role = $roleStmt->fetchColumn() ?: 'Staff';
        }
        $_SESSION['role_name'] = $role;

        // Escape name for output
        $safeName = htmlspecialchars($user['name'] ?? 'User', ENT_QUOTES, 'UTF-8');
        flash('success', 'Welcome ' . $safeName);

        // ✅ Always redirect to dashboard after login
        redirect('/dashboard');
    }

    /** Logout */
    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->auth->logout();

        // ✅ Regenerate CSRF token after logout for safety
        unset($_SESSION['_token']);

        redirect('/login');
    }
}
