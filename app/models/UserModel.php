<?php

declare(strict_types=1);

namespace Govyx\Models;

use Govyx\Core\App;

class UserModel
{
    public static function find(int $id): ?array
    {
        return App::db()->one(
            "SELECT u.*, r.code AS role_code, r.name AS role_name,
                    o.name AS organization_name,
                    d.name AS department_name
               FROM users u
               JOIN roles r ON r.id = u.role_id
               JOIN organizations o ON o.id = u.organization_id
               LEFT JOIN departments d ON d.id = u.department_id
              WHERE u.id = ?",
            [$id]
        );
    }

    public static function list(array $orgIds = [], int $limit = 200, int $offset = 0): array
    {
        if ($orgIds === []) {
            return App::db()->all(
                "SELECT u.id, u.username, u.full_name, u.email, u.phone, u.status,
                        r.name AS role_name, o.name AS organization_name, d.name AS department_name
                   FROM users u
                   JOIN roles r ON r.id = u.role_id
                   JOIN organizations o ON o.id = u.organization_id
                   LEFT JOIN departments d ON d.id = u.department_id
                  ORDER BY u.id DESC
                  LIMIT " . max(1, (int) $limit) . " OFFSET " . max(0, (int) $offset)
            );
        }
        $placeholders = implode(',', array_fill(0, count($orgIds), '?'));
        return App::db()->all(
            "SELECT u.id, u.username, u.full_name, u.email, u.phone, u.status,
                    r.name AS role_name, o.name AS organization_name, d.name AS department_name
               FROM users u
               JOIN roles r ON r.id = u.role_id
               JOIN organizations o ON o.id = u.organization_id
               LEFT JOIN departments d ON d.id = u.department_id
              WHERE u.organization_id IN ($placeholders)
              ORDER BY u.id DESC
              LIMIT " . max(1, (int) $limit) . " OFFSET " . max(0, (int) $offset),
            $orgIds
        );
    }
}