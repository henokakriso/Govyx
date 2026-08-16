<?php

declare(strict_types=1);

namespace Govyx\Controllers;

use Govyx\Core\App;
use Govyx\Core\Request;
use Govyx\Core\Response;
use Govyx\Models\TaskModel;
use Govyx\Security\Audit;
use Govyx\Security\Scope;

class TasksController extends BaseApiController
{
    public const STATUSES = [
        'created', 'assigned', 'in_progress', 'submitted',
        'reviewed', 'completed', 'rejected', 'returned',
    ];
    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public static function nextCode(): string
    {
        $count = (int) App::db()->scalar('SELECT COUNT(*) FROM tasks');
        return 'T-' . date('Y') . '-' . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }

    public function index(): never
    {
        $this->requirePermission('VIEW_TASK');
        $filters = [];
        foreach (['status', 'priority', 'department_id', 'overdue'] as $key) {
            if (Request::query($key) !== null) {
                $filters[$key] = Request::query($key);
            }
        }
        if (Request::query('mine') === '1') {
            $filters['assigned_to'] = $this->user['id'];
        }
        $tasks = TaskModel::list($this->scopeOrgIds(), $filters);
        Response::ok('Tasks retrieved.', [
            'tasks' => $tasks,
            'total' => count($tasks),
            'statuses' => self::STATUSES,
        ]);
    }

    public function show(array $params): never
    {
        $this->requirePermission('VIEW_TASK');
        $task = TaskModel::findWithScope((int) $params['id'], $this->scopeOrgIds());
        if ($task === null) {
            Response::notFound('Task not found.');
        }
        $transitions = App::db()->all(
            "SELECT tt.*, u.full_name AS actor_name
               FROM task_transitions tt JOIN users u ON u.id = tt.action_by
              WHERE tt.task_id = ? ORDER BY tt.id ASC",
            [$task['id']]
        );
        $task['transitions'] = $transitions;
        Response::ok('Task retrieved.', ['task' => $task]);
    }

    public function create(): never
    {
        $this->requirePermission('CREATE_TASK');
        $this->validate([
            'title' => ['required', 'string'],
            'description' => ['string'],
            'organization_id' => ['required', 'int:1'],
            'department_id' => ['int:1'],
            'assigned_to' => ['int:1'],
            'priority' => ['in:low,medium,high,critical'],
            'start_date' => ['date'],
            'deadline' => ['date'],
        ]);
        $orgId = (int) Request::input('organization_id');
        Scope::requiresOrg($this->user, $orgId);

        $priority = Request::input('priority') ?: 'medium';
        $id = App::db()->insert('tasks', [
            'code'            => self::nextCode(),
            'title'           => trim((string) Request::input('title')),
            'description'     => Request::input('description') ?: null,
            'organization_id' => $orgId,
            'department_id'   => Request::input('department_id') ? (int) Request::input('department_id') : null,
            'assigned_to'     => Request::input('assigned_to') ? (int) Request::input('assigned_to') : null,
            'created_by'      => (int) $this->user['id'],
            'priority'        => $priority,
            'start_date'      => Request::input('start_date') ?: null,
            'deadline'        => Request::input('deadline') ?: null,
            'status'          => Request::input('assigned_to') ? 'assigned' : 'created',
            'progress'        => 0,
        ]);
        TaskModel::transition($id, null, Request::input('assigned_to') ? 'assigned' : 'created', (int) $this->user['id'], 'Task created');
        Audit::log($this->user['id'], 'CREATE_TASK', 'task', $id, ['code' => 'T-' . date('Y') . '-', 'title' => Request::input('title')]);
        Response::ok('Task created.', ['task_id' => $id]);
    }

    public function assign(array $params): never
    {
        $this->requirePermission('ASSIGN_TASK');
        $task = TaskModel::findWithScope((int) $params['id'], $this->scopeOrgIds());
        if ($task === null) {
            Response::notFound('Task not found.');
        }
        $this->validate(['assigned_to' => ['required', 'int:1']]);
        $assigneeId = (int) Request::input('assigned_to');
        $assignee = App::db()->one('SELECT id, organization_id FROM users WHERE id = ? AND status = "active"', [$assigneeId]);
        if ($assignee === null) {
            Response::error('Assignee not found or inactive.', 422);
        }
        Scope::requiresOrg($this->user, (int) $assignee['organization_id']);

        $toStatus = in_array($task['status'], ['created', 'assigned'], true) ? 'assigned' : $task['status'];
        App::db()->update('tasks', ['assigned_to' => $assigneeId, 'status' => $toStatus], 'id = :id', ['id' => $task['id']]);
        TaskModel::transition((int) $task['id'], $task['status'], $toStatus, (int) $this->user['id'], 'Assigned to user ' . $assigneeId);
        App::db()->insert('notifications', [
            'user_id' => $assigneeId,
            'title' => 'New task assigned',
            'message' => 'You have been assigned task: ' . $task['title'],
            'type' => 'task',
            'related_type' => 'task',
            'related_id' => (int) $task['id'],
        ]);
        Audit::log($this->user['id'], 'ASSIGN_TASK', 'task', $task['id'], ['assigned_to' => $assigneeId]);
        Response::ok('Task assigned.');
    }

