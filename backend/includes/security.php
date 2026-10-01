<?php
declare(strict_types=1);

require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/../api/lib/email.php';

const SECURITY_RATE_FILE = 'rate-limits.json';
const SECURITY_RESET_FILE = 'password-resets.json';
const SECURITY_AUDIT_FILE = 'audit-log.json';

function security_json_input(): array
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw === false ? '' : $raw, true);
    return is_array($decoded) ? $decoded : [];
}

function security_rate_limit(string $bucket, int $limit, int $windowSeconds): void
{
    $now = time();
    $records = storage_read(SECURITY_RATE_FILE);
    $recent = array_values(array_filter($records, static function ($record) use ($now, $windowSeconds): bool {
        return is_array($record) && (int) ($record['at'] ?? 0) > $now - $windowSeconds;
    }));
    $count = count(array_filter($recent, static fn($record): bool => ($record['bucket'] ?? '') === $bucket));

    if ($count >= $limit) {
        header('Retry-After: ' . $windowSeconds);
        api_json_response(false, 'Too many requests. Please try again later.', 429);
    }

    $recent[] = ['bucket' => $bucket, 'at' => $now];
    storage_write(SECURITY_RATE_FILE, $recent);
}

function security_client_key(string $prefix): string
{
    return $prefix . ':' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function security_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function security_require_csrf(?array $input = null): void
{
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['_csrf'] ?? '');
    if (!is_string($provided) || !hash_equals(security_csrf_token(), $provided)) {
        api_json_response(false, 'Security token expired. Refresh and try again.', 419);
    }
}

function security_password_matches(string $password, array $user): bool
{
    if (isset($user['password_hash']) && is_string($user['password_hash'])) {
        $parts = explode('$', $user['password_hash']);
        if (count($parts) === 4 && $parts[0] === 'pbkdf2_sha256') {
            $iterations = (int) $parts[1];
            $salt = base64_decode($parts[2], true);
            $expected = base64_decode($parts[3], true);
            if ($iterations > 0 && $salt !== false && $expected !== false) {
                $actual = hash_pbkdf2('sha256', $password, $salt, $iterations, strlen($expected), true);
                return hash_equals($expected, $actual);
            }
        }

        return password_verify($password, $user['password_hash']);
    }

    return false;
}

function security_admin_user(string $email, string $password): ?array
{
    $users = storage_read('admin-users.json');
    foreach ($users as $user) {
        if (
            is_array($user)
            && strtolower((string) ($user['email'] ?? '')) === strtolower($email)
            && security_password_matches($password, $user)
        ) {
            return $user;
        }
    }

    return null;
}

function security_require_admin(): array
{
    $admin = $_SESSION['admin'] ?? null;
    if (!is_array($admin) || !isset($admin['email'], $admin['expires_at']) || (int) $admin['expires_at'] < time()) {
        api_json_response(false, 'Unauthorized.', 401);
    }

    return $admin;
}

function security_audit(string $action, string $entityType, int $entityId, array $details = []): void
{
    $admin = $_SESSION['admin'] ?? [];
    storage_append(SECURITY_AUDIT_FILE, [
        'admin_email' => $admin['email'] ?? 'system',
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'details' => $details,
        'created_at' => gmdate('c'),
    ]);
}

function security_notify(string $subject, string $body): void
{
    $recipient = email_admin_recipient();
    if ($recipient === '') {
        error_log('Notification email skipped: admin recipient is not configured.');
        return;
    }

    email_send(
        $recipient,
        $subject,
        '<p>' . nl2br(email_escape($body)) . '</p>',
        $body
    );
}

function security_duplicate_exists(string $filename, string $email, int $windowSeconds): bool
{
    $threshold = time() - $windowSeconds;
    foreach (storage_read($filename) as $record) {
        if (strtolower((string) ($record['email'] ?? '')) !== strtolower($email)) {
            continue;
        }

        $date = strtotime((string) ($record['submitted_at'] ?? $record['date'] ?? ''));
        if ($date !== false && $date >= $threshold) {
            return true;
        }
    }

    return false;
}