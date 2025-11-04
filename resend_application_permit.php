<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/connection/db_connect.php';
require_once __DIR__ . '/function/decrypt.php';
require_once __DIR__ . '/function/sendEmail.php';

if (!$conn) {
    echo json_encode(['ok' => false, 'error' => 'Database connection not available']);
    exit;
}

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$accent_color = isset($_POST['accent_color']) ? trim($_POST['accent_color']) : '';
$admission_officer = isset($_POST['admission_officer']) ? trim($_POST['admission_officer']) : '';
$applicant_name    = isset($_POST['applicant_name']) ? trim($_POST['applicant_name']) : '';
$date_of_exam      = isset($_POST['date_of_exam']) ? trim($_POST['date_of_exam']) : '';
$exam_time_input   = isset($_POST['exam_time']) ? trim($_POST['exam_time']) : '';
$period_start_raw  = isset($_POST['application_period_start']) ? trim($_POST['application_period_start']) : '';
$period_end_raw    = isset($_POST['application_period_end']) ? trim($_POST['application_period_end']) : '';

if ($user_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Missing user_id']);
    exit;
}

// Resolve latest applicant number and permit snapshot for this user
$applicant_number = '';
$permit_id = null;
if ($stmtPermit = $conn->prepare("SELECT id, applicant_number, admission_officer, applicant_name, exam_date, exam_time, room_no, exam_venue, application_period_start, application_period_end, application_period_text, accent_color, download_url FROM application_permit WHERE user_id = ? ORDER BY id DESC LIMIT 1")) {
    $stmtPermit->bind_param('i', $user_id);
    $stmtPermit->execute();
    $resPermit = $stmtPermit->get_result();
    if ($resPermit && ($rowPermit = $resPermit->fetch_assoc())) {
        $permit_id = isset($rowPermit['id']) ? intval($rowPermit['id']) : null;
        $applicant_number = trim((string)($rowPermit['applicant_number'] ?? ''));
        // Prefer stored snapshot values when not provided
        $admission_officer = $admission_officer !== '' ? $admission_officer : trim((string)($rowPermit['admission_officer'] ?? ''));
        $applicant_name    = $applicant_name    !== '' ? $applicant_name    : trim((string)($rowPermit['applicant_name'] ?? ''));
        $date_of_exam      = $date_of_exam      !== '' ? $date_of_exam      : trim((string)($rowPermit['exam_date'] ?? ''));
        $exam_time_input   = $exam_time_input   !== '' ? $exam_time_input   : trim((string)($rowPermit['exam_time'] ?? ''));
        $accent_color      = $accent_color      !== '' ? $accent_color      : trim((string)($rowPermit['accent_color'] ?? ''));
        // We'll re-derive venue below, but keep room_no snapshot if present
        $snap_room_no      = trim((string)($rowPermit['room_no'] ?? ''));
        $snap_venue        = trim((string)($rowPermit['exam_venue'] ?? ''));
        $period_start_raw  = $period_start_raw  !== '' ? $period_start_raw  : trim((string)($rowPermit['application_period_start'] ?? ''));
        $period_end_raw    = $period_end_raw    !== '' ? $period_end_raw    : trim((string)($rowPermit['application_period_end'] ?? ''));
        $application_period = '';
        if (!empty($rowPermit['application_period_text'])) {
            $application_period = trim((string)$rowPermit['application_period_text']);
        }
        $existing_download_url = trim((string)($rowPermit['download_url'] ?? ''));
    }
    $stmtPermit->close();
}
if ($applicant_number === '') {
    echo json_encode(['ok' => false, 'error' => 'No existing permit found to resend']);
    exit;
}

// Helper to resolve plaintext or encrypted emails
function resolve_email($value)
{
    $value = trim($value ?? '');
    if ($value === '') return '';
    if (strpos($value, '@') !== false) return $value;
    $decrypted = decryptData($value);
    if ($decrypted && strpos($decrypted, '@') !== false) {
        return $decrypted;
    }
    return $value;
}

// Fetch recipient email
$receiver = '';
if ($infoStmt = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1")) {
    $infoStmt->bind_param('i', $user_id);
    $infoStmt->execute();
    $resInfo = $infoStmt->get_result();
    if ($resInfo && ($rowInfo = $resInfo->fetch_assoc())) {
        $receiver = resolve_email($rowInfo['email'] ?? '');
    }
    $infoStmt->close();
}
if ($receiver === '') {
    echo json_encode(['ok' => false, 'error' => 'Recipient email not found']);
    exit;
}

// Resolve applicant_name if not provided
if ($applicant_name === '') {
    if ($stmtName = $conn->prepare("SELECT first_name, middle_name, last_name, suffix FROM user_fullname WHERE user_id = ? LIMIT 1")) {
        $stmtName->bind_param('i', $user_id);
        $stmtName->execute();
        $resName = $stmtName->get_result();
        if ($resName && ($rowName = $resName->fetch_assoc())) {
            $parts = [];
            foreach (['first_name', 'middle_name', 'last_name', 'suffix'] as $k) {
                $v = trim((string)($rowName[$k] ?? ''));
                if ($v !== '') $parts[] = $v;
            }
            $applicant_name = implode(' ', $parts);
        }
        $stmtName->close();
    }
}
// Fallback admission officer label if missing
if ($admission_officer === '') {
    $admission_officer = 'Admissions Office';
}

