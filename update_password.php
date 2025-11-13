<?php
// Update password endpoint
// Requires: authenticated user, correct current password; hashes and stores new password

require_once __DIR__ . '/middleware/auth.php';
require_once __DIR__ . '/connection/db_connect.php';

header('Content-Type: application/json');

// Ensure POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Ensure authenticated
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if ($new_password === '') {
    echo json_encode(['success' => false, 'error' => 'New password is required']);
    exit;
}
if (strlen($new_password) < 8) {
    echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters']);
    exit;
}
// Require uppercase, lowercase, number, and symbol
$hasUpper = preg_match('/[A-Z]/', $new_password);
$hasLower = preg_match('/[a-z]/', $new_password);
$hasDigit = preg_match('/\d/', $new_password);
$hasSymbol = preg_match('/[^A-Za-z0-9]/', $new_password);
if (!($hasUpper && $hasLower && $hasDigit && $hasSymbol)) {
    echo json_encode(['success' => false, 'error' => 'Include uppercase, lowercase, number, and symbol']);
    exit;
}
if ($password === '') {
    echo json_encode(['success' => false, 'error' => 'Current password is required']);
    exit;
}

$user_id = intval($_SESSION['user_id']);

// Fetch current user password hash
$stmt = $conn->prepare('SELECT password FROM sso_user WHERE id = ? LIMIT 1');
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Server error (prepare)']);
    exit;
}
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows !== 1) {
    $stmt->close();
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}
$row = $result->fetch_assoc();
$stmt->close();

$current_hash = $row['password'];

// Verify current password
if (!password_verify($password, $current_hash)) {
    echo json_encode(['success' => false, 'error' => 'Incorrect password']);
    exit;
}

// Disallow setting the same password
if (password_verify($new_password, $current_hash)) {
    echo json_encode(['success' => false, 'error' => 'New password must be different from current password']);
    exit;
}

// Hash and update
$new_hash = password_hash($new_password, PASSWORD_DEFAULT);
$stmt2 = $conn->prepare('UPDATE sso_user SET password = ? WHERE id = ?');
if (!$stmt2) {
    echo json_encode(['success' => false, 'error' => 'Server error (prepare update)']);
    exit;
}
$stmt2->bind_param('si', $new_hash, $user_id);
if (!$stmt2->execute()) {
    $stmt2->close();
    echo json_encode(['success' => false, 'error' => 'Failed to update password']);
    exit;
}
$stmt2->close();

echo json_encode(['success' => true]);
exit;
?>