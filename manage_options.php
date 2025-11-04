<?php
require_once 'middleware/auth.php';
include 'connection/db_connect.php';

// --- VALIDATION: Check for 'field_id' and the NEW 'applicant_type_id' ---
if (!isset($_GET['field_id']) || !isset($_GET['applicant_type_id'])) {
    // Redirect or show error if parameters are missing
    $_SESSION['error_message'] = "Error: Missing required parameters to manage options."; // Use a session for error
    header("Location: admin.php"); // Redirect to main admin page if IDs are missing
    exit;
    // die("Error: Missing required parameters."); // Alternative: Stop execution
}
$field_id = (int)$_GET['field_id'];
$applicant_type_id = (int)$_GET['applicant_type_id']; // Used for back button & redirects

// --- ACTION HANDLER (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- ADD A NEW OPTION ---
    if ($action === 'add_option') {
        $label = trim($_POST['option_label'] ?? ''); // Trim whitespace
        $value = trim($_POST['option_value'] ?? ''); // Trim whitespace
        $order = (int)($_POST['option_order'] ?? 0);

        // Basic validation for label and value
        if (empty($label) || empty($value)) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Option Label and Value cannot be empty.'];
        } else {
            $sql = "INSERT INTO form_field_options (field_id, option_label, option_value, option_order)
                    VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Database prepare error (insert): ' . $conn->error];
            } else {
                $stmt->bind_param("issi", $field_id, $label, $value, $order);
                if ($stmt->execute()) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => 'Option added successfully.'];
                } else {
                    $_SESSION['message'] = ['type' => 'error', 'text' => 'Error adding option: ' . $stmt->error];
                }
                $stmt->close();
            }
        }
    }

    // --- UPDATE ALL EXISTING OPTIONS ---
    elseif ($action === 'update_options') {
        $option_ids = $_POST['option_id'] ?? [];
        $labels = $_POST['option_label'] ?? [];
        $values = $_POST['option_value'] ?? [];
        $orders = $_POST['option_order'] ?? [];
        $update_errors = 0;

        $stmt = $conn->prepare("UPDATE form_field_options SET option_label = ?, option_value = ?, option_order = ? WHERE id = ? AND field_id = ?");
        if (!$stmt) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Database prepare error (update): ' . $conn->error];
        } else {
            for ($i = 0; $i < count($option_ids); $i++) {
                $id = (int)$option_ids[$i];
                $label = trim($labels[$i] ?? '');
                $value = trim($values[$i] ?? '');
                $order = (int)($orders[$i] ?? 0);

                // Basic validation before update
                if (empty($label) || empty($value)) {
                    error_log("Skipping update for option ID $id due to empty label or value.");
                    $update_errors++;
                    continue; // Skip this update
                }

                $stmt->bind_param("ssiii", $label, $value, $order, $id, $field_id);
                if (!$stmt->execute()) {
                    error_log("Failed to update option ID $id: " . $stmt->error);
                    $update_errors++;
                }
            }
            $stmt->close();

            if ($update_errors > 0) {
                $_SESSION['message'] = ['type' => 'warning', 'text' => 'Options updated, but ' . $update_errors . ' row(s) encountered errors (e.g., empty label/value).'];
            } else {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'All options updated successfully.'];
            }
        }
    }

    // Redirect back after processing POST action
    header("Location: manage_options.php?field_id=$field_id&applicant_type_id=$applicant_type_id");
    exit;
}

// --- ACTION HANDLER (GET) ---
elseif (isset($_GET['action']) && $_GET['action'] === 'delete_option' && isset($_GET['id'])) {
    $option_id = (int)$_GET['id'];
    $sql = "DELETE FROM form_field_options WHERE id = ? AND field_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Database prepare error (delete): ' . $conn->error];
    } else {
        $stmt->bind_param("ii", $option_id, $field_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Option deleted successfully.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error deleting option: ' . $stmt->error];
        }
        $stmt->close();
    }
    // Redirect back after processing GET action
    header("Location: manage_options.php?field_id=$field_id&applicant_type_id=$applicant_type_id");
    exit;
}


// --- DATA FETCHING (for displaying the page) ---
$field_label = null;
$options = [];

