<?php
require_once 'middleware/auth.php';
include 'connection/db_connect.php';

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$admin_remarks = isset($_POST['admin_remarks']) ? trim($_POST['admin_remarks']) : '';
$status_id = isset($_POST['status_id']) ? (int)$_POST['status_id'] : 0;
$custom_status_name = isset($_POST['custom_status_name']) ? trim($_POST['custom_status_name']) : '';
$custom_color_hex = isset($_POST['custom_color_hex']) ? trim($_POST['custom_color_hex']) : '';
// Optional: can_update toggle (0/1)
$can_update = null;
if (isset($_POST['can_update'])) {
    $cu = trim($_POST['can_update']);
    if ($cu === '1' || $cu === '0') {
        $can_update = (int)$cu; // 0 or 1
    }
}

if ($request_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request ID']);
    exit;
}

// If custom status name provided, insert into statuses and use new id
if ($status_id <= 0 && $custom_status_name !== '') {
    // Normalize color hex
    if ($custom_color_hex === '' || !preg_match('/^#([0-9A-Fa-f]{6})$/', $custom_color_hex)) {
        $custom_color_hex = '#6C757D'; // default neutral color
    }
    $desc = 'Custom status';
    $stmtAdd = $conn->prepare("INSERT INTO services_request_statuses (status_name, description, color_hex) VALUES (?, ?, ?)");
    if (!$stmtAdd) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to prepare status insert']);
        exit;
    }
    $stmtAdd->bind_param('sss', $custom_status_name, $desc, $custom_color_hex);
    if (!$stmtAdd->execute()) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to insert custom status']);
        $stmtAdd->close();
        exit;
    }
    $status_id = $stmtAdd->insert_id;
    $stmtAdd->close();
}

// Build dynamic update depending on provided fields
$updateSql = "UPDATE services_requests SET admin_remarks = ?";
$types = 's';
$params = [$admin_remarks];
if ($status_id > 0) {
    $updateSql .= ", status_id = ?";
    $types .= 'i';
    $params[] = $status_id;
}
if ($can_update !== null) {
    $updateSql .= ", can_update = ?";
    $types .= 'i';
    $params[] = $can_update;
}
$updateSql .= " WHERE request_id = ?";
$types .= 'i';
$params[] = $request_id;

$stmt = $conn->prepare($updateSql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to prepare update statement']);
    exit;
}

// Bind parameters dynamically
// Build references for bind_param
$refs = [];
foreach ($params as $key => $value) {
    $refs[$key] = &$params[$key];
}
array_unshift($refs, $types);
call_user_func_array([$stmt, 'bind_param'], $refs);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database update failed']);
    $stmt->close();
    exit;
}
$stmt->close();

// Fetch back the updated status and remarks
$sqlFetch = "SELECT sr.request_id, sr.admin_remarks, sr.status_id, srs.status_name, srs.color_hex
             FROM services_requests sr
             JOIN services_request_statuses srs ON srs.status_id = sr.status_id
             WHERE sr.request_id = ? LIMIT 1";
$stmtF = $conn->prepare($sqlFetch);
if (!$stmtF) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to prepare fetch statement']);
    exit;
}
$stmtF->bind_param('i', $request_id);
$stmtF->execute();
$resF = $stmtF->get_result();
$row = $resF && $resF->num_rows ? $resF->fetch_assoc() : null;
$stmtF->close();

if (!$row) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to fetch updated record']);
    exit;
}

echo json_encode([
    'ok' => true,
    'request_id' => (int)$row['request_id'],
    'admin_remarks' => $row['admin_remarks'],
    'status_id' => (int)$row['status_id'],
    'status_name' => $row['status_name'],
    'color_hex' => $row['color_hex']
]);

?>