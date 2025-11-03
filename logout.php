<?php
// ====================================================
// FILE: logout.php
// Handles user logout securely
// ====================================================

// Allow access to includes
define('APP_ACCESS', true);

// Include core files
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Start secure session
startSecureSession();

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    logActivity($_SESSION['user_id'], 'logout', 'users', $_SESSION['user_id'], 'User logged out');
}

// Clear all session data
$_SESSION = array();

// Delete session cookie (if any)
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

// Destroy the session
session_destroy();

// Redirect back to login page
header("Location: index.php?message=You+have+been+logged+out");
exit;
