<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();

if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();
// Map logged-in user to student_id
$studentRow = $db->query("SELECT student_id FROM students WHERE user_id = :uid")
                 ->bind(':uid', $_SESSION['user_id'])
                 ->fetch();

if (!$studentRow) {
    die("Student profile not found.");
}

$student_id = $studentRow['student_id'];

$success = '';
$error = '';

// Fetch available assignments
$assignments = $db->query("
    SELECT assignment_id, title, description, due_date 
    FROM assignments 
    ORDER BY due_date DESC
")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assignment_id = (int)($_POST['assignment_id'] ?? 0);
    $submission_text = trim($_POST['submission_text'] ?? '');
    $status = 'Submitted';
    $upload_dir = __DIR__ . '/../uploads/submissions/';
    $file_path = null;

    // Ensure upload directory exists
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Handle file upload if provided
    if (!empty($_FILES['file']['name'])) {
        $file_name = basename($_FILES['file']['name']);
        $target_file = $upload_dir . time() . '_' . $file_name;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $target_file)) {
            $file_path = 'uploads/submissions/' . time() . '_' . $file_name;
        } else {
            $error = "Error uploading file.";
        }
    }

    // Save submission
    if (!$error && $assignment_id) {
        try {
            $db->query("
                INSERT INTO submissions (assignment_id, student_id, file_path, submission_text, submitted_at, status)
                VALUES (:assignment_id, :student_id, :file_path, :submission_text, NOW(), :status)
            ")
            ->bind(':assignment_id', $assignment_id)
            ->bind(':student_id', $student_id)
            ->bind(':file_path', $file_path)
            ->bind(':submission_text', $submission_text)
            ->bind(':status', $status)
            ->execute();

            $success = "Assignment submitted successfully!";
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch student submissions
$subs = $db->query("
    SELECT s.*, a.title 
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.assignment_id
    WHERE s.student_id = :sid
    ORDER BY s.submitted_at DESC
")->bind(':sid', $student_id)->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Submit Assignment - Student Dashboard</title>

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
    background: var(--warning-gradient);
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

/* Submit Card */
.submit-card {
    background: white;
    border-radius: 15px;
    padding: 2.5rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    margin-bottom: 2rem;
}

/* Alert Styles */
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

/* Form Styles */
.form-label {
    font-weight: 600;
    color: #5a5c69;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.form-control, .form-select {
    border: 2px solid #e3e6f0;
    border-radius: 10px;
    padding: 0.8rem 1rem;
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #764ba2;
    box-shadow: 0 0 0 3px rgba(118, 75, 162, 0.1);
    outline: none;
}

.form-control::placeholder {
    color: #b8b9bd;
}

/* File Input Custom Style */
.file-input-wrapper {
    position: relative;
    overflow: hidden;
    display: inline-block;
    width: 100%;
}

.file-input-wrapper input[type=file] {
    position: absolute;
    left: -9999px;
}

.file-input-label {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    background: linear-gradient(135deg, rgba(240, 147, 251, 0.05) 0%, rgba(245, 87, 108, 0.05) 100%);
    border: 2px dashed #f093fb;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
}

.file-input-label:hover {
    background: linear-gradient(135deg, rgba(240, 147, 251, 0.1) 0%, rgba(245, 87, 108, 0.1) 100%);
    border-color: #764ba2;
}

.file-input-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.file-input-icon {
    font-size: 3rem;
    color: #f093fb;
}

.file-input-text {
    color: #5a5c69;
    font-weight: 600;
}

.file-input-hint {
    color: #858796;
    font-size: 0.85rem;
}

.file-name-display {
    margin-top: 0.5rem;
    padding: 0.5rem 1rem;
    background: #f8f9fc;
    border-radius: 8px;
    color: #5a5c69;
    font-size: 0.9rem;
    display: none;
}

.file-name-display.show {
    display: block;
}

/* Submit Button */
.btn-submit {
    padding: 1rem 2.5rem;
    background: var(--warning-gradient);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1rem;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(240, 147, 251, 0.4);
}

.btn-submit:active {
    transform: translateY(0);
}

/* Submissions Section */
.submissions-section {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
}

.section-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}

.section-header h4 {
    margin: 0;
    color: #5a5c69;
    font-weight: 700;
}

.section-header i {
    color: #667eea;
}

/* Table Styles */
.submissions-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    overflow: hidden;
    border-radius: 10px;
}

.submissions-table thead {
    background: var(--primary-gradient);
    color: white;
}

.submissions-table th {
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    font-size: 0.9rem;
}

.submissions-table th:first-child {
    border-top-left-radius: 10px;
}

.submissions-table th:last-child {
    border-top-right-radius: 10px;
}

.submissions-table td {
    padding: 1rem;
    border-bottom: 1px solid #e3e6f0;
    color: #5a5c69;
}

.submissions-table tbody tr {
    transition: all 0.3s ease;
}

.submissions-table tbody tr:hover {
    background: #f8f9fc;
}

.submissions-table tbody tr:last-child td {
    border-bottom: none;
}

.file-link {
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.file-link:hover {
    color: #764ba2;
    text-decoration: underline;
}

.status-badge {
    padding: 0.4rem 1rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-block;
}

.status-submitted {
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

.no-submissions {
    text-align: center;
    padding: 3rem;
    color: #858796;
}

.no-submissions i {
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
    
    .submit-card {
        padding: 1.5rem;
    }

    .submissions-table {
        font-size: 0.85rem;
    }

    .submissions-table th,
    .submissions-table td {
        padding: 0.7rem;
    }
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
            <a href="dashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="messages.php">
                <i class="fas fa-envelope"></i>
                <span>Messages</span>
            </a>
            
            <div class="menu-section">Academics</div>
            <a href="submit_assignment.php" class="active">
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
            
            <div class="menu-section">Profile</div>
            <a href="profile.php">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
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
                <h2>Submit Assignment</h2>
            </div>
            
            <div class="topbar-right">
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
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-header-content">
                    <div class="page-icon">
                        📤
                    </div>
                    <div class="page-title">
                        <h1>Submit Assignment</h1>
                        <p>Upload your completed assignments and track submissions</p>
                    </div>
                </div>
                <a href="dashboard.php" class="back-button">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>

            <!-- Submit Card -->
            <div class="submit-card">
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

                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label for="assignment_id" class="form-label">
                            <i class="fas fa-tasks" style="color: #667eea;"></i> Select Assignment
                        </label>
                        <select name="assignment_id" id="assignment_id" class="form-select" required>
                            <option value="">-- Choose an assignment --</option>
                            <?php foreach ($assignments as $a): ?>
                                <option value="<?= $a['assignment_id'] ?>">
                                    <?= htmlspecialchars($a['title']) ?> 
                                    <?php if ($a['due_date']): ?>
                                        (Due: <?= htmlspecialchars($a['due_date']) ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="file" class="form-label">
                            <i class="fas fa-paperclip" style="color: #f093fb;"></i> Upload File (Optional)
                        </label>
                        <div class="file-input-wrapper">
                            <input type="file" name="file" id="file" onchange="displayFileName(this)">
                            <label for="file" class="file-input-label">
                                <div class="file-input-content">
                                    <i class="fas fa-cloud-upload-alt file-input-icon"></i>
                                    <span class="file-input-text">Click to browse or drag and drop</span>
                                    <span class="file-input-hint">Supported: PDF, DOC, DOCX, Images</span>
                                </div>
                            </label>
                        </div>
                        <div id="fileNameDisplay" class="file-name-display"></div>
                    </div>

                    <div class="mb-4">
                        <label for="submission_text" class="form-label">
                            <i class="fas fa-align-left" style="color: #11998e;"></i> Or Enter Submission Text
                        </label>
                        <textarea name="submission_text" id="submission_text" class="form-control" rows="6" placeholder="Write your answer here..."></textarea>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i>
                        Submit Assignment
                    </button>
                </form>
            </div>

            <!-- Submissions Section -->
            <div class="submissions-section">
                <div class="section-header">
                    <i class="fas fa-history"></i>
                    <h4>Your Submissions</h4>
                </div>

                <?php if ($subs && count($subs) > 0): ?>
                    <table class="submissions-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-book"></i> Assignment</th>
                                <th><i class="fas fa-file"></i> File</th>
                                <th><i class="fas fa-align-left"></i> Text Preview</th>
                                <th><i class="fas fa-clock"></i> Submitted At</th>
                                <th><i class="fas fa-flag"></i> Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subs as $s): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($s['title']) ?></strong></td>
                                    <td>
                                        <?php if ($s['file_path']): ?>
                                            <a href="../<?= htmlspecialchars($s['file_path']) ?>" target="_blank" class="file-link">
                                                <i class="fas fa-download"></i> View File
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #858796;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?= htmlspecialchars(substr($s['submission_text'], 0, 50)) ?>
                                        <?= strlen($s['submission_text']) > 50 ? '...' : '' ?>
                                    </td>
                                    <td><?= htmlspecialchars($s['submitted_at']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($s['status']) ?>">
                                            <?= htmlspecialchars($s['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-submissions">
                        <i class="fas fa-inbox"></i>
                        <p>No submissions yet. Submit your first assignment above!</p>
                    </div>
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
        
        // Display selected file name
        function displayFileName(input) {
            const display = document.getElementById('fileNameDisplay');
            if (input.files && input.files[0]) {
                display.textContent = '📄 ' + input.files[0].name;
                display.classList.add('show');
            } else {
                display.classList.remove('show');
            }
        }
        
        // Keyboard shortcut - ESC to close sidebar on mobile
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.remove('active');
            }
        });
        
        // Add fade-in animation on load
        window.addEventListener('load', function() {
            const elements = document.querySelectorAll('.page-header, .submit-card, .submissions-section');
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