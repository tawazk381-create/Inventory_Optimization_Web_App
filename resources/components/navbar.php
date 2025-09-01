<?php // File: resources/components/navbar.php
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
  <a class="navbar-brand" href="/"><?= e(APP_NAME) ?></a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navMain">
    <span class="navbar-toggler-icon"></span>
  </button>
  <div class="collapse navbar-collapse" id="navMain">
    <ul class="navbar-nav mr-auto">
      <li class="nav-item"><a class="nav-link" href="/items">Items</a></li>
      <li class="nav-item"><a class="nav-link" href="/warehouses">Warehouses</a></li>
      <li class="nav-item"><a class="nav-link" href="/suppliers">Suppliers</a></li>
      <li class="nav-item"><a class="nav-link" href="/optimizations">Optimizations</a></li>
    </ul>
    <div class="form-inline my-2 my-lg-0">
      <button id="darkToggle" class="btn btn-outline-secondary btn-sm mr-2">Dark</button>
      <?php if (!empty($user)): ?>
        <span class="mr-2"><?= e($user['name'] ?? $user['email']) ?></span>
        <a class="btn btn-sm btn-outline-danger" href="/logout">Logout</a>
      <?php else: ?>
        <a class="btn btn-sm btn-primary" href="/login">Login</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
