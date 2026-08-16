<?php

declare(strict_types=1);

function env(string $key, string $default = ''): string
{
    $v = getenv($key);
    return $v === false || $v === '' ? $default : $v;
}

return [
    'database' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'govyx',
        'user'     => 'root',
        'password' => 'theo23',
        'charset'  => 'utf8mb4',
    ],
    'app' => [
        'name'           => 'GOVYX',
        'env'            => 'development',
        'timezone'       => 'Africa/Addis_Ababa',
        'debug'          => true,
        'force_https'    => false,
        'https_by_default' => false,
        'public_base'    => env('GOVYX_BASE_URL') ?: '',
    ],
    'security' => [
        'session_lifetime'     => 7200,
        'session_name'         => 'GOVYXSESSID',
        'csrf_lifetime'        => 3600,
        'min_password_len'     => 8,
        'login_attempt_window' => 900,          // seconds to remember failed attempts
        'login_max_failures'   => 5,            // lockout threshold per user + per IP
        'login_lockout_seconds'=> 900,          // lockout duration
        'max_upload_bytes'     => 20971520,     // 20 MB
    ],
    'storage' => [
        'root'     => dirname(__DIR__) . '/storage',
        'evidence' => dirname(__DIR__) . '/storage/evidence',
        'reports'  => dirname(__DIR__) . '/storage/reports',
    ],
];