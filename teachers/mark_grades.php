<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();

// Only teachers can access
if (!isLoggedIn() || !isTeacher()) {
    header('Location: ../login.php?error=Access+Denied');
    exit;
}

$user = getCurrentUser();
$db = getDB();

// Get unread notifications
$user_id = $user['user_id'];
$unreadNotifications = getUnreadNotificationCount($user_id);

$success = '';
$error = '';
$students = [];
$assignments = [];
$subjects = [];

// Fetch students, subjects, and assignments
try {
    // Students
    $students = $db->query("
        SELECT student_id, CONCAT(first_name, ' ', last_name) AS full_name
        FROM students
        ORDER BY first_name ASC
    ")->fetchAll();

    // Subjects
    $subjects = $db->query("
        SELECT subject_id, subject_name
        FROM subjects
        ORDER BY subject_name ASC
    ")->fetchAll();

    // Teacher ID
    $teacherRow = $db->query("SELECT teacher_id FROM teachers WHERE user_id = :user_id")
                     ->bind(':user_id', $user['user_id'])
                     ->fetch();
    if (!$teacherRow) {
        $error = "Teacher profile not found.";
    } else {
        $teacher_id = $teacherRow['teacher_id'];

        // Assignments
        $assignments = $db->query("
            SELECT a.assignment_id, a.title, a.subject_id, s.subject_name, COALESCE(a.created_at, NOW()) AS created_at
            FROM assignments a
            LEFT JOIN subjects s ON a.subject_id = s.subject_id
            WHERE a.teacher_id = :teacher_id
            ORDER BY a.created_at DESC
        ")->bind(':teacher_id', $teacher_id)
          ->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = "Failed to load data: " . $e->getMessage();
}

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $assignment_id = (int)($_POST['assignment_id'] ?? 0);
    $marks_obtained = trim($_POST['marks_obtained'] ?? '');
    $feedback = trim($_POST['feedback'] ?? '');

    if (!$student_id || !$assignment_id || $marks_obtained === '') {
        $error = "Please select a student, an assignment, and enter marks.";
    } else {
        try {
            // Check if submission exists
            $submission = $db->query("
                SELECT submission_id FROM submissions
                WHERE student_id = :student_id AND assignment_id = :assignment_id
            ")->bind(':student_id', $student_id)
              ->bind(':assignment_id', $assignment_id)
              ->fetch();

            if (!$submission) {
                $error = "Cannot grade: no submission found for this student.";
            } else {
                $submission_id = $submission['submission_id'];

                // Check if grade exists
                $existing = $db->query("
                    SELECT grade_id FROM grades
                    WHERE student_id = :student_id AND assignment_id = :assignment_id
                ")->bind(':student_id', $student_id)
                  ->bind(':assignment_id', $assignment_id)
                  ->fetch();

                if ($existing) {
                    // Update existing grade
                    $db->query("
                        UPDATE grades
                        SET marks_obtained = :marks, feedback = :feedback,
                            graded_at = NOW(), graded_by = :teacher_id, submission_id = :submission_id
                        WHERE grade_id = :grade_id
                    ")->bind(':marks', $marks_obtained)
                      ->bind(':feedback', $feedback)
                      ->bind(':teacher_id', $teacher_id)
                      ->bind(':submission_id', $submission_id)
                      ->bind(':grade_id', $existing['grade_id'])
                      ->execute();
                } else {
                    // Insert new grade
                    $db->query("
                        INSERT INTO grades (student_id, assignment_id, submission_id, marks_obtained, feedback, graded_by, graded_at)
                        VALUES (:student_id, :assignment_id, :submission_id, :marks, :feedback, :teacher_id, NOW())
                    ")->bind(':student_id', $student_id)
                      ->bind(':assignment_id', $assignment_id)
                      ->bind(':submission_id', $submission_id)
                      ->bind(':marks', $marks_obtained)
                      ->bind(':feedback', $feedback)
                      ->bind(':teacher_id', $teacher_id)
                      ->execute();
                }
                
                // Notify student that their assignment has been graded
                $studentUser = $db->query("SELECT user_id FROM students WHERE student_id = :sid")
                                  ->bind(':sid', $student_id)->fetch();
                if ($studentUser) {
                    createNotification(
                        $studentUser['user_id'],
                        $user['user_id'],
                        'Assignment Graded',
                        'Your assignment has been graded. Check your dashboard for marks and feedback.',
                        'grade',
                        $assignment_id
                    );
                }

                $success = "✅ Grade saved successfully!";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch recent grades
try {
    $recentGrades = $db->query("
        SELECT g.grade_id, g.marks_obtained, g.feedback, g.graded_at,
               CONCAT(s.first_name, ' ', s.last_name) AS student_name,
               a.title AS assignment_title
        FROM grades g
        JOIN students s ON g.student_id = s.student_id
        JOIN assignments a ON g.assignment_id = a.assignment_id
        WHERE g.graded_by = :teacher_id
        ORDER BY g.graded_at DESC
        LIMIT 10
    ")->bind(':teacher_id', $teacher_id)
      ->fetchAll();
} catch (PDOException $e) {
    $recentGrades = [];
    $error = "Failed to load recent grades: " . $e->getMessage();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Grade Students - Teacher Dashboard</title>

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

.page-title {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.page-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    background: var(--primary-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
}

.page-title h3 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: #5a5c69;
}

.page-title p {
    margin: 0.3rem 0 0 0;
    color: #858796;
    font-size: 0.9rem;
}

/* Stats Row */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 10px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
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

.stat-icon.students {
    background: linear-gradient(135deg, rgba(79, 172, 254, 0.2) 0%, rgba(0, 242, 254, 0.2) 100%);
    color: #4facfe;
}

.stat-icon.subjects {
    background: linear-gradient(135deg, rgba(17, 153, 142, 0.2) 0%, rgba(56, 239, 125, 0.2) 100%);
    color: #11998e;
}

.stat-icon.grades {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
    color: #667eea;
}

.stat-content h4 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #5a5c69;
}

.stat-content p {
    margin: 0;
    font-size: 0.85rem;
    color: #858796;
}

/* Grid Layout */
.content-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

/* Grade Form Card */
.grade-card {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
}

.card-header-custom {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f8f9fc;
}

.card-header-custom i {
    font-size: 1.5rem;
    color: #764ba2;
}

.card-header-custom h4 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 700;
    color: #5a5c69;
}

/* Alert Styles */
.alert {
    border-radius: 10px;
    border: none;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.alert-success {
    background: linear-gradient(135deg, rgba(17, 153, 142, 0.1) 0%, rgba(56, 239, 125, 0.1) 100%);
    color: #11998e;
    border-left: 4px solid #11998e;
}

.alert-danger {
    background: linear-gradient(135deg, rgba(250, 112, 154, 0.1) 0%, rgba(254, 225, 64, 0.1) 100%);
    color: #fa709a;
    border-left: 4px solid #fa709a;
}

.alert i {
    font-size: 1.5rem;
}

/* Form Styles */
.form-section {
    margin-bottom: 1.5rem;
}

.form-label {
    font-weight: 600;
    color: #5a5c69;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-label i {
    color: #764ba2;
}

.form-control, .form-select {
    border: 2px solid #e3e6f0;
    border-radius: 10px;
    padding: 0.75rem 1rem;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #764ba2;
    box-shadow: 0 0 0 3px rgba(118, 75, 162, 0.1);
    outline: none;
}

textarea.form-control {
    resize: vertical;
    min-height: 100px;
}

/* Grade Input Special */
.grade-input-group {
    position: relative;
}

.grade-suggestions {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
    flex-wrap: wrap;
}

.grade-badge {
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.grade-badge.excellent {
    background: linear-gradient(135deg, rgba(17, 153, 142, 0.2) 0%, rgba(56, 239, 125, 0.2) 100%);
    color: #11998e;
}

.grade-badge.good {
    background: linear-gradient(135deg, rgba(79, 172, 254, 0.2) 0%, rgba(0, 242, 254, 0.2) 100%);
    color: #4facfe;
}

.grade-badge.average {
    background: linear-gradient(135deg, rgba(254, 202, 87, 0.2) 0%, rgba(255, 107, 107, 0.2) 100%);
    color: #f39c12;
}

.grade-badge:hover {
    transform: scale(1.05);
    border-color: currentColor;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    flex-wrap: wrap;
}

.btn {
    padding: 0.75rem 2rem;
    border-radius: 10px;
    font-weight: 600;
    border: none;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary {
    background: var(--primary-gradient);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(118, 75, 162, 0.3);
}

.btn-secondary {
    background: #858796;
    color: white;
}

.btn-secondary:hover {
    background: #6c757d;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
}

/* Recent Grades Table */
.recent-grades-card {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
}

.grades-table-wrapper {
    overflow-x: auto;
    border-radius: 10px;
    max-height: 600px;
    overflow-y: auto;
}

.grades-table {
    width: 100%;
    margin-bottom: 0;
    background: white;
}

.grades-table thead {
    background: var(--primary-gradient);
    color: white;
    position: sticky;
    top: 0;
    z-index: 10;
}

.grades-table thead th {
    padding: 1rem;
    font-weight: 600;
    border: none;
    text-align: left;
    white-space: nowrap;
}

.grades-table tbody tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid #e3e6f0;
}

.grades-table tbody tr:hover {
    background: #f8f9fc;
}

.grades-table tbody td {
    padding: 1rem;
    vertical-align: middle;
}

.grade-badge-display {
    display: inline-block;
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
}

.grade-badge-display.excellent {
    background: var(--success-gradient);
    color: white;
}

.grade-badge-display.good {
    background: var(--info-gradient);
    color: white;
}

.grade-badge-display.average {
    background: var(--warning-gradient);
    color: white;
}

.remarks-text {
    color: #858796;
    font-size: 0.85rem;
    font-style: italic;
}

.date-text {
    color: #858796;
    font-size: 0.85rem;
}

/* Responsive */
@media (max-width: 1200px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
}

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
    
    .page-header {
        flex-direction: column;
        text-align: center;
    }
    
    .stats-row {
        grid-template-columns: 1fr;
    }
}

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

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #858796;
}

.empty-state i {
    font-size: 4rem;
    color: #e3e6f0;
    margin-bottom: 1rem;
}

.empty-state h5 {
    color: #5a5c69;
    margin-bottom: 0.5rem;
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
            <h2>Grade Students</h2>
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
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <div class="page-icon">📊</div>
                <div>
                    <h3>Student Grading System</h3>
                    <p>Evaluate and record student performance</p>
                </div>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon students">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-content">
                    <h4><?= count($students) ?></h4>
                    <p>Total Students</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon subjects">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-content">
                    <h4><?= count($subjects) ?></h4>
                    <p>Total Subjects</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon grades">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <h4><?= count($recentGrades) ?></h4>
                    <p>Recent Grades</p>
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Grade Form -->
            <div class="grade-card">
                <div class="card-header-custom">
                    <i class="fas fa-edit"></i>
                    <h4>Enter Grade</h4>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" id="gradeForm">
                    <!-- Student -->
                    <div class="form-section">
                        <label for="student_id" class="form-label">
                            <i class="fas fa-user-graduate"></i> Select Student
                        </label>
                        <select name="student_id" id="student_id" class="form-select" required>
                            <option value="">-- Choose a student --</option>
                            <?php foreach ($students as $s): ?>
                                <option value="<?= $s['student_id'] ?>">
                                    <?= htmlspecialchars($s['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Subject -->
<div class="form-section">
    <label for="subject_id" class="form-label">
        <i class="fas fa-book"></i> Select Subject
    </label>
    <select name="subject_id" id="subject_id" class="form-select" required>
        <option value="">-- Choose a subject --</option>
        <?php foreach ($subjects as $sub): ?>
            <option value="<?= $sub['subject_id'] ?>">
                <?= htmlspecialchars($sub['subject_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>


                    <!-- Assignment -->
                <!-- Assignment -->
<div class="form-section">
    <label for="assignment_id" class="form-label">
        <i class="fas fa-tasks"></i> Select Assignment
    </label>
    <select name="assignment_id" id="assignment_id" class="form-select" required>
        <option value="">-- Choose an assignment --</option>
        <?php foreach ($assignments as $a): ?>
            <option value="<?= $a['assignment_id'] ?>">
                <?= htmlspecialchars($a['title'] . ' (' . $a['subject_name'] . ')') ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

                    <!-- Marks -->
                    <div class="form-section">
                        <label for="marks_obtained" class="form-label">
                            <i class="fas fa-award"></i> Marks Obtained
                        </label>
                        <input type="number" 
                               name="marks_obtained" 
                               id="marks_obtained" 
                               class="form-control" 
                               required 
                               placeholder="Enter marks e.g., 85">
                    </div>

                    <!-- Feedback -->
                    <div class="form-section">
                        <label for="feedback" class="form-label">
                            <i class="fas fa-comment"></i> Feedback (Optional)
                        </label>
                        <textarea name="feedback" 
                                  id="feedback" 
                                  class="form-control" 
                                  placeholder="Add comments about the student's performance..."></textarea>
                    </div>

                    <!-- Buttons -->
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Grade
                        </button>
                        <button type="reset" class="btn btn-secondary" onclick="resetForm()">
                            <i class="fas fa-redo"></i> Reset Form
                        </button>
                    </div>
                </form>
            </div>

            <!-- Recent Grades -->
            <div class="recent-grades-card">
    <div class="card-header-custom">
        <i class="fas fa-history"></i>
        <h4>Recent Grades</h4>
    </div>

    <?php if (count($recentGrades) > 0): ?>
        <div class="grades-table-wrapper">
            <table class="grades-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Assignment</th>
                        <th>Marks</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentGrades as $g): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($g['student_name'] ?? 'N/A') ?></strong></td>
                            <td><?= htmlspecialchars($g['assignment_title'] ?? 'N/A') ?></td>
                            <td><?= isset($g['marks_obtained']) ? number_format($g['marks_obtained'], 2) : 'N/A' ?></td>
                            <td>
                                <?php
                                    // Safe date display
                                    if (!empty($g['created_at'])) {
                                        echo formatDate($g['created_at'], 'M d, Y');
                                    } else {
                                        echo 'N/A';
                                    }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-clipboard-list"></i>
            <h5>No Grades Yet</h5>
            <p>Start by entering your first grade using the form</p>
        </div>
    <?php endif; ?>
            </div>
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

        // Set grade from quick select
        function setGrade(grade) {
            document.getElementById('grade').value = grade;
            document.getElementById('grade').focus();
        }

        // Reset form
        function resetForm() {
            document.getElementById('gradeForm').reset();
        }

        // Search functionality
        const searchInput = document.querySelector('.search-box input');
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.grades-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Form validation
        document.getElementById('gradeForm').addEventListener('submit', function(e) {
            const student = document.getElementById('student_id').value;
            const subject = document.getElementById('subject_id').value;
            const grade = document.getElementById('grade').value;

            if (!student || !subject || !grade) {
                e.preventDefault();
                alert('Please fill in all required fields');
            }
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

        // Add fade-in animation on load
        window.addEventListener('load', function() {
            const elements = document.querySelectorAll('.page-header, .stat-card, .grade-card, .recent-grades-card');
            elements.forEach((el, index) => {
                setTimeout(() => {
                    el.style.opacity = '0';
                    el.style.transform = 'translateY(20px)';
                    el.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        el.style.opacity = '1';
                        el.style.transform = 'translateY(0)';
                    }, 50);
                }, index * 100);
            });
        });

        // Auto-refresh recent grades table every 30 seconds (optional)
        // setInterval(function() {
        //     location.reload();
        // }, 30000);
    </script>
</body>
</html>

<?php
// Helper function to determine grade class
function getGradeClass($grade) {
    $grade = strtoupper(trim($grade));
    
    // Letter grades
    if (in_array($grade, ['A', 'A+', 'A-'])) {
        return 'excellent';
    }
    if (in_array($grade, ['B', 'B+', 'B-'])) {
        return 'good';
    }
    
    // Percentage grades
    if (is_numeric(str_replace('%', '', $grade))) {
        $numGrade = (float)str_replace('%', '', $grade);
        if ($numGrade >= 85) return 'excellent';
        if ($numGrade >= 70) return 'good';
    }
    
    return 'average';
}
?>