<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/../includes/security.php';

api_require_post();
api_require_body_size();
security_rate_limit(security_client_key('volunteer-submit'), 5, 900);
$input = api_read_input();
security_require_csrf($input);
api_reject_unknown_status($input);

$errors = [];

foreach ([
    'full_name' => 150,
    'relevant_experience' => 3000,
    'availability' => 150,
    'preferred_role' => 150,
    'additional_information' => 2000,
] as $field => $maxLength) {
    $error = api_validate_text($input, $field, $maxLength, $field === 'full_name');

    if ($error !== null) {
        $errors[$field] = $error;
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

api_validation_failed($errors);

if (security_duplicate_exists('applications.json', api_string($input, 'email'), 86400)) {
    api_json_response(false, 'An application from this email was already received recently.', 409);
}

try {
    $id = storage_append('applications.json', [
        'name' => api_string($input, 'full_name'),
        'email' => api_string($input, 'email'),
        'phone' => api_string($input, 'phone'),
        'relevant_experience' => api_string($input, 'relevant_experience'),
        'availability' => api_string($input, 'availability'),
        'preferred_role' => api_string($input, 'preferred_role'),
        'additional_information' => api_string($input, 'additional_information'),
        'date' => gmdate('Y-m-d'),
        'submitted_at' => gmdate('c'),
        'status' => 'Pending',
        'type' => 'Volunteer',
    ]);
    security_notify('New volunteer application', api_string($input, 'full_name') . ' submitted a volunteer application.');
    api_json_response(true, 'Your volunteer application was submitted successfully.', 201, ['id' => $id]);
} catch (Throwable $exception) {
    api_json_response(false, 'Your application could not be saved. Please try again later.', 500);
}
