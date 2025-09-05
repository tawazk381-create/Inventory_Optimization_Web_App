<?php 
// File: app/core/Controller.php
declare(strict_types=1);

class Controller
{
    protected $db;
    protected $viewPath = __DIR__ . '/../../resources/views/';
    protected $auth;

    public function __construct()
    {
        global $DB;
        $this->db = $DB;
        // Auth is lightweight; instantiate if class available
        if (class_exists('Auth')) {
            $this->auth = new Auth();
        } else {
            $this->auth = null;
        }
    }

    /**
     * Require the current user to have one of the given role names.
     * $roles can be a string or array of allowed role names.
     * Redirects to login if unauthenticated.
     */
    protected function requireRole($roles)
    {
        $allowed = is_array($roles) ? $roles : [$roles];
        $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        if (!$this->auth || !$this->auth->check()) {
            if ($currentPath !== '/login') {
                redirect('/login');
            }
            exit;
        }

        $user = $this->auth->user();
        $roleId = $user['role_id'] ?? null;
        if (!$roleId) {
            $_SESSION['flash'] = [
                'type'    => 'danger',
                'message' => 'You do not have permission to access this page.',
            ];
            redirect('/dashboard');
            exit;
        }

        $stmt = $this->db->prepare("SELECT name FROM roles WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $roleId]);
        $roleRow = $stmt->fetch();
        $roleName = $roleRow['name'] ?? null;

        if (!$roleName || !in_array($roleName, $allowed, true)) {
            $_SESSION['flash'] = [
                'type'    => 'danger',
                'message' => 'You do not have permission to access this page.',
            ];
            redirect('/dashboard');
            exit;
        }
    }

    /**
     * Render a view wrapped in the main layout.
     * Supports both `.php` and `.blade.php` extensions.
     * Pass view "path" WITHOUT extension.
     */
    protected function view(string $path, array $data = [])
    {
        // Normalize: remove any passed extension
        $path = preg_replace('/\.(php|blade\.php)$/i', '', $path);

        $filePhp   = $this->viewPath . $path . '.php';
        $fileBlade = $this->viewPath . $path . '.blade.php';

        if (file_exists($filePhp)) {
            $file = $filePhp;
        } elseif (file_exists($fileBlade)) {
            $file = $fileBlade;
        } else {
            throw new Exception("View not found: $filePhp or $fileBlade");
        }

        // expose $data variables
        extract($data, EXTR_SKIP);

        // Pass $file into layout scope
        $title = $data['title'] ?? null;
        require $this->viewPath . 'layouts/main.php';
    }

    protected function json($data, int $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
    }

    protected function redirect(string $url)
    {
        redirect($url);
    }

    // ------------------------------------------------------------
    // ✅ CSRF Protection
    // ------------------------------------------------------------
    protected function verifyCsrfToken(): void
    {
        $token = $_POST['csrf_token'] ?? null;
        $valid = isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);

        if (!$valid) {
            http_response_code(419); // 419 Authentication Timeout
            echo "CSRF token mismatch.";
            exit;
        }
    }
}
