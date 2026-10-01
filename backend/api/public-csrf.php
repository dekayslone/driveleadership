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
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/security.php';

api_require_get();
api_json_response(true, 'Security token issued.', 200, ['csrf_token' => security_csrf_token()]);
