<?php
// File: app/services/SupplierService.php
declare(strict_types=1);

class SupplierService
{
    protected $db;
    protected $supplierModel;

    public function __construct()
    {
        global $DB;
        $this->db = $DB;
        $this->supplierModel = new Supplier();
    }

    /**
     * Returns simple KPI metrics for supplier id:
     * - total_orders (based on stock_movements of type 'entry')
     * - avg_order_age_days (days since movement)
     */
    public function getKPI(int $supplierId): array
    {
        $sql = "SELECT COUNT(sm.id) as total_orders, 
                       AVG(TIMESTAMPDIFF(DAY, sm.created_at, NOW())) as avg_age
                FROM stock_movements sm
                JOIN items i ON i.id = sm.item_id
                WHERE sm.movement_type = 'entry' 
                  AND i.supplier_id = :sid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['sid' => $supplierId]);
        $row = $stmt->fetch();

        return [
            'total_orders'       => (int)($row['total_orders'] ?? 0),
            'avg_order_age_days' => $row['avg_age'] !== null ? round((float)$row['avg_age'], 2) : null
        ];
    }

    /**
     * ✅ Alias for compatibility with SupplierController
     */
    public function getSupplierKPIs(int $supplierId): array
    {
        return $this->getKPI($supplierId);
    }
}
