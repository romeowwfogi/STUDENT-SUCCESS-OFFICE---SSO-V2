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
$applicant_number = isset($_POST['applicant_number']) ? trim($_POST['applicant_number']) : '';
// Optional fields from the Send Permit modal
$admission_officer = isset($_POST['admission_officer']) ? trim($_POST['admission_officer']) : '';
$applicant_name    = isset($_POST['applicant_name']) ? trim($_POST['applicant_name']) : '';
$date_of_exam      = isset($_POST['date_of_exam']) ? trim($_POST['date_of_exam']) : '';
$exam_time_input   = isset($_POST['exam_time']) ? trim($_POST['exam_time']) : '';
$period_start_raw  = isset($_POST['application_period_start']) ? trim($_POST['application_period_start']) : '';
$period_end_raw    = isset($_POST['application_period_end']) ? trim($_POST['application_period_end']) : '';
$accent_color      = isset($_POST['accent_color']) ? trim($_POST['accent_color']) : '';

if ($user_id <= 0 || $applicant_number === '') {
    echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
    exit;
}

// If a permit for this user already exists, perform a resend instead of creating a new one
try {
    if ($user_id > 0) {
        if ($chkStmt = $conn->prepare("SELECT id FROM application_permit WHERE user_id = ? ORDER BY id DESC LIMIT 1")) {
            $chkStmt->bind_param('i', $user_id);
            $chkStmt->execute();
            $resChk = $chkStmt->get_result();
            if ($resChk && $resChk->fetch_assoc()) {
                // Forward current inputs to the resend script
                $_POST['user_id'] = $user_id;
                $_POST['accent_color'] = $accent_color;
                $_POST['admission_officer'] = $admission_officer;
                $_POST['applicant_name'] = $applicant_name;
                $_POST['date_of_exam'] = $date_of_exam;
                $_POST['exam_time'] = $exam_time_input;
                $_POST['application_period_start'] = $period_start_raw;
                $_POST['application_period_end'] = $period_end_raw;
                require __DIR__ . '/resend_application_permit.php';
                exit;
            }
            $chkStmt->close();
        }
    }
} catch (Throwable $e) { /* ignore and proceed with create */
}

$stmt = $conn->prepare("INSERT INTO application_permit (user_id, applicant_number) VALUES (?, ?)");
if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param('is', $user_id, $applicant_number);
$execOk = $stmt->execute();

if (!$execOk) {
    echo json_encode(['ok' => false, 'error' => 'Insert failed: ' . $stmt->error]);
    $stmt->close();
    exit;
}

$insert_id = $stmt->insert_id;
$stmt->close();

// Attempt to send Exam Permit email using template logic similar to applicant_management.php
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

