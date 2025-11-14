<?php
require_once 'middleware/auth.php';
date_default_timezone_set('Asia/Manila');
ob_start();
require_once 'connection/db_connect.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = isset($_POST['action']) ? $_POST['action'] : '';
    if ($act === 'create_tag') {
        $name = trim($_POST['name'] ?? '');
        $hex = trim($_POST['hex_color'] ?? '');
        if ($name !== '') {
            $stmt = $conn->prepare('INSERT INTO announcement_tag(name, hex_color) VALUES(?, ?)');
            $stmt->bind_param('ss', $name, $hex);
            $stmt->execute();
            $stmt->close();
            $msg = 'Tag created successfully';
            $kind = 'success';
        } else {
            $msg = 'Please enter tag name';
            $kind = 'error';
        }
        header('Location: announcement_management.php?msg=' . urlencode($msg) . '&kind=' . urlencode($kind));
        exit;
    } elseif ($act === 'update_tag') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $hex = trim($_POST['hex_color'] ?? '');
        $old = trim($_POST['old_name'] ?? '');
        if ($id > 0 && $name !== '') {
            $stmt = $conn->prepare('UPDATE announcement_tag SET name = ?, hex_color = ? WHERE id = ?');
            $stmt->bind_param('ssi', $name, $hex, $id);
            $stmt->execute();
            $stmt->close();
            if ($old !== '' && $old !== $name) {
                $stmt2 = $conn->prepare('UPDATE announcement SET tag = ? WHERE tag = ?');
                $stmt2->bind_param('ss', $name, $old);
                $stmt2->execute();
                $stmt2->close();
            }
            $msg = 'Tag updated successfully';
            $kind = 'success';
        } else {
            $msg = 'Invalid tag data';
            $kind = 'error';
        }
        header('Location: announcement_management.php?msg=' . urlencode($msg) . '&kind=' . urlencode($kind));
        exit;
    } elseif ($act === 'delete_tag') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare('DELETE FROM announcement_tag WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $msg = 'Tag deleted successfully';
            $kind = 'success';
        } else {
            $msg = 'Invalid tag id';
            $kind = 'error';
        }
        header('Location: announcement_management.php?msg=' . urlencode($msg) . '&kind=' . urlencode($kind));
        exit;
    } elseif ($act === 'create_announcement') {
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $tag = trim($_POST['tag'] ?? '');
        if ($title !== '' && $desc !== '') {
            $stmt = $conn->prepare('INSERT INTO announcement(title, description, tag) VALUES(?, ?, ?)');
            $stmt->bind_param('sss', $title, $desc, $tag);
            $stmt->execute();
            $stmt->close();
            $msg = 'Announcement created successfully';
            $kind = 'success';
        } else {
            $msg = 'Please complete required fields';
            $kind = 'error';
        }
        header('Location: announcement_management.php?msg=' . urlencode($msg) . '&kind=' . urlencode($kind));
        exit;
    } elseif ($act === 'update_announcement') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $tag = trim($_POST['tag'] ?? '');
        if ($id > 0 && $title !== '' && $desc !== '') {
            $stmt = $conn->prepare('UPDATE announcement SET title = ?, description = ?, tag = ? WHERE id = ?');
            $stmt->bind_param('sssi', $title, $desc, $tag, $id);
            $stmt->execute();
            $stmt->close();
            $msg = 'Announcement updated successfully';
            $kind = 'success';
        } else {
            $msg = 'Invalid announcement data';
            $kind = 'error';
        }
        header('Location: announcement_management.php?msg=' . urlencode($msg) . '&kind=' . urlencode($kind));
        exit;
    } elseif ($act === 'toggle_announcement_status') {
        $id = (int)($_POST['id'] ?? 0);
        $next = strtolower(trim((string)($_POST['next_status'] ?? 'archived')));
        if ($id > 0 && in_array($next, ['active', 'archived'], true)) {
            $stmt = $conn->prepare('UPDATE announcement SET status = ? WHERE id = ?');
            $stmt->bind_param('si', $next, $id);
            $stmt->execute();
            $stmt->close();
            $msg = 'Status updated to ' . ($next === 'active' ? 'Active' : 'Archived');
            $kind = 'success';
        } else {
            $msg = 'Invalid status update';
            $kind = 'error';
        }
        header('Location: announcement_management.php?msg=' . urlencode($msg) . '&kind=' . urlencode($kind));
        exit;
    } elseif ($act === 'toggle_announcement_status_json') {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        $next = strtolower(trim((string)($_POST['next_status'] ?? 'archived')));
        if ($id > 0 && in_array($next, ['active', 'archived'], true)) {
            $stmt = $conn->prepare('UPDATE announcement SET status = ? WHERE id = ?');
            $stmt->bind_param('si', $next, $id);
            $ok = $stmt->execute();
            $stmt->close();
            echo json_encode(['ok' => (bool)$ok, 'id' => $id, 'next_status' => $next]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Invalid request']);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Success Office - Announcement Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .button.btn-status {
            border: 1.5px solid rgba(16, 185, 129, 0.35);
            background: var(--color-card);
            color: var(--color-primary);
            font-weight: 100;
            font-size: 0.85rem;
            padding: 8px 14px !important;
            transition: all 0.2s ease;
        }

        #announcementsTable th:nth-child(7) {
            width: 150px !important;
        }

        #announcementsTable td:nth-child(7) {
            width: 150px !important;
        }

        #announcementsTable th:nth-child(3),
        #announcementsTable td:nth-child(3) {
            width: 250px !important;
        }

        .button.btn-status:hover {
            background-color: var(--color-primary);
            color: var(--color-white);
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

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 0.85rem;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            color: #1f2937;
        }

        .badge-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
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
            transition: all .2s ease;
            outline: none;
        }

        .pagination__select:hover {
            border-color: #cbd5e0;
        }

        .pagination__select:focus {
            border-color: #18a558;
            box-shadow: 0 0 0 3px rgba(24, 165, 88, .15);
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

        .modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, .4);
            z-index: 4000;
        }

        .modal__card {
            width: 100%;
            max-width: 520px;
            background: var(--color-card);
            border: 2px solid var(--color-border);
            border-radius: 12px;
            box-shadow: 0 12px 24px rgba(0, 0, 0, .15);
        }

        .modal__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px;
            border-bottom: 2px solid var(--color-border);
        }

        .modal__title {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .modal__body {
            padding: 16px;
            display: grid;
            gap: 12px;
        }

        .modal__footer {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding: 16px;
            border-top: 2px solid var(--color-border);
        }

        .form__group {
            display: grid;
            gap: 6px;
        }

        .form__label {
            font-weight: 600;
            font-size: .9rem;
        }

        .form__input,
        .form__textarea,
        .form__select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid var(--color-border);
            border-radius: 8px;
            background: var(--color-card);
            color: var(--color-text);
        }

        .form__textarea {
            min-height: 120px;
            resize: vertical;
        }

        .button.primary {
            background: #18a558;
            color: #fff;
            border: none;
        }

        .button.danger {
            background: #ef4444;
            color: #fff;
            border: none;
        }

        .button.secondary {
            background: var(--color-card);
            color: var(--color-text);
            border: 2px solid var(--color-border);
        }

        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 5000;
            backdrop-filter: blur(4px);
        }

        .loading-overlay .spinner {
            width: 56px;
            height: 56px;
            border: 5px solid rgba(255, 255, 255, 0.25);
            border-top-color: #18a558;
            border-radius: 50%;
            animation: spin .8s linear infinite;
            margin: 0 auto;
        }

        .loading-overlay .label {
            color: #fff;
            margin-top: 12px;
            font-weight: 600;
            letter-spacing: .02em;
            text-align: center;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/mobile_navbar.php'; ?>
    <div id="loadingOverlay" class="loading-overlay">
        <div>
            <div class="spinner"></div>
            <div class="label">Processing...</div>
        </div>
    </div>
    <?php
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $like = $q !== '' ? ('%' . $q . '%') : null;

    $tags = [];
    $resT = $conn->query('SELECT id, name, hex_color FROM announcement_tag ORDER BY name ASC');
    while ($row = $resT->fetch_assoc()) {
        $tags[] = $row;
    }
    $announcements = [];
    if ($like) {
        $stmt = $conn->prepare('SELECT a.id, a.title, a.description, a.tag, a.status, a.date_added, t.hex_color FROM announcement a LEFT JOIN announcement_tag t ON t.name = a.tag WHERE a.title LIKE ? OR a.description LIKE ? OR a.tag LIKE ? ORDER BY a.date_added DESC');
        $stmt->bind_param('sss', $like, $like, $like);
        $stmt->execute();
        $resA = $stmt->get_result();
        while ($row = $resA->fetch_assoc()) {
            $announcements[] = $row;
        }
        $stmt->close();
    } else {
        $resA = $conn->query('SELECT a.id, a.title, a.description, a.tag, a.status, a.date_added, t.hex_color FROM announcement a LEFT JOIN announcement_tag t ON t.name = a.tag ORDER BY a.date_added DESC');
        while ($row = $resA->fetch_assoc()) {
            $announcements[] = $row;
        }
    }
    ?>
    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <header class="header">
                <div class="header__left">
                    <h1>Announcement Management</h1>
                    <p class="header__subtitle">Manage announcements and tags</p>
                </div>
            </header>
            <section class="section active" style="margin: 0 20px;">
                <div class="table-container">
                    <div class="table-container__header">
                        <h2 class="table-container__title">Announcements</h2>
                        <p class="table-container__subtitle">Create and manage announcements</p>
                    </div>
                    <div class="filtration_container">
                        <form method="GET" action="" style="display:flex; width:100%; justify-content:space-between; gap:12px; align-items:center;">
                            <div class="search_input_container" style="flex:1;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search">
                                    <path d="m21 21-4.34-4.34" />
                                    <circle cx="11" cy="11" r="8" />
                                </svg>
                                <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search announcements..." aria-label="Search announcements">
                            </div>

                            <div class="search_button_container" style="flex:0 0 auto; display:flex; gap:8px; align-items:center;">
                                <button class="button export" type="button" id="addNewTagBtn">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;">
                                        <path d="M5 12h14" />
                                        <path d="M12 5v14" />
                                    </svg>
                                    Add Tag
                                </button>
                            </div>

                            <div class="search_button_container" style="flex:0 0 auto; display:flex; gap:8px; align-items:center;">
                                <button class="button export" type="button" id="addAnnouncementBtn">Add Announcement</button>
                            </div>

                        </form>
                    </div>
                    <div class="table-wrapper">
                        <table class="table" id="announcementsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th>Tag</th>
                                    <th>Status</th>
                                    <th>Date Added</th>
                                    <th style="width:150px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($announcements)): ?>
                                    <tr id="noAnnouncementsRow">
                                        <td colspan="6" style="text-align:center; color: var(--color-text-light);">No announcements found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($announcements as $a): ?>
                                        <tr>
                                            <td><?php echo (int)$a['id']; ?></td>
                                            <td style="width:200px; white-space: normal; word-break: break-word;"><?php echo htmlspecialchars($a['title']); ?></td>
                                            <td style="width:350px; white-space: normal; word-break: break-word;"><?php echo nl2br(htmlspecialchars($a['description'])); ?></td>
                                            <td>
                                                <?php $c = trim((string)($a['hex_color'] ?? ''));
                                                $tn = trim((string)($a['tag'] ?? ''));
                                                $dot = $c !== '' ? $c : '#9ca3af'; ?>
                                                <span class="badge"><span class="badge-dot" style="background: <?php echo htmlspecialchars($dot); ?>;"></span><?php echo htmlspecialchars($tn !== '' ? $tn : 'None'); ?></span>
                                            </td>
                                            <td>
                                                <?php $st = strtolower(trim((string)($a['status'] ?? 'archived')));
                                                $cls = $st === 'active' ? 'pill-active' : 'pill-negative';
                                                $pretty = $st === 'active' ? 'Active' : 'Archived'; ?>
                                                <span class="status-pill <?php echo $cls; ?>"><span class="pill-dot"></span> <?php echo htmlspecialchars($pretty); ?></span>
                                            </td>
                                            <td>
                                                <?php $ts = strtotime((string)($a['date_added'] ?? ''));
                                                $formatted = $ts ? date('M d, Y h:i A', $ts) : ''; ?>
                                                <span class="date-display" data-ts="<?php echo htmlspecialchars((string)$ts); ?>"><?php echo htmlspecialchars($formatted); ?></span>
                                            </td>
                                            <td style="width:150px;">
                                                <button class="button btn-status" type="button" data-edit-announcement='{"id":<?php echo (int)$a['id']; ?>,"title":"<?php echo htmlspecialchars($a['title']); ?>","description":"<?php echo htmlspecialchars(str_replace(["\r", "\n"], ['', '\n'], $a['description'])); ?>","tag":"<?php echo htmlspecialchars($a['tag']); ?>"}'>Update</button>
                                                <?php $st2 = strtolower(trim((string)($a['status'] ?? 'archived')));
                                                $toggleLabel = $st2 === 'active' ? 'Archive' : 'Activate';
                                                $nextStatus = $st2 === 'active' ? 'archived' : 'active'; ?>
                                                <button type="button" class="button btn-status" data-id="<?php echo (int)$a['id']; ?>" data-current="<?php echo htmlspecialchars($st2); ?>" data-next="<?php echo htmlspecialchars($nextStatus); ?>"><?php echo htmlspecialchars($toggleLabel); ?></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="pagination">
                        <div class="pagination__left"><span class="pagination__label">Rows per page:</span>
                            <select class="pagination__select" id="rowsPerPageSelectAnnouncements">
                                <option value="10">10</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                                <option value="all">All</option>
                            </select>
                        </div>
                        <div class="pagination__center"><span class="pagination__info" id="paginationInfoAnnouncements">Showing 1-10 • Page 1</span></div>
                    </div>
                </div>
            </section>
            <section class="section" style="margin: 20px;">
                <div class="table-container">
                    <div class="table-container__header">
                        <h2 class="table-container__title">Tags</h2>
                        <p class="table-container__subtitle">Manage announcement tags</p>
                    </div>
                    <div style="display:flex; justify-content:flex-end; margin-bottom: 8px;">
                        <button class="button export" type="button" id="addTagBtn">Add Tag</button>
                    </div>
                    <div class="table-wrapper">
                        <table class="table" id="tagsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Color</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tags)): ?>
                                    <tr id="noTagsRow">
                                        <td colspan="4" style="text-align:center; color: var(--color-text-light);">No tags found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tags as $t): ?>
                                        <tr>
                                            <td><?php echo (int)$t['id']; ?></td>
                                            <td><?php echo htmlspecialchars($t['name']); ?></td>
                                            <td>
                                                <?php $hc = trim((string)($t['hex_color'] ?? '')); ?>
                                                <span class="badge"><span class="badge-dot" style="background: <?php echo htmlspecialchars($hc !== '' ? $hc : '#9ca3af'); ?>;"></span><?php echo htmlspecialchars($hc !== '' ? $hc : 'None'); ?></span>
                                            </td>
                                            <td>
                                                <button class="button secondary" type="button" data-edit-tag='{"id":<?php echo (int)$t['id']; ?>,"name":"<?php echo htmlspecialchars($t['name']); ?>","hex_color":"<?php echo htmlspecialchars($t['hex_color']); ?>"}'>Edit</button>
                                                <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Delete this tag?');">
                                                    <input type="hidden" name="action" value="delete_tag">
                                                    <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                                                    <button class="button danger" type="submit">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="pagination">
                        <div class="pagination__left"><span class="pagination__label">Rows per page:</span>
                            <select class="pagination__select" id="rowsPerPageSelectTags">
                                <option value="10">10</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                                <option value="all">All</option>
                            </select>
                        </div>
                        <div class="pagination__center"><span class="pagination__info" id="paginationInfoTags">Showing 1-10 • Page 1</span></div>
                    </div>
                </div>
            </section>
        </main>
    </div>
    <div id="announcementModalOverlay" class="modal-overlay" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3500; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
        <div role="dialog" aria-modal="true" aria-labelledby="announcementModalTitle" style="background: var(--color-card); border-radius: 20px; text-align: left; max-width: 520px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); color: var(--color-text); position: relative;">
            <button type="button" id="closeAnnouncementModalBtn" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096;">
                <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <form method="POST" action="" id="announcementForm">
                <div style="padding: 40px 32px 24px 32px; text-align: center;">
                    <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 24px auto; display: flex; align-items: center; justify-content: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus-icon lucide-plus">
                            <path d="M5 12h14" />
                            <path d="M12 5v14" />
                        </svg>
                    </div>
                    <h3 id="announcementModalTitle" style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.4rem; font-weight: 700; letter-spacing: -0.025em;">Add Announcement</h3>
                    <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Create a new announcement and optionally assign a tag.</p>
                </div>
                <div style="padding: 0 32px 8px 32px; display: grid; gap: 10px;">
                    <input type="hidden" name="action" value="create_announcement">
                    <input type="hidden" name="id" value="">
                    <div style="display:grid; gap:6px;">
                        <label style="font-weight:600; font-size:.95rem;">Title <span style="color:#e53e3e">*</span></label>
                        <input style="width:100%; padding:12px; border:2px solid var(--color-border); border-radius:12px; background:var(--color-card); color:var(--color-text);" type="text" name="title" required>
                    </div>
                    <div style="display:grid; gap:6px;">
                        <label style="font-weight:600; font-size:.95rem;">Description <span style="color:#e53e3e">*</span></label>
                        <textarea style="width:100%; padding:12px; border:2px solid var(--color-border); border-radius:12px; background:var(--color-card); color:var(--color-text); min-height:140px; resize:vertical;" name="description" required></textarea>
                    </div>
                    <div style="display:grid; gap:6px;">
                        <label style="font-weight:600; font-size:.95rem;">Tag</label>
                        <select style="width:100%; padding:12px; border:2px solid var(--color-border); border-radius:12px; background:var(--color-card); color:var(--color-text);" name="tag">
                            <option value="">None</option>
                            <?php foreach ($tags as $t): ?>
                                <option value="<?php echo htmlspecialchars($t['name']); ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="padding: 20px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
                    <button type="button" id="cancelAnnouncement" style="flex: 1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
                    <button type="submit" id="saveAnnouncement" style="flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Save</button>
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
    <div id="messageModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3500; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
        <div style="background: var(--color-card); border-radius: 20px; text-align: center; max-width: 400px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); color: var(--color-text);">
            <div style="padding: 32px 32px 16px 32px;">
                <div id="messageIconWrap" style="width: 56px; height: 56px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 16px; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center;">
                    <svg id="messageIconSvg" style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h2 id="messageTitle" style="margin: 0 0 8px; font-size: 1.4rem;">Message</h2>
                <p id="messageText" style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">...</p>
            </div>
            <div style="padding: 20px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
                <button type="button" id="messageCloseBtn" style="flex: 1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Close</button>
            </div>
        </div>
    </div>
    <div id="tagModalOverlay" class="modal-overlay" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3500; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
        <div role="dialog" aria-modal="true" aria-labelledby="tagModalTitle" style="background: var(--color-card); border-radius: 20px; text-align: left; max-width: 480px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); color: var(--color-text); position: relative;">
            <button type="button" id="closeTagModalBtn" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096;">
                <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <form method="POST" action="" id="tagForm">
                <div style="padding: 40px 32px 24px 32px; text-align: center;">
                    <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 24px auto; display: flex; align-items: center; justify-content: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus-icon lucide-plus">
                            <path d="M5 12h14" />
                            <path d="M12 5v14" />
                        </svg>
                    </div>
                    <h3 id="tagModalTitle" style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.4rem; font-weight: 700; letter-spacing: -0.025em;">Add Tag</h3>
                    <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Create or edit a tag used to categorize announcements.</p>
                </div>
                <div style="padding: 0 32px 8px 32px; display: grid; gap: 10px;">
                    <input type="hidden" name="action" value="create_tag">
                    <input type="hidden" name="id" value="">
                    <input type="hidden" name="old_name" value="">
                    <div style="display:grid; gap:6px;">
                        <label style="font-weight:600; font-size:.95rem;">Name <span style="color:#e53e3e">*</span></label>
                        <input style="width:100%; padding:12px; border:2px solid var(--color-border); border-radius:12px; background:var(--color-card); color:var(--color-text);" type="text" name="name" required>
                    </div>
                    <div style="display:grid; gap:6px;">
                        <label style="font-weight:600; font-size:.95rem;">Hex Color <span style="color:#e53e3e">*</span></label>
                        <input style="width:100%; padding:12px; border:2px solid var(--color-border); border-radius:12px; background:var(--color-card); color:var(--color-text);" type="color" name="hex_color" placeholder="#18a558" required>
                    </div>
                </div>
                <div style="padding: 20px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
                    <button type="button" id="cancelTag" style="flex: 1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
                    <button type="submit" id="saveTag" style="flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Save</button>
                </div>
            </form>
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
        document.addEventListener('DOMContentLoaded', function() {
            window.setModalIcon = function(kind) {
                var wrap = document.getElementById('modalIconWrap');
                var svg = document.getElementById('modalIconSvg');
                if (!wrap || !svg) return;
                var bg = 'linear-gradient(135deg, #18a558 0%, #136515 100%)';
                var path = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />';
                if (kind === 'error') {
                    bg = 'linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)';
                    path = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />';
                } else if (kind === 'confirm') {
                    bg = 'linear-gradient(135deg, #18a558 0%, #136515 100%)';
                    path = '<text x="12" y="16" text-anchor="middle" font-size="16" font-weight="700" fill="currentColor">?</text>';
                } else if (kind === 'success') {
                    bg = 'linear-gradient(135deg, #18a558 0%, #136515 100%)';
                }
                wrap.style.background = bg;
                svg.innerHTML = path;
            };
            window.setMessageIcon = function(kind) {
                var wrap = document.getElementById('messageIconWrap');
                var svg = document.getElementById('messageIconSvg');
                if (!wrap || !svg) return;
                var bg = 'linear-gradient(135deg, #18a558 0%, #136515 100%)';
                var path = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />';
                if (kind === 'error') {
                    bg = 'linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)';
                    path = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />';
                } else if (kind === 'success') {
                    bg = 'linear-gradient(135deg, #18a558 0%, #136515 100%)';
                }
                wrap.style.background = bg;
                svg.innerHTML = path;
            };
            var addAnnouncementBtn = document.getElementById('addAnnouncementBtn');
            var announcementOverlay = document.getElementById('announcementModalOverlay');
            var announcementForm = document.getElementById('announcementForm');
            var announcementModalTitle = document.getElementById('announcementModalTitle');
            var closeAnnouncementModal = document.getElementById('closeAnnouncementModalBtn');
            var cancelAnnouncement = document.getElementById('cancelAnnouncement');
            addAnnouncementBtn.addEventListener('click', function() {
                announcementModalTitle.textContent = 'Add Announcement';
                announcementForm.action = '';
                announcementForm.querySelector('input[name=action]').value = 'create_announcement';
                announcementForm.querySelector('input[name=id]').value = '';
                announcementForm.querySelector('input[name=title]').value = '';
                announcementForm.querySelector('textarea[name=description]').value = '';
                var sel = announcementForm.querySelector('select[name=tag]');
                if (sel) sel.value = '';
                announcementOverlay.style.display = 'flex';
            });
            closeAnnouncementModal.addEventListener('click', function() {
                announcementOverlay.style.display = 'none';
            });
            cancelAnnouncement.addEventListener('click', function() {
                announcementOverlay.style.display = 'none';
            });
            announcementOverlay.addEventListener('click', function(e) {
                if (e.target === announcementOverlay) {
                    announcementOverlay.style.display = 'none';
                }
            });
            document.querySelectorAll('[data-edit-announcement]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var data = JSON.parse(btn.getAttribute('data-edit-announcement'));
                    announcementModalTitle.textContent = 'Edit Announcement';
                    announcementForm.querySelector('input[name=action]').value = 'update_announcement';
                    announcementForm.querySelector('input[name=id]').value = data.id;
                    announcementForm.querySelector('input[name=title]').value = data.title;
                    announcementForm.querySelector('textarea[name=description]').value = data.description.replaceAll('\\n', '\n');
                    var sel = announcementForm.querySelector('select[name=tag]');
                    if (sel) sel.value = data.tag || '';
                    announcementOverlay.style.display = 'flex';
                });
            });
            if (announcementForm) {
                announcementForm.addEventListener('submit', function() {
                    if (typeof window.showLoader === 'function') window.showLoader();
                });
            }
            var addTagBtn = document.getElementById('addTagBtn');
            var addNewTagBtn = document.getElementById('addNewTagBtn');
            var tagOverlay = document.getElementById('tagModalOverlay');
            var tagForm = document.getElementById('tagForm');
            var tagModalTitle = document.getElementById('tagModalTitle');
            var closeTagModal = document.getElementById('closeTagModalBtn');
            var cancelTag = document.getElementById('cancelTag');
            addTagBtn.addEventListener('click', function() {
                tagModalTitle.textContent = 'Add Tag';
                tagForm.querySelector('input[name=action]').value = 'create_tag';
                tagForm.querySelector('input[name=id]').value = '';
                tagForm.querySelector('input[name=old_name]').value = '';
                tagForm.querySelector('input[name=name]').value = '';
                tagForm.querySelector('input[name=hex_color]').value = '';
                tagOverlay.style.display = 'flex';
            });
            addNewTagBtn.addEventListener('click', function() {
                tagModalTitle.textContent = 'Add Tag';
                tagForm.querySelector('input[name=action]').value = 'create_tag';
                tagForm.querySelector('input[name=id]').value = '';
                tagForm.querySelector('input[name=old_name]').value = '';
                tagForm.querySelector('input[name=name]').value = '';
                tagForm.querySelector('input[name=hex_color]').value = '';
                tagOverlay.style.display = 'flex';
            });
            closeTagModal.addEventListener('click', function() {
                tagOverlay.style.display = 'none';
            });
            cancelTag.addEventListener('click', function() {
                tagOverlay.style.display = 'none';
            });
            tagOverlay.addEventListener('click', function(e) {
                if (e.target === tagOverlay) {
                    tagOverlay.style.display = 'none';
                }
            });
            document.querySelectorAll('[data-edit-tag]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var data = JSON.parse(btn.getAttribute('data-edit-tag'));
                    tagModalTitle.textContent = 'Edit Tag';
                    tagForm.querySelector('input[name=action]').value = 'update_tag';
                    tagForm.querySelector('input[name=id]').value = data.id;
                    tagForm.querySelector('input[name=old_name]').value = data.name;
                    tagForm.querySelector('input[name=name]').value = data.name;
                    tagForm.querySelector('input[name=hex_color]').value = data.hex_color || '';
                    tagOverlay.style.display = 'flex';
                });
            });
            if (tagForm) {
                tagForm.addEventListener('submit', function() {
                    if (typeof window.showLoader === 'function') window.showLoader();
                });
            }
            var confirmationModal = document.getElementById('confirmationModal');
            var modalTitle = document.getElementById('modalTitle');
            var modalMessage = document.getElementById('modalMessage');
            var modalConfirmBtn = document.getElementById('modalConfirmBtn');
            var modalCancelBtn = document.getElementById('modalCancelBtn');
            var messageModal = document.getElementById('messageModal');
            var messageTitle = document.getElementById('messageTitle');
            var messageText = document.getElementById('messageText');
            var messageCloseBtn = document.getElementById('messageCloseBtn');
            var pendingStatus = null;
            var flashMsg = <?php echo json_encode($_GET['msg'] ?? ''); ?>;
            var flashKind = <?php echo json_encode($_GET['kind'] ?? ''); ?>;
            if (flashMsg) {
                if (messageTitle && messageText && messageModal) {
                    messageTitle.textContent = (flashKind === 'error') ? 'Update Failed' : 'Success';
                    messageText.textContent = flashMsg;
                    window.setMessageIcon && window.setMessageIcon((flashKind === 'error') ? 'error' : 'success');
                    messageModal.style.display = 'flex';
                }
            }

            var annTbody = document.getElementById('announcementsTable')?.querySelector('tbody');
            if (annTbody) {
                annTbody.addEventListener('click', function(e) {
                    var btn = e.target.closest('.btn-status');
                    if (!btn) return;
                    var id = btn.dataset.id;
                    var next = btn.dataset.next;
                    if (!id || !next) return;
                    if (modalTitle && modalMessage && confirmationModal) {
                        modalTitle.textContent = 'Confirm Status Update';
                        modalMessage.textContent = 'Change status to "' + next + '"?';
                        window.setModalIcon && window.setModalIcon('confirm');
                        confirmationModal.style.display = 'flex';
                        pendingStatus = {
                            id: id,
                            next: next,
                            btn: btn
                        };
                    }
                });
            }
            modalCancelBtn?.addEventListener('click', function() {
                pendingStatus = null;
                confirmationModal.style.display = 'none';
            });
            modalConfirmBtn?.addEventListener('click', async function() {
                if (!pendingStatus) return;
                confirmationModal.style.display = 'none';
                var id = pendingStatus.id;
                var next = pendingStatus.next;
                var btn = pendingStatus.btn;
                pendingStatus = null;
                try {
                    if (window.showLoader) window.showLoader();
                    var resp = await fetch('announcement_management.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            action: 'toggle_announcement_status_json',
                            id: String(id),
                            next_status: String(next)
                        }).toString()
                    });
                    var data = await resp.json();
                    if (window.hideLoader) window.hideLoader();
                    if (data && data.ok) {
                        var row = btn.closest('tr');
                        var statusCell = row && row.children[4];
                        if (statusCell) {
                            var pill = statusCell.querySelector('.status-pill');
                            var updated = String(data.next_status || next).toLowerCase();
                            var cls = (updated === 'active') ? 'pill-active' : 'pill-negative';
                            var pretty = (updated.charAt(0).toUpperCase() + updated.slice(1));
                            if (!pill) {
                                pill = document.createElement('span');
                                pill.className = 'status-pill ' + cls;
                                pill.innerHTML = '<span class="pill-dot"></span> ' + pretty;
                                statusCell.innerHTML = '';
                                statusCell.appendChild(pill);
                            } else {
                                pill.className = 'status-pill ' + cls;
                                pill.innerHTML = '<span class="pill-dot"></span> ' + pretty;
                            }
                        }
                        var updated2 = String(data.next_status || next).toLowerCase();
                        var label = (updated2 === 'active') ? 'Archive' : 'Activate';
                        var next2 = (updated2 === 'active') ? 'archived' : 'active';
                        btn.textContent = label;
                        btn.dataset.current = updated2;
                        btn.dataset.next = next2;
                        if (messageTitle && messageText && messageModal) {
                            messageTitle.textContent = 'Status Updated';
                            messageText.textContent = 'Status updated successfully';
                            window.setModalIcon && window.setModalIcon('success');
                            messageModal.style.display = 'flex';
                        }
                    } else {
                        if (messageTitle && messageText && messageModal) {
                            messageTitle.textContent = 'Update Failed';
                            messageText.textContent = (data && data.error) ? String(data.error) : 'Failed to update status';
                            window.setModalIcon && window.setModalIcon('error');
                            messageModal.style.display = 'flex';
                        }
                    }
                } catch (err) {
                    if (window.hideLoader) window.hideLoader();
                    if (messageTitle && messageText && messageModal) {
                        messageTitle.textContent = 'Error';
                        messageText.textContent = 'Error updating status';
                        window.setModalIcon && window.setModalIcon('error');
                        messageModal.style.display = 'flex';
                    }
                }
            });
            messageCloseBtn?.addEventListener('click', function() {
                messageModal.style.display = 'none';
            });
            messageModal?.addEventListener('click', function(e) {
                if (e.target === messageModal) messageModal.style.display = 'none';
            });

            function TablePagination(tableId, selectId, infoId) {
                this.tbody = document.getElementById(tableId)?.querySelector('tbody');
                this.select = document.getElementById(selectId);
                this.info = document.getElementById(infoId);
                this.currentPage = 1;
                this.rowsPerPage = 10;
                this.allRows = [];
                this.filteredRows = [];
                this.getRowsPerPage = function() {
                    var v = this.select?.value || '10';
                    return v === 'all' ? Infinity : parseInt(v, 10);
                };
                this.init = function() {
                    if (!this.tbody) return;
                    this.allRows = Array.from(this.tbody.querySelectorAll('tr')).filter(function(r) {
                        return r.id !== 'noAnnouncementsRow' && r.id !== 'noTagsRow';
                    });
                    this.filteredRows = this.allRows.filter(function(r) {
                        return r.style.display !== 'none';
                    });
                    this.setup();
                    this.updateDisplay();
                };
                this.setup = function() {
                    var self = this;
                    if (this.select) {
                        this.select.addEventListener('change', function() {
                            self.rowsPerPage = self.getRowsPerPage();
                            self.currentPage = 1;
                            self.updateDisplay();
                        });
                    }
                };
                this.updateFilteredRows = function() {
                    this.allRows = Array.from(this.tbody.querySelectorAll('tr')).filter(function(r) {
                        return r.id !== 'noAnnouncementsRow' && r.id !== 'noTagsRow';
                    });
                    this.filteredRows = this.allRows.filter(function(r) {
                        return r.style.display !== 'none';
                    });
                };
                this.updateDisplay = function() {
                    var totalRows = this.filteredRows.length;
                    var perPage = this.rowsPerPage === Infinity ? totalRows : this.rowsPerPage;
                    var totalPages = Math.max(1, Math.ceil(totalRows / (perPage || 1)));
                    var startIndex = (this.currentPage - 1) * (perPage || 1);
                    var endIndex = startIndex + (perPage || 1);
                    this.allRows.forEach(function(r) {
                        r.style.display = 'none';
                    });
                    var toShow = this.filteredRows.slice(startIndex, endIndex);
                    toShow.forEach(function(r) {
                        r.style.display = '';
                    });
                    if (this.info) {
                        var startItem = totalRows === 0 ? 0 : startIndex + 1;
                        var endItem = Math.min(endIndex, totalRows);
                        var rangeText = this.rowsPerPage === Infinity ? ('Showing 1-' + totalRows) : ('Showing ' + startItem + '-' + endItem);
                        this.info.textContent = rangeText + ' • Page ' + this.currentPage + '/' + totalPages;
                    }
                    var emptyRow = this.tbody.querySelector('#noAnnouncementsRow') || this.tbody.querySelector('#noTagsRow');
                    if (emptyRow) {
                        emptyRow.style.display = totalRows === 0 ? '' : 'none';
                    }
                };
            }
            var annPag = new TablePagination('announcementsTable', 'rowsPerPageSelectAnnouncements', 'paginationInfoAnnouncements');
            annPag.init();
            var tagPag = new TablePagination('tagsTable', 'rowsPerPageSelectTags', 'paginationInfoTags');
            tagPag.init();
            var searchInput = document.querySelector('input[name=q]');

            function filterAnnouncements(query) {
                var q = String(query || '').trim().toLowerCase();
                if (!annPag.tbody) return;
                Array.from(annPag.tbody.querySelectorAll('tr')).forEach(function(r) {
                    if (r.id === 'noAnnouncementsRow') return;
                    var show = q === '' || r.textContent.toLowerCase().indexOf(q) !== -1;
                    r.style.display = show ? '' : 'none';
                });
                annPag.updateFilteredRows();
                annPag.currentPage = 1;
                annPag.updateDisplay();
            }
            if (searchInput) {
                filterAnnouncements(searchInput.value);
                searchInput.addEventListener('input', function() {
                    filterAnnouncements(this.value);
                });
            }
        });
    </script>
</body>

</html>