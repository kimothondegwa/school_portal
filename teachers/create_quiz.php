<?php
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
$user_id = $_SESSION['user_id'];
$db = getDB();
$success = '';
$error = '';

// ✅ Get teacher_id
$db->query("SELECT teacher_id FROM teachers WHERE user_id = :user_id");
$db->bind(':user_id', $user['user_id']);
$teacherRow = $db->fetch();

if (!$teacherRow) {
    $error = "Teacher profile not found. Please contact the administrator.";
} else {
    $teacher_id = $teacherRow['teacher_id'];
}

// ✅ Get subjects for dropdown
$db->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name ASC");
$subjects = $db->fetchAll();

// ✅ Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $quiz_title = trim($_POST['quiz_title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration = (int)($_POST['duration_minutes'] ?? 30);
    $total_marks = (int)($_POST['total_marks'] ?? 100);
    $start_time = $_POST['start_time'] ?? null;
    $end_time = $_POST['end_time'] ?? null;

    if (!$subject_id) {
        $error = "Please select a subject.";
    } elseif (empty($quiz_title)) {
        $error = "Quiz title is required.";
    } else {
        try {
            $db->query("INSERT INTO quizzes 
                (subject_id, teacher_id, title, description, duration_minutes, total_marks, start_time, end_time, is_active, created_at)
                VALUES
                (:subject_id, :teacher_id, :title, :description, :duration_minutes, :total_marks, :start_time, :end_time, 1, NOW())");

            $db->bind(':subject_id', $subject_id);
            $db->bind(':teacher_id', $teacher_id);
            $db->bind(':title', $quiz_title);
            $db->bind(':description', $description);
            $db->bind(':duration_minutes', $duration);
            $db->bind(':total_marks', $total_marks);
            $db->bind(':start_time', $start_time);
            $db->bind(':end_time', $end_time);
            $db->execute();

            $success = "Quiz created successfully!";
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Quiz - Teacher Panel</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
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

        .page-header-content {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .page-icon {
            width: 70px;
            height: 70px;
            border-radius: 15px;
            background: var(--info-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            animation: float 3s ease-in-out infinite;
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
        
        /* Form Container */
        .form-container {
            background: white;
            border-radius: 15px;
            padding: 2.5rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }
        
        .form-section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #5a5c69;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f8f9fc;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #5a5c69;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.95rem;
        }
        
        .form-control, .form-select {
            border: 2px solid #e3e6f0;
            border-radius: 10px;
            padding: 0.8rem 1rem;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #764ba2;
            box-shadow: 0 0 0 0.25rem rgba(118, 75, 162, 0.15);
            outline: 0;
        }

        .form-control::placeholder {
            color: #b8b9bd;
        }
        
        /* Question Card */
        .question-card {
            background: #f8f9fc;
            border: 2px solid #e3e6f0;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .question-card:hover {
            border-color: #764ba2;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .question-number {
            background: var(--primary-gradient);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .remove-question-btn {
            background: var(--warning-gradient);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        
        .remove-question-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 10px rgba(240, 147, 251, 0.4);
        }
        
        .option-input {
            position: relative;
            margin-bottom: 0.8rem;
        }
        
        .option-label {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: var(--info-gradient);
            color: white;
            padding: 0.3rem 0.6rem;
            border-radius: 5px;
            font-weight: 600;
            font-size: 0.85rem;
            z-index: 10;
        }
        
        .option-input input {
            padding-left: 4rem;
        }
        
        /* Buttons */
        .btn-add-question {
            background: var(--success-gradient);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .btn-add-question:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(17, 153, 142, 0.4);
        }
        
        .btn-submit {
            background: var(--info-gradient);
            color: white;
            border: none;
            padding: 1rem 2.5rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(79, 172, 254, 0.4);
        }

        .btn-submit:active {
            transform: translateY(0);
        }
        
        /* Alerts */
        .alert {
            border: none;
            border-radius: 10px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
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
            background: linear-gradient(135deg, rgba(17, 153, 142, 0.1) 0%, rgba(56, 239, 125, 0.1) 100%);
            color: #11998e;
            border-left: 4px solid #11998e;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, rgba(250, 112, 154, 0.1) 0%, rgba(254, 225, 64, 0.1) 100%);
            color: #dc3545;
            border-left: 4px solid #dc3545;
        }

        .alert i {
            font-size: 1.5rem;
        }

        /* Info Box */
        .info-box {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            border-left: 4px solid #667eea;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .info-box h6 {
            color: #5a5c69;
            font-weight: 700;
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-box ul {
            margin: 0;
            padding-left: 1.5rem;
            color: #858796;
        }

        .info-box li {
            margin-bottom: 0.5rem;
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
            
            .question-card {
                padding: 1rem;
            }
            
            .content-area {
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                padding: 1.5rem;
            }

            .page-header-content {
                width: 100%;
            }

            .back-button {
                width: 100%;
                justify-content: center;
            }
            
            .form-container {
                padding: 1.5rem;
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
                <h2>Create Quiz</h2>
            </div>
            
            <div class="topbar-right">
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
                <div class="page-header-content">
                    <div class="page-icon">
                        🧠
                    </div>
                    <div class="page-title">
                        <h1>Create New Quiz</h1>
                        <p>Design interactive quizzes for your students</p>
                    </div>
                </div>
                <a href="dashboard.php" class="back-button">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>
            
            <div class="form-container">
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
                
                <form method="POST" id="quizForm">
                    <!-- Quiz Info Section -->
                    <div class="mb-4">
                        <div class="form-section-title">
                            <i class="fas fa-info-circle"></i> Quiz Information
                        </div>

                        <div class="mb-4">
                            <label for="subject_id" class="form-label">
                                <i class="fas fa-book" style="color: #667eea;"></i> Subject <span style="color:#e74a3b">*</span>
                            </label>
                            <select name="subject_id" id="subject_id" class="form-select" required>
                                <option value="">-- Choose a subject --</option>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?= $s['subject_id'] ?>"><?= htmlspecialchars($s['subject_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="quiz_title" class="form-label">
                                <i class="fas fa-heading" style="color: #4facfe;"></i> Quiz Title <span style="color:#e74a3b">*</span>
                            </label>
                            <input type="text" name="quiz_title" id="quiz_title" class="form-control" placeholder="Enter quiz title" required>
                        </div>

                        <div class="mb-4">
                            <label for="description" class="form-label">
                                <i class="fas fa-align-left" style="color: #11998e;"></i> Description
                            </label>
                            <textarea name="description" id="description" class="form-control" rows="4" placeholder="Add any instructions or notes for students..."></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label"><i class="fas fa-clock" style="color: #667eea;"></i> Duration (minutes)</label>
                                <input type="number" name="duration_minutes" class="form-control" value="30" placeholder="30">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label"><i class="fas fa-star" style="color: #4facfe;"></i> Total Marks</label>
                                <input type="number" name="total_marks" class="form-control" value="100" placeholder="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label"><i class="fas fa-calendar-alt" style="color: #11998e;"></i> Active Period</label>
                                <div class="d-flex gap-2">
                                    <input type="datetime-local" name="start_time" class="form-control">
                                    <input type="datetime-local" name="end_time" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Questions Section -->
                    <div class="form-section-title mt-4">
                        <i class="fas fa-question-circle"></i> Quiz Questions
                    </div>
                    <div id="questionsContainer">
                        <!-- Default Question Block -->
                        <div class="question-card">
                            <div class="question-header">
                                <span class="question-number"><i class="fas fa-hashtag"></i> Question 1</span>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-question"></i> Question Text *</label>
                                <input type="text" name="questions[0][question_text]" class="form-control" placeholder="Enter question..." required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <input type="text" name="questions[0][option_a]" class="form-control" placeholder="Option A">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <input type="text" name="questions[0][option_b]" class="form-control" placeholder="Option B">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <input type="text" name="questions[0][option_c]" class="form-control" placeholder="Option C">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <input type="text" name="questions[0][option_d]" class="form-control" placeholder="Option D">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-8 mb-2">
                                    <input type="text" name="questions[0][correct_answer]" class="form-control" placeholder="Correct Answer (A, B, C, D)">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <input type="number" name="questions[0][marks]" class="form-control" placeholder="Marks" value="1">
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn-add-question" onclick="addQuestionField()">
                        <i class="fas fa-plus-circle"></i> Add Another Question
                    </button>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save"></i> Create Quiz
                        </button>
                    </div>
                </form>

                <!-- Info Box -->
                <div class="info-box">
                    <h6>
                        <i class="fas fa-info-circle"></i>
                        Quiz Creation Guidelines
                    </h6>
                    <ul>
                        <li>Each question must have 4 options (A, B, C, D)</li>
                        <li>Specify the correct answer as A, B, C, or D</li>
                        <li>Set individual marks for each question</li>
                        <li>You can add multiple questions by clicking "Add Another Question"</li>
                        <li>Students will see questions in the order you create them</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let questionCount = 1;
        
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
        
        // Add Question Field
        function addQuestionField() {
            questionCount++;
            const container = document.getElementById('questionsContainer');
            
            const wrapper = document.createElement('div');
            wrapper.className = 'question-card';
            wrapper.style.opacity = '0';
            wrapper.style.transform = 'translateY(20px)';
            
            wrapper.innerHTML = `
                <div class="question-header">
                    <span class="question-number">
                        <i class="fas fa-hashtag"></i> Question ${questionCount}
                    </span>
                    <button type="button" class="remove-question-btn" onclick="removeQuestion(this)">
                        <i class="fas fa-trash"></i> Remove
                    </button>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">
                        <i class="fas fa-question"></i> Question Text <span style="color: #e74a3b;">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="questions[][question_text]" 
                        class="form-control" 
                        placeholder="Enter your question here..." 
                        required
                    >
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <div class="option-input">
                            <span class="option-label">A</span>
                            <input 
                                type="text" 
                                name="questions[][option_a]" 
                                class="form-control" 
                                placeholder="Option A"
                            >
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="option-input">
                            <span class="option-label">B</span>
                            <input 
                                type="text" 
                                name="questions[][option_b]" 
                                class="form-control" 
                                placeholder="Option B"
                            >
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="option-input">
                            <span class="option-label">C</span>
                            <input 
                                type="text" 
                                name="questions[][option_c]" 
                                class="form-control" 
                                placeholder="Option C"
                            >
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="option-input">
                            <span class="option-label">D</span>
                            <input 
                                type="text" 
                                name="questions[][option_d]" 
                                class="form-control" 
                                placeholder="Option D"
                            >
                        </div>
                    </div>
                </div>      
                <div class="row">
                    <div class="col-md-8 mb-2">
                        <label class="form-label">
                            <i class="fas fa-check-circle"></i> Correct Answer
                        </label>
                        <input 
                            type="text" 
                            name="questions[][correct_answer]" 
                            class="form-control" 
                            placeholder="Enter correct answer (A, B, C, or D)"
                        >
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">
                            <i class="fas fa-star"></i> Marks   
                        </label>
                        <input
                            type="number" 
                            name="questions[][marks]" 
                            class="form-control" 
                            placeholder="Marks" 
                            value="1" 
                            min="1"
                        >
                    </div>
                </div>
            `;
            container.appendChild(wrapper);
            setTimeout(() => {
                wrapper.style.opacity = '1';
                wrapper.style.transform = 'translateY(0)';
            }, 10); 
        }   
        
        // Remove Question Field
        function removeQuestion(button) {
            const questionCard = button.closest('.question-card');
            questionCard.style.opacity = '0';
            questionCard.style.transform = 'translateY(20px)';
            setTimeout(() => {
                questionCard.remove();
                updateQuestionNumbers();
            }, 300);
        }
        
        // Update Question Numbers
        function updateQuestionNumbers() {
            const questionCards = document.querySelectorAll('.question-card');
            questionCards.forEach((card, index) => {
                const numberSpan = card.querySelector('.question-number');
                numberSpan.innerHTML = `<i class="fas fa-hashtag"></i> Question ${index + 1}`;
            });
            questionCount = questionCards.length;
        }
        
        // Keyboard shortcut - ESC to close sidebar on mobile
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.remove('active');
            }
        });
        
        // Add fade-in animation on load
        window.addEventListener('load', function() {
            const elements = document.querySelectorAll('.page-header, .form-container');
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