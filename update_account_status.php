<?php
require_once 'middleware/auth.php';
require_once 'connection/db_connect.php';
require_once 'function/decrypt.php';
require_once 'function/sendEmail.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$acct = isset($_POST['acct']) ? trim($_POST['acct']) : '';
$next = isset($_POST['next_status']) ? trim($_POST['next_status']) : '';
if ($id <= 0 || $acct === '' || $next === '') {
    echo json_encode(['ok' => false, 'error' => 'Missing parameters']);
    exit;
}

$updated = false;
$receiver = '';
$statusOut = $next;

$resolve_email = function($value) {
    $value = trim($value ?? '');
    if ($value === '') return '';
    if (strpos($value, '@') !== false) return $value;
    $dec = decryptData($value);
    if ($dec && strpos($dec, '@') !== false) return $dec;
    return $value;
};

if ($acct === 'sso') {
    if ($stmt = $conn->prepare('UPDATE sso_user SET status = ? WHERE id = ?')) {
        $stmt->bind_param('si', $next, $id);
        $updated = $stmt->execute();
        $stmt->close();
    }
    if ($stmt2 = $conn->prepare('SELECT email FROM sso_user WHERE id = ? LIMIT 1')) {
        $stmt2->bind_param('i', $id);
        $stmt2->execute();
        $res = $stmt2->get_result();
        if ($res && ($row = $res->fetch_assoc())) {
            $receiver = $resolve_email($row['email'] ?? '');
        }
        $stmt2->close();
    }
} elseif ($acct === 'admission') {
    if ($stmt = $conn->prepare('UPDATE users SET acc_status = ? WHERE id = ? AND acc_type = "admission"')) {
        $stmt->bind_param('si', $next, $id);
        $updated = $stmt->execute();
        $stmt->close();
    }
    if ($stmt2 = $conn->prepare('SELECT email FROM users WHERE id = ? LIMIT 1')) {
        $stmt2->bind_param('i', $id);
        $stmt2->execute();
        $res = $stmt2->get_result();
        if ($res && ($row = $res->fetch_assoc())) {
            $receiver = $resolve_email($row['email'] ?? '');
        }
        $stmt2->close();
    }
} elseif ($acct === 'services') {
    $is_active = (strtolower($next) === 'active') ? 1 : 0;
    $statusOut = $is_active === 1 ? 'active' : 'inactive';
    if ($stmt = $conn->prepare('UPDATE services_users SET is_active = ? WHERE id = ?')) {
        $stmt->bind_param('ii', $is_active, $id);
        $updated = $stmt->execute();
        $stmt->close();
    }
    if ($stmt2 = $conn->prepare('SELECT email FROM services_users WHERE id = ? LIMIT 1')) {
        $stmt2->bind_param('i', $id);
        $stmt2->execute();
        $res = $stmt2->get_result();
        if ($res && ($row = $res->fetch_assoc())) {
            $receiver = $resolve_email($row['email'] ?? '');
        }
        $stmt2->close();
    }
} else {
    echo json_encode(['ok' => false, 'error' => 'Invalid account type']);
    exit;
}

if (!$updated) {
    echo json_encode(['ok' => false, 'error' => 'Update failed']);
    exit;
}

$subject = !empty($ADMISSION_UPDATE_SUBJECT) ? $ADMISSION_UPDATE_SUBJECT : 'Account Status Update';
$body = '';
if (!empty($ADMISSION_UPDATE_TEMPLATE)) {
    $body = str_replace(['{{status}}', '{{remarks}}', '{{subject}}'], [$statusOut, '', $subject], $ADMISSION_UPDATE_TEMPLATE);
} else {
    $body = '<div style="font-family:Arial,sans-serif;line-height:1.5"><h2>' . htmlspecialchars($subject) . '</h2><p>Your account status has been updated to <strong>' . htmlspecialchars($statusOut) . '</strong>.</p></div>';
}
if ($receiver !== '') {
    send_status_email($receiver, $subject, $body);
}

echo json_encode(['ok' => true, 'next_status' => $statusOut]);
exit;