<?php
// Authentication middleware - protect this page
require_once 'middleware/auth.php';
include 'connection/db_connect.php';

// --- VALIDATION ---
if (!isset($_GET['cycle_id'])) {
    die("Error: No admission cycle specified.");
}
$cycle_id = (int)$_GET['cycle_id'];
$cycle_name = $conn->query("SELECT cycle_name FROM admission_cycles WHERE id = $cycle_id")->fetch_assoc()['cycle_name'];
if (!$cycle_name) {
    die("Error: Cycle not found.");
}

// --- ACTION HANDLER (POST) - UPDATE TYPE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_type') {
    // Debug logging
    error_log("Update type request received");
    error_log("POST data: " . print_r($_POST, true));

    $type_id = (int)$_POST['type_id'];
    $name = $conn->real_escape_string($_POST['name']);

    // --- Update Database (name only) ---
    $stmt = $conn->prepare("UPDATE applicant_types SET name = ? WHERE id = ? AND admission_cycle_id = ?");
    $stmt->bind_param("sii", $name, $type_id, $cycle_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Applicant type updated successfully.'];
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Applicant type updated successfully.']);
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error updating applicant type: ' . $stmt->error];
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Error updating applicant type: ' . $stmt->error]);
    }
    $stmt->close();
    exit; // Important: exit after AJAX response
}

// --- ACTION HANDLER (POST) - CREATE TYPE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_type') {
    $name = $conn->real_escape_string($_POST['name']);
    $clone_from_id = (int)$_POST['clone_from_id'];

    // 1. Create the new applicant type (no banner column)
    $stmt_create = $conn->prepare("INSERT INTO applicant_types (admission_cycle_id, name, is_active, is_archived) VALUES (?, ?, 0, 0)");
    $stmt_create->bind_param("is", $cycle_id, $name);

    if ($stmt_create->execute()) {
        $new_type_id = $conn->insert_id;
        $stmt_create->close();

        // 2. If cloning, copy steps and fields (same logic as before)
        if ($clone_from_id > 0) {
            // ... (Your existing cloning code: SELECT steps, INSERT steps, SELECT fields, INSERT fields) ...
            // Make sure this cloning logic uses the correct column names (`applicant_type_id`)
            $sql_steps = "SELECT * FROM form_steps WHERE applicant_type_id = ? AND is_archived = 0 ORDER BY step_order";
            $stmt_steps = $conn->prepare($sql_steps);
            $stmt_steps->bind_param("i", $clone_from_id);
            $stmt_steps->execute();
            $result_steps = $stmt_steps->get_result();
            $step_map = []; // To map old step ID to new step ID

            $stmt_clone_step = $conn->prepare("INSERT INTO form_steps (applicant_type_id, title, step_order, is_archived) VALUES (?, ?, ?, 0)");
            $stmt_clone_fields = $conn->prepare("INSERT INTO form_fields (step_id, name, label, input_type, placeholder_text, is_required, field_order, is_archived) SELECT ?, name, label, input_type, placeholder_text, is_required, field_order, 0 FROM form_fields WHERE step_id = ? AND is_archived = 0");

            while ($step = $result_steps->fetch_assoc()) {
                $old_step_id = $step['id'];
                $stmt_clone_step->bind_param("isi", $new_type_id, $step['title'], $step['step_order']);
                $stmt_clone_step->execute();
                $new_step_id = $conn->insert_id;
                $step_map[$old_step_id] = $new_step_id; // Store mapping

                // Clone fields for this step
                $stmt_clone_fields->bind_param("ii", $new_step_id, $old_step_id);
                $stmt_clone_fields->execute();
            }
            $stmt_steps->close();
            $stmt_clone_step->close();
            $stmt_clone_fields->close();
            // Optionally clone options if select fields exist
            // ... logic to loop through step_map, find select fields, clone options ...
        }
        if (!isset($_SESSION['message'])) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'New applicant type created.'];
        }

        // Return JSON response for AJAX requests
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => $_SESSION['message']['text']
        ]);
        unset($_SESSION['message']);
        exit;
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error creating type: ' . $stmt_create->error];

        // Return JSON response for AJAX requests
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => $_SESSION['message']['text']
        ]);
        unset($_SESSION['message']);
        exit;
    }
}

