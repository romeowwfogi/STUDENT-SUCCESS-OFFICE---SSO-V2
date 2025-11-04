<?php
// Authentication middleware - protect this page
require_once 'middleware/auth.php';
include 'connection/db_connect.php';
require_once 'function/decrypt.php';
include 'function/sendEmail.php';

// Lightweight email sender to local email API

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

// --- ACTION HANDLER: Process form submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fetch valid statuses from DB once
    $status_names = [];
    $status_res = $conn->query("SELECT name FROM statuses ORDER BY name ASC, name ASC");
    if ($status_res) {
        while ($row = $status_res->fetch_assoc()) {
            $status_names[] = $row['name'];
        }
        $status_res->close();
    }

    // --- UPDATE APPLICANT STATUS (single) ---
    if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
        $submission_id = isset($_POST['submission_id']) ? (int)$_POST['submission_id'] : 0;
        $new_status = isset($_POST['status']) ? trim($_POST['status']) : '';
        $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : null;
        $allow_update = isset($_POST['allow_update']) ? (int)$_POST['allow_update'] : 0;

        // Get cycle_id from POST or GET to preserve context
        $cycle_id = isset($_POST['cycle_id']) ? (int)$_POST['cycle_id'] : (isset($_GET['cycle_id']) ? (int)$_GET['cycle_id'] : null);
        $redirect_url = 'applicant_management.php' . ($cycle_id ? '?cycle_id=' . $cycle_id : '');

        if ($submission_id <= 0 || $new_status === '') {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid submission or status.'];
            header("Location: " . $redirect_url);
            exit;
        }

        if (!in_array($new_status, $status_names)) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid status selected: ' . htmlspecialchars($new_status)];
            header("Location: " . $redirect_url);
            exit;
        }

        $stmt = $conn->prepare("UPDATE submissions SET status = ?, remarks = ? WHERE id = ?");
        $stmt->bind_param('ssi', $new_status, $remarks, $submission_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Applicant status updated successfully.'];
            // Optionally allow applicant to update their application submission
            if ($allow_update === 1) {
                if ($usrStmt = $conn->prepare("SELECT user_id FROM submissions WHERE id = ?")) {
                    $usrStmt->bind_param('i', $submission_id);
                    $usrStmt->execute();
                    $resUsr = $usrStmt->get_result();
                    if ($urow = $resUsr->fetch_assoc()) {
                        $uid = (int)$urow['user_id'];
                        if ($uid > 0) {
                            if ($updAdm = $conn->prepare("UPDATE admission_submission SET can_update = 1, updated_at = NOW() WHERE user_id = ?")) {
                                $updAdm->bind_param('i', $uid);
                                $updAdm->execute();
                                $updAdm->close();
                            }
                        }
                    }
                    $usrStmt->close();
                }
            }
            // Fetch recipient email and send notification (no name lookup)
            if ($infoStmt = $conn->prepare("SELECT u.email AS user_email FROM submissions s LEFT JOIN users u ON s.user_id = u.id WHERE s.id = ?")) {
                $infoStmt->bind_param('i', $submission_id);
                $infoStmt->execute();
                $resInfo = $infoStmt->get_result();
                if ($row = $resInfo->fetch_assoc()) {
                    $receiver = resolve_email($row['user_email'] ?? '');
                    if ($receiver !== '') {
                        $subject = $ADMISSION_UPDATE_SUBJECT;
                        $status = $new_status;
                        // $remarks = $remarks; // already set somewhere else?
                        $body = $ADMISSION_UPDATE_TEMPLATE;

                        $email_body = str_replace(
                            ['{{status}}', '{{remarks}}'],
                            [$status, $remarks],
                            $body
                        );

                        // ✅ send the replaced content, not the template
                        send_status_email($receiver, $subject, $email_body);
                    }
                }
                $infoStmt->close();
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $conn->error];
        }
        $stmt->close();
        header("Location: " . $redirect_url);
        exit;
    }

    // --- BULK STATUS CHANGE ---
    if (isset($_POST['action']) && $_POST['action'] === 'bulk_status') {
        $ids = isset($_POST['ids']) ? $_POST['ids'] : [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $submission_ids = array_values(array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        }));
        $new_status = isset($_POST['status']) ? trim($_POST['status']) : '';
        $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : null;
        $allow_update = isset($_POST['allow_update']) ? (int)$_POST['allow_update'] : 0;

        // Get cycle_id from POST or GET to preserve context
        $cycle_id = isset($_POST['cycle_id']) ? (int)$_POST['cycle_id'] : (isset($_GET['cycle_id']) ? (int)$_GET['cycle_id'] : null);
        $redirect_url = 'applicant_management.php' . ($cycle_id ? '?cycle_id=' . $cycle_id : '');

        if (empty($submission_ids)) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'No valid applicants selected for status update.'];
            header("Location: $redirect_url");
            exit;
        }
        if ($new_status === '' || !in_array($new_status, $status_names)) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid status selected: ' . htmlspecialchars($new_status)];
            header("Location: $redirect_url");
            exit;
        }

        $updated_count = 0;
        $successful_ids = [];
        $stmt = $conn->prepare("UPDATE submissions SET status = ?, remarks = ? WHERE id = ?");
        foreach ($submission_ids as $id) {
            $stmt->bind_param('ssi', $new_status, $remarks, $id);
            if ($stmt->execute()) {
                $updated_count += $stmt->affected_rows;
                if ($stmt->affected_rows > 0) {
                    $successful_ids[] = $id;
                }
            }
        }
        $stmt->close();

        if ($updated_count > 0) {
            $submission_text = $updated_count === 1 ? 'applicant' : 'applicants';
            $_SESSION['message'] = ['type' => 'success', 'text' => "Successfully updated status to '$new_status' for $updated_count $submission_text."];
            // Optionally allow applicants to update their application submissions
            if ($allow_update === 1 && !empty($successful_ids)) {
                $in_list = implode(',', array_map('intval', $successful_ids));
                $map_sql = "SELECT id AS sid, user_id FROM submissions WHERE id IN ($in_list)";
                if ($resMap = $conn->query($map_sql)) {
                    if ($updAdm = $conn->prepare("UPDATE admission_submission SET can_update = 1, updated_at = NOW() WHERE user_id = ?")) {
                        while ($mr = $resMap->fetch_assoc()) {
                            $uid = (int)$mr['user_id'];
                            if ($uid > 0) {
                                $updAdm->bind_param('i', $uid);
                                $updAdm->execute();
                            }
                        }
                        $updAdm->close();
                    }
                }
            }
            // Send emails to successfully updated applicants (no name lookup)
            if (!empty($successful_ids)) {
                $in_list = implode(',', array_map('intval', $successful_ids));
                $info_sql = "SELECT s.id AS submission_id, u.email AS user_email FROM submissions s LEFT JOIN users u ON s.user_id = u.id WHERE s.id IN ($in_list)";
                if ($resInfo = $conn->query($info_sql)) {
                    while ($ri = $resInfo->fetch_assoc()) {
                        $receiver = resolve_email($ri['user_email'] ?? '');
                        if ($receiver === '') {
                            continue;
                        }

                        $subject = $ADMISSION_UPDATE_SUBJECT;
                        $status = $new_status;
                        // $remarks = $remarks; // already set somewhere else?
                        $body = $ADMISSION_UPDATE_TEMPLATE;

                        $email_body = str_replace(
                            ['{{status}}', '{{remarks}}'],
                            [$status, $remarks],
                            $body
                        );

                        // ✅ send the replaced content, not the template
                        send_status_email($receiver, $subject, $email_body);
                    }
                }
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'No applicants were updated. Please check selections.'];
        }
        header("Location: $redirect_url");
        exit;
    }

    // --- BULK ASSIGN ROOM ---
    if (isset($_POST['action']) && $_POST['action'] === 'bulk_assign_room') {
        $cycle_id = isset($_POST['cycle_id']) ? (int)$_POST['cycle_id'] : (isset($_GET['cycle_id']) ? (int)$_GET['cycle_id'] : null);
        $redirect_url = 'applicant_management.php' . ($cycle_id ? '?cycle_id=' . $cycle_id : '');

        // Selected applicants are submission IDs from the table
        // Accept both 'submission_ids' and legacy 'ids' from the form
        $submission_ids = isset($_POST['submission_ids']) ? $_POST['submission_ids'] : (isset($_POST['ids']) ? $_POST['ids'] : []);
        if (!is_array($submission_ids)) {
            $submission_ids = [];
        }
        $submission_ids = array_values(array_filter(array_map('intval', $submission_ids), function ($id) {
            return $id > 0;
        }));

        $target_schedule_id = isset($_POST['target_schedule_id']) ? (int)$_POST['target_schedule_id'] : 0;
        $move_existing = isset($_POST['move_existing']) ? (int)$_POST['move_existing'] : 0;
        if ($target_schedule_id <= 0) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Please select a valid room to assign.'];
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

        // Compute email template values if schedule loaded
        $exam_date = '';
        $exam_time = '';
        $exam_venue = '';
        if (!empty($exam_datetime)) {
            $ts = strtotime($exam_datetime);
            if ($ts !== false) {
                // Date: November 31, 2025 (Month Day, Year)
                $exam_date = date('F j, Y', $ts);
                // Time: 12-hour with A.M/P.M
                $exam_time = date('g:i A', $ts);
                // Convert AM/PM to A.M/P.M
                $exam_time = str_replace(['AM', 'PM'], ['A.M', 'P.M'], $exam_time);
            }
        }
        if ($exam_floor !== '' || $exam_room !== '') {
            $exam_venue = trim(($exam_floor !== '' ? $exam_floor : '') . (($exam_floor !== '' && $exam_room !== '') ? ' • ' : '') . ($exam_room !== '' ? $exam_room : ''));
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

        if (empty($submission_ids) || $target_capacity <= 0) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'No applicants selected or target room has invalid capacity.'];
            header("Location: $redirect_url");
            exit;
        }

        // Next registration_id for inserts
        $nextRegId = 1;
        if ($resMax = $conn->query("SELECT IFNULL(MAX(registration_id), 0) + 1 AS next_id FROM ExamRegistrations")) {
            if ($rowMax = $resMax->fetch_assoc()) {
                $nextRegId = (int)$rowMax['next_id'];
            }
        }

        // Prepare statements
        $stmtUser = $conn->prepare("SELECT user_id FROM submissions WHERE id = ?");
        $findStmt = $conn->prepare("SELECT registration_id, schedule_id FROM ExamRegistrations WHERE user_id = ? LIMIT 1");
        $insertStmt = $conn->prepare("INSERT INTO ExamRegistrations (registration_id, user_id, schedule_id) VALUES (?, ?, ?)");

        // Preload display names and emails for summary and notifications
        $names = [];
        $emails = [];
        if (!empty($submission_ids)) {
            $in_list = implode(',', array_map('intval', $submission_ids));
            $name_sql = "SELECT s.id AS submission_id, u.email AS user_email, d_fname.field_value AS first_name, d_lname.field_value AS last_name
                         FROM submissions s
                         LEFT JOIN users u ON s.user_id = u.id
                         LEFT JOIN submission_data d_fname ON (s.id = d_fname.submission_id AND d_fname.field_name = 'first_name')
                         LEFT JOIN submission_data d_lname ON (s.id = d_lname.submission_id AND d_lname.field_name = 'last_name')
                         WHERE s.id IN ($in_list)";
            if ($resNames = $conn->query($name_sql)) {
                while ($rn = $resNames->fetch_assoc()) {
                    $sid = (int)$rn['submission_id'];
                    $fn = trim($rn['first_name'] ?? '');
                    $ln = trim($rn['last_name'] ?? '');
                    $email = trim($rn['user_email'] ?? '');
                    $display = ($fn || $ln) ? trim($fn . ' ' . $ln) : ($email ?: ('Submission #' . $sid));
                    $names[$sid] = $display;
                    $emails[$sid] = $email;
                }
            }
        }

        $assigned = 0;
        $skipped_full_count = 0;
        $success_list = [];
        $skipped_list = [];
        $affected_source_rooms = [];
        // Track processed applicants by user_id to avoid double-processing duplicates
        $processed_users = [];
        foreach ($submission_ids as $sid) {
            if ($target_booked >= $target_capacity) {
                $skipped_full_count++;
                $skipped_list[] = ($names[$sid] ?? ('Submission #' . $sid)) . ' — room at capacity';
                continue;
            }

            // Map submission_id -> user_id
            $stmtUser->bind_param('i', $sid);
            $stmtUser->execute();
            $resU = $stmtUser->get_result();
            $rowU = $resU ? $resU->fetch_assoc() : null;
            $user_id = $rowU ? (int)$rowU['user_id'] : 0;
            if ($user_id <= 0) {
                $skipped_list[] = ($names[$sid] ?? ('Submission #' . $sid)) . ' — invalid user';
                continue;
            }

            // Skip duplicate selections of the same applicant
            if (isset($processed_users[$user_id])) {
                continue;
            }
            $processed_users[$user_id] = true;

            // Check existing registration
            $findStmt->bind_param('i', $user_id);
            $findStmt->execute();
            $resF = $findStmt->get_result();
            $rowF = $resF ? $resF->fetch_assoc() : null;

            if ($rowF) {
                $existingSchedId = (int)$rowF['schedule_id'];
                $existingRegId = (int)$rowF['registration_id'];
                if ($existingSchedId === $target_schedule_id) {
                    // Already in this room
                    $skipped_list[] = ($names[$sid] ?? ('Submission #' . $sid)) . ' — already assigned to selected room';
                    continue;
                }
                if ($move_existing) {
                    // Capacity check before moving
                    if ($target_booked >= $target_capacity) {
                        $skipped_full_count++;
                        $skipped_list[] = ($names[$sid] ?? ('Submission #' . $sid)) . ' — room at capacity';
                        continue;
                    }

                    // Perform move (update registration schedule_id)
                    if ($updReg = $conn->prepare("UPDATE ExamRegistrations SET schedule_id = ? WHERE registration_id = ?")) {
                        $updReg->bind_param('ii', $target_schedule_id, $existingRegId);
                        if ($updReg->execute()) {
                            $assigned++;
                            $target_booked++;
                            $success_list[] = ($names[$sid] ?? ('Submission #' . $sid));
                            $affected_source_rooms[$existingSchedId] = true;

                            // Send exam schedule email to the applicant
                            $receiver = resolve_email($emails[$sid] ?? '');
                            if ($receiver !== '' && !empty($EXAM_SCHEDULE_TEMPLATE) && !empty($EXAM_SCHEDULE_SUBJECT)) {
                                $email_body = str_replace(
                                    ['{{exam_date}}', '{{exam_time}}', '{{exam_venue}}'],
                                    [$exam_date, $exam_time, $exam_venue],
                                    $EXAM_SCHEDULE_TEMPLATE
                                );
                                send_status_email($receiver, $EXAM_SCHEDULE_SUBJECT, $email_body);
                            }
                        } else {
                            $skipped_list[] = ($names[$sid] ?? ('Submission #' . $sid)) . ' — database error while moving';
                        }
                        $updReg->close();
                    }
                    continue;
                } else {
                    // Already assigned to a different room; skip to avoid duplicate user
                    $skipped_list[] = ($names[$sid] ?? ('Submission #' . $sid)) . ' — already assigned to room #' . $existingSchedId;
                    continue;
                }
            }

            // Insert new registration
            $insertStmt->bind_param('iii', $nextRegId, $user_id, $target_schedule_id);
            if ($insertStmt->execute()) {
                $assigned++;
                $target_booked++;
                $success_list[] = ($names[$sid] ?? ('Submission #' . $sid));
                $nextRegId++;

                // Send exam schedule email to the applicant
                $receiver = resolve_email($emails[$sid] ?? '');
                if ($receiver !== '' && !empty($EXAM_SCHEDULE_TEMPLATE) && !empty($EXAM_SCHEDULE_SUBJECT)) {
                    $email_body = str_replace(
                        ['{{exam_date}}', '{{exam_time}}', '{{exam_venue}}'],
                        [$exam_date, $exam_time, $exam_venue],
                        $EXAM_SCHEDULE_TEMPLATE
                    );
                    send_status_email($receiver, $EXAM_SCHEDULE_SUBJECT, $email_body);
                }
            } else {
                $skipped_list[] = ($names[$sid] ?? ('Submission #' . $sid)) . ' — database error while assigning';
            }
        }

        // Update target room status
        $newTargetStatus = ($target_booked >= $target_capacity) ? 'Full' : 'Open';
        if ($stmtUpd = $conn->prepare("UPDATE ExamSchedules SET status = ? WHERE schedule_id = ?")) {
            $stmtUpd->bind_param('si', $newTargetStatus, $target_schedule_id);
            $stmtUpd->execute();
            $stmtUpd->close();
        }

        // Update statuses for any affected source rooms (due to moves)
        if (!empty($affected_source_rooms)) {
            foreach (array_keys($affected_source_rooms) as $srcId) {
                // Recompute counts and capacity, then update status
                $src_capacity = 0;
                if ($stmtSrcCap = $conn->prepare("SELECT capacity FROM ExamSchedules WHERE schedule_id = ?")) {
                    $stmtSrcCap->bind_param('i', $srcId);
                    $stmtSrcCap->execute();
                    $resSrcCap = $stmtSrcCap->get_result();
                    if ($resSrcCap && ($rowSC = $resSrcCap->fetch_assoc())) {
                        $src_capacity = (int)$rowSC['capacity'];
                    }
                    $stmtSrcCap->close();
                }
                $src_booked = 0;
                if ($stmtSrcCount = $conn->prepare("SELECT COUNT(*) AS cnt FROM ExamRegistrations WHERE schedule_id = ?")) {
                    $stmtSrcCount->bind_param('i', $srcId);
                    $stmtSrcCount->execute();
                    $resSrcCount = $stmtSrcCount->get_result();
                    if ($resSrcCount && ($rowSCO = $resSrcCount->fetch_assoc())) {
                        $src_booked = (int)$rowSCO['cnt'];
                    }
                    $stmtSrcCount->close();
                }

                $newSrcStatus = ($src_booked >= $src_capacity) ? 'Full' : 'Open';
                if ($stmtUpdSrc = $conn->prepare("UPDATE ExamSchedules SET status = ? WHERE schedule_id = ?")) {
                    $stmtUpdSrc->bind_param('si', $newSrcStatus, $srcId);
                    $stmtUpdSrc->execute();
                    $stmtUpdSrc->close();
                }
            }
        }

        // Build summary message
        $msg_lines = [];
        $msg_lines[] = 'Room #' . $target_schedule_id . ': ' . $assigned . ' assigned';
        if ($skipped_full_count > 0) {
            $msg_lines[] = $skipped_full_count . ' skipped due to capacity';
        }
        if (!empty($success_list)) {
            $msg_lines[] = '<br><strong>Assigned:</strong><br>• ' . implode('<br>• ', array_map('htmlspecialchars', $success_list));
        }
        if (!empty($skipped_list)) {
            $msg_lines[] = '<br><strong>Skipped:</strong><br>• ' . implode('<br>• ', array_map('htmlspecialchars', $skipped_list));
        }
        $_SESSION['message'] = ['type' => $assigned > 0 ? 'success' : 'error', 'text' => implode('<br>', $msg_lines)];
        header("Location: $redirect_url");
        exit;
    }
}

