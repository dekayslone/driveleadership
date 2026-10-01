<?php
declare(strict_types=1);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/security.php';

api_require_get();

$localBypass = security_local_admin_bypass();
$admin = $localBypass
    ? [
        'email' => 'local-admin@localhost',
        'name' => 'Local Admin',
        'role' => 'Local Development',
        'expires_at' => time() + 3600,
    ]
    : ($_SESSION['admin'] ?? null);
$authenticated = $localBypass || (
    is_array($admin)
    && isset($admin['email'], $admin['expires_at'])
    && (int) $admin['expires_at'] >= time()
);

if (!$authenticated) {
    api_json_response(false, 'Not authenticated.', 401);
}

api_json_response(true, 'Authenticated.', 200, [
    'authenticated' => true,
    'user' => [
        'email' => (string) $admin['email'],
        'name' => (string) ($admin['name'] ?? 'Admin User'),
        'role' => (string) ($admin['role'] ?? 'Super Admin'),
    ],
    'local_bypass' => $localBypass,
]);
