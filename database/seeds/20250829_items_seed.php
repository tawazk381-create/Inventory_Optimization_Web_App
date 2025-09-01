<?php
// File: database/seeds/20250829_items_seed.php

return function(PDO $DB){
    $DB->beginTransaction();

    // Sample items with planning fields
    $items = [
        [
            'sku'              => 'ITEM-001',
            'barcode'          => '123456789001',
            'name'             => 'Steel Bolts Box',
            'description'      => 'Box of 100 high-tensile steel bolts',
            'supplier_id'      => 1,
            'warehouse_id'     => 1,
            'unit'             => 'box',
            'unit_price'       => 150.00,
            'total_stock'      => 500,
            'avg_daily_demand' => 20,
            'lead_time_days'   => 7,
            'safety_stock'     => 100,
            'reorder_point'    => 140
        ],
        [
            'sku'              => 'ITEM-002',
            'barcode'          => '123456789002',
            'name'             => 'Nuts Pack',
            'description'      => 'Pack of 200 industrial nuts',
            'supplier_id'      => 2,
            'warehouse_id'     => 1,
            'unit'             => 'pack',
            'unit_price'       => 80.00,
            'total_stock'      => 300,
            'avg_daily_demand' => 15,
            'lead_time_days'   => 10,
            'safety_stock'     => 50,
            'reorder_point'    => 100
        ]
    ];

    $stmt = $DB->prepare("
        INSERT IGNORE INTO items
        (sku, barcode, name, description, supplier_id, warehouse_id,
         unit, unit_price, total_stock,
         avg_daily_demand, lead_time_days, safety_stock, reorder_point,
         created_at)
        VALUES
        (:sku, :barcode, :name, :description, :supplier_id, :warehouse_id,
         :unit, :unit_price, :total_stock,
         :avg_daily_demand, :lead_time_days, :safety_stock, :reorder_point,
         NOW())
    ");

    foreach ($items as $it) {
        $stmt->execute($it);
    }

    $DB->commit();
};
