<?php

declare(strict_types=1);

namespace Govyx\Security;

use Govyx\Core\App;
use Govyx\Core\Response;

/**
 * Administrative scope (Section 26): a user only accesses data within
 * their authorized hierarchy branch. Higher-level access must be explicit.
 */
final class Scope
{
    /** Organization ids visible to the given user (own subtree for branch roles, all for federal/super). */
    public static function organizationIds(int $userOrgId, string $roleCode): array
    {
        if (in_array($roleCode, ['super_admin', 'gov_admin', 'analyst', 'auditor', 'viewer'], true)) {
            return self::allOrganizationIds();
        }
        return self::subtreeIds($userOrgId);
    }

    /** All organization ids including the root. */
    public static function allOrganizationIds(): array
    {
        return array_map('intval', array_column(App::db()->all('SELECT id FROM organizations'), 'id'));
    }

    /** This org plus every descendant (any depth, since orgs are a tree). */
    public static function subtreeIds(int $orgId): array
    {
        $all = App::db()->all('SELECT id, parent_id FROM organizations');
        $children = [];
        foreach ($all as $row) {
            $p = $row['parent_id'] === null ? null : (int) $row['parent_id'];
            $children[$p][] = (int) $row['id'];
        }
        $result = [];
        $stack = [$orgId];
        while ($stack !== []) {
            $current = array_pop($stack);
            $result[] = $current;
            foreach ($children[$current] ?? [] as $child) {
                $stack[] = $child;
            }
        }
        return $result;
    }

    /** Department ids within the user's organization subtree. */
    public static function departmentIds(int $userOrgId, string $roleCode, ?int $userDeptId = null): array
    {
        if (in_array($roleCode, ['super_admin', 'gov_admin', 'analyst', 'auditor', 'viewer'], true)) {
            return array_map('intval', array_column(App::db()->all('SELECT id FROM departments'), 'id'));
        }
        $orgs = self::organizationIds($userOrgId, $roleCode);
        if ($orgs === []) {
            return $userDeptId !== null ? [$userDeptId] : [];
        }
        $placeholders = implode(',', array_fill(0, count($orgs), '?'));
        return array_map('intval', array_column(App::db()->all("SELECT id FROM departments WHERE organization_id IN ($placeholders)", $orgs), 'id'));
    }

    /** SQL expression for "organization_id IN (visible)" + params. */
    public static function orgSql(string $userOrgId, string $roleCode): array
    {
        return self::inSql('organization_id', self::organizationIds($userOrgId, $roleCode));
    }

    public static function inSql(string $column, array $ids): array
    {
        if ($ids === []) {
            return [$column . ' IN (NULL)', []];
        }
        return [$column . ' IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', array_values($ids)];
    }

    /** Validate that an org id is within the user's scope. */
    public static function requiresOrg(array $user, int $orgId): void
    {
        $visible = self::organizationIds((int) $user['organization_id'], (string) $user['role_code']);
        if (!in_array($orgId, $visible, true)) {
            Response::forbidden('Organization is outside your administrative scope.');
        }
    }

    public static function requiresDepartment(array $user, int $deptId): void
    {
        $visible = self::departmentIds((int) $user['organization_id'], (string) $user['role_code'], isset($user['department_id']) ? (int) $user['department_id'] : null);
        if (!in_array($deptId, $visible, true)) {
            Response::forbidden('Department is outside your administrative scope.');
        }
    }
}