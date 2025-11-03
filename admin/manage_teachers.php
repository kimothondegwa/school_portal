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

// Handle teacher creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_teacher'])) {
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name = sanitize($_POST['last_name'] ?? '');
    $staff_number = sanitize($_POST['staff_number'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $department = sanitize($_POST['department'] ?? '');
    $qualification = sanitize($_POST['qualification'] ?? '');

    if (empty($username) || empty($email) || empty($first_name) || empty($last_name) || empty($staff_number)) {
        $error = 'Please fill in all required fields.';
    } elseif (!isValidEmail($email)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Check if username or email already exists
            $existing = $db->query("SELECT user_id FROM users WHERE username = :u OR email = :e")
                          ->bind(':u', $username)
                          ->bind(':e', $email)
                          ->fetch();
            
            if ($existing) {
                $error = 'Username or email already exists.';
            } else {
                // Start transaction
                $db->beginTransaction();
                
                // Create user account
                $password_hash = hashPassword(empty($password) ? 'teacher123' : $password);
                
                $db->query("INSERT INTO users (username, email, password_hash, role, is_active, created_at) 
                            VALUES (:u, :e, :p, 'teacher', 1, NOW())")
                    ->bind(':u', $username)
                    ->bind(':e', $email)
                    ->bind(':p', $password_hash)
                    ->execute();
                
                $user_id = $db->lastInsertId();
                
                // Create teacher profile
                $db->query("INSERT INTO teachers (user_id, staff_number, first_name, last_name, phone_number, department, qualification, hire_date) 
                            VALUES (:uid, :sn, :fn, :ln, :phone, :dept, :qual, CURDATE())")
                    ->bind(':uid', $user_id)
                    ->bind(':sn', $staff_number)
                    ->bind(':fn', $first_name)
                    ->bind(':ln', $last_name)
                    ->bind(':phone', $phone)
                    ->bind(':dept', $department)
                    ->bind(':qual', $qualification)
                    ->execute();
                
                $db->commit();
                
                logActivity($_SESSION['user_id'], 'create_teacher', 'teachers', $user_id, "Created teacher: $first_name $last_name");
                
                $success = "Teacher account created successfully. Default password: " . (empty($password) ? 'teacher123' : 'Custom password set');
            }
        } catch (PDOException $e) {
            $db->rollback();
            $error = "Error creating teacher: " . $e->getMessage();
        }
    }
}

// Handle teacher actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $action = $_GET['action'];

    try {
        if ($action === 'activate') {
            $db->query("UPDATE users SET is_active = 1 WHERE user_id = :id AND role = 'teacher'")
               ->bind(':id', $id)
               ->execute();
            logActivity($_SESSION['user_id'], 'activate_teacher', 'users', $id, 'Activated teacher account');
            $success = "Teacher account activated successfully.";
        } elseif ($action === 'deactivate') {
            $db->query("UPDATE users SET is_active = 0 WHERE user_id = :id AND role = 'teacher'")
               ->bind(':id', $id)
               ->execute();
            logActivity($_SESSION['user_id'], 'deactivate_teacher', 'users', $id, 'Deactivated teacher account');
            $success = "Teacher account deactivated successfully.";
        } elseif ($action === 'delete') {
            $db->query("DELETE FROM users WHERE user_id = :id AND role = 'teacher'")
               ->bind(':id', $id)
               ->execute();
            logActivity($_SESSION['user_id'], 'delete_teacher', 'users', $id, 'Deleted teacher account');
            $success = "Teacher account deleted successfully.";
        }
    } catch (PDOException $e) {
        $error = "Action failed: " . $e->getMessage();
    }
}

// Search and filter
$searchTerm = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? 'all';
$deptFilter = $_GET['department'] ?? 'all';

// Build query
$sql = "SELECT u.*, t.teacher_id, t.staff_number, t.first_name, t.last_name, t.phone_number, t.department, t.qualification, t.hire_date
        FROM users u 
        LEFT JOIN teachers t ON u.user_id = t.user_id 
        WHERE u.role = 'teacher'";

if (!empty($searchTerm)) {
    $sql .= " AND (u.username LIKE :search OR u.email LIKE :search OR t.first_name LIKE :search OR t.last_name LIKE :search OR t.staff_number LIKE :search)";
}

if ($statusFilter !== 'all') {
    $sql .= " AND u.is_active = :status";
}

if ($deptFilter !== 'all') {
    $sql .= " AND t.department = :dept";
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
    if ($deptFilter !== 'all') {
        $query->bind(':dept', $deptFilter);
    }
    
    $teachers = $query->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching teachers: " . $e->getMessage();
    $teachers = [];
}

// Get statistics
$totalTeachers = count($teachers);
$activeTeachers = count(array_filter($teachers, fn($t) => $t['is_active'] == 1));
$inactiveTeachers = $totalTeachers - $activeTeachers;

// Get unique departments for filter
$departments = $db->query("SELECT DISTINCT department FROM teachers WHERE department IS NOT NULL ORDER BY department")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers - Online School Portal</title>
    
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
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
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
        
        /* Content Card */
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
        }
        
        .btn-add {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        /* Form Styles */
        .form-label {
            font-weight: 600;
            color: #5a5c69;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .form-control, .form-select {
            padding: 0.7rem 1rem;
            border: 2px solid #e3e6f0;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #764ba2;
            box-shadow: 0 0 0 3px rgba(118, 75, 162, 0.1);
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
        
        /* Table */
        .teachers-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 0.5rem;
        }
        
        .teachers-table thead th {
            padding: 1rem;
            background: #f8f9fc;
            font-weight: 600;
            color: #5a5c69;
            font-size: 0.85rem;
            text-transform: uppercase;
            border: none;
        }
        
        .teachers-table tbody tr {
            background: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }
        
        .teachers-table tbody tr:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .teachers-table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border: none;
        }
        
        .teacher-avatar {
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
        
        .teacher-info {
            display: flex;
            align-items: center;
        }
        
        .teacher-details .name {
            font-weight: 600;
            color: #5a5c69;
            margin-bottom: 0.2rem;
        }
        
        .teacher-details .email {
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
        
        /* Modal Overlay */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            animation: fadeIn 0.3s;
        }
        
        .modal-overlay.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f8f9fc;
        }
        
        .modal-header h4 {
            margin: 0;
            color: #5a5c69;
            font-weight: 600;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #858796;
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .close-modal:hover {
            color: #5a5c69;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
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
            <a href="manage_teachers.php" class="active">
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
            <h2><i class="fas fa-chalkboard-teacher me-2"></i>Manage Teachers</h2>
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
                    <h6>Total Teachers</h6>
                    <div class="number"><?= $totalTeachers ?></div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h6>Active Teachers</h6>
                    <div class="number"><?= $activeTeachers ?></div>
                </div>
                
                <div class="stat-card danger">
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <h6>Inactive Teachers</h6>
                    <div class="number"><?= $inactiveTeachers ?></div>
                </div>
            </div>
            
            <!-- Teachers List -->
            <div class="content-card">
                <div class="card-header-custom">
                    <h5><i class="fas fa-list me-2"></i>All Teachers</h5>
                    <button class="btn-add" onclick="openModal()">
                        <i class="fas fa-plus me-2"></i>Add New Teacher
                    </button>
                </div>
                
                <!-- Filters -->
                <form method="GET" action="">
                    <div class="filters">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Search by name, email, or staff number..." value="<?= htmlspecialchars($searchTerm) ?>">
                        </div>
                        
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                        </select>
                        
                        <select name="department" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?= $deptFilter === 'all' ? 'selected' : '' ?>>All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept['department']) ?>" <?= $deptFilter === $dept['department'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['department']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <button type="submit" class="action-btn view">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        
                        <?php if (!empty($searchTerm) || $statusFilter !== 'all' || $deptFilter !== 'all'): ?>
                            <a href="manage_teachers.php" class="action-btn delete">
                                <i class="fas fa-times me-1"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
                
                <!-- Teachers Table -->
                <?php if (count($teachers) > 0): ?>
                    <table class="teachers-table">
                        <thead>
                            <tr>
                                <th>Teacher</th>
                                <th>Staff No</th>
                                <th>Department</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Hired</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teachers as $teacher): ?>
                                <tr>
                                    <td>
                                        <div class="teacher-info">
                                            <div class="teacher-avatar">
                                                <?= strtoupper(substr($teacher['first_name'] ?? $teacher['username'], 0, 1)) ?>
                                            </div>
                                            <div class="teacher-details">
                                                <div class="name">
                                                    <?= htmlspecialchars(($teacher['first_name'] ?? '') . ' ' . ($teacher['last_name'] ?? '')) ?: htmlspecialchars($teacher['username']) ?>
                                                </div>
                                                <div class="email"><?= htmlspecialchars($teacher['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($teacher['staff_number'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($teacher['department'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($teacher['phone_number'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="badge <?= $teacher['is_active'] ? 'active' : 'inactive' ?>">
                                            <?= $teacher['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td><?= $teacher['hire_date'] ? formatDate($teacher['hire_date']) : 'N/A' ?></td>
                                    <td>
                                        <a href="view_teacher.php?id=<?= $teacher['user_id'] ?>" class="action-btn view" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($teacher['is_active']): ?>
                                            <a href="?action=deactivate&id=<?= $teacher['user_id'] ?>" 
                                               class="action-btn deactivate" 
                                               title="Deactivate"
                                               onclick="return confirm('Deactivate this teacher account?');">
                                                <i class="fas fa-ban"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="?action=activate&id=<?= $teacher['user_id'] ?>" 
                                               class="action-btn activate" 
                                               title="Activate">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="?action=delete&id=<?= $teacher['user_id'] ?>" 
                                           class="action-btn delete" 
                                           title="Delete"
                                           onclick="return confirm('⚠️ Delete this teacher permanently? This cannot be undone!');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-user-slash" style="font-size: 4rem; color: #e3e6f0;"></i>
                        <h5 class="mt-3 text-muted">No Teachers Found</h5>
                        <p class="text-muted">No teachers match your search criteria.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add Teacher Modal -->
    <div class="modal-overlay" id="teacherModal">
        <div class="modal-content">
            <div class="modal-header">
                <h4><i class="fas fa-user-plus me-2"></i>Add New Teacher</h4>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="first_name" class="form-control" placeholder="Enter first name" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="last_name" class="form-control" placeholder="Enter last name" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Staff Number <span class="text-danger">*</span></label>
                        <input type="text" name="staff_number" class="form-control" placeholder="e.g., TCH001" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Department</label>
                        <input type="text" name="department" class="form-control" placeholder="e.g., Mathematics">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" placeholder="Enter username" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="teacher@example.com" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-control" placeholder="+254712345678">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Qualification</label>
                        <input type="text" name="qualification" class="form-control" placeholder="e.g., BSc, MSc">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Password <small class="text-muted">(Leave blank for default: teacher123)</small></label>
                    <input type="password" name="password" class="form-control" placeholder="Enter custom password (optional)">
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <small><strong>Note:</strong> If no password is provided, the default password will be <strong>teacher123</strong>. The teacher should change this after first login.</small>
                </div>
                
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" name="create_teacher" class="btn-add">
                        <i class="fas fa-save me-2"></i>Create Teacher
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Modal functions
        function openModal() {
            document.getElementById('teacherModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal() {
            document.getElementById('teacherModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        // Close modal when clicking outside
        document.getElementById('teacherModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Close modal with ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
        
        // Auto-dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Generate username from first and last name
        const firstNameInput = document.querySelector('input[name="first_name"]');
        const lastNameInput = document.querySelector('input[name="last_name"]');
        const usernameInput = document.querySelector('input[name="username"]');
        
        function generateUsername() {
            const firstName = firstNameInput.value.trim().toLowerCase();
            const lastName = lastNameInput.value.trim().toLowerCase();
            
            if (firstName && lastName) {
                const username = firstName.charAt(0) + lastName;
                if (!usernameInput.value) {
                    usernameInput.value = username;
                }
            }
        }
        
        firstNameInput?.addEventListener('blur', generateUsername);
        lastNameInput?.addEventListener('blur', generateUsername);
        
        // Generate staff number suggestion
        const staffNumberInput = document.querySelector('input[name="staff_number"]');
        
        function generateStaffNumber() {
            if (!staffNumberInput.value) {
                const randomNum = Math.floor(Math.random() * 9000) + 1000;
                staffNumberInput.value = 'TCH' + randomNum;
            }
        }
        
        staffNumberInput?.addEventListener('focus', function() {
            if (!this.value) {
                generateStaffNumber();
            }
        });
        
        // Format phone number
        const phoneInput = document.querySelector('input[name="phone"]');
        phoneInput?.addEventListener('blur', function() {
            let phone = this.value.replace(/\D/g, '');
            if (phone.length === 10 && !phone.startsWith('254')) {
                this.value = '+254' + phone.substring(1);
            } else if (phone.length === 9) {
                this.value = '+254' + phone;
            }
        });
        
        // Highlight search results
        const searchTerm = "<?= htmlspecialchars($searchTerm) ?>";
        if (searchTerm) {
            const cells = document.querySelectorAll('.teachers-table tbody td');
            cells.forEach(cell => {
                const text = cell.textContent;
                if (text.toLowerCase().includes(searchTerm.toLowerCase())) {
                    cell.style.background = '#fff3cd';
                }
            });
        }
        
        // Animate numbers
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
        
        // Form validation
        const form = document.querySelector('form[method="POST"]');
        form?.addEventListener('submit', function(e) {
            const email = this.querySelector('input[name="email"]').value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return false;
            }
            
            const staffNumber = this.querySelector('input[name="staff_number"]').value;
            if (staffNumber.length < 3) {
                e.preventDefault();
                alert('Staff number must be at least 3 characters long');
                return false;
            }
        });
        
        // Confirmation for sensitive actions
        document.querySelectorAll('a[href*="action=delete"]').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!confirm('⚠️ Are you sure you want to delete this teacher?\n\nThis action cannot be undone and will remove:\n• Teacher account\n• All assigned subjects\n• All uploaded materials\n\nProceed with deletion?')) {
                    e.preventDefault();
                }
            });
        });
        
        // Table row hover effect with details preview
        document.querySelectorAll('.teachers-table tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.cursor = 'pointer';
            });
        });
    </script>
</body>
</html>