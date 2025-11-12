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

// Handle Add Subject
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = sanitize($_POST['subject_name'] ?? '');
    $code = sanitize($_POST['subject_code'] ?? '');
    $desc = sanitize($_POST['description'] ?? '');
    $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
    $class_level = sanitize($_POST['class_level'] ?? '');
    $credits = !empty($_POST['credits']) ? (int)$_POST['credits'] : 3;

    if (empty($name) || empty($code)) {
        $error = 'Please fill in both Subject Name and Subject Code.';
    } else {
        try {
            // Check if subject code already exists
            $existing = $db->query("SELECT subject_id FROM subjects WHERE subject_code = :code")
                          ->bind(':code', $code)
                          ->fetch();
            
            if ($existing) {
                $error = 'Subject code already exists. Please use a different code.';
            } else {
                $db->query("INSERT INTO subjects (subject_name, subject_code, description, teacher_id, class_level, credits, is_active, created_at) 
                            VALUES (:n, :c, :d, :t, :cl, :cr, 1, NOW())")
                   ->bind(':n', $name)
                   ->bind(':c', $code)
                   ->bind(':d', $desc)
                   ->bind(':t', $teacher_id)
                   ->bind(':cl', $class_level)
                   ->bind(':cr', $credits)
                   ->execute();
                
                logActivity($_SESSION['user_id'], 'create_subject', 'subjects', $db->lastInsertId(), "Created subject: $name");
                $success = "Subject added successfully.";
            }
        } catch (PDOException $e) {
            $error = "Error adding subject: " . $e->getMessage();
        }
    }
}

// Handle Delete Subject
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    try {
        $db->query("DELETE FROM subjects WHERE subject_id = :id")
           ->bind(':id', $id)
           ->execute();
        
        logActivity($_SESSION['user_id'], 'delete_subject', 'subjects', $id, 'Deleted subject');
        $success = "Subject deleted successfully.";
    } catch (PDOException $e) {
        $error = "Error deleting subject: " . $e->getMessage();
    }
}

// Handle Edit Subject
if (isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = (int) $_POST['subject_id'];
    $name = sanitize($_POST['subject_name'] ?? '');
    $code = sanitize($_POST['subject_code'] ?? '');
    $desc = sanitize($_POST['description'] ?? '');
    $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
    $class_level = sanitize($_POST['class_level'] ?? '');
    $credits = !empty($_POST['credits']) ? (int)$_POST['credits'] : 3;

    if (empty($name) || empty($code)) {
        $error = 'Please fill in both Subject Name and Subject Code.';
    } else {
        try {
            $db->query("UPDATE subjects 
                       SET subject_name = :n, subject_code = :c, description = :d, 
                           teacher_id = :t, class_level = :cl, credits = :cr
                       WHERE subject_id = :id")
               ->bind(':n', $name)
               ->bind(':c', $code)
               ->bind(':d', $desc)
               ->bind(':t', $teacher_id)
               ->bind(':cl', $class_level)
               ->bind(':cr', $credits)
               ->bind(':id', $id)
               ->execute();
            
            logActivity($_SESSION['user_id'], 'update_subject', 'subjects', $id, "Updated subject: $name");
            $success = "Subject updated successfully.";
        } catch (PDOException $e) {
            $error = "Error updating subject: " . $e->getMessage();
        }
    }
}

// Handle Toggle Active Status
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    try {
        $db->query("UPDATE subjects SET is_active = NOT is_active WHERE subject_id = :id")
           ->bind(':id', $id)
           ->execute();
        $success = "Subject status updated successfully.";
    } catch (PDOException $e) {
        $error = "Error updating status: " . $e->getMessage();
    }
}

// Search and filter
$searchTerm = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? 'all';
$teacherFilter = $_GET['teacher'] ?? 'all';

// Build query with teacher info
$sql = "SELECT s.*, CONCAT(t.first_name, ' ', t.last_name) as teacher_name
        FROM subjects s 
        LEFT JOIN teachers t ON s.teacher_id = t.teacher_id 
        WHERE 1=1";

