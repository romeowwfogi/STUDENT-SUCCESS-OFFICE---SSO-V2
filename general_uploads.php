<?php
// Authentication middleware - protect this page
require_once 'middleware/auth.php';
include 'connection/db_connect.php';
require_once 'function/decrypt.php';

// Fetch data from general_uploads table
$general_uploads = [];
$gu_table = $conn->query("SHOW TABLES LIKE 'general_uploads'");
if ($gu_table && $gu_table->num_rows > 0) {
    $gu_rows = $conn->query("SELECT id, title, file_url, status FROM `general_uploads` ORDER BY id DESC");
    if ($gu_rows) {
        $general_uploads = $gu_rows->fetch_all(MYSQLI_ASSOC);
        $gu_rows->close();
    }
}
if ($gu_table) {
    $gu_table->close();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Success Office - General Uploads</title>
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
            background: #007bff;
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

        /* Loading Overlay Styles (match applicant_types.php) */
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

        /* Modal Button Hover Effects */
        #modalCancelBtn:hover {
            background: var(--color-hover) !important;
            border-color: var(--color-border) !important;
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
            box-shadow: 0 6px 20px rgba(79, 172, 254, 0.5) !important;
        }

        #cancelNewCycleModal:active,
        #confirmNewCycleModal:active {
            transform: translateY(0);
        }

        /* Input Focus States */
        #modal_cycle_name:focus {
            outline: none !important;
            border-color: var(--color-primary) !important;
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
            border-color: var(--color-primary);
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
            background: #f7fafc !important;
            border-color: #e2e8f0 !important;
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

        /* Status badge styles */
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-under-review {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        /* Export Modal Styles */
        .file-type-option:hover {
            background: var(--color-hover) !important;
            border-color: var(--color-border) !important;
            transform: translateY(-1px);
        }

        .file-type-option input[type="radio"]:checked+div {
            color: inherit;
        }

        .file-type-option:has(input[type="radio"]:checked) {
            border-color: var(--color-primary) !important;
            background: var(--color-hover) !important;
            box-shadow: 0 0 0 3px rgba(24, 165, 88, 0.15);
        }

        .column-option:hover {
            background-color: var(--color-hover) !important;
        }

        .column-option:has(input[type="checkbox"]:checked) {
            background-color: var(--color-hover) !important;
            border: 1px solid var(--color-primary);
        }

        #selectAllColumns:hover,
        #deselectAllColumns:hover {
            background: #f7fafc !important;
            border-color: #cbd5e0 !important;
            transform: translateY(-1px);
        }

        #closeExportModal:hover {
            background: rgba(0, 0, 0, 0.1) !important;
            transform: scale(1.05);
        }

        #cancelExportModal:hover {
            background: #f7fafc !important;
            border-color: #cbd5e0 !important;
            transform: translateY(-1px);
        }

        #confirmExportModal:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5) !important;
        }

        #cancelExportModal:active,
        #confirmExportModal:active {
            transform: translateY(0);
        }

        /* Export Modal Animation */
        #exportModal .modal-overlay>div {
            animation: slideUp 0.3s ease;
        }

        /* Column Drag and Drop Styles */
        .draggable-column {
            cursor: move;
            position: relative;
        }

        .column-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .column-title {
            flex: 1;
        }



        .column-dragging {
            opacity: 0.5 !important;
            background-color: rgba(59, 130, 246, 0.1);
            transform: rotate(1deg);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            z-index: 1000;
            position: relative;
        }

        .column-drag-over {
            border-left: 3px solid #3b82f6;
            background-color: rgba(59, 130, 246, 0.1);
        }

        /* Visual feedback for draggable columns */
        .draggable-column:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }

        /* Smooth transitions for column reordering */
        th.draggable-column {
            transition: background-color 0.2s ease, border 0.2s ease, transform 0.2s ease;
        }

        /* Update File Modal: applicant_types-style animations and responsiveness */
        @keyframes float {

            0%,
            100% {
                transform: translateY(0px) rotate(0deg);
            }

            50% {
                transform: translateY(-20px) rotate(180deg);
            }
        }

        #updateFileModal.show>div {
            transform: scale(1) !important;
            opacity: 1 !important;
        }

        #updateFileInput:focus {
            border-color: var(--color-primary) !important;
            box-shadow: 0 0 0 4px rgba(24, 165, 88, 0.15) !important;
            outline: none !important;
            background: var(--color-card) !important;
            color: var(--color-text) !important;
        }

        #updateFileModal button[type="button"]:hover {
            background: var(--color-hover) !important;
            border-color: var(--color-border) !important;
            transform: translateY(-2px);
        }

        #updateFileModal #confirmUpdateFileModal:not([disabled]):hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(24, 165, 88, 0.5) !important;
        }

        #updateFileModal #confirmUpdateFileModal:not([disabled]):hover>div {
            left: 100% !important;
        }

        #closeUpdateFileModal:hover {
            background: rgba(255, 255, 255, 0.3) !important;
            transform: scale(1.1);
        }

        @media (max-width: 768px) {
            #updateFileModal>div {
                width: 95% !important;
                margin: 10px auto !important;
                max-height: calc(100vh - 20px) !important;
            }

            #updateFileModal>div>div:last-child {
                padding: 40px 20px 20px 20px !important;
            }

            #updateFileModal h3 {
                font-size: 1.6rem !important;
            }

            #updateFileModal p {
                font-size: 1rem !important;
            }

            #updateFileModal input {
                padding: 12px 16px !important;
                font-size: 0.95rem !important;
            }

            #updateFileModal button {
                padding: 12px 20px !important;
                font-size: 0.95rem !important;
            }
        }

        @media (max-height: 600px) {
            #updateFileModal>div {
                max-height: calc(100vh - 10px) !important;
            }

            #updateFileModal>div>div:last-child {
                padding: 30px 40px 20px 40px !important;
            }
        }

        /* File Preview Modal: applicant_types-style animations and responsiveness */
        #filePreviewModal.show>div {
            transform: scale(1) !important;
            opacity: 1 !important;
        }

        #closeFilePreviewBtn:hover {
            background: rgba(255, 255, 255, 0.3) !important;
            transform: scale(1.1);
        }

        #filePreviewViewLink:hover {
            background: #f7fafc !important;
            border-color: #cbd5e0 !important;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            #filePreviewModal>div {
                width: 95% !important;
                margin: 10px auto !important;
                max-height: calc(100vh - 20px) !important;
            }

            #filePreviewModal h3 {
                font-size: 1.6rem !important;
            }
        }

        @media (max-height: 600px) {
            #filePreviewModal>div {
                max-height: calc(100vh - 10px) !important;
            }
        }
    </style>
