<?php // File: app/helpers/rbac.php
declare(strict_types=1);

/**
 * Get the current logged-in user's role name.
 *
 * @param PDO|null $db Optional PDO instance. If omitted, the global $DB will be used.
 * @return string|null Role name or null if not logged in / not available.
 * @throws RuntimeException if no PDO instance is available.
 */
function current_user_role_name(?PDO $db = null): ?string
{
    // Use provided PDO or fall back to global $DB
    if ($db === null) {
        global $DB;
        $db = $DB ?? null;
    }

    // Ensure session user id is available
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    if (!($db instanceof PDO)) {
        throw new RuntimeException('PDO instance not available for role lookup.');
    }

    $stmt = $db->prepare("SELECT r.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id LIMIT 1");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row['name'] ?? null;
}

/**
 * Check whether the current user has the given role (or one of the given roles).
 *
 * @param string|array $role Role name or array of role names.
 * @return bool
 */
function user_has_role($role): bool
{
    $name = current_user_role_name();
    if (!$name) {
        return false;
    }

    if (is_array($role)) {
        return in_array($name, $role, true);
    }

    return $name === $role;
}
