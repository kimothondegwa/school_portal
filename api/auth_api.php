<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
session_start();

// Allow cross-origin for API access
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$db = getDB(); // Returns instance of your Database class

// ============================================================
// LOGIN API ENDPOINT
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'login') {
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['email']) || empty($data['password'])) {
        echo json_encode(["success" => false, "message" => "Email and password are required."]);
        exit;
    }

    $email = trim($data['email']);
    $password = trim($data['password']);

    try {
        // ✅ Using your custom DB wrapper methods
        $user = $db->query("SELECT * FROM users WHERE email = :email LIMIT 1")
                   ->bind(':email', $email)
                   ->fetch();

        if (!$user) {
            echo json_encode(["success" => false, "message" => "Invalid email or password."]);
            exit;
        }

        if (!password_verify($password, $user['password_hash'])) {
            echo json_encode(["success" => false, "message" => "Invalid email or password."]);
            exit;
        }

        // ✅ Store session info
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['logged_in'] = true;

        echo json_encode([
            "success" => true,
            "message" => "Login successful.",
            "user" => [
                "id" => $user['user_id'],
                "username" => $user['username'],
                "email" => $user['email'],
                "role" => $user['role']
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
    }
    exit;
}

// ============================================================
// LOGOUT ENDPOINT
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    echo json_encode(["success" => true, "message" => "Logged out successfully."]);
    exit;
}

// ============================================================
// CHECK SESSION STATUS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'check') {
    if (isset($_SESSION['user_id'])) {
        echo json_encode([
            "logged_in" => true,
            "user" => [
                "id" => $_SESSION['user_id'],
                "username" => $_SESSION['username'],
                "role" => $_SESSION['role']
            ]
        ]);
    } else {
        echo json_encode(["logged_in" => false]);
    }
    exit;
}

// ============================================================
// INVALID REQUEST
// ============================================================
echo json_encode(["success" => false, "message" => "Invalid API request."]);
exit;
?>
