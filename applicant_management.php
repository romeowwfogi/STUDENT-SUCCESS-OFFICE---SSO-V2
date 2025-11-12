<?php
// Authentication middleware - protect this page
require_once 'middleware/auth.php';
include 'connection/db_connect.php';
require_once 'function/decrypt.php';

// Require cycle_id to show applicants for a specific admission cycle
$cycle_id = isset($_GET['cycle_id']) ? (int)$_GET['cycle_id'] : 0;
$type_id = isset($_GET['type_id']) ? (int)$_GET['type_id'] : 0; // optional applicant type filter
if ($cycle_id <= 0) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'No admission specified.'];
}

// Derive cycle display name (Academic Year or Date Range)
$cycle_name = null;
if ($cycle_id > 0) {
    if ($stmt_cycle = $conn->prepare('SELECT * FROM admission_cycles WHERE id = ?')) {
        $stmt_cycle->bind_param('i', $cycle_id);
        $stmt_cycle->execute();
        $res_cycle = $stmt_cycle->get_result();
        $row_cycle = $res_cycle ? $res_cycle->fetch_assoc() : null;
        $stmt_cycle->close();
        if ($row_cycle) {
            $ayStart = $row_cycle['academic_year_start'] ?? null;
            $ayEnd = $row_cycle['academic_year_end'] ?? null;
            if ($ayStart && $ayEnd) {
                $cycle_name = "Academic Year {$ayStart}-{$ayEnd}";
            } else {
                $startDt = $row_cycle['admission_date_time_start'] ?? null;
                $endDt = $row_cycle['admission_date_time_end'] ?? null;
                if ($startDt && $endDt) {
                    $cycle_name = date('M d, Y H:i', strtotime($startDt)) . ' – ' . date('M d, Y H:i', strtotime($endDt));
                }
            }
        }
    }
}

