<?php
function sso_log_email_event($type, $data = [])
{
    $base = dirname(__DIR__);
    $file = $base . DIRECTORY_SEPARATOR . 'log.txt';
    $entry = date('Y-m-d H:i:s') . " [email:$type] " . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    try {
        file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) {
        error_log('Email log write failed: ' . $e->getMessage());
    }
}

function send_status_email($receiver, $subject, $body_html)
{
    global $SSO_EMAIL_SEND_API;

    $url = $SSO_EMAIL_SEND_API;
    if (!$url) {
        sso_log_email_event('skip', [
            'reason' => 'missing_api_url',
            'receiver' => $receiver,
            'subject' => $subject
        ]);
        return false;
    }

    $sender_email = $_SESSION['user_email'] ?? null;
    $sender_password = $_SESSION['user_password'] ?? null;
    if (!$sender_email || !$sender_password) {
        sso_log_email_event('skip', [
            'reason' => 'missing_session_credentials',
            'receiver' => $receiver,
            'subject' => $subject
        ]);
        error_log('Email API: missing session credentials (user_email/user_password).');
        return false;
    }

    $payload = [
        'email' => $sender_email,
        'password' => $sender_password,
        'receiver' => $receiver,
        'subject' => $subject,
        'body' => $body_html,
        'attachments' => [],
    ];

    sso_log_email_event('attempt', [
        'api' => $url,
        'sender' => $sender_email,
        'receiver' => $receiver,
        'subject' => $subject,
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: */*']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($resp === false) {
        $err = curl_error($ch);
        sso_log_email_event('error', [
            'http_code' => $httpCode,
            'error' => $err
        ]);
        error_log('Email API error: ' . $err);
        curl_close($ch);
        // Fallback attempt with form-encoded
        sso_log_email_event('fallback_attempt', [
            'api' => $url,
            'sender' => $sender_email,
            'receiver' => $receiver,
            'subject' => $subject,
            'method' => 'application/x-www-form-urlencoded'
        ]);
        $ch2 = curl_init($url);
        curl_setopt($ch2, CURLOPT_POST, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded', 'Accept: */*']);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 12);
        $resp2 = curl_exec($ch2);
        $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);
        sso_log_email_event('fallback_response', [
            'http_code' => $httpCode2,
            'body' => $resp2
        ]);
        $ok2 = ($httpCode2 >= 200 && $httpCode2 < 300);
        sso_log_email_event($ok2 ? 'success' : 'fail', [
            'receiver' => $receiver,
            'subject' => $subject,
            'http_code' => $httpCode2,
            'transport' => 'form-encoded'
        ]);
        return $ok2;
    }
    curl_close($ch);

    sso_log_email_event('response', [
        'http_code' => $httpCode,
        'body' => $resp
    ]);

    $ok = ($httpCode >= 200 && $httpCode < 300);
    sso_log_email_event($ok ? 'success' : 'fail', [
        'receiver' => $receiver,
        'subject' => $subject,
        'http_code' => $httpCode,
        'transport' => 'json'
    ]);
    return $ok;
}
