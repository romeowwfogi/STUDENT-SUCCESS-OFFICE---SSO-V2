<?php
// Authentication middleware - protect this page
require_once 'middleware/auth.php';
include 'connection/db_connect.php';

// Handle Add Room submission (insert into ExamSchedules)
// Handle Edit Schedule submission (update ExamSchedules)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_schedule') {
    $schedule_id = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 0;
    $floor = isset($_POST['floor']) ? trim($_POST['floor']) : '';
    $room = isset($_POST['room']) ? trim($_POST['room']) : '';
    $capacity = isset($_POST['capacity']) ? intval($_POST['capacity']) : 0;
    $startsAt = isset($_POST['start_date_and_time']) ? $_POST['start_date_and_time'] : '';
    $status = isset($_POST['status']) ? $_POST['status'] : 'Open';

    if ($schedule_id > 0 && $floor !== '' && $room !== '' && $capacity > 0 && $startsAt !== '') {
        // Normalize datetime-local (YYYY-MM-DDTHH:MM) to MySQL DATETIME (YYYY-MM-DD HH:MM:SS)
        $dt = str_replace('T', ' ', $startsAt);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $dt)) {
            $dt .= ':00';
        }
        if ($stmt = $conn->prepare("UPDATE ExamSchedules SET floor = ?, room = ?, capacity = ?, start_date_and_time = ?, status = ? WHERE schedule_id = ?")) {
            $stmt->bind_param("ssissi", $floor, $room, $capacity, $dt, $status, $schedule_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Redirect back to avoid resubmission
    header('Location: schedule_management.php');
    exit;
}

// Handle Add Room submission (insert into ExamSchedules)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_room') {
    $floor = isset($_POST['floor']) ? trim($_POST['floor']) : '';
    $room = isset($_POST['room']) ? trim($_POST['room']) : '';
    $capacity = isset($_POST['capacity']) ? intval($_POST['capacity']) : 0;
    $startsAt = isset($_POST['start_date_and_time']) ? $_POST['start_date_and_time'] : ''; // datetime-local
    $status = isset($_POST['status']) ? $_POST['status'] : 'Open';

    // Basic validation
    if ($floor !== '' && $room !== '' && $capacity > 0 && $startsAt !== '') {
        $dt = str_replace('T', ' ', $startsAt);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $dt)) {
            $dt .= ':00';
        }

        // Compute next schedule_id (since schema uses INT PK without AUTO_INCREMENT)
        $nextId = 1;
        if ($resultMax = $conn->query("SELECT IFNULL(MAX(schedule_id), 0) + 1 AS next_id FROM ExamSchedules")) {
            if ($rowMax = $resultMax->fetch_assoc()) {
                $nextId = intval($rowMax['next_id']);
            }
        }

        if ($stmt = $conn->prepare("INSERT INTO ExamSchedules (schedule_id, floor, room, capacity, start_date_and_time, status) VALUES (?, ?, ?, ?, ?, ?)")) {
            $stmt->bind_param("ississ", $nextId, $floor, $room, $capacity, $dt, $status);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Redirect to avoid resubmission on refresh
    header('Location: schedule_management.php');
    exit;
}

// Auto-update schedule status based on current bookings vs capacity
// A schedule is 'Full' when booked_count >= capacity, otherwise 'Open'
if ($conn) {
    $updateStatusSql = "UPDATE ExamSchedules s\n        LEFT JOIN (\n            SELECT schedule_id, COUNT(*) AS booked_count\n            FROM ExamRegistrations\n            GROUP BY schedule_id\n        ) r ON s.schedule_id = r.schedule_id\n        SET s.status = CASE WHEN IFNULL(r.booked_count, 0) >= s.capacity THEN 'Full' ELSE 'Open' END";
    // Execute without halting on error to keep page robust
    $conn->query($updateStatusSql);
}

// Fetch schedules directly from ExamSchedules as defined in database_schedule.txt
$schedules = [];