if (empty($EXAM_PERMIT_TEMPLATE) || empty($EXAM_PERMIT_SUBJECT)) {
    echo json_encode(['ok' => false, 'error' => 'Exam permit template not configured']);
    exit;
}

// Compose placeholders
$exam_date = '';
$exam_time = '';
$exam_venue = '';
$room_no    = '';

// Resolve schedule details
$schedule_id = null;
if ($stmtReg = $conn->prepare("SELECT schedule_id FROM ExamRegistrations WHERE user_id = ? ORDER BY registration_id DESC LIMIT 1")) {
    $stmtReg->bind_param('i', $user_id);
    $stmtReg->execute();
    $resReg = $stmtReg->get_result();
    if ($resReg && ($rowReg = $resReg->fetch_assoc())) {
        $schedule_id = isset($rowReg['schedule_id']) ? intval($rowReg['schedule_id']) : null;
    }
    $stmtReg->close();
}
if ($schedule_id) {
    if ($stmtSched = $conn->prepare("SELECT floor, room, start_date_and_time FROM ExamSchedules WHERE schedule_id = ? LIMIT 1")) {
        $stmtSched->bind_param('i', $schedule_id);
        $stmtSched->execute();
        $resSched = $stmtSched->get_result();
        if ($resSched && ($rowSched = $resSched->fetch_assoc())) {
            $dt = $rowSched['start_date_and_time'] ?? null;
            if ($dt) {
                $parts = explode(' ', $dt);
                if (count($parts) >= 2) {
                    $exam_date = $parts[0];
                    $timeParts = explode(':', $parts[1]);
                    $hhmm = (count($timeParts) >= 2) ? ($timeParts[0] . ':' . $timeParts[1]) : $parts[1];
                    $exam_time = $hhmm;
                }
            }
            $floor = trim((string)($rowSched['floor'] ?? ''));
            $room = trim((string)($rowSched['room'] ?? ''));
            $room_no = $room;
            $exam_venue = trim($floor . (($floor && $room) ? ' • ' : '') . $room);
        }
        $stmtSched->close();
    }
}

// Prefer stored room_no/venue snapshot if schedule didn’t resolve
if ($room_no === '' && isset($snap_room_no)) {
    $room_no = $snap_room_no;
}
if ($exam_venue === '' && isset($snap_venue)) {
    $exam_venue = $snap_venue;
}

// Prefer provided modal-like overrides if present
if ($date_of_exam !== '') $exam_date = $date_of_exam;
if ($exam_time_input !== '') $exam_time = $exam_time_input;

// Build application period (if not set from snapshot)
$application_period = isset($application_period) ? $application_period : '';
if ($period_start_raw !== '' && $period_end_raw !== '') {
    $ds = DateTime::createFromFormat('Y-m-d', $period_start_raw);
    $de = DateTime::createFromFormat('Y-m-d', $period_end_raw);
    if ($ds && $de) {
        $application_period = $ds->format('F d') . ' to ' . $de->format('F d, Y');
    }
}

// Prefer stored snapshot in application_permit for resend
try {
    if ($stmtSnap = $conn->prepare("SELECT * FROM application_permit WHERE user_id = ? ORDER BY id DESC LIMIT 1")) {
        $stmtSnap->bind_param('i', $user_id);
        $stmtSnap->execute();
        $resSnap = $stmtSnap->get_result();
        if ($resSnap && ($rowSnap = $resSnap->fetch_assoc())) {
            // Use stored details if current values are empty
            if ($admission_officer === '' && isset($rowSnap['admission_officer'])) {
                $admission_officer = trim((string)$rowSnap['admission_officer']);
            }
            if ($applicant_name === '' && isset($rowSnap['applicant_name'])) {
                $applicant_name = trim((string)$rowSnap['applicant_name']);
            }
            if ($exam_date === '' && isset($rowSnap['exam_date'])) {
                $exam_date = trim((string)$rowSnap['exam_date']);
            }
            if ($exam_time === '' && isset($rowSnap['exam_time'])) {
                $exam_time = trim((string)$rowSnap['exam_time']);
            }
            if ($room_no === '' && isset($rowSnap['room_no'])) {
                $room_no = trim((string)$rowSnap['room_no']);
            }
            if ($exam_venue === '' && isset($rowSnap['exam_venue'])) {
                $exam_venue = trim((string)$rowSnap['exam_venue']);
            }
            if ($period_start_raw === '' && isset($rowSnap['application_period_start'])) {
                $period_start_raw = trim((string)$rowSnap['application_period_start']);
            }
            if ($period_end_raw === '' && isset($rowSnap['application_period_end'])) {
                $period_end_raw = trim((string)$rowSnap['application_period_end']);
            }
            if ($application_period === '' && isset($rowSnap['application_period_text'])) {
                $application_period = trim((string)$rowSnap['application_period_text']);
            }
            if ($accent_color === '' && isset($rowSnap['accent_color'])) {
                $accent_color = trim((string)$rowSnap['accent_color']);
            }
        }
        $stmtSnap->close();
    }
} catch (Throwable $e) {
    // ignore snapshot fallback errors
}

