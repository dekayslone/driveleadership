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
require_once __DIR__ . '/lib/email.php';

api_require_post();
api_require_body_size();
security_require_admin();
$input = security_json_input();
security_require_csrf($input);

$applicationId = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$status = $input['status'] ?? null;
$allowedStatuses = ['Pending', 'Approved', 'Rejected'];

if ($applicationId === false || !is_string($status) || !in_array($status, $allowedStatuses, true)) {
    api_json_response(false, 'Unable to update application status.', 422);
}

try {
    $pdo = create_database_pdo();
    $currentStatement = $pdo->prepare(
        'SELECT full_name, email, status FROM attendee_applications WHERE id = ? LIMIT 1'
    );
    $currentStatement->execute([$applicationId]);
    $current = $currentStatement->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        api_json_response(false, 'Unable to update application status.', 404);
    }

    $statement = $pdo->prepare(
        'UPDATE attendee_applications
         SET status = ?
         WHERE id = ?'
    );
    $statement->execute([$status, $applicationId]);

    $emailFailed = false;
    if ($current['status'] !== $status && in_array($status, ['Approved', 'Rejected'], true)) {
        $fullName = (string) $current['full_name'];
        $recipient = (string) $current['email'];
        $safeName = email_escape($fullName);
        $approved = $status === 'Approved';
        $subject = $approved
            ? 'Your Drive Leadership application has been approved'
            : 'Update on your Drive Leadership application';
        $html = $approved
            ? '<p>Dear ' . $safeName . ',</p><p>Your Drive Leadership application has been approved.</p><p>We will contact you with further information.</p>'
            : '<p>Dear ' . $safeName . ',</p><p>Thank you for your interest in Drive Leadership. We are unable to approve your application at this time.</p><p>We appreciate your interest and wish you the best.</p>';
        $text = $approved
            ? "Dear {$fullName},\n\nYour Drive Leadership application has been approved.\n\nWe will contact you with further information."
            : "Dear {$fullName},\n\nThank you for your interest in Drive Leadership. We are unable to approve your application at this time.\n\nWe appreciate your interest and wish you the best.";
        $emailFailed = !email_send($recipient, $subject, $html, $text);
    }

    api_json_response(
        true,
        $emailFailed
            ? 'Application status updated, but the notification email could not be sent.'
            : 'Application status updated successfully.',
        200,
        $emailFailed ? ['email_failed' => true] : []
    );
} catch (Throwable $exception) {
    error_log('Admin application status update failed: ' . $exception->getMessage());
    api_json_response(false, 'Unable to update application status.', 500);
}
