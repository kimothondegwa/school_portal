<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();

// ✅ Only students allowed
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$db = getDB();
$user = getCurrentUser();
$error = '';

// ✅ Fetch notifications sent to this student
try {
    $stmt = $db->getConnection()->prepare("
        SELECT n.*, u.username AS sender_name
        FROM notifications n
        JOIN users u ON n.sender_id = u.user_id
        WHERE n.recipient_id = :student_id
        ORDER BY n.created_at DESC
    ");
    $stmt->execute([':student_id' => $user['user_id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $notifications = [];
    $error = "Error loading notifications: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Notifications</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
body { 
    font-family: 'Poppins', sans-serif; 
    background:#f5f6fa; 
    margin:0; 
    padding:0;
}
.container { 
    max-width:800px; 
    margin:70px auto; 
    background:#fff; 
    padding:30px; 
    border-radius:12px; 
    box-shadow:0 4px 10px rgba(0,0,0,0.1); 
}
h2 { 
    margin-bottom:20px; 
    color:#333; 
    display:flex; 
    align-items:center; 
    justify-content:space-between;
}
h2 i { color:#4e73df; margin-right:10px; }
.notification { 
    background:#f8f9fc; 
    border:1px solid #eee; 
    border-radius:10px; 
    padding:15px; 
    margin-bottom:15px; 
}
.notification strong { color:#4e73df; }
.notification .time { 
    color:#888; 
    font-size:13px; 
    display:block; 
    margin-top:5px; 
}
.empty { 
    text-align:center; 
    color:#888; 
    font-size:15px; 
    margin-top:30px; 
}
.error { 
    background:#f8d7da; 
    color:#721c24; 
    padding:10px; 
    border-radius:6px; 
    margin-top:15px; 
}
.dashboard-btn {
    display:inline-flex;
    align-items:center;
    gap:8px;
    background:#4e73df;
    color:#fff;
    padding:8px 15px;
    border-radius:8px;
    font-size:14px;
    text-decoration:none;
    transition:0.3s ease;
}
.dashboard-btn:hover {
    background:#2e59d9;
    text-decoration:none;
    color:#fff;
}
</style>
</head>
<body>

<div class="container">
    <h2>
        <span><i class="fas fa-bell"></i> My Notifications</span>
        <a href="dashboard.php" class="dashboard-btn">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </h2>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (count($notifications) > 0): ?>
        <?php foreach ($notifications as $n): ?>
            <div class="notification">
                <strong><?= htmlspecialchars($n['title']) ?></strong><br>
                <p><?= nl2br(htmlspecialchars($n['message'])) ?></p>
                <span class="time">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($n['sender_name']) ?> 
                    | <?= htmlspecialchars($n['created_at']) ?>
                </span>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty">
            <i class="fas fa-inbox"></i> No notifications yet.
        </div>
    <?php endif; ?>
</div>

</body>
</html>
