<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();

// Only admin can access
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();


// Get dashboard statistics
$teacherCount = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'teacher'")->fetch()['total'];
$studentCount = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'")->fetch()['total'];
$subjectCount = $db->query("SELECT COUNT(*) as total FROM subjects")->fetch()['total'];
$assignmentCount = $db->query("SELECT COUNT(*) as total FROM assignments WHERE is_active = 1")->fetch()['total'];

// Get recent activities
$recentStudents = $db->query("SELECT s.*, u.created_at FROM students s 
    INNER JOIN users u ON s.user_id = u.user_id 
    ORDER BY u.created_at DESC LIMIT 5")->fetchAll();

$recentAssignments = $db->query("SELECT a.*, sub.subject_name, CONCAT(t.first_name, ' ', t.last_name) as teacher_name 
    FROM assignments a
    INNER JOIN subjects sub ON a.subject_id = sub.subject_id
    INNER JOIN teachers t ON a.teacher_id = t.teacher_id
    ORDER BY a.created_at DESC LIMIT 5")->fetchAll();

// Get current user info
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Online School Portal</title>
    
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
        
        .stat-card .change {
            font-size: 0.85rem;
            color: #1cc88a;
        }
        
        .stat-card .change.negative {
            color: #e74a3b;
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
        
        .student-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        /* Chart Container */
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .action-btn {
            padding: 1.2rem;
            background: white;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            color: #5a5c69;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.8rem;
        }
        
        .action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            color: #764ba2;
        }
        
        .action-btn i {
            font-size: 2rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .action-btn span {
            font-weight: 600;
            font-size: 0.95rem;
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
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-graduation-cap"></i>
            <h4>School Portal</h4>
            <p>Admin Panel</p>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-section">Main Menu</div>
            <a href="dashboard.php" class="active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="manage_teachers.php">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Manage Teachers</span>
            </a>
            <a href="manage_students.php">
                <i class="fas fa-user-graduate"></i>
                <span>View Students</span>
            </a>
            <a href="manage_subjects.php">
                <i class="fas fa-book"></i>
                <span>Manage Subjects</span>
            </a>
            <a href="manage_timetable.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Timetables</span>
            </a>
            
            <div class="menu-section">Academic</div>
            <a href="assignments.php">
                <i class="fas fa-tasks"></i>
                <span>Assignments</span>
            </a>
            <a href="attendance.php">
                <i class="fas fa-clipboard-check"></i>
                <span>Attendance</span>
            </a>
            <a href="grades.php">
                <i class="fas fa-chart-line"></i>
                <span>Grades</span>
            </a>
            
            <div class="menu-section">Communication</div>
            <a href="notifications.php">
                <i class="fas fa-bell"></i>
                <span>Notifications</span>
            </a>
            <a href="message.php">
                <i class="fas fa-envelope"></i>
                <span>Message</span>
            </a>
            
            <div class="menu-section">System</div>
            <a href="reports.php">
                <i class="fas fa-file-alt"></i>
                <span>Reports</span>
            </a>
            <a href="settings.php">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </div>
        
        <div class="sidebar-footer">
            <a href="../logout.php" style="background: rgba(255,255,255,0.1); border-radius: 8px; padding: 0.8rem; text-align: center;">
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
                <h2>Dashboard Overview</h2>
            </div>
            
            <div class="topbar-right">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search...">
                </div>
                
                <div class="notification-icon">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">5</span>
                </div>
                
                <div class="user-profile">
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
                    </div>
                    <div class="user-info">
                        <div class="name"><?= htmlspecialchars($_SESSION['username']) ?></div>
                        <div class="role">Administrator</div>
                    </div>
                    <i class="fas fa-chevron-down" style="color: #858796; font-size: 0.8rem;"></i>
                </div>
            </div>
        </div>
        
        <!-- Content Area -->
        <div class="content-area">
            <!-- Statistics Cards -->
            <div class="stats-row">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <h6>Total Teachers</h6>
                    <div class="number"><?= $teacherCount ?></div>
                    <div class="change">
                        <i class="fas fa-arrow-up"></i> 12% from last month
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h6>Total Students</h6>
                    <div class="number"><?= $studentCount ?></div>
                    <div class="change">
                        <i class="fas fa-arrow-up"></i> 8% from last month
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h6>Active Subjects</h6>
                    <div class="number"><?= $subjectCount ?></div>
                    <div class="change">
                        <i class="fas fa-arrow-up"></i> 3% from last month
                    </div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h6>Active Assignments</h6>
                    <div class="number"><?= $assignmentCount ?></div>
                    <div class="change">
                        <i class="fas fa-arrow-down"></i> 5% from last month
                    </div>
                </div>
            </div>
            
            <!-- Charts and Tables Row -->
            <div class="row">
                <!-- Recent Students -->
                <div class="col-lg-6">
                    <div class="content-card">
                        <div class="card-header-custom">
                            <h5><i class="fas fa-users me-2"></i>Recent Registrations</h5>
                            <span class="badge bg-primary"><?= count($recentStudents) ?> New</span>
                        </div>
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Admission No</th>
                                    <th>Registered</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentStudents)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">No recent registrations</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentStudents as $student): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.8rem;">
                                                    <span class="student-avatar">
                                                        <?= strtoupper(substr($student['first_name'], 0, 1)) ?>
                                                    </span>
                                                    <span><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></span>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($student['admission_number']) ?></td>
                                            <td><?= timeAgo($student['created_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Recent Assignments -->
                <div class="col-lg-6">
                    <div class="content-card">
                        <div class="card-header-custom">
                            <h5><i class="fas fa-clipboard-list me-2"></i>Recent Assignments</h5>
                            <span class="badge bg-success"><?= count($recentAssignments) ?> Active</span>
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
                                <?php if (empty($recentAssignments)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">No assignments yet</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentAssignments as $assignment): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?= htmlspecialchars($assignment['title']) ?></strong>
                                                    <br>
                                                    <small class="text-muted">by <?= htmlspecialchars($assignment['teacher_name']) ?></small>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($assignment['subject_name']) ?></td>
                                            <td><?= formatDate($assignment['due_date']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
           <!-- Activity Chart -->
<div class="content-card">
  <div class="card-header-custom">
    <h5><i class="fas fa-chart-area me-2"></i>System Activity</h5>
    <form method="GET" id="activityForm">
      <select name="range" class="form-select" style="width: auto;" onchange="document.getElementById('activityForm').submit()">
        <option value="7" <?= ($range == '7' ? 'selected' : '') ?>>Last 7 Days</option>
        <option value="30" <?= ($range == '30' ? 'selected' : '') ?>>Last 30 Days</option>
        <option value="90" <?= ($range == '90' ? 'selected' : '') ?>>Last 3 Months</option>
      </select>
    </form>
  </div>
  <div class="chart-container">
    <canvas id="activityChart"></canvas>
  </div>
</div>

            
            <!-- Quick Actions -->
            <div class="content-card">
                <div class="card-header-custom">
                    <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                </div>
                <div class="quick-actions">
                    <a href="manage_teachers.php" class="action-btn">
                        <i class="fas fa-user-plus"></i>
                        <span>Add Teacher</span>
                    </a>
                    <a href="manage_subjects.php" class="action-btn">
                        <i class="fas fa-book-medical"></i>
                        <span>Add Subject</span>
                    </a>
                    <a href="notifications.php" class="action-btn">
                        <i class="fas fa-bullhorn"></i>
                        <span>Send Notification</span>
                    </a>
                    <a href="reports.php" class="action-btn">
                        <i class="fas fa-file-download"></i>
                        <span>Generate Report</span>
                    </a>
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
        
        // Activity Chart
        const ctx = document.getElementById('activityChart').getContext('2d');
        const gradient1 = ctx.createLinearGradient(0, 0, 0, 300);
        gradient1.addColorStop(0, 'rgba(102, 126, 234, 0.4)');
        gradient1.addColorStop(1, 'rgba(118, 75, 162, 0.1)');
        
        const gradient2 = ctx.createLinearGradient(0, 0, 0, 300);
        gradient2.addColorStop(0, 'rgba(17, 153, 142, 0.4)');
        gradient2.addColorStop(1, 'rgba(56, 239, 125, 0.1)');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Students',
                    data: [12, 19, 15, 25, 22, 30, 28],
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
                    label: 'Teachers',
                    data: [5, 8, 6, 10, 9, 12, 11],
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
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y + ' registrations';
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
        
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
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
        
        // Real-time clock in topbar (optional)
        function updateClock() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            const timeString = now.toLocaleDateString('en-US', options);
            
            // You can add a clock element to display this
            // document.getElementById('clock').textContent = timeString;
        }
        
        // Update clock every minute
        updateClock();
        setInterval(updateClock, 60000);
        
        // Notification dropdown (for future implementation)
        const notificationIcon = document.querySelector('.notification-icon');
        notificationIcon.addEventListener('click', function() {
            // Toggle notification dropdown
            console.log('Show notifications');
        });
        
        // User profile dropdown (for future implementation)
        const userProfile = document.querySelector('.user-profile');
        userProfile.addEventListener('click', function() {
            // Toggle user menu
            console.log('Show user menu');
        });
        
        // Add loading state to quick action buttons
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (this.href && !this.href.includes('#')) {
                    const icon = this.querySelector('i');
                    icon.className = 'fas fa-spinner fa-spin';
                }
            });
        });
        
        // Table row click to view details
        document.querySelectorAll('.custom-table tbody tr').forEach(row => {
            row.style.cursor = 'pointer';
            row.addEventListener('click', function() {
                // Add row click handler for viewing details
                console.log('Row clicked:', this);
            });
        });
        
        // Search functionality
        const searchInput = document.querySelector('.search-box input');
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            // Implement search logic here
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
        
        // Prevent sidebar from closing when clicking inside it
        document.getElementById('sidebar').addEventListener('click', function(e) {
            e.stopPropagation();
        });
        
        // Add fade-in animation to cards on load
        window.addEventListener('load', function() {
            const cards = document.querySelectorAll('.stat-card, .content-card');
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