</head>

<body>
    <!-- Loading Overlay (consistent with applicant_types.php) -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <div class="loading-text">Processing...</div>
        </div>
    </div>
    <?php if (isset($_SESSION['message'])) {
        echo '<script>window.__FLASH_MESSAGE__ = ' . json_encode($_SESSION['message']) . ';</script>';
        unset($_SESSION['message']);
    } ?>
    <script>
        function showFeedbackModal(message, type) {
            const overlay = document.createElement('div');
            overlay.style.position = 'fixed';
            overlay.style.left = '0';
            overlay.style.top = '0';
            overlay.style.width = '100%';
            overlay.style.height = '100%';
            overlay.style.backgroundColor = 'rgba(0,0,0,0.4)';
            overlay.style.zIndex = '1100';
            overlay.style.display = 'flex';
            overlay.style.alignItems = 'center';
            overlay.style.justifyContent = 'center';

            const box = document.createElement('div');
            box.style.background = 'white';
            box.style.borderRadius = '16px';
            box.style.padding = '20px';
            box.style.maxWidth = '420px';
            box.style.width = '90%';
            box.style.boxShadow = '0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04)';

            const color = type === 'success' ? '#38a169' : '#e53e3e';
            const title = type === 'success' ? 'Success' : 'Error';

            box.innerHTML = '<h3 style="margin:0 0 12px 0;color:' + color + ';font-size:1.2rem;font-weight:600;">' + title + '</h3>' +
                '<p style="margin:0 0 16px 0;color:#2d3748;font-size:0.95rem;">' + message + '</p>' +
                '<div style="display:flex;gap:8px;justify-content:flex-end;">' +
                '  <button id="feedbackCloseBtn" style="background:#4a5568;color:#fff;border:none;border-radius:8px;padding:8px 14px;cursor:pointer;">Close</button>' +
                '</div>';

            overlay.appendChild(box);
            document.body.appendChild(overlay);

            document.getElementById('feedbackCloseBtn').addEventListener('click', () => {
                overlay.remove();
            });

            setTimeout(() => {
                if (document.body.contains(overlay)) overlay.remove();
            }, 4000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (window.__FLASH_MESSAGE__ && window.__FLASH_MESSAGE__.text) {
                showFeedbackModal(window.__FLASH_MESSAGE__.text, window.__FLASH_MESSAGE__.type || 'success');
                delete window.__FLASH_MESSAGE__;
            }
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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
                <div class="header__actions">
                    <button class="btn btn--secondary" onclick="window.location.href='application_management.php'">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Admission List
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
                        <h2 class="table-container__title">General Uploads</h2>
                        <p class="table-container__subtitle">Manage, modify, add new upload images.</p>
                    </div>
                    <div class="filtration_container">
                        <div class="search_input_container">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search-icon lucide-search">
                                <path d="m21 21-4.34-4.34" />
                                <circle cx="11" cy="11" r="8" />
                            </svg>
                            <input type="text" id="applicantSearchInput" placeholder="Search uploads...">
                        </div>
                    </div>
                    <table class="table" id="applicantsTable">
                        <thead>
                            <tr>
                                <th class="sortable draggable-column" data-column="NO" data-type="numeric" draggable="true">NO
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th class="sortable draggable-column" data-column="Title" draggable="true">TITLE
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th class="sortable draggable-column" data-column="File URL" draggable="true">
                                    <div class="column-header">
                                        <span class="column-title">FILE URL</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                            <path d="m3 16 4 4 4-4" />
                                            <path d="M7 20V4" />
                                            <path d="M20 8h-5" />
                                            <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                            <path d="M15 14h5l-5 6h5" />
                                        </svg>
                                    </div>
                                </th>
                                <th class="sortable draggable-column" data-column="File Preview" draggable="true">
                                    <div class="column-header">
                                        <span class="column-title">FILE PREVIEW</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                            <path d="m3 16 4 4 4-4" />
                                            <path d="M7 20V4" />
                                            <path d="M20 8h-5" />
                                            <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                            <path d="M15 14h5l-5 6h5" />
                                        </svg>
                                    </div>
                                </th>
                                <th class="sortable draggable-column" data-column="Status" draggable="true">
                                    <div class="column-header">
                                        <span class="column-title">STATUS</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                            <path d="m3 16 4 4 4-4" />
                                            <path d="M7 20V4" />
                                            <path d="M20 8h-5" />
                                            <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                            <path d="M15 14h5l-5 6h5" />
                                        </svg>
                                    </div>
                                </th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($general_uploads)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center;">No general uploads found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($general_uploads as $idx => $upload): ?>
                                    <tr>
                                        <?php $url = $upload['file_url'] ?? '';
                                        $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION)); ?>
                                        <td data-cell='NO'><?php echo $idx + 1; ?></td>
                                        <td data-cell='TITLE'><?php echo htmlspecialchars($upload['title'] ?? ''); ?></td>
                                        <td data-cell='FILE URL'>
                                            <?php if (!empty($url)): ?>
                                                <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" style="color:#007bff;text-decoration:none;">Open</a>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td data-cell='FILE PREVIEW'>
                                            <?php if (!empty($url) && in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'])): ?>
                                                <a href="#" onclick="openFilePreview('<?php echo htmlspecialchars($url, ENT_QUOTES); ?>','<?php echo htmlspecialchars($upload['title'] ?? '', ENT_QUOTES); ?>'); return false;" title="Preview image">
                                                    <img src="<?php echo htmlspecialchars($url); ?>" alt="Preview" style="width:80px;height:50px;object-fit:cover;border-radius:6px;border:1px solid #e2e8f0;" />
                                                </a>
                                            <?php elseif (!empty($url) && $ext === 'pdf'): ?>
                                                <button class="table__btn table__btn--view" onclick="openFilePreview('<?php echo htmlspecialchars($url, ENT_QUOTES); ?>','<?php echo htmlspecialchars($upload['title'] ?? '', ENT_QUOTES); ?>')">Preview</button>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td data-cell='STATUS'>
                                            <span class="status-badge status-<?php echo strtolower(htmlspecialchars($upload['status'] ?? '')); ?>">
                                                <?php echo htmlspecialchars($upload['status'] ?? ''); ?>
                                            </span>
                                        </td>
                                        <td class='table_actions actions'>
                                            <div class='table-controls'>
                                                <?php if (!empty($url)): ?>
                                                    <button type="button"
                                                        class="table__btn table__btn--update"
                                                        data-id="<?php echo htmlspecialchars($upload['id'] ?? '', ENT_QUOTES); ?>"
                                                        data-title="<?php echo htmlspecialchars($upload['title'] ?? '', ENT_QUOTES); ?>"
                                                        data-current-url="<?php echo htmlspecialchars($url, ENT_QUOTES); ?>">
                                                        Update
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- File Preview Modal (styled like applicant_types/create & Update modal) -->
                    <div id="filePreviewModal" class="modal-overlay" style="background: linear-gradient(135deg, rgba(24,165,88,0.1) 0%, rgba(19,101,21,0.1) 100%); backdrop-filter: blur(8px);">
                        <div style="background: var(--color-card); border-radius: 24px; max-width: 900px; width: 95%; margin: 0 auto; max-height: calc(100vh - 40px); overflow: hidden; box-shadow: 0 25px 80px rgba(0,0,0,0.15), 0 10px 40px rgba(0,0,0,0.1); border: 1px solid var(--color-border); position: relative; transform: scale(0.9); opacity: 0; transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); display: flex; flex-direction: column;">
                            <!-- Decorative Header Background -->
                            <div style="height: 120px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); position: relative; overflow: hidden;">
                                <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%; animation: float 6s ease-in-out infinite;"></div>
                                <div style="position: absolute; bottom: -30px; left: -30px; width: 100px; height: 100px; background: rgba(255,255,255,0.08); border-radius: 50%; animation: float 8s ease-in-out infinite reverse;"></div>

                                <!-- Close Button -->
                                <button type="button" id="closeFilePreviewBtn" style="position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.2); border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; transition: all 0.3s ease; backdrop-filter: blur(10px);">&times;</button>

                                <!-- Modal Icon and Title -->
                                <div style="position: absolute; bottom: -40px; left: 50%; transform: translateX(-50%); text-align: center;">
                                    <div style="width: 80px; height: 80px; background: var(--color-card); border-radius: 20px; margin: 0 auto 16px auto; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 32px rgba(0,0,0,0.1); border: 4px solid rgba(255,255,255,0.9);">
                                        <svg style="width: 40px; height: 40px; color: #18a558;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A2 2 0 0122 9.618v4.764a2 2 0 01-2.447 1.894L15 14M4 6h8m-8 4h8m-8 4h8" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal Content -->
                            <div style="flex: 1; overflow-y: auto; padding: 60px 40px 40px 40px; text-align: center;">
                                <h3 id="filePreviewTitle" style="margin: 0 0 12px 0; color: #1a202c; font-size: 2rem; font-weight: 800; letter-spacing: -0.025em;">File Preview</h3>
                                <div id="filePreviewContent" style="border: 3px dashed #cbd5e0; border-radius: 16px; padding: 24px; text-align: center; background: #f8fafc; transition: all 0.3s ease; position: relative; overflow: hidden; min-height: 200px;"></div>
                                <div style="display:flex;justify-content:flex-end;margin-top:20px;">
                                    <a id="filePreviewViewLink" href="#" target="_blank" style="display:inline-block; padding: 12px 18px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.95rem; text-decoration: none;">View URL</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Update File Modal (styled like applicant_types create modal) -->
                    <div id="updateFileModal" class="modal-overlay" style="background: linear-gradient(135deg, rgba(24,165,88,0.1) 0%, rgba(19,101,21,0.1) 100%); backdrop-filter: blur(8px);">
                        <div style="background: var(--color-card); border-radius: 24px; max-width: 600px; width: 95%; margin: 0 auto; max-height: calc(100vh - 40px); overflow: hidden; box-shadow: 0 25px 80px rgba(0,0,0,0.15), 0 10px 40px rgba(0,0,0,0.1); border: 1px solid var(--color-border); position: relative; transform: scale(0.9); opacity: 0; transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); display: flex; flex-direction: column;">
                            <!-- Decorative Header Background -->
                            <div style="height: 120px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); position: relative; overflow: hidden;">
                                <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%; animation: float 6s ease-in-out infinite;"></div>
                                <div style="position: absolute; bottom: -30px; left: -30px; width: 100px; height: 100px; background: rgba(255,255,255,0.08); border-radius: 50%; animation: float 8s ease-in-out infinite reverse;"></div>

                                <!-- Close Button -->
                                <button type="button" id="closeUpdateFileModal" style="position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.2); border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; transition: all 0.3s ease; backdrop-filter: blur(10px);">&times;</button>

                                <!-- Modal Icon and Title -->
                                <div style="position: absolute; bottom: -40px; left: 50%; transform: translateX(-50%); text-align: center;">
                                    <div style="width: 80px; height: 80px; background: var(--color-card); border-radius: 20px; margin: 0 auto 16px auto; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 32px rgba(0,0,0,0.1); border: 4px solid rgba(255,255,255,0.9);">
                                        <svg style="width: 40px; height: 40px; color: #18a558;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v16h16M16 12l-4-4-4 4"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal Content -->
                            <div style="flex: 1; overflow-y: auto; padding: 60px 40px 40px 40px; text-align: center;">
                                <h3 id="updateFileModalTitle" style="margin: 0 0 12px 0; color: #1a202c; font-size: 2rem; font-weight: 800; letter-spacing: -0.025em;">Update File</h3>
                                <p id="updateFileModalSubtitle" style="color: #718096; margin: 0 0 32px 0; line-height: 1.6; font-size: 1.1rem;">Choose a new file and preview before saving.</p>

                                <!-- Form -->
                                <div style="text-align: left;">
                                    <div style="margin-bottom: 28px;">
                                        <label for="updateFileInput" style="display: block; margin-bottom: 10px; font-weight: 700; color: #2d3748; font-size: 1rem; display: flex; align-items: center; gap: 8px;">
                                            <svg style="width: 18px; height: 18px; color: #18a558;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7h4m0 0v4m0-4l-5 5M2 12l9 9 7-7-9-9-7 7z" />
                                            </svg>
                                            Select File
                                        </label>
                                        <input type="file" id="updateFileInput" accept=".png,.jpg,.jpeg,.gif,.webp,.svg,.pdf" style="width: 100%; padding: 16px 20px; border: 3px solid #e2e8f0; border-radius: 16px; font-size: 1rem; transition: all 0.3s ease; box-sizing: border-box; background: #f8fafc; color: #2d3748; font-weight: 500;" />
                                        <div style="margin-top: 8px; color: #718096; font-size: 0.95rem;">Supported: images (png, jpg, jpeg, gif, webp, svg) and PDF.</div>
                                    </div>

                                    <div id="updateFilePreview" style="border: 3px dashed #cbd5e0; border-radius: 16px; padding: 24px; text-align: center; background: #f8fafc; transition: all 0.3s ease; position: relative; overflow: hidden; min-height: 220px; display: flex; align-items: center; justify-content: center; color: #718096;">No file selected</div>

                                    <div id="updateFileCurrentLinkWrap" style="display: none; margin-top: 12px;">
                                        <a id="updateFileCurrentLink" href="#" target="_blank" style="display:inline-block; padding: 10px 16px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; text-decoration: none;">View current file</a>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div style="display: flex; gap: 16px; justify-content: center; margin-top: 28px;">
                                    <button id="cancelUpdateFileModal" type="button" style="flex: 1; padding: 16px 32px; border: 3px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 16px; font-weight: 700; font-size: 1rem; cursor: pointer; outline: none;">Cancel</button>
                                    <button id="confirmUpdateFileModal" type="button" disabled style="flex: 1; padding: 16px 32px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 16px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: all 0.3s ease; outline: none; box-shadow: 0 8px 32px rgba(24, 165, 88, 0.4); position: relative; overflow: hidden;">
                                        <span style="position: relative; z-index: 2;">Save</span>
                                        <div style="position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent); transition: left 0.5s ease;"></div>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>



                    <script>
                        // Update File Modal logic (UI only, no submission yet)
                        (function setupUpdateFileModal() {
                            var modalOverlay = document.getElementById('updateFileModal');
                            var titleEl = document.getElementById('updateFileModalTitle');
                            var subtitleEl = document.getElementById('updateFileModalSubtitle');
                            var fileInput = document.getElementById('updateFileInput');
                            var previewBox = document.getElementById('updateFilePreview');
                            var closeBtn = document.getElementById('closeUpdateFileModal');
                            var cancelBtn = document.getElementById('cancelUpdateFileModal');
                            var confirmBtn = document.getElementById('confirmUpdateFileModal');
                            var currentLinkWrap = document.getElementById('updateFileCurrentLinkWrap');
                            var currentLink = document.getElementById('updateFileCurrentLink');
                            // Pull credentials from PHP session
                            var SESSION_EMAIL = "<?php echo isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email'], ENT_QUOTES) : ''; ?>";
                            var SESSION_PASSWORD = "<?php echo isset($_SESSION['user_password']) ? htmlspecialchars($_SESSION['user_password'], ENT_QUOTES) : ''; ?>";

                            var objectUrl = null;
                            var currentId = null;

                            function resetPreview() {
                                if (objectUrl) {
                                    URL.revokeObjectURL(objectUrl);
                                    objectUrl = null;
                                }
                                previewBox.innerHTML = 'No file selected';
                                previewBox.style.color = '#718096';
                                confirmBtn.disabled = true;
                            }

                            function renderPreviewFromFile(file) {
                                resetPreview();
                                if (!file) return;
                                objectUrl = URL.createObjectURL(file);
                                var type = (file.type || '').toLowerCase();
                                var name = (file.name || '').toLowerCase();
                                var ext = name.split('.').pop();

                                previewBox.innerHTML = '';
                                previewBox.style.color = '#1a202c';

                                if (type.startsWith('image/') || ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'].includes(ext)) {
                                    var img = document.createElement('img');
                                    img.src = objectUrl;
                                    img.style.width = '100%';
                                    img.style.maxHeight = '60vh';
                                    img.style.objectFit = 'contain';
                                    img.alt = 'Selected image preview';
                                    previewBox.appendChild(img);
                                } else if (type === 'application/pdf' || ext === 'pdf') {
                                    var iframe = document.createElement('iframe');
                                    iframe.src = objectUrl;
                                    iframe.style.width = '100%';
                                    iframe.style.height = '60vh';
                                    iframe.style.border = 'none';
                                    previewBox.appendChild(iframe);
                                } else {
                                    var msg = document.createElement('div');
                                    msg.textContent = 'Preview not available for this file type.';
                                    msg.style.color = '#4a5568';
                                    previewBox.appendChild(msg);
                                }

                                confirmBtn.disabled = false; // UI only; no action yet
                            }

                            function openUpdateModal(opts) {
                                titleEl.textContent = opts && opts.title ? ('Update: ' + opts.title) : 'Update File';
                                subtitleEl.textContent = 'Choose a new file and preview before saving.';
                                resetPreview();
                                fileInput.value = '';
                                currentId = (opts && opts.id) ? opts.id : null;
                                if (opts && opts.currentUrl) {
                                    currentLink.href = opts.currentUrl;
                                    currentLinkWrap.style.display = 'block';
                                } else {
                                    currentLink.href = '#';
                                    currentLinkWrap.style.display = 'none';
                                }
                                modalOverlay.classList.add('show');
                            }

                            function closeUpdateModal() {
                                modalOverlay.classList.remove('show');
                                resetPreview();
                            }

                            // Loader functions (consistent with applicant_types.php)
                            function showLoader() {
                                var loader = document.getElementById('loadingOverlay');
                                if (loader) {
                                    loader.style.display = 'flex';
                                }
                            }

                            function hideLoader() {
                                var loader = document.getElementById('loadingOverlay');
                                if (loader) {
                                    loader.style.display = 'none';
                                }
                            }

                            // Wire buttons
                            closeBtn.addEventListener('click', closeUpdateModal);
                            cancelBtn.addEventListener('click', closeUpdateModal);

                            // File change
                            fileInput.addEventListener('change', function() {
                                var file = this.files && this.files[0];
                                renderPreviewFromFile(file);
                            });

                            // Hook Update buttons in table
                            document.querySelectorAll('.table__btn--update').forEach(function(btn) {
                                btn.addEventListener('click', function() {
                                    var title = this.getAttribute('data-title') || '';
                                    var currentUrl = this.getAttribute('data-current-url') || '';
                                    var id = this.getAttribute('data-id') || '';
                                    openUpdateModal({
                                        id: id,
                                        title: title,
                                        currentUrl: currentUrl
                                    });
                                });
                            });

                            // Confirm: send multipart/form-data to API via fetch
                            confirmBtn.addEventListener('click', function() {
                                try {
                                    var file = fileInput.files && fileInput.files[0];
                                    if (!currentId) {
                                        (typeof showFeedbackModal === 'function') ?
                                        showFeedbackModal('Missing record ID.', 'error'): alert('Missing record ID.');
                                        return;
                                    }
                                    if (!file) {
                                        (typeof showFeedbackModal === 'function') ?
                                        showFeedbackModal('Please select a file to upload.', 'error'): alert('Please select a file to upload.');
                                        return;
                                    }

                                    var fd = new FormData();
                                    fd.append('id', currentId);
                                    fd.append('file', file, file.name);
                                    if (SESSION_EMAIL) fd.append('email', SESSION_EMAIL);
                                    if (SESSION_PASSWORD) fd.append('password', SESSION_PASSWORD);

                                    var API_URL = '<?php echo $UPDATE_GENERAL_IMAGES_API; ?>';

                                    confirmBtn.disabled = true;
                                    var prevText = confirmBtn.textContent;
                                    confirmBtn.textContent = 'Saving...';
                                    showLoader();

                                    $.ajax({
                                        url: API_URL,
                                        type: 'POST',
                                        data: fd,
                                        processData: false,
                                        contentType: false,
                                        xhrFields: {
                                            withCredentials: true
                                        }, // same as fetch credentials: 'include'
                                        success: function(res) {
                                            var msg = 'Updated successfully.';
                                            if (res && res.message) msg = res.message;

                                            (typeof showFeedbackModal === 'function') ?
                                            showFeedbackModal(msg, 'success'): alert(msg);

                                            closeUpdateModal();
                                            setTimeout(function() {
                                                window.location.reload();
                                            }, 600);
                                        },
                                        error: function(xhr, status, error) {
                                            var msg = 'Update failed: ' + (xhr.responseText || error);
                                            (typeof showFeedbackModal === 'function') ?
                                            showFeedbackModal(msg, 'error'): alert(msg);
                                        },
                                        complete: function() {
                                            confirmBtn.disabled = false;
                                            confirmBtn.textContent = prevText;
                                            hideLoader();
                                        }
                                    });

                                } catch (e) {
                                    (typeof showFeedbackModal === 'function') ?
                                    showFeedbackModal('Unexpected error occurred.', 'error'): alert('Unexpected error occurred.');
                                    confirmBtn.disabled = false;
                                    confirmBtn.textContent = 'Save';
                                    hideLoader();
                                }
                            });
                        })();

                        function openFilePreview(url, title) {
                            var overlay = document.getElementById('filePreviewModal');
                            var content = document.getElementById('filePreviewContent');
                            var titleEl = document.getElementById('filePreviewTitle');
                            var viewLink = document.getElementById('filePreviewViewLink');

                            titleEl.textContent = title && title.length ? title : 'File Preview';
                            viewLink.href = url;
                            content.innerHTML = '';

                            try {
                                var clean = url.split('?')[0].split('#')[0];
                                var ext = (clean.split('.').pop() || '').toLowerCase();
                                if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'].includes(ext)) {
                                    var img = document.createElement('img');
                                    img.src = url;
                                    img.style.width = '100%';
                                    img.style.maxHeight = '70vh';
                                    img.style.objectFit = 'contain';
                                    img.alt = title || 'Image preview';
                                    content.appendChild(img);
                                } else if (ext === 'pdf') {
                                    var iframe = document.createElement('iframe');
                                    iframe.src = url;
                                    iframe.style.width = '100%';
                                    iframe.style.height = '70vh';
                                    iframe.style.border = 'none';
                                    content.appendChild(iframe);
                                } else {
                                    var msg = document.createElement('div');
                                    msg.textContent = 'Preview not available. Use View URL to open.';
                                    msg.style.color = '#4a5568';
                                    content.appendChild(msg);
                                }
                            } catch (e) {
                                var err = document.createElement('div');
                                err.textContent = 'Unable to render preview.';
                                err.style.color = '#e53e3e';
                                content.appendChild(err);
                            }

                            overlay.classList.add('show');
                        }

                        function closeFilePreview() {
                            var overlay = document.getElementById('filePreviewModal');
                            overlay.classList.remove('show');
                        }

                        (function setupFilePreviewModal() {
                            var closeBtn = document.getElementById('closeFilePreviewBtn');
                            var overlay = document.getElementById('filePreviewModal');
                            if (closeBtn) closeBtn.addEventListener('click', closeFilePreview);
                            if (overlay) {
                                overlay.addEventListener('click', function(e) {
                                    if (e.target === overlay) closeFilePreview();
                                });
                            }
                            document.addEventListener('keydown', function(e) {
                                if (e.key === 'Escape') closeFilePreview();
                            });
                        })();
                    </script>


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
            <script>
                (function() {
                    class TableSorter {
                        constructor() {
                            this.table = document.getElementById('applicantsTable');
                            this.tbody = this.table ? this.table.querySelector('tbody') : null;
                            this.headers = this.table ? this.table.querySelectorAll('th.sortable') : [];
                            this.currentSort = {
                                column: null,
                                direction: 'asc'
                            };
                            this.currentRows = [];
                            this.init();
                        }

                        init() {
                            if (!this.table || !this.headers.length) return;
                            this.updateCurrentRows();
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
                            this.currentRows = Array.from(this.tbody.querySelectorAll('tr'));
                        }

                        sortBy(column, direction = null, type = 'text') {
                            if (!column) return;

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
                            this.updateCurrentRows();

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
                                    default:
                                        comparison = this.compareText(aValue, bValue);
                                }

                                return direction === 'asc' ? comparison : -comparison;
                            });

                            this.currentRows.forEach(row => this.tbody.appendChild(row));
                            this.updateSortIndicators(column, direction);

                            // Dispatch custom event for pagination integration
                            if (this.table) {
                                this.table.dispatchEvent(new CustomEvent('tableSorted', {
                                    detail: { column, direction }
                                }));
                            }
                        }

                        getCellValue(row, column) {
                            const cell = row.querySelector(`[data-cell="${column}"]`) ||
                                row.querySelector(`[data-column="${column}"]`) ||
                                row.querySelector(`td:nth-child(${this.getColumnIndex(column)})`);
                            return cell ? cell.textContent.trim() : '';
                        }

                        getColumnIndex(column) {
                            const headers = Array.from(this.headers);
                            const header = headers.find(h => h.dataset.column === column);
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

                        updateSortIndicators(activeColumn, direction) {
                            this.headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
                            const active = Array.from(this.headers).find(h => h.dataset.column === activeColumn);
                            if (active) active.classList.add(`sort-${direction}`);
                        }
                    }

                    // Pagination Functionality (client-side)
                    class TablePagination {
                        constructor() {
                            this.table = document.getElementById('applicantsTable');
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

                            // Capture data rows (exclude empty state row)
                            this.allRows = Array.from(this.tbody.querySelectorAll('tr')).filter(row => {
                                const text = row.textContent.trim();
                                return !text.includes('No general uploads found');
                            });

                            this.filteredRows = [...this.allRows];

                            this.setupEventListeners();
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

                            // Prev button
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
                                    const totalPages = Math.ceil(this.filteredRows.length / this.rowsPerPage) || 1;
                                    if (this.currentPage < totalPages) {
                                        this.currentPage++;
                                        this.updateDisplay();
                                    }
                                });
                            }

                            // Search integration (if present)
                            const searchInput = document.getElementById('applicantSearchInput');
                            if (searchInput) {
                                searchInput.addEventListener('keyup', () => {
                                    // Allow any external filter logic to apply first
                                    setTimeout(() => {
                                        this.updateFilteredRows();
                                        this.currentPage = 1;
                                        this.updateDisplay();
                                    }, 10);
                                });
                            }
                        }

                        updateFilteredRows() {
                            // Use currently visible rows, keep sort order
                            const currentRows = Array.from(this.tbody.querySelectorAll('tr')).filter(row => {
                                const text = row.textContent.trim();
                                return !text.includes('No general uploads found') && !text.includes('No data found');
                            });
                            this.filteredRows = currentRows.filter(row => row.style.display !== 'none');
                        }

                        updateDisplay() {
                            const totalRows = this.filteredRows.length;
                            const totalPages = Math.max(1, Math.ceil(totalRows / this.rowsPerPage));
                            const startIndex = (this.currentPage - 1) * this.rowsPerPage;
                            const endIndex = startIndex + this.rowsPerPage;

                            // Hide all rows
                            this.allRows.forEach(row => { row.style.display = 'none'; });

                            // Show only rows for current page
                            this.filteredRows.slice(startIndex, endIndex).forEach(row => { row.style.display = ''; });

                            // Update info text
                            if (this.paginationInfo) {
                                const startItem = totalRows === 0 ? 0 : startIndex + 1;
                                const endItem = Math.min(endIndex, totalRows);
                                this.paginationInfo.textContent = `Showing ${startItem}-${endItem} of ${totalRows} • Page ${this.currentPage}/${totalPages}`;
                            }

                            // Button states
                            if (this.prevButton) {
                                const isFirst = this.currentPage <= 1;
                                this.prevButton.disabled = isFirst;
                                this.prevButton.classList.toggle('pagination__button--disabled', isFirst);
                            }
                            if (this.nextButton) {
                                const isLast = this.currentPage >= totalPages || totalRows === 0;
                                this.nextButton.disabled = isLast;
                                this.nextButton.classList.toggle('pagination__button--disabled', isLast);
                            }

                            // Ensure empty-state row shows when no data
                            if (totalRows === 0) {
                                const emptyRow = this.tbody.querySelector('tr[style*="text-align:center"]');
                                if (emptyRow) emptyRow.style.display = '';
                            }
                        }

                        refresh() {
                            this.allRows = Array.from(this.tbody.querySelectorAll('tr')).filter(row => {
                                const text = row.textContent.trim();
                                return !text.includes('No general uploads found');
                            });
                            this.filteredRows = [...this.allRows];
                            this.currentPage = 1;
                            this.updateDisplay();
                        }
                    }

                    document.addEventListener('DOMContentLoaded', () => {
                        const sorter = new TableSorter();
                        const paginator = new TablePagination();

                        // Listen for sorting to re-evaluate pagination window
                        const table = document.getElementById('applicantsTable');
                        if (table) {
                            table.addEventListener('tableSorted', () => {
                                paginator.updateFilteredRows();
                                paginator.currentPage = 1;
                                paginator.updateDisplay();
                            });
                        }

                        // Client-side search filtering
                        const searchInput = document.getElementById('applicantSearchInput');
                        const tbody = table ? table.querySelector('tbody') : null;
                        function filterRows(term) {
                            if (!tbody) return;
                            const q = (term || '').toLowerCase();
                            const rows = Array.from(tbody.querySelectorAll('tr'));
                            rows.forEach(row => {
                                // Skip empty-state row
                                const text = row.textContent.trim();
                                if (text.includes('No general uploads found')) {
                                    row.style.display = q ? 'none' : '';
                                    return;
                                }

                                const title = (row.querySelector("[data-cell='TITLE']")?.textContent || '').toLowerCase();
                                const fileUrl = (row.querySelector("[data-cell='FILE URL']")?.textContent || '').toLowerCase();
                                const status = (row.querySelector("[data-cell='STATUS']")?.textContent || '').toLowerCase();
                                const number = (row.querySelector("[data-cell='NO']")?.textContent || '').toLowerCase();

                                const matches = !q || title.includes(q) || fileUrl.includes(q) || status.includes(q) || number.includes(q);
                                row.style.display = matches ? '' : 'none';
                            });

                            // Sync pagination with filtered visibility
                            paginator.updateFilteredRows();
                            paginator.currentPage = 1;
                            paginator.updateDisplay();
                        }

                        if (searchInput) {
                            searchInput.addEventListener('keyup', function() {
                                filterRows(this.value);
                            });
                        }
                    });
                })();
            </script>
        </main>
    </div>
</body>

</html>