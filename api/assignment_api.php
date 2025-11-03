<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Secure session
startSecureSession();

if (!isLoggedIn()) {
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$db = getDB()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// Extract path segments
$request_uri = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
$resource = $request_uri[count($request_uri) - 2] ?? '';
$id = $request_uri[count($request_uri) - 1] ?? null;

// ====================================================
// GET: Fetch Assignments (Student or Teacher view)
// ====================================================
if ($method === 'GET') {
    try {
        $user_id = $_SESSION['user_id'];
        $role = $_SESSION['role'];

        if ($id && is_numeric($id)) {
            $stmt = $db->prepare("SELECT * FROM assignments WHERE assignment_id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($assignment) {
                echo json_encode($assignment);
            } else {
                http_response_code(404);
                echo json_encode(["error" => "Assignment not found"]);
            }
        } else {
            if ($role === 'teacher') {
                $stmt = $db->prepare("SELECT * FROM assignments WHERE teacher_id = :tid ORDER BY created_at DESC");
                $stmt->bindParam(':tid', $user_id, PDO::PARAM_INT);
            } else {
                $stmt = $db->prepare("SELECT * FROM assignments ORDER BY created_at DESC");
            }

            $stmt->execute();
            $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($assignments);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

// ====================================================
// POST: Create or Submit Assignment
// ====================================================
elseif ($method === 'POST') {
    $data = $_POST;

    // Handle teacher creating assignment
    if (isset($data['action']) && $data['action'] === 'create' && $_SESSION['role'] === 'teacher') {
        try {
            $stmt = $db->prepare("
                INSERT INTO assignments (teacher_id, subject_id, title, description, due_date, total_marks, created_at)
                VALUES (:teacher_id, :subject_id, :title, :description, :due_date, :total_marks, NOW())
            ");
            $stmt->execute([
                ':teacher_id' => $_SESSION['user_id'],
                ':subject_id' => $data['subject_id'] ?? null,
                ':title' => $data['title'],
                ':description' => $data['description'],
                ':due_date' => $data['due_date'],
                ':total_marks' => $data['total_marks']
            ]);

            echo json_encode(["success" => true, "message" => "Assignment created successfully"]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // Handle student submission
    elseif (isset($data['action']) && $data['action'] === 'submit' && $_SESSION['role'] === 'student') {
        try {
            $assignment_id = $data['assignment_id'];
            $submission_text = $data['submission_text'] ?? '';
            $file_path = null;

            // Handle file upload if exists
            if (!empty($_FILES['file']['name'])) {
                $uploadDir = __DIR__ . '/../uploads/assignments/';
                if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

                $filename = time() . '_' . basename($_FILES['file']['name']);
                $targetPath = $uploadDir . $filename;
                move_uploaded_file($_FILES['file']['tmp_name'], $targetPath);
                $file_path = 'uploads/assignments/' . $filename;
            }

            $stmt = $db->prepare("
                INSERT INTO submissions (assignment_id, student_id, file_path, submission_text, submitted_at, status)
                VALUES (:assignment_id, :student_id, :file_path, :submission_text, NOW(), 'submitted')
            ");
            $stmt->execute([
                ':assignment_id' => $assignment_id,
                ':student_id' => $_SESSION['user_id'],
                ':file_path' => $file_path,
                ':submission_text' => $submission_text
            ]);

            echo json_encode(["success" => true, "message" => "Assignment submitted successfully"]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    } else {
        http_response_code(403);
        echo json_encode(["error" => "Unauthorized or missing action"]);
    }
}

// ====================================================
// PUT: Update Assignment (Teacher only)
// ====================================================
elseif ($method === 'PUT' && $_SESSION['role'] === 'teacher') {
    parse_str(file_get_contents("php://input"), $data);
    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "Missing assignment ID"]);
        exit;
    }

    try {
        $stmt = $db->prepare("
            UPDATE assignments
            SET title = :title, description = :description, due_date = :due_date, total_marks = :total_marks
            WHERE assignment_id = :id AND teacher_id = :teacher_id
        ");
        $stmt->execute([
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':due_date' => $data['due_date'],
            ':total_marks' => $data['total_marks'],
            ':id' => $id,
            ':teacher_id' => $_SESSION['user_id']
        ]);
        echo json_encode(["success" => true, "message" => "Assignment updated successfully"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

// ====================================================
// DELETE: Delete Assignment (Teacher only)
// ====================================================
elseif ($method === 'DELETE' && $_SESSION['role'] === 'teacher') {
    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "Missing assignment ID"]);
        exit;
    }

    try {
        $stmt = $db->prepare("DELETE FROM assignments WHERE assignment_id = :id AND teacher_id = :teacher_id");
        $stmt->execute([':id' => $id, ':teacher_id' => $_SESSION['user_id']]);
        echo json_encode(["success" => true, "message" => "Assignment deleted successfully"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

else {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
}


// ====================================================
// FILE: api/submit_submission_api.php
// Handle student submissions via API
// ====================================================



header('Content-Type: application/json');

// Secure session
startSecureSession();

if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$student_id = $_SESSION['user_id'];
$assignment_id = $_POST['assignment_id'] ?? null;
$submission_text = $_POST['submission_text'] ?? '';

if (!$assignment_id) {
    echo json_encode(['success' => false, 'message' => 'Assignment ID is required']);
    exit;
}

// Validate file upload
if (!isset($_FILES['submission_file']) || $_FILES['submission_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Please upload a file']);
    exit;
}

$file = $_FILES['submission_file'];

// Validate file size (max 10MB)
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File size must be less than 10MB']);
    exit;
}

// Validate file type
$allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($file_extension, $allowed_extensions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG']);
    exit;
}

// Upload directory
$upload_dir = __DIR__ . '/../uploads/assignments/submissions/';
if (!file_exists($upload_dir)) mkdir($upload_dir, 0755, true);

// Unique filename
$new_filename = 'submission_' . $student_id . '_' . $assignment_id . '_' . time() . '.' . $file_extension;
$file_path = 'uploads/assignments/submissions/' . $new_filename;
$full_path = $upload_dir . $new_filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $full_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
    exit;
}

// Create submission
$result = createSubmission($assignment_id, $student_id, $file_path, $submission_text);

if ($result['success']) {
    echo json_encode(['success' => true, 'message' => $result['message']]);
} else {
    // Remove uploaded file if failed
    if (file_exists($full_path)) unlink($full_path);
    echo json_encode(['success' => false, 'message' => $result['message']]);
}

?>
