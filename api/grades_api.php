<?php
// grades_api.php
define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB()->getConnection();

$response = ["success" => false, "message" => "Invalid request"];

try {
    switch ($method) {

        // =======================================================
        // 1️⃣ GET: View all grades (admin or teacher)
        // =======================================================
        case 'GET':
            if (isset($_GET['student_id'])) {
                $student_id = $_GET['student_id'];
                $stmt = $db->prepare("
                    SELECT g.grade_id, s.first_name, s.last_name, 
                           a.title AS assignment_title, g.marks, g.total_marks, 
                           g.grade_letter, g.created_at
                    FROM grades g
                    JOIN students s ON g.student_id = s.student_id
                    JOIN assignments a ON g.assignment_id = a.assignment_id
                    WHERE g.student_id = :student_id
                    ORDER BY g.created_at DESC
                ");
                $stmt->execute([':student_id' => $student_id]);
                $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $response = [
                    "success" => true,
                    "message" => "Grades retrieved successfully",
                    "data" => $grades
                ];
            } else {
                $stmt = $db->query("
                    SELECT g.grade_id, s.first_name, s.last_name, 
                           a.title AS assignment_title, g.marks, g.total_marks, 
                           g.grade_letter, g.created_at
                    FROM grades g
                    JOIN students s ON g.student_id = s.student_id
                    JOIN assignments a ON g.assignment_id = a.assignment_id
                    ORDER BY g.created_at DESC
                ");
                $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $response = [
                    "success" => true,
                    "message" => "All grades retrieved",
                    "data" => $grades
                ];
            }
            break;

        // =======================================================
        // 2️⃣ POST: Add a new grade
        // =======================================================
        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);

            if (
                isset($data['assignment_id']) &&
                isset($data['student_id']) &&
                isset($data['marks']) &&
                isset($data['total_marks'])
            ) {
                $assignment_id = $data['assignment_id'];
                $student_id = $data['student_id'];
                $marks = $data['marks'];
                $total_marks = $data['total_marks'];

                // Calculate grade letter
                $percentage = ($marks / $total_marks) * 100;
                if ($percentage >= 80) $grade_letter = 'A';
                elseif ($percentage >= 70) $grade_letter = 'B';
                elseif ($percentage >= 60) $grade_letter = 'C';
                elseif ($percentage >= 50) $grade_letter = 'D';
                else $grade_letter = 'F';

                $stmt = $db->prepare("
                    INSERT INTO grades (assignment_id, student_id, marks, total_marks, grade_letter, created_at)
                    VALUES (:assignment_id, :student_id, :marks, :total_marks, :grade_letter, NOW())
                ");
                $stmt->execute([
                    ':assignment_id' => $assignment_id,
                    ':student_id' => $student_id,
                    ':marks' => $marks,
                    ':total_marks' => $total_marks,
                    ':grade_letter' => $grade_letter
                ]);

                $response = [
                    "success" => true,
                    "message" => "Grade added successfully",
                    "grade_letter" => $grade_letter
                ];
            } else {
                $response['message'] = "Missing required fields";
            }
            break;

        // =======================================================
        // 3️⃣ PUT: Update a grade
        // =======================================================
        case 'PUT':
            parse_str(file_get_contents("php://input"), $data);
            if (isset($data['grade_id']) && isset($data['marks']) && isset($data['total_marks'])) {
                $grade_id = $data['grade_id'];
                $marks = $data['marks'];
                $total_marks = $data['total_marks'];

                $percentage = ($marks / $total_marks) * 100;
                if ($percentage >= 80) $grade_letter = 'A';
                elseif ($percentage >= 70) $grade_letter = 'B';
                elseif ($percentage >= 60) $grade_letter = 'C';
                elseif ($percentage >= 50) $grade_letter = 'D';
                else $grade_letter = 'F';

                $stmt = $db->prepare("
                    UPDATE grades 
                    SET marks = :marks, total_marks = :total_marks, grade_letter = :grade_letter 
                    WHERE grade_id = :grade_id
                ");
                $stmt->execute([
                    ':marks' => $marks,
                    ':total_marks' => $total_marks,
                    ':grade_letter' => $grade_letter,
                    ':grade_id' => $grade_id
                ]);

                $response = [
                    "success" => true,
                    "message" => "Grade updated successfully",
                    "grade_letter" => $grade_letter
                ];
            } else {
                $response['message'] = "Missing parameters";
            }
            break;

        // =======================================================
        // 4️⃣ DELETE: Remove a grade
        // =======================================================
        case 'DELETE':
            parse_str(file_get_contents("php://input"), $data);
            if (isset($data['grade_id'])) {
                $stmt = $db->prepare("DELETE FROM grades WHERE grade_id = :grade_id");
                $stmt->execute([':grade_id' => $data['grade_id']]);

                $response = [
                    "success" => true,
                    "message" => "Grade deleted successfully"
                ];
            } else {
                $response['message'] = "Grade ID not provided";
            }
            break;

        default:
            $response['message'] = "Unsupported request method";
    }
} catch (PDOException $e) {
    $response = [
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ];
}

echo json_encode($response);
