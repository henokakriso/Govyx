<?php

declare(strict_types=1);

namespace Govyx\Controllers;

use Govyx\Core\App;
use Govyx\Core\Request;
use Govyx\Core\Response;
use Govyx\Security\Audit;
use Govyx\Security\Scope;

class DepartmentsController extends BaseApiController
{
    public function index(): never
    {
        $this->requirePermission('MANAGE_DEPARTMENTS');
        $deptIds = $this->scopeDeptIds();
        $rows = [];
        if ($deptIds !== []) {
            $placeholders = implode(',', array_fill(0, count($deptIds), '?'));
            $rows = App::db()->all(
                "SELECT d.*, o.name AS organization_name,
                        COALESCE(u.full_name, '-') AS manager_name
                   FROM departments d
                   JOIN organizations o ON o.id = d.organization_id
                   LEFT JOIN users u ON u.id = d.manager_user_id
                  WHERE d.id IN ($placeholders)
                  ORDER BY d.id DESC",
                $deptIds
            );
        }
        Response::ok('Departments retrieved.', ['departments' => $rows]);
    }

    public function show(array $params): never
    {
        $this->requirePermission('MANAGE_DEPARTMENTS');
        $dept = App::db()->one(
            "SELECT d.*, o.name AS organization_name, COALESCE(u.full_name, '-') AS manager_name
               FROM departments d
               JOIN organizations o ON o.id = d.organization_id
               LEFT JOIN users u ON u.id = d.manager_user_id
              WHERE d.id = ?",
            [$params['id']]
        );
        if ($dept === null) {
            Response::notFound('Department not found.');
        }
        Scope::requiresDepartment($this->user, (int) $dept['id']);
        Response::ok('Department retrieved.', ['department' => $dept]);
    }

    public function create(): never
    {
        $this->requirePermission('MANAGE_DEPARTMENTS');
        $this->validate([
            'organization_id' => ['required', 'int:1'],
            'code' => ['required', 'string:32'],
            'name' => ['required', 'string'],
            'manager_user_id' => ['int:1'],
        ]);
        $orgId = (int) Request::input('organization_id');
        Scope::requiresOrg($this->user, $orgId);
        $code = strtoupper(trim((string) Request::input('code')));
        $exists = App::db()->scalar('SELECT id FROM departments WHERE organization_id = ? AND code = ?', [$orgId, $code]);
        if ($exists !== null) {
            Response::error('Department code already exists in this organization.', 409);
        }
        $id = App::db()->insert('departments', [
            'organization_id' => $orgId,
            'code' => $code,
            'name' => trim((string) Request::input('name')),
            'manager_user_id' => Request::input('manager_user_id') ? (int) Request::input('manager_user_id') : null,
            'status' => Request::input('status') ?: 'active',
        ]);
        Audit::log($this->user['id'], 'CREATE_DEPARTMENT', 'department', $id, ['code' => $code]);
        Response::ok('Department created.', ['department_id' => $id]);
    }

    public function update(array $params): never
    {
        $this->requirePermission('MANAGE_DEPARTMENTS');
        $dept = App::db()->one('SELECT * FROM departments WHERE id = ?', [$params['id']]);
        if ($dept === null) {
            Response::notFound('Department not found.');
        }
        Scope::requiresDepartment($this->user, (int) $dept['id']);
        $data = [];
        foreach (['name', 'manager_user_id', 'status'] as $field) {
            if (Request::input($field) !== null) {
                $data[$field] = Request::input($field);
            }
        }
        if ($data === []) {
            Response::error('Nothing to update.', 422);
        }
        App::db()->update('departments', $data, 'id = :id', ['id' => $dept['id']]);
        Audit::log($this->user['id'], 'UPDATE_DEPARTMENT', 'department', $dept['id'], ['fields' => array_keys($data)]);
        Response::ok('Department updated.');
    }

    public function delete(array $params): never
    {
        $this->requirePermission('MANAGE_DEPARTMENTS');
        $dept = App::db()->one('SELECT * FROM departments WHERE id = ?', [$params['id']]);
        if ($dept === null) {
            Response::notFound('Department not found.');
        }
        Scope::requiresDepartment($this->user, (int) $dept['id']);

        $users = (int) App::db()->scalar('SELECT COUNT(*) FROM users WHERE department_id = ?', [$dept['id']]);
        $officials = (int) App::db()->scalar('SELECT COUNT(*) FROM officials WHERE department_id = ?', [$dept['id']]);
        $tasks = (int) App::db()->scalar('SELECT COUNT(*) FROM tasks WHERE department_id = ?', [$dept['id']]);

        if ($users > 0 || $officials > 0 || $tasks > 0) {
            if ($dept['status'] !== 'archived') {
                App::db()->update('departments', ['status' => 'archived'], 'id = :id', ['id' => $dept['id']]);
            }
            Audit::log($this->user['id'], 'ARCHIVE_DEPARTMENT', 'department', $dept['id'], [
                'users' => $users, 'officials' => $officials, 'tasks' => $tasks,
            ]);
            Response::ok('Department archived (has references).');
        }

        App::db()->delete('departments', 'id = :id', ['id' => $dept['id']]);
        Audit::log($this->user['id'], 'DELETE_DEPARTMENT', 'department', $dept['id']);
        Response::ok('Department deleted.');
    }
}