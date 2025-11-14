<?php
require_once 'middleware/auth.php';
require_once 'connection/db_connect.php';
date_default_timezone_set('Asia/Manila');

function fmt_date($s){ if(!$s) return 'N/A'; $t = strtotime($s); if(!$t) return 'N/A'; return date('F j, Y - h:i A', $t); }
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Success Office - Support Messages</title>
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

        .button.btn-view { border: 1.5px solid rgba(16, 185, 129, 0.35); background: var(--color-card); color: var(--color-primary); font-weight: 100; font-size: 0.85rem; padding: 8px 14px !important; transition: all 0.2s ease; }
        .button.btn-view:hover { background-color: var(--color-primary); color: var(--color-white); transform: translateY(-1px); }
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
    $messages = [];
    if ($res = $conn->query('SELECT id, name, email, subject, message, created_at, updated_at FROM contact_support ORDER BY id DESC')) {
        while ($row = $res->fetch_assoc()) { $messages[] = $row; }
    }
    ?>

    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="header">
                <div class="header__left">
                    <h1>Support Messages</h1>
                    <p class="header__subtitle">Contact support submissions</p>
                </div>
            </header>

            <section class="section active" style="margin: 0 20px;">
                <div class="table-container">
                    <div class="table-container__header">
                        <h2 class="table-container__title">Messages</h2>
                        <p class="table-container__subtitle">View support messages</p>
                    </div>

                    <div class="filtration_container">
                        <form method="GET" action="" style="display:flex; width:100%; justify-content:space-between; gap:12px; align-items:center;">
                            <div class="search_input_container" style="flex:1;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search-icon lucide-search">
                                    <path d="m21 21-4.34-4.34" />
                                    <circle cx="11" cy="11" r="8" />
                                </svg>
                                <input type="text" id="searchQuery" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search support messages..." aria-label="Search support messages">
                            </div>
                            <div class="search_button_container" style="flex:0 0 auto; display:flex; gap:8px; align-items:center;"></div>
                        </form>
                    </div>

                    <div class="table-wrapper" style="overflow-x:auto;">
                        <table class="table" id="supportTable">
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
                                    <th class="sortable" data-column="subject">Subject
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sort-icon">
                                            <path d="m3 16 4 4 4-4" />
                                            <path d="M7 20V4" />
                                            <path d="M20 8h-5" />
                                            <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                                            <path d="M15 14h5l-5 6h5" />
                                        </svg>
                                    </th>
                                    <th class="sortable" data-column="message">Message
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
                                <?php if (empty($messages)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center;">No support messages found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($messages as $m): ?>
                                        <?php
                                        $id = (int)($m['id'] ?? 0);
                                        $name = trim((string)($m['name'] ?? ''));
                                        $email = trim((string)($m['email'] ?? ''));
                                        $subject = trim((string)($m['subject'] ?? ''));
                                        $msg = trim((string)($m['message'] ?? ''));
                                        $created = fmt_date($m['created_at'] ?? null);
                                        $updated = fmt_date($m['updated_at'] ?? null);
                                        ?>
                                        <tr>
                                            <td><?php echo $id; ?></td>
                                            <td data-key="name"><?php echo htmlspecialchars($name !== '' ? $name : 'N/A'); ?></td>
                                            <td data-key="subject"><?php echo htmlspecialchars($subject !== '' ? $subject : 'N/A'); ?></td>
                                            <td data-key="message"><?php echo htmlspecialchars($msg !== '' ? $msg : 'N/A'); ?></td>
                                            <td>
                                                <div style="display:flex; gap:8px; align-items:center;">
                                                    <button type="button" class="button btn-view btn-open"
                                                        data-id="<?php echo $id; ?>"
                                                        data-name="<?php echo htmlspecialchars($name); ?>"
                                                        data-email="<?php echo htmlspecialchars($email); ?>"
                                                        data-subject="<?php echo htmlspecialchars($subject); ?>"
                                                        data-message="<?php echo htmlspecialchars($msg); ?>"
                                                        data-created="<?php echo htmlspecialchars($created); ?>"
                                                        data-updated="<?php echo htmlspecialchars($updated); ?>">View</button>
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

                    <div id="viewSupportModal" style="display:none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3500; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
                        <div style="background: var(--color-card); border-radius: 20px; text-align: left; max-width: 560px; width: 92%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); color: var(--color-text);">
                            <div style="padding: 32px 32px 16px 32px; text-align:center;">
                                <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 16px; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center;">
                                    <svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 20h9" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 3.5a2.121 2.121 0 113 3L7 19l-4 1 1-4 12.5-12.5z" />
                                    </svg>
                                </div>
                                <h2 style="margin: 0 0 8px; font-size: 1.4rem;">Support Message</h2>
                                <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Details</p>
                            </div>
                            <div style="padding: 0 32px 24px 32px;">
                                <div style="display:grid; grid-template-columns: 1fr; gap:12px;">
                                    <div>
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Name</label>
                                        <div id="v_name" style="padding:12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card);"></div>
                                    </div>
                                    <div>
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Email</label>
                                        <div id="v_email" style="padding:12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card);"></div>
                                    </div>
                                    <div>
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Subject</label>
                                        <div id="v_subject" style="padding:12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card);"></div>
                                    </div>
                                    <div>
                                        <label style="display:block; font-weight:600; margin-bottom:6px;">Message</label>
                                        <div id="v_message" style="white-space: pre-wrap; padding:12px; border: 2px solid var(--color-border); border-radius: 10px; background: var(--color-card);"></div>
                                    </div>
                                </div>
                                <div style="padding: 20px 0 0; display:flex; gap:12px;">
                                    <button type="button" id="closeViewModalBtn" style="flex:1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var table = document.getElementById('supportTable');
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
                if (!noRow) { noRow = document.createElement('tr'); noRow.id = 'noResultsRow'; var td = document.createElement('td'); var colCount = table.querySelectorAll('thead th').length; td.colSpan = colCount; td.style.textAlign = 'center'; td.style.color = 'var(--color-text-light)'; td.textContent = 'No matching messages.'; noRow.appendChild(td); tbody.appendChild(noRow); }
                noRow.style.display = visible === 0 ? '' : 'none';
            }
            if (searchInput) { filterRows(searchInput.value); searchInput.addEventListener('input', function() { filterRows(this.value); if (window.supportPagination) { window.supportPagination.updateFilteredRows(); window.supportPagination.currentPage = 1; window.supportPagination.updateDisplay(); } }); }

            class SupportPagination {
                constructor() { this.table = document.getElementById('supportTable'); this.tbody = this.table?.querySelector('tbody'); this.select = document.getElementById('rowsPerPageSelect'); this.info = document.getElementById('paginationInfo'); this.prev = document.getElementById('prevPage'); this.next = document.getElementById('nextPage'); this.currentPage = 1; this.rowsPerPage = this.getRowsPerPage(); this.allRows = []; this.filteredRows = []; this.init(); }
                init() { this.updateFilteredRows(); this.updateDisplay(); this.select?.addEventListener('change', () => { this.rowsPerPage = this.getRowsPerPage(); this.currentPage = 1; this.updateDisplay(); }); this.prev?.addEventListener('click', () => { if (this.currentPage > 1) { this.currentPage--; this.updateDisplay(); } }); this.next?.addEventListener('click', () => { const totalPages = Math.max(1, Math.ceil(this.filteredRows.length / this.rowsPerPage)); if (this.currentPage < totalPages) { this.currentPage++; this.updateDisplay(); } }); }
                getRowsPerPage() { const v = this.select?.value || '10'; if (v === 'all') return Infinity; const n = parseInt(v, 10); return isNaN(n) ? 10 : n; }
                updateFilteredRows() { this.allRows = Array.from(this.tbody.querySelectorAll('tr')).filter(r => r.id !== 'noResultsRow'); this.filteredRows = this.allRows.filter(r => r.style.display !== 'none'); }
                updateDisplay() { const totalRows = this.filteredRows.length; const perPage = this.rowsPerPage === Infinity ? totalRows : this.rowsPerPage; const totalPages = Math.max(1, Math.ceil(totalRows / (perPage || 1))); const startIndex = (this.currentPage - 1) * (perPage || 1); const endIndex = startIndex + (perPage || 1); this.allRows.forEach(r => r.style.display = 'none'); const toShow = this.filteredRows.slice(startIndex, endIndex); toShow.forEach(r => r.style.display = ''); if (this.info) { const startItem = totalRows === 0 ? 0 : startIndex + 1; const endItem = Math.min(endIndex, totalRows); this.info.textContent = `Showing ${startItem}-${endItem} • Page ${this.currentPage}`; } if (this.prev) { this.prev.disabled = this.currentPage <= 1; this.prev.classList.toggle('pagination__button--disabled', this.currentPage <= 1); } if (this.next) { this.next.disabled = this.currentPage >= totalPages; this.next.classList.toggle('pagination__button--disabled', this.currentPage >= totalPages); } }
            }
            window.supportPagination = new SupportPagination();

            var viewModal = document.getElementById('viewSupportModal');
            var closeViewBtn = document.getElementById('closeViewModalBtn');
            tbody?.addEventListener('click', function(e){ var vbtn = e.target.closest('.btn-open'); if (!vbtn) return; if (viewModal) { viewModal.style.display='flex'; var nameEl = document.getElementById('v_name'); var emailEl = document.getElementById('v_email'); var subjEl = document.getElementById('v_subject'); var msgEl = document.getElementById('v_message'); var createdEl = document.getElementById('v_created'); var updatedEl = document.getElementById('v_updated'); if (nameEl) nameEl.textContent = vbtn.dataset.name || 'N/A'; if (emailEl) emailEl.textContent = vbtn.dataset.email || 'N/A'; if (subjEl) subjEl.textContent = vbtn.dataset.subject || 'N/A'; if (msgEl) msgEl.textContent = vbtn.dataset.message || 'N/A'; if (createdEl) createdEl.textContent = vbtn.dataset.created || 'N/A'; if (updatedEl) updatedEl.textContent = vbtn.dataset.updated || 'N/A'; } });
            closeViewBtn?.addEventListener('click', function(){ if (viewModal) viewModal.style.display='none'; });
        });
    </script>
</body>

</html>