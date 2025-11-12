<?php
// ====================================================
// FILE: teacher/view_timetable.php
// Teacher's Timetable View
// ====================================================

define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();

// ✅ Only teachers can access
if (!isLoggedIn() || !isTeacher()) {
    header('Location: ../login.php?error=Access+Denied');
    exit;
}

$user = getCurrentUser();
$teacher_id = $user['teacher_id']; // make sure this exists in your teachers table

$db = getDB()->getConnection();

// ✅ Fetch timetable entries for this teacher
$sql = "
    SELECT 
        t.timetable_id,
        t.class_level,
        s.subject_name,
        t.day_of_week,
        t.start_time,
        t.end_time,
        t.room_number
    FROM timetables t
    JOIN subjects s ON t.subject_id = s.subject_id
    WHERE t.teacher_id = ?
    ORDER BY 
        FIELD(t.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
        t.start_time
";

$stmt = $db->prepare($sql);
$stmt->execute([$teacher_id]);
$timetable = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by day for better display
$grouped_timetable = [];
foreach ($timetable as $entry) {
    $grouped_timetable[$entry['day_of_week']][] = $entry;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Timetable - Online School Portal</title>

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
    background: var(--info-gradient);
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

/* Timetable Card */
.timetable-card {
    background: white;
    border-radius: 15px;
    padding: 0;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    margin-bottom: 2rem;
}

.day-header {
    background: var(--primary-gradient);
    color: white;
    padding: 1.2rem 1.5rem;
    font-size: 1.2rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.8rem;
}

.day-header i {
    font-size: 1.4rem;
}

.sessions-container {
    padding: 1.5rem;
}

.session-item {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 1.5rem;
    padding: 1.2rem;
    margin-bottom: 1rem;
    background: linear-gradient(135deg, #f8f9fc 0%, #ffffff 100%);
    border-radius: 12px;
    border-left: 4px solid #667eea;
    transition: all 0.3s ease;
    align-items: center;
}

.session-item:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.session-time {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 100px;
}

.time-badge {
    background: var(--info-gradient);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    text-align: center;
    margin-bottom: 0.3rem;
}

.duration {
    font-size: 0.75rem;
    color: #858796;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.session-details {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.subject-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: #5a5c69;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.subject-name i {
    color: #667eea;
}

.class-info {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.info-badge {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.8rem;
    background: white;
    border-radius: 6px;
    font-size: 0.85rem;
    color: #5a5c69;
    border: 1px solid #e3e6f0;
}

.info-badge i {
    color: #667eea;
}

.session-room {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.3rem;
}

.room-badge {
    background: var(--warning-gradient);
    color: white;
    padding: 0.6rem 1rem;
    border-radius: 8px;
    font-weight: 700;
    font-size: 1rem;
    min-width: 80px;
    text-align: center;
}

.room-label {
    font-size: 0.7rem;
    color: #858796;
    text-transform: uppercase;
    letter-spacing: 0.5px;
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
    background: var(--primary-gradient);
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

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.2rem;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-icon.classes {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.stat-icon.hours {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
}

.stat-icon.subjects {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
}

.stat-info h4 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #5a5c69;
}

.stat-info p {
    margin: 0;
    font-size: 0.85rem;
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
    
    .session-item {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .session-time,
    .session-room {
        align-items: center;
        justify-content: center;
    }
    
    .class-info {
        justify-content: center;
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
                <h2>My Timetable</h2>
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
            <!-- Page Header -->
            <div class="page-header">
                <div class="header-content">
                    <div class="header-icon">
                        📅
                    </div>
                    <div class="header-text">
                        <h1>My Teaching Schedule</h1>
                        <p>View your complete weekly timetable</p>
                    </div>
                </div>
                <a href="dashboard.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>

            <?php if (empty($timetable)): ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Timetable Found</h3>
                    <p>You don't have any scheduled classes yet. Please contact the administrator.</p>
                </div>
            <?php else: ?>
                <!-- Stats Cards -->
                <?php
                $total_sessions = count($timetable);
                $unique_subjects = count(array_unique(array_column($timetable, 'subject_name')));
                $days_active = count($grouped_timetable);
                ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon classes">
                            <i class="fas fa-chalkboard"></i>
                        </div>
                        <div class="stat-info">
                            <h4><?= $total_sessions ?></h4>
                            <p>Total Sessions</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon subjects">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-info">
                            <h4><?= $unique_subjects ?></h4>
                            <p>Subjects</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon hours">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="stat-info">
                            <h4><?= $days_active ?></h4>
                            <p>Active Days</p>
                        </div>
                    </div>
                </div>

                <!-- Timetable by Day -->
                <?php 
                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                $day_icons = [
                    'Monday' => 'fa-sun',
                    'Tuesday' => 'fa-star',
                    'Wednesday' => 'fa-cloud',
                    'Thursday' => 'fa-bolt',
                    'Friday' => 'fa-heart',
                    'Saturday' => 'fa-smile',
                    'Sunday' => 'fa-coffee'
                ];
                
                foreach ($days as $day):
                    if (!isset($grouped_timetable[$day])) continue;
                ?>
                    <div class="timetable-card">
                        <div class="day-header">
                            <i class="fas <?= $day_icons[$day] ?>"></i>
                            <?= $day ?>
                        </div>
                        <div class="sessions-container">
                            <?php foreach ($grouped_timetable[$day] as $session): 
                                $start = date("g:i A", strtotime($session['start_time']));
                                $end = date("g:i A", strtotime($session['end_time']));
                                $start_ts = strtotime($session['start_time']);
                                $end_ts = strtotime($session['end_time']);
                                $duration = ($end_ts - $start_ts) / 60;
                            ?>
                                <div class="session-item">
                                    <div class="session-time">
                                        <div class="time-badge"><?= htmlspecialchars($start) ?></div>
                                        <div class="duration">
                                            <i class="far fa-clock"></i>
                                            <?= $duration ?> mins
                                        </div>
                                    </div>
                                    
                                    <div class="session-details">
                                        <div class="subject-name">
                                            <i class="fas fa-book-open"></i>
                                            <?= htmlspecialchars($session['subject_name']) ?>
                                        </div>
                                        <div class="class-info">
                                            <div class="info-badge">
                                                <i class="fas fa-users"></i>
                                                <?= htmlspecialchars($session['class_level']) ?>
                                            </div>
                                            <div class="info-badge">
                                                <i class="fas fa-arrow-right"></i>
                                                <?= htmlspecialchars($end) ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="session-room">
                                        <div class="room-badge">
                                            <?= htmlspecialchars($session['room_number']) ?>
                                        </div>
                                        <div class="room-label">Room</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
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
            const cards = document.querySelectorAll('.timetable-card, .stat-card, .page-header');
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
        
        // Highlight current day
        const today = new Date().toLocaleDateString('en-US', { weekday: 'long' });
        document.querySelectorAll('.day-header').forEach(header => {
            if (header.textContent.trim().includes(today)) {
                header.style.background = 'linear-gradient(135deg, #11998e 0%, #38ef7d 100%)';
                header.innerHTML += ' <span style="margin-left: auto; font-size: 0.9rem;">📍 Today</span>';
            }
        });
    </script>
</body>
</html>