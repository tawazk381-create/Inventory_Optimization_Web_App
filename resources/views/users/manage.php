<?php 
// File: resources/views/users/manage.php
// Variables passed from controller: $users (array of user records)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$roleName = $_SESSION['role_name'] ?? '';
$currentUserId = $_SESSION['user_id'] ?? null;

// ✅ Normalize BASE_PATH so it never includes '/public'
$actionBase = rtrim(str_replace('/public', '', BASE_PATH), '/');
?>
<div class="row">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">User Management</h4>
                <a href="<?= htmlspecialchars($actionBase . '/users/register', ENT_QUOTES, 'UTF-8') ?>" class="btn btn-light btn-sm">+ Add New User</a>
            </div>
            <div class="card-body">
                <?php if (!empty($users)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Created At</th>
                                    <th style="width: 120px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td><?= (int)$u['id'] ?></td>
                                        <td><?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($u['role_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($u['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?php if ($roleName === 'Admin'): ?>
                                                <?php if ((int)$u['id'] === (int)$currentUserId): ?>
                                                    <em>Protected</em>
                                                <?php else: ?>
                                                    <form action="<?= htmlspecialchars($actionBase . '/users/delete', ENT_QUOTES, 'UTF-8') ?>" 
                                                          method="POST" 
                                                          onsubmit="return confirm('Are you sure you want to remove this user?');"
                                                          style="display:inline;">
                                                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm">
                                                            Delete
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <em>No actions</em>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No users found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
