<?php
require_once 'middleware/auth.php';
require_once 'connection/db_connect.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$first = trim($_POST['first_name'] ?? '');
$middle = trim($_POST['middle_name'] ?? '');
$last = trim($_POST['last_name'] ?? '');
$suffix = trim($_POST['suffix'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($first === '' || $last === '' || $email === '') {
    echo json_encode(['ok' => false, 'error' => 'First, Last name and Email are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid email address']);
    exit;
}

$hasLen = strlen($password) >= 8;
$hasUpper = preg_match('/[A-Z]/', $password);
$hasLower = preg_match('/[a-z]/', $password);
$hasDigit = preg_match('/\d/', $password);
$hasSpecial = preg_match('/[^A-Za-z0-9]/', $password);
if (!($hasLen && $hasUpper && $hasLower && $hasDigit && $hasSpecial)) {
    echo json_encode(['ok' => false, 'error' => 'Password does not meet requirements']);
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
if (!$hash) {
    echo json_encode(['ok' => false, 'error' => 'Failed to hash password']);
    exit;
}

if ($stmtChk = $conn->prepare('SELECT id FROM sso_user WHERE email = ? LIMIT 1')) {
    $stmtChk->bind_param('s', $email);
    $stmtChk->execute();
    $res = $stmtChk->get_result();
    if ($res && $res->num_rows > 0) {
        $stmtChk->close();
        echo json_encode(['ok' => false, 'error' => 'Email already exists']);
        exit;
    }
    $stmtChk->close();
}

$stmt = $conn->prepare('INSERT INTO sso_user(first_name, middle_name, last_name, suffix, email, password, status) VALUES(?,?,?,?,?,?,?)');
if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => 'Database error: prepare failed']);
    exit;
}
$status = 'active';
$stmt->bind_param('sssssss', $first, $middle, $last, $suffix, $email, $hash, $status);
$ok = $stmt->execute();
if (!$ok) {
    $err = $stmt->error;
    $stmt->close();
    echo json_encode(['ok' => false, 'error' => 'Insert failed: ' . $err]);
    exit;
}
$newId = $stmt->insert_id;
$stmt->close();

echo json_encode(['ok' => true, 'id' => $newId, 'email' => $email]);
