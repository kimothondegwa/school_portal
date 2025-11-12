<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();
$notifications = $db->query("SELECT * FROM notifications ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Admin Panel</title>
    
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
        
        /* Header Card */
        .header-card {
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
        
        .header-content h3 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 700;
            color: #5a5c69;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        
        .header-content p {
            margin: 0.5rem 0 0 0;
            color: #858796;
        }
        
        .header-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            animation: float 3s ease-in-out infinite;
        }
        
        /* Notifications Container */
        .notifications-container {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }
        
        .notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f8f9fc;
        }
        
        .notifications-header h5 {
            margin: 0;
            font-weight: 600;
            color: #5a5c69;
            font-size: 1.2rem;
        }
        
        .filter-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .filter-btn {
            padding: 0.4rem 1rem;
            border: 1px solid #e3e6f0;
            background: white;
            border-radius: 50px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #858796;
        }
        
        .filter-btn:hover {
            background: #f8f9fc;
            border-color: #764ba2;
            color: #764ba2;
        }
        
        .filter-btn.active {
            background: var(--primary-gradient);
            color: white;
            border-color: transparent;
        }
        
        /* Notification Items */
        .notification-item {
            padding: 1.5rem;
            border-left: 4px solid transparent;
            margin-bottom: 1rem;
            background: #f8f9fc;
            border-radius: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .notification-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        
        .notification-item.unread {
            background: #fff;
            border-left-color: #667eea;
        }
        
        .notification-item.info {
            border-left-color: #4facfe;
        }
        
        .notification-item.success {
            border-left-color: #11998e;
        }
        
        .notification-item.warning {
            border-left-color: #f093fb;
        }
        
        .notification-header-row {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.8rem;
        }
        
        .notification-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: #5a5c69;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: white;
        }
        
        .notification-item.info .notification-icon {
            background: var(--info-gradient);
        }
        
        .notification-item.success .notification-icon {
            background: var(--success-gradient);
        }
        
        .notification-item.warning .notification-icon {
            background: var(--warning-gradient);
        }
        
        .notification-item.unread .notification-icon {
            background: var(--primary-gradient);
        }
        
        .notification-time {
            font-size: 0.8rem;
            color: #858796;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .notification-message {
            color: #5a5c69;
            line-height: 1.6;
            margin-bottom: 0.8rem;
        }
        
        .notification-footer {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .notification-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-unread {
            background: #e3e6f0;
            color: #667eea;
        }
        
        .badge-priority {
            background: #fee2e2;
            color: #e74a3b;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #858796;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #e3e6f0;
            margin-bottom: 1rem;
        }
        
        .empty-state h4 {
            margin-bottom: 0.5rem;
            color: #5a5c69;
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
            
            .topbar h2 {
                font-size: 1.3rem;
            }
            
            .user-info {
                display: none;
            }
            
            .mobile-toggle {
                display: block;
            }
            
            .filter-buttons {
                flex-wrap: wrap;
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
                <h2>Notifications</h2>
            </div>
            
            <div class="topbar-right">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
                    </div>
                    <div class="user-info">
                        <div class="name"><?= htmlspecialchars($_SESSION['username']) ?></div>
                        <div class="role">Administrator</div>
                    </div>
                    <i class="fas fa-chevron-down" style="color: #858796; font-size: 0.8rem;"></i>
                </div>
            </div>
        </div>
        
        <!-- Content Area -->
        <div class="content-area">
            <!-- Header Card -->
            <div class="header-card">
                <div class="header-content">
                    <h3>
                        <i class="fas fa-bell"></i>
                        All Notifications
                    </h3>
                    <p>Stay updated with all system notifications and alerts</p>
                </div>
                <div class="header-icon">
                    <i class="fas fa-bell"></i>
                </div>
            </div>
            
            <!-- Notifications Container -->
            <div class="notifications-container">
                <div class="notifications-header">
                    <h5><i class="fas fa-list me-2"></i><?= count($notifications) ?> Total Notifications</h5>
                    <div class="filter-buttons">
                        <button class="filter-btn active" onclick="filterNotifications('all')">All</button>
                        <button class="filter-btn" onclick="filterNotifications('unread')">Unread</button>
                        <button class="filter-btn" onclick="filterNotifications('important')">Important</button>
                    </div>
                </div>
                
                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h4>No Notifications Yet</h4>
                        <p>You're all caught up! Check back later for updates.</p>
                    </div>
                <?php else: ?>
                    <div id="notificationsList">
                        <?php foreach ($notifications as $index => $n): ?>
                            <?php
                                // Determine notification type based on keywords in title/message
                                $type = 'info';
                                $icon = 'fa-info-circle';
                                if (stripos($n['title'], 'success') !== false || stripos($n['title'], 'approved') !== false) {
                                    $type = 'success';
                                    $icon = 'fa-check-circle';
                                } elseif (stripos($n['title'], 'warning') !== false || stripos($n['title'], 'alert') !== false) {
                                    $type = 'warning';
                                    $icon = 'fa-exclamation-triangle';
                                } elseif ($index < 3) {
                                    $type = 'unread';
                                    $icon = 'fa-bell';
                                }
                                
                                // Calculate time ago
                                $time = strtotime($n['created_at']);
                                $diff = time() - $time;
                                if ($diff < 60) {
                                    $timeAgo = 'Just now';
                                } elseif ($diff < 3600) {
                                    $timeAgo = floor($diff / 60) . ' minutes ago';
                                } elseif ($diff < 86400) {
                                    $timeAgo = floor($diff / 3600) . ' hours ago';
                                } else {
                                    $timeAgo = floor($diff / 86400) . ' days ago';
                                }
                            ?>
                            <div class="notification-item <?= $type ?>" data-type="<?= $type ?>">
                                <div class="notification-header-row">
                                    <div class="notification-title">
                                        <div class="notification-icon">
                                            <i class="fas <?= $icon ?>"></i>
                                        </div>
                                        <?= htmlspecialchars($n['title']) ?>
                                    </div>
                                    <div class="notification-time">
                                        <i class="far fa-clock"></i>
                                        <?= $timeAgo ?>
                                    </div>
                                </div>
                                <div class="notification-message">
                                    <?= htmlspecialchars($n['message']) ?>
                                </div>
                                <div class="notification-footer">
                                    <?php if ($type === 'unread'): ?>
                                        <span class="notification-badge badge-unread">
                                            <i class="fas fa-circle" style="font-size: 0.5rem;"></i> Unread
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($index % 3 === 0): ?>
                                        <span class="notification-badge badge-priority">
                                            <i class="fas fa-star"></i> Priority
                                        </span>
                                    <?php endif; ?>
                                    <span style="color: #858796; font-size: 0.85rem;">
                                        <i class="far fa-calendar"></i> <?= date('M d, Y', $time) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
        
        // Filter Notifications
        function filterNotifications(filter) {
            const buttons = document.querySelectorAll('.filter-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            const notifications = document.querySelectorAll('.notification-item');
            notifications.forEach(item => {
                if (filter === 'all') {
                    item.style.display = 'block';
                } else if (filter === 'unread') {
                    item.style.display = item.classList.contains('unread') ? 'block' : 'none';
                } else if (filter === 'important') {
                    const hasPriority = item.querySelector('.badge-priority');
                    item.style.display = hasPriority ? 'block' : 'none';
                }
            });
        }
        
        // Mark as read on click
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function() {
                this.classList.remove('unread');
                const unreadBadge = this.querySelector('.badge-unread');
                if (unreadBadge) {
                    unreadBadge.remove();
                }
            });
        });
        
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
        
        // Fade-in animation on load
        window.addEventListener('load', function() {
            const items = document.querySelectorAll('.notification-item');
            items.forEach((item, index) => {
                setTimeout(() => {
                    item.style.opacity = '0';
                    item.style.transform = 'translateY(20px)';
                    item.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        item.style.opacity = '1';
                        item.style.transform = 'translateY(0)';
                    }, 50);
                }, index * 50);
            });
        });
    </script>
</body>