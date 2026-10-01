<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo 'Method not allowed.';
    exit;
}

try {
    require_once __DIR__ . '/../config/database.php';
    create_database_pdo();
    echo 'Database connection successful.';
} catch (Throwable $exception) {
    $technicalMessage = $exception->getMessage();
    $technicalMessage = preg_replace('/mysql:\S+/i', 'mysql:[redacted]', $technicalMessage) ?? $technicalMessage;
    $technicalMessage = preg_replace('/(password|passwd|pwd|token|secret)\s*[=:]\s*[^\s;,)]+/i', '$1=[redacted]', $technicalMessage) ?? $technicalMessage;
    $technicalMessage = preg_replace("/Access denied for user '[^']*'@'[^']*'/i", 'Access denied for configured database user', $technicalMessage) ?? $technicalMessage;
    $technicalMessage = preg_replace('/(dbname|database|host|user)\s*[=:]\s*[^\s;,]+/i', '$1=[redacted]', $technicalMessage) ?? $technicalMessage;

    error_log(sprintf(
        '[%s] Drive Leadership database test failed: %s; code=%s; message=%s',
        gmdate('c'),
        get_class($exception),
        (string) $exception->getCode(),
        $technicalMessage ?: 'No technical message.'
    ));

    http_response_code(503);
    echo 'Database connection unavailable.';
}
