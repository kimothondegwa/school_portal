<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Start secure session
startSecureSession();

if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    redirect('../login.php');
}

$db = getDB();
$student_id = $_SESSION['user_id'];
$user_id = $_SESSION['user_id'];
$unreadNotifications = getUnreadNotificationCount($user_id);

// ✅ Fetch all active quizzes
$quizzes = $db->query("
    SELECT 
        q.quiz_id, 
        q.title, 
        q.description, 
        q.duration_minutes, 
        q.total_marks, 
        q.start_time, 
        q.end_time, 
        q.is_active,
        s.subject_name
    FROM quizzes q
    LEFT JOIN subjects s ON q.subject_id = s.subject_id
    WHERE q.is_active = 1
    ORDER BY q.start_time DESC
")->fetchAll();

// ✅ Fetch student quiz attempts
$attempts = $db->query("
    SELECT 
        a.quiz_id,
        a.score,
        a.total_marks,
        a.status,
        a.start_time,
        a.end_time
    FROM quiz_attempts a
    WHERE a.student_id = :student_id
")->bind(':student_id', $student_id)->fetchAll();

$attemptMap = [];
foreach ($attempts as $attempt) {
    $attemptMap[$attempt['quiz_id']] = $attempt;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Take Quiz - Student Dashboard</title>

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
    background: var(--success-gradient);
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

/* Quiz Cards Grid */
.quizzes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.quiz-card {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.quiz-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: var(--success-gradient);
}

.quiz-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

.quiz-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
}

.quiz-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    background: var(--success-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    flex-shrink: 0;
}

.quiz-title {
    flex: 1;
}

.quiz-title h5 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 700;
    color: #5a5c69;
}

.quiz-subject {
    display: inline-block;
    margin-top: 0.5rem;
    padding: 0.3rem 0.8rem;
    background: linear-gradient(135deg, rgba(79, 172, 254, 0.1) 0%, rgba(0, 242, 254, 0.1) 100%);
    color: #4facfe;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.quiz-description {
    color: #858796;
    font-size: 0.9rem;
    margin: 1rem 0;
    line-height: 1.5;
}

.quiz-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.8rem;
    margin: 1.5rem 0;
    padding: 1rem;
    background: #f8f9fc;
    border-radius: 10px;
}

.quiz-detail-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.quiz-detail-item i {
    color: #667eea;
    font-size: 0.9rem;
}

.quiz-detail-item span {
    color: #5a5c69;
    font-size: 0.85rem;
    font-weight: 600;
}

.quiz-timing {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin: 1rem 0;
    padding: 1rem;
    background: linear-gradient(135deg, rgba(240, 147, 251, 0.05) 0%, rgba(245, 87, 108, 0.05) 100%);
    border-left: 3px solid #f093fb;
    border-radius: 8px;
}

.quiz-timing-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: #5a5c69;
}

.quiz-timing-item i {
    color: #f093fb;
    width: 18px;
}

.quiz-timing-item strong {
    min-width: 60px;
}

/* Quiz Actions */
.quiz-actions {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e3e6f0;
}

.btn-start-quiz {
    width: 100%;
    padding: 1rem;
    background: var(--success-gradient);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-start-quiz:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(17, 153, 142, 0.4);
}

/* Attempt Status */
.attempt-status {
    padding: 1.5rem;
    background: linear-gradient(135deg, rgba(17, 153, 142, 0.05) 0%, rgba(56, 239, 125, 0.05) 100%);
    border-left: 3px solid #11998e;
    border-radius: 10px;
}

.attempt-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--success-gradient);
    color: white;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 1rem;
}

.score-display {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 1rem;
}

.score-value {
    font-size: 2rem;
    font-weight: 700;
    color: #11998e;
}

.score-label {
    color: #858796;
    font-size: 0.9rem;
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
    color: #e3e6f0;
    margin-bottom: 1.5rem;
}

