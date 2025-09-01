<?php // File: app/views/stock/exit.php ?>
<?php require APP_PATH . '/views/layouts/main.php'; ?>

<div class="container">
    <h2>Stock Exit</h2>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/stock/exit">
        <div class="form-group">
            <label>Item:</label>
            <select name="item_id" class="form-control" required>
                <?php foreach ($items as $item): ?>
                    <option value="<?= $item['id'] ?>">
                        <?= htmlspecialchars($item['name']) ?> (Available: <?= $item['stock'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Quantity to Exit:</label>
            <input type="number" name="quantity" min="1" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-danger">Record Exit</button>
    </form>
</div>