// Use provided ExamSchedules table and alias columns to match frontend expectations
$sql = "SELECT 
            s.schedule_id AS schedule_id,
            CAST(s.schedule_id AS CHAR) AS schedule_code,
            s.start_date_and_time AS starts_at,
            NULL AS ends_at,
            s.room AS room_name,
            s.floor AS floor_label,
            s.capacity AS capacity,
            IFNULL(r.booked_count, 0) AS booked_count,
            s.status AS status
        FROM ExamSchedules s
        LEFT JOIN (
            SELECT schedule_id, COUNT(*) AS booked_count
            FROM ExamRegistrations
            GROUP BY schedule_id
        ) r ON s.schedule_id = r.schedule_id
        ORDER BY s.start_date_and_time ASC";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="admin_style.css">
    
    <link rel="stylesheet" href="dashboard.css">
    <style>
        /* Ensure the table keeps dashboard layout and not admin defaults */
        .table th,
        .table td {
            border: none;
        }

        .table td {
            border-top: 1px solid var(--color-border);
        }

        .table tr:nth-child(even) {
            background-color: transparent;
        }
    </style>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

</head>

<body>
    <!-- Mobile Navbar -->
    <?php
    include "includes/mobile_navbar.php";
    ?>

    <!-- Layout Container -->
    <div class="layout">
        <!-- Sidebar -->
        <?php
        include "includes/sidebar.php";
        ?>

        <!-- Main Content -->
        <main class="main-content">


            <!-- Header -->
            <header class="header">
                <div class="header__left">
                    <h1>Schedule Management</h1>
                    <p class="header__subtitle"></p>
                </div>
                <div class="header__actions">
                    <button class="btn btn--primary" id="btnNewRoom">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New Room
                    </button>
                </div>
            </header>

            <!-- Scheduling Section -->
            <section class="section active" id="scheduling_section">
                <div class="table-container">
                    <div class="table-container__header">
                        <h2 class="table-container__title">Exam Scheduling</h2>
                        <p class="table-container__subtitle">Manage exam schedules and room assignments</p>
                    </div>

                    <div class="filtration_container">
                        <div class="search_input_container">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search-icon lucide-search">
                                <path d="m21 21-4.34-4.34" />
                                <circle cx="11" cy="11" r="8" />
                            </svg>
                            <input type="text" placeholder="Search schedules...">
                        </div>

                        <div class="search_button_container">
                            <button class="button export">Import</button>
                        </div>

                        <div class="search_button_container">
                            <button class="button export">Export</button>
                        </div>
                    </div>

                    <table class="table" id="schedulesTable">
                        <thead>
                            <tr>
                                <th class="sortable" data-column="no">NO
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th class="sortable" data-column="room">Room
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th class="sortable" data-column="floor">Floor
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th class="sortable" data-column="capacity">Capacity (in/total)
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th class="sortable" data-column="start-date-time">Start Date and Time
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td data-cell="no">1</td>
                                <td data-cell="room">301</td>
                                <td data-cell="floor">3rd Floor</td>
                                <td data-cell="start-date-time">Oct 15, 2025 - 9:00 AM</td>
                                <td data-cell="status"><span class="badge badge--success">Open</span></td>
                                <td data-cell="Actions">
                                    <div class="table__actions">
                                        <button class="table__btn table__btn--view">View</button>
                                        <button class="table__btn table__btn--edit">Edit</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td data-cell="no">1</td>
                                <td data-cell="room">301</td>
                                <td data-cell="floor">3rd Floor</td>
                                <td data-cell="start-date-time">Oct 15, 2025 - 9:00 AM</td>
                                <td data-cell="status"><span class="badge badge--success">Open</span></td>
                                <td data-cell="Actions">
                                    <div class="table__actions">
                                        <button class="table__btn table__btn--view">View</button>
                                        <button class="table__btn table__btn--edit">Edit</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td data-cell="no">1</td>
                                <td data-cell="room">301</td>
                                <td data-cell="floor">3rd Floor</td>
                                <td data-cell="start-date-time">Oct 15, 2025 - 9:00 AM</td>
                                <td data-cell="status"><span class="badge badge--success">Open</span></td>
                                <td data-cell="Actions">
                                    <div class="table__actions">
                                        <button class="table__btn table__btn--view">View</button>
                                        <button class="table__btn table__btn--edit">Edit</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td data-cell="no">1</td>
                                <td data-cell="room">301</td>
                                <td data-cell="floor">3rd Floor</td>
                                <td data-cell="start-date-time">Oct 15, 2025 - 9:00 AM</td>
                                <td data-cell="status"><span class="badge badge--success">Open</span></td>
                                <td data-cell="Actions">
                                    <div class="table__actions">
                                        <button class="table__btn table__btn--view">View</button>
                                        <button class="table__btn table__btn--edit">Edit</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td data-cell="no">1</td>
                                <td data-cell="room">301</td>
                                <td data-cell="floor">3rd Floor</td>
                                <td data-cell="start-date-time">Oct 15, 2025 - 9:00 AM</td>
                                <td data-cell="status"><span class="badge badge--success">Open</span></td>
                                <td data-cell="Actions">
                                    <div class="table__actions">
                                        <button class="table__btn table__btn--view">View</button>
                                        <button class="table__btn table__btn--edit">Edit</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td data-cell="no">1</td>
                                <td data-cell="room">301</td>
                                <td data-cell="floor">3rd Floor</td>
                                <td data-cell="start-date-time">Oct 15, 2025 - 9:00 AM</td>
                                <td data-cell="status"><span class="badge badge--success">Open</span></td>
                                <td data-cell="Actions">
                                    <div class="table__actions">
                                        <button class="table__btn table__btn--view">View</button>
                                        <button class="table__btn table__btn--edit">Edit</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td data-cell="no">1</td>
                                <td data-cell="room">301</td>
                                <td data-cell="floor">3rd Floor</td>
                                <td data-cell="start-date-time">Oct 15, 2025 - 9:00 AM</td>
                                <td data-cell="status"><span class="badge badge--success">Open</span></td>
                                <td data-cell="Actions">
                                    <div class="table__actions">
                                        <button class="table__btn table__btn--view">View</button>
                                        <button class="table__btn table__btn--edit">Edit</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td data-cell="no">1</td>
                                <td data-cell="room">301</td>
                                <td data-cell="floor">3rd Floor</td>
                                <td data-cell="start-date-time">Oct 15, 2025 - 9:00 AM</td>
                                <td data-cell="status"><span class="badge badge--success">Open</span></td>
                                <td data-cell="Actions">
                                    <div class="table__actions">
                                        <button class="table__btn table__btn--view">View</button>
                                        <button class="table__btn table__btn--edit">Edit</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td data-cell="no">1</td>
                                <td data-cell="room">301</td>
                                <td data-cell="floor">3rd Floor</td>
                                <td data-cell="start-date-time">Oct 15, 2025 - 9:00 AM</td>
                                <td data-cell="status"><span class="badge badge--success">Open</span></td>
                                <td data-cell="Actions">
                                    <div class="table__actions">
                                        <button class="table__btn table__btn--view">View</button>
                                        <button class="table__btn table__btn--edit">Edit</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td data-cell="no">1</td>
                                <td data-cell="room">301</td>
                                <td data-cell="floor">3rd Floor</td>
                                <td data-cell="start-date-time">Oct 15, 2025 - 9:00 AM</td>
                                <td data-cell="status"><span class="badge badge--success">Open</span></td>
                                <td data-cell="Actions">
                                    <div class="table__actions">
                                        <button class="table__btn table__btn--view">View</button>
                                        <button class="table__btn table__btn--edit">Edit</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td data-cell="no">1</td>
                                <td data-cell="room">301</td>
                                <td data-cell="floor">3rd Floor</td>
                                <td data-cell="start-date-time">Oct 15, 2025 - 9:00 AM</td>
                                <td data-cell="status"><span class="badge badge--success">Open</span></td>
                                <td data-cell="Actions">
                                    <div class="table__actions">
                                        <button class="table__btn table__btn--view">View</button>
                                        <button class="table__btn table__btn--edit">Edit</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td data-cell="no">1</td>
                                <td data-cell="room">301</td>
                                <td data-cell="floor">3rd Floor</td>
                                <td data-cell="start-date-time">Oct 15, 2025 - 9:00 AM</td>
                                <td data-cell="status"><span class="badge badge--success">Open</span></td>
                                <td data-cell="Actions">
                                    <div class="table__actions">
                                        <button class="table__btn table__btn--view">View</button>
                                        <button class="table__btn table__btn--edit">Edit</button>
                                    </div>
                                </td>
                            </tr>


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
                            <span class="pagination__info">Showing 1-10 of 45 • Page 1/5</span>
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

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <div class="loading-text">Processing...</div>
        </div>
    </div>

    <!-- Add Room Modal - Reusing Manage Form design -->
    <div id="addRoomModal" class="add-step-modal" style="display: none;">
        <div class="add-step-modal-content">
            <div class="add-step-modal-header">
                <button type="button" class="close-btn" id="closeAddRoomModal">&times;</button>
            </div>
            <div class="add-step-modal-body">
                <h3>Add New Room</h3>
                <p class="add-step-modal-description">Create a new exam room schedule</p>
                <form id="addRoomForm" action="schedule_management.php" method="post">
                    <input type="hidden" name="action" value="add_room">
                    <div class="form-group">
                        <label for="floor">
