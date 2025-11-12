<?php
header('Content-Type: application/json');
require_once __DIR__ . '/connection/db_connect.php';

if (!isset($conn) || !$conn) {
    echo json_encode(['ok' => false, 'error' => 'Database connection failed']);
    exit;
}

// Fetch schedules with booked counts and essential fields
$sql = "SELECT 
            s.schedule_id AS schedule_id,
            s.floor AS floor,
            s.room AS room,
            s.capacity AS capacity,
            s.start_date_and_time AS start_date_and_time,
            s.status AS status,
            IFNULL(r.booked_count, 0) AS booked_count
        FROM ExamSchedules s
        LEFT JOIN (
            SELECT schedule_id, COUNT(*) AS booked_count
            FROM ExamRegistrations
            GROUP BY schedule_id
        ) r ON s.schedule_id = r.schedule_id
        ORDER BY s.start_date_and_time ASC";

$result = $conn->query($sql);
if (!$result) {
    echo json_encode(['ok' => false, 'error' => 'Query failed: ' . $conn->error]);
    exit;
}

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = [
        'schedule_id' => (int)($row['schedule_id'] ?? 0),
        'floor' => (string)($row['floor'] ?? ''),
        'room' => (string)($row['room'] ?? ''),
        'capacity' => (int)($row['capacity'] ?? 0),
        'booked_count' => (int)($row['booked_count'] ?? 0),
        'start_date_and_time' => (string)($row['start_date_and_time'] ?? ''),
        'status' => (string)($row['status'] ?? 'Open')
    ];
}

echo json_encode(['ok' => true, 'schedules' => $rows]);
exit;
?>