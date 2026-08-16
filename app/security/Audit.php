<?php

declare(strict_types=1);

namespace Govyx\Security;

use Govyx\Core\App;
use Govyx\Core\Request;

final class Audit
{
    public static function log(
        int $actionUserId, string $action, string $entityType = null, int|string $entityId = null, array $details = []
    ): void {
        try {
            App::db()->insert('audit_logs', [
                'user_id'      => $actionUserId,
                'action'       => $action,
                'entity_type'  => $entityType,
                'entity_id'    => is_numeric($entityId) ? (int) $entityId : null,
                'details_json' => json_encode($details, JSON_UNESCAPED_UNICODE),
                'ip_address'   => Request::ip(),
                'user_agent'   => Request::userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Audit must never break the main request; log to stderr instead.
            error_log('AUDIT FAILURE: ' . $e->getMessage());
        }
    }

    public static function page(): array
    {
        return App::db()->all(
            "SELECT al.*, COALESCE(u.username, 'system') AS username
               FROM audit_logs al
               LEFT JOIN users u ON u.id = al.user_id
              ORDER BY al.id DESC
              LIMIT 200"
        );
    }

    public static function byEntity(string $entityType, int $entityId): array
    {
        return App::db()->all(
            "SELECT al.*, COALESCE(u.username, 'system') AS username
               FROM audit_logs al
               LEFT JOIN users u ON u.id = al.user_id
              WHERE al.entity_type = ? AND al.entity_id = ?
              ORDER BY al.id ASC",
            [$entityType, $entityId]
        );
    }
}