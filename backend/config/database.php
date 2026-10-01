<?php
declare(strict_types=1);

function database_config(): array
{
    $config = [
        'host' => (string) (getenv('DRIVE_DB_HOST') ?: ''),
        'name' => (string) (getenv('DRIVE_DB_NAME') ?: ''),
        'user' => (string) (getenv('DRIVE_DB_USER') ?: ''),
        'password' => (string) (getenv('DRIVE_DB_PASSWORD') ?: ''),
        'charset' => (string) (getenv('DRIVE_DB_CHARSET') ?: 'utf8mb4'),
    ];

    $localConfigFile = __DIR__ . '/database.local.php';
    if (is_file($localConfigFile)) {
        $localConfig = require $localConfigFile;
        if (is_array($localConfig)) {
            foreach (array_intersect_key($localConfig, $config) as $key => $value) {
                if ($config[$key] === '' && is_string($value)) {
                    $config[$key] = $value;
                }
            }
        }
    }

    return $config;
}

function create_database_pdo(): PDO
{
    if (!class_exists('PDO')) {
        throw new RuntimeException('PDO is not available.');
    }

    if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('PDO MySQL driver is not available.');
    }

    $config = database_config();

    foreach (['host', 'name', 'user', 'password'] as $requiredKey) {
        if ($config[$requiredKey] === '') {
            throw new RuntimeException('Database configuration is incomplete.');
        }
    }

    if (!in_array($config['charset'], ['utf8mb4', 'utf8'], true)) {
        throw new RuntimeException('Database character set is not allowed.');
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['name'],
        $config['charset']
    );

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
        $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES ' . $config['charset'];
    }

    return new PDO(
        $dsn,
        $config['user'],
        $config['password'],
        $options
    );
}
