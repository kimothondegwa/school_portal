<?php
// ====================================================
// FILE: teacher/view_timetable.php
// Teacher's Timetable View
// ====================================================

define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();

// ✅ Only teachers can access
if (!isLoggedIn() || !isTeacher()) {
    header('Location: ../login.php?error=Access+Denied');
    exit;
}

$user = getCurrentUser();
$teacher_id = $user['teacher_id']; // make sure this exists in your teachers table

$db = getDB()->getConnection();

// ✅ Fetch timetable entries for this teacher
$sql = "
    SELECT 
        t.timetable_id,
        t.class_level,
        s.subject_name,
        t.day_of_week,
        t.start_time,
        t.end_time,
        t.room_number
    FROM timetables t
    JOIN subjects s ON t.subject_id = s.subject_id
    WHERE t.teacher_id = ?
    ORDER BY 
        FIELD(t.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
        t.start_time
";

$stmt = $db->prepare($sql);
$stmt->execute([$teacher_id]);
$timetable = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Timetable</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f8f9fa; }
        .container { max-width: 1100px; margin: 50px auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; margin-bottom: 25px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; text-align: center; border-bottom: 1px solid #ddd; }
        th { background: #007bff; color: white; }
        tr:hover { background: #f1f1f1; }
        .no-data { text-align: center; color: #777; padding: 30px; }
        .back-btn {
            display: inline-block;
            background: #6c63ff;
            color: #fff;
            padding: 10px 18px;
            border-radius: 8px;
            text-decoration: none;
            transition: 0.3s;
        }
        .back-btn:hover { opacity: 0.8; }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    <h2>My Teaching Timetable</h2>

    <?php if (empty($timetable)): ?>
        <div class="no-data">No timetable entries found for you.</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Day</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Subject</th>
                    <th>Class</th>
                    <th>Room</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($timetable as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['day_of_week']) ?></td>
                        <td><?= htmlspecialchars(date("g:i A", strtotime($row['start_time']))) ?></td>
                        <td><?= htmlspecialchars(date("g:i A", strtotime($row['end_time']))) ?></td>
                        <td><?= htmlspecialchars($row['subject_name']) ?></td>
                        <td><?= htmlspecialchars($row['class_level']) ?></td>
                        <td><?= htmlspecialchars($row['room_number']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
