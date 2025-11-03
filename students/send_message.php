<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();

// ✅ Only logged-in students can access
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();
$user = getCurrentUser();
$success = '';
$error = '';

// ✅ Fetch list of teachers
try {
    $stmt = $db->getConnection()->query("
        SELECT u.user_id, CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
        FROM teachers t
        INNER JOIN users u ON u.user_id = t.user_id
        WHERE u.is_active = 1
        ORDER BY t.first_name ASC
    ");
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $teachers = [];
    $error = "Error loading teachers: " . $e->getMessage();
}

// ✅ Handle message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient_id = (int)($_POST['teacher_id'] ?? 0);
    $message_body = trim($_POST['message'] ?? '');

    if (!$recipient_id || empty($message_body)) {
        $error = "Please select a teacher and write your message.";
    } else {
        // ✅ Auto-generate subject: first 5 words of message or fallback
        $words = explode(' ', $message_body);
        $subject = implode(' ', array_slice($words, 0, 5));
        if (strlen($subject) < 3) {
            $subject = "Message from " . $user['username'];
        }

        try {
            $stmt = $db->getConnection()->prepare("
                INSERT INTO messages (sender_id, recipient_id, subject, message_body, is_read, sent_at)
                VALUES (:sid, :rid, :subject, :body, 0, NOW())
            ");
            $stmt->execute([
                ':sid' => $user['user_id'],
                ':rid' => $recipient_id,
                ':subject' => $subject,
                ':body' => $message_body
            ]);
            $success = "Message sent successfully!";
        } catch (PDOException $e) {
            $error = "Error sending message: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Compose Message - Student Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">

            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4><i class="fas fa-edit"></i> Compose Message</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="teacher_id" class="form-label">Send To</label>
                            <select class="form-select" name="teacher_id" id="teacher_id" required>
                                <option value="">Select Teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?= $teacher['user_id'] ?>"><?= htmlspecialchars($teacher['teacher_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea name="message" id="message" class="form-control" rows="6" required></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="messages.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Inbox</a>
                            <button type="submit" class="btn btn-success"><i class="fas fa-paper-plane"></i> Send Message</button>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
