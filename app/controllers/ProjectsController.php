<?php

declare(strict_types=1);

namespace Govyx\Controllers;

use Govyx\Core\App;
use Govyx\Core\Request;
use Govyx\Core\Response;
use Govyx\Models\ProjectModel;
use Govyx\Security\Audit;
use Govyx\Security\Scope;

class ProjectsController extends BaseApiController
{
    public function index(): never
    {
        $this->requirePermission('VIEW_PROJECT');
        Response::ok('Projects retrieved.', ['projects' => ProjectModel::list($this->scopeOrgIds())]);
    }

    public function show(array $params): never
    {
        $this->requirePermission('VIEW_PROJECT');
        $project = ProjectModel::findWithScope((int) $params['id'], $this->scopeOrgIds());
        if ($project === null) {
            Response::notFound('Project not found.');
        }
        $tasks = App::db()->all(
            "SELECT id, code, title, status, progress, deadline
               FROM tasks WHERE organization_id = ?
              ORDER BY deadline IS NULL, deadline ASC LIMIT 50",
            [$project['organization_id']]
        );
        Response::ok('Project retrieved.', ['project' => $project, 'related_tasks' => $tasks]);
    }

    public function create(): never
    {
        $this->requirePermission('CREATE_PROJECT');
        $this->validate([
            'code' => ['required', 'string:32'],
            'name' => ['required', 'string'],
            'description' => ['string'],
            'organization_id' => ['required', 'int:1'],
            'department_id' => ['int:1'],
            'start_date' => ['date'],
            'end_date' => ['date'],
        ]);
        $orgId = (int) Request::input('organization_id');
        Scope::requiresOrg($this->user, $orgId);
        $code = strtoupper(trim((string) Request::input('code')));
        if (App::db()->scalar('SELECT id FROM projects WHERE code = ?', [$code]) !== null) {
            Response::error('Project code already exists.', 409);
        }
        $id = App::db()->insert('projects', [
            'code'            => $code,
            'name'            => trim((string) Request::input('name')),
            'description'     => Request::input('description') ?: null,
            'organization_id' => $orgId,
            'department_id'   => Request::input('department_id') ? (int) Request::input('department_id') : null,
            'status'          => Request::input('status') ?: 'planning',
            'progress'        => 0,
            'start_date'      => Request::input('start_date') ?: null,
            'end_date'        => Request::input('end_date') ?: null,
            'created_by'      => (int) $this->user['id'],
        ]);
        Audit::log($this->user['id'], 'CREATE_PROJECT', 'project', $id, ['code' => $code]);
        Response::ok('Project created.', ['project_id' => $id]);
    }

    public function update(array $params): never
    {
        $this->requirePermission('EDIT_PROJECT');
        $project = ProjectModel::findWithScope((int) $params['id'], $this->scopeOrgIds());
        if ($project === null) {
            Response::notFound('Project not found.');
        }
        $data = [];
        foreach (['name', 'description', 'status', 'progress', 'start_date', 'end_date'] as $field) {
            if (Request::input($field) !== null) {
                $data[$field] = Request::input($field);
            }
        }
        if ($data === []) {
            Response::error('Nothing to update.', 422);
        }
        App::db()->update('projects', $data, 'id = :id', ['id' => $project['id']]);
        Audit::log($this->user['id'], 'UPDATE_PROJECT', 'project', $project['id'], ['fields' => array_keys($data)]);
        Response::ok('Project updated.');
    }

    public function delete(array $params): never
    {
        $this->requirePermission('EDIT_PROJECT');
        $project = ProjectModel::findWithScope((int) $params['id'], $this->scopeOrgIds());
        if ($project === null) {
            Response::notFound('Project not found.');
        }
        if ($project['status'] !== 'archived') {
            App::db()->update('projects', ['status' => 'archived'], 'id = :id', ['id' => $project['id']]);
        } elseif ($project['status'] === 'archived') {
            App::db()->delete('projects', 'id = :id', ['id' => $project['id']]);
        }
        Audit::log($this->user['id'], 'ARCHIVE_PROJECT', 'project', $project['id']);
        Response::ok('Project archived.');
    }
}