<?php

declare(strict_types=1);

namespace Govyx\Security;

use Govyx\Core\App;
use Govyx\Core\Database;
use Govyx\Core\Response;

final class Auth
{
    public const SESSION_USER = 'govyx_user';
    public const SESSION_TOKEN = 'govyx_token';

    public static function attempt(string $username, string $password): ?array
    {
        $user = App::db()->one(
            "SELECT u.*, r.code AS role_code, r.name AS role_name,
                    o.name AS organization_name, o.type AS organization_type,
                    d.name AS department_name
               FROM users u
               JOIN roles r ON r.id = u.role_id
               JOIN organizations o ON o.id = u.organization_id
               LEFT JOIN departments d ON d.id = u.department_id
              WHERE u.username = ? AND u.status = 'active'",
            [$username]
        );
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            LoginGuard::recordFailure($username);
            Audit::log($user['id'] ?? -1, 'LOGIN_FAILED', 'user', null, ['username' => $username]);
            return null;
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            App::db()->update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $user['id']]);
        }

        $token = bin2hex(random_bytes(32));
        $ttl = (int) App::config('security.session_lifetime');
        // Token table kept in DB so it survives across sessions/workers.
        App::db()->query(
            'INSERT INTO settings (`key`, `value`, `updated_by`) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_by = VALUES(updated_by)',
            ['auth.session_' . $user['id'], json_encode(['token' => $token, 'expires' => time() + $ttl]), $user['id']]
        );

        unset($user['password_hash']);
        session_regenerate_id(true); // session fixation protection
        $_SESSION[self::SESSION_USER] = $user;
        $_SESSION[self::SESSION_TOKEN] = $token;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_expires'] = time() + (int) App::config('security.csrf_lifetime');

        App::db()->update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $user['id']]);
        Audit::log($user['id'], 'LOGIN', 'user', $user['id'], ['username' => $username]);
        LoginGuard::clear($username);
        return $user;
    }

    public static function check(): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }
        $stored = App::db()->one('SELECT `value` FROM settings WHERE `key` = ?', ['auth.session_' . $user['id']]);
        if ($stored === null) {
            return false;
        }
        $payload = json_decode((string) $stored['value'], true);
        if (!is_array($payload) || ($payload['expires'] ?? 0) < time()) {
            self::logout();
            return false;
        }
        return hash_equals((string) $payload['token'], (string) ($_SESSION[self::SESSION_TOKEN] ?? ''));
    }

    public static function user(): ?array
    {
        $user = $_SESSION[self::SESSION_USER] ?? null;
        return is_array($user) ? $user : null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user === null ? null : (int) $user['id'];
    }

    public static function requireAuth(): array
    {
        if (!self::check()) {
            Response::unauthorized('Authentication required. Please log in.');
        }
        return self::user();
    }

    public static function logout(): void
    {
        $user = self::user();
        if ($user !== null) {
            Audit::log((int) $user['id'], 'LOGOUT', 'user', $user['id']);
            App::db()->query('DELETE FROM settings WHERE `key` = ?', ['auth.session_' . $user['id']]);
        }
        unset($_SESSION[self::SESSION_USER], $_SESSION[self::SESSION_TOKEN]);
        session_regenerate_id(true);
    }

    public static function scopeOrganizationIds(): array
    {
        $user = self::requireAuth();
        return Scope::organizationIds((int) $user['organization_id'], (string) $user['role_code']);
    }
}