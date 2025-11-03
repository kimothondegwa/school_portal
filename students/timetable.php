<?php
// ====================================================
// FILE: student/view_timetable.php
// PURPOSE: Students view their weekly timetable
// ====================================================

define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();

// ✅ Only logged-in students can access
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php?error=Access+Denied');
    exit;
}

$db = getDB();
$pdo = $db->getConnection();
$user = getCurrentUser();

// ✅ Fetch student class_level from table
$student_id = $_SESSION['user_id'];
$studentData = $pdo->prepare("
    SELECT s.class_level
    FROM students s
    INNER JOIN users u ON s.user_id = u.user_id
    WHERE s.user_id = :id
");
$studentData->execute([':id' => $student_id]);
$student = $studentData->fetch();

if (!$student) {
    die("Student data not found.");
}

$classLevel = $student['class_level'];

// ====================================================
// FETCH TIMETABLE FOR THIS STUDENT'S CLASS
// ====================================================
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

$stmt = $pdo->prepare("
    SELECT t.*, s.subject_name, CONCAT(te.first_name, ' ', te.last_name) AS teacher_name
    FROM timetables t
    INNER JOIN subjects s ON t.subject_id = s.subject_id
    LEFT JOIN teachers te ON t.teacher_id = te.teacher_id
    WHERE t.class_level = :class
      AND t.is_active = 1
    ORDER BY FIELD(t.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), t.start_time ASC
");
$stmt->execute([':class' => $classLevel]);
$timetable = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Organize timetable by day
$organizedTimetable = [];
foreach ($days as $day) {
    $organizedTimetable[$day] = [];
}
foreach ($timetable as $row) {
    $organizedTimetable[$row['day_of_week']][] = $row;
}
foreach ($organizedTimetable as &$slots) {
    usort($slots, fn($a, $b) => strtotime($a['start_time']) - strtotime($b['start_time']));
}
unset($slots);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Timetable - Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            max-width: 1200px;
            margin-top: 2rem;
        }
        .day-column {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            min-height: 250px;
        }
        .day-header {
            font-weight: 600;
            font-size: 1.1rem;
            color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 0.8rem;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 1rem;
        }
        .time-slot {
            background: #f8f9fc;
            border-left: 4px solid #667eea;
            padding: 0.8rem;
            border-radius: 8px;
            margin-bottom: 0.8rem;
        }
        .time-slot:nth-child(even) {
            border-left-color: #38ef7d;
        }
        .slot-time {
            font-weight: 600;
            color: #5a5c69;
        }
        .slot-subject {
            font-weight: 600;
            color: #667eea;
        }
        .slot-info {
            color: #858796;
            font-size: 0.9rem;
        }
        .empty-day {
            text-align: center;
            padding: 2rem;
            color: #999;
        }
    </style>
</head>
<body>

<div class="container">
    <h2 class="mb-4 text-center">
        <i class="fas fa-calendar-alt me-2"></i>
        My Weekly Timetable
    </h2>
    <h5 class="text-center text-muted mb-4">Class Level: <?= htmlspecialchars($classLevel) ?></h5>

    <div class="row g-4">
        <?php foreach ($days as $day): ?>
            <div class="col-md-6 col-lg-4">
                <div class="day-column">
                    <div class="day-header"><?= $day ?></div>
                    <?php if (empty($organizedTimetable[$day])): ?>
                        <div class="empty-day">
                            <i class="fas fa-calendar-times" style="font-size: 1.5rem; opacity: 0.3;"></i><br>
                            No classes
                        </div>
                    <?php else: ?>
                        <?php foreach ($organizedTimetable[$day] as $slot): ?>
                            <div class="time-slot">
                                <div class="slot-time">
                                    <i class="fas fa-clock me-1"></i>
                                    <?= date('g:i A', strtotime($slot['start_time'])) ?> - <?= date('g:i A', strtotime($slot['end_time'])) ?>
                                </div>
                                <div class="slot-subject"><?= htmlspecialchars($slot['subject_name']) ?></div>
                                <div class="slot-info">
                                    <i class="fas fa-chalkboard-teacher me-1"></i> <?= htmlspecialchars($slot['teacher_name'] ?? 'TBA') ?><br>
                                    <?php if (!empty($slot['room_number'])): ?>
                                        <i class="fas fa-door-open me-1"></i> Room <?= htmlspecialchars($slot['room_number']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<!-- View Timetable Button -->
<div style="text-align: center; margin: 20px 0;">
    <a href="view_timetable.php" class="btn btn-primary btn-lg" style="
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 10px;
        padding: 12px 24px;
        font-weight: 600;
        box-shadow: 0 4px 10px rgba(102, 126, 234, 0.4);
        transition: 0.3s ease;
        text-decoration: none;
        color: white;
    " onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
        <i class="fas fa-calendar-alt me-2"></i> View Timetable
    </a>
</div>

</body>
</html>
