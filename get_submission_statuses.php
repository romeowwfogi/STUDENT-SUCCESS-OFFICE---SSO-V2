<?php
require_once 'middleware/auth.php';
require_once 'connection/db_connect.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$statuses = [];
$sql = "SELECT name, hex_color, remarks FROM statuses ORDER BY name ASC";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $statuses[] = [
            'name' => $row['name'],
            'hex_color' => $row['hex_color'] ?? null,
            'remarks' => $row['remarks'] ?? null
        ];
    }
}
echo json_encode(['ok' => true, 'statuses' => $statuses]);
exit;
?>