<?php
// Authentication middleware - protect this page
require_once 'middleware/auth.php';
include 'connection/db_connect.php';

// --- ACTION HANDLER: Process form submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- CREATE A NEW CYCLE ---
    if (isset($_POST['action']) && $_POST['action'] === 'create_cycle') {
        // Gather inputs
        $admission_start = isset($_POST['admission_date_time_start']) ? trim($_POST['admission_date_time_start']) : '';
        $admission_end   = isset($_POST['admission_date_time_end']) ? trim($_POST['admission_date_time_end']) : '';
        $ay_start_raw    = isset($_POST['academic_year_start']) ? trim($_POST['academic_year_start']) : '';
        $ay_end_raw      = isset($_POST['academic_year_end']) ? trim($_POST['academic_year_end']) : '';

        // Basic validation
        $errors = [];
        if ($admission_start === '' || $admission_end === '') {
            $errors[] = 'Admission start and end date/time are required.';
        }

        $start_ts = $admission_start ? strtotime($admission_start) : false;
        $end_ts   = $admission_end ? strtotime($admission_end) : false;
        if ($start_ts === false || $end_ts === false) {
            $errors[] = 'Invalid date/time format.';
        } elseif ($start_ts >= $end_ts) {
            $errors[] = 'Admission start must be earlier than end.';
        }

        // Normalize academic years: if not provided, derive from dates
        $ay_start = $ay_start_raw !== '' ? (int)$ay_start_raw : ($start_ts ? (int)date('Y', $start_ts) : 0);
        $ay_end   = $ay_end_raw !== '' ? (int)$ay_end_raw   : ($end_ts   ? (int)date('Y', $end_ts)   : 0);

        if ($ay_start <= 0 || $ay_end <= 0) {
            $errors[] = 'Academic year start and end are required (4-digit years).';
        } elseif ($ay_start > $ay_end) {
            $errors[] = 'Academic year start must be earlier than end.';
        }

        if (!empty($errors)) {
            $_SESSION['message'] = ['type' => 'error', 'text' => implode('\n', $errors)];
            header("Location: application_management.php");
            exit;
        }

        // Determine auto open/close flag
        $auto_flag = 0;
        if (isset($_POST['is_automatically_open_closed'])) {
            $auto_flag = ($_POST['is_automatically_open_closed'] === '1' || $_POST['is_automatically_open_closed'] === 'on') ? 1 : 0;
        }

        // Insert using prepared statement with new schema (including auto flag)
        $stmt = $conn->prepare(
            "INSERT INTO admission_cycles (admission_date_time_start, admission_date_time_end, academic_year_start, academic_year_end, is_automatically_open_closed, is_archived) VALUES (?, ?, ?, ?, ?, 0)"
        );
        if ($stmt) {
            $stmt->bind_param('ssiii', $admission_start, $admission_end, $ay_start, $ay_end, $auto_flag);
            if ($stmt->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'New admission cycle created.'];
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Error creating cycle: ' . $stmt->error];
            }
            $stmt->close();
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error preparing statement: ' . $conn->error];
        }

        header("Location: application_management.php");
        exit;
    }

    // --- UPDATE AN EXISTING CYCLE ---
    if (isset($_POST['action']) && $_POST['action'] === 'update_cycle') {
        $cycle_id        = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $admission_start = isset($_POST['admission_date_time_start']) ? trim($_POST['admission_date_time_start']) : '';
        $admission_end   = isset($_POST['admission_date_time_end']) ? trim($_POST['admission_date_time_end']) : '';
        $ay_start_raw    = isset($_POST['academic_year_start']) ? trim($_POST['academic_year_start']) : '';
        $ay_end_raw      = isset($_POST['academic_year_end']) ? trim($_POST['academic_year_end']) : '';
        $is_archived_raw = isset($_POST['is_archived']) ? trim($_POST['is_archived']) : '0';

        $errors = [];
        if ($cycle_id <= 0) {
            $errors[] = 'Invalid cycle id.';
        }
        if ($admission_start === '' || $admission_end === '') {
            $errors[] = 'Admission start and end date/time are required.';
        }

        $start_ts = $admission_start ? strtotime($admission_start) : false;
        $end_ts   = $admission_end ? strtotime($admission_end) : false;
        if ($start_ts === false || $end_ts === false) {
            $errors[] = 'Invalid date/time format.';
        } elseif ($start_ts >= $end_ts) {
            $errors[] = 'Admission start must be earlier than end.';
        }

        $ay_start = $ay_start_raw !== '' ? (int)$ay_start_raw : ($start_ts ? (int)date('Y', $start_ts) : 0);
        $ay_end   = $ay_end_raw !== '' ? (int)$ay_end_raw   : ($end_ts   ? (int)date('Y', $end_ts)   : 0);

        if ($ay_start <= 0 || $ay_end <= 0) {
            $errors[] = 'Academic year start and end are required (4-digit years).';
        } elseif ($ay_start > $ay_end) {
            $errors[] = 'Academic year start must be earlier than end.';
        }

        $auto_flag = 0;
        if (isset($_POST['is_automatically_open_closed'])) {
            $auto_flag = ($_POST['is_automatically_open_closed'] === '1' || $_POST['is_automatically_open_closed'] === 'on') ? 1 : 0;
        }

        if (!empty($errors)) {
            $_SESSION['message'] = ['type' => 'error', 'text' => implode('\n', $errors)];
            header("Location: application_management.php");
            exit;
        }

        $is_archived = ($is_archived_raw === '1') ? 1 : 0;

        $stmt = $conn->prepare(
            "UPDATE admission_cycles SET admission_date_time_start = ?, admission_date_time_end = ?, academic_year_start = ?, academic_year_end = ?, is_automatically_open_closed = ?, is_archived = ? WHERE id = ?"
        );
        if ($stmt) {
            $stmt->bind_param('ssiiiii', $admission_start, $admission_end, $ay_start, $ay_end, $auto_flag, $is_archived, $cycle_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Admission cycle updated.'];
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Error updating cycle: ' . $stmt->error];
            }
            $stmt->close();
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error preparing update: ' . $conn->error];
        }

        header("Location: application_management.php");
        exit;
    }
}

