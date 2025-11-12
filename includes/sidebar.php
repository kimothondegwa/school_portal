<?php
if (!defined('APP_ACCESS')) die('Direct access not permitted');
$role = $_SESSION['role'] ?? 'guest';
$user = isset($user) ? $user : (function_exists('getCurrentUser') ? getCurrentUser() : null);
?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-school"></i>
        <h4>School Portal</h4>
        <p><?= htmlspecialchars(ucfirst($role)) ?> Panel</p>
    </div>

    <div class="sidebar-menu">
        <div class="menu-section">Main Menu</div>
        <?php if ($role === 'teacher'): ?>
            <a href="dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a>
            <a href="upload_assignment.php"><i class="fas fa-file-upload"></i><span>Upload Assignments</span></a>
            <a href="create_quiz.php"><i class="fas fa-brain"></i><span>Create Quizzes</span></a>
            <a href="mark_attendance.php"><i class="fas fa-user-check"></i><span>Mark Attendance</span></a>

            <div class="menu-section">Academic</div>
            <a href="mark_grades.php"><i class="fas fa-award"></i><span>Grade Students</span></a>
            <a href="my_classes.php"><i class="fas fa-users"></i><span>My Classes</span></a>
            <a href="schedule.php"><i class="fas fa-calendar-alt"></i><span>Class Schedule</span></a>

            <div class="menu-section">Communication</div>
            <a href="comment_students.php"><i class="fas fa-bell"></i><span>Notifications</span></a>
            <a href="message.php"><i class="fas fa-envelope"></i><span>Messages</span></a>

        <?php elseif ($role === 'student'): ?>
            <a href="dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a>
            <a href="messages.php"><i class="fas fa-envelope"></i><span>Messages</span></a>

            <div class="menu-section">Academics</div>
            <a href="submit_assignment.php"><i class="fas fa-file-upload"></i><span>Submit Assignment</span></a>
            <a href="take_quiz.php"><i class="fas fa-brain"></i><span>Take Quiz</span></a>
            <a href="view_grades.php"><i class="fas fa-chart-line"></i><span>View Grades</span></a>
            <a href="attendance.php"><i class="fas fa-calendar-check"></i><span>My Attendance</span></a>
            <a href="view_assignment.php"><i class="fas fa-calendar-alt"></i><span>View Assignment</span></a>
            <a href="comment.php"><i class="fas fa-comments"></i><span>View Comments</span></a>

            <div class="menu-section">Schedule</div>
            <a href="timetable.php"><i class="fas fa-calendar"></i><span>TimeTable</span></a>
            <a href="settings.php"><i class="fas fa-cog"></i><span>Settings</span></a>

        <?php elseif ($role === 'admin'): ?>
            <a href="dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a>
            <a href="manage_teachers.php"><i class="fas fa-chalkboard-teacher"></i><span>Manage Teachers</span></a>
            <a href="manage_students.php"><i class="fas fa-user-graduate"></i><span>View Students</span></a>
            <a href="manage_subjects.php"><i class="fas fa-book"></i><span>Manage Subjects</span></a>
            <a href="manage_timetable.php"><i class="fas fa-calendar-alt"></i><span>Timetables</span></a>

            <div class="menu-section">Academic</div>
            <a href="assignments.php"><i class="fas fa-tasks"></i><span>Assignments</span></a>
            <a href="attendance.php"><i class="fas fa-clipboard-check"></i><span>Attendance</span></a>
            <a href="grades.php"><i class="fas fa-chart-line"></i><span>Grades</span></a>

            <div class="menu-section">Communication</div>
            <a href="notifications.php"><i class="fas fa-bell"></i><span>Notifications</span></a>
            <a href="message.php"><i class="fas fa-envelope"></i><span>Message</span></a>

            <div class="menu-section">System</div>
            <a href="reports.php"><i class="fas fa-file-alt"></i><span>Reports</span></a>
            <a href="settings.php"><i class="fas fa-cog"></i><span>Settings</span></a>

        <?php else: ?>
            <a href="/">Home</a>
        <?php endif; ?>
    </div>

    <div class="sidebar-footer">
        <a href="../logout.php" style="background: rgba(255,255,255,0.1); border-radius: 8px; padding: 0.8rem; text-align: center; display: block;">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<script>
// Auto highlight active sidebar link based on current filename
(function(){
    try{
        const path = window.location.pathname.split('/').pop();
        const links = document.querySelectorAll('.sidebar-menu a');
        links.forEach(a => {
            let href = a.getAttribute('href').split('/').pop();
            if (!href) return;
            // Normalize index or empty to dashboard
            if (href === '' || href === 'index.php') href = 'dashboard.php';
            if (path === href) {
                a.classList.add('active');
            } else {
                a.classList.remove('active');
            }
        });
    }catch(e){console.warn('Sidebar active link script error', e);}    
})();
</script>
