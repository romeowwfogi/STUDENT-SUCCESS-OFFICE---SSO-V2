<?php
header('Content-Type: application/json');
require_once __DIR__ . '/connection/db_connect.php';

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if ($user_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid user_id']);
    exit;
}

$schedule_id = null;
// Find the applicant's registered schedule
if ($stmtReg = $conn->prepare("SELECT schedule_id FROM ExamRegistrations WHERE user_id = ? ORDER BY registration_id DESC LIMIT 1")) {
    $stmtReg->bind_param('i', $user_id);
    $stmtReg->execute();
    $resReg = $stmtReg->get_result();
    if ($resReg && ($rowReg = $resReg->fetch_assoc())) {
        $schedule_id = isset($rowReg['schedule_id']) ? intval($rowReg['schedule_id']) : null;
    }
    $stmtReg->close();
}

if (!$schedule_id) {
    echo json_encode(['ok' => true, 'start_date_and_time' => null]);
    exit;
}

$start_dt = null;
if ($stmtSched = $conn->prepare("SELECT start_date_and_time FROM ExamSchedules WHERE schedule_id = ? LIMIT 1")) {
    $stmtSched->bind_param('i', $schedule_id);
    $stmtSched->execute();
    $resSched = $stmtSched->get_result();
    if ($resSched && ($rowSched = $resSched->fetch_assoc())) {
        $start_dt = $rowSched['start_date_and_time'] ?? null;
    }
    $stmtSched->close();
}

echo json_encode(['ok' => true, 'start_date_and_time' => $start_dt]);
exit;