<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();

// Only students can access
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();
$student_id = $_SESSION['user_id'];
$user_id = $_SESSION['user_id'];
$unreadNotifications = getUnreadNotificationCount($user_id);

// ==============================
// Fetch quiz grades
// ==============================
$quiz_grades = $db->query("
    SELECT 
        q.title AS quiz_title,
        qa.score,
        qa.total_marks,
        qa.status,
        qa.start_time,
        qa.end_time,
        s.subject_name
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.quiz_id
    LEFT JOIN subjects s ON q.subject_id = s.subject_id
    WHERE qa.student_id = :student_id
    ORDER BY qa.end_time DESC
")->bind(':student_id', $student_id)->fetchAll();

// ==============================
// Fetch assignment grades (with grades table)
// ==============================
$assignment_grades = $db->query("
    SELECT 
        a.title AS assignment_title,
        a.total_marks AS assignment_total_marks,
        sub.submission_id,
        sub.submitted_at,
        sub.status AS submission_status,
        g.marks_obtained,
        g.feedback,
        g.graded_at,
        s.subject_name
    FROM submissions sub
    JOIN assignments a ON sub.assignment_id = a.assignment_id
    LEFT JOIN subjects s ON a.subject_id = s.subject_id
    LEFT JOIN grades g ON g.submission_id = sub.submission_id
    WHERE sub.student_id = :student_id
    ORDER BY sub.submitted_at DESC
")->bind(':student_id', $student_id)->fetchAll();

// ==============================
// Calculate statistics
// ==============================

// Quiz stats
$total_quizzes = count($quiz_grades);
$quiz_total_score = 0;
$quiz_max_score = 0;
foreach ($quiz_grades as $q) {
    $quiz_total_score += $q['score'];
    $quiz_max_score += $q['total_marks'];
}
$quiz_average = $quiz_max_score > 0 ? round(($quiz_total_score / $quiz_max_score) * 100, 2) : 0;

// Assignment stats
$graded_assignments = array_filter($assignment_grades, fn($a) => $a['marks_obtained'] !== null);
$total_graded = count($graded_assignments);
$assignment_total = 0;
$assignment_max_total = 0;

foreach ($graded_assignments as $a) {
    $assignment_total += $a['marks_obtained'];
    $assignment_max_total += $a['assignment_total_marks'] ?? 100; // fallback
}
$assignment_average = $assignment_max_total > 0 ? round(($assignment_total / $assignment_max_total) * 100, 2) : 0;

// Overall average
$overall_average = ($quiz_average + $assignment_average) / 2;
$overall_grade = calculateGrade($overall_average ?? 0); // your grading function
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Grades - Student Dashboard</title>

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
    background: var(--primary-gradient);
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

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
}

.stat-card.quizzes::before {
    background: var(--success-gradient);
}

.stat-card.assignments::before {
    background: var(--info-gradient);
}

.stat-card.average::before {
    background: var(--warning-gradient);
}

.stat-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
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

.stat-card.quizzes .stat-icon {
    background: var(--success-gradient);
}

.stat-card.assignments .stat-icon {
    background: var(--info-gradient);
}

.stat-card.average .stat-icon {
    background: var(--warning-gradient);
}

.stat-content h3 {
    margin: 0;
    font-size: 2.5rem;
    font-weight: 700;
    color: #5a5c69;
}

.stat-content p {
    margin: 0;
    color: #858796;
    font-size: 0.95rem;
}

/* Grades Section */
.grades-section {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    margin-bottom: 2rem;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e3e6f0;
}

.section-header h4 {
    margin: 0;
    color: #5a5c69;
    font-weight: 700;
    font-size: 1.3rem;
}

.section-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.section-icon.quiz {
    background: var(--success-gradient);
}

.section-icon.assignment {
    background: var(--info-gradient);
}

/* Grades Table */
.grades-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    overflow: hidden;
    border-radius: 10px;
}

.grades-table thead {
    background: var(--primary-gradient);
    color: white;
}

.grades-table th {
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    font-size: 0.9rem;
}

.grades-table th:first-child {
    border-top-left-radius: 10px;
}

.grades-table th:last-child {
    border-top-right-radius: 10px;
}

.grades-table td {
    padding: 1rem;
    border-bottom: 1px solid #e3e6f0;
    color: #5a5c69;
}

.grades-table tbody tr {
    transition: all 0.3s ease;
}

.grades-table tbody tr:hover {
    background: #f8f9fc;
}

.grades-table tbody tr:last-child td {
    border-bottom: none;
}

/* Score Display */
.score-display {
    font-weight: 700;
    font-size: 1.1rem;
}

.score-good {
    color: #11998e;
}

.score-average {
    color: #f093fb;
}

.score-low {
    color: #fa709a;
}

/* Status Badges */
.status-badge {
    padding: 0.4rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
}

.status-completed {
    background: linear-gradient(135deg, rgba(17, 153, 142, 0.1) 0%, rgba(56, 239, 125, 0.1) 100%);
    color: #11998e;
}

.status-graded {
    background: linear-gradient(135deg, rgba(79, 172, 254, 0.1) 0%, rgba(0, 242, 254, 0.1) 100%);
    color: #4facfe;
}

.status-pending {
    background: linear-gradient(135deg, rgba(240, 147, 251, 0.1) 0%, rgba(245, 87, 108, 0.1) 100%);
    color: #f093fb;
}

.status-submitted {
    background: linear-gradient(135deg, rgba(240, 147, 251, 0.1) 0%, rgba(245, 87, 108, 0.1) 100%);
    color: #f5576c;
}

