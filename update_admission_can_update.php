<?php
header('Content-Type: application/json');
require_once __DIR__ . '/connection/db_connect.php';

$user_id = 0;
if (isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
} elseif (isset($_GET['user_id'])) {
    $user_id = (int)$_GET['user_id'];
}

if ($user_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Missing or invalid user_id']);
    exit;
}

if (!isset($conn) || !$conn) {
    echo json_encode(['ok' => false, 'error' => 'Database connection not available']);
    exit;
}

$updated = 0;
if ($stmt = $conn->prepare("UPDATE admission_submission SET can_update = 1, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?")) {
    $stmt->bind_param('i', $user_id);
    if ($stmt->execute()) {
        $updated = $stmt->affected_rows;
    }
    $stmt->close();
}

if ($updated <= 0) {
    echo json_encode(['ok' => false, 'error' => 'No record updated (user not found or already allowed).']);
    exit;
}

echo json_encode(['ok' => true, 'user_id' => $user_id, 'can_update' => 1]);
exit;
?>