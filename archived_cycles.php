<?php
// Authentication middleware - protect this page
require_once 'middleware/auth.php';
include 'connection/db_connect.php';

// --- ACTION: Unarchive a cycle ---
if (isset($_GET['action']) && $_GET['action'] === 'unarchive' && isset($_GET['id'])) {
    $cycle_id = (int)$_GET['id'];

    // Unarchive the cycle itself
    $sql = "UPDATE admission_cycles SET is_archived = 0 WHERE id = $cycle_id";
    if ($conn->query($sql)) {
        // IMPORTANT: Unarchiving a cycle does NOT automatically unarchive its types.
        // The admin must go into the cycle and unarchive types manually if needed.
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Cycle unarchived. You may need to unarchive applicant types within it separately.'];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $conn->error];
    }
    header("Location: archived_cycles.php"); // Refresh this page
    exit;
}

// --- DATA: Get all ARCHIVED cycles ---
$cycles = [];
$result = $conn->query("SELECT * FROM admission_cycles WHERE is_archived = 1 ORDER BY id DESC"); // WHERE is_archived = 1
while ($row = $result->fetch_assoc()) {
    $cycles[] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Admission Cycles - SSO Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        /* Custom styles for archived cycles page */
        .table {
            background: var(--color-card);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--color-border);
        }

        .table th {
            background: linear-gradient(135deg, #e8f5e9 0%, #f2f2f2 100%);
            color: var(--color-text);
            font-weight: 600;
            padding: 16px;
            text-align: left;
            border-bottom: 2px solid var(--color-border);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table td {
            padding: 16px;
            border-bottom: 1px solid var(--color-border);
            color: var(--color-text);
            font-size: 0.95rem;
        }

        .table tbody tr:hover {
            background-color: var(--color-hover);
            transition: background-color var(--transition-fast);
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all var(--transition-fast);
            border: none;
            cursor: pointer;
        }

        .btn--success {
            background: linear-gradient(135deg, #18a558 0%, #1b8f3e 100%);
            color: white;
        }

        .btn--success:hover {
            background: linear-gradient(135deg, #1b8f3e 0%, #136515 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(24, 165, 88, 0.3);
        }

        .btn--secondary {
            background: linear-gradient(145deg, #136515, #136515);
            color: white;
        }

        .btn--secondary:hover {
            background: linear-gradient(135deg, #4b5563 0%, #374151 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--color-text-light);
        }

        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--color-text);
        }

        .empty-state p {
            font-size: 0.95rem;
        }

        /* Alert styles */
        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert--success {
            background: linear-gradient(135deg, #e8f5e9 0%, #d9efdc 100%);
            color: #0f4f12;
            border: 1px solid #18a558;
        }

        .alert--error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        .card {
            background: var(--color-card);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--color-border);
        }
    </style>
</head>

<body>
    <!-- Mobile Navbar -->
    <?php include "includes/mobile_navbar.php"; ?>

    <!-- Layout Container -->
    <div class="layout">
        <!-- Sidebar -->
        <?php include "includes/sidebar.php"; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Back Button -->
            <div style="margin-bottom: 24px;">
                <button class="btn btn--secondary" onclick="window.location.href='application_management.php'" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 20px; font-size: 0.9rem;">
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Admission List
                </button>
            </div>

            <!-- Main Content Section -->
            <section class="section active">
                <div class="card">
                    <!-- Card Header -->
                    <div style="margin-bottom: 24px;">
                        <h1 style="font-size: 1.5rem; font-weight: 600; color: var(--text-primary); margin: 0 0 8px 0;">Archived Admission Cycles</h1>
                        <p style="color: var(--text-secondary); margin: 0; font-size: 0.9rem;">View and manage archived admission cycles</p>
                    </div>

                    <?php
                    if (isset($_SESSION['message'])) {
                        $alertType = $_SESSION['message']['type'] === 'success' ? 'alert--success' : 'alert--error';
                        echo '<div class="alert ' . $alertType . '">';
                        if ($_SESSION['message']['type'] === 'success') {
                            echo '<svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                  </svg>';
                        } else {
                            echo '<svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                  </svg>';
                        }
                        echo htmlspecialchars($_SESSION['message']['text']) . '</div>';
                        unset($_SESSION['message']);
                    }
                    ?>

                    <?php if (empty($cycles)): ?>
                        <div class="empty-state">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            <h3>No Archived Cycles</h3>
                            <p>There are currently no archived admission cycles to display.</p>
                        </div>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cycle Name</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cycles as $cycle): ?>
                                    <tr>
                                        <td><strong>#<?php echo $cycle['id']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($cycle['cycle_name']); ?></td>
                                        <td class="actions">
                                            <a href="archived_cycles.php?action=unarchive&id=<?php echo $cycle['id']; ?>"
                                                class="btn btn--success confirm-action"
                                                data-modal-title="Confirm Unarchive"
                                                data-modal-message="Are you sure you want to unarchive this cycle? Its applicant types will remain archived unless unarchived separately.">
                                                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                                </svg>
                                                Unarchive
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 1002; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div style="background: var(--color-card); border-radius: 20px; text-align: center; max-width: 400px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); color: var(--color-text);">
            <div style="padding: 32px 32px 16px 32px;">
            <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 16px; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center;">
            
                    <svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 id="modalTitle" style="color: #2d3748; font-size: 1.25rem; font-weight: 600; margin-bottom: 12px;">Confirm Action</h3>
                <p id="modalMessage" style="color: #4a5568; font-size: 0.95rem; line-height: 1.5; margin-bottom: 24px;">Are you sure you want to proceed?</p>
            </div>
            <div style="padding: 0 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
                <button id="modalCancel" style="background: var(--color-card); color: var(--color-text); border: 2px solid var(--color-border); border-radius: 12px; padding: 12px 24px; font-weight: 600; cursor: pointer; transition: all 0.2s ease;">Cancel</button>
                <button id="modalConfirm" style="background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border: none; border-radius: 12px; padding: 12px 24px; font-weight: 600; cursor: pointer; transition: all 0.2s ease;">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        // Basic sidebar and mobile menu functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle functionality
            const sidebar = document.getElementById('sidebar');
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            
            if (mobileMenuToggle && sidebar) {
                mobileMenuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('mobile-open');
                });

                // Close sidebar when clicking outside on mobile
                document.addEventListener('click', function(e) {
                    if (window.innerWidth <= 640 && 
                        !sidebar.contains(e.target) && 
                        !mobileMenuToggle.contains(e.target)) {
                        sidebar.classList.remove('mobile-open');
                    }
                });
            }

            // Setup confirmation links
            setupConfirmationLinks();
        });

        // Confirmation modal functionality
        function setupConfirmationLinks() {
            const modal = document.getElementById('confirmationModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            const modalCancel = document.getElementById('modalCancel');
            const modalConfirm = document.getElementById('modalConfirm');

            document.querySelectorAll('.confirm-action').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const title = this.getAttribute('data-modal-title') || 'Confirm Action';
                    const message = this.getAttribute('data-modal-message') || 'Are you sure you want to proceed?';
                    const href = this.href;

                    modalTitle.textContent = title;
                    modalMessage.textContent = message;
                    modal.style.display = 'flex';

                    modalConfirm.onclick = () => {
                        window.location.href = href;
                    };

                    modalCancel.onclick = () => {
                        modal.style.display = 'none';
                    };

                    modal.onclick = (e) => {
                        if (e.target === modal) {
                            modal.style.display = 'none';
                        }
                    };
                });
            });
        }
    </script>
</body>

</html>