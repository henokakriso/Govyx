<?php

declare(strict_types=1);

namespace Govyx\Rankor;

use Govyx\Core\App;

/**
 * Rankor - the intelligence component inside GOVYX.
 *
 * Decision-support only. Produces transparent, explainable scores
 * (Section 17, 32, 33): every result carries source data, calculation
 * method, timestamp, version and an explanation.
 */
final class RankorEngine
{
    public const VERSION = '1.0';

    public static function version(): string
    {
        $v = App::db()->scalar("SELECT `value` FROM settings WHERE `key` = 'rankor.method_version'");
        return $v === null ? self::VERSION : (string) $v;
    }

    /**
     * Recompute delay indicators for every active task in the visible scope.
     */
    public static function delayScores(array $orgIds, ?int $officialId = null): array
    {
        $scores = [];
        if ($orgIds === []) {
            return $scores;
        }
        $placeholders = implode(',', array_fill(0, count($orgIds), '?'));
        $params = $orgIds;
        $sql = "SELECT t.id, t.assigned_to, t.deadline, t.status, t.priority, t.progress,
                       DATEDIFF(CURDATE(), t.deadline) AS days_overdue
                  FROM tasks t
                 WHERE t.organization_id IN ($placeholders)
                   AND t.status NOT IN ('completed','reviewed')";
        if ($officialId !== null) {
            $sql .= ' AND t.assigned_to = ?';
            $params[] = $officialId;
        }
        $rows = App::db()->all($sql, $params);
        $cScores = CScoreClient::delayScores($rows);
        $cMap = [];
        foreach ($cScores as $cs) {
            $cMap[(int) $cs['task_id']] = (float) $cs['delay_score'];
        }
        $source = CScoreClient::available() && $cMap !== [] ? 'c' : 'php';
        foreach ($rows as $row) {
            $score = 0.0;
            $factors = [];

            if (isset($cMap[(int) $row['id']])) {
                $score = $cMap[(int) $row['id']];
                $factors[] = 'priority ' . $row['priority'];
            } elseif ($row['deadline'] !== null) {
                $days = (int) $row['days_overdue'];
                if ($days > 0) {
                    $score += min(50, 10 * $days);
                    $factors[] = "$days day(s) overdue";
                } elseif ($row['deadline'] === date('Y-m-d')) {
                    $score += 15;
                    $factors[] = 'deadline is today';
                } else {
                    $score += 5;
                }
                $priorityPts = ['low' => 5, 'medium' => 10, 'high' => 15, 'critical' => 20][$row['priority']] ?? 10;
                $score += $priorityPts;
                $factors[] = 'priority ' . $row['priority'];
            }

            $scores[$row['id']] = [
                'task_id'     => (int) $row['id'],
                'assigned_to' => $row['assigned_to'] === null ? null : (int) $row['assigned_to'],
                'delay_score' => round(min(100, $score), 2),
                'factors'     => $factors,
                'source'      => $source,
            ];
        }
        return $scores;
    }

