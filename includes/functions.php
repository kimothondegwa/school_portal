<?php
// Prevent direct access
if (!defined('APP_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Sanitize input data
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Hash password securely
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate random string
 */
function generateRandomString($length = 10) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Redirect to another page
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Check if user is logged in
 */
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['role']);
    }
}


/**
 * Check if user has specific role
 */
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Require login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect(APP_URL . '/login.php');
    }
}

/**
 * Require specific role
 */
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        redirect(APP_URL . '/index.php');
    }
}

/**
 * Format date for display
/**
 * Format date for display
 */
function formatDate($date, $format = DISPLAY_DATE_FORMAT) {
    if (empty($date)) return 'N/A';
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : 'Invalid date';
}

/**
 * Format datetime for display
 */
function formatDateTime($datetime, $format = DISPLAY_DATETIME_FORMAT) {
    if (empty($datetime)) return 'N/A';
    $timestamp = strtotime($datetime);
    return $timestamp ? date($format, $timestamp) : 'Invalid datetime';
}

/**
 * Get time ago string (e.g., "2 hours ago")
 */
function timeAgo($datetime) {
    if (empty($datetime)) return 'N/A';

    $timestamp = strtotime($datetime);
    if (!$timestamp) return 'Invalid date';

    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return formatDate($datetime);
    }
}

/**
 * Show success message
 */
function setSuccessMessage($message) {
    $_SESSION['success_message'] = $message;
}

/**
 * Show error message
 */
function setErrorMessage($message) {
    $_SESSION['error_message'] = $message;
}

/**
 * Get and clear success message
 */
function getSuccessMessage() {
    if (isset($_SESSION['success_message'])) {
        $message = $_SESSION['success_message'];
        unset($_SESSION['success_message']);
        return $message;
    }
    return null;
}

/**
 * Get and clear error message
 */
function getErrorMessage() {
    if (isset($_SESSION['error_message'])) {
        $message = $_SESSION['error_message'];
        unset($_SESSION['error_message']);
        return $message;
    }
    return null;
}



/**
 * Upload file
 */


/**
 * Log activity to audit log
 */
function logActivity($userId, $action, $tableAffected = null, $recordId = null, $description = null) {
    $db = getDB();
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    
    $sql = "INSERT INTO audit_log (user_id, action, table_affected, record_id, description, ip_address) 
            VALUES (:user_id, :action, :table_affected, :record_id, :description, :ip_address)";
    
    $db->query($sql)
       ->bind(':user_id', $userId)
       ->bind(':action', $action)
       ->bind(':table_affected', $tableAffected)
       ->bind(':record_id', $recordId)
       ->bind(':description', $description)
       ->bind(':ip_address', $ipAddress)
       ->execute();
}

/**
 * Escape output for HTML
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
};
// ====================================================
// FILE: includes/helpers.php
// Helper Functions for Teacher-Student Integration
// ====================================================

if (!defined('APP_ACCESS')) {
    die('Direct access not permitted');
}

// ============================================
// ASSIGNMENT FUNCTIONS
// ============================================
/**
 * Get student's assignment submissions
 */

