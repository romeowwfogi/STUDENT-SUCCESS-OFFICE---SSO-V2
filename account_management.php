<?php
// Protect this page
require_once 'middleware/auth.php';

// Ensure timezone is Asia/Manila for date output
date_default_timezone_set('Asia/Manila');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Success Office - Account Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="dashboard.css">
    <style>
        /* Sorting styles aligned with Schedule Management */
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

        /* Page loading overlay */
        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.35);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 3000;
            backdrop-filter: blur(2px);
        }

        .loading-overlay .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid rgba(255, 255, 255, 0.5);
            border-top-color: #1a73e8;
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

        .pagination__bttns:active:not(.pagination__button--disabled) {
            transform: translateY(0);
        }

        .pagination__button--disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: var(--color-hover) !important;
            border-color: var(--color-border) !important;
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

        /* Status pill (match Applicant Types design) */
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

        .pill-warning {
            background: #fff7ed;
            color: #9a3412;
            border: 1px solid #fb923c;
        }

        .pill-warning .pill-dot {
            background: #fb923c;
        }
    </style>
</head>

<body>
    <?php include 'includes/mobile_navbar.php'; ?>
    <?php include 'connection/db_connect.php'; ?>
    <?php require_once 'function/decrypt.php'; ?>

    <!-- Full-screen loader overlay (match design from other pages) -->
    <div id="loadingOverlay" class="loading-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); display: none; justify-content: center; align-items: center; z-index: 5000; backdrop-filter: blur(4px);">
        <div class="loading-spinner" style="text-align:center;">
            <div class="spinner" style="width: 56px; height: 56px; border: 5px solid rgba(255,255,255,0.25); border-top-color: #18a558; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto;"></div>
            <div class="loading-text" style="color:#fff; margin-top:12px; font-weight:600; letter-spacing:0.02em;">Processing...</div>
        </div>
    </div>
    <script>
        // Global loader helpers (consistent with other pages)
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
    </script>

    <?php
    // Helper to resolve plaintext or encrypted emails
    function resolve_email($value)
    {
        $value = trim($value ?? '');
        if ($value === '') return '';
        if (strpos($value, '@') !== false) return $value;
        $decrypted = decryptData($value);
        if ($decrypted && strpos($decrypted, '@') !== false) {
            return $decrypted;
        }
        return $value;
    }
    ?>

    <?php
    // Filters
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $acct = isset($_GET['acct']) ? trim($_GET['acct']) : 'all'; // all | sso | admission | services

    // Unified accounts list
    $accounts = [];

    // Helper to push a normalized account row
    $pushAccount = function ($row, $source) use (&$accounts) {
        $accounts[] = [
            'id' => (int)($row['id'] ?? 0),
            'first_name' => $row['first_name'] ?? '',
            'middle_name' => $row['middle_name'] ?? '',
            'last_name' => $row['last_name'] ?? '',
            'suffix' => $row['suffix'] ?? '',
            'email' => resolve_email($row['email'] ?? ''),
            'status' => $row['status'] ?? '',
            'created_at' => $row['created_at'] ?? '',
            'source' => $source,
        ];
    };

    // Build LIKE param for search
    $like = $q !== '' ? ("%" . $q . "%") : null;

    // SSO accounts (sso_user)
    if ($acct === 'all' || $acct === 'sso') {
        $sql = "SELECT id, first_name, middle_name, last_name, suffix, email, status, created_at FROM sso_user";
        $conds = [];
        $params = [];
        $types = '';
        if ($like !== null) {
            $conds[] = "(first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= 'ssss';
        }
        if ($status !== '') {
            $conds[] = "status = ?";
            $params[] = $status;
            $types .= 's';
        }
        if (!empty($conds)) {
            $sql .= " WHERE " . implode(" AND ", $conds);
        }
        $sql .= " ORDER BY created_at DESC";

        if (!empty($params)) {
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $pushAccount($row, 'sso');
                }
                $stmt->close();
            }
        } else {
            if ($res = $conn->query($sql)) {
                while ($row = $res->fetch_assoc()) {
                    $pushAccount($row, 'sso');
                }
            }
        }
    }

    // Admission accounts (users + user_fullname)
    if ($acct === 'all' || $acct === 'admission') {
        $sqlA = "SELECT u.id, u.email, u.acc_status AS status, u.created_at, uf.first_name, uf.middle_name, uf.last_name, uf.suffix
                 FROM users u
                 LEFT JOIN user_fullname uf ON uf.user_id = u.id
                 WHERE u.acc_type = 'admission'";
        $condsA = [];
        $paramsA = [];
        $typesA = '';
        if ($like !== null) {
            $condsA[] = "(uf.first_name LIKE ? OR uf.middle_name LIKE ? OR uf.last_name LIKE ? OR u.email LIKE ?)";
            $paramsA[] = $like;
            $paramsA[] = $like;
            $paramsA[] = $like;
            $paramsA[] = $like;
            $typesA .= 'ssss';
        }
        if ($status !== '') {
            $condsA[] = "u.acc_status = ?";
            $paramsA[] = $status;
            $typesA .= 's';
        }
        if (!empty($condsA)) {
            $sqlA .= " AND " . implode(" AND ", $condsA);
        }
        $sqlA .= " ORDER BY u.created_at DESC";

        if (!empty($paramsA)) {
            if ($stmtA = $conn->prepare($sqlA)) {
                $stmtA->bind_param($typesA, ...$paramsA);
                $stmtA->execute();
                $resA = $stmtA->get_result();
                while ($row = $resA->fetch_assoc()) {
                    $pushAccount($row, 'admission');
                }
                $stmtA->close();
            }
        } else {
            if ($resA = $conn->query($sqlA)) {
                while ($row = $resA->fetch_assoc()) {
                    $pushAccount($row, 'admission');
                }
            }
        }
    }

    // Services accounts (services_users)
    if ($acct === 'all' || $acct === 'services') {
        $sqlS = "SELECT id, email, first_name, middle_name, last_name, suffix, is_active, created_at FROM services_users";
        $condsS = [];
        $paramsS = [];
        $typesS = '';
        if ($like !== null) {
            $condsS[] = "(first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
            $paramsS[] = $like;
            $paramsS[] = $like;
            $paramsS[] = $like;
            $paramsS[] = $like;
            $typesS .= 'ssss';
        }
        if ($status !== '') {
            if ($status === 'active') {
                $condsS[] = "is_active = 1";
            } elseif ($status === 'inactive') {
                $condsS[] = "is_active = 0";
            }
            // other statuses not applicable
        }
        if (!empty($condsS)) {
            $sqlS .= " WHERE " . implode(" AND ", $condsS);
        }
        $sqlS .= " ORDER BY created_at DESC";

        if (!empty($paramsS)) {
            if ($stmtS = $conn->prepare($sqlS)) {
                $stmtS->bind_param($typesS, ...$paramsS);
                $stmtS->execute();
                $resS = $stmtS->get_result();
                while ($row = $resS->fetch_assoc()) {
                    // Normalize status field
                    $row['status'] = ((int)($row['is_active'] ?? 0) === 1) ? 'active' : 'inactive';
                    unset($row['is_active']);
                    $pushAccount($row, 'services');
                }
                $stmtS->close();
            }
        } else {
            if ($resS = $conn->query($sqlS)) {
                while ($row = $resS->fetch_assoc()) {
                    $row['status'] = ((int)($row['is_active'] ?? 0) === 1) ? 'active' : 'inactive';
                    unset($row['is_active']);
                    $pushAccount($row, 'services');
                }
            }
        }
    }
    ?>

    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="header">
                <div class="header__left">
                    <h1>Account Management</h1>
                    <p class="header__subtitle">List of all accounts</p>
                </div>
            </header>

            <section class="section active" id="accounts_list_section" style="margin: 0 20px;">
                <div class="table-container">
                    <div class="table-container__header">
                        <h2 class="table-container__title">Accounts</h2>
                        <p class="table-container__subtitle">Manage SSO, Admission, Services Accounts</p>
                    </div>

                    <div class="filtration_container">
                        <form method="GET" action="" style="display:flex; width:100%; justify-content:space-between; gap:12px; align-items:center;">
                            <div class="search_input_container" style="flex:1;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search-icon lucide-search">
                                    <path d="m21 21-4.34-4.34" />
                                    <circle cx="11" cy="11" r="8" />
                                </svg>
                                <input type="text" id="searchQuery" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search accounts..." aria-label="Search accounts">
                            </div>

                            <div class="search_button_container" style="flex:0 0 auto; display:flex; gap:8px; align-items:center;">
                                <select name="acct" class="pagination__select" id="acctSelect">
                                    <option value="all" <?php echo $acct === 'all' ? 'selected' : ''; ?>>All Account</option>
                                    <option value="sso" <?php echo $acct === 'sso' ? 'selected' : ''; ?>>SSO Account</option>
                                    <option value="admission" <?php echo $acct === 'admission' ? 'selected' : ''; ?>>Admission Account</option>
                                    <option value="services" <?php echo $acct === 'services' ? 'selected' : ''; ?>>Services Account</option>
                                </select>
                                <button class="button export" id="addAccountBtn" type="button" name="add-sso-account" style="display: <?php echo ($acct === 'sso') ? 'inline-flex' : 'none'; ?>;">Add Account</button>
                            </div>
                        </form>
                    </div>

                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const acctSelect = document.getElementById('acctSelect');
                            const addBtn = document.getElementById('addAccountBtn');
                            const form = acctSelect ? acctSelect.closest('form') : null;
                            if (acctSelect && form) {
                                // Auto-submit on account type change
                                acctSelect.addEventListener('change', function() {
                                    if (addBtn) {
                                        addBtn.style.display = (this.value === 'sso') ? 'inline-flex' : 'none';
                                    }
                                    if (window.showLoader) window.showLoader();
                                    form.submit();
                                });

                                // Show loader on any form submit
                                form.addEventListener('submit', function() {
                                    if (window.showLoader) window.showLoader();
                                });
                            }
                        });
                    </script>

                    <div class="table-responsive">
                        <table class="table" id="accountsTable" style="width:100%; table-layout: fixed;">
                            <thead>
                                <tr>
                                    <th class="sortable" data-column="id" style="width:60px;">ID
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                            <path d="m3 16 4 4 4-4" />
                                            <path d="M7 20V4" />
                                            <path d="m21 8-4-4-4 4" />
                                            <path d="M17 4v16" />
                                        </svg>
                                    </th>
                                    <th class="sortable" data-column="name" style="width:150px;">Name
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                            <path d="m3 16 4 4 4-4" />
                                            <path d="M7 20V4" />
                                            <path d="m21 8-4-4-4 4" />
                                            <path d="M17 4v16" />
                                        </svg>
                                    </th>
                                    <th class="sortable" data-column="email" style="width:150px;">Email
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                            <path d="m3 16 4 4 4-4" />
                                            <path d="M7 20V4" />
                                            <path d="m21 8-4-4-4 4" />
                                            <path d="M17 4v16" />
                                        </svg>
                                    </th>
                                    <?php if ($acct === 'all'): ?>
                                        <th class="sortable" data-column="type" style="width:120px;">Type
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                                <path d="m3 16 4 4 4-4" />
                                                <path d="M7 20V4" />
                                                <path d="m21 8-4-4-4 4" />
                                                <path d="M17 4v16" />
                                            </svg>
                                        </th>
                                    <?php endif; ?>
                                    <th class="sortable" data-column="status" style="width:120px;">Status
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                            <path d="m3 16 4 4 4-4" />
                                            <path d="M7 20V4" />
                                            <path d="m21 8-4-4-4 4" />
                                            <path d="M17 4v16" />
                                        </svg>
                                    </th>
                                    <th class="sortable" data-column="created" style="width:180px;">Created
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                            <path d="m3 16 4 4 4-4" />
                                            <path d="M7 20V4" />
                                            <path d="m21 8-4-4-4 4" />
                                            <path d="M17 4v16" />
                                        </svg>
                                    </th>
                                    <th style="width:160px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($accounts)): ?>
                                    <tr id="noResultsRow">
                                        <td colspan="<?php echo ($acct === 'all') ? 7 : 6; ?>" style="text-align:center; color: var(--color-text-light);">No accounts found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($accounts as $acc): ?>
                                        <tr>
                                            <td><?php echo (int)$acc['id']; ?></td>
                                            <td style="width:150px;">
                                                <?php
                                                $name = trim(($acc['first_name'] ?? '') . ' ' . ($acc['middle_name'] ?? '') . ' ' . ($acc['last_name'] ?? ''));
                                                if (!empty($acc['suffix'])) {
                                                    $name .= ', ' . $acc['suffix'];
                                                }
                                                echo htmlspecialchars($name);
                                                ?>
                                            </td>
                                            <td style="width:150px; white-space: normal; overflow-wrap: anywhere; word-break: break-word;">
                                                <?php echo htmlspecialchars($acc['email']); ?>
                                            </td>
                                            <?php if ($acct === 'all'): ?>
                                                <td style="width:120px;">
                                                    <?php echo htmlspecialchars(ucfirst((string)($acc['source'] ?? ''))); ?>
                                                </td>
                                            <?php endif; ?>
                                            <td>
                                                <?php
                                                $stRaw = (string)($acc['status'] ?? '');
                                                $st = strtolower(str_replace('_', ' ', $stRaw));
                                                $pillClass = 'pill-negative';
                                                if ($st === 'active') {
                                                    $pillClass = 'pill-active';
                                                } elseif ($st === 'not verified') {
                                                    $pillClass = 'pill-warning';
                                                } else {
                                                    // archived, banned, inactive, others => red
                                                    $pillClass = 'pill-negative';
                                                }
                                                $prettyStatus = ucfirst(trim($st));
                                                ?>
                                                <span class="status-pill <?php echo $pillClass; ?>"><span class="pill-dot"></span> <?php echo htmlspecialchars($prettyStatus); ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $rawCreated = $acc['created_at'] ?? '';
                                                try {
                                                    $dt = new DateTime($rawCreated, new DateTimeZone('Asia/Manila'));
                                                    $ampm = $dt->format('A') === 'AM' ? 'A.M' : 'P.M';
                                                    $formatted = $dt->format('F j, Y - h:i ') . $ampm;
                                                    $ts = $dt->getTimestamp();
                                                } catch (Exception $e) {
                                                    $formatted = htmlspecialchars($rawCreated);
                                                    $ts = 0;
                                                }
                                                ?>
                                                <span class="date-display" data-ts="<?php echo htmlspecialchars((string)$ts); ?>">
                                                    <?php echo htmlspecialchars($formatted); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $source = $acc['source'] ?? 'all';
                                                $cur = strtolower((string)($acc['status'] ?? ''));
                                                $label = 'Activate';
                                                $next = 'active';
                                                if ($source === 'sso') {
                                                    if ($cur === 'active') {
                                                        $label = 'Archive';
                                                        $next = 'archived';
                                                    } else {
                                                        $label = 'Activate';
                                                        $next = 'active';
                                                    }
                                                } elseif ($source === 'admission') {
                                                    if ($cur === 'active') {
                                                        $label = 'Ban';
                                                        $next = 'banned';
                                                    } else {
                                                        $label = 'Activate';
                                                        $next = 'active';
                                                    }
                                                } elseif ($source === 'services') {
                                                    if ($cur === 'active') {
                                                        $label = 'Deactivate';
                                                        $next = 'inactive';
                                                    } else {
                                                        $label = 'Activate';
                                                        $next = 'active';
                                                    }
                                                }
                                                ?>
                                                <button type="button" class="button btn-status" style="padding:6px 10px;"
                                                    data-id="<?php echo (int)$acc['id']; ?>"
                                                    data-source="<?php echo htmlspecialchars($source); ?>"
                                                    data-current="<?php echo htmlspecialchars($cur); ?>"
                                                    data-next="<?php echo htmlspecialchars($next); ?>">
                                                    <?php echo htmlspecialchars($label); ?>
                                                </button>
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

                    <div id="newSsoAccountModal" style="display:none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3500; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
                        <div style="background: var(--color-card); border-radius: 20px; text-align: center; max-width: 480px; width: 92%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); color: var(--color-text);">
                            <div style="padding: 32px 32px 16px 32px;">
                                <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 16px; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center;">
                                    <svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2-2-.9-2-2z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 21v-2a4 4 0 00-3-3.87M4 21v-2a4 4 0 013-3.87M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <h2 style="margin: 0 0 8px; font-size: 1.4rem;">New SSO Account</h2>
                                <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Enter the user’s name and password.</p>
                            </div>

                            <form id="newSsoAccountForm" style="padding: 0 32px 24px 32px; text-align:left;">
                                <div style="margin-bottom:12px;">
                                    <label for="sso_first_name" style="display:block; font-weight:600; margin-bottom:6px;">First Name <span style="color:#ef4444;">*</span></label>
                                    <input id="sso_first_name" name="first_name" type="text" required placeholder="e.g., Juan" style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                </div>
                                <div style="margin-bottom:12px;">
                                    <label for="sso_middle_name" style="display:block; font-weight:600; margin-bottom:6px;">Middle Name</label>
                                    <input id="sso_middle_name" name="middle_name" type="text" placeholder="Optional" style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                </div>
                                <div style="margin-bottom:12px;">
                                    <label for="sso_last_name" style="display:block; font-weight:600; margin-bottom:6px;">Last Name <span style="color:#ef4444;">*</span></label>
                                    <input id="sso_last_name" name="last_name" type="text" required placeholder="e.g., Dela Cruz" style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                </div>
                                <div style="margin-bottom:12px;">
                                    <label for="sso_suffix" style="display:block; font-weight:600; margin-bottom:6px;">Suffix</label>
                                    <input id="sso_suffix" name="suffix" type="text" placeholder="e.g., Jr., III (optional)" style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                </div>
                                <div style="margin-bottom:12px;">
                                    <label for="sso_email" style="display:block; font-weight:600; margin-bottom:6px;">Email Address <span style="color:#ef4444;">*</span></label>
                                    <input id="sso_email" name="email" type="email" required placeholder="e.g., user@example.com" style="width:100%; padding: 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                </div>

                                <div style="margin-top: 12px;">
                                    <label for="sso_password" style="display:block; font-weight:600; margin-bottom:6px;">Password <span style="color:#ef4444;">*</span></label>
                                    <div style="position:relative;">
                                        <input id="sso_password" name="password" type="password" required placeholder="Strong password" style="width:100%; padding: 12px 40px 12px 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                        <button type="button" id="togglePwd" aria-label="Show/Hide password" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:transparent; border:none; cursor:pointer; color:#4a5568;">
                                            <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                        </button>
                                    </div>
                                    <ul id="pwdReqs" style="list-style:none; padding:8px 0 0; margin:0; font-size:0.9rem; color:#4a5568;">
                                        <li data-req="len">At least 8 characters</li>
                                        <li data-req="upper">Contains uppercase letter</li>
                                        <li data-req="lower">Contains lowercase letter</li>
                                        <li data-req="digit">Contains number</li>
                                        <li data-req="special">Contains special character</li>
                                    </ul>
                                </div>

                                <div style="margin-top: 12px;">
                                    <label for="sso_password_confirm" style="display:block; font-weight:600; margin-bottom:6px;">Confirm Password <span style="color:#ef4444;">*</span></label>
                                    <div style="position:relative;">
                                        <input id="sso_password_confirm" name="password_confirm" type="password" required placeholder="Re-enter password" style="width:100%; padding: 12px 40px 12px 12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card); color: var(--color-text);">
                                        <button type="button" id="togglePwdConfirm" aria-label="Show/Hide password" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:transparent; border:none; cursor:pointer; color:#4a5568;">
                                            <svg id="eyeIconConfirm" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div style="padding: 20px 0 0; display:flex; gap:12px;">
                                    <button type="button" id="cancelNewSsoBtn" style="flex:1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Cancel</button>
                                    <button type="button" id="confirmNewSsoBtn" style="flex:1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Create Account</button>
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
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            window.setModalIcon = function(kind) {
                                const wrap = document.getElementById('modalIconWrap');
                                const svg = document.getElementById('modalIconSvg');
                                if (!wrap || !svg) return;
                                let bg = 'linear-gradient(135deg, #18a558 0%, #136515 100%)';
                                let path = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />';
                                if (kind === 'error') {
                                    bg = 'linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)';
                                    path = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />';
                                } else if (kind === 'info') {
                                    bg = 'linear-gradient(135deg, #4f46e5 0%, #3730a3 100%)';
                                    path = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8h.01M11 12h2v4h-2z" />';
                                } else if (kind === 'confirm') {
                                    bg = 'linear-gradient(135deg, #18a558 0%, #136515 100%)';
                                    path = '<text x="12" y="16" text-anchor="middle" font-size="16" font-weight="700" fill="currentColor">?</text>';
                                } else if (kind === 'success') {
                                    bg = 'linear-gradient(135deg, #18a558 0%, #136515 100%)';
                                    path = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />';
                                }
                                wrap.style.background = bg;
                                svg.innerHTML = path;
                            }
                            const acctSelect = document.getElementById('acctSelect');
                            const addBtn = document.getElementById('addAccountBtn');
                            const ssoModal = document.getElementById('newSsoAccountModal');
                            const ssoPwd = document.getElementById('sso_password');
                            const ssoPwdConfirm = document.getElementById('sso_password_confirm');
                            const togglePwdBtn = document.getElementById('togglePwd');
                            const togglePwdConfirmBtn = document.getElementById('togglePwdConfirm');
                            const eyeIcon = document.getElementById('eyeIcon');
                            const eyeIconConfirm = document.getElementById('eyeIconConfirm');
                            const ssoEmail = document.getElementById('sso_email');
                            const pwdReqs = document.getElementById('pwdReqs');
                            const confirmationModal = document.getElementById('confirmationModal');
                            const modalTitle = document.getElementById('modalTitle');
                            const modalMessage = document.getElementById('modalMessage');
                            const modalConfirmBtn = document.getElementById('modalConfirmBtn');
                            const modalCancelBtn = document.getElementById('modalCancelBtn');
                            const cancelNewSsoBtn = document.getElementById('cancelNewSsoBtn');
                            const confirmNewSsoBtn = document.getElementById('confirmNewSsoBtn');

                            let pendingCreate = null;

                            function openSsoModal() {
                                if (ssoModal) {
                                    ssoModal.style.display = 'flex';
                                    document.getElementById('sso_first_name')?.focus();
                                }
                            }

                            function closeSsoModal() {
                                if (ssoModal) ssoModal.style.display = 'none';
                                pendingCreate = null;
                            }

                            function updatePwdReqs(value) {
                                const checks = {
                                    len: value.length >= 8,
                                    upper: /[A-Z]/.test(value),
                                    lower: /[a-z]/.test(value),
                                    digit: /\d/.test(value),
                                    special: /[^A-Za-z0-9]/.test(value)
                                };
                                Array.from(pwdReqs.querySelectorAll('li')).forEach(li => {
                                    const k = li.getAttribute('data-req');
                                    const ok = checks[k];
                                    li.style.color = ok ? '#10b981' : '#4a5568';
                                });
                                return Object.values(checks).every(Boolean);
                            }

                            addBtn?.addEventListener('click', function() {
                                if ((acctSelect?.value || '') === 'sso') {
                                    openSsoModal();
                                }
                            });
                            ssoModal?.addEventListener('click', (e) => {
                                if (e.target === ssoModal) closeSsoModal();
                            });
                            cancelNewSsoBtn?.addEventListener('click', closeSsoModal);
                            togglePwdBtn?.addEventListener('click', () => {
                                const isPw = ssoPwd.type === 'password';
                                ssoPwd.type = isPw ? 'text' : 'password';
                                eyeIcon.innerHTML = isPw ?
                                    '<path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.86 21.86 0 0 1 5.08-6.36M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a21.86 21.86 0 0 1-3.4 4.2"/><path d="M1 1l22 22"/>' :
                                    '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
                            });
                            togglePwdConfirmBtn?.addEventListener('click', () => {
                                const isPw = ssoPwdConfirm.type === 'password';
                                ssoPwdConfirm.type = isPw ? 'text' : 'password';
                                eyeIconConfirm.innerHTML = isPw ?
                                    '<path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.86 21.86 0 0 1 5.08-6.36M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a21.86 21.86 0 0 1-3.4 4.2"/><path d="M1 1l22 22"/>' :
                                    '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
                            });
                            ssoPwd?.addEventListener('input', () => updatePwdReqs(ssoPwd.value));

                            confirmNewSsoBtn?.addEventListener('click', () => {
                                const data = {
                                    first_name: (document.getElementById('sso_first_name')?.value || '').trim(),
                                    middle_name: (document.getElementById('sso_middle_name')?.value || '').trim(),
                                    last_name: (document.getElementById('sso_last_name')?.value || '').trim(),
                                    suffix: (document.getElementById('sso_suffix')?.value || '').trim(),
                                    email: (ssoEmail?.value || '').trim(),
                                    password: ssoPwd?.value || '',
                                    password_confirm: ssoPwdConfirm?.value || ''
                                };
                                const pwOk = updatePwdReqs(data.password);
                                const emailValid = ssoEmail ? ssoEmail.checkValidity() : false;
                                if (!data.first_name || !data.last_name || !pwOk || !emailValid) {
                                    modalTitle.textContent = 'Missing/Invalid Fields';
                                    modalMessage.innerHTML = 'Please complete First Name, Last Name, a valid Email Address, and ensure password meets all requirements.';
                                    setModalIcon('error');
                                    confirmationModal.style.display = 'flex';
                                    pendingCreate = null;
                                    return;
                                }
                                if (data.password !== data.password_confirm) {
                                    modalTitle.textContent = 'Passwords Do Not Match';
                                    modalMessage.innerHTML = 'Please ensure the password and confirmation are identical.';
                                    setModalIcon('error');
                                    confirmationModal.style.display = 'flex';
                                    pendingCreate = null;
                                    return;
                                }
                                modalTitle.textContent = 'Create SSO Account';
                                const safe = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                modalMessage.innerHTML = `Are you sure you want to create this account?<br><br><strong>${safe(data.first_name)} ${safe(data.middle_name)} ${safe(data.last_name)} ${safe(data.suffix)}</strong>`;
                                setModalIcon('confirm');
                                confirmationModal.style.display = 'flex';
                                pendingCreate = data;
                            });
                            modalCancelBtn?.addEventListener('click', () => {
                                confirmationModal.style.display = 'none';
                                pendingCreate = null;
                            });
                            modalConfirmBtn?.addEventListener('click', async () => {
                                confirmationModal.style.display = 'none';
                                if (!pendingCreate) return;
                                try {
                                    window.showLoader && window.showLoader();
                                } catch (e) {}
                                const formData = new FormData();
                                formData.append('first_name', pendingCreate.first_name);
                                formData.append('middle_name', pendingCreate.middle_name);
                                formData.append('last_name', pendingCreate.last_name);
                                formData.append('suffix', pendingCreate.suffix);
                                formData.append('email', pendingCreate.email);
                                formData.append('password', pendingCreate.password);
                                const res = await fetch('create_sso_account.php', {
                                    method: 'POST',
                                    body: formData
                                });
                                const json = await res.json();
                                if (json && json.ok) {
                                    closeSsoModal();
                                    setTimeout(() => {
                                        window.location.reload();
                                    }, 150);
                                } else {
                                    modalTitle.textContent = 'Create Failed';
                                    const msg = (json && json.error) ? String(json.error) : 'Unable to create account.';
                                    modalMessage.innerHTML = msg;
                                    setModalIcon('error');
                                    confirmationModal.style.display = 'flex';
                                }
                                try {
                                    window.hideLoader && window.hideLoader();
                                } catch (e) {}
                            });
                        });
                    </script>
                </div>
            </section>
        </main>
    </div>
    <script>
        // Client-side sorting mirroring Schedule Management behavior
        document.addEventListener('DOMContentLoaded', function() {
            const table = document.getElementById('accountsTable');
            if (!table) return;
            const tbody = table.querySelector('tbody');
            const headers = table.querySelectorAll('th.sortable');
            const searchInput = document.getElementById('searchQuery');

            let currentSort = {
                column: null,
                direction: 'asc'
            };

            function getColumnIndex(columnKey) {
                const header = Array.from(headers).find(h => h.dataset.column === columnKey);
                if (!header) return -1;
                return Array.from(header.parentNode.children).indexOf(header);
            }

            function getCellValue(row, columnKey) {
                const idx = getColumnIndex(columnKey);
                const cell = row.children[idx];
                if (!cell) return '';
                if (columnKey === 'created') {
                    const span = cell.querySelector('.date-display');
                    const ts = span?.dataset.ts;
                    if (ts) return ts; // return numeric timestamp for reliable sort
                }
                return cell.textContent.trim();
            }

            function compareText(a, b) {
                return a.toLowerCase().localeCompare(b.toLowerCase());
            }

            function compareNumeric(a, b) {
                const na = parseFloat(String(a).replace(/[^\d.-]/g, '')) || 0;
                const nb = parseFloat(String(b).replace(/[^\d.-]/g, '')) || 0;
                return na - nb;
            }

            function compareDate(a, b) {
                const na = parseInt(a, 10);
                const nb = parseInt(b, 10);
                if (!Number.isNaN(na) && !Number.isNaN(nb)) {
                    return na - nb; // compare Unix timestamps
                }
                return new Date(String(a).replace(' ', 'T')) - new Date(String(b).replace(' ', 'T'));
            }

            const columnType = {
                id: 'numeric',
                name: 'text',
                email: 'text',
                type: 'text',
                status: 'text',
                created: 'date'
            };

            function sortRows(columnKey, direction = null) {
                if (direction === null) {
                    if (currentSort.column === columnKey) {
                        direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
                    } else {
                        direction = 'asc';
                    }
                }
                currentSort = {
                    column: columnKey,
                    direction
                };

                const rows = Array.from(tbody.querySelectorAll('tr'));
                rows.sort((a, b) => {
                    const av = getCellValue(a, columnKey);
                    const bv = getCellValue(b, columnKey);
                    let cmp = 0;
                    switch (columnType[columnKey] || 'text') {
                        case 'numeric':
                            cmp = compareNumeric(av, bv);
                            break;
                        case 'date':
                            cmp = compareDate(av, bv);
                            break;
                        default:
                            cmp = compareText(av, bv);
                    }
                    return direction === 'asc' ? cmp : -cmp;
                });

                rows.forEach(r => tbody.appendChild(r));
                updateHeaderIndicators(columnKey, direction);

                if (window.accountsPagination) {
                    window.accountsPagination.updateFilteredRows();
                    window.accountsPagination.currentPage = 1;
                    window.accountsPagination.updateDisplay();
                }
            }

            function updateHeaderIndicators(activeColumn, direction) {
                headers.forEach(h => h.classList.remove('sorted-asc', 'sorted-desc'));
                const active = Array.from(headers).find(h => h.dataset.column === activeColumn);
                if (active) {
                    active.classList.add(direction === 'asc' ? 'sorted-asc' : 'sorted-desc');
                }
            }

            headers.forEach(header => {
                header.addEventListener('click', () => {
                    const col = header.dataset.column;
                    sortRows(col);
                });
            });

            // Live filtering by search query across all visible text
            function filterRows(query) {
                const q = String(query || '').trim().toLowerCase();
                const rows = Array.from(tbody.querySelectorAll('tr'));
                let visibleCount = 0;

                rows.forEach(row => {
                    if (row.id === 'noResultsRow') return;
                    const text = row.textContent.toLowerCase();
                    const show = q === '' || text.includes(q);
                    row.style.display = show ? '' : 'none';
                    if (show) visibleCount++;
                });

                // Show a temporary "no results" row when filtering hides everything
                let noRow = tbody.querySelector('#noResultsRow');
                if (!noRow) {
                    noRow = document.createElement('tr');
                    noRow.id = 'noResultsRow';
                    const td = document.createElement('td');
                    const colCount = table.querySelectorAll('thead th').length;
                    td.colSpan = colCount;
                    td.style.textAlign = 'center';
                    td.style.color = 'var(--color-text-light)';
                    td.textContent = 'No matching accounts.';
                    noRow.appendChild(td);
                    tbody.appendChild(noRow);
                }
                noRow.style.display = visibleCount === 0 ? '' : 'none';
            }

            if (searchInput) {
                // Initialize from existing value (server-side q)
                filterRows(searchInput.value);
                searchInput.addEventListener('input', function() {
                    filterRows(this.value);
                    if (window.accountsPagination) {
                        window.accountsPagination.updateFilteredRows();
                        window.accountsPagination.currentPage = 1;
                        window.accountsPagination.updateDisplay();
                    }
                });
            }

            const confirmationModal2 = document.getElementById('confirmationModal');
            const modalTitle2 = document.getElementById('modalTitle');
            const modalMessage2 = document.getElementById('modalMessage');
            const modalConfirmBtn2 = document.getElementById('modalConfirmBtn');
            const modalCancelBtn2 = document.getElementById('modalCancelBtn');
            let pendingStatusUpdate = null;

            tbody.addEventListener('click', function(e) {
                const btn = e.target.closest('.btn-status');
                if (!btn) return;
                const id = btn.dataset.id;
                const source = btn.dataset.source;
                const next = btn.dataset.next;
                if (!id || !source || !next) return;
                if (modalTitle2 && modalMessage2 && confirmationModal2) {
                    modalTitle2.textContent = 'Confirm Status Update';
                    modalMessage2.textContent = 'Send email and change status to "' + next + '"?';
                    window.setModalIcon && window.setModalIcon('confirm');
                    confirmationModal2.style.display = 'flex';
                    pendingStatusUpdate = {
                        id,
                        source,
                        next,
                        btn
                    };
                }
            });

            modalCancelBtn2?.addEventListener('click', function() {
                pendingStatusUpdate = null;
            });

            modalConfirmBtn2?.addEventListener('click', async function() {
                if (!pendingStatusUpdate) return;
                confirmationModal2.style.display = 'none';
                const {
                    id,
                    source,
                    next,
                    btn
                } = pendingStatusUpdate;
                pendingStatusUpdate = null;
                try {
                    if (window.showLoader) window.showLoader();
                    const resp = await fetch('update_account_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            id,
                            acct: source,
                            next_status: next
                        }).toString()
                    });
                    const data = await resp.json();
                    if (window.hideLoader) window.hideLoader();
                    if (data && data.ok) {
                        const row = btn.closest('tr');
                        const statusCell = row && row.children[getColumnIndex('status')];
                        if (statusCell) {
                            const pill = statusCell.querySelector('.status-pill');
                            const rawStatus = String(data.next_status || next);
                            const stNorm = rawStatus.trim().toLowerCase().replace(/_/g, ' ');
                            let cls = 'pill-negative';
                            if (stNorm === 'active') cls = 'pill-active';
                            else if (stNorm === 'not verified') cls = 'pill-warning';
                            const pretty = stNorm ? (stNorm.charAt(0).toUpperCase() + stNorm.slice(1)) : rawStatus;
                            if (pill) {
                                pill.className = 'status-pill ' + cls;
                                pill.innerHTML = '<span class="pill-dot"></span> ' + pretty;
                            }
                        }
                        const updated = String(data.next_status || next).toLowerCase();
                        let label = 'Activate',
                            next2 = 'active';
                        if (source === 'sso') {
                            if (updated === 'active') {
                                label = 'Archive';
                                next2 = 'archived';
                            } else {
                                label = 'Activate';
                                next2 = 'active';
                            }
                        } else if (source === 'admission') {
                            if (updated === 'active') {
                                label = 'Ban';
                                next2 = 'banned';
                            } else {
                                label = 'Activate';
                                next2 = 'active';
                            }
                        } else if (source === 'services') {
                            if (updated === 'active') {
                                label = 'Deactivate';
                                next2 = 'inactive';
                            } else {
                                label = 'Activate';
                                next2 = 'active';
                            }
                        }
                        btn.textContent = label;
                        btn.dataset.current = updated;
                        btn.dataset.next = next2;
                    } else {
                        if (modalTitle2 && modalMessage2 && confirmationModal2) {
                            modalTitle2.textContent = 'Update Failed';
                            modalMessage2.textContent = (data && data.error) ? String(data.error) : 'Failed to update status';
                            window.setModalIcon && window.setModalIcon('error');
                            confirmationModal2.style.display = 'flex';
                        }
                    }
                } catch (err) {
                    if (window.hideLoader) window.hideLoader();
                    if (modalTitle2 && modalMessage2 && confirmationModal2) {
                        modalTitle2.textContent = 'Error';
                        modalMessage2.textContent = 'Error updating status';
                        window.setModalIcon && window.setModalIcon('error');
                        confirmationModal2.style.display = 'flex';
                    }
                }
            });

            class AccountsPagination {
                constructor() {
                    this.table = document.getElementById('accountsTable');
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
                getRowsPerPage() {
                    const v = this.select?.value || '10';
                    return v === 'all' ? Infinity : parseInt(v, 10);
                }
                init() {
                    if (!this.tbody) return;
                    this.allRows = Array.from(this.tbody.querySelectorAll('tr')).filter(r => r.id !== 'noResultsRow');
                    this.filteredRows = this.allRows.filter(r => r.style.display !== 'none');
                    this.setup();
                    this.updateDisplay();
                }
                setup() {
                    if (this.select) {
                        this.select.addEventListener('change', () => {
                            this.rowsPerPage = this.getRowsPerPage();
                            this.currentPage = 1;
                            this.updateDisplay();
                        });
                    }
                    if (this.prev) {
                        this.prev.addEventListener('click', () => {
                            if (this.currentPage > 1) {
                                this.currentPage--;
                                this.updateDisplay();
                            }
                        });
                    }
                    if (this.next) {
                        this.next.addEventListener('click', () => {
                            const totalPages = Math.max(1, Math.ceil(this.filteredRows.length / this.rowsPerPage));
                            if (this.currentPage < totalPages) {
                                this.currentPage++;
                                this.updateDisplay();
                            }
                        });
                    }
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
                        const rangeText = this.rowsPerPage === Infinity ? `Showing 1-${totalRows}` : `Showing ${startItem}-${endItem}`;
                        this.info.textContent = `${rangeText} • Page ${this.currentPage}/${totalPages}`;
                    }
                    const isFirst = this.currentPage <= 1;
                    const isLast = this.currentPage >= totalPages || totalRows === 0;
                    if (this.prev) {
                        this.prev.disabled = isFirst;
                        this.prev.classList.toggle('pagination__button--disabled', isFirst);
                    }
                    if (this.next) {
                        this.next.disabled = isLast;
                        this.next.classList.toggle('pagination__button--disabled', isLast);
                    }
                    const emptyRow = this.tbody.querySelector('#noResultsRow');
                    if (emptyRow) {
                        emptyRow.style.display = totalRows === 0 ? '' : 'none';
                    }
                }
            }

            window.accountsPagination = new AccountsPagination();
        });
    </script>
</body>

</html>