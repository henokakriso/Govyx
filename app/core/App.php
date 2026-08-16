<?php

declare(strict_types=1);

namespace Govyx\Core;

use Govyx\Core\Database;

final class App
{
    private static ?App $instance = null;
    private array $config = [];
    public ?Database $db = null;

    private function __construct()
    {
        $this->config = require dirname(__DIR__, 2) . '/config/config.php';
        date_default_timezone_set($this->config['app']['timezone'] ?? 'UTC');
        $this->db = new Database($this->config['database']);
    }

    public static function init(): App
    {
        if (self::$instance === null) {
            self::$instance = new App();
        }
        return self::$instance;
    }

    public static function db(): Database
    {
        return self::init()->db;
    }

    public static function config(?string $key = null): mixed
    {
        $cfg = self::init()->config;
        if ($key === null) {
            return $cfg;
        }
        foreach (explode('.', $key) as $part) {
            if (!is_array($cfg) || !array_key_exists($part, $cfg)) {
                return null;
            }
            $cfg = $cfg[$part];
        }
        return $cfg;
    }

    public static function isDebug(): bool
    {
        return (bool) self::config('app.debug');
    }

    public static function isSecure(): bool
    {
        return (bool) self::config('app.force_https') || self::config('app.https_by_default');
    }

    private function __clone() {}
    public function __wakeup(): void { throw new RuntimeException('Cannot unserialize App'); }
}