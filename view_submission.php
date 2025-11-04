<?php
// Authentication middleware - protect this page
require_once 'middleware/auth.php';
include 'connection/db_connect.php';
require_once 'function/decrypt.php';
include 'function/sendEmail.php';

// --- VALIDATION ---
if (!isset($_GET['id'])) {
    die("Error: No submission ID specified.");
}
$submission_id = (int)$_GET['id'];

// Attempt to decrypt email values when stored encrypted; otherwise return as-is
function resolve_email($value)
{
    $value = trim($value ?? '');
    if ($value === '') return '';
    // If it already looks like an email, use it
    if (strpos($value, '@') !== false) return $value;
    // Try decrypting base64(AES256CBC) format
    $decrypted = decryptData($value);
    if ($decrypted && strpos($decrypted, '@') !== false) {
        return $decrypted;
    }
    return $value; // fallback
}

// Helper to determine contrasting text color for a background hex
function getContrastingTextColor($hex)
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6) {
        return '#ffffff';
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    // YIQ contrast formula
    $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    return ($yiq >= 128) ? '#000000' : '#ffffff';
}

// --- ACTION HANDLER: Update Status and Remarks ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status_remarks') {
    $new_status = $conn->real_escape_string($_POST['status']);
    $new_remarks = !empty($_POST['remarks']) ? $conn->real_escape_string($_POST['remarks']) : null;

    $stmt = $conn->prepare("UPDATE submissions SET status = ?, remarks = ? WHERE id = ?");
    if (!$stmt) { // Check if prepare failed
        die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }

    $stmt->bind_param("ssi", $new_status, $new_remarks, $submission_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Status and remarks updated successfully.'];

        // Send status update email to the applicant
        if ($infoStmt = $conn->prepare("SELECT u.email AS user_email FROM submissions s LEFT JOIN users u ON s.user_id = u.id WHERE s.id = ?")) {
            $infoStmt->bind_param('i', $submission_id);
            $infoStmt->execute();
            $resInfo = $infoStmt->get_result();
            if ($row = $resInfo->fetch_assoc()) {
                $receiver = resolve_email($row['user_email'] ?? '');
                if ($receiver !== '') {
                    $subject = $ADMISSION_UPDATE_SUBJECT;
                    $body = $ADMISSION_UPDATE_TEMPLATE;
                    $status = $new_status;
                    $remarks = $new_remarks; // may be null

                    $email_body = str_replace(
                        ['{{status}}', '{{remarks}}'],
                        [$status, $remarks ?? ''],
                        $body
                    );

                    send_status_email($receiver, $subject, $email_body);
                }
            }
            $infoStmt->close();
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error updating status/remarks: ' . $stmt->error];
        // ------------------------------------
    }
    $stmt->close();
}

