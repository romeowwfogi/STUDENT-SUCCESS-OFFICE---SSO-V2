<?php
// Authentication middleware - protect this page
require_once 'middleware/auth.php';
include 'connection/db_connect.php';

// --- Cycle context ---
if (!isset($_GET['cycle_id'])) {
    die('Error: No admission cycle specified.');
}
$cycle_id = (int)$_GET['cycle_id'];

// Fetch cycle info and derive display name
$cycle_name = null;
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
if (!$cycle_name) {
    die('Error: Cycle not found.');
}

// --- ACTION HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create a new applicant type
    if (isset($_POST['action']) && $_POST['action'] === 'create_type') {
        $name = $conn->real_escape_string($_POST['name'] ?? '');
        $is_active = isset($_POST['is_active']) && $_POST['is_active'] === '1' ? 1 : 0;

        if (trim($name) === '') {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Type name is required.'];
            header('Location: manage_applicant_types.php?cycle_id=' . $cycle_id);
            exit;
        }

        // Schema updated: no is_archived column
        $stmt = $conn->prepare('INSERT INTO applicant_types (admission_cycle_id, name, is_active) VALUES (?, ?, ?)');
        $stmt->bind_param('isi', $cycle_id, $name, $is_active);
        if ($stmt->execute()) {
            $statusText = $is_active ? 'Open' : 'Closed';
            $_SESSION['message'] = ['type' => 'success', 'text' => "Created type '" . htmlspecialchars($name, ENT_QUOTES) . "' with status $statusText."];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error creating type: ' . $stmt->error];
        }
        $stmt->close();
        header('Location: manage_applicant_types.php?cycle_id=' . $cycle_id);
        exit;
    }

    // Update an existing applicant type
    if (isset($_POST['action']) && $_POST['action'] === 'update_type') {
        $type_id = (int)($_POST['type_id'] ?? 0);
        $name = $conn->real_escape_string($_POST['name'] ?? '');
        $is_active = isset($_POST['is_active']) && $_POST['is_active'] === '1' ? 1 : 0;

        if ($type_id <= 0 || trim($name) === '') {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Type ID and name are required.'];
            header('Location: manage_applicant_types.php?cycle_id=' . $cycle_id);
            exit;
        }

        $stmt = $conn->prepare('UPDATE applicant_types SET name = ?, is_active = ? WHERE id = ? AND admission_cycle_id = ?');
        $stmt->bind_param('siii', $name, $is_active, $type_id, $cycle_id);
        if ($stmt->execute()) {
            $statusText = $is_active ? 'Open' : 'Closed';
            $_SESSION['message'] = ['type' => 'success', 'text' => "Updated type '" . htmlspecialchars($name, ENT_QUOTES) . "' — status set to $statusText."];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error updating type: ' . $stmt->error];
        }
        $stmt->close();
        header('Location: manage_applicant_types.php?cycle_id=' . $cycle_id);
        exit;
    }
}

// GET actions: open/close, archive
if (isset($_GET['action'])) {
    $type_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($type_id > 0) {
        // Fetch type name for informative messaging
        $type_name = null;
        if ($stmtN = $conn->prepare('SELECT name FROM applicant_types WHERE id = ? AND admission_cycle_id = ?')) {
            $stmtN->bind_param('ii', $type_id, $cycle_id);
            if ($stmtN->execute()) {
                $resN = $stmtN->get_result();
                $rowN = $resN ? $resN->fetch_assoc() : null;
                $type_name = $rowN ? ($rowN['name'] ?? null) : null;
            }
            $stmtN->close();
        }
        if ($_GET['action'] === 'open') {
            $stmt = $conn->prepare('UPDATE applicant_types SET is_active = 1 WHERE id = ? AND admission_cycle_id = ?');
            $stmt->bind_param('ii', $type_id, $cycle_id);
            $ok = $stmt->execute();
            $stmt->close();
            if ($ok) {
                $_SESSION['message'] = ['type' => 'success', 'text' => "Successfully marked as open the '" . htmlspecialchars($type_name ?? (string)$type_id, ENT_QUOTES) . "'."];
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => "Failed to mark as open the '" . htmlspecialchars($type_name ?? (string)$type_id, ENT_QUOTES) . "'."];
            }
        } elseif ($_GET['action'] === 'close') {
            $stmt = $conn->prepare('UPDATE applicant_types SET is_active = 0 WHERE id = ? AND admission_cycle_id = ?');
            $stmt->bind_param('ii', $type_id, $cycle_id);
            $ok = $stmt->execute();
            $stmt->close();
            if ($ok) {
                $_SESSION['message'] = ['type' => 'success', 'text' => "Successfully marked as closed the '" . htmlspecialchars($type_name ?? (string)$type_id, ENT_QUOTES) . "'."];
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => "Failed to mark as closed the '" . htmlspecialchars($type_name ?? (string)$type_id, ENT_QUOTES) . "'."];
            }
        }
    }
    header('Location: manage_applicant_types.php?cycle_id=' . $cycle_id);
    exit;
}

