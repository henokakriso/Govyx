<?php

declare(strict_types=1);

namespace Govyx\Core;

final class Response
{
    public static function json(mixed $data, int $status = 200, array $headers = []): never
    {
        http_response_code($status);
        foreach ($headers as $name => $value) {
            header("$name: $value");
        }
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $message, int $status = 400, mixed $details = null): never
    {
        $body = ['error' => true, 'message' => $message];
        if ($details !== null && App::isDebug()) {
            $body['details'] = $details;
        }
        self::json($body, $status);
    }

    public static function ok(string $message, mixed $data = null, int $status = 200): never
    {
        $body = ['error' => false, 'message' => $message];
        if ($data !== null) {
            $body = array_merge($body, $data);
        }
        self::json($body, $status);
    }

    public static function unauthorized(string $message = 'Unauthorized'): never
    {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): never
    {
        self::error($message, 403);
    }

    public static function notFound(string $message = 'Not found'): never
    {
        self::error($message, 404);
    }

    public static function html(string $content, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $content;
        exit;
    }

    public static function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }
}