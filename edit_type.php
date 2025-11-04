<?php
// Authentication middleware - protect this page
require_once 'middleware/auth.php';
include 'connection/db_connect.php';

// --- VALIDATION ---
if (!isset($_GET['id']) || !isset($_GET['cycle_id'])) {
    die("Error: Missing required parameters.");
}
$type_id = (int)$_GET['id'];
$cycle_id = (int)$_GET['cycle_id']; // For back button

// --- ACTION HANDLER: Process Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_type') {
    $name = $conn->real_escape_string($_POST['name']);

    // --- Update Database (name only) ---
    $stmt = $conn->prepare("UPDATE applicant_types SET name = ? WHERE id = ?");
    $stmt->bind_param("si", $name, $type_id);

    if ($stmt->execute()) {
        if (!isset($_SESSION['message'])) { // Don't overwrite upload errors
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Applicant type updated successfully.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error updating applicant type: ' . $stmt->error];
    }
    $stmt->close();
    header("Location: applicant_types.php?cycle_id=$cycle_id"); // Redirect back to list
    exit;
}

// --- DATA FETCHING: Get current type data ---
$sql_get = "SELECT * FROM applicant_types WHERE id = ? AND admission_cycle_id = ?";
$stmt_get = $conn->prepare($sql_get);
$stmt_get->bind_param("ii", $type_id, $cycle_id);
$stmt_get->execute();
$result = $stmt_get->get_result();
if ($result->num_rows === 0) {
    die("Error: Applicant type not found.");
}
$type = $result->fetch_assoc();
$stmt_get->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Applicant Type</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin_style.css">

</head>

<body>
    <div class="container">
        <a href="applicant_types.php?cycle_id=<?php echo $cycle_id; ?>" class="btn btn-secondary" style="margin-bottom: 20px;">&laquo; Back to Applicant Types</a>
        <h1>Edit Applicant Type</h1>

        <div class="form-section">
            <form action="edit_type.php?id=<?php echo $type_id; ?>&cycle_id=<?php echo $cycle_id; ?>" method="post">
                <input type="hidden" name="action" value="update_type">

                <div class="form-group">
                    <label for="name">Applicant Type Name:</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($type['name']); ?>" required>
                </div>

                

                <button type="submit" class="btn btn-primary">Update Applicant Type</button>
            </form>
        </div>
    </div>
</body>

</html>