<?php
require_once 'middleware/auth.php';
include 'connection/db_connect.php';

// --- VALIDATION ---
if (!isset($_GET['cycle_id'])) {
    die("Error: No admission cycle specified.");
}
$cycle_id = (int)$_GET['cycle_id'];

// Get cycle name for the header
$cycle_name = $conn->query("SELECT cycle_name FROM admission_cycles WHERE id = $cycle_id")->fetch_assoc()['cycle_name'];
if (!$cycle_name) {
    die("Error: Cycle not found.");
}

// --- ACTION: Unarchive a type ---
if (isset($_GET['action']) && $_GET['action'] === 'unarchive' && isset($_GET['id'])) {
    $type_id = (int)$_GET['id'];

    $sql = "UPDATE applicant_types SET is_archived = 0 WHERE id = $type_id AND admission_cycle_id = $cycle_id";
    if ($conn->query($sql)) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Applicant type unarchived.'];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $conn->error];
    }
    header("Location: archived_types.php?cycle_id=$cycle_id"); // Refresh this page
    exit;
}

// --- DATA: Get ARCHIVED types for this cycle ---
$types = [];
$result = $conn->query("SELECT * FROM applicant_types WHERE admission_cycle_id = $cycle_id AND is_archived = 1 ORDER BY name"); // WHERE is_archived = 1
while ($row = $result->fetch_assoc()) {
    $types[] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Archived Applicant Types</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin_style.css">

</head>

<body>
    <div class="container">
        <a href="applicant_types.php?cycle_id=<?php echo $cycle_id; ?>" class="btn btn-secondary" style="margin-bottom: 20px;">&laquo; Back to Active Types</a>
        <h1>Archived Types for: <?php echo htmlspecialchars($cycle_name); ?></h1>

        <?php
        if (isset($_SESSION['message'])) {
            echo '<div class="alert alert-' . $_SESSION['message']['type'] . '">' . htmlspecialchars($_SESSION['message']['text']) . '</div>';
            unset($_SESSION['message']);
        }
        ?>

        <table>
            <thead>
                <tr>
                    <th>Type Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($types)): ?>
                    <tr>
                        <td colspan="2" style="text-align:center;">No archived types found for this cycle.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($types as $type): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($type['name']); ?></td>
                            <td class="actions">
                                <a href="archived_types.php?action=unarchive&id=<?php echo $type['id']; ?>&cycle_id=<?php echo $cycle_id; ?>"
                                    class="btn btn-success confirm-action"
                                    data-modal-title="Confirm Unarchive"
                                    data-modal-message="Are you sure you want to unarchive this applicant type?">
                                    Unarchive
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
        // Make sure your modal JS is included or pasted here
        document.addEventListener('DOMContentLoaded', setupConfirmationLinks); // Re-run setup
    </script>
</body>

</html>