// --- ACTION HANDLER: Assign Room ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_room') {
    $target_schedule_id = isset($_POST['target_schedule_id']) ? (int)$_POST['target_schedule_id'] : 0;
    $redirect_url = 'view_submission.php?id=' . urlencode($submission_id);

    if ($target_schedule_id <= 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Please select a valid room to assign.'];
        header("Location: $redirect_url");
        exit;
    }

    // Get target capacity
    $target_capacity = 0;
    if ($stmtCap = $conn->prepare("SELECT capacity FROM ExamSchedules WHERE schedule_id = ?")) {
        $stmtCap->bind_param('i', $target_schedule_id);
        $stmtCap->execute();
        $resCap = $stmtCap->get_result();
        if ($resCap && ($rowCap = $resCap->fetch_assoc())) {
            $target_capacity = (int)$rowCap['capacity'];
        }
        $stmtCap->close();
    }

    // Current booked count for target
    $target_booked = 0;
    if ($stmtCount = $conn->prepare("SELECT COUNT(*) AS cnt FROM ExamRegistrations WHERE schedule_id = ?")) {
        $stmtCount->bind_param('i', $target_schedule_id);
        $stmtCount->execute();
        $resCount = $stmtCount->get_result();
        if ($resCount && ($rowCount = $resCount->fetch_assoc())) {
            $target_booked = (int)$rowCount['cnt'];
        }
        $stmtCount->close();
    }

    if ($target_capacity <= 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Target room has invalid capacity.'];
        header("Location: $redirect_url");
        exit;
    }

    // Fetch schedule details for email placeholders
    $exam_floor = '';
    $exam_room = '';
    $exam_datetime = null;
    if ($stmtSched = $conn->prepare("SELECT floor, room, start_date_and_time FROM ExamSchedules WHERE schedule_id = ?")) {
        $stmtSched->bind_param('i', $target_schedule_id);
        $stmtSched->execute();
        $resSched = $stmtSched->get_result();
        if ($resSched && ($rowSched = $resSched->fetch_assoc())) {
            $exam_floor = trim($rowSched['floor'] ?? '');
            $exam_room = trim($rowSched['room'] ?? '');
            $exam_datetime = $rowSched['start_date_and_time'] ?? null;
        }
        $stmtSched->close();
    }

    // Compute email template values
    $exam_date = '';
    $exam_time = '';
    $exam_venue = '';
    if (!empty($exam_datetime)) {
        $ts = strtotime($exam_datetime);
        if ($ts !== false) {
            // Date: November 31, 2025
            $exam_date = date('F j, Y', $ts);
            // Time: 12-hour with A.M/P.M
            $exam_time = date('g:i A', $ts);
            $exam_time = str_replace(['AM', 'PM'], ['A.M', 'P.M'], $exam_time);
        }
    }
    if ($exam_floor !== '' || $exam_room !== '') {
        $exam_venue = trim(($exam_floor !== '' ? $exam_floor : '') . (($exam_floor !== '' && $exam_room !== '') ? ' • ' : '') . ($exam_room !== '' ? $exam_room : ''));
    }

    // Map this submission -> user_id
    $user_id = 0;
    if ($stmtUser = $conn->prepare("SELECT user_id FROM submissions WHERE id = ?")) {
        $stmtUser->bind_param('i', $submission_id);
        $stmtUser->execute();
        $resU = $stmtUser->get_result();
        $rowU = $resU ? $resU->fetch_assoc() : null;
        $user_id = $rowU ? (int)$rowU['user_id'] : 0;
        $stmtUser->close();
    }

    if ($user_id <= 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid user associated with this submission.'];
        header("Location: $redirect_url");
        exit;
    }

    // Resolve receiver email for notifications
    $receiver = '';
    if ($infoStmt = $conn->prepare("SELECT u.email AS user_email FROM submissions s LEFT JOIN users u ON s.user_id = u.id WHERE s.id = ?")) {
        $infoStmt->bind_param('i', $submission_id);
        $infoStmt->execute();
        $resInfo = $infoStmt->get_result();
        if ($row = $resInfo->fetch_assoc()) {
            $receiver = resolve_email($row['user_email'] ?? '');
        }
        $infoStmt->close();
    }

    // Check existing registration for this user
    $existingSchedId = null;
    $existingRegId = null;
    if ($findStmt = $conn->prepare("SELECT registration_id, schedule_id FROM ExamRegistrations WHERE user_id = ? LIMIT 1")) {
        $findStmt->bind_param('i', $user_id);
        $findStmt->execute();
        $resF = $findStmt->get_result();
        $rowF = $resF ? $resF->fetch_assoc() : null;
        if ($rowF) {
            $existingRegId = (int)$rowF['registration_id'];
            $existingSchedId = (int)$rowF['schedule_id'];
        }
        $findStmt->close();
    }

    // If already assigned, move to the selected room
    if ($existingSchedId !== null) {
        if ($existingSchedId === $target_schedule_id) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Applicant is already in the selected room.'];
            header("Location: $redirect_url");
            exit;
        }

        // Capacity check before moving
        if ($target_booked >= $target_capacity) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Selected room is already at capacity.'];
            header("Location: $redirect_url");
            exit;
        }

        // Perform move (update registration schedule_id)
        if ($updReg = $conn->prepare("UPDATE ExamRegistrations SET schedule_id = ? WHERE registration_id = ?")) {
            $updReg->bind_param('ii', $target_schedule_id, $existingRegId);
            if ($updReg->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Applicant moved to room #' . $target_schedule_id . ' successfully.'];
                // Send exam schedule email to the applicant
                if ($receiver !== '' && !empty($EXAM_SCHEDULE_TEMPLATE) && !empty($EXAM_SCHEDULE_SUBJECT)) {
                    $email_body = str_replace(
                        ['{{exam_date}}', '{{exam_time}}', '{{exam_venue}}'],
                        [$exam_date, $exam_time, $exam_venue],
                        $EXAM_SCHEDULE_TEMPLATE
                    );
                    send_status_email($receiver, $EXAM_SCHEDULE_SUBJECT, $email_body);
                }
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Database error while moving applicant.'];
                $updReg->close();
                header("Location: $redirect_url");
                exit;
            }
            $updReg->close();
        }

        // Recompute and update statuses for source and target rooms
        // Source room
        $src_capacity = 0;
        if ($stmtSrcCap = $conn->prepare("SELECT capacity FROM ExamSchedules WHERE schedule_id = ?")) {
            $stmtSrcCap->bind_param('i', $existingSchedId);
            $stmtSrcCap->execute();
            $resSrcCap = $stmtSrcCap->get_result();
            if ($resSrcCap && ($rowSC = $resSrcCap->fetch_assoc())) {
                $src_capacity = (int)$rowSC['capacity'];
            }
            $stmtSrcCap->close();
        }
        $src_booked = 0;
        if ($stmtSrcCount = $conn->prepare("SELECT COUNT(*) AS cnt FROM ExamRegistrations WHERE schedule_id = ?")) {
            $stmtSrcCount->bind_param('i', $existingSchedId);
            $stmtSrcCount->execute();
            $resSrcCount = $stmtSrcCount->get_result();
            if ($resSrcCount && ($rowSCO = $resSrcCount->fetch_assoc())) {
                $src_booked = (int)$rowSCO['cnt'];
            }
            $stmtSrcCount->close();
        }
        $srcStatus = ($src_booked >= $src_capacity) ? 'Full' : 'Open';
        if ($stmtUS = $conn->prepare("UPDATE ExamSchedules SET status = ? WHERE schedule_id = ?")) {
            $stmtUS->bind_param('si', $srcStatus, $existingSchedId);
            $stmtUS->execute();
            $stmtUS->close();
        }

        // Target room
        $t_booked = 0;
        if ($stmtTC = $conn->prepare("SELECT COUNT(*) AS cnt FROM ExamRegistrations WHERE schedule_id = ?")) {
            $stmtTC->bind_param('i', $target_schedule_id);
            $stmtTC->execute();
            $resTC = $stmtTC->get_result();
            if ($resTC && ($rowTC = $resTC->fetch_assoc())) {
                $t_booked = (int)$rowTC['cnt'];
            }
            $stmtTC->close();
        }
        $tStatus = ($t_booked >= $target_capacity) ? 'Full' : 'Open';
        if ($stmtUT = $conn->prepare("UPDATE ExamSchedules SET status = ? WHERE schedule_id = ?")) {
            $stmtUT->bind_param('si', $tStatus, $target_schedule_id);
            $stmtUT->execute();
            $stmtUT->close();
        }

        header("Location: $redirect_url");
        exit;
    }

    // Capacity check
    if ($target_booked >= $target_capacity) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Selected room is already at capacity.'];
        header("Location: $redirect_url");
        exit;
    }

    // Next registration_id for insert
    $nextRegId = 1;
    if ($resMax = $conn->query("SELECT IFNULL(MAX(registration_id), 0) + 1 AS next_id FROM ExamRegistrations")) {
        if ($rowMax = $resMax->fetch_assoc()) {
            $nextRegId = (int)$rowMax['next_id'];
        }
    }

    // Insert registration
    if ($insertStmt = $conn->prepare("INSERT INTO ExamRegistrations (registration_id, user_id, schedule_id) VALUES (?, ?, ?)")) {
        $insertStmt->bind_param('iii', $nextRegId, $user_id, $target_schedule_id);
        if ($insertStmt->execute()) {
            $target_booked++;
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Applicant assigned to room #' . $target_schedule_id . ' successfully.'];
            // Send exam schedule email to the applicant
            if ($receiver !== '' && !empty($EXAM_SCHEDULE_TEMPLATE) && !empty($EXAM_SCHEDULE_SUBJECT)) {
                $email_body = str_replace(
                    ['{{exam_date}}', '{{exam_time}}', '{{exam_venue}}'],
                    [$exam_date, $exam_time, $exam_venue],
                    $EXAM_SCHEDULE_TEMPLATE
                );
                send_status_email($receiver, $EXAM_SCHEDULE_SUBJECT, $email_body);
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Database error while assigning applicant.'];
        }
        $insertStmt->close();
    }

    // Update target room status
    $newTargetStatus = ($target_booked >= $target_capacity) ? 'Full' : 'Open';
    if ($stmtUpd = $conn->prepare("UPDATE ExamSchedules SET status = ? WHERE schedule_id = ?")) {
        $stmtUpd->bind_param('si', $newTargetStatus, $target_schedule_id);
        $stmtUpd->execute();
        $stmtUpd->close();
    }

    header("Location: $redirect_url");
    exit;
}


