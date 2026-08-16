<?php

declare(strict_types=1);

namespace Govyx\Security;

use Govyx\Core\App;
use Govyx\Core\Request;

/**
 * Login throttling (Section 28: credential attacks).
 * Tracks failures per user and per IP, with time-based lockout.
 * Records live in the settings table via JSON blobs (no extra schema).
 */
final class LoginGuard
{
    private static function key(string $scope): string
    {
        return 'login_guard_' . md5($scope);
    }

    private static function read(string $key): array
    {
        $row = App::db()->one('SELECT `value` FROM settings WHERE `key` = ?', [$key]);
        $data = $row !== null ? json_decode((string) $row['value'], true) : null;
        return is_array($data) ? $data : ['failures' => [], 'locked_until' => 0];
    }

    private static function write(string $key, array $data): void
    {
        App::db()->query(
            'INSERT INTO settings (`key`, `value`, `updated_at`) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()',
            [$key, json_encode($data)]
        );
    }

    /** Returns lockout remaining seconds for a username, or 0 when allowed. */
    public static function lockRemaining(string $username): int
    {
        $max = (int) App::config('security.login_max_failures');

        $byUser = self::read(self::key('user:' . strtolower($username)));
        $byIp = self::read(self::key('ip:' . Request::ip()));

        foreach ([$byUser, $byIp] as $entry) {
            $count = count(array_filter($entry['failures'], fn(int $t) => $t > time() - (int) App::config('security.login_attempt_window')));
            if ($count >= $max) {
                if ($entry['locked_until'] > time()) {
                    return $entry['locked_until'] - time();
                }
                $lockUntil = time() + (int) App::config('security.login_lockout_seconds');
                $entry['locked_until'] = $lockUntil;
                self::write(self::key('user:' . strtolower($username)), $byUser);
                self::write(self::key('ip:' . Request::ip()), $byIp);
                return (int) App::config('security.login_lockout_seconds');
            }
        }
        return 0;
    }

    public static function recordFailure(string $username): void
    {
        $now = time();

        $userKey = self::key('user:' . strtolower($username));
        $userData = self::read($userKey);
        $userData['failures'][] = $now;
        $userData['failures'] = array_values(array_filter($userData['failures'], fn(int $t) => $t > $now - 86400));
        self::write($userKey, $userData);

        $ipKey = self::key('ip:' . Request::ip());
        $ipData = self::read($ipKey);
        $ipData['failures'][] = $now;
        $ipData['failures'] = array_values(array_filter($ipData['failures'], fn(int $t) => $t > $now - 86400));
        self::write($ipKey, $ipData);
    }

    public static function clear(string $username): void
    {
        App::db()->query('DELETE FROM settings WHERE `key` IN (?, ?)', [
            self::key('user:' . strtolower($username)),
            self::key('ip:' . Request::ip()),
        ]);
    }

    public static function setupCleanup(): void
    {
        // Housekeeping: remove guard entries older than 2 days.
        App::db()->query(
            "DELETE FROM settings WHERE `key` LIKE 'login_guard_%' "
            . "AND (UNIX_TIMESTAMP(updated_at) < UNIX_TIMESTAMP() - 172800)"
        );
    }
}