<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/security.php';

api_require_post();
api_require_body_size();
security_rate_limit(security_client_key('password-reset-complete'), 5, 900);
$data = security_json_input();
security_require_csrf($data);

$token = trim((string) ($data['token'] ?? ''));
$password = (string) ($data['password'] ?? '');
if (strlen($password) < 12) {
    api_json_response(false, 'Password must be at least 12 characters.', 422);
}

$resets = storage_read(SECURITY_RESET_FILE);
$now = time();
$match = null;
foreach ($resets as $index => $reset) {
    if (
        isset($reset['token_hash'], $reset['expires_at'])
        && hash_equals((string) $reset['token_hash'], hash('sha256', $token))
        && (int) $reset['expires_at'] >= $now
    ) {
        $match = ['index' => $index, 'email' => $reset['email']];
        break;
    }
}

if ($match === null) {
    api_json_response(false, 'This reset link is invalid or expired.', 400);
}

try {
    $pdo = create_database_pdo();
    $statement = $pdo->prepare('UPDATE admins SET password_hash = ? WHERE email = ? AND is_active = 1');
    $statement->execute([password_hash($password, PASSWORD_DEFAULT), $match['email']]);
    if ($statement->rowCount() < 1) {
        api_json_response(false, 'This reset link is invalid or expired.', 400);
    }
} catch (Throwable $exception) {
    error_log('Admin password reset failed: ' . $exception->getMessage());
    api_json_response(false, 'Password reset is temporarily unavailable.', 503);
}

$resets = array_values(array_filter($resets, static fn($reset, $index): bool => $index !== $match['index'], ARRAY_FILTER_USE_BOTH));
storage_write(SECURITY_RESET_FILE, $resets);
security_audit('password_reset', 'admin', 0, ['email' => $match['email']]);

api_json_response(true, 'Password reset successfully.', 200);
