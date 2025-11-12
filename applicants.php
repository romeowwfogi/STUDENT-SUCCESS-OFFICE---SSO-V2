<?php
// Authentication middleware - protect this page
require_once 'middleware/auth.php';
include 'connection/db_connect.php';
require_once 'function/decrypt.php';

// --- Require cycle_id ---
if (!isset($_GET['cycle_id'])) {
    // Redirect back to admin if no cycle is specified
    header("Location: admin.php");
    exit;
}

$selected_cycle_id = (int)$_GET['cycle_id'];
$cycle_name = null;
$applicants = [];

// Get the cycle name for the header
$cycle_stmt = $conn->prepare("SELECT cycle_name FROM admission_cycles WHERE id = ? AND is_archived = 0");
$cycle_stmt->bind_param("i", $selected_cycle_id);
$cycle_stmt->execute();
$cycle_result = $cycle_stmt->get_result();
if ($cycle_row = $cycle_result->fetch_assoc()) {
    $cycle_name = $cycle_row['cycle_name'];
} else {
    die("Error: Selected cycle not found or is archived.");
}
$cycle_stmt->close();

// Fetch applicants FOR THIS CYCLE ONLY
$sql = "SELECT
            s.id AS submission_id,
            s.submitted_at,
            s.status,
            at.name AS applicant_type,
            u.email AS user_email,
            d_fname.field_value AS first_name,
            d_lname.field_value AS last_name
        FROM
            submissions s
        LEFT JOIN
            applicant_types at ON s.applicant_type_id = at.id
        LEFT JOIN
            users u ON s.user_id = u.id
        LEFT JOIN
            submission_data d_fname ON (s.id = d_fname.submission_id AND d_fname.field_name = 'first_name')
        LEFT JOIN
            submission_data d_lname ON (s.id = d_lname.submission_id AND d_lname.field_name = 'last_name')
        WHERE
            at.admission_cycle_id = ? -- Filter by the selected cycle ID
        ORDER BY
            s.submitted_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $selected_cycle_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $applicants[] = $row;
    }
} else {
    die("Error fetching applicants: " . $conn->error);
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Applicants for <?php echo htmlspecialchars($cycle_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin_style.css">
    
</head>

<body>
    <div class="container">
        <a href="admin.php" class="btn btn-secondary" style="margin-bottom: 20px;">&laquo; Back to All Cycles</a>
        <h1>Applicants for: <?php echo htmlspecialchars($cycle_name); ?></h1>

        <?php
        if (isset($_SESSION['message'])) {
            echo '<div class="alert alert-' . htmlspecialchars($_SESSION['message']['type']) . '">
                    ' . htmlspecialchars($_SESSION['message']['text']) . '
                  </div>';
            unset($_SESSION['message']);
        }
        ?>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Applicant Name</th>
                    <th>Email</th>
                    <th>Application Type</th>
                    <th>Submitted On</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($applicants)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;">No submissions found for this cycle.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($applicants as $app): ?>
                        <tr>
                            <td><?php echo $app['submission_id']; ?></td>
                            <td><?php echo htmlspecialchars(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? '')); ?></td>
                            <td><?php
                                $email = $app['user_email'] ?? null;
                                $dec = $email ? decryptData($email) : null;
                                $displayEmail = ($dec !== false && !empty($dec)) ? $dec : ($email ?? 'N/A');
                                echo htmlspecialchars($displayEmail);
                            ?></td>
                            <td><?php echo htmlspecialchars($app['applicant_type'] ?? 'N/A'); ?></td>
                            <td><?php echo date('M j, Y, g:i A', strtotime($app['submitted_at'])); ?></td>
                            <td>
                                <span class="status-<?php echo strtolower(htmlspecialchars($app['status'])); ?>">
                                    <?php echo htmlspecialchars($app['status']); ?>
                                </span>
                            </td>
                            <td class="actions">
                                <a href="view_submission.php?id=<?php echo $app['submission_id']; ?>" class="btn btn-primary">View Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>

</html>