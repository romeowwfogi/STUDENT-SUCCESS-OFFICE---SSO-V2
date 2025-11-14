<?php
require_once 'middleware/auth.php';
require_once 'connection/db_connect.php';
date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_officer_status') {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $next = isset($_POST['next_status']) ? trim($_POST['next_status']) : '';
    if ($id <= 0 || $next === '') {
        echo json_encode(['ok' => false, 'error' => 'Missing parameters']);
        exit;
    }
    if ($stmt = $conn->prepare('UPDATE sso_officers SET status = ? WHERE id = ?')) {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_officer') {
    header('Content-Type: application/json');
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $profile_image_url = trim($_POST['profile_image_url'] ?? '');
    if ($first_name === '' || $last_name === '' || $title === '' || $email === '') {
        echo json_encode(['ok' => false, 'error' => 'Please fill required fields']);
        exit;
    }
    $sql = 'INSERT INTO sso_officers (first_name, middle_name, last_name, suffix, title, email, profile_image_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, "active")';
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('sssssss', $first_name, $middle_name, $last_name, $suffix, $title, $email, $profile_image_url);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_officer') {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $profile_image_url = trim($_POST['profile_image_url'] ?? '');
    if ($id <= 0 || $first_name === '' || $last_name === '' || $title === '' || $email === '') {
        echo json_encode(['ok' => false, 'error' => 'Missing parameters']);
        exit;
    }
    $sql = 'UPDATE sso_officers SET first_name = ?, middle_name = ?, last_name = ?, suffix = ?, title = ?, email = ?, profile_image_url = ? WHERE id = ?';
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('sssssssi', $first_name, $middle_name, $last_name, $suffix, $title, $email, $profile_image_url, $id);
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
    <title>Student Success Office - Officer Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .table th.sortable {
            position: relative;
            cursor: pointer;
            user-select: none;
            padding-right: 26px;
        }

        .table th.sortable:hover {
            background-color: #f8fafc;
        }

        .table th .sort-icon {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.6;
            transition: transform 0.2s ease, opacity 0.2s ease;
            width: 16px;
            height: 16px;
        }

        .table th.sorted-asc .sort-icon,
        .table th.sorted-desc .sort-icon {
            opacity: 1;
        }

        .table th.sorted-desc .sort-icon {
            transform: translateY(-50%) rotate(180deg);
        }

        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.35);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 6000;
            backdrop-filter: blur(2px);
        }

        .loading-overlay .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid rgba(255, 255, 255, 0.5);
            border-top-color: #18a558;
            border-radius: 50%;
            animation: spin 0.9s linear infinite;
        }

        .loading-overlay .label {
            margin-top: 12px;
            color: #fff;
            font-weight: 600;
            letter-spacing: 0.2px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .pagination {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border: 2px solid var(--color-border);
            border-radius: 12px;
            background: var(--color-card);
            margin-top: 12px;
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

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 0.85rem;
            letter-spacing: .01em;
        }

        .pill-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .pill-active {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #10b981;
        }

        .pill-active .pill-dot {
            background: #10b981;
        }

        .pill-negative {
            background: #fee2e2;
            color: #7f1d1d;
            border: 1px solid #ef4444;
        }

        .pill-negative .pill-dot {
            background: #ef4444;
        }

        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #e5e7eb;
            color: #374151;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            overflow: hidden;
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .button.btn-status {
            border: 1.5px solid rgba(16, 185, 129, 0.35);
            background: var(--color-card);
            color: var(--color-primary);
            font-weight: 100;
            font-size: 0.85rem;
            padding: 8px 14px !important;
            transition: all 0.2s ease;
        }

        .button.btn-status:hover {
            background-color: var(--color-primary);
            color: var(--color-white);
            transform: translateY(-1px);
        }
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
        window.showLoader = function() {
            var l = document.getElementById('loadingOverlay');
            if (l) {
                document.body.appendChild(l);
                l.style.display = 'flex';
            }
        };
        window.hideLoader = function() {
            var l = document.getElementById('loadingOverlay');
            if (l) l.style.display = 'none';
        };
    </script>

    <?php
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $officers = [];
    $sql = "SELECT id, first_name, middle_name, last_name, suffix, title, email, profile_image_url, status FROM sso_officers";
    $conds = [];
    $params = [];
    $types = '';
    if ($q !== '') {
        $like = "%" . $q . "%";
        $conds[] = "(first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ? OR suffix LIKE ? OR title LIKE ? OR email LIKE ?)";
        $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
        $types .= 'ssssss';
    }
    if ($status !== '') {
        $conds[] = "status = ?";
        $params[] = $status;
        $types .= 's';
    }
    if (!empty($conds)) {
        $sql .= " WHERE " . implode(" AND ", $conds);
    }
    $sql .= " ORDER BY id DESC";
    if (!empty($params)) {
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $officers[] = $row;
            }
            $stmt->close();
        }
    } else {
        if ($res = $conn->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $officers[] = $row;
            }
        }
    }
    ?>

    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="header">
                <div class="header__left">
                    <h1>Officer Management</h1>
                    <p class="header__subtitle">List of SSO officers</p>
                </div>
            </header>

            <section class="section active" style="margin: 0 20px;">
                <div class="table-container">
                    <div class="table-container__header">
                        <h2 class="table-container__title">Officers</h2>
                        <p class="table-container__subtitle">Manage Student Success Office officers</p>
                    </div>

                    <div class="filtration_container">
                        <form method="GET" action="" style="display:flex; width:100%; justify-content:space-between; gap:12px; align-items:center;">
                            <div class="search_input_container" style="flex:1;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search-icon lucide-search">
                                    <path d="m21 21-4.34-4.34" />
                                    <circle cx="11" cy="11" r="8" />
                                </svg>
                                <input type="text" id="searchQuery" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search officers..." aria-label="Search officers">
                            </div>
                            <div class="search_button_container" style="flex:0 0 auto; display:flex; gap:8px; align-items:center;">
                                <button class="button export" id="addOfficerBtn" type="button">Add Officer</button>
                            </div>
                        </form>
                    </div>

                    <div class="table-wrapper" style="overflow-x:auto;">
                        <table class="table" id="officersTable">
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
                                    <th class="sortable" data-column="fullname">Fullname
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                            <path d="m3 16 4 4 4-4" />
                                            <path d="M7 20V4" />
                                            <path d="M20 8h-5" />
                                            <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                            <path d="M15 14h5l-5 6h5" />
                                        </svg>
                                    </th>
                                    <th class="sortable" data-column="position">Position
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
                                    <th>Profile</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($officers)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center;">No officers found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($officers as $o): ?>
                                        <?php
                                        $fn = trim((string)($o['first_name'] ?? ''));
                                        $mn = trim((string)($o['middle_name'] ?? ''));
                                        $ln = trim((string)($o['last_name'] ?? ''));
                                        $sf = trim((string)($o['suffix'] ?? ''));
                                        $full = trim($fn . ' ' . ($mn !== '' ? ($mn . ' ') : '') . $ln . ($sf !== '' ? (' ' . $sf) : ''));
                                        $st = strtolower(trim((string)($o['status'] ?? '')));
                                        $pillClass = ($st === 'active') ? 'pill-active' : 'pill-negative';
                                        $label = ($st === 'active') ? 'Archive' : 'Activate';
                                        $next = ($st === 'active') ? 'archived' : 'active';
                                        $img = trim((string)($o['profile_image_url'] ?? ''));
                                        $pos = trim((string)($o['title'] ?? ''));
                                        $disp = $st !== '' ? ucfirst($st) : 'N/A';
                                        ?>
                                        <tr>
                                            <td><?php echo (int)($o['id'] ?? 0); ?></td>
                                            <td data-key="fullname"><?php echo htmlspecialchars($full !== '' ? $full : 'N/A'); ?></td>
                                            <td data-key="position"><?php echo htmlspecialchars($pos !== '' ? $pos : 'N/A'); ?></td>
                                            <td data-key="status">
                                                <span class="status-pill <?php echo $pillClass; ?>"><span class="pill-dot"></span> <?php echo htmlspecialchars($disp); ?></span>
                                            </td>
                                            <td>
                                                <?php if ($img !== ''): ?>
                                                    <div class="avatar"><img src="<?php echo htmlspecialchars($img); ?>" alt="Profile"></div>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display:flex; gap:8px; align-items:center;">
                                                    <button type="button" class="button btn-status" data-id="<?php echo (int)($o['id'] ?? 0); ?>" data-next="<?php echo htmlspecialchars($next); ?>" data-current="<?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars($label); ?></button>
                                                    <button type="button" class="button btn-status btn-update"
                                                        data-id="<?php echo (int)($o['id'] ?? 0); ?>"
                                                        data-first-name="<?php echo htmlspecialchars($fn); ?>"
                                                        data-middle-name="<?php echo htmlspecialchars($mn); ?>"
                                                        data-last-name="<?php echo htmlspecialchars($ln); ?>"
                                                        data-suffix="<?php echo htmlspecialchars($sf); ?>"
                                                        data-title="<?php echo htmlspecialchars($pos); ?>"
                                                        data-email="<?php echo htmlspecialchars((string)($o['email'] ?? '')); ?>"
                                                        data-profile="<?php echo htmlspecialchars($img); ?>">Update</button>
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

                    <div id="newOfficerModal" style="display:none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3500; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
                        <div style="background: var(--color-card); border-radius: 20px; text-align: center; max-width: 520px; width: 92%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); color: var(--color-text);">
                            <div style="padding: 32px 32px 16px 32px;">
                                <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 16px; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center;">
                                    <svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2-2-.9-2-2z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 21v-2a4 4 0 00-3-3.87M4 21v-2a4 4 0 013-3.87M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <h2 style="margin: 0 0 8px; font-size: 1.4rem;">Add Officer</h2>
                                <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Enter officer details.</p>
                            </div>
                            <form id="newOfficerForm" style="padding: 0 32px 24px 32px; text-align:left;">
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                                    <div>
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">First Name <span style="color:#ef4444;">*</span></label>
                                        <input name="first_name" type="text" required style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                    </div>
                                    <div>
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Middle Name</label>
                                        <input name="middle_name" type="text" style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                    </div>
                                    <div>
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Last Name <span style="color:#ef4444;">*</span></label>
                                        <input name="last_name" type="text" required style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                    </div>
                                    <div>
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Suffix</label>
                                        <input name="suffix" type="text" style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                    </div>
                                    <div style="grid-column:1 / -1;">
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Position/Title <span style="color:#ef4444;">*</span></label>
                                        <input name="title" type="text" required style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                    </div>
                                    <div style="grid-column:1 / -1;">
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Email <span style="color:#ef4444;">*</span></label>
                                        <input name="email" type="email" required style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                    </div>
                                    <div style="grid-column:1 / -1;">
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Profile Image URL</label>
                                        <input name="profile_image_url" type="url" placeholder="https://..." style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                    </div>
                                </div>
                                <div style="padding: 20px 0 0; display:flex; gap:12px;">
                                    <button type="button" id="cancelNewOfficerBtn" style="flex:1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Cancel</button>
                                    <button type="button" id="confirmNewOfficerBtn" style="flex:1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Add Officer</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div id="updateOfficerModal" style="display:none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3500; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
                        <div style="background: var(--color-card); border-radius: 20px; text-align: center; max-width: 520px; width: 92%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); color: var(--color-text);">
                            <div style="padding: 32px 32px 16px 32px;">
                                <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 16px; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center;">
                                    <svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 20h9" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 3.5a2.121 2.121 0 113 3L7 19l-4 1 1-4 12.5-12.5z" />
                                    </svg>
                                </div>
                                <h2 style="margin: 0 0 8px; font-size: 1.4rem;">Update Officer</h2>
                                <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Edit officer details.</p>
                            </div>
                            <form id="updateOfficerForm" style="padding: 0 32px 24px 32px; text-align:left;">
                                <input type="hidden" name="id" />
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                                    <div>
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">First Name <span style="color:#ef4444;">*</span></label>
                                        <input name="first_name" type="text" required style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                    </div>
                                    <div>
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Middle Name</label>
                                        <input name="middle_name" type="text" style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                    </div>
                                    <div>
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Last Name <span style="color:#ef4444;">*</span></label>
                                        <input name="last_name" type="text" required style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                    </div>
                                    <div>
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Suffix</label>
                                        <input name="suffix" type="text" style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                    </div>
                                    <div style="grid-column:1 / -1;">
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Position/Title <span style="color:#ef4444;">*</span></label>
                                        <input name="title" type="text" required style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                    </div>
                                    <div style="grid-column:1 / -1;">
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Email <span style="color:#ef4444;">*</span></label>
                                        <input name="email" type="email" required style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                    </div>
                                    <div style="grid-column:1 / -1;">
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Profile Image URL</label>
                                        <input name="profile_image_url" type="url" placeholder="https://..." style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                    </div>
                                </div>
                                <div style="padding: 20px 0 0; display:flex; gap:12px;">
                                    <button type="button" id="cancelUpdateOfficerBtn" style="flex:1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Cancel</button>
                                    <button type="button" id="confirmUpdateOfficerBtn" style="flex:1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Update Officer</button>
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
                var w = document.getElementById('modalIconWrap'),
                    s = document.getElementById('modalIconSvg');
                if (!w || !s) return;
                var bg = 'linear-gradient(135deg, #18a558 0%, #136515 100%)';
                var path = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />';
                if (kind === 'error') {
                    bg = 'linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)';
                    path = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />';
                } else if (kind === 'confirm') {
                    bg = 'linear-gradient(135deg, #18a558 0%, #136515 100%)';
                    path = '<text x="12" y="16" text-anchor="middle" font-size="16" font-weight="700" fill="currentColor">?</text>';
                }
                w.style.background = bg;
                s.innerHTML = path;
            }

            var table = document.getElementById('officersTable');
            var tbody = table ? table.querySelector('tbody') : null;
            var headers = table ? table.querySelectorAll('th.sortable') : [];
            var searchInput = document.getElementById('searchQuery');

            var currentSort = {
                column: null,
                direction: 'asc'
            };

            function getColumnIndex(key) {
                var h = Array.from(headers).find(x => x.dataset.column === key);
                if (!h) return -1;
                return Array.from(h.parentNode.children).indexOf(h);
            }

            function getCellValue(row, key) {
                var idx = getColumnIndex(key);
                var cell = row.children[idx];
                if (!cell) return '';
                return cell.textContent.trim();
            }

            function compareText(a, b) {
                return a.toLowerCase().localeCompare(b.toLowerCase());
            }

            function compareNumeric(a, b) {
                var na = parseFloat(String(a).replace(/[^\d.-]/g, '')) || 0;
                var nb = parseFloat(String(b).replace(/[^\d.-]/g, '')) || 0;
                return na - nb;
            }

            headers.forEach(h => {
                h.addEventListener('click', function() {
                    var key = this.dataset.column;
                    var dir = (currentSort.column === key && currentSort.direction === 'asc') ? 'desc' : 'asc';
                    currentSort = {
                        column: key,
                        direction: dir
                    };
                    headers.forEach(x => x.classList.remove('sorted-asc', 'sorted-desc'));
                    this.classList.add(dir === 'asc' ? 'sorted-asc' : 'sorted-desc');
                    var rows = Array.from(tbody.querySelectorAll('tr'));
                    rows.sort(function(r1, r2) {
                        var v1 = getCellValue(r1, key),
                            v2 = getCellValue(r2, key);
                        if (key === 'id') {
                            return dir === 'asc' ? compareNumeric(v1, v2) : compareNumeric(v2, v1);
                        }
                        return dir === 'asc' ? compareText(v1, v2) : compareText(v2, v1);
                    });
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
                if (!noRow) {
                    noRow = document.createElement('tr');
                    noRow.id = 'noResultsRow';
                    var td = document.createElement('td');
                    var colCount = table.querySelectorAll('thead th').length;
                    td.colSpan = colCount;
                    td.style.textAlign = 'center';
                    td.style.color = 'var(--color-text-light)';
                    td.textContent = 'No matching officers.';
                    noRow.appendChild(td);
                    tbody.appendChild(noRow);
                }
                noRow.style.display = visible === 0 ? '' : 'none';
            }
            if (searchInput) {
                filterRows(searchInput.value);
                searchInput.addEventListener('input', function() {
                    filterRows(this.value);
                    if (window.officersPagination) {
                        window.officersPagination.updateFilteredRows();
                        window.officersPagination.currentPage = 1;
                        window.officersPagination.updateDisplay();
                    }
                });
            }

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
                    modalMessage.textContent = 'Change status to \"' + next + '\"?';
                    window.setModalIcon && window.setModalIcon('confirm');
                    confirmationModal.style.display = 'flex';
                    pending = {
                        id,
                        next,
                        btn
                    };
                }
            });
            modalCancelBtn?.addEventListener('click', function() {
                pending = null;
                if (confirmationModal) confirmationModal.style.display = 'none';
            });
            modalConfirmBtn?.addEventListener('click', async function() {
                if (!pending) return;
                confirmationModal.style.display = 'none';
                var id = pending.id,
                    next = pending.next,
                    btn = pending.btn;
                pending = null;
                try {
                    window.showLoader && window.showLoader();
                    var resp = await fetch('officer_management.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            action: 'update_officer_status',
                            id: id,
                            next_status: next
                        }).toString()
                    });
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
                            if (pill) {
                                pill.className = 'status-pill ' + cls;
                                pill.innerHTML = '<span class="pill-dot"></span> ' + pretty;
                            }
                        }
                        var updated = String(data.next_status || next).toLowerCase();
                        var label = (updated === 'active') ? 'Archive' : 'Activate';
                        var next2 = (updated === 'active') ? 'archived' : 'active';
                        btn.textContent = label;
                        btn.dataset.current = updated;
                        btn.dataset.next = next2;
                    } else {
                        if (modalTitle && modalMessage && confirmationModal) {
                            modalTitle.textContent = 'Update Failed';
                            modalMessage.textContent = (data && data.error) ? String(data.error) : 'Failed to update status';
                            window.setModalIcon && window.setModalIcon('error');
                            confirmationModal.style.display = 'flex';
                        }
                    }
                } catch (err) {
                    window.hideLoader && window.hideLoader();
                    if (modalTitle && modalMessage && confirmationModal) {
                        modalTitle.textContent = 'Error';
                        modalMessage.textContent = 'Error updating status';
                        window.setModalIcon && window.setModalIcon('error');
                        confirmationModal.style.display = 'flex';
                    }
                }
            });

            class OfficersPagination {
                constructor() {
                    this.table = document.getElementById('officersTable');
                    this.tbody = this.table?.querySelector('tbody');
                    this.select = document.getElementById('rowsPerPageSelect');
                    this.info = document.getElementById('paginationInfo');
                    this.prev = document.getElementById('prevPage');
                    this.next = document.getElementById('nextPage');
                    this.currentPage = 1;
                    this.rowsPerPage = this.getRowsPerPage();
                    this.allRows = [];
                    this.filteredRows = [];
                    this.init();
                }
                init() {
                    this.updateFilteredRows();
                    this.updateDisplay();
                    this.select?.addEventListener('change', () => {
                        this.rowsPerPage = this.getRowsPerPage();
                        this.currentPage = 1;
                        this.updateDisplay();
                    });
                    this.prev?.addEventListener('click', () => {
                        if (this.currentPage > 1) {
                            this.currentPage--;
                            this.updateDisplay();
                        }
                    });
                    this.next?.addEventListener('click', () => {
                        const totalPages = Math.max(1, Math.ceil(this.filteredRows.length / this.rowsPerPage));
                        if (this.currentPage < totalPages) {
                            this.currentPage++;
                            this.updateDisplay();
                        }
                    });
                }
                getRowsPerPage() {
                    const v = this.select?.value || '10';
                    if (v === 'all') return Infinity;
                    const n = parseInt(v, 10);
                    return isNaN(n) ? 10 : n;
                }
                updateFilteredRows() {
                    this.allRows = Array.from(this.tbody.querySelectorAll('tr')).filter(r => r.id !== 'noResultsRow');
                    this.filteredRows = this.allRows.filter(r => r.style.display !== 'none');
                }
                updateDisplay() {
                    const totalRows = this.filteredRows.length;
                    const perPage = this.rowsPerPage === Infinity ? totalRows : this.rowsPerPage;
                    const totalPages = Math.max(1, Math.ceil(totalRows / (perPage || 1)));
                    const startIndex = (this.currentPage - 1) * (perPage || 1);
                    const endIndex = startIndex + (perPage || 1);
                    this.allRows.forEach(r => r.style.display = 'none');
                    const toShow = this.filteredRows.slice(startIndex, endIndex);
                    toShow.forEach(r => r.style.display = '');
                    if (this.info) {
                        const startItem = totalRows === 0 ? 0 : startIndex + 1;
                        const endItem = Math.min(endIndex, totalRows);
                        this.info.textContent = `Showing ${startItem}-${endItem} • Page ${this.currentPage}`;
                    }
                    if (this.prev) {
                        this.prev.disabled = this.currentPage <= 1;
                        this.prev.classList.toggle('pagination__button--disabled', this.currentPage <= 1);
                    }
                    if (this.next) {
                        this.next.disabled = this.currentPage >= totalPages;
                        this.next.classList.toggle('pagination__button--disabled', this.currentPage >= totalPages);
                    }
                }
            }
            window.officersPagination = new OfficersPagination();
            var messageModal = document.getElementById('messageModal');
            var msgTitle = document.getElementById('msgTitle');
            var msgMessage = document.getElementById('msgMessage');
            var msgOkBtn = document.getElementById('msgOkBtn');
            var msgIconWrap = document.getElementById('msgIconWrap');
            var msgIconSvg = document.getElementById('msgIconSvg');
            var messageOkAction = null;

            function setMsgIcon(kind) {
                var bg = 'linear-gradient(135deg, #18a558 0%, #136515 100%)';
                var path = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />';
                if (kind === 'error') {
                    bg = 'linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)';
                    path = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />';
                } else if (kind === 'info') {
                    bg = 'linear-gradient(135deg, #4f46e5 0%, #3730a3 100%)';
                    path = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8h.01M11 12h2v4h-2z" />';
                } else if (kind === 'success') {
                    bg = 'linear-gradient(135deg, #18a558 0%, #136515 100%)';
                    path = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />';
                }
                if (msgIconWrap) msgIconWrap.style.background = bg;
                if (msgIconSvg) msgIconSvg.innerHTML = path;
            }
            window.showMessage = function(title, message, kind, onOk) {
                if (msgTitle) msgTitle.textContent = title || 'Message';
                if (msgMessage) msgMessage.textContent = message || '';
                setMsgIcon(kind || 'info');
                if (messageModal) messageModal.style.display = 'flex';
                messageOkAction = typeof onOk === 'function' ? onOk : null;
            };
            msgOkBtn?.addEventListener('click', function() {
                if (messageModal) messageModal.style.display = 'none';
                var fn = messageOkAction;
                messageOkAction = null;
                if (typeof fn === 'function') fn();
            });
            var addOfficerBtn = document.getElementById('addOfficerBtn');
            var newOfficerModal = document.getElementById('newOfficerModal');
            var cancelNewOfficerBtn = document.getElementById('cancelNewOfficerBtn');
            var confirmNewOfficerBtn = document.getElementById('confirmNewOfficerBtn');
            var updateOfficerModal = document.getElementById('updateOfficerModal');
            var cancelUpdateOfficerBtn = document.getElementById('cancelUpdateOfficerBtn');
            var confirmUpdateOfficerBtn = document.getElementById('confirmUpdateOfficerBtn');

            addOfficerBtn?.addEventListener('click', function() {
                if (newOfficerModal) newOfficerModal.style.display = 'flex';
            });
            cancelNewOfficerBtn?.addEventListener('click', function() {
                if (newOfficerModal) newOfficerModal.style.display = 'none';
            });
            confirmNewOfficerBtn?.addEventListener('click', async function() {
                var form = document.getElementById('newOfficerForm');
                var fd = new FormData(form);
                var first_name = String(fd.get('first_name') || '').trim();
                var last_name = String(fd.get('last_name') || '').trim();
                var title = String(fd.get('title') || '').trim();
                var email = String(fd.get('email') || '').trim();
                if (!first_name || !last_name || !title || !email) return;
                try {
                    window.showLoader && window.showLoader();
                    var params = new URLSearchParams();
                    params.set('action', 'create_officer');
                    params.set('first_name', first_name);
                    params.set('middle_name', String(fd.get('middle_name') || ''));
                    params.set('last_name', last_name);
                    params.set('suffix', String(fd.get('suffix') || ''));
                    params.set('title', title);
                    params.set('email', email);
                    params.set('profile_image_url', String(fd.get('profile_image_url') || ''));
                    var resp = await fetch('officer_management.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: params.toString()
                    });
                    var data = await resp.json();
                    window.hideLoader && window.hideLoader();
                    if (data && data.ok) {
                        newOfficerModal.style.display = 'none';
                        showMessage('Officer Added', 'New officer created successfully.', 'success', function() {
                            location.reload();
                        });
                    } else {
                        newOfficerModal.style.display = 'none';
                        showMessage('Create Failed', (data && data.error) ? String(data.error) : 'Failed to add officer', 'error');
                    }
                } catch (err) {
                    window.hideLoader && window.hideLoader();
                    window.setModalIcon && window.setModalIcon('error');
                    confirmationModal.style.display = 'flex';
                    modalTitle.textContent = 'Error';
                    modalMessage.textContent = 'Error adding officer';
                }
            });

            tbody?.addEventListener('click', function(e) {
                var ubtn = e.target.closest('.btn-update');
                if (!ubtn) return;
                if (updateOfficerModal) {
                    updateOfficerModal.style.display = 'flex';
                    var form = document.getElementById('updateOfficerForm');
                    form.querySelector('[name="id"]').value = ubtn.dataset.id || '';
                    form.querySelector('[name="first_name"]').value = ubtn.dataset.firstName || '';
                    form.querySelector('[name="middle_name"]').value = ubtn.dataset.middleName || '';
                    form.querySelector('[name="last_name"]').value = ubtn.dataset.lastName || '';
                    form.querySelector('[name="suffix"]').value = ubtn.dataset.suffix || '';
                    form.querySelector('[name="title"]').value = ubtn.dataset.title || '';
                    form.querySelector('[name="email"]').value = ubtn.dataset.email || '';
                    form.querySelector('[name="profile_image_url"]').value = ubtn.dataset.profile || '';
                }
            });
            cancelUpdateOfficerBtn?.addEventListener('click', function() {
                if (updateOfficerModal) updateOfficerModal.style.display = 'none';
            });
            confirmUpdateOfficerBtn?.addEventListener('click', async function() {
                var form = document.getElementById('updateOfficerForm');
                var fd = new FormData(form);
                var id = Number(fd.get('id') || 0);
                var first_name = String(fd.get('first_name') || '').trim();
                var last_name = String(fd.get('last_name') || '').trim();
                var title = String(fd.get('title') || '').trim();
                var email = String(fd.get('email') || '').trim();
                if (!id || !first_name || !last_name || !title || !email) return;
                try {
                    window.showLoader && window.showLoader();
                    var params = new URLSearchParams();
                    params.set('action', 'update_officer');
                    params.set('id', String(id));
                    params.set('first_name', first_name);
                    params.set('middle_name', String(fd.get('middle_name') || ''));
                    params.set('last_name', last_name);
                    params.set('suffix', String(fd.get('suffix') || ''));
                    params.set('title', title);
                    params.set('email', email);
                    params.set('profile_image_url', String(fd.get('profile_image_url') || ''));
                    var resp = await fetch('officer_management.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: params.toString()
                    });
                    var data = await resp.json();
                    window.hideLoader && window.hideLoader();
                    if (data && data.ok) {
                        updateOfficerModal.style.display = 'none';
                        var row = Array.from(tbody.querySelectorAll('tr')).find(r => {
                            var cell = r.children[0];
                            return cell && Number(cell.textContent.trim()) === id;
                        });
                        if (row) {
                            var full = `${first_name} ${String(fd.get('middle_name')||'').trim()} ${last_name}`.replace(/\s+/g, ' ').trim();
                            var pos = title;
                            row.children[getColumnIndex('fullname')].textContent = full || 'N/A';
                            row.children[getColumnIndex('position')].textContent = pos || 'N/A';
                            var profileCell = row.children[getColumnIndex('status') + 1];
                            var url = String(fd.get('profile_image_url') || '').trim();
                            profileCell.innerHTML = url ? `<div class="avatar"><img src="${url}" alt="Profile"></div>` : 'N/A';
                        }
                        showMessage('Officer Updated', 'Changes saved successfully.', 'success');
                    } else {
                        updateOfficerModal.style.display = 'none';
                        showMessage('Update Failed', (data && data.error) ? String(data.error) : 'Failed to update officer', 'error');
                    }
                } catch (err) {
                    window.hideLoader && window.hideLoader();
                    updateOfficerModal.style.display = 'none';
                    showMessage('Error', 'Error updating officer', 'error');
                }
            });
        });
    </script>
</body>

</html>