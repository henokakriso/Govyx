<?php

declare(strict_types=1);

/**
 * GOVYX - front controller.
 * Routes /api/v1/* to API controllers and serves the web shell.
 */

require __DIR__ . '/../app/Bootstrap.php';
Bootstrap::start();

use Govyx\Core\Router;
use Govyx\Security\Headers;
use Govyx\Security\LoginGuard;

Headers::apply();
if (random_int(1, 40) === 1) {
    LoginGuard::setupCleanup();
}
use Govyx\Controllers\AuthController;
use Govyx\Controllers\UsersController;
use Govyx\Controllers\OrganizationsController;
use Govyx\Controllers\DepartmentsController;
use Govyx\Controllers\OfficialsController;
use Govyx\Controllers\TasksController;
use Govyx\Controllers\ProjectsController;
use Govyx\Controllers\KpisController;
use Govyx\Controllers\PerformanceController;
use Govyx\Controllers\RankingsController;
use Govyx\Controllers\AlertsController;
use Govyx\Controllers\NotificationsController;
use Govyx\Controllers\ReportsController;
use Govyx\Controllers\AnalyticsController;
use Govyx\Controllers\AuditController;
use Govyx\Controllers\RankorController;
use Govyx\Controllers\EvidenceController;
use Govyx\Controllers\RolesController;
use Govyx\Controllers\SettingsController;
use Govyx\Security\Auth;

$path = Govyx\Core\Request::path();

// Serve static assets directly (PHP built-in server router mode).
$file = __DIR__ . $path;
if (is_file($file) && $path !== '/index.php') {
    return false;
}

if ($path === '/login' && Govyx\Core\Request::method() === 'GET') {
    if (Auth::check()) {
        Govyx\Core\Response::redirect('/');
    }
    renderLogin();
}

if ($path === '/login') {
    $auth = new AuthController();
    $_POST['username'] = $_POST['username'] ?? null;
    $_POST['password'] = $_POST['password'] ?? null;
    $auth->login();
}