    public function update(array $params): never
    {
        $task = TaskModel::findWithScope((int) $params['id'], $this->scopeOrgIds());
        if ($task === null) {
            Response::notFound('Task not found.');
        }
        $this->requirePermission('EDIT_TASK');

        $data = [];
        foreach (['title', 'description', 'priority', 'start_date', 'deadline', 'progress'] as $field) {
            if (Request::input($field) !== null) {
                $data[$field] = Request::input($field);
            }
        }
        if (Request::input('progress') !== null) {
            $p = max(0, min(100, (int) Request::input('progress')));
            $data['progress'] = $p;
            if ($p === 100 && in_array($task['status'], ['created', 'assigned', 'in_progress'], true)) {
                $data['status'] = 'submitted';
            }
        }
        if ($data === []) {
            Response::error('Nothing to update.', 422);
        }
        App::db()->update('tasks', $data, 'id = :id', ['id' => $task['id']]);
        if (isset($data['status']) && $data['status'] !== $task['status']) {
            TaskModel::transition((int) $task['id'], $task['status'], $data['status'], (int) $this->user['id'], 'Auto-submitted at 100% progress');
        }
        Audit::log($this->user['id'], 'UPDATE_TASK', 'task', $task['id'], ['fields' => array_keys($data)]);
        Response::ok('Task updated.');
    }

    public function changeStatus(array $params): never
    {
        $task = TaskModel::findWithScope((int) $params['id'], $this->scopeOrgIds());
        if ($task === null) {
            Response::notFound('Task not found.');
        }
        $this->validate([
            'status' => ['in:' . implode(',', self::STATUSES)],
            'note' => ['string:1000'],
        ]);
        $to = (string) Request::input('status');
        $allows = [
            'submitted' => ['created', 'assigned', 'in_progress'],
            'in_progress' => ['created', 'assigned'],
            'returned' => ['submitted', 'in_progress'],
            'rejected' => ['submitted'],
            'completed' => ['submitted', 'reviewed'],
            'reviewed' => ['submitted'],
        ];
        if (($allows[$to] ?? null) !== null && !in_array($task['status'], $allows[$to], true)) {
            Response::error('Invalid transition from "' . $task['status'] . '" to "' . $to . '".', 422);
        }
        if (in_array($to, ['completed', 'reviewed', 'rejected', 'returned'], true)) {
            $this->requirePermission('APPROVE_TASK');
        } elseif ($to === 'submitted' && (int) $task['assigned_to'] !== (int) $this->user['id']) {
            $this->requirePermission('SUBMIT_TASK');
        }

        $note = Request::input('note');
        $extra = [];
        $extra['status'] = $to;
        if ($to === 'submitted') {
            $extra['progress'] = 100;
        }
        if (in_array($to, ['completed', 'reviewed'], true)) {
            $extra['completed_at'] = date('Y-m-d H:i:s');
            $extra['approval_by'] = (int) $this->user['id'];
            $extra['approval_at'] = date('Y-m-d H:i:s');
            $extra['approval_note'] = $note;
        }
        App::db()->update('tasks', $extra, 'id = :id', ['id' => $task['id']]);
        TaskModel::transition((int) $task['id'], $task['status'], $to, (int) $this->user['id'], $note);
        Audit::log($this->user['id'], strtoupper($to === 'completed' ? 'COMPLETE_TASK' : ($to === 'rejected' ? 'REJECT_TASK' : 'UPDATE_TASK')), 'task', $task['id'], ['from' => $task['status'], 'to' => $to]);
        Response::ok('Task status changed to "' . $to . '".');
    }

    public function approve(array $params): never
    {
        $this->requirePermission('APPROVE_TASK');
        $task = TaskModel::findWithScope((int) $params['id'], $this->scopeOrgIds());
        if ($task === null) {
            Response::notFound('Task not found.');
        }
        if (!in_array($task['status'], ['submitted', 'reviewed'], true)) {
            Response::error('Only submitted or reviewed tasks can be approved.', 422);
        }
        App::db()->update('tasks', [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
            'progress' => 100,
            'approval_by' => (int) $this->user['id'],
            'approval_at' => date('Y-m-d H:i:s'),
            'approval_note' => Request::input('note') ?: null,
        ], 'id = :id', ['id' => $task['id']]);
        TaskModel::transition((int) $task['id'], $task['status'], 'completed', (int) $this->user['id'], 'Approved');
        Audit::log($this->user['id'], 'APPROVE_TASK', 'task', $task['id']);
        Response::ok('Task approved and completed.');
    }

    private function isManager(): bool
    {
        return in_array($this->user['role_code'], ['super_admin', 'gov_admin', 'regional_admin', 'woreda_admin', 'org_admin', 'dept_manager'], true);
    }
}