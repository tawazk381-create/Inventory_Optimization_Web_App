<?php
// File: database/seeds/run_php_seeds.php
// Execute seed files in alphabetical order
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php'; // provides $DB

if (!isset($DB) || !($DB instanceof PDO)) {
    echo "ERROR: PDO \$DB not found. Check config/database.php\n";
    exit(1);
}

$seedDir = __DIR__;
$files = glob($seedDir . '/*.php');
sort($files, SORT_STRING);

foreach ($files as $f) {
    if (basename($f) === basename(__FILE__)) continue;
    echo "Running seed: " . basename($f) . " ...\n";
    $callable = include $f;
    if (!is_callable($callable)) {
        echo "  => Skipped (not callable)\n";
        continue;
    }
    try {
        $callable($DB);
        echo "  => OK\n";
    } catch (Exception $e) {
        echo "  => ERROR: " . $e->getMessage() . PHP_EOL;
        exit(1);
    }
}

echo "All seeds done.\n";

// =======================================
// ✅ Extra verification: print items table
// =======================================
try {
    $stmt = $DB->query("
        SELECT id, sku, name, unit_price, total_stock,
               avg_daily_demand, lead_time_days, safety_stock, reorder_point
        FROM items
        ORDER BY id ASC
    ");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($items) {
        echo "\nSeeded Items:\n";
        echo str_repeat("-", 90) . "\n";
        foreach ($items as $it) {
            printf(
                "ID:%d | SKU:%s | %s | Price: %.2f | Stock: %d | Demand: %d | Lead: %d | Safety: %d | Reorder: %d\n",
                $it['id'],
                $it['sku'],
                $it['name'],
                $it['unit_price'],
                $it['total_stock'],
                $it['avg_daily_demand'],
                $it['lead_time_days'],
                $it['safety_stock'],
                $it['reorder_point']
            );
        }
        echo str_repeat("-", 90) . "\n";
    } else {
        echo "⚠️ No items found in database.\n";
    }
} catch (Throwable $e) {
    echo "⚠️ Could not fetch items for verification: " . $e->getMessage() . "\n";
}