if (str_starts_with($path, '/api/v1/')) {
    $router = new Router();

    // ---- auth ----
    $router->get('/api/v1/auth/me', ['Govyx\\Controllers\\AuthController', 'me']);
    $router->get('/api/v1/auth/csrf', ['Govyx\\Controllers\\AuthController', 'csrf']);
    $router->post('/api/v1/auth/login', ['Govyx\\Controllers\\AuthController', 'login']);
    $router->post('/api/v1/auth/logout', ['Govyx\\Controllers\\AuthController', 'logout']);

    // ---- users / organizations / departments / officials ----
    $router->get('/api/v1/users', ['Govyx\\Controllers\\UsersController', 'index']);
    $router->post('/api/v1/users', ['Govyx\\Controllers\\UsersController', 'create']);
    $router->get('/api/v1/users/{id}', ['Govyx\\Controllers\\UsersController', 'show']);
    $router->put('/api/v1/users/{id}', ['Govyx\\Controllers\\UsersController', 'update']);
    $router->delete('/api/v1/users/{id}', ['Govyx\\Controllers\\UsersController', 'destroy']);

    $router->get('/api/v1/roles', ['Govyx\\Controllers\\RolesController', 'index']);
    $router->post('/api/v1/roles', ['Govyx\\Controllers\\RolesController', 'create']);
    $router->get('/api/v1/roles/{id}', ['Govyx\\Controllers\\RolesController', 'show']);
    $router->put('/api/v1/roles/{id}', ['Govyx\\Controllers\\RolesController', 'update']);
    $router->delete('/api/v1/roles/{id}', ['Govyx\\Controllers\\RolesController', 'delete']);

    $router->get('/api/v1/settings', ['Govyx\\Controllers\\SettingsController', 'index']);
    $router->put('/api/v1/settings', ['Govyx\\Controllers\\SettingsController', 'update']);

    $router->get('/api/v1/organizations', ['Govyx\\Controllers\\OrganizationsController', 'index']);
    $router->post('/api/v1/organizations', ['Govyx\\Controllers\\OrganizationsController', 'create']);
    $router->get('/api/v1/organizations/{id}', ['Govyx\\Controllers\\OrganizationsController', 'show']);
    $router->put('/api/v1/organizations/{id}', ['Govyx\\Controllers\\OrganizationsController', 'update']);
    $router->delete('/api/v1/organizations/{id}', ['Govyx\\Controllers\\OrganizationsController', 'delete']);

    $router->get('/api/v1/departments', ['Govyx\\Controllers\\DepartmentsController', 'index']);
    $router->post('/api/v1/departments', ['Govyx\\Controllers\\DepartmentsController', 'create']);
    $router->get('/api/v1/departments/{id}', ['Govyx\\Controllers\\DepartmentsController', 'show']);
    $router->put('/api/v1/departments/{id}', ['Govyx\\Controllers\\DepartmentsController', 'update']);
    $router->delete('/api/v1/departments/{id}', ['Govyx\\Controllers\\DepartmentsController', 'delete']);

    $router->get('/api/v1/officials', ['Govyx\\Controllers\\OfficialsController', 'index']);
    $router->post('/api/v1/officials', ['Govyx\\Controllers\\OfficialsController', 'create']);
    $router->get('/api/v1/officials/{id}', ['Govyx\\Controllers\\OfficialsController', 'show']);
    $router->put('/api/v1/officials/{id}', ['Govyx\\Controllers\\OfficialsController', 'update']);
    $router->delete('/api/v1/officials/{id}', ['Govyx\\Controllers\\OfficialsController', 'delete']);

    // ---- tasks ----
    $router->get('/api/v1/tasks', ['Govyx\\Controllers\\TasksController', 'index']);
    $router->post('/api/v1/tasks', ['Govyx\\Controllers\\TasksController', 'create']);
    $router->get('/api/v1/tasks/{id}', ['Govyx\\Controllers\\TasksController', 'show']);
    $router->put('/api/v1/tasks/{id}', ['Govyx\\Controllers\\TasksController', 'update']);
    $router->post('/api/v1/tasks/{id}/assign', ['Govyx\\Controllers\\TasksController', 'assign']);
    $router->post('/api/v1/tasks/{id}/status', ['Govyx\\Controllers\\TasksController', 'changeStatus']);
    $router->post('/api/v1/tasks/{id}/approve', ['Govyx\\Controllers\\TasksController', 'approve']);
    $router->get('/api/v1/tasks/{task_id}/evidence', ['Govyx\\Controllers\\EvidenceController', 'list']);
    $router->post('/api/v1/tasks/{task_id}/evidence', ['Govyx\\Controllers\\EvidenceController', 'upload']);
    $router->get('/api/v1/tasks/{task_id}/evidence/{id}', ['Govyx\\Controllers\\EvidenceController', 'download']);

    // ---- projects ----
    $router->get('/api/v1/projects', ['Govyx\\Controllers\\ProjectsController', 'index']);
    $router->post('/api/v1/projects', ['Govyx\\Controllers\\ProjectsController', 'create']);
    $router->get('/api/v1/projects/{id}', ['Govyx\\Controllers\\ProjectsController', 'show']);
    $router->put('/api/v1/projects/{id}', ['Govyx\\Controllers\\ProjectsController', 'update']);
    $router->delete('/api/v1/projects/{id}', ['Govyx\\Controllers\\ProjectsController', 'delete']);

    // ---- kpis ----
    $router->get('/api/v1/kpis', ['Govyx\\Controllers\\KpisController', 'index']);
    $router->post('/api/v1/kpis', ['Govyx\\Controllers\\KpisController', 'create']);
    $router->get('/api/v1/kpis/{id}', ['Govyx\\Controllers\\KpisController', 'show']);
    $router->put('/api/v1/kpis/{id}', ['Govyx\\Controllers\\KpisController', 'update']);
    $router->post('/api/v1/kpis/{id}/measurements', ['Govyx\\Controllers\\KpisController', 'measurement']);
    $router->delete('/api/v1/kpis/{id}', ['Govyx\\Controllers\\KpisController', 'delete']);

    // ---- performance / rankings ----
    $router->get('/api/v1/performance', ['Govyx\\Controllers\\PerformanceController', 'index']);
    $router->post('/api/v1/performance/calculate', ['Govyx\\Controllers\\PerformanceController', 'calculate']);
    $router->get('/api/v1/rankings', ['Govyx\\Controllers\\RankingsController', 'index']);

    // ---- alerts ----
    $router->get('/api/v1/alerts', ['Govyx\\Controllers\\AlertsController', 'index']);
    $router->put('/api/v1/alerts/{id}/review', ['Govyx\\Controllers\\AlertsController', 'review']);

    // ---- reports ----
    $router->get('/api/v1/reports', ['Govyx\\Controllers\\ReportsController', 'index']);
    $router->get('/api/v1/reports/{id}', ['Govyx\\Controllers\\ReportsController', 'show']);
    $router->post('/api/v1/reports/generate', ['Govyx\\Controllers\\ReportsController', 'generate']);

    // ---- notifications ----
    $router->get('/api/v1/notifications', ['Govyx\\Controllers\\NotificationsController', 'index']);
    $router->put('/api/v1/notifications/{id}/read', ['Govyx\\Controllers\\NotificationsController', 'markRead']);
    $router->put('/api/v1/notifications/read-all', ['Govyx\\Controllers\\NotificationsController', 'markAllRead']);

    // ---- analytics / audit / rankor ----
    $router->get('/api/v1/analytics/overview', ['Govyx\\Controllers\\AnalyticsController', 'overview']);
    $router->get('/api/v1/audit', ['Govyx\\Controllers\\AuditController', 'index']);
    $router->get('/api/v1/rankor', ['Govyx\\Controllers\\RankorController', 'index']);
    $router->get('/api/v1/rankor/{id}', ['Govyx\\Controllers\\RankorController', 'show']);
    $router->post('/api/v1/rankor/run', ['Govyx\\Controllers\\RankorController', 'run']);

    $router->dispatch();
}

