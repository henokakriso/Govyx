<?php

declare(strict_types=1);

namespace Govyx\Models;

use Govyx\Core\App;

class OrganizationModel
{
    public static function find(int $id): ?array
    {
        return App::db()->one('SELECT * FROM organizations WHERE id = ?', [$id]);
    }

    public static function tree(): array
    {
        $rows = App::db()->all('SELECT id, code, name, type, parent_id, region, zone, woreda, kebele, status FROM organizations ORDER BY id');
        $tree = [];
        $byId = [];
        foreach ($rows as $row) {
            $row['children'] = [];
            $byId[$row['id']] = $row;
        }
        foreach ($byId as &$row) {
            if ($row['parent_id'] !== null && isset($byId[$row['parent_id']])) {
                $byId[$row['parent_id']]['children'][] = &$row;
            } else {
                $tree[] = &$row;
            }
        }
        return $tree;
    }
}