// Fetch applicants for the given cycle
$applicants = [];
$applicantTypes = [];
$dynamicFields = [];
if ($cycle_id > 0) {
    // Fetch applicant types for the selected cycle (for dropdown filter)
    if ($stmt_types = $conn->prepare('SELECT id, name FROM applicant_types WHERE admission_cycle_id = ? ORDER BY name ASC')) {
        $stmt_types->bind_param('i', $cycle_id);
        $stmt_types->execute();
        $res_types = $stmt_types->get_result();
        while ($row = $res_types->fetch_assoc()) {
            $applicantTypes[] = $row;
        }
        $stmt_types->close();
    }

    $sql = "SELECT
                s.id AS submission_id,
                s.submitted_at,
                s.status,
                st.hex_color AS status_color_hex,
                at.name AS applicant_type,
                u.email AS user_email,
                TRIM(CONCAT_WS(' ', uf.first_name, NULLIF(uf.middle_name,''), uf.last_name, NULLIF(uf.suffix,''))) AS registered_full_name,
                d_fname.field_value AS first_name,
                d_lname.field_value AS last_name
            FROM
                submissions s
            LEFT JOIN
                applicant_types at ON s.applicant_type_id = at.id
            LEFT JOIN
                admission_cycles ac ON at.admission_cycle_id = ac.id
            LEFT JOIN
                users u ON s.user_id = u.id
            LEFT JOIN
                user_fullname uf ON uf.user_id = u.id
            LEFT JOIN
                submission_data d_fname ON (s.id = d_fname.submission_id AND d_fname.field_name = 'first_name')
            LEFT JOIN
                submission_data d_lname ON (s.id = d_lname.submission_id AND d_lname.field_name = 'last_name')
            LEFT JOIN
                statuses st ON st.name = s.status
            WHERE
                (ac.is_archived = 0 OR ac.is_archived IS NULL) AND ac.id = ?";

    // If a specific applicant type is selected, filter by it
    if ($type_id > 0) {
        $sql .= " AND at.id = ?";
    }

    $sql .= " ORDER BY s.submitted_at DESC";

    if ($stmt = $conn->prepare($sql)) {
        if ($type_id > 0) {
            $stmt->bind_param('ii', $cycle_id, $type_id);
        } else {
            $stmt->bind_param('i', $cycle_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $applicants[] = $row;
        }
        $stmt->close();
    }

    // Fetch dynamic form fields for the selected applicant type within this cycle
    // Only load fields when a specific applicant type is selected
    $dynamicFields = [];
    if ($type_id > 0) {
        $sql_df = "SELECT ff.name AS field_name, ff.label AS field_label
                   FROM form_fields ff
                   LEFT JOIN form_steps fs ON ff.step_id = fs.id
                   LEFT JOIN applicant_types at ON fs.applicant_type_id = at.id
                   WHERE (ff.is_archived = 0 OR ff.is_archived IS NULL)
                     AND (fs.is_archived = 0 OR fs.is_archived IS NULL)
                     AND at.admission_cycle_id = ?
                     AND fs.applicant_type_id = ?
                   ORDER BY fs.step_order ASC, ff.field_order ASC";

        if ($stmt_df = $conn->prepare($sql_df)) {
            $stmt_df->bind_param('ii', $cycle_id, $type_id);
            $stmt_df->execute();
            $res_df = $stmt_df->get_result();
            while ($row = $res_df->fetch_assoc()) {
                $dynamicFields[] = $row;
            }
            $stmt_df->close();
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Success Office - Applicants</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        /* Loading Overlay Styles (consistent with other dashboard pages) */
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

        /* Align action buttons to Admission Management look */
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

        /* Status pill component (consistent across dashboard) */
        .status-pill {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            color: #fff;
            font-size: 0.8rem;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <!-- Full-screen loader overlay (shared design) -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <div class="loading-text">Processing...</div>
        </div>
    </div>
    <?php include "includes/mobile_navbar.php"; ?>

    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="header">
                <div class="header__left">
                    <h1 class="header__title">
                        Applicants <?php echo $cycle_name ? "— " . htmlspecialchars($cycle_name) : ''; ?>
                    </h1>
                </div>
                <div class="header__actions">
                    <button onclick="window.location.href='application_management.php'" class="btn btn--secondary">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Back to Admission Management
                    </button>
                </div>
            </header>

            <section class="section active" id="applicants_section" style="margin: 0 20px;">
                <div class="table-container">
                    <div class="table-container__header">
                        <h2 class="table-container__title">Applicants</h2>
                        <p class="table-container__subtitle">List of applicants for the selected admission</p>
                    </div>

                    <div class="filtration_container">
                        <div class="search_input_container">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search-icon lucide-search">
                                <path d="m21 21-4.34-4.34" />
                                <circle cx="11" cy="11" r="8" />
                            </svg>
                            <input type="text" id="applicantSearchInput" placeholder="Search applicants...">
                        </div>

                        <?php if ($cycle_id > 0): ?>
                            <form method="get" id="typeFilterForm" style="display:flex; gap:10px; align-items:center; margin-left:auto;">
                                <input type="hidden" name="cycle_id" value="<?php echo (int)$cycle_id; ?>">
                                <select name="type_id" id="applicantTypeFilter" class="pagination__select">
                                    <option value="0">All Types</option>
                                    <?php foreach ($applicantTypes as $t): ?>
                                        <option value="<?php echo (int)$t['id']; ?>" <?php echo ($type_id === (int)$t['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($t['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        <?php endif; ?>

                        <div class="search_button_container">
                            <button class="button export" id="openExportModalBtn" type="button">Export</button>
                        </div>
                    </div>

                    <table class="table" id="applicantsTable">
                        <thead>
                            <tr>
                                <th style="width:36px; text-align:center;"><input type="checkbox" id="selectAllRows" aria-label="Select all"></th>
                                <th class="sortable" data-column="ID">ID
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th class="sortable" data-column="Registered Name">REGISTERED NAME
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th class="sortable" data-column="Application Type">APPLICATION TYPE
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th class="sortable" data-column="Submitted On">SUBMITTED ON
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
                            <?php if ($cycle_id <= 0): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center;">No cycle selected. Go back to Admission Management and click "View Applicants".</td>
                                </tr>
                            <?php elseif (empty($applicants)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center;">No submissions found for this admission.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($applicants as $app): ?>
                                    <tr>
                                        <td data-cell='Select' style="text-align:center;"><input type="checkbox" class="row-select" name="selected_submissions[]" value="<?php echo (int)$app['submission_id']; ?>" aria-label="Select submission"></td>
                                        <td data-cell='ID'><?php echo (int)$app['submission_id']; ?></td>
                                        <td data-cell='Registered Name'>
                                            <?php
                                            $reg = trim((string)($app['registered_full_name'] ?? ''));
                                            $fallback = trim(((string)($app['first_name'] ?? '')) . ' ' . ((string)($app['last_name'] ?? '')));
                                            echo htmlspecialchars($reg !== '' ? $reg : $fallback);
                                            ?>
                                        </td>
                                        <td data-cell='Application Type'><?php echo htmlspecialchars($app['applicant_type'] ?? 'N/A'); ?></td>
                                        <td data-cell='Submitted On'><?php echo $app['submitted_at'] ? date('M j, Y, g:i A', strtotime($app['submitted_at'])) : 'N/A'; ?></td>
                                        <td data-cell='Status'>
                                            <?php $statusName = htmlspecialchars($app['status'] ?? '');
                                            $colorHex = htmlspecialchars($app['status_color_hex'] ?? '#6C757D'); ?>
                                            <span class="status-pill" style="background-color: <?php echo $colorHex; ?>;"><?php echo $statusName; ?></span>
                                        </td>
                                        <td class='table_actions actions'>
                                            <div class="table__actions">
                                                <button type="button" class="table__btn table__btn--update" title="View"
                                                    data-submission-id="<?php echo (int)$app['submission_id']; ?>"
                                                    onclick="openViewModal(this)">View</button>
                                                <button type="button" class="table__btn table__btn--update" title="Update"
                                                    data-submission-id="<?php echo (int)$app['submission_id']; ?>"
                                                    data-current-status="<?php echo htmlspecialchars($app['status'] ?? ''); ?>"
                                                    onclick="openUpdateModal(this)">Update</button>
                                                <button type="button" class="table__btn table__btn--update" title="Assign Room"
                                                    onclick="openAssignRoomModal(<?php echo (int)$app['submission_id']; ?>)">Assign Room</button>
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
                            <select class="pagination__select" id="rowsPerPageSelect">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                                <option value="all">All</option>
                            </select>
                        </div>
                        <div class="pagination__center">
                            <span class="pagination__info" id="paginationInfo">Showing 1-10 • Page 1</span>
                        </div>
                        <div class="pagination__right">
                            <button class="pagination__bttns pagination__button--disabled" id="paginationPrevBtn" disabled>Prev</button>
                            <button class="pagination__bttns" id="paginationNextBtn">Next</button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Update Submission Modal (aligned with common overlay modal design) -->
            <div id="updateSubmissionModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3002; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
                <div role="dialog" aria-modal="true" aria-labelledby="updateModalTitle" style="background: var(--color-card); border-radius: 20px; max-width: 720px; width: 95%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); position: relative;">
                    <!-- Close Button -->
                    <button type="button" id="closeUpdateModalBtn" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px;">&times;</button>

                    <!-- Modal Header -->
                    <div style="padding: 40px 32px 16px 32px; text-align: center;">
                        <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 24px auto; display: flex; align-items: center; justify-content: center;">
                            <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4h2v16h-2M4 11h16v2H4" />
                            </svg>
                        </div>
                        <h3 id="updateModalTitle" style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.6rem; font-weight: 700; letter-spacing: -0.025em;">Update Submission</h3>
                        <p id="updateModalSubtitle" style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Adjust status, remarks, and applicant privileges.</p>
                    </div>

                    <!-- Modal Body (scrollable) -->
                    <div style="padding: 0 32px 24px 32px; flex: 1; overflow-y: auto; min-height: 0; display: flex; flex-direction: column; gap: 12px;">
                        <input type="hidden" id="updateSubmissionId" value="0">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; align-items: start;">
                            <label style="display: flex; flex-direction: column; gap: 6px;">
                                <span style="font-weight: 600; color: #2d3748;">Status</span>
                                <select id="updateStatusSelect" class="pagination__select"></select>
                            </label>
                            <label style="display: flex; flex-direction: column; gap: 6px;">
                                <span style="font-weight: 600; color: #2d3748;">Remarks</span>
                                <textarea id="updateRemarksInput" rows="3" style="border: 1px solid var(--color-border); border-radius: 10px; padding: 8px;"></textarea>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" id="updateCanEditCheckbox">
                                <span>Allow applicant to update submission</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" id="updateCanResubmitCheckbox">
                                <span>Allow applicant to submit another application</span>
                            </label>
                        </div>

                        <!-- Modal Footer -->
                        <div style="padding: 8px 0 0 0; display: flex; gap: 12px; justify-content: center;">
                            <button type="button" id="cancelUpdateModalBtn" style="flex: 1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
                            <button type="button" id="confirmUpdateModalBtn" style="flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Save</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- View Submission Modal (aligned with common overlay modal design) -->
            <div id="viewSubmissionModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3002; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
                <div role="dialog" aria-modal="true" aria-labelledby="viewModalTitle" style="background: var(--color-card); border-radius: 20px; max-width: 960px; width: 95%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); position: relative;">
                    <!-- Close Button -->
                    <button type="button" id="closeViewModalBtn" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px;">&times;</button>

                    <!-- Modal Header -->
                    <div style="padding: 40px 32px 16px 32px; text-align: center;">
                        <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 24px auto; display: flex; align-items: center; justify-content: center;">
                            <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <h3 id="viewModalTitle" style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.6rem; font-weight: 700; letter-spacing: -0.025em;">Submission Details</h3>
                        <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Review account info, application status, answers, and uploaded files.</p>
                    </div>

                    <!-- Modal Body (scrollable) -->
                    <div id="viewSubmissionContent" style="padding: 0 32px 24px 32px; flex: 1; overflow-y: auto; min-height: 0;">

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div><strong>Registered Fullname:</strong> <span id="modalRegName">-</span></div>
                            <div><strong>Registered Email:</strong> <span id="modalEmail">-</span></div>
                            <div><strong>Admission Cycle:</strong> <span id="modalCycle">-</span></div>
                            <div><strong>Submitted On:</strong> <span id="modalSubmitted">-</span></div>
                            <div><strong>Status:</strong> <span id="modalStatus" class="badge" style="background-color:#6C757D; color:#fff; padding:4px 8px; border-radius:999px;">-</span></div>
                            <div><strong>Remarks:</strong> <span id="modalRemarks">-</span></div>
                        </div>

                        <div style="margin-top:16px;">
                            <h4 style="margin:8px 0;">Data Submissions</h4>
                            <ul id="modalAnswers" style="list-style: none; padding: 0; margin:0;"></ul>
                        </div>
                        <div style="margin-top:16px;">
                            <h4 style="margin:8px 0;">File Submissions</h4>
                            <ul id="modalFiles" style="list-style: none; padding: 0; margin:0;"></ul>
                        </div>
                        <div style="margin-top:16px; border-top:1px solid var(--color-border); padding-top:12px;">
                            <h4 style="margin:8px 0;">Update Submission</h4>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px; align-items:start;">
                                <label style="display:flex; flex-direction:column; gap:6px;">
                                    <span>Status</span>
                                    <select id="viewUpdateStatusSelect" style="border:1px solid var(--color-border); border-radius:10px; padding:8px;"></select>
                                </label>
                                <label style="display:flex; flex-direction:column; gap:6px;">
                                    <span>Remarks</span>
                                    <textarea id="viewUpdateRemarksInput" rows="3" style="border:1px solid var(--color-border); border-radius:10px; padding:8px;"></textarea>
                                </label>
                                <label style="display:flex; align-items:center; gap:8px;">
                                    <input type="checkbox" id="viewAllowUpdateCheckbox">
                                    <span>Allow applicant to update submission</span>
                                </label>
                                <label style="display:flex; align-items:center; gap:8px;">
                                    <input type="checkbox" id="viewAllowResubmitCheckbox">
                                    <span>Allow applicant to submit another application</span>
                                </label>
                            </div>
                            <div style="padding: 8px 0 0 0; display: flex; gap: 12px; justify-content: center;">
                                <button type="button" id="viewUpdateCancelBtn" style="flex: 1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
                                <button type="button" id="viewUpdateSaveBtn" style="flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Save</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Export Modal (styled like Manage Form modals and scrollable) -->
            <div id="exportModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3002; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
                <div role="dialog" aria-modal="true" aria-labelledby="exportModalTitle" style="background: var(--color-card); border-radius: 20px; max-width: 720px; width: 95%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); position: relative;">
                    <!-- Close Button -->
                    <button type="button" id="closeExportModal" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px;">&times;</button>

                    <!-- Modal Header -->
                    <div style="padding: 40px 32px 16px 32px; text-align: center;">
                        <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 24px auto; display: flex; align-items: center; justify-content: center;">
                            <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 10l5 5 5-5M12 15V3"></path>
                            </svg>
                        </div>
                        <h3 id="exportModalTitle" style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.6rem; font-weight: 700; letter-spacing: -0.025em;">Export Applicants</h3>
                        <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Choose fields to include. Registered email will be decrypted automatically.</p>
                    </div>

                    <!-- Modal Body (scrollable) -->
                    <div style="padding: 0 32px 24px 32px; flex: 1; overflow-y: auto; min-height: 0;">
                        <form id="exportForm" method="post" action="export_applicants.php" style="display: flex; flex-direction: column; gap: 12px;">
                            <input type="hidden" name="file_type" value="excel">
                            <input type="hidden" name="cycle_id" value="<?php echo isset($cycle_id) ? (int)$cycle_id : 0; ?>">
                            <input type="hidden" name="type_id" value="<?php echo isset($type_id) ? (int)$type_id : 0; ?>">

                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 10px;">
                                <label class="column-option" style="padding: 8px; border: 1px solid #e5e7eb; border-radius: 8px; display: flex; gap: 8px; align-items: center;">
                                    <input type="checkbox" name="columns[]" value="id" checked>
                                    <span>ID</span>
                                </label>
                                <label class="column-option" style="padding: 8px; border: 1px solid #e5e7eb; border-radius: 8px; display: flex; gap: 8px; align-items: center;">
                                    <input type="checkbox" name="columns[]" value="registered_fullname" checked>
                                    <span>Registered Full Name (user_fullname)</span>
                                </label>
                                <label class="column-option" style="padding: 8px; border: 1px solid #e5e7eb; border-radius: 8px; display: flex; gap: 8px; align-items: center;">
                                    <input type="checkbox" name="columns[]" value="email" checked>
                                    <span>Registered Email (decrypted)</span>
                                </label>
                                <label class="column-option" style="padding: 8px; border: 1px solid #e5e7eb; border-radius: 8px; display: flex; gap: 8px; align-items: center;">
                                    <input type="checkbox" name="columns[]" value="type" checked>
                                    <span>Application Type</span>
                                </label>
                                <label class="column-option" style="padding: 8px; border: 1px solid #e5e7eb; border-radius: 8px; display: flex; gap: 8px; align-items: center;">
                                    <input type="checkbox" name="columns[]" value="status" checked>
                                    <span>Status</span>
                                </label>
                                <label class="column-option" style="padding: 8px; border: 1px solid #e5e7eb; border-radius: 8px; display: flex; gap: 8px; align-items: center;">
                                    <input type="checkbox" name="columns[]" value="submitted_date" checked>
                                    <span>Submitted Date</span>
                                </label>
                                <label class="column-option" style="padding: 8px; border: 1px solid #e5e7eb; border-radius: 8px; display: flex; gap: 8px; align-items: center;">
                                    <input type="checkbox" name="columns[]" value="cycle">
                                    <span>Admission Cycle</span>
                                </label>
                            </div>

                            <?php if ($cycle_id <= 0): ?>
                                <p style="margin: 6px 0 0; color: #6b7280;">Select an admission cycle to load cycle-specific form fields.</p>
                            <?php elseif ($type_id <= 0): ?>
                                <p style="margin: 6px 0 0; color: #6b7280;">Select an applicant type to load its form fields.</p>
                            <?php else: ?>
                                <div style="margin-top: 12px;">
                                    <h4 style="margin: 0 0 8px; font-weight: 600;">Form Fields for selected Applicant Type</h4>
                                    <?php if (!empty($dynamicFields)): ?>
                                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 10px;">
                                            <?php foreach ($dynamicFields as $df): ?>
                                                <label class="column-option" style="padding: 8px; border: 1px solid #e5e7eb; border-radius: 8px; display: flex; gap: 8px; align-items: center;">
                                                    <input type="checkbox" name="columns[]" value="<?php echo htmlspecialchars($df['field_name']); ?>">
                                                    <span><?php echo htmlspecialchars($df['field_label'] ?: $df['field_name']); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p style="margin: 0; color: #6b7280;">No form fields found for the selected applicant type.</p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Modal Footer -->
                            <div style="padding: 16px 0 0 0; display: flex; gap: 12px; justify-content: center;">
                                <button type="button" id="cancelExportModal" style="flex: 1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
                                <button type="submit" id="confirmExportModal" style="flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Export to Sheets</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <script>
                (function() {
                    const openBtn = document.getElementById('openExportModalBtn');
                    const modal = document.getElementById('exportModal');
                    const closeBtn = document.getElementById('closeExportModal');
                    const cancelBtn = document.getElementById('cancelExportModal');
                    const form = document.getElementById('exportForm');

                    function openModal() {
                        if (modal) modal.style.display = 'flex';
                    }

                    function closeModal() {
                        if (modal) modal.style.display = 'none';
                    }

                    if (openBtn) openBtn.addEventListener('click', openModal);
                    if (closeBtn) closeBtn.addEventListener('click', closeModal);
                    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

                    // Close when clicking outside the dialog (overlay)
                    modal?.addEventListener('click', (e) => {
                        if (e.target === modal) closeModal();
                    });

                    if (form) {
                        form.addEventListener('submit', function() {
                            // Let normal form submission proceed to export_applicants.php
                            closeModal();
                        });
                    }
                })();
            </script>
        </main>
    </div>

    <script>
        // Simple client-side search by text content
        const searchInput = document.getElementById('applicantSearchInput');
        const table = document.getElementById('applicantsTable');
        const tbody = table ? table.querySelector('tbody') : null;
        if (searchInput && tbody) {
            searchInput.addEventListener('input', () => {
                const q = searchInput.value.toLowerCase();
                // Update filtered rows and re-render pagination
                filteredRows = allRows.filter(row => row.textContent.toLowerCase().includes(q));
                currentPage = 1;
                renderPage();
            });
        }

        // Auto-submit Applicant Type filter on change
        const typeSelect = document.getElementById('applicantTypeFilter');
        const filterForm = document.getElementById('typeFilterForm');
        if (typeSelect && filterForm) {
            typeSelect.addEventListener('change', () => {
                showLoader();
                filterForm.submit();
            });
        }

        // Client-side pagination with "All" option
        const rowsSelect = document.getElementById('rowsPerPageSelect');
        const infoEl = document.getElementById('paginationInfo');
        const prevBtn = document.getElementById('paginationPrevBtn');
        const nextBtn = document.getElementById('paginationNextBtn');
        const allRows = tbody ? Array.from(tbody.querySelectorAll('tr')) : [];
        let filteredRows = allRows.slice();
        let currentPage = 1;
        let rowsPerPage = parseRowsPerPage(rowsSelect ? rowsSelect.value : '10');

        // Select-all for first column checkboxes
        const selectAll = document.getElementById('selectAllRows');
        if (selectAll && tbody) {
            selectAll.addEventListener('change', () => {
                tbody.querySelectorAll('input.row-select').forEach(cb => {
                    cb.checked = selectAll.checked;
                });
            });
        }

        function parseRowsPerPage(val) {
            return (val === 'all') ? Infinity : (parseInt(val, 10) || 10);
        }

        function renderPage() {
            if (!tbody) return;
            // Hide all rows first
            allRows.forEach(r => r.style.display = 'none');
            const total = filteredRows.length;
            const totalPages = rowsPerPage === Infinity ? 1 : Math.max(1, Math.ceil(total / rowsPerPage));
            currentPage = Math.min(currentPage, totalPages);
            const startIndex = rowsPerPage === Infinity ? 0 : (currentPage - 1) * rowsPerPage;
            const endIndexExclusive = rowsPerPage === Infinity ? total : Math.min(startIndex + rowsPerPage, total);
            const visible = filteredRows.slice(startIndex, endIndexExclusive);
            visible.forEach(r => r.style.display = '');

            // Update info text (include total pages)
            const startDisp = total === 0 ? 0 : startIndex + 1;
            const endDisp = rowsPerPage === Infinity ? total : endIndexExclusive;
            const currentPageDisplay = rowsPerPage === Infinity ? 1 : currentPage;
            if (infoEl) infoEl.textContent = `Showing ${startDisp}-${endDisp} • Page ${currentPageDisplay} of ${totalPages}`;

            // Update buttons
            const disablePrev = (rowsPerPage === Infinity || currentPage <= 1);
            const disableNext = (rowsPerPage === Infinity || currentPage >= totalPages);
            if (prevBtn) {
                prevBtn.disabled = disablePrev;
                prevBtn.setAttribute('aria-disabled', disablePrev ? 'true' : 'false');
                prevBtn.classList.toggle('pagination__button--disabled', disablePrev);
            }
            if (nextBtn) {
                nextBtn.disabled = disableNext;
                nextBtn.setAttribute('aria-disabled', disableNext ? 'true' : 'false');
                nextBtn.classList.toggle('pagination__button--disabled', disableNext);
            }
        }

        if (rowsSelect) {
            rowsSelect.addEventListener('change', () => {
                rowsPerPage = parseRowsPerPage(rowsSelect.value);
                currentPage = 1;
                renderPage();
            });
        }
        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    renderPage();
                }
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                const totalPages = rowsPerPage === Infinity ? 1 : Math.max(1, Math.ceil(filteredRows.length / rowsPerPage));
                if (currentPage < totalPages) {
                    currentPage++;
                    renderPage();
                }
            });
        }

        // Initial render
        renderPage();

        // Global loader controls (shared across dashboard pages)
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
        // Show loader when navigating away; hide once DOM is ready
        window.addEventListener('beforeunload', showLoader);
        document.addEventListener('DOMContentLoaded', hideLoader);
    </script>

    <!-- Confirmation Modal (aligned with styled design used on other pages) -->
    <div id="confirmationModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3500; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
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

    <script>
        // Update modal handlers
        const updateModalEl = document.getElementById('updateSubmissionModal');
        const updateStatusSelect = document.getElementById('updateStatusSelect');
        const updateRemarksInput = document.getElementById('updateRemarksInput');
        const updateCanEditCheckbox = document.getElementById('updateCanEditCheckbox');
        const updateCanResubmitCheckbox = document.getElementById('updateCanResubmitCheckbox');
        const updateSubmissionIdInput = document.getElementById('updateSubmissionId');
        const closeUpdateBtn = document.getElementById('closeUpdateModalBtn');
        const cancelUpdateBtn = document.getElementById('cancelUpdateModalBtn');
        const confirmUpdateBtn = document.getElementById('confirmUpdateModalBtn');

        // Cache of submission statuses (name + color) for pill refresh
        window.submissionStatusesCache = window.submissionStatusesCache || [];

        function openUpdateModal(btn) {
            const sid = Number(btn?.dataset?.submissionId || 0);
            const currentStatus = (btn?.dataset?.currentStatus || '').trim();
            if (!sid) {
                showMessage('Update Submission', 'Invalid submission id');
                return;
            }
            updateSubmissionIdInput.value = String(sid);
            updateRemarksInput.value = '';
            updateCanEditCheckbox.checked = false;
            updateCanResubmitCheckbox.checked = false;
            // Load statuses
            fetch('get_submission_statuses.php')
                .then(r => r.json())
                .then(data => {
                    updateStatusSelect.innerHTML = '';
                    const names = Array.isArray(data?.statuses) ? data.statuses : [];
                    window.submissionStatusesCache = names;
                    names.forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s.name;
                        opt.textContent = s.name;
                        if (s.name === currentStatus) opt.selected = true;
                        updateStatusSelect.appendChild(opt);
                    });
                    // Auto-fill remarks from selected status remarks
                    const selectedName = updateStatusSelect.value || '';
                    const match = names.find(x => x.name === selectedName);
                    updateRemarksInput.value = (match && match.remarks) ? String(match.remarks) : '';
                    updateModalEl.style.display = 'flex';
                })
                .catch(() => {
                    // Fallback minimal options
                    updateStatusSelect.innerHTML = '';
                    ['Pending', 'Accepted', 'Rejected'].forEach(n => {
                        const opt = document.createElement('option');
                        opt.value = n;
                        opt.textContent = n;
                        if (n === currentStatus) opt.selected = true;
                        updateStatusSelect.appendChild(opt);
                    });
                    updateModalEl.style.display = 'flex';
                });
        }
        window.openUpdateModal = openUpdateModal;

        function closeUpdateModal() {
            updateModalEl.style.display = 'none';
        }
        closeUpdateBtn?.addEventListener('click', closeUpdateModal);
        cancelUpdateBtn?.addEventListener('click', closeUpdateModal);
        updateModalEl?.addEventListener('click', (e) => {
            if (e.target === updateModalEl) closeUpdateModal();
        });

        confirmUpdateBtn?.addEventListener('click', async () => {
            const sid = Number(updateSubmissionIdInput.value || 0);
            const status = (updateStatusSelect.value || '').trim();
            const remarks = (updateRemarksInput.value || '').trim();
            const canUpdate = updateCanEditCheckbox.checked ? '1' : '0';
            const canSubmitAnother = updateCanResubmitCheckbox.checked ? '1' : '0';
            if (!sid || !status) {
                showMessage('Update Submission', 'Select a valid status.');
                return;
            }
            try {
                showLoader();
                const resp = await fetch('update_submission_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        submission_id: String(sid),
                        status,
                        remarks,
                        can_update: canUpdate,
                        can_submit_another: canSubmitAnother
                    }).toString()
                });
                const data = await resp.json();
                hideLoader();
                if (data && data.ok) {
                    // Update the row's status cell
                    const row = Array.from(document.querySelectorAll('#applicantsTable tbody tr')).find(r => {
                        const idCell = r.querySelector("td[data-cell='ID']");
                        return idCell && Number(idCell.textContent.trim()) === sid;
                    });
                    if (row) {
                        const statusCell = row.querySelector("td[data-cell='Status']");
                        if (statusCell) {
                            const arr = window.submissionStatusesCache || [];
                            const match = arr.find(s => s.name === status);
                            const colorHex = match && (match.hex_color || match.color_hex) ? String(match.hex_color || match.color_hex) : '#6C757D';
                            statusCell.innerHTML = '<span class="status-pill" style="background-color: ' + colorHex.replace(/</g, '&lt;') + ';">' + status.replace(/</g, '&lt;') + '</span>';
                        }
                    }
                    closeUpdateModal();
                } else {
                    showMessage('Update Submission', (data && data.error) ? data.error : 'Failed to update submission');
                }
            } catch (e) {
                hideLoader();
                showMessage('Network Error', 'Network error while updating submission');
            }
        });

        // View modal handlers
        const viewModalEl = document.getElementById('viewSubmissionModal');
        const closeViewBtn = document.getElementById('closeViewModalBtn');
        const modalRegNameEl = document.getElementById('modalRegName');
        const modalEmailEl = document.getElementById('modalEmail');
        const modalCycleEl = document.getElementById('modalCycle');
        const modalSubmittedEl = document.getElementById('modalSubmitted');
        const modalStatusEl = document.getElementById('modalStatus');
        const modalRemarksEl = document.getElementById('modalRemarks');
        const modalAnswersEl = document.getElementById('modalAnswers');
        const modalFilesEl = document.getElementById('modalFiles');
        const viewUpdateStatusSelect = document.getElementById('viewUpdateStatusSelect');
        const viewUpdateRemarksInput = document.getElementById('viewUpdateRemarksInput');
        const viewAllowUpdateCheckbox = document.getElementById('viewAllowUpdateCheckbox');
        const viewAllowResubmitCheckbox = document.getElementById('viewAllowResubmitCheckbox');
        const viewUpdateSaveBtn = document.getElementById('viewUpdateSaveBtn');
        const viewUpdateCancelBtn = document.getElementById('viewUpdateCancelBtn');

        function openViewModal(btn) {
            const sid = Number(btn?.dataset?.submissionId || 0);
            if (!sid) {
                showMessage('View Submission', 'Invalid submission id');
                return;
            }
            // Get full name from the table row
            const row = btn.closest('tr');
            const nameCell = row ? row.querySelector("td[data-cell='Registered Name']") : null;
            const regName = nameCell ? (nameCell.textContent || '').trim() : '-';
            modalRegNameEl.textContent = regName || '-';
            modalEmailEl.textContent = '-';
            modalCycleEl.textContent = '-';
            modalSubmittedEl.textContent = '-';
            modalStatusEl.textContent = '-';
            modalStatusEl.style.backgroundColor = '#6C757D';
            modalRemarksEl.textContent = '-';
            modalAnswersEl.innerHTML = '';
            modalFilesEl.innerHTML = '';
            viewModalEl.style.display = 'flex';
            viewModalEl.setAttribute('data-sid', String(sid));

            // Load details
            showLoader();
            fetch('get_submission_details.php?id=' + encodeURIComponent(sid))
                .then(res => res.json())
                .then(data => {
                    hideLoader();
                    if (!data || data.ok === false) {
                        showMessage('View Submission', (data && data.error) ? data.error : 'Failed to load submission details');
                        return;
                    }
                    const m = data.main || {};
                    // Account & cycle info
                    modalEmailEl.textContent = m.email || '-';
                    modalCycleEl.textContent = m.cycle_name || '-';
                    modalSubmittedEl.textContent = m.submitted_at || '-';

                    // Application info
                    const statusName = m.status || '-';
                    const statusHex = m.status_hex || '#6C757D';
                    modalStatusEl.textContent = String(statusName);
                    modalStatusEl.style.backgroundColor = String(statusHex || '#6C757D');
                    modalRemarksEl.textContent = (m.remarks || '-');

                    // Text data
                    const textArr = Array.isArray(data.text_data) ? data.text_data : [];
                    if (textArr.length === 0) {
                        const li = document.createElement('li');
                        li.style.color = '#6b7280';
                        li.textContent = 'No text fields';
                        modalAnswersEl.appendChild(li);
                    } else {
                        textArr.forEach(item => {
                            const li = document.createElement('li');
                            li.style.padding = '6px 8px';
                            li.style.border = '1px solid var(--color-border)';
                            li.style.borderRadius = '10px';
                            li.innerHTML = '<strong>' + String(item.label || 'Field').replace(/</g, '&lt;') + ':</strong> ' +
                                '<span>' + String(item.value || '').replace(/</g, '&lt;') + '</span>';
                            modalAnswersEl.appendChild(li);
                        });
                    }

                    // Files
                    const filesArr = Array.isArray(data.files) ? data.files : [];
                    if (filesArr.length === 0) {
                        const li = document.createElement('li');
                        li.style.color = '#6b7280';
                        li.textContent = 'No files';
                        modalFilesEl.appendChild(li);
                    } else {
                        filesArr.forEach(f => {
                            const li = document.createElement('li');
                            li.style.padding = '6px 8px';
                            li.style.border = '1px solid var(--color-border)';
                            li.style.borderRadius = '10px';
                            const label = String(f.label || f.field_name || 'File').replace(/</g, '&lt;');
                            const fname = String(f.original_filename || '').replace(/</g, '&lt;');
                            const url = String(f.file_path || '').replace(/</g, '&lt;');
                            li.innerHTML = '<strong>' + label + ':</strong> ' +
                                (url ? '<a href="' + url + '" target="_blank" rel="noopener">' + (fname || url) + '</a>' : '<span>-</span>');
                            modalFilesEl.appendChild(li);
                        });
                    }

                    // Prefill update controls in View modal
                    viewAllowUpdateCheckbox.checked = (Number(m.can_update || 0) === 1);
                    viewAllowResubmitCheckbox.checked = false;
                    viewUpdateStatusSelect.innerHTML = '';
                    viewUpdateRemarksInput.value = '';

                    // Load statuses for update controls
                    fetch('get_submission_statuses.php')
                        .then(r => r.json())
                        .then(sdata => {
                            const arr = Array.isArray(sdata?.statuses) ? sdata.statuses : [];
                            window.submissionStatusesCache = arr;
                            arr.forEach(s => {
                                const opt = document.createElement('option');
                                opt.value = s.name;
                                opt.textContent = s.name;
                                if (s.name === statusName) opt.selected = true;
                                viewUpdateStatusSelect.appendChild(opt);
                            });
                            const match = arr.find(x => x.name === (viewUpdateStatusSelect.value || ''));
                            viewUpdateRemarksInput.value = (match && match.remarks) ? String(match.remarks) : '';
                        })
                        .catch(() => {
                            viewUpdateStatusSelect.innerHTML = '';
                            ['Pending', 'Accepted', 'Rejected'].forEach(n => {
                                const opt = document.createElement('option');
                                opt.value = n;
                                opt.textContent = n;
                                if (n === statusName) opt.selected = true;
                                viewUpdateStatusSelect.appendChild(opt);
                            });
                            viewUpdateRemarksInput.value = '';
                        });
                })
                .catch(() => {
                    hideLoader();
                    showMessage('Network Error', 'Network error while loading details');
                });
        }
        window.openViewModal = openViewModal;

        function closeViewModal() {
            viewModalEl.style.display = 'none';
        }
        closeViewBtn?.addEventListener('click', closeViewModal);
        viewUpdateCancelBtn?.addEventListener('click', closeViewModal);
        viewModalEl?.addEventListener('click', (e) => {
            if (e.target === viewModalEl) closeViewModal();
        });

        // Sync remarks in View modal when status changes
        viewUpdateStatusSelect?.addEventListener('change', () => {
            const arr = window.submissionStatusesCache || [];
            const match = arr.find(s => s.name === (viewUpdateStatusSelect.value || ''));
            viewUpdateRemarksInput.value = (match && match.remarks) ? String(match.remarks) : '';
        });

        // Save updates from View modal
        viewUpdateSaveBtn?.addEventListener('click', async () => {
            const sid = Number(viewModalEl.getAttribute('data-sid') || 0);
            const status = (viewUpdateStatusSelect.value || '').trim();
            const remarks = (viewUpdateRemarksInput.value || '').trim();
            const canUpdate = viewAllowUpdateCheckbox.checked ? '1' : '0';
            const canSubmitAnother = viewAllowResubmitCheckbox.checked ? '1' : '0';
            if (!sid || !status) {
                showMessage('View Submission', 'Select a valid status.');
                return;
            }
            try {
                showLoader();
                const resp = await fetch('update_submission_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        submission_id: String(sid),
                        status,
                        remarks,
                        can_update: canUpdate,
                        can_submit_another: canSubmitAnother
                    }).toString()
                });
                const data = await resp.json();
                hideLoader();
                if (data && data.ok) {
                    // Update displayed fields
                    modalStatusEl.textContent = status;
                    const arr = window.submissionStatusesCache || [];
                    const match = arr.find(s => s.name === status);
                    const colorHex = match && (match.hex_color || match.color_hex) ? String(match.hex_color || match.color_hex) : '#6C757D';
                    modalStatusEl.style.backgroundColor = colorHex;
                    modalRemarksEl.textContent = remarks || '-';

                    // Also update the table row status pill if present
                    const row = Array.from(document.querySelectorAll('#applicantsTable tbody tr')).find(r => {
                        const idCell = r.querySelector("td[data-cell='ID']");
                        return idCell && Number(idCell.textContent.trim()) === sid;
                    });
                    if (row) {
                        const statusCell = row.querySelector("td[data-cell='Status']");
                        if (statusCell) {
                            statusCell.innerHTML = '<span class="status-pill" style="background-color: ' + colorHex.replace(/</g, '&lt;') + ';">' + status.replace(/</g, '&lt;') + '</span>';
                        }
                    }
                    showMessage('Update Submission', 'Submission updated.');
                } else {
                    showMessage('Update Submission', (data && data.error) ? data.error : 'Failed to update submission');
                }
            } catch (e) {
                hideLoader();
                showMessage('Network Error', 'Network error while updating submission');
            }
        });

        // Assign Room Modal UI and logic
        (function() {
            const modalHtml = `
            <div id="assignRoomModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3002; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
                <div role="dialog" aria-modal="true" aria-labelledby="assignRoomTitle" style="background: var(--color-card); border-radius: 20px; max-width: 860px; width: 95%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); position: relative;">
                    <button type="button" id="closeAssignRoomModal" style="position: absolute; top: 16px; right: 16px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px;">&times;</button>
                    <div style="padding: 32px 24px 16px 24px;">
                        <h3 id="assignRoomTitle" style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.4rem; font-weight: 700; letter-spacing: -0.015em;">Select a Room</h3>
                        <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Choose from available exam schedules. Click a row to assign.</p>
                        <div class="table-container" style="margin-top: 14px;">
                            <table class="table" id="roomsTable">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Floor</th>
                                        <th>Room</th>
                                        <th>Capacity</th>
                                        <th>Booked</th>
                                        <th>Starts At</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>`;

            // Inject modal once (works whether DOMContentLoaded has fired or not)
            function injectAssignRoomModal() {
                if (!document.getElementById('assignRoomModal')) {
                    const container = document.createElement('div');
                    container.innerHTML = modalHtml;
                    document.body.appendChild(container.firstElementChild);
                }
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', injectAssignRoomModal);
            } else {
                injectAssignRoomModal();
            }

            let currentAssign = {
                submissionId: null,
                userId: null,
                applicantName: ''
            };

            // Confirmation modal helpers (reuse site-wide design)
            const confirmationModal = document.getElementById('confirmationModal');
            const modalTitleEl = document.getElementById('modalTitle');
            const modalMessageEl = document.getElementById('modalMessage');
            const modalConfirmBtn = document.getElementById('modalConfirmBtn');
            const modalCancelBtn = document.getElementById('modalCancelBtn');

            function closeConfirmationModal() {
                if (confirmationModal) confirmationModal.style.display = 'none';
                if (modalConfirmBtn) modalConfirmBtn.onclick = null;
            }
            if (confirmationModal) {
                confirmationModal.addEventListener('click', function(e) {
                    if (e.target === confirmationModal) closeConfirmationModal();
                });
            }
            if (modalCancelBtn) {
                modalCancelBtn.addEventListener('click', closeConfirmationModal);
            }

            function showConfirm(title, message, onConfirm) {
                if (!confirmationModal || !modalTitleEl || !modalMessageEl || !modalConfirmBtn || !modalCancelBtn) {
                    if (typeof onConfirm === 'function') onConfirm();
                    return;
                }
                modalTitleEl.textContent = title || 'Confirm Action';
                modalMessageEl.textContent = message || 'Are you sure?';
                modalConfirmBtn.textContent = 'Confirm';
                modalCancelBtn.style.display = '';
                modalConfirmBtn.onclick = function() {
                    try {
                        onConfirm && onConfirm();
                    } finally {
                        closeConfirmationModal();
                    }
                };
                confirmationModal.style.display = 'flex';
                confirmationModal.style.visibility = 'visible';
                confirmationModal.style.opacity = '1';
            }

            // Message modal helper (same design, OK-only)
            function showMessage(title, message) {
                if (!confirmationModal || !modalTitleEl || !modalMessageEl || !modalConfirmBtn || !modalCancelBtn) {
                    try {
                        alert(message || '');
                    } catch (e) {}
                    return;
                }
                modalTitleEl.textContent = title || 'Message';
                modalMessageEl.textContent = message || '';
                modalConfirmBtn.textContent = 'OK';
                modalCancelBtn.style.display = 'none';
                modalConfirmBtn.onclick = function() {
                    closeConfirmationModal();
                };
                confirmationModal.style.display = 'flex';
                confirmationModal.style.visibility = 'visible';
                confirmationModal.style.opacity = '1';
            }

            async function openAssignRoomModal(submissionId) {
                currentAssign = {
                    submissionId,
                    userId: null,
                    applicantName: ''
                };
                // Ensure modal exists; inject if missing
                injectAssignRoomModal();
                const modal = document.getElementById('assignRoomModal');
                const closeBtn = document.getElementById('closeAssignRoomModal');
                const tbody = document.querySelector('#roomsTable tbody');
                if (!modal || !tbody) return;

                // Show modal upfront and indicate loading
                modal.style.display = 'flex';
                setTimeout(() => modal.classList.add('show'), 20);
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">Loading schedules…</td></tr>';
                closeBtn.onclick = () => {
                    modal.classList.remove('show');
                    setTimeout(() => {
                        modal.style.display = 'none';
                    }, 180);
                };
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        modal.classList.remove('show');
                        setTimeout(() => {
                            modal.style.display = 'none';
                        }, 180);
                    }
                });
                document.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape' && modal.classList.contains('show')) {
                        modal.classList.remove('show');
                        modal.style.display = 'none';
                    }
                }, {
                    once: true
                });

                // Fetch submission details to get user_id and name
                try {
                    showLoader();
                    const res = await fetch('get_submission_details.php?id=' + encodeURIComponent(submissionId));
                    const det = await res.json();
                    if (!det || det.ok === false) {
                        hideLoader();
                        showMessage('Assign Room', (det && det.error) ? det.error : 'Failed to load details');
                        return;
                    }
                    const m = det.main || {};
                    currentAssign.userId = Number(m.user_id || 0);
                    currentAssign.applicantName = (m.applicant_name || '').trim();
                    if (!currentAssign.userId) {
                        hideLoader();
                        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; color:#c53030;">Missing user ID for this submission.</td></tr>';
                        return;
                    }

                    // Load schedules
                    const schedRes = await fetch('list_exam_schedules.php');
                    const schedData = await schedRes.json();
                    hideLoader();
                    if (!schedData || schedData.ok === false) {
                        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; color:#c53030;">' + ((schedData && schedData.error) ? String(schedData.error).replace(/</g, '&lt;') : 'Failed to load schedules') + '</td></tr>';
                        return;
                    }

                    const schedules = Array.isArray(schedData.schedules) ? schedData.schedules : [];
                    tbody.innerHTML = '';
                    if (schedules.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No schedules found</td></tr>';
                    } else {
                        schedules.forEach((s, idx) => {
                            const dt = String(s.start_date_and_time || '')
                                .replace(' ', 'T');
                            const d = new Date(dt);
                            const prettyDt = isNaN(d.getTime()) ? (s.start_date_and_time || '') : d.toLocaleString('en-US', {
                                month: 'short',
                                day: 'numeric',
                                year: 'numeric',
                                hour: 'numeric',
                                minute: '2-digit'
                            });
                            const tr = document.createElement('tr');
                            tr.style.cursor = 'pointer';
                            tr.innerHTML = `
                                <td>${idx + 1}</td>
                                <td>${(s.floor || '').replace(/</g,'&lt;')}</td>
                                <td>${(s.room || '').replace(/</g,'&lt;')}</td>
                                <td>${s.capacity ?? 0}</td>
                                <td>${s.booked_count ?? 0}</td>
                                <td>${prettyDt}</td>
                                <td>${(s.status || 'Open').replace(/</g,'&lt;')}</td>
                            `;
                            tr.addEventListener('click', function() {
                                const label = `${(s.floor||'').trim()}${(s.floor&&s.room?' • ':'')}${(s.room||'').trim()}`;
                                const msg = `Assign ${currentAssign.applicantName || 'this applicant'} to ${label} at ${prettyDt}?`;
                                showConfirm('Confirm Assignment', msg, function() {
                                    doAssignRoom(currentAssign.userId, s.schedule_id, () => {
                                        modal.classList.remove('show');
                                        modal.style.display = 'none';
                                    });
                                });
                            });
                            tbody.appendChild(tr);
                        });
                    }

                } catch (e) {
                    hideLoader();
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; color:#c53030;">Network error while loading schedules</td></tr>';
                }
            }

            async function doAssignRoom(userId, scheduleId, onDone) {
                try {
                    showLoader();
                    const resp = await fetch('assign_room.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            user_id: String(userId),
                            schedule_id: String(scheduleId)
                        }).toString()
                    });
                    const data = await resp.json();
                    hideLoader();
                    if (data && data.ok) {
                        const msg = String(data.message || '').toLowerCase();
                        if (msg.includes('already assigned')) {
                            // Do not close modal; allow user to pick another room
                            showMessage('Assign Room', 'User is already assigned to this room.');
                        } else {
                            showMessage('Assign Room', 'Room assigned successfully.');
                            if (typeof onDone === 'function') onDone();
                        }
                    } else {
                        showMessage('Assign Room', (data && data.error) ? data.error : 'Failed to assign room');
                    }
                } catch (err) {
                    hideLoader();
                    showMessage('Network Error', 'Network error while assigning room');
                }
            }

            // Expose
            window.openAssignRoomModal = openAssignRoomModal;
        })();
    </script>
</body>

</html>