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
<title>View Assignments - Student Portal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --student-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f8f9fc;
}

.page-header {
    background: white;
    padding: 2rem;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    margin-bottom: 2rem;
}

.page-header h2 {
    color: #5a5c69;
    font-weight: 700;
    margin: 0;
}

.assignment-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    border-left: 5px solid #667eea;
}

.assignment-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

.assignment-card.submitted {
    border-left-color: #11998e;
}

.assignment-card.graded {
    border-left-color: #f093fb;
}

.assignment-card.overdue {
    border-left-color: #e74a3b;
}

.assignment-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 1rem;
}

.assignment-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #5a5c69;
    margin: 0;
}

.assignment-meta {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #858796;
    font-size: 0.9rem;
}

.meta-item i {
    color: #667eea;
}

.assignment-description {
    color: #5a5c69;
    margin-bottom: 1rem;
    line-height: 1.6;
}

.badge {
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.85rem;
}

.badge-pending {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
}

.badge-submitted {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
}

.badge-graded {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
}

.badge-overdue {
    background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    color: white;
}

.assignment-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

.btn-custom {
    padding: 0.7rem 1.5rem;
    border-radius: 50px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-submit {
    background: var(--student-gradient);
    color: white;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.btn-view {
    background: white;
    color: #667eea;
    border: 2px solid #667eea;
}

.btn-view:hover {
    background: #667eea;
    color: white;
}

.grade-display {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    padding: 1rem;
    border-radius: 10px;
    margin-top: 1rem;
}

.grade-display h5 {
    margin: 0;
    font-size: 1.1rem;
}

.feedback-box {
    background: #f8f9fc;
    padding: 1rem;
    border-radius: 10px;
    margin-top: 1rem;
    border-left: 4px solid #667eea;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
}

.empty-state i {
    font-size: 4rem;
    color: #e3e6f0;
    margin-bottom: 1rem;
}

.empty-state h4 {
    color: #5a5c69;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #858796;
}

/* Modal Styles */
.modal-content {
    border-radius: 15px;
    border: none;
}

.modal-header {
    background: var(--student-gradient);
    color: white;
    border-radius: 15px 15px 0 0;
}

.modal-body {
    padding: 2rem;
}
</style>
</head>
<body>
<div class="container py-5">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2><i class="fas fa-clipboard-list me-2"></i>My Assignments</h2>
                <p class="text-muted mb-0">View and submit your assignments</p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
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
            $is_graded = $is_submitted && $submission['status'] === 'graded';
            $is_overdue = !$is_submitted && strtotime($assignment['due_date']) < time();
            
            $card_class = '';
            if ($is_graded) {
                $card_class = 'graded';
            } elseif ($is_submitted) {
                $card_class = 'submitted';
            } elseif ($is_overdue) {
                $card_class = 'overdue';
            }
            ?>
            
            <div class="assignment-card <?= $card_class ?>">
                <div class="assignment-header">
                    <div>
                        <h3 class="assignment-title"><?= htmlspecialchars($assignment['title']) ?></h3>
                        <div class="assignment-meta">
                            <div class="meta-item">
                                <i class="fas fa-user-tie"></i>
                                <span><?= htmlspecialchars($assignment['teacher_name']) ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-book"></i>
                                <span><?= htmlspecialchars($assignment['subject']) ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span>Due: <?= date('M d, Y h:i A', strtotime($assignment['due_date'])) ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-star"></i>
                                <span><?= $assignment['total_marks'] ?> marks</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <?php if ($is_graded): ?>
                            <span class="badge badge-graded">
                                <i class="fas fa-check-circle me-1"></i>Graded
                            </span>
                        <?php elseif ($is_submitted): ?>
                            <span class="badge badge-submitted">
                                <i class="fas fa-check me-1"></i>Submitted
                            </span>
                        <?php elseif ($is_overdue): ?>
                            <span class="badge badge-overdue">
                                <i class="fas fa-exclamation-triangle me-1"></i>Overdue
                            </span>
                        <?php else: ?>
                            <span class="badge badge-pending">
                                <i class="fas fa-clock me-1"></i>Pending
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($assignment['description']): ?>
                    <div class="assignment-description">
                        <?= nl2br(htmlspecialchars($assignment['description'])) ?>
                    </div>
                <?php endif; ?>

                <?php if ($assignment['instructions']): ?>
                    <div class="alert alert-info mt-2">
                        <strong><i class="fas fa-info-circle me-2"></i>Instructions:</strong>
                        <?= nl2br(htmlspecialchars($assignment['instructions'])) ?>
                    </div>
                <?php endif; ?>

                <?php if ($assignment['file_path']): ?>
                    <div class="mt-2">
                        <a href="../<?= htmlspecialchars($assignment['file_path']) ?>" 
                           class="btn btn-sm btn-outline-primary" 
                           target="_blank">
                            <i class="fas fa-download me-1"></i>Download Assignment File
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($is_graded && $submission): ?>
                    <div class="grade-display">
                        <h5>
                            <i class="fas fa-award me-2"></i>
                            Your Grade: <?= $submission['marks_obtained'] ?>/<?= $assignment['total_marks'] ?>
                            (<?= round(($submission['marks_obtained'] / $assignment['total_marks']) * 100, 2) ?>%)
                        </h5>
                    </div>
                    
                    <?php if ($submission['feedback']): ?>
                        <div class="feedback-box">
                            <strong><i class="fas fa-comment-dots me-2"></i>Teacher Feedback:</strong>
                            <p class="mb-0 mt-2"><?= nl2br(htmlspecialchars($submission['feedback'])) ?></p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="assignment-actions">
                    <?php if (!$is_submitted && !$is_overdue): ?>
                        <button class="btn btn-custom btn-submit" 
                                onclick="openSubmitModal(<?= $assignment_id ?>, '<?= htmlspecialchars(addslashes($assignment['title'])) ?>')">
                            <i class="fas fa-upload me-2"></i>Submit Assignment
                        </button>
                    <?php elseif ($is_submitted && $submission['submission_file']): ?>
                        <a href="../<?= htmlspecialchars($submission['submission_file']) ?>" 
                           class="btn btn-custom btn-view" 
                           target="_blank">
                            <i class="fas fa-eye me-2"></i>View My Submission
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($is_overdue && !$is_submitted): ?>
                        <button class="btn btn-custom btn-submit" 
                                onclick="openSubmitModal(<?= $assignment_id ?>, '<?= htmlspecialchars(addslashes($assignment['title'])) ?>', true)">
                            <i class="fas fa-upload me-2"></i>Submit Late
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Submit Assignment Modal -->
<div class="modal fade" id="submitModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-upload me-2"></i>Submit Assignment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="submitForm" enctype="multipart/form-data">
                    <input type="hidden" name="assignment_id" id="assignment_id">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Assignment Title</label>
                        <p id="assignment_title" class="text-muted"></p>
                    </div>
                    
                    <div id="lateWarning" class="alert alert-warning d-none">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Late Submission:</strong> This assignment is past the due date. Your submission may receive reduced marks.
                    </div>
                    
                    <div class="mb-3">
                        <label for="submission_file" class="form-label fw-bold">
                            Upload Your Work <span class="text-danger">*</span>
                        </label>
                        <input type="file" 
                               class="form-control" 
                               id="submission_file" 
                               name="submission_file" 
                               accept=".pdf,.doc,.docx,.jpg,.png" 
                               required>
                        <div class="form-text">Accepted formats: PDF, DOC, DOCX, JPG, PNG (Max 10MB)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="submission_text" class="form-label fw-bold">
                            Additional Notes (Optional)
                        </label>
                        <textarea class="form-control" 
                                  id="submission_text" 
                                  name="submission_text" 
                                  rows="4" 
                                  placeholder="Add any comments or notes for your teacher..."></textarea>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-custom btn-submit">
                            <i class="fas fa-paper-plane me-2"></i>Submit Assignment
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let submitModal;

document.addEventListener('DOMContentLoaded', function() {
    submitModal = new bootstrap.Modal(document.getElementById('submitModal'));
    
    // Form submission
    document.getElementById('submitForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
        
        fetch('submit_assignment_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Assignment submitted successfully!');
                submitModal.hide();
                location.reload();
            } else {
                alert('Error: ' + data.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(error => {
            alert('An error occurred. Please try again.');
            console.error('Error:', error);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    });
});

function openSubmitModal(assignmentId, assignmentTitle, isLate = false) {
    document.getElementById('assignment_id').value = assignmentId;
    document.getElementById('assignment_title').textContent = assignmentTitle;
    
    if (isLate) {
        document.getElementById('lateWarning').classList.remove('d-none');
    } else {
        document.getElementById('lateWarning').classList.add('d-none');
    }
    
    document.getElementById('submitForm').reset();
    document.getElementById('assignment_id').value = assignmentId;
    
    submitModal.show();
}
</script>
</body>
</html>