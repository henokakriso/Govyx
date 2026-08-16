<?php

declare(strict_types=1);

namespace Govyx\Controllers;

use Govyx\Core\App;
use Govyx\Core\Request;
use Govyx\Core\Response;
use Govyx\Security\Audit;

class RolesController extends BaseApiController
{
    public function index(): never
    {
        $this->requirePermission('MANAGE_ROLES');
        $rows = App::db()->all(
            "SELECT r.*, COUNT(rp.permission_id) AS permission_count,
                    (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS user_count
               FROM roles r
               LEFT JOIN role_permissions rp ON rp.role_id = r.id
              GROUP BY r.id
              ORDER BY r.id"
        );
        $response = ['roles' => $rows];
        if ((string) Request::query('all_permissions', '') === '1') {
            $response['permissions'] = App::db()->all(
                'SELECT code, description FROM permissions ORDER BY code'
            );
        }
        Response::ok('Roles retrieved.', $response);
    }

    public function show(array $params): never
    {
        $this->requirePermission('MANAGE_ROLES');
        $role = App::db()->one('SELECT * FROM roles WHERE id = ?', [(int) $params['id']]);
        if ($role === null) {
            Response::notFound('Role not found.');
        }
        $role['permissions'] = App::db()->all(
            "SELECT p.code FROM permissions p
               JOIN role_permissions rp ON rp.permission_id = p.id
              WHERE rp.role_id = ? ORDER BY p.code",
            [$role['id']]
        );
        Response::ok('Role retrieved.', ['role' => $role]);
    }

    public function create(): never
    {
        $this->requirePermission('MANAGE_ROLES');
        $this->validate([
            'code' => ['required', 'string:32'],
            'name' => ['required', 'string:64'],
            'description' => ['string:255'],
        ]);
        $code = strtoupper(trim((string) Request::input('code')));
        if (!preg_match('/^[A-Z0-9_]+$/', $code)) {
            Response::error('Role code allows uppercase letters, digits and underscore only.', 422);
        }
        if (App::db()->scalar('SELECT id FROM roles WHERE code = ?', [$code]) !== null) {
            Response::error('Role code already exists.', 409);
        }
        $id = App::db()->insert('roles', [
            'code' => $code,
            'name' => trim((string) Request::input('name')),
            'description' => Request::input('description') ?: null,
        ]);
        $this->syncPermissions((int) $id);
        Audit::log($this->user['id'], 'CREATE_ROLE', 'role', $id, ['code' => $code]);
        Response::ok('Role created.', ['role_id' => $id]);
    }

    public function update(array $params): never
    {
        $this->requirePermission('MANAGE_ROLES');
        $role = App::db()->one('SELECT * FROM roles WHERE id = ?', [(int) $params['id']]);
        if ($role === null) {
            Response::notFound('Role not found.');
        }
        if ($role['code'] === 'SUPER_ADMIN') {
            Response::error('The SUPER_ADMIN role cannot be modified.', 403);
        }
        $data = [];
        foreach (['name', 'description'] as $field) {
            if (Request::input($field) !== null) {
                $data[$field] = Request::input($field);
            }
        }
        if ($data !== []) {
            App::db()->update('roles', $data, 'id = :id', ['id' => $role['id']]);
        }
        if (Request::input('permissions') !== null) {
            $this->syncPermissions((int) $role['id']);
        }
        Audit::log($this->user['id'], 'UPDATE_ROLE', 'role', $role['id'], ['fields' => array_keys($data)]);
        Response::ok('Role updated.');
    }

    public function delete(array $params): never
    {
        $this->requirePermission('MANAGE_ROLES');
        $role = App::db()->one('SELECT * FROM roles WHERE id = ?', [(int) $params['id']]);
        if ($role === null) {
            Response::notFound('Role not found.');
        }
        if ($role['code'] === 'SUPER_ADMIN' || (int) (App::db()->scalar('SELECT COUNT(*) FROM users WHERE role_id = ?', [$role['id']]) ?? 0) > 0) {
            Response::error('Role is in use and cannot be deleted.', 409);
        }
        App::db()->query('DELETE FROM role_permissions WHERE role_id = ?', [$role['id']]);
        App::db()->delete('roles', 'id = :id', ['id' => $role['id']]);
        Audit::log($this->user['id'], 'DELETE_ROLE', 'role', $role['id']);
        Response::ok('Role deleted.');
    }

    /** Replaces the role's permission set with the submitted list of permission codes. */
    private function syncPermissions(int $roleId): void
    {
        $perms = Request::input('permissions');
        if (!is_array($perms)) {
            Response::error('permissions must be an array of permission codes.', 422);
        }
        $valid = array_column(App::db()->all('SELECT code FROM permissions'), 'code');
        $perms = array_values(array_unique(array_intersect($perms, $valid)));
        App::db()->query('DELETE FROM role_permissions WHERE role_id = ?', [$roleId]);
        foreach ($perms as $code) {
            $permId = (int) App::db()->scalar('SELECT id FROM permissions WHERE code = ?', [$code]);
            App::db()->insert('role_permissions', ['role_id' => $roleId, 'permission_id' => $permId]);
        }
        Audit::log($this->user['id'], 'SYNC_ROLE_PERMISSIONS', 'role', $roleId, ['permissions' => $perms]);
    }
}