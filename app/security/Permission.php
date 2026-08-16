<?php

declare(strict_types=1);

namespace Govyx\Security;

use Govyx\Core\App;
use Govyx\Core\Response;

/**
 * Role-based access control (Section 25). Permissions are explicit.
 */
final class Permission
{
    private static ?array $cache = null;

    public static function permissionsForRole(int $roleId): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            $rows = App::db()->all(
                "SELECT rp.role_id, p.code FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id"
            );
            foreach ($rows as $row) {
                self::$cache[(int) $row['role_id']][] = $row['code'];
            }
        }
        return self::$cache[$roleId] ?? [];
    }

    public static function has(array $user, string $permission): bool
    {
        $roleId = (int) $user['role_id'];
        $roleCode = (string) $user['role_code'];
        if ($roleCode === 'super_admin') {
            return true;
        }
        return in_array($permission, self::permissionsForRole($roleId), true);
    }

    public static function require(array $user, string $permission, string $message = null): void
    {
        if (!self::has($user, $permission)) {
            Response::forbidden($message ?? 'You do not have permission: ' . $permission);
        }
    }
}