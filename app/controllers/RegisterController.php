<?php  
// File: app/controllers/RegisterController.php

declare(strict_types=1);

class RegisterController extends Controller
{
    protected $auth;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new Auth();
        $this->requireRole('Admin'); // Only Admin can manage users
    }

    /**
     * Show the registration form
     */
    public function showForm(): void
    {
        $roleModel = new Role();
        $roles = $roleModel->all();

        $this->view('auth/register', [
            'title' => 'Register New User',
            'roles' => $roles,
        ]);
    }

    /**
     * Handle user registration
     */
    public function handleRegister(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // ✅ Verify CSRF token
        $submittedToken = $_POST['csrf_token'] ?? '';
        $validToken     = $_SESSION['csrf_token'] ?? '';

        if (!$submittedToken || !$validToken || !hash_equals($validToken, $submittedToken)) {
            flash('error', 'CSRF token mismatch. Please try again.');
            redirect('/users/register');
        }

        global $DB;

        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirmation'] ?? '';
        $roleId   = (int)($_POST['role_id'] ?? 0);

        // Validate required fields
        if ($name === '' || $email === '' || $password === '') {
            flash('error', 'All fields are required.');
            redirect('/users/register');
        }

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Invalid email address.');
            redirect('/users/register');
        }

        // Validate password match
        if ($password !== $confirm) {
            flash('error', 'Passwords do not match.');
            redirect('/users/register');
        }

        // 🔒 Password strength check
        if (strlen($password) < 8) {
            flash('error', 'Password must be at least 8 characters long.');
            redirect('/users/register');
        }

        // Ensure email is unique
        $check = $DB->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $check->execute(['email' => $email]);
        if ($check->fetch()) {
            flash('error', 'Email already registered.');
            redirect('/users/register');
        }

        // ✅ Secure password hash
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Insert new user
        $stmt = $DB->prepare("
            INSERT INTO users (name, email, password_hash, role_id, is_active, created_at) 
            VALUES (:name, :email, :hash, :role, 1, NOW())
        ");
        $stmt->execute([
            'name'  => $name,
            'email' => $email,
            'hash'  => $hash,
            'role'  => $roleId ?: null,
        ]);

        flash('success', 'User registered successfully.');
        redirect('/users/manage');
    }

    /**
     * Show all users for management
     */
    public function manageUsers(): void
    {
        $userModel = new User();
        $users = $userModel->getAllWithRoles();

        $this->view('users/manage', [
            'title' => 'Manage Users',
            'users' => $users,
        ]);
    }

    /**
     * Delete a user (staff removal) - Admin only
     */
    public function deleteUser(int $id): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // ✅ Verify CSRF token before allowing deletion
        $submittedToken = $_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? '');
        $validToken     = $_SESSION['csrf_token'] ?? '';

        if (!$submittedToken || !$validToken || !hash_equals($validToken, $submittedToken)) {
            flash('error', 'CSRF token mismatch. Please try again.');
            redirect('/users/manage');
        }

        $userModel = new User();

        if ($userModel->delete($id)) {
            flash('success', 'User removed successfully.');
        } else {
            flash('error', 'Failed to remove user.');
        }

        redirect('/users/manage');
    }
}
