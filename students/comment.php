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

// Mark notifications as read (optional)
try {
    $stmt = $db->getConnection()->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE recipient_id = :student_id AND is_read = 0
    ");
    $stmt->execute([':student_id' => $user['user_id']]);
} catch (PDOException $e) {
    // Silent fail
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Notifications - School Portal</title>

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

/* Page Header */
.page-header {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.5rem;
}

.header-content {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.header-icon {
    width: 70px;
    height: 70px;
    border-radius: 15px;
    background: var(--danger-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    animation: float 3s ease-in-out infinite;
}

.header-text h1 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: #5a5c69;
}

.header-text p {
    margin: 0.3rem 0 0 0;
    color: #858796;
    font-size: 1rem;
}

.notification-stats {
    display: flex;
    gap: 2rem;
    align-items: center;
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.stat-label {
    font-size: 0.85rem;
    color: #858796;
    margin-top: 0.3rem;
}

.btn-back {
    background: var(--success-gradient);
    color: white;
    border: none;
    padding: 0.8rem 1.5rem;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    box-shadow: 0 4px 15px rgba(17, 153, 142, 0.3);
}

.btn-back:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(17, 153, 142, 0.4);
    color: white;
}

/* Notification Card */
.notification-card {
    background: white;
    border-radius: 15px;
    padding: 1.8rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    border-left: 5px solid #667eea;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.notification-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    background: var(--danger-gradient);
}

.notification-card:hover {
    transform: translateX(5px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.notification-card.priority-high::before {
    background: var(--danger-gradient);
}

.notification-card.priority-normal::before {
    background: var(--info-gradient);
}

.notification-card.priority-low::before {
    background: var(--success-gradient);
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 1rem;
}

.notification-title {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
}

.notification-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    background: var(--danger-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.notification-info h3 {
    margin: 0 0 0.3rem 0;
    font-size: 1.2rem;
    font-weight: 700;
    color: #5a5c69;
}

.sender-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: #858796;
}

.sender-avatar {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: var(--primary-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.7rem;
    font-weight: 600;
}

.notification-time {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.8rem;
    color: #858796;
    white-space: nowrap;
}

.notification-time i {
    font-size: 0.75rem;
}

.notification-body {
    padding: 1.2rem;
    background: linear-gradient(135deg, #f8f9fc 0%, #ffffff 100%);
    border-radius: 10px;
    color: #5a5c69;
    line-height: 1.7;
    margin-top: 1rem;
}

.priority-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.9rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-top: 0.8rem;
}

.priority-high {
    background: linear-gradient(135deg, #fff0f0 0%, #ffe0e0 100%);
    color: #dc3545;
}

.priority-normal {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    color: #1976d2;
}

.priority-low {
    background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
    color: #388e3c;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
}

.empty-state i {
    font-size: 5rem;
    margin-bottom: 1.5rem;
    opacity: 0.3;
    background: var(--danger-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.empty-state h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.5rem;
    color: #5a5c69;
}

.empty-state p {
    margin: 0;
    color: #858796;
    font-size: 1rem;
}

/* Error Alert */
.alert-error {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    border-left: 5px solid #dc3545;
    color: #721c24;
    padding: 1.2rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.alert-error i {
    font-size: 1.5rem;
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
    
    .page-header {
        flex-direction: column;
        text-align: center;
    }
    
    .header-content {
        flex-direction: column;
        text-align: center;
    }
    
    .notification-stats {
        flex-direction: column;
        gap: 1rem;
    }
    
    .notification-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .notification-title {
        flex-direction: column;
        text-align: center;
    }
}
</style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-user-graduate"></i>
            <h4>School Portal</h4>
            <p>Student Panel</p>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-section">Main Menu</div>
            <a href="dashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="my_subjects.php">
                <i class="fas fa-book"></i>
                <span>My Subjects</span>
            </a>
            <a href="assignments.php">
                <i class="fas fa-tasks"></i>
                <span>Assignments</span>
            </a>
            <a href="quizzes.php">
                <i class="fas fa-brain"></i>
                <span>Quizzes</span>
            </a>
            
            <div class="menu-section">Academic</div>
            <a href="grades.php">
                <i class="fas fa-chart-line"></i>
                <span>My Grades</span>
            </a>
            <a href="attendance.php">
                <i class="fas fa-calendar-check"></i>
                <span>Attendance</span>
            </a>
            <a href="timetable.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Timetable</span>
            </a>
            
            <div class="menu-section">Communication</div>
            <a href="notifications.php" class="active">
                <i class="fas fa-bell"></i>
                <span>Notifications</span>
            </a>
            <a href="messages.php">
                <i class="fas fa-envelope"></i>
                <span>Messages</span>
            </a>
            
            <div class="menu-section"></div>
            <a href="profile.php">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
            </a>
            <a href="settings.php">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </div>
        
        <div class="sidebar-footer">
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
                <h2>Notifications</h2>
            </div>
            
            <div class="topbar-right">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?= strtoupper(substr($user['username'] ?? 'S', 0, 1)) ?>
                    </div>
                    <div class="user-info">
                        <div class="name"><?= htmlspecialchars($user['username'] ?? 'Student') ?></div>
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
                <div class="header-content">
                    <div class="header-icon">
                        🔔
                    </div>
                    <div class="header-text">
                        <h1>My Notifications</h1>
                        <p>Stay updated with important announcements from your teachers</p>
                    </div>
                </div>
                <div class="notification-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?= count($notifications) ?></div>
                        <div class="stat-label">Total</div>
                    </div>
                </div>
                <a href="dashboard.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if (count($notifications) > 0): ?>
                <?php foreach ($notifications as $n): 
                    $priority = $n['priority'] ?? 'normal';
                ?>
                    <div class="notification-card priority-<?= $priority ?>">
                        <div class="notification-header">
                            <div class="notification-title">
                                <div class="notification-icon">
                                    <?php if ($priority === 'high'): ?>
                                        <i class="fas fa-exclamation-circle"></i>
                                    <?php elseif ($priority === 'low'): ?>
                                        <i class="fas fa-info-circle"></i>
                                    <?php else: ?>
                                        <i class="fas fa-bell"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="notification-info">
                                    <h3><?= htmlspecialchars($n['title']) ?></h3>
                                    <div class="sender-info">
                                        <div class="sender-avatar">
                                            <?= strtoupper(substr($n['sender_name'], 0, 1)) ?>
                                        </div>
                                        <span><?= htmlspecialchars($n['sender_name']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="notification-time">
                                <i class="far fa-clock"></i>
                                <?= date('M d, Y g:i A', strtotime($n['created_at'])) ?>
                            </div>
                        </div>
                        
                        <div class="notification-body">
                            <?= nl2br(htmlspecialchars($n['message'])) ?>
                        </div>

                        <span class="priority-badge priority-<?= $priority ?>">
                            <?php if ($priority === 'high'): ?>
                                <i class="fas fa-exclamation"></i> High Priority
                            <?php elseif ($priority === 'low'): ?>
                                <i class="fas fa-info"></i> Low Priority
                            <?php else: ?>
                                <i class="fas fa-star"></i> Normal
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <h3>No Notifications Yet</h3>
                    <p>You don't have any notifications at the moment. Check back later!</p>
                </div>
            <?php endif; ?>
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
            const cards = document.querySelectorAll('.notification-card, .page-header');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 50);
                }, index * 100);
            });
        });
    </script>
</body>
</html>