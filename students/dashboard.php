<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Start secure session & check login
startSecureSession();

if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    redirect('../login.php');
}

$student_id = $_SESSION['user_id']; // user_id from session
$db = getDB();

// Fetch student info using user_id
$student = $db->query("SELECT * FROM students WHERE user_id = :id")
              ->bind(':id', $student_id)
              ->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Dashboard - Online School Portal</title>

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
    border-color: #764ba2;
    box-shadow: 0 0 0 3px rgba(118, 75, 162, 0.1);
    width: 350px;
}

.search-box i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #858796;
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

/* Dashboard Cards Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

.dashboard-card {
    background: white;
    border-radius: 15px;
    padding: 2rem 1.5rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    text-decoration: none;
    color: inherit;
    display: block;
}

.dashboard-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
}

.dashboard-card.messages::before { background: var(--info-gradient); }
.dashboard-card.submit::before { background: var(--warning-gradient); }
.dashboard-card.quiz::before { background: var(--success-gradient); }
.dashboard-card.grades::before { background: var(--primary-gradient); }
.dashboard-card.attendance::before { background: var(--danger-gradient); }
.dashboard-card.profile::before { background: var(--info-gradient); }

.dashboard-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

.card-icon-wrapper {
    width: 70px;
    height: 70px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
}

.dashboard-card.messages .card-icon-wrapper { background: var(--info-gradient); }
.dashboard-card.submit .card-icon-wrapper { background: var(--warning-gradient); }
.dashboard-card.quiz .card-icon-wrapper { background: var(--success-gradient); }
.dashboard-card.grades .card-icon-wrapper { background: var(--primary-gradient); }
.dashboard-card.attendance .card-icon-wrapper { background: var(--danger-gradient); }
.dashboard-card.profile .card-icon-wrapper { background: var(--info-gradient); }

.dashboard-card:hover .card-icon-wrapper {
    transform: scale(1.1) rotate(5deg);
}

.dashboard-card h5 {
    margin: 0 0 0.5rem 0;
    font-size: 1.2rem;
    font-weight: 700;
    color: #5a5c69;
}

.dashboard-card p {
    margin: 0;
    color: #858796;
    font-size: 0.9rem;
}

.dashboard-card .arrow-icon {
    position: absolute;
    bottom: 1.5rem;
    right: 1.5rem;
    font-size: 1.5rem;
    color: #e3e6f0;
    transition: all 0.3s ease;
}

.dashboard-card:hover .arrow-icon {
    color: #764ba2;
    transform: translateX(5px);
}

/* Welcome Section */
.welcome-section {
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

.welcome-content h3 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: #5a5c69;
}

.welcome-content p {
    margin: 0.5rem 0 0 0;
    color: #858796;
    font-size: 1rem;
}

.welcome-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--student-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    color: white;
    animation: float 3s ease-in-out infinite;
}

/* Student Details Card */
.details-card {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    margin-top: 2rem;
}