<svg style="width: 18px; height: 18px; color: #18a558;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7l9-4 9 4-9 4-9-4zm0 8l9 4 9-4M3 7v8m18-8v8" />
                            </svg>
                            Floor
                        </label>
                        <input type="text" id="floor" name="floor" required placeholder="e.g., 3rd Floor">
                    </div>
                    <div class="form-group">
                        <label for="room">
<svg style="width: 18px; height: 18px; color: #18a558;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                            Room
                        </label>
                        <input type="text" id="room" name="room" required placeholder="e.g., Room 301">
                    </div>
                    <div class="form-group">
                        <label for="capacity">
<svg style="width: 18px; height: 18px; color: #18a558;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-3-3h-2M9 20H4v-2a3 3 0 013-3h2m4-12a4 4 0 110 8 4 4 0 010-8z" />
                            </svg>
                            Capacity
                        </label>
                        <input type="number" id="capacity" name="capacity" min="1" required placeholder="e.g., 30">
                    </div>
                    <div class="form-group">
                        <label for="start_date_and_time">
<svg style="width: 18px; height: 18px; color: #18a558;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3M3 11h18M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            Start Date & Time
                        </label>
                        <input type="datetime-local" id="start_date_and_time" name="start_date_and_time" required>
                    </div>
                    <div class="form-group">
                        <label for="status">
