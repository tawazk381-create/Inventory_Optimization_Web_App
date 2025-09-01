<?php 
// File: app/core/Auth.php 
declare(strict_types=1);

class Auth
{
    protected $db;
    protected ?array $user = null;

    // Config
    private int $maxAttempts = 5;       // Maximum allowed failed attempts
    private int $lockoutTime = 300;     // Lockout duration in seconds (5 min)

    public function __construct()
    {
        global $DB;
        $this->db = $DB;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->loadFromSession();
    }

    /**
     * Attempt to log in a user by verifying email & password.
     */
    public function attempt(string $email, string $password): bool
    {
        // Check lockout status
        if ($this->isLockedOut()) {
            return false;
        }

        // Sanitize email
        $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);

        $stmt = $this->db->prepare("
            SELECT id, name, email, password_hash, role_id, is_active
            FROM users 
            WHERE email = :email 
            LIMIT 1
        ");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verify password + active status
        if (
            $user &&
            !empty($user['password_hash']) &&
            $user['is_active'] == 1 &&
            password_verify($password, $user['password_hash'])
        ) {
            // Reset failed attempts
            $_SESSION['failed_attempts'] = 0;
            $_SESSION['lockout_time'] = null;

            // Successful login → regenerate session ID
            session_regenerate_id(true);

            // Store minimal safe user data in session
            $_SESSION['user_id']   = (int)$user['id'];
            $_SESSION['user_name'] = htmlspecialchars($user['name'] ?? 'User', ENT_QUOTES, 'UTF-8');
            $_SESSION['role_id']   = $user['role_id'] ?? null;

            $this->user = $user;
            return true;
        }

        // Record failed attempt
        $this->recordFailedAttempt();

        // Security: delay brute-force attempts
        usleep(500000); // 0.5s delay

        return false;
    }

    /** Check if user is authenticated. */
    public function check(): bool
    {
        return $this->user !== null;
    }

    /** Get authenticated user data. */
    public function user(): ?array
    {
        return $this->user;
    }

    /** Get authenticated user ID. */
    public function id(): ?int
    {
        return $this->user['id'] ?? null;
    }

    /** Logout securely and destroy session. */
    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();
        session_regenerate_id(true);

        $this->user = null;
    }

    /** Load user from session if logged in. */
    protected function loadFromSession(): void
    {
        if (!empty($_SESSION['user_id'])) {
            $stmt = $this->db->prepare("
                SELECT id, name, email, role_id, is_active
                FROM users 
                WHERE id = :id 
                LIMIT 1
            ");
            $stmt->execute(['id' => $_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            // ✅ Ensure user still exists and is active
            if ($user && $user['is_active'] == 1) {
                $this->user = $user;
            } else {
                // Auto-logout if invalid session
                $this->logout();
            }
        }
    }

    /** Check if login is currently locked out. */
    private function isLockedOut(): bool
    {
        return (!empty($_SESSION['lockout_time']) && time() < $_SESSION['lockout_time']);
    }

    /** Record a failed login attempt and enforce lockout if needed. */
    private function recordFailedAttempt(): void
    {
        $_SESSION['failed_attempts'] = ($_SESSION['failed_attempts'] ?? 0) + 1;

        if ($_SESSION['failed_attempts'] >= $this->maxAttempts) {
            $_SESSION['lockout_time'] = time() + $this->lockoutTime;
        }
    }
}