// ---- Web shell ----
if (Auth::check()) {
    renderShell();
} else {
    Govyx\Core\Response::redirect('/login');
}

function renderShell(): never
{
    $user = Auth::user();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(Govyx\Security\Csrf::token(), ENT_QUOTES) ?>">
    <title>GOVYX — AI Governance Brain</title>
    <link rel="stylesheet" href="/css/govyx.css?v=1">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏛️</text></svg>">
</head>
<body>
<div id="app">
    <nav id="sidebar" class="sidebar">
        <div class="brand">
            <div class="brand-mark">G</div>
            <div>
                <div class="brand-name">GOVYX</div>
                <div class="brand-sub">AI Governance Brain · ARWE</div>
            </div>
        </div>
        <div class="user-card" id="user-card">
            <div class="avatar" id="avatar">—</div>
            <div class="user-meta">
                <div class="user-name" id="user-name">Loading…</div>
                <div class="user-role" id="user-role"></div>
            </div>
        </div>
        <div class="nav-group">Modules</div>
        <nav-links id="nav-links"></nav-links>
        <div class="sidebar-footer">
            <div class="nav-link" data-view="logout" id="logout-link">Sign Out</div>
            <div class="version">Rankor 1.0 · C + PHP + JS + MySQL</div>
        </div>
    </nav>
    <main class="main">
        <header class="topbar">
            <button id="menu-toggle" class="menu-toggle" aria-label="Toggle menu">☰</button>
            <h1 id="page-title">Dashboard</h1>
            <div class="topbar-right">
                <button id="run-rankor-btn" class="btn btn-sm btn-rancor" title="Run Rankor analysis">Run Rankor</button>
                <div class="notification-bell" id="notification-bell" title="Notifications">
                    🔔 <span id="notification-count" class="badge hidden">0</span>
                </div>
            </div>
        </header>
        <div id="toast-container"></div>
        <section id="view" class="view"></section>
    </main>
</div>
<div id="notification-panel" class="notification-panel hidden">
    <div class="notification-panel-header">
        <strong>Notifications</strong>
        <button id="notif-read-all" class="btn btn-sm">Mark all read</button>
    </div>
    <div id="notification-list"></div>
</div>
<div id="modal-root"></div>
<script src="/js/api.js?v=1"></script>
<script src="/js/app.js?v=1"></script>
</body>
</html>
    <?php
    exit;
}

function renderLogin(): never
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in — GOVYX</title>
    <link rel="stylesheet" href="/css/govyx.css?v=1">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏛️</text></svg>">
    <meta name="csrf-token" content="<?= htmlspecialchars(Govyx\Security\Csrf::token(), ENT_QUOTES) ?>">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="login-logo">
            <div class="brand-mark">G</div>
            <div>
                <div class="login-title">GOVYX</div>
                <div class="login-sub">AI Governance Brain · Project ARWE</div>
            </div>
        </div>
        <div class="login-error" id="login-error"></div>
        <form id="login-form">
            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" autocomplete="username" required autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn" style="width:100%;justify-content:center" id="login-btn">Sign in</button>
        </form>
        <p class="muted mt" style="font-size:11.5px;text-align:center">Accounts: admin / manager / official / auditor / analyst — password <code>Govyx@2026</code> (demo)</p>
    </div>
</div>
<script src="/js/login.js?v=1"></script>
</body>
</html>
    <?php
    exit;
}