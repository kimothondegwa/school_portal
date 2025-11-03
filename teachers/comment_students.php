<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();

// ✅ Only teachers allowed
if (!isLoggedIn() || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit;
}

$db = getDB();
$user = getCurrentUser();
$success = '';
$error = '';

// ✅ Fetch active students
try {
    $stmt = $db->getConnection()->query("
        SELECT s.student_id, CONCAT(s.first_name, ' ', s.last_name) AS student_name, u.user_id
        FROM students s
        INNER JOIN users u ON s.user_id = u.user_id
        WHERE u.is_active = 1
        ORDER BY s.first_name ASC
    ");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $students = [];
    $error = "Error loading students: " . $e->getMessage();
}

// ✅ Handle message send
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient_id = (int)($_POST['student_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    if (!$recipient_id || empty($message)) {
        $error = "Please select a student and enter a message.";
    } else {
        try {
            $stmt = $db->getConnection()->prepare("
                INSERT INTO notifications 
                (title, message, recipient_role, recipient_id, sender_id, is_read, priority)
                VALUES (:title, :message, 'student', :recipient_id, :sender_id, 0, 'normal')
            ");
            $stmt->execute([
                ':title' => 'Comment from Teacher',
                ':message' => $message,
                ':recipient_id' => $recipient_id,
                ':sender_id' => $user['user_id']
            ]);
            $success = "Comment sent successfully!";
        } catch (PDOException $e) {
            $error = "Error sending comment: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Send Comment to Student</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
body { font-family: 'Poppins', sans-serif; background:#f5f6fa; }
.container { max-width:700px; margin:70px auto; background:#fff; padding:30px; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.1); }
h2 { margin-bottom:20px; color:#333; }
select, textarea { width:100%; padding:10px; margin-bottom:15px; border:1px solid #ccc; border-radius:6px; font-size:15px; }
button, .dashboard-btn { background:#4e73df; color:#fff; padding:10px 20px; border:none; border-radius:6px; cursor:pointer; font-size:15px; text-decoration:none; display:inline-block; text-align:center; }
button:hover, .dashboard-btn:hover { opacity:0.9; }
.alert { margin-top:10px; padding:10px; border-radius:6px; }
.success { background:#d4edda; color:#155724; }
.error { background:#f8d7da; color:#721c24; }
.btn-container { display:flex; justify-content:space-between; align-items:center; margin-top:20px; }
</style>
</head>
<body>

<div class="container">
    <h2><i class="fas fa-comment-dots"></i> Send Comment to Student</h2>

    <form method="POST">
        <label for="student_id"><strong>Select Student:</strong></label>
        <select name="student_id" id="student_id" required>
            <option value="">-- Choose Student --</option>
            <?php foreach ($students as $stu): ?>
                <option value="<?= htmlspecialchars($stu['user_id']) ?>">
                    <?= htmlspecialchars($stu['student_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="message"><strong>Message:</strong></label>
        <textarea name="message" id="message" placeholder="Write your comment here..." required></textarea>

        <div class="btn-container">
            <button type="submit"><i class="fas fa-paper-plane"></i> Send Comment</button>
            <a href="dashboard.php" class="dashboard-btn"><i class="fas fa-home"></i> Back to Dashboard</a>
        </div>
    </form>

    <?php if ($success): ?>
        <div class="alert success"><?= $success ?></div>
    <?php elseif ($error): ?>
        <div class="alert error"><?= $error ?></div>
    <?php endif; ?>
</div>

</body>
</html>
