<?php

declare(strict_types=1);

namespace Govyx\Controllers;

use Govyx\Core\App;
use Govyx\Core\Request;
use Govyx\Core\Response;
use Govyx\Security\Audit;

class ReportsController extends BaseApiController
{
    public function index(): never
    {
        $this->requirePermission('VIEW_REPORT');
        $rows = App::db()->all(
            "SELECT r.*, COALESCE(o.name, '-') AS organization_name, u.full_name AS generated_by_name
               FROM reports r
               LEFT JOIN organizations o ON o.id = r.organization_id
               JOIN users u ON u.id = r.generated_by
              ORDER BY r.id DESC LIMIT 100"
        );
        Response::ok('Reports retrieved.', ['reports' => $rows]);
    }

    public function show(array $params): never
    {
        $this->requirePermission('VIEW_REPORT');
        $report = App::db()->one('SELECT * FROM reports WHERE id = ?', [$params['id']]);
        if ($report === null) {
            Response::notFound('Report not found.');
        }
        if ($report['organization_id'] !== null) {
            \Govyx\Security\Scope::requiresOrg($this->user, (int) $report['organization_id']);
        }
        $report['json_data'] = json_decode((string) $report['json_data'], true);
        Response::ok('Report retrieved.', ['report' => $report]);
    }

    public function generate(): never
    {
        $this->requirePermission('GENERATE_REPORT');
        $type = (string) (Request::input('type') ?? 'executive_summary');
        $allowed = [
            'kpi', 'task', 'department', 'organization', 'project',
            'performance', 'risk', 'executive_summary', 'audit',
        ];
        if (!in_array($type, $allowed, true)) {
            Response::error('Unsupported report type.', 422);
        }
        $orgIds = $this->scopeOrgIds();
        $report = self::buildReport($type, $orgIds);
        $id = App::db()->insert('reports', [
            'title' => 'Report: ' . strtoupper(str_replace('_', ' ', $type)) . ' — ' . date('Y-m-d H:i'),
            'type' => $type,
            'organization_id' => null,
            'period' => date('Y-m'),
            'generated_by' => (int) $this->user['id'],
            'json_data' => json_encode($report, JSON_UNESCAPED_UNICODE),
        ]);
        Audit::log($this->user['id'], 'GENERATE_REPORT', 'report', $id, ['type' => $type]);
        Response::ok('Report generated.', ['report_id' => $id, 'report' => $report]);
    }

    private static function buildReport(string $type, array $orgIds): array
    {
        $db = App::db();
        if ($orgIds === []) {
            return ['generated_at' => date('c'), 'empty_scope' => true];
        }
        $ph = implode(',', array_fill(0, count($orgIds), '?'));
        $common = [
            'generated_at' => date('c'),
            'scope_orgs' => $db->all("SELECT id, name FROM organizations WHERE id IN ($ph)", $orgIds),
        ];
        return match ($type) {
            'kpi' => array_merge($common, ['kpis' => $db->all(
                "SELECT k.code, k.name, k.target, k.actual,
                        ROUND(CASE WHEN k.target = 0 THEN NULL ELSE k.actual / k.target * 100 END, 2) AS achievement,
                        k.unit, k.period, d.name AS department
                   FROM kpis k LEFT JOIN departments d ON d.id = k.department_id
                  WHERE k.organization_id IN ($ph)", $orgIds)]),
            'task' => array_merge($common, ['tasks' => $db->all(
                "SELECT t.code, t.title, t.status, t.progress, t.priority, t.deadline,
                        COALESCE(u.full_name, '-') AS assignee, d.name AS department
                   FROM tasks t
                   LEFT JOIN users u ON u.id = t.assigned_to
                   LEFT JOIN departments d ON d.id = t.department_id
                  WHERE t.organization_id IN ($ph)
                  ORDER BY FIELD(t.status,'created','assigned','in_progress','submitted','reviewed','completed')", $orgIds)]),
            'performance' => array_merge($common, ['records' => $db->all(
                "SELECT u.full_name, d.name AS department, pr.period, pr.total_score, pr.explanation
                   FROM performance_records pr
                   JOIN officials off ON off.id = pr.official_id
                   JOIN users u ON u.id = off.user_id
                   JOIN departments d ON d.id = pr.department_id
                  WHERE pr.organization_id IN ($ph)
                  ORDER BY pr.total_score DESC", $orgIds)]),
            'risk' => array_merge($common, ['alerts' => $db->all(
                "SELECT ra.title, ra.severity, ra.status, ra.description
                   FROM risk_alerts ra
                  WHERE ra.organization_id IN ($ph)
                  ORDER BY ra.severity DESC, ra.id DESC", $orgIds)]),
            'audit' => array_merge($common, ['events' => $db->all(
                "SELECT al.action, al.entity_type, al.created_at, COALESCE(u.username, 'system') AS actor
                   FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id
                  ORDER BY al.id DESC LIMIT 500", []) ]),
            'executive_summary' => array_merge($common, [
                'task_status' => $db->all(
                    "SELECT status, COUNT(*) AS total FROM tasks WHERE organization_id IN ($ph) GROUP BY status", $orgIds),
                'completion_rate' => (float) $db->scalar(
                    "SELECT ROUND(SUM(CASE WHEN status IN ('completed','reviewed') THEN 1 ELSE 0 END) / COUNT(*) * 100, 2)
                       FROM tasks WHERE organization_id IN ($ph)", $orgIds) ?? 0,
                'overdue' => (int) $db->scalar(
                    "SELECT COUNT(*) FROM tasks
                      WHERE organization_id IN ($ph) AND deadline < CURDATE()
                        AND status NOT IN ('completed','reviewed')", $orgIds),
                'avg_kpi_achievement' => $db->scalar(
                    "SELECT ROUND(AVG(CASE WHEN target = 0 THEN NULL ELSE actual / target * 100 END), 2)
                       FROM kpis WHERE organization_id IN ($ph)", $orgIds),
                'open_alerts' => (int) $db->scalar(
                    "SELECT COUNT(*) FROM risk_alerts WHERE organization_id IN ($ph) AND status = 'open'", $orgIds),
            ]),
            default => $common,
        };
    }
}