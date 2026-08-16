<?php

declare(strict_types=1);

namespace Govyx\Controllers;

use Govyx\Core\Response;
use Govyx\Core\Request;
use Govyx\Security\Auth;
use Govyx\Security\Csrf;

class AuthController
{
    public const LOCKOUT_MESSAGE = 'Too many failed attempts. Try again later.';

    public function login(): never
    {
        Csrf::protect();
        $username = trim((string) Request::input('username') ?? '');
        $password = (string) Request::input('password') ?? '';

        if ($username === '' || $password === '') {
            Response::error('Username and password are required.', 422);
        }
if (strlen($password) < 6) {
            Response::error('Invalid credentials.', 401);
        }

        $lock = \Govyx\Security\LoginGuard::lockRemaining($username);
        if ($lock > 0) {
            \Govyx\Security\Audit::log(-1, 'LOGIN_BLOCKED', 'user', null, ['username' => $username, 'ip' => Request::ip()]);
            Response::error(self::LOCKOUT_MESSAGE . ' (' . ceil($lock / 60) . ' min)', 429);
        }

        $user = Auth::attempt($username, $password);
        if ($user === null) {
            Response::error('Invalid credentials.', 401);
        }
        Response::ok('Login successful.', [
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'role_code' => $user['role_code'],
                'role_name' => $user['role_name'],
                'organization_name' => $user['organization_name'],
                'department_name' => $user['department_name'] ?? null,
            ],
        ]);
    }

    public function me(): never
    {
        $user = Auth::requireAuth();
        Response::ok('Authenticated user.', ['user' => $user]);
    }

    public function logout(): never
    {
        Csrf::protect();
        Auth::logout();
        Response::ok('Logged out.');
    }

    public function csrf(): never
    {
        Response::json(['token' => Csrf::token()]);
    }
}