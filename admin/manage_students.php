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
$error = '';
$success = '';

// Handle account actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $action = $_GET['action'];

    try {
        if ($action === 'activate') {
            $db->query("UPDATE users SET is_active = 1 WHERE user_id = :id AND role = 'student'")
               ->bind(':id', $id)
               ->execute();
            logActivity($_SESSION['user_id'], 'activate_student', 'users', $id, 'Activated student account');
            $success = "Student account activated successfully.";
        } elseif ($action === 'deactivate') {
            $db->query("UPDATE users SET is_active = 0 WHERE user_id = :id AND role = 'student'")
               ->bind(':id', $id)
               ->execute();
            logActivity($_SESSION['user_id'], 'deactivate_student', 'users', $id, 'Deactivated student account');
            $success = "Student account deactivated successfully.";
        } elseif ($action === 'delete') {
            $db->query("DELETE FROM users WHERE user_id = :id AND role = 'student'")
               ->bind(':id', $id)
               ->execute();
            logActivity($_SESSION['user_id'], 'delete_student', 'users', $id, 'Deleted student account');
            $success = "Student account deleted successfully.";
        }
    } catch (PDOException $e) {
        $error = "Action failed: " . $e->getMessage();
    }
}

// Search and filter
$searchTerm = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? 'all';
$classFilter = $_GET['class'] ?? 'all';

// Build query
$sql = "SELECT u.*, s.first_name, s.last_name, s.admission_number, s.phone_number, s.class_level, s.profile_picture 
        FROM users u 
        LEFT JOIN students s ON u.user_id = s.user_id 
        WHERE u.role = 'student'";

if (!empty($searchTerm)) {
    $sql .= " AND (u.username LIKE :search OR u.email LIKE :search OR s.first_name LIKE :search OR s.last_name LIKE :search OR s.admission_number LIKE :search)";
}

if ($statusFilter !== 'all') {
    $sql .= " AND u.is_active = :status";
}

if ($classFilter !== 'all') {
    $sql .= " AND s.class_level = :class";
}

$sql .= " ORDER BY u.created_at DESC";

try {
    $query = $db->query($sql);
    
    if (!empty($searchTerm)) {
        $query->bind(':search', "%$searchTerm%");
    }
    if ($statusFilter !== 'all') {
        $query->bind(':status', $statusFilter === 'active' ? 1 : 0);
    }
    if ($classFilter !== 'all') {
        $query->bind(':class', $classFilter);
    }
    
    $students = $query->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching students: " . $e->getMessage();
    $students = [];
}

// Get statistics
$totalStudents = count($students);
$activeStudents = count(array_filter($students, fn($s) => $s['is_active'] == 1));
$inactiveStudents = $totalStudents - $activeStudents;

