<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();

// ✅ Only admins allowed
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$db = getDB();
$user = getCurrentUser();
$success = '';
$error = '';

/* ===========================
   ✅ DELETE MESSAGE (if requested)
=========================== */
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $message_id = (int)$_GET['delete'];
    try {
        $stmt = $db->getConnection()->prepare("DELETE FROM messages WHERE message_id = :id");
        $stmt->execute([':id' => $message_id]);
        $success = "Message deleted successfully!";
    } catch (PDOException $e) {
        $error = "Error deleting message: " . $e->getMessage();
    }
}

/* ===========================
   ✅ FETCH ALL MESSAGES
=========================== */
try {
    $stmt = $db->getConnection()->query("
        SELECT 
            m.message_id,
            m.subject,
            m.message_body,
            m.sent_at,
            u1.username AS sender_name,
            u2.username AS recipient_name
        FROM messages m
        LEFT JOIN users u1 ON m.sender_id = u1.user_id
        LEFT JOIN users u2 ON m.recipient_id = u2.user_id
        ORDER BY m.sent_at DESC
    ");
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $messages = [];
    $error = "Error loading messages: " . $e->getMessage();
}

// Calculate stats
$totalMessages = count($messages);
$thisWeek = count(array_filter($messages, fn($m) => strtotime($m['sent_at']) > strtotime('-7 days')));
$last24Hours = count(array_filter($messages, fn($m) => strtotime($m['sent_at']) > strtotime('-24 hours')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messages Management - Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --sidebar-width: 280px;
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --danger-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    --primary: #667eea;
    --success: #11998e;
    --danger: #e74a3b;
    --dark: #0f172a;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: #f8f9fc;
    overflow-x: hidden;
}

/* Sidebar */
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
    font-weight: 700;
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
    font-weight: 700;
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
    font-weight: 500;
}

.sidebar-footer {
    position: absolute;
    bottom: 0;
    width: 100%;
    padding: 1.5rem;
    background: rgba(0, 0, 0, 0.2);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-footer a {
    background: rgba(255,255,255,0.1);
    border-radius: 8px;
    padding: 0.8rem;
    text-align: center;
    display: block;
    color: white;
    text-decoration: none;
    transition: all 0.3s ease;
}

.sidebar-footer a:hover {
    background: rgba(255,255,255,0.2);
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
    font-weight: 700;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.search-box {
    position: relative;
}

.search-box input {
    padding: 0.6rem 1rem 0.6rem 2.5rem;
    border: 1px solid #e3e6f0;
    border-radius: 50px;
    width: 300px;
    transition: all 0.3s ease;
}

.search-box input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    width: 350px;
}

.search-box i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #858796;
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
    font-weight: 700;
    font-size: 1.1rem;
}

.user-info {
    text-align: left;
}

.user-info .name {
    font-weight: 700;
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

/* Stats Cards */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
}

.stat-card.primary::before { background: var(--primary-gradient); }
.stat-card.success::before { background: var(--success-gradient); }
.stat-card.info::before { background: var(--info-gradient); }

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
    margin-bottom: 1rem;
}

.stat-card.primary .stat-icon { background: var(--primary-gradient); }
.stat-card.success .stat-icon { background: var(--success-gradient); }
.stat-card.info .stat-icon { background: var(--info-gradient); }

.stat-card h6 {
    margin: 0;
    color: #858796;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 700;
}

.stat-card .number {
    font-size: 2rem;
    font-weight: 800;
    color: #5a5c69;
    margin: 0.5rem 0;
}

.stat-card .change {
    font-size: 0.85rem;
    color: #1cc88a;
    font-weight: 600;
}

