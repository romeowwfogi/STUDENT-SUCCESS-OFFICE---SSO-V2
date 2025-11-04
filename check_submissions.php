<?php
// Authentication middleware - protect this page
require_once 'middleware/auth.php';
include 'connection/db_connect.php';

$result = $conn->query('SELECT COUNT(*) as count FROM submissions');
$row = $result->fetch_assoc();
echo 'Total submissions: ' . $row['count'] . PHP_EOL;

$result = $conn->query('SELECT id, status FROM submissions LIMIT 5');
echo "Sample submissions:" . PHP_EOL;
while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . ", Status: " . $row['status'] . PHP_EOL;
}

$conn->close();
?>