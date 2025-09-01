<?php
// File: routes/api.php

// ----------------------
// 📦 Items list
// ----------------------
$router->add('GET', '/api/items', function () {
    global $DB;
    header('Content-Type: application/json');
    try {
        $stmt = $DB->query("
            SELECT i.*, COALESCE(SUM(sm.quantity),0) AS current_stock
            FROM items i
            LEFT JOIN stock_movements sm ON sm.item_id = i.id
            GROUP BY i.id
            ORDER BY i.sku ASC
        ");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'count' => count($items), 'data' => $items]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Server error']);
    }
});

// ----------------------
// 📦 Single item by ID
// ----------------------
$router->add('GET', '/api/item', function () {
    global $DB;
    header('Content-Type: application/json');
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) { 
        http_response_code(400); 
        echo json_encode(['ok'=>false,'error'=>'missing id']); 
        return; 
    }
    $stmt = $DB->prepare("SELECT * FROM items WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $it = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$it) { 
        http_response_code(404); 
        echo json_encode(['ok'=>false,'error'=>'not found']); 
        return; 
    }
    echo json_encode(['ok' => true, 'data' => $it]);
});

// ----------------------
// 🏷 Stock check by item + warehouse
// Example: GET /api/stock/check?item_id=1&warehouse_id=2
// ----------------------
$router->add('GET', '/api/stock/check', function () {
    global $DB;
    header('Content-Type: application/json');

    $itemId      = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT);
    $warehouseId = filter_input(INPUT_GET, 'warehouse_id', FILTER_VALIDATE_INT);

    if (!$itemId || !$warehouseId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing item_id or warehouse_id']);
        return;
    }

    try {
        $stmt = $DB->prepare("
            SELECT COALESCE(SUM(
                CASE 
                    WHEN sm.movement_type = 'entry'     AND sm.warehouse_to_id   = :wid THEN sm.quantity
                    WHEN sm.movement_type = 'exit'      AND sm.warehouse_from_id = :wid THEN -sm.quantity
                    WHEN sm.movement_type = 'transfer'  AND sm.warehouse_to_id   = :wid THEN sm.quantity
                    WHEN sm.movement_type = 'transfer'  AND sm.warehouse_from_id = :wid THEN -sm.quantity
                    WHEN sm.movement_type = 'adjustment' AND (sm.warehouse_to_id = :wid OR sm.warehouse_from_id = :wid) THEN sm.quantity
                    ELSE 0
                END
            ),0) AS stock
            FROM stock_movements sm
            WHERE sm.item_id = :item_id
        ");
        $stmt->execute([
            'item_id' => $itemId,
            'wid'     => $warehouseId,
        ]);
        $stock = (int)$stmt->fetchColumn();

        echo json_encode(['ok' => true, 'item_id' => $itemId, 'warehouse_id' => $warehouseId, 'stock' => $stock]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Server error']);
    }
});
