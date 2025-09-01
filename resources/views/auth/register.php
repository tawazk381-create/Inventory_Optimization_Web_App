<?php
// File: resources/views/auth/register.php
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Register New User</h4>
            </div>
            <div class="card-body">
                <form action="<?= BASE_PATH ?>/users/register" method="POST" class="needs-validation" novalidate>
                    <?= csrf_field() ?>

                    <div class="form-group mb-3">
                        <label for="name">Full Name</label>
                        <input type="text" name="name" id="name" class="form-control" required>
                        <div class="invalid-feedback">Name is required.</div>
                    </div>

                    <div class="form-group mb-3">
                        <label for="email">Email Address</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                        <div class="invalid-feedback">A valid email is required.</div>
                    </div>

                    <div class="form-group mb-3">
                        <label for="password">Password</label>
                        <input type="password" name="password" id="password" class="form-control" required minlength="6">
                        <div class="invalid-feedback">Password must be at least 6 characters.</div>
                    </div>

                    <div class="form-group mb-3">
                        <label for="password_confirmation">Confirm Password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required>
                        <div class="invalid-feedback">Please confirm the password.</div>
                    </div>

                    <div class="form-group mb-3">
                        <label for="role_id">Assign Role</label>
                        <select name="role_id" id="role_id" class="form-control" required>
                            <option value="">-- Select Role --</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= (int)$role['id'] ?>">
                                    <?= htmlspecialchars($role['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a role.</div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="<?= BASE_PATH ?>/dashboard" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success">Register User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_PATH ?>/assets/js/form-validation.js"></script>
