<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/api.php';

api_require_get();

try {
    create_database_pdo();
    api_json_response(true, 'Database connection successful.', 200);
} catch (Throwable $exception) {
    error_log('Database diagnostic failed: ' . $exception->getMessage());
    api_json_response(false, 'Database connection unavailable.', 503);
}