// --- ACTION HANDLER (GET) ---
if (isset($_GET['action'])) {
    $type_id = (int)$_GET['id'];

    // --- OPEN a type ---
    if ($_GET['action'] === 'open') {
        $sql = "UPDATE applicant_types SET is_active = 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $type_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Application type opened.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error opening type: ' . $stmt->error];
        }
        $stmt->close();
        // Redirect AFTER processing
        header("Location: applicant_types.php?cycle_id=$cycle_id");
        exit;
    }

    // --- CLOSE a type ---
    if ($_GET['action'] === 'close') {
        $sql = "UPDATE applicant_types SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $type_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Application type closed.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error closing type: ' . $stmt->error];
        }
        $stmt->close();
        // Redirect AFTER processing
        header("Location: applicant_types.php?cycle_id=$cycle_id");
        exit;
    }

    // --- ARCHIVE a type ---
    if ($_GET['action'] === 'archive') {
        // Set archived flag and ensure it's inactive
        $sql = "UPDATE applicant_types SET is_archived = 1, is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $type_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Application type archived.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error archiving type: ' . $stmt->error];
        }
        $stmt->close();
        // Redirect AFTER processing
        header("Location: applicant_types.php?cycle_id=$cycle_id");
        exit;
    }

    // --- NEW: DUPLICATE action ---
    if ($_GET['action'] === 'duplicate') {
        // 1. Get original type details
        $stmt_orig = $conn->prepare("SELECT * FROM applicant_types WHERE id = ?");
        $stmt_orig->bind_param("i", $type_id);
        $stmt_orig->execute();
        $original_type = $stmt_orig->get_result()->fetch_assoc();
        $stmt_orig->close();

        if ($original_type) {
            $new_name = $original_type['name'] . " (Copy)";

            // 2. Create the new applicant type (inactive, not archived) without banner
            $stmt_create = $conn->prepare("INSERT INTO applicant_types (admission_cycle_id, name, is_active, is_archived) VALUES (?, ?, 0, 0)");
            $stmt_create->bind_param("is", $original_type['admission_cycle_id'], $new_name);

            if ($stmt_create->execute()) {
                $new_type_id = $conn->insert_id;
                $stmt_create->close();

                // 3. Clone steps and fields (using the same logic as in POST create)
                $sql_steps = "SELECT * FROM form_steps WHERE applicant_type_id = ? AND is_archived = 0 ORDER BY step_order";
                $stmt_steps = $conn->prepare($sql_steps);
                $stmt_steps->bind_param("i", $type_id); // Use original type ID ($type_id)
                $stmt_steps->execute();
                $result_steps = $stmt_steps->get_result();
                $step_map = [];

                $stmt_clone_step = $conn->prepare("INSERT INTO form_steps (applicant_type_id, title, step_order, is_archived) VALUES (?, ?, ?, 0)");
                $stmt_clone_fields = $conn->prepare("INSERT INTO form_fields (step_id, name, label, input_type, placeholder_text, is_required, field_order, is_archived) SELECT ?, name, label, input_type, placeholder_text, is_required, field_order, 0 FROM form_fields WHERE step_id = ? AND is_archived = 0");

                while ($step = $result_steps->fetch_assoc()) {
                    $old_step_id = $step['id'];
                    $stmt_clone_step->bind_param("isi", $new_type_id, $step['title'], $step['step_order']);
                    $stmt_clone_step->execute();
                    $new_step_id = $conn->insert_id;
                    $step_map[$old_step_id] = $new_step_id;

                    $stmt_clone_fields->bind_param("ii", $new_step_id, $old_step_id);
                    $stmt_clone_fields->execute();
                }
                $stmt_steps->close();
                $stmt_clone_step->close();
                $stmt_clone_fields->close();
                // Optionally clone options here too...

                $_SESSION['message'] = ['type' => 'success', 'text' => 'Applicant type duplicated successfully.'];
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Error duplicating type: ' . $stmt_create->error];
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Original applicant type not found.'];
        }
    }

    header("Location: applicant_types.php?cycle_id=$cycle_id");
    exit;
}

