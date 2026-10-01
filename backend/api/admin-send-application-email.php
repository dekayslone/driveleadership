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
$admin = security_require_admin();
$input = security_json_input();
security_require_csrf($input);

$applicationId = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$subject = $input['subject'] ?? null;
$message = $input['message'] ?? null;

if (
    $applicationId === false
    || !is_string($subject)
    || trim($subject) === ''
    || strlen($subject) > 180
    || preg_match('/[\r\n]/', $subject)
    || !is_string($message)
    || trim($message) === ''
    || strlen($message) > 5000
) {
    api_json_response(false, 'Enter a valid subject and message before sending.', 422);
}

security_rate_limit(security_client_key('admin-email'), 60, 3600);

try {
    $pdo = create_database_pdo();
    $statement = $pdo->prepare('SELECT full_name, email FROM attendee_applications WHERE id = ? LIMIT 1');
    $statement->execute([$applicationId]);
    $application = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$application) {
        api_json_response(false, 'The selected application could not be found.', 404);
    }

    $fullName = (string) $application['full_name'];
    $emailSent = email_send(
        (string) $application['email'],
        trim($subject),
        '<p>Dear ' . email_escape($fullName) . ',</p><p>' . nl2br(email_escape(trim($message))) . '</p>',
        "Dear {$fullName},\n\n" . trim($message)
    );
    if (!$emailSent) {
        api_json_response(false, 'The email service is unavailable. Check the email configuration and try again.', 503);
    }

    try {
        security_audit('application_email_sent', 'attendee_application', (int) $applicationId, [
            'recipient' => (string) $application['email'],
            'admin' => (string) $admin['email'],
        ]);
    } catch (Throwable $auditException) {
        error_log('Admin application email audit failed: ' . $auditException->getMessage());
        api_json_response(true, 'Email sent, but the audit entry could not be saved.', 200, ['audit_warning' => true]);
    }
    api_json_response(true, 'Email sent successfully.', 200);
} catch (Throwable $exception) {
    error_log('Admin application email failed: ' . $exception->getMessage());
    api_json_response(false, 'Email could not be sent. Please try again later.', 500);
}