function getAssignmentsBySubject($subject_id) {
    $db = getDB();
    return $db->query("
        SELECT a.*, u.username as teacher_name
        FROM assignments a
        JOIN users u ON a.teacher_id = u.user_id
        WHERE a.subject_id = :subject_id
        ORDER BY a.due_date ASC
    ")
    ->bind(':subject_id', $subject_id)
    ->fetchAll();
}
function getStudentSubmissions($student_id) {
    $db = getDB();
    return $db->query("
        SELECT s.*, a.title, a.subject_id, a.due_date, a.total_marks,
               u.username as teacher_name
        FROM submissions s
        JOIN assignments a ON s.assignment_id = a.assignment_id
        JOIN users u ON a.teacher_id = u.user_id
        WHERE s.student_id = :student_id
        ORDER BY s.submitted_at DESC
    ")
    ->bind(':student_id', $student_id)
    ->fetchAll();
}

/**
 * Check if student has submitted an assignment
 */
function hasSubmittedAssignment($student_id, $assignment_id) {
    $db = getDB();
    $result = $db->query("
        SELECT submission_id FROM submissions 
        WHERE student_id = :student_id AND assignment_id = :assignment_id
    ")
    ->bind(':student_id', $student_id)
    ->bind(':assignment_id', $assignment_id)
    ->fetch();
    
    return !empty($result);
}

/**
 * Submit an assignment
 */
function submitAssignment($assignment_id, $student_id, $file_path, $text = '') {
    $db = getDB();
    
    // Check if already submitted
    if (hasSubmittedAssignment($student_id, $assignment_id)) {
        return ['success' => false, 'message' => 'Assignment already submitted'];
    }
    
    // Check if past due date
    $assignment = $db->query("SELECT due_date, teacher_id FROM assignments WHERE assignment_id = :id")
                     ->bind(':id', $assignment_id)
                     ->fetch();
    
    $status = (strtotime($assignment['due_date']) < time()) ? 'late' : 'submitted';
    
    $result = $db->query("
        INSERT INTO submissions 
        (assignment_id, student_id, file_path, submission_text, status)
        VALUES (:assignment_id, :student_id, :file, :text, :status)
    ")
    ->bind(':assignment_id', $assignment_id)
    ->bind(':student_id', $student_id)
    ->bind(':file', $file_path)
    ->bind(':text', $text)
    ->bind(':status', $status)
    ->execute();
    
    if ($result) {
        // Create notification for teacher
        createNotification(
            $assignment['teacher_id'],
            $student_id,
            'New Assignment Submission',
            'A student has submitted an assignment',
            'assignment',
            $assignment_id
        );
        
        return ['success' => true, 'message' => 'Assignment submitted successfully'];
    }
    
    return ['success' => false, 'message' => 'Failed to submit assignment'];
}

/**
 * Get pending assignments for grading (Teacher)
 */
function getPendingSubmissions($teacher_id) {
    $db = getDB();
    return $db->query("
        SELECT s.*, a.title, a.total_marks, st.admission_number,
               u.username as student_name
        FROM submissions s
        JOIN assignments a ON s.assignment_id = a.assignment_id
        JOIN users u ON s.student_id = u.user_id
        JOIN students st ON u.user_id = st.user_id
        WHERE a.teacher_id = :teacher_id AND s.status IN ('submitted', 'late')
        ORDER BY s.submitted_at ASC
    ")
    ->bind(':teacher_id', $teacher_id)
    ->fetchAll();
}

/**
 * Grade an assignment submission
 */
function gradeSubmission($submission_id, $marks, $feedback, $grader_id) {
    $db = getDB();
    
    $result = $db->query("
        UPDATE submissions 
        SET marks_obtained = :marks, 
            feedback = :feedback, 
            status = 'graded',
            graded_at = NOW(),
            graded_by = :grader_id
        WHERE submission_id = :id
    ")
    ->bind(':marks', $marks)
    ->bind(':feedback', $feedback)
    ->bind(':grader_id', $grader_id)
    ->bind(':id', $submission_id)
    ->execute();
    
    if ($result) {
        // Get student ID and create notification
        $submission = $db->query("SELECT student_id, assignment_id FROM submissions WHERE submission_id = :id")
                         ->bind(':id', $submission_id)
                         ->fetch();
        
        createNotification(
            $submission['student_id'],
            $grader_id,
            'Assignment Graded',
            'Your assignment has been graded',
            'grade',
            $submission_id
        );
        
        return true;
    }
    
    return false;
}

// ============================================
// QUIZ FUNCTIONS
// ============================================

/**
 * Get available quizzes for a class
 */
function getQuizzesByClass($class_level, $status = 'active') {
    $db = getDB();
    return $db->query("
        SELECT q.*, u.username as teacher_name,
               (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as question_count
        FROM quizzes q
        JOIN users u ON q.teacher_id = u.id
        WHERE q.class_level = :class AND q.status = :status
        AND (q.start_time IS NULL OR q.start_time <= NOW())
        AND (q.end_time IS NULL OR q.end_time >= NOW())
        ORDER BY q.created_at DESC
    ")
    ->bind(':class', $class_level)
    ->bind(':status', $status)
    ->fetchAll();
}

/**
 * Check if student has attempted a quiz
 */
function hasAttemptedQuiz($student_id, $quiz_id) {
    $db = getDB();
    $result = $db->query("
        SELECT id FROM quiz_attempts 
        WHERE student_id = :student_id AND quiz_id = :quiz_id
    ")
    ->bind(':student_id', $student_id)
    ->bind(':quiz_id', $quiz_id)
    ->fetch();
    
    return !empty($result);
}

/**
 * Get student's quiz attempts
 */
function getStudentQuizAttempts($student_id) {
    $db = getDB();
    return $db->query("
        SELECT a.*, q.title, q.subject, q.total_marks,
               u.username as teacher_name
        FROM quiz_attempts a
        JOIN quizzes q ON a.quiz_id = q.id
        JOIN users u ON q.teacher_id = u.id
        WHERE a.student_id = :student_id
        ORDER BY a.started_at DESC
    ")
    ->bind(':student_id', $student_id)
    ->fetchAll();
}

// ============================================
// GRADES FUNCTIONS
// ============================================

/**
 * Get student grades
 */
function getStudentGrades($student_id, $term = null) {
    $db = getDB();
    $query = "
        SELECT g.*, u.username as teacher_name
        FROM grades g
        JOIN users u ON g.teacher_id = u.id
        WHERE g.student_id = :student_id
    ";
    
    if ($term) {
        $query .= " AND g.term = :term";
    }
    
    $query .= " ORDER BY g.exam_date DESC, g.subject";
    
    $stmt = $db->query($query)->bind(':student_id', $student_id);
    
    if ($term) {
        $stmt->bind(':term', $term);
    }
    
    return $stmt->fetchAll();
}

/**
 * Calculate grade letter from percentage
 */
function calculateGrade($percentage) {
    if ($percentage >= 90) return 'A+';
    if ($percentage >= 80) return 'A';
    if ($percentage >= 70) return 'B+';
    if ($percentage >= 60) return 'B';
    if ($percentage >= 50) return 'C';
    if ($percentage >= 40) return 'D';
    return 'F';
}

/**
 * Add/Update grade
 */
function addGrade($student_id, $teacher_id, $subject, $marks_obtained, $total_marks, $exam_type, $term, $year) {
    $db = getDB();
    
    $percentage = ($marks_obtained / $total_marks) * 100;
    $grade = calculateGrade($percentage);
    
    $result = $db->query("
        INSERT INTO grades 
        (student_id, teacher_id, subject, exam_type, term, academic_year, 
         marks_obtained, total_marks, percentage, grade, exam_date)
        VALUES 
        (:student_id, :teacher_id, :subject, :exam_type, :term, :year,
         :marks, :total, :percentage, :grade, CURDATE())
    ")
    ->bind(':student_id', $student_id)
    ->bind(':teacher_id', $teacher_id)
    ->bind(':subject', $subject)
    ->bind(':exam_type', $exam_type)
    ->bind(':term', $term)
    ->bind(':year', $year)
    ->bind(':marks', $marks_obtained)
    ->bind(':total', $total_marks)
    ->bind(':percentage', $percentage)
    ->bind(':grade', $grade)
    ->execute();
    
    if ($result) {
        createNotification(
            $student_id,
            $teacher_id,
            'New Grade Posted',
            "Your grade for $subject has been posted",
            'grade',
            $db->lastInsertId()
        );
        return true;
    }
    
    return false;
}

// ============================================
// ATTENDANCE FUNCTIONS
// ============================================

/**
 * Get student attendance records
 */
function getStudentAttendance($student_id, $start_date = null, $end_date = null) {
    $db = getDB();
    $query = "
        SELECT a.*, u.username as teacher_name
        FROM attendance a
        JOIN users u ON a.teacher_id = u.id
        WHERE a.student_id = :student_id
    ";
    
    if ($start_date && $end_date) {
        $query .= " AND a.attendance_date BETWEEN :start_date AND :end_date";
    }
    
    $query .= " ORDER BY a.attendance_date DESC";
    
    $stmt = $db->query($query)->bind(':student_id', $student_id);
    
    if ($start_date && $end_date) {
        $stmt->bind(':start_date', $start_date)
             ->bind(':end_date', $end_date);
    }
    
    return $stmt->fetchAll();
}

/**
 * Calculate attendance percentage
 */
function calculateAttendancePercentage($student_id, $start_date = null, $end_date = null) {
    $records = getStudentAttendance($student_id, $start_date, $end_date);
    
    if (empty($records)) {
        return 0;
    }
    
    $total = count($records);
    $present = 0;
    
    foreach ($records as $record) {
        if ($record['status'] === 'present') {
            $present++;
        }
    }
    
    return round(($present / $total) * 100, 2);
}

// ============================================
// NOTIFICATION FUNCTIONS
// ============================================

/**
 * Create a notification
 */
function createNotification($recipient_id, $sender_id, $title, $message, $type = 'general', $related_id = null) {
    $db = getDB();
    return $db->query("
        INSERT INTO notifications 
        (recipient_id, sender_id, title, message, type, related_id, created_at, is_read)
        VALUES (:recipient_id, :sender_id, :title, :message, :type, :related_id, NOW(), 0)
    ")
    ->bind(':recipient_id', $recipient_id)
    ->bind(':sender_id', $sender_id)
    ->bind(':title', $title)
    ->bind(':message', $message)
    ->bind(':type', $type)
    ->bind(':related_id', $related_id)
    ->execute();
}

/**
 * Get unread notification count
 */
function getUnreadNotificationCount($recipient_id) {
    $db = getDB();
    $result = $db->query("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE recipient_id = :recipient_id AND is_read = 0
    ")
    ->bind(':recipient_id', $recipient_id)
    ->fetch();
    
    return $result['count'] ?? 0;
}

/**
 * Get recent notifications
 */
function getRecentNotifications($recipient_id, $limit = 10) {
    $db = getDB();
    return $db->query("
        SELECT * FROM notifications 
        WHERE recipient_id = :recipient_id
        ORDER BY created_at DESC
        LIMIT :limit
    ")
    ->bind(':recipient_id', $recipient_id)
    ->bind(':limit', $limit)
    ->fetchAll();
}

/**
 * Mark notification as read
 */
function markNotificationRead($notification_id) {
    $db = getDB();
    return $db->query("
        UPDATE notifications 
        SET is_read = 1 
        WHERE id = :id
    ")
    ->bind(':id', $notification_id)
    ->execute();
}

/**
 * Get notification badge HTML (for use in all pages)
 */
function getNotificationBadgeHTML($user_id, $notification_page = 'comment.php') {
    $count = getUnreadNotificationCount($user_id);
    if ($count > 0) {
        $displayCount = $count > 9 ? '9+' : $count;
        return "
            <div class=\"notification-icon\">
                <a href=\"{$notification_page}\" style=\"color: inherit; text-decoration: none;\">
                    <i class=\"fas fa-bell\"></i>
                    <span class=\"notification-badge\" style=\"background: #ff4444; animation: pulse 1.5s infinite;\">
                        {$displayCount}
                    </span>
                </a>
            </div>
        ";
    }
    return "
        <div class=\"notification-icon\">
            <a href=\"{$notification_page}\" style=\"color: inherit; text-decoration: none;\">
                <i class=\"fas fa-bell\"></i>
            </a>
        </div>
    ";
}

// ============================================
// MESSAGE FUNCTIONS
// ============================================

/**
 * Send a message
 */
function sendMessage($sender_id, $receiver_id, $subject, $message, $parent_id = null) {
    $db = getDB();
    $result = $db->query("
        INSERT INTO messages 
        (sender_id, receiver_id, subject, message, parent_message_id)
        VALUES (:sender, :receiver, :subject, :message, :parent)
    ")
    ->bind(':sender', $sender_id)
    ->bind(':receiver', $receiver_id)
    ->bind(':subject', $subject)
    ->bind(':message', $message)
    ->bind(':parent', $parent_id)
    ->execute();
    
    if ($result) {
        // Create notification for receiver
        createNotification(
            $receiver_id,
            $sender_id,
            'New Message',
            'You have received a new message',
            'general',
            $db->lastInsertId()
        );
    }
    
    return $result;
}

/**
 * Get unread message count
 */
function getUnreadMessageCount($user_id) {
    $db = getDB();
    $result = $db->query("
        SELECT COUNT(*) as count 
        FROM messages 
        WHERE receiver_id = :user_id AND is_read = 0
    ")
    ->bind(':user_id', $user_id)
    ->fetch();
    
    return $result['count'] ?? 0;
}
/**
 * Teacher comments on a student (visible to admin)
 */
function commentStudent($teacher_id, $student_id, $comment_text, $priority = 'normal') {
    $db = getDB();

    // 1️⃣ Insert into notifications table so both student and admin can see
    $result = $db->query("
        INSERT INTO notifications 
        (title, message, recipient_role, recipient_id, sender_id, is_read, priority, created_at)
        VALUES (:title, :message, :role, :student_id, :teacher_id, 0, :priority, NOW())
    ")
    ->bind(':title', 'Teacher Comment')
    ->bind(':message', $comment_text)
    ->bind(':role', 'student')
    ->bind(':student_id', $student_id)
    ->bind(':teacher_id', $teacher_id)
    ->bind(':priority', $priority)
    ->execute();

    if ($result) {
        // 2️⃣ Also log it for admin visibility in the audit log
        logActivity($teacher_id, 'comment_student', 'notifications', $db->lastInsertId(), "Teacher commented on student #$student_id");
        return ['success' => true, 'message' => 'Comment sent successfully'];
    }

    return ['success' => false, 'message' => 'Failed to send comment'];
}

// ============================================
// DASHBOARD STATISTICS
// ============================================

/**
 * Get student dashboard statistics
 */
function getStudentDashboardStats($student_id, $class_level) {
    $stats = [];
    
    // Pending assignments
    $db = getDB();
    $result = $db->query("
        SELECT COUNT(*) as count FROM assignments 
        WHERE class_level = :class AND status = 'active'
        AND due_date >= NOW()
        AND id NOT IN (
            SELECT assignment_id FROM assignment_submissions 
            WHERE student_id = :student_id
        )
    ")
    ->bind(':class', $class_level)
    ->bind(':student_id', $student_id)
    ->fetch();
    $stats['pending_assignments'] = $result['count'] ?? 0;
    
    // Available quizzes
    $result = $db->query("
        SELECT COUNT(*) as count FROM quizzes 
        WHERE class_level = :class AND status = 'active'
        AND (start_time IS NULL OR start_time <= NOW())
        AND (end_time IS NULL OR end_time >= NOW())
        AND id NOT IN (
            SELECT quiz_id FROM quiz_attempts 
            WHERE student_id = :student_id
        )
    ")
    ->bind(':class', $class_level)
    ->bind(':student_id', $student_id)
    ->fetch();
    $stats['available_quizzes'] = $result['count'] ?? 0;
    
    // Unread messages
    $stats['unread_messages'] = getUnreadMessageCount($student_id);
    
    // Unread notifications — map student_id -> user_id (notifications use recipient_id which is a user_id)
    try {
        $db->query("SELECT user_id FROM students WHERE student_id = :sid");
        $db->bind(':sid', $student_id);
        $studentUserRow = $db->fetch();
        $stats['unread_notifications'] = $studentUserRow ? getUnreadNotificationCount($studentUserRow['user_id']) : 0;
    } catch (Exception $e) {
        $stats['unread_notifications'] = 0;
    }
    
    // Attendance percentage (last 30 days)
    $start_date = date('Y-m-d', strtotime('-30 days'));
    $end_date = date('Y-m-d');
    $stats['attendance_percentage'] = calculateAttendancePercentage($student_id, $start_date, $end_date);
    
    return $stats;
}

/**
 * Get teacher dashboard statistics
 */
function getTeacherDashboardStats($teacher_id) {
    $stats = [];
    $db = getDB();
    
    // Pending submissions to grade
    $result = $db->query("
        SELECT COUNT(*) as count 
        FROM assignment_submissions s
        JOIN assignments a ON s.assignment_id = a.id
        WHERE a.teacher_id = :teacher_id 
        AND s.status IN ('submitted', 'late')
    ")
    ->bind(':teacher_id', $teacher_id)
    ->fetch();
    $stats['pending_grading'] = $result['count'] ?? 0;
    
    // Active assignments
    $result = $db->query("
        SELECT COUNT(*) as count FROM assignments 
        WHERE teacher_id = :teacher_id AND status = 'active'
    ")
    ->bind(':teacher_id', $teacher_id)
    ->fetch();
    $stats['active_assignments'] = $result['count'] ?? 0;
    
    // Active quizzes
    $result = $db->query("
        SELECT COUNT(*) as count FROM quizzes 
        WHERE teacher_id = :teacher_id AND status = 'active'
    ")
    ->bind(':teacher_id', $teacher_id)
    ->fetch();
    $stats['active_quizzes'] = $result['count'] ?? 0;
    
    // Unread messages
    $stats['unread_messages'] = getUnreadMessageCount($teacher_id);
    
    return $stats;
}

// ============================================
// FILE UPLOAD FUNCTIONS
// ============================================

/**
 * Handle file upload
 */
function uploadFile($file, $upload_dir = 'uploads/', $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'png']) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'No file uploaded or upload error'];
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    // Create upload directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
    $destination = $upload_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'file_path' => $destination, 'filename' => $new_filename];
    }
    
    return ['success' => false, 'message' => 'Failed to move uploaded file'];
}
// ====================================================
// FILE: includes/functions.php
// ====================================================

/**
 * Create a new submission
 */
function createSubmission($assignment_id, $student_id, $file_path, $text = '') {
    $db = getDB();

    // Check if already submitted
    $result = $db->query("
        SELECT submission_id FROM submissions
        WHERE student_id = :student_id AND assignment_id = :assignment_id
    ")
    ->bind(':student_id', $student_id)
    ->bind(':assignment_id', $assignment_id)
    ->fetch();

    if (!empty($result)) {
        return ['success' => false, 'message' => 'Submission already exists'];
    }

    // Check due date
    $assignment = $db->query("SELECT due_date, teacher_id FROM assignments WHERE assignment_id = :id")
                     ->bind(':id', $assignment_id)
                     ->fetch();

    $status = (strtotime($assignment['due_date']) < time()) ? 'late' : 'submitted';

    // Insert submission
    $insert = $db->query("
        INSERT INTO submissions (assignment_id, student_id, file_path, submission_text, status, submitted_at)
        VALUES (:assignment_id, :student_id, :file_path, :text, :status, NOW())
    ")
    ->bind(':assignment_id', $assignment_id)
    ->bind(':student_id', $student_id)
    ->bind(':file_path', $file_path)
    ->bind(':text', $text)
    ->bind(':status', $status)
    ->execute();

    if ($insert) {
        // Notify teacher
        createNotification(
            $assignment['teacher_id'],
            $student_id,
            'New Submission',
            'A student has submitted an assignment',
            'submission',
            $assignment_id
        );

        return ['success' => true, 'message' => 'Submission created successfully'];
    }

    return ['success' => false, 'message' => 'Failed to create submission'];
}

/**
 * Get subject name by subject ID
 */
function getSubjectName($subject_id) {
    $db = getDB();
    $result = $db->query("SELECT subject_name FROM subjects WHERE subject_id = :subject_id")
                 ->bind(':subject_id', $subject_id)
                 ->fetch();
    return $result ? $result['subject_name'] : 'Unknown Subject';
}
