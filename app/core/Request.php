<?php

declare(strict_types=1);

namespace Govyx\Core;

final class Request
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function path(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        // Only strip a base directory when this is truly a routed request to the
        // front controller (the built-in server reports the static path itself).
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (basename($script) === 'index.php') {
            $base = '/' . trim(dirname($script), '/');
            if ($base !== '/' && (str_starts_with($uri, $base . '/') || $uri === $base)) {
                $uri = substr($uri, strlen($base)) ?: '/';
            }
        }
        return '/' . ltrim($uri, '/');
    }

    public static function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public static function queryInt(string $key, int $default = 0): int
    {
        $v = $_GET[$key] ?? $default;
        return ctype_digit((string) $v) ? (int) $v : $default;
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        $body = self::jsonBody();
        if (is_array($body) && array_key_exists($key, $body)) {
            return $body[$key];
        }
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    /** Raw JSON request body as assoc array (first call cached). */
    public static function jsonBody(): ?array
    {
        static $cached = null;
        static $done = false;
        if ($done) {
            return $cached;
        }
        $done = true;
        $raw = file_get_contents('php://input');
        $data = json_decode((string) $raw, true);
        $cached = is_array($data) ? $data : null;
        return $cached;
    }

    public static function all(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = self::input($key);
        }
        return $out;
    }

    public static function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    public static function userAgent(): string
    {
        return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    }

    public static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return $m[1];
        }
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $h = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            if (preg_match('/^Bearer\s+(\S+)$/i', $h, $m)) {
                return $m[1];
            }
        }
        return null;
    }
}