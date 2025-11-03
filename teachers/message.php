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

/* ===========================
   ✅ GET TEACHER ID
=========================== */
try {
    $stmt = $db->getConnection()->prepare("SELECT teacher_id FROM teachers WHERE user_id = :uid");
    $stmt->execute([':uid' => $user['user_id']]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    $teacher_id = $teacher ? $teacher['teacher_id'] : null;
} catch (PDOException $e) {
    $teacher_id = null;
    $error = "Error fetching teacher ID: " . $e->getMessage();
}

/* ===========================
   ✅ FETCH STUDENTS
=========================== */
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

/* ===========================
   ✅ FETCH SUBJECTS (linked to this teacher)
=========================== */
try {
    if ($teacher_id) {
        $stmt = $db->getConnection()->prepare("
            SELECT subject_id, subject_name 
            FROM subjects 
            WHERE teacher_id = :tid AND is_active = 1
            ORDER BY subject_name ASC
        ");
        $stmt->execute([':tid' => $teacher_id]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $subjects = [];
    }
} catch (PDOException $e) {
    $subjects = [];
    $error = "Error loading subjects: " . $e->getMessage();
}

/* ===========================
   ✅ HANDLE MESSAGE SENDING
=========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $recipient_id = (int)($_POST['student_id'] ?? 0);
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $message_body = trim($_POST['message_body'] ?? '');

    if (!$recipient_id || !$subject_id || empty($message_body)) {
        $error = "Please select a student, subject, and enter a message.";
    } else {
        // Fetch subject name
        $stmt = $db->getConnection()->prepare("SELECT subject_name FROM subjects WHERE subject_id = :sid");
        $stmt->execute([':sid' => $subject_id]);
        $subject_info = $stmt->fetch(PDO::FETCH_ASSOC);

        $subject_final = "Regarding " . ($subject_info['subject_name'] ?? 'Subject');

        try {
            $stmt = $db->getConnection()->prepare("
                INSERT INTO messages (sender_id, recipient_id, subject, message_body, is_read, sent_at)
                VALUES (:sender_id, :recipient_id, :subject, :message_body, 0, NOW())
            ");
            $stmt->execute([
                ':sender_id' => $user['user_id'],
                ':recipient_id' => $recipient_id,
                ':subject' => $subject_final,
                ':message_body' => $message_body
            ]);
            $success = "Message sent successfully!";
        } catch (PDOException $e) {
            $error = "Error sending message: " . $e->getMessage();
        }
    }
}

/* ===========================
   ✅ FETCH INBOX (student → teacher)
=========================== */
try {
    $stmt = $db->getConnection()->prepare("
        SELECT m.*, u.username AS sender_name
        FROM messages m
        JOIN users u ON m.sender_id = u.user_id
        WHERE m.recipient_id = :teacher_id
        ORDER BY m.sent_at DESC
    ");
    $stmt->execute([':teacher_id' => $user['user_id']]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $messages = [];
    $error = "Error loading messages: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Teacher Messages</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
body { font-family: 'Poppins', sans-serif; background:#f5f6fa; }
.container { max-width:900px; margin:50px auto; background:#fff; padding:30px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
h2 { color:#333; margin-bottom:20px; }
select, textarea { width:100%; padding:10px; margin-bottom:15px; border:1px solid #ccc; border-radius:6px; font-size:15px; }
textarea { height:100px; resize:none; }
button { background:#4e73df; color:#fff; padding:10px 20px; border:none; border-radius:6px; cursor:pointer; font-size:15px; }
button:hover { opacity:0.9; }
.alert { margin-top:10px; padding:10px; border-radius:6px; }
.success { background:#d4edda; color:#155724; }
.error { background:#f8d7da; color:#721c24; }
.message-box { background:#f8f9fc; border-radius:10px; padding:10px 15px; margin-bottom:10px; border:1px solid #eee; }
.message-box strong { color:#4e73df; }
.timestamp { font-size:12px; color:#888; display:block; margin-top:5px; }
.subject { font-weight:bold; color:#333; }
.back-btn {
    display:inline-block;
    margin-top:15px;
    background:#1cc88a;
    color:#fff;
    padding:10px 20px;
    border-radius:6px;
    text-decoration:none;
}
.back-btn:hover { opacity:0.9; }
</style>
</head>
<body>

<div class="container">
    <h2><i class="fas fa-inbox"></i> Messages from Students</h2>

    <?php if ($messages && count($messages) > 0): ?>
        <?php foreach ($messages as $msg): ?>
            <div class="message-box">
                <div class="subject"><?= htmlspecialchars($msg['subject']) ?></div>
                <strong><?= htmlspecialchars($msg['sender_name']) ?>:</strong>
                <p><?= nl2br(htmlspecialchars($msg['message_body'])) ?></p>
                <span class="timestamp"><?= htmlspecialchars($msg['sent_at']) ?></span>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No messages yet.</p>
    <?php endif; ?>

    <hr style="margin:25px 0;">

    <h2><i class="fas fa-paper-plane"></i> Send Message to Student</h2>
    <form method="POST">
        <label><strong>Select Student:</strong></label>
        <select name="student_id" required>
            <option value="">-- Choose Student --</option>
            <?php foreach ($students as $stu): ?>
                <option value="<?= htmlspecialchars($stu['user_id']) ?>">
                    <?= htmlspecialchars($stu['student_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label><strong>Select Subject:</strong></label>
        <select name="subject_id" required>
            <option value="">-- Choose Subject --</option>
            <?php foreach ($subjects as $sub): ?>
                <option value="<?= htmlspecialchars($sub['subject_id']) ?>">
                    <?= htmlspecialchars($sub['subject_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label><strong>Message:</strong></label>
        <textarea name="message_body" placeholder="Write your message here..." required></textarea>

        <button type="submit" name="send_message"><i class="fas fa-paper-plane"></i> Send</button>
    </form>

    <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

    <?php if ($success): ?>
        <div class="alert success"><?= $success ?></div>
    <?php elseif ($error): ?>
        <div class="alert error"><?= $error ?></div>
    <?php endif; ?>
</div>

</body>
</html>
