<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/connection/db_connect.php';

if (!$conn) {
    echo json_encode(['ok' => false, 'error' => 'Database connection not available']);
    exit;
}

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if ($user_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Missing user_id']);
    exit;
}

$data = null;
if ($stmt = $conn->prepare("SELECT id, applicant_number, admission_officer, applicant_name, exam_date, exam_time, room_no, exam_venue, application_period_start, application_period_end, application_period_text, accent_color FROM application_permit WHERE user_id = ? ORDER BY id DESC LIMIT 1")) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && ($row = $res->fetch_assoc())) {
        $data = [
            'permit_id' => isset($row['id']) ? intval($row['id']) : null,
            'applicant_number' => trim((string)($row['applicant_number'] ?? '')),
            'admission_officer' => trim((string)($row['admission_officer'] ?? '')),
            'applicant_name' => trim((string)($row['applicant_name'] ?? '')),
            'exam_date' => trim((string)($row['exam_date'] ?? '')),
            'exam_time' => trim((string)($row['exam_time'] ?? '')),
            'room_no' => trim((string)($row['room_no'] ?? '')),
            'exam_venue' => trim((string)($row['exam_venue'] ?? '')),
            'application_period_start' => trim((string)($row['application_period_start'] ?? '')),
            'application_period_end' => trim((string)($row['application_period_end'] ?? '')),
            'application_period_text' => trim((string)($row['application_period_text'] ?? '')),
            'accent_color' => trim((string)($row['accent_color'] ?? '')),
        ];
    }
    $stmt->close();
}

if (!$data || empty($data['applicant_number'])) {
    echo json_encode(['ok' => false, 'error' => 'No existing permit found']);
    exit;
}

echo json_encode(['ok' => true, 'permit' => $data] + $data);
exit;
?>