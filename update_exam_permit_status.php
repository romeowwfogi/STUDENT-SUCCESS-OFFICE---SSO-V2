<?php
header('Content-Type: application/json');
require_once __DIR__ . '/connection/db_connect.php';

$applicant_number = '';
$new_status = '';
if (isset($_POST['applicant_number'])) {
    $applicant_number = trim((string)$_POST['applicant_number']);
} elseif (isset($_GET['applicant_number'])) {
    $applicant_number = trim((string)$_GET['applicant_number']);
}
if (isset($_POST['status'])) {
    $new_status = strtolower(trim((string)$_POST['status']));
} elseif (isset($_GET['status'])) {
    $new_status = strtolower(trim((string)$_GET['status']));
}

if ($applicant_number === '' || $new_status === '') {
    echo json_encode(['ok' => false, 'error' => 'Missing applicant_number or status']);
    exit;
}

// Allow only 'used' or 'pending'
if (!in_array($new_status, ['used', 'pending'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid status value']);
    exit;
}

if (!isset($conn) || !$conn) {
    echo json_encode(['ok' => false, 'error' => 'Database connection not available']);
    exit;
}

// Update the most recent permit record for the applicant_number
$updated = 0;
if ($stmt = $conn->prepare("UPDATE application_permit SET status = ? WHERE applicant_number = ? ORDER BY id DESC LIMIT 1")) {
    $stmt->bind_param('ss', $new_status, $applicant_number);
    if ($stmt->execute()) {
        $updated = $stmt->affected_rows;
    }
    $stmt->close();
}

if ($updated <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Permit not found or not updated']);
    exit;
}

echo json_encode(['ok' => true, 'applicant_number' => $applicant_number, 'status' => $new_status]);
exit;
?>