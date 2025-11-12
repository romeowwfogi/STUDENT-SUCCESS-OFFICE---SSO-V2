<?php
require_once 'middleware/auth.php';
require_once 'connection/db_connect.php';
require_once 'function/decrypt.php';
require_once 'function/sendEmail.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$submission_id = isset($_POST['submission_id']) ? (int)$_POST['submission_id'] : 0;
$status = isset($_POST['status']) ? trim($_POST['status']) : '';
$remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : null;
$can_update_raw = isset($_POST['can_update']) ? trim($_POST['can_update']) : null;
$can_update = ($can_update_raw === '1') ? 1 : 0;
// Optional flag: allow applicant to submit another application
$can_submit_another_raw = isset($_POST['can_submit_another']) ? trim($_POST['can_submit_another']) : null;
$can_submit_another = ($can_submit_another_raw === '1') ? 1 : 0;

if ($submission_id <= 0 || $status === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid submission id or status']);
    exit;
}

// Ensure submission exists
$exists = false;
if ($stmtC = $conn->prepare('SELECT id FROM submissions WHERE id = ? LIMIT 1')) {
    $stmtC->bind_param('i', $submission_id);
    $stmtC->execute();
    $resC = $stmtC->get_result();
    $exists = $resC && $resC->num_rows > 0;
    $stmtC->close();
}
if (!$exists) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Submission not found']);
    exit;
}

// Determine if 'can_submit_another' column exists
$colExists = false;
if ($stmtCol = $conn->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'submissions' AND COLUMN_NAME = 'can_submit_another'")) {
    $stmtCol->execute();
    $resCol = $stmtCol->get_result();
    if ($resCol && ($rowCol = $resCol->fetch_assoc())) {
        $colExists = ((int)($rowCol['cnt'] ?? 0)) > 0;
    }
    $stmtCol->close();
}

// Update status, remarks, can_update, and optionally can_submit_another
if ($colExists) {
    if ($stmtU = $conn->prepare('UPDATE submissions SET status = ?, remarks = ?, can_update = ?, can_submit_another = ? WHERE id = ?')) {
        $stmtU->bind_param('ssiii', $status, $remarks, $can_update, $can_submit_another, $submission_id);
        if (!$stmtU->execute()) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Database update failed']);
            $stmtU->close();
            exit;
        }
        $stmtU->close();
    }
} else {
    if ($stmtU = $conn->prepare('UPDATE submissions SET status = ?, remarks = ?, can_update = ? WHERE id = ?')) {
        $stmtU->bind_param('ssii', $status, $remarks, $can_update, $submission_id);
        if (!$stmtU->execute()) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Database update failed']);
            $stmtU->close();
            exit;
        }
        $stmtU->close();
    }
}

// After successful update, send admission status update email
// Helper to resolve encrypted emails
function resolve_email($value)
{
    $value = trim($value ?? '');
    if ($value === '') return '';
    if (strpos($value, '@') !== false) return $value;
    $dec = decryptData($value);
    if ($dec && strpos($dec, '@') !== false) return $dec;
    return $value;
}

// Fetch minimal info for email
$receiver = '';
if ($stmtE = $conn->prepare('SELECT u.email AS user_email FROM submissions s LEFT JOIN users u ON s.user_id = u.id WHERE s.id = ? LIMIT 1')) {
    $stmtE->bind_param('i', $submission_id);
    $stmtE->execute();
    $resE = $stmtE->get_result();
    if ($resE && ($rowE = $resE->fetch_assoc())) {
        $receiver = resolve_email($rowE['user_email'] ?? '');
    }
    $stmtE->close();
}

// Compose and send email if possible
if ($receiver !== '' && !empty($ADMISSION_UPDATE_TEMPLATE) && !empty($ADMISSION_UPDATE_SUBJECT)) {
    $email_body = str_replace(
        ['{{status}}', '{{remarks}}', '{{subject}}'],
        [$status, (string)($remarks ?? ''), (string)$ADMISSION_UPDATE_SUBJECT],
        $ADMISSION_UPDATE_TEMPLATE
    );
    // Fire-and-forget; sendStatusEmail handles logging
    send_status_email($receiver, $ADMISSION_UPDATE_SUBJECT, $email_body);
}

echo json_encode(['ok' => true]);
exit;
?>