<?php

declare(strict_types=1);

namespace Govyx\Models;

use Govyx\Core\App;

class KpiModel
{
    public static function find(int $id): ?array
    {
        return App::db()->one(
            "SELECT k.*, o.name AS organization_name, d.name AS department_name
               FROM kpis k
               JOIN organizations o ON o.id = k.organization_id
               LEFT JOIN departments d ON d.id = k.department_id
              WHERE k.id = ?",
            [$id]
        );
    }

    public static function findWithScope(int $id, array $orgIds): ?array
    {
        $kpi = self::find($id);
        if ($kpi === null || !in_array((int) $kpi['organization_id'], $orgIds, true)) {
            return null;
        }
        return $kpi;
    }

    public static function list(array $orgIds): array
    {
        if ($orgIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($orgIds), '?'));
        return App::db()->all(
            "SELECT k.*, o.name AS organization_name, d.name AS department_name,
                    CASE WHEN k.target = 0 THEN NULL ELSE ROUND(k.actual / k.target * 100, 2) END AS achievement
               FROM kpis k
               JOIN organizations o ON o.id = k.organization_id
               LEFT JOIN departments d ON d.id = k.department_id
              WHERE k.organization_id IN ($placeholders)
              ORDER BY k.id DESC",
            $orgIds
        );
    }

    public static function measurements(int $kpiId): array
    {
        return App::db()->all(
            "SELECT km.*, u.full_name AS measured_by_name
               FROM kpi_measurements km
               JOIN users u ON u.id = km.measured_by
              WHERE km.kpi_id = ?
              ORDER BY km.period DESC",
            [$kpiId]
        );
    }

    public static function averageAchievement(array $orgIds, ?string $period = null): ?float
    {
        if ($orgIds === []) {
            return null;
        }
        $placeholders = implode(',', array_fill(0, count($orgIds), '?'));
        $params = $orgIds;
        $sql = "SELECT AVG(CASE WHEN k.target = 0 THEN NULL ELSE k.actual / k.target * 100 END)
                  FROM kpis k WHERE k.organization_id IN ($placeholders)";
        if ($period !== null) {
            $sql .= ' AND k.period = ?';
            $params[] = $period;
        }
        $v = App::db()->scalar($sql, $params);
        return $v === null ? null : round((float) $v, 2);
    }
}