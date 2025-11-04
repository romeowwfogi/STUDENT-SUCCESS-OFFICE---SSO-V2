<?php
// Authentication middleware - protect this page
require_once 'middleware/auth.php';
include 'connection/db_connect.php';

// --- ACTION HANDLER: Process form submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- CREATE A NEW SERVICE ---
    if (isset($_POST['action']) && $_POST['action'] === 'create_service') {
        $service_name = $conn->real_escape_string($_POST['service_name'] ?? '');
        $description = $conn->real_escape_string($_POST['description'] ?? '');
        $icon = $conn->real_escape_string($_POST['icon'] ?? '');
        $button_text = $conn->real_escape_string($_POST['button_text'] ?? '');
        $is_active = isset($_POST['is_active']) && $_POST['is_active'] === '1' ? 1 : 0;

        if (trim($service_name) === '') {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Service Name is required.'];
            header("Location: services_management.php");
            exit;
        }

        $sql = "INSERT INTO services_list (name, description, icon, button_text, is_active) VALUES ('$service_name', '$description', '$icon', '$button_text', $is_active)";
        if ($conn->query($sql)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'New service created successfully.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error creating service: ' . $conn->error];
        }
        header("Location: services_management.php");
        exit;
    }

    // --- UPDATE AN EXISTING SERVICE ---
    if (isset($_POST['action']) && $_POST['action'] === 'update_service') {
        $service_id = (int)($_POST['service_id'] ?? 0);
        $service_name = $conn->real_escape_string($_POST['service_name'] ?? '');
        $description = $conn->real_escape_string($_POST['description'] ?? '');
        $icon = $conn->real_escape_string($_POST['icon'] ?? '');
        $button_text = $conn->real_escape_string($_POST['button_text'] ?? '');
        $is_active = (isset($_POST['is_active']) && $_POST['is_active'] === '1') ? 1 : 0;

        if ($service_id <= 0 || trim($service_name) === '') {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Service ID and Service Name are required.'];
            header("Location: services_management.php");
            exit;
        }

        $sql = "UPDATE services_list SET name='$service_name', description='$description', icon='$icon', button_text='$button_text', is_active=$is_active WHERE service_id=$service_id";
        if ($conn->query($sql)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Service updated successfully.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error updating service: ' . $conn->error];
        }
        header("Location: services_management.php");
        exit;
    }

    // --- ACTION: Bulk Activate services (mark as available) ---
    if ($_GET['action'] === 'bulk_activate_services' && isset($_GET['ids'])) {
        $service_ids = explode(',', $_GET['ids']);
        $service_ids = array_map('intval', $service_ids);
        $service_ids = array_filter($service_ids, function ($id) {
            return $id > 0;
        });

        if (!empty($service_ids)) {
            $ids_string = implode(',', $service_ids);
            $sql = "UPDATE services_list SET is_active = 1 WHERE service_id IN ($ids_string)";
            if ($conn->query($sql)) {
                $updated_count = $conn->affected_rows;
                $service_text = $updated_count === 1 ? 'service' : 'services';
                $_SESSION['message'] = ['type' => 'success', 'text' => "Successfully marked $updated_count $service_text as available."];
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Error during bulk activation: ' . $conn->error];
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'No valid services selected for activation.'];
        }

        header("Location: services_management.php");
        exit;
    }
}