/* Alert */
.alert {
    border-radius: 14px;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    border: 1px solid;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 600;
    animation: slideDown 0.4s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert-success {
    background: #d1fae5;
    border-color: #a7f3d0;
    color: #065f46;
}

.alert-danger {
    background: #fee2e2;
    border-color: #fecaca;
    color: #991b1b;
}

/* Content Card */
.content-card {
    background: white;
    border-radius: 15px;
    padding: 0;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    margin-bottom: 2rem;
    overflow: hidden;
}

.card-header-custom {
    padding: 1.5rem 2rem;
    border-bottom: 2px solid #f8f9fc;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header-custom h5 {
    margin: 0;
    font-weight: 700;
    color: #5a5c69;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.badge {
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 700;
}

.badge-primary {
    background: #dbeafe;
    color: #1e40af;
}

/* Table */
.custom-table {
    width: 100%;
    border-collapse: collapse;
}

.custom-table thead {
    background: #f8f9fc;
}

.custom-table th {
    padding: 1rem 1.5rem;
    font-weight: 700;
    color: #5a5c69;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    text-align: left;
}

.custom-table td {
    padding: 1.25rem 1.5rem;
    vertical-align: middle;
    border-bottom: 1px solid #f8f9fc;
    font-size: 0.9rem;
    color: #5a5c69;
}

.custom-table tbody tr {
    transition: all 0.3s ease;
}

.custom-table tbody tr:hover {
    background: #f8f9fc;
}

.user-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.table-avatar {
    width: 40px;
    height: 40px;
    background: var(--primary-gradient);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.table-avatar.success {
    background: var(--success-gradient);
}

.user-name {
    font-weight: 600;
    color: #5a5c69;
}

.message-subject {
    font-weight: 600;
    color: #5a5c69;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.message-body {
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    line-clamp: 2;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    line-height: 1.5;
    color: #858796;
}

.message-date {
    color: #858796;
    font-size: 0.85rem;
}

.btn-delete {
    padding: 0.5rem 1rem;
    background: var(--danger-gradient);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 2px 8px rgba(231, 74, 59, 0.3);
}

.btn-delete:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(231, 74, 59, 0.4);
    color: white;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-icon {
    width: 80px;
    height: 80px;
    background: #f8f9fc;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    font-size: 2rem;
    color: #858796;
}

.empty-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #5a5c69;
    margin-bottom: 0.5rem;
}

.empty-text {
    color: #858796;
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
    
    .mobile-toggle {
        display: block;
    }
    
    .topbar h2 {
        font-size: 1.3rem;
    }
    
    .search-box input {
        width: 200px;
    }
    
    .search-box input:focus {
        width: 250px;
    }
    
    .user-info {
        display: none;
    }
    
    .content-area {
        padding: 1rem;
    }
    
    .stats-row {
        grid-template-columns: 1fr;
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
            <h2>Messages Management</h2>
        </div>
        
        <div class="topbar-right">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search messages...">
            </div>
            
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
        <!-- Statistics Cards -->
        <div class="stats-row">
            <div class="stat-card primary">
                <div class="stat-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <h6>Total Messages</h6>
                <div class="number"><?= $totalMessages ?></div>
                <div class="change">
                    <i class="fas fa-arrow-up"></i> All Time
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <h6>This Week</h6>
                <div class="number"><?= $thisWeek ?></div>
                <div class="change">
                    <i class="fas fa-arrow-up"></i> Last 7 Days
                </div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h6>Last 24 Hours</h6>
                <div class="number"><?= $last24Hours ?></div>
                <div class="change">
                    <i class="fas fa-arrow-up"></i> Recent Activity
                </div>
            </div>
        </div>
        
        <!-- Alert Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>
        
        <!-- Messages Table -->
        <div class="content-card">
            <div class="card-header-custom">
                <h5>
                    <i class="fas fa-envelope-open-text"></i>
                    All Messages
                    <span class="badge badge-primary"><?= $totalMessages ?></span>
                </h5>
            </div>
            
            <div style="overflow-x: auto;">
                <table class="custom-table" id="messagesTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Sender</th>
                            <th>Recipient</th>
                            <th>Subject</th>
                            <th>Message</th>
                            <th>Date & Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($messages): ?>
                            <?php foreach ($messages as $index => $msg): ?>
                                <tr>
                                    <td style="font-weight: 700; color: #858796;"><?= $index + 1 ?></td>
                                    <td>
                                        <div class="user-cell">
                                            <div class="table-avatar">
                                                <?= strtoupper(substr($msg['sender_name'] ?? 'N', 0, 1)) ?>
                                            </div>
                                            <span class="user-name"><?= htmlspecialchars($msg['sender_name'] ?? 'N/A') ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="user-cell">
                                            <div class="table-avatar success">
                                                <?= strtoupper(substr($msg['recipient_name'] ?? 'N', 0, 1)) ?>
                                            </div>
                                            <span class="user-name"><?= htmlspecialchars($msg['recipient_name'] ?? 'N/A') ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="message-subject" title="<?= htmlspecialchars($msg['subject']) ?>">
                                            <?= htmlspecialchars($msg['subject']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="message-body">
                                            <?= htmlspecialchars($msg['message_body']) ?>
                                        </div>
                                    </td>
                                    <td class="message-date">
                                        <i class="far fa-clock" style="margin-right: 0.25rem;"></i>
                                        <?= date('M j, Y', strtotime($msg['sent_at'])) ?><br>
                                        <small><?= date('g:i A', strtotime($msg['sent_at'])) ?></small>
                                    </td>
                                    <td>
                                        <a href="?delete=<?= $msg['message_id'] ?>" 
                                           class="btn-delete" 
                                           onclick="return confirm('Are you sure you want to delete this message?')">
                                            <i class="fas fa-trash-alt"></i>
                                            Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <i class="fas fa-inbox"></i>
                                        </div>
                                        <h3 class="empty-title">No Messages Found</h3>
                                        <p class="empty-text">There are currently no messages in the system.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle Sidebar for Mobile
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('active');
}

// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchText = this.value.toLowerCase();
    const table = document.getElementById('messagesTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const text = row.textContent.toLowerCase();
        
        if (text.includes(searchText)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
});

// Auto-hide alerts after 5 seconds
const alerts = document.querySelectorAll('.alert');
alerts.forEach(alert => {
    setTimeout(() => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-20px)';
        setTimeout(() => alert.remove(), 300);
    }, 5000);
});

// Close sidebar when clicking outside on mobile
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

// Number animation for stats
function animateValue(element, start, end, duration) {
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        element.textContent = Math.floor(progress * (end - start) + start);
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}

// Trigger number animation when visible
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const number = entry.target;
            const finalValue = parseInt(number.textContent);
            animateValue(number, 0, finalValue, 1000);
            observer.unobserve(number);
        }
    });
});

