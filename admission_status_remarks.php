<?php
require_once 'middleware/auth.php';
require_once 'connection/db_connect.php';
date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status_default') {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $next = isset($_POST['next_default']) ? (int)$_POST['next_default'] : -1;
    if ($id <= 0 || ($next !== 0 && $next !== 1)) { echo json_encode(['ok' => false, 'error' => 'Missing parameters']); exit; }
    $ok = true;
    if ($next === 1) { $ok = $conn->query('UPDATE statuses SET is_default = 0') ? true : false; }
    if ($ok) {
        if ($stmt = $conn->prepare('UPDATE statuses SET is_default = ? WHERE id = ?')) {
            $stmt->bind_param('ii', $next, $id);
            $ok = $stmt->execute();
            $stmt->close();
        } else { $ok = false; }
    }
    if (!$ok) { echo json_encode(['ok' => false, 'error' => 'Update failed']); exit; }
    echo json_encode(['ok' => true, 'next_default' => $next]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_status') {
    header('Content-Type: application/json');
    $name = trim($_POST['name'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $hex_color = trim($_POST['hex_color'] ?? '');
    $is_default = isset($_POST['is_default']) ? (int)$_POST['is_default'] : 0;
    if ($name === '' || $remarks === '' || $hex_color === '') { echo json_encode(['ok' => false, 'error' => 'Please fill required fields']); exit; }
    $ok = true;
    if ($is_default === 1) { $ok = $conn->query('UPDATE statuses SET is_default = 0') ? true : false; }
    if ($ok) {
        if ($stmt = $conn->prepare('INSERT INTO statuses (name, remarks, is_default, hex_color) VALUES (?, ?, ?, ?)')) {
            $stmt->bind_param('ssis', $name, $remarks, $is_default, $hex_color);
            $ok = $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();
            if ($ok) { echo json_encode(['ok' => true, 'id' => $newId]); }
            else { echo json_encode(['ok' => false, 'error' => 'Create failed']); }
        } else { echo json_encode(['ok' => false, 'error' => 'Prepare failed']); }
    } else { echo json_encode(['ok' => false, 'error' => 'Prepare failed']); }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = trim($_POST['name'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $hex_color = trim($_POST['hex_color'] ?? '');
    $is_default = isset($_POST['is_default']) ? (int)$_POST['is_default'] : 0;
    if ($id <= 0 || $name === '' || $remarks === '' || $hex_color === '') { echo json_encode(['ok' => false, 'error' => 'Missing parameters']); exit; }
    $ok = true;
    if ($is_default === 1) { $ok = $conn->query('UPDATE statuses SET is_default = 0 WHERE id <> ' . $id) ? true : false; }
    if ($ok) {
        if ($stmt = $conn->prepare('UPDATE statuses SET name = ?, remarks = ?, is_default = ?, hex_color = ? WHERE id = ?')) {
            $stmt->bind_param('ssisi', $name, $remarks, $is_default, $hex_color, $id);
            $ok = $stmt->execute();
            $stmt->close();
            if ($ok) { echo json_encode(['ok' => true]); }
            else { echo json_encode(['ok' => false, 'error' => 'Update failed']); }
        } else { echo json_encode(['ok' => false, 'error' => 'Prepare failed']); }
    } else { echo json_encode(['ok' => false, 'error' => 'Prepare failed']); }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Success Office - Admission Status Remarks</title>
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
    $statuses = [];
    if ($res = $conn->query('SELECT id, name, remarks, is_default, hex_color FROM statuses ORDER BY id DESC')) { while ($row = $res->fetch_assoc()) { $statuses[] = $row; } }
    ?>

    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="header">
                <div class="header__left">
                    <h1>Admission Status Remarks</h1>
                    <p class="header__subtitle">Manage status names, remarks, and colors</p>
                </div>
            </header>

            <section class="section active" style="margin: 0 20px;">
                <div class="table-container">
                    <div class="table-container__header">
                        <h2 class="table-container__title">Statuses</h2>
                        <p class="table-container__subtitle">Create and update status remarks</p>
                    </div>

                    <div class="filtration_container">
                        <form method="GET" action="" style="display:flex; width:100%; justify-content:space-between; gap:12px; align-items:center;">
                            <div class="search_input_container" style="flex:1;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search-icon lucide-search">
                                    <path d="m21 21-4.34-4.34" />
                                    <circle cx="11" cy="11" r="8" />
                                </svg>
                                <input type="text" id="searchQuery" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search statuses..." aria-label="Search statuses">
                            </div>
                            <div class="search_button_container" style="flex:0 0 auto; display:flex; gap:8px; align-items:center;">
                                <button class="button export" id="addStatusBtn" type="button">Add Status</button>
                            </div>
                        </form>
                    </div>

                    <div class="table-wrapper" style="overflow-x:auto;">
                        <table class="table" id="statusesTable">
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
                                    <th class="sortable" data-column="name">Name
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                            <path d="m3 16 4 4 4-4" />
                                            <path d="M7 20V4" />
                                            <path d="M20 8h-5" />
                                            <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                            <path d="M15 14h5l-5 6h5" />
                                        </svg>
                                    </th>
                                    <th class="sortable" data-column="remarks">Remarks
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                            <path d="m3 16 4 4 4-4" />
                                            <path d="M7 20V4" />
                                            <path d="M20 8h-5" />
                                            <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                            <path d="M15 14h5l-5 6h5" />
                                        </svg>
                                    </th>
                                    <th class="sortable" data-column="default">Default
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                            <path d="m3 16 4 4 4-4" />
                                            <path d="M7 20V4" />
                                            <path d="M20 8h-5" />
                                            <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                            <path d="M15 14h5l-5 6h5" />
                                        </svg>
                                    </th>
                                    <th class="sortable" data-column="color">Color
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
                                <?php if (empty($statuses)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center;">No statuses found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($statuses as $s): ?>
                                        <?php
                                        $id = (int)($s['id'] ?? 0);
                                        $name = trim((string)($s['name'] ?? ''));
                                        $remarks = trim((string)($s['remarks'] ?? ''));
                                        $isDefault = (int)($s['is_default'] ?? 0);
                                        $hex = trim((string)($s['hex_color'] ?? ''));
                                        $st = $isDefault === 1 ? 'Yes' : 'No';
                                        $pillClass = ($isDefault === 1) ? 'pill-active' : 'pill-negative';
                                        $label = ($isDefault === 1) ? 'Unset Default' : 'Set Default';
                                        $next = ($isDefault === 1) ? 0 : 1;
                                        ?>
                                        <tr>
                                            <td><?php echo $id; ?></td>
                                            <td data-key="name"><?php echo htmlspecialchars($name !== '' ? $name : 'N/A'); ?></td>
                                            <td data-key="remarks"><?php echo htmlspecialchars($remarks !== '' ? $remarks : 'N/A'); ?></td>
                                            <td data-key="default"><span class="status-pill <?php echo $pillClass; ?>"><span class="pill-dot"></span> <?php echo htmlspecialchars($st); ?></span></td>
                                            <td data-key="color"><div style="display:flex; align-items:center; gap:8px;"><span style="width:14px; height:14px; border-radius:50%; display:inline-block; border:1px solid var(--color-border); background: <?php echo htmlspecialchars($hex !== '' ? $hex : '#e5e7eb'); ?>;"></span><span><?php echo htmlspecialchars($hex !== '' ? $hex : 'N/A'); ?></span></div></td>
                                            <td>
                                                <div style="display:flex; gap:8px; align-items:center;">
                                                    <button type="button" class="button btn-status" data-id="<?php echo $id; ?>" data-next="<?php echo htmlspecialchars((string)$next); ?>" data-current="<?php echo htmlspecialchars((string)$isDefault); ?>"><?php echo htmlspecialchars($label); ?></button>
                                                    <button type="button" class="button btn-status btn-update"
                                                        data-id="<?php echo $id; ?>"
                                                        data-name="<?php echo htmlspecialchars($name); ?>"
                                                        data-remarks="<?php echo htmlspecialchars($remarks); ?>"
                                                        data-default="<?php echo htmlspecialchars((string)$isDefault); ?>"
                                                        data-color="<?php echo htmlspecialchars($hex); ?>">Update</button>
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

                    <div id="newStatusModal" style="display:none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3500; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
                        <div style="background: var(--color-card); border-radius: 20px; text-align: center; max-width: 520px; width: 92%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); color: var(--color-text);">
                            <div style="padding: 32px 32px 16px 32px;">
                                <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 16px; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center;">
                                    <svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 20h9" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 3.5a2.121 2.121 0 113 3L7 19l-4 1 1-4 12.5-12.5z" />
                                    </svg>
                                </div>
                                <h2 style="margin: 0 0 8px; font-size: 1.4rem;">Add Status</h2>
                                <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Enter status details.</p>
                            </div>
                            <form id="newStatusForm" style="padding: 0 32px 24px 32px; text-align:left;">
                                <div style="display:grid; grid-template-columns: 1fr; gap:12px;">
                                    <div>
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Name <span style="color:#ef4444;">*</span></label>
                                        <input name="name" type="text" required style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                    </div>
                                    <div>
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Remarks <span style="color:#ef4444;">*</span></label>
                                        <textarea name="remarks" required style="width:100%; min-height: 120px; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);"></textarea>
                                    </div>
                                    <div>
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Hex Color <span style="color:#ef4444;">*</span></label>
                                        <input name="hex_color" type="text" placeholder="#28A745" required style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                    </div>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <input id="newIsDefault" name="is_default" type="checkbox" value="1" style="accent-color:#18a558;">
                                        <label for="newIsDefault" style="font-weight:600;">Set as default</label>
                                    </div>
                                </div>
                                <div style="padding: 20px 0 0; display:flex; gap:12px;">
                                    <button type="button" id="cancelNewStatusBtn" style="flex:1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Cancel</button>
                                    <button type="button" id="confirmNewStatusBtn" style="flex:1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Add Status</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div id="updateStatusModal" style="display:none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3500; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
                        <div style="background: var(--color-card); border-radius: 20px; text-align: center; max-width: 520px; width: 92%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); color: var(--color-text);">
                            <div style="padding: 32px 32px 16px 32px;">
                                <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 16px; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center;">
                                    <svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 20h9" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 3.5a2.121 2.121 0 113 3L7 19l-4 1 1-4 12.5-12.5z" />
                                    </svg>
                                </div>
                                <h2 style="margin: 0 0 8px; font-size: 1.4rem;">Update Status</h2>
                                <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Edit status details.</p>
                            </div>
                            <form id="updateStatusForm" style="padding: 0 32px 24px 32px; text-align:left;">
                                <input type="hidden" name="id" />
                                <div style="display:grid; grid-template-columns: 1fr; gap:12px;">
                                    <div>
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Name <span style="color:#ef4444;">*</span></label>
                                        <input name="name" type="text" required style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                    </div>
                                    <div>
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Remarks <span style="color:#ef4444;">*</span></label>
                                        <textarea name="remarks" required style="width:100%; min-height: 120px; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);"></textarea>
                                    </div>
                                    <div>
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Hex Color <span style="color:#ef4444;">*</span></label>
                                        <input name="hex_color" type="text" placeholder="#28A745" required style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                    </div>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <input id="updateIsDefault" name="is_default" type="checkbox" value="1" style="accent-color:#18a558;">
                                        <label for="updateIsDefault" style="font-weight:600;">Set as default</label>
                                    </div>
                                </div>
                                <div style="padding: 20px 0 0; display:flex; gap:12px;">
                                    <button type="button" id="cancelUpdateStatusBtn" style="flex:1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Cancel</button>
                                    <button type="button" id="confirmUpdateStatusBtn" style="flex:1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Update Status</button>
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

            var table = document.getElementById('statusesTable');
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
                rows.forEach(row => { if (row.id === 'noResultsRow') return; var text = row.textContent.toLowerCase(); var show = q === '' || text.includes(q); row.style.display = show ? '' : 'none'; if (show) visible++; });
                var noRow = tbody.querySelector('#noResultsRow');
                if (!noRow) { noRow = document.createElement('tr'); noRow.id = 'noResultsRow'; var td = document.createElement('td'); var colCount = table.querySelectorAll('thead th').length; td.colSpan = colCount; td.style.textAlign = 'center'; td.style.color = 'var(--color-text-light)'; td.textContent = 'No matching statuses.'; noRow.appendChild(td); tbody.appendChild(noRow); }
                noRow.style.display = visible === 0 ? '' : 'none';
            }
            if (searchInput) { filterRows(searchInput.value); searchInput.addEventListener('input', function() { filterRows(this.value); if (window.statusesPagination) { window.statusesPagination.updateFilteredRows(); window.statusesPagination.currentPage = 1; window.statusesPagination.updateDisplay(); } }); }

            var confirmationModal = document.getElementById('confirmationModal');
            var modalTitle = document.getElementById('modalTitle');
            var modalMessage = document.getElementById('modalMessage');
            var modalConfirmBtn = document.getElementById('modalConfirmBtn');
            var modalCancelBtn = document.getElementById('modalCancelBtn');
            var pending = null;
            tbody?.addEventListener('click', function(e) {
                var btn = e.target.closest('.btn-status');
                if (!btn || btn.classList.contains('btn-update')) return;
                var id = btn.dataset.id;
                var next = parseInt(btn.dataset.next || '-1', 10);
                if (!id || (next !== 0 && next !== 1)) return;
                if (modalTitle && modalMessage && confirmationModal) {
                    modalTitle.textContent = 'Confirm Default Change';
                    modalMessage.textContent = 'Set default to ' + (next === 1 ? 'Yes' : 'No') + '?';
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
                    var resp = await fetch('admission_status_remarks.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'update_status_default', id: id, next_default: String(next) }).toString() });
                    var data = await resp.json();
                    window.hideLoader && window.hideLoader();
                    if (data && data.ok) {
                        var rows = Array.from(tbody.querySelectorAll('tr'));
                        rows.forEach(function(row){
                            var statusCell = row.children[getColumnIndex('default')];
                            var pill = statusCell && statusCell.querySelector('.status-pill');
                            if (pill) { pill.className = 'status-pill pill-negative'; pill.innerHTML = '<span class="pill-dot"></span> No'; }
                            var toggleBtn = row.querySelector('button.button.btn-status:not(.btn-update)');
                            if (toggleBtn) { toggleBtn.textContent = 'Set Default'; toggleBtn.dataset.current = '0'; toggleBtn.dataset.next = '1'; }
                        });
                        var row = btn.closest('tr');
                        var statusCell2 = row && row.children[getColumnIndex('default')];
                        var pill2 = statusCell2 && statusCell2.querySelector('.status-pill');
                        if (pill2 && next === 1) { pill2.className = 'status-pill pill-active'; pill2.innerHTML = '<span class="pill-dot"></span> Yes'; }
                        btn.textContent = next === 1 ? 'Unset Default' : 'Set Default';
                        btn.dataset.current = String(next);
                        btn.dataset.next = next === 1 ? '0' : '1';
                    } else {
                        window.showMessage && window.showMessage('Update Failed', (data && data.error) ? String(data.error) : 'Failed to update default', 'error');
                    }
                } catch (err) {
                    window.hideLoader && window.hideLoader();
                    window.showMessage && window.showMessage('Error', 'Error updating default', 'error');
                }
            });

            class StatusesPagination {
                constructor() { this.table = document.getElementById('statusesTable'); this.tbody = this.table?.querySelector('tbody'); this.select = document.getElementById('rowsPerPageSelect'); this.info = document.getElementById('paginationInfo'); this.prev = document.getElementById('prevPage'); this.next = document.getElementById('nextPage'); this.currentPage = 1; this.rowsPerPage = this.getRowsPerPage(); this.allRows = []; this.filteredRows = []; this.init(); }
                init() { this.updateFilteredRows(); this.updateDisplay(); this.select?.addEventListener('change', () => { this.rowsPerPage = this.getRowsPerPage(); this.currentPage = 1; this.updateDisplay(); }); this.prev?.addEventListener('click', () => { if (this.currentPage > 1) { this.currentPage--; this.updateDisplay(); } }); this.next?.addEventListener('click', () => { const totalPages = Math.max(1, Math.ceil(this.filteredRows.length / this.rowsPerPage)); if (this.currentPage < totalPages) { this.currentPage++; this.updateDisplay(); } }); }
                getRowsPerPage() { const v = this.select?.value || '10'; if (v === 'all') return Infinity; const n = parseInt(v, 10); return isNaN(n) ? 10 : n; }
                updateFilteredRows() { this.allRows = Array.from(this.tbody.querySelectorAll('tr')).filter(r => r.id !== 'noResultsRow'); this.filteredRows = this.allRows.filter(r => r.style.display !== 'none'); }
                updateDisplay() { const totalRows = this.filteredRows.length; const perPage = this.rowsPerPage === Infinity ? totalRows : this.rowsPerPage; const totalPages = Math.max(1, Math.ceil(totalRows / (perPage || 1))); const startIndex = (this.currentPage - 1) * (perPage || 1); const endIndex = startIndex + (perPage || 1); this.allRows.forEach(r => r.style.display = 'none'); const toShow = this.filteredRows.slice(startIndex, endIndex); toShow.forEach(r => r.style.display = ''); if (this.info) { const startItem = totalRows === 0 ? 0 : startIndex + 1; const endItem = Math.min(endIndex, totalRows); this.info.textContent = `Showing ${startItem}-${endItem} • Page ${this.currentPage}`; } if (this.prev) { this.prev.disabled = this.currentPage <= 1; this.prev.classList.toggle('pagination__button--disabled', this.currentPage <= 1); } if (this.next) { this.next.disabled = this.currentPage >= totalPages; this.next.classList.toggle('pagination__button--disabled', this.currentPage >= totalPages); } }
            }
            window.statusesPagination = new StatusesPagination();

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

            var addBtn = document.getElementById('addStatusBtn');
            var newModal = document.getElementById('newStatusModal');
            var cancelNewBtn = document.getElementById('cancelNewStatusBtn');
            var confirmNewBtn = document.getElementById('confirmNewStatusBtn');
            var updateModal = document.getElementById('updateStatusModal');
            var cancelUpdateBtn = document.getElementById('cancelUpdateStatusBtn');
            var confirmUpdateBtn = document.getElementById('confirmUpdateStatusBtn');

            addBtn?.addEventListener('click', function(){ if(newModal) newModal.style.display='flex'; });
            cancelNewBtn?.addEventListener('click', function(){ if(newModal) newModal.style.display='none'; });
            confirmNewBtn?.addEventListener('click', async function(){
                var form = document.getElementById('newStatusForm'); var fd = new FormData(form);
                var name = String(fd.get('name')||'').trim(); var remarks = String(fd.get('remarks')||'').trim(); var hex_color = String(fd.get('hex_color')||'').trim(); var is_default = fd.get('is_default') ? 1 : 0;
                if (!name || !remarks || !hex_color) return;
                try{
                    window.showLoader && window.showLoader();
                    var resp = await fetch('admission_status_remarks.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action:'create_status', name:name, remarks:remarks, hex_color:hex_color, is_default:String(is_default) }).toString() });
                    var data = await resp.json();
                    window.hideLoader && window.hideLoader();
                    if (data && data.ok){ newModal.style.display='none'; showMessage('Status Added','New status created successfully.','success', function(){ location.reload(); }); }
                    else { newModal.style.display='none'; showMessage('Create Failed', (data && data.error) ? String(data.error) : 'Failed to add status','error'); }
                }catch(err){ window.hideLoader && window.hideLoader(); newModal.style.display='none'; showMessage('Error','Error adding status','error'); }
            });

            tbody?.addEventListener('click', function(e){ var ubtn = e.target.closest('.btn-update'); if (!ubtn) return; if (updateModal) { updateModal.style.display='flex'; var form = document.getElementById('updateStatusForm'); form.querySelector('[name="id"]').value = ubtn.dataset.id || ''; form.querySelector('[name="name"]').value = ubtn.dataset.name || ''; form.querySelector('[name="remarks"]').value = ubtn.dataset.remarks || ''; form.querySelector('[name="hex_color"]').value = ubtn.dataset.color || ''; var chk = document.getElementById('updateIsDefault'); if (chk) chk.checked = (parseInt(ubtn.dataset.default||'0',10)===1); } });
            cancelUpdateBtn?.addEventListener('click', function(){ if (updateModal) updateModal.style.display='none'; });
            confirmUpdateBtn?.addEventListener('click', async function(){
                var form = document.getElementById('updateStatusForm'); var fd = new FormData(form);
                var id = Number(fd.get('id')||0); var name = String(fd.get('name')||'').trim(); var remarks = String(fd.get('remarks')||'').trim(); var hex_color = String(fd.get('hex_color')||'').trim(); var is_default = fd.get('is_default') ? 1 : 0;
                if (!id || !name || !remarks || !hex_color) return;
                try{
                    window.showLoader && window.showLoader();
                    var resp = await fetch('admission_status_remarks.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action:'update_status', id:String(id), name:name, remarks:remarks, hex_color:hex_color, is_default:String(is_default) }).toString() });
                    var data = await resp.json();
                    window.hideLoader && window.hideLoader();
                    if (data && data.ok){
                        updateModal.style.display='none';
                        var row = Array.from(tbody.querySelectorAll('tr')).find(r=>{ var cell = r.children[0]; return cell && Number(cell.textContent.trim())===id; });
                        if (row){
                            row.children[getColumnIndex('name')].textContent = name || 'N/A';
                            row.children[getColumnIndex('remarks')].textContent = remarks || 'N/A';
                            var colorCell = row.children[getColumnIndex('color')];
                            if (colorCell){ colorCell.innerHTML = `<div style="display:flex; align-items:center; gap:8px;"><span style="width:14px; height:14px; border-radius:50%; display:inline-block; border:1px solid var(--color-border); background: ${hex_color || '#e5e7eb'};"></span><span>${hex_color || 'N/A'}</span></div>`; }
                        }
                        if (is_default === 1){
                            var rows = Array.from(tbody.querySelectorAll('tr'));
                            rows.forEach(function(r){ var dcell = r.children[getColumnIndex('default')]; var pill = dcell && dcell.querySelector('.status-pill'); var tbtn = r.querySelector('button.button.btn-status:not(.btn-update)'); if (pill) { pill.className = 'status-pill pill-negative'; pill.innerHTML = '<span class="pill-dot"></span> No'; } if (tbtn) { tbtn.textContent = 'Set Default'; tbtn.dataset.current = '0'; tbtn.dataset.next = '1'; } });
                            var dcell2 = row.children[getColumnIndex('default')]; var pill2 = dcell2 && dcell2.querySelector('.status-pill'); if (pill2) { pill2.className = 'status-pill pill-active'; pill2.innerHTML = '<span class="pill-dot"></span> Yes'; }
                            var b = row.querySelector('button.button.btn-status:not(.btn-update)'); if (b) { b.textContent = 'Unset Default'; b.dataset.current = '1'; b.dataset.next = '0'; }
                        }
                        showMessage('Status Updated','Changes saved successfully.','success');
                    } else {
                        updateModal.style.display='none'; showMessage('Update Failed', (data && data.error) ? String(data.error) : 'Failed to update status','error');
                    }
                }catch(err){ window.hideLoader && window.hideLoader(); updateModal.style.display='none'; showMessage('Error','Error updating status','error'); }
            });
        });
    </script>
</body>

</html>