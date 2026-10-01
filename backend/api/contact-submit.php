<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/lib/email.php';

api_require_post();
api_require_body_size();
$input = api_read_input();
security_require_csrf($input);
api_reject_unknown_status($input);

$errors = [];

foreach ([
    'sender_name' => 150,
    'message' => 3000,
] as $field => $maxLength) {
    $error = api_validate_text($input, $field, $maxLength, true);
    if ($error !== null) {
        $errors[$field] = $error;
    }
}

$emailError = api_validate_email($input, 'sender_email');
if ($emailError !== null) {
    $errors['sender_email'] = $emailError;
}

$interestError = api_validate_choice($input, 'interest', [
    'Executive coaching',
    'Emerging leaders programme',
    'Corporate workshop',
    'Speaking engagement',
]);
if ($interestError !== null) {
    $errors['interest'] = $interestError;
}

if ($errors !== []) {
    api_json_response(false, 'Please check the information you entered.', 422, ['errors' => $errors]);
}

try {
    $pdo = create_database_pdo();
    $sql = 'INSERT INTO contact_messages (sender_name, sender_email, interest, message) VALUES (?, ?, ?, ?)';
    $statement = $pdo->prepare($sql);
    $statement->execute([
        api_string($input, 'sender_name'),
        api_string($input, 'sender_email'),
        api_string($input, 'interest'),
        api_string($input, 'message'),
    ]);

    $senderName = api_string($input, 'sender_name');
    $senderEmail = api_string($input, 'sender_email');
    $interest = api_string($input, 'interest');
    $message = api_string($input, 'message');
    $adminEmail = email_admin_recipient();
    $emailSent = email_send(
        $adminEmail,
        'New Drive Leadership contact message',
        '<p><strong>Name:</strong> ' . email_escape($senderName) . '</p>'
            . '<p><strong>Email:</strong> ' . email_escape($senderEmail) . '</p>'
            . '<p><strong>Interest:</strong> ' . email_escape($interest) . '</p>'
            . '<p><strong>Message:</strong><br>' . nl2br(email_escape($message)) . '</p>',
        "Name: {$senderName}\nEmail: {$senderEmail}\nInterest: {$interest}\n\nMessage:\n{$message}"
    );

    api_json_response(true, 'Message sent successfully.', 201);
} catch (Throwable $exception) {
    error_log('Contact message insert failed: ' . $exception->getMessage());
    api_json_response(false, 'Please check the information you entered.', 500);
}
