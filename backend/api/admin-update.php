<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/security.php';

api_require_post();
api_require_body_size();
$admin = security_require_admin();
$data = security_json_input();
security_require_csrf($data);

$entity = (string) ($data['entity'] ?? '');
$ids = $data['ids'] ?? [];
$status = (string) ($data['status'] ?? '');
$allowedStatuses = $entity === 'messages' ? ['Read', 'Unread', 'Replied'] : ['Pending', 'Approved', 'Rejected'];

if (!in_array($entity, ['applications', 'messages'], true) || !is_array($ids) || $ids === [] || !in_array($status, $allowedStatuses, true)) {
    api_json_response(false, 'Invalid status update request.', 422);
}

$filename = $entity === 'messages' ? 'messages.json' : 'applications.json';
$records = storage_read($filename);
$updated = [];

foreach ($records as &$record) {
    if (in_array((int) ($record['id'] ?? 0), array_map('intval', $ids), true)) {
        $oldStatus = (string) ($record['status'] ?? '');
        $record['status'] = $status;
        $record['updated_at'] = gmdate('c');
        $updated[] = (int) $record['id'];
        security_audit('status_changed', $entity, (int) $record['id'], [
            'from' => $oldStatus,
            'to' => $status,
            'admin' => $admin['email'],
        ]);

        if ($entity === 'applications' && $oldStatus !== $status) {
            security_notify('Application status changed', sprintf('%s is now %s.', $record['name'] ?? 'An application', $status));
        }
    }
}
unset($record);

storage_write($filename, $records);
api_json_response(true, 'Status updated successfully.', 200, ['updated_ids' => $updated, $entity => $records]);