// Get unique class levels for filter
$classLevels = $db->query("SELECT DISTINCT class_level FROM students WHERE class_level IS NOT NULL ORDER BY class_level")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - Online School Portal</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --sidebar-width: 280px;
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fc;
        }
        
        /* Sidebar */
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
        }
        
        .sidebar-header {
            padding: 2rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header i {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }
        
        .sidebar-header h4 {
            margin: 0;
            font-size: 1.3rem;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 0.9rem 1.5rem;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.15);
            border-left-color: white;
        }
        
        .sidebar-menu a i {
            margin-right: 1rem;
            width: 20px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }
        
        .topbar {
            background: white;
            padding: 1.5rem 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }
        
        .topbar h2 {
            margin: 0;
            color: #5a5c69;
            font-weight: 600;
        }
        
        .content-area {
            padding: 0 2rem 2rem;
        }
        
        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
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
        
        .stat-card.primary::before { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-card.success::before { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .stat-card.danger::before { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }
        
        .stat-card.primary .stat-icon { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-card.success .stat-icon { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .stat-card.danger .stat-icon { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        
        .stat-card h6 {
            margin: 0;
            color: #858796;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        
        .stat-card .number {
            font-size: 2rem;
            font-weight: 700;
            color: #5a5c69;
            margin: 0.5rem 0;
        }
        
        /* Main Card */
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
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
        }
        
        /* Filters */
        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .search-box {
            position: relative;
            flex: 1;
            min-width: 250px;
        }
        
        .search-box input {
            width: 100%;
            padding: 0.7rem 1rem 0.7rem 2.8rem;
            border: 2px solid #e3e6f0;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #764ba2;
            box-shadow: 0 0 0 3px rgba(118, 75, 162, 0.1);
        }
        
        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #858796;
        }
        
        .filter-select {
            padding: 0.7rem 1rem;
            border: 2px solid #e3e6f0;
            border-radius: 10px;
            background: white;
            cursor: pointer;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: #764ba2;
        }
        
        /* Table */
        .students-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 0.5rem;
        }
        
        .students-table thead th {
            padding: 1rem;
            background: #f8f9fc;
            font-weight: 600;
            color: #5a5c69;
            font-size: 0.85rem;
            text-transform: uppercase;
            border: none;
        }
        
        .students-table thead th:first-child {
            border-radius: 10px 0 0 10px;
        }
        
        .students-table thead th:last-child {
            border-radius: 0 10px 10px 0;
        }
        
        .students-table tbody tr {
            background: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }
        
        .students-table tbody tr:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .students-table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border: none;
        }
        
        .students-table tbody td:first-child {
            border-radius: 10px 0 0 10px;
        }
        
        .students-table tbody td:last-child {
            border-radius: 0 10px 10px 0;
        }
        
        .student-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            margin-right: 0.8rem;
        }
        
        .student-info {
            display: flex;
            align-items: center;
        }
        
        .student-details .name {
            font-weight: 600;
            color: #5a5c69;
            margin-bottom: 0.2rem;
        }
        
        .student-details .email {
            font-size: 0.85rem;
            color: #858796;
        }
        
        .badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge.active {
            background: #d4edda;
            color: #155724;
        }
        
        .badge.inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .action-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            margin-right: 0.5rem;
        }
        
        .action-btn.view {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .action-btn.activate {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .action-btn.deactivate {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .action-btn.delete {
            background: #ffebee;
            color: #c62828;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #858796;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #e3e6f0;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .filters {
                flex-direction: column;
            }
            
            .students-table {
                font-size: 0.85rem;
            }
            
            .action-btn {
                padding: 0.4rem 0.6rem;
                font-size: 0.75rem;
                margin-bottom: 0.3rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-graduation-cap"></i>
            <h4>School Portal</h4>
            <p>Admin Panel</p>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="manage_teachers.php">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Manage Teachers</span>
            </a>
            <a href="manage_students.php" class="active">
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
            <a href="reports.php">
                <i class="fas fa-file-alt"></i>
                <span>Reports</span>
            </a>
            <a href="../logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <h2><i class="fas fa-user-graduate me-2"></i>Manage Students</h2>
        </div>
        
        <!-- Content Area -->
        <div class="content-area">
            <!-- Messages -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Stats -->
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
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h6>Active Students</h6>
                    <div class="number"><?= $activeStudents ?></div>
                </div>
                
                <div class="stat-card danger">
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <h6>Inactive Students</h6>
                    <div class="number"><?= $inactiveStudents ?></div>
                </div>
            </div>
            
            <!-- Main Card -->
            <div class="content-card">
                <div class="card-header-custom">
                    <h5><i class="fas fa-list me-2"></i>All Students</h5>
                    <span class="badge bg-primary"><?= $totalStudents ?> Total</span>
                </div>
                
                <!-- Filters -->
                <form method="GET" action="">
                    <div class="filters">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Search by name, email, or admission number..." value="<?= htmlspecialchars($searchTerm) ?>">
                        </div>
                        
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                        </select>
                        
                        <select name="class" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?= $classFilter === 'all' ? 'selected' : '' ?>>All Classes</option>
                            <?php foreach ($classLevels as $level): ?>
                                <option value="<?= htmlspecialchars($level['class_level']) ?>" <?= $classFilter === $level['class_level'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($level['class_level']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <button type="submit" class="action-btn view">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        
                        <?php if (!empty($searchTerm) || $statusFilter !== 'all' || $classFilter !== 'all'): ?>
                            <a href="manage_students.php" class="action-btn delete">
                                <i class="fas fa-times me-1"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
                
                <!-- Students Table -->
                <?php if (count($students) > 0): ?>
                    <table class="students-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Admission No</th>
                                <th>Class</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <div class="student-info">
                                            <div class="student-avatar">
                                                <?= strtoupper(substr($student['first_name'] ?? $student['username'], 0, 1)) ?>
                                            </div>
                                            <div class="student-details">
                                                <div class="name">
                                                    <?= htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')) ?: htmlspecialchars($student['username']) ?>
                                                </div>
                                                <div class="email"><?= htmlspecialchars($student['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($student['admission_number'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($student['class_level'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($student['phone_number'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="badge <?= $student['is_active'] ? 'active' : 'inactive' ?>">
                                            <?= $student['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td><?= timeAgo($student['created_at']) ?></td>
                                    <td>
                                        <a href="view_student.php?id=<?= $student['user_id'] ?>" class="action-btn view" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($student['is_active']): ?>
                                            <a href="?action=deactivate&id=<?= $student['user_id'] ?>" 
                                               class="action-btn deactivate" 
                                               title="Deactivate"
                                               onclick="return confirm('Deactivate this student account?');">
                                                <i class="fas fa-ban"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="?action=activate&id=<?= $student['user_id'] ?>" 
                                               class="action-btn activate" 
                                               title="Activate">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="?action=delete&id=<?= $student['user_id'] ?>" 
                                           class="action-btn delete" 
                                           title="Delete"
                                           onclick="return confirm('⚠️ Delete this student permanently? This cannot be undone!');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <h5>No Students Found</h5>
                        <p>No students match your search criteria.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Highlight search results
        const searchTerm = "<?= htmlspecialchars($searchTerm) ?>";
        if (searchTerm) {
            const cells = document.querySelectorAll('.students-table tbody td');
            cells.forEach(cell => {
                const text = cell.textContent;
                if (text.toLowerCase().includes(searchTerm.toLowerCase())) {
                    cell.style.background = '#fff3cd';
                }
            });
        }
    </script>
</body>
</html>