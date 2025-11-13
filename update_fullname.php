<?php
// Update fullname endpoint
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
$first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
$middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
$last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
$suffix = isset($_POST['suffix']) ? trim($_POST['suffix']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if ($first_name === '' || $last_name === '') {
    echo json_encode(['success' => false, 'error' => 'First name and Last name are required']);
    exit;
}
if ($password === '') {
    echo json_encode(['success' => false, 'error' => 'Password is required']);
    exit;
}

$user_id = intval($_SESSION['user_id']);

// Fetch current user password hash (lookup by id only to avoid status mismatches)
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

// Update names
$stmt2 = $conn->prepare('UPDATE sso_user SET first_name = ?, middle_name = ?, last_name = ?, suffix = ? WHERE id = ?');
if (!$stmt2) {
    echo json_encode(['success' => false, 'error' => 'Server error (prepare update)']);
    exit;
}
$stmt2->bind_param('ssssi', $first_name, $middle_name, $last_name, $suffix, $user_id);
if (!$stmt2->execute()) {
    $stmt2->close();
    echo json_encode(['success' => false, 'error' => 'Failed to update name']);
    exit;
}
$stmt2->close();

// Update session fullname
$parts = [];
if ($first_name !== '') $parts[] = $first_name;
if ($middle_name !== '') $parts[] = $middle_name;
if ($last_name !== '') $parts[] = $last_name;
if ($suffix !== '') $parts[] = $suffix;
$updated_name = trim(implode(' ', $parts));
$_SESSION['user_name'] = $updated_name;

echo json_encode(['success' => true, 'updated_name' => $updated_name]);
exit;
