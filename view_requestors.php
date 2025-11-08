<?php
// Authentication middleware - protect this page
require_once 'middleware/auth.php';
include 'connection/db_connect.php';

// Read optional service_id filter
$service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;

// Fetch requests
$requests = [];
$serviceName = null;

$sql = "SELECT sr.request_id, sr.admin_remarks, sr.requested_at, srs.status_name, srs.color_hex, sl.name AS service_name,
        su.first_name, su.middle_name, su.last_name, su.suffix
        FROM services_requests sr
        JOIN services_request_statuses srs ON srs.status_id = sr.status_id
        LEFT JOIN services_list sl ON sl.service_id = sr.service_id
        LEFT JOIN services_users su ON su.id = sr.user_id";
if ($service_id > 0) {
    $sql .= " WHERE sr.service_id = " . $service_id;
}
$sql .= " ORDER BY sr.request_id DESC";

if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    if ($service_id > 0) {
        // Try to derive service name from first row
        foreach ($requests as $r) {
            if (!empty($r['service_name'])) {
                $serviceName = $r['service_name'];
                break;
            }
        }
        if (!$serviceName) {
            // Fallback query to get service name
            $res2 = $conn->query("SELECT name FROM services_list WHERE service_id = " . $service_id . " LIMIT 1");
            if ($res2 && $res2->num_rows > 0) {
                $serviceName = $res2->fetch_assoc()['name'];
            }
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
    <title>Student Success Office - View Requestors</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        /* Keep only page-specific styles; rely on global dashboard.css for layout */
        .status-pill {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            color: #fff;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .remarks-cell {
            width: 200px;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        /* Update modal button hover/active to match other pages */
        #updateModalCancelBtn:hover {
            background: var(--color-card);
            border-color: #cbd5e1;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        #updateModalConfirmBtn:hover {
            box-shadow: 0 6px 18px rgba(24, 165, 88, 0.45);
            filter: brightness(1.03);
        }

        #updateModalCancelBtn:active,
        #updateModalConfirmBtn:active {
            transform: translateY(1px);
        }
    </style>
</head>

<body>
    <!-- Mobile Navbar -->
    <?php include "includes/mobile_navbar.php"; ?>

    <div class="layout">
        <!-- Sidebar -->
        <?php include "includes/sidebar.php"; ?>

        <main class="main-content">
            <header class="header">
                <div class="header__left">
                    <h1 class="header__title">
                        <?php if ($service_id > 0): ?>
                            <?php echo htmlspecialchars(($serviceName ?? ('Service #' . $service_id)) . ' Requestors'); ?>
                        <?php else: ?>
                            Service Requestors
                        <?php endif; ?>
                    </h1>
                </div>
                <div class="header__actions">
                    <button onclick="window.location.href='services_management.php'" class="btn btn--secondary">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Back to Services Management
                    </button>
                </div>
            </header>

            <section class="section active" id="requestors_section" style="margin: 0 20px;">
                <div class="table-container">
                    <div class="table-container__header">
                        <h2 class="table-container__title">Requestors</h2>
                        <p class="table-container__subtitle">List of service requests with status and remarks</p>
                    </div>
                    <div class="filtration_container">
                        <div class="search_input_container">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search-icon lucide-search">
                                <path d="m21 21-4.34-4.34" />
                                <circle cx="11" cy="11" r="8" />
                            </svg>
                            <input type="text" id="requestorsSearchInput" placeholder="Search requestors...">
                        </div>
                    </div>
                    <table class="table" id="requestorsTable">
                        <thead>
                            <tr>
                                <th style="width:40px"><input type="checkbox" id="selectAllRequests"></th>
                                <th class="sortable" data-column="Request ID">ID
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th class="sortable" data-column="Registered Name">Registered Name
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
                                <th style="width:150px;" class="sortable" data-column="Remarks">Remarks
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                        <path d="m3 16 4 4 4-4" />
                                        <path d="M7 20V4" />
                                        <path d="M20 8h-5" />
                                        <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                        <path d="M15 14h5l-5 6h5" />
                                    </svg>
                                </th>
                                <th class="sortable" data-column="Date Request">Date Request
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
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center;">No requests found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($requests as $req): ?>
                                    <tr>
                                        <td data-cell='Select'><input type="checkbox" class="row-select" value="<?php echo (int)$req['request_id']; ?>"></td>
                                        <td data-cell='Request ID'><?php echo (int)$req['request_id']; ?></td>
                                        <td data-cell='Registered Name'>
                                            <?php
                                            $parts = [];
                                            if (!empty($req['first_name'])) $parts[] = $req['first_name'];
                                            if (!empty($req['middle_name'])) $parts[] = $req['middle_name'];
                                            if (!empty($req['last_name'])) $parts[] = $req['last_name'];
                                            $fullName = trim(implode(' ', $parts));
                                            if (!empty($req['suffix'])) {
                                                $fullName = trim($fullName . ' ' . $req['suffix']);
                                            }
                                            echo htmlspecialchars($fullName !== '' ? $fullName : 'N/A');
                                            ?>
                                        </td>
                                        <td data-cell='Status'>
                                            <?php
                                            $statusName = htmlspecialchars($req['status_name']);
                                            $colorHex = isset($req['color_hex']) ? $req['color_hex'] : '#999999';
                                            ?>
                                            <span class="status-pill" style="background-color: <?php echo htmlspecialchars($colorHex); ?>;"><?php echo $statusName; ?></span>
                                        </td>
                                        <td class="remarks-cell" data-cell='Remarks'><?php echo htmlspecialchars($req['admin_remarks'] ?? ''); ?></td>
                                        <td data-cell='Date Request' data-timestamp="<?php echo strtotime($req['requested_at']); ?>"><?php echo htmlspecialchars(date('F j, Y h:i A', strtotime($req['requested_at']))); ?></td>
                                        <td data-cell='Actions'>
                                            <button class="table__btn table__btn--view btn-view-request" data-request-id="<?php echo (int)$req['request_id']; ?>">View</button>
                                            <button class="table__btn table__btn--edit btn-update-request" data-request-id="<?php echo (int)$req['request_id']; ?>">Update</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Pagination placeholder (static UI to mirror layout) -->
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
                            <span class="pagination__info">Showing <?php echo min(count($requests), 10); ?> of <?php echo count($requests); ?></span>
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

    <script>
        // Simple client-side search
        const searchInput = document.getElementById('requestorsSearchInput');
        const table = document.getElementById('requestorsTable');
        const tbody = table ? table.querySelector('tbody') : null;

        if (searchInput && table) {
            searchInput.addEventListener('input', function() {
                const q = this.value.toLowerCase();
                const rows = table.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(q) ? '' : 'none';
                });
            });
        }

        // Select-all behavior
        const selectAll = document.getElementById('selectAllRequests');

        function getRowCheckboxes() {
            return tbody ? Array.from(tbody.querySelectorAll('input.row-select')) : [];
        }
        if (selectAll && tbody) {
            selectAll.addEventListener('change', () => {
                getRowCheckboxes().forEach(cb => {
                    cb.checked = selectAll.checked;
                });
            });
            // Keep select-all state in sync when rows change
            getRowCheckboxes().forEach(cb => {
                cb.addEventListener('change', () => {
                    const cbs = getRowCheckboxes();
                    const allChecked = cbs.length > 0 && cbs.every(x => x.checked);
                    const someChecked = cbs.some(x => x.checked);
                    selectAll.checked = allChecked;
                    selectAll.indeterminate = !allChecked && someChecked;
                });
            });
        }

        // Sorting behavior
        const sortableHeaders = table ? table.querySelectorAll('thead th.sortable') : [];
        sortableHeaders.forEach(th => {
            th.addEventListener('click', () => {
                const column = th.getAttribute('data-column');
                const current = th.getAttribute('data-sort');
                const direction = current === 'asc' ? 'desc' : 'asc';

                // Reset other headers' state and indicators
                sortableHeaders.forEach(h => {
                    h.removeAttribute('data-sort');
                    h.classList.remove('sorted-asc', 'sorted-desc');
                });

                th.setAttribute('data-sort', direction);
                th.classList.add(direction === 'asc' ? 'sorted-asc' : 'sorted-desc');

                const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => !r.querySelector('td[colspan]'));
                const comparator = (a, b) => {
                    const aCell = a.querySelector(`td[data-cell='${column}']`);
                    const bCell = b.querySelector(`td[data-cell='${column}']`);
                    let aVal, bVal;
                    if (column === 'Date Request') {
                        aVal = parseInt(aCell?.getAttribute('data-timestamp') || '0', 10);
                        bVal = parseInt(bCell?.getAttribute('data-timestamp') || '0', 10);
                    } else if (column === 'Request ID') {
                        aVal = parseFloat((aCell?.textContent || '0').trim());
                        bVal = parseFloat((bCell?.textContent || '0').trim());
                    } else {
                        aVal = (aCell?.textContent || '').trim().toLowerCase();
                        bVal = (bCell?.textContent || '').trim().toLowerCase();
                    }
                    if (aVal < bVal) return direction === 'asc' ? -1 : 1;
                    if (aVal > bVal) return direction === 'asc' ? 1 : -1;
                    return 0;
                };
                rows.sort(comparator).forEach(r => tbody.appendChild(r));
            });
        });

        // Actions: View (styled modal) and Update modals
        (function() {
            function $(sel, root) {
                return (root || document).querySelector(sel);
            }

            function $all(sel, root) {
                return Array.from((root || document).querySelectorAll(sel));
            }

            // Create styled View modal (reusing Add Field modal aesthetics)
            const modalHtml = `
                <div id="requestModalOverlay" class="modal-overlay" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 2001; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
                  <div role="dialog" aria-modal="true" aria-labelledby="requestModalTitle" style="background: var(--color-card); border-radius: 20px; max-width: 860px; width: 94%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); position: relative; color: var(--color-text); display: flex; flex-direction: column; max-height: 85vh;">
                    <!-- Close Button -->
                    <button type="button" id="requestModalClose" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px; transition: all 0.2s ease; z-index: 10;">&times;</button>

                    <!-- Modal Header -->
                    <div style="padding: 32px 32px 16px 32px; text-align: center;">
                        <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 16px auto; display: flex; align-items: center; justify-content: center;">
                            <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7Z" />
                            </svg>
                        </div>
                        <h3 id="requestModalTitle" style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.6rem; font-weight: 700; letter-spacing: -0.025em;">View Requestor</h3>
                        <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Detailed request information including account, status, and answers.</p>
                    </div>

                    <!-- Modal Body -->
                    <div id="requestModalBody" style="padding: 0 32px 24px 32px; overflow-y: auto;"></div>

                    <!-- Modal Footer -->
                    <div id="requestModalFooter" class="modal-actions" style="padding: 20px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;"></div>
                  </div>
                </div>`;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const overlay = $('#requestModalOverlay');
            const bodyEl = $('#requestModalBody');
            const footerEl = $('#requestModalFooter');
            const closeBtn = $('#requestModalClose');
            const closeBtnFooter = $('#requestModalCloseFooter');
            const titleEl = $('#requestModalTitle');

            // Ensure global full-screen loader exists (consistent with other pages)
            if (!document.getElementById('loadingOverlay')) {
                document.body.insertAdjacentHTML('afterbegin', `
                  <div id="loadingOverlay" class="loading-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); display: none; justify-content: center; align-items: center; z-index: 3000; backdrop-filter: blur(4px);">
                    <div class="loading-spinner" style="text-align: center; color: white;">
                      <div class="spinner" style="width: 50px; height: 50px; border: 4px solid rgba(255, 255, 255, 0.3); border-top: 4px solid #ffffff; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 20px auto;"></div>
                      <div class="loading-text" style="font-size: 18px; font-weight: 500; color: white; margin-top: 10px;">Processing...</div>
                    </div>
                  </div>`);
            }
            // Ensure the spin keyframes exist for animation
            if (!document.getElementById('ssoLoaderKeyframes')) {
                const style = document.createElement('style');
                style.id = 'ssoLoaderKeyframes';
                style.textContent = '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
                (document.head || document.body).appendChild(style);
            }

            function showLoader() {
                var loader = document.getElementById('loadingOverlay');
                if (loader) {
                    document.body.appendChild(loader);
                    loader.style.display = 'flex';
                }
            }

            function hideLoader() {
                var loader = document.getElementById('loadingOverlay');
                if (loader) loader.style.display = 'none';
            }

            function openModal() {
                overlay.style.display = 'flex';
            }

            function closeModal() {
                overlay.style.display = 'none';
            }

            // Ensure shared confirmation/message modal exists (consistent site design)
            if (!document.getElementById('confirmationModal')) {
                const confirmHtml = `
                  <div id=\"confirmationModal\" style=\"display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3002; align-items: center; justify-content: center; backdrop-filter: blur(4px);\">
                    <div style=\"background: var(--color-card); border-radius: 20px; text-align: center; max-width: 400px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); color: var(--color-text);\">
                      <div style=\"padding: 32px 32px 16px 32px;\">
                        <div id=\"modalIconWrap\" style=\"width: 56px; height: 56px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 16px; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center;\">
                          <svg id=\"modalIconSvg\" style=\"width: 28px; height: 28px; color: white;\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">
                            <path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M5 13l4 4L19 7\"></path>
                          </svg>
                        </div>
                        <h3 id=\"modalTitle\" style=\"margin: 0 0 12px 0; color: #1a202c; font-size: 1.5rem; font-weight: 700; letter-spacing: -0.025em;\">Confirm Action</h3>
                        <p id=\"modalMessage\" style=\"color: #718096; margin: 0; line-height: 1.6; font-size: 0.95rem;\">Are you sure you want to proceed?</p>
                      </div>
                      <div style=\"padding: 16px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;\">
                        <button id=\"modalCancelBtn\" style=\"flex: 1; padding: 12px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;\">Cancel</button>
                        <button id=\"modalConfirmBtn\" style=\"flex: 1; padding: 12px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4);\">Confirm</button>
                      </div>
                    </div>
                  </div>`;
                document.body.insertAdjacentHTML('beforeend', confirmHtml);
                const cm = document.getElementById('confirmationModal');
                cm.addEventListener('click', (e) => {
                    if (e.target === cm) cm.style.display = 'none';
                });
                const cancelBtn = document.getElementById('modalCancelBtn');
                cancelBtn.addEventListener('click', () => {
                    cm.style.display = 'none';
                });
            }

            // Feedback using shared confirmation modal (message-only variant)
            function showFeedbackModal(title, message, variant) {
                const cm = document.getElementById('confirmationModal');
                const modalTitle = document.getElementById('modalTitle');
                const modalMessage = document.getElementById('modalMessage');
                const modalConfirmBtn = document.getElementById('modalConfirmBtn');
                const modalCancelBtn = document.getElementById('modalCancelBtn');
                if (!cm || !modalTitle || !modalMessage || !modalConfirmBtn || !modalCancelBtn) {
                    alert(message || (String(variant).toLowerCase() === 'success' ? 'Request updated successfully.' : 'We couldn’t save your changes. Please try again.'));
                    return;
                }
                const isSuccess = String(variant).toLowerCase() === 'success';
                // Friendly, professional defaults
                modalTitle.textContent = title || 'Update Request';
                modalMessage.textContent = message || (isSuccess ? 'Your changes have been saved successfully.' : 'We couldn’t save your changes. Please try again.');

                // Success/Failure icon and color
                const iconWrap = document.getElementById('modalIconWrap');
                const iconSvg = document.getElementById('modalIconSvg');
                if (iconWrap) {
                    iconWrap.style.background = isSuccess ? 'linear-gradient(135deg, #18a558 0%, #136515 100%)' : 'linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)';
                }
                if (iconSvg) {
                    iconSvg.innerHTML = isSuccess ?
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>' :
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>';
                }

                // Ensure the modal appears above the Update modal
                cm.style.zIndex = '3002';
                modalConfirmBtn.textContent = 'Close';
                modalCancelBtn.style.display = 'none';
                modalConfirmBtn.onclick = () => {
                    cm.style.display = 'none';
                    modalConfirmBtn.textContent = 'Confirm';
                    modalCancelBtn.style.display = '';
                    modalConfirmBtn.onclick = null;
                };
                cm.style.display = 'flex';
            }
            closeBtn.addEventListener('click', closeModal);
            if (closeBtnFooter) closeBtnFooter.addEventListener('click', closeModal);
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) closeModal();
            });

            // Wire View buttons: fetch request details and render sections
            $all('.btn-view-request').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const reqId = this.getAttribute('data-request-id');
                    const row = this.closest('tr');
                    const derivedName = (row?.querySelector("td[data-cell='Registered Name']")?.textContent || '').trim();
                    titleEl.textContent = `View Requestor - ${derivedName || 'Loading...'}`;
                    // Reset footer so Update buttons don't carry over (no footer buttons for View)
                    footerEl.innerHTML = '';
                    // Use full-screen loader while fetching
                    showLoader();
                    bodyEl.innerHTML = '';
                    openModal();
                    try {
                        const resp = await fetch(`get_request_details.php?request_id=${encodeURIComponent(reqId)}`);
                        const data = await resp.json();
                        if (!data || !data.ok) {
                            hideLoader();
                            bodyEl.innerHTML = `<div class="alert alert--error">${(data && data.error) ? data.error : 'Failed to load details.'}</div>`;
                            return;
                        }

                        const fullName = data.full_name || 'N/A';
                        const email = data.email || 'N/A';
                        const statusName = data.status_name || 'N/A';
                        const remarks = data.admin_remarks || '';
                        const colorHex = data.color_hex || '#999999';

                        // Update modal title with registered fullname
                        titleEl.textContent = `View Requestor - ${fullName.replace(/</g,'&lt;')}`;

                        hideLoader();
                        // Build answers table
                        const answersRows = (data.answers || []).map(a => {
                            const label = a.label || '';
                            let val = a.answer_value || '';
                            // If it's a file-like value or URL, render as link
                            const isUrl = /^https?:\/\//i.test(val);
                            const looksFile = /\.(pdf|jpg|jpeg|png|docx?|xlsx?)$/i.test(val);
                            if (isUrl || looksFile) {
                                val = `<a href="${val}" target="_blank" rel="noopener">${val}</a>`;
                            } else {
                                val = val.replace(/</g, '&lt;');
                            }
                            return `<tr><td style="padding:10px; border-bottom:1px solid var(--color-border); width:40%; font-weight:600; color:#2d3748;">${label}</td><td style="padding:10px; border-bottom:1px solid var(--color-border);">${val}</td></tr>`;
                        }).join('');

                        bodyEl.innerHTML = `
                          <div style="display:unset; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                            <section style="background: var(--color-card); border:1px solid var(--color-border); border-radius: 12px; padding: 16px;">
                              <h4 style="margin:0 0 8px 0; font-size:1rem; color:#1a202c;">Account</h4>
                              <div style="display:grid; grid-template-columns: 180px 1fr; gap:8px;">
                                <div style="font-weight:600; color:#2d3748;">Registered Fullname</div>
                                <div>${fullName.replace(/</g,'&lt;')}</div>
                                <div style="font-weight:600; color:#2d3748;">Registered Email</div>
                                <div>${email.replace(/</g,'&lt;')}</div>
                              </div>
                            </section>

                            <section style="background: var(--color-card); border:1px solid var(--color-border); border-radius: 12px; padding: 16px;">
                              <h4 style="margin:0 0 8px 0; font-size:1rem; color:#1a202c;">Request Status</h4>
                              <div style="display:grid; grid-template-columns: 180px 1fr; gap:8px; align-items:center;">
                                <div style="font-weight:600; color:#2d3748;">Status</div>
                                <div><span class="status-pill" style="background:${colorHex};">${statusName.replace(/</g,'&lt;')}</span></div>
                                <div style="font-weight:600; color:#2d3748;">Remarks</div>
                                <div>${(remarks || '').replace(/</g,'&lt;')}</div>
                              </div>
                            </section>
                          </div>

                          <section style="margin-top:16px; background: var(--color-card); border:1px solid var(--color-border); border-radius: 12px; padding: 16px;">
                            <h4 style="margin:0 0 8px 0; font-size:1rem; color:#1a202c;">Form and Answer</h4>
                            <div style="overflow-x:auto;">
                              <table style="width:100%; border-collapse:collapse;">
                                <tbody>
                                  ${answersRows || '<tr><td style="padding:10px; color:#718096;">No answers submitted.</td></tr>'}
                                </tbody>
                              </table>
                            </div>
                          </section>
                        `;
                    } catch (e) {
                        hideLoader();
                        bodyEl.innerHTML = `<div class="alert alert--error">Network error: ${e.message}</div>`;
                    }
                });
            });

            // Wire Update buttons (Status + Admin remarks)
            $all('.btn-update-request').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const reqId = this.getAttribute('data-request-id');
                    const row = this.closest('tr');
                    const currentRemarks = row?.querySelector("td[data-cell='Remarks']")?.textContent?.trim() || '';

                    titleEl.textContent = 'Update Request';
                    footerEl.innerHTML = '';

                    // Build form UI using shared form-group styles
                    bodyEl.innerHTML = `
                        <form id="updateRequestForm">
                          <div class="form-group" style="margin-bottom:16px;">
                            <label for="statusSelect" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Status</label>
                            <select id="statusSelect" name="status_id" style="width:100%; padding:12px 14px; border:2px solid #e2e8f0; border-radius:12px; font-size:0.95rem; transition:all 0.2s ease; box-sizing:border-box; background:#f7fafc; color:#2d3748;">
                              <option value="" disabled selected>Loading statuses…</option>
                            </select>
                          </div>
                          <div id="customStatusGroup" class="form-group" style="display:none; gap:12px; align-items:center;">
                            <div style="flex:1;">
                              <label for="customStatusName" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Custom Status Name</label>
                              <input id="customStatusName" type="text" placeholder="e.g., Awaiting Documents" style="width:100%; padding:12px 14px; border:2px solid #e2e8f0; border-radius:12px; font-size:0.95rem; transition:all 0.2s ease; box-sizing:border-box; background:#f7fafc; color:#2d3748;" />
                            </div>
                            <div style="width:160px;">
                              <label for="customColorHex" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Color</label>
                              <input id="customColorHex" type="color" value="#6C757D" style="width:100%; height:44px; padding:0; border:2px solid #e2e8f0; border-radius:12px; background:#f7fafc;" />
                            </div>
                          </div>
                          <div class="form-group" style="margin-bottom:16px;">
                            <label for="adminRemarks" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Admin Remarks</label>
                            <textarea id="adminRemarks" name="admin_remarks" rows="4" style="width:100%; padding:12px 14px; border:2px solid #e2e8f0; border-radius:12px; font-size:0.95rem; transition:all 0.2s ease; box-sizing:border-box; background:#f7fafc; color:#2d3748;">${currentRemarks.replace(/</g,'&lt;')}</textarea>
                          </div>
                          <div class="form-group" style="margin-bottom:16px; display:flex; align-items:center; gap:10px;">
                            <input id="canUpdateCheckbox" type="checkbox" style="width:18px; height:18px; cursor:pointer;" />
                            <label for="canUpdateCheckbox" style="font-weight:600; color:#2d3748; font-size:0.9rem; cursor:pointer;">Allow requestor to update this request</label>
                          </div>
                          <!-- Current status & remarks summary -->
                          <div id="updateStatusSummary" style="margin-top:8px; background: var(--color-card); border:1px solid var(--color-border); border-radius: 12px; padding: 12px;">
                            <div style="display:flex; align-items:center; gap:10px;">
                              <span style="font-weight:600; color:#2d3748;">Current Status:</span>
                              <span id="updateCurrentStatusPill" class="status-pill" style="background:#6C757D;">Loading…</span>
                            </div>
                            <div style="margin-top:8px;">
                              <span style="font-weight:600; color:#2d3748;">Current Remarks:</span>
                              <div id="updateCurrentRemarks" style="color:#4a5568; line-height:1.5;"></div>
                            </div>
                          </div>
                        </form>`;

                    const cancelBtn = document.createElement('button');
                    cancelBtn.id = 'updateModalCancelBtn';
                    cancelBtn.className = 'btn btn--secondary';
                    cancelBtn.type = 'button';
                    cancelBtn.textContent = 'Cancel';
                    cancelBtn.style.cssText = 'flex:1; padding:14px 24px; border:2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius:12px; font-weight:600; font-size:0.9rem; cursor:pointer; transition: all 0.2s ease; outline:none; display:flex; align-items:center; justify-content:center;';
                    cancelBtn.addEventListener('click', closeModal);
                    const saveBtn = document.createElement('button');
                    saveBtn.id = 'updateModalConfirmBtn';
                    saveBtn.className = 'btn btn--primary';
                    saveBtn.type = 'button';
                    saveBtn.textContent = 'Confirm';
                    saveBtn.style.cssText = 'flex:1; padding:14px 24px; border:none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color:#fff; border-radius:12px; font-weight:600; font-size:0.9rem; cursor:pointer; transition: all 0.2s ease; outline:none; box-shadow:0 4px 14px rgba(24,165,88,0.4); display:flex; align-items:center; justify-content:center;';
                    footerEl.appendChild(cancelBtn);
                    footerEl.appendChild(saveBtn);

                    overlay.style.display = 'flex';

                    // Prepare names for feedback messages
                    let currentRequestFullName = '';
                    let currentRequestServiceName = '';
                    const registeredNameCell = row ? row.querySelector("td[data-cell='Registered Name']") : null;
                    const fallbackName = registeredNameCell ? (registeredNameCell.textContent || '').trim() : '';

                    // Load details and statuses
                    try {
                        showLoader('Loading update data…');
                        const [detailsResp, statusesResp] = await Promise.all([
                            fetch(`get_request_details.php?request_id=${encodeURIComponent(reqId)}`),
                            fetch('get_service_request_statuses.php')
                        ]);
                        const details = await detailsResp.json();
                        const statuses = await statusesResp.json();

                        if (details && details.ok) {
                            currentRequestFullName = (details.full_name || fallbackName || 'Unknown');
                            currentRequestServiceName = (details.service_name || 'Service Request');
                        } else {
                            currentRequestFullName = (fallbackName || 'Unknown');
                            currentRequestServiceName = 'Service Request';
                        }

                        const statusSelect = document.getElementById('statusSelect');
                        const customGroup = document.getElementById('customStatusGroup');
                        const adminRemarksEl = document.getElementById('adminRemarks');
                        const canUpdateEl = document.getElementById('canUpdateCheckbox');
                        // Map of status_id -> description for remarks templating
                        const statusDescriptions = {};
                        if (details && details.ok) {
                            // Prefer details remarks over table text if available
                            if (details.admin_remarks != null) {
                                adminRemarksEl.value = details.admin_remarks;
                            }
                            if (canUpdateEl) {
                                canUpdateEl.checked = !!(details.can_update);
                            }
                            // Populate current summary
                            const pillEl = document.getElementById('updateCurrentStatusPill');
                            const currRemarksEl = document.getElementById('updateCurrentRemarks');
                            if (pillEl) {
                                pillEl.textContent = (details.status_name || 'Unknown').replace(/</g, '&lt;');
                                pillEl.style.background = details.color_hex || '#6C757D';
                            }
                            if (currRemarksEl) {
                                currRemarksEl.textContent = (details.admin_remarks || '').replace(/</g, '&lt;');
                            }
                        }

                        // Populate statuses
                        statusSelect.innerHTML = '';
                        if (statuses && statuses.ok && Array.isArray(statuses.statuses)) {
                            // Add options
                            statuses.statuses.forEach(s => {
                                const opt = document.createElement('option');
                                opt.value = String(s.status_id);
                                opt.textContent = s.status_name;
                                statusSelect.appendChild(opt);
                                // capture description for auto-fill
                                statusDescriptions[String(s.status_id)] = s.description || '';
                            });
                        }
                        // Add Other option
                        const optOther = document.createElement('option');
                        optOther.value = 'other';
                        optOther.textContent = 'Other (custom)';
                        statusSelect.appendChild(optOther);

                        // Preselect current status if available
                        const currentStatusId = details && details.status_id ? String(details.status_id) : '';
                        if (currentStatusId) {
                            statusSelect.value = currentStatusId;
                        } else {
                            statusSelect.selectedIndex = 0;
                        }

                        // Toggle custom group when Other selected and auto-fill remarks for known statuses
                        statusSelect.addEventListener('change', () => {
                            const val = statusSelect.value;
                            if (val === 'other') {
                                customGroup.style.display = 'flex';
                                // Do not override remarks for custom statuses
                            } else {
                                customGroup.style.display = 'none';
                                const tmpl = statusDescriptions[val] || '';
                                if (tmpl) {
                                    adminRemarksEl.value = tmpl;
                                }
                            }
                        });

                        hideLoader();
                    } catch (e) {
                        hideLoader();
                        alert('Failed to load update data: ' + e.message);
                    }

                    // Save handler
                    saveBtn.addEventListener('click', async function() {
                        const statusSelect = document.getElementById('statusSelect');
                        const adminRemarks = (document.getElementById('adminRemarks').value || '').trim();
                        const isOther = statusSelect.value === 'other';
                        const payload = new URLSearchParams();
                        payload.set('request_id', reqId);
                        payload.set('admin_remarks', adminRemarks);
                        const canUpdateChecked = document.getElementById('canUpdateCheckbox')?.checked || false;
                        payload.set('can_update', canUpdateChecked ? '1' : '0');

                        if (!isOther) {
                            const sid = parseInt(statusSelect.value, 10);
                            if (!sid || sid <= 0) {
                                alert('Please select a status.');
                                return;
                            }
                            payload.set('status_id', String(sid));
                        } else {
                            const name = (document.getElementById('customStatusName').value || '').trim();
                            const color = (document.getElementById('customColorHex').value || '').trim();
                            if (name === '') {
                                alert('Enter a custom status name.');
                                return;
                            }
                            payload.set('custom_status_name', name);
                            if (color) payload.set('custom_color_hex', color);
                        }

                        try {
                            showLoader('Saving update…');
                            const resp = await fetch('update_service_request.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: payload.toString()
                            });
                            const data = await resp.json();
                            hideLoader();
                            if (data && data.ok) {
                                // Update table row
                                if (row) {
                                    const remarksCell = row.querySelector("td[data-cell='Remarks']");
                                    if (remarksCell) remarksCell.textContent = data.admin_remarks || '';
                                    const statusCell = row.querySelector("td[data-cell='Status']");
                                    if (statusCell) {
                                        let pill = statusCell.querySelector('.status-pill');
                                        if (!pill) {
                                            pill = document.createElement('span');
                                            pill.className = 'status-pill';
                                            statusCell.innerHTML = '';
                                            statusCell.appendChild(pill);
                                        }
                                        pill.textContent = data.status_name || 'Unknown';
                                        pill.style.backgroundColor = data.color_hex || '#6C757D';
                                    }
                                }
                                // Update summary inside the modal
                                const pillEl = document.getElementById('updateCurrentStatusPill');
                                const currRemarksEl = document.getElementById('updateCurrentRemarks');
                                if (pillEl) {
                                    pillEl.textContent = (data.status_name || 'Unknown').replace(/</g, '&lt;');
                                    pillEl.style.background = data.color_hex || '#6C757D';
                                }
                                if (currRemarksEl) {
                                    currRemarksEl.textContent = (data.admin_remarks || '').replace(/</g, '&lt;');
                                }
                                // Show success message in separate feedback modal
                                const msgSuccess = `${currentRequestServiceName} for ${currentRequestFullName} successfully updated to status ${data.status_name || 'Unknown'} with remarks: ${data.admin_remarks ? data.admin_remarks : 'None'}.`;
                                showFeedbackModal('Success', msgSuccess, 'success');
                                // Keep modal open, no alerts
                            } else {
                                const errMsg = (data && data.error) ? data.error : 'Failed to update.';
                                const msgError = `Failed to update ${currentRequestServiceName} for ${currentRequestFullName}. ${errMsg}`;
                                showFeedbackModal('Error', msgError, 'error');
                            }
                        } catch (e) {
                            hideLoader();
                            const msgNetErr = `Failed to update ${currentRequestServiceName} for ${currentRequestFullName}. Network error: ${e.message}`;
                            showFeedbackModal('Network Error', msgNetErr, 'error');
                        }
                    });
                });
            });
        })();
    </script>
</body>

</html>