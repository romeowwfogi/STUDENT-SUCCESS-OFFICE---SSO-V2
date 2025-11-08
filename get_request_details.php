<?php
require_once 'middleware/auth.php';
include 'connection/db_connect.php';

header('Content-Type: application/json');

$request_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;
if ($request_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid request_id']);
    exit;
}

// Fetch requestor core details
$sql = "SELECT sr.request_id, sr.admin_remarks, sr.requested_at, sr.status_id, srs.status_name, srs.color_hex,
                su.first_name, su.middle_name, su.last_name, su.suffix, su.email,
                sl.name AS service_name, sr.can_update
         FROM services_requests sr
         JOIN services_request_statuses srs ON srs.status_id = sr.status_id
         LEFT JOIN services_users su ON su.id = sr.user_id
         LEFT JOIN services_list sl ON sl.service_id = sr.service_id
         WHERE sr.request_id = ?
         LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => 'Database error preparing statement']);
    exit;
}
$stmt->bind_param('i', $request_id);
$stmt->execute();
$res = $stmt->get_result();
$core = $res && $res->num_rows ? $res->fetch_assoc() : null;
$stmt->close();

if (!$core) {
    echo json_encode(['ok' => false, 'error' => 'Request not found']);
    exit;
}

// Build full name
$parts = [];
if (!empty($core['first_name'])) $parts[] = $core['first_name'];
if (!empty($core['middle_name'])) $parts[] = $core['middle_name'];
if (!empty($core['last_name'])) $parts[] = $core['last_name'];
$full_name = trim(implode(' ', $parts));
if (!empty($core['suffix'])) $full_name = trim($full_name . ' ' . $core['suffix']);

// Fetch answers
$answers = [];
$sqlA = "SELECT sf.label, sf.field_type, sra.answer_value
         FROM services_answers sra
         JOIN services_fields sf ON sf.field_id = sra.field_id
         WHERE sra.request_id = ?
         ORDER BY sf.display_order ASC, sf.label ASC";
$stmtA = $conn->prepare($sqlA);
if ($stmtA) {
    $stmtA->bind_param('i', $request_id);
    $stmtA->execute();
    $resA = $stmtA->get_result();
    while ($row = $resA->fetch_assoc()) {
        $answers[] = [
            'label' => $row['label'],
            'field_type' => $row['field_type'],
            'answer_value' => $row['answer_value']
        ];
    }
    $stmtA->close();
}

$payload = [
    'ok' => true,
    'request_id' => (int)$core['request_id'],
    'full_name' => $full_name !== '' ? $full_name : null,
    'email' => $core['email'] ?? null,
    'status_id' => isset($core['status_id']) ? (int)$core['status_id'] : null,
    'status_name' => $core['status_name'] ?? null,
    'color_hex' => $core['color_hex'] ?? '#999999',
    'admin_remarks' => $core['admin_remarks'] ?? null,
    'requested_at' => $core['requested_at'] ?? null,
    'service_name' => $core['service_name'] ?? null,
    'can_update' => isset($core['can_update']) ? (int)$core['can_update'] : 0,
    'answers' => $answers
];

echo json_encode($payload);
exit;
?>