<?php

declare(strict_types=1);

namespace Govyx\Controllers;

use Govyx\Core\App;
use Govyx\Core\Request;
use Govyx\Core\Response;
use Govyx\Models\UserModel;
use Govyx\Security\Audit;

class UsersController extends BaseApiController
{
    public function index(): never
    {
        $this->requirePermission('MANAGE_USERS');
        $users = UserModel::list($this->scopeOrgIds(), max(1, Request::queryInt('limit', 100)), max(0, Request::queryInt('offset', 0)));
        foreach ($users as &$u) {
            unset($u['password_hash']);
        }
        Response::ok('Users retrieved.', ['users' => $users]);
    }

    public function show(array $params): never
    {
        $this->requirePermission('MANAGE_USERS');
        $user = UserModel::find((int) $params['id']);
        if ($user === null) {
            Response::notFound('User not found.');
        }
        unset($user['password_hash']);
        Response::ok('User retrieved.', ['user' => $user]);
    }

    public function create(): never
    {
        $this->requirePermission('MANAGE_USERS');
        $this->validate([
            'username' => ['required', 'string:64'],
            'password' => ['required', 'string:255'],
            'full_name' => ['required', 'string'],
            'email' => ['string', 'email'],
            'phone' => ['string:32'],
            'role_id' => ['required', 'int:1'],
            'organization_id' => ['required', 'int:1'],
            'department_id' => ['int:1'],
        ]);

        $username = trim((string) Request::input('username'));
        $password = (string) Request::input('password');
        if (strlen($password) < 8) {
            Response::error('Password must be at least 8 characters.', 422);
        }

        $exists = App::db()->scalar('SELECT id FROM users WHERE username = ?', [$username]);
        if ($exists !== null) {
            Response::error('Username already taken.', 409);
        }

        $orgId = (int) Request::input('organization_id');
        \Govyx\Security\Scope::requiresOrg($this->user, $orgId);

        $roleId = (int) Request::input('role_id');
        $roleCheck = App::db()->one('SELECT id FROM roles WHERE id = ?', [$roleId]);
        if ($roleCheck === null) {
            Response::error('Invalid role.', 422);
        }

        $deptId = Request::input('department_id');
        if ($deptId !== null && $deptId !== '') {
            \Govyx\Security\Scope::requiresDepartment($this->user, (int) $deptId);
        }

        $id = App::db()->insert('users', [
            'username'        => $username,
            'password_hash'   => password_hash($password, PASSWORD_DEFAULT),
            'full_name'       => trim((string) Request::input('full_name')),
            'email'           => Request::input('email') ?: null,
            'phone'           => Request::input('phone') ?: null,
            'role_id'         => $roleId,
            'organization_id' => $orgId,
            'department_id'   => $deptId ? (int) $deptId : null,
            'status'          => Request::input('status') ?: 'active',
        ]);
        Audit::log($this->user['id'], 'CREATE_USER', 'user', $id, ['username' => $username]);
        Response::ok('User created.', ['user_id' => $id]);
    }

    public function update(array $params): never
    {
        $this->requirePermission('MANAGE_USERS');
        $target = UserModel::find((int) $params['id']);
        if ($target === null) {
            Response::notFound('User not found.');
        }
        \Govyx\Security\Scope::requiresOrg($this->user, (int) $target['organization_id']);

        $data = [];
        foreach (['full_name', 'email', 'phone', 'status', 'department_id'] as $field) {
            if (Request::input($field) !== null) {
                $data[$field] = Request::input($field);
            }
        }
        if (Request::input('role_id') !== null) {
            if (!\Govyx\Security\Permission::has($this->user, 'MANAGE_ROLES')) {
                Response::forbidden('You may not change roles.');
            }
            $data['role_id'] = (int) Request::input('role_id');
        }
        if (Request::input('password')) {
            $pw = (string) Request::input('password');
            if (strlen($pw) < 8) {
                Response::error('Password must be at least 8 characters.', 422);
            }
            $data['password_hash'] = password_hash($pw, PASSWORD_DEFAULT);
        }
        if ($data === []) {
            Response::error('Nothing to update.', 422);
        }
        App::db()->update('users', $data, 'id = :id', ['id' => $target['id']]);
        Audit::log($this->user['id'], 'UPDATE_USER', 'user', $target['id'], ['fields' => array_keys($data)]);
        Response::ok('User updated.');
    }

    public function destroy(array $params): never
    {
        $this->requirePermission('MANAGE_USERS');
        $target = UserModel::find((int) $params['id']);
        if ($target === null) {
            Response::notFound('User not found.');
        }
        $this->scopeOrgIds();
        \Govyx\Security\Scope::requiresOrg($this->user, (int) $target['organization_id']);
        if ((int) $target['id'] === (int) $this->user['id']) {
            Response::error('You cannot delete your own account.', 422);
        }
        App::db()->update('users', ['status' => 'disabled'], 'id = :id', ['id' => $target['id']]);
        Audit::log($this->user['id'], 'DISABLE_USER', 'user', $target['id']);
        Response::ok('User disabled.');
    }
}