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

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/security.php';

api_require_post();
api_require_body_size();
$data = security_json_input();
security_require_csrf($data);

$email = strtolower(trim((string) ($data['email'] ?? '')));
$password = (string) ($data['password'] ?? '');

if ($email === '' || $password === '') {
    api_json_response(false, 'Invalid email or password.', 401);
}

try {
    $pdo = create_database_pdo();
    $statement = $pdo->prepare('SELECT id, name, email, password_hash, is_active FROM admins WHERE email = ? LIMIT 1');
    $statement->execute([$email]);
    $admin = $statement->fetch(PDO::FETCH_ASSOC);

    if (
        !$admin
        || !((int) ($admin['is_active'] ?? 0) === 1)
        || !security_password_matches($password, ['password_hash' => (string) ($admin['password_hash'] ?? '')])
    ) {
        api_json_response(false, 'Invalid email or password.', 401);
    }

    session_regenerate_id(true);
    $_SESSION['admin'] = [
        'id' => (int) $admin['id'],
        'email' => (string) $admin['email'],
        'name' => (string) ($admin['name'] ?? 'Admin User'),
        'role' => 'Super Admin',
        'expires_at' => time() + (60 * 60 * 8),
    ];

    api_json_response(true, 'Login successful.', 200, [
        'csrf_token' => security_csrf_token(),
        'user' => [
            'email' => (string) $admin['email'],
            'name' => (string) ($admin['name'] ?? 'Admin User'),
            'role' => 'Super Admin',
        ],
    ]);
} catch (Throwable $exception) {
    error_log('Admin login failed: ' . $exception->getMessage());
    api_json_response(false, 'Invalid email or password.', 401);
}
