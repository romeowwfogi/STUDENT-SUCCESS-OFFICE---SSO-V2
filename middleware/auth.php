<?php

/**
 * Authentication Middleware
 * 
 * This middleware handles user authentication verification for all protected pages.
 * It checks if the user is logged in and has valid session data.
 * 
 * Usage: Include this file at the top of any page that requires authentication
 * Example: require_once 'middleware/auth.php';
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is authenticated
 * 
 * @return bool True if user is authenticated, false otherwise
 */
function isAuthenticated()
{
    return isset($_SESSION['user_id']) &&
        isset($_SESSION['user_email']) &&
        isset($_SESSION['login_time']);
}

/**
 * Check if session has expired
 * 
 * @param int $timeout Session timeout in seconds (default: 8 hours)
 * @return bool True if session has expired, false otherwise
 */
function isSessionExpired($timeout = 28800)
{ // 8 hours default
    if (!isset($_SESSION['login_time'])) {
        return true;
    }

    return (time() - $_SESSION['login_time']) > $timeout;
}

/**
 * Refresh session timestamp
 * Updates the login time to extend the session
 */
function refreshSession()
{
    $_SESSION['login_time'] = time();
}

/**
 * Destroy user session and redirect to login
 * 
 * @param string $message Optional message to display on login page
 */
function destroySessionAndRedirect($message = '')
{
    // Store message in session before destroying it
    if (!empty($message)) {
        $_SESSION['auth_message'] = $message;
    }

    // Destroy session data
    session_unset();
    session_destroy();

    // Clear session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Redirect to login page
    header('Location: ' . getLoginUrl());
    exit();
}

/**
 * Get the login URL relative to current page
 * 
 * @return string Login URL
 */
function getLoginUrl()
{
    // Get current directory depth to determine relative path to login.php
    $currentPath = $_SERVER['PHP_SELF'];
    $depth = substr_count(dirname($currentPath), '/');

    // Build relative path to login.php
    $relativePath = str_repeat('../', $depth) . 'login.php';

    // If we're already in the root directory, just use login.php
    if ($depth === 0 || dirname($currentPath) === '/') {
        $relativePath = 'login.php';
    }

    return $relativePath;
}

/**
 * Get user information from session
 * 
 * @return array User information
 */
function getCurrentUser()
{
    if (!isAuthenticated()) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'email' => $_SESSION['user_email'],
        'name' => $_SESSION['user_name'] ?? 'Unknown User',
        'login_time' => $_SESSION['login_time']
    ];
}

/**
 * Main authentication check
 * This function should be called on every protected page
 * 
 * @param bool $refreshSession Whether to refresh session timestamp (default: true)
 */
function requireAuth($refreshSession = true)
{
    // Check if user is authenticated
    if (!isAuthenticated()) {
        destroySessionAndRedirect('Please log in to access this page.');
        return;
    }

    // Check if session has expired
    if (isSessionExpired()) {
        destroySessionAndRedirect('Your session has expired. Please log in again.');
        return;
    }

    // Refresh session timestamp if requested
    if ($refreshSession) {
        refreshSession();
    }
}

// Auto-execute authentication check when this file is included
// This ensures that any page including this middleware is automatically protected
requireAuth();

/**
 * Optional: Log authentication events
 * Uncomment the following lines if you want to log authentication events
 */
/*
function logAuthEvent($event, $details = '') {
    $logFile = __DIR__ . '/../logs/auth.log';
    $timestamp = date('Y-m-d H:i:s');
    $user = getCurrentUser();
    $userId = $user ? $user['id'] : 'unknown';
    $userEmail = $user ? $user['email'] : 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $logEntry = "[$timestamp] Event: $event | User ID: $userId | Email: $userEmail | IP: $ip | Details: $details" . PHP_EOL;
    
    // Create logs directory if it doesn't exist
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Log successful authentication
logAuthEvent('AUTH_SUCCESS', 'User accessed: ' . $_SERVER['PHP_SELF']);
*/
