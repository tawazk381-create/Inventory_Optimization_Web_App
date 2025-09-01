<?php // File: resources/views/classification/index.php
/** Expects $rows */
?>
<h1><?= e($title ?? 'ABC/XYZ') ?></h1>

<table class="table table-sm table-striped">
    <thead>
        <tr>
            <th>SKU</th>
            <th>Name</th>
            <th>Annual Demand</th>
            <th>Annual Value</th>
            <th>ABC</th>
            <th>XYZ (CV)</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= e($r['sku'] ?? '-') ?></td>
            <td><?= e($r['name'] ?? '-') ?></td>
            <td><?= number_format($r['annual_demand'] ?? 0, 2) ?></td>
            <td><?= number_format($r['annual_value'] ?? 0, 2) ?></td>
            <td><?= e($r['abc'] ?? '-') ?></td>
            <td>
                <?= e($r['xyz'] ?? '-') ?>
                <?php if (isset($r['cv']) && $r['cv'] !== null): ?>
                    (<?= number_format($r['cv'], 3) ?>)
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