document.querySelectorAll('.stat-card .number').forEach(number => {
    observer.observe(number);
});

// Add fade-in animation to cards on load
window.addEventListener('load', function() {
    const cards = document.querySelectorAll('.stat-card, .content-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.5s ease';
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + K for search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.getElementById('searchInput').focus();
    }
    
    // ESC to close sidebar on mobile
    if (e.key === 'Escape' && window.innerWidth <= 768) {
        document.getElementById('sidebar').classList.remove('active');
    }
});

// Table row hover effect
document.querySelectorAll('.custom-table tbody tr').forEach(row => {
    row.addEventListener('mouseenter', function() {
        this.style.cursor = 'pointer';
    });
});

// Add loading state to delete buttons
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', function(e) {
        if (confirm('Are you sure you want to delete this message? This action cannot be undone.')) {
            const icon = this.querySelector('i');
            icon.className = 'fas fa-spinner fa-spin';
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
        } else {
            e.preventDefault();
        }
    });
});

// Real-time search highlight
document.getElementById('searchInput').addEventListener('input', function() {
    const searchText = this.value.toLowerCase();
    const rows = document.querySelectorAll('.custom-table tbody tr');
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        let found = false;
        
        cells.forEach(cell => {
            const text = cell.textContent.toLowerCase();
            if (text.includes(searchText) && searchText !== '') {
                found = true;
            }
        });
        
        if (searchText === '' || found) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// Smooth scroll behavior
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Add print functionality
function printTable() {
    window.print();
}

// Add export to CSV functionality
function exportToCSV() {
    const table = document.getElementById('messagesTable');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td, th');
        const rowData = [];
        cells.forEach(cell => {
            rowData.push('"' + cell.textContent.replace(/"/g, '""') + '"');
        });
        csv.push(rowData.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'messages_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Add tooltip functionality
document.querySelectorAll('[title]').forEach(element => {
    element.addEventListener('mouseenter', function() {
        const title = this.getAttribute('title');
        if (title && title.length > 30) {
            this.style.position = 'relative';
        }
    });
});

// Message preview modal (future enhancement)
function showMessagePreview(messageId) {
    console.log('Show message preview for ID:', messageId);
    // Implement modal preview here
}

// Bulk actions (future enhancement)
function selectAllMessages() {
    const checkboxes = document.querySelectorAll('.message-checkbox');
    checkboxes.forEach(cb => cb.checked = true);
}

function deselectAllMessages() {
    const checkboxes = document.querySelectorAll('.message-checkbox');
    checkboxes.forEach(cb => cb.checked = false);
}

// Filter messages by date range
function filterByDateRange(days) {
    const rows = document.querySelectorAll('.custom-table tbody tr');
    const now = new Date();
    const cutoffDate = new Date(now.setDate(now.getDate() - days));
    
    rows.forEach(row => {
        const dateCell = row.querySelector('.message-date');
        if (dateCell) {
            const dateText = dateCell.textContent;
            const messageDate = new Date(dateText);
            
            if (messageDate >= cutoffDate) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
}

// Notification sound for new messages (optional)
function playNotificationSound() {
    const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBTGH0fPTgjMGHm7A7+OZUQ8PTqTi9LdoJggog9Dz0X0zBR5qvO7imFAPEFK');
    audio.play().catch(e => console.log('Audio play failed:', e));
}

// Auto-refresh messages (optional - disabled by default)
let autoRefreshEnabled = false;
let autoRefreshInterval;

function toggleAutoRefresh() {
    autoRefreshEnabled = !autoRefreshEnabled;
    
    if (autoRefreshEnabled) {
        autoRefreshInterval = setInterval(() => {
            location.reload();
        }, 60000); // Refresh every 60 seconds
        console.log('Auto-refresh enabled');
    } else {
        clearInterval(autoRefreshInterval);
        console.log('Auto-refresh disabled');
    }
}

// Initialize tooltips if Bootstrap is fully loaded
if (typeof bootstrap !== 'undefined') {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Log page load time for performance monitoring
window.addEventListener('load', function() {
    const perfData = window.performance.timing;
    const pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;
    console.log('Page load time:', (pageLoadTime / 1000).toFixed(2), 'seconds');
});

// Responsive table handling
function handleResponsiveTable() {
    const table = document.querySelector('.custom-table');
    const wrapper = table.parentElement;
    
    if (window.innerWidth < 768) {
        wrapper.style.overflowX = 'auto';
    } else {
        wrapper.style.overflowX = 'visible';
    }
}

window.addEventListener('resize', handleResponsiveTable);
handleResponsiveTable();

// Add confirmation before leaving page if search is active
window.addEventListener('beforeunload', function(e) {
    const searchInput = document.getElementById('searchInput');
    if (searchInput.value.trim() !== '') {
        e.preventDefault();
        e.returnValue = 'You have an active search. Are you sure you want to leave?';
    }
});

// Enhanced delete confirmation with details
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const row = this.closest('tr');
        const sender = row.querySelector('.user-name').textContent;
        const subject = row.querySelector('.message-subject').textContent;
        
        const confirmMsg = `Are you sure you want to delete this message?\n\nFrom: ${sender}\nSubject: ${subject}\n\nThis action cannot be undone.`;
        
        if (confirm(confirmMsg)) {
            window.location.href = this.href;
        }
    });
});

console.log('Messages Management System initialized successfully');
console.log('Total messages loaded:', <?= $totalMessages ?>);
</script>

</body>
</html>