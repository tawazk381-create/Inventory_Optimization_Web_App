<?php // File: app/services/InventoryService.php
class InventoryService
{
    protected $db;
    protected $itemModel;
    protected $stockModel;

    public function __construct()
    {
        global $DB;
        $this->db = $DB;
        $this->itemModel = new Item();
        $this->stockModel = new StockMovement();
    }

    public function recordMovement(int $userId, int $itemId, int $quantity, string $type = 'adjustment', string $source = '')
    {
        try {
            $this->db->beginTransaction();

            // Validate item exists
            $item = $this->itemModel->findById($itemId);
            if (!$item) {
                throw new Exception("Item not found");
            }

            // Insert stock movement
            $this->stockModel->create([
                'item_id' => $itemId,
                'user_id' => $userId,
                'quantity' => $quantity,
                'type' => $type,
                'source' => $source
            ]);

            // Optionally update cached stock, audit logs etc.

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