// --- ACTION HANDLER: Handle GET requests (Archive) ---
if (isset($_GET['action'])) {
    // --- ACTION: Archive a cycle ---
    if ($_GET['action'] === 'archive' && isset($_GET['id'])) {
        $cycle_id = (int)$_GET['id'];

        $sql = "UPDATE admission_cycles SET is_archived = 1 WHERE id = $cycle_id";
        if ($conn->query($sql)) {
            $conn->query("UPDATE applicant_types SET is_archived = 1 WHERE admission_cycle_id = $cycle_id");
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Cycle and its contents archived.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $conn->error];
        }
        header("Location: application_management.php");
        exit;
    }

    // --- ACTION: Bulk Archive cycles ---
    if ($_GET['action'] === 'bulk_archive' && isset($_GET['ids'])) {
        $cycle_ids = explode(',', $_GET['ids']);
        $cycle_ids = array_map('intval', $cycle_ids); // Sanitize IDs
        $cycle_ids = array_filter($cycle_ids, function ($id) {
            return $id > 0;
        }); // Remove invalid IDs

        if (!empty($cycle_ids)) {
            $ids_string = implode(',', $cycle_ids);
            $archived_count = 0;

            // Archive cycles
            $sql = "UPDATE admission_cycles SET is_archived = 1 WHERE id IN ($ids_string)";
            if ($conn->query($sql)) {
                $archived_count = $conn->affected_rows;

                // Archive associated applicant types
                $conn->query("UPDATE applicant_types SET is_archived = 1 WHERE admission_cycle_id IN ($ids_string)");

                $cycle_text = $archived_count === 1 ? 'cycle' : 'cycles';
                $_SESSION['message'] = ['type' => 'success', 'text' => "Successfully archived $archived_count $cycle_text and their contents."];
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Error during bulk archive: ' . $conn->error];
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'No valid cycles selected for archiving.'];
        }

        header("Location: application_management.php");
        exit;
    }
}

