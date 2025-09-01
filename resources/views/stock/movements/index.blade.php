<!-- File: resources/views/stock/movements/index.blade.php -->

<div class="container">
    <h1 class="mt-4 mb-4"><?= htmlspecialchars($title) ?></h1>

    <!-- Transactions Table -->
    <div class="card">
        <div class="card-header">
            <strong>All Stock Movements</strong>
        </div>
        <div class="card-body">
            <?php if (!empty($transactions)): ?>
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Type</th>
                            <th>Warehouse From</th>
                            <th>Warehouse To</th>
                            <th>User</th>
                            <th>Reason</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $txn): ?>
                            <tr>
                                <td><?= htmlspecialchars($txn['id'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($txn['item_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($txn['quantity'] ?? 0) ?></td>
                                <td>
                                    <?php
                                        $type = $txn['movement_type'] ?? '';
                                        switch ($type) {
                                            case 'entry':
                                                echo '<span class="badge bg-success">Entry</span>';
                                                break;
                                            case 'exit':
                                                echo '<span class="badge bg-danger">Exit</span>';
                                                break;
                                            case 'transfer':
                                                echo '<span class="badge bg-primary">Transfer</span>';
                                                break;
                                            case 'adjustment':
                                                echo '<span class="badge bg-warning text-dark">Adjustment</span>';
                                                break;
                                            default:
                                                echo htmlspecialchars($type ?: '-');
                                        }
                                    ?>
                                </td>
                                <!-- Model returns warehouse_from / warehouse_to (names), not *_name -->
                                <td><?= htmlspecialchars($txn['warehouse_from'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($txn['warehouse_to'] ?? '-') ?></td>
                                <!-- user_name may not be provided by model; fall back to supplier_name or 'System' -->
                                <td>
                                  <?= htmlspecialchars(
                                        $txn['user_name'] ??
                                        $txn['user'] ??
                                        $txn['supplier_name'] ??
                                        'System'
                                  ) ?>
                                </td>
                                <!-- use 'reference' as reason (model uses reference) -->
                                <td><?= htmlspecialchars($txn['reference'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($txn['created_at'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No stock movements recorded yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