// Get the field's name/label
$field_stmt = $conn->prepare("SELECT label FROM form_fields WHERE id = ? AND is_archived = 0");
if (!$field_stmt) {
    die("Prepare failed (get label): " . $conn->error);
}
$field_stmt->bind_param("i", $field_id);
if ($field_stmt->execute()) {
    $result_label = $field_stmt->get_result();
    if ($result_label->num_rows === 0) {
        die("Error: Field not found or is archived."); // Field must exist to manage options
    }
    $field_label = $result_label->fetch_assoc()['label'];
} else {
    die("Execute failed (get label): " . $field_stmt->error);
}
$field_stmt->close();

// Get all options for this field
$options_stmt = $conn->prepare("SELECT * FROM form_field_options WHERE field_id = ? ORDER BY option_order, id");
if (!$options_stmt) {
    die("Prepare failed (get options): " . $conn->error);
}
$options_stmt->bind_param("i", $field_id);
if ($options_stmt->execute()) {
    $result_options = $options_stmt->get_result();
    while ($row = $result_options->fetch_assoc()) {
        $options[] = $row;
    }
} else {
    die("Execute failed (get options): " . $options_stmt->error);
}
$options_stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Options for <?php echo htmlspecialchars($field_label ?? 'Field'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin_style.css">
    
    <script>
        function confirmDelete() {
            return confirm('Are you sure you want to permanently delete this option?');
        }
    </script>
</head>

<body>
    <div class="container">
        <a href="manage_form.php?applicant_type_id=<?php echo $applicant_type_id; ?>" class="btn btn-secondary" style="margin-bottom: 20px;">
            &laquo; Back to Form Manager
        </a>
        <h1>Manage Options for: <?php echo htmlspecialchars($field_label ?? 'Field'); ?></h1>

        <?php
        // Display session messages
        if (isset($_SESSION['message'])) {
            echo '<div class="alert alert-' . htmlspecialchars($_SESSION['message']['type']) . '">' . htmlspecialchars($_SESSION['message']['text']) . '</div>';
            unset($_SESSION['message']);
        }
        ?>

        <div class="form-section">
            <h2>Existing Options</h2>
            <?php if (empty($options)): ?>
                <p>No options found. Add one below.</p>
            <?php else: ?>
                <form action="manage_options.php?field_id=<?php echo $field_id; ?>&applicant_type_id=<?php echo $applicant_type_id; ?>" method="post">
                    <input type="hidden" name="action" value="update_options">
                    <table>
                        <thead>
                            <tr>
                                <th>Label (Text user sees)</th>
                                <th>Value (Server value)</th>
                                <th>Order</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($options as $option): ?>
                                <tr>
                                    <input type="hidden" name="option_id[]" value="<?php echo $option['id']; ?>">
                                    <td><input type="text" name="option_label[]" class="form-group" value="<?php echo htmlspecialchars($option['option_label']); ?>" required></td>
                                    <td><input type="text" name="option_value[]" class="form-group" value="<?php echo htmlspecialchars($option['option_value']); ?>" required></td>
                                    <td><input type="number" name="option_order[]" class="form-group" value="<?php echo $option['option_order']; ?>" style="width: 80px;" required></td>
                                    <td>
                                        <a href="manage_options.php?action=delete_option&id=<?php echo $option['id']; ?>&field_id=<?php echo $field_id; ?>&applicant_type_id=<?php echo $applicant_type_id; ?>"
                                            class="btn btn-danger" onclick="return confirmDelete();">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="submit" class="btn btn-primary" style="margin-top: 15px;">Update All Options</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="form-section">
            <h2>Add New Option</h2>
            <form action="manage_options.php?field_id=<?php echo $field_id; ?>&applicant_type_id=<?php echo $applicant_type_id; ?>" method="post" style="display: flex; gap: 15px; align-items: flex-end;">
                <input type="hidden" name="action" value="add_option">
                <div class="form-group" style="flex: 2;">
                    <label for="add_option_label">Label:</label>
                    <input type="text" id="add_option_label" name="option_label" placeholder="e.g., Male" required>
                </div>
                <div class="form-group" style="flex: 2;">
                    <label for="add_option_value">Value:</label>
                    <input type="text" id="add_option_value" name="option_value" placeholder="e.g., m" required>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="add_option_order">Order:</label>
                    <input type="number" id="add_option_order" name="option_order" value="0" required>
                </div>
                <button type="submit" class="btn btn-success">Add Option</button>
            </form>
        </div>
    </div>
</body>

</html>