// --- DATA FETCHING ---

// 1. Get main submission info (Applicant, Cycle, etc.)
$sql_main = $conn->prepare("SELECT s.*, at.name as applicant_type, c.cycle_name, u.email, at.admission_cycle_id
                            FROM submissions s
                            LEFT JOIN applicant_types at ON s.applicant_type_id = at.id
                            LEFT JOIN admission_cycles c ON at.admission_cycle_id = c.id
                            LEFT JOIN users u ON s.user_id = u.id
                            WHERE s.id = ?");
$sql_main->bind_param("i", $submission_id);
$sql_main->execute();
$main_info = $sql_main->get_result()->fetch_assoc();
$sql_main->close();

if (!$main_info) {
    die("Error: Submission not found.");
}

// Prepare decrypted email for display (fallback to original if decrypt fails)
$display_email = null;
if (!empty($main_info['email'])) {
    $decrypted = decryptData($main_info['email']);
    $display_email = ($decrypted !== false && !empty($decrypted)) ? $decrypted : $main_info['email'];
}

$cycle_id = $main_info['admission_cycle_id']; // Needed for next queries

// 2. Get all TEXT answers, with their proper labels, in the correct order
$applicant_type_id = $main_info['applicant_type_id']; // Get this from the first query

// Query 2
$sql_data = $conn->prepare("SELECT sd.field_value, ff.label, sd.field_name
                            FROM submission_data sd
                            LEFT JOIN form_fields ff ON sd.field_name = ff.name
                            LEFT JOIN form_steps fs ON ff.step_id = fs.id
                            WHERE sd.submission_id = ? AND fs.applicant_type_id = ?
                            ORDER BY fs.step_order, ff.field_order");
$sql_data->bind_param("ii", $submission_id, $applicant_type_id);
$sql_data->execute();
$text_data = $sql_data->get_result()->fetch_all(MYSQLI_ASSOC);
$sql_data->close();

// 3. Get all FILE answers, with their proper labels, in the correct order
$sql_files = $conn->prepare("SELECT sf.original_filename, sf.file_path, ff.label, sf.field_name
                             FROM submission_files sf
                             LEFT JOIN form_fields ff ON sf.field_name = ff.name
                             LEFT JOIN form_steps fs ON ff.step_id = fs.id
                             WHERE sf.submission_id = ? AND fs.applicant_type_id = ?
                             ORDER BY fs.step_order, ff.field_order");
$sql_files->bind_param("ii", $submission_id, $applicant_type_id);
$sql_files->execute();
$file_data = $sql_files->get_result()->fetch_all(MYSQLI_ASSOC);
$sql_files->close();

// 4. Get all possible statuses from the new table (include hex colors)
$sql_statuses = "SELECT name, hex_color FROM statuses ORDER BY name, name";
$result_statuses = $conn->query($sql_statuses);
$possible_statuses = [];
$status_color_map = [];
if ($result_statuses) {
    while ($row = $result_statuses->fetch_assoc()) {
        $possible_statuses[] = $row['name']; // Store just the name
        if (!empty($row['hex_color'])) {
            $status_color_map[$row['name']] = $row['hex_color'];
        }
    }
}

// 4b. Build schedules list and booked counts for Assign Room UI
$schedules = [];
if ($resSched = $conn->query("SELECT schedule_id, floor, room, capacity, start_date_and_time, status FROM ExamSchedules ORDER BY start_date_and_time ASC")) {
    while ($row = $resSched->fetch_assoc()) {
        $schedules[(int)$row['schedule_id']] = $row;
    }
}
$booked_map = [];
if ($resBC = $conn->query("SELECT schedule_id, COUNT(*) AS cnt FROM ExamRegistrations GROUP BY schedule_id")) {
    while ($row = $resBC->fetch_assoc()) {
        $booked_map[(int)$row['schedule_id']] = (int)$row['cnt'];
    }
}

// 4c. Get current room assignment for this applicant (if any)
$current_room = null;
if (!empty($main_info['user_id'])) {
    if ($stmtCur = $conn->prepare("SELECT er.schedule_id, es.floor, es.room, es.start_date_and_time FROM ExamRegistrations er LEFT JOIN ExamSchedules es ON er.schedule_id = es.schedule_id WHERE er.user_id = ? LIMIT 1")) {
        $stmtCur->bind_param('i', $main_info['user_id']);
        $stmtCur->execute();
        $resCur = $stmtCur->get_result();
        if ($resCur && ($rowCur = $resCur->fetch_assoc())) {
            $current_room = $rowCur;
        }
        $stmtCur->close();
    }
}

// 5. Get all active remark templates
$sql_remarks = "SELECT remark_text FROM remark_templates WHERE is_active = 1 ORDER BY id";
$result_remarks = $conn->query($sql_remarks);
$remark_templates = [];
if ($result_remarks) {
    while ($row = $result_remarks->fetch_assoc()) {
        $remark_templates[] = $row['remark_text'];
    }
}

$conn->close(); // Close connection after all queries are done
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Submission #<?php echo $submission_id; ?> - Student Success Office</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .submission-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--spacing-lg);
            background-color: var(--color-background);
        }

        .top-nav {
             margin-bottom: var(--spacing-md);
             padding-bottom: var(--spacing-sm);
             border-bottom: 1px solid var(--color-border);
         }

         .back-link {
             display: inline-flex;
             align-items: center;
             gap: var(--spacing-xs);
             color: #666;
             text-decoration: none;
             font-size: 14px;
             font-weight: 500;
             transition: color var(--transition-fast);
         }

         .back-link:hover {
             color: var(--color-primary);
         }

         .back-link svg {
             width: 16px;
             height: 16px;
             stroke: currentColor;
         }

        .submission-header {
            background: linear-gradient(135deg, var(--color-primary), var(--color-accent));
            color: white;
            padding: var(--spacing-lg);
            border-radius: var(--radius-lg);
            margin-bottom: var(--spacing-lg);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .submission-header h1 {
            margin: 0;
            font-size: var(--font-size-lg);
            font-weight: 600;
        }

        .submission-header .breadcrumb {
            margin-top: var(--spacing-xs);
            opacity: 0.9;
            font-size: var(--font-size-sm);
        }

        .submission-header .breadcrumb a {
            color: white;
            text-decoration: none;
            opacity: 0.8;
            transition: opacity var(--transition-fast);
        }

        .submission-header .breadcrumb a:hover {
            opacity: 1;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }

        .card {
            background: var(--color-card);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid var(--color-border);
            margin-bottom: var(--spacing-lg);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-md);
            padding-bottom: var(--spacing-sm);
            border-bottom: 1px solid var(--color-border);
        }

        .card-header h2, .card-header h3 {
            margin: 0;
            font-size: var(--font-size-md);
            font-weight: 600;
            color: var(--color-text);
        }

        .card-header .icon {
             width: 32px;
             height: 32px;
             background: var(--color-primary);
             border-radius: var(--radius-sm);
             display: flex;
             align-items: center;
             justify-content: center;
             color: white;
             flex-shrink: 0;
         }

         .card-header .icon svg {
             width: 16px;
             height: 16px;
             stroke: currentColor;
         }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-md);
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-xs);
        }

        .info-label {
            font-size: var(--font-size-sm);
            font-weight: 500;
            color: var(--color-text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: var(--font-size-md);
            color: var(--color-text);
            font-weight: 500;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-md);
            font-size: var(--font-size-sm);
            font-weight: 500;
            text-transform: capitalize;
        }

        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .status-under_review { background: #dbeafe; color: #1e40af; }

        .answers-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .answer-item {
            padding: var(--spacing-sm) 0;
            border-bottom: 1px solid var(--color-border);
        }

        .answer-item:last-child {
            border-bottom: none;
        }

        .answer-label {
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: var(--spacing-xs);
        }

        .answer-value {
            color: var(--color-text-light);
            font-size: var(--font-size-sm);
        }

        .file-link {
             display: inline-flex;
             align-items: center;
             gap: var(--spacing-xs);
             color: var(--color-primary);
             text-decoration: none;
             font-weight: 500;
             transition: color var(--transition-fast);
         }

         .file-link:hover {
             color: var(--color-accent);
         }

         .file-link svg {
             width: 16px;
             height: 16px;
             stroke: currentColor;
         }

        .form-group {
            margin-bottom: var(--spacing-md);
        }

        .form-label {
            display: block;
            font-size: var(--font-size-sm);
            font-weight: 500;
            color: var(--color-text);
            margin-bottom: var(--spacing-xs);
        }

        .form-select,
        .form-textarea {
            width: 100%;
            padding: var(--spacing-sm);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            font-family: var(--font-base);
            font-size: var(--font-size-sm);
            color: var(--color-text);
            background-color: var(--color-card);
            transition: border-color var(--transition-fast);
        }

        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .quick-remarks {
            display: flex;
            flex-wrap: wrap;
            gap: var(--spacing-xs);
            margin-top: var(--spacing-sm);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-xs);
            padding: var(--spacing-sm) var(--spacing-md);
            border: none;
            border-radius: var(--radius-md);
            font-family: var(--font-base);
            font-size: var(--font-size-sm);
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .btn-primary {
            background: var(--color-primary);
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--color-hover);
            color: var(--color-text);
            border: 1px solid var(--color-border);
        }

        .btn-secondary:hover {
            background: var(--color-border);
        }

        .btn-outline {
            background: transparent;
            color: var(--color-primary);
            border: 1px solid var(--color-primary);
        }

        .btn-outline:hover {
             background: var(--color-primary);
             color: white;
         }

         .btn svg {
             width: 16px;
             height: 16px;
             stroke: currentColor;
         }

        .actions-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: var(--spacing-lg);
            padding-top: var(--spacing-lg);
            border-top: 1px solid var(--color-border);
        }

        .empty-state {
            text-align: center;
            padding: var(--spacing-lg);
            color: var(--color-text-light);
            font-style: italic;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .submission-container {
                padding: var(--spacing-md);
            }
            
            .actions-footer {
                flex-direction: column;
                gap: var(--spacing-sm);
            }
        }
    </style>
