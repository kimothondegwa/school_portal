<?php
// ====================================================
// FILE: teacher/upload_assignment.php
// Teacher Upload/Create Assignments
// ====================================================

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
$success = '';
$error = '';

// Fetch teacher_id
$db->query("SELECT teacher_id FROM teachers WHERE user_id = :user_id");
$db->bind(':user_id', $user['user_id']);
$teacherRow = $db->fetch();

if (!$teacherRow) {
    $error = "Teacher profile not found. Please contact the administrator.";
} else {
    $teacher_id = $teacherRow['teacher_id'];
}

// Fetch subjects
$db->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name ASC");
$subjects = $db->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date = $_POST['due_date'] ?? '';
    $total_marks = intval($_POST['total_marks'] ?? 100);
    $file = $_FILES['assignment_file'] ?? null;

    // Validate inputs
    if (!$subject_id || empty($title) || empty($due_date) || !$file || $file['error'] !== UPLOAD_ERR_OK) {
        $error = "Please fill in all required fields and upload a valid file.";
    } else {
        // Validate file type and size
        $allowed = ['pdf','doc','docx','ppt','pptx','jpg','jpeg','png'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = "Invalid file type. Allowed: " . implode(', ', $allowed);
        } elseif ($file['size'] > 20*1024*1024) {
            $error = "File size exceeds 20MB limit.";
        } else {
            $uploadDir = __DIR__ . '/../uploads/assignments/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $filename = time() . '_' . basename($file['name']);
            $targetPath = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                try {
                    // Insert into assignments table
                    $db->query("INSERT INTO assignments (teacher_id, subject_id, title, description, due_date, total_marks, file_path, created_at, is_active)
                                VALUES (:teacher_id, :subject_id, :title, :description, :due_date, :total_marks, :file_path, NOW(), 1)");
                    $db->bind(':teacher_id', $teacher_id);
                    $db->bind(':subject_id', $subject_id);
                    $db->bind(':title', $title);
                    $db->bind(':description', $description);
                    $db->bind(':due_date', $due_date);
                    $db->bind(':total_marks', $total_marks);
                    $db->bind(':file_path', $filename);
                    $db->execute();
                    $success = "Assignment uploaded successfully!";
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            } else {
                $error = "Failed to move uploaded file.";
            }
        }
    }
}

