<?php
// File: resources/views/auth/login.php
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="<?= htmlspecialchars(BASE_PATH . '/login', ENT_QUOTES, 'UTF-8') ?>">
                    <?= csrf_field() ?>

                    <div class="form-group mb-3">
                        <label for="email">Email Address</label>
                        <input 
                            type="email" 
                            name="email" 
                            id="email" 
                            class="form-control" 
                            required 
                            autofocus
                        >
                    </div>

                    <div class="form-group mb-3">
                        <label for="password">Password</label>
                        <input 
                            type="password" 
                            name="password" 
                            id="password" 
                            class="form-control" 
                            required
                        >
                    </div>

                    <!-- ❌ Removed insecure role selector
                    <div class="form-group mb-3">
                        <label for="role">Login As</label>
                        <select name="role" id="role" class="form-control" required>
                            <option value="">-- Select Role --</option>
                            <option value="Admin">Admin</option>
                            <option value="Manager">Manager</option>
                            <option value="Staff">Staff</option>
                        </select>
                    </div>
                    -->

                    <button type="submit" class="btn btn-success w-100">Login</button>
                </form>
            </div>
        </div>
    </div>
</div>
