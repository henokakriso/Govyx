<?php

declare(strict_types=1);

use Govyx\Core\App;

final class Bootstrap
{
    private static ?string $baseDir = null;

    public static function baseDir(): string
    {
        if (self::$baseDir === null) {
            self::$baseDir = dirname(__DIR__);
        }
        return self::$baseDir;
    }

    public static function registerAutoloader(): void
    {
        spl_autoload_register(static function (string $class): void {
            $prefix = 'Govyx\\';
            if (str_starts_with($class, $prefix)) {
                $rest = substr($class, strlen($prefix));
                $segments = explode('\\', $rest);
                $segments[0] = strtolower($segments[0]);
                $path = self::baseDir() . '/app/' . implode('/', $segments) . '.php';
                if (is_file($path)) {
                    require $path;
                }
                return;
            }
            foreach ([
                self::baseDir() . '/app',
                self::baseDir() . '/app/controllers',
                self::baseDir() . '/app/services',
                self::baseDir() . '/app/models',
                self::baseDir() . '/app/repositories',
                self::baseDir() . '/app/middleware',
                self::baseDir() . '/app/validators',
                self::baseDir() . '/app/security',
                self::baseDir() . '/app/rankor',
            ] as $dir) {
                $path = $dir . '/' . $class . '.php';
                if (is_file($path)) {
                    require $path;
                    return;
                }
            }
        });
    }

    public static function start(): void
    {
        self::registerAutoloader();
        session_name((string) App::config('security.session_name'));
        session_set_cookie_params([
            'lifetime' => (int) App::config('security.session_lifetime'),
            'httponly' => true,
            'samesite' => 'Strict',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'path'     => '/',
        ]);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        App::init();
    }
}