// --- ACTION HANDLER: Handle GET requests (Archive) ---
if (isset($_GET['action'])) {
    // --- ACTION: Deactivate a service (mark as not available) ---
    if ($_GET['action'] === 'deactivate_service' && isset($_GET['id'])) {
        $service_id = (int)$_GET['id'];
        $sql = "UPDATE services_list SET is_active = 0 WHERE service_id = $service_id";
        $ok = $conn->query($sql);
        $msg = $ok ? 'Service marked as not available.' : ('Error updating service: ' . $conn->error);
        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => $ok, 'message' => $msg, 'id' => $service_id]);
            exit;
        }
        $_SESSION['message'] = ['type' => $ok ? 'success' : 'error', 'text' => $msg];
        header("Location: services_management.php");
        exit;
    }

    // --- ACTION: Activate a service (mark as available) ---
    if ($_GET['action'] === 'activate_service' && isset($_GET['id'])) {
        $service_id = (int)$_GET['id'];
        $sql = "UPDATE services_list SET is_active = 1 WHERE service_id = $service_id";
        $ok = $conn->query($sql);
        $msg = $ok ? 'Service marked as available.' : ('Error updating service: ' . $conn->error);
        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => $ok, 'message' => $msg, 'id' => $service_id]);
            exit;
        }
        $_SESSION['message'] = ['type' => $ok ? 'success' : 'error', 'text' => $msg];
        header("Location: services_management.php");
        exit;
    }

    // --- ACTION: Bulk Deactivate services (mark as not available) ---
    if ($_GET['action'] === 'bulk_deactivate_services' && isset($_GET['ids'])) {
        $service_ids = explode(',', $_GET['ids']);
        $service_ids = array_map('intval', $service_ids);
        $service_ids = array_filter($service_ids, function ($id) {
            return $id > 0;
        });

        if (!empty($service_ids)) {
            $ids_string = implode(',', $service_ids);
            $sql = "UPDATE services_list SET is_active = 0 WHERE service_id IN ($ids_string)";
            if ($conn->query($sql)) {
                $updated_count = $conn->affected_rows;
                $service_text = $updated_count === 1 ? 'service' : 'services';
                $_SESSION['message'] = ['type' => 'success', 'text' => "Successfully archived $updated_count $service_text (marked as not available)."];
                if (isset($_GET['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => $_SESSION['message']['text']]);
                    exit;
                }
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Error during bulk archive: ' . $conn->error];
                if (isset($_GET['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => $_SESSION['message']['text']]);
                    exit;
                }
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'No valid services selected for archiving.'];
            if (isset($_GET['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $_SESSION['message']['text']]);
                exit;
            }
        }

        header("Location: services_management.php");
        exit;
    }

    // --- ACTION: Bulk Activate services (mark as available) ---
    if ($_GET['action'] === 'bulk_activate_services' && isset($_GET['ids'])) {
        $service_ids = explode(',', $_GET['ids']);
        $service_ids = array_map('intval', $service_ids);
        $service_ids = array_filter($service_ids, function ($id) {
            return $id > 0;
        });

        if (!empty($service_ids)) {
            $ids_string = implode(',', $service_ids);
            $sql = "UPDATE services_list SET is_active = 1 WHERE service_id IN ($ids_string)";
            if ($conn->query($sql)) {
                $updated_count = $conn->affected_rows;
                $service_text = $updated_count === 1 ? 'service' : 'services';
                $_SESSION['message'] = ['type' => 'success', 'text' => "Successfully updated $updated_count $service_text (marked as available)."];
                if (isset($_GET['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => $_SESSION['message']['text']]);
                    exit;
                }
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Error during bulk activation: ' . $conn->error];
                if (isset($_GET['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => $_SESSION['message']['text']]);
                    exit;
                }
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'No valid services selected for activation.'];
            if (isset($_GET['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $_SESSION['message']['text']]);
                exit;
            }
        }

        header("Location: services_management.php");
        exit;
    }
}

// --- DATA FETCHING: Get services list for display ---
$services = [];
$result = $conn->query("SELECT service_id, name, description, icon, button_text, is_active FROM services_list ORDER BY service_id DESC");
while ($row = $result->fetch_assoc()) {
    $services[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Success Office - Services Management</title>
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
                    <h1 class="header__title">Services Management</h1>
                </div>
                <div class="header__actions">
                    <button id="newServicesBtn" class="btn btn--primary">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New Service
                    </button>
                </div>
            </header>

            <section class="section active" id="cycle_management_section" style="margin: 0 20px;">
                <div class="table-container">
                    <div class="table-container__header">
                        <h2 class="table-container__title">Manage Services</h2>
                        <p class="table-container__subtitle">View services, manage services, or view requestors</p>
                    </div>
                    <div class="filtration_container">
                        <div class="search_input_container">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search-icon lucide-search">
                                <path d="m21 21-4.34-4.34" />
                                <circle cx="11" cy="11" r="8" />
                            </svg>
                            <input type="text" id="cycleSearchInput" placeholder="Search services...">
                        </div>


                        <div class="search_button_container">
                            <button class="button export" onclick="window.location.href = 'archived_cycles.php';">View Archived Services</button>
                        </div>
                    </div>
                    <table class="table" id="cyclesTable">
                        <thead>
                            <tr>
                                <th style="width:40px;">
                                    <input type="checkbox" id="selectAllPermits" title="Select All">
                                </th>
                                <th class="sortable" data-column="ID">ID
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th class="sortable" data-column="Service Name">Service Name
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th class="sortable" data-column="Status">Status
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th style="width:230px;" class="sortable" data-column="Description">Description
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
                            <?php if (empty($services)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center;">No services found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($services as $service): ?>
                                    <tr>
                                        <td data-cell='Select'>
                                            <input type="checkbox" class="row-checkbox" data-id="<?php echo $service['service_id']; ?>">
                                        </td>
                                        <td data-cell='ID'><?php echo $service["service_id"]; ?></td>
                                        <td data-cell='Service Name'><?php echo htmlspecialchars($service["name"]); ?></td>
                                        <td data-cell='Status'><?php echo ((int)$service['is_active'] === 1) ? '<span class="status-pill status-pill--available">Available</span>' : '<span class="status-pill status-pill--unavailable">Not Available</span>'; ?></td>
                                        <td data-cell='Description'><?php echo htmlspecialchars($service["description"] ?? ''); ?></td>
                                        <td class='table_actions actions'>
                                            <div class='table-controls'>
                                                <a href="#" class="table__btn table__btn--view" data-action="view-applicants" data-service-id="<?php echo $service['service_id']; ?>" data-tooltip="View requestors" aria-label="View requestors" title="View requestors">View</a>
                                                <a href="#" class="table__btn table__btn--view" data-action="manage-service" data-service-id="<?php echo $service['service_id']; ?>" data-tooltip="Manage service" aria-label="Manage service" title="Manage service">Manage</a>
                                                <a href="#" class="table__btn table__btn--view" data-action="edit-service"
                                                    data-service-id="<?php echo $service['service_id']; ?>"
                                                    data-service-name="<?php echo htmlspecialchars($service['name']); ?>"
                                                    data-description="<?php echo htmlspecialchars($service['description'] ?? ''); ?>"
                                                    data-icon="<?php echo htmlspecialchars($service['icon'] ?? ''); ?>"
                                                    data-button-text="<?php echo htmlspecialchars($service['button_text'] ?? ''); ?>"
                                                    data-is-active="<?php echo (int)$service['is_active'] === 1 ? '1' : '0'; ?>"
                                                    data-tooltip="Update <?php echo htmlspecialchars($service['name']); ?>" aria-label="Update <?php echo htmlspecialchars($service['name']); ?>" title="Update <?php echo htmlspecialchars($service['name']); ?>">Update</a>
                                                <?php if ((int)$service['is_active'] === 1): ?>
                                                    <a href="services_management.php?action=deactivate_service&id=<?php echo $service['service_id']; ?>"
                                                        class="table__btn table__btn--delete confirm-action" data-tooltip="Disable service" aria-label="Disable service" title="Disable service"
                                                        data-modal-title="Confirm Disable"
                                                        data-modal-message="Mark this service as not available?">
                                                        Disable
                                                    </a>
                                                <?php else: ?>
                                                    <a href="services_management.php?action=activate_service&id=<?php echo $service['service_id']; ?>"
                                                        class="table__btn table__btn--view confirm-action" data-tooltip="Enable service" aria-label="Enable service" title="Enable service"
                                                        data-modal-title="Confirm Enable"
                                                        data-modal-message="Mark this service as available?">
                                                        Enable
                                                    </a>
                                                <?php endif; ?>
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

    <!-- New Service Modal -->
    <div id="newServiceModal" class="modal-overlay">
        <div style="background: var(--color-card); border-radius: 20px; max-width: 640px; width: 95%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 90vh; overflow-y: auto; border: 1px solid var(--color-border); position: relative; color: var(--color-text);">
            <!-- Close Button -->
            <button type="button" id="closeNewServiceModal" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px; transition: all 0.2s ease; z-index: 10;">&times;</button>

            <!-- Modal Header -->
            <div style="padding: 40px 32px 24px 32px; text-align: center;">
                <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 24px auto; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                </div>
                <h3 style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.6rem; font-weight: 700; letter-spacing: -0.025em;">Create New Service</h3>
                <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Define the service details shown to requestors</p>
            </div>

            <!-- Modal Body -->
            <div style="padding: 0 32px 24px 32px;">
                <form id="newServiceForm">
                    <div style="margin-bottom: 16px;">
                        <label for="service_name" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; font-size: 0.9rem;">Service Name</label>
                        <input type="text" id="service_name" name="service_name" placeholder="e.g., Good Moral Request" required style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                    </div>

                    <div style="margin-bottom: 16px;">
                        <label for="description" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; font-size: 0.9rem;">Description</label>
                        <textarea id="description" name="description" placeholder="Short description of the service..." rows="3" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;"></textarea>
                    </div>

                    <div style="margin-bottom: 16px;">
                        <label for="icon" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; font-size: 0.9rem;">Icon</label>
                        <textarea id="icon" name="icon" placeholder="FontAwesome class (e.g., fa-user) or full SVG code" rows="3" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;"></textarea>
                        <small style="display: block; margin-top: 8px; font-size: 0.85rem; color: #718096;">You can paste an SVG or enter a known icon class.</small>
                    </div>

                    <div style="margin-bottom: 16px;">
                        <label for="button_text" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; font-size: 0.9rem;">Button Text</label>
                        <input type="text" id="button_text" name="button_text" placeholder="e.g., Request Now" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                    </div>
                    <div style="margin-bottom: 24px; display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" id="is_active" name="is_active" value="1" checked style="width: 18px; height: 18px;">
                        <label for="is_active" style="font-weight: 600; color: #2d3748; font-size: 0.9rem;">Is Active</label>
                    </div>
                </form>
            </div>

            <!-- Modal Footer -->
            <div style="padding: 20px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
                <button type="button" id="cancelNewServiceModal" style="flex: 1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
                <button type="button" id="confirmNewServiceModal" style="flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4);">Create Service</button>
            </div>
        </div>
    </div>


    <!-- Edit Service Modal -->
    <div id="editServiceModal" class="modal-overlay" style="display:none;">
        <div style="background: var(--color-card); border-radius: 20px; max-width: 640px; width: 95%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 90vh; overflow-y: auto; border: 1px solid var(--color-border); position: relative; color: var(--color-text);">
            <!-- Close Button -->
            <button type="button" id="closeEditServiceModal" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px; transition: all 0.2s ease; z-index: 10;">&times;</button>

            <!-- Modal Header -->
            <div style="padding: 40px 32px 24px 32px; text-align: center;">
                <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 24px auto; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4h2v16h-2zM4 11v2h16v-2z"></path>
                    </svg>
                </div>
                <h3 style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.6rem; font-weight: 700; letter-spacing: -0.025em;">Update Service</h3>
                <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Edit the service details and save changes</p>
            </div>

            <!-- Modal Body -->
            <div style="padding: 0 32px 24px 32px;">
                <form id="editServiceForm">
                    <input type="hidden" id="edit_service_id" name="service_id">
                    <div style="margin-bottom: 16px;">
                        <label for="edit_service_name" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; font-size: 0.9rem;">Service Name</label>
                        <input type="text" id="edit_service_name" name="service_name" placeholder="e.g., Good Moral Request" required style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                    </div>

                    <div style="margin-bottom: 16px;">
                        <label for="edit_description" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; font-size: 0.9rem;">Description</label>
                        <textarea id="edit_description" name="description" placeholder="Short description of the service..." rows="3" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;"></textarea>
                    </div>

                    <div style="margin-bottom: 16px;">
                        <label for="edit_icon" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; font-size: 0.9rem;">Icon</label>
                        <textarea id="edit_icon" name="icon" placeholder="FontAwesome class (e.g., fa-user) or full SVG code" rows="3" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;"></textarea>
                        <small style="display: block; margin-top: 8px; font-size: 0.85rem; color: #718096;">You can paste an SVG or enter a known icon class.</small>
                    </div>

                    <div style="margin-bottom: 16px;">
                        <label for="edit_button_text" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; font-size: 0.9rem;">Button Text</label>
                        <input type="text" id="edit_button_text" name="button_text" placeholder="e.g., Request Now" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                    </div>
                    <div style="margin-bottom: 24px; display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" id="edit_is_active" name="is_active" value="1" style="width: 18px; height: 18px;">
                        <label for="edit_is_active" style="font-weight: 600; color: #2d3748; font-size: 0.9rem;">Is Active</label>
                    </div>
                </form>
            </div>

            <!-- Modal Footer -->
            <div style="padding: 20px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
                <button type="button" id="cancelEditServiceModal" style="flex: 1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
                <button type="button" id="confirmEditServiceModal" style="flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4);">Update Service</button>
            </div>
        </div>
    </div>

    <!-- Floating Action Menu for Bulk Operations (mirrored from exam_permit_management) -->
    <div id="floatingActionMenu" style="display: none; position: fixed; bottom: 30px; right: 30px; background: var(--color-card); border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.15), 0 8px 25px rgba(0,0,0,0.1); z-index: 1001; padding: 20px; min-width: 280px; border: 1px solid var(--color-border);">
        <div style="margin-bottom: 16px;">
            <h4 style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.1rem; font-weight: 600;">Bulk Actions</h4>
            <p id="selectedCount" style="margin: 0; color: #718096; font-size: 0.9rem;">0 services selected</p>
        </div>
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <button id="bulkDeactivateServices" class="btn btn--secondary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 16px; border-radius: 10px; font-size: 0.9rem;">
                <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 12H6" />
                </svg>
                Mark as Not Available
            </button>
            <button id="bulkActivateServices" class="btn btn--primary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 16px; border-radius: 10px; font-size: 0.9rem;">
                <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12M6 12h12" />
                </svg>
                Mark as Available
            </button>
        </div>
    </div>

    <script>
        // Use shared loader helpers defined earlier (loadingOverlay)

        const modal = document.getElementById('confirmationModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');
        const modalConfirmBtn = document.getElementById('modalConfirmBtn');
        const modalCancelBtn = document.getElementById('modalCancelBtn');
        let currentActionUrl = '';
        let modalLocked = false;

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
            modalLocked = false;
            modal.style.display = 'flex';
            modal.style.visibility = 'visible';
            modal.style.opacity = '1';

            console.log('Modal should now be visible');
        }
        // Execute server bulk action via AJAX and keep modal open to show result
        async function executeBulkAction(actionUrl) {
            try {
                showLoader();
                // Ensure ajax flag
                const url = actionUrl.includes('ajax=1') ? actionUrl : (actionUrl + '&ajax=1');
                const resp = await fetch(url, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                let data;
                try {
                    data = await resp.json();
                } catch (e) {
                    data = null;
                }

                hideLoader();
                const ok = data && (data.success === true || data.status === 'success');
                const msg = (data && data.message) ? data.message : (ok ? 'Operation completed successfully.' : 'Operation completed with issues.');
                modalTitle.textContent = ok ? 'Success' : 'Notice';
                modalMessage.textContent = msg;
                // Lock modal and turn confirm into Close + refresh
                modalLocked = true;
                modalConfirmBtn.textContent = 'Close';
                modalCancelBtn.style.display = 'none';
                modalConfirmBtn.onclick = () => {
                    window.location.reload();
                };

                // Update UI: status pills and selection state
                try {
                    const isActivate = actionUrl.includes('bulk_activate_services');
                    selectedServices.forEach(id => {
                        const row = document.querySelector(`.row-checkbox[data-id="${id}"]`)?.closest('tr');
                        if (!row) return;
                        const statusCell = row.querySelector("[data-cell='Status']");
                        if (!statusCell) return;
                        statusCell.innerHTML = isActivate ?
                            '<span class="status-pill status-pill--available">Available</span>' :
                            '<span class="status-pill status-pill--unavailable">Not Available</span>';
                        // Uncheck
                        const cb = row.querySelector('.row-checkbox');
                        if (cb) cb.checked = false;
                    });
                    selectedServices = [];
                    updateFloatingMenu();
                } catch (e) {}

            } catch (err) {
                hideLoader();
                modalTitle.textContent = 'Error';
                modalMessage.textContent = 'Unexpected error occurred. Please try again.';
                modalLocked = true;
                modalConfirmBtn.textContent = 'Close';
                modalCancelBtn.style.display = 'none';
                modalConfirmBtn.onclick = () => {
                    window.location.reload();
                };
            }
        }

        // Execute single action (activate/deactivate) via AJAX and show result in modal
        async function executeSingleAction(actionUrl) {
            try {
                showLoader();
                const url = actionUrl.includes('ajax=1') ? actionUrl : (actionUrl + '&ajax=1');
                const resp = await fetch(url, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                let data;
                try {
                    data = await resp.json();
                } catch (e) {
                    data = null;
                }

                hideLoader();
                const ok = data && (data.success === true || data.status === 'success');
                const msg = (data && data.message) ? data.message : (ok ? 'Operation completed successfully.' : 'Operation failed.');
                modalTitle.textContent = ok ? 'Success' : 'Error';
                modalMessage.textContent = msg;
                modalLocked = true;
                modalConfirmBtn.textContent = 'Close';
                modalCancelBtn.style.display = 'none';
                modalConfirmBtn.onclick = () => {
                    window.location.reload();
                };

                // Optional UI update without reload
                try {
                    const isActivate = actionUrl.includes('activate_service');
                    const idMatch = actionUrl.match(/id=(\d+)/);
                    const id = idMatch ? idMatch[1] : null;
                    if (id) {
                        const row = document.querySelector(`a[href*="id=${id}"]`)?.closest('tr');
                        const statusCell = row?.querySelector("[data-cell='Status']");
                        if (statusCell) {
                            statusCell.innerHTML = isActivate ?
                                '<span class="status-pill status-pill--available">Available</span>' :
                                '<span class="status-pill status-pill--unavailable">Not Available</span>';
                        }
                    }
                } catch (e) {}
            } catch (err) {
                hideLoader();
                modalTitle.textContent = 'Error';
                modalMessage.textContent = 'Unexpected error occurred. Please try again.';
                modalLocked = true;
                modalConfirmBtn.textContent = 'Close';
                modalCancelBtn.style.display = 'none';
                modalConfirmBtn.onclick = () => {
                    window.location.reload();
                };
            }
        }

        function resetModalButtons() {
            modalConfirmBtn.textContent = 'Confirm';
            modalCancelBtn.style.display = '';
            modalLocked = false;
            // Rebind default click (restored below)
            modalConfirmBtn.onclick = null;
        }

        modalConfirmBtn.addEventListener('click', () => {
            if (currentActionUrl === 'create_new_service') {
                // Handle new service creation
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'services_management.php';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'create_service';

                const nameInput = document.createElement('input');
                nameInput.type = 'hidden';
                nameInput.name = 'service_name';
                nameInput.value = window.pendingServiceData?.service_name || '';

                const descInput = document.createElement('input');
                descInput.type = 'hidden';
                descInput.name = 'description';
                descInput.value = window.pendingServiceData?.description || '';

                const iconInput = document.createElement('input');
                iconInput.type = 'hidden';
                iconInput.name = 'icon';
                iconInput.value = window.pendingServiceData?.icon || '';

                const btnTextInput = document.createElement('input');
                btnTextInput.type = 'hidden';
                btnTextInput.name = 'button_text';
                btnTextInput.value = window.pendingServiceData?.button_text || '';

                const isActiveInput = document.createElement('input');
                isActiveInput.type = 'hidden';
                isActiveInput.name = 'is_active';
                isActiveInput.value = window.pendingServiceData?.is_active ? '1' : '0';

                form.appendChild(actionInput);
                form.appendChild(nameInput);
                form.appendChild(descInput);
                form.appendChild(iconInput);
                form.appendChild(btnTextInput);
                form.appendChild(isActiveInput);
                document.body.appendChild(form);

                // Show loader for feedback
                try {
                    if (typeof showLoader === 'function') showLoader();
                } catch (e) {}

                // Close the service modal and hide confirmation modal
                closeNewServiceModalFunc();
                modal.style.display = 'none';

                // Defer submission to ensure loader renders
                setTimeout(function() {
                    form.submit();
                }, 120);
            } else if (currentActionUrl) {
                // For bulk actions, use AJAX and keep modal for message
                if (currentActionUrl.includes('bulk_activate_services') || currentActionUrl.includes('bulk_deactivate_services')) {
                    executeBulkAction(currentActionUrl);
                } else {
                    // Single activate/deactivate via AJAX for loader + feedback
                    executeSingleAction(currentActionUrl);
                }
            }
        });
        modalCancelBtn.addEventListener('click', () => {
            if (modalLocked) return; // Not cancellable when locked
            modal.style.display = 'none';
        });
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                if (modalLocked) return; // Prevent closing by clicking overlay when locked
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
        // Bind confirmation handlers for action links
        setupConfirmationLinks();
        // New Service Modal Functionality
        const newServicesBtn = document.getElementById('newServicesBtn');
        const newServiceModal = document.getElementById('newServiceModal');
        const closeNewServiceModal = document.getElementById('closeNewServiceModal');
        const cancelNewServiceModal = document.getElementById('cancelNewServiceModal');
        const confirmNewServiceModal = document.getElementById('confirmNewServiceModal');
        const newServiceForm = document.getElementById('newServiceForm');
        const serviceNameInput = document.getElementById('service_name');
        const descriptionInput = document.getElementById('description');
        const iconInput = document.getElementById('icon');
        const buttonTextInput = document.getElementById('button_text');
        const isActiveInput = document.getElementById('is_active');

        function openNewServiceModal() {
            newServiceModal.classList.add('show');
            serviceNameInput?.focus();
        }

        function closeNewServiceModalFunc() {
            newServiceModal.classList.remove('show');
            newServiceForm?.reset();
        }

        function submitNewService() {
            const serviceName = serviceNameInput.value.trim();
            if (!serviceName) {
                serviceNameInput.focus();
                return;
            }

            const description = descriptionInput.value.trim();
            const icon = iconInput.value.trim();
            const buttonText = buttonTextInput.value.trim();
            const isActive = !!isActiveInput.checked;

            window.pendingServiceData = {
                service_name: serviceName,
                description,
                icon,
                button_text: buttonText,
                is_active: isActive
            };

            showConfirmationModal(
                'Confirm New Service Creation',
                `Create service "${serviceName}" now?`,
                'create_new_service'
            );
        }

        if (newServicesBtn) newServicesBtn.addEventListener('click', openNewServiceModal);
        if (closeNewServiceModal) closeNewServiceModal.addEventListener('click', closeNewServiceModalFunc);
        if (cancelNewServiceModal) cancelNewServiceModal.addEventListener('click', closeNewServiceModalFunc);
        if (confirmNewServiceModal) confirmNewServiceModal.addEventListener('click', submitNewService);

        // Close modal when clicking outside
        newServiceModal.addEventListener('click', function(event) {
            if (event.target === newServiceModal) {
                closeNewServiceModalFunc();
            }
        });

        // Handle Enter key in the form (submit on Enter within name field)
        serviceNameInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                submitNewService();
            }
        });

        // Edit Service Modal Functionality
        const editServiceModal = document.getElementById('editServiceModal');
        const closeEditServiceModal = document.getElementById('closeEditServiceModal');
        const cancelEditServiceModal = document.getElementById('cancelEditServiceModal');
        const confirmEditServiceModal = document.getElementById('confirmEditServiceModal');
        const editServiceForm = document.getElementById('editServiceForm');
        const editServiceIdInput = document.getElementById('edit_service_id');
        const editServiceNameInput = document.getElementById('edit_service_name');
        const editDescriptionInput = document.getElementById('edit_description');
        const editIconInput = document.getElementById('edit_icon');
        const editButtonTextInput = document.getElementById('edit_button_text');
        const editIsActiveInput = document.getElementById('edit_is_active');

        function openEditServiceModal() {
            // Ensure inline display:none doesn't block visibility
            editServiceModal.style.display = 'flex';
            setTimeout(() => editServiceModal.classList.add('show'), 10);
            setTimeout(() => editServiceNameInput?.focus(), 100);
        }

        function closeEditServiceModalFunc() {
            editServiceModal.classList.remove('show');
            setTimeout(() => {
                editServiceModal.style.display = 'none';
                editServiceForm?.reset();
                editServiceIdInput.value = '';
            }, 200);
        }

        async function submitEditService() {
            const serviceId = editServiceIdInput.value.trim();
            const serviceName = editServiceNameInput.value.trim();
            if (!serviceId || !serviceName) {
                return;
            }

            const description = editDescriptionInput.value.trim();
            const icon = editIconInput.value.trim();
            const buttonText = editButtonTextInput.value.trim();
            const isActive = editIsActiveInput.checked ? '1' : '0';

            const formData = new FormData();
            formData.append('action', 'update_service');
            formData.append('service_id', serviceId);
            formData.append('service_name', serviceName);
            formData.append('description', description);
            formData.append('icon', icon);
            formData.append('button_text', buttonText);
            formData.append('is_active', isActive);

            try {
                if (typeof showLoader === 'function') showLoader();
                const response = await fetch('services_management.php', {
                    method: 'POST',
                    body: formData
                });
                if (!response.ok) throw new Error('Network response was not ok');
                if (typeof hideLoader === 'function') hideLoader();

                // Close editor and show confirmation modal locked with reload
                closeEditServiceModalFunc();
                modalTitle.textContent = 'Update Service';
                modalMessage.textContent = 'Service updated successfully.';
                modalLocked = true;
                modalConfirmBtn.textContent = 'Close';
                modalCancelBtn.style.display = 'none';
                modalConfirmBtn.onclick = () => {
                    window.location.reload();
                };
                modal.style.display = 'flex';
            } catch (error) {
                if (typeof hideLoader === 'function') hideLoader();
                closeEditServiceModalFunc();
                modalTitle.textContent = 'Update Service';
                modalMessage.textContent = 'Error updating service.';
                modalLocked = true;
                modalConfirmBtn.textContent = 'Close';
                modalCancelBtn.style.display = 'none';
                modalConfirmBtn.onclick = () => {
                    window.location.reload();
                };
                modal.style.display = 'flex';
            }
        }

        // Bind edit buttons
        document.querySelectorAll('[data-action="edit-service"]').forEach(btn => {
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                const el = event.currentTarget;
                editServiceIdInput.value = el.getAttribute('data-service-id') || '';
                editServiceNameInput.value = el.getAttribute('data-service-name') || '';
                editDescriptionInput.value = el.getAttribute('data-description') || '';
                editIconInput.value = el.getAttribute('data-icon') || '';
                editButtonTextInput.value = el.getAttribute('data-button-text') || '';
                const isActiveAttr = el.getAttribute('data-is-active');
                editIsActiveInput.checked = (isActiveAttr === '1');
                openEditServiceModal();
            });
        });

        if (closeEditServiceModal) closeEditServiceModal.addEventListener('click', closeEditServiceModalFunc);
        if (cancelEditServiceModal) cancelEditServiceModal.addEventListener('click', closeEditServiceModalFunc);
        if (confirmEditServiceModal) confirmEditServiceModal.addEventListener('click', submitEditService);

        editServiceModal.addEventListener('click', function(event) {
            if (event.target === editServiceModal) {
                closeEditServiceModalFunc();
            }
        });

        const searchInput = document.getElementById('cycleSearchInput');
        const tableBody = document.getElementById('cyclesTable')?.querySelector('tbody');
        if (searchInput && tableBody) {
            searchInput.addEventListener('keyup', function() {
                const term = this.value.trim().toLowerCase();
                const rows = tableBody.querySelectorAll('tr');
                rows.forEach(row => {
                    // Skip empty state row
                    if (row.textContent.includes('No services found')) {
                        row.style.display = term ? 'none' : '';
                        return;
                    }

                    const idCell = row.querySelector("[data-cell='ID']");
                    const nameCell = row.querySelector("[data-cell='Service Name']");
                    const statusCell = row.querySelector("[data-cell='Status']");
                    const descCell = row.querySelector("[data-cell='Description']");

                    const idText = idCell ? idCell.textContent.toLowerCase() : '';
                    const nameText = nameCell ? nameCell.textContent.toLowerCase() : '';
                    const statusText = statusCell ? statusCell.textContent.toLowerCase() : '';
                    const descText = descCell ? descCell.textContent.toLowerCase() : '';

                    const matches = !term ||
                        idText.includes(term) ||
                        nameText.includes(term) ||
                        statusText.includes(term) ||
                        descText.includes(term);

                    row.style.display = matches ? '' : 'none';
                });
            });
        }
        document.addEventListener('DOMContentLoaded', setupConfirmationLinks);

        // Floating Archive Icon Functionality
        const floatingArchiveBtn = document.getElementById('floatingArchiveBtn');
        const selectionCounter = document.getElementById('selectionCounter');
        const selectAllCheckbox = document.getElementById('selectAllPermits');
        const rowCheckboxes = document.querySelectorAll('.row-checkbox');
        let selectedServices = [];

        function updateFloatingIcon() {
            const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
            selectedServices = Array.from(checkedBoxes).map(cb => cb.dataset.id);

            if (selectedServices.length > 0) {
                if (floatingArchiveBtn) floatingArchiveBtn.classList.add('show');
                if (selectionCounter) selectionCounter.textContent = selectedServices.length;
            } else {
                if (floatingArchiveBtn) floatingArchiveBtn.classList.remove('show');
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
        if (floatingArchiveBtn) floatingArchiveBtn.addEventListener('click', function() {
            if (selectedServices.length === 0) return;

            const serviceText = selectedServices.length === 1 ? 'service' : 'services';
            const message = `Are you sure you want to archive ${selectedServices.length} ${serviceText}? This will mark them as Not Available.`;

            showConfirmationModal(
                'Confirm Bulk Archive',
                message,
                `services_management.php?action=bulk_deactivate_services&ids=${selectedServices.join(',')}`
            );
        });

        // Floating Action Menu Functionality (mirrored from exam_permit_management)
        const floatingMenu = document.getElementById('floatingActionMenu');
        const selectedCountEl = document.getElementById('selectedCount');
        const bulkDeactivateBtn = document.getElementById('bulkDeactivateServices');
        const bulkActivateBtn = document.getElementById('bulkActivateServices');

        function updateFloatingMenu() {
            const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
            const ids = Array.from(checkedBoxes).map(cb => cb.dataset.id);
            const count = ids.length;
            selectedServices = ids;
            if (selectedCountEl) selectedCountEl.textContent = `${count} service${count === 1 ? '' : 's'} selected`;
            if (floatingMenu) floatingMenu.style.display = count > 0 ? 'block' : 'none';
        }

        // Wire individual checkboxes to floating menu
        rowCheckboxes.forEach(cb => {
            cb.addEventListener('change', updateFloatingMenu);
        });

        // Wire select-all to floating menu
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const checked = !!this.checked;
                rowCheckboxes.forEach(cb => {
                    cb.checked = checked;
                });
                updateFloatingMenu();
            });
        }

        // Close floating menu
        const closeBulkMenu = document.getElementById('closeBulkMenu');
        if (closeBulkMenu) closeBulkMenu.addEventListener('click', function() {
            if (floatingMenu) floatingMenu.style.display = 'none';
        });

        // Bulk deactivate click
        if (bulkDeactivateBtn) {
            bulkDeactivateBtn.addEventListener('click', function() {
                if (!selectedServices.length) return;
                const serviceText = selectedServices.length === 1 ? 'service' : 'services';
                const message = `Mark ${selectedServices.length} ${serviceText} as Not Available?`;
                showConfirmationModal(
                    'Confirm Bulk Not Available',
                    message,
                    `services_management.php?action=bulk_deactivate_services&ids=${selectedServices.join(',')}`
                );
            });
        }

        // Bulk activate click
        if (bulkActivateBtn) {
            bulkActivateBtn.addEventListener('click', function() {
                if (!selectedServices.length) return;
                const serviceText = selectedServices.length === 1 ? 'service' : 'services';
                const message = `Mark ${selectedServices.length} ${serviceText} as Available?`;
                showConfirmationModal(
                    'Confirm Bulk Available',
                    message,
                    `services_management.php?action=bulk_activate_services&ids=${selectedServices.join(',')}`
                );
            });
        }

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
                    return !text.includes('No services found') && !text.includes('No data found');
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
                    return !row.textContent.includes('No services found');
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
                    return !text.includes('No services found') && !text.includes('No data found');
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
                    return !row.textContent.includes('No services found');
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