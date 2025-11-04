<?php
require_once 'middleware/auth.php';
include 'connection/db_connect.php';

// --- VALIDATION: Check for 'step_id' and the NEW 'applicant_type_id' ---
if (!isset($_GET['step_id']) || !isset($_GET['applicant_type_id'])) { // <<< CORRECTED CHECK
    die("Error: Missing required parameters.");
}
$step_id = (int)$_GET['step_id'];
$applicant_type_id = (int)$_GET['applicant_type_id']; // <<< CORRECTED VARIABLE

// --- ACTION HANDLER: Process Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_step') {
        $title = $conn->real_escape_string($_POST['title']);
        // Archive status is NOT updated here

        // Corrected query using applicant_type_id column
        $sql = "UPDATE form_steps SET title = ?
                WHERE id = ? AND applicant_type_id = ?"; // <<< CORRECTED COLUMN NAME
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Database prepare error: ' . $conn->error];
        } else {
            // Bind parameters: 'sii' (string, integer, integer)
            $stmt->bind_param("sii", $title, $step_id, $applicant_type_id);

            if ($stmt->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Step updated successfully.'];
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Error updating step: ' . $stmt->error];
            }
            $stmt->close();
        }
        // Redirect back using the correct ID
        header("Location: manage_form.php?applicant_type_id=$applicant_type_id"); // <<< CORRECTED REDIRECT
        exit;
    }
}

// --- DATA FETCHING: Get current step data ---
// Also check applicant_type_id for security and make sure it's not archived
$sql_get = "SELECT * FROM form_steps WHERE id = ? AND applicant_type_id = ? AND is_archived = 0";
$stmt_get = $conn->prepare($sql_get);
if (!$stmt_get) {
    die("Prepare failed (get step): " . $conn->error);
}
$stmt_get->bind_param("ii", $step_id, $applicant_type_id);
$stmt_get->execute();
$result = $stmt_get->get_result();
if ($result->num_rows === 0) {
    die("Error: Step not found, does not belong to this applicant type, or is archived.");
}
$step = $result->fetch_assoc();
$stmt_get->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Step</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin_style.css">
</head>

<body>
    <div class="container">
        <a href="manage_form.php?applicant_type_id=<?php echo $applicant_type_id; ?>" class="btn btn-secondary" style="margin-bottom: 20px;">
            &laquo; Back to Form Manager
        </a>
        <h1>Edit Step</h1>

        <div class="form-section">
            <form action="edit_step.php?step_id=<?php echo $step_id; ?>&applicant_type_id=<?php echo $applicant_type_id; ?>" method="post">
                <input type="hidden" name="action" value="update_step">
                <div class="form-group">
                    <label for="title">Step Title:</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($step['title']); ?>" required>
                </div>
                <button type="submit" class="btn btn-primary">Update Step</button>
            </form>
        </div>
    </div>
</body>

</html>