.details-card h4 {
    color: #5a5c69;
    font-weight: 700;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.details-card h4 i {
    color: #667eea;
}

.details-table {
    width: 100%;
    margin-top: 1rem;
}

.details-table tr {
    border-bottom: 1px solid #e3e6f0;
}

.details-table tr:last-child {
    border-bottom: none;
}

.details-table th {
    padding: 1rem;
    text-align: left;
    color: #5a5c69;
    font-weight: 600;
    width: 35%;
    background: #f8f9fc;
}

.details-table td {
    padding: 1rem;
    color: #858796;
}

/* Alert Styles */
.alert {
    border: none;
    border-radius: 10px;
    padding: 1rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.alert-info {
    background: linear-gradient(135deg, rgba(79, 172, 254, 0.1) 0%, rgba(0, 242, 254, 0.1) 100%);
    color: #0c7cd5;
    border-left: 4px solid #4facfe;
}

.alert-warning {
    background: linear-gradient(135deg, rgba(240, 147, 251, 0.1) 0%, rgba(245, 87, 108, 0.1) 100%);
    color: #dc3545;
    border-left: 4px solid #f093fb;
}

.alert i {
    font-size: 1.5rem;
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
    
    .search-box input {
        width: 200px;
    }
    
    .search-box input:focus {
        width: 250px;
    }
    
    .user-info {
        display: none;
    }
    
    .dashboard-grid {
        grid-template-columns: 1fr;
    }

    .details-table th,
    .details-table td {
        padding: 0.7rem;
        font-size: 0.9rem;
    }

    .details-table th {
        width: 40%;
    }
}

/* Mobile Menu Toggle */
.mobile-toggle {
    display: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #5a5c69;
}

@media (max-width: 768px) {
    .mobile-toggle {
        display: block;
    }
}

/* Section Title */
.section-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #5a5c69;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.section-title i {
    color: #764ba2;
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
            <a href="dashboard.php" class="active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="messages.php">
                <i class="fas fa-envelope"></i>
                <span>Messages</span>
            </a>
            
            <div class="menu-section">Academics</div>
            <a href="submit_assignment.php">
                <i class="fas fa-file-upload"></i>
                <span>Submit Assignment</span>
            </a>
            <a href="take_quiz.php">
                <i class="fas fa-brain"></i>
                <span>Take Quiz</span>
            </a>
            <a href="view_grades.php">
                <i class="fas fa-chart-line"></i>
                <span>View Grades</span>
            </a>
            <a href="attendance.php">
                <i class="fas fa-calendar-check"></i>
                <span>My Attendance</span>
            </a>
            <a href="view_assignment.php.php">
                <i class="fas fa-calendar-alt"></i>
                <span>view Assignment</span>

            <a href="comment.php.php">
                <i class="fas fa-calendar-alt"></i>
                <span>view comments</span>
            
            <div class="menu-section">TimeTable</div>
            <a href="timetable.php">
                <i class="fas fa-user-circle"></i>
                <span>TimeTable</span>
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
                <h2>Student Dashboard</h2>
            </div>
            
            <div class="topbar-right">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search...">
                </div>
                
                <div class="notification-icon">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">5</span>
                </div>
                
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
            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="welcome-content">
                    <h3>Welcome back, <?= htmlspecialchars($_SESSION['username'] ?? 'Student') ?>! 👋</h3>
                    <p>Ready to continue your learning journey today?</p>
                </div>
                <div class="welcome-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
            </div>

            <!-- Section Title -->
            <div class="section-title">
                <i class="fas fa-bolt"></i>
                Quick Actions
            </div>

            <!-- Dashboard Cards -->
            <div class="dashboard-grid">
                <a href="messages.php" class="dashboard-card messages">
                    <div class="card-icon-wrapper">
                        📧
                    </div>
                    <h5>Messages</h5>
                    <p>Check your inbox and communicate with teachers</p>
                    <i class="fas fa-arrow-right arrow-icon"></i>
                </a>

                <a href="submit_assignment.php" class="dashboard-card submit">
                    <div class="card-icon-wrapper">
                        📤
                    </div>
                    <h5>Submit Assignment</h5>
                    <p>Upload and submit your completed assignments</p>
                    <i class="fas fa-arrow-right arrow-icon"></i>
                </a>

                <a href="take_quiz.php" class="dashboard-card quiz">
                    <div class="card-icon-wrapper">
                        🧠
                    </div>
                    <h5>Take Quiz</h5>
                    <p>Test your knowledge with interactive quizzes</p>
                    <i class="fas fa-arrow-right arrow-icon"></i>
                </a>

                <a href="view_grades.php" class="dashboard-card grades">
                    <div class="card-icon-wrapper">
                        📊
                    </div>
                    <h5>View Grades</h5>
                    <p>Check your academic performance and scores</p>
                    <i class="fas fa-arrow-right arrow-icon"></i>
                </a>

                <a href="attendance.php" class="dashboard-card attendance">
                    <div class="card-icon-wrapper">
                        ✅
                    </div>
                    <h5>My Attendance</h5>
                    <p>View your attendance records and statistics</p>
                    <i class="fas fa-arrow-right arrow-icon"></i>
                </a>
                <a href="view_assignment.php" class="dashboard-card attendance">
                    <div class="card-icon-wrapper">
                        ✅
                    </div>
                    <h5>My Assignment</h5>
                    <p>View your assignment records and statistics</p>
                    <i class="fas fa-arrow-right arrow-icon"></i>

                <a href="comment.php" class="dashboard-card attendance">
                    <div class="card-icon-wrapper">
                        ✅
                    </div>
                    <h5>Comments</h5>
                    <p>comments from the teacher</p>
                    <i class="fas fa-arrow-right arrow-icon"></i>

                <a href="timetable.php" class="dashboard-card profile">
                    <div class="card-icon-wrapper">
                        📅
                    </div>
                    <h5>timeTable</h5>
                    <p>View the time table</p>
                    <i class="fas fa-arrow-right arrow-icon"></i>
                </a>
            </div>

            <?php if (isAdmin()): ?>
                <!-- Student Details Card (Admin View) -->
                <div class="details-card">
                    <h4>
                        <i class="fas fa-id-card"></i>
                        Student Details (Admin View)
                    </h4>
                    
                    <?php if ($student): ?>
                        <table class="details-table">
                            <tr>
                                <th><i class="fas fa-hashtag" style="color: #667eea;"></i> Admission Number</th>
                                <td><?= htmlspecialchars($student['admission_number']) ?></td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-school" style="color: #667eea;"></i> Class Level</th>
                                <td><?= htmlspecialchars($student['class_level']) ?></td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-birthday-cake" style="color: #667eea;"></i> Date of Birth</th>
                                <td><?= htmlspecialchars($student['date_of_birth']) ?></td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-venus-mars" style="color: #667eea;"></i> Gender</th>
                                <td><?= htmlspecialchars($student['gender']) ?></td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-phone" style="color: #667eea;"></i> Phone Number</th>
                                <td><?= htmlspecialchars($student['phone_number']) ?></td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-map-marker-alt" style="color: #667eea;"></i> Address</th>
                                <td><?= htmlspecialchars($student['address']) ?></td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-user-shield" style="color: #667eea;"></i> Guardian Name</th>
                                <td><?= htmlspecialchars($student['guardian_name']) ?></td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-mobile-alt" style="color: #667eea;"></i> Guardian Phone</th>
                                <td><?= htmlspecialchars($student['guardian_phone']) ?></td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-envelope" style="color: #667eea;"></i> Guardian Email</th>
                                <td><?= htmlspecialchars($student['guardian_email']) ?></td>
                            </tr>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Student record not found.</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="details-card">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <span>Only administrators can view detailed student profile information.</span>
                    </div>
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
        
        // Search functionality
        const searchInput = document.querySelector('.search-box input');
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            console.log('Searching for:', searchTerm);
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + K for search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                searchInput.focus();
            }
            
            // ESC to close sidebar on mobile
            if (e.key === 'Escape' && window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.remove('active');
            }
        });
        
        // Add fade-in animation to cards on load
        window.addEventListener('load', function() {
            const cards = document.querySelectorAll('.dashboard-card, .welcome-section, .details-card');
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