if (!empty($searchTerm)) {
    $sql .= " AND (s.subject_name LIKE :search OR s.subject_code LIKE :search OR s.description LIKE :search)";
}

if ($statusFilter !== 'all') {
    $sql .= " AND s.is_active = :status";
}

if ($teacherFilter !== 'all') {
    $sql .= " AND s.teacher_id = :teacher";
}

$sql .= " ORDER BY s.subject_name ASC";

try {
    $query = $db->query($sql);
    
    if (!empty($searchTerm)) {
        $query->bind(':search', "%$searchTerm%");
    }
    if ($statusFilter !== 'all') {
        $query->bind(':status', $statusFilter === 'active' ? 1 : 0);
    }
    if ($teacherFilter !== 'all') {
        $query->bind(':teacher', (int)$teacherFilter);
    }
    
    $subjects = $query->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching subjects: " . $e->getMessage();
    $subjects = [];
}

// Get all teachers for dropdown
$teachers = $db->query("SELECT t.teacher_id, CONCAT(t.first_name, ' ', t.last_name) as teacher_name 
                        FROM teachers t 
                        INNER JOIN users u ON t.user_id = u.user_id 
                        WHERE u.is_active = 1 
                        ORDER BY t.first_name")->fetchAll();

// Get statistics
$totalSubjects = count($subjects);
$activeSubjects = count(array_filter($subjects, fn($s) => $s['is_active'] == 1));
$assignedSubjects = count(array_filter($subjects, fn($s) => !empty($s['teacher_id'])));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects - Online School Portal</title>
    
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
        .stat-card.info::before { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        
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
        .stat-card.info .stat-icon { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        
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
        
        /* Subjects Grid */
        .subjects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .subject-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
            border-top: 4px solid;
        }
        
        .subject-card:nth-child(4n+1) { border-top-color: #667eea; }
        .subject-card:nth-child(4n+2) { border-top-color: #11998e; }
        .subject-card:nth-child(4n+3) { border-top-color: #f093fb; }
        .subject-card:nth-child(4n+4) { border-top-color: #4facfe; }
        
        .subject-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .subject-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .subject-code {
            background: #f8f9fc;
            color: #5a5c69;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .subject-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #5a5c69;
            margin: 0.5rem 0;
        }
        
        .subject-description {
            font-size: 0.9rem;
            color: #858796;
            margin-bottom: 1rem;
            min-height: 40px;
        }
        
        .subject-info {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }
        
        .subject-info-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            color: #858796;
        }
        
        .subject-info-item i {
            color: #667eea;
        }
        
        .subject-teacher {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.8rem;
            background: #f8f9fc;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .teacher-avatar-small {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .teacher-name {
            font-size: 0.9rem;
            color: #5a5c69;
            font-weight: 500;
        }
        
        .subject-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .action-btn-small {
            flex: 1;
            padding: 0.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
        }
        
        .action-btn-small.edit {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .action-btn-small.delete {
            background: #ffebee;
            color: #c62828;
        }
        
        .action-btn-small.toggle {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .action-btn-small:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
        }
        
        .badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
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
        
        /* Modal */
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
            
            .subjects-grid {
                grid-template-columns: 1fr;
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
            <h2><i class="fas fa-book me-2"></i>Manage Subjects</h2>
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
                        <i class="fas fa-book"></i>
                    </div>
                    <h6>Total Subjects</h6>
                    <div class="number"><?= $totalSubjects ?></div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h6>Active Subjects</h6>
                    <div class="number"><?= $activeSubjects ?></div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h6>Assigned to Teachers</h6>
                    <div class="number"><?= $assignedSubjects ?></div>
                </div>
            </div>
            
            <!-- Subjects List -->
            <div class="content-card">
                <div class="card-header-custom">
                    <h5><i class="fas fa-list me-2"></i>All Subjects</h5>
                    <button class="btn-add" onclick="openAddModal()">
                        <i class="fas fa-plus me-2"></i>Add New Subject
                    </button>
                </div>
                
                <!-- Filters -->
                <form method="GET" action="">
                    <div class="filters">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Search subjects..." value="<?= htmlspecialchars($searchTerm) ?>">
                        </div>
                        
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                        </select>
                        
                        <select name="teacher" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?= $teacherFilter === 'all' ? 'selected' : '' ?>>All Teachers</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?= $t['teacher_id'] ?>" <?= $teacherFilter == $t['teacher_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['teacher_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <button type="submit" class="btn-add" style="padding: 0.7rem 1rem;">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        
                        <?php if (!empty($searchTerm) || $statusFilter !== 'all' || $teacherFilter !== 'all'): ?>
                            <a href="manage_subjects.php" class="action-btn-small delete">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
                
                <!-- Subjects Grid -->
                <?php if (count($subjects) > 0): ?>
                    <div class="subjects-grid">
                        <?php foreach ($subjects as $subject): ?>
                            <div class="subject-card">
                                <div class="subject-header">
                                    <span class="subject-code"><?= htmlspecialchars($subject['subject_code']) ?></span>
                                    <span class="badge <?= $subject['is_active'] ? 'active' : 'inactive' ?>">
                                        <?= $subject['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </div>
                                
                                <h3 class="subject-name"><?= htmlspecialchars($subject['subject_name']) ?></h3>
                                <p class="subject-description">
                                    <?= htmlspecialchars($subject['description'] ?: 'No description provided') ?>
                                </p>
                                
                                <div class="subject-info">
                                    <div class="subject-info-item">
                                        <i class="fas fa-layer-group"></i>
                                        <span><?= htmlspecialchars($subject['class_level'] ?: 'N/A') ?></span>
                                    </div>
                                    <div class="subject-info-item">
                                        <i class="fas fa-star"></i>
                                        <span><?= $subject['credits'] ?> Credits</span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($subject['teacher_name'])): ?>
                                    <div class="subject-teacher">
                                        <div class="teacher-avatar-small">
                                            <?= strtoupper(substr($subject['teacher_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <small style="color: #858796; font-size: 0.75rem;">Assigned to</small>
                                            <div class="teacher-name"><?= htmlspecialchars($subject['teacher_name']) ?></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="subject-teacher" style="background: #fff3e0;">
                                        <div class="teacher-avatar-small" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                            <i class="fas fa-user-slash" style="font-size: 0.8rem;"></i>
                                        </div>
                                        <div>
                                            <small style="color: #f57c00; font-size: 0.75rem;">Not Assigned</small>
                                            <div class="teacher-name" style="color: #f57c00;">No teacher assigned</div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="subject-actions">
                                    <button class="action-btn-small edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($subject)) ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="?action=toggle&id=<?= $subject['subject_id'] ?>" class="action-btn-small toggle" title="Toggle Status">
                                        <i class="fas fa-power-off"></i>
                                    </a>
                                    <a href="?action=delete&id=<?= $subject['subject_id'] ?>" 
                                       class="action-btn-small delete" 
                                       onclick="return confirm('⚠️ Delete this subject?\n\nThis will remove:\n• All assignments\n• All grades\n• All enrollments\n\nThis cannot be undone!');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-book-open" style="font-size: 4rem; color: #e3e6f0;"></i>
                        <h5 class="mt-3 text-muted">No Subjects Found</h5>
                        <p class="text-muted">Start by adding your first subject.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add Subject Modal -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h4><i class="fas fa-plus-circle me-2"></i>Add New Subject</h4>
                <button class="close-modal" onclick="closeAddModal()">&times;</button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Subject Name <span class="text-danger">*</span></label>
                        <input type="text" name="subject_name" class="form-control" placeholder="e.g., Mathematics" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Subject Code <span class="text-danger">*</span></label>
                        <input type="text" name="subject_code" class="form-control" placeholder="e.g., MATH101" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Brief description of the subject"></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Class Level</label>
                        <input type="text" name="class_level" class="form-control" placeholder="e.g., Form 1, Grade 10">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Credits</label>
                        <input type="number" name="credits" class="form-control" value="3" min="1" max="10">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Assign Teacher <small class="text-muted">(Optional)</small></label>
                    <select name="teacher_id" class="form-select">
                        <option value="">-- Select Teacher --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['teacher_id'] ?>">
                                <?= htmlspecialchars($teacher['teacher_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <small>You can assign a teacher now or do it later. Teachers can be changed anytime.</small>
                </div>
                
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn-add">
                        <i class="fas fa-save me-2"></i>Add Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Subject Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h4><i class="fas fa-edit me-2"></i>Edit Subject</h4>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="subject_id" id="edit_subject_id">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Subject Name <span class="text-danger">*</span></label>
                        <input type="text" name="subject_name" id="edit_subject_name" class="form-control" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Subject Code <span class="text-danger">*</span></label>
                        <input type="text" name="subject_code" id="edit_subject_code" class="form-control" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Class Level</label>
                        <input type="text" name="class_level" id="edit_class_level" class="form-control">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Credits</label>
                        <input type="number" name="credits" id="edit_credits" class="form-control" min="1" max="10">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Assign Teacher</label>
                    <select name="teacher_id" id="edit_teacher_id" class="form-select">
                        <option value="">-- No Teacher --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['teacher_id'] ?>">
                                <?= htmlspecialchars($teacher['teacher_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn-add">
                        <i class="fas fa-save me-2"></i>Update Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add Modal Functions
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        // Edit Modal Functions
        function openEditModal(subject) {
            document.getElementById('edit_subject_id').value = subject.subject_id;
            document.getElementById('edit_subject_name').value = subject.subject_name;
            document.getElementById('edit_subject_code').value = subject.subject_code;
            document.getElementById('edit_description').value = subject.description || '';
            document.getElementById('edit_class_level').value = subject.class_level || '';
            document.getElementById('edit_credits').value = subject.credits || 3;
            document.getElementById('edit_teacher_id').value = subject.teacher_id || '';
            
            document.getElementById('editModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        // Close modals when clicking outside
        document.getElementById('addModal').addEventListener('click', function(e) {
            if (e.target === this) closeAddModal();
        });
        
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeEditModal();
        });
        
        // Close modals with ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddModal();
                closeEditModal();
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
        
        // Auto-generate subject code from subject name
        const subjectNameInput = document.querySelector('#addModal input[name="subject_name"]');
        const subjectCodeInput = document.querySelector('#addModal input[name="subject_code"]');
        
        subjectNameInput?.addEventListener('blur', function() {
            if (!subjectCodeInput.value) {
                const name = this.value.trim().toUpperCase().replace(/\s+/g, '');
                if (name.length >= 3) {
                    const code = name.substring(0, 4) + Math.floor(Math.random() * 900 + 100);
                    subjectCodeInput.value = code;
                }
            }
        });
        
        // Highlight search results
        const searchTerm = "<?= htmlspecialchars($searchTerm) ?>";
        if (searchTerm) {
            const cards = document.querySelectorAll('.subject-card');
            cards.forEach(card => {
                const text = card.textContent;
                if (text.toLowerCase().includes(searchTerm.toLowerCase())) {
                    card.style.border = '2px solid #fff3cd';
                    card.style.background = '#fffef7';
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
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const code = this.querySelector('input[name="subject_code"]').value.trim();
                if (code.length < 3) {
                    e.preventDefault();
                    alert('Subject code must be at least 3 characters long');
                    return false;
                }
            });
        });
        
        // Confirmation for delete
        document.querySelectorAll('a[href*="action=delete"]').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!confirm('⚠️ Are you sure you want to delete this subject?\n\nThis will permanently remove:\n• All assignments for this subject\n• All student enrollments\n• All grades and submissions\n\nThis action CANNOT be undone!\n\nProceed with deletion?')) {
                    e.preventDefault();
                }
            });
        });
        
        // Card hover effect with preview
        document.querySelectorAll('.subject-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.cursor = 'pointer';
            });
        });
        
        // Add staggered animation on load
        window.addEventListener('load', function() {
            const cards = document.querySelectorAll('.subject-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>