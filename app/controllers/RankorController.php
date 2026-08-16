<?php

declare(strict_types=1);

namespace Govyx\Controllers;

use Govyx\Core\App;
use Govyx\Core\Response;
use Govyx\Rankor\RankorEngine;
use Govyx\Security\Audit;

class RankorController extends BaseApiController
{
    public function run(): never
    {
        $this->requirePermission('RUN_RANKOR');
        $result = RankorEngine::runFullAnalysis($this->user, $this->scopeOrgIds());
        Audit::log($this->user['id'], 'RUN_RANKOR', null, null, [
            'alerts_created' => $result['alerts_created'],
            'orgs_analyzed' => count($result['risk_scores']),
        ]);
        Response::ok('Rankor analysis completed.', $result);
    }

    public function index(): never
    {
        $this->requirePermission('VIEW_RANKOR');
        $rows = App::db()->all(
            "SELECT ra.*, COALESCE(u.username, 'system') AS triggered_by
               FROM rankor_analyses ra
               LEFT JOIN users u ON u.id = ra.created_by
              ORDER BY ra.id DESC LIMIT 100"
        );
        foreach ($rows as &$row) {
            $row['factors'] = json_decode((string) $row['factors_json'], true);
            $row['factors_json'] = null;
        }
        Response::ok('Rankor analyses retrieved.', ['analyses' => $rows]);
    }

    public function show(array $params): never
    {
        $this->requirePermission('VIEW_RANKOR');
        $row = App::db()->one('SELECT * FROM rankor_analyses WHERE id = ?', [$params['id']]);
        if ($row === null) {
            Response::notFound('Analysis not found.');
        }
        $row['factors'] = json_decode((string) $row['factors_json'], true);
        Response::ok('Rankor analysis retrieved.', ['analysis' => $row]);
    }
}