<?php
// Authentication middleware - protect this endpoint
require_once 'middleware/auth.php';
include 'connection/db_connect.php';

header('Content-Type: application/json');

function json_error($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

function json_ok($data = []) {
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$isAjax = isset($_POST['ajax']) || isset($_GET['ajax']);

if (!$isAjax) {
    json_error('Invalid request: ajax flag missing');
}

if ($method === 'POST' && $action === 'add_field') {
    $service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
    $label = trim($_POST['label'] ?? '');
    $field_type = strtolower(trim($_POST['field_type'] ?? ''));
    $is_required = isset($_POST['is_required']) ? (int)$_POST['is_required'] : 1;
    $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
    $allowed_file_types = trim($_POST['allowed_file_types'] ?? '');

    if ($service_id <= 0) json_error('service_id is required');
    if ($label === '') json_error('label is required');
    $allowed_types = ['text','textarea','date','number','email','select','checkbox','radio','file'];
    if (!in_array($field_type, $allowed_types, true)) json_error('Invalid field_type');
    if ($is_required !== 0 && $is_required !== 1) $is_required = 1;

    // If file type, validate allowed_file_types pattern (simple check: only commas and leading dots, no spaces)
    if ($field_type === 'file' && $allowed_file_types !== '') {
        if (preg_match('/\s/', $allowed_file_types)) {
            json_error('allowed_file_types must be comma-separated without spaces, e.g., .pdf,.jpg');
        }
        if (!preg_match('/^(\.[A-Za-z0-9]+)(,\.[A-Za-z0-9]+)*$/', $allowed_file_types)) {
            json_error('allowed_file_types format invalid. Example: .pdf,.jpg,.png');
        }
    } else {
        $allowed_file_types = null; // store as NULL when not applicable
    }

    // If display_order is 0, compute next order
    if ($display_order === 0) {
        $stmtOrder = $conn->prepare('SELECT COALESCE(MAX(display_order), 0) AS max_order FROM services_fields WHERE service_id = ?');
        $stmtOrder->bind_param('i', $service_id);
        if ($stmtOrder->execute()) {
            $resOrder = $stmtOrder->get_result();
            $rowOrder = $resOrder->fetch_assoc();
            $display_order = (int)($rowOrder['max_order'] ?? 0) + 1;
            $resOrder->close();
        }
        $stmtOrder->close();
    }

    // Insert new field
    $sql = 'INSERT INTO services_fields (service_id, label, field_type, is_required, display_order, allowed_file_types) VALUES (?, ?, ?, ?, ?, ?)';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        json_error('Failed to prepare statement: ' . $conn->error, 500);
    }
    // Bind allowed_file_types as nullable
    $stmt->bind_param('issiis', $service_id, $label, $field_type, $is_required, $display_order, $allowed_file_types);
    if (!$stmt->execute()) {
        $stmt->close();
        json_error('Failed to add field: ' . $conn->error, 500);
    }
    $new_id = $stmt->insert_id;
    $stmt->close();

    json_ok(['field_id' => $new_id, 'display_order' => $display_order]);
}

if ($method === 'POST' && $action === 'update_field') {
    $service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
    $field_id = isset($_POST['field_id']) ? (int)$_POST['field_id'] : 0;
    $label = trim($_POST['label'] ?? '');
    $field_type = strtolower(trim($_POST['field_type'] ?? ''));
    $is_required = isset($_POST['is_required']) ? (int)$_POST['is_required'] : 1;
    $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
    $allowed_file_types = trim($_POST['allowed_file_types'] ?? '');

    if ($service_id <= 0) json_error('service_id is required');
    if ($field_id <= 0) json_error('field_id is required');
    if ($label === '') json_error('label is required');
    $allowed_types = ['text','textarea','date','number','email','select','checkbox','radio','file'];
    if (!in_array($field_type, $allowed_types, true)) json_error('Invalid field_type');
    if ($is_required !== 0 && $is_required !== 1) $is_required = 1;

    // Validate field ownership
    $stmtChk = $conn->prepare('SELECT field_id FROM services_fields WHERE field_id = ? AND service_id = ?');
    $stmtChk->bind_param('ii', $field_id, $service_id);
    if (!$stmtChk->execute()) {
        $stmtChk->close();
        json_error('Failed to validate field: ' . $conn->error, 500);
    }
    $resChk = $stmtChk->get_result();
    if (!$resChk || $resChk->num_rows === 0) {
        $stmtChk->close();
        json_error('Field not found for this service', 404);
    }
    $resChk->close();
    $stmtChk->close();

    // If file type, validate allowed_file_types pattern (simple check: only commas and leading dots, no spaces)
    if ($field_type === 'file' && $allowed_file_types !== '') {
        if (preg_match('/\s/', $allowed_file_types)) {
            json_error('allowed_file_types must be comma-separated without spaces, e.g., .pdf,.jpg');
        }
        if (!preg_match('/^(\.[A-Za-z0-9]+)(,\.[A-Za-z0-9]+)*$/', $allowed_file_types)) {
            json_error('allowed_file_types format invalid. Example: .pdf,.jpg,.png');
        }
    } else {
        $allowed_file_types = null; // store as NULL when not applicable
    }

    // If display_order is 0, keep current order; otherwise update
    if ($display_order === 0) {
        // Query current order to return it in response
        $stmtCur = $conn->prepare('SELECT display_order FROM services_fields WHERE field_id = ?');
        $stmtCur->bind_param('i', $field_id);
        if ($stmtCur->execute()) {
            $resCur = $stmtCur->get_result();
            $rowCur = $resCur->fetch_assoc();
            $display_order = (int)($rowCur['display_order'] ?? 0);
            $resCur->close();
        }
        $stmtCur->close();
    }

    $sql = 'UPDATE services_fields SET label = ?, field_type = ?, is_required = ?, display_order = ?, allowed_file_types = ? WHERE field_id = ? AND service_id = ?';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        json_error('Failed to prepare statement: ' . $conn->error, 500);
    }
    $stmt->bind_param('ssiisii', $label, $field_type, $is_required, $display_order, $allowed_file_types, $field_id, $service_id);
    if (!$stmt->execute()) {
        $stmt->close();
        json_error('Failed to update field: ' . $conn->error, 500);
    }
    $stmt->close();

    json_ok(['field_id' => $field_id, 'display_order' => $display_order]);
}

json_error('Unsupported action', 400);