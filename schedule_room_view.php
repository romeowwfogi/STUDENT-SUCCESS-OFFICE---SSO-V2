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
            if ($target_booked >= $target_capacity) { $skipped_full++; break; }
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
    if ($skipped_full > 0) { $msg .= ' (stopped due to target capacity).'; }
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
        d_lname.field_value AS last_name
     FROM ExamRegistrations er
     LEFT JOIN users u ON er.user_id = u.id
     LEFT JOIN submissions s ON s.user_id = er.user_id
     LEFT JOIN submission_data d_fname ON (s.id = d_fname.submission_id AND d_fname.field_name = 'first_name')
     LEFT JOIN submission_data d_lname ON (s.id = d_lname.submission_id AND d_lname.field_name = 'last_name')
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
$registered_user_ids = array_map(function($r){ return (int)($r['user_id'] ?? 0); }, $applicants);
$registered_set = array_flip($registered_user_ids);
$available_applicants = [];
$avail_sql = "SELECT 
        s.user_id,
        s.id AS submission_id,
        u.email AS user_email,
        d_fname.field_value AS first_name,
        d_lname.field_value AS last_name,
        at.name AS applicant_type,
        ac.cycle_name
    FROM submissions s
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN submission_data d_fname ON (s.id = d_fname.submission_id AND d_fname.field_name = 'first_name')
    LEFT JOIN submission_data d_lname ON (s.id = d_lname.submission_id AND d_lname.field_name = 'last_name')
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
                        <p class="table-container__subtitle">Shows all registered examinees for this schedule</p>
                    </div>
                    <table class="table" id="roomApplicantsTable">
                        <thead>
                            <tr>
                                <th>NO</th>
                                <th>Applicant</th>
                                <th>Email</th>
                                <th>Submission ID</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($applicants)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; color:#666; padding:16px;">Empty — no applicants registered for this room.</td>
                                </tr>
                            <?php else: ?>
                                <?php $idx = 1; foreach ($applicants as $row): ?>
                                    <tr>
                                        <td><?php echo $idx++; ?></td>
                                        <td><?php echo htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?: 'Unknown'; ?></td>
                                        <td><?php
                                            $email = $row['user_email'] ?? null;
                                            $dec = $email ? decryptData($email) : null;
                                            echo htmlspecialchars(($dec !== false && !empty($dec)) ? $dec : ($email ?? 'N/A'));
                                        ?></td>
                                        <td><?php echo htmlspecialchars($row['submission_id'] ?? '—'); ?></td>
                                        <td>
                                            <?php if (!empty($row['submission_id'])): ?>
                                                <a class="table__btn table__btn--view" href="view_submission.php?id=<?php echo (int)$row['submission_id']; ?>">View</a>
                                                <button type="button" class="table__btn table__btn--secondary replace-room-btn" data-user-id="<?php echo (int)$row['user_id']; ?>" style="margin-left:6px;">Replace Room</button>
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
<!-- Add Applicants Modal -->
<div id="addApplicantsModal" class="add-step-modal" style="display: none;">
    <div class="add-step-modal-content">
        <div class="add-step-modal-header">
            <button type="button" class="close-btn" id="closeAddApplicantsModal">&times;</button>
            <div class="add-step-modal-icon">
                <div class="add-step-modal-icon-container">
