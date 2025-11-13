<?php
// Update email endpoint
// Requires: authenticated user, correct password confirmation

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

// Read and sanitize inputs
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if ($email === '') {
    echo json_encode(['success' => false, 'error' => 'Email is required']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email format']);
    exit;
}
if ($password === '') {
    echo json_encode(['success' => false, 'error' => 'Password is required']);
    exit;
}

$user_id = intval($_SESSION['user_id']);

// Check if the email is already used by another account
$stmtCheck = $conn->prepare('SELECT id FROM sso_user WHERE email = ? AND id <> ? LIMIT 1');
if (!$stmtCheck) {
    echo json_encode(['success' => false, 'error' => 'Server error (prepare check)']);
    exit;
}
$stmtCheck->bind_param('si', $email, $user_id);
$stmtCheck->execute();
$resultCheck = $stmtCheck->get_result();
if ($resultCheck && $resultCheck->num_rows > 0) {
    $stmtCheck->close();
    echo json_encode(['success' => false, 'error' => 'Email already in use']);
    exit;
}
$stmtCheck->close();

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

$hash = $row['password'];
if (!password_verify($password, $hash)) {
    echo json_encode(['success' => false, 'error' => 'Incorrect password']);
    exit;
}

// Update email
$stmt2 = $conn->prepare('UPDATE sso_user SET email = ? WHERE id = ?');
if (!$stmt2) {
    echo json_encode(['success' => false, 'error' => 'Server error (prepare update)']);
    exit;
}
$stmt2->bind_param('si', $email, $user_id);
if (!$stmt2->execute()) {
    $stmt2->close();
    echo json_encode(['success' => false, 'error' => 'Failed to update email']);
    exit;
}
$stmt2->close();

// Update session email
$_SESSION['user_email'] = $email;

echo json_encode(['success' => true, 'updated_email' => $email]);
exit;
?>