/* Feedback Display */
.feedback-text {
    max-width: 300px;
    display: -webkit-box;
    line-clamp: 2;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    color: #858796;
    font-size: 0.85rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem;
    color: #858796;
}

.empty-state i {
    font-size: 4rem;
    color: #e3e6f0;
    margin-bottom: 1rem;
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
    
    .back-button {
        width: 100%;
        justify-content: center;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .grades-table {
        font-size: 0.85rem;
    }

    .grades-table th,
    .grades-table td {
        padding: 0.7rem;
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
                <h2>View Grades</h2>
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
                    <div class="page-icon">
                        📊
                    </div>
                    <div class="page-title">
                        <h1>Your Grades</h1>
                        <p>Track your academic performance and progress</p>
                    </div>
                </div>
                <a href="dashboard.php" class="back-button">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card quizzes">
                    <div class="stat-header">
                        <div class="stat-icon">
                            🧠
                        </div>
                        <div class="stat-content">
                            <h3><?= $total_quizzes ?></h3>
                            <p>Quiz Attempts</p>
                        </div>
                    </div>
                </div>
                <div class="stat-card assignments">
                    <div class="stat-header">
                        <div class="stat-icon">
                            📝
                        </div>
                        <div class="stat-content">
                            <h3><?= $total_graded ?></h3>
                            <p>Graded Assignments</p>
                        </div>
                    </div>
                </div>
                <div class="stat-card average">
                    <div class="stat-header">
                        <div class="stat-icon">
                            ⭐
                        </div>
                        <div class="stat-content">
                            <h3><?= $quiz_average ?>%</h3>
                            <p>Quiz Average</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- QUIZ GRADES -->
            <div class="grades-section">
                <div class="section-header">
                    <div class="section-icon quiz">
                        🧠
                    </div>
                    <h4>Quiz Results</h4>
                </div>

                <?php if (empty($quiz_grades)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-question"></i>
                        <p>No quiz attempts recorded yet.</p>
                    </div>
                <?php else: ?>
                    <table class="grades-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-book"></i> Quiz Title</th>
                                <th><i class="fas fa-graduation-cap"></i> Subject</th>
                                <th><i class="fas fa-star"></i> Score</th>
                                <th><i class="fas fa-chart-bar"></i> Percentage</th>
                                <th><i class="fas fa-flag"></i> Status</th>
                                <th><i class="fas fa-clock"></i> Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quiz_grades as $q): 
                                $percentage = $q['total_marks'] > 0 ? round(($q['score'] / $q['total_marks']) * 100, 1) : 0;
                                $score_class = $percentage >= 70 ? 'score-good' : ($percentage >= 50 ? 'score-average' : 'score-low');
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($q['quiz_title']) ?></strong></td>
                                <td><?= htmlspecialchars($q['subject_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="score-display <?= $score_class ?>">
                                        <?= htmlspecialchars($q['score']) ?> / <?= htmlspecialchars($q['total_marks']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="score-display <?= $score_class ?>">
                                        <?= $percentage ?>%
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($q['status']) ?>">
                                        <?= htmlspecialchars(ucfirst($q['status'])) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($q['end_time'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- ASSIGNMENT GRADES -->
            <div class="grades-section">
    <div class="section-header">
        <div class="section-icon assignment">
            📝
        </div>
        <h4>Assignment Grades</h4>
    </div>

    <?php if (empty($assignment_grades)): ?>
        <div class="empty-state">
            <i class="fas fa-file-alt"></i>
            <p>No assignments graded yet.</p>
        </div>
    <?php else: ?>
        <table class="grades-table">
            <thead>
                <tr>
                    <th><i class="fas fa-file-alt"></i> Assignment</th>
                    <th><i class="fas fa-calendar"></i> Submitted</th>
                    <th><i class="fas fa-flag"></i> Status</th>
                    <th><i class="fas fa-star"></i> Marks</th>
                    <th><i class="fas fa-comment"></i> Feedback</th>
                    <th><i class="fas fa-check-circle"></i> Graded On</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignment_grades as $a): 
                    $status = $a['status'] ?? 'pending'; // fallback if NULL
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($a['assignment_title']) ?></strong></td>
                    <td><?= date('M d, Y', strtotime($a['submitted_at'])) ?></td>
                    <td>
                        <span class="status-badge status-<?= strtolower($status) ?>">
                            <?= htmlspecialchars(ucfirst($status)) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($a['marks_obtained'] !== null): ?>
                            <span class="score-display score-good">
                                <?= htmlspecialchars($a['marks_obtained']) ?>
                            </span>
                        <?php else: ?>
                            <span style="color: #858796;">Not graded yet</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($a['feedback'])): ?>
                            <div class="feedback-text" title="<?= htmlspecialchars($a['feedback']) ?>">
                                <?= htmlspecialchars($a['feedback']) ?>
                            </div>
                        <?php else: ?>
                            <span style="color: #858796;">No feedback yet</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= $a['graded_at'] ? date('M d, Y', strtotime($a['graded_at'])) : '—' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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
            const elements = document.querySelectorAll('.page-header, .stat-card, .grades-section');
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
        
        // Tooltip for truncated feedback
        document.querySelectorAll('.feedback-text').forEach(function(element) {
            element.addEventListener('click', function() {
                const fullText = this.getAttribute('title');
                if (fullText && fullText !== this.textContent) {
                    alert('Feedback:\n\n' + fullText);
                }
            });
        });
    </script>
</body>
</html>