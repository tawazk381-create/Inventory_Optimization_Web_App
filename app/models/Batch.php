<?php // File: app/models/Batch.php
declare(strict_types=1);

class Batch extends Model
{
    protected $table = 'batches';

    public function findByItem(int $itemId)
    {
        return $this->find("SELECT * FROM batches WHERE item_id = :item_id ORDER BY received_at DESC", ['item_id'=>$itemId]);
    }

    public function create(array $data)
    {
        $sql = "INSERT INTO batches (item_id, batch_code, quantity, received_at, expiry_date, created_at) VALUES (:item_id, :batch_code, :quantity, :received_at, :expiry_date, NOW())";
        $this->execute($sql, $data);
        return (int)$this->lastInsertId();
    }

    public function consume(int $id, int $qty)
    {
        // Reduce batch quantity - basic implementation
        $this->execute("UPDATE batches SET quantity = GREATEST(quantity - :q, 0) WHERE id = :id", ['q'=>$qty,'id'=>$id]);
    }
}