// Fetch teacher's assignments
if (!empty($teacher_id)) {
    $db->query("SELECT a.*, s.subject_name 
                FROM assignments a 
                JOIN subjects s ON a.subject_id = s.subject_id 
                WHERE a.teacher_id = :teacher_id 
                ORDER BY a.created_at DESC");
    $db->bind(':teacher_id', $teacher_id);
    $my_assignments = $db->fetchAll();
} else {
    $my_assignments = [];
}
?>
<!-- HTML content is the same as you provided above, all dynamic PHP variables are now integrated -->



<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Upload Assignment - Teacher Dashboard</title>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
    --sidebar-width: 280px;
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
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

/* Upload Card */
.upload-card {
    background: white;
    border-radius: 15px;
    padding: 2.5rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
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
    background: linear-gradient(135deg, rgba(79, 172, 254, 0.05) 0%, rgba(0, 242, 254, 0.05) 100%);
    border: 2px dashed #4facfe;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
}

.file-input-label:hover {
    background: linear-gradient(135deg, rgba(79, 172, 254, 0.1) 0%, rgba(0, 242, 254, 0.1) 100%);
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
    color: #4facfe;
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
    background: var(--info-gradient);
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
    box-shadow: 0 8px 20px rgba(79, 172, 254, 0.4);
}

.btn-submit:active {
    transform: translateY(0);
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
    
    .upload-card {
        padding: 1.5rem;
    }
}
</style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-chalkboard-teacher"></i>
            <h4>School Portal</h4>
            <p>Teacher Panel</p>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-section">Main Menu</div>
            <a href="dashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="upload_assignment.php" class="active">
                <i class="fas fa-file-upload"></i>
                <span>Upload Assignments</span>
            </a>
            <a href="create_quiz.php">
                <i class="fas fa-brain"></i>
                <span>Create Quizzes</span>
            </a>
            <a href="mark_attendance.php">
                <i class="fas fa-user-check"></i>
                <span>Mark Attendance</span>
            </a>
            
            <div class="menu-section">Academic</div>
            <a href="mark_grades.php">
                <i class="fas fa-award"></i>
                <span>Grade Students</span>
            </a>
            <a href="my_classes.php">
                <i class="fas fa-users"></i>
                <span>My Classes</span>
            </a>
            <a href="schedule.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Class Schedule</span>
            </a>
            
            <div class="menu-section">Communication</div>
            <a href="notifications.php">
                <i class="fas fa-bell"></i>
                <span>Notifications</span>
            </a>
            <a href="messages.php">
                <i class="fas fa-envelope"></i>
                <span>Messages</span>
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
            <h2>Upload Assignment</h2>
        </div>
        
        <div class="topbar-right">
            <div class="notification-icon">
                <i class="fas fa-bell"></i>
                <span class="notification-badge">3</span>
            </div>
            
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
                    📤
                </div>
                <div class="page-title">
                    <h1>Upload Assignment</h1>
                    <p>Share assignments and materials with your students</p>
                </div>
            </div>
            <a href="dashboard.php" class="back-button">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
        </div>

        <!-- Upload Card -->
        <div class="upload-card">
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="mb-4">
                    <label for="subject_id" class="form-label">
                        <i class="fas fa-book" style="color: #667eea;"></i> Select Subject
                    </label>
                    <select name="subject_id" id="subject_id" class="form-select" required>
                        <option value="">-- Choose a subject --</option>
                        <?php foreach ($subjects as $sub): ?>
                            <option value="<?= $sub['subject_id'] ?>"><?= htmlspecialchars($sub['subject_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="assignment_file" class="form-label">
                        <i class="fas fa-file-upload" style="color: #4facfe;"></i> Assignment File
                    </label>
                    <div class="file-input-wrapper">
                        <input type="file" name="assignment_file" id="assignment_file" required onchange="displayFileName(this)">
                        <label for="assignment_file" class="file-input-label">
                            <div class="file-input-content">
                                <i class="fas fa-cloud-upload-alt file-input-icon"></i>
                                <span class="file-input-text">Click to browse or drag and drop</span>
                                <span class="file-input-hint">Supported: PDF, DOC, DOCX, PPT, PPTX, JPG, PNG</span>
                            </div>
                        </label>
                    </div>
                    <div id="fileNameDisplay" class="file-name-display"></div>
                </div>

                <div class="mb-4">
                    <label for="description" class="form-label">
                        <i class="fas fa-align-left" style="color: #11998e;"></i> Description (Optional)
                    </label>
                    <textarea name="description" id="description" class="form-control" rows="4" placeholder="Add any instructions or notes for students..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
                <div class="mb-4">
    <label for="title" class="form-label">
        <i class="fas fa-heading" style="color: #667eea;"></i> Assignment Title
    </label>
    <input type="text" name="title" id="title" class="form-control" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
</div>

<div class="mb-4">
    <label for="due_date" class="form-label">
        <i class="fas fa-calendar-alt" style="color: #4facfe;"></i> Due Date
    </label>
    <input type="date" name="due_date" id="due_date" class="form-control" required value="<?= htmlspecialchars($_POST['due_date'] ?? '') ?>">
</div>


                <button type="submit" class="btn-submit">
                    <i class="fas fa-upload"></i>
                    Upload Assignment
                </button>
            </form>

            <!-- Info Box -->
            <div class="info-box">
                <h6>
                    <i class="fas fa-info-circle"></i>
                    Upload Guidelines
                </h6>
                <ul>
                    <li>Maximum file size: 10MB</li>
                    <li>Supported formats: PDF, Word, PowerPoint, Images</li>
                    <li>Files will be accessible to all students in the selected subject</li>
                    <li>You can upload multiple assignments by repeating this process</li>
                </ul>
            </div>
        </div>
    </div>
</div>
v>
    
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
            const elements = document.querySelectorAll('.page-header, .upload-card');
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