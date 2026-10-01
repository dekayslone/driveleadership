<?php
declare(strict_types=1);

function email_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function email_send(string $recipient, string $subject, string $html, string $text = ''): bool
{
    $apiKey = (string) (getenv('RESEND_API_KEY') ?: '');
    $from = (string) (getenv('DRIVE_FROM_EMAIL') ?: 'The Drive Leadership <thedrive009@gmail.com>');

    if ($apiKey === '' || $from === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $subject)) {
        error_log('Resend email skipped: incomplete configuration or invalid email input.');
        return false;
    }

    if (!function_exists('curl_init')) {
        error_log('Resend email skipped: cURL is unavailable.');
        return false;
    }

    $payload = json_encode([
        'from' => $from,
        'to' => [$recipient],
        'subject' => trim($subject),
        'html' => $html,
        'text' => $text !== '' ? $text : trim(strip_tags($html)),
    ], JSON_UNESCAPED_SLASHES);

    if ($payload === false) {
        error_log('Resend email skipped: payload encoding failed.');
        return false;
    }

    $handle = curl_init('https://api.resend.com/emails');
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $errorNumber = curl_errno($handle);
    curl_close($handle);

    if ($errorNumber !== 0 || $status < 200 || $status >= 300) {
        error_log('Resend email failed; status=' . $status . '; curl_errno=' . $errorNumber);
        return false;
    }

    return true;
}

function email_admin_recipient(): string
{
    return trim((string) (getenv('DRIVE_ADMIN_EMAIL') ?: ''));
}
