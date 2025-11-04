<?php
require_once 'middleware/auth.php';
include 'connection/db_connect.php';

// --- VALIDATION: Check for 'field_id' and the NEW 'applicant_type_id' ---
if (!isset($_GET['field_id']) || !isset($_GET['applicant_type_id'])) { // <<< CORRECTED CHECK
    die("Error: Missing required parameters.");
}
$field_id = (int)$_GET['field_id'];
$applicant_type_id = (int)$_GET['applicant_type_id']; // <<< CORRECTED VARIABLE

// --- ACTION HANDLER: Process Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_field') {
        $name = $conn->real_escape_string($_POST['name']);
        // Server-side validation: Name/ID must start with a letter and contain only letters, numbers, and underscores
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $_POST['name'])) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid Name/ID. Use letters, numbers, underscores; start with a letter.'];
            header("Location: manage_form.php?applicant_type_id=$applicant_type_id");
            exit;
        }
        $label = $conn->real_escape_string($_POST['label']);
        $input_type = $conn->real_escape_string($_POST['input_type']);
        $placeholder = $conn->real_escape_string($_POST['placeholder_text']);
        $is_required = isset($_POST['is_required']) ? 1 : 0;
        // Archive status is NOT updated here, only via the archive button

        $sql = "UPDATE form_fields SET
                    name = '$name',
                    label = '$label',
                    input_type = '$input_type',
                    placeholder_text = '$placeholder',
                    is_required = $is_required
                WHERE id = $field_id";

        if ($conn->query($sql)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Field updated successfully.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $conn->error];
        }
        // Redirect back using the correct ID
        header("Location: manage_form.php?applicant_type_id=$applicant_type_id"); // <<< CORRECTED REDIRECT
        exit;
    }
}

// --- DATA FETCHING: Get current field data ---
$sql_get = "SELECT * FROM form_fields WHERE id = $field_id AND is_archived = 0"; // Also check if archived
$result = $conn->query($sql_get);
if ($result->num_rows === 0) {
    die("Error: Field not found or is archived.");
}
$field = $result->fetch_assoc();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Field</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin_style.css">
</head>

<body>
    <div class="container">
        <a href="manage_form.php?applicant_type_id=<?php echo $applicant_type_id; ?>" class="btn btn-secondary" style="margin-bottom: 20px;">
            &laquo; Back to Form Manager
        </a>
        <h1>Edit Field: <?php echo htmlspecialchars($field['label']); ?></h1>

        <div class="form-section">
            <form action="edit_field.php?field_id=<?php echo $field_id; ?>&applicant_type_id=<?php echo $applicant_type_id; ?>" method="post">
                <input type="hidden" name="action" value="update_field">

                <div class="form-group">
                    <label for="label">Label:</label>
                    <input type="text" id="label" name="label" value="<?php echo htmlspecialchars($field['label']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="name">Name/ID:</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($field['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="input_type">Input Type:</label>
                    <select id="input_type" name="input_type">
                        <?php
                        $types = ['text', 'email', 'tel', 'date', 'file', 'select'];
                        foreach ($types as $type):
                        ?>
                            <option value="<?php echo $type; ?>" <?php echo ($field['input_type'] === $type) ? 'selected' : ''; ?>>
                                <?php echo ucfirst($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="placeholder_text">Placeholder Text:</label>
                    <input type="text" id="placeholder_text" name="placeholder_text" value="<?php echo htmlspecialchars($field['placeholder_text']); ?>">
                </div>
                <div class="form-group">
                    <input type="checkbox" id="is_required" name="is_required" value="1" <?php echo $field['is_required'] ? 'checked' : ''; ?>>
                    <label for="is_required" style="display:inline; font-weight:normal;">This field is required</label>
                </div>

                <button type="submit" class="btn btn-primary">Update Field</button>
            </form>
        </div>
    </div>
</body>

</html>