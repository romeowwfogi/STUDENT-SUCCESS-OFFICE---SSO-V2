<?php
header('Content-Type: application/json');
require_once __DIR__ . '/connection/db_connect.php';

$nextId = 1;
$error = null;

if (isset($conn) && $conn) {
    try {
        $sql = "SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM application_permit";
        if ($result = $conn->query($sql)) {
            if ($row = $result->fetch_assoc()) {
                $val = isset($row['next_id']) ? (int)$row['next_id'] : 1;
                $nextId = $val > 0 ? $val : 1;
            }
            $result->close();
        } else {
            $error = 'Query failed';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
} else {
    $error = 'DB connection not available';
}

echo json_encode([
    'ok' => $error === null,
    'next_id' => $nextId,
    'error' => $error,
]);
?>