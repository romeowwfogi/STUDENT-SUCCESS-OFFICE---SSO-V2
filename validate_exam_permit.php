<?php
header('Content-Type: application/json');
require_once __DIR__ . '/connection/db_connect.php';

// Accept applicant_number from POST or GET
$applicant_number = '';
if (isset($_POST['applicant_number'])) {
    $applicant_number = trim((string)$_POST['applicant_number']);
} elseif (isset($_GET['applicant_number'])) {
    $applicant_number = trim((string)$_GET['applicant_number']);
} elseif (isset($_POST['qr_text'])) {
    $applicant_number = trim((string)$_POST['qr_text']);
} elseif (isset($_GET['qr_text'])) {
    $applicant_number = trim((string)$_GET['qr_text']);
}

if ($applicant_number === '') {
    echo json_encode(['ok' => false, 'error' => 'Missing applicant_number']);
    exit;
}

if (!isset($conn) || !$conn) {
    echo json_encode(['ok' => false, 'error' => 'Database connection not available']);
    exit;
}

// Find permit by applicant_number
$permit = null;
if ($stmt = $conn->prepare("SELECT id, user_id, applicant_number, status FROM application_permit WHERE applicant_number = ? ORDER BY id DESC LIMIT 1")) {
    $stmt->bind_param('s', $applicant_number);
    $stmt->execute();
    $res = $stmt->get_result();
    $permit = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}

if (!$permit) {
    echo json_encode(['ok' => false, 'error' => 'Permit not found']);
    exit;
}

$user_id = (int)($permit['user_id'] ?? 0);
$full_name = null;

// Try user_fullname view/table first (used elsewhere in this app)
if ($user_id > 0) {
    if ($stmtUF = $conn->prepare("SELECT TRIM(CONCAT_WS(' ', first_name, NULLIF(middle_name,''), last_name, NULLIF(suffix,''))) AS full_name FROM user_fullname WHERE user_id = ? LIMIT 1")) {
        $stmtUF->bind_param('i', $user_id);
        $stmtUF->execute();
        $resUF = $stmtUF->get_result();
        if ($resUF && ($rowUF = $resUF->fetch_assoc())) {
            $full_name = trim((string)($rowUF['full_name'] ?? ''));
        }
        $stmtUF->close();
    }
}

// Fallback: get first_name/last_name from latest submission_data
if ($user_id > 0 && (!$full_name || $full_name === '')) {
    $first_name = '';
    $last_name = '';
    $sqlName = "SELECT sd_fname.field_value AS first_name, sd_lname.field_value AS last_name
                FROM submissions s
                LEFT JOIN submission_data sd_fname ON (s.id = sd_fname.submission_id AND sd_fname.field_name = 'first_name')
                LEFT JOIN submission_data sd_lname ON (s.id = sd_lname.submission_id AND sd_lname.field_name = 'last_name')
                WHERE s.user_id = ?
                ORDER BY s.submitted_at DESC LIMIT 1";
    if ($stmtN = $conn->prepare($sqlName)) {
        $stmtN->bind_param('i', $user_id);
        $stmtN->execute();
        $resN = $stmtN->get_result();
        if ($resN && ($rowN = $resN->fetch_assoc())) {
            $first_name = trim((string)($rowN['first_name'] ?? ''));
            $last_name = trim((string)($rowN['last_name'] ?? ''));
        }
        $stmtN->close();
    }
    $full_name = trim($first_name . ' ' . $last_name);
}

echo json_encode([
    'ok' => true,
    'applicant_number' => $permit['applicant_number'],
    'status' => $permit['status'] ?? null,
    'user_id' => $user_id,
    'applicant_name' => $full_name ?: null,
]);
exit;
?>