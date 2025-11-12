<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();

// Only logged-in students can access
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$db = getDB();

// Get unread notifications
$unreadNotifications = getUnreadNotificationCount($user_id);

/* ===============================
   ✅ Message Counts
=============================== */
try {
    $stmt = $db->getConnection()->prepare("
        SELECT 
            COUNT(*) AS total_received,
            SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) AS unread_count
        FROM messages
        WHERE recipient_id = :uid
    ");
    $stmt->execute([':uid' => $user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $total_received = $stats['total_received'] ?? 0;
    $unread_count = $stats['unread_count'] ?? 0;

    $stmtSent = $db->getConnection()->prepare("
        SELECT COUNT(*) FROM messages WHERE sender_id = :uid
    ");
    $stmtSent->execute([':uid' => $user_id]);
    $total_sent = $stmtSent->fetchColumn() ?? 0;
} catch (PDOException $e) {
    $total_received = $unread_count = $total_sent = 0;
}

/* ===============================
   ✅ Fetch Messages Sent by Teachers
=============================== */
try {
    $stmt = $db->getConnection()->prepare("
        SELECT 
            m.message_id,
            m.subject,
            m.message_body,
            m.sent_at,
            m.is_read,
            u.username AS sender_name
        FROM messages m
        INNER JOIN users u ON m.sender_id = u.user_id
        WHERE m.recipient_id = :student_id 
        AND u.role = 'teacher'
        ORDER BY m.sent_at DESC
    ");
    $stmt->execute([':student_id' => $user_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $messages = [];
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messages - Student Dashboard</title>

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
    --student-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
    background: var(--student-gradient);
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

.notification-icon {
    position: relative;
    cursor: pointer;
    font-size: 1.3rem;
    color: #858796;
    transition: color 0.3s ease;
}

.notification-icon:hover {
    color: #764ba2;
}

.notification-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #e74a3b;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
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
    background: var(--student-gradient);
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

/* Page Header */
.page-header {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.page-header-content {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.page-icon {
    width: 70px;
    height: 70px;
    border-radius: 15px;
    background: var(--info-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
}

.page-title h1 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: #5a5c69;
}

.page-title p {
    margin: 0.3rem 0 0 0;
    color: #858796;
    font-size: 0.95rem;
}

.header-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.back-button {
    padding: 0.8rem 1.5rem;
    background: var(--primary-gradient);
    color: white;
    border: none;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.back-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(118, 75, 162, 0.3);
    color: white;
}

.compose-button {
    padding: 0.8rem 1.5rem;
    background: var(--success-gradient);
    color: white;
    border: none;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.compose-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(17, 153, 142, 0.3);
    color: white;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
}

.stat-icon.total {
    background: var(--info-gradient);
}

.stat-icon.unread {
    background: var(--warning-gradient);
}

.stat-icon.sent {
    background: var(--success-gradient);
}

.stat-content h4 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: #5a5c69;
}

.stat-content p {
    margin: 0;
    color: #858796;
    font-size: 0.85rem;
}

/* Messages Section */
.messages-section {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e3e6f0;
}

.section-header h4 {
    margin: 0;
    color: #5a5c69;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.section-header i {
    color: #667eea;
}

/* Message List */
.message-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.message-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: #f8f9fc;
    border-radius: 10px;
    transition: all 0.3s ease;
    cursor: pointer;
    border-left: 4px solid transparent;
}

.message-item:hover {
    background: white;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
    transform: translateX(5px);
    border-left-color: #667eea;
}

.message-item.unread {
    background: linear-gradient(135deg, rgba(79, 172, 254, 0.05) 0%, rgba(0, 242, 254, 0.05) 100%);
    border-left-color: #4facfe;
}

.message-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--primary-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.message-content {
    flex: 1;
    min-width: 0;
}

.message-header {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    margin-bottom: 0.3rem;
}

.message-sender {
    font-weight: 700;
    color: #5a5c69;
    font-size: 1rem;
}

.message-type {
    padding: 0.2rem 0.6rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
}

.message-type.inbox {
    background: linear-gradient(135deg, rgba(79, 172, 254, 0.1) 0%, rgba(0, 242, 254, 0.1) 100%);
    color: #4facfe;
}

.message-type.sent {
    background: linear-gradient(135deg, rgba(17, 153, 142, 0.1) 0%, rgba(56, 239, 125, 0.1) 100%);
    color: #11998e;
}

.message-subject {
    font-weight: 600;
    color: #5a5c69;
    margin-bottom: 0.3rem;
    display: -webkit-box;
    line-clamp: 1;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.message-preview {
    color: #858796;
    font-size: 0.85rem;
    display: -webkit-box;
    line-clamp: 2;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.message-meta {
    text-align: right;
    flex-shrink: 0;
}

.message-time {
    color: #858796;
    font-size: 0.8rem;
    margin-bottom: 0.5rem;
}

.message-status {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.message-status.read {
    background: linear-gradient(135deg, rgba(17, 153, 142, 0.1) 0%, rgba(56, 239, 125, 0.1) 100%);
    color: #11998e;
}

.message-status.unread {
    background: linear-gradient(135deg, rgba(240, 147, 251, 0.1) 0%, rgba(245, 87, 108, 0.1) 100%);
    color: #f093fb;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state i {
    font-size: 5rem;
    color: #e3e6f0;
    margin-bottom: 1.5rem;
}

.empty-state h4 {
    color: #5a5c69;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #858796;
    margin-bottom: 1.5rem;
}

/* Mobile Menu Toggle */
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
    
    .mobile-toggle {
        display: block;
    }
    
    .topbar h2 {
        font-size: 1.3rem;
    }
    
    .user-info {
        display: none;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .page-header-content {
        width: 100%;
    }
    
    .header-actions {
        width: 100%;
        flex-direction: column;
    }

    .header-actions a {
        width: 100%;
        justify-content: center;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .message-item {
        flex-direction: column;
        align-items: flex-start;
    }

    .message-meta {
        align-self: flex-end;
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
            <?php echo getNotificationBadgeHTML($user_id, 'comment.php'); ?>
            
            <div class="user-profile">
                <div class="user-avatar">
                    <?= strtoupper(substr($_SESSION['username'] ?? 'S', 0, 1)) ?>
                </div>
                <div class="user-info">
                    <div class="name"><?= htmlspecialchars($_SESSION['username'] ?? 'Student') ?></div>
                    <div class="role">Student</div>
                </div>
                <i class="fas fa-chevron-down" style="color: #858796; font-size: 0.8rem;"></i>
            </div>
        </div>
    </div>
    
    <!-- Content Area -->
    <div class="content-area">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-content">
                <div class="page-icon">📧</div>
                <div class="page-title">
                    <h1>Messages</h1>
                    <p>View your message stats</p>
                </div>
            </div>
            <div class="header-actions">
                <a href="send_message.php" class="compose-button">
                    <i class="fas fa-edit"></i>
                    Compose Message
                </a>
                <a href="dashboard.php" class="back-button">
                    <i class="fas fa-arrow-left"></i>
                    Back
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total">📬</div>
                <div class="stat-content">
                    <h4><?= $total_received ?></h4>
                    <p>Total Messages</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon unread">🔔</div>
                <div class="stat-content">
                    <h4><?= $unread_count ?></h4>
                    <p>Unread Messages</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon sent">📤</div>
                <div class="stat-content">
                    <h4><?= $total_sent ?></h4>
                    <p>Sent Messages</p>
                </div>
            </div>
        </div>

        <!-- Empty Messages Section -->
       <!-- Messages Section -->
<div class="messages-section">
    <div class="section-header">
        <h4><i class="fas fa-inbox"></i> Inbox</h4>
        <a href="send_message.php" class="compose-button">
            <i class="fas fa-edit"></i> New Message
        </a>
    </div>

    <?php if (!empty($messages)): ?>
        <div class="message-list">
            <?php foreach ($messages as $msg): ?>
                <div class="message-item <?= $msg['is_read'] ? '' : 'unread' ?>">
                    <div class="message-avatar">
                        <?= strtoupper(substr($msg['sender_name'], 0, 1)) ?>
                    </div>
                    <div class="message-content">
                        <div class="message-header">
                            <div class="message-sender"><?= htmlspecialchars($msg['sender_name']) ?></div>
                            <span class="message-type inbox">
                                <?= ($msg['sender_name'] === 'admin') ? 'Admin' : 'Teacher' ?>
                            </span>
                        </div>
                        <div class="message-subject">
                            <?= htmlspecialchars($msg['subject']) ?>
                        </div>
                        <div class="message-preview">
                            <?= nl2br(htmlspecialchars(substr($msg['message_body'], 0, 100))) ?>...
                        </div>
                    </div>
                    <div class="message-meta">
                        <div class="message-time">
                            <?= date('M d, Y H:i', strtotime($msg['sent_at'])) ?>
                        </div>
                        <div class="message-status <?= $msg['is_read'] ? 'read' : 'unread' ?>">
                            <?= $msg['is_read'] ? 'Read' : 'Unread' ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-envelope-open-text"></i>
            <h4>No Messages Yet</h4>
            <p>You haven’t received any messages from your teachers or the admin.</p>
            <a href="send_message.php" class="compose-button">
                <i class="fas fa-paper-plane"></i> Send Message
            </a>
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
        
        // Keyboard shortcut - ESC to close sidebar on mobile
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.remove('active');
            }
        });
        
        // Add fade-in animation on load
        window.addEventListener('load', function() {
            const elements = document.querySelectorAll('.page-header, .stat-card, .messages-section');
            elements.forEach((element, index) => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(20px)';
                element.style.transition = 'all 0.5s ease';
                
                setTimeout(() => {
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>