<svg style="width: 40px; height: 40px; color: #18a558;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11v6m3-3h-6M8 7a4 4 0 110 8 4 4 0 010-8" />
                    </svg>
                </div>
            </div>
        </div>
        <div class="add-step-modal-body">
            <h3>Add Applicants to Room</h3>
            <p class="add-step-modal-description">Select applicants and add them to this schedule</p>
            <form method="post" action="schedule_room_view.php?schedule_id=<?php echo urlencode($schedule['schedule_id']); ?>" id="addApplicantsForm">
                <input type="hidden" name="action" value="replace_room" />
                <input type="hidden" name="schedule_id" value="<?php echo (int)$schedule['schedule_id']; ?>" />
                <div class="toolbar" style="display:flex; gap: 12px; align-items:center; margin-bottom: 12px;">
                    <label style="display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" id="selectAllAvailable" />
                        Select all
                    </label>
                    <input type="text" id="addApplicantsSearch" placeholder="Search by name or email..." style="flex:1; max-width: 360px;" />
                    <button type="button" class="btn btn--secondary" id="btnCancelAddApplicants">Cancel</button>
                    <button type="submit" class="btn btn--primary">Add Selected</button>
                </div>
                <table class="table" id="availableApplicantsTable">
                    <thead>
                        <tr>
                            <th style="width:32px;">#</th>
                            <th>Applicant</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Cycle</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($available_applicants)): ?>
                        <tr><td colspan="5" style="text-align:center;">No available applicants to add.</td></tr>
                    <?php else: ?>
                        <?php foreach ($available_applicants as $uid => $a): ?>
                            <?php
                                $fname = $a['first_name'] ?? '';
                                $lname = $a['last_name'] ?? '';
                                $full = trim($fname . ' ' . $lname);
                                if ($full === '') $full = 'Unnamed';
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="available-checkbox" name="user_ids[]" value="<?php echo (int)$uid; ?>" />
                                </td>
                                <td class="col-name"><?php echo htmlspecialchars($full); ?></td>
                                <td class="col-email"><?php
                                    $email = $a['user_email'] ?? null;
                                    $dec = $email ? decryptData($email) : null;
                                    echo htmlspecialchars(($dec !== false && !empty($dec)) ? $dec : ($email ?? ''));
                                ?></td>
                                <td><?php echo htmlspecialchars($a['applicant_type'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($a['cycle_name'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>
    </div>
</div>
<!-- Replace Room Modal -->
<div id="replaceRoomModal" class="add-step-modal" style="display: none;">
    <div class="add-step-modal-content">
        <div class="add-step-modal-header">
            <button type="button" class="close-btn" id="closeReplaceRoomModal">&times;</button>
            <div class="add-step-modal-icon">
                <div class="add-step-modal-icon-container">
<svg style="width: 40px; height: 40px; color: #18a558;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7" />
                    </svg>
                </div>
            </div>
        </div>
        <div class="add-step-modal-body">
            <h3>Replace Room</h3>
            <p class="add-step-modal-description">Move selected applicant to another room</p>
            <form method="post" action="schedule_room_view.php?schedule_id=<?php echo urlencode($schedule['schedule_id']); ?>" id="replaceRoomForm">
                <input type="hidden" name="action" value="replace_room_to_target" />
                <input type="hidden" name="user_ids[]" id="replaceUserId" value="" />
                <div style="display:flex; gap: 12px; align-items:center; margin-bottom: 12px;">
                    <input type="text" id="replaceRoomSearch" placeholder="Search rooms..." style="flex:1; max-width: 360px;" />
                    <button type="button" class="btn btn--secondary" id="btnCancelReplaceRoom">Cancel</button>
                    <button type="submit" class="btn btn--primary">Replace Room</button>
                </div>
                <div style="max-height: 320px; overflow:auto; border:1px solid #e5e7eb; border-radius:8px;">
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
                            <?php foreach ($schedules as $sid => $sch): if ($sid == $schedule_id) continue; $b = $booked_map[$sid] ?? 0; $cap = (int)$sch['capacity']; $full = ($b >= $cap); ?>
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
        modal.style.display = 'block';
        setTimeout(() => modal.classList.add('show'), 10);
        setTimeout(() => searchInput && searchInput.focus(), 100);
    }
    function closeModal() {
        if (!modal) return;
        modal.classList.remove('show');
        setTimeout(() => { modal.style.display = 'none'; }, 400);
    }

    btnOpen && btnOpen.addEventListener('click', openModal);
    btnClose && btnClose.addEventListener('click', closeModal);
    btnCancel && btnCancel.addEventListener('click', function(e) { e.preventDefault(); closeModal(); });
    modal && modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && modal && modal.classList.contains('show')) closeModal(); });
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
        replaceModal.style.display = 'block';
        setTimeout(() => replaceModal.classList.add('show'), 10);
        setTimeout(() => replaceSearch && replaceSearch.focus(), 100);
    }
    function closeReplaceModal() {
        if (!replaceModal) return;
        replaceModal.classList.remove('show');
        setTimeout(() => { replaceModal.style.display = 'none'; }, 400);
        replaceUserIdInput.value = '';
    }

    document.querySelectorAll('.replace-room-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const uid = btn.getAttribute('data-user-id');
            openReplaceModal(uid);
        });
    });

    replaceClose && replaceClose.addEventListener('click', closeReplaceModal);
    replaceCancel && replaceCancel.addEventListener('click', function(e) { e.preventDefault(); closeReplaceModal(); });
    replaceModal && replaceModal.addEventListener('click', function(e) { if (e.target === replaceModal) closeReplaceModal(); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && replaceModal && replaceModal.classList.contains('show')) closeReplaceModal(); });

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
</script>
</html>