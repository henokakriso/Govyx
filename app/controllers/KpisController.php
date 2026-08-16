<?php

declare(strict_types=1);

namespace Govyx\Controllers;

use Govyx\Core\App;
use Govyx\Core\Request;
use Govyx\Core\Response;
use Govyx\Models\KpiModel;
use Govyx\Security\Audit;
use Govyx\Security\Scope;

class KpisController extends BaseApiController
{
    public function index(): never
    {
        $this->requirePermission('VIEW_KPI');
        Response::ok('KPIs retrieved.', ['kpis' => KpiModel::list($this->scopeOrgIds())]);
    }

    public function show(array $params): never
    {
        $this->requirePermission('VIEW_KPI');
        $kpi = KpiModel::findWithScope((int) $params['id'], $this->scopeOrgIds());
        if ($kpi === null) {
            Response::notFound('KPI not found.');
        }
        $kpi['measurements'] = KpiModel::measurements((int) $kpi['id']);
        Response::ok('KPI retrieved.', ['kpi' => $kpi]);
    }

    public function create(): never
    {
        $this->requirePermission('CREATE_KPI');
        $this->validate([
            'code' => ['required', 'string:32'],
            'name' => ['required', 'string'],
            'description' => ['string'],
            'organization_id' => ['required', 'int:1'],
            'department_id' => ['int:1'],
            'measurement_method' => ['string'],
            'target' => ['required', 'numeric'],
            'actual' => ['numeric'],
            'unit' => ['string:32'],
            'period' => ['string:16'],
            'weight' => ['numeric'],
            'threshold' => ['numeric'],
        ]);
        $orgId = (int) Request::input('organization_id');
        Scope::requiresOrg($this->user, $orgId);

        $code = strtoupper(trim((string) Request::input('code')));
        if (App::db()->scalar('SELECT id FROM kpis WHERE code = ?', [$code]) !== null) {
            Response::error('KPI code already exists.', 409);
        }
        $target = (float) Request::input('target');
        $actual = Request::input('actual') !== null ? (float) Request::input('actual') : 0.0;
        $id = App::db()->insert('kpis', [
            'code'               => $code,
            'name'               => trim((string) Request::input('name')),
            'description'        => Request::input('description') ?: null,
            'organization_id'    => $orgId,
            'department_id'      => Request::input('department_id') ? (int) Request::input('department_id') : null,
            'measurement_method' => Request::input('measurement_method') ?: null,
            'target'             => $target,
            'actual'             => $actual,
            'unit'               => Request::input('unit') ?: null,
            'period'             => Request::input('period') ?: null,
            'weight'             => Request::input('weight') !== null ? (float) Request::input('weight') : 1.0,
            'threshold'          => Request::input('threshold') !== null ? (float) Request::input('threshold') : 70.0,
            'status'             => 'active',
            'created_by'         => (int) $this->user['id'],
        ]);
        if ($actual > 0) {
            self::addMeasurement((int) $id, Request::input('period') ?: date('Y-m'), $target, $actual, $this->user['id']);
        }
        Audit::log($this->user['id'], 'CREATE_KPI', 'kpi', $id, ['code' => $code]);
        Response::ok('KPI created.', ['kpi_id' => $id]);
    }

    public function update(array $params): never
    {
        $this->requirePermission('UPDATE_KPI');
        $kpi = KpiModel::findWithScope((int) $params['id'], $this->scopeOrgIds());
        if ($kpi === null) {
            Response::notFound('KPI not found.');
        }
        $data = [];
        foreach (['name', 'description', 'measurement_method', 'target', 'actual', 'unit', 'period', 'weight', 'threshold', 'status'] as $field) {
            if (Request::input($field) !== null) {
                $data[$field] = Request::input($field);
            }
        }
        if ($data === []) {
            Response::error('Nothing to update.', 422);
        }
        App::db()->update('kpis', $data, 'id = :id', ['id' => $kpi['id']]);
        Audit::log($this->user['id'], 'UPDATE_KPI', 'kpi', $kpi['id'], ['fields' => array_keys($data)]);
        Response::ok('KPI updated.');
    }

    public function delete(array $params): never
    {
        $this->requirePermission('UPDATE_KPI');
        $kpi = KpiModel::findWithScope((int) $params['id'], $this->scopeOrgIds());
        if ($kpi === null) {
            Response::notFound('KPI not found.');
        }
        if ($kpi['status'] !== 'archived') {
            App::db()->update('kpis', ['status' => 'archived'], 'id = :id', ['id' => $kpi['id']]);
        } else {
            App::db()->delete('kpi_measurements', 'kpi_id = :id', ['id' => $kpi['id']]);
            App::db()->delete('kpis', 'id = :id', ['id' => $kpi['id']]);
        }
        Audit::log($this->user['id'], 'ARCHIVE_KPI', 'kpi', $kpi['id']);
        Response::ok('KPI archived.');
    }

    public function measurement(array $params): never
    {
        $this->requirePermission('UPDATE_KPI');
        $kpi = KpiModel::findWithScope((int) $params['id'], $this->scopeOrgIds());
        if ($kpi === null) {
            Response::notFound('KPI not found.');
        }
        $this->validate([
            'period' => ['required', 'string:16'],
            'target' => ['required', 'numeric'],
            'actual' => ['required', 'numeric'],
        ]);
        $target = (float) Request::input('target');
        $actual = (float) Request::input('actual');
        self::addMeasurement((int) $kpi['id'], (string) Request::input('period'), $target, $actual, (int) $this->user['id']);
        App::db()->update('kpis', ['target' => $target, 'actual' => $actual], 'id = :id', ['id' => $kpi['id']]);
        Audit::log($this->user['id'], 'UPDATE_KPI', 'kpi', $kpi['id'], ['measurement' => Request::input('period')]);
        Response::ok('KPI measurement recorded.');
    }

    private static function addMeasurement(int $kpiId, string $period, float $target, float $actual, int $userId): void
    {
        $achievement = $target > 0 ? round($actual / $target * 100, 2) : null;
        $exists = App::db()->scalar('SELECT id FROM kpi_measurements WHERE kpi_id = ? AND period = ?', [$kpiId, $period]);
        if ($exists !== null) {
            App::db()->update('kpi_measurements', [
                'target' => $target, 'actual' => $actual, 'achievement' => $achievement, 'measured_by' => $userId,
            ], 'id = :id', ['id' => $exists]);
        } else {
            App::db()->insert('kpi_measurements', [
                'kpi_id' => $kpiId, 'period' => $period, 'target' => $target, 'actual' => $actual,
                'achievement' => $achievement, 'measured_by' => $userId,
            ]);
        }
    }
}