<?php

declare(strict_types=1);

namespace Govyx\Controllers;

use Govyx\Core\App;
use Govyx\Core\Request;
use Govyx\Core\Response;
use Govyx\Security\Audit;

class SettingsController extends BaseApiController
{
    private const SENSITIVE = ['login_guard_'];

    public function index(): never
    {
        $this->requirePermission('MANAGE_SETTINGS');
        $rows = App::db()->all("SELECT `key`, `value`, updated_at FROM settings WHERE `key` NOT LIKE 'auth.session_%' AND `key` NOT LIKE 'login_guard_%' ORDER BY `key`");
        $settings = [];
        foreach ($rows as $row) {
            $decoded = json_decode((string) $row['value'], true);
            $settings[$row['key']] = $decoded !== null ? $decoded : $row['value'];
        }
        Response::ok('Settings retrieved.', ['settings' => $settings]);
    }

    public function update(): never
    {
        $this->requirePermission('MANAGE_SETTINGS');
        $payload = Request::jsonBody();
        if (!is_array($payload) || !isset($payload['settings']) || !is_array($payload['settings'])) {
            Response::error('settings object is required.', 422);
        }
        foreach ($payload['settings'] as $key => $value) {
            $key = (string) $key;
            if (!preg_match('/^[a-z0-9_.]{1,96}$/', $key) || str_starts_with($key, 'auth.session_')) {
                Response::error('Invalid settings key: ' . $key, 422);
            }
            foreach (self::SENSITIVE as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    Response::error('Key is protected: ' . $key, 403);
                }
            }
            $json = json_encode($value);
            App::db()->query(
                'INSERT INTO settings (`key`, `value`, `updated_at`) VALUES (?, ?, NOW())
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()',
                [$key, $json === false ? 'null' : $json]
            );
        }
        Audit::log($this->user['id'], 'UPDATE_SETTINGS', 'settings', null, ['keys' => array_keys($payload['settings'])]);
        Response::ok('Settings saved.');
    }
}