// --- ACTION HANDLER: Handle GET requests ---
if (isset($_GET['action'])) {
    // --- ACTION: Delete a submission ---
    if ($_GET['action'] === 'delete' && isset($_GET['id'])) {
        $submission_id = (int)$_GET['id'];
        $cycle_id = isset($_GET['cycle_id']) ? (int)$_GET['cycle_id'] : null;
        $redirect_url = 'applicant_management.php' . ($cycle_id ? '?cycle_id=' . $cycle_id : '');

        // First delete related submission_data
        $conn->query("DELETE FROM submission_data WHERE submission_id = $submission_id");

        // Then delete the submission
        $sql = "DELETE FROM submissions WHERE id = $submission_id";
        if ($conn->query($sql)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Applicant submission deleted successfully.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $conn->error];
        }
        header("Location: $redirect_url");
        exit;
    }

    // --- ACTION: Bulk Status Change (GET, legacy) ---
    if ($_GET['action'] === 'bulk_status' && isset($_GET['ids']) && isset($_GET['status'])) {
        $submission_ids = explode(',', $_GET['ids']);
        $submission_ids = array_map('intval', $submission_ids);
        $submission_ids = array_filter($submission_ids, function ($id) {
            return $id > 0;
        });

        $new_status = trim($_GET['status']);
        // Validate against statuses table
        $valid_statuses = [];
        $res = $conn->query("SELECT name FROM statuses ORDER BY name ASC, name ASC");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $valid_statuses[] = $r['name'];
            }
            $res->close();
        }

        if (!in_array($new_status, $valid_statuses)) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid status selected: ' . $new_status];
            $cycle_id = isset($_GET['cycle_id']) ? (int)$_GET['cycle_id'] : null;
            $redirect_url = 'applicant_management.php' . ($cycle_id ? '?cycle_id=' . $cycle_id : '');
            header("Location: " . $redirect_url);
            exit;
        }

        if (!empty($submission_ids)) {
            $updated_count = 0;
            $successful_ids = [];
            $stmt = $conn->prepare("UPDATE submissions SET status = ? WHERE id = ?");
            foreach ($submission_ids as $id) {
                $stmt->bind_param('si', $new_status, $id);
                if ($stmt->execute()) {
                    $updated_count += $stmt->affected_rows;
                    if ($stmt->affected_rows > 0) {
                        $successful_ids[] = $id;
                    }
                }
            }
            $stmt->close();

            if ($updated_count > 0) {
                $submission_text = $updated_count === 1 ? 'applicant' : 'applicants';
                $_SESSION['message'] = ['type' => 'success', 'text' => "Successfully updated status to '$new_status' for $updated_count $submission_text."];
                // Send emails for legacy bulk GET (no name lookup)
                if (!empty($successful_ids)) {
                    $in_list = implode(',', array_map('intval', $successful_ids));
                    $info_sql = "SELECT s.id AS submission_id, u.email AS user_email FROM submissions s LEFT JOIN users u ON s.user_id = u.id WHERE s.id IN ($in_list)";
                    if ($resInfo = $conn->query($info_sql)) {
                        while ($ri = $resInfo->fetch_assoc()) {
                            $receiver = resolve_email($ri['user_email'] ?? '');
                            if ($receiver === '') {
                                continue;
                            }

                            $subject = $ADMISSION_UPDATE_SUBJECT;
                            $status = $new_status;
                            // $remarks = $remarks; // already set somewhere else?
                            $body = $ADMISSION_UPDATE_TEMPLATE;

                            $email_body = str_replace(
                                ['{{status}}', '{{remarks}}'],
                                [$status, $remarks],
                                $body
                            );

                            // ✅ send the replaced content, not the template
                            send_status_email($receiver, $subject, $email_body);
                        }
                    }
                }
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'No applicants were updated.'];
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'No valid applicants selected for status update.'];
        }

        $cycle_id = isset($_GET['cycle_id']) ? (int)$_GET['cycle_id'] : null;
        $redirect_url = 'applicant_management.php' . ($cycle_id ? '?cycle_id=' . $cycle_id : '');
        header("Location: " . $redirect_url);
        exit;
    }

    // --- ACTION: Update single applicant status (GET, legacy) ---
    if ($_GET['action'] === 'update_status' && isset($_GET['id']) && isset($_GET['status'])) {
        $submission_id = (int)$_GET['id'];
        $new_status = trim($_GET['status']);
        // Validate against statuses table
        $valid_statuses = [];
        $res = $conn->query("SELECT name FROM statuses ORDER BY name ASC, name ASC");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $valid_statuses[] = $r['name'];
            }
            $res->close();
        }

        if (!in_array($new_status, $valid_statuses)) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid status selected: ' . $new_status];
            $cycle_id = isset($_GET['cycle_id']) ? (int)$_GET['cycle_id'] : null;
            $redirect_url = 'applicant_management.php' . ($cycle_id ? '?cycle_id=' . $cycle_id : '');
            header("Location: " . $redirect_url);
            exit;
        }

        if ($submission_id > 0) {
            $stmt = $conn->prepare("UPDATE submissions SET status = ? WHERE id = ?");
            $stmt->bind_param('si', $new_status, $submission_id);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => "Successfully updated applicant status to '$new_status'."];
                    // Send email for legacy single GET (no name lookup)
                    if ($infoStmt = $conn->prepare("SELECT u.email AS user_email FROM submissions s LEFT JOIN users u ON s.user_id = u.id WHERE s.id = ?")) {
                        $infoStmt->bind_param('i', $submission_id);
                        $infoStmt->execute();
                        $resInfo = $infoStmt->get_result();
                        if ($row = $resInfo->fetch_assoc()) {
                            $receiver = resolve_email($row['user_email'] ?? '');
                            if ($receiver !== '') {
                                $subject = $ADMISSION_UPDATE_SUBJECT;
                                $status = $new_status;
                                // $remarks = $remarks; // already set somewhere else?
                                $body = $ADMISSION_UPDATE_TEMPLATE;

                                $email_body = str_replace(
                                    ['{{status}}', '{{remarks}}'],
                                    [$status, $remarks],
                                    $body
                                );

                                // ✅ send the replaced content, not the template
                                send_status_email($receiver, $subject, $email_body);
                            }
                        }
                        $infoStmt->close();
                    }
                } else {
                    $_SESSION['message'] = ['type' => 'error', 'text' => 'No applicant was updated. Please check if the applicant exists.'];
                }
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Error updating applicant status: ' . $conn->error];
            }

            $stmt->close();
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid applicant ID provided.'];
        }

        $cycle_id = isset($_GET['cycle_id']) ? (int)$_GET['cycle_id'] : null;
        $redirect_url = 'applicant_management.php' . ($cycle_id ? '?cycle_id=' . $cycle_id : '');
        header("Location: " . $redirect_url);
        exit;
    }
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

// Preload statuses (names and colors) and remark templates for UI
$available_statuses = [];
$status_color_map = [];
$resStatuses = $conn->query("SELECT name, hex_color FROM statuses ORDER BY name ASC, name ASC");
if ($resStatuses) {
    while ($r = $resStatuses->fetch_assoc()) {
        $available_statuses[] = $r['name'];
        if (!empty($r['hex_color'])) {
            $status_color_map[$r['name']] = $r['hex_color'];
        }
    }
    $resStatuses->close();
}

$remark_templates = [];
$resRemarks = $conn->query("SELECT remark_text FROM remark_templates WHERE is_active = 1 ORDER BY id ASC");
if ($resRemarks) {
    while ($r = $resRemarks->fetch_assoc()) {
        $remark_templates[] = $r['remark_text'];
    }
    $resRemarks->close();
}

// --- DATA FETCHING: Get applicants strictly for the selected cycle ---
$applicants = [];
if (!isset($_GET['cycle_id'])) {
    // Try to recover cycle_id from POST, referer, or session to preserve context
    $recovered_cycle_id = null;
    if (isset($_POST['cycle_id'])) {
        $recovered_cycle_id = (int)$_POST['cycle_id'];
    } elseif (!empty($_SERVER['HTTP_REFERER'])) {
        $ref = $_SERVER['HTTP_REFERER'];
        $parts = parse_url($ref);
        if (isset($parts['query'])) {
            parse_str($parts['query'], $q);
            if (isset($q['cycle_id'])) {
                $recovered_cycle_id = (int)$q['cycle_id'];
            }
        }
    } elseif (isset($_SESSION['last_cycle_id'])) {
        $recovered_cycle_id = (int)$_SESSION['last_cycle_id'];
    }
    if ($recovered_cycle_id) {
        header("Location: applicant_management.php?cycle_id=" . $recovered_cycle_id);
        exit;
    }
    // Require cycle selection for this page if recovery fails
    header("Location: application_management.php");
    exit;
}
$selected_cycle_id = (int)$_GET['cycle_id'];
$_SESSION['last_cycle_id'] = $selected_cycle_id; // remember last cycle context
$selected_cycle_name = null;

// Validate cycle and get its name
$cycle_stmt = $conn->prepare("SELECT cycle_name FROM admission_cycles WHERE id = ? AND is_archived = 0");
$cycle_stmt->bind_param("i", $selected_cycle_id);
$cycle_stmt->execute();
$cycle_result = $cycle_stmt->get_result();
if ($cycle_row = $cycle_result->fetch_assoc()) {
    $selected_cycle_name = $cycle_row['cycle_name'];
} else {
    die("Error: Selected cycle not found or is archived.");
}
$cycle_stmt->close();

// Fetch applicants for this cycle only (prepared statement)
$sql = "SELECT
            s.id AS submission_id,
            s.submitted_at,
            s.status,
            s.remarks,
            at.name AS applicant_type,
            ac.id AS cycle_id,
            ac.cycle_name,
            u.email AS user_email,
            d_fname.field_value AS first_name,
            d_lname.field_value AS last_name
        FROM
            submissions s
        LEFT JOIN applicant_types at ON s.applicant_type_id = at.id
        LEFT JOIN admission_cycles ac ON at.admission_cycle_id = ac.id
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN submission_data d_fname ON (s.id = d_fname.submission_id AND d_fname.field_name = 'first_name')
        LEFT JOIN submission_data d_lname ON (s.id = d_lname.submission_id AND d_lname.field_name = 'last_name')
        WHERE ac.id = ? AND ac.is_archived = 0
        ORDER BY s.submitted_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $selected_cycle_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $applicants[] = $row;
    }
} else {
    die("Error fetching applicants: " . $conn->error);
}
$stmt->close();

