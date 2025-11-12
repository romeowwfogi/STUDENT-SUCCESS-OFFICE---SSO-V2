<?php
// Protect page
require_once 'middleware/auth.php';
include 'connection/db_connect.php';
require_once 'function/decrypt.php';

// Validate schedule_id
if (!isset($_GET['schedule_id'])) {
    header('Location: schedule_management.php');
    exit;
}
$schedule_id = (int)($_GET['schedule_id']);

// Fetch schedule details
$schedule = null;
if ($stmt = $conn->prepare("SELECT schedule_id, floor, room, capacity, start_date_and_time, status FROM ExamSchedules WHERE schedule_id = ?")) {
    $stmt->bind_param('i', $schedule_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $schedule = $res->fetch_assoc();
    }
    $stmt->close();
}

if (!$schedule) {
    // If schedule not found, go back to management page
    header('Location: schedule_management.php');
    exit;
}

// Quick booked count for capacity checks
$booked_count = 0;
if ($stmtCount = $conn->prepare("SELECT COUNT(*) AS cnt FROM ExamRegistrations WHERE schedule_id = ?")) {
    $stmtCount->bind_param('i', $schedule_id);
    $stmtCount->execute();
    $resCount = $stmtCount->get_result();
    if ($resCount && ($rc = $resCount->fetch_assoc())) {
        $booked_count = (int)$rc['cnt'];
    }
    $stmtCount->close();
}

// Handle replacing room assignment (move or add) for selected applicants
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'replace_room') {
    $selected_user_ids = isset($_POST['user_ids']) && is_array($_POST['user_ids']) ? array_unique(array_map('intval', $_POST['user_ids'])) : [];
    $capacity = (int)$schedule['capacity'];
    $current_booked = (int)$booked_count; // from initial count query above

    $moved = 0;
    if (!empty($selected_user_ids) && $current_booked < $capacity) {
        // Compute next registration_id for inserts
        $nextId = 1;
        if ($resMax = $conn->query("SELECT IFNULL(MAX(registration_id), 0) + 1 AS next_id FROM ExamRegistrations")) {
            if ($rowMax = $resMax->fetch_assoc()) {
                $nextId = (int)$rowMax['next_id'];
            }
        }

        // Prepare statements
        $findStmt = $conn->prepare("SELECT registration_id, schedule_id FROM ExamRegistrations WHERE user_id = ? LIMIT 1");
        $updateStmt = $conn->prepare("UPDATE ExamRegistrations SET schedule_id = ? WHERE registration_id = ?");
        $insertStmt = $conn->prepare("INSERT INTO ExamRegistrations (registration_id, user_id, schedule_id) VALUES (?, ?, ?)");

        foreach ($selected_user_ids as $uid) {
            if ($current_booked >= $capacity) break; // respect capacity
            if ($uid <= 0) continue;

            // Check if user already has a registration (any schedule)
            $findStmt->bind_param('i', $uid);
            $findStmt->execute();
            $res = $findStmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;

            if ($row) {
                $regId = (int)$row['registration_id'];
                $existingScheduleId = (int)$row['schedule_id'];
                if ($existingScheduleId === $schedule_id) {
                    // Already in this room, skip
                    continue;
                }
                // Move to this room
                $updateStmt->bind_param('ii', $schedule_id, $regId);
                if ($updateStmt->execute()) {
                    $moved++;
                    $current_booked++;
                }
            } else {
                // No registration yet, insert new
                $insertStmt->bind_param('iii', $nextId, $uid, $schedule_id);
                if ($insertStmt->execute()) {
                    $moved++;
                    $current_booked++;
                    $nextId++;
                }
            }
        }

        // Close statements
        $findStmt->close();
        $updateStmt->close();
        $insertStmt->close();

        // Update current schedule status based on new booked count
        $newStatus = ($current_booked >= $capacity) ? 'Full' : 'Open';
        if ($updStmt = $conn->prepare("UPDATE ExamSchedules SET status = ? WHERE schedule_id = ?")) {
            $updStmt->bind_param('si', $newStatus, $schedule_id);
            $updStmt->execute();
            $updStmt->close();
        }
    }

    // Feedback
    if (!isset($_SESSION)) session_start();
    $_SESSION['message'] = ['type' => 'success', 'text' => 'Replaced room for ' . $moved . ' applicant(s).'];

    header('Location: schedule_room_view.php?schedule_id=' . urlencode($schedule_id));
    exit;
}

// Handle replacing room to a selected target schedule (from current room to another)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'replace_room_to_target') {
    $selected_user_ids = isset($_POST['user_ids']) && is_array($_POST['user_ids']) ? array_unique(array_map('intval', $_POST['user_ids'])) : [];
    $target_schedule_id = isset($_POST['target_schedule_id']) ? (int)$_POST['target_schedule_id'] : 0;

    if ($target_schedule_id <= 0) {
        if (!isset($_SESSION)) session_start();
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Please select a valid target room.'];
        header('Location: schedule_room_view.php?schedule_id=' . urlencode($schedule_id));
        exit;
    }

    // Get target room capacity and current booked count
    $target_capacity = 0;
    if ($stmtT = $conn->prepare("SELECT capacity FROM ExamSchedules WHERE schedule_id = ?")) {
        $stmtT->bind_param('i', $target_schedule_id);
        $stmtT->execute();
        $resT = $stmtT->get_result();
        if ($resT && ($rowT = $resT->fetch_assoc())) {
            $target_capacity = (int)$rowT['capacity'];
        }
        $stmtT->close();
    }

    $target_booked = 0;
    if ($stmtTB = $conn->prepare("SELECT COUNT(*) AS cnt FROM ExamRegistrations WHERE schedule_id = ?")) {
        $stmtTB->bind_param('i', $target_schedule_id);
        $stmtTB->execute();
        $resTB = $stmtTB->get_result();
        if ($resTB && ($rowTB = $resTB->fetch_assoc())) {
            $target_booked = (int)$rowTB['cnt'];
        }
        $stmtTB->close();
    }

    $moved = 0;
    $skipped_full = 0;
    if (!empty($selected_user_ids) && $target_capacity > 0) {
        // Prepare statements
        $findStmt = $conn->prepare("SELECT registration_id, schedule_id FROM ExamRegistrations WHERE user_id = ? LIMIT 1");
        $updateStmt = $conn->prepare("UPDATE ExamRegistrations SET schedule_id = ? WHERE registration_id = ?");
        $insertStmt = $conn->prepare("INSERT INTO ExamRegistrations (registration_id, user_id, schedule_id) VALUES (?, ?, ?)");

        // Next registration id for inserts
        $nextId = 1;
        if ($resMax = $conn->query("SELECT IFNULL(MAX(registration_id), 0) + 1 AS next_id FROM ExamRegistrations")) {
            if ($rowMax = $resMax->fetch_assoc()) {
                $nextId = (int)$rowMax['next_id'];
            }
        }

        foreach ($selected_user_ids as $uid) {
            if ($target_booked >= $target_capacity) {
                $skipped_full++;
                break;
            }
            if ($uid <= 0) continue;

            $findStmt->bind_param('i', $uid);
            $findStmt->execute();
            $res = $findStmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;

            if ($row) {
                $regId = (int)$row['registration_id'];
                $existingScheduleId = (int)$row['schedule_id'];
                if ($existingScheduleId === $target_schedule_id) {
                    continue; // already in target
                }
                $updateStmt->bind_param('ii', $target_schedule_id, $regId);
                if ($updateStmt->execute()) {
                    $moved++;
                    $target_booked++;
                }
            } else {
                $insertStmt->bind_param('iii', $nextId, $uid, $target_schedule_id);
                if ($insertStmt->execute()) {
                    $moved++;
                    $target_booked++;
                    $nextId++;
                }
            }
        }

        // Close statements
        $findStmt->close();
        $updateStmt->close();
        $insertStmt->close();

        // Update statuses for current and target rooms
        // Current room
        $currCount = 0;
        if ($stmtCB = $conn->prepare("SELECT COUNT(*) AS cnt FROM ExamRegistrations WHERE schedule_id = ?")) {
            $stmtCB->bind_param('i', $schedule_id);
            $stmtCB->execute();
            $resCB = $stmtCB->get_result();
            if ($resCB && ($rowCB = $resCB->fetch_assoc())) {
                $currCount = (int)$rowCB['cnt'];
            }
            $stmtCB->close();
        }
        $currStatus = ($currCount >= (int)$schedule['capacity']) ? 'Full' : 'Open';
        if ($stmtUS = $conn->prepare("UPDATE ExamSchedules SET status = ? WHERE schedule_id = ?")) {
            $stmtUS->bind_param('si', $currStatus, $schedule_id);
            $stmtUS->execute();
            $stmtUS->close();
        }

        // Target room
        $tStatus = ($target_booked >= $target_capacity) ? 'Full' : 'Open';
        if ($stmtUT = $conn->prepare("UPDATE ExamSchedules SET status = ? WHERE schedule_id = ?")) {
            $stmtUT->bind_param('si', $tStatus, $target_schedule_id);
            $stmtUT->execute();
            $stmtUT->close();
        }
    }

    if (!isset($_SESSION)) session_start();
    $msg = 'Moved ' . $moved . ' applicant(s) to room #' . $target_schedule_id;
    if ($skipped_full > 0) {
        $msg .= ' (stopped due to target capacity).';
    }
    $_SESSION['message'] = ['type' => 'success', 'text' => $msg];

    header('Location: schedule_room_view.php?schedule_id=' . urlencode($schedule_id));
    exit;
}

