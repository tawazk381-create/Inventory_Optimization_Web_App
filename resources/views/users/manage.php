<?php 
// File: resources/views/users/manage.php
// Variables passed from controller: $users (array of user records)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$roleName = $_SESSION['role_name'] ?? '';
$currentUserId = $_SESSION['user_id'] ?? null;
?>
<div class="row">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">User Management</h4>
                <a href="<?= BASE_PATH ?>/users/register" class="btn btn-light btn-sm">+ Add New User</a>
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
                                        <td><?= htmlspecialchars($u['name']) ?></td>
                                        <td><?= htmlspecialchars($u['email']) ?></td>
                                        <td><?= htmlspecialchars($u['role_name'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($u['created_at'] ?? '') ?></td>
                                        <td>
                                            <?php if ($roleName === 'Admin'): ?>
                                                <?php if ((int)$u['id'] === (int)$currentUserId): ?>
                                                    <em>Protected</em>
                                                <?php else: ?>
                                                    <form action="<?= BASE_PATH ?>/users/delete" 
                                                          method="POST" 
                                                          onsubmit="return confirm('Are you sure you want to remove this user?');"
                                                          style="display:inline;">
                                                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                                        <?= csrf_field() ?>
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
