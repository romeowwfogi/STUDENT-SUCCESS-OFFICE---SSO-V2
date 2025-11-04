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

// Parse user_ids from POST: supports user_ids[] array or comma-separated string
$user_ids = [];
if (isset($_POST['user_ids']) && is_array($_POST['user_ids'])) {
    $user_ids = array_map('intval', $_POST['user_ids']);
} elseif (isset($_POST['user_ids'])) {
    $raw = trim((string)$_POST['user_ids']);
    if ($raw !== '') {
        foreach (explode(',', $raw) as $p) {
            $id = intval(trim($p));
            if ($id > 0) $user_ids[] = $id;
        }
    }
}

// Fallback: support JSON body
if (empty($user_ids)) {
    $input = file_get_contents('php://input');
    if ($input) {
        $decoded = json_decode($input, true);
        if (is_array($decoded) && isset($decoded['user_ids'])) {
            $arr = $decoded['user_ids'];
            if (is_array($arr)) {
                $user_ids = array_map('intval', $arr);
            }
        }
    }
}

// Deduplicate and validate
$user_ids = array_values(array_unique(array_filter($user_ids, function($v){ return intval($v) > 0; })));
if (empty($user_ids)) {
    echo json_encode(['ok' => false, 'error' => 'No user_ids provided']);
    exit;
}

// Helper: resolve plaintext or encrypted emails
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

// Helper: fetch next permit id (MAX(id)+1) once, then increment locally
$next_id = 1;
try {
    $sqlNext = "SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM application_permit";
    if ($resNext = $conn->query($sqlNext)) {
        if ($rowN = $resNext->fetch_assoc()) {
            $val = isset($rowN['next_id']) ? (int)$rowN['next_id'] : 1;
            $next_id = $val > 0 ? $val : 1;
        }
        $resNext->close();
    }
} catch (Throwable $e) {
    $next_id = 1;
}

// Helper: latest prefix and default digits/order
$prefix = '';
$digits = 8;
$order = 'last';
try {
    if ($stmtPref = $conn->prepare("SELECT prefix FROM applicant_number_prefix ORDER BY date_added DESC LIMIT 1")) {
        $stmtPref->execute();
        $resPref = $stmtPref->get_result();
        if ($resPref && ($rowP = $resPref->fetch_assoc())) {
            $p = trim((string)($rowP['prefix'] ?? ''));
            if ($p !== '') $prefix = $p;
        }
        $stmtPref->close();
    }
} catch (Throwable $e) { /* ignore */ }

function compose_applicant_number($prefix, $digits, $order, $seq)
{
    $digits = max(1, min(20, (int)$digits));
    $seqStr = (string)max(1, (int)$seq);
    if ($order === 'first') {
        $numPart = strlen($seqStr) >= $digits ? substr($seqStr, 0, $digits) : ($seqStr . str_repeat('0', $digits - strlen($seqStr)));
    } else {
        $numPart = strlen($seqStr) >= $digits ? substr($seqStr, -$digits) : str_pad($seqStr, $digits, '0', STR_PAD_LEFT);
    }
    return ($prefix !== '' ? ($prefix . '-') : '') . $numPart;
}

// Process each user
$details = [];
$success = 0;
$failed = 0;

