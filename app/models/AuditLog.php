<?php // File: app/models/AuditLog.php
declare(strict_types=1);

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    public function record(?int $userId, string $action, ?string $objectType = null, $objectId = null, array $meta = [], ?string $ip = null)
    {
        $sql = "INSERT INTO audit_logs (user_id, action, object_type, object_id, meta, ip_address, created_at) VALUES (:user_id, :action, :object_type, :object_id, :meta, :ip, NOW())";
        $this->execute($sql, [
            'user_id' => $userId,
            'action' => substr($action,0,100),
            'object_type' => $objectType,
            'object_id' => $objectId,
            'meta' => json_encode($meta),
            'ip' => $ip ?? $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
}
