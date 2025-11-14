<?php
require_once 'middleware/auth.php';
require_once 'connection/db_connect.php';
date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_faq_status') {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $next = isset($_POST['next_status']) ? trim($_POST['next_status']) : '';
    if ($id <= 0 || $next === '') {
        echo json_encode(['ok' => false, 'error' => 'Missing parameters']);
        exit;
    }
    if ($stmt = $conn->prepare('UPDATE faqs SET status = ? WHERE id = ?')) {
        $stmt->bind_param('si', $next, $id);
        $ok = $stmt->execute();
        $stmt->close();
    } else {
        $ok = false;
    }
    if (!$ok) {
        echo json_encode(['ok' => false, 'error' => 'Update failed']);
        exit;
    }
    echo json_encode(['ok' => true, 'next_status' => $next]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_faq') {
    header('Content-Type: application/json');
    $question = trim($_POST['question'] ?? '');
    $answer = trim($_POST['answer'] ?? '');
    if ($question === '' || $answer === '') {
        echo json_encode(['ok' => false, 'error' => 'Please fill required fields']);
        exit;
    }
    $sql = 'INSERT INTO faqs (question, answer, status) VALUES (?, ?, "active")';
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('ss', $question, $answer);
        $ok = $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();
        if ($ok) {
            echo json_encode(['ok' => true, 'id' => $newId]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Create failed']);
        }
    } else {
        echo json_encode(['ok' => false, 'error' => 'Prepare failed']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_faq') {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $question = trim($_POST['question'] ?? '');
    $answer = trim($_POST['answer'] ?? '');
    if ($id <= 0 || $question === '' || $answer === '') {
        echo json_encode(['ok' => false, 'error' => 'Missing parameters']);
        exit;
    }
    $sql = 'UPDATE faqs SET question = ?, answer = ? WHERE id = ?';
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('ssi', $question, $answer, $id);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Update failed']);
        }
    } else {
        echo json_encode(['ok' => false, 'error' => 'Prepare failed']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Success Office - FAQs Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .table th.sortable { position: relative; cursor: pointer; user-select: none; padding-right: 26px; }
        .table th.sortable:hover { background-color: #f8fafc; }
        .table th .sort-icon { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); opacity: 0.6; transition: transform 0.2s ease, opacity 0.2s ease; width: 16px; height: 16px; }
        .table th.sorted-asc .sort-icon, .table th.sorted-desc .sort-icon { opacity: 1; }
        .table th.sorted-desc .sort-icon { transform: translateY(-50%) rotate(180deg); }

        .loading-overlay { position: fixed; inset: 0; background: rgba(0, 0, 0, 0.35); display: none; justify-content: center; align-items: center; z-index: 6000; backdrop-filter: blur(2px); }
        .loading-overlay .spinner { width: 48px; height: 48px; border: 4px solid rgba(255, 255, 255, 0.5); border-top-color: #18a558; border-radius: 50%; animation: spin 0.9s linear infinite; }
        .loading-overlay .label { margin-top: 12px; color: #fff; font-weight: 600; letter-spacing: 0.2px; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .pagination { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border: 2px solid var(--color-border); border-radius: 12px; background: var(--color-card); margin-top: 12px; }
        .pagination__label { font-size: 0.9rem; color: #4a5568; font-weight: 500; }
        .pagination__select { padding: 8px 12px; border: 2px solid var(--color-border); border-radius: 8px; background: var(--color-card); color: var(--color-text); font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; }
        .pagination__select:hover { border-color: #cbd5e0; }
        .pagination__select:focus { border-color: #18a558; box-shadow: 0 0 0 3px rgba(24, 165, 88, 0.15); }
        .pagination__center { flex: 1; text-align: center; }
        .pagination__info { font-size: 0.9rem; color: #4a5568; font-weight: 500; }
        .pagination__right { display: flex; gap: 8px; }
        .pagination__bttns { padding: 10px 16px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; min-width: 80px; }
        .pagination__bttns:hover:not(.pagination__button--disabled) { background: #f7fafc; border-color: #cbd5e0; transform: translateY(-1px); }
        .pagination__button--disabled { opacity: 0.5; cursor: not-allowed; background: var(--color-hover) !important; border-color: var(--color-border) !important; }

        .status-pill { display: inline-flex; align-items: center; gap: 8px; padding: 6px 10px; border-radius: 999px; font-weight: 600; font-size: 0.85rem; letter-spacing: .01em; }
        .pill-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
        .pill-active { background: #ecfdf5; color: #065f46; border: 1px solid #10b981; }
        .pill-active .pill-dot { background: #10b981; }
        .pill-negative { background: #fee2e2; color: #7f1d1d; border: 1px solid #ef4444; }
        .pill-negative .pill-dot { background: #ef4444; }

        .button.btn-status { border: 1.5px solid rgba(16, 185, 129, 0.35); background: var(--color-card); color: var(--color-primary); font-weight: 100; font-size: 0.85rem; padding: 8px 14px !important; transition: all 0.2s ease; }
        .button.btn-status:hover { background-color: var(--color-primary); color: var(--color-white); transform: translateY(-1px); }
    </style>
</head>

<body>
    <?php include 'includes/mobile_navbar.php'; ?>
    <?php include 'connection/db_connect.php'; ?>

    <div id="loadingOverlay" class="loading-overlay">
        <div style="text-align:center;">
            <div class="spinner" style="margin:0 auto;"></div>
            <div class="label">Processing...</div>
        </div>
    </div>
    <script>
        window.showLoader = function() { var l = document.getElementById('loadingOverlay'); if (l) { l.style.display = 'flex'; } };
        window.hideLoader = function() { var l = document.getElementById('loadingOverlay'); if (l) { l.style.display = 'none'; } };
    </script>

    <?php
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $faqs = [];
    if ($res = $conn->query('SELECT id, question, answer, status FROM faqs ORDER BY id DESC')) {
        while ($row = $res->fetch_assoc()) { $faqs[] = $row; }
    }
    ?>

    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="header">
                <div class="header__left">
                    <h1>FAQs Management</h1>
                    <p class="header__subtitle">Frequently Asked Questions</p>
                </div>
            </header>

            <section class="section active" style="margin: 0 20px;">
                <div class="table-container">
                    <div class="table-container__header">
                        <h2 class="table-container__title">FAQs</h2>
                        <p class="table-container__subtitle">Manage FAQs content</p>
                    </div>

                    <div class="filtration_container">
                        <form method="GET" action="" style="display:flex; width:100%; justify-content:space-between; gap:12px; align-items:center;">
                            <div class="search_input_container" style="flex:1;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search-icon lucide-search">
                                    <path d="m21 21-4.34-4.34" />
                                    <circle cx="11" cy="11" r="8" />
                                </svg>
                                <input type="text" id="searchQuery" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search FAQs..." aria-label="Search FAQs">
                            </div>
                            <div class="search_button_container" style="flex:0 0 auto; display:flex; gap:8px; align-items:center;">
                                <button class="button export" id="addFaqBtn" type="button">Add FAQ</button>
                            </div>
                        </form>
                    </div>

                    <div class="table-wrapper" style="overflow-x:auto;">
                        <table class="table" id="faqsTable">
                            <thead>
                                <tr>
                                    <th class="sortable" data-column="id">ID
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                            <path d="m3 16 4 4 4-4" />
                                            <path d="M7 20V4" />
                                            <path d="M20 8h-5" />
                                            <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                            <path d="M15 14h5l-5 6h5" />
                                        </svg>
                                    </th>
                                    <th class="sortable" data-column="question">Question
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                            <path d="m3 16 4 4 4-4" />
                                            <path d="M7 20V4" />
                                            <path d="M20 8h-5" />
                                            <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                            <path d="M15 14h5l-5 6h5" />
                                        </svg>
                                    </th>
                                    <th class="sortable" data-column="answer">Answer
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                            <path d="m3 16 4 4 4-4" />
                                            <path d="M7 20V4" />
                                            <path d="M20 8h-5" />
                                            <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                            <path d="M15 14h5l-5 6h5" />
                                        </svg>
                                    </th>
                                    <th class="sortable" data-column="status">Status
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                            <path d="m3 16 4 4 4-4" />
                                            <path d="M7 20V4" />
                                            <path d="M20 8h-5" />
                                            <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                            <path d="M15 14h5l-5 6h5" />
                                        </svg>
                                    </th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($faqs)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center;">No FAQs found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($faqs as $f): ?>
                                        <?php
                                        $id = (int)($f['id'] ?? 0);
                                        $question = trim((string)($f['question'] ?? ''));
                                        $answer = trim((string)($f['answer'] ?? ''));
                                        $st = strtolower(trim((string)($f['status'] ?? '')));
                                        $pillClass = ($st === 'active') ? 'pill-active' : 'pill-negative';
                                        $label = ($st === 'active') ? 'Archive' : 'Activate';
                                        $next = ($st === 'active') ? 'inactive' : 'active';
                                        $disp = $st !== '' ? ucfirst($st) : 'N/A';
                                        ?>
                                        <tr>
                                            <td><?php echo $id; ?></td>
                                            <td data-key="question"><?php echo htmlspecialchars($question !== '' ? $question : 'N/A'); ?></td>
                                            <td data-key="answer"><?php echo htmlspecialchars($answer !== '' ? $answer : 'N/A'); ?></td>
                                            <td data-key="status">
                                                <span class="status-pill <?php echo $pillClass; ?>"><span class="pill-dot"></span> <?php echo htmlspecialchars($disp); ?></span>
                                            </td>
                                            <td>
                                                <div style="display:flex; gap:8px; align-items:center;">
                                                    <button type="button" class="button btn-status" data-id="<?php echo $id; ?>" data-next="<?php echo htmlspecialchars($next); ?>" data-current="<?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars($label); ?></button>
                                                    <button type="button" class="button btn-status btn-update"
                                                        data-id="<?php echo $id; ?>"
                                                        data-question="<?php echo htmlspecialchars($question); ?>"
                                                        data-answer="<?php echo htmlspecialchars($answer); ?>">Update</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="pagination">
                        <div class="pagination__left">
                            <span class="pagination__label">Rows per page:</span>
                            <select class="pagination__select" id="rowsPerPageSelect">
                                <option value="10">10</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                                <option value="all">All</option>
                            </select>
                        </div>
                        <div class="pagination__center">
                            <span class="pagination__info" id="paginationInfo">Showing 1-10 • Page 1</span>
                        </div>
                        <div class="pagination__right">
                            <button class="pagination__bttns pagination__button--disabled" id="prevPage" disabled>Prev</button>
                            <button class="pagination__bttns" id="nextPage">Next</button>
                        </div>
                    </div>

                    <div id="newFaqModal" style="display:none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3500; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
                        <div style="background: var(--color-card); border-radius: 20px; text-align: center; max-width: 520px; width: 92%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); color: var(--color-text);">
                            <div style="padding: 32px 32px 16px 32px;">
                                <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 16px; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center;">
                                    <svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 20h9" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 3.5a2.121 2.121 0 113 3L7 19l-4 1 1-4 12.5-12.5z" />
                                    </svg>
                                </div>
                                <h2 style="margin: 0 0 8px; font-size: 1.4rem;">Add FAQ</h2>
                                <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Enter FAQ details.</p>
                            </div>
                            <form id="newFaqForm" style="padding: 0 32px 24px 32px; text-align:left;">
                                <div style="display:grid; grid-template-columns: 1fr; gap:12px;">
                                    <div>
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Question <span style="color:#ef4444;">*</span></label>
                                        <textarea name="question" required style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);"></textarea>
                                    </div>
                                    <div>
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Answer <span style="color:#ef4444;">*</span></label>
                                        <textarea name="answer" required style="width:100%; min-height: 120px; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);"></textarea>
                                    </div>
                                </div>
                                <div style="padding: 20px 0 0; display:flex; gap:12px;">
                                    <button type="button" id="cancelNewFaqBtn" style="flex:1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Cancel</button>
                                    <button type="button" id="confirmNewFaqBtn" style="flex:1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Add FAQ</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div id="updateFaqModal" style="display:none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3500; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
                        <div style="background: var(--color-card); border-radius: 20px; text-align: center; max-width: 520px; width: 92%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); color: var(--color-text);">
                            <div style="padding: 32px 32px 16px 32px;">
                                <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 16px; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center;">
                                    <svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 20h9" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 3.5a2.121 2.121 0 113 3L7 19l-4 1 1-4 12.5-12.5z" />
                                    </svg>
                                </div>
                                <h2 style="margin: 0 0 8px; font-size: 1.4rem;">Update FAQ</h2>
                                <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Edit FAQ content.</p>
                            </div>
                            <form id="updateFaqForm" style="padding: 0 32px 24px 32px; text-align:left;">
                                <input type="hidden" name="id" />
                                <div style="display:grid; grid-template-columns: 1fr; gap:12px;">
                                    <div>
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Question <span style="color:#ef4444;">*</span></label>
                                        <textarea name="question" required style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);"></textarea>
                                    </div>
                                    <div>
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Answer <span style="color:#ef4444;">*</span></label>
                                        <textarea name="answer" required style="width:100%; min-height: 120px; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);"></textarea>
                                    </div>
                                </div>
                                <div style="padding: 20px 0 0; display:flex; gap:12px;">
                                    <button type="button" id="cancelUpdateFaqBtn" style="flex:1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Cancel</button>
                                    <button type="button" id="confirmUpdateFaqBtn" style="flex:1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Update FAQ</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div id="confirmationModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3500; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
                        <div style="background: var(--color-card); border-radius: 20px; text-align: center; max-width: 400px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); color: var(--color-text);">
                            <div style="padding: 32px 32px 16px 32px;">
                                <div id="modalIconWrap" style="width: 56px; height: 56px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 16px; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center;">
                                    <svg id="modalIconSvg" style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <h2 id="modalTitle" style="margin: 0 0 8px; font-size: 1.4rem;">Confirm</h2>
                                <p id="modalMessage" style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Proceed?</p>
                            </div>
                            <div style="padding: 20px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
                                <button type="button" id="modalCancelBtn" style="flex: 1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Cancel</button>
                                <button type="button" id="modalConfirmBtn" style="flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Confirm</button>
                            </div>
                        </div>
                    </div>

                    <div id="messageModal" style="display:none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 5000; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
                        <div style="background: var(--color-card); border-radius: 20px; text-align: center; max-width: 400px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); color: var(--color-text);">
                            <div style="padding: 32px 32px 16px 32px;">
                                <div id="msgIconWrap" style="width: 56px; height: 56px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 16px; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center;">
                                    <svg id="msgIconSvg" style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <h2 id="msgTitle" style="margin: 0 0 8px; font-size: 1.4rem;">Message</h2>
                                <p id="msgMessage" style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Details.</p>
                            </div>
                            <div style="padding: 20px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
                                <button type="button" id="msgOkBtn" style="flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Okay</button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            window.setModalIcon = function(kind) {
                var w = document.getElementById('modalIconWrap'), s = document.getElementById('modalIconSvg');
                if (!w || !s) return;
                var bg = 'linear-gradient(135deg, #18a558 0%, #136515 100%)';
                var path = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />';
                if (kind === 'error') { bg = 'linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)'; path = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />'; }
                else if (kind === 'confirm') { bg = 'linear-gradient(135deg, #18a558 0%, #136515 100%)'; path = '<text x="12" y="16" text-anchor="middle" font-size="16" font-weight="700" fill="currentColor">?</text>'; }
                w.style.background = bg; s.innerHTML = path;
            }

            var table = document.getElementById('faqsTable');
            var tbody = table ? table.querySelector('tbody') : null;
            var headers = table ? table.querySelectorAll('th.sortable') : [];
            var searchInput = document.getElementById('searchQuery');

            var currentSort = { column: null, direction: 'asc' };
            function getColumnIndex(key) { var h = Array.from(headers).find(x => x.dataset.column === key); if (!h) return -1; return Array.from(h.parentNode.children).indexOf(h); }
            function getCellValue(row, key) { var idx = getColumnIndex(key); var cell = row.children[idx]; if (!cell) return ''; return cell.textContent.trim(); }
            function compareText(a, b) { return a.toLowerCase().localeCompare(b.toLowerCase()); }
            function compareNumeric(a, b) { var na = parseFloat(String(a).replace(/[^\d.-]/g, '')) || 0; var nb = parseFloat(String(b).replace(/[^\d.-]/g, '')) || 0; return na - nb; }

            headers.forEach(h => {
                h.addEventListener('click', function() {
                    var key = this.dataset.column; var dir = (currentSort.column === key && currentSort.direction === 'asc') ? 'desc' : 'asc';
                    currentSort = { column: key, direction: dir };
                    headers.forEach(x => x.classList.remove('sorted-asc', 'sorted-desc'));
                    this.classList.add(dir === 'asc' ? 'sorted-asc' : 'sorted-desc');
                    var rows = Array.from(tbody.querySelectorAll('tr'));
                    rows.sort(function(r1, r2) { var v1 = getCellValue(r1, key), v2 = getCellValue(r2, key); if (key === 'id') { return dir === 'asc' ? compareNumeric(v1, v2) : compareNumeric(v2, v1); } return dir === 'asc' ? compareText(v1, v2) : compareText(v2, v1); });
                    rows.forEach(r => tbody.appendChild(r));
                });
            });

            function filterRows(q) {
                q = String(q || '').trim().toLowerCase();
                var rows = Array.from(tbody.querySelectorAll('tr'));
                let visible = 0;
                rows.forEach(row => {
                    if (row.id === 'noResultsRow') return;
                    var text = row.textContent.toLowerCase();
                    var show = q === '' || text.includes(q);
                    row.style.display = show ? '' : 'none';
                    if (show) visible++;
                });
                var noRow = tbody.querySelector('#noResultsRow');
                if (!noRow) { noRow = document.createElement('tr'); noRow.id = 'noResultsRow'; var td = document.createElement('td'); var colCount = table.querySelectorAll('thead th').length; td.colSpan = colCount; td.style.textAlign = 'center'; td.style.color = 'var(--color-text-light)'; td.textContent = 'No matching FAQs.'; noRow.appendChild(td); tbody.appendChild(noRow); }
                noRow.style.display = visible === 0 ? '' : 'none';
            }
            if (searchInput) { filterRows(searchInput.value); searchInput.addEventListener('input', function() { filterRows(this.value); if (window.faqsPagination) { window.faqsPagination.updateFilteredRows(); window.faqsPagination.currentPage = 1; window.faqsPagination.updateDisplay(); } }); }

            var confirmationModal = document.getElementById('confirmationModal');
            var modalTitle = document.getElementById('modalTitle');
            var modalMessage = document.getElementById('modalMessage');
            var modalConfirmBtn = document.getElementById('modalConfirmBtn');
            var modalCancelBtn = document.getElementById('modalCancelBtn');
            var pending = null;
            tbody?.addEventListener('click', function(e) {
                var btn = e.target.closest('.btn-status');
                if (!btn) return;
                var id = btn.dataset.id;
                var next = btn.dataset.next;
                var cur = btn.dataset.current;
                if (!id || !next) return;
                if (modalTitle && modalMessage && confirmationModal) {
                    modalTitle.textContent = 'Confirm Status Update';
                    modalMessage.textContent = 'Change status to "' + next + '"?';
                    window.setModalIcon && window.setModalIcon('confirm');
                    confirmationModal.style.display = 'flex';
                    pending = { id, next, btn };
                }
            });
            modalCancelBtn?.addEventListener('click', function() { pending = null; if (confirmationModal) confirmationModal.style.display = 'none'; });
            modalConfirmBtn?.addEventListener('click', async function() {
                if (!pending) return;
                confirmationModal.style.display = 'none';
                var id = pending.id, next = pending.next, btn = pending.btn; pending = null;
                try {
                    window.showLoader && window.showLoader();
                    var resp = await fetch('faqs_management.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'update_faq_status', id: id, next_status: next }).toString() });
                    var data = await resp.json();
                    window.hideLoader && window.hideLoader();
                    if (data && data.ok) {
                        var row = btn.closest('tr');
                        var statusCell = row && row.children[getColumnIndex('status')];
                        if (statusCell) {
                            var pill = statusCell.querySelector('.status-pill');
                            var stNorm = String(data.next_status || next).trim().toLowerCase();
                            var cls = (stNorm === 'active') ? 'pill-active' : 'pill-negative';
                            var pretty = stNorm.charAt(0).toUpperCase() + stNorm.slice(1);
                            if (pill) { pill.className = 'status-pill ' + cls; pill.innerHTML = '<span class="pill-dot"></span> ' + pretty; }
                        }
                        var updated = String(data.next_status || next).toLowerCase();
                        var label = (updated === 'active') ? 'Archive' : 'Activate';
                        var next2 = (updated === 'active') ? 'inactive' : 'active';
                        btn.textContent = label; btn.dataset.current = updated; btn.dataset.next = next2;
                    } else {
                        window.showMessage && window.showMessage('Update Failed', (data && data.error) ? String(data.error) : 'Failed to update status', 'error');
                    }
                } catch (err) {
                    window.hideLoader && window.hideLoader();
                    window.showMessage && window.showMessage('Error', 'Error updating status', 'error');
                }
            });

            class FaqsPagination {
                constructor() { this.table = document.getElementById('faqsTable'); this.tbody = this.table?.querySelector('tbody'); this.select = document.getElementById('rowsPerPageSelect'); this.info = document.getElementById('paginationInfo'); this.prev = document.getElementById('prevPage'); this.next = document.getElementById('nextPage'); this.currentPage = 1; this.rowsPerPage = this.getRowsPerPage(); this.allRows = []; this.filteredRows = []; this.init(); }
                init() { this.updateFilteredRows(); this.updateDisplay(); this.select?.addEventListener('change', () => { this.rowsPerPage = this.getRowsPerPage(); this.currentPage = 1; this.updateDisplay(); }); this.prev?.addEventListener('click', () => { if (this.currentPage > 1) { this.currentPage--; this.updateDisplay(); } }); this.next?.addEventListener('click', () => { const totalPages = Math.max(1, Math.ceil(this.filteredRows.length / this.rowsPerPage)); if (this.currentPage < totalPages) { this.currentPage++; this.updateDisplay(); } }); }
                getRowsPerPage() { const v = this.select?.value || '10'; if (v === 'all') return Infinity; const n = parseInt(v, 10); return isNaN(n) ? 10 : n; }
                updateFilteredRows() { this.allRows = Array.from(this.tbody.querySelectorAll('tr')).filter(r => r.id !== 'noResultsRow'); this.filteredRows = this.allRows.filter(r => r.style.display !== 'none'); }
                updateDisplay() { const totalRows = this.filteredRows.length; const perPage = this.rowsPerPage === Infinity ? totalRows : this.rowsPerPage; const totalPages = Math.max(1, Math.ceil(totalRows / (perPage || 1))); const startIndex = (this.currentPage - 1) * (perPage || 1); const endIndex = startIndex + (perPage || 1); this.allRows.forEach(r => r.style.display = 'none'); const toShow = this.filteredRows.slice(startIndex, endIndex); toShow.forEach(r => r.style.display = ''); if (this.info) { const startItem = totalRows === 0 ? 0 : startIndex + 1; const endItem = Math.min(endIndex, totalRows); this.info.textContent = `Showing ${startItem}-${endItem} • Page ${this.currentPage}`; } if (this.prev) { this.prev.disabled = this.currentPage <= 1; this.prev.classList.toggle('pagination__button--disabled', this.currentPage <= 1); } if (this.next) { this.next.disabled = this.currentPage >= totalPages; this.next.classList.toggle('pagination__button--disabled', this.currentPage >= totalPages); } }
            }
            window.faqsPagination = new FaqsPagination();

            var messageModal = document.getElementById('messageModal');
            var msgTitle = document.getElementById('msgTitle');
            var msgMessage = document.getElementById('msgMessage');
            var msgOkBtn = document.getElementById('msgOkBtn');
            var msgIconWrap = document.getElementById('msgIconWrap');
            var msgIconSvg = document.getElementById('msgIconSvg');
            var messageOkAction = null;
            function setMsgIcon(kind){ var bg='linear-gradient(135deg, #18a558 0%, #136515 100%)'; var path='<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />'; if(kind==='error'){ bg='linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)'; path='<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />'; } else if(kind==='info'){ bg='linear-gradient(135deg, #4f46e5 0%, #3730a3 100%)'; path='<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8h.01M11 12h2v4h-2z" />'; } else if(kind==='success'){ bg='linear-gradient(135deg, #18a558 0%, #136515 100%)'; path='<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />'; } if(msgIconWrap) msgIconWrap.style.background=bg; if(msgIconSvg) msgIconSvg.innerHTML=path; }
            window.showMessage = function(title, message, kind, onOk){ if (msgTitle) msgTitle.textContent = title||'Message'; if (msgMessage) msgMessage.textContent = message||''; setMsgIcon(kind||'info'); if (messageModal) messageModal.style.display='flex'; messageOkAction = typeof onOk==='function'?onOk:null; };
            msgOkBtn?.addEventListener('click', function(){ if (messageModal) messageModal.style.display='none'; var fn = messageOkAction; messageOkAction=null; if (typeof fn==='function') fn(); });

            var addFaqBtn = document.getElementById('addFaqBtn');
            var newFaqModal = document.getElementById('newFaqModal');
            var cancelNewFaqBtn = document.getElementById('cancelNewFaqBtn');
            var confirmNewFaqBtn = document.getElementById('confirmNewFaqBtn');
            var updateFaqModal = document.getElementById('updateFaqModal');
            var cancelUpdateFaqBtn = document.getElementById('cancelUpdateFaqBtn');
            var confirmUpdateFaqBtn = document.getElementById('confirmUpdateFaqBtn');

            addFaqBtn?.addEventListener('click', function(){ if(newFaqModal) newFaqModal.style.display='flex'; });
            cancelNewFaqBtn?.addEventListener('click', function(){ if(newFaqModal) newFaqModal.style.display='none'; });
            confirmNewFaqBtn?.addEventListener('click', async function(){
                var form = document.getElementById('newFaqForm'); var fd = new FormData(form);
                var question = String(fd.get('question')||'').trim(); var answer = String(fd.get('answer')||'').trim();
                if (!question || !answer) return;
                try{
                    window.showLoader && window.showLoader();
                    var resp = await fetch('faqs_management.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action:'create_faq', question:question, answer:answer }).toString() });
                    var data = await resp.json();
                    window.hideLoader && window.hideLoader();
                    if (data && data.ok){ newFaqModal.style.display='none'; showMessage('FAQ Added','New FAQ created successfully.','success', function(){ location.reload(); }); }
                    else { newFaqModal.style.display='none'; showMessage('Create Failed', (data && data.error) ? String(data.error) : 'Failed to add FAQ','error'); }
                }catch(err){ window.hideLoader && window.hideLoader(); newFaqModal.style.display='none'; showMessage('Error','Error adding FAQ','error'); }
            });

            tbody?.addEventListener('click', function(e){ var ubtn = e.target.closest('.btn-update'); if (!ubtn) return; if (updateFaqModal) { updateFaqModal.style.display='flex'; var form = document.getElementById('updateFaqForm'); form.querySelector('[name="id"]').value = ubtn.dataset.id || ''; form.querySelector('[name="question"]').value = ubtn.dataset.question || ''; form.querySelector('[name="answer"]').value = ubtn.dataset.answer || ''; } });
            cancelUpdateFaqBtn?.addEventListener('click', function(){ if (updateFaqModal) updateFaqModal.style.display='none'; });
            confirmUpdateFaqBtn?.addEventListener('click', async function(){
                var form = document.getElementById('updateFaqForm'); var fd = new FormData(form);
                var id = Number(fd.get('id')||0); var question = String(fd.get('question')||'').trim(); var answer = String(fd.get('answer')||'').trim();
                if (!id || !question || !answer) return;
                try{
                    window.showLoader && window.showLoader();
                    var resp = await fetch('faqs_management.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action:'update_faq', id:String(id), question:question, answer:answer }).toString() });
                    var data = await resp.json();
                    window.hideLoader && window.hideLoader();
                    if (data && data.ok){
                        updateFaqModal.style.display='none';
                        var row = Array.from(tbody.querySelectorAll('tr')).find(r=>{ var cell = r.children[0]; return cell && Number(cell.textContent.trim())===id; });
                        if (row){ row.children[getColumnIndex('question')].textContent = question || 'N/A'; row.children[getColumnIndex('answer')].textContent = answer || 'N/A'; }
                        showMessage('FAQ Updated','Changes saved successfully.','success');
                    } else {
                        updateFaqModal.style.display='none'; showMessage('Update Failed', (data && data.error) ? String(data.error) : 'Failed to update FAQ','error');
                    }
                }catch(err){ window.hideLoader && window.hideLoader(); updateFaqModal.style.display='none'; showMessage('Error','Error updating FAQ','error'); }
            });
        });
    </script>
</body>

</html>