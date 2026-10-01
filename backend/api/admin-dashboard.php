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

api_require_get();
$admin = security_require_admin();

try {
    $pdo = create_database_pdo();

    $applicationStatement = $pdo->prepare(
        'SELECT id, full_name, organisation, employment_status, employment_status_other,
                email, phone, is_network_member, referral_source, referral_source_other,
                expectations, status, submitted_at, updated_at
         FROM attendee_applications
         ORDER BY submitted_at DESC, id DESC'
    );
    $applicationStatement->execute();
    $applications = array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['full_name'],
            'email' => (string) $row['email'],
            'phone' => (string) $row['phone'],
            'date' => (string) $row['submitted_at'],
            'status' => (string) $row['status'],
            'type' => 'Attendee',
            'full_name' => (string) $row['full_name'],
            'organisation' => (string) $row['organisation'],
            'employment_status' => (string) $row['employment_status'],
            'employment_status_other' => $row['employment_status_other'],
            'is_network_member' => (string) $row['is_network_member'],
            'referral_source' => (string) $row['referral_source'],
            'referral_source_other' => $row['referral_source_other'],
            'expectations' => (string) $row['expectations'],
            'submitted_at' => (string) $row['submitted_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }, $applicationStatement->fetchAll(PDO::FETCH_ASSOC));

    $messageStatement = $pdo->prepare(
        'SELECT id, sender_name, sender_email, interest, message, status, submitted_at, updated_at
         FROM contact_messages
         ORDER BY submitted_at DESC, id DESC'
    );
    $messageStatement->execute();
    $messages = array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'sender' => (string) $row['sender_name'],
            'email' => (string) $row['sender_email'],
            'subject' => (string) $row['interest'],
            'message' => $row['message'],
            'date' => (string) $row['submitted_at'],
            'status' => (string) $row['status'],
            'sender_name' => (string) $row['sender_name'],
            'sender_email' => (string) $row['sender_email'],
            'interest' => (string) $row['interest'],
            'submitted_at' => (string) $row['submitted_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }, $messageStatement->fetchAll(PDO::FETCH_ASSOC));

    api_json_response(true, 'Dashboard data loaded.', 200, [
        'user' => [
            'email' => (string) $admin['email'],
            'name' => (string) ($admin['name'] ?? 'Admin User'),
            'role' => (string) ($admin['role'] ?? 'Super Admin'),
        ],
        'csrf_token' => security_csrf_token(),
        'applications' => $applications,
        'messages' => $messages,
        'stats' => [
            'total' => count($applications),
            'pending' => count(array_filter($applications, static fn(array $item): bool => $item['status'] === 'Pending')),
            'approved' => count(array_filter($applications, static fn(array $item): bool => $item['status'] === 'Approved')),
            'rejected' => count(array_filter($applications, static fn(array $item): bool => $item['status'] === 'Rejected')),
        ],
        'audit_log' => array_slice(array_reverse(storage_read(SECURITY_AUDIT_FILE)), 0, 100),
    ]);
} catch (Throwable $exception) {
    error_log('Admin dashboard read failed: ' . $exception->getMessage());
    api_json_response(false, 'Dashboard data is temporarily unavailable.', 503);
}
