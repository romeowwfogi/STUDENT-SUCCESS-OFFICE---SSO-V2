<?php
require_once 'middleware/auth.php';
include 'connection/db_connect.php';

header('Content-Type: application/json');

// Allow GET only
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$sql = "SELECT status_id, status_name, description, color_hex FROM services_request_statuses ORDER BY status_id ASC";
$res = $conn->query($sql);
if (!$res) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database query failed']);
    exit;
}

$statuses = [];
while ($row = $res->fetch_assoc()) {
    $statuses[] = [
        'status_id' => (int)$row['status_id'],
        'status_name' => $row['status_name'],
        'description' => isset($row['description']) ? $row['description'] : '',
        'color_hex' => $row['color_hex']
    ];
}

echo json_encode(['ok' => true, 'statuses' => $statuses]);
exit;
?>