// If we obtained start/end from snapshot and period text still empty, build it
if ($application_period === '' && $period_start_raw !== '' && $period_end_raw !== '') {
    $ds2 = DateTime::createFromFormat('Y-m-d', $period_start_raw);
    $de2 = DateTime::createFromFormat('Y-m-d', $period_end_raw);
    if ($ds2 && $de2) {
        $application_period = $ds2->format('F d') . ' to ' . $de2->format('F d, Y');
    }
}

// Call external generator API only if we don't have a stored URL
$permit_download_url = isset($existing_download_url) ? $existing_download_url : '';
try {
    if ($permit_download_url === '') {
        $generator_url = isset($EXAM_PERMIT_GENERATOR_API) && $EXAM_PERMIT_GENERATOR_API ? $EXAM_PERMIT_GENERATOR_API : 'https://gold-lion-549609.hostingersite.com/exam-permit.php';
        $sender_email = isset($_SESSION['user_email']) ? trim($_SESSION['user_email']) : '';
        $sender_password = isset($_SESSION['user_password']) ? trim($_SESSION['user_password']) : '';
        $payload = [
            'text_color'         => $accent_color !== '' ? $accent_color : '#18a558',
            'email'              => $sender_email,
            'password'           => $sender_password,
            'applicant_number'   => $applicant_number,
            'date_of_exam'       => $exam_date,
            'time'               => $exam_time,
            'room_no'            => $room_no,
            'admission_officer'  => $admission_officer,
            'applicant_name'     => $applicant_name,
            'application_period' => $application_period,
        ];
        $ch = curl_init($generator_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $resp = curl_exec($ch);
        if ($resp !== false) {
            $json = json_decode($resp, true);
            if (is_array($json) && !empty($json['success']) && !empty($json['download_url'])) {
                $permit_download_url = (string)$json['download_url'];
            }
        }
        curl_close($ch);
        // Log generator call for diagnostics
        $log = date('Y-m-d H:i:s') . " RESEND generator payload=" . json_encode($payload) . "\nRESP=" . (is_string($resp) ? $resp : 'null') . "\nURL=" . $permit_download_url . "\n";
        @file_put_contents(__DIR__ . '/log.txt', $log, FILE_APPEND);
        // Persist new download URL to application_permit if available
        if ($permit_download_url !== '' && !empty($permit_id)) {
            try {
                $stmtUpd = $conn->prepare("UPDATE application_permit SET download_url = ? WHERE id = ?");
                $stmtUpd->bind_param('si', $permit_download_url, $permit_id);
                $stmtUpd->execute();
                $stmtUpd->close();
            } catch (Throwable $e2) { /* ignore */ }
        }
    }
} catch (Throwable $e) {
    // ignore and proceed without link
}

// Prepare email body with placeholders
$email_body = str_replace(
    ['{{applicant_number}}', '{{exam_date}}', '{{exam_time}}', '{{exam_venue}}'],
    [$applicant_number, $exam_date, $exam_time, $exam_venue],
    $EXAM_PERMIT_TEMPLATE
);
if ($permit_download_url !== '') {
    if (strpos($email_body, '{{exam_permit_download_link}}') !== false) {
        $email_body = str_replace('{{exam_permit_download_link}}', $permit_download_url, $email_body);
    } else {
        // Inject before closing </body> if present, else append
        $linkHtml = "<p style=\"margin-top:12px;\">Download your exam permit: <a href=\"" . htmlspecialchars($permit_download_url, ENT_QUOTES) . "\" target=\"_blank\">Click here</a></p>";
        if (stripos($email_body, '</body>') !== false) {
            $email_body = preg_replace('/<\/body>\s*<\/html>\s*$/i', $linkHtml . '</body></html>', $email_body, 1);
            $email_body = preg_replace('/<\/body>/i', $linkHtml . '</body>', $email_body, 1);
        } else {
            $email_body .= "\n\n" . $linkHtml;
        }
    }
}

// Send email
send_status_email($receiver, $EXAM_PERMIT_SUBJECT, $email_body);

// Update email status snapshot if we have a permit record
try {
    if (!empty($permit_id)) {
        $sent_at = date('Y-m-d H:i:s');
        $stmtUpd2 = $conn->prepare("UPDATE application_permit SET email_status = 'sent', email_subject = ?, email_body = ?, email_sent_at = ?, download_url = COALESCE(NULLIF(?, ''), download_url) WHERE id = ?");
        $stmtUpd2->bind_param('ssssi', $EXAM_PERMIT_SUBJECT, $email_body, $sent_at, $permit_download_url, $permit_id);
        $stmtUpd2->execute();
        $stmtUpd2->close();
    }
} catch (Throwable $e3) { /* ignore */ }

echo json_encode(['ok' => true, 'emailed' => true, 'download_url' => $permit_download_url]);
exit;
