<?php

declare(strict_types=1);

namespace Govyx\Controllers;

use Govyx\Core\App;
use Govyx\Core\Request;
use Govyx\Core\Response;
use Govyx\Security\Audit;

class NotificationsController extends BaseApiController
{
    public function index(): never
    {
        $this->requirePermission('VIEW_NOTIFICATIONS');
        $rows = App::db()->all(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 100",
            [$this->user['id']]
        );
        $unread = (int) App::db()->scalar(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL',
            [$this->user['id']]
        );
        Response::ok('Notifications retrieved.', ['notifications' => $rows, 'unread' => $unread]);
    }

    public function markRead(array $params): never
    {
        $this->requirePermission('VIEW_NOTIFICATIONS');
        $n = App::db()->one('SELECT * FROM notifications WHERE id = ?', [$params['id']]);
        if ($n === null || (int) $n['user_id'] !== (int) $this->user['id']) {
            Response::notFound('Notification not found.');
        }
        App::db()->update('notifications', ['read_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $n['id']]);
        Response::ok('Notification marked as read.');
    }

    public function markAllRead(): never
    {
        $this->requirePermission('VIEW_NOTIFICATIONS');
        App::db()->update('notifications', ['read_at' => date('Y-m-d H:i:s')], 'user_id = :uid AND read_at IS NULL', ['uid' => $this->user['id']]);
        Response::ok('All notifications marked as read.');
    }
}