// Build dynamic fields for selected cycle and load values
$dynamic_fields = [];
$submission_field_values = [];

// Fetch active form fields for applicant types in the selected cycle
$df_sql = "SELECT DISTINCT ff.name, ff.label, ff.input_type
           FROM form_fields ff
           JOIN form_steps fs ON ff.step_id = fs.id
           JOIN applicant_types at ON fs.applicant_type_id = at.id
           WHERE at.admission_cycle_id = ? AND fs.is_archived = 0 AND ff.is_archived = 0
           ORDER BY fs.step_order, ff.field_order";
if ($df_stmt = $conn->prepare($df_sql)) {
    $df_stmt->bind_param("i", $selected_cycle_id);
    $df_stmt->execute();
    $df_res = $df_stmt->get_result();
    while ($f = $df_res->fetch_assoc()) {
        $dynamic_fields[] = ['name' => $f['name'], 'label' => $f['label'], 'input_type' => $f['input_type']];
    }
    $df_stmt->close();
}

// Fetch submission_data values for those dynamic fields across all listed applicants
if (!empty($applicants) && !empty($dynamic_fields)) {
    $submission_ids = array_column($applicants, 'submission_id');
    $field_names = array_column($dynamic_fields, 'name');

    // Build safe IN lists
    $submission_ids_esc = implode(',', array_map('intval', $submission_ids));
    $field_names_esc = implode(',', array_map(function ($n) use ($conn) {
        return "'" . $conn->real_escape_string($n) . "'";
    }, $field_names));

    // Fetch text/select field values from submission_data
    $sd_sql = "SELECT submission_id, field_name, field_value
               FROM submission_data
               WHERE submission_id IN ($submission_ids_esc) AND field_name IN ($field_names_esc)";
    if ($sd_res = $conn->query($sd_sql)) {
        while ($row_sd = $sd_res->fetch_assoc()) {
            $sid = (int)$row_sd['submission_id'];
            $fname = $row_sd['field_name'];
            $submission_field_values[$sid][$fname] = $row_sd['field_value'];
        }
    }

    // Fetch file field values from submission_files
    $sf_sql = "SELECT submission_id, field_name, original_filename, file_path
               FROM submission_files
               WHERE submission_id IN ($submission_ids_esc) AND field_name IN ($field_names_esc)";
    if ($sf_res = $conn->query($sf_sql)) {
        while ($row_sf = $sf_res->fetch_assoc()) {
            $sid = (int)$row_sf['submission_id'];
            $fname = $row_sf['field_name'];
            // Store file info as an array with filename and path
            $submission_field_values[$sid][$fname] = [
                'type' => 'file',
                'filename' => $row_sf['original_filename'],
                'path' => $row_sf['file_path']
            ];
        }
    }
}

