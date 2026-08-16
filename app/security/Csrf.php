<?php

declare(strict_types=1);

namespace Govyx\Security;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token']) || (int) ($_SESSION['csrf_expires'] ?? 0) < time()) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_expires'] = time() + 3600;
        }
        return $_SESSION['csrf_token'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    /** Verify a provided token. Compatible with _csrf field, X-CSRF-Token header, or _csrf query param. */
    public static function verify(mixed $provided = null): bool
    {
        $provided ??= $_POST['_csrf']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_GET['_csrf']
            ?? null;
        if (!is_string($provided) || $provided === '' || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $provided);
    }

    public static function protect(): void
    {
        $method = \Govyx\Core\Request::method();
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !self::verify()) {
            \Govyx\Core\Response::error('Invalid or missing CSRF token', 419);
        }
    }
}