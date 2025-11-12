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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messages - Online School Portal</title>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
    --sidebar-width: 280px;
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --danger-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f8f9fc;
    overflow-x: hidden;
}

/* Sidebar Styles */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: var(--sidebar-width);
    background: var(--primary-gradient);
    color: white;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 3px;
}

.sidebar-header {
    padding: 2rem 1.5rem;
    text-align: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-header i {
    font-size: 3rem;
    margin-bottom: 0.5rem;
    display: block;
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.sidebar-header h4 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 600;
}

.sidebar-header p {
    margin: 0.3rem 0 0 0;
    font-size: 0.85rem;
    opacity: 0.8;
}

.sidebar-menu {
    padding: 1rem 0;
}

.menu-section {
    padding: 1rem 1.5rem 0.5rem;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    opacity: 0.7;
    font-weight: 600;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    padding: 0.9rem 1.5rem;
    color: white;
    text-decoration: none;
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
}

.sidebar-menu a:hover {
    background: rgba(255, 255, 255, 0.1);
    border-left-color: white;
    padding-left: 2rem;
}

.sidebar-menu a.active {
    background: rgba(255, 255, 255, 0.15);
    border-left-color: white;
}

.sidebar-menu a i {
    margin-right: 1rem;
    width: 20px;
    text-align: center;
    font-size: 1.1rem;
}

.sidebar-menu a span {
    font-size: 0.95rem;
}

.sidebar-footer {
    position: absolute;
    bottom: 0;
    width: 100%;
    padding: 1.5rem;
    background: rgba(0, 0, 0, 0.2);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

/* Main Content */
.main-content {
    margin-left: var(--sidebar-width);
    min-height: 100vh;
    transition: all 0.3s ease;
}

/* Topbar */
.topbar {
    background: white;
    padding: 1rem 2rem;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 999;
}

.topbar h2 {
    margin: 0;
    font-size: 1.8rem;
    color: #5a5c69;
    font-weight: 600;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.user-profile {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    cursor: pointer;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    transition: all 0.3s ease;
}

.user-profile:hover {
    background: #f8f9fc;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 1.1rem;
}

.user-info {
    text-align: left;
}

.user-info .name {
    font-weight: 600;
    font-size: 0.9rem;
    color: #5a5c69;
}

.user-info .role {
    font-size: 0.75rem;
    color: #858796;
}

/* Content Area */
.content-area {
    padding: 2rem;
}

/* Message Cards */
.message-section {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    margin-bottom: 2rem;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f0f0f0;
}

.section-header i {
    font-size: 2rem;
    background: var(--info-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.section-header h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #5a5c69;
}

.message-box {
    background: linear-gradient(135deg, #f8f9fc 0%, #ffffff 100%);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    border-left: 4px solid #667eea;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.message-box:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.message-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 1rem;
}

.sender-info {
    display: flex;
    align-items: center;
    gap: 0.8rem;
}

.sender-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: var(--primary-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 1.1rem;
}

.sender-details h5 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #5a5c69;
}

.message-subject {
    margin: 0;
    font-size: 0.85rem;
    color: #667eea;
    font-weight: 500;
}

.message-time {
    font-size: 0.75rem;
    color: #858796;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.message-time i {
    font-size: 0.7rem;
}

.message-body {
    color: #5a5c69;
    line-height: 1.6;
    margin: 0;
    padding: 1rem;
    background: white;
    border-radius: 8px;
}

/* Form Styles */
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #5a5c69;
    font-size: 0.95rem;
}

.form-control {
    width: 100%;
    padding: 0.8rem 1rem;
    border: 2px solid #e3e6f0;
    border-radius: 10px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    font-family: inherit;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

textarea.form-control {
    resize: vertical;
    min-height: 120px;
}

.btn-send {
    background: var(--primary-gradient);
    color: white;
    border: none;
    padding: 0.9rem 2rem;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-send:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
}

.btn-back {
    background: var(--success-gradient);
    color: white;
    border: none;
    padding: 0.9rem 2rem;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    margin-top: 1rem;
}

.btn-back:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(17, 153, 142, 0.3);
    color: white;
}

/* Alerts */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 10px;
    margin-top: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.8rem;
    font-weight: 500;
}

.alert i {
    font-size: 1.2rem;
}

.alert.success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
    border-left: 4px solid #28a745;
}

.alert.error {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    color: #721c24;
    border-left: 4px solid #dc3545;
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: #858796;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.3;
}

.empty-state p {
    font-size: 1.1rem;
}

/* Mobile Toggle */
.mobile-toggle {
    display: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #5a5c69;
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
    
    .main-content {
        margin-left: 0;
    }
    
    .topbar h2 {
        font-size: 1.3rem;
    }
    
    .user-info {
        display: none;
    }
    
    .mobile-toggle {
        display: block;
    }
    
    .content-area {
        padding: 1rem;
    }
    
    .message-section {
        padding: 1.5rem;
    }
    
    .message-header {
        flex-direction: column;
        gap: 0.5rem;
    }
}
</style>
</head>
<body>
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <i class="fas fa-bars mobile-toggle" onclick="toggleSidebar()"></i>
                <h2>Messages</h2>
            </div>
            
            <div class="topbar-right">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?= strtoupper(substr($user['username'] ?? 'T', 0, 1)) ?>
                    </div>
                    <div class="user-info">
                        <div class="name"><?= htmlspecialchars($user['username'] ?? 'Teacher') ?></div>
                        <div class="role">Teacher</div>
                    </div>
                    <i class="fas fa-chevron-down" style="color: #858796; font-size: 0.8rem;"></i>
                </div>
            </div>
        </div>
        
        <!-- Content Area -->
        <div class="content-area">
            <!-- Inbox Section -->
            <div class="message-section">
                <div class="section-header">
                    <i class="fas fa-inbox"></i>
                    <h3>Messages from Students</h3>
                </div>

                <?php if ($messages && count($messages) > 0): ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="message-box">
                            <div class="message-header">
                                <div class="sender-info">
                                    <div class="sender-avatar">
                                        <?= strtoupper(substr($msg['sender_name'], 0, 1)) ?>
                                    </div>
                                    <div class="sender-details">
                                        <h5><?= htmlspecialchars($msg['sender_name']) ?></h5>
                                        <p class="message-subject"><?= htmlspecialchars($msg['subject']) ?></p>
                                    </div>
                                </div>
                                <div class="message-time">
                                    <i class="far fa-clock"></i>
                                    <?= htmlspecialchars($msg['sent_at']) ?>
                                </div>
                            </div>
                            <div class="message-body">
                                <?= nl2br(htmlspecialchars($msg['message_body'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-envelope-open"></i>
                        <p>No messages yet. Your inbox is empty.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Send Message Section -->
            <div class="message-section">
                <div class="section-header">
                    <i class="fas fa-paper-plane"></i>
                    <h3>Send Message to Student</h3>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Select Student:</label>
                        <select name="student_id" class="form-control" required>
                            <option value="">-- Choose Student --</option>
                            <?php foreach ($students as $stu): ?>
                                <option value="<?= htmlspecialchars($stu['user_id']) ?>">
                                    <?= htmlspecialchars($stu['student_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-book"></i> Select Subject:</label>
                        <select name="subject_id" class="form-control" required>
                            <option value="">-- Choose Subject --</option>
                            <?php foreach ($subjects as $sub): ?>
                                <option value="<?= htmlspecialchars($sub['subject_id']) ?>">
                                    <?= htmlspecialchars($sub['subject_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-comment-dots"></i> Message:</label>
                        <textarea name="message_body" class="form-control" placeholder="Write your message here..." required></textarea>
                    </div>

                    <button type="submit" name="send_message" class="btn-send">
                        <i class="fas fa-paper-plane"></i>
                        Send Message
                    </button>
                </form>

                <a href="dashboard.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>

                <?php if ($success): ?>
                    <div class="alert success">
                        <i class="fas fa-check-circle"></i>
                        <?= $success ?>
                    </div>
                <?php elseif ($error): ?>
                    <div class="alert error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= $error ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle Sidebar for Mobile
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }
        
        // Auto-hide mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-toggle');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });
        
        // Prevent sidebar from closing when clicking inside it
        document.getElementById('sidebar').addEventListener('click', function(e) {
            e.stopPropagation();
        });
        
        // ESC to close sidebar on mobile
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.remove('active');
            }
        });
        
        // Add fade-in animation on load
        window.addEventListener('load', function() {
            const sections = document.querySelectorAll('.message-section');
            sections.forEach((section, index) => {
                setTimeout(() => {
                    section.style.opacity = '0';
                    section.style.transform = 'translateY(20px)';
                    section.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        section.style.opacity = '1';
                        section.style.transform = 'translateY(0)';
                    }, 50);
                }, index * 100);
            });
        });
    </script>
</body>
</html>