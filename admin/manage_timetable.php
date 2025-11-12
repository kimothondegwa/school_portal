<?php
// ====================================================
// FILE: admin/manage_timetable.php
// PURPOSE: Admin backend for managing school timetable
// ====================================================

define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();

// ✅ Only admin can access
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();
$pdo = $db->getConnection(); // ✅ Direct PDO connection for safer inserts
$error = '';
$success = '';

/* ============================================================
   FETCH SUBJECTS AND TEACHERS
============================================================ */
$subjects = $db->query("
    SELECT s.subject_id, s.subject_name, CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
    FROM subjects s
    LEFT JOIN teachers t ON s.teacher_id = t.teacher_id
    WHERE s.is_active = 1
    ORDER BY s.subject_name ASC
")->fetchAll();

$teachers = $db->query("
    SELECT t.teacher_id, CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
    FROM teachers t
    INNER JOIN users u ON t.user_id = u.user_id
    WHERE u.is_active = 1
    ORDER BY t.first_name
")->fetchAll();

/* ============================================================
   ADD TIMETABLE ENTRY (FINAL SAFE VERSION)
============================================================ */
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $subject_id  = (int)($_POST['subject_id'] ?? 0);
    $teacher_id  = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
    $class_level = sanitize($_POST['class_level'] ?? '');
    $day         = sanitize($_POST['day_of_week'] ?? '');
    $start_time  = sanitize($_POST['start_time'] ?? '');
    $end_time    = sanitize($_POST['end_time'] ?? '');
    $room_number = sanitize($_POST['room_number'] ?? '');

    if (!$subject_id || !$class_level || !$day || !$start_time || !$end_time) {
        $error = "Please fill all required fields.";
    } elseif ($start_time >= $end_time) {
        $error = "End time must be after start time.";
    } else {
       try {
    // ✅ Check for timetable conflicts
    $conflict = $db->query("
        SELECT timetable_id FROM timetables
        WHERE class_level = :class
          AND day_of_week = :day
          AND (
                (start_time <= :start AND end_time > :start)
                OR (start_time < :end AND end_time >= :end)
                OR (start_time >= :start AND end_time <= :end)
          )
    ")
    ->bind(':class', $class_level)
    ->bind(':day', $day)
    ->bind(':start', $start_time)
    ->bind(':end', $end_time)
    ->fetch();

    if ($conflict) {
        $error = "Time conflict detected! This class already has a session during that period.";
    } else {
        if (!$teacher_id) {
            $subjectData = $db->query("SELECT teacher_id FROM subjects WHERE subject_id = :id")
                ->bind(':id', $subject_id)
                ->fetch();
            $teacher_id = $subjectData['teacher_id'] ?? null;
        }

        // ✅ Prepare and execute in one chain (keeps stmt context)
        $db->query("
            INSERT INTO timetables 
            (subject_id, teacher_id, class_level, day_of_week, start_time, end_time, room_number, is_active)
            VALUES (:sid, :tid, :class, :day, :start, :end, :room, 1)
        ")
        ->bind(':sid', $subject_id)
        ->bind(':tid', $teacher_id)
        ->bind(':class', $class_level)
        ->bind(':day', $day)
        ->bind(':start', $start_time)
        ->bind(':end', $end_time)
        ->bind(':room', $room_number)
        ->execute();

        logActivity($_SESSION['user_id'], 'create_timetable', 'timetables', $db->lastInsertId(), "Created timetable for $class_level");
        $success = "Timetable entry added successfully.";
    }
} catch (PDOException $e) {
    $error = "Error adding timetable: " . $e->getMessage();
}

}
}
/* ============================================================
   ADD TIMETABLE ENTRY (FIXED VERSION)
============================================================ */
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $subject_id  = (int)($_POST['subject_id'] ?? 0);
    $teacher_id  = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
    $class_level = sanitize($_POST['class_level'] ?? '');
    $day         = sanitize($_POST['day_of_week'] ?? '');
    $start_time  = sanitize($_POST['start_time'] ?? '');
    $end_time    = sanitize($_POST['end_time'] ?? '');
    $room_number = sanitize($_POST['room_number'] ?? '');

    if (!$subject_id || !$class_level || !$day || !$start_time || !$end_time) {
        $error = "⚠️ Please fill in all required fields.";
    } elseif ($start_time >= $end_time) {
        $error = "⚠️ End time must be after start time.";
    } else {
        try {
            // ✅ Check if time overlaps with existing timetable for the same class/day
            $conflict = $db->query("
                SELECT timetable_id FROM timetables
                WHERE class_level = :class
                  AND day_of_week = :day
                  AND (
                        (start_time < :end AND end_time > :start)
                  )
            ")
            ->bind(':class', $class_level)
            ->bind(':day', $day)
            ->bind(':start', $start_time)
            ->bind(':end', $end_time)
            ->fetch();

            if ($conflict) {
                $error = "⛔ Time conflict detected — another session overlaps with this period.";
            } else {
                // ✅ Auto-fetch teacher from subject if not provided
                if (!$teacher_id) {
                    $subjectData = $db->query("SELECT teacher_id FROM subjects WHERE subject_id = :id")
                        ->bind(':id', $subject_id)
                        ->fetch();
                    $teacher_id = $subjectData['teacher_id'] ?? null;
                }

                // ✅ Insert timetable safely
                $db->query("
                    INSERT INTO timetables 
                    (subject_id, teacher_id, class_level, day_of_week, start_time, end_time, room_number, is_active)
                    VALUES (:sid, :tid, :class, :day, :start, :end, :room, 1)
                ")
                ->bind(':sid', $subject_id)
                ->bind(':tid', $teacher_id)
                ->bind(':class', $class_level)
                ->bind(':day', $day)
                ->bind(':start', $start_time)
                ->bind(':end', $end_time)
                ->bind(':room', $room_number)
                ->execute();

                $success = "✅ Timetable entry added successfully.";
                logActivity($_SESSION['user_id'], 'create_timetable', 'timetables', $db->lastInsertId(), "Created timetable for $class_level");
            }
        } catch (PDOException $e) {
            $error = "❌ Error adding timetable: " . $e->getMessage();
        }
    }
}


/* ============================================================
   DELETE TIMETABLE ENTRY
============================================================ */
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    try {
        $delete = $pdo->prepare("DELETE FROM timetables WHERE timetable_id = :id");
        $delete->execute([':id' => $id]);

        logActivity($_SESSION['user_id'], 'delete_timetable', 'timetables', $id, 'Deleted timetable entry');
        $success = "✅ Timetable entry deleted successfully.";
    } catch (PDOException $e) {
        $error = "Error deleting timetable: " . $e->getMessage();
    }
}

/* ============================================================
   FETCH ALL TIMETABLE DATA FOR DISPLAY
============================================================ */
$classFilter = $_GET['class'] ?? 'all';

$sql = "
    SELECT t.*, s.subject_name, s.subject_code, CONCAT(te.first_name, ' ', te.last_name) AS teacher_name
    FROM timetables t
    INNER JOIN subjects s ON t.subject_id = s.subject_id
    LEFT JOIN teachers te ON t.teacher_id = te.teacher_id
    WHERE 1=1
";
if ($classFilter !== 'all') {
    $sql .= " AND t.class_level = :class";
}
$sql .= " ORDER BY FIELD(t.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), t.start_time ASC";

$timetableStmt = $pdo->prepare($sql);
if ($classFilter !== 'all') {
    $timetableStmt->execute([':class' => $classFilter]);
} else {
    $timetableStmt->execute();
}
$timetable = $timetableStmt->fetchAll();

/* ============================================================
   EXTRA: STATISTICS & ORGANIZATION
============================================================ */
$classLevels   = $db->query("SELECT DISTINCT class_level FROM timetables ORDER BY class_level")->fetchAll();
$totalEntries  = count($timetable);
$totalClasses  = count($classLevels);
$totalSubjects = count(array_unique(array_column($timetable, 'subject_id')));
$uniqueClasses = count(array_unique(array_column($timetable, 'class_level')));
/* ============================================================
   ORGANIZE TIMETABLE DATA BY DAY
============================================================ */
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

$organizedTimetable = [];
foreach ($days as $day) {
    $organizedTimetable[$day] = [];
}

foreach ($timetable as $entry) {
    $day = $entry['day_of_week'];
    if (!isset($organizedTimetable[$day])) {
        $organizedTimetable[$day] = [];
    }
    $organizedTimetable[$day][] = $entry;
}
foreach ($organizedTimetable as &$slots) {
    usort($slots, function($a, $b) {
        return strtotime($a['start_time']) - strtotime($b['start_time']);
    });
}
unset($slots);

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Timetable - Online School Portal</title>
    
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
        
        /* Calendar View */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .day-column {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            min-height: 200px;
        }
        
        .day-header {
            font-weight: 600;
            font-size: 1.1rem;
            color: #5a5c69;
            padding: 0.8rem;
            background: var(--primary-gradient);
            color: white;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .time-slot {
            background: #f8f9fc;
            border-left: 4px solid;
            padding: 0.8rem;
            margin-bottom: 0.8rem;
            border-radius: 8px;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .time-slot:nth-child(odd) { border-left-color: #667eea; }
        .time-slot:nth-child(even) { border-left-color: #11998e; }
        
        .time-slot:hover {
            transform: translateX(5px);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        
        .slot-time {
            font-weight: 600;
            color: #5a5c69;
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
        }
        
        .slot-subject {
            font-weight: 600;
            color: #667eea;
            margin-bottom: 0.2rem;
        }
        
        .slot-info {
            font-size: 0.85rem;
            color: #858796;
            display: flex;
            gap: 1rem;
            margin-top: 0.3rem;
        }
        
        .slot-info span {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .slot-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .action-btn-tiny {
            padding: 0.3rem 0.6rem;
            border: none;
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .action-btn-tiny.edit {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .action-btn-tiny.delete {
            background: #ffebee;
            color: #c62828;
        }
        
        .action-btn-tiny:hover {
            transform: scale(1.05);
        }
        
        .empty-day {
            text-align: center;
            padding: 2rem;
            color: #858796;
        }
        
        /* Filters */
        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: center;
        }
        
        .filter-select {
            padding: 0.7rem 1rem;
            border: 2px solid #e3e6f0;
            border-radius: 10px;
            background: white;
            cursor: pointer;
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
            max-width: 700px;
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
        
        .view-toggle {
            display: flex;
            gap: 0.5rem;
            background: #f8f9fc;
            padding: 0.3rem;
            border-radius: 10px;
        }
        
        .view-btn {
            padding: 0.5rem 1rem;
            border: none;
            background: transparent;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            color: #858796;
        }
        
        .view-btn.active {
            background: white;
            color: #667eea;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .calendar-grid {
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
            <h2><i class="fas fa-calendar-alt me-2"></i>Manage Timetable</h2>
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
                        <i class="fas fa-clock"></i>
                    </div>
                    <h6>Total Time Slots</h6>
                    <div class="number"><?= $totalEntries ?></div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h6>Classes</h6>
                    <div class="number"><?= $uniqueClasses ?></div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h6>Subjects Scheduled</h6>
                    <div class="number"><?= $totalSubjects ?></div>
                </div>
            </div>
            
            <!-- Timetable View -->
            <div class="content-card">
                <div class="card-header-custom">
                    <div class="d-flex align-items-center gap-3">
                        <h5><i class="fas fa-calendar-week me-2"></i>Weekly Timetable</h5>
                        <div class="view-toggle">
                            <button class="view-btn active" onclick="showCalendarView()">
                                <i class="fas fa-calendar me-1"></i> Calendar
                            </button>
                            <button class="view-btn" onclick="showListView()">
                                <i class="fas fa-list me-1"></i> List
                            </button>
                        </div>
                    </div>
                    <button class="btn-add" onclick="openAddModal()">
                        <i class="fas fa-plus me-2"></i>Add Time Slot
                    </button>
                </div>
                
                <!-- Filters -->
                <form method="GET" action="">
                    <div class="filters">
                        <label style="font-weight: 600; color: #5a5c69;">Filter by Class:</label>
                        <select name="class" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?= $classFilter === 'all' ? 'selected' : '' ?>>All Classes</option>
                            <?php foreach ($classLevels as $level): ?>
                                <option value="<?= htmlspecialchars($level['class_level']) ?>" <?= $classFilter === $level['class_level'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($level['class_level']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <?php if ($classFilter !== 'all'): ?>
                            <a href="manage_timetable.php" class="btn-add" style="padding: 0.7rem 1rem;">
                                <i class="fas fa-times me-1"></i> Clear Filter
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
                
                <!-- Calendar View -->
                <div id="calendarView">
                    <?php if (count($timetable) > 0): ?>
                        <div class="calendar-grid">
                            <?php foreach ($days as $day): ?>
                                <div class="day-column">
                                    <div class="day-header">
                                        <i class="fas fa-calendar-day me-2"></i><?= $day ?>
                                    </div>
                                    
                                    <?php if (empty($organizedTimetable[$day])): ?>
                                        <div class="empty-day">
                                            <i class="fas fa-calendar-times" style="font-size: 2rem; opacity: 0.3;"></i>
                                            <p class="mt-2">No classes scheduled</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($organizedTimetable[$day] as $slot): ?>
                                            <div class="time-slot" onclick="openEditModal(<?= htmlspecialchars(json_encode($slot)) ?>)">
                                                <div class="slot-time">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?= date('g:i A', strtotime($slot['start_time'])) ?> - <?= date('g:i A', strtotime($slot['end_time'])) ?>
                                                </div>
                                                <div class="slot-subject"><?= htmlspecialchars($slot['subject_name']) ?></div>
                                                <div class="slot-info">
                                                    <span><i class="fas fa-chalkboard-teacher"></i> <?= htmlspecialchars($slot['teacher_name'] ?? 'TBA') ?></span>
                                                    <span><i class="fas fa-school"></i> <?= htmlspecialchars($slot['class_level']) ?></span>
                                                    <?php if ($slot['room_number']): ?>
                                                        <span><i class="fas fa-door-open"></i> Room <?= htmlspecialchars($slot['room_number']) ?></span>
                                                    <?php endif; ?> 
                                                </div>
                                                <div class="slot-actions">              
                                                    <button class="action-btn-tiny edit" onclick="event.stopPropagation(); openEditModal(<?= htmlspecialchars(json_encode($slot)) ?>)">
                                                        <i class="fas fa-edit me-1"></i>Edit
                                                    </button>
                                                    <button class="action-btn-tiny delete" onclick="event.stopPropagation(); confirmDelete(<?= $slot['timetable_id'] ?>);">
                                                        <i class="fas fa-trash-alt me-1"></i>Delete
                                                    </button>
                                                </div>
                                            </div>  
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-day" style="padding: 4rem;">
                            <i class="fas fa-calendar-times" style="font-size: 3rem; opacity: 0.3;"></i>
                            <p class="mt-3" style="font-size: 1.2rem;">No timetable entries found. Please add some time slots.</p>
                        </div>
                    <?php endif; ?>
                </div>  
                
                <!-- List View -->
                <div id="listView" style="display: none;">  
                        

                </div>
            </div>      
        </div>
    </div>
                        
    <!-- Modals -->
    <div id="modalOverlay" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h4 id="modalTitle">Add Time Slot</h4>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form id="timetableForm" method="POST" action="">
                <input type="hidden" name="timetable_id" id="timetable_id">
                <input type="hidden" name="action" id="formAction" value="add">
                
                <div class="mb-3">
                    <label for="subject_id" class="form-label">Subject <span class="text-danger">*</span></label>
                    <select name="subject_id" id="subject_id" class="form-select" required>
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= $subject['subject_id'] ?>"><?= htmlspecialchars($subject['subject_name']) ?> (<?= htmlspecialchars($subject['teacher_name'] ?? 'TBA') ?>)</option>
                        <?php endforeach; ?>    
                    </select>
                </div>  
                <div class="mb-3">
                    <label for="class_level" class="form-label">Class Level <span class="text-danger">*</span></label>
                    <input type="text" name="class_level" id="class_level" class="form-control" placeholder="e.g., Grade 10" required>
                </div>
                <div class="mb-3">
                    <label for="day_of_week" class="form-label">Day of the Week <span class="text-danger">*</span></label>
                    <select name="day_of_week" id="day_of_week" class="form-select" required>
                        <option value="">Select Day</option>
                        <option value="Monday">Monday</option>
                        <option value="Tuesday">Tuesday</option>
                        <option value="Wednesday">Wednesday</option>
                        <option value="Thursday">Thursday</option>
                        <option value="Friday">Friday</option>
                        <option value="Saturday">Saturday</option>
                        <option value="Sunday">Sunday</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
                    <input type="time" name="start_time" id="start_time" class="form-control" required> 
                </div>
                <div class="mb-3">
                    <label for="end_time" class="form-label">End Time <span class="text-danger">*</span></label>
                    <input type="time" name="end_time" id="end_time" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="room_number" class="form-label">Room Number</label>
                    <input type="text" name="room_number" id="room_number" class="form-control" placeholder="e.g., 101">
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // View Toggle
        function showCalendarView() {
            document.getElementById('calendarView').style.display = 'block';
            document.getElementById('listView').style.display = 'none';
            document.querySelectorAll('.view-btn')[0].classList.add('active');
            document.querySelectorAll('.view-btn')[1].classList.remove('active');
        }
        
        function showListView() {
            document.getElementById('calendarView').style.display = 'none';
            document.getElementById('listView').style.display = 'block';
            document.querySelectorAll('.view-btn')[1].classList.add('active');
            document.querySelectorAll('.view-btn')[0].classList.remove('active');
        }
        
        // Modal Functions
        function openAddModal() {
            document.getElementById('modalTitle').innerText = 'Add Time Slot';
            document.getElementById('formAction').value = 'add';
            document.getElementById('timetableForm').reset();
            document.getElementById('timetable_id').value = '';
            document.getElementById('modalOverlay').classList.add('active');
        }
        
        function openEditModal(slot) {
            document.getElementById('modalTitle').innerText = 'Edit Time Slot';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('timetable_id').value = slot.timetable_id;
            document.getElementById('subject_id').value = slot.subject_id;
            document.getElementById('class_level').value = slot.class_level;
            document.getElementById('day_of_week').value = slot.day_of_week;
            document.getElementById('start_time').value = slot.start_time;
            document.getElementById('end_time').value = slot.end_time;
            document.getElementById('room_number').value = slot.room_number;
            document.getElementById('modalOverlay').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('modalOverlay').classList.remove('active');
        }
        
        // Confirm Delete
        function confirmDelete(id) {
            if (confirm('Are you sure you want to delete this timetable entry?')) {
                window.location.href = 'manage_timetable.php?action=delete&id=' + id;
            }
        }
    </script>
</body>
</html>