    /**
     * Workload score per official: task count, open tasks share, urgency.
     */
    public static function workloadScores(array $orgIds): array
    {
        if ($orgIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($orgIds), '?'));
        $rows = App::db()->all(
            "SELECT t.assigned_to, u.full_name,
                    COUNT(*) AS total,
                    SUM(CASE WHEN t.status NOT IN ('completed','reviewed') THEN 1 ELSE 0 END) AS open,
                    SUM(CASE WHEN t.deadline IS NOT NULL AND t.deadline < CURDATE()
                              AND t.status NOT IN ('completed','reviewed') THEN 1 ELSE 0 END) AS overdue
               FROM tasks t
               JOIN users u ON u.id = t.assigned_to
              WHERE t.organization_id IN ($placeholders) AND t.assigned_to IS NOT NULL
              GROUP BY t.assigned_to, u.full_name",
            $orgIds
        );
        $threshold = (int) (App::db()->scalar("SELECT `value` FROM settings WHERE `key` = 'risk.workload_threshold'") ?? 6);
        $scores = [];
        foreach ($rows as $r) {
            $total = (int) $r['total'];
            $open = (int) $r['open'];
            $base = min(60, $total / max(1, $threshold) * 40);
            $score = $base + $open * 5;
            $factors = ["$total assigned task(s)", "$open open task(s)"];
            if ((int) $r['overdue'] > 0) {
                $score += (int) $r['overdue'] * 10;
                $factors[] = (int) $r['overdue'] . ' overdue';
            }
            $scores[(int) $r['assigned_to']] = [
                'official_id' => (int) $r['assigned_to'],
                'official'    => $r['full_name'],
                'workload_score' => round(min(100, $score), 2),
                'factors'     => $factors,
            ];
        }
        return $scores;
    }

    /**
     * Risk score for an organization based on KPI decline + repeated delays + workload.
     */
    public static function riskScore(int $orgId, array $delayScores, array $workloadScores): array
    {
        $kpis = App::db()->all(
            "SELECT k.id, k.name, k.target, k.actual,
                    (SELECT km.actual FROM kpi_measurements km WHERE km.kpi_id = k.id
                      ORDER BY km.period DESC LIMIT 1,1) AS prev_actual,
                    (SELECT km.period FROM kpi_measurements km WHERE km.kpi_id = k.id
                      ORDER BY km.period DESC LIMIT 1,1) AS prev_period
               FROM kpis k
              WHERE k.organization_id = ? AND k.status = 'active'",
            [$orgId]
        );
        $score = 0.0;
        $factors = [];

        foreach ($kpis as $kpi) {
            if ((float) $kpi['target'] <= 0) {
                continue;
            }
            $achievement = round((float) $kpi['actual'] / (float) $kpi['target'] * 100, 2);
            if ($achievement < 70) {
                $score += 20;
                $factors[] = "KPI '{$kpi['name']}' at {$achievement}% (below 70%)";
            } elseif ($achievement < 85) {
                $score += 10;
                $factors[] = "KPI '{$kpi['name']}' at {$achievement}%";
            }
            if ($kpi['prev_actual'] !== null && (float) $kpi['prev_actual'] > (float) $kpi['actual']) {
                $score += 10;
                $factors[] = "KPI '{$kpi['name']}' declining (from {$kpi['prev_actual']} to {$kpi['actual']})";
            }
        }

        $overdue = 0;
        foreach ($delayScores as $d) {
            if ($d['delay_score'] >= 40) {
                $score += 5;
                $overdue++;
            }
        }
        if ($overdue > 0) {
            $factors[] = $overdue . ' task(s) with high delay score';
        }

        $highWorkload = 0;
        foreach ($workloadScores as $w) {
            if ($w['workload_score'] >= 70) {
                $highWorkload++;
            }
        }
        if ($highWorkload > 0) {
            $score += $highWorkload * 5;
            $factors[] = $highWorkload . ' official(s) with high workload';
        }

        return [
            'risk_score' => round(min(100, $score), 2),
            'factors'    => array_slice($factors, 0, 10),
        ];
    }

    /**
     * Performance score for an official: KPI achievement + timeliness + completion.
     * Transparent formula caveats: timeliness = on-time completed share.
     */
    public static function performanceScore(int $officialUserId): array
    {
        $org = App::db()->scalar(
            "SELECT organization_id FROM users WHERE id = ? AND status = 'active'", [$officialUserId]
        );
        if ($org === null) {
            return ['performance_score' => 0, 'factors' => ['official not found']];
        }
        $orgId = (int) $org;

        $kpiAvg = App::db()->scalar(
            "SELECT AVG(k.actual / k.target * 100) FROM kpis k
              WHERE k.organization_id = ? AND k.target > 0 AND k.status = 'active'",
            [$orgId]
        );

        $taskStats = App::db()->one(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN t.status = 'completed' AND t.deadline IS NOT NULL
                              AND t.completed_at IS NOT NULL AND DATE(t.completed_at) <= t.deadline THEN 1 ELSE 0 END) AS on_time
               FROM tasks t WHERE t.assigned_to = ?",
            [$officialUserId]
        );
        $total = (int) $taskStats['total'];
        $completed = (int) $taskStats['completed'];
        $onTime = (int) $taskStats['on_time'];

        $kpiScore = $kpiAvg === null ? 0 : round((float) $kpiAvg, 2);
        $timeliness = $completed > 0 ? round($onTime / $completed * 100, 2) : 0;
        $completion = $total > 0 ? round($completed / $total * 100, 2) : 0;

        $factors = [];
        if ($kpiAvg !== null) {
            $factors[] = 'organization KPI average ' . $kpiScore . '%';
        }
        $factors[] = "$onTime of $completed completed tasks were on time";
        $factors[] = "$completed of $total tasks completed";
        $factors[] = 'formula: 0.5 * KPI achievement + 0.3 * timeliness + 0.2 * completion';

        $totalScore = round(0.5 * $kpiScore + 0.3 * $timeliness + 0.2 * $completion, 2);

        return [
            'performance_score' => $totalScore,
            'kpi_achievement'   => $kpiScore,
            'timeliness'        => $timeliness,
            'completion'        => $completion,
            'factors'           => $factors,
        ];
    }

    /**
     * Run a full analysis cycle for the current scope and persist every result.
     * Returns headline numbers for dashboards.
     */
    public static function runFullAnalysis(array $user, array $orgIds): array
    {
        if ($orgIds === []) {
            return ['risk_scores' => [], 'alerts_created' => 0, 'delay_scores' => [], 'workload_scores' => []];
        }
        $version = self::version();
        $delay = self::delayScores($orgIds);
        $workload = self::workloadScores($orgIds);
        $orgRisks = [];
        $stored = 0;

        foreach ($orgIds as $orgId) {
            $risk = self::riskScore($orgId, $delay, $workload);
            $orgRisks[$orgId] = $risk;
            if ($risk['risk_score'] >= 50) {
                $title = 'Operational risk alert (score ' . $risk['risk_score'] . ')';
                $existing = App::db()->scalar(
                    "SELECT id FROM risk_alerts WHERE organization_id = ? AND status = 'open' AND title = ?",
                    [$orgId, $title]
                );
                if ($existing === null) {
                    $alertId = App::db()->insert('risk_alerts', [
                        'organization_id' => $orgId,
                        'title'           => $title,
                        'description'     => implode('; ', $risk['factors']),
                        'severity'        => $risk['risk_score'] >= 75 ? 'high' : 'medium',
                        'factors_json'    => json_encode($risk['factors'], JSON_UNESCAPED_UNICODE),
                        'status'          => 'open',
                    ]);
                    $stored++;
                    $officials = App::db()->all(
                        "SELECT u.id FROM users u WHERE u.organization_id = ? AND u.status = 'active'",
                        [$orgId]
                    );
                    foreach ($officials as $o) {
                        App::db()->insert('notifications', [
                            'user_id' => (int) $o['id'],
                            'title'   => $title,
                            'message' => 'Rankor detected: ' . implode('; ', $risk['factors']),
                            'type'    => 'risk',
                            'related_type' => 'risk_alert',
                            'related_id'   => $alertId,
                        ]);
                    }
                }
            }
        }

        $officials = App::db()->all(
            "SELECT DISTINCT t.assigned_to AS uid FROM tasks t
              WHERE t.organization_id IN (" . implode(',', array_fill(0, count($orgIds), '?')) . ")
                AND t.assigned_to IS NOT NULL AND t.status NOT IN ('completed','reviewed')",
            $orgIds
        );
        foreach ($officials as $off) {
            $uid = (int) $off['uid'];
            $perf = self::performanceScore($uid);
            $officialRow = App::db()->one(
                "SELECT o.id AS official_id, o.organization_id, o.department_id FROM officials o WHERE o.user_id = ?",
                [$uid]
            );
            if ($officialRow !== null) {
                App::db()->insert('performance_records', [
                    'official_id'     => (int) $officialRow['official_id'],
                    'organization_id' => (int) $officialRow['organization_id'],
                    'department_id'   => (int) $officialRow['department_id'],
                    'period'          => date('Y-m'),
                    'kpi_achievement' => $perf['kpi_achievement'],
                    'timeliness'      => $perf['timeliness'],
                    'quality'         => null,
                    'completion'      => $perf['completion'],
                    'total_score'     => $perf['performance_score'],
                    'method_version'  => $version,
                    'explanation'     => implode('; ', $perf['factors']),
                    'calculated_by'   => (int) $user['id'],
                ]);
            }
        }

        foreach ($delay as $d) {
            App::db()->insert('rankor_analyses', [
                'target_type'    => 'task',
                'target_id'      => $d['task_id'],
                'score_type'     => 'delay',
                'score'          => $d['delay_score'],
                'method_version' => $version,
                'factors_json'   => json_encode($d['factors'], JSON_UNESCAPED_UNICODE),
                'explanation'    => 'Delay score: ' . implode('; ', $d['factors']),
                'source'         => $d['source'] ?? 'php',
                'created_by'     => (int) $user['id'],
            ]);
        }

        return [
            'risk_scores' => $orgRisks,
            'alerts_created' => $stored,
            'delay_scores' => $delay,
            'workload_scores' => $workload,
        ];
    }
}