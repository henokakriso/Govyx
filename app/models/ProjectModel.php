<?php

declare(strict_types=1);

namespace Govyx\Models;

use Govyx\Core\App;

class ProjectModel
{
    public static function find(int $id): ?array
    {
        return App::db()->one(
            "SELECT p.*, o.name AS organization_name, d.name AS department_name
               FROM projects p
               JOIN organizations o ON o.id = p.organization_id
               LEFT JOIN departments d ON d.id = p.department_id
              WHERE p.id = ?",
            [$id]
        );
    }

    public static function findWithScope(int $id, array $orgIds): ?array
    {
        $p = self::find($id);
        if ($p === null || !in_array((int) $p['organization_id'], $orgIds, true)) {
            return null;
        }
        return $p;
    }

    public static function list(array $orgIds): array
    {
        if ($orgIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($orgIds), '?'));
        return App::db()->all(
            "SELECT p.*, o.name AS organization_name, d.name AS department_name
               FROM projects p
               JOIN organizations o ON o.id = p.organization_id
               LEFT JOIN departments d ON d.id = p.department_id
              WHERE p.organization_id IN ($placeholders)
              ORDER BY p.id DESC",
            $orgIds
        );
    }
}