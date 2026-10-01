<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/api.php';

api_require_get();
api_json_response(true, 'Drive Leadership backend is running.', 200);
