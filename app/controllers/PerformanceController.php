<?php

declare(strict_types=1);

namespace Govyx\Controllers;

use Govyx\Core\App;
use Govyx\Core\Response;
use Govyx\Rankor\RankorEngine;
use Govyx\Security\Audit;

class PerformanceController extends BaseApiController
{
    public function index(): never
    {
        $this->requirePermission('VIEW_PERFORMANCE');
        $rows = App::db()->all(
            "SELECT pr.*, o.full_name AS official_name, d.name AS department_name, org.name AS organization_name
               FROM performance_records pr
               JOIN officials off ON off.id = pr.official_id
               JOIN users o ON o.id = off.user_id
               JOIN departments d ON d.id = pr.department_id
               JOIN organizations org ON org.id = pr.organization_id
              ORDER BY pr.id DESC LIMIT 200"
        );
        Response::ok('Performance records retrieved.', ['records' => $rows]);
    }

    public function calculate(): never
    {
        $this->requirePermission('CALCULATE_PERFORMANCE');
        $orgIds = $this->scopeOrgIds();
        $result = RankorEngine::runFullAnalysis($this->user, $orgIds);
        Audit::log($this->user['id'], 'CALCULATE_PERFORMANCE', null, null, ['alerts' => $result['alerts_created']]);
        Response::ok('Performance and risk analysis completed.', $result);
    }
}