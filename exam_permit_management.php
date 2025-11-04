<?php
// Authentication middleware - protect this page
require_once 'middleware/auth.php';
require_once 'connection/db_connect.php';

// Fetch submissions with applicant name, cycle, and exam permit status
// If application_permit not found => "Exam Permit NOT Sent"
// If found => use application_permit.status (enum: 'pending','used')
$sql = "SELECT\n  s.user_id,\n  s.submitted_at,\n  TRIM(CONCAT_WS(' ',\n    uf.first_name,\n    NULLIF(uf.middle_name, ''),\n    uf.last_name,\n    NULLIF(uf.suffix, '')\n  )) AS applicant_name,\n  c.cycle_name AS admission_cycle_name,\n  CASE WHEN ap.id IS NULL THEN 'Exam Permit NOT Sent' ELSE ap.status END AS status,\n  CASE WHEN ap.id IS NULL THEN 0 ELSE 1 END AS has_permit\nFROM submissions s\nLEFT JOIN user_fullname uf\n  ON uf.user_id = s.user_id\nLEFT JOIN applicant_types at\n  ON at.id = s.applicant_type_id\nLEFT JOIN admission_cycles c\n  ON c.id = at.admission_cycle_id\nLEFT JOIN application_permit ap\n  ON ap.id = (SELECT MAX(ap2.id) FROM application_permit ap2 WHERE ap2.user_id = s.user_id)\nORDER BY s.submitted_at DESC";
$result_rows = [];
if ($res = $conn->query($sql)) {
    while ($row = $res->fetch_assoc()) {
        $result_rows[] = $row;
    }
    $res->free();
}
function statusBadgeClass($status)
{
    $s = strtolower(trim((string)$status));
    // Exam permit specific statuses
    if ($s === 'exam permit sent') return 'badge--success';
    if ($s === 'exam permit not sent') return 'badge--danger';
    // application_permit statuses
    if ($s === 'pending') return 'badge--warning';
    if ($s === 'used') return 'badge--success';
    // Fallbacks for submission lifecycle statuses
    if ($s === 'accepted' || $s === 'approved') return 'badge--success';
    if ($s === 'pending' || $s === 'waitlisted') return 'badge--warning';
    if ($s === 'rejected' || $s === 'failed') return 'badge--danger';
    if ($s === 'in review' || $s === 'examination') return 'badge--info';
    return 'badge--secondary';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Success Office - Exam Permit Generator</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="dashboard.css">

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
                    <h1></h1>
                    <p class="header__subtitle"></p>
                </div>
                <div class="header__actions">
                    <button class="btn btn--primary">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Generate Exam Permit
                    </button>
                </div>
            </header>

            <section class="section active" id="exam_permit_generation_section">
                <div class="table-container">
                    <div class="table-container__header">
                        <h2 class="table-container__title">Manage Exam Permit</h2>
                        <p class="table-container__subtitle">View and manage exam permit</p>
                    </div>

                    <div class="filtration_container">
                        <div class="search_input_container">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search-icon lucide-search">
                                <path d="m21 21-4.34-4.34" />
                                <circle cx="11" cy="11" r="8" />
                            </svg>
                            <input type="text" placeholder="Search application name, academic year, date applied, status..">
                        </div>

                        <div class="search_button_container">
                            <button class="button export">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-scan-qr-code-icon lucide-scan-qr-code">
                                    <path d="M17 12v4a1 1 0 0 1-1 1h-4" />
                                    <path d="M17 3h2a2 2 0 0 1 2 2v2" />
                                    <path d="M17 8V7" />
                                    <path d="M21 17v2a2 2 0 0 1-2 2h-2" />
                                    <path d="M3 7V5a2 2 0 0 1 2-2h2" />
                                    <path d="M7 17h.01" />
                                    <path d="M7 21H5a2 2 0 0 1-2-2v-2" />
                                    <rect x="7" y="7" width="5" height="5" rx="1" />
                                </svg>
                                Validate Exam Permit</button>
                        </div>

                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllPermits" class="table-checkbox"></th>
                                <th class="sortable" data-column="no">#
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th class="sortable" data-column="applicant-name">APPLICANT NAME
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th class="sortable" data-column="academic-year">ACADEMIC YEAR
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th class="sortable" data-column="date-applied">DATE APPLIED
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th class="sortable" data-column="status">STATUS
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th data-column="action">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rowNum = 1;
                            foreach ($result_rows as $r):
                                $applicant = htmlspecialchars($r['applicant_name'] ?? '—');
                                $cycle = htmlspecialchars($r['admission_cycle_name'] ?? '—');
                                $submittedRaw = $r['submitted_at'] ?? null;
                                $submitted = isset($submittedRaw) ? date('M d, Y', strtotime($submittedRaw)) : '—';
                                $status = htmlspecialchars($r['status'] ?? 'Pending');
                                $badgeClass = statusBadgeClass($status);
                                // Resolve submission id for View modal
                                $submissionId = 0;
                                if (!empty($r['user_id']) && !empty($submittedRaw)) {
                                    if ($stmtSid = $conn->prepare("SELECT id FROM submissions WHERE user_id = ? AND submitted_at = ? LIMIT 1")) {
                                        $uid = (int)$r['user_id'];
                                        $stmtSid->bind_param('is', $uid, $submittedRaw);
                                        $stmtSid->execute();
                                        $resSid = $stmtSid->get_result();
                                        if ($resSid && ($rowSid = $resSid->fetch_assoc())) {
                                            $submissionId = (int)$rowSid['id'];
                                        }
                                        $stmtSid->close();
                                    }
                                }
                            ?>
                                <tr>
                                    <td><input type="checkbox" class="table-checkbox row-checkbox" data-id="USR-<?php echo (int)($r['user_id'] ?? 0); ?>"></td>
                                    <td data-cell="no"><?php echo $rowNum++; ?></td>
                                    <td data-cell="applicant-name"><?php echo $applicant; ?></td>
                                    <td data-cell="academic-year"><?php echo $cycle; ?></td>
                                    <td data-cell="date-applied"><?php echo $submitted; ?></td>
                                    <td data-cell="status"><span class="badge <?php echo $badgeClass; ?> status"><?php echo $status; ?></span></td>
                                    <td data-cell="action">
                                        <div class="table__actions">
                                            <?php $hasPermit = (int)($r['has_permit'] ?? 0) === 1; ?>
                                            <button class="table__btn table__btn--delete showResendbtn" data-applicant-name="<?php echo htmlspecialchars($applicant); ?>" data-user-id="<?php echo (int)($r['user_id'] ?? 0); ?>" style="<?php echo $hasPermit ? 'display:inline-block;' : 'display:none;'; ?>">Resend</button>
                                            <button class="table__btn showViewbtn" data-submission-id="<?php echo $submissionId; ?>">View</button>
                                            <?php if (!$hasPermit): ?>
                                                <button class="table__btn table__btn--edit sendbtn" data-applicant-name="<?php echo htmlspecialchars($applicant); ?>" data-user-id="<?php echo (int)($r['user_id'] ?? 0); ?>">Send</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
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

                <!-- Floating Action Menu for Bulk Operations -->
                <div id="floatingActionMenu" style="display: none; position: fixed; bottom: 30px; right: 30px; background: var(--color-card); border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.15), 0 8px 25px rgba(0,0,0,0.1); z-index: 1001; padding: 20px; min-width: 280px; border: 1px solid var(--color-border);">
                    <div style="margin-bottom: 16px;">
                        <h4 style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.1rem; font-weight: 600;">Bulk Actions</h4>
                        <p id="selectedCount" style="margin: 0; color: #718096; font-size: 0.9rem;">0 applicants selected</p>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <button id="bulkSendPermit" class="btn btn--secondary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 16px; border-radius: 10px; font-size: 0.9rem;" title="Floating only (no action)">
                            <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Send Exam Permit
                        </button>
                    </div>
                    <button id="closeBulkMenu" style="position: absolute; top: 8px; right: 8px; background: none; border: none; color: #a0aec0; cursor: pointer; padding: 4px; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </section>
        </main>
    </div>

    <!-- Send Exam Permit Modal (matches View modal design) -->
    <div id="sendExamPermitModal" style="display:none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background: linear-gradient(135deg, rgba(24, 165, 88, 0.10) 0%, rgba(19, 101, 21, 0.10) 100%); z-index: 2100; align-items: center; justify-content: center; backdrop-filter: blur(8px); padding: 20px;">
        <div style="background: var(--color-card); border-radius: 24px; max-width: 600px; width: 95%; margin: 0 auto; max-height: calc(100vh - 40px); overflow: hidden; box-shadow: 0 25px 80px rgba(0,0,0,0.15), 0 10px 40px rgba(0,0,0,0.1); border: 1px solid var(--color-border); position: relative; display: flex; flex-direction: column;">
            <!-- Decorative Header Background -->
            <div style="height: 120px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); position: relative; overflow: hidden;">
                <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%; animation: float 6s ease-in-out infinite;"></div>
                <div style="position: absolute; bottom: -30px; left: -30px; width: 100px; height: 100px; background: rgba(255,255,255,0.08); border-radius: 50%; animation: float 8s ease-in-out infinite reverse;"></div>

                <!-- Close Button -->
                <button type="button" id="closeSendModalBtn" style="position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.2); border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; transition: all 0.3s ease; backdrop-filter: blur(10px);">&times;</button>
            </div>

            <!-- Modal Content -->
            <div style="flex: 1; overflow-y: auto; padding: 20px 40px 40px 40px;">
                <h3 style="margin: 0 0 12px 0; color: #1a202c; font-size: 1.6rem; font-weight: 800; letter-spacing: -0.025em; text-align:center;">Exam Permit Details</h3>
                <p style="color: #718096; margin: 0 0 24px 0; line-height: 1.6; font-size: 1rem; text-align:center;">Fill out the fields below. Applicant name is prefilled.</p>

                <form id="sendExamPermitForm" style="width: 100%; margin: 0 auto;">
                    <div style="display:grid; grid-template-columns: 1fr; gap: 16px;">
                        <div>
                            <label for="sendAdmissionOfficer" style="display:block; font-weight:600; margin-bottom:6px; color:#2d3748;">Admission Officer <span style="color:#e53e3e">*</span></label>
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
                            function full_name_display($row)
                            {
                                $parts = [];
                                $fn = trim((string)($row['first_name'] ?? ''));
                                $mn = trim((string)($row['middle_name'] ?? ''));
                                $ln = trim((string)($row['last_name'] ?? ''));
                                $sx = trim((string)($row['suffix'] ?? ''));
                                if ($fn !== '') $parts[] = $fn;
                                if ($mn !== '') $parts[] = $mn;
                                if ($ln !== '') $parts[] = $ln;
                                if ($sx !== '') $parts[] = $sx;
                                return implode(' ', $parts);
                            }
                            ?>
                            <select id="sendAdmissionOfficer" name="admission_officer" style="width:100%; padding:12px 14px; border:1px solid #e2e8f0; border-radius:12px; background:#fff;" required>
                                <option value="">Select an officer</option>
                                <?php foreach ($officers as $o):
                                    $full = full_name_display($o);
                                    $title = trim((string)($o['title'] ?? ''));
                                    $label = $full . ($title !== '' ? (' - ' . $title) : '');
                                ?>
                                    <option value="<?php echo htmlspecialchars($full, ENT_QUOTES); ?>">
                                        <?php echo htmlspecialchars($label, ENT_QUOTES); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; color:#2d3748;">Applicant Name</label>
                            <div id="sendApplicantNameText" style="width:100%; padding:12px 14px; border:1px solid #e2e8f0; border-radius:12px; background:#f7fafc; color:#1a202c; cursor:default;"></div>
                            <input type="hidden" id="sendApplicantNameHidden" name="applicant_name" />
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; color:#2d3748;">Applicant Number</label>
                            <div id="sendApplicantNumberText" style="width:100%; padding:12px 14px; border:1px solid #e2e8f0; border-radius:12px; background:#f7fafc; color:#1a202c; cursor:not-allowed;" aria-readonly="true">Will be generated</div>
                            <input type="hidden" id="sendApplicantNumberHidden" name="applicant_number" />
                        </div>
                        <input type="hidden" id="sendUserIdHidden" name="user_id" />
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; color:#2d3748;">Applicant Number Options</label>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                <div>
                                    <?php
                                    $prefixes = [];
                                    if (isset($conn) && $conn) {
                                        if ($stmtPref = $conn->prepare("SELECT prefix FROM applicant_number_prefix ORDER BY date_added DESC")) {
                                            $stmtPref->execute();
                                            $resPref = $stmtPref->get_result();
                                            if ($resPref) {
                                                while ($rowPref = $resPref->fetch_assoc()) {
                                                    $prefixes[] = $rowPref['prefix'];
                                                }
                                            }
                                            $stmtPref->close();
                                        }
                                    }
                                    ?>
                                    <label for="sendApplicantPrefix" style="display:block; font-weight:500; margin-bottom:4px; color:#4a5568;">Prefix <span style="color:#e53e3e">*</span></label>
                                    <select id="sendApplicantPrefix" name="applicant_number_prefix" style="width:100%; padding:12px 14px; border:1px solid #e2e8f0; border-radius:12px; background:#fff;" required>
                                        <option value="">Select prefix</option>
                                        <?php foreach ($prefixes as $p): ?>
                                            <option value="<?php echo htmlspecialchars($p, ENT_QUOTES); ?>"><?php echo htmlspecialchars($p, ENT_QUOTES); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="sendApplicantNumberLength" style="display:block; font-weight:500; margin-bottom:4px; color:#4a5568;">Digits <span style="color:#e53e3e">*</span></label>
                                    <input type="number" id="sendApplicantNumberLength" name="applicant_number_length" min="4" max="12" value="8" style="width:100%; padding:12px 14px; border:1px solid #e2e8f0; border-radius:12px; background:#fff;" required />
                                </div>
                            </div>
                            <div style="display:flex; gap:16px; align-items:center; margin-top:8px;">
                                <div style="display:flex; gap:12px; align-items:center;">
                                    <label style="font-weight:500; color:#4a5568;">Start Position:</label>
                                    <label><input type="radio" name="applicant_number_order" value="first" checked> First</label>
                                    <label><input type="radio" name="applicant_number_order" value="last"> Last</label>
                                </div>

                            </div>
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; color:#2d3748;">Application Period <span style="color:#e53e3e">*</span></label>
                            <div style="display:flex; gap:8px;">
                                <input type="date" id="sendApplicationStart" name="application_period_start" style="flex:1; padding:12px 14px; border:1px solid #e2e8f0; border-radius:12px; background:#fff;" required>
                                <input type="date" id="sendApplicationEnd" name="application_period_end" style="flex:1; padding:12px 14px; border:1px solid #e2e8f0; border-radius:12px; background:#fff;" required>
                            </div>
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; color:#2d3748;">Date of Exam</label>
                            <div id="sendExamDateText" style="width:100%; padding:12px 14px; border:1px solid #e2e8f0; border-radius:12px; background:#f7fafc; color:#1a202c; cursor:default;"></div>
                            <input type="hidden" id="sendExamDateHidden" name="date_of_exam" />
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:6px; color:#2d3748;">Time</label>
                            <div id="sendExamTimeText" style="width:100%; padding:12px 14px; border:1px solid #e2e8f0; border-radius:12px; background:#f7fafc; color:#1a202c; cursor:default;"></div>
                            <input type="hidden" id="sendExamTimeHidden" name="exam_time" />
                        </div>
                        <div>
                            <label for="sendAccentColor" style="display:block; font-weight:600; margin-bottom:6px; color:#2d3748;">Permit Color (Hex) <span style="color:#e53e3e">*</span></label>
                            <input type="color" id="sendAccentColor" name="accent_color" value="#18a558" style="width:100%; padding:8px 10px; border:1px solid #e2e8f0; border-radius:12px; background:#fff; height:42px;" required />
                            <small style="display:block; color:#718096; margin-top:6px;">Select a color; hex value will be submitted.</small>
                        </div>
                    </div>

                    <div style="display: flex; gap: 16px; justify-content: center; margin-top: 40px;">
                        <button type="button" id="cancelSendExamPermit" style="flex: 1; padding: 16px 32px; border: 3px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 16px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: all 0.2s ease;">Cancel</button>
                        <button type="submit" id="confirmSendExamPermit" style="flex: 1; padding: 16px 32px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 16px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: all 0.2s ease;">Send</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Feedback Modal (styled like Send modal) -->
    <div id="sendFeedbackModal" style="display:none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background: linear-gradient(135deg, rgba(24, 165, 88, 0.10) 0%, rgba(19, 101, 21, 0.10) 100%); z-index: 2200; align-items: center; justify-content: center; backdrop-filter: blur(8px); padding: 20px;">
        <div style="background: var(--color-card); border-radius: 24px; max-width: 600px; width: 95%; margin: 0 auto; max-height: calc(100vh - 40px); overflow: hidden; box-shadow: 0 25px 80px rgba(0,0,0,0.15), 0 10px 40px rgba(0,0,0,0.1); border: 1px solid var(--color-border); position: relative; display: flex; flex-direction: column;">
            <!-- Decorative Header Background -->
            <div style="height: 120px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); position: relative; overflow: hidden;">
                <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%; animation: float 6s ease-in-out infinite;"></div>
                <div style="position: absolute; bottom: -30px; left: -30px; width: 100px; height: 100px; background: rgba(255,255,255,0.08); border-radius: 50%; animation: float 8s ease-in-out infinite reverse;"></div>
                <!-- Close Button (shown for failure, hidden for success) -->
                <button type="button" id="closeFeedbackModalBtn" style="position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.2); border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; transition: all 0.3s ease; backdrop-filter: blur(10px);">&times;</button>
            </div>

            <!-- Modal Content -->
            <div style="flex: 1; overflow-y: auto; padding: 20px 40px 40px 40px;">
                <h3 style="margin: 0 0 12px 0; color: #1a202c; font-size: 1.6rem; font-weight: 800; letter-spacing: -0.025em; text-align:center;">Submission Status</h3>
                <div id="sendFeedbackMessage" style="color:#2d3748; font-weight:600; text-align:center; margin: 8px 0 24px 0;">Status</div>
                <div style="display:flex; justify-content:center; gap: 12px;">
                    <button type="button" id="closeFeedbackBtn" style="padding: 12px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 700; cursor: pointer;">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Resend Confirmation Modal -->
    <div id="resendConfirmModal" style="display:none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background: linear-gradient(135deg, rgba(24, 165, 88, 0.10) 0%, rgba(19, 101, 21, 0.10) 100%); z-index: 2250; align-items: center; justify-content: center; backdrop-filter: blur(8px); padding: 20px;">
        <div style="background: var(--color-card); border-radius: 24px; max-width: 560px; width: 95%; margin: 0 auto; overflow: hidden; box-shadow: 0 25px 80px rgba(0,0,0,0.15), 0 10px 40px rgba(0,0,0,0.1); border: 1px solid var(--color-border); position: relative; display: flex; flex-direction: column;">
            <div style="height: 100px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); position: relative; overflow: hidden;"></div>
            <div style="padding: 20px 32px 8px 32px;">
                <h3 style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.4rem; font-weight: 800; text-align:center;">Confirm Resend</h3>
                <p style="color:#4a5568; text-align:center; margin:0 0 16px 0;">Send the exam permit again to the applicant below.</p>
                <div style="border:1px solid #e2e8f0; border-radius:12px; background:#f7fafc; padding:12px 16px;">
                    <div style="color:#2d3748; font-weight:600;">Applicant</div>
                    <div id="resendConfirmName" style="color:#4a5568; margin-top:6px;">Name</div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:16px;">
                    <button type="button" id="resendCancelBtn" style="padding: 10px 16px; border: 3px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 700; cursor: pointer;">Cancel</button>
                    <button type="button" id="resendConfirmBtn" style="padding: 10px 16px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 700; cursor: pointer;">Resend Permit</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Send Confirmation Modal -->
    <div id="bulkConfirmModal" style="display:none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background: linear-gradient(135deg, rgba(24, 165, 88, 0.10) 0%, rgba(19, 101, 21, 0.10) 100%); z-index: 2250; align-items: center; justify-content: center; backdrop-filter: blur(8px); padding: 20px;">
        <div style="background: var(--color-card); border-radius: 24px; max-width: 600px; width: 95%; margin: 0 auto; overflow: hidden; box-shadow: 0 25px 80px rgba(0,0,0,0.15), 0 10px 40px rgba(0,0,0,0.1); border: 1px solid var(--color-border); position: relative; display: flex; flex-direction: column;">
            <div style="height: 100px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); position: relative; overflow: hidden;"></div>
            <div style="padding: 20px 32px 8px 32px;">
                <h3 style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.4rem; font-weight: 800; text-align:center;">Send Exam Permits</h3>
                <p style="color:#4a5568; text-align:center; margin:0 0 16px 0;">Proceed to send permits to selected applicants.</p>
                <div style="border:1px solid #e2e8f0; border-radius:12px; background:#f7fafc; padding:12px 16px;">
                    <div style="color:#2d3748; font-weight:600;">Selection</div>
                    <div id="bulkConfirmCount" style="color:#4a5568; margin-top:6px;">0 applicants selected</div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:16px;">
                    <button type="button" id="bulkCancelBtn" style="padding: 10px 16px; border: 3px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 700; cursor: pointer;">Cancel</button>
                    <button type="button" id="bulkConfirmBtn" style="padding: 10px 16px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 700; cursor: pointer;">Send Permits</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Validate Exam Permit Modal (design matches Send modal) -->
    <div id="validateExamPermitModal" style="display:none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background: linear-gradient(135deg, rgba(24, 165, 88, 0.10) 0%, rgba(19, 101, 21, 0.10) 100%); z-index: 2300; align-items: center; justify-content: center; backdrop-filter: blur(8px); padding: 20px;">
        <div style="background: var(--color-card); border-radius: 24px; max-width: 720px; width: 95%; margin: 0 auto; max-height: calc(100vh - 40px); overflow: hidden; box-shadow: 0 25px 80px rgba(0,0,0,0.15), 0 10px 40px rgba(0,0,0,0.1); border: 1px solid var(--color-border); position: relative; display: flex; flex-direction: column;">
            <!-- Decorative Header Background -->
            <div style="height: 120px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); position: relative; overflow: hidden;">
                <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%; animation: float 6s ease-in-out infinite;"></div>
                <div style="position: absolute; bottom: -30px; left: -3F0px; width: 100px; height: 100px; background: rgba(255,255,255,0.08); border-radius: 50%; animation: float 8s ease-in-out infinite reverse;"></div>
                <!-- No header close button (per request: only Cancel and Search) -->
            </div>

            <!-- Modal Content -->
            <div style="flex: 1; overflow-y: auto; padding: 20px 40px 40px 40px;">
                <h3 style="margin: 0 0 12px 0; color: #1a202c; font-size: 1.6rem; font-weight: 800; letter-spacing: -0.025em; text-align:center;">Validate Exam Permit</h3>
                <p style="color: #718096; margin: 0 0 24px 0; line-height: 1.6; font-size: 1rem; text-align:center;">Scan the QR code or enter the applicant number to validate.</p>

                <!-- Mode Switch -->
                <div style="display:flex; gap:8px; justify-content:center; margin-bottom: 12px;">
                    <button type="button" id="switchToScan" style="padding: 10px 16px; border: none; background: #edf2f7; color: #4a5568; border-radius: 10px; font-weight: 600; cursor: pointer;">Scan QR</button>
                    <button type="button" id="switchToManual" style="padding: 10px 16px; border: none; background: #edf2f7; color: #4a5568; border-radius: 10px; font-weight: 600; cursor: pointer;">Manual Entry</button>
                </div>

                <!-- Scan Section -->
                <div id="scanSection" style="display:block; border:1px solid #e2e8f0; border-radius:12px; padding:16px; background:#f7fafc;">
                    <video id="insta-preview" style="width:100%; height:300px; border:1px solid #e2e8f0; border-radius:8px; background:#000;"></video>
                    <div style="display:flex; gap:8px; justify-content:center; margin-top:12px;">
                        <button type="button" id="startScanBtn" style="padding:10px 16px; border:none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color:white; border-radius:10px; font-weight:700; cursor:pointer;">Start Scan</button>
                        <button type="button" id="stopScanBtn" style="padding:10px 16px; border:3px solid var(--color-border); background:var(--color-card); color:var(--color-text); border-radius:10px; font-weight:700; cursor:pointer; display:none;">Stop Scan</button>
                    </div>
                    <div id="qr-status" style="margin-top:8px; color:#718096; text-align:center; font-size:0.95rem;">Scanner idle. Click "Start Scan" to begin.</div>
                </div>

                <!-- Manual Section -->
                <div id="manualSection" style="display:none; border:1px solid #e2e8f0; border-radius:12px; padding:16px; background:#f7fafc;">
                    <label for="manualApplicantNumber" style="display:block; font-weight:600; margin-bottom:6px; color:#2d3748;">Applicant Number</label>
                    <input type="text" id="manualApplicantNumber" placeholder="e.g. PREFIX-00001234" style="width:100%; padding:12px 14px; border:1px solid #e2e8f0; border-radius:12px; background:#fff;" />
                </div>

                <!-- Result -->
                <div id="validateResult" style="margin-top:16px; display:none;">
                    <h4 style="margin:8px 0; color:#2d3748;">Result</h4>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px; border:1px solid #e2e8f0; border-radius:12px; padding:12px; background:#fff;">
                        <div><strong>Applicant Name:</strong> <span id="resultName">-</span></div>
                        <div><strong>Status:</strong> <span id="resultStatus" class="badge">-</span></div>
                        <div style="grid-column: span 2;"><strong>Applicant Number:</strong> <span id="resultNumber">-</span></div>
                    </div>
                    <div style="display:flex; gap: 12px; justify-content:flex-end; margin-top:12px;">
                        <button type="button" id="openStatusUpdateBtn" style="padding:8px 14px; border:none; background:#4c51bf; color:#fff; border-radius:10px; font-weight:700; cursor:pointer;">Update Status</button>
                    </div>
                </div>

                <!-- Not Found Message -->
                <div id="validateNotFound" style="margin-top:16px; display:none;">
                    <div style="border:1px solid #fee2e2; background:#fff5f5; color:#c53030; border-radius:12px; padding:12px; text-align:center; font-weight:700;">
                        Applicant number not found
                    </div>
                </div>

                <div style="display:flex; gap: 16px; justify-content: center; margin-top: 24px;">
                    <button type="button" id="cancelValidateBtn" style="flex: 1; padding: 12px 20px; border: 3px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 16px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: all 0.2s ease;">Cancel</button>
                    <button type="button" id="validateSearchBtn" style="flex: 1; padding: 12px 20px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 16px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: all 0.2s ease;">Search</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Permit Status Modal -->
    <div id="updatePermitStatusModal" style="display:none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.35); z-index: 2400; align-items: center; justify-content: center;">
        <div style="background: var(--color-card); border-radius: 16px; width: 520px; max-width: 95%; box-shadow: 0 20px 60px rgba(0,0,0,0.20); overflow: hidden;">
            <div style="display:flex; align-items:center; justify-content:space-between; padding: 14px 18px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color:white;">
                <h3 style="margin:0; font-size:1.2rem; font-weight:800;">Update Permit Status</h3>
            </div>
            <div style="padding: 16px 18px;">
                <div style="margin-bottom:12px; color:#4a5568;">
                    <div><strong>Applicant Name:</strong> <span id="upsName">-</span></div>
                    <div><strong>Applicant Number:</strong> <span id="upsNumber">-</span></div>
                    <div><strong>Current Status:</strong> <span id="upsCurrent" class="badge">-</span></div>
                </div>
                <div style="display:flex; gap:10px; justify-content:center; margin: 14px 0;">
                    <button type="button" id="markUsedBtn" style="padding:10px 14px; border:none; background:#38a169; color:white; border-radius:10px; font-weight:700; cursor:pointer;">Mark as Used</button>
                    <button type="button" id="markPendingBtn" style="padding:10px 14px; border:none; background:#d69e2e; color:white; border-radius:10px; font-weight:700; cursor:pointer;">Mark as Pending</button>
                </div>
                <div id="upsFeedback" style="display:none; margin-top:10px; text-align:center; font-weight:700; padding:10px; border-radius:10px;"></div>
                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top: 16px;">
                    <button type="button" id="closeUpdatePermitStatusBtn" style="padding:10px 14px; border:3px solid var(--color-border); background:var(--color-card); color:var(--color-text); border-radius:10px; font-weight:700; cursor:pointer;">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- QR scanning library (Instascan) -->
    <script src="https://rawgit.com/schmich/instascan-builds/master/instascan.min.js"></script>
    <script src="dahsboard.js"></script>
    <script>
        // Bulk Send Exam Permit - selection and action wiring
        document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('selectAllPermits');
            const floatingMenu = document.getElementById('floatingActionMenu');
            const selectedCountEl = document.getElementById('selectedCount');
            const bulkBtn = document.getElementById('bulkSendPermit');

            function rowCheckboxes() {
                return Array.from(document.querySelectorAll('.row-checkbox'));
            }

            function getCheckedUserIds() {
                const ids = [];
                rowCheckboxes().forEach(cb => {
                    if (cb.checked) {
                        const raw = cb.getAttribute('data-id') || '';
                        const m = raw.match(/USR-(\d+)/);
                        if (m) ids.push(parseInt(m[1], 10));
                    }
                });
                return ids;
            }

            function updateFloatingMenu() {
                const count = getCheckedUserIds().length;
                if (selectedCountEl) selectedCountEl.textContent = `${count} applicants selected`;
                if (count > 0) {
                    floatingMenu.style.display = 'block';
                } else {
                    floatingMenu.style.display = 'none';
                }
            }

            // Wire select all
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    const checked = !!this.checked;
                    rowCheckboxes().forEach(cb => {
                        cb.checked = checked;
                    });
                    updateFloatingMenu();
                });
            }

            // Wire individual checkboxes
            rowCheckboxes().forEach(cb => {
                cb.addEventListener('change', updateFloatingMenu);
            });

            // Close bulk floating menu
            const closeBulkMenu = document.getElementById('closeBulkMenu');
            if (closeBulkMenu) closeBulkMenu.addEventListener('click', function() {
                floatingMenu.style.display = 'none';
            });

            // Bulk send handler using confirmation modal
            if (bulkBtn) {
                bulkBtn.addEventListener('click', function() {
                    const ids = getCheckedUserIds();
                    if (!ids.length) return;
                    const modal = document.getElementById('bulkConfirmModal');
                    const countEl = document.getElementById('bulkConfirmCount');
                    const confirmBtn = document.getElementById('bulkConfirmBtn');
                    const cancelBtn = document.getElementById('bulkCancelBtn');
                    if (!modal || !countEl || !confirmBtn || !cancelBtn) return;
                    countEl.textContent = `${ids.length} applicant${ids.length > 1 ? 's' : ''} selected`;
                    modal.style.display = 'flex';
                    cancelBtn.onclick = function() {
                        modal.style.display = 'none';
                    };
                    confirmBtn.onclick = async function() {
                        modal.style.display = 'none';
                        if (typeof window.showLoader === 'function') window.showLoader();
                        try {
                            const params = new URLSearchParams();
                            ids.forEach(id => params.append('user_ids[]', String(id)));
                            const res = await fetch('bulk_send_exam_permits.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: params.toString(),
                            });
                            const data = await res.json();
                            if (!data || data.ok === false) {
                                throw new Error(data?.error || 'Bulk send failed');
                            }
                            const details = Array.isArray(data.details) ? data.details : [];
                            details.forEach(item => {
                                const uid = item.user_id;
                                const sendBtn = document.querySelector('.sendbtn[data-user-id="' + uid + '"]');
                                const row = sendBtn ? sendBtn.closest('tr') : document.querySelector('.row-checkbox[data-id="USR-' + uid + '"]')?.closest('tr');
                                if (row) {
                                    const statusEl = row.querySelector('.status');
                                    if (statusEl) statusEl.textContent = 'pending';
                                    const resendBtn = row.querySelector('.showResendbtn');
                                    if (item.mode === 'created') {
                                        if (sendBtn) sendBtn.style.display = 'none';
                                        if (resendBtn) resendBtn.style.display = 'inline-block';
                                    }
                                }
                            });
                            rowCheckboxes().forEach(cb => {
                                cb.checked = false;
                            });
                            if (selectAll) selectAll.checked = false;
                            updateFloatingMenu();
                            const msg = data.message || `Exam permits successfully generated and sent for ${data.success || 0} students. ${(data.failed || 0)} failed — check logs.`;
                            const feedbackModal = document.getElementById('sendFeedbackModal');
                            const feedbackMessage = document.getElementById('sendFeedbackMessage');
                            const closeFeedbackBtn = document.getElementById('closeFeedbackBtn');
                            if (feedbackModal && feedbackMessage && closeFeedbackBtn) {
                                feedbackMessage.textContent = msg;
                                feedbackMessage.style.color = '#2f855a';
                                feedbackModal.style.display = 'flex';
                                closeFeedbackBtn.onclick = function() {
                                    location.reload();
                                };
                            }
                        } catch (err) {
                            console.error(err);
                            const feedbackModal = document.getElementById('sendFeedbackModal');
                            const feedbackMessage = document.getElementById('sendFeedbackMessage');
                            const closeFeedbackBtn = document.getElementById('closeFeedbackBtn');
                            if (feedbackModal && feedbackMessage && closeFeedbackBtn) {
                                feedbackMessage.textContent = 'Error: ' + err.message;
                                feedbackMessage.style.color = '#c53030';
                                feedbackModal.style.display = 'flex';
                                closeFeedbackBtn.onclick = function() {
                                    feedbackModal.style.display = 'none';
                                };
                            }
                        } finally {
                            if (typeof window.hideLoader === 'function') window.hideLoader();
                        }
                    };
                });
            }
        });
    </script>

    </script>
    <script>
        // Validate Exam Permit Modal JS
        document.addEventListener('DOMContentLoaded', function() {
            const validateModal = document.getElementById('validateExamPermitModal');
            const openValidateBtn = document.querySelector('.search_button_container .button.export');
            const cancelValidateBtn = document.getElementById('cancelValidateBtn');
            const searchBtn = document.getElementById('validateSearchBtn');
            const scanSection = document.getElementById('scanSection');
            const manualSection = document.getElementById('manualSection');
            const switchToScanBtn = document.getElementById('switchToScan');
            const switchToManualBtn = document.getElementById('switchToManual');
            const startScanBtn = document.getElementById('startScanBtn');
            const stopScanBtn = document.getElementById('stopScanBtn');
            const manualInput = document.getElementById('manualApplicantNumber');
            const resultWrap = document.getElementById('validateResult');
            const notFoundWrap = document.getElementById('validateNotFound');
            const resultName = document.getElementById('resultName');
            const resultStatus = document.getElementById('resultStatus');
            const resultNumber = document.getElementById('resultNumber');
            const openStatusUpdateBtn = document.getElementById('openStatusUpdateBtn');
            const upsModal = document.getElementById('updatePermitStatusModal');
            const upsName = document.getElementById('upsName');
            const upsNumber = document.getElementById('upsNumber');
            const upsCurrent = document.getElementById('upsCurrent');
            const markUsedBtn = document.getElementById('markUsedBtn');
            const markPendingBtn = document.getElementById('markPendingBtn');
            const closeUpsHeaderBtn = document.getElementById('closeUpdatePermitStatusHeader');
            const closeUpsBtn = document.getElementById('closeUpdatePermitStatusBtn');
            const upsFeedback = document.getElementById('upsFeedback');
            const qrStatus = document.getElementById('qr-status');
            let qrScanner = null;
            let isScanning = false;

            function updateScanButtons() {
                if (!startScanBtn || !stopScanBtn) return;
                if (isScanning) {
                    startScanBtn.style.display = 'none';
                    stopScanBtn.style.display = 'inline-block';
                } else {
                    startScanBtn.style.display = 'inline-block';
                    stopScanBtn.style.display = 'none';
                }
            }

            function showModal() {
                if (typeof window.showLoader === 'function') window.showLoader();
                try {
                    validateModal.style.display = 'flex';
                    // Default to scan section (do not auto-start scanning)
                    switchToScan();
                } finally {
                    if (typeof window.hideLoader === 'function') window.hideLoader();
                }
            }

            function hideModal() {
                validateModal.style.display = 'none';
                stopScanner();
                clearResult();
                manualInput.value = '';
            }

            function clearResult() {
                resultWrap.style.display = 'none';
                if (notFoundWrap) notFoundWrap.style.display = 'none';
                resultName.textContent = '-';
                resultStatus.textContent = '-';
                resultStatus.className = 'badge';
                resultNumber.textContent = '-';
                if (upsModal) upsModal.style.display = 'none';
            }

            function badgeClassForStatus(s) {
                const val = (s || '').toLowerCase().trim();
                if (val === 'used') return 'badge badge--success';
                if (val === 'pending') return 'badge badge--warning';
                if (val === 'exam permit not sent') return 'badge badge--danger';
                return 'badge badge--secondary';
            }

            async function validateApplicantNumber(num) {
                clearResult();
                if (!num) {
                    // Show not found when QR content is empty/invalid
                    if (notFoundWrap) notFoundWrap.style.display = 'block';
                    resultWrap.style.display = 'none';
                    return;
                }
                try {
                    if (typeof window.showLoader === 'function') window.showLoader();
                    const params = new URLSearchParams();
                    params.append('applicant_number', num);
                    const res = await fetch('validate_exam_permit.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: params.toString(),
                    });
                    const data = await res.json();
                    if (!data || !data.ok) {
                        resultWrap.style.display = 'none';
                        if (notFoundWrap) notFoundWrap.style.display = 'block';
                        return;
                    }
                    resultWrap.style.display = 'block';
                    if (notFoundWrap) notFoundWrap.style.display = 'none';
                    resultName.textContent = data.applicant_name || 'N/A';
                    resultStatus.textContent = data.status || 'N/A';
                    resultStatus.className = badgeClassForStatus(data.status);
                    resultNumber.textContent = data.applicant_number || num;
                    // Auto-open status update modal after a successful lookup
                    openStatusUpdateModal({
                        name: data.applicant_name || 'N/A',
                        number: data.applicant_number || num,
                        status: data.status || 'pending'
                    });
                } catch (e) {
                    resultWrap.style.display = 'none';
                    if (notFoundWrap) notFoundWrap.style.display = 'block';
                } finally {
                    if (typeof window.hideLoader === 'function') window.hideLoader();
                }
            }

            function switchToScan() {
                scanSection.style.display = 'block';
                manualSection.style.display = 'none';
                switchToScanBtn.style.background = '#cbd5e0';
                switchToManualBtn.style.background = '#edf2f7';
                // Hide search button in scan mode (scan triggers automatically)
                if (searchBtn) searchBtn.style.display = 'none';
                // Ensure scanner stopped until user starts
                stopScanner();
                isScanning = false;
                updateScanButtons();
                if (qrStatus) qrStatus.textContent = 'Scanner idle. Click "Start Scan" to begin.';
            }

            function switchToManual() {
                scanSection.style.display = 'none';
                manualSection.style.display = 'block';
                switchToScanBtn.style.background = '#edf2f7';
                switchToManualBtn.style.background = '#cbd5e0';
                stopScanner();
                isScanning = false;
                updateScanButtons();
                // Show search button in manual mode
                if (searchBtn) searchBtn.style.display = 'inline-block';
            }

            function startScanner() {
                if (!window.Instascan || !window.Instascan.Scanner) {
                    if (qrStatus) qrStatus.textContent = 'QR library not loaded.';
                    return;
                }
                try {
                    const videoEl = document.getElementById('insta-preview');
                    qrScanner = new Instascan.Scanner({
                        video: videoEl,
                        mirror: false
                    });
                    if (qrStatus) qrStatus.textContent = 'Point camera at permit QR code.';
                    qrScanner.addListener('scan', function(content) {
                        // Stop after first successful scan
                        stopScanner();
                        validateApplicantNumber(String(content || '').trim());
                    });
                    Instascan.Camera.getCameras().then(function(cameras) {
                        if (cameras.length > 0) {
                            const backCam = cameras.find(c => String(c.name || '').toLowerCase().includes('back')) || cameras[0];
                            qrScanner.start(backCam).then(function() {
                                isScanning = true;
                                updateScanButtons();
                                if (qrStatus) qrStatus.textContent = 'Scanning…';
                            }).catch(function() {
                                if (qrStatus) qrStatus.textContent = 'Failed to start selected camera.';
                            });
                        } else {
                            if (qrStatus) qrStatus.textContent = 'No cameras found on this device.';
                        }
                    }).catch(function(e) {
                        if (qrStatus) qrStatus.textContent = 'Camera access denied or failed to initialize.';
                    });
                } catch (e) {
                    if (qrStatus) qrStatus.textContent = 'Failed to start camera.';
                }
            }

            function stopScanner() {
                if (qrScanner) {
                    try {
                        qrScanner.stop();
                    } catch (e) {}
                    qrScanner = null;
                }
                isScanning = false;
                updateScanButtons();
                if (qrStatus) qrStatus.textContent = 'Scanner stopped. Click "Start Scan" to begin again.';
            }

            function openStatusUpdateModal(info) {
                if (!upsModal) return;
                upsName.textContent = info.name || '-';
                upsNumber.textContent = info.number || '-';
                upsCurrent.textContent = info.status || '-';
                upsCurrent.className = badgeClassForStatus(info.status || 'pending');
                upsFeedback.style.display = 'none';
                upsFeedback.textContent = '';
                upsModal.style.display = 'flex';
            }

            function closeStatusUpdateModal() {
                if (upsModal) upsModal.style.display = 'none';
            }

            async function updatePermitStatus(newStatus) {
                const num = (upsNumber.textContent || '').trim();
                if (!num || !newStatus) return;
                try {
                    if (typeof window.showLoader === 'function') window.showLoader();
                    const params = new URLSearchParams();
                    params.append('applicant_number', num);
                    params.append('status', newStatus);
                    const res = await fetch('update_exam_permit_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: params.toString(),
                    });
                    const data = await res.json();
                    if (!data || !data.ok) {
                        upsFeedback.textContent = data?.error || 'Failed to update status';
                        upsFeedback.style.display = 'block';
                        upsFeedback.style.background = '#fff5f5';
                        upsFeedback.style.color = '#c53030';
                        return;
                    }
                    // Update UI badges
                    resultStatus.textContent = newStatus;
                    resultStatus.className = badgeClassForStatus(newStatus);
                    upsCurrent.textContent = newStatus;
                    upsCurrent.className = badgeClassForStatus(newStatus);
                    upsFeedback.textContent = 'Status updated successfully';
                    upsFeedback.style.display = 'block';
                    upsFeedback.style.background = '#f0fff4';
                    upsFeedback.style.color = '#2f855a';
                } catch (e) {
                    upsFeedback.textContent = 'Error updating status';
                    upsFeedback.style.display = 'block';
                    upsFeedback.style.background = '#fff5f5';
                    upsFeedback.style.color = '#c53030';
                } finally {
                    if (typeof window.hideLoader === 'function') window.hideLoader();
                }
            }

            // Wire events
            if (openValidateBtn) openValidateBtn.addEventListener('click', showModal);
            if (cancelValidateBtn) cancelValidateBtn.addEventListener('click', hideModal);
            if (switchToScanBtn) switchToScanBtn.addEventListener('click', function() {
                switchToScan();
            });
            if (switchToManualBtn) switchToManualBtn.addEventListener('click', switchToManual);
            if (startScanBtn) startScanBtn.addEventListener('click', function() {
                startScanner();
            });
            if (stopScanBtn) stopScanBtn.addEventListener('click', function() {
                stopScanner();
            });
            if (searchBtn) searchBtn.addEventListener('click', function() {
                validateApplicantNumber((manualInput.value || '').trim());
            });
            if (validateModal) validateModal.addEventListener('click', function(e) {
                if (e.target === validateModal) hideModal();
            });
            if (openStatusUpdateBtn) openStatusUpdateBtn.addEventListener('click', function() {
                openStatusUpdateModal({
                    name: (resultName.textContent || '-'),
                    number: (resultNumber.textContent || '-'),
                    status: (resultStatus.textContent || 'pending')
                });
            });
            if (markUsedBtn) markUsedBtn.addEventListener('click', function() {
                updatePermitStatus('used');
            });
            if (markPendingBtn) markPendingBtn.addEventListener('click', function() {
                updatePermitStatus('pending');
            });
            if (closeUpsHeaderBtn) closeUpsHeaderBtn.addEventListener('click', closeStatusUpdateModal);
            if (closeUpsBtn) closeUpsBtn.addEventListener('click', closeStatusUpdateModal);
            if (upsModal) upsModal.addEventListener('click', function(e) {
                if (e.target === upsModal) closeStatusUpdateModal();
            });
            // Initialize buttons state on load
            updateScanButtons();
        });
    </script>

    <!-- View Submission Modal (applicant_management design) -->
    <div id="viewSubmissionModal" style="display:none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background: linear-gradient(135deg, rgba(24, 165, 88, 0.10) 0%, rgba(19, 101, 21, 0.10) 100%); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(8px); padding: 20px;">
        <div style="background: var(--color-card); border-radius: 24px; max-width: 1200px; width: 95%; margin: 0 auto; max-height: calc(100vh - 40px); overflow: hidden; box-shadow: 0 25px 80px rgba(0,0,0,0.15), 0 10px 40px rgba(0,0,0,0.1); border: 1px solid var(--color-border); position: relative; display: flex; flex-direction: column;">
            <!-- Decorative Header Background -->
            <div style="height: 120px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); position: relative; overflow: hidden;">
                <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%; animation: float 6s ease-in-out infinite;"></div>
                <div style="position: absolute; bottom: -30px; left: -30px; width: 100px; height: 100px; background: rgba(255,255,255,0.08); border-radius: 50%; animation: float 8s ease-in-out infinite reverse;"></div>

                <!-- Close Button -->
                <button type="button" id="closeViewModalBtn" style="position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.2); border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; transition: all 0.3s ease; backdrop-filter: blur(10px);">&times;</button>
            </div>

            <!-- Modal Content -->
            <div id="viewSubmissionContent" style="flex: 1; overflow-y: auto; padding: 20px 40px 40px 40px;">
                <h3 style="margin: 0 0 12px 0; color: #1a202c; font-size: 1.6rem; font-weight: 800; letter-spacing: -0.025em; text-align:center;">Submission Details</h3>
                <p style="color: #718096; margin: 0 0 24px 0; line-height: 1.6; font-size: 1rem; text-align:center;">Review applicant information, schedule, answers, and uploaded files</p>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div><strong>Email:</strong> <span id="modalEmail">-</span></div>
                    <div><strong>Admission Cycle:</strong> <span id="modalCycle">-</span></div>
                    <div><strong>Submitted On:</strong> <span id="modalSubmitted">-</span></div>
                    <div><strong>Status:</strong> <span id="modalStatus" class="badge">-</span></div>
                </div>

                <div style="margin-top:16px;">
                    <h4 style="margin:8px 0;">Schedule</h4>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div><strong>Date:</strong> <span id="modalSchedDate">Not assigned</span></div>
                        <div><strong>Time:</strong> <span id="modalSchedTime">Not assigned</span></div>
                        <div><strong>Venue:</strong> <span id="modalSchedVenue">Not assigned</span></div>
                    </div>
                </div>

                <div style="margin-top:16px;">
                    <h4 style="margin:8px 0;">Answers</h4>
                    <ul id="modalAnswers" style="list-style: none; padding: 0; margin:0;"></ul>
                </div>
                <div style="margin-top:16px;">
                    <h4 style="margin:8px 0;">Files</h4>
                    <ul id="modalFiles" style="list-style: none; padding: 0; margin:0;"></ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Full-screen loader overlay (consistent with view_submission.php) -->
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
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('viewSubmissionModal');
            const closeBtn = document.getElementById('closeViewModalBtn');
            const emailEl = document.getElementById('modalEmail');
            const cycleEl = document.getElementById('modalCycle');
            const submittedEl = document.getElementById('modalSubmitted');
            const statusEl = document.getElementById('modalStatus');
            const schedDateEl = document.getElementById('modalSchedDate');
            const schedTimeEl = document.getElementById('modalSchedTime');
            const schedVenueEl = document.getElementById('modalSchedVenue');
            const answersEl = document.getElementById('modalAnswers');
            const filesEl = document.getElementById('modalFiles');

            function statusClass(name) {
                const s = (name || '').toLowerCase();
                if (s === 'exam permit sent') return 'badge badge--success';
                if (s === 'exam permit not sent') return 'badge badge--danger';
                if (s === 'accepted' || s === 'approved') return 'badge badge--success';
                if (s === 'pending' || s === 'waitlisted') return 'badge badge--warning';
                if (s === 'rejected' || s === 'failed') return 'badge badge--danger';
                if (s === 'in review' || s === 'examination') return 'badge badge--info';
                return 'badge badge--secondary';
            }

            // Global loader controls (consistent with view_submission.php)
            window.showLoader = function() {
                var loader = document.getElementById('loadingOverlay');
                if (loader) {
                    document.body.appendChild(loader);
                    loader.style.display = 'flex';
                }
            };
            window.hideLoader = function() {
                var loader = document.getElementById('loadingOverlay');
                if (loader) loader.style.display = 'none';
            };
            // Local aliases so existing calls in this block still work
            const showLoader = window.showLoader;
            const hideLoader = window.hideLoader;

            function openViewModal(submissionId) {
                if (!submissionId) return;
                // Reset placeholders, then show global loader
                emailEl.textContent = '-';
                cycleEl.textContent = '-';
                submittedEl.textContent = '-';
                statusEl.textContent = '-';
                statusEl.className = 'badge badge--secondary';
                schedDateEl.textContent = 'Not assigned';
                schedTimeEl.textContent = 'Not assigned';
                schedVenueEl.textContent = 'Not assigned';
                answersEl.innerHTML = '';
                filesEl.innerHTML = '';
                window.showLoader();

                fetch('get_submission_details.php?id=' + encodeURIComponent(submissionId))
                    .then(res => res.json())
                    .then(data => {
                        if (!data || data.ok === false) {
                            alert((data && data.error) ? data.error : 'Failed to load submission details');
                            return;
                        }
                        const m = data.main || {};
                        emailEl.textContent = m.email || '-';
                        cycleEl.textContent = m.cycle_name || '-';
                        submittedEl.textContent = m.submitted_at || '-';
                        statusEl.textContent = m.status || '-';
                        statusEl.className = statusClass(m.status);

                        // Schedule
                        const sched = data.schedule || null;
                        if (sched) {
                            if (sched.exam_date) schedDateEl.textContent = sched.exam_date;
                            else if (sched.start_date_and_time) schedDateEl.textContent = sched.start_date_and_time;
                            if (sched.exam_time) schedTimeEl.textContent = sched.exam_time;
                            const venue = sched.exam_venue || ((sched.floor || '') + ((sched.floor && sched.room) ? ' • ' : '') + (sched.room || ''));
                            if (venue && venue.trim() !== '') schedVenueEl.textContent = venue;
                        }

                        // Answers
                        answersEl.innerHTML = '';
                        const text = Array.isArray(data.text_data) ? data.text_data : [];
                        text.forEach(item => {
                            const li = document.createElement('li');
                            li.style.padding = '6px 0';
                            li.innerHTML = `<strong>${item.label || ''}:</strong> <span>${(item.value || '').toString()}</span>`;
                            answersEl.appendChild(li);
                        });

                        // Files
                        filesEl.innerHTML = '';
                        const files = Array.isArray(data.files) ? data.files : [];
                        files.forEach(f => {
                            const li = document.createElement('li');
                            li.style.padding = '6px 0';
                            const safeLabel = f.label || f.field_name || 'File';
                            const safeName = f.original_filename || 'download';
                            const safeHref = f.file_path || '#';
                            li.innerHTML = `<strong>${safeLabel}:</strong> <a href="${safeHref}" target="_blank" rel="noopener">${safeName}</a>`;
                            filesEl.appendChild(li);
                        });
                        // Show modal after content is prepared
                        modal.style.display = 'flex';
                    })
                    .catch(() => {
                        alert('Network error while loading submission details');
                    })
                    .finally(() => {
                        window.hideLoader();
                    });
            }

            function closeViewModal() {
                modal.style.display = 'none';
            }

            document.querySelectorAll('.showViewbtn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const sid = this.getAttribute('data-submission-id');
                    openViewModal(sid);
                });
            });
            if (closeBtn) closeBtn.addEventListener('click', closeViewModal);
            modal.addEventListener('click', function(e) {
                if (e.target === modal) closeViewModal();
            });
        });
    </script>
    <script>
        // Send Exam Permit Modal JS
        document.addEventListener('DOMContentLoaded', function() {
            const sendModal = document.getElementById('sendExamPermitModal');
            const closeSendBtn = document.getElementById('closeSendModalBtn');
            const form = document.getElementById('sendExamPermitForm');
            const userIdHidden = document.getElementById('sendUserIdHidden');
            const cancelBtn = document.getElementById('cancelSendExamPermit');
            // Feedback modal elements
            const feedbackModal = document.getElementById('sendFeedbackModal');
            const feedbackMessage = document.getElementById('sendFeedbackMessage');
            const closeFeedbackBtn = document.getElementById('closeFeedbackBtn');
            const closeFeedbackHeaderBtn = document.getElementById('closeFeedbackModalBtn');
            let feedbackIsSuccess = false;

            // Display-only elements + hidden fields for submit
            const applicantNameText = document.getElementById('sendApplicantNameText');
            const applicantNameHidden = document.getElementById('sendApplicantNameHidden');
            const examDateText = document.getElementById('sendExamDateText');
            const examDateHidden = document.getElementById('sendExamDateHidden');
            const examTimeText = document.getElementById('sendExamTimeText');
            const examTimeHidden = document.getElementById('sendExamTimeHidden');

            // Applicant number composition controls
            const applicantNumberText = document.getElementById('sendApplicantNumberText');
            const applicantNumberHidden = document.getElementById('sendApplicantNumberHidden');
            const applicantPrefixSelect = document.getElementById('sendApplicantPrefix');
            const applicantDigitsInput = document.getElementById('sendApplicantNumberLength');
            const applicantOrderRadios = function() {
                return Array.from(document.querySelectorAll('input[name="applicant_number_order"]'));
            };


            function composeSampleNumber(length, order) {
                const n = Math.max(1, Math.min(20, parseInt(length || '8', 10) || 8));
                if (n <= 1) return '1';
                if (order === 'last') {
                    // Last position starts: 000...001
                    return '0'.repeat(n - 1) + '1';
                }
                // First position starts: 1000...000
                return '1' + '0'.repeat(n - 1);
            }

            let nextPermitIdCache = null;

            function showFeedbackModal(message, isSuccess) {
                if (feedbackMessage) {
                    feedbackMessage.textContent = message || '';
                    feedbackMessage.style.color = isSuccess ? '#2f855a' : '#c53030';
                }
                feedbackIsSuccess = !!isSuccess;
                if (closeFeedbackHeaderBtn) {
                    closeFeedbackHeaderBtn.style.display = feedbackIsSuccess ? 'none' : 'inline-flex';
                }
                if (feedbackModal) feedbackModal.style.display = 'flex';
            }

            function closeFeedbackModal() {
                if (feedbackModal) feedbackModal.style.display = 'none';
            }

            async function fetchNextPermitId() {
                try {
                    const res = await fetch('get_next_permit_id.php?_t=' + Date.now(), {
                        cache: 'no-store'
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    const data = await res.json();
                    if (data && (typeof data.next_id !== 'undefined')) {
                        nextPermitIdCache = parseInt(data.next_id, 10);
                        if (isNaN(nextPermitIdCache) || nextPermitIdCache <= 0) nextPermitIdCache = 1;
                    } else {
                        nextPermitIdCache = 1;
                    }
                } catch (e) {
                    console.warn('Failed to fetch next permit id', e);
                    nextPermitIdCache = 1;
                }
                return nextPermitIdCache;
            }

            function updateApplicantNumberFromOptions() {
                const prefix = (applicantPrefixSelect && applicantPrefixSelect.value) ? applicantPrefixSelect.value.trim() : '';
                const digits = (applicantDigitsInput && applicantDigitsInput.value) ? parseInt(applicantDigitsInput.value, 10) : 8;
                const orderChecked = applicantOrderRadios().find(r => r.checked);
                const order = orderChecked ? orderChecked.value : 'last';
                const nextId = (nextPermitIdCache && nextPermitIdCache > 0) ? nextPermitIdCache : 1;

                const nextIdStr = String(nextId);
                let numberPart = '';
                if (order === 'first') {
                    numberPart = nextIdStr.length >= digits ? nextIdStr.slice(0, digits) : (nextIdStr + '0'.repeat(digits - nextIdStr.length));
                } else {
                    numberPart = nextIdStr.length >= digits ? nextIdStr.slice(-digits) : nextIdStr.padStart(digits, '0');
                }

                const composed = (prefix ? (prefix + '-') : '') + numberPart;
                if (applicantNumberText) applicantNumberText.textContent = composed;
                if (applicantNumberHidden) applicantNumberHidden.value = composed;

            }

            async function prefillSchedule(userId) {
                try {
                    // Clear existing values
                    if (examDateText) examDateText.textContent = '';
                    if (examTimeText) examTimeText.textContent = '';
                    if (examDateHidden) examDateHidden.value = '';
                    if (examTimeHidden) examTimeHidden.value = '';
                    const uidNum = parseInt(userId, 10);
                    if (!uidNum || uidNum <= 0) return;
                    const res = await fetch('get_exam_schedule.php?user_id=' + encodeURIComponent(uidNum) + '&_t=' + Date.now());
                    const data = await res.json();
                    if (data && data.ok && data.start_date_and_time) {
                        // Expecting format: YYYY-MM-DD HH:MM:SS
                        const parts = String(data.start_date_and_time).split(' ');
                        if (parts.length >= 2) {
                            const datePart = parts[0];
                            const timePart = parts[1];
                            const tparts = String(timePart).split(':');
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
                            if (examDateText) examDateText.textContent = datePart;
                            if (examTimeText) examTimeText.textContent = displayTime;
                            if (examDateHidden) examDateHidden.value = datePart;
                            if (examTimeHidden) examTimeHidden.value = hhmm;
                        }
                    } else {
                        // No schedule found; show placeholders so fields are not missing
                        if (examDateText) examDateText.textContent = 'Not set';
                        if (examTimeText) examTimeText.textContent = 'Not set';
                    }
                } catch (err) {
                    // Silently ignore and leave fields empty
                    console.warn('Failed to prefill schedule', err);
                    if (examDateText && !examDateText.textContent) examDateText.textContent = 'Not set';
                    if (examTimeText && !examTimeText.textContent) examTimeText.textContent = 'Not set';
                }
            }

            async function openSendModal(applicantName, userId) {
                const nameVal = applicantName || '';
                if (applicantNameText) applicantNameText.textContent = nameVal;
                if (applicantNameHidden) applicantNameHidden.value = nameVal;
                currentSendUserId = userId ? parseInt(userId, 10) : null;
                if (userIdHidden) userIdHidden.value = currentSendUserId || '';
                // Show global loader while we fetch both schedule and next permit id
                if (typeof window.showLoader === 'function') window.showLoader();
                try {
                    await Promise.all([
                        prefillSchedule(userId),
                        fetchNextPermitId()
                    ]);
                    // Initialize applicant number composition
                    updateApplicantNumberFromOptions();
                    sendModal.style.display = 'flex';
                } finally {
                    if (typeof window.hideLoader === 'function') window.hideLoader();
                }
            }

            function closeSendModal() {
                sendModal.style.display = 'none';
                if (form) form.reset();
                if (applicantNameText) applicantNameText.textContent = '';
                if (applicantNameHidden) applicantNameHidden.value = '';
                if (examDateText) examDateText.textContent = '';
                if (examTimeText) examTimeText.textContent = '';
                if (examDateHidden) examDateHidden.value = '';
                if (examTimeHidden) examTimeHidden.value = '';
                if (applicantNumberText) applicantNumberText.textContent = 'Will be generated';
                if (applicantNumberHidden) applicantNumberHidden.value = '';
            }

            document.querySelectorAll('.sendbtn').forEach(btn => {
                btn.addEventListener('click', function() {
                    let name = this.getAttribute('data-applicant-name') || '';
                    const uid = this.getAttribute('data-user-id') || '';
                    if (!name) {
                        const row = this.closest('tr');
                        const cell = row ? row.querySelector('[data-cell="applicant-name"]') : null;
                        const fallback = cell ? (cell.textContent || '').trim() : '';
                        if (fallback) name = fallback;
                    }
                    openSendModal(name, uid);
                });
            });
            // Resend button behavior: open confirmation modal then call backend
            function openResendModal(name, uid) {
                const modal = document.getElementById('resendConfirmModal');
                const nameEl = document.getElementById('resendConfirmName');
                const cancelBtn = document.getElementById('resendCancelBtn');
                const confirmBtn = document.getElementById('resendConfirmBtn');
                if (!modal || !nameEl || !cancelBtn || !confirmBtn) return;
                nameEl.textContent = name;
                modal.style.display = 'flex';
                cancelBtn.onclick = function() {
                    modal.style.display = 'none';
                };
                confirmBtn.onclick = async function() {
                    modal.style.display = 'none';
                    if (typeof window.showLoader === 'function') window.showLoader();
                    try {
                        const defaultAccent = document.getElementById('sendAccentColor')?.value || '#18a558';
                        const params = new URLSearchParams();
                        params.append('user_id', String(uid));
                        params.append('accent_color', defaultAccent);
                        params.append('applicant_name', name);
                        const res = await fetch('resend_application_permit.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: params.toString(),
                        });
                        const data = await res.json();
                        if (!data || !data.ok) {
                            throw new Error(data?.error || 'Failed to resend permit');
                        }
                        showFeedbackModal('Exam Permit resent successfully.', true);
                    } catch (err) {
                        console.error(err);
                        showFeedbackModal('Error resending permit: ' + err.message, false);
                    } finally {
                        if (typeof window.hideLoader === 'function') window.hideLoader();
                    }
                };
            }
            document.querySelectorAll('.showResendbtn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const name = this.getAttribute('data-applicant-name') || 'this applicant';
                    const uidStr = this.getAttribute('data-user-id') || '0';
                    const uid = parseInt(uidStr, 10) || 0;
                    if (!uid) return;
                    openResendModal(name, uid);
                });
            });
            // Wire applicant number option changes
            if (applicantPrefixSelect) applicantPrefixSelect.addEventListener('change', updateApplicantNumberFromOptions);
            if (applicantDigitsInput) applicantDigitsInput.addEventListener('input', updateApplicantNumberFromOptions);
            applicantOrderRadios().forEach(r => r.addEventListener('change', updateApplicantNumberFromOptions));
            if (closeSendBtn) closeSendBtn.addEventListener('click', closeSendModal);
            if (cancelBtn) cancelBtn.addEventListener('click', closeSendModal);
            sendModal.addEventListener('click', function(e) {
                if (e.target === sendModal) closeSendModal();
            });

            // Feedback modal wiring
            // Feedback modal behavior: success -> non-cancellable & OK reloads; failure -> cancellable & OK closes
            if (closeFeedbackBtn) closeFeedbackBtn.addEventListener('click', function() {
                if (feedbackIsSuccess) {
                    location.reload();
                } else {
                    closeFeedbackModal();
                }
            });
            if (closeFeedbackHeaderBtn) closeFeedbackHeaderBtn.addEventListener('click', closeFeedbackModal);
            const feedbackModalEl = document.getElementById('sendFeedbackModal');
            if (feedbackModalEl) feedbackModalEl.addEventListener('click', function(e) {
                if (e.target === feedbackModalEl && !feedbackIsSuccess) {
                    closeFeedbackModal();
                }
            });

            if (form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const start = document.getElementById('sendApplicationStart').value;
                    const end = document.getElementById('sendApplicationEnd').value;
                    if (start && end && new Date(start) > new Date(end)) {
                        showFeedbackModal('Application period start must be before end date.', false);
                        return;
                    }
                    const applicantNumber = document.getElementById('sendApplicantNumberHidden')?.value || '';
                    const userIdVal = currentSendUserId || parseInt(userIdHidden?.value || '0', 10) || 0;
                    const admissionOfficer = document.getElementById('sendAdmissionOfficer')?.value || '';
                    const applicantName = document.getElementById('sendApplicantNameHidden')?.value || '';
                    const dateOfExam = document.getElementById('sendExamDateHidden')?.value || '';
                    const examTime = document.getElementById('sendExamTimeHidden')?.value || '';
                    const accentColor = document.getElementById('sendAccentColor')?.value || '';
                    if (!userIdVal || !applicantNumber) {
                        showFeedbackModal('Missing user or applicant number.', false);
                        return;
                    }
                    if (typeof window.showLoader === 'function') window.showLoader();
                    try {
                        const params = new URLSearchParams();
                        params.append('user_id', String(userIdVal));
                        params.append('applicant_number', applicantNumber);
                        params.append('admission_officer', admissionOfficer);
                        params.append('applicant_name', applicantName);
                        params.append('date_of_exam', dateOfExam);
                        params.append('exam_time', examTime);
                        params.append('application_period_start', start || '');
                        params.append('application_period_end', end || '');
                        params.append('accent_color', accentColor);
                        const res = await fetch('create_application_permit.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: params.toString(),
                        });
                        const data = await res.json();
                        if (!data || !data.ok) {
                            throw new Error(data?.error || 'Failed to insert permit');
                        }
                        // Update UI status for the row if possible
                        const sendBtn = document.querySelector('.sendbtn[data-user-id="' + userIdVal + '"]');
                        const row = sendBtn ? sendBtn.closest('tr') : null;
                        const statusEl = row ? row.querySelector('.status') : null;
                        if (statusEl) statusEl.textContent = 'pending';
                        if (sendBtn) sendBtn.style.display = 'none';
                        const resendBtn = row ? row.querySelector('.showResendbtn') : null;
                        if (resendBtn) resendBtn.style.display = 'inline-block';
                        closeSendModal();
                        showFeedbackModal('Exam Permit sent successfully.', true);
                    } catch (err) {
                        console.error(err);
                        showFeedbackModal('Error sending permit: ' + err.message, false);
                    } finally {
                        if (typeof window.hideLoader === 'function') window.hideLoader();
                    }
                });
            }
        });
    </script>
</body>

</html>