<svg style="width: 18px; height: 18px; color: #18a558;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Status
                        </label>
                        <select id="status" name="status" required>
                            <option value="Open">Open</option>
                            <option value="Full">Full</option>
                        </select>
                    </div>
                    <div class="add-step-modal-buttons">
                        <button type="button" class="cancel-btn" id="cancelAddRoom">Cancel</button>
                        <button type="submit" class="submit-btn"><span style="position: relative; z-index: 2;">Create Room</span></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Schedule Modal -->
    <div id="editScheduleModal" class="add-step-modal" style="display: none;">
        <div class="add-step-modal-content">
            <div class="add-step-modal-header">
                <button type="button" class="close-btn" id="closeEditScheduleModal">&times;</button>
                <div class="add-step-modal-icon">
                    <div class="add-step-modal-icon-container">
<svg style="width: 40px; height: 40px; color: #18a558;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5h2m-1 14V5m7 7H5" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="add-step-modal-body">
                <h3>Edit Schedule</h3>
                <p class="add-step-modal-description">Update exam room schedule details</p>
                <form id="editScheduleForm" action="schedule_management.php" method="post">
                    <input type="hidden" name="action" value="edit_schedule">
                    <input type="hidden" id="edit_schedule_id" name="schedule_id" value="">
                    <div class="form-group">
                        <label for="edit_floor">
