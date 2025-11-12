<?php
// ====================================================
// FILE: student/view_assignments.php
// Enhanced Student View and Submit Assignments
// ====================================================

define('APP_ACCESS', true);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();

if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    redirect('../login.php');
}

$student_id = $_SESSION['user_id'];
$user = getCurrentUser();
$db = getDB();
$unreadNotifications = getUnreadNotificationCount($student_id);

/**
 * Get all assignments for a student
 * Returns an array with assignments and a submission map
 */
function getStudentAssignments($student_id) {
    $db = getDB();

    // Fetch student's subjects
    $subject_ids = $db->query("
        SELECT subject_id 
        FROM student_subjects 
        WHERE student_id = :student_id
    ")
    ->bind(':student_id', $student_id)
    ->fetchAll(PDO::FETCH_COLUMN);

    $assignments = [];

    // Fetch assignments for each subject
    if (!empty($subject_ids)) {
        foreach ($subject_ids as $subject_id) {
            $subject_assignments = getAssignmentsBySubject($subject_id);
            $assignments = array_merge($assignments, $subject_assignments);
        }
    }

    // Fetch student's submissions
    $submissions = getStudentSubmissions($student_id);
    $submission_map = [];
    foreach ($submissions as $sub) {
        $submission_map[$sub['assignment_id']] = $sub;
    }

    return [
        'assignments' => $assignments,
        'submissions' => $submission_map
    ];
}

// Call the function
$data = getStudentAssignments($student_id);
$assignments = $data['assignments'];
$submission_map = $data['submissions'];

// Calculate statistics
$total_assignments = count($assignments);
$submitted_count = 0;
$graded_count = 0;
$pending_count = 0;
$overdue_count = 0;
$avg_grade = 0;
$total_graded = 0;

foreach ($assignments as $assignment) {
    $assignment_id = $assignment['id'];
    $is_submitted = isset($submission_map[$assignment_id]);
    $submission = $submission_map[$assignment_id] ?? null;
    
    if ($is_submitted) {
        $submitted_count++;
        if ($submission['status'] === 'graded') {
            $graded_count++;
            $total_graded += ($submission['marks_obtained'] / $assignment['total_marks']) * 100;
        }
    } else {
        if (strtotime($assignment['due_date']) < time()) {
            $overdue_count++;
        } else {
            $pending_count++;
        }
    }
}

$avg_grade = $graded_count > 0 ? round($total_graded / $graded_count) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Assignments - Student Portal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --sidebar-width: 280px;
    --student-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

.sidebar-footer a {
    display: block;
    text-align: center;
    padding: 0.8rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    color: white;
    text-decoration: none;
    transition: all 0.3s ease;
}

.sidebar-footer a:hover {
    background: rgba(255, 255, 255, 0.2);
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

/* Mobile Toggle */
.mobile-toggle {
    display: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #5a5c69;
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

/* Stats Grid */
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

.stat-card.total::before { background: var(--student-gradient); }
.stat-card.submitted::before { background: var(--success-gradient); }
.stat-card.pending::before { background: var(--info-gradient); }
.stat-card.graded::before { background: var(--warning-gradient); }
.stat-card.overdue::before { background: var(--danger-gradient); }

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

.stat-card.total .stat-icon { background: var(--student-gradient); }
.stat-card.submitted .stat-icon { background: var(--success-gradient); }
.stat-card.pending .stat-icon { background: var(--info-gradient); }
.stat-card.graded .stat-icon { background: var(--warning-gradient); }
.stat-card.overdue .stat-icon { background: var(--danger-gradient); }

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

/* Filter Tabs */
.filter-tabs {
    background: white;
    border-radius: 15px;
    padding: 1rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    margin-bottom: 2rem;
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 0.7rem 1.5rem;
    border-radius: 50px;
    border: 2px solid #e3e6f0;
    background: white;
    color: #858796;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
    font-size: 0.9rem;
}

.filter-tab:hover {
    border-color: #667eea;
    color: #667eea;
}

.filter-tab.active {
    background: var(--student-gradient);
    border-color: transparent;
    color: white;
}

/* Assignment Card */
.assignment-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    border-left: 5px solid #667eea;
}

.assignment-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

.assignment-card.submitted {
    border-left-color: #11998e;
}

.assignment-card.graded {
    border-left-color: #f093fb;
}

.assignment-card.overdue {
    border-left-color: #e74a3b;
}

.assignment-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.assignment-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #5a5c69;
    margin: 0;
}

.assignment-meta {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #858796;
    font-size: 0.9rem;
}

.meta-item i {
    color: #667eea;
}

.assignment-description {
    color: #5a5c69;
    margin-bottom: 1rem;
    line-height: 1.6;
}

.badge {
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.85rem;
}

.badge-pending {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
}

.badge-submitted {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
}

.badge-graded {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
}

.badge-overdue {
    background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    color: white;
}

.assignment-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
    flex-wrap: wrap;
}