// --- DATA FETCHING: Get all NON-ARCHIVED cycles for display ---
$cycles = [];
$result = $conn->query("SELECT * FROM admission_cycles ORDER BY id DESC");
while ($row = $result->fetch_assoc()) {
    $cycles[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Success Office - Admission Management</title>
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
            background: #136515;
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

        /* Admission Schedule column width */
        #cyclesTable th[data-column="Admission Schedule"],
        #cyclesTable td[data-cell="Admission Schedule"] {
            width: 200px;
            max-width: 200px;
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
            background: #f7fafc !important;
            border-color: #cbd5e0 !important;
            transform: translateY(-1px);
        }

        #modalConfirmBtn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(24, 165, 88, 0.5) !important;
        }

        #modalCancelBtn:active,
        #modalConfirmBtn:active {
            transform: translateY(0);
        }

        /* New Cycle Modal Button Hover Effects */
        #closeNewCycleModal:hover {
            background: rgba(0, 0, 0, 0.1) !important;
            transform: scale(1.05);
        }

        #cancelNewCycleModal:hover {
            background: #f7fafc !important;
            border-color: #cbd5e0 !important;
            transform: translateY(-1px);
        }

        #confirmNewCycleModal:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(24, 165, 88, 0.5) !important;
        }

        #cancelNewCycleModal:active,
        #confirmNewCycleModal:active {
            transform: translateY(0);
        }

        /* Input Focus States */
        #modal_cycle_name:focus {
            outline: none !important;
            border-color: #18a558 !important;
            box-shadow: 0 0 0 3px rgba(24, 165, 88, 0.15) !important;
            background: var(--color-card) !important;
            color: var(--color-text) !important;
        }

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
            border-color: #18a558;
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
            background: #f7fafc;
            border-color: #cbd5e0;
            transform: translateY(-1px);
        }

        .pagination__bttns:active:not(.pagination__button--disabled) {
            transform: translateY(0);
        }

        .pagination__button--disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: var(--color-hover) !important;
            border-color: var(--color-border) !important;
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

        /* Loading Overlay Styles */
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

        /* Ensure anchor-based action buttons look identical to button elements */
        .table__btn,
        .table__btn:link,
        .table__btn:visited,
        .table__btn:hover {
            text-decoration: none !important;
            font-weight: 150;
        }

        /* Uniform Update-style button variant for all actions */
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
                <div class="header__left">
                    <h1 class="header__title">Admission Management</h1>
                </div>
                <div class="header__actions">
                    <button id="newCycleBtn" class="btn btn--primary">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New Admission
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
                        <h2 class="table-container__title">Manage Admission</h2>
                        <p class="table-container__subtitle">View admission, manage applicant types, or view applicants</p>
                    </div>
                    <div class="filtration_container">
                        <div class="search_input_container">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search-icon lucide-search">
                                <path d="m21 21-4.34-4.34" />
                                <circle cx="11" cy="11" r="8" />
                            </svg>
                            <input type="text" id="cycleSearchInput" placeholder="Search cycles...">
                        </div>
                    </div>
                    <table class="table" id="cyclesTable">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllPermits" class="table-checkbox"></th>
                                <th class="sortable" data-column="ID">ID
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th class="sortable" data-column="Academic Year">ACADEMIC YEAR
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th class="sortable" data-column="Admission Schedule">ADMISSION SCHEDULE
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th class="sortable" data-column="Status">STATUS
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cycles)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center;">No admission found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($cycles as $cycle): ?>
                                    <tr>
                                        <td><input type="checkbox" class="table-checkbox row-checkbox" data-id="<?php echo $cycle['id']; ?>"></td>
                                        <td data-cell='ID'><?php echo $cycle["id"]; ?></td>
                                        <td data-cell='Academic Year'><?php
                                                                        $ayStart = isset($cycle['academic_year_start']) ? $cycle['academic_year_start'] : null;
                                                                        $ayEnd = isset($cycle['academic_year_end']) ? $cycle['academic_year_end'] : null;
                                                                        $label = ($ayStart && $ayEnd) ? (htmlspecialchars($ayStart) . ' - ' . htmlspecialchars($ayEnd)) : 'N/A';
                                                                        echo $label;
                                                                        ?></td>
                                        <td data-cell='Admission Schedule'><?php
                                                                            $startRaw = $cycle['admission_date_time_start'] ?? null;
                                                                            $endRaw = $cycle['admission_date_time_end'] ?? null;
                                                                            if ($startRaw && $endRaw) {
                                                                                $tsStart = strtotime($startRaw);
                                                                                $tsEnd = strtotime($endRaw);
                                                                                $startDisp = $tsStart ? date('M j, Y, g:i A', $tsStart) : htmlspecialchars($startRaw);
                                                                                $endDisp = $tsEnd ? date('M j, Y, g:i A', $tsEnd) : htmlspecialchars($endRaw);
                                                                                echo $startDisp . ' <br><center>to</center> ' . $endDisp; // en dash separator
                                                                            } elseif ($startRaw || $endRaw) {
                                                                                $one = $startRaw ? $startRaw : $endRaw;
                                                                                $tsOne = strtotime($one);
                                                                                echo $tsOne ? date('M j, Y, g:i A', $tsOne) : htmlspecialchars($one);
                                                                            } else {
                                                                                echo 'N/A';
                                                                            }
                                                                            ?></td>
                                        <td data-cell='Status'><?php
                                                                $isArchived = isset($cycle['is_archived']) ? (int)$cycle['is_archived'] : 0;
                                                                echo $isArchived === 1 ? 'Closed' : 'Open';
                                                                ?></td>
                                        <td class='table_actions actions'>
                                            <div class='table-controls'>
                                                <button type="button"
                                                    class="table__btn table__btn--update update-cycle-btn"
                                                    data-id="<?php echo (int)$cycle['id']; ?>"
                                                    data-start="<?php echo htmlspecialchars($cycle['admission_date_time_start'] ?? '', ENT_QUOTES); ?>"
                                                    data-end="<?php echo htmlspecialchars($cycle['admission_date_time_end'] ?? '', ENT_QUOTES); ?>"
                                                    data-ay-start="<?php echo htmlspecialchars($cycle['academic_year_start'] ?? '', ENT_QUOTES); ?>"
                                                    data-ay-end="<?php echo htmlspecialchars($cycle['academic_year_end'] ?? '', ENT_QUOTES); ?>"
                                                    data-auto="<?php echo htmlspecialchars($cycle['is_automatically_open_closed'] ?? '0', ENT_QUOTES); ?>"
                                                    data-archived="<?php echo isset($cycle['is_archived']) ? (int)$cycle['is_archived'] : 0; ?>">
                                                    Update
                                                </button>
                                                <a href="manage_applicant_types.php?cycle_id=<?php echo $cycle['id']; ?>" class="table__btn table__btn--update">Manage Applicant Types</a>
                                                <a href="applicant_management.php?cycle_id=<?php echo $cycle['id']; ?>" class="table__btn table__btn--update">View Applicants</a>
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

    <div id="confirmationModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 1002; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
        <div style="background: var(--color-card); border-radius: 20px; text-align: center; max-width: 400px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); color: var(--color-text);">
            <!-- Modal Header -->
            <div style="padding: 32px 32px 16px 32px;">
                <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 16px; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center;">
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

    <!-- New Admission Cycle Modal -->
    <div id="newCycleModal" class="modal-overlay">
        <div style="background: var(--color-card); border-radius: 20px; max-width: 480px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); position: relative; color: var(--color-text);">
            <!-- Close Button -->
            <button type="button" id="closeNewCycleModal" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px; transition: all 0.2s ease; z-index: 10;">&times;</button>

            <!-- Modal Header -->
            <div style="padding: 40px 32px 24px 32px; text-align: center;">
                <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 24px auto; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                </div>
                <h3 style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.6rem; font-weight: 700; letter-spacing: -0.025em;">Create New Admission</h3>
                <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Set up a new admission for your institution</p>
            </div>

            <!-- Modal Body -->
            <div style="padding: 0 32px 24px 32px;">
                <form id="newCycleForm">
                    <div style="margin-bottom: 16px;">
                        <label for="modal_admission_start" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; font-size: 0.9rem;">Admission Start (date & time)</label>
                        <input type="datetime-local" id="modal_admission_start" name="admission_date_time_start" required style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                    </div>
                    <div style="margin-bottom: 16px;">
                        <label for="modal_admission_end" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; font-size: 0.9rem;">Admission End (date & time)</label>
                        <input type="datetime-local" id="modal_admission_end" name="admission_date_time_end" required style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 8px;">
                        <div>
                            <label for="modal_ay_start" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; font-size: 0.9rem;">Academic Year Start</label>
                            <input type="number" id="modal_ay_start" name="academic_year_start" min="1900" max="2100" placeholder="e.g., 2025" style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                        </div>
                        <div>
                            <label for="modal_ay_end" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; font-size: 0.9rem;">Academic Year End</label>
                            <input type="number" id="modal_ay_end" name="academic_year_end" min="1900" max="2100" placeholder="e.g., 2026" style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                        </div>
                    </div>
                    <small style="display: block; margin-top: 8px; font-size: 0.85rem; color: #718096;">Academic year is optional; if blank it will derive from selected dates.</small>
                    <div style="margin-top: 16px; display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" id="modal_auto_toggle" name="is_automatically_open_closed" style="width: 18px; height: 18px; accent-color: #18a558;">
                        <label for="modal_auto_toggle" style="font-size: 0.95rem; color: #2d3748;">Automatically open and close based on the setup start and end date and time</label>
                    </div>
                </form>
            </div>

            <!-- Modal Footer -->
            <div style="padding: 20px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
                <button type="button" id="cancelNewCycleModal" style="flex: 1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
                <button type="button" id="confirmNewCycleModal" style="flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4);">Create</button>
            </div>
        </div>
    </div>

    <!-- Update Admission Cycle Modal -->
    <div id="updateCycleModal" class="modal-overlay">
        <div style="background: var(--color-card); border-radius: 20px; max-width: 480px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); position: relative; color: var(--color-text);">
            <!-- Close Button -->
            <button type="button" id="closeUpdateCycleModal" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px; transition: all 0.2s ease; z-index: 10;">&times;</button>

            <!-- Modal Header -->
            <div style="padding: 40px 32px 24px 32px; text-align: center;">
                <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 24px auto; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5h2v14h-2zM5 11h14v2H5z"></path>
                    </svg>
                </div>
                <h3 style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.6rem; font-weight: 700; letter-spacing: -0.025em;">Update Admission</h3>
                <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Modify the selected admission cycle</p>
                <div style="margin-top: 12px;">
                    <span id="updateCycleStatusPill" class="status-pill status-pill--open">Open</span>
                </div>
            </div>

            <!-- Modal Body -->
            <div style="padding: 0 32px 24px 32px;">
                <form id="updateCycleForm">
                    <div style="margin-bottom: 16px;">
                        <label for="update_modal_admission_start" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; font-size: 0.9rem;">Admission Start (date & time)</label>
                        <input type="datetime-local" id="update_modal_admission_start" name="admission_date_time_start" required style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                    </div>
                    <div style="margin-bottom: 16px;">
                        <label for="update_modal_admission_end" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; font-size: 0.9rem;">Admission End (date & time)</label>
                        <input type="datetime-local" id="update_modal_admission_end" name="admission_date_time_end" required style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 8px;">
                        <div>
                            <label for="update_modal_ay_start" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; font-size: 0.9rem;">Academic Year Start</label>
                            <input type="number" id="update_modal_ay_start" name="academic_year_start" min="1900" max="2100" placeholder="e.g., 2025" style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                        </div>
                        <div>
                            <label for="update_modal_ay_end" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; font-size: 0.9rem;">Academic Year End</label>
                            <input type="number" id="update_modal_ay_end" name="academic_year_end" min="1900" max="2100" placeholder="e.g., 2026" style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                        </div>
                    </div>
                    <small style="display: block; margin-top: 8px; font-size: 0.85rem; color: #718096;">Academic year may be derived from dates if left blank.</small>
                    <div style="margin-top: 16px; display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" id="update_modal_auto_toggle" name="is_automatically_open_closed" style="width: 18px; height: 18px; accent-color: #18a558;">
                        <label for="update_modal_auto_toggle" style="font-size: 0.95rem; color: #2d3748;">Automatically open and close based on the setup start and end date and time</label>
                    </div>
                    <div style="margin-top: 16px;">
                        <label style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Status</label>
                        <div style="display:flex; gap:14px; align-items:center;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="radio" name="update_is_archived" id="update_status_open" value="0" style="width: 18px; height: 18px; accent-color: #18a558;">
                                <span>Open</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="radio" name="update_is_archived" id="update_status_closed" value="1" style="width: 18px; height: 18px; accent-color: #dc3545;">
                                <span>Closed</span>
                            </label>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Modal Footer -->
            <div style="padding: 20px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
                <button type="button" id="cancelUpdateCycleModal" style="flex: 1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
                <button type="button" id="confirmUpdateCycleModal" style="flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4);">Update</button>
            </div>
        </div>
    </div>

    <!-- Floating Archive Icon -->
    <button class="floating-archive-icon" id="floatingArchiveBtn" title="Archive Selected Cycles">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8l4 4 4-4m0 0V4a2 2 0 012-2h2a2 2 0 012 2v4m-6 0l4 4 4-4" />
        </svg>
        <span class="selection-counter" id="selectionCounter">0</span>
    </button>

    <script>
        // Helper: Format a datetime-local string (YYYY-MM-DDTHH:MM) to 'Mon D, YYYY, h:mm AM/PM'
        function formatDateTimeLocal(dtStr) {
            try {
                if (!dtStr || typeof dtStr !== 'string' || !dtStr.includes('T')) return dtStr || '';
                const [datePart, timePart] = dtStr.split('T');
                const [y, m, d] = datePart.split('-').map(n => parseInt(n, 10));
                const [hhRaw, mmRaw] = timePart.split(':');
                const hh = parseInt(hhRaw, 10);
                const mm = parseInt(mmRaw, 10);
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                const period = hh >= 12 ? 'PM' : 'AM';
                const hour12 = ((hh + 11) % 12) + 1;
                const mmPadded = String(mm).padStart(2, '0');
                return `${months[m - 1]} ${d}, ${y}, ${hour12}:${mmPadded} ${period}`;
            } catch (e) {
                return dtStr;
            }
        }
        const modal = document.getElementById('confirmationModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');
        const modalConfirmBtn = document.getElementById('modalConfirmBtn');
        const modalCancelBtn = document.getElementById('modalCancelBtn');
        let currentActionUrl = '';

        function showConfirmationModal(title, message, actionUrl) {
            console.log('showConfirmationModal called:', title, message, actionUrl);
            console.log('Modal element:', modal);

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

            console.log('Modal should now be visible');
        }

        // Safely render HTML in the confirmation modal when rich formatting is desired
        function escapeHtml(str) {
            const s = String(str ?? '');
            return s
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function showConfirmationModalHtml(title, htmlMessage, actionUrl) {
            if (!modal) return;
            modalTitle.textContent = title;
            modalMessage.innerHTML = htmlMessage;
            currentActionUrl = actionUrl;
            modal.style.display = 'flex';
            modal.style.visibility = 'visible';
            modal.style.opacity = '1';
        }
        modalConfirmBtn.addEventListener('click', () => {
            if (currentActionUrl === 'create_new_cycle') {
                // Handle new cycle creation
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'application_management.php';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'create_cycle';
                form.appendChild(actionInput);

                const pending = window.pendingNewCycle || {};

                const sInput = document.createElement('input');
                sInput.type = 'hidden';
                sInput.name = 'admission_date_time_start';
                sInput.value = pending.admission_date_time_start || '';
                form.appendChild(sInput);

                const eInput = document.createElement('input');
                eInput.type = 'hidden';
                eInput.name = 'admission_date_time_end';
                eInput.value = pending.admission_date_time_end || '';
                form.appendChild(eInput);

                const aySInput = document.createElement('input');
                aySInput.type = 'hidden';
                aySInput.name = 'academic_year_start';
                aySInput.value = pending.academic_year_start || '';
                form.appendChild(aySInput);

                const ayEInput = document.createElement('input');
                ayEInput.type = 'hidden';
                ayEInput.name = 'academic_year_end';
                ayEInput.value = pending.academic_year_end || '';
                form.appendChild(ayEInput);

                const autoInput = document.createElement('input');
                autoInput.type = 'hidden';
                autoInput.name = 'is_automatically_open_closed';
                autoInput.value = pending.is_automatically_open_closed || '0';
                form.appendChild(autoInput);

                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'is_archived';
                statusInput.value = pending.is_archived || '0';
                form.appendChild(statusInput);
                document.body.appendChild(form);
                // Show loader before submitting to allow visual feedback
                try {
                    if (typeof showLoader === 'function') showLoader();
                } catch (e) {}

                // Close the new cycle modal as well
                closeNewCycleModalFunc();
                // Hide confirmation modal
                modal.style.display = 'none';

                // Defer submission to ensure loader renders
                setTimeout(function() {
                    form.submit();
                }, 120);
            } else if (currentActionUrl === 'update_cycle') {
                // Handle cycle update
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'application_management.php';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'update_cycle';
                form.appendChild(actionInput);

                const pending = window.pendingUpdateCycle || {};
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = String(window.pendingUpdateCycleId || '');
                form.appendChild(idInput);

                const sInput = document.createElement('input');
                sInput.type = 'hidden';
                sInput.name = 'admission_date_time_start';
                sInput.value = pending.admission_date_time_start || '';
                form.appendChild(sInput);

                const eInput = document.createElement('input');
                eInput.type = 'hidden';
                eInput.name = 'admission_date_time_end';
                eInput.value = pending.admission_date_time_end || '';
                form.appendChild(eInput);

                const aySInput = document.createElement('input');
                aySInput.type = 'hidden';
                aySInput.name = 'academic_year_start';
                aySInput.value = pending.academic_year_start || '';
                form.appendChild(aySInput);

                const ayEInput = document.createElement('input');
                ayEInput.type = 'hidden';
                ayEInput.name = 'academic_year_end';
                ayEInput.value = pending.academic_year_end || '';
                form.appendChild(ayEInput);

                const autoInput = document.createElement('input');
                autoInput.type = 'hidden';
                autoInput.name = 'is_automatically_open_closed';
                autoInput.value = pending.is_automatically_open_closed || '0';
                form.appendChild(autoInput);

                // Ensure status (open/closed) is submitted
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'is_archived';
                statusInput.value = pending.is_archived || '0';
                form.appendChild(statusInput);

                document.body.appendChild(form);
                try {
                    if (typeof showLoader === 'function') showLoader();
                } catch (e) {}
                closeUpdateCycleModalFunc();
                modal.style.display = 'none';
                setTimeout(function() {
                    form.submit();
                }, 120);
            } else if (currentActionUrl) {
                // Show loader for visual feedback before navigating to archive/bulk actions
                try {
                    if (typeof showLoader === 'function') showLoader();
                } catch (e) {}
                modal.style.display = 'none';
                // Slight delay to ensure loader renders before navigation
                setTimeout(function() {
                    window.location.href = currentActionUrl;
                }, 100);
            }
        });
        modalCancelBtn.addEventListener('click', () => {
            modal.style.display = 'none';
        });
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });

        function setupConfirmationLinks() {
            document.querySelectorAll('.confirm-action').forEach(link => {
                link.addEventListener('click', function(event) {
                    event.preventDefault();
                    const title = this.dataset.modalTitle || 'Confirm Action';
                    const message = this.dataset.modalMessage || 'Are you sure?';
                    const url = this.href;
                    showConfirmationModal(title, message, url);
                });
            });
        }
        // New Cycle Modal Functionality
        const newCycleBtn = document.getElementById('newCycleBtn');
        const newCycleModal = document.getElementById('newCycleModal');
        const closeNewCycleModal = document.getElementById('closeNewCycleModal');
        const cancelNewCycleModal = document.getElementById('cancelNewCycleModal');
        const confirmNewCycleModal = document.getElementById('confirmNewCycleModal');
        const newCycleForm = document.getElementById('newCycleForm');
        const modalAdmissionStartInput = document.getElementById('modal_admission_start');
        const modalAdmissionEndInput = document.getElementById('modal_admission_end');
        const modalAyStartInput = document.getElementById('modal_ay_start');
        const modalAyEndInput = document.getElementById('modal_ay_end');
        const modalAutoToggleInput = document.getElementById('modal_auto_toggle');

        function openNewCycleModal() {
            newCycleModal.classList.add('show');
            modalAdmissionStartInput.focus();
        }

        function closeNewCycleModalFunc() {
            newCycleModal.classList.remove('show');
            newCycleForm.reset();
        }

        function submitNewCycle() {
            console.log('submitNewCycle called');
            const admissionStart = modalAdmissionStartInput.value.trim();
            const admissionEnd = modalAdmissionEndInput.value.trim();
            const ayStart = modalAyStartInput.value.trim();
            const ayEnd = modalAyEndInput.value.trim();
            const autoToggle = modalAutoToggleInput && modalAutoToggleInput.checked ? '1' : '0';

            if (!admissionStart || !admissionEnd) {
                alert('Please provide both admission start and end date/time.');
                (!admissionStart ? modalAdmissionStartInput : modalAdmissionEndInput).focus();
                return;
            }

            try {
                const s = new Date(admissionStart);
                const e = new Date(admissionEnd);
                if (isNaN(s.getTime()) || isNaN(e.getTime())) {
                    alert('Invalid date/time format.');
                    modalAdmissionStartInput.focus();
                    return;
                }
                if (s.getTime() >= e.getTime()) {
                    alert('Admission start must be earlier than end.');
                    modalAdmissionStartInput.focus();
                    return;
                }
            } catch (err) {}

            // Show confirmation before creating
            const formattedStart = formatDateTimeLocal(admissionStart);
            const formattedEnd = formatDateTimeLocal(admissionEnd);
            const ayText = (ayStart && ayEnd) ? `${ayStart}–${ayEnd}` : 'Derived from dates';
            const confirmBodyHtml = `
                <div style="margin-top:6px; text-align:center;">
                    <div><strong>Start Admission:</strong> ${escapeHtml(formattedStart)}</div>
                    <div><strong>End Admission:</strong> ${escapeHtml(formattedEnd)}</div>
                    <div><strong>Academic Year:</strong> ${escapeHtml(ayText)}</div>
                    <div><strong>Auto Open-Closed?</strong> ${autoToggle === '1' ? 'Yes' : 'No'}</div>
                </div>
            `;
            showConfirmationModalHtml(
                'Confirm New Admission',
                confirmBodyHtml,
                'create_new_cycle' // Special identifier for this action
            );

            // Store the pending data for later use
            window.pendingNewCycle = {
                admission_date_time_start: admissionStart,
                admission_date_time_end: admissionEnd,
                academic_year_start: ayStart,
                academic_year_end: ayEnd,
                is_automatically_open_closed: autoToggle
            };
        }

        if (newCycleBtn) {
            newCycleBtn.addEventListener('click', openNewCycleModal);
        }

        if (closeNewCycleModal) {
            closeNewCycleModal.addEventListener('click', closeNewCycleModalFunc);
        }

        if (cancelNewCycleModal) {
            cancelNewCycleModal.addEventListener('click', closeNewCycleModalFunc);
        }

        if (confirmNewCycleModal) {
            confirmNewCycleModal.addEventListener('click', submitNewCycle);
        }

        // Close modal when clicking outside
        newCycleModal.addEventListener('click', function(event) {
            if (event.target === newCycleModal) {
                closeNewCycleModalFunc();
            }
        });

        // Handle Enter key in the form
        [modalAdmissionStartInput, modalAdmissionEndInput, modalAyStartInput, modalAyEndInput, modalAutoToggleInput].forEach(el => {
            el && el.addEventListener('keypress', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    submitNewCycle();
                }
            });
        });

        // --- Update Cycle Modal Functionality ---
        const updateCycleModal = document.getElementById('updateCycleModal');
        const closeUpdateCycleModal = document.getElementById('closeUpdateCycleModal');
        const cancelUpdateCycleModal = document.getElementById('cancelUpdateCycleModal');
        const confirmUpdateCycleModal = document.getElementById('confirmUpdateCycleModal');
        const updateCycleForm = document.getElementById('updateCycleForm');
        const updateAdmissionStartInput = document.getElementById('update_modal_admission_start');
        const updateAdmissionEndInput = document.getElementById('update_modal_admission_end');
        const updateAyStartInput = document.getElementById('update_modal_ay_start');
        const updateAyEndInput = document.getElementById('update_modal_ay_end');
        const updateAutoToggleInput = document.getElementById('update_modal_auto_toggle');
        const updateStatusPill = document.getElementById('updateCycleStatusPill');
        const updateStatusOpenRadio = document.getElementById('update_status_open');
        const updateStatusClosedRadio = document.getElementById('update_status_closed');
        let currentUpdateCycleId = null;

        function toDateTimeLocalValue(str) {
            // Accept formats like 'YYYY-MM-DD HH:MM:SS' or ISO; output 'YYYY-MM-DDTHH:MM'
            if (!str) return '';
            try {
                // Replace space with T if present
                let s = String(str).trim().replace(' ', 'T');
                // If seconds exist, remove
                if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/.test(s)) {
                    s = s.substring(0, 16);
                }
                // Ensure length 16
                if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(s)) return s;
                const d = new Date(str);
                if (!isNaN(d.getTime())) {
                    const y = d.getFullYear();
                    const m = String(d.getMonth() + 1).padStart(2, '0');
                    const day = String(d.getDate()).padStart(2, '0');
                    const hh = String(d.getHours()).padStart(2, '0');
                    const mm = String(d.getMinutes()).padStart(2, '0');
                    return `${y}-${m}-${day}T${hh}:${mm}`;
                }
                return '';
            } catch (e) {
                return '';
            }
        }

        function openUpdateCycleModalWithData(data) {
            currentUpdateCycleId = data.id;
            updateAdmissionStartInput.value = toDateTimeLocalValue(data.start);
            updateAdmissionEndInput.value = toDateTimeLocalValue(data.end);
            updateAyStartInput.value = data.ayStart || '';
            updateAyEndInput.value = data.ayEnd || '';
            updateAutoToggleInput.checked = String(data.auto) === '1';
            // Set status pill
            if (updateStatusPill) {
                const isClosed = String(data.archived) === '1';
                updateStatusPill.textContent = isClosed ? 'Closed' : 'Open';
                updateStatusPill.className = 'status-pill ' + (isClosed ? 'status-pill--closed' : 'status-pill--open');
            }
            if (updateStatusOpenRadio && updateStatusClosedRadio) {
                const isClosed = String(data.archived) === '1';
                updateStatusOpenRadio.checked = !isClosed;
                updateStatusClosedRadio.checked = isClosed;
            }
            updateCycleModal.classList.add('show');
            updateAdmissionStartInput.focus();
        }

        function closeUpdateCycleModalFunc() {
            updateCycleModal.classList.remove('show');
            updateCycleForm.reset();
            currentUpdateCycleId = null;
        }

        function submitUpdateCycle() {
            const admissionStart = updateAdmissionStartInput.value.trim();
            const admissionEnd = updateAdmissionEndInput.value.trim();
            const ayStart = updateAyStartInput.value.trim();
            const ayEnd = updateAyEndInput.value.trim();
            const autoToggle = updateAutoToggleInput && updateAutoToggleInput.checked ? '1' : '0';

            if (!admissionStart || !admissionEnd) {
                alert('Please provide both admission start and end date/time.');
                (!admissionStart ? updateAdmissionStartInput : updateAdmissionEndInput).focus();
                return;
            }

            try {
                const s = new Date(admissionStart);
                const e = new Date(admissionEnd);
                if (isNaN(s.getTime()) || isNaN(e.getTime())) {
                    alert('Invalid date/time format.');
                    updateAdmissionStartInput.focus();
                    return;
                }
                if (s.getTime() >= e.getTime()) {
                    alert('Admission start must be earlier than end.');
                    updateAdmissionStartInput.focus();
                    return;
                }
            } catch (err) {}

            const formattedStart = formatDateTimeLocal(admissionStart);
            const formattedEnd = formatDateTimeLocal(admissionEnd);
            const ayText = (ayStart && ayEnd) ? `${ayStart}–${ayEnd}` : 'Derived from dates';
            const isArchivedVal = (updateStatusClosedRadio && updateStatusClosedRadio.checked) ? '1' : '0';
            const statusText = isArchivedVal === '1' ? 'Closed' : 'Open';
            const confirmBodyHtml = `
                <div style="margin-top:6px; text-align:center;">
                    <div><strong>Start Admission:</strong> ${escapeHtml(formattedStart)}</div>
                    <div><strong>End Admission:</strong> ${escapeHtml(formattedEnd)}</div>
                    <div><strong>Academic Year:</strong> ${escapeHtml(ayText)}</div>
                    <div><strong>Auto Open-Closed?</strong> ${autoToggle === '1' ? 'Yes' : 'No'}</div>
                    <div><strong>Status:</strong> ${escapeHtml(statusText)}</div>
                </div>
            `;
            showConfirmationModalHtml('Confirm Update Admission', confirmBodyHtml, 'update_cycle');

            window.pendingUpdateCycle = {
                admission_date_time_start: admissionStart,
                admission_date_time_end: admissionEnd,
                academic_year_start: ayStart,
                academic_year_end: ayEnd,
                is_automatically_open_closed: autoToggle,
                is_archived: isArchivedVal
            };
            window.pendingUpdateCycleId = currentUpdateCycleId;
        }

        // Wire up Update buttons
        function setupUpdateButtons() {
            document.querySelectorAll('.update-cycle-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const data = {
                        id: this.dataset.id,
                        start: this.dataset.start,
                        end: this.dataset.end,
                        ayStart: this.dataset.ayStart,
                        ayEnd: this.dataset.ayEnd,
                        auto: this.dataset.auto,
                        archived: this.dataset.archived
                    };
                    openUpdateCycleModalWithData(data);
                });
            });
        }

        setupUpdateButtons();

        // Sync status pill when radios change
        [updateStatusOpenRadio, updateStatusClosedRadio].forEach(r => {
            if (r) {
                r.addEventListener('change', function() {
                    if (!updateStatusPill) return;
                    const isClosed = updateStatusClosedRadio && updateStatusClosedRadio.checked;
                    updateStatusPill.textContent = isClosed ? 'Closed' : 'Open';
                    updateStatusPill.className = 'status-pill ' + (isClosed ? 'status-pill--closed' : 'status-pill--open');
                });
            }
        });

        if (closeUpdateCycleModal) closeUpdateCycleModal.addEventListener('click', closeUpdateCycleModalFunc);
        if (cancelUpdateCycleModal) cancelUpdateCycleModal.addEventListener('click', closeUpdateCycleModalFunc);
        if (confirmUpdateCycleModal) confirmUpdateCycleModal.addEventListener('click', submitUpdateCycle);
        updateCycleModal.addEventListener('click', function(event) {
            if (event.target === updateCycleModal) {
                closeUpdateCycleModalFunc();
            }
        });
        const searchInput = document.getElementById('cycleSearchInput');
        const tableBody = document.getElementById('cyclesTable')?.querySelector('tbody');
        if (searchInput && tableBody) {
            searchInput.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = tableBody.querySelectorAll('tr');
                rows.forEach(row => {
                    const academicYearCell = row.cells[2];
                    if (academicYearCell) {
                        const ayText = academicYearCell.textContent.toLowerCase();
                        row.style.display = ayText.includes(searchTerm) ? '' : 'none';
                    }
                });
            });
        }
        document.addEventListener('DOMContentLoaded', setupConfirmationLinks);

        // Floating Archive Icon Functionality
        const floatingArchiveBtn = document.getElementById('floatingArchiveBtn');
        const selectionCounter = document.getElementById('selectionCounter');
        const selectAllCheckbox = document.getElementById('selectAllPermits');
        const rowCheckboxes = document.querySelectorAll('.row-checkbox');
        let selectedCycles = [];

        function updateFloatingIcon() {
            const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
            selectedCycles = Array.from(checkedBoxes).map(cb => cb.dataset.id);

            if (selectedCycles.length > 0) {
                floatingArchiveBtn.classList.add('show');
                selectionCounter.textContent = selectedCycles.length;
            } else {
                floatingArchiveBtn.classList.remove('show');
            }
        }

        // Handle individual checkbox changes
        rowCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateFloatingIcon();

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
                updateFloatingIcon();
            });
        }

        // Handle floating archive button click
        floatingArchiveBtn.addEventListener('click', function() {
            if (selectedCycles.length === 0) return;

            const cycleText = selectedCycles.length === 1 ? 'cycle' : 'cycles';
            const message = `Are you sure you want to archive ${selectedCycles.length} ${cycleText}? This action will also archive all associated applicant types.`;

            showConfirmationModal(
                'Confirm Bulk Archive',
                message,
                `application_management.php?action=bulk_archive&ids=${selectedCycles.join(',')}`
            );
        });

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
                const cell = row.querySelector(`[data-column="${column}"], td:nth-child(${this.getColumnIndex(column)})`);
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
                this.table = document.getElementById('cyclesTable');
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
                    return !row.textContent.includes('No active cycles found');
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
                const originalSearchFunction = searchInput?.oninput || searchInput?.onkeyup;
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
                    return !text.includes('No active cycles found') && !text.includes('No data found');
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
                    return !row.textContent.includes('No active cycles found');
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
            tableSorter = new TableSorter('#cyclesTable');

            // Initialize table pagination
            tablePagination = new TablePagination();

            // Listen for sorting events to update pagination
            const cyclesTable = document.getElementById('cyclesTable');
            if (cyclesTable) {
                cyclesTable.addEventListener('tableSorted', function() {
                    tablePagination.updateFilteredRows();
                    tablePagination.currentPage = 1;
                    tablePagination.updateDisplay();
                });
            }
        });
    </script>
</body>

</html>