<svg style="width: 18px; height: 18px; color: #18a558;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7l9-4 9 4-9 4-9-4zm0 8l9 4 9-4M3 7v8m18-8v8" />
                            </svg>
                            Floor
                        </label>
                        <input type="text" id="edit_floor" name="floor" required placeholder="e.g., 3rd Floor">
                    </div>
                    <div class="form-group">
                        <label for="edit_room">
<svg style="width: 18px; height: 18px; color: #18a558;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                            Room
                        </label>
                        <input type="text" id="edit_room" name="room" required placeholder="e.g., Room 301">
                    </div>
                    <div class="form-group">
                        <label for="edit_capacity">
<svg style="width: 18px; height: 18px; color: #18a558;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-3-3h-2M9 20H4v-2a3 3 0 013-3h2m4-12a4 4 0 110 8 4 4 0 010-8z" />
                            </svg>
                            Capacity
                        </label>
                        <input type="number" id="edit_capacity" name="capacity" min="1" required placeholder="e.g., 30">
                    </div>
                    <div class="form-group">
                        <label for="edit_start_date_and_time">
<svg style="width: 18px; height: 18px; color: #18a558;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3M3 11h18M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            Start Date & Time
                        </label>
                        <input type="datetime-local" id="edit_start_date_and_time" name="start_date_and_time" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">
                            <svg style="width: 18px; height: 18px; color: #4facfe;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Status
                        </label>
                        <select id="edit_status" name="status" required>
                            <option value="Open">Open</option>
                            <option value="Full">Full</option>
                        </select>
                    </div>
                    <div class="add-step-modal-buttons">
                        <button type="button" class="cancel-btn" id="cancelEditSchedule">Cancel</button>
                        <button type="submit" class="submit-btn"><span style="position: relative; z-index: 2;">Update Schedule</span></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="dahsboard.js"></script>
    <script>
        // Loader controls
        function showLoader() {
            const loader = document.getElementById('loadingOverlay');
            if (loader) {
                // Ensure loader is the last child of body to avoid stacking context issues
                document.body.appendChild(loader);
                loader.style.display = 'flex';
            }
        }

        function hideLoader() {
            const loader = document.getElementById('loadingOverlay');
            if (loader) loader.style.display = 'none';
        }

        // Add Room modal controls
        document.addEventListener('DOMContentLoaded', function() {
            const btnNewRoom = document.getElementById('btnNewRoom');
            const addRoomModal = document.getElementById('addRoomModal');
            const closeAddRoomModal = document.getElementById('closeAddRoomModal');
            const cancelAddRoom = document.getElementById('cancelAddRoom');
            const addRoomForm = document.getElementById('addRoomForm');

            function openAddRoomModal() {
                addRoomModal.style.display = 'block';
                setTimeout(() => addRoomModal.classList.add('show'), 10);
                const floorInput = document.getElementById('floor');
                setTimeout(() => floorInput && floorInput.focus(), 100);
            }

            function closeAddRoomWithAnimation() {
                addRoomModal.classList.remove('show');
                setTimeout(() => {
                    addRoomModal.style.display = 'none';
                }, 400);
            }

            if (btnNewRoom) {
                btnNewRoom.addEventListener('click', openAddRoomModal);
            }
            if (closeAddRoomModal) {
                closeAddRoomModal.addEventListener('click', function() {
                    closeAddRoomWithAnimation();
                    addRoomForm.reset();
                });
            }
            if (cancelAddRoom) {
                cancelAddRoom.addEventListener('click', function() {
                    closeAddRoomWithAnimation();
                    addRoomForm.reset();
                });
            }
            if (addRoomModal) {
                addRoomModal.addEventListener('click', function(event) {
                    if (event.target === addRoomModal) {
                        closeAddRoomWithAnimation();
                        addRoomForm.reset();
                    }
                });
            }
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && addRoomModal.classList.contains('show')) {
                    closeAddRoomWithAnimation();
                    addRoomForm.reset();
                }
            });

            if (addRoomForm) {
                addRoomForm.addEventListener('submit', function(e) {
                    // Show loader before actual submission
                    e.preventDefault();
                    showLoader();
                    // Hide modal to guarantee loader sits visually above everything
                    if (addRoomModal) {
                        addRoomModal.classList.remove('show');
                        addRoomModal.style.display = 'none';
                    }
                    // Submit the form after a brief delay to ensure loader visibility
                    setTimeout(() => this.submit(), 120);
                });
            }
        });
        // Render schedules from database into the table body
        document.addEventListener('DOMContentLoaded', function() {
            const tbody = document.querySelector('#scheduling_section tbody');
            if (!tbody) return;

            const schedules = <?php echo json_encode($schedules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

            // Clear any static rows
            tbody.innerHTML = '';

            // Helper to format datetime for display
            function formatDatetime(dt) {
                try {
                    const iso = (dt || '').replace(' ', 'T');
                    const d = new Date(iso);
                    if (isNaN(d.getTime())) return dt;
                    const opts = {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric',
                        hour: 'numeric',
                        minute: '2-digit'
                    };
                    return d.toLocaleString('en-US', opts);
                } catch (e) {
                    return dt;
                }
            }

            if (!schedules || schedules.length === 0) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td colspan="8" style="text-align:center; color:#666; padding:16px;">No schedules found</td>
                `;
                tbody.appendChild(tr);
                return;
            }

            // Simple client-side pagination
            const paginationSelect = document.querySelector('.pagination__select');
            const paginationInfo = document.querySelector('.pagination__info');
            const prevButton = document.querySelector('.pagination__bttns:first-of-type');
            const nextButton = document.querySelector('.pagination__bttns:last-of-type');

            let rowsPerPage = parseInt(paginationSelect?.value || '10', 10);
            let currentPage = 1;

            function updateButtons(totalPages) {
                const isFirst = currentPage <= 1;
                const isLast = currentPage >= totalPages;
                if (prevButton) {
                    prevButton.disabled = isFirst;
                    prevButton.classList.toggle('pagination__button--disabled', isFirst);
                }
                if (nextButton) {
                    nextButton.disabled = isLast;
                    nextButton.classList.toggle('pagination__button--disabled', isLast);
                }
            }

            function renderPage() {
                const totalRows = schedules.length;
                const totalPages = Math.max(1, Math.ceil(totalRows / rowsPerPage));
                // Clamp current page
                currentPage = Math.min(Math.max(1, currentPage), totalPages);

                const startIdx = (currentPage - 1) * rowsPerPage;
                const endIdx = Math.min(startIdx + rowsPerPage, totalRows);

                // Clear and render current page
                tbody.innerHTML = '';
                schedules.slice(startIdx, endIdx).forEach((s, localIdx) => {
                    const statusClass = (s.status === 'Full') ? 'badge--danger' : 'badge--success';
                    const startsAtText = formatDatetime(s.starts_at || s.start_date_and_time);
                    const overallIdx = startIdx + localIdx;
                    let displayNo = overallIdx + 1;
                    if (currentSort.column === 'no') {
                        displayNo = currentSort.direction === 'desc' ? (totalRows - overallIdx) : (overallIdx + 1);
                    }
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td data-cell="no">${displayNo}</td>
                        <td data-cell="room">${s.room_name || s.room || ''}</td>
                        <td data-cell="floor">${s.floor_label || s.floor || ''}</td>
                        <td data-cell="capacity">${(s.booked_count ?? 0)}/${(s.capacity ?? 0)}</td>
                        <td data-cell="start-date-time">${startsAtText}</td>
                        <td data-cell="status"><span class="badge ${statusClass}">${s.status}</span></td>
                        <td data-cell="Actions">
                            <div class="table__actions">
                                <button class="table__btn table__btn--view" data-id="${s.schedule_id}">View</button>
                                <button class="table__btn table__btn--edit" data-id="${s.schedule_id}">Edit</button>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });

                // Update info
                if (paginationInfo) {
                    paginationInfo.textContent = `Showing ${totalRows === 0 ? 0 : startIdx + 1}-${endIdx} of ${totalRows} • Page ${currentPage}/${totalPages}`;
                }

                updateButtons(totalPages);
            }

            // Header-based sorting integrated with dataset and pagination
            const scheduleTable = document.getElementById('schedulesTable');
            const sortableHeaders = scheduleTable ? scheduleTable.querySelectorAll('.sortable') : [];
            const columnTypeMap = {
                'no': 'numeric',
                'room': 'numeric',
                'floor': 'text',
                'capacity': 'numeric',
                'start-date-time': 'date'
            };
            let currentSort = {
                column: null,
                direction: 'asc'
            };

            function getScheduleValue(s, column) {
                switch (column) {
                    case 'no':
                        return s.schedule_id ?? 0;
                    case 'room':
                        return (s.room_name ?? s.room ?? '').toString();
                    case 'floor':
                        return (s.floor_label ?? s.floor ?? '').toString();
                    case 'capacity':
                        return s.booked_count ?? 0;
                    case 'start-date-time':
                        return s.starts_at ?? s.start_date_and_time ?? '';
                    default:
                        return '';
                }
            }

            function compareText(a, b) {
                return a.toLowerCase().localeCompare(b.toLowerCase());
            }

            function compareNumeric(a, b) {
                const numA = parseFloat(String(a).replace(/[^\d.-]/g, '')) || 0;
                const numB = parseFloat(String(b).replace(/[^\d.-]/g, '')) || 0;
                return numA - numB;
            }

            function compareDate(a, b) {
                const dateA = new Date(String(a).replace(' ', 'T'));
                const dateB = new Date(String(b).replace(' ', 'T'));
                return dateA - dateB;
            }

            function sortSchedulesBy(column, direction = null) {
                if (!column) return;
                const type = columnTypeMap[column] || 'text';

                // Determine sort direction
                if (direction === null) {
                    if (currentSort.column === column) {
                        direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
                    } else {
                        direction = 'asc';
                    }
                }
                currentSort = {
                    column,
                    direction
                };

                schedules.sort((a, b) => {
                    const aVal = getScheduleValue(a, column);
                    const bVal = getScheduleValue(b, column);
                    let cmp = 0;
                    switch (type) {
                        case 'numeric':
                            cmp = compareNumeric(aVal, bVal);
                            break;
                        case 'date':
                            cmp = compareDate(aVal, bVal);
                            break;
                        default:
                            cmp = compareText(String(aVal), String(bVal));
                    }
                    return direction === 'asc' ? cmp : -cmp;
                });
            }

            function updateHeaderIndicators(activeColumn, direction) {
                sortableHeaders.forEach(h => h.classList.remove('sorted-asc', 'sorted-desc'));
                const activeHeader = Array.from(sortableHeaders).find(h => h.dataset.column === activeColumn);
                if (activeHeader) {
                    activeHeader.classList.add(direction === 'asc' ? 'sorted-asc' : 'sorted-desc');
                }
            }

            // Attach click listeners to headers
            sortableHeaders.forEach(header => {
                header.addEventListener('click', () => {
                    const col = header.dataset.column;
                    sortSchedulesBy(col);
                    updateHeaderIndicators(currentSort.column, currentSort.direction);
                    currentPage = 1;
                    renderPage();
                });
            });

            // Events
            if (paginationSelect) {
                paginationSelect.addEventListener('change', function() {
                    rowsPerPage = parseInt(this.value, 10);
                    currentPage = 1;
                    renderPage();
                });
            }
            if (prevButton) {
                prevButton.addEventListener('click', function() {
                    currentPage -= 1;
                    renderPage();
                });
            }
            if (nextButton) {
                nextButton.addEventListener('click', function() {
                    currentPage += 1;
                    renderPage();
                });
            }

            // Delegate View button clicks to navigate to room view page
            if (scheduleTable) {
                scheduleTable.addEventListener('click', function(e) {
                    const btn = e.target.closest('.table__btn--view');
                    if (!btn) return;
                    const id = btn.getAttribute('data-id');
                    if (id) {
                        window.location.href = 'schedule_room_view.php?schedule_id=' + encodeURIComponent(id);
                    }
                });
            }

            // Edit modal controls and helpers
            const editModal = document.getElementById('editScheduleModal');
            const closeEditModalBtn = document.getElementById('closeEditScheduleModal');
            const cancelEditBtn = document.getElementById('cancelEditSchedule');
            const editForm = document.getElementById('editScheduleForm');
            const editFloor = document.getElementById('edit_floor');
            const editRoom = document.getElementById('edit_room');
            const editCapacity = document.getElementById('edit_capacity');
            const editStartsAt = document.getElementById('edit_start_date_and_time');
            const editStatus = document.getElementById('edit_status');
            const editIdInput = document.getElementById('edit_schedule_id');

            function openEditModal() {
                editModal.style.display = 'block';
                setTimeout(() => editModal.classList.add('show'), 10);
                setTimeout(() => editFloor && editFloor.focus(), 100);
            }

            function closeEditModalWithAnimation() {
                editModal.classList.remove('show');
                setTimeout(() => {
                    editModal.style.display = 'none';
                }, 400);
            }

            function toDatetimeLocalValue(dt) {
                try {
                    if (!dt) return '';
                    const d = new Date(String(dt).replace(' ', 'T'));
                    if (isNaN(d.getTime())) return '';
                    const pad = n => String(n).padStart(2, '0');
                    const yyyy = d.getFullYear();
                    const mm = pad(d.getMonth() + 1);
                    const dd = pad(d.getDate());
                    const hh = pad(d.getHours());
                    const mi = pad(d.getMinutes());
                    return `${yyyy}-${mm}-${dd}T${hh}:${mi}`;
                } catch (e) {
                    return '';
                }
            }

            // Delegate Edit button clicks
            if (scheduleTable) {
                scheduleTable.addEventListener('click', function(e) {
                    const btn = e.target.closest('.table__btn--edit');
                    if (!btn) return;
                    const id = parseInt(btn.getAttribute('data-id'), 10);
                    if (!id) return;
                    const s = schedules.find(x => parseInt(x.schedule_id, 10) === id);
                    if (!s) return;

                    editIdInput.value = s.schedule_id;
                    editFloor.value = s.floor_label || s.floor || '';
                    editRoom.value = s.room_name || s.room || '';
                    editCapacity.value = (s.capacity ?? 0);
                    editStartsAt.value = toDatetimeLocalValue(s.starts_at || s.start_date_and_time);
                    editStatus.value = s.status || 'Open';

                    openEditModal();
                });
            }

            if (closeEditModalBtn) {
                closeEditModalBtn.addEventListener('click', function() {
                    closeEditModalWithAnimation();
                    editForm && editForm.reset();
                });
            }
            if (cancelEditBtn) {
                cancelEditBtn.addEventListener('click', function() {
                    closeEditModalWithAnimation();
                    editForm && editForm.reset();
                });
            }
            if (editModal) {
                editModal.addEventListener('click', function(event) {
                    if (event.target === editModal) {
                        closeEditModalWithAnimation();
                        editForm && editForm.reset();
                    }
                });
            }
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && editModal.classList.contains('show')) {
                    closeEditModalWithAnimation();
                    editForm && editForm.reset();
                }
            });

            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    showLoader();
                    if (editModal) {
                        editModal.classList.remove('show');
                        editModal.style.display = 'none';
                    }
                    setTimeout(() => this.submit(), 120);
                });
            }

            // Initial render
            renderPage();
        });
    </script>
</body>

</html>