.btn-custom {
    padding: 0.7rem 1.5rem;
    border-radius: 50px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-submit {
    background: var(--student-gradient);
    color: white;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    color: white;
}

.btn-view {
    background: white;
    color: #667eea;
    border: 2px solid #667eea;
}

.btn-view:hover {
    background: #667eea;
    color: white;
}

.grade-display {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    padding: 1rem;
    border-radius: 10px;
    margin-top: 1rem;
}

.grade-display h5 {
    margin: 0;
    font-size: 1.1rem;
}

.feedback-box {
    background: #f8f9fc;
    padding: 1rem;
    border-radius: 10px;
    margin-top: 1rem;
    border-left: 4px solid #667eea;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
}

.empty-state i {
    font-size: 4rem;
    color: #e3e6f0;
    margin-bottom: 1rem;
}

.empty-state h4 {
    color: #5a5c69;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #858796;
}

/* Modal Styles */
.modal-content {
    border-radius: 15px;
    border: none;
}

.modal-header {
    background: var(--student-gradient);
    color: white;
    border-radius: 15px 15px 0 0;
}

.modal-body {
    padding: 2rem;
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
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-tabs {
        justify-content: center;
    }
    
    .assignment-header {
        flex-direction: column;
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
                <h2>My Assignments</h2>
            </div>
            
            <div class="topbar-right">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search assignments..." id="searchInput">
                </div>
                
                <?php echo getNotificationBadgeHTML($student_id, 'comment.php'); ?>
                
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
            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="welcome-content">
                    <h3>Welcome back, <?= htmlspecialchars($user['username'] ?? 'Student') ?>! 📚</h3>
                    <p>You have <?= $pending_count ?> pending assignment<?= $pending_count != 1 ? 's' : '' ?> to complete. Stay on track!</p>
                </div>
                <div class="welcome-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
            </div>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-details">
                        <h4><?= $total_assignments ?></h4>
                        <p>Total Assignments</p>
                    </div>
                </div>

                <div class="stat-card submitted">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h4><?= $submitted_count ?></h4>
                        <p>Submitted</p>
                    </div>
                </div>

                <div class="stat-card pending">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <h4><?= $pending_count ?></h4>
                        <p>Pending</p>
                    </div>
                </div>

                <div class="stat-card graded">
                    <div class="stat-icon">
                        <i class="fas fa-award"></i>
                    </div>
                    <div class="stat-details">
                        <h4><?= $graded_count ?></h4>
                        <p>Graded</p>
                    </div>
                </div>

                <div class="stat-card overdue">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-details">
                        <h4><?= $overdue_count ?></h4>
                        <p>Overdue</p>
                    </div>
                </div>

                <?php if ($graded_count > 0): ?>
                <div class="stat-card graded">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-details">
                        <h4><?= $avg_grade ?>%</h4>
                        <p>Average Grade</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <div class="filter-tab active" onclick="filterAssignments('all')">
                    <i class="fas fa-list me-2"></i>All Assignments
                </div>
                <div class="filter-tab" onclick="filterAssignments('pending')">
                    <i class="fas fa-clock me-2"></i>Pending
                </div>
                <div class="filter-tab" onclick="filterAssignments('submitted')">
                    <i class="fas fa-check me-2"></i>Submitted
                </div>
                <div class="filter-tab" onclick="filterAssignments('graded')">
                    <i class="fas fa-award me-2"></i>Graded
                </div>
                <div class="filter-tab" onclick="filterAssign
ments('overdue')">
                    <i class="fas fa-exclamation-triangle me-2"></i>Overdue
                </div>
            </div>  
            <!-- Assignments List -->
            <div id="assignmentsList">

                <?php if (empty($assignments)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h4>No Assignments Found</h4>
                        <p>You currently have no assignments. Please check back later.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($assignments as $assignment): 
                        $assignment_id = $assignment['id'];
                        $is_submitted = isset($submission_map[$assignment_id]);
                        $submission = $submission_map[$assignment_id] ?? null;
                        $status = 'pending';
                        if ($is_submitted) {
                            $status = $submission['status'];
                        } else {
                            if (strtotime($assignment['due_date']) < time()) {
                                $status = 'overdue';
                            }
                        }
                    ?>
                    <div class="assignment-card <?= $status ?>">
                        <div class="assignment-header">
                            <h3 class="assignment-title"><?= htmlspecialchars($assignment['title']) ?></h3>
                            <div class="assignment-meta">
                                <div class="meta-item">
                                    <i class="fas fa-book"></i>
                                    <span>Subject: <?= htmlspecialchars(getSubjectName($assignment['subject_id'])) ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>Due: <?= date('M d, Y', strtotime($assignment['due_date'])) ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-coins"></i>
                                    <span>Total Marks: <?= $assignment['total_marks'] ?></span>
                                </div>  
                            </div>
                        </div>
                        <div class="assignment-description">
                            <?= nl2br(htmlspecialchars($assignment['description'])) ?>  
                        </div>
                        <div class="assignment-actions">
                            <?php if ($status === 'pending' || $status === 'overdue'): ?>
                                <a href="submit_assignment.php?assignment_id=<?= $assignment_id ?>" class="btn-custom btn-submit">
                                    <i class="fas fa-upload"></i>Submit Assignment
                                </a>
                            <?php elseif ($status === 'submitted'): ?>
                                <a href="view_submission.php?assignment_id=<?= $assignment_id ?>" class="btn-custom btn-view">
                                    <i class="fas fa-eye
"></i>View Submission
                                </a>    
                            <?php elseif ($status === 'graded'): ?>
                                <a href="view_submission.php?assignment_id=<?= $assignment_id ?>" class="btn-custom btn-view">
                                    <i class="fas fa-eye"></i>View Submission
                                </a>    
                                <div class="grade-display">
                                    <h5>Your Grade: <?= $submission['marks_obtained'] ?> / <?= $assignment['total_marks'] ?> (<?= round(($submission['marks_obtained'] / $assignment['total_marks']) * 100) ?>%)</h5>
                                </div>
                                <?php if (!empty($submission['feedback'])): ?>
                                    <div class="feedback-box">
                                        <strong>Feedback:</strong>
                                        <p><?= nl2br(htmlspecialchars($submission['feedback'])) ?></p>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?> 
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>  
    <script>
        // Toggle Sidebar
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        // Filter Assignments
        function filterAssignments(status) {
            const tabs = document.querySelectorAll('.filter-tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            event.currentTarget.classList.add('active');

            const assignments = document.querySelectorAll('.assignment-card');
            assignments.forEach(card => {
                if (status === 'all' || card.classList.contains(status)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Search Assignments
        document.getElementById('searchInput').addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const assignments = document.querySelectorAll('.assignment-card');
            assignments.forEach(card => {
                const title = card.querySelector('.assignment-title').textContent.toLowerCase();
                if (title.includes(query)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    </script>
</body> 