// Build schedules list and booked counts for Assign Room modal
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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo $selected_cycle_name ? 'Applicants for ' . htmlspecialchars($selected_cycle_name) : 'Applicant Management'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        /* Floating Archive Icon Styles */
        .floating-archive-icon {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: #dc3545;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
            transition: all 0.3s ease;
            z-index: 1000;
            border: none;
        }

        .floating-archive-icon:hover {
            background: #c82333;
            transform: scale(1.1);
            box-shadow: 0 6px 16px rgba(220, 53, 69, 0.4);
        }

        .floating-archive-icon svg {
            width: 24px;
            height: 24px;
            color: white;
        }

        .floating-archive-icon.show {
            display: flex;
            animation: fadeInUp 0.3s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Selection counter badge */
        .selection-counter {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #007bff;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }

        /* New Cycle Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
            z-index: 1001;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .modal-overlay.show {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Modal Button Hover Effects */
        #modalCancelBtn:hover {
            background: var(--color-hover) !important;
            border-color: var(--color-border) !important;
            transform: translateY(-1px);
        }

        #modalConfirmBtn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5) !important;
        }

        #modalCancelBtn:active,
        #modalConfirmBtn:active {
            transform: translateY(0);
        }

        /* (removed) New Cycle Modal button hover and input focus styles */

        /* Pagination Styles */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            background: var(--color-card);
            border-top: 1px solid var(--color-border);
            border-radius: 0 0 12px 12px;
            margin-top: 0;
        }

        .pagination__left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .pagination__label {
            font-size: 0.9rem;
            color: #4a5568;
            font-weight: 500;
        }

        .pagination__select {
            padding: 8px 12px;
            border: 2px solid var(--color-border);
            border-radius: 8px;
            background: var(--color-card);
            color: var(--color-text);
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
            outline: none;
        }

        .pagination__select:hover {
            border-color: #cbd5e0;
        }

        .pagination__select:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(24, 165, 88, 0.15);
        }

        .pagination__center {
            flex: 1;
            text-align: center;
        }

        .pagination__info {
            font-size: 0.9rem;
            color: #4a5568;
            font-weight: 500;
        }

        .pagination__right {
            display: flex;
            gap: 8px;
        }

        .pagination__bttns {
            padding: 10px 16px;
            border: 2px solid var(--color-border);
            background: var(--color-card);
            color: var(--color-text);
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
            outline: none;
            min-width: 80px;
        }

        .pagination__bttns:hover:not(.pagination__button--disabled) {
            background: var(--color-hover);
            border-color: var(--color-border);
            transform: translateY(-1px);
        }

        .pagination__bttns:active:not(.pagination__button--disabled) {
            transform: translateY(0);
        }

        .pagination__button--disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f7fafc !important;
            border-color: #e2e8f0 !important;
            transform: none !important;
        }

        @media (max-width: 768px) {
            .pagination {
                flex-direction: column;
                gap: 16px;
                padding: 16px;
            }

            .pagination__center {
                order: -1;
            }

            .pagination__left,
            .pagination__right {
                justify-content: center;
            }
        }

        /* Table Sorting Styles */
        .sortable {
            position: relative;
            cursor: pointer !important;
            user-select: none;
            transition: all 0.2s ease;
            padding-right: 30px !important;
        }

        .sortable:hover {
            background-color: #f8fafc;
            color: #2d3748;
        }

        .sortable .sort-icon {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.5;
            transition: all 0.3s ease;
            width: 16px;
            height: 16px;
        }

        .sortable:hover .sort-icon {
            opacity: 0.8;
        }

        .sortable.sort-asc .sort-icon,
        .sortable.sort-desc .sort-icon {
            opacity: 1;
            color: #4299e1;
        }

        .sortable.sort-asc .sort-icon {
            transform: translateY(-50%) rotate(0deg);
        }

        .sortable.sort-desc .sort-icon {
            transform: translateY(-50%) rotate(180deg);
        }

        /* Enhanced table header styling */
        .table th.sortable {
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            border-bottom: 2px solid #e2e8f0;
        }

        .table th.sortable:hover {
            background: linear-gradient(135deg, #edf2f7 0%, #e2e8f0 100%);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .table th.sortable.sort-asc,
        .table th.sortable.sort-desc {
            background: linear-gradient(135deg, #ebf8ff 0%, #bee3f8 100%);
            border-bottom-color: #4299e1;
        }

        /* Sort indicator animations */
        @keyframes sortIndicator {
            0% {
                transform: translateY(-50%) scale(0.8);
            }

            50% {
                transform: translateY(-50%) scale(1.1);
            }

            100% {
                transform: translateY(-50%) scale(1);
            }
        }

        .sortable.sort-asc .sort-icon,
        .sortable.sort-desc .sort-icon {
            animation: sortIndicator 0.3s ease;
        }

        /* Status badge styles */
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-under-review {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        /* Export Modal Styles */
        .file-type-option:hover {
            background: var(--color-hover) !important;
            border-color: var(--color-border) !important;
            transform: translateY(-1px);
        }

        .file-type-option input[type="radio"]:checked+div {
            color: inherit;
        }

        .file-type-option:has(input[type="radio"]:checked) {
            border-color: var(--color-primary) !important;
            background: var(--color-hover) !important;
            box-shadow: 0 0 0 3px rgba(24, 165, 88, 0.15);
        }

        .column-option:hover {
            background-color: var(--color-hover) !important;
        }

        .column-option:has(input[type="checkbox"]:checked) {
            background-color: #f0f4ff !important;
        }

        #selectAllColumns:hover,
        #deselectAllColumns:hover {
            background: #f7fafc !important;
            border-color: #cbd5e0 !important;
            transform: translateY(-1px);
        }

        #closeExportModal:hover {
            background: rgba(0, 0, 0, 0.1) !important;
            transform: scale(1.05);
        }

        #cancelExportModal:hover {
            background: #f7fafc !important;
            border-color: #cbd5e0 !important;
            transform: translateY(-1px);
        }

        #confirmExportModal:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5) !important;
        }

        #cancelExportModal:active,
        #confirmExportModal:active {
            transform: translateY(0);
        }

        /* Export Modal Animation */
        #exportModal .modal-overlay>div {
            animation: slideUp 0.3s ease;
        }

        /* Column Drag and Drop Styles */
        .draggable-column {
            cursor: move;
            position: relative;
        }

        .column-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .column-title {
            flex: 1;
        }



        .column-dragging {
            opacity: 0.5 !important;
            background-color: rgba(59, 130, 246, 0.1);
            transform: rotate(1deg);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            z-index: 1000;
            position: relative;
        }

        .column-drag-over {
            border-left: 3px solid #3b82f6;
            background-color: rgba(59, 130, 246, 0.1);
        }

        /* Visual feedback for draggable columns */
        .draggable-column:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }

        /* Smooth transitions for column reordering */
        th.draggable-column {
            transition: background-color 0.2s ease, border 0.2s ease, transform 0.2s ease;
        }

        /* Loading Overlay Styles (consistent with general_uploads.php & applicant_types.php) */
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

        .loading-spinner {
            text-align: center;
            color: white;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px auto;
        }

        .loading-text {
            font-size: 18px;
            font-weight: 500;
            color: white;
            margin-top: 10px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <!-- Full-screen loader overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <div class="loading-text">Processing...</div>
        </div>
    </div>
    <script>
        // Global loader controls
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
    </script>
    <?php if (isset($_SESSION['message'])) {
        echo '<script>window.__FLASH_MESSAGE__ = ' . json_encode($_SESSION['message']) . ';</script>';
        unset($_SESSION['message']);
    } ?>
    <script>
        function showFeedbackModal(message, type) {
            const overlay = document.createElement('div');
            overlay.id = 'responseSummaryModalOverlay';
            overlay.innerHTML = `
                <div id="responseSummaryModal" style="position: fixed; left: 0; top: 0; width: 100%; height: 100%; background: linear-gradient(135deg, rgba(24, 165, 88, 0.08) 0%, rgba(19, 101, 21, 0.08) 100%); z-index: 1100; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(8px); padding: 20px;">
                    <div style="background: var(--color-card); border-radius: 24px; max-width: 560px; width: 95%; margin: 0 auto; overflow: hidden; box-shadow: 0 25px 80px rgba(0,0,0,0.15), 0 10px 40px rgba(0,0,0,0.1); border: 1px solid var(--color-border); position: relative; color: var(--color-text);">
                        <div style="height: 110px; background: linear-gradient(135deg, ${type === 'success' ? '#48bb78' : '#f56565'} 0%, ${type === 'success' ? '#38a169' : '#e53e3e'} 100%); position: relative; overflow: hidden;">
                            <button type="button" onclick="(function(){ const el=document.getElementById('responseSummaryModalOverlay'); if(el) el.remove(); })()" style="position: absolute; top: 16px; right: 16px; background: rgba(255,255,255,0.25); border: none; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; transition: all 0.3s ease; backdrop-filter: blur(8px);">&times;</button>
                            <div style="position: absolute; bottom: -36px; left: 50%; transform: translateX(-50%);">
                                <div style="width: 72px; height: 72px; background: var(--color-card); border-radius: 18px; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 32px rgba(0,0,0,0.1); border: 4px solid rgba(255,255,255,0.9);">
                                    <svg style="width: 36px; height: 36px; color: ${type === 'success' ? '#48bb78' : '#f56565'};" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        ${type === 'success' ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />' : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />'}
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <div style="padding: 56px 32px 28px 32px; text-align: center;">
                            <h3 style="margin: 0 0 12px 0; color: #1a202c; font-size: 1.6rem; font-weight: 800; letter-spacing: -0.025em;">Update Summary</h3>
                            <p style="margin: 0 0 20px 0; color: #2d3748; font-size: 1rem; line-height: 1.6;">${message}</p>
                            <div style="display: flex; gap: 12px; justify-content: center;">
                                <button type="button" onclick="(function(){ const el=document.getElementById('responseSummaryModalOverlay'); if(el) el.remove(); })()" style="padding: 12px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 700; font-size: 0.95rem; cursor: pointer;">Close</button>
                            </div>
                        </div>
                    </div>
                </div>`;

            document.body.appendChild(overlay);

            // Close when clicking outside
            const respOverlay = document.getElementById('responseSummaryModal');
            if (respOverlay) {
                respOverlay.addEventListener('click', function(e) {
                    if (e.target === respOverlay) {
                        const el = document.getElementById('responseSummaryModalOverlay');
                        if (el) el.remove();
                    }
                });
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (window.__FLASH_MESSAGE__ && window.__FLASH_MESSAGE__.text) {
                showFeedbackModal(window.__FLASH_MESSAGE__.text, window.__FLASH_MESSAGE__.type || 'success');
                delete window.__FLASH_MESSAGE__;
            }
        });
    </script>
    <!-- Mobile Navbar -->
    <?php
    include "includes/mobile_navbar.php";
    ?>

    <div class="layout">
        <!-- Sidebar -->
        <?php
        include "includes/sidebar.php";
        ?>

        <main class="main-content">
            <header class="header">
                <div class="header__actions">
                    <button class="btn btn--secondary" onclick="window.location.href='application_management.php'">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Admission List
                    </button>
                </div>
            </header>

            <?php
            if (isset($_SESSION['message'])) {
                echo '<div class="alert alert-' . htmlspecialchars($_SESSION['message']['type']) . '" style="margin: 0 20px 20px 20px;">
                        ' . htmlspecialchars($_SESSION['message']['text']) . '
                      </div>';
                unset($_SESSION['message']);
            }
            ?>

            <section class="section active" id="cycle_management_section" style="margin: 0 20px;">
                <div class="table-container">
                    <div class="table-container__header">
                        <h2 class="table-container__title"><?php echo $selected_cycle_name ? 'Applicants for ' . htmlspecialchars($selected_cycle_name) : 'Manage Applicants'; ?></h2>
                        <p class="table-container__subtitle"><?php echo $selected_cycle_name ? 'View and manage applicants for this specific cycle' : 'View and manage all applicant submissions'; ?></p>
                    </div>
                    <div class="filtration_container">
                        <div class="search_input_container">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search-icon lucide-search">
                                <path d="m21 21-4.34-4.34" />
                                <circle cx="11" cy="11" r="8" />
                            </svg>
                            <input type="text" id="applicantSearchInput" placeholder="Search applicants...">
                        </div>

                        <div class="search_button_container">
                            <button class="button export" onclick="exportApplicants();">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-download-icon lucide-download">
                                    <path d="M12 15V3" />
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                    <path d="m7 10 5 5 5-5" />
                                </svg>
                                Export Applicants
                            </button>
                        </div>

                        <div class="search_button_container">
                            <button class="button export" onclick="openTableConfig();">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-wrench-icon lucide-wrench">
                                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.106-3.105c.32-.322.863-.22.983.218a6 6 0 0 1-8.259 7.057l-7.91 7.91a1 1 0 0 1-2.999-3l7.91-7.91a6 6 0 0 1 7.057-8.259c.438.12.54.662.219.984z" />
                                </svg>
                                Table Configuration
                            </button>
                        </div>
                    </div>
                    <table class="table" id="applicantsTable">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllApplicants" class="table-checkbox"></th>
                                <th class="sortable draggable-column" data-column="#" data-type="numeric" draggable="true">#
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th class="sortable draggable-column" data-column="Submission ID" data-type="numeric" draggable="true">Submission ID
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>

                                <th class="sortable draggable-column" data-column="Email" draggable="true">
                                    <div class="column-header">
                                        <span class="column-title">Email</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                            <path d="m3 16 4 4 4-4" />
                                            <path d="M7 20V4" />
                                            <path d="M20 8h-5" />
                                            <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                            <path d="M15 14h5l-5 6h5" />
                                        </svg>
                                    </div>
                                </th>
                                <th class="sortable draggable-column" data-column="Application Type" draggable="true">
                                    <div class="column-header">
                                        <span class="column-title">Type</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                            <path d="m3 16 4 4 4-4" />
                                            <path d="M7 20V4" />
                                            <path d="M20 8h-5" />
                                            <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                            <path d="M15 14h5l-5 6h5" />
                                        </svg>
                                    </div>
                                </th>
                                <th class="sortable draggable-column" data-column="Status" draggable="true">
                                    <div class="column-header">
                                        <span class="column-title">Status</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                            <path d="m3 16 4 4 4-4" />
                                            <path d="M7 20V4" />
                                            <path d="M20 8h-5" />
                                            <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                            <path d="M15 14h5l-5 6h5" />
                                        </svg>
                                    </div>
                                </th>
                                <th class="sortable draggable-column" data-column="Remarks" draggable="true">
                                    <div class="column-header">
                                        <span class="column-title">Remarks</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                            <path d="m3 16 4 4 4-4" />
                                            <path d="M7 20V4" />
                                            <path d="M20 8h-5" />
                                            <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                            <path d="M15 14h5l-5 6h5" />
                                        </svg>
                                    </div>
                                </th>
                                <th class="sortable draggable-column" data-column="Submitted" data-type="date" draggable="true">
                                    <div class="column-header">
                                        <span class="column-title">Submitted</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                            <path d="m3 16 4 4 4-4" />
                                            <path d="M7 20V4" />
                                            <path d="M20 8h-5" />
                                            <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                            <path d="M15 14h5l-5 6h5" />
                                        </svg>
                                    </div>
                                </th>
                                <th class="sortable draggable-column" data-column="Cycle" draggable="true">
                                    <div class="column-header">
                                        <span class="column-title">Cycle</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                            <path d="m3 16 4 4 4-4" />
                                            <path d="M7 20V4" />
                                            <path d="M20 8h-5" />
                                            <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                            <path d="M15 14h5l-5 6h5" />
                                        </svg>
                                    </div>
                                </th>
                                <?php if (!empty($dynamic_fields)) {
                                    foreach ($dynamic_fields as $f) { ?>
                                        <th class="sortable draggable-column" data-column="<?php echo htmlspecialchars($f['label']); ?>" draggable="true">
                                            <div class="column-header">
                                                <span class="column-title"><?php echo htmlspecialchars($f['label']); ?></span>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                                    <path d="m3 16 4 4 4-4" />
                                                    <path d="M7 20V4" />
                                                    <path d="M20 8h-5" />
                                                    <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                                    <path d="M15 14h5l-5 6h5" />
                                                </svg>
                                            </div>
                                        </th>
                                <?php }
                                } ?>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($applicants)): ?>
                                <tr>
                                    <td colspan="10" style="text-align:center;">No applicants found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($applicants as $idx => $applicant): ?>
                                    <tr data-row-id="<?php echo $applicant['submission_id']; ?>">
                                        <td><input type="checkbox" class="table-checkbox row-checkbox" data-id="<?php echo $applicant['submission_id']; ?>"></td>
                                        <td data-cell='#'><?php echo $idx + 1; ?></td>
                                        <td data-cell='Submission ID'><?php echo htmlspecialchars($applicant["submission_id"]); ?></td>

                                        <td data-cell='Email'><?php
                                                                $email = $applicant["user_email"] ?? null;
                                                                $dec = $email ? decryptData($email) : null;
                                                                echo htmlspecialchars(($dec !== false && !empty($dec)) ? $dec : ($email ?? 'N/A'));
                                                                ?></td>
                                        <td data-cell='Application Type'><?php echo htmlspecialchars($applicant["applicant_type"] ?? 'N/A'); ?></td>
                                        <td data-cell='Status'>
                                            <?php
                                            $status_name = $applicant['status'];
                                            $bg_hex = $status_color_map[$status_name] ?? null;
                                            if ($bg_hex) {
                                                $text_color = getContrastingTextColor($bg_hex);
                                                $style = 'background-color: ' . htmlspecialchars($bg_hex) . '; color: ' . htmlspecialchars($text_color) . ';';
                                            } else {
                                                $style = '';
                                            }
                                            ?>
                                            <span class="status-badge" style="<?php echo $style; ?>">
                                                <?php echo htmlspecialchars($status_name); ?>
                                            </span>
                                        </td>
                                        <td data-cell='Remarks'><?php echo htmlspecialchars($applicant['remarks'] ?? ''); ?></td>
                                        <td data-cell='Submitted'><?php echo date('M j, Y', strtotime($applicant['submitted_at'])); ?></td>
                                        <td data-cell='Cycle'><?php echo htmlspecialchars($applicant['cycle_name'] ?? 'N/A'); ?></td>
                                        <?php if (!empty($dynamic_fields)) {
                                            foreach ($dynamic_fields as $f) {
                                                $val = $submission_field_values[$applicant['submission_id']][$f['name']] ?? '';
                                                $display_value = '';

                                                if (is_array($val) && isset($val['type']) && $val['type'] === 'file') {
                                                    // This is a file field
                                                    $display_value = '<a href="' . htmlspecialchars($val['path']) . '" target="_blank" style="color: #007bff; text-decoration: none;">' . htmlspecialchars($val['filename']) . '</a>';
                                                } else {
                                                    // This is a regular text field
                                                    $display_value = htmlspecialchars($val);
                                                }
                                        ?>
                                                <td data-cell='<?php echo htmlspecialchars($f['label']); ?>'><?php echo $display_value; ?></td>
                                        <?php }
                                        } ?>
                                        <td class='table_actions actions'>
                                            <div class='table-controls'>
                                                <a href="view_submission.php?id=<?php echo $applicant['submission_id']; ?>" class="table__btn table__btn--view">View</a>
                                                <button class="table__btn table__btn--edit" onclick="openStatusModal(<?php echo $applicant['submission_id']; ?>, '<?php echo htmlspecialchars($applicant['status']); ?>')">Update</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>


                    <!-- Pagination -->
                    <div class="pagination">
                        <div class="pagination__left">
                            <span class="pagination__label">Rows per page:</span>
                            <select class="pagination__select">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                        <div class="pagination__center">
                            <span class="pagination__info">Showing 1-10 of 75 • Page 1/8</span>
                        </div>
                        <div class="pagination__right">
                            <button class="pagination__bttns pagination__button--disabled" disabled>Prev</button>
                            <button class="pagination__bttns">Next</button>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Assign Room Modal -->
    <div id="assignRoomModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 1004; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div style="background: var(--color-card); border-radius: 20px; max-width: 760px; width: 95%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); position: relative; color: var(--color-text);">
            <!-- Close Button -->
            <button type="button" id="closeAssignRoomModal" style="position: absolute; top: 16px; right: 16px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px; transition: all 0.2s ease;">&times;</button>
            <div style="padding: 24px 24px 8px 24px;">
                <h3 style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.4rem; font-weight: 700;">Assign Room to Selected Applicants</h3>
                <p style="color: #718096; margin: 0 0 12px 0;">Choose an exam room. Full rooms are disabled.</p>
                <div style="display:flex; align-items:center; gap: 10px; margin-bottom: 12px;">
                    <input type="text" id="assignRoomSearch" placeholder="Search rooms by floor, name, or time..." style="flex:1; padding:10px 12px; border:2px solid #e2e8f0; border-radius: 10px; background:#f7fafc;">
                </div>
            </div>
            <div style="padding: 0 24px 16px 24px; max-height: 420px; overflow-y: auto;">
                <div id="assignRoomList" style="display: grid; grid-template-columns: 1fr; gap: 10px;">
                    <?php if (!empty($schedules)):
                        foreach ($schedules as $sid => $sch):
                            $floor = htmlspecialchars($sch['floor']);
                            $room = htmlspecialchars($sch['room']);
                            $cap = (int)$sch['capacity'];
                            $starts = htmlspecialchars($sch['start_date_and_time']);
                            $status = htmlspecialchars($sch['status']);
                            $booked = isset($booked_map[$sid]) ? (int)$booked_map[$sid] : 0;
                            $is_full = ($cap > 0 && $booked >= $cap);
                    ?>
                            <label class="assign-room-item" data-text="<?php echo strtolower($floor . ' ' . $room . ' ' . $starts); ?>" style="display:flex; align-items:center; gap:12px; padding: 14px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff;">
                                <input type="radio" name="assign_target_schedule_id" value="<?php echo (int)$sid; ?>" <?php echo $is_full ? 'disabled' : ''; ?> />
                                <div style="flex:1;">
                                    <div style="display:flex; justify-content: space-between;">
                                        <strong><?php echo $floor; ?> • <?php echo $room; ?></strong>
                                        <span style="font-size: 0.85rem; color: #718096;">ID #<?php echo (int)$sid; ?></span>
                                    </div>
                                    <div style="font-size: 0.9rem; color: #4a5568;">Starts: <?php echo $starts; ?></div>
                                    <div style="margin-top:6px; display:flex; gap:10px; align-items:center;">
                                        <span style="padding:4px 8px; border-radius: 8px; background: <?php echo $is_full ? '#fed7d7' : '#c6f6d5'; ?>; color: <?php echo $is_full ? '#c53030' : '#2f855a'; ?>; font-size: 0.8rem;">Status: <?php echo $status; ?></span>
                                        <span style="font-size:0.85rem; color:#718096;">Capacity: <?php echo $booked; ?>/<?php echo $cap; ?></span>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach;
                    else: ?>
                        <div style="padding: 12px; color:#718096;">No rooms available.</div>
                    <?php endif; ?>
                </div>
            </div>
            <div style="padding: 0 24px 8px 24px; display:flex; align-items:center; gap:10px;">
                <input type="checkbox" id="moveExistingCheckbox" style="width:16px;height:16px;">
                <label for="moveExistingCheckbox" style="color:#4a5568; font-size:0.9rem;">Move already-assigned applicants to selected room</label>
            </div>
            <div style="padding: 16px 24px 24px 24px; display:flex; gap:12px; justify-content:flex-end;">
                <button type="button" id="assignRoomCancelBtn" style="padding: 10px 16px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 10px; font-weight: 600; font-size: 0.9rem; cursor: pointer;">Cancel</button>
                <button type="button" id="assignRoomConfirmBtn" style="padding: 10px 16px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 10px; font-weight: 600; font-size: 0.9rem; cursor: pointer; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4);">Assign Room</button>
            </div>
        </div>
    </div>

    <!-- Floating Action Menu for Bulk Operations -->
    <div id="floatingActionMenu" style="display: none; position: fixed; bottom: 30px; right: 30px; background: var(--color-card); border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.15), 0 8px 25px rgba(0,0,0,0.1); z-index: 1001; padding: 20px; min-width: 280px; border: 1px solid var(--color-border); color: var(--color-text);">
        <div style="margin-bottom: 16px;">
            <h4 style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.1rem; font-weight: 600;">Bulk Actions</h4>
            <p id="selectedCount" style="margin: 0; color: #718096; font-size: 0.9rem;">0 applicants selected</p>
        </div>
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <button id="bulkAssignRoom" class="btn btn--secondary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 16px; border-radius: 10px; font-size: 0.9rem;">
                <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Assign Room
            </button>
            <button id="bulkChangeStatus" class="btn btn--secondary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 16px; border-radius: 10px; font-size: 0.9rem;">
                <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Change Status
            </button>
        </div>
        <button id="closeBulkMenu" style="position: absolute; top: 8px; right: 8px; background: none; border: none; color: #a0aec0; cursor: pointer; padding: 4px; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
            <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    <div id="confirmationModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 1002; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div style="background: var(--color-card); color: var(--color-text); border-radius: 20px; text-align: center; max-width: 400px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border);">
            <!-- Modal Header -->
            <div style="padding: 32px 32px 16px 32px;">
                <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 16px; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 id="modalTitle" style="margin: 0 0 12px 0; color: #1a202c; font-size: 1.5rem; font-weight: 700; letter-spacing: -0.025em;">Confirm Action</h3>
                <p id="modalMessage" style="color: #718096; margin: 0; line-height: 1.6; font-size: 0.95rem;">Are you sure you want to proceed?</p>
            </div>

            <!-- Modal Footer -->
            <div style="padding: 16px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
                <button id="modalCancelBtn" style="flex: 1; padding: 12px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
                <button id="modalConfirmBtn" style="flex: 1; padding: 12px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4);">Confirm</button>
            </div>
        </div>
    </div>

    <!-- (removed) New Admission Cycle Modal -->



    <script>
        const modal = document.getElementById('confirmationModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');
        const modalConfirmBtn = document.getElementById('modalConfirmBtn');
        const modalCancelBtn = document.getElementById('modalCancelBtn');
        let currentActionUrl = '';

        function showConfirmationModal(title, message, actionUrl) {

            if (!modal) {
                console.error('Confirmation modal element not found!');
                return;
            }

            modalTitle.textContent = title;
            modalMessage.textContent = message;
            currentActionUrl = actionUrl;
            modal.style.display = 'flex';
            modal.style.visibility = 'visible';
            modal.style.opacity = '1';

        }
        modalConfirmBtn.addEventListener('click', () => {
            if (currentActionUrl) {
                window.location.href = currentActionUrl;
            }
            modal.style.display = 'none';
        });
        modalCancelBtn.addEventListener('click', () => {
            modal.style.display = 'none';
        });
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });

        // (removed) setupConfirmationLinks and New Cycle modal handlers
        const searchInput = document.getElementById('applicantSearchInput');
        const tableBody = document.getElementById('applicantsTable')?.querySelector('tbody');
        if (searchInput && tableBody) {
            searchInput.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = tableBody.querySelectorAll('tr');
                rows.forEach(row => {
                    if (row.cells.length > 1) {
                        const applicantName = row.cells[2]?.textContent.toLowerCase() || '';
                        const email = row.cells[3]?.textContent.toLowerCase() || '';
                        const applicationType = row.cells[4]?.textContent.toLowerCase() || '';
                        const cycleId = row.cells[5]?.textContent.toLowerCase() || '';
                        const cycle = row.cells[6]?.textContent.toLowerCase() || '';

                        const matchesSearch = applicantName.includes(searchTerm) ||
                            email.includes(searchTerm) ||
                            applicationType.includes(searchTerm) ||
                            cycleId.includes(searchTerm) ||
                            cycle.includes(searchTerm);

                        row.style.display = matchesSearch ? '' : 'none';
                    }
                });
            });
        }
        // (removed) setupConfirmationLinks event binding

        // Floating Action Menu Functionality
        const floatingActionMenu = document.getElementById('floatingActionMenu');
        const selectedCount = document.getElementById('selectedCount');
        const selectAllCheckbox = document.getElementById('selectAllApplicants');
        const rowCheckboxes = document.querySelectorAll('.row-checkbox');
        let selectedApplicants = [];

        function updateFloatingMenu() {
            const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
            selectedApplicants = Array.from(checkedBoxes).map(cb => cb.dataset.id);

            if (selectedApplicants.length > 0) {
                floatingActionMenu.style.display = 'block';
                selectedCount.textContent = `${selectedApplicants.length} applicant${selectedApplicants.length > 1 ? 's' : ''} selected`;
            } else {
                floatingActionMenu.style.display = 'none';
            }
        }

        // Handle individual checkbox changes
        rowCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateFloatingMenu();

                // Update select all checkbox state
                const totalCheckboxes = rowCheckboxes.length;
                const checkedCheckboxes = document.querySelectorAll('.row-checkbox:checked').length;

                if (checkedCheckboxes === totalCheckboxes) {
                    selectAllCheckbox.checked = true;
                    selectAllCheckbox.indeterminate = false;
                } else if (checkedCheckboxes === 0) {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = false;
                } else {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = true;
                }
            });
        });

        // Handle select all checkbox
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const isChecked = this.checked;
                rowCheckboxes.forEach(checkbox => {
                    checkbox.checked = isChecked;
                });
                updateFloatingMenu();
            });
        }

        // Handle floating action menu buttons
        // Assign Room: open modal and handle selection
        const assignRoomModal = document.getElementById('assignRoomModal');
        const assignRoomSearch = document.getElementById('assignRoomSearch');
        const assignRoomList = document.getElementById('assignRoomList');
        const assignRoomConfirmBtn = document.getElementById('assignRoomConfirmBtn');
        const assignRoomCancelBtn = document.getElementById('assignRoomCancelBtn');
        const closeAssignRoomModalBtn = document.getElementById('closeAssignRoomModal');

        function openAssignRoomModal() {
            if (selectedApplicants.length === 0) return;
            if (assignRoomModal) {
                assignRoomModal.style.display = 'flex';
                // Reset search
                if (assignRoomSearch) {
                    assignRoomSearch.value = '';
                    filterAssignRoomList('');
                }
            }
        }

        function closeAssignRoomModalFn() {
            if (assignRoomModal) {
                assignRoomModal.style.display = 'none';
            }
        }

        function filterAssignRoomList(term) {
            const q = (term || '').toLowerCase();
            if (!assignRoomList) return;
            assignRoomList.querySelectorAll('.assign-room-item').forEach(item => {
                const text = (item.getAttribute('data-text') || '').toLowerCase();
                item.style.display = text.includes(q) ? 'flex' : 'none';
            });
        }

        function submitAssignRoom() {
            if (selectedApplicants.length === 0) return;
            const selectedRadio = document.querySelector('input[name="assign_target_schedule_id"]:checked');
            if (!selectedRadio) {
                alert('Please select a room to assign.');
                return;
            }
            const targetScheduleId = selectedRadio.value;
            const moveExisting = document.getElementById('moveExistingCheckbox')?.checked ? '1' : '0';

            // Add loader state to confirm button
            if (assignRoomConfirmBtn) {
                assignRoomConfirmBtn.disabled = true;
                assignRoomConfirmBtn.dataset.originalText = assignRoomConfirmBtn.textContent;
                assignRoomConfirmBtn.innerHTML = '<span class="spinner" style="display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,0.6);border-top-color:#fff;border-radius:50%;animation:spin 0.8s linear infinite;margin-right:8px;"></span>Assigning…';
            }
            // Show full-screen loader overlay for consistency
            if (typeof showLoader === 'function') {
                showLoader();
            }

            // Build POST form to backend handler
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'applicant_management.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'bulk_assign_room';
            form.appendChild(actionInput);

            const targetInput = document.createElement('input');
            targetInput.type = 'hidden';
            targetInput.name = 'target_schedule_id';
            targetInput.value = targetScheduleId;
            form.appendChild(targetInput);

            const moveExistingInput = document.createElement('input');
            moveExistingInput.type = 'hidden';
            moveExistingInput.name = 'move_existing';
            moveExistingInput.value = moveExisting;
            form.appendChild(moveExistingInput);

            selectedApplicants.forEach(id => {
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                // Use submission_ids[] to be explicit; backend also accepts ids[]
                idInput.name = 'submission_ids[]';
                idInput.value = id;
                form.appendChild(idInput);
            });

            // Preserve cycle context
            const cycleId = new URLSearchParams(window.location.search).get('cycle_id');
            if (cycleId) {
                const cycleInput = document.createElement('input');
                cycleInput.type = 'hidden';
                cycleInput.name = 'cycle_id';
                cycleInput.value = cycleId;
                form.appendChild(cycleInput);
            }

            document.body.appendChild(form);
            form.submit();
        }

        const bulkAssignRoomBtn = document.getElementById('bulkAssignRoom');
        if (bulkAssignRoomBtn) {
            bulkAssignRoomBtn.addEventListener('click', openAssignRoomModal);
        }
        if (assignRoomSearch) {
            assignRoomSearch.addEventListener('keyup', function() {
                filterAssignRoomList(this.value);
            });
        }
        if (assignRoomConfirmBtn) {
            assignRoomConfirmBtn.addEventListener('click', submitAssignRoom);
        }
        if (assignRoomCancelBtn) {
            assignRoomCancelBtn.addEventListener('click', closeAssignRoomModalFn);
        }
        if (closeAssignRoomModalBtn) {
            closeAssignRoomModalBtn.addEventListener('click', closeAssignRoomModalFn);
        }
        if (assignRoomModal) {
            assignRoomModal.addEventListener('click', function(event) {
                if (event.target === assignRoomModal) {
                    closeAssignRoomModalFn();
                }
            });
        }

        document.getElementById('bulkChangeStatus').addEventListener('click', function() {
            if (selectedApplicants.length === 0) {
                return;
            }

            const statuses = <?php echo json_encode($available_statuses); ?>;
            const statusOptions = statuses.map(status => `\n                <option value="${status}">${status}</option>`).join('');
            const templates = <?php echo json_encode($remark_templates); ?>;
            const templateOptions = templates.map(t => `<option value="${t}">${t}</option>`).join('');

            const overlay = document.createElement('div');
            overlay.id = 'bulkStatusModalOverlay';
            overlay.innerHTML = `
                <div id="bulkStatusModal" style="position: fixed; left: 0; top: 0; width: 100%; height: 100%; background: linear-gradient(135deg, rgba(24, 165, 88, 0.1) 0%, rgba(19, 101, 21, 0.1) 100%); z-index: 1003; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(8px); padding: 20px;">
                    <div style="background: var(--color-card); border-radius: 24px; max-width: 640px; width: 95%; margin: 0 auto; max-height: calc(100vh - 40px); overflow: hidden; box-shadow: 0 25px 80px rgba(0,0,0,0.15), 0 10px 40px rgba(0,0,0,0.1); border: 1px solid var(--color-border); position: relative; display: flex; flex-direction: column; color: var(--color-text);">
                        <div style="height: 120px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); position: relative; overflow: hidden;">
                            <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%; animation: float 6s ease-in-out infinite;"></div>
                            <div style="position: absolute; bottom: -30px; left: -30px; width: 100px; height: 100px; background: rgba(255,255,255,0.08); border-radius: 50%; animation: float 8s ease-in-out infinite reverse;"></div>

                            <button type="button" onclick="(function(){ const el=document.getElementById('bulkStatusModalOverlay'); if(el) el.remove(); })()" style="position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.2); border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; transition: all 0.3s ease; backdrop-filter: blur(10px);">&times;</button>

                            <div style="position: absolute; bottom: -40px; left: 50%; transform: translateX(-50%); text-align: center;">
                                <div style="width: 80px; height: 80px; background: var(--color-card); border-radius: 20px; margin: 0 auto 16px auto; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 32px rgba(0,0,0,0.1); border: 4px solid rgba(255,255,255,0.9);">
                                    <svg style="width: 40px; height: 40px; color: var(--color-primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div style="flex: 1; overflow-y: auto; padding: 60px 40px 40px 40px; text-align: center;">
                            <h3 style="margin: 0 0 12px 0; color: #1a202c; font-size: 2rem; font-weight: 800; letter-spacing: -0.025em;">Bulk Update Status</h3>
                            <p style="color: #718096; margin: 0 0 32px 0; line-height: 1.6; font-size: 1.1rem;">Update status and optionally add remarks for ${selectedApplicants.length} applicant${selectedApplicants.length > 1 ? 's' : ''}</p>

                            <form style="text-align: left;">
                                <div style="margin-bottom: 28px; position: relative;">
                                    <label style="display: block; margin-bottom: 10px; font-weight: 700; color: #2d3748; font-size: 1rem; display: flex; align-items: center; gap: 8px;">New Status</label>
                                    <select id="bulkNewStatus" style="width: 100%; padding: 16px 20px; border: 3px solid #e2e8f0; border-radius: 16px; font-size: 1rem; transition: all 0.3s ease; box-sizing: border-box; background: #f8fafc; color: #2d3748; font-weight: 500;">
                                        ${statusOptions}
                                    </select>
                                </div>

                                <div style="margin-bottom: 28px; position: relative;">
                                    <label style="display: block; margin-bottom: 10px; font-weight: 700; color: #2d3748; font-size: 1rem; display: flex; align-items: center; gap: 8px;">Quick Remark Template</label>
                                    <select id="bulkRemarkTemplate" style="width: 100%; padding: 16px 20px; border: 3px solid #e2e8f0; border-radius: 16px; font-size: 1rem; transition: all 0.3s ease; box-sizing: border-box; background: #f8fafc; color: #2d3748; font-weight: 500;">
                                        <option value="">None</option>
                                        ${templateOptions}
                                        <option value="__other__">Other (custom)...</option>
                                    </select>
                                </div>

                                <div id="bulkRemarksContainer" style="margin-bottom: 28px; position: relative; display: none;">
                                    <label for="bulkRemarks" style="display: block; margin-bottom: 10px; font-weight: 700; color: #2d3748; font-size: 1rem; display: flex; align-items: center; gap: 8px;">Remarks</label>
                                    <textarea id="bulkRemarks" rows="4" style="width: 100%; padding: 16px 20px; border: 3px solid #e2e8f0; border-radius: 16px; font-size: 1rem; transition: all 0.3s ease; box-sizing: border-box; background: #f8fafc; color: #2d3748; font-weight: 500;" disabled></textarea>
                                </div>

                                <div style="margin-bottom: 12px; position: relative;">
                                    <label for="bulkAllowUpdate" style="display: flex; align-items: center; gap: 10px; font-weight: 700; color: #2d3748; font-size: 1rem;">
                                        <input type="checkbox" id="bulkAllowUpdate" style="width: 18px; height: 18px; accent-color: #18a558;">
                                        Allow applicants to update application submission
                                    </label>
                                </div>

                                <div style="display: flex; gap: 16px; justify-content: center; margin-top: 40px;">
                                    <button type="button" onclick="(function(){ const el=document.getElementById('bulkStatusModalOverlay'); if(el) el.remove(); })()" style="flex: 1; padding: 16px 32px; border: 3px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 16px; font-weight: 700; font-size: 1rem; cursor: pointer; outline: none;">Cancel</button>
                                    <button type="button" onclick="bulkChangeStatus()" style="flex: 1; padding: 16px 32px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 16px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: all 0.3s ease; outline: none; box-shadow: 0 8px 32px rgba(24, 165, 88, 0.4); position: relative; overflow: hidden;">Update Status</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);

            // Close when clicking outside the content
            const bulkOverlay = document.getElementById('bulkStatusModal');
            if (bulkOverlay) {
                bulkOverlay.addEventListener('click', function(e) {
                    if (e.target === bulkOverlay) {
                        const el = document.getElementById('bulkStatusModalOverlay');
                        if (el) el.remove();
                    }
                });
            }

            // Hook up bulk remark template behavior
            const bulkTemplateSel = document.getElementById('bulkRemarkTemplate');
            const bulkRemarksTa = document.getElementById('bulkRemarks');
            const bulkRemarksContainer = document.getElementById('bulkRemarksContainer');
            if (bulkTemplateSel && bulkRemarksTa && bulkRemarksContainer) {
                bulkTemplateSel.addEventListener('change', function() {
                    const val = this.value;
                    if (val === '__other__') {
                        bulkRemarksContainer.style.display = 'block';
                        bulkRemarksTa.disabled = false;
                        bulkRemarksTa.value = '';
                        bulkRemarksTa.focus();
                    } else {
                        bulkRemarksContainer.style.display = 'none';
                        bulkRemarksTa.disabled = true;
                        bulkRemarksTa.value = '';
                    }
                });
            }

            setTimeout(() => {
                const sel = document.getElementById('bulkNewStatus');
                if (sel) sel.focus();
            }, 100);
        });

        document.getElementById('closeBulkMenu').addEventListener('click', function() {
            floatingActionMenu.style.display = 'none';
            // Uncheck all checkboxes
            rowCheckboxes.forEach(checkbox => checkbox.checked = false);
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        });

        // Global function for bulk status change
        window.bulkChangeStatus = function() {

            const newStatus = document.getElementById('bulkNewStatus').value;
            const templateVal = document.getElementById('bulkRemarkTemplate')?.value || '';
            let remarks = '';
            if (templateVal === '__other__') {
                remarks = document.getElementById('bulkRemarks').value;
            } else {
                remarks = templateVal; // use selected template or empty
            }
            const allowUpdate = document.getElementById('bulkAllowUpdate')?.checked ? '1' : '0';

            const statusModal = document.querySelector('div[style*="z-index: 1003"]');
            if (statusModal) {
                statusModal.remove();
            }

            // Submit as POST with ids[], status, and remarks
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'applicant_management.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'bulk_status';
            form.appendChild(actionInput);

            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = newStatus;
            form.appendChild(statusInput);

            const remarksInput = document.createElement('input');
            remarksInput.type = 'hidden';
            remarksInput.name = 'remarks';
            remarksInput.value = remarks;
            form.appendChild(remarksInput);

            const allowUpdateInput = document.createElement('input');
            allowUpdateInput.type = 'hidden';
            allowUpdateInput.name = 'allow_update';
            allowUpdateInput.value = allowUpdate;
            form.appendChild(allowUpdateInput);

            selectedApplicants.forEach(id => {
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'ids[]';
                idInput.value = id;
                form.appendChild(idInput);
            });

            // Preserve cycle context
            const cycleId = new URLSearchParams(window.location.search).get('cycle_id');
            if (cycleId) {
                const cycleInput = document.createElement('input');
                cycleInput.type = 'hidden';
                cycleInput.name = 'cycle_id';
                cycleInput.value = cycleId;
                form.appendChild(cycleInput);
            }

            document.body.appendChild(form);
            // Show loader and defer submit slightly for rendering
            if (typeof showLoader === 'function') {
                try {
                    showLoader();
                } catch (e) {}
            }
            const modal = document.getElementById('bulkStatusModalOverlay');
            if (modal) {
                try {
                    modal.remove();
                } catch (e) {}
            }
            setTimeout(function() {
                form.submit();
            }, 120);
        };

        // Table Sorting Functionality
        class TableSorter {
            constructor(tableSelector) {
                this.table = document.querySelector(tableSelector);
                this.tbody = this.table?.querySelector('tbody');
                this.headers = this.table?.querySelectorAll('th.sortable');
                this.currentSort = {
                    column: null,
                    direction: 'asc'
                };
                this.currentRows = [];

                this.init();
            }

            init() {
                if (!this.table || !this.headers) return;

                // Get all data rows
                this.updateCurrentRows();

                // Add click listeners to sortable headers
                this.headers.forEach(header => {
                    header.addEventListener('click', () => {
                        const column = header.dataset.column;
                        const type = header.dataset.type || 'text';
                        this.sortBy(column, null, type);
                    });
                });
            }

            updateCurrentRows() {
                if (!this.tbody) return;
                this.currentRows = Array.from(this.tbody.querySelectorAll('tr')).filter(row => {
                    const text = row.textContent.trim();
                    return !text.includes('No active cycles found') && !text.includes('No data found');
                });
            }

            sortBy(column, direction = null, type = 'text') {
                if (!column) return;

                // Determine sort direction
                if (direction === null) {
                    if (this.currentSort.column === column) {
                        direction = this.currentSort.direction === 'asc' ? 'desc' : 'asc';
                    } else {
                        direction = 'asc';
                    }
                }

                this.currentSort = {
                    column,
                    direction
                };

                // Update current rows before sorting
                this.updateCurrentRows();

                // Sort the rows
                this.currentRows.sort((a, b) => {
                    const aValue = this.getCellValue(a, column);
                    const bValue = this.getCellValue(b, column);

                    let comparison = 0;

                    switch (type) {
                        case 'numeric':
                            comparison = this.compareNumeric(aValue, bValue);
                            break;
                        case 'date':
                            comparison = this.compareDate(aValue, bValue);
                            break;
                        case 'custom':
                            comparison = this.compareCustom(aValue, bValue, column);
                            break;
                        default:
                            comparison = this.compareText(aValue, bValue);
                    }

                    return direction === 'asc' ? comparison : -comparison;
                });

                // Reorder DOM elements
                this.currentRows.forEach(row => {
                    this.tbody.appendChild(row);
                });

                // Update sort indicators
                this.updateSortIndicators(column, direction);

                // Dispatch custom event for pagination integration
                this.table.dispatchEvent(new CustomEvent('tableSorted', {
                    detail: {
                        column,
                        direction,
                        rows: this.currentRows
                    }
                }));
            }

            getCellValue(row, column) {
                const cell = row.querySelector(`[data-cell="${column}"]`) ||
                    row.querySelector(`[data-column="${column}"]`) ||
                    row.querySelector(`td:nth-child(${this.getColumnIndex(column)})`);
                return cell ? cell.textContent.trim() : '';
            }

            getColumnIndex(column) {
                const headerArray = Array.from(this.headers);
                const header = headerArray.find(h => h.dataset.column === column);
                return header ? Array.from(header.parentNode.children).indexOf(header) + 1 : 1;
            }

            compareText(a, b) {
                return a.toLowerCase().localeCompare(b.toLowerCase());
            }

            compareNumeric(a, b) {
                const numA = parseFloat(a.replace(/[^\d.-]/g, '')) || 0;
                const numB = parseFloat(b.replace(/[^\d.-]/g, '')) || 0;
                return numA - numB;
            }

            compareDate(a, b) {
                const dateA = new Date(a);
                const dateB = new Date(b);
                return dateA - dateB;
            }

            compareCustom(a, b, column) {
                // Override this method for custom sorting logic
                return this.compareText(a, b);
            }

            updateSortIndicators(activeColumn, direction) {
                // Reset all headers
                this.headers.forEach(header => {
                    header.classList.remove('sort-asc', 'sort-desc');
                });

                // Set active header
                const activeHeader = Array.from(this.headers).find(h => h.dataset.column === activeColumn);
                if (activeHeader) {
                    activeHeader.classList.add(`sort-${direction}`);
                }
            }

            // Public methods
            refresh() {
                this.updateCurrentRows();
                if (this.currentSort.column) {
                    this.sortBy(this.currentSort.column, this.currentSort.direction);
                }
            }

            getCurrentSort() {
                return {
                    ...this.currentSort
                };
            }
        }

        // Pagination Functionality
        class TablePagination {
            constructor() {
                this.table = document.getElementById('applicantsTable');
                this.tbody = this.table?.querySelector('tbody');
                this.paginationSelect = document.querySelector('.pagination__select');
                this.paginationInfo = document.querySelector('.pagination__info');
                this.prevButton = document.querySelector('.pagination__bttns:first-of-type');
                this.nextButton = document.querySelector('.pagination__bttns:last-of-type');

                this.currentPage = 1;
                this.rowsPerPage = parseInt(this.paginationSelect?.value) || 10;
                this.allRows = [];
                this.filteredRows = [];

                this.init();
            }

            init() {
                if (!this.tbody) return;

                // Get all table rows (excluding empty state row)
                this.allRows = Array.from(this.tbody.querySelectorAll('tr')).filter(row => {
                    return !row.textContent.includes('No applicants found');
                });

                this.filteredRows = [...this.allRows];

                // Set up event listeners
                this.setupEventListeners();

                // Initial render
                this.updateDisplay();
            }

            setupEventListeners() {
                // Rows per page selector
                if (this.paginationSelect) {
                    this.paginationSelect.addEventListener('change', (e) => {
                        this.rowsPerPage = parseInt(e.target.value);
                        this.currentPage = 1;
                        this.updateDisplay();
                    });
                }

                // Previous button
                if (this.prevButton) {
                    this.prevButton.addEventListener('click', () => {
                        if (this.currentPage > 1) {
                            this.currentPage--;
                            this.updateDisplay();
                        }
                    });
                }

                // Next button
                if (this.nextButton) {
                    this.nextButton.addEventListener('click', () => {
                        const totalPages = Math.ceil(this.filteredRows.length / this.rowsPerPage);
                        if (this.currentPage < totalPages) {
                            this.currentPage++;
                            this.updateDisplay();
                        }
                    });
                }

                // Update pagination when search filters rows
                if (searchInput) {
                    searchInput.addEventListener('keyup', () => {
                        // Wait for search to complete, then update pagination
                        setTimeout(() => {
                            this.updateFilteredRows();
                            this.currentPage = 1;
                            this.updateDisplay();
                        }, 10);
                    });
                }
            }

            updateFilteredRows() {
                // Get currently visible rows after search, but maintain sort order
                const currentRows = Array.from(this.tbody.querySelectorAll('tr')).filter(row => {
                    const text = row.textContent.trim();
                    return !text.includes('No applicants found') && !text.includes('No data found');
                });

                this.filteredRows = currentRows.filter(row => {
                    return row.style.display !== 'none';
                });
            }

            updateDisplay() {
                const totalRows = this.filteredRows.length;
                const totalPages = Math.ceil(totalRows / this.rowsPerPage);
                const startIndex = (this.currentPage - 1) * this.rowsPerPage;
                const endIndex = startIndex + this.rowsPerPage;

                // Hide all rows first
                this.allRows.forEach(row => {
                    row.style.display = 'none';
                });

                // Show only the rows for current page
                this.filteredRows.slice(startIndex, endIndex).forEach(row => {
                    row.style.display = '';
                });

                // Update pagination info
                if (this.paginationInfo) {
                    const startItem = totalRows === 0 ? 0 : startIndex + 1;
                    const endItem = Math.min(endIndex, totalRows);
                    this.paginationInfo.textContent = `Showing ${startItem}-${endItem} of ${totalRows} • Page ${this.currentPage}/${totalPages || 1}`;
                }

                // Update button states
                if (this.prevButton) {
                    if (this.currentPage <= 1) {
                        this.prevButton.classList.add('pagination__button--disabled');
                        this.prevButton.disabled = true;
                    } else {
                        this.prevButton.classList.remove('pagination__button--disabled');
                        this.prevButton.disabled = false;
                    }
                }

                if (this.nextButton) {
                    if (this.currentPage >= totalPages || totalPages === 0) {
                        this.nextButton.classList.add('pagination__button--disabled');
                        this.nextButton.disabled = true;
                    } else {
                        this.nextButton.classList.remove('pagination__button--disabled');
                        this.nextButton.disabled = false;
                    }
                }

                // Show empty state if no rows
                if (totalRows === 0) {
                    const emptyRow = this.tbody.querySelector('tr[style*="text-align:center"]');
                    if (emptyRow) {
                        emptyRow.style.display = '';
                    }
                }
            }

            // Public method to refresh pagination (useful after dynamic content changes)
            refresh() {
                this.allRows = Array.from(this.tbody.querySelectorAll('tr')).filter(row => {
                    return !row.textContent.includes('No applicants found');
                });
                this.filteredRows = [...this.allRows];
                this.currentPage = 1;
                this.updateDisplay();
            }
        }

        // Initialize sorting and pagination when DOM is loaded
        let tableSorter, tablePagination;
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize table sorting
            tableSorter = new TableSorter('#applicantsTable');

            // Initialize table pagination
            tablePagination = new TablePagination();

            // Listen for sorting events to update pagination
            const applicantsTable = document.getElementById('applicantsTable');
            if (applicantsTable) {
                applicantsTable.addEventListener('tableSorted', function() {
                    tablePagination.updateFilteredRows();
                    tablePagination.currentPage = 1;
                    tablePagination.updateDisplay();
                });
            }
        });

        // Add functions for applicant management
        function openStatusModal(submissionId, currentStatus) {
            // Dynamic statuses from server and remark templates
            const statuses = <?php echo json_encode($available_statuses); ?>;
            const statusOptions = statuses.map(status =>
                `\n                <option value="${status}" ${status === currentStatus ? 'selected' : ''}>${status}</option>`
            ).join('');
            const templates = <?php echo json_encode($remark_templates); ?>;
            const templateOptions = templates.map(t => `<option value="${t}">${t}</option>`).join('');

            const statusModal = document.createElement('div');
            statusModal.id = 'statusModalOverlay';
            statusModal.innerHTML = `
                <div id="statusModal" style="position: fixed; left: 0; top: 0; width: 100%; height: 100%; background: linear-gradient(135deg, rgba(24, 165, 88, 0.1) 0%, rgba(19, 101, 21, 0.1) 100%); z-index: 1003; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(8px); padding: 20px;">
                    <div style="background: var(--color-card); border-radius: 24px; max-width: 600px; width: 95%; margin: 0 auto; max-height: calc(100vh - 40px); overflow: hidden; box-shadow: 0 25px 80px rgba(0,0,0,0.15), 0 10px 40px rgba(0,0,0,0.1); border: 1px solid var(--color-border); position: relative; display: flex; flex-direction: column; color: var(--color-text);">
                        <!-- Decorative Header Background -->
                        <div style="height: 120px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); position: relative; overflow: hidden;">
                            <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%; animation: float 6s ease-in-out infinite;"></div>
                            <div style="position: absolute; bottom: -30px; left: -30px; width: 100px; height: 100px; background: rgba(255,255,255,0.08); border-radius: 50%; animation: float 8s ease-in-out infinite reverse;"></div>

                            <!-- Close Button -->
                            <button type="button" onclick="(function(){ const el=document.getElementById('statusModalOverlay'); if(el) el.remove(); })()" style="position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.2); border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; transition: all 0.3s ease; backdrop-filter: blur(10px);">&times;</button>

                            <!-- Modal Icon and Title -->
                            <div style="position: absolute; bottom: -40px; left: 50%; transform: translateX(-50%); text-align: center;">
                                <div style="width: 80px; height: 80px; background: var(--color-card); border-radius: 20px; margin: 0 auto 16px auto; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 32px rgba(0,0,0,0.1); border: 4px solid rgba(255,255,255,0.9);">
                                    <svg style="width: 40px; height: 40px; color: var(--color-primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Content -->
                        <div style="flex: 1; overflow-y: auto; padding: 60px 40px 40px 40px; text-align: center;">
                            <h3 style="margin: 0 0 12px 0; color: #1a202c; font-size: 2rem; font-weight: 800; letter-spacing: -0.025em;">Update Applicant Status</h3>
                            <p style="color: #718096; margin: 0 0 32px 0; line-height: 1.6; font-size: 1.1rem;">Change status and optionally add remarks for this applicant</p>

                            <form style="text-align: left;">
                                <div style="margin-bottom: 28px; position: relative;">
                                    <label style="display: block; margin-bottom: 10px; font-weight: 700; color: #2d3748; font-size: 1rem; display: flex; align-items: center; gap: 8px;">
                                        Current Status
                                        <span style="color: var(--color-primary); font-weight: 700; margin-left: 8px;">${currentStatus}</span>
                                    </label>
                                    <select id="soloNewStatus" style="width: 100%; padding: 16px 20px; border: 3px solid #e2e8f0; border-radius: 16px; font-size: 1rem; transition: all 0.3s ease; box-sizing: border-box; background: #f8fafc; color: #2d3748; font-weight: 500;">
                                        ${statusOptions}
                                    </select>
                                </div>

                                <div style="margin-bottom: 28px; position: relative;">
                                    <label style="display: block; margin-bottom: 10px; font-weight: 700; color: #2d3748; font-size: 1rem; display: flex; align-items: center; gap: 8px;">Quick Remark Template</label>
                                    <select id="soloRemarkTemplate" style="width: 100%; padding: 16px 20px; border: 3px solid #e2e8f0; border-radius: 16px; font-size: 1rem; transition: all 0.3s ease; box-sizing: border-box; background: #f8fafc; color: #2d3748; font-weight: 500;">
                                        <option value="">None</option>
                                        ${templateOptions}
                                        <option value="__other__">Other (custom)...</option>
                                    </select>
                                </div>

                                <div id="soloRemarksContainer" style="margin-bottom: 28px; position: relative; display: none;">
                                    <label for="soloRemarks" style="display: block; margin-bottom: 10px; font-weight: 700; color: #2d3748; font-size: 1rem; display: flex; align-items: center; gap: 8px;">Remarks</label>
                                    <textarea id="soloRemarks" rows="4" style="width: 100%; padding: 16px 20px; border: 3px solid #e2e8f0; border-radius: 16px; font-size: 1rem; transition: all 0.3s ease; box-sizing: border-box; background: #f8fafc; color: #2d3748; font-weight: 500;" disabled></textarea>
                                </div>

                                <div style="margin-bottom: 12px; position: relative;">
                                    <label for="soloAllowUpdate" style="display: flex; align-items: center; gap: 10px; font-weight: 700; color: #2d3748; font-size: 1rem;">
                                        <input type="checkbox" id="soloAllowUpdate" style="width: 18px; height: 18px; accent-color: #18a558;">
                                        Allow applicant to update application submission
                                    </label>
                                </div>

                                <div style="display: flex; gap: 16px; justify-content: center; margin-top: 40px;">
                                    <button type="button" onclick="(function(){ const el=document.getElementById('statusModalOverlay'); if(el) el.remove(); })()" style="flex: 1; padding: 16px 32px; border: 3px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 16px; font-weight: 700; font-size: 1rem; cursor: pointer; outline: none;">Cancel</button>
                                    <button type="button" onclick="updateSoloStatus(${submissionId})" style="flex: 1; padding: 16px 32px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 16px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: all 0.3s ease; outline: none; box-shadow: 0 8px 32px rgba(24, 165, 88, 0.4); position: relative; overflow: hidden;">Update Status</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(statusModal);

            // Close modal when clicking outside the content
            const overlay = document.getElementById('statusModal');
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) {
                        const el = document.getElementById('statusModalOverlay');
                        if (el) el.remove();
                    }
                });
            }

            // Wire up solo remark template behavior
            const soloTemplateSel = document.getElementById('soloRemarkTemplate');
            const soloRemarksTa = document.getElementById('soloRemarks');
            const soloRemarksContainer = document.getElementById('soloRemarksContainer');
            if (soloTemplateSel && soloRemarksTa && soloRemarksContainer) {
                soloTemplateSel.addEventListener('change', function() {
                    const val = this.value;
                    if (val === '__other__') {
                        soloRemarksContainer.style.display = 'block';
                        soloRemarksTa.disabled = false;
                        soloRemarksTa.value = '';
                        soloRemarksTa.focus();
                    } else {
                        soloRemarksContainer.style.display = 'none';
                        soloRemarksTa.disabled = true;
                        soloRemarksTa.value = '';
                    }
                });
            }

            // Focus on the select element
            setTimeout(() => {
                const sel = document.getElementById('soloNewStatus');
                if (sel) sel.focus();
            }, 100);
        }

        // Global function for solo status update
        window.updateSoloStatus = function(submissionId) {

            const newStatus = document.getElementById('soloNewStatus').value;
            const templateVal = document.getElementById('soloRemarkTemplate')?.value || '';
            let remarks = '';
            if (templateVal === '__other__') {
                remarks = document.getElementById('soloRemarks').value;
            } else {
                remarks = templateVal; // use selected template or empty
            }
            const allowUpdate = document.getElementById('soloAllowUpdate')?.checked ? '1' : '0';

            // Show loader before navigation
            if (typeof showLoader === 'function') {
                try {
                    showLoader();
                } catch (e) {}
            }
            // Remove the modal (loader sits above all overlays)
            const statusModal = document.querySelector('div[style*="z-index: 1003"]');
            if (statusModal) {
                statusModal.remove();
            }

            // Submit as POST to include remarks
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'applicant_management.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'update_status';
            form.appendChild(actionInput);

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'submission_id';
            idInput.value = submissionId;
            form.appendChild(idInput);

            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = newStatus;
            form.appendChild(statusInput);

            const remarksInput = document.createElement('input');
            remarksInput.type = 'hidden';
            remarksInput.name = 'remarks';
            remarksInput.value = remarks;
            form.appendChild(remarksInput);

            const allowUpdateInput = document.createElement('input');
            allowUpdateInput.type = 'hidden';
            allowUpdateInput.name = 'allow_update';
            allowUpdateInput.value = allowUpdate;
            form.appendChild(allowUpdateInput);

            // Preserve cycle context
            const cycleId = new URLSearchParams(window.location.search).get('cycle_id');
            if (cycleId) {
                const cycleInput = document.createElement('input');
                cycleInput.type = 'hidden';
                cycleInput.name = 'cycle_id';
                cycleInput.value = cycleId;
                form.appendChild(cycleInput);
            }

            document.body.appendChild(form);
            // Defer submission slightly to allow the loader to render
            setTimeout(function() {
                form.submit();
            }, 120);
        };

        function exportApplicants() {
            // Open the export modal
            document.getElementById('exportModal').classList.add('show');
        }

        // Export Modal Functionality - Wait for DOM to be ready
        document.addEventListener('DOMContentLoaded', function() {
            const exportModal = document.getElementById('exportModal');
            const closeExportModal = document.getElementById('closeExportModal');
            const cancelExportModal = document.getElementById('cancelExportModal');
            const confirmExportModal = document.getElementById('confirmExportModal');
            const exportForm = document.getElementById('exportForm');
            const selectAllColumns = document.getElementById('selectAllColumns');
            const deselectAllColumns = document.getElementById('deselectAllColumns');

            function closeExportModalFunc() {
                exportModal.classList.remove('show');
                exportForm.reset();
                // Reset all checkboxes to checked state
                const columnCheckboxes = document.querySelectorAll('input[name="columns[]"]');
                columnCheckboxes.forEach(checkbox => {
                    if (checkbox.value !== 'cycle') {
                        checkbox.checked = true;
                    }
                });
            }

            function submitExport() {
                const formData = new FormData(exportForm);
                const fileType = formData.get('file_type');
                const selectedColumns = formData.getAll('columns[]');

                if (!fileType) {
                    alert('Please select a file format.');
                    return;
                }

                if (selectedColumns.length === 0) {
                    alert('Please select at least one column to export.');
                    return;
                }

                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'export_applicants.php';

                // Add file type
                const fileTypeInput = document.createElement('input');
                fileTypeInput.type = 'hidden';
                fileTypeInput.name = 'file_type';
                fileTypeInput.value = fileType;
                form.appendChild(fileTypeInput);

                // Add selected columns
                selectedColumns.forEach(column => {
                    const columnInput = document.createElement('input');
                    columnInput.type = 'hidden';
                    columnInput.name = 'columns[]';
                    columnInput.value = column;
                    form.appendChild(columnInput);
                });

                // Add cycle ID if available
                const urlParams = new URLSearchParams(window.location.search);
                const cycleId = urlParams.get('cycle_id');
                if (cycleId) {
                    const cycleInput = document.createElement('input');
                    cycleInput.type = 'hidden';
                    cycleInput.name = 'cycle_id';
                    cycleInput.value = cycleId;
                    form.appendChild(cycleInput);
                }

                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);

                // Close the modal
                closeExportModalFunc();
            }

            // Event listeners for export modal
            if (closeExportModal) {
                closeExportModal.addEventListener('click', closeExportModalFunc);
            }

            if (cancelExportModal) {
                cancelExportModal.addEventListener('click', closeExportModalFunc);
            }

            if (confirmExportModal) {
                confirmExportModal.addEventListener('click', submitExport);
            }

            // Close modal when clicking outside
            if (exportModal) {
                exportModal.addEventListener('click', function(event) {
                    if (event.target === exportModal) {
                        closeExportModalFunc();
                    }
                });
            }

            // Select/Deselect all columns functionality
            if (selectAllColumns) {
                selectAllColumns.addEventListener('click', function() {
                    const columnCheckboxes = document.querySelectorAll('input[name="columns[]"]');
                    columnCheckboxes.forEach(checkbox => {
                        checkbox.checked = true;
                    });
                });
            }

            if (deselectAllColumns) {
                deselectAllColumns.addEventListener('click', function() {
                    const columnCheckboxes = document.querySelectorAll('input[name="columns[]"]');
                    columnCheckboxes.forEach(checkbox => {
                        checkbox.checked = false;
                    });
                });
            }

            // Handle Enter key in the export modal
            if (exportModal) {
                exportModal.addEventListener('keypress', function(event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        submitExport();
                    }
                });
            }

        }); // End of DOMContentLoaded

        // Table Configuration Modal and Column Visibility
        function openTableConfig() {
            const modal = document.getElementById('tableConfigModal');
            if (modal) modal.classList.add('show');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const tableConfigModal = document.getElementById('tableConfigModal');
            const closeTableConfigModal = document.getElementById('closeTableConfigModal');
            const cancelTableConfigModal = document.getElementById('cancelTableConfigModal');
            const confirmTableConfigModal = document.getElementById('confirmTableConfigModal');

            const columnMap = {
                id: 3,
                email: 4,
                type: 5,
                status: 6,
                remarks: 7,
                submitted_date: 8,
                cycle: 9
            };

            // Extend with dynamic fields discovered server-side for the selected cycle
            const dynamicFields = <?php echo json_encode(array_column($dynamic_fields, 'name')); ?>;
            dynamicFields.forEach((key, i) => {
                columnMap[key] = 9 + (i + 1); // after Cycle (starts at 10)
            });

            function applyColumns(selected) {
                const table = document.getElementById('applicantsTable');
                if (!table) return;

                const theadCells = table.querySelector('thead tr')?.children || [];
                const rows = table.querySelectorAll('tbody tr');

                Object.entries(columnMap).forEach(([key, index]) => {
                    const visible = selected.includes(key);
                    const display = visible ? '' : 'none';

                    if (theadCells[index - 1]) {
                        theadCells[index - 1].style.display = display;
                    }
                    rows.forEach(row => {
                        const cell = row.children[index - 1];
                        if (cell) {
                            cell.style.display = display;
                        }
                    });
                });
            }

            function getSelectedColumns() {
                const defaults = ['id', 'email', 'type', 'status', 'remarks', 'submitted_date', 'cycle'];
                const saved = localStorage.getItem('applicantTableColumns');
                if (saved) {
                    try {
                        const parsed = JSON.parse(saved);
                        if (Array.isArray(parsed)) {
                            // Ensure newly added columns like 'remarks' appear by default
                            const merged = Array.from(new Set([...parsed, 'remarks']));
                            return merged;
                        }
                    } catch (e) {}
                }
                return defaults;
            }

            function syncCheckboxes(selected) {
                document.querySelectorAll('#tableConfigModal input[name="columns[]"]').forEach(cb => {
                    cb.checked = selected.includes(cb.value);
                });
            }

            function closeConfig() {
                if (tableConfigModal) {
                    tableConfigModal.classList.remove('show');
                }
            }

            // Initialize
            const initial = getSelectedColumns();
            applyColumns(initial);
            syncCheckboxes(initial);

            // Event listeners
            if (closeTableConfigModal) closeTableConfigModal.addEventListener('click', closeConfig);
            if (cancelTableConfigModal) cancelTableConfigModal.addEventListener('click', closeConfig);

            if (confirmTableConfigModal) {
                confirmTableConfigModal.addEventListener('click', function() {
                    const selected = Array.from(document.querySelectorAll('#tableConfigModal input[name="columns[]"]:checked')).map(cb => cb.value);
                    localStorage.setItem('applicantTableColumns', JSON.stringify(selected));
                    applyColumns(selected);
                    closeConfig();
                });
            }

            // Close modal when clicking outside
            if (tableConfigModal) {
                tableConfigModal.addEventListener('click', function(event) {
                    if (event.target === tableConfigModal) {
                        closeConfig();
                    }
                });
            }

            // Select/Deselect all
            const selectAllColumnsConfig = document.getElementById('selectAllColumnsConfig');
            const deselectAllColumnsConfig = document.getElementById('deselectAllColumnsConfig');

            if (selectAllColumnsConfig) {
                selectAllColumnsConfig.addEventListener('click', function() {
                    document.querySelectorAll('#tableConfigModal input[name="columns[]"]').forEach(cb => cb.checked = true);
                });
            }

            if (deselectAllColumnsConfig) {
                deselectAllColumnsConfig.addEventListener('click', function() {
                    document.querySelectorAll('#tableConfigModal input[name="columns[]"]').forEach(cb => cb.checked = false);
                });
            }

            // Initialize drag and drop functionality
            initializeDragAndDrop();
        });

        // Column Drag and Drop functionality
        function initializeDragAndDrop() {
            const table = document.getElementById('applicantsTable');
            const thead = table.querySelector('thead tr');
            const tbody = table.querySelector('tbody');
            let draggedColumn = null;
            let draggedIndex = null;

            // Add drag event listeners to all draggable column headers
            function addColumnDragListeners() {
                const headers = thead.querySelectorAll('th.draggable-column');
                headers.forEach((header, index) => {
                    header.addEventListener('dragstart', handleColumnDragStart);
                    header.addEventListener('dragover', handleColumnDragOver);
                    header.addEventListener('drop', handleColumnDrop);
                    header.addEventListener('dragend', handleColumnDragEnd);
                    header.addEventListener('dragenter', handleColumnDragEnter);
                    header.addEventListener('dragleave', handleColumnDragLeave);
                });
            }

            function handleColumnDragStart(e) {
                draggedColumn = this;
                draggedIndex = Array.from(thead.children).indexOf(this);
                this.style.opacity = '0.5';
                this.classList.add('column-dragging');

                // Set drag effect
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', draggedIndex.toString());
            }

            function handleColumnDragOver(e) {
                if (e.preventDefault) {
                    e.preventDefault();
                }
                e.dataTransfer.dropEffect = 'move';
                return false;
            }

            function handleColumnDragEnter(e) {
                if (this !== draggedColumn && this.classList.contains('draggable-column')) {
                    this.classList.add('column-drag-over');
                }
            }

            function handleColumnDragLeave(e) {
                this.classList.remove('column-drag-over');
            }

            function handleColumnDrop(e) {
                if (e.stopPropagation) {
                    e.stopPropagation();
                }

                if (draggedColumn !== this && this.classList.contains('draggable-column')) {
                    const targetIndex = Array.from(thead.children).indexOf(this);

                    // Reorder columns in header
                    if (draggedIndex < targetIndex) {
                        thead.insertBefore(draggedColumn, this.nextSibling);
                    } else {
                        thead.insertBefore(draggedColumn, this);
                    }

                    // Reorder corresponding cells in all body rows
                    reorderTableColumns(draggedIndex, targetIndex);
                }

                this.classList.remove('column-drag-over');
                return false;
            }

            function handleColumnDragEnd(e) {
                this.style.opacity = '';
                this.classList.remove('column-dragging');

                // Remove drag-over class from all headers
                const headers = thead.querySelectorAll('th');
                headers.forEach(header => {
                    header.classList.remove('column-drag-over');
                });

                draggedColumn = null;
                draggedIndex = null;
            }

            function reorderTableColumns(fromIndex, toIndex) {
                const rows = tbody.querySelectorAll('tr');

                rows.forEach(row => {
                    const cells = Array.from(row.children);
                    if (cells.length > fromIndex && cells.length > toIndex) {
                        const draggedCell = cells[fromIndex];

                        if (fromIndex < toIndex) {
                            row.insertBefore(draggedCell, cells[toIndex].nextSibling);
                        } else {
                            row.insertBefore(draggedCell, cells[toIndex]);
                        }
                    }
                });
            }

            // Initialize column drag listeners
            addColumnDragListeners();

            // Re-initialize when table content changes
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        addColumnDragListeners();
                    }
                });
            });

            observer.observe(thead, {
                childList: true,
                subtree: true
            });
            observer.observe(tbody, {
                childList: true,
                subtree: true
            });
        }
    </script>

    <!-- Table Configuration Modal -->
    <div id="tableConfigModal" class="modal-overlay">
        <div style="background: var(--color-card); color: var(--color-text); border-radius: 20px; max-width: 600px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); position: relative; max-height: 90vh; overflow-y: auto;">
            <!-- Close Button -->
            <button type="button" id="closeTableConfigModal" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px; transition: all 0.2s ease; z-index: 10;">&times;</button>

            <!-- Modal Header -->
            <div style="padding: 40px 32px 24px 32px; text-align: center;">
                <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 24px auto; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                </div>
                <h3 style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.6rem; font-weight: 700; letter-spacing: -0.025em;">Table Configuration</h3>
                <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Select which columns to display in the applicants table</p>
            </div>

            <!-- Modal Body -->
            <div style="padding: 0 32px 24px 32px;">
                <div style="display: flex; gap: 12px; margin-bottom: 12px; justify-content: center;">
                    <button type="button" id="selectAllColumnsConfig" style="padding: 10px 16px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 10px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Select All</button>
                    <button type="button" id="deselectAllColumnsConfig" style="padding: 10px 16px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 10px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Deselect All</button>
                </div>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;">
                    <label class="column-option" style="display: flex; align-items: center; cursor: pointer; padding: 8px; border-radius: 8px; transition: background-color 0.2s ease;">
                        <input type="checkbox" name="columns[]" value="id" checked style="margin-right: 8px; accent-color: var(--color-primary);">
                        <span style="font-size: 0.9rem; color: #2d3748;">Submission ID</span>
                    </label>

                    <label class="column-option" style="display: flex; align-items: center; cursor: pointer; padding: 8px; border-radius: 8px; transition: background-color 0.2s ease;">
                        <input type="checkbox" name="columns[]" value="email" checked style="margin-right: 8px; accent-color: var(--color-primary);">
                        <span style="font-size: 0.9rem; color: #2d3748;">Email</span>
                    </label>
                    <label class="column-option" style="display: flex; align-items: center; cursor: pointer; padding: 8px; border-radius: 8px; transition: background-color 0.2s ease;">
                        <input type="checkbox" name="columns[]" value="type" checked style="margin-right: 8px; accent-color: var(--color-primary);">
                        <span style="font-size: 0.9rem; color: #2d3748;">Application Type</span>
                    </label>
                    <label class="column-option" style="display: flex; align-items: center; cursor: pointer; padding: 8px; border-radius: 8px; transition: background-color 0.2s ease;">
                        <input type="checkbox" name="columns[]" value="status" checked style="margin-right: 8px; accent-color: var(--color-primary);">
                        <span style="font-size: 0.9rem; color: #2d3748;">Status</span>
                    </label>
                    <label class="column-option" style="display: flex; align-items: center; cursor: pointer; padding: 8px; border-radius: 8px; transition: background-color 0.2s ease;">
                        <input type="checkbox" name="columns[]" value="remarks" checked style="margin-right: 8px; accent-color: var(--color-primary);">
                        <span style="font-size: 0.9rem; color: #2d3748;">Remarks</span>
                    </label>
                    <label class="column-option" style="display: flex; align-items: center; cursor: pointer; padding: 8px; border-radius: 8px; transition: background-color 0.2s ease;">
                        <input type="checkbox" name="columns[]" value="submitted_date" checked style="margin-right: 8px; accent-color: var(--color-primary);">
                        <span style="font-size: 0.9rem; color: #2d3748;">Submitted Date</span>
                    </label>
                    <label class="column-option" style="display: flex; align-items: center; cursor: pointer; padding: 8px; border-radius: 8px; transition: background-color 0.2s ease;">
                        <input type="checkbox" name="columns[]" value="cycle" checked style="margin-right: 8px; accent-color: var(--color-primary);">
                        <span style="font-size: 0.9rem; color: #2d3748;">Cycle</span>
                    </label>
                    <?php if (!empty($dynamic_fields)) { ?>
                        <div style="grid-column: span 2; margin-top: 8px; font-weight: 600; color: #4a5568;">Form Fields</div>
                        <?php foreach ($dynamic_fields as $f) { ?>
                            <label class="column-option" style="display: flex; align-items: center; cursor: pointer; padding: 8px; border-radius: 8px; transition: background-color 0.2s ease;">
                                <input type="checkbox" name="columns[]" value="<?php echo htmlspecialchars($f['name']); ?>" style="margin-right: 8px; accent-color: var(--color-primary);">
                                <span style="font-size: 0.9rem; color: #2d3748;"><?php echo htmlspecialchars($f['label']); ?></span>
                            </label>
                    <?php }
                    } ?>
                </div>
            </div>

            <!-- Modal Footer -->
            <div style="padding: 20px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
                <button type="button" id="cancelTableConfigModal" style="flex: 1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Cancel</button>
                <button type="button" id="confirmTableConfigModal" style="flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Apply</button>
            </div>
        </div>
    </div>

    <!-- Export Modal -->
    <div id="exportModal" class="modal-overlay">
        <div style="background: var(--color-card); color: var(--color-text); border-radius: 20px; max-width: 600px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); position: relative; max-height: 90vh; overflow-y: auto;">
            <!-- Close Button -->
            <button type="button" id="closeExportModal" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px; transition: all 0.2s ease; z-index: 10;">&times;</button>

            <!-- Modal Header -->
            <div style="padding: 40px 32px 24px 32px; text-align: center;">
                <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 18px; margin: 0 auto 24px auto; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <h3 style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.6rem; font-weight: 700; letter-spacing: -0.025em;">Export Applicants</h3>
                <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Choose file format and select columns to export</p>
            </div>

            <!-- Modal Body -->
            <div style="padding: 0 32px 24px 32px;">
                <form id="exportForm">
                    <!-- File Type Selection -->
                    <div style="margin-bottom: 24px;">
                        <label style="display: block; margin-bottom: 12px; font-weight: 600; color: #2d3748; font-size: 1rem;">File Format</label>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
                            <label style="display: flex; align-items: center; padding: 16px; border: 2px solid #e2e8f0; border-radius: 12px; cursor: pointer; transition: all 0.2s ease; background: #f7fafc;" class="file-type-option">
                                <input type="radio" name="file_type" value="pdf" style="margin-right: 12px; accent-color: #dc2626;">
                                <div>
                                    <div style="font-weight: 600; color: #dc2626; font-size: 0.9rem;">PDF</div>
                                    <div style="font-size: 0.8rem; color: #718096;">Portable Document</div>
                                </div>
                            </label>
                            <label style="display: flex; align-items: center; padding: 16px; border: 2px solid #e2e8f0; border-radius: 12px; cursor: pointer; transition: all 0.2s ease; background: #f7fafc;" class="file-type-option">
                                <input type="radio" name="file_type" value="excel" style="margin-right: 12px; accent-color: #059669;">
                                <div>
                                    <div style="font-weight: 600; color: #059669; font-size: 0.9rem;">Excel</div>
                                    <div style="font-size: 0.8rem; color: #718096;">Spreadsheet</div>
                                </div>
                            </label>
                            <label style="display: flex; align-items: center; padding: 16px; border: 2px solid #e2e8f0; border-radius: 12px; cursor: pointer; transition: all 0.2s ease; background: #f7fafc;" class="file-type-option">
                                <input type="radio" name="file_type" value="docs" style="margin-right: 12px; accent-color: #2563eb;">
                                <div>
                                    <div style="font-weight: 600; color: #2563eb; font-size: 0.9rem;">Word</div>
                                    <div style="font-size: 0.8rem; color: #718096;">Document</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Column Selection -->
                    <div style="margin-bottom: 24px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <label style="font-weight: 600; color: #2d3748; font-size: 1rem;">Select Columns to Export</label>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" id="selectAllColumns" style="padding: 6px 12px; border: 1px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 6px; cursor: pointer; font-size: 0.8rem; transition: all 0.2s ease;">Select All</button>
                                <button type="button" id="deselectAllColumns" style="padding: 6px 12px; border: 1px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 6px; cursor: pointer; font-size: 0.8rem; transition: all 0.2s ease;">Deselect All</button>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; max-height: 200px; overflow-y: auto; padding: 16px; border: 2px solid #e2e8f0; border-radius: 12px; background: #f7fafc;">
                            <label style="display: flex; align-items: center; cursor: pointer; padding: 8px; border-radius: 8px; transition: background-color 0.2s ease;" class="column-option">
                                <input type="checkbox" name="columns[]" value="id" checked style="margin-right: 8px; accent-color: #667eea;">
                                <span style="font-size: 0.9rem; color: #2d3748;">Submission ID</span>
                            </label>
                            <label style="display: flex; align-items: center; cursor: pointer; padding: 8px; border-radius: 8px; transition: background-color 0.2s ease;" class="column-option">
                                <input type="checkbox" name="columns[]" value="name" checked style="margin-right: 8px; accent-color: #667eea;">
                                <span style="font-size: 0.9rem; color: #2d3748;">Name</span>
                            </label>
                            <label style="display: flex; align-items: center; cursor: pointer; padding: 8px; border-radius: 8px; transition: background-color 0.2s ease;" class="column-option">
                                <input type="checkbox" name="columns[]" value="email" checked style="margin-right: 8px; accent-color: #667eea;">
                                <span style="font-size: 0.9rem; color: #2d3748;">Email</span>
                            </label>
                            <label style="display: flex; align-items: center; cursor: pointer; padding: 8px; border-radius: 8px; transition: background-color 0.2s ease;" class="column-option">
                                <input type="checkbox" name="columns[]" value="type" checked style="margin-right: 8px; accent-color: #667eea;">
                                <span style="font-size: 0.9rem; color: #2d3748;">Application Type</span>
                            </label>
                            <label style="display: flex; align-items: center; cursor: pointer; padding: 8px; border-radius: 8px; transition: background-color 0.2s ease;" class="column-option">
                                <input type="checkbox" name="columns[]" value="status" checked style="margin-right: 8px; accent-color: #667eea;">
                                <span style="font-size: 0.9rem; color: #2d3748;">Status</span>
                            </label>
                            <label style="display: flex; align-items: center; cursor: pointer; padding: 8px; border-radius: 8px; transition: background-color 0.2s ease;" class="column-option">
                                <input type="checkbox" name="columns[]" value="submitted" checked style="margin-right: 8px; accent-color: #667eea;">
                                <span style="font-size: 0.9rem; color: #2d3748;">Submitted Date</span>
                            </label>
                            <label style="display: flex; align-items: center; cursor: pointer; padding: 8px; border-radius: 8px; transition: background-color 0.2s ease;" class="column-option">
                                <input type="checkbox" name="columns[]" value="cycle" style="margin-right: 8px; accent-color: #667eea;">
                                <span style="font-size: 0.9rem; color: #2d3748;">Admission Cycle</span>
                            </label>
                            <?php if (!empty($dynamic_fields)) { ?>
                                <div style="grid-column: span 2; margin-top: 8px; font-weight: 600; color: #4a5568; border-top: 1px solid #e2e8f0; padding-top: 8px;">Form Fields</div>
                                <?php foreach ($dynamic_fields as $f) { ?>
                                    <label style="display: flex; align-items: center; cursor: pointer; padding: 8px; border-radius: 8px; transition: background-color 0.2s ease;" class="column-option">
                                        <input type="checkbox" name="columns[]" value="<?php echo htmlspecialchars($f['name']); ?>" style="margin-right: 8px; accent-color: var(--color-primary);">
                                        <span style="font-size: 0.9rem; color: #2d3748;"><?php echo htmlspecialchars($f['label']); ?></span>
                                    </label>
                            <?php }
                            } ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Modal Footer -->
            <div style="padding: 20px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
                <button type="button" id="cancelExportModal" style="flex: 1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
                <button type="button" id="confirmExportModal" style="flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);">Export Data</button>
            </div>
        </div>
    </div>
</body>

</html>