</head>

<body>
    <?php if (isset($_SESSION['message'])) { echo '<script>window.__FLASH_MESSAGE__ = ' . json_encode($_SESSION['message']) . ';</script>'; unset($_SESSION['message']); } ?>
    
    <!-- Mobile Navbar -->
    <?php include "includes/mobile_navbar.php"; ?>

    <div class="layout">
        <!-- Sidebar -->
        <?php include "includes/sidebar.php"; ?>

        <main class="main-content">
            <div class="submission-container">
                <!-- Top Navigation -->
                   <div class="top-nav">
                       <a href="applicant_management.php" class="back-link">
                           <i data-lucide="arrow-left"></i> Back to Admission List
                       </a>
                   </div>
                
                <!-- Header -->
                <div class="submission-header">
                    <h1>View Submission #<?php echo $submission_id; ?></h1>
                    <div class="breadcrumb">
                        <a href="applicants.php">Applicant Management</a> / View Submission
                    </div>
                </div>

                <!-- Content Grid -->
                <div class="content-grid">
                    <!-- Left Column - Submission Data -->
                    <div>
                        <!-- Applicant Information Card -->
                         <div class="card">
                             <div class="card-header">
                                 <div class="icon"><i data-lucide="user"></i></div>
                                 <h3>Submission Details</h3>
                             </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">Applicant Email</span>
                                    <span class="info-value"><?php echo htmlspecialchars($display_email ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Admission Cycle</span>
                                    <span class="info-value"><?php echo htmlspecialchars($main_info['cycle_name']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Submitted On</span>
                                    <span class="info-value"><?php echo date('M j, Y, g:i A', strtotime($main_info['submitted_at'])); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Status</span>
                                    <?php
                                        $status_name = $main_info['status'];
                                        $bg_hex = $status_color_map[$status_name] ?? null;
                                        if ($bg_hex) {
                                            $text_color = getContrastingTextColor($bg_hex);
                                            $style = 'background-color: ' . htmlspecialchars($bg_hex) . '; color: ' . htmlspecialchars($text_color) . ';';
                                        } else {
                                            $style = '';
                                        }
                                    ?>
                                    <span class="status-badge" style="<?php echo $style; ?>"><?php echo htmlspecialchars($status_name); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Form Answers Card -->
                         <div class="card">
                             <div class="card-header">
                                 <div class="icon"><i data-lucide="file-text"></i></div>
                                 <h3>Form Answers</h3>
                             </div>
                            <?php if (!empty($text_data)): ?>
                                <ul class="answers-list">
                                    <?php foreach ($text_data as $data): ?>
                                        <li class="answer-item">
                                            <div class="answer-label"><?php echo htmlspecialchars($data['label'] ?? $data['field_name']); ?></div>
                                            <div class="answer-value"><?php echo htmlspecialchars($data['field_value']); ?></div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="empty-state">No form answers found.</div>
                            <?php endif; ?>
                        </div>

                        <!-- Uploaded Files Card -->
                         <div class="card">
                             <div class="card-header">
                                 <div class="icon"><i data-lucide="paperclip"></i></div>
                                 <h3>Uploaded Files</h3>
                             </div>
                            <?php if (!empty($file_data)): ?>
                                <ul class="answers-list">
                                    <?php foreach ($file_data as $file): ?>
                                        <li class="answer-item">
                                            <div class="answer-label"><?php echo htmlspecialchars($file['label'] ?? $file['field_name']); ?></div>
                                            <div class="answer-value">
                                                <a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank" class="file-link">
                                                    <i data-lucide="file"></i> <?php echo htmlspecialchars($file['original_filename']); ?>
                                                </a>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="empty-state">No files were uploaded.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right Column - Admin Actions -->
                    <div>
                        <div class="card">
                            <div class="card-header">
                                <div class="icon"><i data-lucide="settings"></i></div>
                                <h2>Admin Actions</h2>
                            </div>
                            <form id="updateForm" action="view_submission.php?id=<?php echo $submission_id; ?>" method="post">
                                <input type="hidden" name="action" value="update_status_remarks">

                                <div class="form-group">
                                    <label for="status" class="form-label">Change Status</label>
                                    <select name="status" id="status" class="form-select">
                                        <?php foreach ($possible_statuses as $status_name): ?>
                                            <option value="<?php echo htmlspecialchars($status_name); ?>" <?php echo ($main_info['status'] === $status_name) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($status_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <?php if (!empty($remark_templates)): ?>
                                    <div class="form-group">
                                        <label class="form-label">Quick Remarks</label>
                                        <div class="quick-remarks">
                                            <?php foreach ($remark_templates as $template): ?>
                                                <button type="button" class="btn btn-secondary remark-template-btn" data-template="<?php echo htmlspecialchars($template); ?>">
                                                    <?php echo htmlspecialchars(strlen($template) > 20 ? substr($template, 0, 17) . '...' : $template); ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="form-group">
                                    <label for="remarks" class="form-label">Add/Edit Remarks</label>
                                    <textarea name="remarks" id="remarks" class="form-textarea" placeholder="Enter your remarks here..."><?php echo htmlspecialchars($main_info['remarks'] ?? ''); ?></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i data-lucide="save"></i> Update Status & Remarks
                                </button>
                            </form>
                        </div>

                        <!-- Assign Room Card -->
                        <div class="card" style="margin-top: var(--spacing-md);">
                            <div class="card-header">
                                <div class="icon"><i data-lucide="map-pin"></i></div>
                                <h2>Assign Room</h2>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Current Assignment</label>
                                <?php if ($current_room): ?>
                                    <div class="info-value">
                                        Room <?php echo htmlspecialchars($current_room['floor'] . ' • ' . $current_room['room']); ?> —
                                        <?php echo htmlspecialchars($current_room['start_date_and_time']); ?>
                                        (ID #<?php echo (int)$current_room['schedule_id']; ?>)
                                    </div>
                                <?php else: ?>
                                    <div class="info-value">Not assigned</div>
                                <?php endif; ?>
                            </div>
                            <form id="assignRoomForm" action="view_submission.php?id=<?php echo $submission_id; ?>" method="post">
                                <input type="hidden" name="action" value="assign_room">
                                <div class="form-group">
                                    <label for="target_schedule_id" class="form-label">Select Room</label>
                                    <select name="target_schedule_id" id="target_schedule_id" class="form-select">
                                        <?php foreach ($schedules as $sid => $sch): ?>
                                            <?php
                                                $floor = htmlspecialchars($sch['floor']);
                                                $room = htmlspecialchars($sch['room']);
                                                $cap = (int)$sch['capacity'];
                                                $starts = htmlspecialchars($sch['start_date_and_time']);
                                                $booked = isset($booked_map[$sid]) ? (int)$booked_map[$sid] : 0;
                                                $is_full = ($cap > 0 && $booked >= $cap);
                                                $label = $floor . ' • ' . $room . ' — ' . $starts . ' (' . $booked . '/' . $cap . ')';
                                            ?>
                                            <option value="<?php echo (int)$sid; ?>" <?php echo $is_full ? 'disabled' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i data-lucide="check"></i> Assign Room
                                </button>
                            </form>
                        </div>
                        <!-- Applicant Update Permission Card -->
                        <div class="card" style="margin-top: var(--spacing-md);">
                            <div class="card-header">
                                <div class="icon"><i data-lucide="refresh-cw"></i></div>
                                <h2>Applicant Update Permission</h2>
                            </div>
                            <div class="form-group">
                                <p class="info-value">Ask to allow the applicant to update their submission.</p>
                            </div>
                            <button type="button" class="btn btn-outline" id="allowUpdateBtn" data-user-id="<?php echo (int)$main_info['user_id']; ?>">
                                <i data-lucide="unlock"></i> Allow Applicant to Update
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Full-screen loader overlay (replicated from applicant_management.php) -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <div class="loading-text">Processing...</div>
        </div>
    </div>
    <style>
        /* Loading Overlay Styles (consistent with applicant_management.php) */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            backdrop-filter: blur(4px);
        }
        .loading-spinner { text-align: center; color: white; }
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px auto;
        }
        .loading-text { font-size: 18px; font-weight: 500; color: white; margin-top: 10px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>

    <script>
        // Global loader controls (consistent with applicant_management.php)
        function showLoader() {
            var loader = document.getElementById('loadingOverlay');
            if (loader) {
                // ensure loader is at top of stacking context
                document.body.appendChild(loader);
                loader.style.display = 'flex';
            }
        }
        function hideLoader() {
            var loader = document.getElementById('loadingOverlay');
            if (loader) {
                loader.style.display = 'none';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            function attachLoader(formId) {
                var form = document.getElementById(formId);
                if (!form) return;
                form.addEventListener('submit', function() {
                    showLoader();
                });
            }
            // Hook both forms
            attachLoader('updateForm');
            attachLoader('assignRoomForm');
        });
    </script>

    <script>
        // Flash message handling
        function showFeedbackModal(message, type) {
            const overlay = document.createElement('div');
            overlay.style.position = 'fixed';
            overlay.style.left = '0';
            overlay.style.top = '0';
            overlay.style.width = '100%';
            overlay.style.height = '100%';
            overlay.style.backgroundColor = 'rgba(0,0,0,0.4)';
            overlay.style.zIndex = '1100';
            overlay.style.display = 'flex';
            overlay.style.alignItems = 'center';
            overlay.style.justifyContent = 'center';

            const box = document.createElement('div');
            box.style.background = 'white';
            box.style.borderRadius = '16px';
            box.style.padding = '20px';
            box.style.maxWidth = '420px';
            box.style.width = '90%';
            box.style.boxShadow = '0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04)';

            const color = type === 'success' ? '#38a169' : '#e53e3e';
            const title = type === 'success' ? 'Success' : 'Error';

            box.innerHTML = '<h3 style="margin:0 0 12px 0;color:'+color+';font-size:1.2rem;font-weight:600;">'+title+'</h3>'
                          + '<p style="margin:0 0 16px 0;color:#2d3748;font-size:0.95rem;">'+message+'</p>'
                          + '<div style="display:flex;gap:8px;justify-content:flex-end;">'
                          + '  <button id="feedbackCloseBtn" style="background:#4a5568;color:#fff;border:none;border-radius:8px;padding:8px 14px;cursor:pointer;">Close</button>'
                          + '</div>';

            overlay.appendChild(box);
            document.body.appendChild(overlay);

            document.getElementById('feedbackCloseBtn').addEventListener('click', () => {
                overlay.remove();
            });

            setTimeout(() => {
                if (document.body.contains(overlay)) overlay.remove();
            }, 4000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (window.__FLASH_MESSAGE__ && window.__FLASH_MESSAGE__.text) {
                showFeedbackModal(window.__FLASH_MESSAGE__.text, window.__FLASH_MESSAGE__.type || 'success');
                delete window.__FLASH_MESSAGE__;
            }

            const remarksTextarea = document.getElementById('remarks');
            const templateButtons = document.querySelectorAll('.remark-template-btn');

            templateButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const template = this.dataset.template;
                    // Append template to existing text (or replace if you prefer)
                    // Add a newline if textarea isn't empty
                    remarksTextarea.value += (remarksTextarea.value ? "\n" : "") + template;
                    remarksTextarea.focus(); // Put cursor in textarea
                });
            });
        });
    </script>

    <script>
        // Allow Update confirmation modal and update handler
        document.addEventListener('DOMContentLoaded', function() {
            const allowBtn = document.getElementById('allowUpdateBtn');
            if (!allowBtn) return;

            const userId = allowBtn.dataset.userId;

            allowBtn.addEventListener('click', function() {
                showConfirmModal(
                    'Allow this applicant to update their application submission?',
                    function onConfirm() {
                        updateCanUpdate(userId);
                    }
                );
            });

            function showConfirmModal(message, onConfirm) {
                const overlay = document.createElement('div');
                overlay.style.position = 'fixed';
                overlay.style.left = '0';
                overlay.style.top = '0';
                overlay.style.width = '100%';
                overlay.style.height = '100%';
                overlay.style.backgroundColor = 'rgba(0,0,0,0.4)';
                overlay.style.zIndex = '1200';
                overlay.style.display = 'flex';
                overlay.style.alignItems = 'center';
                overlay.style.justifyContent = 'center';

                const box = document.createElement('div');
                box.style.background = 'white';
                box.style.borderRadius = '16px';
                box.style.padding = '20px';
                box.style.maxWidth = '460px';
                box.style.width = '90%';
                box.style.boxShadow = '0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04)';
                box.innerHTML = '<h3 style="margin:0 0 12px 0;color:#1a202c;font-size:1.2rem;font-weight:600;">Confirm Action</h3>'
                    + '<p style="margin:0 0 16px 0;color:#2d3748;font-size:0.95rem;">' + message + '</p>'
                    + '<div style="display:flex;gap:8px;justify-content:flex-end;">'
                    + '  <button id="confirmAllowUpdate" style="background:#2563eb;color:#fff;border:none;border-radius:8px;padding:8px 14px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check"><path d="M20 6 9 17l-5-5"/></svg> Confirm</button>'
                    + '  <button id="cancelAllowUpdate" style="background:#4a5568;color:#fff;border:none;border-radius:8px;padding:8px 14px;cursor:pointer;">Cancel</button>'
                    + '</div>';

                overlay.appendChild(box);
                document.body.appendChild(overlay);

                document.getElementById('confirmAllowUpdate').addEventListener('click', function() {
                    overlay.remove();
                    if (typeof onConfirm === 'function') onConfirm();
                });
                document.getElementById('cancelAllowUpdate').addEventListener('click', function() {
                    overlay.remove();
                });
            }

            function updateCanUpdate(uid) {
                if (!uid || parseInt(uid, 10) <= 0) {
                    showFeedbackModal('Invalid user ID.', 'error');
                    return;
                }
                showLoader();
                fetch('update_admission_can_update.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ user_id: uid })
                })
                .then(res => res.json())
                .then(data => {
                    hideLoader();
                    if (data && data.ok) {
                        showFeedbackModal('Applicant can now update their submission.', 'success');
                    } else {
                        showFeedbackModal((data && data.error) ? data.error : 'Failed to update permission.', 'error');
                    }
                })
                .catch(err => {
                    hideLoader();
                    showFeedbackModal('Network error: ' + err, 'error');
                });
            }
        });
    </script>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>

</html>