// --- Fetch types for this cycle ---
$types = [];
if ($stmt_types = $conn->prepare('SELECT id, name, is_active FROM applicant_types WHERE admission_cycle_id = ? ORDER BY id DESC')) {
    $stmt_types->bind_param('i', $cycle_id);
    if ($stmt_types->execute()) {
        $res = $stmt_types->get_result();
        while ($row = $res->fetch_assoc()) {
            $types[] = $row;
        }
        $res->close();
    }
    $stmt_types->close();
}

// Build applicant counts per type id for this cycle
$type_counts = [];
if (!empty($types)) {
    $ids = array_map(function ($t) {
        return (int)$t['id'];
    }, $types);
    $in_list = implode(',', $ids);
    if ($in_list !== '') {
        $sql_counts = "SELECT applicant_type_id AS type_id, COUNT(*) AS cnt FROM submissions WHERE applicant_type_id IN ($in_list) GROUP BY applicant_type_id";
        if ($resC = $conn->query($sql_counts)) {
            while ($r = $resC->fetch_assoc()) {
                $type_counts[(int)$r['type_id']] = (int)$r['cnt'];
            }
            $resC->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Applicant Types</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        /* Spinner keyframes for loader overlay */
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

        /* Uniform Update-style button variant (match Admission Management) */
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

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .pill-open {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #10b981;
        }

        .pill-closed {
            background: #fff7ed;
            color: #9a3412;
            border: 1px solid #fb923c;
        }

        .pill-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .pill-open .pill-dot {
            background: #10b981;
        }

        .pill-closed .pill-dot {
            background: #fb923c;
        }
    </style>
</head>

<body>
    <!-- Full-screen loader overlay -->
    <div id="loadingOverlay" class="loading-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); display: none; justify-content: center; align-items: center; z-index: 3000; backdrop-filter: blur(4px);">
        <div class="loading-spinner" style="text-align:center;">
            <div class="spinner" style="width: 56px; height: 56px; border: 5px solid rgba(255,255,255,0.25); border-top-color: #18a558; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto;"></div>
            <div class="loading-text" style="color:#fff; margin-top:12px; font-weight:600; letter-spacing:0.02em;">Processing...</div>
        </div>
    </div>
    <script>
        // Global loader controls
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
        // Status modal helpers
        window.showStatusModal = function(title, message, kind) {
            const modal = document.getElementById('statusModal');
            if (!modal) return;
            const t = modal.querySelector('#statusModalTitle');
            const m = modal.querySelector('#statusModalMessage');
            const icon = modal.querySelector('#statusModalIcon');
            t.textContent = title || (kind === 'success' ? 'Success' : 'Error');
            m.innerHTML = message || '';
            // Swap icon color by kind
            if (kind === 'success') {
                icon.style.background = 'linear-gradient(135deg, #18a558 0%, #136515 100%)';
                icon.innerHTML = '<svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"></path></svg>';
            } else {
                icon.style.background = 'linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)';
                icon.innerHTML = '<svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>';
            }
            modal.style.display = 'flex';
        };
        window.closeStatusModal = function() {
            const modal = document.getElementById('statusModal');
            if (modal) modal.style.display = 'none';
        };
    </script>
    <?php
    // If there is a flash message in the session, surface it via status modal
    if (isset($_SESSION['message'])) {
        $msg = $_SESSION['message'];
        unset($_SESSION['message']);
        $kind = ($msg['type'] ?? '') === 'success' ? 'success' : 'error';
        $text = htmlspecialchars($msg['text'] ?? '', ENT_QUOTES);
        echo "<script>document.addEventListener('DOMContentLoaded',function(){window.showStatusModal('', '$text', '$kind');});</script>";
    }
    ?>
    <?php include 'includes/mobile_navbar.php'; ?>
    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <header class="header">
                <div class="header__left">
                    <h1 class="header__title">Manage Applicant Types</h1>
                    <p style="color:#718096; margin:4px 0 0 0; font-size:0.95rem;"><?php echo htmlspecialchars($cycle_name); ?></p>
                </div>
                <div class="header__actions">
                    <a href="application_management.php" class="btn btn--secondary" title="Back to Application Management">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to Admission List
                    </a>
                </div>
            </header>

            <section class="section active" id="manage_types_section" style="margin:0 20px;">
                <div class="table-container" style="margin-bottom: 20px;">
                    <div class="table-container__header" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                        <div>
                            <h2 class="table-container__title">Applicant Types</h2>
                            <p class="table-container__subtitle">Create, update, and open/close types for this academic year</p>
                        </div>
                        <div>
                            <button id="openNewTypeModalBtn" class="btn btn--primary" type="button" title="Create New Type">Create New Type</button>
                        </div>
                    </div>
                    <table class="table" id="typesTable">
                        <thead>
                            <tr>
                                <th class="sortable" data-column="ID" data-type="numeric" style="width:80px;">ID
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th class="sortable" data-column="Name" data-type="text" style="width:180px;">Name
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th class="sortable" data-column="Applicants" data-type="numeric" style="width:120px;">Applicants
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th class="sortable" data-column="Status" data-type="custom" style="width:160px;">Status
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th style="width:260px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($types)): ?>
                                <?php foreach ($types as $t): ?>
                                    <?php $isOpen = ((int)$t['is_active'] === 1); ?>
                                    <tr>
                                        <td data-cell="ID"><?php echo (int)$t['id']; ?></td>
                                        <td data-cell="Name"><?php echo htmlspecialchars($t['name']); ?></td>
                                        <td data-cell="Applicants"><?php echo (int)($type_counts[(int)$t['id']] ?? 0); ?></td>
                                        <td data-cell="Status">
                                            <?php if ($isOpen): ?>
                                                <span class="status-pill pill-open"><span class="pill-dot"></span> Open</span>
                                            <?php else: ?>
                                                <span class="status-pill pill-closed"><span class="pill-dot"></span> Closed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="table__actions">
                                                <a href="#" class="table__btn table__btn--update" title="Update Type"
                                                    onclick="openUpdateTypeModal(<?php echo (int)$t['id']; ?>, '<?php echo htmlspecialchars($t['name'], ENT_QUOTES); ?>', <?php echo $isOpen ? 1 : 0; ?>); return false;">Update</a>
                                                <a href="manage_form.php?applicant_type_id=<?php echo (int)$t['id']; ?>" class="table__btn table__btn--update" title="Manage Fields" onclick="showLoader()">Manage Fields</a>
                                                <?php if ($isOpen): ?>
                                                    <a href="manage_applicant_types.php?cycle_id=<?php echo $cycle_id; ?>&action=close&id=<?php echo (int)$t['id']; ?>" class="table__btn table__btn--update" title="Mark Closed">Mark Closed</a>
                                                <?php else: ?>
                                                    <a href="manage_applicant_types.php?cycle_id=<?php echo $cycle_id; ?>&action=open&id=<?php echo (int)$t['id']; ?>" class="table__btn table__btn--update" title="Mark Open">Mark Open</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align:center;">No applicant types found for this academic year.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <!-- Confirmation Modal (styled like in Admission Management) -->
    <div id="confirmationModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3002; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
        <div style="background: var(--color-card); border-radius: 20px; text-align: center; max-width: 420px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); color: var(--color-text);">
            <div style="padding: 32px 32px 16px 32px;">
                <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 16px; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 id="modalTitle" style="margin: 0 0 12px 0; color: #1a202c; font-size: 1.5rem; font-weight: 700; letter-spacing: -0.025em;">Confirm Action</h3>
                <p id="modalMessage" style="color: #718096; margin: 0; line-height: 1.6; font-size: 0.95rem;">Are you sure you want to proceed?</p>
            </div>
            <div style="padding: 16px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
                <button id="modalCancelBtn" style="flex: 1; padding: 12px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
                <button id="modalConfirmBtn" style="flex: 1; padding: 12px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4);">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Status Message Modal -->
    <div id="statusModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3002; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div role="dialog" aria-modal="true" aria-labelledby="statusModalTitle" style="background: var(--color-card); border-radius: 20px; text-align: center; max-width: 420px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); color: var(--color-text);">
            <div style="padding: 32px 32px 16px 32px;">
                <div id="statusModalIcon" style="width: 56px; height: 56px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 16px; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 id="statusModalTitle" style="margin: 0 0 12px 0; color: #1a202c; font-size: 1.5rem; font-weight: 700; letter-spacing: -0.025em;">Action Status</h3>
                <p id="statusModalMessage" style="color: #718096; margin: 0; line-height: 1.6; font-size: 0.95rem;">Done.</p>
            </div>
            <div style="padding: 16px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
                <button id="statusModalCloseBtn" style="flex: 1; padding: 12px 24px; border: none; background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; border: 2px solid var(--color-border);">Close</button>
            </div>
        </div>
    </div>

    <!-- New Applicant Type Modal (styled like Create New Admission) -->
    <div id="newTypeModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color: rgba(0,0,0,0.4); z-index:1001; align-items:center; justify-content:center; backdrop-filter: blur(4px);">
        <div role="dialog" aria-modal="true" aria-labelledby="newTypeModalTitle" style="background: var(--color-card); border-radius: 20px; max-width: 480px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); position: relative; color: var(--color-text);">
            <!-- Close Button -->
            <button type="button" id="closeNewTypeModalBtn" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px; transition: all 0.2s ease; z-index: 10;">&times;</button>

            <!-- Modal Header -->
            <div style="padding: 40px 32px 24px 32px; text-align: center;">
                <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 24px auto; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                </div>
                <h3 id="newTypeModalTitle" style="margin:0 0 8px 0; color:#1a202c; font-size:1.6rem; font-weight:700; letter-spacing:-0.025em;">Create New Applicant Type</h3>
                <p style="color:#718096; margin:0; line-height:1.5; font-size:0.95rem;">Add a type for this admission cycle</p>
            </div>

            <!-- Modal Body -->
            <div style="padding: 0 32px 24px 32px;">
                <form method="POST" action="manage_applicant_types.php?cycle_id=<?php echo $cycle_id; ?>">
                    <input type="hidden" name="action" value="create_type">
                    <div style="margin-bottom: 16px;">
                        <label for="newTypeName" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Type Name</label>
                        <input type="text" id="newTypeName" name="name" placeholder="e.g., Transferee" required style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                    </div>
                    <div style="margin-top: 8px;">
                        <label style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Status</label>
                        <div style="display:flex; gap:14px; align-items:center;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="radio" name="is_active" value="1" style="width:18px; height:18px; accent-color:#18a558;">
                                <span>Open</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="radio" name="is_active" value="0" checked style="width:18px; height:18px; accent-color:#dc3545;">
                                <span>Closed</span>
                            </label>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Modal Footer -->
            <div style="padding: 20px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
                <button type="button" id="cancelNewTypeBtn" style="flex: 1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
                <button type="button" id="confirmNewTypeBtn" style="flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4);">Create</button>
            </div>
        </div>
    </div>

    <!-- Update Type Modal -->
    <div id="updateTypeModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color: rgba(0,0,0,0.4); z-index:1001; align-items:center; justify-content:center; backdrop-filter: blur(4px);">
        <div role="dialog" aria-modal="true" aria-labelledby="updateTypeModalTitle" style="background: var(--color-card); border-radius: 20px; max-width: 480px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); position: relative; color: var(--color-text);">
            <!-- Close Button -->
            <button type="button" id="closeUpdateTypeModalBtn" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px; transition: all 0.2s ease; z-index: 10;">&times;</button>

            <!-- Modal Header (match Create New Type design) -->
            <div style="padding: 40px 32px 24px 32px; text-align: center;">
                <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 24px auto; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 20h9" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4 12.5-12.5z" />
                    </svg>
                </div>
                <h3 id="updateTypeModalTitle" style="margin:0 0 8px 0; color:#1a202c; font-size:1.6rem; font-weight:700; letter-spacing:-0.025em;">Update Applicant Type</h3>
                <p style="color:#718096; margin:0; line-height:1.5; font-size:0.95rem;">Edit this applicant type</p>
            </div>

            <!-- Modal Body -->
            <div style="padding: 0 32px 24px 32px;">
                <form method="POST" action="manage_applicant_types.php?cycle_id=<?php echo $cycle_id; ?>">
                    <input type="hidden" name="action" value="update_type">
                    <input type="hidden" id="updateTypeId" name="type_id" value="">
                    <div style="margin-bottom: 16px;">
                        <label for="updateTypeName" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Type Name</label>
                        <input type="text" id="updateTypeName" name="name" required style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                    </div>
                    <div style="margin-top: 8px;">
                        <label style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Status</label>
                        <div style="display:flex; gap:14px; align-items:center;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="radio" name="is_active" id="updateStatusOpen" value="1" style="width:18px; height:18px; accent-color:#18a558;">
                                <span>Open</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="radio" name="is_active" id="updateStatusClosed" value="0" style="width:18px; height:18px; accent-color:#dc3545;">
                                <span>Closed</span>
                            </label>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div style="padding: 20px 0 0 0; display: flex; gap: 12px; justify-content: center;">
                        <button type="button" id="cancelUpdateTypeBtn" style="flex: 1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
                        <button type="button" id="confirmUpdateTypeBtn" style="flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4);">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Confirmation modal helpers (consistent with Application Management)
        const confirmationModal = document.getElementById('confirmationModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');
        const modalConfirmBtn = document.getElementById('modalConfirmBtn');
        const modalCancelBtn = document.getElementById('modalCancelBtn');
        let currentAction = '';
        let currentActionUrl = '';

        function escapeHtml(str) {
            const s = String(str ?? '');
            return s
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function showConfirmationModal(title, message, actionKey) {
            if (!confirmationModal) return;
            modalTitle.textContent = title;
            modalMessage.innerHTML = message;
            currentAction = actionKey || '';
            currentActionUrl = '';
            confirmationModal.style.display = 'flex';
        }

        function showConfirmUrl(title, htmlMessage, url) {
            if (!confirmationModal) return;
            modalTitle.textContent = title;
            modalMessage.innerHTML = htmlMessage;
            currentAction = '';
            currentActionUrl = url || '';
            confirmationModal.style.display = 'flex';
        }

        function closeConfirmationModal() {
            if (confirmationModal) confirmationModal.style.display = 'none';
        }

        const newTypeModal = document.getElementById('newTypeModal');
        const openNewTypeModalBtn = document.getElementById('openNewTypeModalBtn');
        const closeNewTypeModalBtn = document.getElementById('closeNewTypeModalBtn');
        const cancelNewTypeBtn = document.getElementById('cancelNewTypeBtn');
        const confirmNewTypeBtn = document.getElementById('confirmNewTypeBtn');

        const updateTypeModal = document.getElementById('updateTypeModal');
        const closeUpdateTypeModalBtn = document.getElementById('closeUpdateTypeModalBtn');
        const cancelUpdateTypeBtn = document.getElementById('cancelUpdateTypeBtn');
        const confirmUpdateTypeBtn = document.getElementById('confirmUpdateTypeBtn');
        const updateTypeIdInput = document.getElementById('updateTypeId');
        const updateTypeNameInput = document.getElementById('updateTypeName');
        const updateStatusOpen = document.getElementById('updateStatusOpen');
        const updateStatusClosed = document.getElementById('updateStatusClosed');

        function openUpdateTypeModal(id, name, isActive) {
            updateTypeIdInput.value = String(id);
            updateTypeNameInput.value = name;
            if (parseInt(isActive, 10) === 1) {
                updateStatusOpen.checked = true;
                updateStatusClosed.checked = false;
            } else {
                updateStatusOpen.checked = false;
                updateStatusClosed.checked = true;
            }
            updateTypeModal.style.display = 'flex';
        }

        openNewTypeModalBtn?.addEventListener('click', () => {
            newTypeModal.style.display = 'flex';
            // Focus the name input for quick entry
            document.getElementById('newTypeName')?.focus();
        });
        closeNewTypeModalBtn?.addEventListener('click', () => {
            newTypeModal.style.display = 'none';
        });
        cancelNewTypeBtn?.addEventListener('click', () => {
            newTypeModal.style.display = 'none';
        });

        // Close when clicking outside card
        newTypeModal.addEventListener('click', (e) => {
            if (e.target === newTypeModal) {
                newTypeModal.style.display = 'none';
            }
        });

        // Show confirmation on Create
        confirmNewTypeBtn?.addEventListener('click', () => {
            const form = document.querySelector('#newTypeModal form');
            if (!form) return;
            const name = (form.querySelector('#newTypeName')?.value || '').trim();
            const isActiveVal = form.querySelector('input[name="is_active"]:checked')?.value || '0';
            const statusText = isActiveVal === '1' ? 'Open' : 'Closed';
            if (!name) {
                alert('Type name is required.');
                form.querySelector('#newTypeName')?.focus();
                return;
            }
            const html = `
                <div style="margin-top:6px; text-align:center;">
                    <div><strong>Type Name:</strong> ${name.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</div>
                    <div><strong>Status:</strong> ${statusText}</div>
                </div>
            `;
            showConfirmationModal('Confirm New Applicant Type', html, 'create_new_type');
        });

        modalCancelBtn?.addEventListener('click', () => {
            closeConfirmationModal();
        });
        confirmationModal?.addEventListener('click', (e) => {
            if (e.target === confirmationModal) closeConfirmationModal();
        });
        // Close status modal on overlay click or button
        document.getElementById('statusModal')?.addEventListener('click', (e) => {
            if (e.target === document.getElementById('statusModal')) {
                window.closeStatusModal();
            }
        });
        document.getElementById('statusModalCloseBtn')?.addEventListener('click', () => window.closeStatusModal());

        // Close Update modal on overlay click
        updateTypeModal?.addEventListener('click', (e) => {
            if (e.target === updateTypeModal) {
                updateTypeModal.style.display = 'none';
            }
        });
        modalConfirmBtn?.addEventListener('click', () => {
            if (currentAction === 'create_new_type') {
                const form = document.querySelector('#newTypeModal form');
                if (!form) return;
                try {
                    window.showLoader && window.showLoader();
                } catch (e) {}
                // Close modals
                newTypeModal.style.display = 'none';
                closeConfirmationModal();
                // Defer submit to allow loader to render
                setTimeout(() => {
                    form.submit();
                }, 120);
            } else if (currentAction === 'update_type') {
                const form = document.querySelector('#updateTypeModal form');
                if (!form) return;
                try {
                    window.showLoader && window.showLoader();
                } catch (e) {}
                // Close modals
                updateTypeModal.style.display = 'none';
                closeConfirmationModal();
                // Defer submit to allow loader to render
                setTimeout(() => {
                    form.submit();
                }, 120);
            } else if (currentActionUrl) {
                try {
                    window.showLoader && window.showLoader();
                } catch (e) {}
                closeConfirmationModal();
                // Navigate to perform server-side open/close
                window.location.href = currentActionUrl;
            }
        });

        closeUpdateTypeModalBtn?.addEventListener('click', () => {
            updateTypeModal.style.display = 'none';
        });
        cancelUpdateTypeBtn?.addEventListener('click', () => {
            updateTypeModal.style.display = 'none';
        });

        // Show confirmation for Update
        confirmUpdateTypeBtn?.addEventListener('click', () => {
            const form = document.querySelector('#updateTypeModal form');
            if (!form) return;
            const name = (updateTypeNameInput?.value || '').trim();
            const isActiveVal = document.querySelector('#updateTypeModal input[name="is_active"]:checked')?.value || '0';
            const statusText = isActiveVal === '1' ? 'Open' : 'Closed';
            if (!name) {
                alert('Type name is required.');
                updateTypeNameInput?.focus();
                return;
            }
            const html = `
                <div style="margin-top:6px; text-align:center;">
                    <div><strong>Type Name:</strong> ${name.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</div>
                    <div><strong>Status:</strong> ${statusText}</div>
                </div>
            `;
            showConfirmationModal('Confirm Update Applicant Type', html, 'update_type');
        });

        window.openUpdateTypeModal = openUpdateTypeModal;

        // Table Sorting (similar to Admission Management)
        class TableSorter {
            constructor(tableSelector) {
                this.table = document.querySelector(tableSelector);
                this.tbody = this.table?.querySelector('tbody');
                this.headers = this.table?.querySelectorAll('th.sortable');
                this.currentSort = {
                    column: null,
                    direction: 'asc'
                };
                this.rows = [];
                this.init();
            }
            init() {
                if (!this.table || !this.tbody || !this.headers) return;
                this.rows = Array.from(this.tbody.querySelectorAll('tr'));
                this.headers.forEach(header => {
                    header.addEventListener('click', () => {
                        const column = header.dataset.column;
                        const type = header.dataset.type || 'text';
                        this.sortBy(column, null, type);
                    });
                });
            }
            getColumnIndex(column) {
                const headerArray = Array.from(this.headers);
                const header = headerArray.find(h => h.dataset.column === column);
                return header ? Array.from(header.parentNode.children).indexOf(header) + 1 : 1;
            }
            getCellValue(row, column) {
                const idx = this.getColumnIndex(column);
                const cell = row.querySelector(`[data-cell="${column}"]`) || row.querySelector(`td:nth-child(${idx})`);
                return cell ? cell.textContent.trim() : '';
            }
            compareText(a, b) {
                return a.toLowerCase().localeCompare(b.toLowerCase());
            }
            compareNumeric(a, b) {
                const na = parseFloat(a.replace(/[^\d.-]/g, '')) || 0;
                const nb = parseFloat(b.replace(/[^\d.-]/g, '')) || 0;
                return na - nb;
            }
            compareCustom(a, b, column) {
                // Status: Open should come before Closed when ascending
                const map = {
                    'open': 0,
                    'closed': 1
                };
                const av = map[a.toLowerCase()] ?? 99;
                const bv = map[b.toLowerCase()] ?? 99;
                return av - bv;
            }
            updateIndicators(activeColumn, direction) {
                this.headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
                const active = Array.from(this.headers).find(h => h.dataset.column === activeColumn);
                if (active) active.classList.add(`sort-${direction}`);
            }
            sortBy(column, direction = null, type = 'text') {
                if (!column) return;
                if (direction === null) {
                    direction = (this.currentSort.column === column && this.currentSort.direction === 'asc') ? 'desc' : 'asc';
                }
                this.currentSort = {
                    column,
                    direction
                };
                this.rows.sort((a, b) => {
                    const va = this.getCellValue(a, column);
                    const vb = this.getCellValue(b, column);
                    let cmp = 0;
                    if (type === 'numeric') cmp = this.compareNumeric(va, vb);
                    else if (type === 'custom') cmp = this.compareCustom(va, vb, column);
                    else cmp = this.compareText(va, vb);
                    return direction === 'asc' ? cmp : -cmp;
                });
                this.rows.forEach(r => this.tbody.appendChild(r));
                this.updateIndicators(column, direction);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            new TableSorter('#typesTable');

            const table = document.getElementById('typesTable');
            table?.addEventListener('click', function(e) {
                const link = e.target.closest('a');
                if (!link) return;
                const href = link.getAttribute('href') || '';
                if (!href.includes('action=')) return;
                try {
                    const url = new URL(href, window.location.href);
                    const action = url.searchParams.get('action');
                    if (action === 'open' || action === 'close') {
                        e.preventDefault();
                        const row = link.closest('tr');
                        const typeName = row?.querySelector('[data-cell="Name"]')?.textContent?.trim() || 'this type';
                        const nextStatus = action === 'open' ? 'Open' : 'Closed';
                        const title = action === 'open' ? 'Mark Type as Open?' : 'Mark Type as Closed?';
                        const msg = `
                            <div style="margin-top:6px; text-align:center;">
                                <div><strong>Type:</strong> ${escapeHtml(typeName)}</div>
                                <div><strong>New Status:</strong> ${nextStatus}</div>
                            </div>
                        `;
                        showConfirmUrl(title, msg, href);
                    }
                } catch (err) {
                    // If URL parsing fails, do nothing and allow default
                }
            });
        });
    </script>
</body>

</html>