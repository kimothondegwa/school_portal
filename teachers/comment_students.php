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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Send Comment - Online School Portal</title>

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
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: calc(100vh - 80px);
}

/* Comment Card */
.comment-card {
    background: white;
    border-radius: 20px;
    padding: 3rem;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    max-width: 700px;
    width: 100%;
    position: relative;
    overflow: hidden;
}

.comment-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 6px;
    background: var(--warning-gradient);
}

.card-header {
    text-align: center;
    margin-bottom: 2.5rem;
}

.card-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    border-radius: 20px;
    background: var(--warning-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    animation: float 3s ease-in-out infinite;
}

.card-header h2 {
    margin: 0 0 0.5rem 0;
    font-size: 2rem;
    font-weight: 700;
    color: #5a5c69;
}

.card-header p {
    margin: 0;
    color: #858796;
    font-size: 1rem;
}

/* Form Styles */
.form-group {
    margin-bottom: 2rem;
}

.form-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.8rem;
    font-weight: 600;
    color: #5a5c69;
    font-size: 1rem;
}

.form-label i {
    color: #667eea;
    font-size: 1.1rem;
}

.form-control {
    width: 100%;
    padding: 1rem 1.2rem;
    border: 2px solid #e3e6f0;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    font-family: inherit;
    background: #f8f9fc;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}

.form-control:hover {
    border-color: #b8b9cf;
}

select.form-control {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23667eea' d='M10.293 3.293L6 7.586 1.707 3.293A1 1 0 00.293 4.707l5 5a1 1 0 001.414 0l5-5a1 1 0 10-1.414-1.414z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    padding-right: 3rem;
}

textarea.form-control {
    resize: vertical;
    min-height: 150px;
    line-height: 1.6;
}

/* Button Group */
.button-group {
    display: flex;
    gap: 1rem;
    margin-top: 2.5rem;
    flex-wrap: wrap;
}

.btn-submit {
    flex: 1;
    background: var(--warning-gradient);
    color: white;
    border: none;
    padding: 1rem 2rem;
    border-radius: 12px;
    font-size: 1.05rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    box-shadow: 0 4px 15px rgba(240, 147, 251, 0.3);
}

.btn-submit:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 25px rgba(240, 147, 251, 0.4);
}

.btn-submit:active {
    transform: translateY(-1px);
}

.btn-back {
    flex: 1;
    background: var(--success-gradient);
    color: white;
    border: none;
    padding: 1rem 2rem;
    border-radius: 12px;
    font-size: 1.05rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    text-decoration: none;
    box-shadow: 0 4px 15px rgba(17, 153, 142, 0.3);
}

.btn-back:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 25px rgba(17, 153, 142, 0.4);
    color: white;
}

.btn-back:active {
    transform: translateY(-1px);
}

/* Alerts */
.alert {
    padding: 1.2rem 1.5rem;
    border-radius: 12px;
    margin-top: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    font-weight: 500;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert i {
    font-size: 1.5rem;
}

.alert.success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
    border-left: 5px solid #28a745;
}

.alert.error {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    color: #721c24;
    border-left: 5px solid #dc3545;
}

/* Student Count Badge */
.student-count {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: var(--info-gradient);
    color: white;
    padding: 0.4rem 1rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    margin-left: 0.5rem;
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
    
    .comment-card {
        padding: 2rem 1.5rem;
    }
    
    .card-header h2 {
        font-size: 1.5rem;
    }
    
    .button-group {
        flex-direction: column;
    }
    
    .btn-submit, .btn-back {
        width: 100%;
    }
}

/* Loading Animation */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.loading {
    animation: pulse 1.5s ease-in-out infinite;
}
</style>
</head>
<body>
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
            <a href="../logout.php" style="background: rgba(255,255,255,0.1); border-radius: 8px; padding: 0.8rem; text-align: center; display: block;">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <i class="fas fa-bars mobile-toggle" onclick="toggleSidebar()"></i>
                <h2>Send Comment</h2>
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
            <div class="comment-card">
                <div class="card-header">
                    <div class="card-icon">
                        💬
                    </div>
                    <h2>Send Comment to Student</h2>
                    <p>Share feedback and important messages with your students
                        <span class="student-count">
                            <i class="fas fa-users"></i>
                            <?= count($students) ?> Students
                        </span>
                    </p>
                </div>

                <form method="POST" id="commentForm">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user-graduate"></i>
                            Select Student
                        </label>
                        <select name="student_id" id="student_id" class="form-control" required>
                            <option value="">-- Choose a student to send comment --</option>
                            <?php foreach ($students as $stu): ?>
                                <option value="<?= htmlspecialchars($stu['user_id']) ?>">
                                    <?= htmlspecialchars($stu['student_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-comment-dots"></i>
                            Your Comment
                        </label>
                        <textarea 
                            name="message" 
                            id="message" 
                            class="form-control" 
                            placeholder="Write your comment or feedback here... Be clear, constructive, and encouraging!"
                            required
                        ></textarea>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i>
                            Send Comment
                        </button>
                        <a href="dashboard.php" class="btn-back">
                            <i class="fas fa-arrow-left"></i>
                            Back to Dashboard
                        </a>
                    </div>
                </form>

                <?php if ($success): ?>
                    <div class="alert success">
                        <i class="fas fa-check-circle"></i>
                        <span><?= $success ?></span>
                    </div>
                <?php elseif ($error): ?>
                    <div class="alert error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= $error ?></span>
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
        
        // Character counter for textarea
        const textarea = document.getElementById('message');
        const form = document.getElementById('commentForm');
        
        textarea.addEventListener('input', function() {
            const length = this.value.length;
            if (length > 0) {
                this.style.borderColor = '#667eea';
            }
        });
        
        // Form submission animation
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('.btn-submit');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            submitBtn.disabled = true;
        });
        
        // Add fade-in animation on load
        window.addEventListener('load', function() {
            const card = document.querySelector('.comment-card');
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            card.style.transition = 'all 0.6s ease';
            
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100);
        });
        
        // Auto-dismiss alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            }, 5000);
        });
        
        // Add slideOut animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideOut {
                from {
                    opacity: 1;
                    transform: translateY(0);
                }
                to {
                    opacity: 0;
                    transform: translateY(-20px);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>