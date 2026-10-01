<?php
declare(strict_types=1);

const API_MAX_BODY_BYTES = 32768;

function api_json_response(bool $success, string $message, int $status, array $extra = []): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    echo json_encode(
        array_merge([
            'success' => $success,
            'message' => $message,
        ], $extra),
        JSON_UNESCAPED_SLASHES
    );
    exit;
}

function api_require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        api_json_response(false, 'Method not allowed.', 405);
    }
}

function api_require_get(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        header('Allow: GET');
        api_json_response(false, 'Method not allowed.', 405);
    }
}

function api_require_body_size(): void
{
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;

    if ($contentLength > API_MAX_BODY_BYTES) {
        api_json_response(false, 'Request is too large.', 413);
    }
}

function api_read_input(): array
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

    if (strpos($contentType, 'application/json') === 0) {
        $rawBody = file_get_contents('php://input');
        $rawBody = trim((string) ($rawBody === false ? '' : $rawBody));

        if ($rawBody === '') {
            api_json_response(false, 'Please check the information you entered.', 400);
        }

        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            error_log('Malformed JSON request: ' . $rawBody);
            api_json_response(false, 'Please check the information you entered.', 400);
        }

        return $decoded;
    }

    if (
        strpos($contentType, 'application/x-www-form-urlencoded') === 0
        || strpos($contentType, 'multipart/form-data') === 0
        || $contentType === ''
    ) {
        return $_POST;
    }

    api_json_response(false, 'Unsupported content type.', 415);
}

function api_string(array $input, string $field): string
{
    $value = $input[$field] ?? '';

    return is_string($value) ? trim($value) : '';
}

function api_validate_text(array $input, string $field, int $maxLength, bool $required = true): ?string
{
    $value = api_string($input, $field);

    if ($required && $value === '') {
        return 'This field is required.';
    }

    if ($value !== '' && strlen($value) > $maxLength) {
        return 'This field is too long.';
    }

    if ($value !== '' && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
        return 'This field contains invalid characters.';
    }

    return null;
}

function api_validate_email(array $input, string $field): ?string
{
    $value = api_string($input, $field);

    if ($value === '') {
        return 'This field is required.';
    }

    if (strlen($value) > 254 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
        return 'Please enter a valid email address.';
    }

    return null;
}

function api_validate_choice(array $input, string $field, array $allowed): ?string
{
    $value = api_string($input, $field);

    if ($value === '') {
        return 'This field is required.';
    }

    if (!in_array($value, $allowed, true)) {
        return 'Please select a valid option.';
    }

    return null;
}

function api_validate_phone(array $input, string $field): ?string
{
    $value = api_string($input, $field);

    if ($value === '') {
        return 'This field is required.';
    }

    if (strlen($value) > 30 || !preg_match('/^[+0-9() .-]+$/', $value)) {
        return 'Please enter a valid phone number.';
    }

    return null;
}

function api_require_other_value(array &$errors, array $input, string $choiceField, string $otherField, string $expectedChoice, int $maxLength): void
{
    if (api_string($input, $choiceField) === $expectedChoice) {
        $error = api_validate_text($input, $otherField, $maxLength);

        if ($error !== null) {
            $errors[$otherField] = $error;
        }
    }
}

function api_reject_unknown_status(array $input): void
{
    foreach (['id', 'status', 'submitted_at', 'updated_at'] as $field) {
        if (array_key_exists($field, $input)) {
            api_json_response(false, 'Invalid request fields.', 400);
        }
    }
}

function api_validation_failed(array $errors): void
{
    if ($errors !== []) {
        api_json_response(false, 'Please correct the highlighted fields.', 422, ['errors' => $errors]);
    }
}

function api_persistence_unavailable(): void
{
    api_json_response(false, 'Submissions are temporarily unavailable. Please try again later.', 503);
}