$emailed = false;
// Will hold download URL from generator API if successful
$permit_download_url = '';
// Fetch recipient email
if ($user_id > 0) {
    if ($infoStmt = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1")) {
        $infoStmt->bind_param('i', $user_id);
        $infoStmt->execute();
        $resInfo = $infoStmt->get_result();
        if ($resInfo && ($rowInfo = $resInfo->fetch_assoc())) {
            $receiver = resolve_email($rowInfo['email'] ?? '');
            // Compose email using EXAM PERMIT template and subject loaded from db_connect.php
            if ($receiver !== '' && !empty($EXAM_PERMIT_TEMPLATE) && !empty($EXAM_PERMIT_SUBJECT)) {
                $exam_date = '';
                $exam_time = '';
                $exam_venue = '';
                $room_no    = '';
                // Try to fetch the user's latest registered exam schedule for placeholders
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
                                // Expecting format YYYY-MM-DD HH:MM:SS
                                $parts = explode(' ', $dt);
                                if (count($parts) >= 2) {
                                    $exam_date = $parts[0];
                                    $timeParts = explode(':', $parts[1]);
                                    $hhmm = (count($timeParts) >= 2) ? ($timeParts[0] . ':' . $timeParts[1]) : $parts[1];
                                    // Convert to 12-hour with AM/PM
                                    $h = intval($timeParts[0]);
                                    $m = isset($timeParts[1]) ? $timeParts[1] : '00';
                                    $ampm = ($h >= 12) ? 'PM' : 'AM';
                                    $h12 = ($h % 12) ?: 12;
                                    $exam_time = sprintf('%d:%s %s', $h12, $m, $ampm);
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

                // Prefer modal-provided value when present; normalize to YYYY-MM-DD
                if ($date_of_exam !== '') {
                    $normalized = '';
                    // If it's already YYYY-MM-DD, keep it
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_of_exam)) {
                        $normalized = $date_of_exam;
                    } else {
                        $ts = strtotime($date_of_exam);
                        if ($ts) {
                            $normalized = date('Y-m-d', $ts);
                        }
                    }
                    $exam_date = $normalized !== '' ? $normalized : $exam_date;
                }
                if ($exam_time_input !== '') {
                    // Trust provided input (expects 12-hour format like 12:00 AM/PM)
                    $exam_time = $exam_time_input;
                }

                // Build "January 06 to March 14, 2025" style period from modal inputs
                $application_period = '';
                if ($period_start_raw !== '' && $period_end_raw !== '') {
                    $ds = DateTime::createFromFormat('Y-m-d', $period_start_raw);
                    $de = DateTime::createFromFormat('Y-m-d', $period_end_raw);
                    if ($ds && $de) {
                        // Show year once, like example
                        $application_period = $ds->format('F d') . ' to ' . $de->format('F d, Y');
                    }
                }

                // Call external generator API BEFORE sending the email
                try {
                    $generator_url = 'https://gold-lion-549609.hostingersite.com/exam-permit.php';
                    $sender_email = isset($_SESSION['user_email']) ? trim($_SESSION['user_email']) : '';
                    $sender_password = isset($_SESSION['user_password']) ? trim($_SESSION['user_password']) : '';
                    $payload = [
                        'text_color'         => $accent_color !== '' ? $accent_color : '#000000',
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
                    // In case SSL issues occur on dev environments
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    $resp = curl_exec($ch);
                    if ($resp === false) {
                        // swallow error, proceed to send email without link
                    } else {
                        $json = json_decode($resp, true);
                        if (is_array($json) && !empty($json['success']) && !empty($json['download_url'])) {
                            $permit_download_url = (string)$json['download_url'];
                        }
                    }
                    curl_close($ch);
                    // Log generator call for diagnostics
                    $log = date('Y-m-d H:i:s') . " CREATE generator payload=" . json_encode($payload) . "\nRESP=" . (is_string($resp) ? $resp : 'null') . "\nURL=" . $permit_download_url . "\n";
                    @file_put_contents(__DIR__ . '/log.txt', $log, FILE_APPEND);
                } catch (Throwable $e) {
                    // proceed without download link on any exception
                }

                // Resolve applicant type and academic year for placeholders
                $applicant_type = '';
                $academic_year = '';
                try {
                    if ($ayStmt = $conn->prepare("SELECT at.name AS type_name, ac.academic_year_start AS ay_start, ac.academic_year_end AS ay_end FROM submissions s INNER JOIN applicant_types at ON at.id = s.applicant_type_id INNER JOIN admission_cycles ac ON ac.id = at.admission_cycle_id WHERE s.user_id = ? ORDER BY s.submitted_at DESC LIMIT 1")) {
                        $ayStmt->bind_param('i', $user_id);
                        $ayStmt->execute();
                        $resAy = $ayStmt->get_result();
                        if ($resAy && ($rowAy = $resAy->fetch_assoc())) {
                            $applicant_type = trim((string)($rowAy['type_name'] ?? ''));
                            $ay_start = trim((string)($rowAy['ay_start'] ?? ''));
                            $ay_end   = trim((string)($rowAy['ay_end'] ?? ''));
                            if ($ay_start !== '' && $ay_end !== '') {
                                $academic_year = $ay_start . ' - ' . $ay_end;
                            }
                        }
                        $ayStmt->close();
                    }
                } catch (Throwable $e) { /* ignore */
                }

                // Build QR link to validator using applicant number
                $qr_text = $applicant_number;
                $validator_url = (isset($_SERVER['HTTP_HOST']) ? ('http://' . $_SERVER['HTTP_HOST']) : 'http://localhost') . dirname($_SERVER['PHP_SELF']) . '/validate_exam_permit.php?qr_text=' . urlencode($qr_text);
                $qr_download_link = 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . urlencode($validator_url);

                // Prepare replacements for known and new placeholders; harmless if not present
                $email_body = str_replace(
                    [
                        '{{registered_fullname}}',
                        '{{academic_year}}',
                        '{{applicant_type}}',
                        '{{applicant_number}}',
                        '{{room_number}}',
                        '{{floor}}',
                        '{{exam_date}}',
                        '{{start_date}}',
                        '{{exam_time}}',
                        '{{start_time}}',
                        '{{exam_venue}}',
                        '{{qr_download_link}}'
                    ],
                    [
                        $applicant_name,
                        $academic_year,
                        $applicant_type,
                        $applicant_number,
                        $room_no,
                        isset($floor) ? $floor : '',
                        $exam_date,
                        $exam_date,
                        $exam_time,
                        $exam_time,
                        $exam_venue,
                        $qr_download_link
                    ],
                    $EXAM_PERMIT_TEMPLATE
                );
                if ($permit_download_url !== '') {
                    if (strpos($email_body, '{{exam_permit_download_link}}') !== false) {
                        $email_body = str_replace('{{exam_permit_download_link}}', $permit_download_url, $email_body);
                    } else {
                        // Append a fallback link section if placeholder not present
                        $email_body .= "\n\n<p style=\"margin-top:12px;\">Download your exam permit: <a href=\"" . htmlspecialchars($permit_download_url, ENT_QUOTES) . "\" target=\"_blank\">Click here</a></p>";
                    }
                }
                // Send via configured API using session-bound credentials
                send_status_email($receiver, $EXAM_PERMIT_SUBJECT, $email_body);
                $emailed = true;

                // Persist extended permit fields into application_permit (if columns exist)
                try {
                    $cols = [];
                    if ($colStmt = $conn->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'application_permit'")) {
                        $colStmt->execute();
                        $resCols = $colStmt->get_result();
                        while ($resCols && ($rowC = $resCols->fetch_assoc())) {
                            $cols[] = $rowC['COLUMN_NAME'];
                        }
                        $colStmt->close();
                    }
                    $avail = array_flip($cols);
                    $fields = [];
                    $values = [];
                    $types  = '';

                    // Map of potential fields to values
                    $map = [
                        'admission_officer'         => $admission_officer,
                        'applicant_name'            => $applicant_name,
                        'exam_date'                 => $exam_date,
                        'exam_time'                 => $exam_time,
                        'room_no'                   => $room_no,
                        'exam_venue'                => $exam_venue,
                        'application_period_start'  => $period_start_raw,
                        'application_period_end'    => $period_end_raw,
                        'application_period_text'   => $application_period,
                        'accent_color'              => ($accent_color !== '' ? $accent_color : '#18a558'),
                        'qr_text'                   => $qr_text,
                        'download_url'              => $permit_download_url,
                        'email_subject'             => $EXAM_PERMIT_SUBJECT,
                        'email_body'                => $email_body,
                        'email_status'              => $emailed ? 'sent' : 'queued',
                        'email_sent_at'             => $emailed ? date('Y-m-d H:i:s') : null,
                    ];

                    foreach ($map as $col => $val) {
                        if (!isset($avail[$col])) continue;
                        if ($col === 'email_sent_at' && $val === null) continue; // skip null timestamp
                        $fields[] = "$col = ?";
                        $values[] = $val;
                        // type detection (default to string)
                        if ($col === 'exam_date' || $col === 'application_period_start' || $col === 'application_period_end' || $col === 'email_sent_at') {
                            $types .= 's';
                        } else {
                            $types .= 's';
                        }
                    }

                    if (!empty($fields)) {
                        $sqlUpd = "UPDATE application_permit SET " . implode(', ', $fields) . " WHERE id = ?";
                        if ($uStmt = $conn->prepare($sqlUpd)) {
                            $typesAll = $types . 'i';
                            $values[] = $insert_id;
                            $uStmt->bind_param($typesAll, ...$values);
                            $uStmt->execute();
                            $uStmt->close();
                        }
                    }
                } catch (Throwable $e) {
                    // Silent failure; core flow already succeeded
                }
            }
        }
        $infoStmt->close();
    }
}

echo json_encode(['ok' => true, 'insert_id' => $insert_id, 'emailed' => $emailed]);
exit;
