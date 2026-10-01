<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/security.php';

api_require_post();
api_require_body_size();
security_rate_limit(security_client_key('password-reset'), 3, 900);
$data = security_json_input();
security_require_csrf($data);
$email = strtolower(trim((string) ($data['email'] ?? '')));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    api_json_response(false, 'Enter a valid email address.', 422);
}

try {
    $pdo = create_database_pdo();
    $statement = $pdo->prepare('SELECT email FROM admins WHERE email = ? AND is_active = 1 LIMIT 1');
    $statement->execute([$email]);
    $admin = $statement->fetch(PDO::FETCH_ASSOC);

    if ($admin) {

    $plainToken = bin2hex(random_bytes(32));
    $resets = storage_read(SECURITY_RESET_FILE);
    $resets[] = [
        'email' => $email,
        'token_hash' => hash('sha256', $plainToken),
        'expires_at' => time() + 900,
    ];
    storage_write(SECURITY_RESET_FILE, $resets);

    $baseUrl = rtrim((string) (getenv('DRIVE_ADMIN_RESET_URL') ?: '/admin/reset-password.html'), '/');
    $resetUrl = $baseUrl . '?token=' . rawurlencode($plainToken);
    security_notify('Admin password reset request', "Use this link to reset the admin password:\n\n" . $resetUrl);
    }
} catch (Throwable $exception) {
    error_log('Admin password reset request failed: ' . $exception->getMessage());
}

api_json_response(true, 'If that email belongs to an admin, a reset link has been sent.', 200);
