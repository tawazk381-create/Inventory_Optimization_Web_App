<?php 
// File: resources/views/auth/login.php

// Normalize BASE_PATH so it never includes '/public'
$actionBase = rtrim(str_replace('/public', '', BASE_PATH), '/');
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h4 class="mb-3 text-center">Login</h4>

                <form method="POST" 
                      action="<?= htmlspecialchars($actionBase . '/login', ENT_QUOTES, 'UTF-8') ?>" 
                      novalidate
                      class="needs-validation">

                    <!-- ✅ CSRF token field -->
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
                        <div class="invalid-feedback">Please enter your email.</div>
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
                        <div class="invalid-feedback">Please enter your password.</div>
                    </div>

                    <button type="submit" class="btn btn-success w-100">Login</button>
                </form>
            </div>
        </div>
    </div>
</div>
