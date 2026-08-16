<?php

declare(strict_types=1);

namespace Govyx\Models;

use Govyx\Core\App;

class TaskModel
{
    public static function find(int $id): ?array
    {
        return App::db()->one(
            "SELECT t.*,
                    o.name AS organization_name,
                    d.name AS department_name,
                    COALESCE(a.full_name, '-') AS assignee_name,
                    COALESCE(c.full_name, '-') AS creator_name,
                    COALESCE(ap.full_name, '-') AS approver_name
               FROM tasks t
               JOIN organizations o ON o.id = t.organization_id
               LEFT JOIN departments d ON d.id = t.department_id
               LEFT JOIN users a ON a.id = t.assigned_to
               JOIN users c ON c.id = t.created_by
               LEFT JOIN users ap ON ap.id = t.approval_by
              WHERE t.id = ?",
            [$id]
        );
    }

    public static function findWithScope(int $id, array $orgIds): ?array
    {
        $task = self::find($id);
        if ($task === null || !in_array((int) $task['organization_id'], $orgIds, true)) {
            return null;
        }
        return $task;
    }

    public static function list(array $orgIds, array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $where = [];
        $params = [];
        if ($orgIds !== []) {
            $where[] = 't.organization_id IN (' . implode(',', array_fill(0, count($orgIds), '?')) . ')';
            $params = array_merge($params, $orgIds);
        }
        if (!empty($filters['status'])) {
            $where[] = 't.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $where[] = 't.priority = ?';
            $params[] = $filters['priority'];
        }
        if (!empty($filters['assigned_to'])) {
            $where[] = 't.assigned_to = ?';
            $params[] = $filters['assigned_to'];
        }
        if (!empty($filters['department_id'])) {
            $where[] = 't.department_id = ?';
            $params[] = $filters['department_id'];
        }
        if (!empty($filters['overdue'])) {
            $where[] = "t.deadline IS NOT NULL AND t.deadline < CURDATE() AND t.status NOT IN ('completed','reviewed')";
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        return App::db()->all(
            "SELECT t.*, o.name AS organization_name, d.name AS department_name,
                    COALESCE(a.full_name, '-') AS assignee_name
               FROM tasks t
               JOIN organizations o ON o.id = t.organization_id
               LEFT JOIN departments d ON d.id = t.department_id
               LEFT JOIN users a ON a.id = t.assigned_to
              $whereSql
              ORDER BY FIELD(t.status,'created','assigned','in_progress','submitted','reviewed','completed','rejected','returned'),
                       t.deadline IS NULL, t.deadline ASC
              LIMIT " . max(1, (int) $limit) . " OFFSET " . max(0, (int) $offset),
            $params
        );
    }

    public static function countByStatus(array $orgIds): array
    {
        if ($orgIds === []) {
            return App::db()->all('SELECT status, COUNT(*) AS total FROM tasks GROUP BY status');
        }
        $placeholders = implode(',', array_fill(0, count($orgIds), '?'));
        return App::db()->all(
            "SELECT status, COUNT(*) AS total FROM tasks WHERE organization_id IN ($placeholders) GROUP BY status",
            $orgIds
        );
    }

    public static function transition(int $taskId, ?string $from, string $to, int $userId, ?string $note = null): void
    {
        App::db()->insert('task_transitions', [
            'task_id'     => $taskId,
            'from_status' => $from,
            'to_status'   => $to,
            'action_by'   => $userId,
            'note'        => $note,
        ]);
    }

    public static function overdue(array $orgIds): int
    {
        if ($orgIds === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($orgIds), '?'));
        return (int) App::db()->scalar(
            "SELECT COUNT(*) FROM tasks
              WHERE organization_id IN ($placeholders)
                AND deadline IS NOT NULL AND deadline < CURDATE()
                AND status NOT IN ('completed','reviewed')",
            $orgIds
        );
    }

    public static function completionRate(array $orgIds): float
    {
        if ($orgIds === []) {
            return 0.0;
        }
        $placeholders = implode(',', array_fill(0, count($orgIds), '?'));
        $total = (int) App::db()->scalar("SELECT COUNT(*) FROM tasks WHERE organization_id IN ($placeholders)", $orgIds);
        if ($total === 0) {
            return 0.0;
        }
        $done = (int) App::db()->scalar("SELECT COUNT(*) FROM tasks WHERE organization_id IN ($placeholders) AND status IN ('completed','reviewed')", $orgIds);
        return round($done / $total * 100, 2);
    }
}