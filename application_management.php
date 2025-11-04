<?php
// Authentication middleware - protect this page
require_once 'middleware/auth.php';
include 'connection/db_connect.php';

// --- ACTION HANDLER: Process form submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- CREATE A NEW CYCLE ---
    if (isset($_POST['action']) && $_POST['action'] === 'create_cycle') {
        $cycle_name = $conn->real_escape_string($_POST['cycle_name']);

        $sql = "INSERT INTO admission_cycles (cycle_name, is_archived) VALUES ('$cycle_name', 0)";
        if ($conn->query($sql)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'New admission cycle created.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $conn->error];
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
$result = $conn->query("SELECT * FROM admission_cycles WHERE is_archived = 0 ORDER BY id DESC");
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
                        New Admission Cycle
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
                        <p class="table-container__subtitle">View cycles, manage types, or view applicants</p>
                    </div>
                    <div class="filtration_container">
                        <div class="search_input_container">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search-icon lucide-search">
                                <path d="m21 21-4.34-4.34" />
                                <circle cx="11" cy="11" r="8" />
                            </svg>
                            <input type="text" id="cycleSearchInput" placeholder="Search cycles...">
                        </div>


                        <div class="search_button_container">
                            <button class="button export" onclick="window.location.href = 'archived_cycles.php';">View Archived Cycle</button>
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
                                <th class="sortable" data-column="Cycle Name">Cycle Name
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
                                    <td colspan="3" style="text-align:center;">No active cycles found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($cycles as $cycle): ?>
                                    <tr>
                                        <td><input type="checkbox" class="table-checkbox row-checkbox" data-id="<?php echo $cycle['id']; ?>"></td>
                                        <td data-cell='ID'><?php echo $cycle["id"]; ?></td>
                                        <td data-cell='Cycle Name'><?php echo htmlspecialchars($cycle["cycle_name"]); ?></td>
                                        <td class='table_actions actions'>
                                            <div class='table-controls'>
                                                <a href="applicant_types.php?cycle_id=<?php echo $cycle['id']; ?>" class="table__btn table__btn--view">Manage Applicant Types</a>
                                                <a href="applicant_management.php?cycle_id=<?php echo $cycle['id']; ?>" class="table__btn table__btn--view">View Applicants</a>
                                                <a href="application_management.php?action=archive&id=<?php echo $cycle['id']; ?>"
                                                    class="table__btn table__btn--delete confirm-action"
                                                    data-modal-title="Confirm Archive"
                                                    data-modal-message="Are you sure you want to archive this cycle and all its applicant types?">
                                                    Archive
                                                </a>
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

    <div id="confirmationModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 1002; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div style="background: var(--color-card); border-radius: 20px; text-align: center; max-width: 400px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); color: var(--color-text);">
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
        <div style="background: var(--color-card); border-radius: 20px; max-width: 480px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); position: relative; color: var(--color-text);">
            <!-- Close Button -->
            <button type="button" id="closeNewCycleModal" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px; transition: all 0.2s ease; z-index: 10;">&times;</button>

            <!-- Modal Header -->
            <div style="padding: 40px 32px 24px 32px; text-align: center;">
        <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 24px auto; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                </div>
                <h3 style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.6rem; font-weight: 700; letter-spacing: -0.025em;">Create New Admission Cycle</h3>
                <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Set up a new admission cycle for your institution</p>
            </div>

            <!-- Modal Body -->
            <div style="padding: 0 32px 24px 32px;">
                <form id="newCycleForm">
                    <div style="margin-bottom: 24px;">
                        <label for="modal_cycle_name" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; font-size: 0.9rem;">Cycle Name</label>
                        <input type="text" id="modal_cycle_name" name="cycle_name" placeholder="e.g., 2026-2027 Admissions" required style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                        <small style="display: block; margin-top: 8px; font-size: 0.85rem; color: #718096;">Enter a descriptive name for the new admission cycle</small>
                    </div>
                </form>
            </div>

            <!-- Modal Footer -->
            <div style="padding: 20px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
                <button type="button" id="cancelNewCycleModal" style="flex: 1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
        <button type="button" id="confirmNewCycleModal" style="flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4);">Create Cycle</button>
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

                const nameInput = document.createElement('input');
                nameInput.type = 'hidden';
                nameInput.name = 'cycle_name';
                nameInput.value = window.pendingCycleName;

                form.appendChild(actionInput);
                form.appendChild(nameInput);
                document.body.appendChild(form);
                // Show loader before submitting to allow visual feedback
                try { if (typeof showLoader === 'function') showLoader(); } catch (e) {}

                // Close the new cycle modal as well
                closeNewCycleModalFunc();
                // Hide confirmation modal
                modal.style.display = 'none';

                // Defer submission to ensure loader renders
                setTimeout(function() {
                    form.submit();
                }, 120);
            } else if (currentActionUrl) {
                window.location.href = currentActionUrl;
                modal.style.display = 'none';
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
        const modalCycleNameInput = document.getElementById('modal_cycle_name');

        function openNewCycleModal() {
            newCycleModal.classList.add('show');
            modalCycleNameInput.focus();
        }

        function closeNewCycleModalFunc() {
            newCycleModal.classList.remove('show');
            newCycleForm.reset();
        }

        function submitNewCycle() {
            console.log('submitNewCycle called');
            const cycleName = modalCycleNameInput.value.trim();
            console.log('Cycle name:', cycleName);

            if (!cycleName) {
                console.log('No cycle name provided, focusing input');
                modalCycleNameInput.focus();
                return;
            }

            console.log('About to show confirmation modal');
            // Show confirmation before creating
            showConfirmationModal(
                'Confirm New Cycle Creation',
                `Are you sure you want to create the admission cycle "${cycleName}"?`,
                'create_new_cycle' // Special identifier for this action
            );

            // Store the cycle name for later use
            window.pendingCycleName = cycleName;
            console.log('Stored pending cycle name:', window.pendingCycleName);
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
        modalCycleNameInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                submitNewCycle();
            }
        });
        const searchInput = document.getElementById('cycleSearchInput');
        const tableBody = document.getElementById('cyclesTable')?.querySelector('tbody');
        if (searchInput && tableBody) {
            searchInput.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = tableBody.querySelectorAll('tr');
                rows.forEach(row => {
                    const cycleNameCell = row.cells[1];
                    if (cycleNameCell) {
                        const cycleName = cycleNameCell.textContent.toLowerCase();
                        row.style.display = cycleName.includes(searchTerm) ? '' : 'none';
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