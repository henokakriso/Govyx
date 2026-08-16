<?php

declare(strict_types=1);

namespace Govyx\Controllers;

use Govyx\Core\Response;
use Govyx\Models\KpiModel;
use Govyx\Models\OrganizationModel;
use Govyx\Models\TaskModel;

class AnalyticsController extends BaseApiController
{
    public function overview(): never
    {
        $this->requirePermission('VIEW_ANALYTICS');
        $orgIds = $this->scopeOrgIds();
        $statuses = TaskModel::countByStatus($orgIds);

        $orgs = [];
        foreach ($orgIds as $orgId) {
            $org = OrganizationModel::find($orgId);
            if ($org === null) {
                continue;
            }
            $orgs[] = [
                'id'      => $orgId,
                'name'    => $org['name'],
                'type'    => $org['type'],
                'avg_kpi_achievement' => KpiModel::averageAchievement([$orgId]),
                'overdue' => TaskModel::overdue([$orgId]),
                'completion_rate' => TaskModel::completionRate([$orgId]),
            ];
        }

        $trend = TaskModel::countByStatus($orgIds);
        Response::ok('Analytics overview retrieved.', [
            'analytics' => [
                'task_statuses'    => $statuses,
                'organizations'    => $orgs,
                'kpi_average'      => KpiModel::averageAchievement($orgIds),
                'completion_rate'  => TaskModel::completionRate($orgIds),
                'overdue'          => TaskModel::overdue($orgIds),
                'total_tasks'      => array_sum(array_column($statuses, 'total')),
                'trend'            => $trend,
                'generated_at'     => date('c'),
            ],
        ]);
    }
}