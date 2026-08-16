<?php

declare(strict_types=1);

namespace Govyx\Controllers;

use Govyx\Core\App;
use Govyx\Core\Request;
use Govyx\Core\Response;
use Govyx\Security\Audit;
use Govyx\Security\Scope;

class AuditController extends BaseApiController
{
    public function index(): never
    {
        $this->requirePermission('VIEW_AUDIT');
        $page = max(1, Request::queryInt('page', 1));
        $per = min(200, max(10, Request::queryInt('per', 50)));
        $offset = ($page - 1) * $per;
        $total = (int) App::db()->scalar('SELECT COUNT(*) FROM audit_logs');
        $rows = App::db()->all(
            "SELECT al.*, COALESCE(u.username, 'system') AS username
               FROM audit_logs al
               LEFT JOIN users u ON u.id = al.user_id
              ORDER BY al.id DESC
              LIMIT $per OFFSET $offset"
        );
        Response::ok('Audit logs retrieved.', ['logs' => $rows, 'total' => $total, 'page' => $page, 'per' => $per]);
    }
}