foreach ($user_ids as $uid) {
    $mode = 'created';
    $emailed = false;
    $error = null;
    $applicant_number = '';
    $permit_id = null;
    $permit_download_url = '';
    $admission_officer = 'Admissions Office';
    $applicant_name = '';
    $exam_date = '';
    $exam_time = '';
    $exam_venue = '';
    $room_no = '';
    $period_start_raw = '';
    $period_end_raw = '';
    $application_period = '';
    $accent_color = '#18a558';

    try {
        // Check if permit exists
        $hasPermit = false;
        $rowSnap = null;
        if ($stmtChk = $conn->prepare("SELECT * FROM application_permit WHERE user_id = ? ORDER BY id DESC LIMIT 1")) {
            $stmtChk->bind_param('i', $uid);
            $stmtChk->execute();
            $resChk = $stmtChk->get_result();
            if ($resChk && ($rowSnap = $resChk->fetch_assoc())) {
                $hasPermit = true;
                $permit_id = (int)($rowSnap['id'] ?? 0);
                $applicant_number = trim((string)($rowSnap['applicant_number'] ?? ''));
                $admission_officer = trim((string)($rowSnap['admission_officer'] ?? $admission_officer));
                $applicant_name    = trim((string)($rowSnap['applicant_name'] ?? $applicant_name));
                $exam_date         = trim((string)($rowSnap['exam_date'] ?? $exam_date));
                $exam_time         = trim((string)($rowSnap['exam_time'] ?? $exam_time));
                $room_no           = trim((string)($rowSnap['room_no'] ?? $room_no));
                $exam_venue        = trim((string)($rowSnap['exam_venue'] ?? $exam_venue));
                $period_start_raw  = trim((string)($rowSnap['application_period_start'] ?? $period_start_raw));
                $period_end_raw    = trim((string)($rowSnap['application_period_end'] ?? $period_end_raw));
                $application_period= trim((string)($rowSnap['application_period_text'] ?? $application_period));
                $accent_color      = trim((string)($rowSnap['accent_color'] ?? $accent_color));
                $permit_download_url = trim((string)($rowSnap['download_url'] ?? ''));
            }
            $stmtChk->close();
        }

        // Resolve email recipient
        $receiver = '';
        if ($infoStmt = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1")) {
            $infoStmt->bind_param('i', $uid);
            $infoStmt->execute();
            $resInfo = $infoStmt->get_result();
            if ($resInfo && ($rowInfo = $resInfo->fetch_assoc())) {
                $receiver = resolve_email($rowInfo['email'] ?? '');
            }
            $infoStmt->close();
        }
        if ($receiver === '') {
            throw new Exception('Recipient email not found');
        }

        // Resolve applicant name if missing
        if ($applicant_name === '') {
            if ($stmtName = $conn->prepare("SELECT first_name, middle_name, last_name, suffix FROM user_fullname WHERE user_id = ? LIMIT 1")) {
                $stmtName->bind_param('i', $uid);
                $stmtName->execute();
                $resName = $stmtName->get_result();
                if ($resName && ($rowName = $resName->fetch_assoc())) {
                    $parts = [];
                    foreach (['first_name','middle_name','last_name','suffix'] as $k) {
                        $v = trim((string)($rowName[$k] ?? ''));
                        if ($v !== '') $parts[] = $v;
                    }
                    $applicant_name = implode(' ', $parts);
                }
                $stmtName->close();
            }
        }

        // Resolve latest schedule for placeholders if missing
        $schedule_id = null;
        if ($stmtReg = $conn->prepare("SELECT schedule_id FROM ExamRegistrations WHERE user_id = ? ORDER BY registration_id DESC LIMIT 1")) {
            $stmtReg->bind_param('i', $uid);
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
                            $exam_date = $exam_date ?: $parts[0];
                            $timeParts = explode(':', $parts[1]);
                            $hhmm = (count($timeParts) >= 2) ? ($timeParts[0] . ':' . $timeParts[1]) : $parts[1];
                            $exam_time = $exam_time ?: $hhmm;
                        }
                    }
                    $floor = trim((string)($rowSched['floor'] ?? ''));
                    $room = trim((string)($rowSched['room'] ?? ''));
                    $room_no = $room_no ?: $room;
                    $exam_venue = $exam_venue ?: trim($floor . (($floor && $room) ? ' • ' : '') . $room);
                }
                $stmtSched->close();
            }
        }

        // Build application period text if raw dates present and text missing
        if ($application_period === '' && $period_start_raw !== '' && $period_end_raw !== '') {
            $ds = DateTime::createFromFormat('Y-m-d', $period_start_raw);
            $de = DateTime::createFromFormat('Y-m-d', $period_end_raw);
            if ($ds && $de) {
                $application_period = $ds->format('F d') . ' to ' . $de->format('F d, Y');
            }
        }

        // Decide mode: reuse existing permit (no regeneration) or create new
        if ($hasPermit && $applicant_number !== '') {
            $mode = 'reuse';
            // Prepare email body using existing template; attach existing download_url if available
            if (empty($EXAM_PERMIT_TEMPLATE) || empty($EXAM_PERMIT_SUBJECT)) {
                throw new Exception('Exam permit template not configured');
            }
            $email_body = str_replace(
                ['{{applicant_number}}','{{exam_date}}','{{exam_time}}','{{exam_venue}}'],
                [$applicant_number, $exam_date, $exam_time, $exam_venue],
                $EXAM_PERMIT_TEMPLATE
            );
            if ($permit_download_url !== '') {
                if (strpos($email_body, '{{exam_permit_download_link}}') !== false) {
                    $email_body = str_replace('{{exam_permit_download_link}}', $permit_download_url, $email_body);
                } else {
                    $email_body .= "\n\n<p style=\"margin-top:12px;\">Download your exam permit: <a href=\"" . htmlspecialchars($permit_download_url, ENT_QUOTES) . "\" target=\"_blank\">Click here</a></p>";
                }
            }
            $emailed = send_status_email($receiver, $EXAM_PERMIT_SUBJECT, $email_body) ? true : false;
            if ($permit_id) {
                // Snapshot update
                try {
                    $sent_at = date('Y-m-d H:i:s');
                    $stmtUpd = $conn->prepare("UPDATE application_permit SET email_status='sent', email_subject=?, email_body=?, email_sent_at=? WHERE id = ?");
                    $stmtUpd->bind_param('sssi', $EXAM_PERMIT_SUBJECT, $email_body, $sent_at, $permit_id);
                    $stmtUpd->execute();
                    $stmtUpd->close();
                } catch (Throwable $e2) { /* ignore */ }
            }
        } else {
            $mode = 'created';
            // Compose new applicant_number and insert minimal record
            $applicant_number = compose_applicant_number($prefix, $digits, $order, $next_id);
            $next_id++;
            if (empty($EXAM_PERMIT_TEMPLATE) || empty($EXAM_PERMIT_SUBJECT)) {
                throw new Exception('Exam permit template not configured');
            }
            if ($stmtIns = $conn->prepare("INSERT INTO application_permit (user_id, applicant_number) VALUES (?, ?)")) {
                $stmtIns->bind_param('is', $uid, $applicant_number);
                if (!$stmtIns->execute()) {
                    throw new Exception('Insert failed: ' . $stmtIns->error);
                }
                $permit_id = $stmtIns->insert_id;
                $stmtIns->close();
            } else {
                throw new Exception('Prepare insert failed: ' . $conn->error);
            }

            // Call external generator API BEFORE sending
            try {
                $generator_url = 'https://gold-lion-549609.hostingersite.com/exam-permit.php';
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
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json','Accept: application/json']);
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
                $log = date('Y-m-d H:i:s') . " BULK CREATE generator payload=" . json_encode($payload) . "\nRESP=" . (is_string($resp) ? $resp : 'null') . "\nURL=" . $permit_download_url . "\n";
                @file_put_contents(__DIR__ . '/log.txt', $log, FILE_APPEND);
            } catch (Throwable $e) { /* proceed without link */ }

            // Prepare email body and send
            $email_body = str_replace(
                ['{{applicant_number}}','{{exam_date}}','{{exam_time}}','{{exam_venue}}'],
                [$applicant_number, $exam_date, $exam_time, $exam_venue],
                $EXAM_PERMIT_TEMPLATE
            );
            if ($permit_download_url !== '') {
                if (strpos($email_body, '{{exam_permit_download_link}}') !== false) {
                    $email_body = str_replace('{{exam_permit_download_link}}', $permit_download_url, $email_body);
                } else {
                    $email_body .= "\n\n<p style=\"margin-top:12px;\">Download your exam permit: <a href=\"" . htmlspecialchars($permit_download_url, ENT_QUOTES) . "\" target=\"_blank\">Click here</a></p>";
                }
            }
            $emailed = send_status_email($receiver, $EXAM_PERMIT_SUBJECT, $email_body) ? true : false;

            // Persist extended fields snapshot
            try {
                $stmtUpd = $conn->prepare("UPDATE application_permit SET admission_officer=?, applicant_name=?, exam_date=?, exam_time=?, room_no=?, exam_venue=?, application_period_start=?, application_period_end=?, application_period_text=?, accent_color=?, download_url=?, email_subject=?, email_body=?, email_status=?, email_sent_at=? WHERE id = ?");
                $email_status = $emailed ? 'sent' : 'queued';
                $sent_at = $emailed ? date('Y-m-d H:i:s') : null;
                $stmtUpd->bind_param(
                    'sssssssssssssssi',
                    $admission_officer,
                    $applicant_name,
                    $exam_date,
                    $exam_time,
                    $room_no,
                    $exam_venue,
                    $period_start_raw,
                    $period_end_raw,
                    $application_period,
                    $accent_color,
                    $permit_download_url,
                    $EXAM_PERMIT_SUBJECT,
                    $email_body,
                    $email_status,
                    $sent_at,
                    $permit_id
                );
                $stmtUpd->execute();
                $stmtUpd->close();
            } catch (Throwable $e3) { /* ignore */ }
        }

        // Log per user result
        sso_log_email_event($emailed ? 'bulk_success' : 'bulk_fail', [
            'user_id' => $uid,
            'mode' => $mode,
            'applicant_number' => $applicant_number,
        ]);
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
        sso_log_email_event('bulk_error', [ 'user_id' => $uid, 'error' => $error ]);
    }

    if ($error === null && $emailed) {
        $success++;
    } else {
        $failed++;
    }
    $details[] = [
        'user_id' => $uid,
        'mode' => $mode,
        'emailed' => $emailed,
        'error' => $error,
    ];
}

$msg = sprintf(
    'Exam permits successfully generated and sent for %d students. %d failed — check logs.',
    $success,
    $failed
);

echo json_encode([
    'ok' => true,
    'processed' => count($user_ids),
    'success' => $success,
    'failed' => $failed,
    'message' => $msg,
    'details' => $details,
]);
exit;
?>