<?php
// ====================================================
// FILE: student/view_assignments.php
// Student View and Submit Assignments
// ====================================================

define('APP_ACCESS', true);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_once __DIR__ . '/../includes/auth.php';

startSecureSession();

if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    redirect('../login.php');
}

$student_id = $_SESSION['user_id'];
$db = getDB();

/**
 * Get all assignments for a student
 * Returns an array with assignments and a submission map
 */
function getStudentAssignments($student_id) {
    $db = getDB();

    // Fetch student's subjects
    $subject_ids = $db->query("
        SELECT subject_id 
        FROM student_subjects 
        WHERE student_id = :student_id
    ")
    ->bind(':student_id', $student_id)
    ->fetchAll(PDO::FETCH_COLUMN);

    $assignments = [];

    // Fetch assignments for each subject
    if (!empty($subject_ids)) {
        foreach ($subject_ids as $subject_id) {
            $subject_assignments = getAssignmentsBySubject($subject_id);
            $assignments = array_merge($assignments, $subject_assignments);
        }
    }

    // Fetch student's submissions
    $submissions = getStudentSubmissions($student_id);
    $submission_map = [];
    foreach ($submissions as $sub) {
        $submission_map[$sub['assignment_id']] = $sub;
    }

    return [
        'assignments' => $assignments,
        'submissions' => $submission_map
    ];
}

// Call the function
$data = getStudentAssignments($student_id);
$assignments = $data['assignments'];
$submission_map = $data['submissions'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Assignments - Student Portal</title>

<!-- Bootstrap & Font Awesome -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* Reuse student panel styles for consistent look */
:root {
    --sidebar-width: 280px;
    --student-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

* { box-sizing: border-box; }
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fc; }

/* Sidebar (copied from other student pages) */
.sidebar { position: fixed; top: 0; left: 0; bottom: 0; width: var(--sidebar-width); background: var(--student-gradient); color: white; overflow-y: auto; z-index: 1000; box-shadow: 4px 0 15px rgba(0,0,0,0.1); }
.sidebar-header { padding: 2rem 1.5rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
.sidebar-header i { font-size: 3rem; margin-bottom: 0.5rem; display:block; }
.sidebar-header h4 { margin:0; font-size:1.2rem; }
.sidebar-menu { padding: 1rem 0; }
.sidebar-menu a { display:flex; align-items:center; padding:0.9rem 1.5rem; color:white; text-decoration:none; border-left:3px solid transparent; }
.sidebar-menu a.active, .sidebar-menu a:hover { background: rgba(255,255,255,0.08); border-left-color: white; }
.sidebar-menu a i { margin-right: 1rem; width:20px; text-align:center; }
.sidebar-footer { position:absolute; bottom:0; width:100%; padding:1.2rem; background: rgba(0,0,0,0.15); }

.main-content { margin-left: var(--sidebar-width); min-height:100vh; }
.topbar { background:white; padding:1rem 2rem; box-shadow:0 2px 10px rgba(0,0,0,0.05); display:flex; justify-content:space-between; align-items:center; position: sticky; top:0; z-index:999; }
.content-area { padding: 2rem; }

/* Keep assignment-specific styles */
.assignment-card { background: white; border-radius: 15px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 5px 20px rgba(0,0,0,0.08); transition: all 0.3s ease; border-left: 5px solid #667eea; }
.assignment-card.submitted { border-left-color: #11998e; }
.assignment-card.graded { border-left-color: #f093fb; }
.assignment-card.overdue { border-left-color: #e74a3b; }
.assignment-header { display:flex; justify-content:space-between; align-items:start; margin-bottom:1rem; }
.assignment-title { font-size:1.25rem; font-weight:700; color:#5a5c69; margin:0; }
.assignment-meta { display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:1rem; }
.meta-item { display:flex; align-items:center; gap:0.5rem; color:#858796; font-size:0.9rem; }
.meta-item i { color:#667eea; }
.assignment-description { color:#5a5c69; margin-bottom:1rem; line-height:1.6; }
.badge { padding:0.45rem 0.9rem; border-radius:50px; font-weight:600; font-size:0.82rem; }
.badge-pending { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color:white; }
.badge-submitted { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color:white; }
.badge-graded { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color:white; }
.badge-overdue { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color:white; }
.assignment-actions { display:flex; gap:1rem; margin-top:1rem; }
.btn-custom { padding:0.6rem 1.2rem; border-radius:50px; font-weight:600; border:none; cursor:pointer; }
.btn-submit { background:var(--student-gradient); color:white; }
.btn-view { background:white; color:#667eea; border:2px solid #667eea; }
.grade-display { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color:white; padding:1rem; border-radius:10px; margin-top:1rem; }
.feedback-box { background:#f8f9fc; padding:1rem; border-radius:10px; margin-top:1rem; border-left:4px solid #667eea; }
.empty-state { text-align:center; padding:3.5rem 2rem; background:white; border-radius:15px; box-shadow:0 5px 20px rgba(0,0,0,0.08); }

/* Modal */
.modal-content { border-radius: 12px; }

@media (max-width: 991px) {
    .sidebar { left: -100%; position: fixed; transition: left .25s ease; }
    .sidebar.show { left: 0; }
    .main-content { margin-left: 0; }
}

</style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-user-graduate"></i>
            <h4>School Portal</h4>
            <p>Student Panel</p>
        </div>

        <div class="sidebar-menu">
            <div class="menu-section">Main Menu</div>
            <a href="dashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="messages.php">
                <i class="fas fa-envelope"></i>
                <span>Messages</span>
            </a>

            <div class="menu-section">Academics</div>
            <a href="submit_assignment.php">
                <i class="fas fa-file-upload"></i>
                <span>Submit Assignment</span>
            </a>
            <a href="take_quiz.php">
                <i class="fas fa-brain"></i>
                <span>Take Quiz</span>
            </a>
            <a href="view_grades.php">
                <i class="fas fa-chart-line"></i>
                <span>View Grades</span>
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
            <a href="../logout.php" style="color: white; text-decoration:none; display:block; text-align:center;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div style="display:flex; align-items:center; gap:1rem;">
                <i class="fas fa-bars mobile-toggle" onclick="toggleSidebar()" style="cursor:pointer;"></i>
                <h2>My Assignments</h2>
            </div>

            <div class="topbar-right" style="display:flex; align-items:center; gap:1rem;">
                <div class="user-profile">
                    <div class="user-avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'S', 0, 1)) ?></div>
                    <div class="user-info" style="margin-left:0.5rem;">
                        <div class="name"><?= htmlspecialchars($_SESSION['username'] ?? 'Student') ?></div>
                        <div class="role">Student</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <div class="page-header" style="display:flex; justify-content:space-between; align-items:center; gap:1rem; margin-bottom:1.5rem;">
                <div>
                    <h3 style="margin:0; font-weight:700; color:#5a5c69;"><i class="fas fa-clipboard-list me-2"></i>My Assignments</h3>
                    <p class="text-muted mb-0">View and submit your assignments</p>
                </div>
                <a href="dashboard.php" class="btn btn-outline-secondary"> <i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
            </div>

            <?php if (empty($assignments)): ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <h4>No Assignments Yet</h4>
                    <p>Your teachers haven't posted any assignments yet. Check back later!</p>
                </div>
            <?php else: ?>
                <?php foreach ($assignments as $assignment): ?>
                    <?php
                    $assignment_id = $assignment['id'];
                    $is_submitted = isset($submission_map[$assignment_id]);
                    $submission = $submission_map[$assignment_id] ?? null;
                    $is_graded = $is_submitted && ($submission['status'] ?? '') === 'graded';
                    $is_overdue = !$is_submitted && strtotime($assignment['due_date']) < time();
                    $card_class = '';
                    if ($is_graded) { $card_class = 'graded'; }
                    elseif ($is_submitted) { $card_class = 'submitted'; }
                    elseif ($is_overdue) { $card_class = 'overdue'; }
                    ?>

                    <div class="assignment-card <?= $card_class ?>">
                        <div class="assignment-header">
                            <div>
                                <h4 class="assignment-title"><?= htmlspecialchars($assignment['title']) ?></h4>
                                <div class="assignment-meta">
                                    <div class="meta-item"><i class="fas fa-user-tie"></i><span><?= htmlspecialchars($assignment['teacher_name']) ?></span></div>
                                    <div class="meta-item"><i class="fas fa-book"></i><span><?= htmlspecialchars($assignment['subject']) ?></span></div>
                                    <div class="meta-item"><i class="fas fa-calendar"></i><span>Due: <?= date('M d, Y h:i A', strtotime($assignment['due_date'])) ?></span></div>
                                    <div class="meta-item"><i class="fas fa-star"></i><span><?= htmlspecialchars($assignment['total_marks']) ?> marks</span></div>
                                </div>
                            </div>
                            <div>
                                <?php if ($is_graded): ?>
                                    <span class="badge badge-graded"><i class="fas fa-check-circle me-1"></i>Graded</span>
                                <?php elseif ($is_submitted): ?>
                                    <span class="badge badge-submitted"><i class="fas fa-check me-1"></i>Submitted</span>
                                <?php elseif ($is_overdue): ?>
                                    <span class="badge badge-overdue"><i class="fas fa-exclamation-triangle me-1"></i>Overdue</span>
                                <?php else: ?>
                                    <span class="badge badge-pending"><i class="fas fa-clock me-1"></i>Pending</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($assignment['description'])): ?>
                            <div class="assignment-description"><?= nl2br(htmlspecialchars($assignment['description'])) ?></div>
                        <?php endif; ?>

                        <?php if (!empty($assignment['instructions'])): ?>
                            <div class="alert alert-info mt-2"><strong><i class="fas fa-info-circle me-2"></i>Instructions:</strong> <?= nl2br(htmlspecialchars($assignment['instructions'])) ?></div>
                        <?php endif; ?>

                        <?php if (!empty($assignment['file_path'])): ?>
                            <div class="mt-2"><a href="../<?= htmlspecialchars($assignment['file_path']) ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="fas fa-download me-1"></i>Download Assignment File</a></div>
                        <?php endif; ?>

                        <?php if ($is_graded && $submission): ?>
                            <div class="grade-display"><h5><i class="fas fa-award me-2"></i>Your Grade: <?= htmlspecialchars($submission['marks_obtained']) ?>/<?= htmlspecialchars($assignment['total_marks']) ?> (<?= round((($submission['marks_obtained'] ?? 0) / max(1, $assignment['total_marks'])) * 100, 2) ?>%)</h5></div>
                            <?php if (!empty($submission['feedback'])): ?><div class="feedback-box"><strong><i class="fas fa-comment-dots me-2"></i>Teacher Feedback:</strong><p class="mb-0 mt-2"><?= nl2br(htmlspecialchars($submission['feedback'])) ?></p></div><?php endif; ?>
                        <?php endif; ?>

                        <div class="assignment-actions">
                            <?php if (!$is_submitted && !$is_overdue): ?>
                                <button class="btn btn-custom btn-submit" onclick="openSubmitModal(<?= htmlspecialchars($assignment_id) ?>, '<?= htmlspecialchars(addslashes($assignment['title'])) ?>')"><i class="fas fa-upload me-2"></i>Submit Assignment</button>
                            <?php elseif ($is_submitted && !empty($submission['submission_file'])): ?>
                                <a href="../<?= htmlspecialchars($submission['submission_file']) ?>" class="btn btn-custom btn-view" target="_blank"><i class="fas fa-eye me-2"></i>View My Submission</a>
                            <?php endif; ?>
                            <?php if ($is_overdue && !$is_submitted): ?>
                                <button class="btn btn-custom btn-submit" onclick="openSubmitModal(<?= htmlspecialchars($assignment_id) ?>, '<?= htmlspecialchars(addslashes($assignment['title'])) ?>', true)"><i class="fas fa-upload me-2"></i>Submit Late</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Submit Assignment Modal -->
    <div class="modal fade" id="submitModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background:var(--student-gradient); color:white; border-radius:12px 12px 0 0;">
                    <h5 class="modal-title"><i class="fas fa-upload me-2"></i>Submit Assignment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="submitForm" enctype="multipart/form-data">
                        <input type="hidden" name="assignment_id" id="assignment_id">
                        <div class="mb-3"><label class="form-label fw-bold">Assignment Title</label><p id="assignment_title" class="text-muted"></p></div>
                        <div id="lateWarning" class="alert alert-warning d-none"><i class="fas fa-exclamation-triangle me-2"></i><strong>Late Submission:</strong> This assignment is past the due date. Your submission may receive reduced marks.</div>
                        <div class="mb-3"><label for="submission_file" class="form-label fw-bold">Upload Your Work <span class="text-danger">*</span></label><input type="file" class="form-control" id="submission_file" name="submission_file" accept=".pdf,.doc,.docx,.jpg,.png" required><div class="form-text">Accepted formats: PDF, DOC, DOCX, JPG, PNG (Max 10MB)</div></div>
                        <div class="mb-3"><label for="submission_text" class="form-label fw-bold">Additional Notes (Optional)</label><textarea class="form-control" id="submission_text" name="submission_text" rows="4" placeholder="Add any comments or notes for your teacher..."></textarea></div>
                        <div class="d-grid gap-2"><button type="submit" class="btn btn-custom btn-submit"><i class="fas fa-paper-plane me-2"></i>Submit Assignment</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let submitModal;
    function toggleSidebar(){ document.getElementById('sidebar').classList.toggle('show'); }
    document.addEventListener('DOMContentLoaded', function(){ submitModal = new bootstrap.Modal(document.getElementById('submitModal'));
        document.getElementById('submitForm').addEventListener('submit', function(e){ e.preventDefault(); const formData = new FormData(this); const submitBtn = this.querySelector('button[type="submit"]'); const originalText = submitBtn.innerHTML; submitBtn.disabled = true; submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...'; fetch('submit_assignment_handler.php', { method:'POST', body: formData }).then(r=>r.json()).then(data=>{ if(data.success){ alert('Assignment submitted successfully!'); submitModal.hide(); location.reload(); } else { alert('Error: ' + data.message); submitBtn.disabled = false; submitBtn.innerHTML = originalText; } }).catch(err=>{ alert('An error occurred. Please try again.'); console.error(err); submitBtn.disabled = false; submitBtn.innerHTML = originalText; }); }); });
    function openSubmitModal(assignmentId, assignmentTitle, isLate = false){ document.getElementById('assignment_id').value = assignmentId; document.getElementById('assignment_title').textContent = assignmentTitle; if(isLate) document.getElementById('lateWarning').classList.remove('d-none'); else document.getElementById('lateWarning').classList.add('d-none'); document.getElementById('submitForm').reset(); document.getElementById('assignment_id').value = assignmentId; submitModal.show(); }
    </script>
</body>
</html>