// --- DATA: Get all non-archived types for this cycle ---
$types = [];
$result = $conn->query("SELECT * FROM applicant_types WHERE admission_cycle_id = $cycle_id AND is_archived = 0 ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $types[] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Applicant Types</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin_style.css">
    
    <style>
        /* Modal Animations */
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

        /* Modal Overlay Animation */
        #editModal.show {
            display: block !important;
            animation: fadeIn 0.3s ease;
        }

        #editModal.show>div {
            animation: slideUp 0.3s ease;
        }

        /* Modal Button Hover Effects */
        #editModal button[onclick="closeEditModal()"]:hover {
            background: #f7fafc !important;
            border-color: #cbd5e0 !important;
            transform: translateY(-1px);
        }

        #editModal button[type="submit"]:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(24, 165, 88, 0.5) !important;
        }

        #editModal button:active {
            transform: translateY(0);
        }

        /* Close Button Hover Effect */
        #editModal button[onclick="closeEditModal()"]:first-of-type:hover {
            background: rgba(0, 0, 0, 0.1) !important;
            transform: scale(1.05);
        }

        /* Input Focus Effects */
        #editModal input:focus {
            border-color: var(--color-primary) !important;
            box-shadow: 0 0 0 3px rgba(24, 165, 88, 0.15) !important;
            outline: none;
        }

        /* File Input Styling */
        #editModal input[type="file"] {
            cursor: pointer;
        }

        #editModal input[type="file"]:hover {
            border-color: #cbd5e0 !important;
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

        button:hover{
            transform: translateY(-1px); /* lifts the button slightly */
            filter: drop-shadow(-2px 9px 5px #000000);
        }

        button:active{
            box-shadow: inset 2px 2px 5px rgba(0,0,0,0.5);
             transform: translateY(1px);
        }

        button:focus-visible{
            outline: 3px solid var(--ring-color);
            outline-offset: 2px;
        }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <div class="loading-text">Processing...</div>
        </div>
    </div>

    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <a href="application_management.php" class="btn btn-secondary">&laquo; Back to All Cycles</a>
            <a href="archived_types.php?cycle_id=<?php echo $cycle_id; ?>" class="btn btn-secondary">View Archived Types for this Cycle</a>
        </div>
        <h1>Manage Applicant Types for: <?php echo htmlspecialchars($cycle_name); ?></h1>

        <?php /* Session message display */ ?>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding: 24px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 16px; box-shadow: 0 8px 32px rgba(24, 165, 88, 0.3);">
            <div>
                <h2 style="color: white; margin: 0 0 8px 0; font-size: 1.8rem; font-weight: 700;">Applicant Types</h2>
                <p style="color: rgba(255, 255, 255, 0.9); margin: 0; font-size: 1rem;">Manage and create new applicant types for this admission cycle</p>
            </div>
            <button onclick="openCreateModal()" style="background: rgba(255, 255, 255, 0.2); border: 2px solid rgba(255, 255, 255, 0.3); color: white; padding: 14px 28px; border-radius: 12px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.3s ease; backdrop-filter: blur(10px); display: flex; align-items: center; gap: 8px;">
                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Add New Type
            </button>
        </div>

        <h2>Manage Existing Types</h2>
        <table>
            <thead>
                <tr>
                    <th>Type Name</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($types)): ?>
                    <tr>
                        <td colspan="3" style="text-align:center;">No active types found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($types as $type): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($type['name']); ?></td>
                            <td>
                                <?php if ($type['is_active']): ?>
                                    <span style="color:green; font-weight:bold;">Open (Live)</span>
                                <?php else: ?>
                                    <span style="color:gray;">Closed</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <button onclick="openEditModal(<?php echo $type['id']; ?>, '<?php echo htmlspecialchars($type['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($type['banner_image_path'] ?? '', ENT_QUOTES); ?>')" class="btn btn-warning btn-sm">Edit</button>
                                <a href="manage_form.php?applicant_type_id=<?php echo $type['id']; ?>" class="btn btn-primary btn-sm">Manage Form</a>

                                <a href="applicant_types.php?action=duplicate&id=<?php echo $type['id']; ?>&cycle_id=<?php echo $cycle_id; ?>"
                                    class="btn btn-secondary btn-sm confirm-action"
                                    data-modal-title="Confirm Duplicate"
                                    data-modal-message="Duplicate '<?php echo htmlspecialchars($type['name']); ?>'? This will copy its form structure but not its submissions. The duplicate will be inactive.">
                                    Duplicate
                                </a>

                                <?php /* Open/Close/Archive Buttons with confirm-action */ ?>
                                <?php if ($type['is_active']): ?>
                                    <a href="applicant_types.php?action=close&id=<?php echo $type['id']; ?>&cycle_id=<?php echo $cycle_id; ?>" class="btn btn-warning btn-sm confirm-action" data-modal-title="Confirm Close" data-modal-message="Close '<?php echo htmlspecialchars($type['name']); ?>'?">Close</a>
                                <?php else: ?>
                                    <a href="applicant_types.php?action=open&id=<?php echo $type['id']; ?>&cycle_id=<?php echo $cycle_id; ?>" class="btn btn-success btn-sm confirm-action" data-modal-title="Confirm Open" data-modal-message="Open '<?php echo htmlspecialchars($type['name']); ?>'?">Open</a>
                                <?php endif; ?>
                                <a href="applicant_types.php?action=archive&id=<?php echo $type['id']; ?>&cycle_id=<?php echo $cycle_id; ?>" class="btn btn-danger btn-sm confirm-action" data-modal-title="Confirm Archive" data-modal-message="Archive '<?php echo htmlspecialchars($type['name']); ?>'?">Archive</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 1002; backdrop-filter: blur(4px); overflow-y: auto; padding: 20px 0;">
        <div style="background: var(--color-card); border-radius: 20px; max-width: 500px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: 20px auto; overflow: hidden; border: 1px solid var(--color-border); position: relative; height: fit-content;">
            <!-- Close Button -->
            <button type="button" onclick="closeEditModal()" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px; transition: all 0.2s ease; z-index: 10;">&times;</button>

            <!-- Modal Header -->
            <div style="padding: 40px 32px 24px 32px; text-align: center;">
                <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 24px auto; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                </div>
                <h3 style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.6rem; font-weight: 700; letter-spacing: -0.025em;">Edit Applicant Type</h3>
                <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Update the applicant type information</p>
            </div>

            <!-- Modal Body -->
            <div style="padding: 0 32px 24px 32px;">
                <form id="editForm">
                    <input type="hidden" id="editTypeId" name="type_id">
                    <input type="hidden" id="editCycleId" name="cycle_id" value="<?php echo $cycle_id; ?>">
                    <input type="hidden" name="action" value="update_type">

                    <div style="margin-bottom: 24px;">
                        <label for="editName" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; font-size: 0.9rem;">Name</label>
                        <input type="text" id="editName" name="name" required style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                    </div>

                    <!-- Banner fields removed -->

                    <!-- Modal Footer inside form -->
                    <div style="padding: 20px 0 0 0; display: flex; gap: 12px; justify-content: center;">
                        <button type="button" onclick="closeEditModal()" style="flex: 1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4);">Update Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 1002; backdrop-filter: blur(4px); overflow-y: auto; padding: 20px 0;">
        <div style="background: var(--color-card); border-radius: 20px; text-align: center; max-width: 400px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: 20px auto; overflow: hidden; border: 1px solid var(--color-border); height: fit-content;">
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

    <!-- Success/Error Message Modal -->
    <div id="messageModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 1003; backdrop-filter: blur(4px); overflow-y: auto; padding: 20px 0;">
        <div style="background: var(--color-card); border-radius: 20px; text-align: center; max-width: 400px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: 20px auto; overflow: hidden; border: 1px solid var(--color-border); height: fit-content;">
            <!-- Modal Header -->
            <div style="padding: 32px 32px 16px 32px;">
                <div id="messageModalIcon" style="width: 56px; height: 56px; border-radius: 16px; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center;">
                    <!-- Icon will be set dynamically -->
                </div>
                <h3 id="messageModalTitle" style="margin: 0 0 12px 0; color: #1a202c; font-size: 1.5rem; font-weight: 700; letter-spacing: -0.025em;">Message</h3>
                <p id="messageModalText" style="color: #718096; margin: 0; line-height: 1.6; font-size: 0.95rem;">Message content</p>
            </div>

            <!-- Modal Footer -->
            <div style="padding: 16px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
                <button id="messageModalOkBtn" style="flex: 1; padding: 12px 24px; border: none; color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">OK</button>
            </div>
        </div>
    </div>

    <!-- Create New Type Modal - Unique Design -->
    <div id="createModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background: linear-gradient(135deg, rgba(24, 165, 88, 0.1) 0%, rgba(19, 101, 21, 0.1) 100%); z-index: 9999; backdrop-filter: blur(8px); overflow-y: auto; padding: 20px;">
        <div style="background: var(--color-card); border-radius: 24px; max-width: 600px; width: 95%; margin: 0 auto; max-height: calc(100vh - 40px); overflow: hidden; box-shadow: 0 25px 80px rgba(0,0,0,0.15), 0 10px 40px rgba(0,0,0,0.1); border: 1px solid var(--color-border); position: relative; transform: scale(0.9); opacity: 0; transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); display: flex; flex-direction: column;">

            <!-- Decorative Header Background -->
            <div style="height: 120px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); position: relative; overflow: hidden;">
                <!-- Animated Background Shapes -->
                <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%; animation: float 6s ease-in-out infinite;"></div>
                <div style="position: absolute; bottom: -30px; left: -30px; width: 100px; height: 100px; background: rgba(255,255,255,0.08); border-radius: 50%; animation: float 8s ease-in-out infinite reverse;"></div>

                <!-- Close Button -->
                <button type="button" onclick="closeCreateModal()" style="position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.2); border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; transition: all 0.3s ease; backdrop-filter: blur(10px);">&times;</button>

                <!-- Modal Icon and Title -->
                <div style="position: absolute; bottom: -40px; left: 50%; transform: translateX(-50%); text-align: center;">
                    <div style="width: 80px; height: 80px; background: var(--color-card); border-radius: 20px; margin: 0 auto 16px auto; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 32px rgba(0,0,0,0.1); border: 4px solid rgba(255,255,255,0.9);">
                        <svg style="width: 40px; height: 40px; color: #18a558;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Modal Content -->
            <div style="flex: 1; overflow-y: auto; padding: 60px 40px 40px 40px; text-align: center;">
                <h3 style="margin: 0 0 12px 0; color: #1a202c; font-size: 2rem; font-weight: 800; letter-spacing: -0.025em;">Create New Type</h3>
                <p style="color: #718096; margin: 0 0 32px 0; line-height: 1.6; font-size: 1.1rem;">Add a new applicant type to your admission cycle</p>

                <!-- Form -->
                <form id="createForm" style="text-align: left;">
                    <input type="hidden" name="action" value="create_type">
                    <input type="hidden" name="cycle_id" value="<?php echo $cycle_id; ?>">

                    <!-- Name Field with Icon -->
                    <div style="margin-bottom: 28px; position: relative;">
                        <label for="createName" style="display: block; margin-bottom: 10px; font-weight: 700; color: #2d3748; font-size: 1rem; display: flex; align-items: center; gap: 8px;">
                            <svg style="width: 18px; height: 18px; color: #18a558;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z"></path>
                            </svg>
                            Type Name
                        </label>
                        <input type="text" id="createName" name="name" required style="width: 100%; padding: 16px 20px; border: 3px solid #e2e8f0; border-radius: 16px; font-size: 1rem; transition: all 0.3s ease; box-sizing: border-box; background: #f8fafc; color: #2d3748; font-weight: 500;" placeholder="Enter applicant type name...">
                    </div>

                    <!-- Banner Upload with Preview -->
                    <div style="margin-bottom: 28px; display: none;">
                        <label for="createBannerImage" style="display: block; margin-bottom: 10px; font-weight: 700; color: #2d3748; font-size: 1rem; display: flex; align-items: center; gap: 8px;">
                            <svg style="width: 18px; height: 18px; color: #18a558;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Banner Image <span style="color: #e53e3e;">*</span>
                        </label>
                        <div style="margin-bottom: 12px; padding: 12px 16px; background: #f0f8ff; border: 2px solid #bee3f8; border-radius: 12px; font-size: 0.9rem; color: #2b6cb0;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                <svg style="width: 16px; height: 16px; color: #3182ce;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <strong>Upload Path:</strong>
                            </div>
                            <code style="background: rgba(255,255,255,0.8); padding: 4px 8px; border-radius: 6px; font-family: 'Courier New', monospace; font-size: 0.85rem; color: #2d3748;">
                                <?php echo realpath(__DIR__ . '/uploads/banners/'); ?>\
                            </code>
                        </div>
                        <div style="border: 3px dashed #cbd5e0; border-radius: 16px; padding: 24px; text-align: center; background: #f8fafc; transition: all 0.3s ease; position: relative; overflow: hidden;">
                            <input type="file" id="createBannerImage" name="banner_image" accept="image/jpeg,image/png,image/gif,image/webp" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 2;" disabled>
                            <div id="uploadArea" style="pointer-events: none;">
                                <svg style="width: 48px; height: 48px; color: #a0aec0; margin: 0 auto 16px auto; display: block;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <p style="margin: 0 0 8px 0; font-weight: 600; color: #4a5568;">Click to upload or drag and drop</p>
                                <p style="margin: 0; font-size: 0.9rem; color: #718096;">PNG, JPG, GIF, WEBP up to 5MB</p>
                                <p style="margin: 8px 0 0 0; font-size: 0.85rem; color: #a0aec0;">Recommended: 800x200px</p>
                            </div>
                        </div>
                    </div>

                    <!-- Clone From Dropdown -->
                    <div style="margin-bottom: 32px;">
                        <label for="createCloneFromId" style="display: block; margin-bottom: 10px; font-weight: 700; color: #2d3748; font-size: 1rem; display: flex; align-items: center; gap: 8px;">
                            <svg style="width: 18px; height: 18px; color: var(--color-primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                            Clone From Existing (Optional)
                        </label>
                        <select id="createCloneFromId" name="clone_from_id" style="width: 100%; padding: 16px 20px; border: 3px solid #e2e8f0; border-radius: 16px; font-size: 1rem; transition: all 0.3s ease; box-sizing: border-box; background: #f8fafc; color: #2d3748; font-weight: 500;">
                            <option value="0">Start with a blank form</option>
                            <?php foreach ($types as $type): ?>
                                <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 16px; justify-content: center; margin-top: 40px;">
                        <button type="button" onclick="closeCreateModal()" style="flex: 1; padding: 16px 32px; border: 3px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 16px; font-weight: 700; font-size: 1rem; cursor: pointer; outline: none;">Cancel</button>
                        <button type="submit" style="flex: 1; padding: 16px 32px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 16px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: all 0.3s ease; outline: none; box-shadow: 0 8px 32px rgba(24, 165, 88, 0.4); position: relative; overflow: hidden;">
                            <span style="position: relative; z-index: 2;">Create Type</span>
                            <div style="position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent); transition: left 0.5s ease;"></div>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        @keyframes float {

            0%,
            100% {
                transform: translateY(0px) rotate(0deg);
            }

            50% {
                transform: translateY(-20px) rotate(180deg);
            }
        }

        /* Modal Animation Classes */
        #createModal.show>div {
            transform: scale(1) !important;
            opacity: 1 !important;
        }

        /* Input Focus Effects */
        #createName:focus,
        #createCloneFromId:focus {
            border-color: var(--color-primary) !important;
            box-shadow: 0 0 0 4px rgba(24, 165, 88, 0.15) !important;
            outline: none !important;
            background: var(--color-card) !important;
            color: var(--color-text) !important;
        }

        /* File Upload Hover Effect */
        #createBannerImage:hover+#uploadArea {
            border-color: var(--color-primary) !important;
            background: var(--color-hover) !important;
        }

        /* Button Hover Effects */
        #createModal button[type="button"]:hover {
            background: var(--color-hover) !important;
            border-color: var(--color-border) !important;
            transform: translateY(-2px);
        }

        #createModal button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(24, 165, 88, 0.6) !important;
        }

        #createModal button[type="submit"]:hover>div {
            left: 100% !important;
        }

        /* Close Button Hover */
        #createModal button[onclick="closeCreateModal()"]:hover {
            background: rgba(255, 255, 255, 0.3) !important;
            transform: scale(1.1);
        }

        /* Add New Type Button Hover */
        button[onclick="openCreateModal()"]:hover {
            background: rgba(255, 255, 255, 0.3) !important;
            border-color: rgba(255, 255, 255, 0.5) !important;
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(24, 165, 88, 0.4);
        }

        /* Responsive Design for Mobile */
        @media (max-width: 768px) {
            #createModal>div {
                width: 95% !important;
                margin: 10px auto !important;
                max-height: calc(100vh - 20px) !important;
            }

            #createModal>div>div:last-child {
                padding: 40px 20px 20px 20px !important;
            }

            #createModal h3 {
                font-size: 1.5rem !important;
            }

            #createModal p {
                font-size: 1rem !important;
            }

            #createModal input,
            #createModal select {
                padding: 12px 16px !important;
                font-size: 0.9rem !important;
            }

            #createModal button {
                padding: 12px 20px !important;
                font-size: 0.9rem !important;
            }
        }

        @media (max-height: 600px) {
            #createModal>div {
                max-height: calc(100vh - 10px) !important;
            }

            #createModal>div>div:last-child {
                padding: 30px 40px 20px 40px !important;
            }
        }
    </style>

    <script>
        // Confirmation Modal Functions
        const modal = document.getElementById('confirmationModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');
        const modalConfirmBtn = document.getElementById('modalConfirmBtn');
        const modalCancelBtn = document.getElementById('modalCancelBtn');
        let currentActionUrl = '';

        function showConfirmationModal(title, message, actionUrl) {
            modalTitle.textContent = title;
            modalMessage.textContent = message;
            currentActionUrl = actionUrl;
            modal.style.display = 'flex';
        }

        modalConfirmBtn.addEventListener('click', () => {
            if (currentActionUrl) {
                // Show loader when confirming action
                showLoader();
                window.location.href = currentActionUrl;
            }
            modal.style.display = 'none';
        });

        modalCancelBtn.addEventListener('click', () => {
            modal.style.display = 'none';
        });

        // Close modal when clicking outside
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
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

        // Message Modal Functions
        const messageModal = document.getElementById('messageModal');
        const messageModalTitle = document.getElementById('messageModalTitle');
        const messageModalText = document.getElementById('messageModalText');
        const messageModalIcon = document.getElementById('messageModalIcon');
        const messageModalOkBtn = document.getElementById('messageModalOkBtn');

        function showMessageModal(type, title, message, callback = null) {
            messageModalTitle.textContent = title;
            messageModalText.textContent = message;

            // Set icon and button color based on type
            if (type === 'success') {
                messageModalIcon.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
                messageModalIcon.innerHTML = '<svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                messageModalOkBtn.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
                messageModalOkBtn.style.boxShadow = '0 4px 14px rgba(16, 185, 129, 0.4)';
            } else {
                messageModalIcon.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
                messageModalIcon.innerHTML = '<svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
                messageModalOkBtn.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
                messageModalOkBtn.style.boxShadow = '0 4px 14px rgba(239, 68, 68, 0.4)';
            }

            messageModal.style.display = 'flex';

            // Handle OK button click
            messageModalOkBtn.onclick = function() {
                messageModal.style.display = 'none';
                if (callback) callback();
            };
        }

        // Close message modal when clicking outside
        messageModal.addEventListener('click', function(event) {
            if (event.target === messageModal) {
                messageModal.style.display = 'none';
            }
        });

        // Edit Modal Functions
        function openEditModal(typeId, typeName, bannerPath) {
            document.getElementById('editTypeId').value = typeId;
            document.getElementById('editName').value = typeName;

            const modal = document.getElementById('editModal');
            modal.style.display = 'block';
            modal.classList.add('show');
        }

        function closeEditModal() {
            const modal = document.getElementById('editModal');
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
                document.getElementById('editForm').reset();
            }, 300);
        }

        // Ensure all event listeners are attached after DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Handle form submission
            const editForm = document.getElementById('editForm');

            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn.textContent;

                    // Disable submit button and show loading state
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Updating...';

                    fetch('applicant_types.php?cycle_id=<?php echo $cycle_id; ?>', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.text())
                        .then(text => {
                            try {
                                const data = JSON.parse(text);
                                if (data.status === 'success') {
                                    closeEditModal();
                                    showMessageModal('success', 'Success!', data.message || 'Applicant type updated successfully.', function() {
                                        location.reload(); // Reload the page to show updated data
                                    });
                                } else {
                                    showMessageModal('error', 'Error', data.message || 'Unknown error occurred');
                                }
                            } catch (e) {
                                console.error('Server response:', text);
                                showMessageModal('error', 'Error', 'Server returned invalid response. Please check console for details.');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showMessageModal('error', 'Error', 'Error updating applicant type. Please try again.');
                        })
                        .finally(() => {
                            // Re-enable submit button
                            submitBtn.disabled = false;
                            submitBtn.textContent = originalText;
                        });
                });
            }

            // Close modal when clicking outside
            const editModal = document.getElementById('editModal');
            if (editModal) {
                editModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeEditModal();
                    }
                });
            }

            // Close confirmation modal when clicking outside
            const modal = document.getElementById('confirmationModal');
            if (modal) {
                modal.addEventListener('click', function(event) {
                    if (event.target === modal) {
                        modal.style.display = 'none';
                    }
                });
            }

            // Setup confirmation links
            setupConfirmationLinks();
        });

        // Create Modal Functions
        function openCreateModal() {
            const modal = document.getElementById('createModal');
            modal.style.display = 'flex';
            modal.classList.add('show');

            // Focus on the first input field
            setTimeout(() => {
                const firstInput = modal.querySelector('input[name="name"]');
                if (firstInput) firstInput.focus();
            }, 100);
        }

        function closeCreateModal() {
            const modal = document.getElementById('createModal');
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
                document.getElementById('createForm').reset();
            }, 300);
        }

        // Handle create form submission
        document.addEventListener('DOMContentLoaded', function() {
            const createForm = document.getElementById('createForm');
            const createModal = document.getElementById('createModal');

            if (createForm) {
                createForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn.textContent;

                    // Disable submit button and show loading state
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Creating...';

                    fetch('applicant_types.php?cycle_id=<?php echo $cycle_id; ?>', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.text())
                        .then(text => {
                            try {
                                const data = JSON.parse(text);
                                if (data.status === 'success') {
                                    closeCreateModal();
                                    showMessageModal('success', 'Success!', data.message || 'Applicant type created successfully.', function() {
                                        location.reload(); // Reload the page to show new data
                                    });
                                } else {
                                    showMessageModal('error', 'Error', data.message || 'Unknown error occurred');
                                }
                            } catch (e) {
                                console.error('Server response:', text);
                                showMessageModal('error', 'Error', 'Server returned invalid response. Please check console for details.');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showMessageModal('error', 'Error', 'Error creating applicant type. Please try again.');
                        })
                        .finally(() => {
                            // Re-enable submit button
                            submitBtn.disabled = false;
                            submitBtn.textContent = originalText;
                        });
                });
            }

            // Close create modal when clicking outside
            if (createModal) {
                createModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeCreateModal();
                    }
                });
            }

            // Close create modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && createModal && createModal.style.display === 'flex') {
                    closeCreateModal();
                }
            });

            // Banner image preview functionality
            const bannerInput = document.getElementById('createBannerImage');
            const uploadArea = document.getElementById('uploadArea');

            if (bannerInput && uploadArea) {
                bannerInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];

                    if (file) {
                        // Validate file type
                        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                        if (!allowedTypes.includes(file.type)) {
                            showMessageModal('error', 'Invalid File Type', 'Please select a valid image file (JPG, PNG, GIF, or WEBP).');
                            this.value = '';
                            resetUploadArea();
                            return;
                        }

                        // Validate file size (5MB limit)
                        const maxSize = 5 * 1024 * 1024; // 5MB in bytes
                        if (file.size > maxSize) {
                            showMessageModal('error', 'File Too Large', 'Please select an image smaller than 5MB.');
                            this.value = '';
                            resetUploadArea();
                            return;
                        }

                        // Create image preview
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            uploadArea.innerHTML = `
                                <div style="position: relative;">
                                    <img src="${e.target.result}" alt="Banner Preview" style="max-width: 100%; max-height: 200px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                                    <div style="margin-top: 12px; padding: 8px 12px; background: #e6fffa; border: 1px solid #81e6d9; border-radius: 8px; font-size: 0.9rem; color: #234e52;">
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <svg style="width: 16px; height: 16px; color: #38b2ac;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <strong>${file.name}</strong>
                                        </div>
                                        <div style="margin-top: 4px; font-size: 0.85rem; color: #2d3748;">
                                            Size: ${(file.size / 1024 / 1024).toFixed(2)} MB
                                        </div>
                                    </div>
                                    <button type="button" onclick="clearBannerPreview()" style="position: absolute; top: -8px; right: -8px; width: 24px; height: 24px; border-radius: 50%; background: #e53e3e; color: white; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">×</button>
                                </div>
                            `;
                        };
                        reader.readAsDataURL(file);
                    } else {
                        resetUploadArea();
                    }
                });
            }

            function resetUploadArea() {
                if (uploadArea) {
                    uploadArea.innerHTML = `
                        <svg style="width: 48px; height: 48px; color: #a0aec0; margin: 0 auto 16px auto; display: block;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        <p style="margin: 0 0 8px 0; font-weight: 600; color: #4a5568;">Click to upload or drag and drop</p>
                        <p style="margin: 0; font-size: 0.9rem; color: #718096;">PNG, JPG, GIF, WEBP up to 5MB</p>
                        <p style="margin: 8px 0 0 0; font-size: 0.85rem; color: #a0aec0;">Recommended: 800x200px</p>
                    `;
                }
            }

            // Global function to clear banner preview
            window.clearBannerPreview = function() {
                const bannerInput = document.getElementById('createBannerImage');
                if (bannerInput) {
                    bannerInput.value = '';
                    resetUploadArea();
                }
            };
        });

        // Loader functions
        function showLoader() {
            const loader = document.getElementById('loadingOverlay');
            if (loader) {
                loader.style.display = 'flex';
            }
        }

        function hideLoader() {
            const loader = document.getElementById('loadingOverlay');
            if (loader) {
                loader.style.display = 'none';
            }
        }
    </script>
</body>

</html>