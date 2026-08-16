<?php

declare(strict_types=1);

namespace Govyx\Controllers;

use Govyx\Core\App;
use Govyx\Core\Request;
use Govyx\Core\Response;
use Govyx\Security\Audit;

class AlertsController extends BaseApiController
{
    public function index(): never
    {
        $this->requirePermission('VIEW_RISK');
        $orgIds = $this->scopeOrgIds();
        $rows = [];
        if ($orgIds !== []) {
            $placeholders = implode(',', array_fill(0, count($orgIds), '?'));
            $rows = App::db()->all(
                "SELECT ra.*, COALESCE(o.name, '-') AS organization_name,
                        COALESCE(d.name, '-') AS department_name,
                        COALESCE(u.full_name, '-') AS reviewed_by_name
                   FROM risk_alerts ra
                   LEFT JOIN organizations o ON o.id = ra.organization_id
                   LEFT JOIN departments d ON d.id = ra.department_id
                   LEFT JOIN users u ON u.id = ra.reviewed_by
                  WHERE ra.organization_id IS NULL OR ra.organization_id IN ($placeholders)
                  ORDER BY FIELD(ra.status,'open','under_review','resolved'), ra.severity DESC, ra.id DESC",
                $orgIds
            );
        }
        Response::ok('Risk alerts retrieved.', ['alerts' => $rows]);
    }

    public function review(array $params): never
    {
        $this->requirePermission('REVIEW_RISK');
        $alert = App::db()->one('SELECT * FROM risk_alerts WHERE id = ?', [$params['id']]);
        if ($alert === null) {
            Response::notFound('Alert not found.');
        }
        $this->validate(['status' => ['in:open,under_review,resolved,dismissed']]);
        App::db()->update('risk_alerts', [
            'status'       => Request::input('status'),
            'reviewed_by'  => (int) $this->user['id'],
            'reviewed_at'  => date('Y-m-d H:i:s'),
            'review_note'  => Request::input('note') ?: null,
        ], 'id = :id', ['id' => $alert['id']]);
        Audit::log($this->user['id'], 'REVIEW_RISK', 'risk_alert', $alert['id'], ['status' => Request::input('status')]);
        Response::ok('Alert status updated.');
    }
}