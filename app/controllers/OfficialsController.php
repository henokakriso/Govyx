<?php

declare(strict_types=1);

namespace Govyx\Controllers;

use Govyx\Core\App;
use Govyx\Core\Request;
use Govyx\Core\Response;
use Govyx\Security\Audit;
use Govyx\Security\Scope;

class OfficialsController extends BaseApiController
{
    public function index(): never
    {
        $orgIds = $this->scopeOrgIds();
        if ($orgIds === []) {
            Response::ok('Officials retrieved.', ['officials' => []]);
        }
        $placeholders = implode(',', array_fill(0, count($orgIds), '?'));
        $rows = App::db()->all(
            "SELECT o.*, u.username, u.full_name, u.email, u.phone,
                    d.name AS department_name, org.name AS organization_name
               FROM officials o
               JOIN users u ON u.id = o.user_id
               JOIN departments d ON d.id = o.department_id
               JOIN organizations org ON org.id = o.organization_id
              WHERE o.organization_id IN ($placeholders)
              ORDER BY o.id DESC",
            $orgIds
        );
        Response::ok('Officials retrieved.', ['officials' => $rows]);
    }

    public function show(array $params): never
    {
        $orgIds = $this->scopeOrgIds();
        if ($orgIds === []) {
            Response::notFound('Official not found.');
        }
        $placeholders = implode(',', array_fill(0, count($orgIds), '?'));
        $official = App::db()->one(
            "SELECT o.*, u.username, u.full_name, u.email, u.phone,
                    d.name AS department_name, org.name AS organization_name
               FROM officials o
               JOIN users u ON u.id = o.user_id
               JOIN departments d ON d.id = o.department_id
               JOIN organizations org ON org.id = o.organization_id
              WHERE o.id = ? AND o.organization_id IN ($placeholders)",
            array_merge([(int) $params['id']], $orgIds)
        );
        if ($official === null) {
            Response::notFound('Official not found.');
        }
        Response::ok('Official retrieved.', ['official' => $official]);
    }

    public function update(array $params): never
    {
        $this->requirePermission('MANAGE_USERS');
        $official = App::db()->one('SELECT * FROM officials WHERE id = ?', [$params['id']]);
        if ($official === null) {
            Response::notFound('Official not found.');
        }
        Scope::requiresOrg($this->user, (int) $official['organization_id']);

        $data = [];
        foreach (['department_id', 'position', 'grade', 'status'] as $field) {
            if (Request::input($field) !== null) {
                $data[$field] = Request::input($field);
            }
        }
        if (Request::input('department_id') !== null) {
            Scope::requiresDepartment($this->user, (int) Request::input('department_id'));
        }
        if ($data === []) {
            Response::error('Nothing to update.', 422);
        }
        App::db()->update('officials', $data, 'id = :id', ['id' => $official['id']]);
        Audit::log($this->user['id'], 'UPDATE_OFFICIAL', 'official', $official['id'], ['fields' => array_keys($data)]);
        Response::ok('Official updated.');
    }

    public function delete(array $params): never
    {
        $this->requirePermission('MANAGE_USERS');
        $official = App::db()->one('SELECT * FROM officials WHERE id = ?', [$params['id']]);
        if ($official === null) {
            Response::notFound('Official not found.');
        }
        Scope::requiresOrg($this->user, (int) $official['organization_id']);

        App::db()->update('officials', ['status' => 'inactive'], 'id = :id', ['id' => $official['id']]);
        Audit::log($this->user['id'], 'ARCHIVE_OFFICIAL', 'official', $official['id']);
        Response::ok('Official deactivated.');
    }

    public function create(): never
    {
        $this->requirePermission('MANAGE_USERS');
        $this->validate([
            'user_id' => ['required', 'int:1'],
            'organization_id' => ['required', 'int:1'],
            'department_id' => ['required', 'int:1'],
            'position' => ['string:255'],
            'grade' => ['string:64'],
        ]);
        $orgId = (int) Request::input('organization_id');
        $deptId = (int) Request::input('department_id');
        Scope::requiresOrg($this->user, $orgId);
        Scope::requiresDepartment($this->user, $deptId);

        $exists = App::db()->scalar('SELECT id FROM officials WHERE user_id = ?', [Request::input('user_id')]);
        if ($exists !== null) {
            Response::error('This user is already registered as an official.', 409);
        }
        $id = App::db()->insert('officials', [
            'user_id'         => (int) Request::input('user_id'),
            'organization_id' => $orgId,
            'department_id'   => $deptId,
            'position'        => Request::input('position') ?: null,
            'grade'           => Request::input('grade') ?: null,
            'status'          => 'active',
        ]);
        Audit::log($this->user['id'], 'CREATE_OFFICIAL', 'official', $id);
        Response::ok('Official registered.', ['official_id' => $id]);
    }
}