.empty-state h4 {
    color: #5a5c69;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #858796;
    margin: 0;
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

    .quizzes-grid {
        grid-template-columns: 1fr;
    }

    .quiz-details {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>
    <!-- Sidebar -->
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <i class="fas fa-bars mobile-toggle" onclick="toggleSidebar()"></i>
                <h2>Take Quiz</h2>
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
                        🧠
                    </div>
                    <div class="page-title">
                        <h1>Available Quizzes</h1>
                        <p>Test your knowledge and track your progress</p>
                    </div>
                </div>
                <a href="dashboard.php" class="back-button">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>

            <?php if (empty($quizzes)): ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <i class="fas fa-clipboard-question"></i>
                    <h4>No Quizzes Available</h4>
                    <p>There are currently no active quizzes. Check back later!</p>
                </div>
            <?php else: ?>
                <!-- Quizzes Grid -->
                <div class="quizzes-grid">
                    <?php foreach ($quizzes as $quiz): 
                        $quiz_id = $quiz['quiz_id'];
                        $attempt = $attemptMap[$quiz_id] ?? null;
                    ?>
                    <div class="quiz-card">
                        <div class="quiz-header">
                            <div class="quiz-icon">
                                📝
                            </div>
                            <div class="quiz-title">
                                <h5><?= htmlspecialchars($quiz['title']) ?></h5>
                                <span class="quiz-subject">
                                    <i class="fas fa-book"></i>
                                    <?= htmlspecialchars($quiz['subject_name'] ?? 'General') ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($quiz['description']): ?>
                            <div class="quiz-description">
                                <?= nl2br(htmlspecialchars($quiz['description'])) ?>
                            </div>
                        <?php endif; ?>

                        <div class="quiz-details">
                            <div class="quiz-detail-item">
                                <i class="fas fa-clock"></i>
                                <span><?= htmlspecialchars($quiz['duration_minutes']) ?> minutes</span>
                            </div>
                            <div class="quiz-detail-item">
                                <i class="fas fa-star"></i>
                                <span><?= htmlspecialchars($quiz['total_marks']) ?> marks</span>
                            </div>
                        </div>

                        <div class="quiz-timing">
                            <div class="quiz-timing-item">
                                <i class="fas fa-play-circle"></i>
                                <strong>Starts:</strong>
                                <span><?= htmlspecialchars($quiz['start_time']) ?></span>
                            </div>
                            <div class="quiz-timing-item">
                                <i class="fas fa-stop-circle"></i>
                                <strong>Ends:</strong>
                                <span><?= htmlspecialchars($quiz['end_time']) ?></span>
                            </div>
                        </div>

                        <?php if ($attempt): ?>
                            <!-- Attempt Status -->
                            <div class="attempt-status">
                                <div class="attempt-badge">
                                    <i class="fas fa-check-circle"></i>
                                    Quiz Completed
                                </div>
                                <div class="score-display">
                                    <div>
                                        <div class="score-value">
                                            <?= htmlspecialchars($attempt['score']) ?> / <?= htmlspecialchars($attempt['total_marks']) ?>
                                        </div>
                                        <div class="score-label">Your Score</div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-weight: 700; font-size: 1.2rem; color: #5a5c69;">
                                            <?= round(($attempt['score'] / $attempt['total_marks']) * 100, 1) ?>%
                                        </div>
                                        <div class="score-label">Percentage</div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Start Quiz Button -->
                            <div class="quiz-actions">
                                <form action="start_quiz.php" method="POST">
                                    <input type="hidden" name="quiz_id" value="<?= $quiz_id ?>">
                                    <button type="submit" class="btn-start-quiz">
                                        <i class="fas fa-play"></i>
                                        Start Quiz
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
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
        
        // Keyboard shortcut - ESC to close sidebar on mobile
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.remove('active');
            }
        });
        
        // Add fade-in animation on load
        window.addEventListener('load', function() {
            const elements = document.querySelectorAll('.page-header, .quiz-card, .empty-state');
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
    </script>
</body>
</html>