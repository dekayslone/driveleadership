<?php
declare(strict_types=1);

function storage_read(string $filename): array
{
    $path = __DIR__ . '/../data/' . $filename;
    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function storage_append(string $filename, array $record): int
{
    $path = __DIR__ . '/../data/' . $filename;
    $records = storage_read($filename);
    $highestId = 0;

    foreach ($records as $existing) {
        $highestId = max($highestId, (int) ($existing['id'] ?? 0));
    }

    $record['id'] = $highestId + 1;
    $records[] = $record;

    $written = file_put_contents(
        $path,
        json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        LOCK_EX
    );

    if ($written === false) {
        throw new RuntimeException('Unable to save submission.');
    }

    return $record['id'];
}

function storage_write(string $filename, array $records): void
{
    $path = __DIR__ . '/../data/' . $filename;
    $written = file_put_contents(
        $path,
        json_encode(array_values($records), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        LOCK_EX
    );

    if ($written === false) {
        throw new RuntimeException('Unable to write storage.');
    }
}