// Fetch applicants registered in this schedule (room)
$applicants = [];
if ($stmt2 = $conn->prepare(
    "SELECT 
        er.registration_id,
        er.user_id,
        u.email AS user_email,
        s.id AS submission_id,
        d_fname.field_value AS first_name,
        d_lname.field_value AS last_name,
        TRIM(CONCAT_WS(' ', uf.first_name, NULLIF(uf.middle_name,''), uf.last_name, NULLIF(uf.suffix,''))) AS registered_full_name,
        at.name AS applicant_type,
        CASE
            WHEN ac.academic_year_start IS NOT NULL AND ac.academic_year_end IS NOT NULL AND ac.academic_year_start > 0 AND ac.academic_year_end > 0
                THEN CONCAT('Academic Year ', ac.academic_year_start, '–', ac.academic_year_end)
            ELSE CONCAT(
                DATE_FORMAT(ac.admission_date_time_start, '%b %e, %Y %H:%i'),
                ' – ',
                DATE_FORMAT(ac.admission_date_time_end, '%b %e, %Y %H:%i')
            )
        END AS academic_year,
        ac.admission_date_time_start AS cycle_start,
        ac.admission_date_time_end AS cycle_end,
        CASE WHEN ap.id IS NULL THEN 0 ELSE 1 END AS has_permit,
        ap.status AS permit_status,
        s.status AS application_status,
        s.remarks AS application_remarks
     FROM ExamRegistrations er
     LEFT JOIN users u ON er.user_id = u.id
     LEFT JOIN submissions s ON s.user_id = er.user_id
     LEFT JOIN submission_data d_fname ON (s.id = d_fname.submission_id AND d_fname.field_name = 'first_name')
     LEFT JOIN submission_data d_lname ON (s.id = d_lname.submission_id AND d_lname.field_name = 'last_name')
     LEFT JOIN user_fullname uf ON uf.user_id = er.user_id
     LEFT JOIN applicant_types at ON s.applicant_type_id = at.id
     LEFT JOIN admission_cycles ac ON at.admission_cycle_id = ac.id
     LEFT JOIN application_permit ap ON ap.id = (SELECT MAX(ap2.id) FROM application_permit ap2 WHERE ap2.user_id = er.user_id)
     WHERE er.schedule_id = ?
     ORDER BY er.registration_id ASC"
)) {
    $stmt2->bind_param('i', $schedule_id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($row = $res2->fetch_assoc()) {
        $applicants[] = $row;
    }
    $stmt2->close();
}

// Compute booked count based on current list
$booked_count = count($applicants);

// Build available applicants list (not yet registered in this room)
$registered_user_ids = array_map(function ($r) {
    return (int)($r['user_id'] ?? 0);
}, $applicants);
$registered_set = array_flip($registered_user_ids);
$available_applicants = [];
$avail_sql = "SELECT 
        s.user_id,
        s.id AS submission_id,
        u.email AS user_email,
        TRIM(CONCAT_WS(' ', uf.first_name, NULLIF(uf.middle_name,''), uf.last_name, NULLIF(uf.suffix,''))) AS registered_full_name,
        at.name AS applicant_type,
        CASE
            WHEN ac.academic_year_start IS NOT NULL AND ac.academic_year_end IS NOT NULL AND ac.academic_year_start > 0 AND ac.academic_year_end > 0
                THEN CONCAT('Academic Year ', ac.academic_year_start, '–', ac.academic_year_end)
            ELSE CONCAT(
                DATE_FORMAT(ac.admission_date_time_start, '%b %e, %Y %H:%i'),
                ' – ',
                DATE_FORMAT(ac.admission_date_time_end, '%b %e, %Y %H:%i')
            )
        END AS academic_year,
        s.status AS application_status,
        s.remarks AS application_remarks
    FROM submissions s
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN user_fullname uf ON uf.user_id = s.user_id
    LEFT JOIN applicant_types at ON s.applicant_type_id = at.id
    LEFT JOIN admission_cycles ac ON at.admission_cycle_id = ac.id
    WHERE NOT EXISTS (
        SELECT 1 FROM ExamRegistrations er WHERE er.user_id = s.user_id
    )
    ORDER BY s.submitted_at DESC";
if ($resAvail = $conn->query($avail_sql)) {
    while ($row = $resAvail->fetch_assoc()) {
        $uid = (int)($row['user_id'] ?? 0);
        if ($uid <= 0) continue;
        if (isset($registered_set[$uid])) continue; // skip already registered
        // Deduplicate by user_id, keep first (latest submission)
        if (!isset($available_applicants[$uid])) {
            $available_applicants[$uid] = $row;
        }
    }
}

// Build schedules list and booked counts for Replace Room modal
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room View • Schedule <?php echo htmlspecialchars($schedule['schedule_id']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin_style.css">

    <link rel="stylesheet" href="dashboard.css">
    <style>
        /* Ensure anchor-based action buttons look identical to button elements */
        .table__btn,
        .table__btn:link,
        .table__btn:visited,
        .table__btn:hover {
            text-decoration: none !important;
            font-weight: 150;
        }

        /* Uniform Update-style button variant to match Admission Management */
        .table__btn--update {
            background-color: var(--color-card);
            color: var(--color-accent);
            border: 1.5px solid rgba(16, 185, 129, 0.35);
        }

        .table__btn--update:hover {
            background-color: var(--color-accent);
            color: var(--color-white);
            border-color: var(--color-accent);
        }
    </style>
</head>

<body>
    <?php include 'includes/mobile_navbar.php'; ?>

    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="header">
                <div class="header__left">
                    <h1>Room View</h1>
                    <p class="header__subtitle">Schedule #<?php echo htmlspecialchars($schedule['schedule_id']); ?> • <?php echo htmlspecialchars($schedule['floor']); ?> • <?php echo htmlspecialchars($schedule['room']); ?></p>
                </div>
                <div class="header__actions">
                    <button class="btn btn--secondary" onclick="window.location.href='schedule_management.php'">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Schedule
                    </button>
                    <button type="button" id="btnAddApplicants" class="btn btn--primary" style="margin-left: 8px;">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11v6m3-3h-6M8 7a4 4 0 110 8 4 4 0 010-8" />
                        </svg>
                        Add Applicants
                    </button>
                </div>
            </header>

            <section class="section active" id="room_view_section" style="margin: 0 20px;">
                <div class="table-container" style="margin-bottom: 16px;">
                    <div class="table-container__header" style="gap: 12px;">
                        <h2 class="table-container__title">Room Details</h2>
                        <p class="table-container__subtitle">Capacity: <?php echo (int)$schedule['capacity']; ?> • Booked: <?php echo (int)$booked_count; ?> • Starts: <?php echo date('M j, Y, g:i A', strtotime($schedule['start_date_and_time'])); ?></p>
                    </div>
                    <div>
                        <?php $statusClass = ($schedule['status'] === 'Full') ? 'badge--danger' : 'badge--success'; ?>
                        <span class="badge <?php echo $statusClass; ?>">Status: <?php echo htmlspecialchars($schedule['status']); ?></span>
                    </div>
                </div>



                <div class="table-container">
                    <div class="table-container__header">
                        <h2 class="table-container__title">Applicants in this Room</h2>
                        <p class="table-container__subtitle">Registered examinees for this schedule</p>
                    </div>
                    <table class="table" id="roomApplicantsTable">
                        <thead>
                            <tr>
                                <th>Registered Full Name</th>
                                <th>Email</th>
                                <th>Applicant Type</th>
                                <th>Academic Year</th>
                                <th>Status</th>
                                <th>Remarks</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($applicants)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; color:#666; padding:16px;">Empty — no applicants registered for this room.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($applicants as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['registered_full_name'] ?? 'Unknown'); ?></td>
                                        <td><?php
                                            $email = $row['user_email'] ?? null;
                                            $dec = $email ? decryptData($email) : null;
                                            echo htmlspecialchars(($dec !== false && !empty($dec)) ? $dec : ($email ?? 'N/A'));
                                            ?></td>
                                        <td><?php echo htmlspecialchars($row['applicant_type'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($row['academic_year'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($row['application_status'] ?? '—'); ?></td>
                                        <td>
                                            <?php
                                            $remarksRaw = trim($row['application_remarks'] ?? '');
                                            if ($remarksRaw === '') {
                                                echo '';
                                            } else {
                                                $words = preg_split('/\s+/', $remarksRaw);
                                                $hasMore = is_array($words) && count($words) > 30;
                                                $previewWords = is_array($words) ? array_slice($words, 0, 30) : [];
                                                $preview = htmlspecialchars(implode(' ', $previewWords));
                                                if ($hasMore) {
                                                    $preview .= '...';
                                                }
                                                $fullEsc = htmlspecialchars($remarksRaw);
                                                echo '<span class="remarks-preview">' . $preview . '</span>';
                                                if ($hasMore) {
                                                    echo ' <span class="remarks-full" style="display:none;">' . $fullEsc . '</span>';
                                                    echo ' <button type="button" class="see-more-toggle" style="padding:0; margin-left:6px; background:none; border:none; color:#004aad; cursor:pointer;">See more</button>';
                                                }
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['submission_id'])): ?>
                                                <a class="table__btn table__btn--update" href="view_submission.php?id=<?php echo (int)$row['submission_id']; ?>">View</a>
                                                <button type="button" class="table__btn table__btn--update replace-room-btn" data-user-id="<?php echo (int)$row['user_id']; ?>" style="margin-left:6px;" data-tooltip="Replace room">Replace Room</button>
                                                <?php $hasPermit = !empty($row['has_permit']); ?>
                                                <button
                                                    type="button"
                                                    class="table__btn table__btn--update send-permit-btn"
                                                    data-user-id="<?php echo (int)$row['user_id']; ?>"
                                                    data-applicant-name="<?php echo htmlspecialchars($row['registered_full_name'] ?? ''); ?>"
                                                    data-period-start="<?php
                                                                        $cs = isset($row['cycle_start']) ? $row['cycle_start'] : '';
                                                                        echo htmlspecialchars($cs ? date('Y-m-d', strtotime($cs)) : '', ENT_QUOTES);
                                                                        ?>"
                                                    data-period-end="<?php
                                                                        $ce = isset($row['cycle_end']) ? $row['cycle_end'] : '';
                                                                        echo htmlspecialchars($ce ? date('Y-m-d', strtotime($ce)) : '', ENT_QUOTES);
                                                                        ?>"
                                                    data-action="<?php echo $hasPermit ? 'resend' : 'send'; ?>"
                                                    style="margin-left:6px;"
                                                    data-tooltip="<?php echo $hasPermit ? 'Resend exam permit' : 'Send exam permit'; ?>">
                                                    <?php echo $hasPermit ? 'Resend Permit' : 'Send Permit'; ?>
                                                </button>
                                            <?php else: ?>
                                                <span class="table__btn table__btn--disabled" style="opacity:.6; cursor:not-allowed;">View</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</body>
<!-- Add Applicants Modal (styled like Admission Management) -->
<div id="addApplicantsModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color: rgba(0,0,0,0.4); z-index:1001; align-items:center; justify-content:center; backdrop-filter: blur(4px);">
    <div role="dialog" aria-modal="true" aria-labelledby="addApplicantsModalTitle" style="background: var(--color-card); border-radius: 20px; max-width: 960px; width: 92%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); animation: slideUp 0.3s ease; position: relative; display:flex; flex-direction:column; max-height: 86vh;">
        <!-- Close Button -->
        <button type="button" id="closeAddApplicantsModal" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-weight: 700;">
            &times;
        </button>

        <!-- Modal Header -->
        <div style="padding: 40px 32px 16px 32px; text-align: center;">
            <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 16px auto; display: flex; align-items: center; justify-content: center;">
                <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
            </div>
            <h3 id="addApplicantsModalTitle" style="margin:0 0 6px 0; color:#1a202c; font-size:1.6rem; font-weight:700; letter-spacing:-0.025em;">Add Applicants to Room</h3>
            <p style="color:#718096; margin:0; line-height:1.5; font-size:0.95rem;">Select applicants and add them to this schedule</p>
        </div>

        <!-- Modal Body -->
        <div style="padding: 0 32px 0 32px; flex: 1 1 auto; overflow-y: auto;">
            <form method="post" action="schedule_room_view.php?schedule_id=<?php echo urlencode($schedule['schedule_id']); ?>" id="addApplicantsForm">
                <input type="hidden" name="action" value="replace_room" />
                <input type="hidden" name="schedule_id" value="<?php echo (int)$schedule['schedule_id']; ?>" />
                <div style="display:flex; gap: 12px; align-items:center; margin-bottom: 12px;">
                    <label style="display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" id="selectAllAvailable" />
                        Select all
                    </label>
                    <input type="text" id="addApplicantsSearch" placeholder="Search by name or email..." style="flex:1; max-width: 360px; padding: 10px 12px; border: 2px solid #e2e8f0; border-radius: 12px; background:#f7fafc; color:#2d3748;">
                </div>
                <div style="max-height: 360px; overflow:auto; border:1px solid #e5e7eb; border-radius:12px;">
                    <table class="table" id="availableApplicantsTable">
                        <thead>
                            <tr>
                                <th style="width:42px;">Select</th>
                                <th>Registered Full Name</th>
                                <th>Email</th>
                                <th>Applicant Type</th>
                                <th>Academic Year</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($available_applicants)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center;">No available applicants to add.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($available_applicants as $uid => $a): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="available-checkbox" name="user_ids[]" value="<?php echo (int)$uid; ?>" />
                                        </td>
                                        <td class="col-name"><?php echo htmlspecialchars($a['registered_full_name'] ?? 'Unnamed'); ?></td>
                                        <td class="col-email"><?php
                                                                $email = $a['user_email'] ?? null;
                                                                $dec = $email ? decryptData($email) : null;
                                                                echo htmlspecialchars(($dec !== false && !empty($dec)) ? $dec : ($email ?? ''));
                                                                ?></td>
                                        <td><?php echo htmlspecialchars($a['applicant_type'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($a['academic_year'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($a['application_status'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Modal Footer (sticky at bottom) -->
                <div style="position: sticky; bottom: 0; background: var(--color-card); padding: 16px 0 24px 0; border-top: 1px solid var(--color-border);">
                    <div style="display:flex; gap:12px; justify-content:center; padding: 0 32px;">
                        <button type="button" id="btnCancelAddApplicants" style="flex: 1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
                        <button type="submit" style="flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4);">Add Selected</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Replace Room Modal (styled like Admission Management) -->
<div id="replaceRoomModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color: rgba(0,0,0,0.4); z-index:1001; align-items:center; justify-content:center; backdrop-filter: blur(4px);">
    <div role="dialog" aria-modal="true" aria-labelledby="replaceRoomModalTitle" style="background: var(--color-card); border-radius: 20px; max-width: 720px; width: 92%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); animation: slideUp 0.3s ease; position: relative;">
        <!-- Close Button -->
        <button type="button" id="closeReplaceRoomModal" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-weight: 700;">
            &times;
        </button>

        <!-- Modal Header -->
        <div style="padding: 40px 32px 16px 32px; text-align: center;">
            <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 16px auto; display: flex; align-items: center; justify-content: center;">
                <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7" />
                </svg>
            </div>
            <h3 id="replaceRoomModalTitle" style="margin:0 0 6px 0; color:#1a202c; font-size:1.6rem; font-weight:700; letter-spacing:-0.025em;">Replace Room</h3>
            <p style="color:#718096; margin:0; line-height:1.5; font-size:0.95rem;">Move selected applicant to another room</p>
        </div>

        <!-- Modal Body -->
        <div style="padding: 0 32px 24px 32px;">
            <form method="post" action="schedule_room_view.php?schedule_id=<?php echo urlencode($schedule['schedule_id']); ?>" id="replaceRoomForm">
                <input type="hidden" name="action" value="replace_room_to_target" />
                <input type="hidden" name="user_ids[]" id="replaceUserId" value="" />
                <div style="display:flex; gap: 12px; align-items:center; margin-bottom: 12px;">
                    <input type="text" id="replaceRoomSearch" placeholder="Search rooms..." style="flex:1; max-width: 360px; padding: 10px 12px; border: 2px solid #e2e8f0; border-radius: 12px; background:#f7fafc; color:#2d3748;" />
                    <button type="button" class="btn btn--secondary" id="btnCancelReplaceRoom">Cancel</button>
                    <button type="submit" class="btn btn--primary">Replace Room</button>
                </div>
                <div style="max-height: 320px; overflow:auto; border:1px solid #e5e7eb; border-radius:12px;">
                    <table class="table" id="replaceRoomTable">
                        <thead>
                            <tr>
                                <th>Room</th>
                                <th>Start</th>
                                <th>Capacity</th>
                                <th>Status</th>
                                <th>Select</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schedules as $sid => $sch): if ($sid == $schedule_id) continue;
                                $b = $booked_map[$sid] ?? 0;
                                $cap = (int)$sch['capacity'];
                                $full = ($b >= $cap); ?>
                                <tr class="replace-row">
                                    <td class="col-room"><?php echo htmlspecialchars($sch['floor'] . ' • ' . $sch['room']); ?></td>
                                    <td class="col-start"><?php echo htmlspecialchars(date('M j, Y, g:i A', strtotime($sch['start_date_and_time']))); ?></td>
                                    <td><?php echo $b . ' / ' . $cap; ?></td>
                                    <td><?php echo htmlspecialchars($sch['status']); ?></td>
                                    <td>
                                        <input type="radio" name="target_schedule_id" value="<?php echo (int)$sid; ?>" <?php echo $full ? 'disabled' : ''; ?> />
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Full Remarks Modal -->
<div id="viewRemarksModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color: rgba(0,0,0,0.4); z-index:1001; align-items:center; justify-content:center; backdrop-filter: blur(4px);">
    <div role="dialog" aria-modal="true" aria-labelledby="viewRemarksModalTitle" style="background: var(--color-card); border-radius: 20px; max-width: 720px; width: 92%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); animation: slideUp 0.3s ease; position: relative; max-height: 86vh; display: flex; flex-direction: column;">
        <!-- Close Button -->
        <button type="button" id="closeViewRemarksModal" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-weight: 700;">&times;</button>

        <!-- Modal Header -->
        <div style="padding: 40px 32px 16px 32px; text-align: center;">
            <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #004aad 0%, #0c326f 100%); border-radius: 18px; margin: 0 auto 16px auto; display: flex; align-items: center; justify-content: center;">
                <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
            </div>
            <h3 id="viewRemarksModalTitle" style="margin:0 0 6px 0; color:#1a202c; font-size:1.6rem; font-weight:700; letter-spacing:-0.025em;">Full Remarks</h3>
            <p style="color:#718096; margin:0; line-height:1.5; font-size:0.95rem;">Viewing full remarks for this applicant</p>
        </div>

        <!-- Modal Body -->
        <div style="padding: 0 32px 24px 32px; flex: 1 1 auto; overflow-y: auto;">
            <div id="remarksModalContent" style="white-space: pre-wrap; color:#2d3748; background:#f7fafc; border:2px solid #e2e8f0; border-radius:12px; padding:14px; line-height:1.6;"></div>
            <div style="display:flex; gap:12px; justify-content:center; margin-top:16px;">
                <button type="button" id="btnCancelViewRemarks" style="padding: 12px 18px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Send Exam Permit Modal (lightweight, reuses permit management flow) -->
<div id="sendExamPermitModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color: rgba(0,0,0,0.4); z-index:1001; align-items:center; justify-content:center; backdrop-filter: blur(4px);">
    <div role="dialog" aria-modal="true" aria-labelledby="sendExamPermitModalTitle" style="background: var(--color-card); border-radius: 20px; max-width: 720px; width: 92%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); animation: slideUp 0.3s ease; position: relative; max-height: 86vh; display:flex; flex-direction:column;">
        <!-- Close Button -->
        <button type="button" id="closeSendModalBtn" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-weight: 700;">&times;</button>

        <!-- Modal Header -->
        <div style="padding: 40px 32px 16px 32px; text-align: center;">
            <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 16px auto; display: flex; align-items: center; justify-content: center;">
                <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
            </div>
            <h3 id="sendExamPermitModalTitle" style="margin:0 0 6px 0; color:#1a202c; font-size:1.6rem; font-weight:700; letter-spacing:-0.025em;">Send Exam Permit</h3>
            <p style="color:#718096; margin:0; line-height:1.5; font-size:0.95rem;">Confirm details and send permit to the applicant</p>
        </div>

        <!-- Modal Body -->
        <div style="padding: 0 32px 24px 32px; flex: 1 1 auto; overflow-y: auto;">
            <form id="sendExamPermitForm">
                <input type="hidden" id="sendUserIdHidden" />
                <input type="hidden" id="sendApplicantNameHidden" />
                <input type="hidden" id="sendExamDateHidden" />
                <input type="hidden" id="sendExamTimeHidden" />
                <input type="hidden" id="sendApplicantNumberHidden" />

                <div class="card" style="background:#f8fafc; border:1px solid var(--color-border); border-radius:12px; padding:16px;">
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div>
                            <label style="display:block; font-weight:600; color:#4a5568;">Applicant</label>
                            <div id="sendApplicantNameText" style="margin-top:6px; color:#1a202c;">—</div>
                        </div>
                        <div id="accentColorBlock">
                            <label style="display:block; font-weight:600; color:#4a5568;">Accent Color</label>
                            <input type="color" id="sendAccentColor" value="#18a558" class="pagination__select" style="margin-top:6px; width: 48px; height: 32px; padding: 0; border-radius: 8px; border: 1px solid #e2e8f0;" />
                        </div>
                        <div id="admissionOfficerBlock">
                            <label for="sendAdmissionOfficer" style="display:block; font-weight:600; color:#4a5568;">Admission Officer</label>
                            <?php
                            $officers = [];
                            if (isset($conn) && $conn) {
                                if ($stmtOff = $conn->prepare("SELECT first_name, middle_name, last_name, suffix, title FROM sso_officers ORDER BY last_name ASC, first_name ASC")) {
                                    $stmtOff->execute();
                                    $resOff = $stmtOff->get_result();
                                    if ($resOff) {
                                        while ($rowOff = $resOff->fetch_assoc()) {
                                            $officers[] = $rowOff;
                                        }
                                    }
                                    $stmtOff->close();
                                }
                            }
                            ?>
                            <select id="sendAdmissionOfficer" class="pagination__select" style="margin-top:6px;">
                                <option value="">Select an officer</option>
                                <?php foreach ($officers as $o):
                                    $nameParts = [];
                                    $fn = trim((string)($o['first_name'] ?? ''));
                                    $mn = trim((string)($o['middle_name'] ?? ''));
                                    $ln = trim((string)($o['last_name'] ?? ''));
                                    $sf = trim((string)($o['suffix'] ?? ''));
                                    $ti = trim((string)($o['title'] ?? ''));
                                    if ($fn !== '') $nameParts[] = $fn;
                                    if ($mn !== '') $nameParts[] = $mn;
                                    if ($ln !== '') $nameParts[] = $ln;
                                    if ($sf !== '') $nameParts[] = $sf;
                                    $full = implode(' ', $nameParts);
                                    $label = $full . ($ti !== '' ? (' - ' . $ti) : '');
                                ?>
                                    <option value="<?php echo htmlspecialchars($full, ENT_QUOTES); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; color:#4a5568;">Exam Date</label>
                            <div id="sendExamDateText" style="margin-top:6px; color:#1a202c;">Not set</div>
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; color:#4a5568;">Exam Time</label>
                            <div id="sendExamTimeText" style="margin-top:6px; color:#1a202c;">Not set</div>
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; color:#4a5568;">Applicant Number</label>
                            <div id="sendApplicantNumberText" style="margin-top:6px; color:#1a202c;">Will be generated</div>
                        </div>
                        <div id="applicationPeriodBlock">
                            <label style="display:block; font-weight:600; color:#4a5568;">Application Period</label>
                            <div style="display:flex; gap:8px; align-items:center; margin-top:6px;">
                                <input type="date" id="sendApplicationStart" class="pagination__select" />
                                <span style="color:#718096;">to</span>
                                <input type="date" id="sendApplicationEnd" class="pagination__select" />
                            </div>
                        </div>
                    </div>
                    <div id="applicantNumberConfig" style="margin-top:12px; display:grid; grid-template-columns: 1fr 1fr; gap: 12px; align-items: end;">
                        <div>
                            <?php
                            // Fetch available prefixes (latest first)
                            $prefixes = [];
                            if (isset($conn) && $conn) {
                                if ($stmtPref = $conn->prepare("SELECT prefix FROM applicant_number_prefix ORDER BY date_added DESC")) {
                                    $stmtPref->execute();
                                    $resPref = $stmtPref->get_result();
                                    if ($resPref) {
                                        while ($rowPref = $resPref->fetch_assoc()) {
                                            $p = trim((string)($rowPref['prefix'] ?? ''));
                                            if ($p !== '') $prefixes[] = $p;
                                        }
                                    }
                                    $stmtPref->close();
                                }
                            }
                            ?>
                            <label for="sendApplicantPrefix" style="display:block; font-weight:600; color:#4a5568;">Prefix</label>
                            <select id="sendApplicantPrefix" class="pagination__select" style="margin-top:6px;">
                                <option value="">None</option>
                                <?php foreach ($prefixes as $p): ?>
                                    <option value="<?php echo htmlspecialchars($p, ENT_QUOTES); ?>"><?php echo htmlspecialchars($p, ENT_QUOTES); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="sendApplicantNumberLength" style="display:block; font-weight:600; color:#4a5568;">Digits</label>
                            <input type="number" id="sendApplicantNumberLength" min="4" max="12" value="8" class="pagination__select" style="margin-top:6px;" />
                            <div style="margin-top:8px; display:flex; gap:12px; align-items:center; color:#4a5568;">
                                <span style="font-weight:600;">Start Position:</span>
                                <label><input type="radio" name="applicant_number_order" value="first"> First</label>
                                <label><input type="radio" name="applicant_number_order" value="last" checked> Last</label>
                            </div>
                            <div style="margin-top:8px; color:#4a5568;">
                                <span style="display:block; font-weight:600;">Number Mode</span>
                                <div style="display:flex; gap:12px; margin-top:6px; align-items:center;">
                                    <label><input type="radio" name="applicant_number_mode" value="auto" checked> Automatic</label>
                                    <label><input type="radio" name="applicant_number_mode" value="manual"> Manual start</label>
                                </div>
                                <div id="manualStartWrapper" style="margin-top:6px; display:none;">
                                    <label for="sendApplicantStartNumber" style="display:block; font-weight:500;">Start Number</label>
                                    <input type="number" id="sendApplicantStartNumber" min="0" step="1" placeholder="e.g., 1" class="pagination__select" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display:flex; gap:12px; justify-content:center; margin-top:16px;">
                    <button type="button" id="cancelSendExamPermit" style="padding: 12px 18px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
                    <button type="submit" style="padding: 12px 18px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4);">Send Permit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Full-screen loader overlay (consistent with other pages) -->
<div id="loadingOverlay" class="loading-overlay">
    <div class="loading-spinner">
        <div class="spinner"></div>
        <div class="loading-text">Processing...</div>
    </div>
</div>
<style>
    /* Loading Overlay Styles (consistent with view_submission.php) */
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

<script>
    // Add Applicants modal controls
    document.addEventListener('DOMContentLoaded', function() {
        const btnOpen = document.getElementById('btnAddApplicants');
        const modal = document.getElementById('addApplicantsModal');
        const btnClose = document.getElementById('closeAddApplicantsModal');
        const btnCancel = document.getElementById('btnCancelAddApplicants');
        const searchInput = document.getElementById('addApplicantsSearch');

        function openModal() {
            if (!modal) return;
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10);
            setTimeout(() => searchInput && searchInput.focus(), 100);
        }

        function closeModal() {
            if (!modal) return;
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 400);
        }

        btnOpen && btnOpen.addEventListener('click', openModal);
        btnClose && btnClose.addEventListener('click', closeModal);
        btnCancel && btnCancel.addEventListener('click', function(e) {
            e.preventDefault();
            closeModal();
        });
        modal && modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal && modal.classList.contains('show')) closeModal();
        });
    });

    // Select all functionality
    document.getElementById('selectAllAvailable')?.addEventListener('change', (e) => {
        const checked = e.target.checked;
        document.querySelectorAll('#availableApplicantsTable .available-checkbox').forEach(cb => {
            cb.checked = checked;
        });
    });

    // Search filter
    document.getElementById('addApplicantsSearch')?.addEventListener('input', (e) => {
        const q = e.target.value.toLowerCase();
        document.querySelectorAll('#availableApplicantsTable tbody tr').forEach(tr => {
            const name = (tr.querySelector('.col-name')?.textContent || '').toLowerCase();
            const email = (tr.querySelector('.col-email')?.textContent || '').toLowerCase();
            tr.style.display = (name.includes(q) || email.includes(q)) ? '' : 'none';
        });
    });

    // Validate selection on submit
    document.getElementById('addApplicantsForm')?.addEventListener('submit', (e) => {
        const selected = Array.from(document.querySelectorAll('#availableApplicantsTable .available-checkbox')).filter(cb => cb.checked);
        if (selected.length === 0) {
            e.preventDefault();
            alert('Please select at least one applicant to add.');
        }
    });

    // Replace Room modal controls (per-row)
    document.addEventListener('DOMContentLoaded', function() {
        const replaceModal = document.getElementById('replaceRoomModal');
        const replaceClose = document.getElementById('closeReplaceRoomModal');
        const replaceCancel = document.getElementById('btnCancelReplaceRoom');
        const replaceSearch = document.getElementById('replaceRoomSearch');
        const replaceUserIdInput = document.getElementById('replaceUserId');

        function openReplaceModal(userId) {
            if (!replaceModal) return;
            replaceUserIdInput.value = userId || '';
            replaceModal.style.display = 'flex';
            setTimeout(() => replaceModal.classList.add('show'), 10);
            setTimeout(() => replaceSearch && replaceSearch.focus(), 100);
        }

        function closeReplaceModal() {
            if (!replaceModal) return;
            replaceModal.classList.remove('show');
            setTimeout(() => {
                replaceModal.style.display = 'none';
            }, 400);
            replaceUserIdInput.value = '';
        }

        document.querySelectorAll('.replace-room-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const uid = btn.getAttribute('data-user-id');
                openReplaceModal(uid);
            });
        });

        replaceClose && replaceClose.addEventListener('click', closeReplaceModal);
        replaceCancel && replaceCancel.addEventListener('click', function(e) {
            e.preventDefault();
            closeReplaceModal();
        });
        replaceModal && replaceModal.addEventListener('click', function(e) {
            if (e.target === replaceModal) closeReplaceModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && replaceModal && replaceModal.classList.contains('show')) closeReplaceModal();
        });

        // Search filter for rooms
        replaceSearch?.addEventListener('input', (e) => {
            const q = e.target.value.toLowerCase();
            document.querySelectorAll('#replaceRoomTable tbody tr').forEach(tr => {
                const room = (tr.querySelector('.col-room')?.textContent || '').toLowerCase();
                const start = (tr.querySelector('.col-start')?.textContent || '').toLowerCase();
                tr.style.display = (room.includes(q) || start.includes(q)) ? '' : 'none';
            });
        });
    });

    // View full remarks in modal
    document.addEventListener('DOMContentLoaded', function() {
        const remarksModal = document.getElementById('viewRemarksModal');
        const closeBtn = document.getElementById('closeViewRemarksModal');
        const cancelBtn = document.getElementById('btnCancelViewRemarks');
        const contentEl = document.getElementById('remarksModalContent');
        let remarksModalClosingUntil = 0;

        function openRemarksModal(text) {
            if (!remarksModal || !contentEl) return;
            contentEl.textContent = text || '';
            remarksModal.style.display = 'flex';
            setTimeout(() => remarksModal.classList.add('show'), 10);
        }

        function closeRemarksModal() {
            if (!remarksModal || !contentEl) return;
            remarksModal.classList.remove('show');
            setTimeout(() => {
                remarksModal.style.display = 'none';
            }, 400);
            contentEl.textContent = '';
            remarksModalClosingUntil = Date.now() + 450;
        }

        document.querySelectorAll('#roomApplicantsTable .see-more-toggle').forEach(btn => {
            btn.addEventListener('click', (e) => {
                if (Date.now() < remarksModalClosingUntil) return;
                e.preventDefault();
                e.stopPropagation();
                const td = btn.closest('td');
                if (!td) return;
                const full = td.querySelector('.remarks-full');
                const text = full ? full.textContent : (td.querySelector('.remarks-preview')?.textContent || '');
                openRemarksModal(text);
            });
        });

        closeBtn && closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeRemarksModal();
        });
        cancelBtn && cancelBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeRemarksModal();
        });
        remarksModal && remarksModal.addEventListener('click', function(e) {
            if (e.target === remarksModal) {
                e.stopPropagation();
                closeRemarksModal();
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && remarksModal && remarksModal.classList.contains('show')) closeRemarksModal();
        });
    });

    // Send Exam Permit modal logic
    document.addEventListener('DOMContentLoaded', function() {
        const sendModal = document.getElementById('sendExamPermitModal');
        const closeBtn = document.getElementById('closeSendModalBtn');
        const cancelBtn = document.getElementById('cancelSendExamPermit');
        const form = document.getElementById('sendExamPermitForm');
        const userIdHidden = document.getElementById('sendUserIdHidden');
        const nameText = document.getElementById('sendApplicantNameText');
        const nameHidden = document.getElementById('sendApplicantNameHidden');
        const examDateText = document.getElementById('sendExamDateText');
        const examDateHidden = document.getElementById('sendExamDateHidden');
        const examTimeText = document.getElementById('sendExamTimeText');
        const examTimeHidden = document.getElementById('sendExamTimeHidden');
        const numberText = document.getElementById('sendApplicantNumberText');
        const numberHidden = document.getElementById('sendApplicantNumberHidden');
        const accentColor = document.getElementById('sendAccentColor');
        let nextPermitIdCache = null;

        async function fetchNextPermitId() {
            try {
                const res = await fetch('get_next_permit_id.php?_t=' + Date.now(), {
                    cache: 'no-store'
                });
                const data = await res.json();
                const nxt = parseInt(data?.next_id ?? '1', 10);
                nextPermitIdCache = isNaN(nxt) || nxt <= 0 ? 1 : nxt;
            } catch (e) {
                nextPermitIdCache = 1;
            }
            return nextPermitIdCache;
        }

        function composeApplicantNumber() {
            const nextId = (nextPermitIdCache && nextPermitIdCache > 0) ? nextPermitIdCache : 1;
            const prefixSel = document.getElementById('sendApplicantPrefix');
            const digitsInput = document.getElementById('sendApplicantNumberLength');
            const orderRadios = Array.from(document.querySelectorAll('input[name="applicant_number_order"]'));
            const prefix = (prefixSel && prefixSel.value) ? prefixSel.value.trim() : '';
            const digits = (digitsInput && digitsInput.value) ? parseInt(digitsInput.value, 10) : 8;
            const orderChecked = orderRadios.find(r => r.checked);
            const order = orderChecked ? orderChecked.value : 'last';
            const modeRadios = Array.from(document.querySelectorAll('input[name="applicant_number_mode"]'));
            const startInput = document.getElementById('sendApplicantStartNumber');
            const modeChecked = modeRadios.find(r => r.checked);
            const mode = modeChecked ? modeChecked.value : 'auto';

            const sourceNumber = (mode === 'manual' && startInput && startInput.value !== '') ? parseInt(startInput.value, 10) : nextId;
            const safeNumber = isNaN(sourceNumber) || sourceNumber < 0 ? nextId : sourceNumber;
            const nextIdStr = String(safeNumber);
            let numberPart = '';
            if (order === 'first') {
                numberPart = nextIdStr.length >= digits ? nextIdStr.slice(0, digits) : (nextIdStr + '0'.repeat(Math.max(0, digits - nextIdStr.length)));
            } else {
                numberPart = nextIdStr.length >= digits ? nextIdStr.slice(-digits) : nextIdStr.padStart(digits, '0');
            }

            const composed = (prefix ? (prefix + '-') : '') + numberPart;
            if (numberText) numberText.textContent = composed;
            if (numberHidden) numberHidden.value = composed;
        }

        // Toggle modal inputs between editable and view-only
        function setFormEditable(enabled) {
            const disable = !enabled;
            const ids = [
                'sendAdmissionOfficer',
                'sendApplicationStart',
                'sendApplicationEnd',
                'sendAccentColor',
                'sendApplicantPrefix',
                'sendApplicantNumberLength',
                'sendApplicantStartNumber'
            ];
            ids.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.disabled = disable;
                    el.style.opacity = disable ? '0.6' : '';
                    el.style.pointerEvents = disable ? 'none' : '';
                    el.style.cursor = disable ? 'not-allowed' : '';
                }
            });
            document.querySelectorAll('input[name="applicant_number_order"]').forEach(r => {
                r.disabled = disable;
                r.style.opacity = disable ? '0.6' : '';
                r.style.pointerEvents = disable ? 'none' : '';
                r.style.cursor = disable ? 'not-allowed' : '';
            });
            document.querySelectorAll('input[name="applicant_number_mode"]').forEach(r => {
                r.disabled = disable;
                r.style.opacity = disable ? '0.6' : '';
                r.style.pointerEvents = disable ? 'none' : '';
                r.style.cursor = disable ? 'not-allowed' : '';
            });
        }

        async function prefillSchedule(uid) {
            try {
                const res = await fetch('get_exam_schedule.php?user_id=' + encodeURIComponent(uid) + '&_t=' + Date.now());
                const data = await res.json();
                if (data && data.ok && data.start_date_and_time) {
                    const parts = String(data.start_date_and_time).split(' ');
                    const datePart = parts[0];
                    const timePart = parts[1] || '';
                    const tparts = timePart.split(':');
                    const hhmm = (tparts.length >= 2) ? (tparts[0] + ':' + tparts[1]) : timePart;
                    let displayTime = hhmm;
                    if (tparts.length >= 2) {
                        const hour24 = parseInt(tparts[0], 10);
                        const minute = tparts[1];
                        if (!isNaN(hour24)) {
                            const ampm = hour24 >= 12 ? 'PM' : 'AM';
                            const hour12 = (hour24 % 12) || 12;
                            displayTime = hour12 + ':' + minute + ' ' + ampm;
                        }
                    }
                    // Format date as "Month day, Year" (e.g., November 11, 2025)
                    try {
                        const d = new Date(datePart + 'T00:00:00');
                        const formatted = d.toLocaleDateString('en-US', {
                            month: 'long',
                            day: 'numeric',
                            year: 'numeric'
                        });
                        examDateText.textContent = formatted;
                        // Keep hidden as raw YYYY-MM-DD for submission
                        examDateHidden.value = datePart;
                    } catch (_) {
                        // Fallback to raw if parsing fails
                        examDateText.textContent = datePart;
                        examDateHidden.value = datePart;
                    }
                    examTimeText.textContent = displayTime;
                    // Store 12-hour format with AM/PM for submission
                    examTimeHidden.value = displayTime;
                } else {
                    examDateText.textContent = 'Not set';
                    examTimeText.textContent = 'Not set';
                    examDateHidden.value = '';
                    examTimeHidden.value = '';
                }
            } catch (e) {
                examDateText.textContent = 'Not set';
                examTimeText.textContent = 'Not set';
                examDateHidden.value = '';
                examTimeHidden.value = '';
            }
        }

        function openSendModal(name, uid) {
            if (!sendModal) return;
            nameText.textContent = name || '—';
            nameHidden.value = name || '';
            userIdHidden.value = uid || '';
            sendModal.style.display = 'flex';
            setTimeout(() => sendModal.classList.add('show'), 10);
        }

        function closeSendModal() {
            if (!sendModal) return;
            sendModal.classList.remove('show');
            setTimeout(() => {
                sendModal.style.display = 'none';
            }, 400);
            userIdHidden.value = '';
            nameHidden.value = '';
            nameText.textContent = '—';
            examDateText.textContent = 'Not set';
            examTimeText.textContent = 'Not set';
            examDateHidden.value = '';
            examTimeHidden.value = '';
            numberText.textContent = 'Will be generated';
            numberHidden.value = '';
            form?.reset();
            const manualWrap = document.getElementById('manualStartWrapper');
            if (manualWrap) manualWrap.style.display = 'none';
            // Reset toggled blocks and button text for next open
            const submitBtn = form?.querySelector('button[type="submit"]');
            const admissionBlock = document.getElementById('admissionOfficerBlock');
            const periodBlock = document.getElementById('applicationPeriodBlock');
            const numberConfig = document.getElementById('applicantNumberConfig');
            const accentBlock = document.getElementById('accentColorBlock');
            if (submitBtn) submitBtn.textContent = 'Send Permit';
            if (admissionBlock) admissionBlock.style.display = '';
            if (periodBlock) periodBlock.style.display = '';
            if (numberConfig) numberConfig.style.display = '';
            if (accentBlock) accentBlock.style.display = '';
            if (form && form.dataset) {
                delete form.dataset.action;
            }
            // Ensure inputs are editable again for next open
            setFormEditable(true);
            // Ensure global loader hidden and controls enabled on close
            if (typeof window.hideLoader === 'function') window.hideLoader();
            const cancelButtonEl = document.getElementById('cancelSendExamPermit');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '';
                submitBtn.style.cursor = '';
            }
            if (cancelButtonEl) {
                cancelButtonEl.disabled = false;
                cancelButtonEl.style.opacity = '';
                cancelButtonEl.style.cursor = '';
            }
        }

        document.querySelectorAll('.send-permit-btn').forEach(btn => {
            btn.addEventListener('click', async function(e) {
                e.preventDefault();
                e.stopPropagation();
                const uid = parseInt(this.getAttribute('data-user-id') || '0', 10);
                const name = this.getAttribute('data-applicant-name') || '';
                const periodStart = this.getAttribute('data-period-start') || '';
                const periodEnd = this.getAttribute('data-period-end') || '';
                const action = this.getAttribute('data-action') || 'send';
                if (!uid) return;
                // Prefill application period from applicant type's cycle
                const startInput = document.getElementById('sendApplicationStart');
                const endInput = document.getElementById('sendApplicationEnd');
                if (startInput) startInput.value = periodStart;
                if (endInput) endInput.value = periodEnd;
                // Remember action for submit
                if (form) form.dataset.action = action;
                const submitBtn = form?.querySelector('button[type="submit"]');
                const admissionBlock = document.getElementById('admissionOfficerBlock');
                const periodBlock = document.getElementById('applicationPeriodBlock');
                const numberConfig = document.getElementById('applicantNumberConfig');
                const accentBlock = document.getElementById('accentColorBlock');

                async function prefillExistingPermit(userId) {
                    try {
                        const res = await fetch('get_latest_permit.php?user_id=' + encodeURIComponent(userId) + '&_t=' + Date.now());
                        const data = await res.json();
                        if (data && data.ok) {
                            if (data.applicant_name) {
                                nameText.textContent = data.applicant_name;
                                nameHidden.value = data.applicant_name;
                            }
                            if (data.exam_date) {
                                try {
                                    const d = new Date(data.exam_date + 'T00:00:00');
                                    const formatted = d.toLocaleDateString('en-US', {
                                        month: 'long',
                                        day: 'numeric',
                                        year: 'numeric'
                                    });
                                    examDateText.textContent = formatted;
                                } catch (_) {
                                    examDateText.textContent = data.exam_date;
                                }
                                // Keep hidden as raw YYYY-MM-DD from DB
                                examDateHidden.value = data.exam_date;
                            }
                            if (data.exam_time) {
                                examTimeText.textContent = data.exam_time;
                                examTimeHidden.value = data.exam_time;
                            }
                            if (data.applicant_number) {
                                numberText.textContent = data.applicant_number;
                                numberHidden.value = data.applicant_number;
                            }
                            return true;
                        }
                    } catch (err) {
                        console.error('Failed to prefill existing permit', err);
                    }
                    return false;
                }

                if (action === 'resend') {
                    if (submitBtn) submitBtn.textContent = 'Resend Permit';
                    // Show all blocks for resend; user can review full details
                    if (admissionBlock) admissionBlock.style.display = '';
                    if (periodBlock) periodBlock.style.display = '';
                    if (numberConfig) numberConfig.style.display = '';
                    if (accentBlock) accentBlock.style.display = '';
                    // Make fields view-only for resend
                    setFormEditable(false);
                    if (typeof window.showLoader === 'function') window.showLoader('Preparing…');
                    try {
                        await prefillExistingPermit(uid);
                        openSendModal(name, String(uid));
                    } finally {
                        if (typeof window.hideLoader === 'function') window.hideLoader();
                    }
                } else {
                    if (submitBtn) submitBtn.textContent = 'Send Permit';
                    if (admissionBlock) admissionBlock.style.display = '';
                    if (periodBlock) periodBlock.style.display = '';
                    if (numberConfig) numberConfig.style.display = '';
                    if (accentBlock) accentBlock.style.display = '';
                    // Enable editing for fresh send
                    setFormEditable(true);
                    await Promise.all([fetchNextPermitId(), prefillSchedule(uid)]);
                    composeApplicantNumber();
                    openSendModal(name, String(uid));
                }
            });
        });

        // Re-compose number when options change
        ['change', 'input'].forEach(ev => {
            document.getElementById('sendApplicantPrefix')?.addEventListener(ev, composeApplicantNumber);
            document.getElementById('sendApplicantNumberLength')?.addEventListener(ev, composeApplicantNumber);
            document.querySelectorAll('input[name="applicant_number_order"]').forEach(r => r.addEventListener(ev, composeApplicantNumber));
            document.querySelectorAll('input[name="applicant_number_mode"]').forEach(r => r.addEventListener(ev, function() {
                const manualWrap = document.getElementById('manualStartWrapper');
                const isManual = this.value === 'manual' && this.checked;
                if (manualWrap) manualWrap.style.display = isManual ? 'block' : 'none';
                composeApplicantNumber();
            }));
            document.getElementById('sendApplicantStartNumber')?.addEventListener(ev, composeApplicantNumber);
        });

        closeBtn && closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeSendModal();
        });
        cancelBtn && cancelBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeSendModal();
        });
        sendModal && sendModal.addEventListener('click', function(e) {
            if (e.target === sendModal) {
                e.stopPropagation();
                closeSendModal();
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sendModal && sendModal.classList.contains('show')) closeSendModal();
        });

        form && form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const userIdVal = parseInt(userIdHidden.value || '0', 10) || 0;
            const applicantNumber = numberHidden.value || '';
            const applicantName = nameHidden.value || '';
            const dateOfExam = examDateHidden.value || '';
            const examTime = examTimeHidden.value || '';
            const start = document.getElementById('sendApplicationStart').value || '';
            const end = document.getElementById('sendApplicationEnd').value || '';
            const accent = accentColor?.value || '#18a558';
            const admissionOfficer = document.getElementById('sendAdmissionOfficer')?.value || '';
            if (!userIdVal || !applicantNumber) {
                alert('Missing user or applicant number.');
                return;
            }
            const submitBtnEl = form?.querySelector('button[type="submit"]');
            const cancelButtonEl = cancelBtn;
            // Show global loader and disable controls
            if (typeof window.showLoader === 'function') {
                const isResend = (form?.dataset?.action === 'resend');
                window.showLoader(isResend ? 'Resending…' : 'Sending…');
            }
            if (submitBtnEl) {
                submitBtnEl.disabled = true;
                submitBtnEl.style.opacity = '0.7';
                submitBtnEl.style.cursor = 'not-allowed';
            }
            if (cancelButtonEl) {
                cancelButtonEl.disabled = true;
                cancelButtonEl.style.opacity = '0.7';
                cancelButtonEl.style.cursor = 'not-allowed';
            }
            try {
                const params = new URLSearchParams();
                params.append('user_id', String(userIdVal));
                params.append('applicant_number', applicantNumber);
                params.append('admission_officer', admissionOfficer);
                params.append('applicant_name', applicantName);
                params.append('date_of_exam', dateOfExam);
                params.append('exam_time', examTime);
                params.append('application_period_start', start);
                params.append('application_period_end', end);
                params.append('accent_color', accent);
                const endpoint = (form?.dataset?.action === 'resend') ? 'resend_application_permit.php' : 'create_application_permit.php';
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: params.toString(),
                });
                const data = await res.json();
                if (!data || !data.ok) {
                    throw new Error(data?.error || 'Failed to send permit');
                }
                closeSendModal();
                alert('Exam Permit sent successfully.');
            } catch (err) {
                console.error(err);
                alert('Error sending permit: ' + err.message);
            } finally {
                // Hide global loader and re-enable controls
                if (typeof window.hideLoader === 'function') window.hideLoader();
                if (submitBtnEl) {
                    submitBtnEl.disabled = false;
                    submitBtnEl.style.opacity = '';
                    submitBtnEl.style.cursor = '';
                }
                if (cancelButtonEl) {
                    cancelButtonEl.disabled = false;
                    cancelButtonEl.style.opacity = '';
                    cancelButtonEl.style.cursor = '';
                }
            }
        });

        // Global loader controls (consistent with other pages)
        window.showLoader = function(text) {
            var loader = document.getElementById('loadingOverlay');
            if (loader) {
                var lt = loader.querySelector('.loading-text');
                if (lt && text) lt.textContent = text;
                // Ensure loader sits at top of stacking context
                document.body.appendChild(loader);
                loader.style.display = 'flex';
            }
        };
        window.hideLoader = function() {
            var loader = document.getElementById('loadingOverlay');
            if (loader) loader.style.display = 'none';
        };
    });
</script>

</html>