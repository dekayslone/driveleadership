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

api_require_post();
$admin = security_require_admin();

$maximumBytes = 2 * 1024 * 1024;
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $maximumBytes) {
    api_json_response(false, 'The import is too large. Split it into smaller files and try again.', 413);
}

$rawBody = file_get_contents('php://input', false, null, 0, $maximumBytes + 1);
if ($rawBody === false || strlen($rawBody) > $maximumBytes) {
    api_json_response(false, 'The import is too large or could not be read.', 413);
}

$input = json_decode($rawBody, true);
if (!is_array($input) || json_last_error() !== JSON_ERROR_NONE) {
    api_json_response(false, 'The import request is invalid.', 400);
}
security_require_csrf($input);

$applications = $input['applications'] ?? null;
if (!is_array($applications) || $applications === [] || count($applications) > 300) {
    api_json_response(false, 'Import between 1 and 300 applications at a time.', 422);
}
$applications = array_values($applications);

$allowedEmployment = ['Employed', 'Student', 'Entrepreneur', 'Unemployed', 'Other'];
$allowedMembership = ['Yes', 'No'];
$allowedReferral = ['Social Media', 'Referral', 'Email Invitation', 'Other'];
$validatedApplications = [];
$rowErrors = [];

foreach ($applications as $index => $application) {
    $rowNumber = (int) $index + 1;
    if (!is_array($application)) {
        $rowErrors[] = ['row' => $rowNumber, 'message' => 'The row is not a valid response.'];
        continue;
    }

    $record = [];
    foreach ([
        'full_name' => 150,
        'organisation' => 200,
        'employment_status' => 20,
        'employment_status_other' => 100,
        'email' => 254,
        'phone' => 30,
        'is_network_member' => 3,
        'referral_source' => 30,
        'referral_source_other' => 150,
        'expectations' => 2000,
    ] as $field => $maximumLength) {
        $value = $application[$field] ?? '';
        if (!is_string($value)) {
            $rowErrors[] = ['row' => $rowNumber, 'message' => 'The ' . str_replace('_', ' ', $field) . ' value is invalid.'];
            continue 2;
        }
        $record[$field] = trim($value);
        if (strlen($record[$field]) > $maximumLength || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $record[$field])) {
            $rowErrors[] = ['row' => $rowNumber, 'message' => 'The ' . str_replace('_', ' ', $field) . ' value is too long or contains invalid characters.'];
            continue 2;
        }
    }

    $email = strtolower($record['email']);
    if (
        $record['full_name'] === ''
        || $record['organisation'] === ''
        || $record['expectations'] === ''
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || !preg_match('/^[+0-9() .-]{1,30}$/', $record['phone'])
        || !in_array($record['employment_status'], $allowedEmployment, true)
        || !in_array($record['is_network_member'], $allowedMembership, true)
        || !in_array($record['referral_source'], $allowedReferral, true)
        || ($record['employment_status'] === 'Other' && $record['employment_status_other'] === '')
        || ($record['referral_source'] === 'Other' && $record['referral_source_other'] === '')
    ) {
        $rowErrors[] = ['row' => $rowNumber, 'message' => 'Required values or response choices are invalid.'];
        continue;
    }
    $record['email'] = $email;

    $submittedAt = $application['submitted_at'] ?? '';
    if (!is_string($submittedAt) || strlen($submittedAt) > 80) {
        $rowErrors[] = ['row' => $rowNumber, 'message' => 'The submission date is invalid.'];
        continue;
    }
    $record['submitted_at'] = null;
    if (trim($submittedAt) !== '') {
        try {
            $date = new DateTimeImmutable($submittedAt);
            $record['submitted_at'] = $date->format('Y-m-d H:i:s');
        } catch (Throwable $exception) {
            $rowErrors[] = ['row' => $rowNumber, 'message' => 'The submission date is invalid.'];
            continue;
        }
    }
    $validatedApplications[] = $record;
}

if ($rowErrors !== []) {
    api_json_response(false, 'Some responses failed validation. Correct the file and preview it again.', 422, ['row_errors' => $rowErrors]);
}

try {
    $pdo = create_database_pdo();
    $pdo->beginTransaction();
    $existingEmail = $pdo->prepare('SELECT id FROM attendee_applications WHERE LOWER(email) = LOWER(?) LIMIT 1');
    $insert = $pdo->prepare(
        'INSERT INTO attendee_applications (
            full_name, organisation, employment_status, employment_status_other,
            email, phone, is_network_member, referral_source, referral_source_other,
            expectations, submitted_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, COALESCE(?, CURRENT_TIMESTAMP))'
    );
    $seenEmails = [];
    $imported = 0;
    $duplicates = 0;

    foreach ($validatedApplications as $record) {
        if (isset($seenEmails[$record['email']])) {
            $duplicates++;
            continue;
        }
        $seenEmails[$record['email']] = true;
        $existingEmail->execute([$record['email']]);
        if ($existingEmail->fetchColumn() !== false) {
            $duplicates++;
            continue;
        }
        $insert->execute([
            $record['full_name'],
            $record['organisation'],
            $record['employment_status'],
            $record['employment_status_other'] !== '' ? $record['employment_status_other'] : null,
            $record['email'],
            $record['phone'],
            $record['is_network_member'],
            $record['referral_source'],
            $record['referral_source_other'] !== '' ? $record['referral_source_other'] : null,
            $record['expectations'],
            $record['submitted_at'],
        ]);
        $imported++;
    }

    if ($imported > 0) {
        security_audit('applications_imported', 'attendee_applications', 0, [
            'imported' => $imported,
            'duplicates' => $duplicates,
            'admin' => (string) $admin['email'],
        ]);
    }
    $pdo->commit();

    api_json_response(true, 'Application responses imported.', 200, [
        'imported' => $imported,
        'duplicates' => $duplicates,
    ]);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Admin application import failed: ' . $exception->getMessage());
    api_json_response(false, 'Applications could not be imported. No responses were saved.', 500);
}
