<?php
// ====================================================
// FILE: admin/reports.php
// Admin-only Reports Page (No Grades, No Subject ID)
// ====================================================

define('APP_ACCESS', true); // ✅ Required for auth.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();

// ✅ Allow only Admin access
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php?error=Access+Denied');
    exit;
}

try {
    $db = getDB();
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ====== 1. Attendance Summary ======
try {
    $attendanceReport = $db->query("
        SELECT 
            CONCAT(s.first_name, ' ', s.last_name) AS student_name,
            COUNT(a.attendance_id) AS total_days,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_days,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent_days
        FROM attendance a
        JOIN students s ON a.student_id = s.student_id
        GROUP BY s.student_id
        ORDER BY s.first_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Attendance query failed: " . $e->getMessage());
}

// ====== 2. Assignment Submissions Summary (No Subject ID) ======
try {
    $submissionReport = $db->query("
        SELECT 
            CONCAT(s.first_name, ' ', s.last_name) AS student_name,
            COUNT(sb.submission_id) AS total_submissions
        FROM submissions sb
        JOIN students s ON sb.student_id = s.student_id
        GROUP BY s.student_id
        ORDER BY s.first_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Submission query failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin Dashboard</title>
    
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
        
        /* Header Card */
        .header-card {
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
        
        .header-content h3 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 700;
            color: #5a5c69;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        
        .header-content p {
            margin: 0.5rem 0 0 0;
            color: #858796;
        }
        
        .header-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .btn-export {
            padding: 0.7rem 1.5rem;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-export.primary {
            background: var(--primary-gradient);
            color: white;
        }
        
        .btn-export.success {
            background: var(--success-gradient);
            color: white;
        }
        
        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
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
        
        /* Report Container */
        .report-container {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }
        
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f8f9fc;
        }
        
        .report-header h5 {
            margin: 0;
            font-weight: 600;
            color: #5a5c69;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
            color: #5a5c69;
        }
        
        .custom-table tbody tr {
            transition: all 0.3s ease;
        }
        
        .custom-table tbody tr:hover {
            background: #f8f9fc;
        }
        
        .progress-bar-wrapper {
            width: 100%;
            height: 8px;
            background: #e3e6f0;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        
        .progress-bar-fill {
            height: 100%;
            background: var(--success-gradient);
            border-radius: 10px;
            transition: width 0.5s ease;
        }
        
        .badge-custom {
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
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
            
            .custom-table {
                font-size: 0.85rem;
            }
            
            .custom-table th,
            .custom-table td {
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
                <h2>Reports & Analytics</h2>
            </div>
            
            <div class="topbar-right">
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
            <!-- Header Card -->
            <div class="header-card">
                <div class="header-content">
                    <h3>
                        <i class="fas fa-chart-bar"></i>
                        System Reports
                    </h3>
                    <p>Comprehensive analytics and performance reports</p>
                </div>
                <div class="header-actions">
                    <button class="btn-export primary" onclick="window.print()">
                        <i class="fas fa-print"></i>
                        Print Report
                    </button>
                    <button class="btn-export success" onclick="exportToCSV()">
                        <i class="fas fa-file-excel"></i>
                        Export to Excel
                    </button>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-row">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h6>Total Students</h6>
                    <div class="number"><?= $totalStudents ?></div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <h6>Average Attendance</h6>
                    <div class="number"><?= $avgAttendance ?>%</div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h6>Total Submissions</h6>
                    <div class="number"><?= $totalSubmissions ?></div>
                </div>
            </div>
            
            <!-- Attendance Report -->
            <div class="report-container">
                <div class="report-header">
                    <h5>
                        <i class="fas fa-clipboard-list"></i>
                        Attendance Report
                    </h5>
                    <span class="badge-custom badge-success"><?= count($attendanceReport) ?> Students</span>
                </div>
                
                <div style="overflow-x: auto;">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Total Days</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Attendance Rate</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($attendanceReport)): ?>
                                <?php foreach ($attendanceReport as $row): ?>
                                    <?php 
                                        $attendanceRate = $row['total_days'] > 0 ? round(($row['present_days'] / $row['total_days']) * 100, 1) : 0;
                                        $statusClass = $attendanceRate >= 75 ? 'badge-success' : ($attendanceRate >= 50 ? 'badge-warning' : 'badge-danger');
                                        $statusText = $attendanceRate >= 75 ? 'Good' : ($attendanceRate >= 50 ? 'Average' : 'Poor');
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['student_name']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['total_days']) ?></td>
                                        <td><span class="badge-custom badge-success"><?= htmlspecialchars($row['present_days']) ?></span></td>
                                        <td><span class="badge-custom badge-danger"><?= htmlspecialchars($row['absent_days']) ?></span></td>
                                        <td>
                                            <div><?= $attendanceRate ?>%</div>
                                            <div class="progress-bar-wrapper">
                                                <div class="progress-bar-fill" style="width: <?= $attendanceRate ?>%"></div>
                                            </div>
                                        </td>
                                        <td><span class="badge-custom <?= $statusClass ?>"><?= $statusText ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #858796; padding: 2rem;">
                                        <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 1rem;"></i>
                                        No attendance records found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Submissions Report -->
            <div class="report-container">
                <div class="report-header">
                    <h5>
                        <i class="fas fa-tasks"></i>
                        Assignment Submissions Report
                    </h5>
                    <span class="badge-custom badge-success"><?= count($submissionReport) ?> Students</span>
                </div>
                
                <div style="overflow-x: auto;">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Total Submissions</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($submissionReport)): ?>
                                <?php foreach ($submissionReport as $row): ?>
                                    <?php 
                                        $submissions = $row['total_submissions'];
                                        $performanceClass = $submissions >= 10 ? 'badge-success' : ($submissions >= 5 ? 'badge-warning' : 'badge-danger');
                                        $performanceText = $submissions >= 10 ? 'Excellent' : ($submissions >= 5 ? 'Good' : 'Needs Improvement');
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['student_name']) ?></strong></td>
                                        <td>
                                            <span class="badge-custom badge-success">
                                                <i class="fas fa-file-alt"></i> <?= htmlspecialchars($submissions) ?>
                                            </span>
                                        </td>
                                        <td><span class="badge-custom <?= $performanceClass ?>"><?= $performanceText ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; color: #858796; padding: 2rem;">
                                        <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 1rem;"></i>
                                        No submission records found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
        
        // Export to CSV
        function exportToCSV() {
            let csvContent = "data:text/csv;charset=utf-8,";
            
            // Attendance Report
            csvContent += "ATTENDANCE REPORT\n";
            csvContent += "Student Name,Total Days,Present,Absent,Attendance Rate\n";
            
            <?php foreach ($attendanceReport as $row): ?>
                <?php $rate = $row['total_days'] > 0 ? round(($row['present_days'] / $row['total_days']) * 100, 1) : 0; ?>
                csvContent += "<?= addslashes($row['student_name']) ?>,<?= $row['total_days'] ?>,<?= $row['present_days'] ?>,<?= $row['absent_days'] ?>,<?= $rate ?>%\n";
            <?php endforeach; ?>
            
            csvContent += "\n\nASSIGNMENT SUBMISSIONS REPORT\n";
            csvContent += "Student Name,Total Submissions\n";
            
            <?php foreach ($submissionReport as $row): ?>
                csvContent += "<?= addslashes($row['student_name']) ?>,<?= $row['total_submissions'] ?>\n";
            <?php endforeach; ?>
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "school_reports_" + new Date().toISOString().split('T')[0] + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
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
        
        // Animate progress bars on load
        window.addEventListener('load', function() {
            const progressBars = document.querySelectorAll('.progress-bar-fill');
            progressBars.forEach((bar, index) => {
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.width = width;
                }, index * 100);
            });
        });
        
        // Animate stat numbers on scroll
        function animateValue(element, start, end, duration) {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const current = Math.floor(progress * (end - start) + start);
                element.textContent = element.textContent.includes('%') ? current + '%' : current;
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
                    const finalValue = parseInt(text.replace(/[^0-9]/g, ''));
                    const hasPercent = text.includes('%');
                    animateValue(number, 0, finalValue, 1000);
                    observer.unobserve(number);
                }
            });
        });
        
        document.querySelectorAll('.stat-card .number').forEach(number => {
            observer.observe(number);
        });
        
        // Add fade-in animation to cards on load
        window.addEventListener('load', function() {
            const cards = document.querySelectorAll('.stat-card, .report-container');
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
        
        // Print functionality - hide sidebar and buttons
        window.onbeforeprint = function() {
            document.querySelector('.sidebar').style.display = 'none';
            document.querySelector('.topbar').style.display = 'none';
            document.querySelector('.header-actions').style.display = 'none';
            document.querySelector('.main-content').style.marginLeft = '0';
        };
        
        window.onafterprint = function() {
            document.querySelector('.sidebar').style.display = 'block';
            document.querySelector('.topbar').style.display = 'flex';
            document.querySelector('.header-actions').style.display = 'flex';
            document.querySelector('.main-content').style.marginLeft = 'var(--sidebar-width)';
        };
    </script>
</body>
</html>