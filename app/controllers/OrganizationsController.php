<?php

declare(strict_types=1);

namespace Govyx\Controllers;

use Govyx\Core\App;
use Govyx\Core\Request;
use Govyx\Core\Response;
use Govyx\Models\OrganizationModel;
use Govyx\Security\Audit;
use Govyx\Security\Scope;

class OrganizationsController extends BaseApiController
{
    public function index(): never
    {
        $this->requirePermission('MANAGE_ORGANIZATIONS');
        Response::ok('Organizations retrieved.', [
            'organizations' => OrganizationModel::tree(),
            'scope_org_ids' => $this->scopeOrgIds(),
        ]);
    }

    public function show(array $params): never
    {
        $this->requirePermission('MANAGE_ORGANIZATIONS');
        $org = OrganizationModel::find((int) $params['id']);
        if ($org === null) {
            Response::notFound('Organization not found.');
        }
        Scope::requiresOrg($this->user, (int) $org['id']);
        Response::ok('Organization retrieved.', ['organization' => $org]);
    }

    public function create(): never
    {
        $this->requirePermission('MANAGE_ORGANIZATIONS');
        $this->validate([
            'code' => ['required', 'string:32'],
            'name' => ['required', 'string'],
            'type' => ['required', 'in:federal,region,zone,woreda,kebele,organization'],
            'parent_id' => ['int:1'],
            'region' => ['string:128'],
            'zone' => ['string:128'],
            'woreda' => ['string:128'],
            'kebele' => ['string:128'],
        ]);

        $parentId = Request::input('parent_id');
        if ($parentId !== null && $parentId !== '') {
            Scope::requiresOrg($this->user, (int) $parentId);
        }
        $code = strtoupper(trim((string) Request::input('code')));
        $exists = App::db()->scalar('SELECT id FROM organizations WHERE code = ?', [$code]);
        if ($exists !== null) {
            Response::error('Organization code already exists.', 409);
        }

        $id = App::db()->insert('organizations', [
            'code'      => $code,
            'name'      => trim((string) Request::input('name')),
            'type'      => Request::input('type'),
            'parent_id' => $parentId !== null && $parentId !== '' ? (int) $parentId : null,
            'region'    => Request::input('region') ?: null,
            'zone'      => Request::input('zone') ?: null,
            'woreda'    => Request::input('woreda') ?: null,
            'kebele'    => Request::input('kebele') ?: null,
            'status'    => Request::input('status') ?: 'active',
        ]);
        Audit::log($this->user['id'], 'CREATE_ORGANIZATION', 'organization', $id, ['code' => $code]);
        Response::ok('Organization created.', ['organization_id' => $id]);
    }

    public function update(array $params): never
    {
        $this->requirePermission('MANAGE_ORGANIZATIONS');
        $org = OrganizationModel::find((int) $params['id']);
        if ($org === null) {
            Response::notFound('Organization not found.');
        }
        Scope::requiresOrg($this->user, (int) $org['id']);
        $data = [];
        foreach (['name', 'type', 'region', 'zone', 'woreda', 'kebele', 'status'] as $field) {
            if (Request::input($field) !== null) {
                $data[$field] = Request::input($field);
            }
        }
        if ($data === []) {
            Response::error('Nothing to update.', 422);
        }
        App::db()->update('organizations', $data, 'id = :id', ['id' => $org['id']]);
        Audit::log($this->user['id'], 'UPDATE_ORGANIZATION', 'organization', $org['id'], ['fields' => array_keys($data)]);
        Response::ok('Organization updated.');
    }

    public function delete(array $params): never
    {
        $this->requirePermission('MANAGE_ORGANIZATIONS');
        $org = OrganizationModel::find((int) $params['id']);
        if ($org === null) {
            Response::notFound('Organization not found.');
        }
        Scope::requiresOrg($this->user, (int) $org['id']);

        $children = (int) App::db()->scalar('SELECT COUNT(*) FROM organizations WHERE parent_id = ?', [$org['id']]);
        $users = (int) App::db()->scalar('SELECT COUNT(*) FROM users WHERE organization_id = ?', [$org['id']]);
        $tasks = (int) App::db()->scalar('SELECT COUNT(*) FROM tasks WHERE organization_id = ?', [$org['id']]);

        if ($org['status'] !== 'archived') {
            // Soft-archive: keep the record, disable it and its subtree.
            App::db()->query(
                "UPDATE organizations SET status = 'archived' WHERE id = ? OR parent_id = ?",
                [$org['id'], $org['id']]
            );
        }
        if ($org['status'] === 'archived' && $children === 0 && $users === 0 && $tasks === 0) {
            App::db()->delete('organizations', 'id = :id', ['id' => $org['id']]);
        }

        Audit::log($this->user['id'], 'ARCHIVE_ORGANIZATION', 'organization', $org['id'], [
            'children' => $children, 'users' => $users, 'tasks' => $tasks,
        ]);
        Response::ok('Organization archived.');
    }
}