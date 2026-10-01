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
$requiredText = [
    'full_name' => 150,
    'organisation' => 200,
    'expectations' => 2000,
];

foreach ($requiredText as $field => $maxLength) {
    $error = api_validate_text($input, $field, $maxLength);
    if ($error !== null) {
        $errors[$field] = $error;
    }
}

foreach ([
    'employment_status' => ['Employed', 'Student', 'Entrepreneur', 'Unemployed', 'Other'],
    'is_network_member' => ['Yes', 'No'],
    'referral_source' => ['Social Media', 'Referral', 'Email Invitation', 'Other'],
] as $field => $allowed) {
    $error = api_validate_choice($input, $field, $allowed);
    if ($error !== null) {
        $errors[$field] = $error;
    }
}

foreach ([
    ['employment_status', 'employment_status_other', 'Other', 100],
    ['referral_source', 'referral_source_other', 'Other', 150],
] as $otherField) {
    api_require_other_value($errors, $input, $otherField[0], $otherField[1], $otherField[2], $otherField[3]);
}

foreach ([
    ['employment_status_other', 100],
    ['referral_source_other', 150],
] as $optionalField) {
    if (api_string($input, $optionalField[0]) !== '') {
        $error = api_validate_text($input, $optionalField[0], $optionalField[1], false);
        if ($error !== null) {
            $errors[$optionalField[0]] = $error;
        }
    }
}

$emailError = api_validate_email($input, 'email');
if ($emailError !== null) {
    $errors['email'] = $emailError;
}

$phoneError = api_validate_phone($input, 'phone');
if ($phoneError !== null) {
    $errors['phone'] = $phoneError;
}

if ($errors !== []) {
    api_json_response(false, 'Please check the information you entered.', 422, ['errors' => $errors]);
}

try {
    $pdo = create_database_pdo();
    $sql = 'INSERT INTO attendee_applications (
        full_name,
        organisation,
        employment_status,
        employment_status_other,
        email,
        phone,
        is_network_member,
        referral_source,
        referral_source_other,
        expectations
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    $statement = $pdo->prepare($sql);
    $statement->execute([
        api_string($input, 'full_name'),
        api_string($input, 'organisation'),
        api_string($input, 'employment_status'),
        api_string($input, 'employment_status_other'),
        api_string($input, 'email'),
        api_string($input, 'phone'),
        api_string($input, 'is_network_member'),
        api_string($input, 'referral_source'),
        api_string($input, 'referral_source_other'),
        api_string($input, 'expectations'),
    ]);

    $fullName = api_string($input, 'full_name');
    $email = api_string($input, 'email');
    $safeName = email_escape($fullName);
    $emailSent = email_send(
        $email,
        'We received your Drive Leadership application',
        '<p>Dear ' . $safeName . ',</p><p>We have received your Drive Leadership application and it is now being reviewed.</p><p>We will contact you with further information.</p>',
        "Dear {$fullName},\n\nWe have received your Drive Leadership application and it is now being reviewed.\n\nWe will contact you with further information."
    );

    api_json_response(true, 'Application submitted successfully.', 201);
} catch (Throwable $exception) {
    error_log('Attendee application insert failed: ' . $exception->getMessage());
    api_json_response(false, 'Please check the information you entered.', 500);
}
