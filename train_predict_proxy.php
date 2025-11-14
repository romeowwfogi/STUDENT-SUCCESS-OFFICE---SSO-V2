<?php
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}
if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}
$name = $_FILES['file']['name'] ?? 'dataset.csv';
$tmp = $_FILES['file']['tmp_name'];
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    http_response_code(400);
    echo json_encode(['error' => 'File must be CSV']);
    exit;
}
$fi = new finfo(FILEINFO_MIME_TYPE);
$mime = $fi->file($tmp);
if ($mime && strpos($mime, 'text') === false && strpos($mime, 'csv') === false && $mime !== 'application/vnd.ms-excel') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type']);
    exit;
}
$cf = curl_file_create($tmp, 'text/csv', $name);
$fields = ['file' => $cf];
$ch = curl_init('https://sso-prediction-admission.onrender.com/train_predict_next_year');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$resp = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($resp === false) {
    http_response_code(502);
    echo json_encode(['error' => $err ?: 'Upstream request failed']);
    exit;
}
$data = json_decode($resp, true);
if (json_last_error() === JSON_ERROR_NONE) {
    http_response_code($code >= 200 && $code < 300 ? 200 : $code);
    echo json_encode($data);
    exit;
}
http_response_code($code >= 200 && $code < 300 ? 200 : $code);
echo json_encode(['error' => 'Unexpected upstream response', 'raw' => $resp]);
