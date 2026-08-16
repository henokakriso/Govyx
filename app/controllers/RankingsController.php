<?php

declare(strict_types=1);

namespace Govyx\Controllers;

use Govyx\Core\App;
use Govyx\Core\Response;

class RankingsController extends BaseApiController
{
    public function index(): never
    {
        $this->requirePermission('VIEW_PERFORMANCE');
        $rows = App::db()->all(
            "SELECT off.id AS official_id, u.full_name, u.username, d.name AS department_name,
                    org.name AS organization_name,
                    pr.total_score, pr.period, pr.explanation
               FROM performance_records pr
               JOIN officials off ON off.id = pr.official_id
               JOIN users u ON u.id = off.user_id
               JOIN departments d ON d.id = pr.department_id
               JOIN organizations org ON org.id = pr.organization_id
              WHERE pr.period = (SELECT MAX(period) FROM performance_records)
              ORDER BY pr.total_score DESC
              LIMIT 100"
        );
        Response::ok('Rankings retrieved.', ['rankings' => $rows]);
    }
}