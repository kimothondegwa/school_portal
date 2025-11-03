<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();

// ✅ Only teachers can access
if (!isLoggedIn() || !isTeacher()) {
    header('Location: ../login.php?error=Access+Denied');
    exit;
}

$user = getCurrentUser();
$db = getDB();

$success = '';
$error = '';

// Fetch all students (you can adjust if you have classes)
$students = $db->query("SELECT student_id, CONCAT(first_name, ' ', last_name) AS full_name FROM students ORDER BY first_name ASC")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['attendance_date'] ?? date('Y-m-d');
    $attendance = $_POST['attendance'] ?? [];

    if (empty($attendance)) {
        $error = "Please mark attendance for at least one student.";
    } else {
        try {
            foreach ($attendance as $student_id => $status) {
                $student_id = (int)$student_id;
                $status = ($status === 'Present') ? 'Present' : 'Absent';

                // Check if attendance already exists for this student on this date
                $existing = $db->query("SELECT attendance_id FROM attendance WHERE student_id = :student_id AND date = :date")
                               ->bind(':student_id', $student_id)
                               ->bind(':date', $date)
                               ->fetch();

                if ($existing) {
                    // Update existing record
                    $db->query("UPDATE attendance SET status = :status WHERE attendance_id = :attendance_id")
                       ->bind(':status', $status)
                       ->bind(':attendance_id', $existing['attendance_id'])
                       ->execute();
                } else {
                    // Insert new record
                    $db->query("INSERT INTO attendance (student_id, date, status) VALUES (:student_id, :date, :status)")
                       ->bind(':student_id', $student_id)
                       ->bind(':date', $date)
                       ->bind(':status', $status)
                       ->execute();
                }
            }

            $success = "Attendance saved successfully!";
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
<title>Mark Attendance - Teacher Dashboard</title>

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
    background: var(--warning-gradient);
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

/* Attendance Card */
.attendance-card {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
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
    margin-bottom: 2rem;
}

.form-label {
    font-weight: 600;
    color: #5a5c69;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-control {
    border: 2px solid #e3e6f0;
    border-radius: 10px;
    padding: 0.75rem 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #764ba2;
    box-shadow: 0 0 0 3px rgba(118, 75, 162, 0.1);
    outline: none;
}

/* Attendance Table */
.attendance-table-wrapper {
    overflow-x: auto;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.attendance-table {
    width: 100%;
    margin-bottom: 0;
    background: white;
}

.attendance-table thead {
    background: var(--warning-gradient);
    color: white;
}

.attendance-table thead th {
    padding: 1rem;
    font-weight: 600;
    border: none;
    text-align: left;
}

.attendance-table tbody tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid #e3e6f0;
}

.attendance-table tbody tr:hover {
    background: #f8f9fc;
    transform: scale(1.01);
}

.attendance-table tbody td {
    padding: 1rem;
    vertical-align: middle;
}

.student-name {
    font-weight: 600;
    color: #5a5c69;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.student-name i {
    color: #764ba2;
}

/* Custom Radio Buttons */
.radio-group {
    display: flex;
    gap: 2rem;
    align-items: center;
}

.custom-radio {
    position: relative;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.custom-radio input[type="radio"] {
    width: 24px;
    height: 24px;
    cursor: pointer;
    accent-color: #11998e;
}

.custom-radio.present input[type="radio"]:checked {
    accent-color: #11998e;
}

.custom-radio.absent input[type="radio"]:checked {
    accent-color: #e74a3b;
}

.custom-radio label {
    cursor: pointer;
    margin: 0;
    font-weight: 500;
    color: #5a5c69;
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

.btn-success {
    background: var(--success-gradient);
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(17, 153, 142, 0.3);
}

/* Stats Section */
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

.stat-icon.date {
    background: linear-gradient(135deg, rgba(240, 147, 251, 0.2) 0%, rgba(245, 87, 108, 0.2) 100%);
    color: #f093fb;
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
    
    .page-header {
        flex-direction: column;
        text-align: center;
    }
    
    .radio-group {
        flex-direction: column;
        gap: 0.5rem;
        align-items: flex-start;
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
            <a href="upload_assignment.php">
                <i class="fas fa-file-upload"></i>
                <span>Upload Assignments</span>
            </a>
            <a href="create_quiz.php">
                <i class="fas fa-brain"></i>
                <span>Create Quizzes</span>
            </a>
            <a href="mark_attendance.php" class="active">
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
                <h2>Mark Attendance</h2>
            </div>
            
            <div class="topbar-right">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search...">
                </div>
                
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
                <div class="page-title">
                    <div class="page-icon">
                        ✅
                    </div>
                    <div>
                        <h3>Mark Student Attendance</h3>
                        <p>Record and track student attendance for today</p>
                    </div>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon students">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h4><?= count($students) ?></h4>
                        <p>Total Students</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon date">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="stat-content">
                        <h4><?= date('M d, Y') ?></h4>
                        <p>Today's Date</p>
                    </div>
                </div>
            </div>

            <!-- Attendance Card -->
            <div class="attendance-card">
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

                <form method="POST" id="attendanceForm">
                    <div class="form-section">
                        <label for="attendance_date" class="form-label">
                            <i class="fas fa-calendar-alt"></i>
                            Select Date
                        </label>
                        <input type="date" 
                               name="attendance_date" 
                               id="attendance_date" 
                               class="form-control" 
                               value="<?= date('Y-m-d') ?>" 
                               required
                               style="max-width: 300px;">
                    </div>

                    <div class="attendance-table-wrapper">
                        <table class="attendance-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Student Name</th>
                                    <th style="width: 200px;">Attendance Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $index => $student): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td>
                                            <div class="student-name">
                                                <i class="fas fa-user-graduate"></i>
                                                <?= htmlspecialchars($student['full_name']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="radio-group">
                                                <div class="custom-radio present">
                                                    <input type="radio" 
                                                           name="attendance[<?= $student['student_id'] ?>]" 
                                                           value="Present" 
                                                           id="present_<?= $student['student_id'] ?>"
                                                           required>
                                                    <label for="present_<?= $student['student_id'] ?>">
                                                        <i class="fas fa-check"></i> Present
                                                    </label>
                                                </div>
                                                <div class="custom-radio absent">
                                                    <input type="radio" 
                                                           name="attendance[<?= $student['student_id'] ?>]" 
                                                           value="Absent"
                                                           id="absent_<?= $student['student_id'] ?>">
                                                    <label for="absent_<?= $student['student_id'] ?>">
                                                        <i class="fas fa-times"></i> Absent
                                                    </label>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="action-buttons">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i>
                            Save Attendance
                        </button>
                        <button type="button" class="btn btn-primary" onclick="markAllPresent()">
                            <i class="fas fa-check-double"></i>
                            Mark All Present
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Back to Dashboard
                        </a>
                    </div>
                </form>
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

        // Mark all students as present
        function markAllPresent() {
            const presentRadios = document.querySelectorAll('input[type="radio"][value="Present"]');
            presentRadios.forEach(radio => {
                radio.checked = true;
            });
        }

        // Search functionality
        const searchInput = document.querySelector('.search-box input');
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.attendance-table tbody tr');
            
            rows.forEach(row => {
                const studentName = row.querySelector('.student-name').textContent.toLowerCase();
                if (studentName.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
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

        // Form submission confirmation
        document.getElementById('attendanceForm').addEventListener('submit', function(e) {
            const checkedRadios = document.querySelectorAll('input[type="radio"]:checked');
            const totalStudents = <?= count($students) ?>;
            
            if (checkedRadios.length < totalStudents) {
                if (!confirm('You have not marked attendance for all students. Do you want to continue?')) {
                    e.preventDefault();
                }
            }
        });

        // Add fade-in animation on load
        window.addEventListener('load', function() {
            const elements = document.querySelectorAll('.page-header, .stat-card, .attendance-card');
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

        // Count present/absent in real-time
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                updateCounts();
            });
        });

        function updateCounts() {
            const presentCount = document.querySelectorAll('input[type="radio"][value="Present"]:checked').length;
            const absentCount = document.querySelectorAll('input[type="radio"][value="Absent"]:checked').length;
            
            console.log(`Present: ${presentCount}, Absent: ${absentCount}`);
        }