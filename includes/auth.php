<?php
// ====================================================
// FILE: includes/auth.php
// Authentication and session management
// ====================================================

// Prevent direct access
if (!defined('APP_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Start secure session
 */
function startSecureSession() {
    // Session configuration
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');

    if (defined('SESSION_NAME')) {
        session_name(SESSION_NAME);
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Regenerate session ID periodically (every 5 minutes)
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}


/**
 * Login user
 */
function loginUser($email, $password) {
    $db = getDB();
    
    // Get user from database
    $sql = "SELECT * FROM users WHERE email = :email AND is_active = 1 LIMIT 1";
    $user = $db->query($sql)->bind(':email', $email)->fetch();
    
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    // Verify password
    if (!verifyPassword($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    // Set session variables
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['logged_in'] = true;
    
    // Update last login
    $updateSql = "UPDATE users SET last_login = NOW() WHERE user_id = :user_id";
    $db->query($updateSql)->bind(':user_id', $user['user_id'])->execute();
    
    // Log activity
    logActivity($user['user_id'], 'login', 'users', $user['user_id'], 'User logged in');
    
    return ['success' => true, 'role' => $user['role']];
}

/**
 * Logout user
 */
function logoutUser() {
    if (isset($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], 'logout', 'users', $_SESSION['user_id'], 'User logged out');
    }
    
    // Destroy session
    $_SESSION = array();
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
}

/**
 * Get current user details
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db = getDB();
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    $sql = "SELECT u.*, ";
    
    if ($role === 'student') {
        $sql .= "s.* FROM users u 
                 LEFT JOIN students s ON u.user_id = s.user_id 
                 WHERE u.user_id = :user_id";
    } elseif ($role === 'teacher') {
        $sql .= "t.* FROM users u 
                 LEFT JOIN teachers t ON u.user_id = t.user_id 
                 WHERE u.user_id = :user_id";
    } else {
        $sql .= "u.user_id FROM users u WHERE u.user_id = :user_id";
    }
    
    return $db->query($sql)->bind(':user_id', $userId)->fetch();
}
// ====================================================
// ROLE & LOGIN HELPERS (SAFE WRAPPED)
// ====================================================

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isLoggedIn() && isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin';
    }
}

if (!function_exists('isTeacher')) {
    function isTeacher() {
        return isLoggedIn() && isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'teacher';
    }
}

if (!function_exists('isStudent')) {
    function isStudent() {
        return isLoggedIn() && isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'student';
    }
}
