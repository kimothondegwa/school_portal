<?php
// ====================================================
// FILE: teacher/dashboard.php
// Enhanced Teacher Dashboard with Statistics & Graphs
// ====================================================

define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();

if (!isLoggedIn() || !isTeacher()) {
    header('Location: ../login.php?error=Access+Denied');
    exit;
}

$user = getCurrentUser();
$db = getDB()->getConnection();

// Get unread notifications
$user_id = $user['user_id'];
$unreadNotifications = getUnreadNotificationCount($user_id);

// ✅ Get teacher_id
$stmt = $db->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
$stmt->execute([$user['user_id']]);
$teacher_data = $stmt->fetch(PDO::FETCH_ASSOC);
$teacher_id = $teacher_data['teacher_id'] ?? null;

// ✅ Fetch Statistics
$stats = [
    'total_students' => 0,
    'total_assignments' => 0,
    'pending_submissions' => 0,
    'total_subjects' => 0,
    'unread_messages' => 0,
    'today_classes' => 0,
    'active_quizzes' => 0,
    'avg_attendance' => 0,
    'graded_submissions' => 0,
    'total_submissions' => 0
];

try {
    // Total students in teacher's subjects
    $stmt = $db->prepare("SELECT COUNT(DISTINCT e.student_id) as count 
                          FROM enrollments e 
                          JOIN subjects s ON e.subject_id = s.subject_id 
                          WHERE s.teacher_id = ?");
    $stmt->execute([$teacher_id]);
    $stats['total_students'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Total assignments created
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM assignments WHERE teacher_id = ?");
    $stmt->execute([$teacher_id]);
    $stats['total_assignments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Pending submissions
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM assignment_submissions 
                          WHERE assignment_id IN (SELECT assignment_id FROM assignments WHERE teacher_id = ?) 
                          AND status = 'pending'");
    $stmt->execute([$teacher_id]);
    $stats['pending_submissions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Graded submissions
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM assignment_submissions 
                          WHERE assignment_id IN (SELECT assignment_id FROM assignments WHERE teacher_id = ?) 
                          AND status = 'graded'");
    $stmt->execute([$teacher_id]);
    $stats['graded_submissions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Total submissions
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM assignment_submissions 
                          WHERE assignment_id IN (SELECT assignment_id FROM assignments WHERE teacher_id = ?)");
    $stmt->execute([$teacher_id]);
    $stats['total_submissions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Total subjects
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM subjects WHERE teacher_id = ? AND is_active = 1");
    $stmt->execute([$teacher_id]);
    $stats['total_subjects'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Unread messages
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM messages WHERE recipient_id = ? AND is_read = 0");
    $stmt->execute([$user['user_id']]);
    $stats['unread_messages'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Today's classes
    $today = date('l');
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM timetables WHERE teacher_id = ? AND day_of_week = ?");
    $stmt->execute([$teacher_id, $today]);
    $stats['today_classes'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Active quizzes
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM quizzes WHERE teacher_id = ? AND status = 'active'");
    $stmt->execute([$teacher_id]);
    $stats['active_quizzes'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Average attendance rate
    $stmt = $db->prepare("SELECT AVG(CASE WHEN status = 'present' THEN 100 ELSE 0 END) as avg_rate 
                          FROM attendance 
                          WHERE subject_id IN (SELECT subject_id FROM subjects WHERE teacher_id = ?)");
    $stmt->execute([$teacher_id]);
    $stats['avg_attendance'] = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_rate'] ?? 0);

} catch (PDOException $e) {
    // Keep default zeros
}

// ✅ Data for Charts

// Attendance Chart Data (Last 7 days)
$attendanceData = ['labels' => [], 'present' => [], 'absent' => [], 'late' => []];
try {
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $attendanceData['labels'][] = date('D', strtotime($date));
        
        $stmt = $db->prepare("SELECT 
                              SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                              SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                              SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
                              FROM attendance 
                              WHERE subject_id IN (SELECT subject_id FROM subjects WHERE teacher_id = ?)
                              AND DATE(attendance_date) = ?");
        $stmt->execute([$teacher_id, $date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $attendanceData['present'][] = (int)($result['present'] ?? 0);
        $attendanceData['absent'][] = (int)($result['absent'] ?? 0);
        $attendanceData['late'][] = (int)($result['late'] ?? 0);
    }
} catch (PDOException $e) {}

// Assignment Submission Status
$submissionStatus = ['pending' => 0, 'submitted' => 0, 'graded' => 0, 'late' => 0];
try {
    $stmt = $db->prepare("SELECT status, COUNT(*) as count 
                          FROM assignment_submissions 
                          WHERE assignment_id IN (SELECT assignment_id FROM assignments WHERE teacher_id = ?)
                          GROUP BY status");
    $stmt->execute([$teacher_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $submissionStatus[$row['status']] = (int)$row['count'];
    }
} catch (PDOException $e) {}

// Subject-wise Student Distribution
$subjectDistribution = ['labels' => [], 'data' => []];
try {
    $stmt = $db->prepare("SELECT s.subject_name, COUNT(e.student_id) as count 
                          FROM subjects s
                          LEFT JOIN enrollments e ON s.subject_id = e.subject_id
                          WHERE s.teacher_id = ?
                          GROUP BY s.subject_id
                          LIMIT 6");
    $stmt->execute([$teacher_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $subjectDistribution['labels'][] = $row['subject_name'];
        $subjectDistribution['data'][] = (int)$row['count'];
    }
} catch (PDOException $e) {}

// Grade Distribution
$gradeDistribution = ['labels' => ['A', 'B', 'C', 'D', 'F'], 'data' => [0, 0, 0, 0, 0]];
try {
    $stmt = $db->prepare("SELECT 
                          SUM(CASE WHEN marks >= 90 THEN 1 ELSE 0 END) as a_grade,
                          SUM(CASE WHEN marks >= 80 AND marks < 90 THEN 1 ELSE 0 END) as b_grade,
                          SUM(CASE WHEN marks >= 70 AND marks < 80 THEN 1 ELSE 0 END) as c_grade,
                          SUM(CASE WHEN marks >= 60 AND marks < 70 THEN 1 ELSE 0 END) as d_grade,
                          SUM(CASE WHEN marks < 60 THEN 1 ELSE 0 END) as f_grade
                          FROM assignment_submissions 
                          WHERE assignment_id IN (SELECT assignment_id FROM assignments WHERE teacher_id = ?)
                          AND marks IS NOT NULL");
    $stmt->execute([$teacher_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $gradeDistribution['data'] = [
            (int)($result['a_grade'] ?? 0),
            (int)($result['b_grade'] ?? 0),
            (int)($result['c_grade'] ?? 0),
            (int)($result['d_grade'] ?? 0),
            (int)($result['f_grade'] ?? 0)
        ];
    }
} catch (PDOException $e) {}

// ✅ Recent Assignments
$recent_assignments = [];
try {
    $stmt = $db->prepare("SELECT a.assignment_id, a.title, s.subject_name, a.due_date,
                          COUNT(asub.submission_id) as submissions
                          FROM assignments a
                          JOIN subjects s ON a.subject_id = s.subject_id
                          LEFT JOIN assignment_submissions asub ON a.assignment_id = asub.assignment_id
                          WHERE a.teacher_id = ?
                          GROUP BY a.assignment_id
                          ORDER BY a.created_at DESC
                          LIMIT 5");
    $stmt->execute([$teacher_id]);
    $recent_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ✅ Today's Schedule
$todays_schedule = [];
try {
    $today = date('l');
    $stmt = $db->prepare("SELECT t.*, s.subject_name 
                          FROM timetables t
                          JOIN subjects s ON t.subject_id = s.subject_id
                          WHERE t.teacher_id = ? AND t.day_of_week = ?
                          ORDER BY t.start_time");
    $stmt->execute([$teacher_id, $today]);
    $todays_schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ✅ Recent Messages
$recent_messages = [];
try {
    $stmt = $db->prepare("SELECT m.*, u.username as sender_name 
                          FROM messages m
                          JOIN users u ON m.sender_id = u.user_id
                          WHERE m.recipient_id = ?
                          ORDER BY m.sent_at DESC
                          LIMIT 3");
    $stmt->execute([$user['user_id']]);
    $recent_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Convert data to JSON for JavaScript
$attendanceDataJson = json_encode($attendanceData);
$submissionStatusJson = json_encode($submissionStatus);
$subjectDistributionJson = json_encode($subjectDistribution);
$gradeDistributionJson = json_encode($gradeDistribution);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teacher Dashboard - Online School Portal</title>

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
    background: var(--primary-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    color: white;
    animation: float 3s ease-in-out infinite;
}

/* Stats Grid */
.stats-grid {
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
    display: flex;
    align-items: center;
    gap: 1.2rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
}

.stat-card.students::before { background: var(--primary-gradient); }
.stat-card.assignments::before { background: var(--info-gradient); }
.stat-card.pending::before { background: var(--warning-gradient); }
.stat-card.subjects::before { background: var(--success-gradient); }
.stat-card.messages::before { background: var(--danger-gradient); }
.stat-card.classes::before { background: var(--info-gradient); }
.stat-card.quizzes::before { background: var(--success-gradient); }
.stat-card.attendance::before { background: var(--primary-gradient); }

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
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

.stat-card.students .stat-icon { background: var(--primary-gradient); }
.stat-card.assignments .stat-icon { background: var(--info-gradient); }
.stat-card.pending .stat-icon { background: var(--warning-gradient); }
.stat-card.subjects .stat-icon { background: var(--success-gradient); }
.stat-card.messages .stat-icon { background: var(--danger-gradient); }
.stat-card.classes .stat-icon { background: var(--info-gradient); }
.stat-card.quizzes .stat-icon { background: var(--success-gradient); }
.stat-card.attendance .stat-icon { background: var(--primary-gradient); }

.stat-details h4 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: #5a5c69;
}

.stat-details p {
    margin: 0.3rem 0 0 0;
    font-size: 0.85rem;
    color: #858796;
    font-weight: 500;
}

/* Chart Cards */
.chart-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    margin-bottom: 2rem;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f8f9fc;
}

.chart-header h5 {
    margin: 0;
    font-weight: 600;
    color: #5a5c69;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.chart-header i {
    color: #667eea;
}

.chart-container {
    position: relative;
    height: 300px;
}

/* Charts Grid */
.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 2rem;
    margin-bottom: 2rem;
}

/* Activity Sections */
.activity-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}

.activity-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
}

.activity-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f0f0f0;
}

.activity-header h3 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 700;
    color: #5a5c69;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.activity-header i {
    color: #667eea;
}

.view-all {
    font-size: 0.85rem;
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.3s ease;
}

.view-all:hover {
    color: #764ba2;
}

.activity-item {
    padding: 1rem;
    margin-bottom: 0.8rem;
    background: linear-gradient(135deg, #f8f9fc 0%, #ffffff 100%);
    border-radius: 10px;
    border-left: 3px solid #667eea;
    transition: all 0.3s ease;
}

.activity-item:hover {
    transform: translateX(5px);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.activity-item h5 {
    margin: 0 0 0.3rem 0;
    font-size: 0.95rem;
    font-weight: 600;
    color: #5a5c69;
}

.activity-item p {
    margin: 0;
    font-size: 0.8rem;
    color: #858796;
}

.activity-item .badge {
    display: inline-block;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-top: 0.5rem;
}

.badge-primary { background: #e3e6f0; color: #667eea; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-success { background: #d4edda; color: #155724; }

.empty-state {
    text-align: center;
    padding: 2rem;
    color: #858796;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.3;
}

/* Schedule Item */
.schedule-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    margin-bottom: 0.8rem;
    background: linear-gradient(135deg, #f8f9fc 0%, #ffffff 100%);
    border-radius: 10px;
    border-left: 3px solid #667eea;
}

.schedule-time {
    background: var(--info-gradient);
    color: white;
    padding: 0.5rem 0.8rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    min-width: 80px;
    text-align: center;
}

.schedule-details h5 {
    margin: 0 0 0.2rem 0;
    font-size: 1rem;
    font-weight: 600;
    color: #5a5c69;
}

.schedule-details p {
    margin: 0;
    font-size: 0.8rem;
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
    
    .search-box input {
        width: 150px;
    }
    
    .search-box input:focus {
        width: 200px;
    }
    
    .user-info {
        display: none;
    }
    
    .mobile-toggle {
        display: block;
    }
    
    .stats-grid,
    .charts-grid {
        grid-template-columns: 1fr;
    }
    
    .activity-grid {
        grid-template-columns: 1fr;
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
                <h2>Teacher Dashboard</h2>
            </div>
            
            <div class="topbar-right">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search...">
                </div>
                
                <?php echo getNotificationBadgeHTML($user_id, 'comment_students.php'); ?>
                
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
            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="welcome-content">
                    <h3>Welcome back, <?= htmlspecialchars($user['username'] ?? 'Teacher') ?>! 👋</h3>
                    <p>You have <?= $stats['today_classes'] ?> class<?= $stats['today_classes'] != 1 ? 'es' : '' ?> scheduled today. Keep up the great work!</p>
                </div>
                <div class="welcome-icon">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
            </div>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card students">
                    <div class="stat-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-details">
                        <h4><?= $stats['total_students'] ?></h4>
                        <p>Total Students</p>
                    </div>
                </div>

                <div class="stat-card assignments">
                    <div class="stat-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-details">
                        <h4><?= $stats['total_assignments'] ?></h4>
                        <p>Total Assignments</p>
                    </div>
                </div>

                <div class="stat-card pending">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <h4><?= $stats['pending_submissions'] ?></h4>
                        <p>Pending Reviews</p>
                    </div>
                </div>

                <div class="stat-card subjects">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-details">
                        <h4><?= $stats['total_subjects'] ?></h4>
                        <p>Active Subjects</p>
                    </div>
                </div>

                <div class="stat-card messages">
                    <div class="stat-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-details">
                        <h4><?= $stats['unread_messages'] ?></h4>
                        <p>Unread Messages</p>
                    </div>
                </div>

                <div class="stat-card classes">
                    <div class="stat-icon">
                        <i class="fas fa-chalkboard"></i>
                    </div>
                    <div class="stat-details">
                        <h4><?= $stats['today_classes'] ?></h4>
                        <p>Today's Classes</p>
                    </div>
                </div>

                <div class="stat-card quizzes">
                    <div class="stat-icon">
                        <i class="fas fa-brain"></i>
                    </div>
                    <div class="stat-details">
                        <h4><?= $stats['active_quizzes'] ?></h4>
                        <p>Active Quizzes</p>
                    </div>
                </div>

                <div class="stat-card attendance">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-details">
                        <h4><?= $stats['avg_attendance'] ?>%</h4>
                        <p>Avg Attendance</p>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-grid">
                <!-- Attendance Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h5><i class="fas fa-chart-line"></i> Weekly Attendance Trend</h5>
                    </div>
                    <div class="chart-container">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>

                <!-- Submission Status Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h5><i class="fas fa-chart-pie"></i> Assignment Submission Status</h5>
                    </div>
                    <div class="chart-container">
                        <canvas id="submissionChart"></canvas>
                    </div>
                </div>

                <!-- Subject Distribution Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h5><i class="fas fa-chart-bar"></i> Students per Subject</h5>
                    </div>
                    <div class="chart-container">
                        <canvas id="subjectChart"></canvas>
                    </div>
                </div>

                <!-- Grade Distribution Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h5><i class="fas fa-chart-area"></i> Grade Distribution</h5>
                    </div>
                    <div class="chart-container">
                        <canvas id="gradeChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Activity Sections -->
            <div class="activity-grid">
                <!-- Recent Assignments -->
                <div class="activity-card">
                    <div class="activity-header">
                        <h3><i class="fas fa-tasks"></i> Recent Assignments</h3>
                        <a href="upload_assignment.php" class="view-all">View All →</a>
                    </div>
                    <?php if (!empty($recent_assignments)): ?>
                        <?php foreach ($recent_assignments as $assignment): ?>
                            <div class="activity-item">
                                <h5><?= htmlspecialchars($assignment['title']) ?></h5>
                                <p><?= htmlspecialchars($assignment['subject_name']) ?></p>
                                <span class="badge badge-primary">
                                    <?= $assignment['submissions'] ?> Submissions
                                </span>
                                <span class="badge <?= strtotime($assignment['due_date']) < time() ? 'badge-warning' : 'badge-success' ?>">
                                    Due: <?= date('M d', strtotime($assignment['due_date'])) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-clipboard"></i>
                            <p>No assignments yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Today's Schedule -->
                <div class="activity-card">
                    <div class="activity-header">
                        <h3><i class="fas fa-calendar-day"></i> Today's Schedule</h3>
                        <a href="view_timetable.php" class="view-all">Full Timetable →</a>
                    </div>
                    <?php if (!empty($todays_schedule)): ?>
                        <?php foreach ($todays_schedule as $class): ?>
                            <div class="schedule-item">
                                <div class="schedule-time">
                                    <?= date('g:i A', strtotime($class['start_time'])) ?>
                                </div>
                                <div class="schedule-details">
                                    <h5><?= htmlspecialchars($class['subject_name']) ?></h5>
                                    <p>
                                        <?= htmlspecialchars($class['class_level']) ?> • 
                                        Room <?= htmlspecialchars($class['room_number']) ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <p>No classes scheduled for today</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Messages -->
                <div class="activity-card">
                    <div class="activity-header">
                        <h3><i class="fas fa-envelope"></i> Recent Messages</h3>
                        <a href="message.php" class="view-all">View All →</a>
                    </div>
                    <?php if (!empty($recent_messages)): ?>
                        <?php foreach ($recent_messages as $msg): ?>
                            <div class="activity-item">
                                <h5><?= htmlspecialchars($msg['sender_name']) ?></h5>
                                <p><?= htmlspecialchars(substr($msg['message_body'], 0, 80)) ?>...</p>
                                <span class="badge badge-primary">
                                    <?= date('M d, g:i A', strtotime($msg['sent_at'])) ?>
                                </span>
                                <?php if (!$msg['is_read']): ?>
                                    <span class="badge badge-warning">New</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No messages yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Data from PHP
        const attendanceData = <?= $attendanceDataJson ?>;
        const submissionStatus = <?= $submissionStatusJson ?>;
        const subjectDistribution = <?= $subjectDistributionJson ?>;
        const gradeDistribution = <?= $gradeDistributionJson ?>;

        // Chart.js Default Settings
        Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
        Chart.defaults.color = '#858796';

        // Attendance Chart (Area Chart with Gradient)
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        
        // Create gradients
        const gradientPresent = attendanceCtx.createLinearGradient(0, 0, 0, 300);
        gradientPresent.addColorStop(0, 'rgba(17, 153, 142, 0.8)');
        gradientPresent.addColorStop(1, 'rgba(17, 153, 142, 0.05)');
        
        const gradientAbsent = attendanceCtx.createLinearGradient(0, 0, 0, 300);
        gradientAbsent.addColorStop(0, 'rgba(231, 74, 59, 0.8)');
        gradientAbsent.addColorStop(1, 'rgba(231, 74, 59, 0.05)');
        
        const gradientLate = attendanceCtx.createLinearGradient(0, 0, 0, 300);
        gradientLate.addColorStop(0, 'rgba(246, 194, 62, 0.8)');
        gradientLate.addColorStop(1, 'rgba(246, 194, 62, 0.05)');
        
        new Chart(attendanceCtx, {
            type: 'line',
            data: {
                labels: attendanceData.labels,
                datasets: [
                    {
                        label: 'Present',
                        data: attendanceData.present,
                        borderColor: '#11998e',
                        backgroundColor: gradientPresent,
                        borderWidth: 4,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#11998e',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 3,
                        pointRadius: 6,
                        pointHoverRadius: 9,
                        pointHoverBackgroundColor: '#11998e',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 3
                    },
                    {
                        label: 'Absent',
                        data: attendanceData.absent,
                        borderColor: '#e74a3b',
                        backgroundColor: gradientAbsent,
                        borderWidth: 4,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#e74a3b',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 3,
                        pointRadius: 6,
                        pointHoverRadius: 9,
                        pointHoverBackgroundColor: '#e74a3b',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 3
                    },
                    {
                        label: 'Late',
                        data: attendanceData.late,
                        borderColor: '#f6c23e',
                        backgroundColor: gradientLate,
                        borderWidth: 4,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#f6c23e',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 3,
                        pointRadius: 6,
                        pointHoverRadius: 9,
                        pointHoverBackgroundColor: '#f6c23e',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: { size: 13, weight: '600', family: "'Segoe UI', sans-serif" },
                            color: '#5a5c69'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.9)',
                        padding: 16,
                        titleFont: { size: 15, weight: '700' },
                        bodyFont: { size: 14 },
                        borderColor: '#667eea',
                        borderWidth: 2,
                        displayColors: true,
                        boxWidth: 12,
                        boxHeight: 12,
                        usePointStyle: true,
                        callbacks: {
                            title: function(context) {
                                return 'Day: ' + context[0].label;
                            },
                            label: function(context) {
                                return ' ' + context.dataset.label + ': ' + context.parsed.y + ' students';
                            }
                        }
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
                            padding: 12, 
                            font: { size: 12, weight: '500' },
                            color: '#858796',
                            stepSize: 5
                        },
                        title: {
                            display: true,
                            text: 'Number of Students',
                            font: { size: 13, weight: '600' },
                            color: '#5a5c69',
                            padding: { top: 10 }
                        }
                    },
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: { 
                            padding: 12, 
                            font: { size: 12, weight: '600' },
                            color: '#5a5c69'
                        }
                    }
                }
            }
        });

        // Submission Status Chart (Animated Doughnut Chart)
        const submissionCtx = document.getElementById('submissionChart').getContext('2d');
        new Chart(submissionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Submitted', 'Graded', 'Late'],
                datasets: [{
                    data: [
                        submissionStatus.pending || 0,
                        submissionStatus.submitted || 0,
                        submissionStatus.graded || 0,
                        submissionStatus.late || 0
                    ],
                    backgroundColor: [
                        'rgba(246, 194, 62, 0.9)',
                        'rgba(78, 115, 223, 0.9)',
                        'rgba(28, 200, 138, 0.9)',
                        'rgba(231, 74, 59, 0.9)'
                    ],
                    borderColor: '#ffffff',
                    borderWidth: 4,
                    hoverOffset: 15,
                    hoverBorderColor: '#ffffff',
                    hoverBorderWidth: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                animation: {
                    animateScale: true,
                    animateRotate: true
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            font: { size: 13, weight: '600', family: "'Segoe UI', sans-serif" },
                            usePointStyle: true,
                            pointStyle: 'circle',
                            color: '#5a5c69'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.9)',
                        padding: 16,
                        titleFont: { size: 15, weight: '700' },
                        bodyFont: { size: 14 },
                        borderColor: '#667eea',
                        borderWidth: 2,
                        displayColors: true,
                        boxWidth: 15,
                        boxHeight: 15,
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                return ' ' + context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            },
                            afterLabel: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                if (total > 0) {
                                    return 'Total Submissions: ' + total;
                                }
                                return '';
                            }
                        }
                    }
                }
            }
        });

        // Subject Distribution Chart (Horizontal Bar Chart with Gradient)
        const subjectCtx = document.getElementById('subjectChart').getContext('2d');
        
        // Create gradient colors for each bar
        const createGradient = (ctx, color1, color2) => {
            const gradient = ctx.createLinearGradient(0, 0, 400, 0);
            gradient.addColorStop(0, color1);
            gradient.addColorStop(1, color2);
            return gradient;
        };
        
        const colors = [
            ['rgba(102, 126, 234, 0.9)', 'rgba(118, 75, 162, 0.9)'],
            ['rgba(17, 153, 142, 0.9)', 'rgba(56, 239, 125, 0.9)'],
            ['rgba(240, 147, 251, 0.9)', 'rgba(245, 87, 108, 0.9)'],
            ['rgba(79, 172, 254, 0.9)', 'rgba(0, 242, 254, 0.9)'],
            ['rgba(250, 112, 154, 0.9)', 'rgba(254, 225, 64, 0.9)'],
            ['rgba(255, 121, 63, 0.9)', 'rgba(255, 184, 34, 0.9)']
        ];
        
        const backgroundColors = subjectDistribution.data.map((_, index) => 
            createGradient(subjectCtx, colors[index % colors.length][0], colors[index % colors.length][1])
        );
        
        new Chart(subjectCtx, {
            type: 'bar',
            data: {
                labels: subjectDistribution.labels,
                datasets: [{
                    label: 'Number of Students',
                    data: subjectDistribution.data,
                    backgroundColor: backgroundColors,
                    borderColor: 'rgba(255, 255, 255, 0.8)',
                    borderWidth: 2,
                    borderRadius: 12,
                    borderSkipped: false,
                    barThickness: 'flex',
                    maxBarThickness: 50
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.9)',
                        padding: 16,
                        titleFont: { size: 15, weight: '700' },
                        bodyFont: { size: 14 },
                        borderColor: '#667eea',
                        borderWidth: 2,
                        callbacks: {
                            label: function(context) {
                                return ' Students Enrolled: ' + context.parsed.x;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { 
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            padding: 10,
                            font: { size: 12, weight: '600' },
                            color: '#5a5c69',
                            stepSize: 5
                        },
                        title: {
                            display: true,
                            text: 'Number of Students',
                            font: { size: 13, weight: '600' },
                            color: '#5a5c69',
                            padding: { top: 10 }
                        }
                    },
                    y: {
                        grid: { display: false },
                        ticks: {
                            padding: 12,
                            font: { size: 12, weight: '600' },
                            color: '#5a5c69'
                        }
                    }
                }
            }
        });

        // Grade Distribution Chart (Radar Chart with Animation)
        const gradeCtx = document.getElementById('gradeChart').getContext('2d');
        new Chart(gradeCtx, {
            type: 'radar',
            data: {
                labels: gradeDistribution.labels,
                datasets: [{
                    label: 'Grade Distribution',
                    data: gradeDistribution.data,
                    backgroundColor: 'rgba(102, 126, 234, 0.2)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 3,
                    pointBackgroundColor: [
                        'rgba(28, 200, 138, 1)',
                        'rgba(54, 185, 204, 1)',
                        'rgba(246, 194, 62, 1)',
                        'rgba(231, 133, 75, 1)',
                        'rgba(231, 74, 59, 1)'
                    ],
                    pointBorderColor: '#fff',
                    pointBorderWidth: 3,
                    pointRadius: 6,
                    pointHoverRadius: 9,
                    pointHoverBackgroundColor: '#667eea',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 1500,
                    easing: 'easeInOutQuart'
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.9)',
                        padding: 16,
                        titleFont: { size: 15, weight: '700' },
                        bodyFont: { size: 14 },
                        borderColor: '#667eea',
                        borderWidth: 2,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((context.parsed.r / total) * 100).toFixed(1) : 0;
                                return ' Grade ' + context.label + ': ' + context.parsed.r + ' students (' + percentage + '%)';
                            },
                            afterLabel: function(context) {
                                const gradeLabels = {
                                    'A': '90-100% (Excellent)',
                                    'B': '80-89% (Good)',
                                    'C': '70-79% (Average)',
                                    'D': '60-69% (Below Average)',
                                    'F': 'Below 60% (Fail)'
                                };
                                return gradeLabels[context.label] || '';
                            }
                        }
                    }
                },
                scales: {
                    r: {
                        beginAtZero: true,
                        angleLines: {
                            color: 'rgba(0, 0, 0, 0.1)',
                            lineWidth: 1
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)',
                            circular: true
                        },
                        pointLabels: {
                            font: { 
                                size: 14, 
                                weight: '700',
                                family: "'Segoe UI', sans-serif"
                            },
                            color: '#5a5c69',
                            padding: 15
                        },
                        ticks: {
                            stepSize: 5,
                            font: { size: 11, weight: '600' },
                            color: '#858796',
                            backdropColor: 'rgba(255, 255, 255, 0.9)',
                            backdropPadding: 4,
                            showLabelBackdrop: true
                        }
                    }
                }
            }
        });

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
            const elements = document.querySelectorAll('.stat-card, .chart-card, .activity-card, .welcome-section');
            elements.forEach((element, index) => {
                setTimeout(() => {
                    element.style.opacity = '0';
                    element.style.transform = 'translateY(20px)';
                    element.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        element.style.opacity = '1';
                        element.style.transform = 'translateY(0)';
                    }, 50);
                }, index * 50);
            });
        });

        // Number animation for stats
        function animateValue(element, start, end, duration) {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                element.textContent = Math.floor(progress * (end - start) + start);
                if (end.toString().includes('%')) {
                    element.textContent += '%';
                }
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
                    const text = number.textContent;
                    const finalValue = parseInt(text);
                    if (!isNaN(finalValue)) {
                        animateValue(number, 0, finalValue, 1000);
                        observer.unobserve(number);
                    }
                }
            });
        });

        document.querySelectorAll('.stat-card h4').forEach(number => {
            observer.observe(number);
        });

        // Auto-refresh stats every 5 minutes
        setInterval(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>