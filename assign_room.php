<?php
header('Content-Type: application/json');
require_once __DIR__ . '/connection/db_connect.php';

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : (isset($_GET['user_id']) ? intval($_GET['user_id']) : 0);
$schedule_id = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : (isset($_GET['schedule_id']) ? intval($_GET['schedule_id']) : 0);

if ($user_id <= 0 || $schedule_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Missing user_id or schedule_id']);
    exit;
}

// Validate schedule exists and capacity
$capacity = 0;
if ($stmtCap = $conn->prepare("SELECT capacity FROM ExamSchedules WHERE schedule_id = ? LIMIT 1")) {
    $stmtCap->bind_param('i', $schedule_id);
    $stmtCap->execute();
    $resCap = $stmtCap->get_result();
    $rowCap = $resCap ? $resCap->fetch_assoc() : null;
    if (!$rowCap) {
        echo json_encode(['ok' => false, 'error' => 'Schedule not found']);
        $stmtCap->close();
        exit;
    }
    $capacity = (int)($rowCap['capacity'] ?? 0);
    $stmtCap->close();
}

$booked = 0;
if ($stmtBC = $conn->prepare("SELECT COUNT(*) AS cnt FROM ExamRegistrations WHERE schedule_id = ?")) {
    $stmtBC->bind_param('i', $schedule_id);
    $stmtBC->execute();
    $resBC = $stmtBC->get_result();
    if ($resBC && ($rowBC = $resBC->fetch_assoc())) {
        $booked = (int)$rowBC['cnt'];
    }
    $stmtBC->close();
}

if ($capacity > 0 && $booked >= $capacity) {
    echo json_encode(['ok' => false, 'error' => 'Room is full']);
    exit;
}

// Determine if user already has a registration
$existing_reg_id = null;
$existing_sched_id = null;
if ($stmtFind = $conn->prepare("SELECT registration_id, schedule_id FROM ExamRegistrations WHERE user_id = ? LIMIT 1")) {
    $stmtFind->bind_param('i', $user_id);
    $stmtFind->execute();
    $resFind = $stmtFind->get_result();
    $rowF = $resFind ? $resFind->fetch_assoc() : null;
    if ($rowF) {
        $existing_reg_id = (int)$rowF['registration_id'];
        $existing_sched_id = (int)$rowF['schedule_id'];
    }
    $stmtFind->close();
}

if ($existing_sched_id === $schedule_id) {
    // Already assigned to this room
    echo json_encode(['ok' => true, 'message' => 'User already assigned to this room']);
    exit;
}

$success = false;
if ($existing_reg_id) {
    if ($stmtUpd = $conn->prepare("UPDATE ExamRegistrations SET schedule_id = ? WHERE registration_id = ?")) {
        $stmtUpd->bind_param('ii', $schedule_id, $existing_reg_id);
        $success = $stmtUpd->execute();
        $stmtUpd->close();
    }
} else {
    // Compute next registration_id
    $nextId = 1;
    if ($resMax = $conn->query("SELECT IFNULL(MAX(registration_id), 0) + 1 AS next_id FROM ExamRegistrations")) {
        if ($rowMax = $resMax->fetch_assoc()) {
            $nextId = (int)$rowMax['next_id'];
        }
    }
    if ($stmtIns = $conn->prepare("INSERT INTO ExamRegistrations (registration_id, user_id, schedule_id) VALUES (?, ?, ?)")) {
        $stmtIns->bind_param('iii', $nextId, $user_id, $schedule_id);
        $success = $stmtIns->execute();
        $stmtIns->close();
    }
}

if (!$success) {
    echo json_encode(['ok' => false, 'error' => 'Failed to assign room']);
    exit;
}

// Update schedule status after assignment
$newBooked = 0;
if ($stmtNB = $conn->prepare("SELECT COUNT(*) AS cnt FROM ExamRegistrations WHERE schedule_id = ?")) {
    $stmtNB->bind_param('i', $schedule_id);
    $stmtNB->execute();
    $resNB = $stmtNB->get_result();
    if ($resNB && ($rowNB = $resNB->fetch_assoc())) {
        $newBooked = (int)$rowNB['cnt'];
    }
    $stmtNB->close();
}

$newStatus = ($capacity > 0 && $newBooked >= $capacity) ? 'Full' : 'Open';
if ($stmtStat = $conn->prepare("UPDATE ExamSchedules SET status = ? WHERE schedule_id = ?")) {
    $stmtStat->bind_param('si', $newStatus, $schedule_id);
    $stmtStat->execute();
    $stmtStat->close();
}

echo json_encode(['ok' => true, 'message' => 'Room assigned successfully']);
exit;
?>