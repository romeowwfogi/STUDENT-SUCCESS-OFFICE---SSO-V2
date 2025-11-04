<?php
// Authentication middleware - protect this endpoint
require_once 'middleware/auth.php';
require_once 'connection/db_connect.php';
require_once 'function/decrypt.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['ok' => false, 'error' => 'Missing submission id']);
    exit;
}
$submission_id = (int)$_GET['id'];

$response = ['ok' => true];

// Fetch main submission info
$main_info = null;
if ($stmt = $conn->prepare("SELECT s.*, at.name AS applicant_type, c.cycle_name, u.email
                             FROM submissions s
                             LEFT JOIN applicant_types at ON s.applicant_type_id = at.id
                             LEFT JOIN admission_cycles c ON at.admission_cycle_id = c.id
                             LEFT JOIN users u ON s.user_id = u.id
                             WHERE s.id = ?")) {
    $stmt->bind_param('i', $submission_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $main_info = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}

if (!$main_info) {
    echo json_encode(['ok' => false, 'error' => 'Submission not found']);
    exit;
}

// Decrypt email if possible
$display_email = null;
if (!empty($main_info['email'])) {
    $decrypted = decryptData($main_info['email']);
    $display_email = ($decrypted !== false && !empty($decrypted)) ? $decrypted : $main_info['email'];
}

// Build status color map
$status_color_map = [];
if ($resStatus = $conn->query("SELECT name, hex_color FROM statuses")) {
    while ($row = $resStatus->fetch_assoc()) {
        $status_color_map[$row['name']] = $row['hex_color'];
    }
}
$status_name = $main_info['status'] ?? 'Pending';
$status_hex = $status_color_map[$status_name] ?? null;

// Fetch text answers
$text_data = [];
$applicant_type_id = (int)($main_info['applicant_type_id'] ?? 0);
if ($applicant_type_id > 0) {
    if ($stmt2 = $conn->prepare("SELECT sd.field_value, ff.label, sd.field_name
                                 FROM submission_data sd
                                 LEFT JOIN form_fields ff ON sd.field_name = ff.name
                                 LEFT JOIN form_steps fs ON ff.step_id = fs.id
                                 WHERE sd.submission_id = ? AND fs.applicant_type_id = ?
                                 ORDER BY fs.step_order, ff.field_order")) {
        $stmt2->bind_param('ii', $submission_id, $applicant_type_id);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        if ($res2) {
            while ($row = $res2->fetch_assoc()) {
                $text_data[] = [
                    'label' => $row['label'] ?? $row['field_name'],
                    'value' => $row['field_value']
                ];
            }
        }
        $stmt2->close();
    }
}

// Fetch files
$files = [];
if ($applicant_type_id > 0) {
    if ($stmt3 = $conn->prepare("SELECT sf.original_filename, sf.file_path, ff.label, sf.field_name
                                 FROM submission_files sf
                                 LEFT JOIN form_fields ff ON sf.field_name = ff.name
                                 LEFT JOIN form_steps fs ON ff.step_id = fs.id
                                 WHERE sf.submission_id = ? AND fs.applicant_type_id = ?
                                 ORDER BY fs.step_order, ff.field_order")) {
        $stmt3->bind_param('ii', $submission_id, $applicant_type_id);
        $stmt3->execute();
        $res3 = $stmt3->get_result();
        if ($res3) {
            while ($row = $res3->fetch_assoc()) {
                $files[] = [
                    'label' => $row['label'] ?? $row['field_name'],
                    'original_filename' => $row['original_filename'],
                    'file_path' => $row['file_path']
                ];
            }
        }
        $stmt3->close();
    }
}

$response['main'] = [
    'submission_id' => $submission_id,
    'applicant_type' => $main_info['applicant_type'] ?? null,
    'cycle_name' => $main_info['cycle_name'] ?? null,
    'email' => $display_email ?? null,
    'submitted_at' => $main_info['submitted_at'] ?? null,
    'status' => $status_name,
    'status_hex' => $status_hex
];
$response['text_data'] = $text_data;
$response['files'] = $files;

// Fetch schedule information for this applicant (if assigned)
$schedule = null;
$user_id = (int)($main_info['user_id'] ?? 0);
if ($user_id > 0) {
    if ($stmtSched = $conn->prepare("SELECT er.schedule_id, es.floor, es.room, es.start_date_and_time
                                     FROM ExamRegistrations er
                                     LEFT JOIN ExamSchedules es ON er.schedule_id = es.schedule_id
                                     WHERE er.user_id = ? LIMIT 1")) {
        $stmtSched->bind_param('i', $user_id);
        $stmtSched->execute();
        $resSched = $stmtSched->get_result();
        $rowSched = $resSched ? $resSched->fetch_assoc() : null;
        if ($rowSched) {
            $schedule = $rowSched;
            // Derive friendly date/time/venue
            $exam_date = '';
            $exam_time = '';
            $exam_venue = '';
            $dt = $rowSched['start_date_and_time'] ?? '';
            if (!empty($dt)) {
                $ts = strtotime($dt);
                if ($ts !== false) {
                    $exam_date = date('F j, Y', $ts);
                    $exam_time = date('g:i A', $ts);
                    $exam_time = str_replace(['AM', 'PM'], ['A.M', 'P.M'], $exam_time);
                }
            }
            $floor = trim((string)($rowSched['floor'] ?? ''));
            $room = trim((string)($rowSched['room'] ?? ''));
            if ($floor !== '' || $room !== '') {
                $exam_venue = trim(($floor !== '' ? $floor : '') . (($floor !== '' && $room !== '') ? ' • ' : '') . ($room !== '' ? $room : ''));
            }
            $response['schedule'] = [
                'schedule_id' => (int)($rowSched['schedule_id'] ?? 0),
                'floor' => $floor,
                'room' => $room,
                'start_date_and_time' => $dt,
                'exam_date' => $exam_date,
                'exam_time' => $exam_time,
                'exam_venue' => $exam_venue
            ];
        }
        $stmtSched->close();
    }
}

echo json_encode($response);
exit;
?>