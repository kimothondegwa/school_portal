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

$student_id = $_SESSION['user_id'];
$db = getDB();

// Fetch student info using user_id (include email from users table)
$student = $db->query("SELECT s.*, u.email FROM students s LEFT JOIN users u ON s.user_id = u.user_id WHERE s.user_id = :id")
              ->bind(':id', $student_id)
              ->fetch();

// Get student's actual ID from students table
$student_record_id = $student['student_id'] ?? null;

// Initialize statistics
$totalAssignments = 0;
$submittedAssignments = 0;
$pendingAssignments = 0;
$totalQuizzes = 0;
$completedQuizzes = 0;
$averageGrade = 0;
$attendanceRate = 0;

if ($student_record_id) {
    // Get assignment statistics
    $assignmentStats = $db->query("
        SELECT 
            COUNT(DISTINCT a.assignment_id) as total,
            COUNT(DISTINCT s.submission_id) as submitted
        FROM assignments a
        LEFT JOIN submissions s ON a.assignment_id = s.assignment_id AND s.student_id = :student_id
        WHERE a.is_active = 1
    ")->bind(':student_id', $student_record_id)->fetch();
    
    $totalAssignments = $assignmentStats['total'] ?? 0;
    $submittedAssignments = $assignmentStats['submitted'] ?? 0;
    $pendingAssignments = $totalAssignments - $submittedAssignments;

    // Get quiz statistics
    $quizStats = $db->query("
        SELECT 
            COUNT(DISTINCT q.quiz_id) as total,
            COUNT(DISTINCT qa.attempt_id) as completed
        FROM quizzes q
        LEFT JOIN quiz_attempts qa ON q.quiz_id = qa.quiz_id AND qa.student_id = :student_id
        WHERE q.is_active = 1
    ")->bind(':student_id', $student_record_id)->fetch();
    
    $totalQuizzes = $quizStats['total'] ?? 0;
    $completedQuizzes = $quizStats['completed'] ?? 0;

    // Get average marks
    $gradeData = $db->query("
        SELECT AVG(marks_obtained) as avg_marks
        FROM grades
        WHERE student_id = :student_id
    ")->bind(':student_id', $student_record_id)->fetch();
    
    $averageGrade = round($gradeData['avg_marks'] ?? 0, 1);

    // Get unread notifications count
    $unreadNotifications = getUnreadNotificationCount($student_id);

    // Get attendance rate
    $attendanceData = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
        FROM attendance
        WHERE student_id = :student_id
    ")->bind(':student_id', $student_record_id)->fetch();
    
    if ($attendanceData['total'] > 0) {
        $attendanceRate = round(($attendanceData['present'] / $attendanceData['total']) * 100, 1);
    }

    // Get recent submissions
    $recentSubmissions = $db->query("
        SELECT s.*, a.title as assignment_title, sub.subject_name, s.submitted_at
        FROM submissions s
        INNER JOIN assignments a ON s.assignment_id = a.assignment_id
        INNER JOIN subjects sub ON a.subject_id = sub.subject_id
        WHERE s.student_id = :student_id
        ORDER BY s.submitted_at DESC
        LIMIT 5
    ")->bind(':student_id', $student_record_id)->fetchAll();

    // Get recent grades (use assignment title and marks_obtained)
    $recentGrades = $db->query("
        SELECT g.*, a.title AS assignment_title, g.marks_obtained, g.graded_at
        FROM grades g
        LEFT JOIN assignments a ON g.assignment_id = a.assignment_id
        WHERE g.student_id = :student_id
        ORDER BY g.graded_at DESC
        LIMIT 5
    ")->bind(':student_id', $student_record_id)->fetchAll();

    // Get upcoming assignments
    $upcomingAssignments = $db->query("
        SELECT a.*, sub.subject_name, 
               CONCAT(t.first_name, ' ', t.last_name) as teacher_name
        FROM assignments a
        INNER JOIN subjects sub ON a.subject_id = sub.subject_id
        INNER JOIN teachers t ON a.teacher_id = t.teacher_id
        LEFT JOIN submissions s ON a.assignment_id = s.assignment_id AND s.student_id = :student_id
        WHERE a.is_active = 1 
        AND a.due_date >= CURDATE()
        AND s.submission_id IS NULL
        ORDER BY a.due_date ASC
        LIMIT 5
    ")->bind(':student_id', $student_record_id)->fetchAll();
}
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

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

@keyframes pulse {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(231, 74, 59, 0.7);
    }
    50% {
        box-shadow: 0 0 0 6px rgba(231, 74, 59, 0);
    }
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
.stat-card.warning::before { background: var(--warning-gradient); }
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
.stat-card.warning .stat-icon { background: var(--warning-gradient); }
.stat-card.info .stat-icon { background: var(--info-gradient); }

.stat-card h6 {
    margin: 0;
    color: #858796;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.stat-card .number {
    font-size: 2rem;
    font-weight: 700;
    color: #5a5c69;
    margin: 0.5rem 0;
}

.stat-card .subtitle {
    font-size: 0.85rem;
    color: #858796;
}

/* Content Cards */
.content-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    margin-bottom: 2rem;
}

.card-header-custom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f8f9fc;
}

.card-header-custom h5 {
    margin: 0;
    font-weight: 600;
    color: #5a5c69;
    font-size: 1.2rem;
}

.card-header-custom .badge {
    padding: 0.5rem 1rem;
    border-radius: 50px;
}

/* Table Styles */
.custom-table {
    width: 100%;
}

.custom-table thead {
    background: #f8f9fc;
}

.custom-table th {
    padding: 1rem;
    font-weight: 600;
    color: #5a5c69;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
}

.custom-table td {
    padding: 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #f8f9fc;
}

.custom-table tbody tr {
    transition: all 0.3s ease;
}

.custom-table tbody tr:hover {
    background: #f8f9fc;
}

/* Chart Container */
.chart-container {
    position: relative;
    height: 300px;
}

/* Progress Circles */
.progress-circle {
    width: 120px;
    height: 120px;
    margin: 0 auto;
    position: relative;
}

.progress-circle svg {
    transform: rotate(-90deg);
}

.progress-circle circle {
    fill: none;
    stroke-width: 10;
}

.progress-circle .bg {
    stroke: #e3e6f0;
}

.progress-circle .progress {
    stroke-linecap: round;
    transition: stroke-dashoffset 1s ease;
}

.progress-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 1.5rem;
    font-weight: 700;
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
    
    .search-box input {
        width: 200px;
    }
    
    .search-box input:focus {
        width: 250px;
    }
    
    .user-info {
        display: none;
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
</style>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
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
                    <a href="comment.php" style="color: inherit; text-decoration: none;">
                        <i class="fas fa-bell"></i>
                        <?php if ($unreadNotifications > 0): ?>
                            <span class="notification-badge" style="background: #ff4444; animation: pulse 1.5s infinite;">
                                <?= $unreadNotifications > 9 ? '9+' : $unreadNotifications ?>
                            </span>
                        <?php endif; ?>
                    </a>
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
                    <p>Here's your academic overview for today</p>
                </div>
                <div class="welcome-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-row">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h6>Total Assignments</h6>
                    <div class="number"><?= $totalAssignments ?></div>
                    <div class="subtitle"><?= $pendingAssignments ?> pending</div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h6>Submitted</h6>
                    <div class="number"><?= $submittedAssignments ?></div>
                    <div class="subtitle">Completed assignments</div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-brain"></i>
                    </div>
                    <h6>Quizzes Completed</h6>
                    <div class="number"><?= $completedQuizzes ?></div>
                    <div class="subtitle">Out of <?= $totalQuizzes ?> total</div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h6>Average Grade</h6>
                    <div class="number"><?= $averageGrade ?>%</div>
                    <div class="subtitle">Overall performance</div>
                </div>
            </div>
            
            <!-- Charts and Tables Row -->
            <div class="row">
                <!-- Performance Chart -->
                <div class="col-lg-8">
                    <div class="content-card">
                        <div class="card-header-custom">
                            <h5><i class="fas fa-chart-area me-2"></i>Academic Performance</h5>
                            <select class="form-select" style="width: auto;">
                                <option>Last 7 Days</option>
                                <option>Last 30 Days</option>
                                <option>Last 3 Months</option>
                            </select>
                        </div>
                        <div class="chart-container">
                            <canvas id="performanceChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Attendance Circle -->
                <div class="col-lg-4">
                    <div class="content-card">
                        <div class="card-header-custom">
                            <h5><i class="fas fa-calendar-check me-2"></i>Attendance</h5>
                        </div>
                        <div class="progress-circle">
                            <svg width="120" height="120">
                                <circle class="bg" cx="60" cy="60" r="50"></circle>
                                <circle class="progress" cx="60" cy="60" r="50" 
                                        stroke="url(#gradient)" 
                                        stroke-dasharray="314" 
                                        stroke-dashoffset="<?= 314 - (314 * $attendanceRate / 100) ?>"></circle>
                                <defs>
                                    <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" style="stop-color:#667eea;stop-opacity:1" />
                                        <stop offset="100%" style="stop-color:#764ba2;stop-opacity:1" />
                                    </linearGradient>
                                </defs>
                            </svg>
                            <div class="progress-text"><?= $attendanceRate ?>%</div>
                        </div>
                        <div class="text-center mt-3">
                            <h6 class="text-muted">Attendance Rate</h6>
                            <p class="text-muted small mb-0">Keep up the good work!</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Upcoming Assignments and Recent Grades -->
            <div class="row">
                <!-- Upcoming Assignments -->
                <div class="col-lg-6">
                    <div class="content-card">
                        <div class="card-header-custom">
                            <h5><i class="fas fa-clock me-2"></i>Upcoming Assignments</h5>
                            <span class="badge bg-warning"><?= count($upcomingAssignments) ?> Due</span>
                        </div>
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Assignment</th>
                                    <th>Subject</th>
                                    <th>Due Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($upcomingAssignments)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">No upcoming assignments</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($upcomingAssignments as $assignment): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?= htmlspecialchars($assignment['title']) ?></strong>
                                                    <br>
                                                    <small class="text-muted">by <?= htmlspecialchars($assignment['teacher_name']) ?></small>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($assignment['subject_name']) ?></td>
                                            <td>
                                                <span class="badge <?= strtotime($assignment['due_date']) < time() ? 'bg-danger' : 'bg-success' ?>">
                                                    <?= date('M d, Y', strtotime($assignment['due_date'])) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Recent Grades -->
                <div class="col-lg-6">
                    <div class="content-card">
                        <div class="card-header-custom">
                            <h5><i class="fas fa-star me-2"></i>Recent Grades</h5>
                            <span class="badge bg-primary"><?= count($recentGrades) ?> New</span>
                        </div>
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Grade</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentGrades)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">No grades available yet</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentGrades as $grade): 
                                        // Normalize marks and determine badge color safely
                                        $marks = isset($grade['marks_obtained']) && $grade['marks_obtained'] !== null ? (float)$grade['marks_obtained'] : null;
                                        $letter = $grade['grade'] ?? null;
                                        if ($marks !== null) {
                                            $bg = $marks >= 70 ? '#11998e' : ($marks >= 50 ? '#f093fb' : '#fa709a');
                                        } else {
                                            // fallback color when numeric marks not available
                                            $bg = '#6c757d';
                                        }
                                    ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($grade['assignment_title'] ?? ($grade['subject_name'] ?? '—')) ?></strong></td>
                                            <td>
                                                <span class="badge" style="background: <?= $bg ?>">
                                                    <?= htmlspecialchars($marks !== null ? $marks . '%' : ($letter ?? '—')) ?>
                                                </span>
                                            </td>
                                            <td><?= isset($grade['graded_at']) ? date('M d, Y', strtotime($grade['graded_at'])) : '—' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Recent Submissions -->
            <div class="content-card">
                <div class="card-header-custom">
                    <h5><i class="fas fa-clipboard-check me-2"></i>Recent Submissions</h5>
                    <span class="badge bg-info"><?= count($recentSubmissions) ?> Total</span>
                </div>
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Assignment</th>
                            <th>Subject</th>
                            <th>Submitted On</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentSubmissions)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">No submissions yet</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentSubmissions as $submission): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($submission['assignment_title']) ?></strong></td>
                                    <td><?= htmlspecialchars($submission['subject_name']) ?></td>
                                    <td><?= date('M d, Y g:i A', strtotime($submission['submitted_at'])) ?></td>
                                    <td>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle"></i> Submitted
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($student): ?>
                <!-- Student Details Card: show limited info to students (full profile available to admin elsewhere) -->
                <div class="content-card">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-id-card me-2"></i>My Profile Information</h5>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <table class="custom-table">
                                <tr>
                                    <th style="width: 40%"><i class="fas fa-hashtag" style="color: #667eea;"></i> Admission Number</th>
                                    <td><?= htmlspecialchars($student['admission_number'] ?? '—') ?></td>
                                </tr>
                                <tr>
                                    <th><i class="fas fa-school" style="color: #667eea;"></i> Class Level</th>
                                    <td><?= htmlspecialchars($student['class_level'] ?? '—') ?></td>
                                </tr>
                                <tr>
                                    <th><i class="fas fa-envelope" style="color: #667eea;"></i> Email</th>
                                    <td><?= htmlspecialchars($student['email'] ?? '—') ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <p class="text-muted">Other personal details (date of birth, phone, address, guardian details) are restricted and can only be viewed by administrators.</p>
                        </div>
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
        
        // Performance Chart
        const ctx = document.getElementById('performanceChart').getContext('2d');
        const gradient1 = ctx.createLinearGradient(0, 0, 0, 300);
        gradient1.addColorStop(0, 'rgba(102, 126, 234, 0.4)');
        gradient1.addColorStop(1, 'rgba(118, 75, 162, 0.1)');
        
        const gradient2 = ctx.createLinearGradient(0, 0, 0, 300);
        gradient2.addColorStop(0, 'rgba(17, 153, 142, 0.4)');
        gradient2.addColorStop(1, 'rgba(56, 239, 125, 0.1)');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6'],
                datasets: [{
                    label: 'Assignments',
                    data: [<?= $submittedAssignments > 0 ? min($submittedAssignments, 12) : 8 ?>, 
                           <?= $submittedAssignments > 0 ? min($submittedAssignments + 2, 15) : 12 ?>, 
                           <?= $submittedAssignments > 0 ? min($submittedAssignments + 1, 14) : 10 ?>, 
                           <?= $submittedAssignments > 0 ? min($submittedAssignments + 3, 18) : 15 ?>, 
                           <?= $submittedAssignments > 0 ? min($submittedAssignments + 2, 17) : 14 ?>, 
                           <?= $submittedAssignments ?>],
                    borderColor: '#667eea',
                    backgroundColor: gradient1,
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }, {
                    label: 'Average Grade',
                    data: [<?= max($averageGrade - 10, 60) ?>, 
                           <?= max($averageGrade - 5, 65) ?>, 
                           <?= max($averageGrade - 8, 62) ?>, 
                           <?= max($averageGrade - 3, 70) ?>, 
                           <?= max($averageGrade - 2, 72) ?>, 
                           <?= $averageGrade ?>],
                    borderColor: '#11998e',
                    backgroundColor: gradient2,
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#11998e',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: {
                                size: 13,
                                weight: '600'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: {
                            size: 14,
                            weight: '600'
                        },
                        bodyFont: {
                            size: 13
                        },
                        borderColor: '#667eea',
                        borderWidth: 1,
                        displayColors: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            padding: 10,
                            font: {
                                size: 12
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            padding: 10,
                            font: {
                                size: 12
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
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
        
        // Number animation on scroll
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
            const cards = document.querySelectorAll('.stat-card, .content-card, .welcome-section');
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