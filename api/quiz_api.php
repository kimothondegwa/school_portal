<?php
// quiz_api.php
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
        // 1️⃣ GET: Retrieve quizzes
        // =======================================================
        case 'GET':
            if (isset($_GET['quiz_id'])) {
                // Get single quiz by ID
                $stmt = $db->prepare("
                    SELECT * FROM quizzes 
                    WHERE quiz_id = :quiz_id
                ");
                $stmt->execute([':quiz_id' => $_GET['quiz_id']]);
                $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

                $response = $quiz
                    ? ["success" => true, "data" => $quiz]
                    : ["success" => false, "message" => "Quiz not found"];
            } else {
                // Get all quizzes
                $stmt = $db->query("
                    SELECT q.*, s.name AS subject_name, 
                           t.first_name AS teacher_first, t.last_name AS teacher_last
                    FROM quizzes q
                    LEFT JOIN subjects s ON q.subject_id = s.subject_id
                    LEFT JOIN teachers t ON q.teacher_id = t.teacher_id
                    ORDER BY q.created_at DESC
                ");
                $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $response = [
                    "success" => true,
                    "message" => "All quizzes retrieved",
                    "data" => $quizzes
                ];
            }
            break;

        // =======================================================
        // 2️⃣ POST: Create a new quiz
        // =======================================================
        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);

            if (
                isset($data['subject_id']) &&
                isset($data['teacher_id']) &&
                isset($data['title']) &&
                isset($data['duration_minutes']) &&
                isset($data['total_marks'])
            ) {
                $stmt = $db->prepare("
                    INSERT INTO quizzes 
                    (subject_id, teacher_id, title, description, duration_minutes, total_marks, start_time, end_time, is_active, created_at)
                    VALUES 
                    (:subject_id, :teacher_id, :title, :description, :duration_minutes, :total_marks, :start_time, :end_time, :is_active, NOW())
                ");
                $stmt->execute([
                    ':subject_id' => $data['subject_id'],
                    ':teacher_id' => $data['teacher_id'],
                    ':title' => $data['title'],
                    ':description' => $data['description'] ?? '',
                    ':duration_minutes' => $data['duration_minutes'],
                    ':total_marks' => $data['total_marks'],
                    ':start_time' => $data['start_time'] ?? null,
                    ':end_time' => $data['end_time'] ?? null,
                    ':is_active' => $data['is_active'] ?? 0
                ]);

                $response = [
                    "success" => true,
                    "message" => "Quiz created successfully",
                    "quiz_id" => $db->lastInsertId()
                ];
            } else {
                $response['message'] = "Missing required quiz fields";
            }
            break;

        // =======================================================
        // 3️⃣ PUT: Update an existing quiz
        // =======================================================
        case 'PUT':
            parse_str(file_get_contents("php://input"), $data);

            if (isset($data['quiz_id'])) {
                $fields = [];
                $params = [':quiz_id' => $data['quiz_id']];

                $allowed = ['title', 'description', 'duration_minutes', 'total_marks', 'start_time', 'end_time', 'is_active'];
                foreach ($allowed as $key) {
                    if (isset($data[$key])) {
                        $fields[] = "$key = :$key";
                        $params[":$key"] = $data[$key];
                    }
                }

                if (!empty($fields)) {
                    $sql = "UPDATE quizzes SET " . implode(", ", $fields) . " WHERE quiz_id = :quiz_id";
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);

                    $response = [
                        "success" => true,
                        "message" => "Quiz updated successfully"
                    ];
                } else {
                    $response['message'] = "No fields to update";
                }
            } else {
                $response['message'] = "Quiz ID missing";
            }
            break;

        // =======================================================
        // 4️⃣ DELETE: Remove a quiz
        // =======================================================
        case 'DELETE':
            parse_str(file_get_contents("php://input"), $data);
            if (isset($data['quiz_id'])) {
                $stmt = $db->prepare("DELETE FROM quizzes WHERE quiz_id = :quiz_id");
                $stmt->execute([':quiz_id' => $data['quiz_id']]);

                $response = [
                    "success" => true,
                    "message" => "Quiz deleted successfully"
                ];
            } else {
                $response['message'] = "Quiz ID required";
            }
            break;

        default:
            $response['message'] = "Unsupported HTTP method";
    }
} catch (PDOException $e) {
    $response = [
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ];
}

echo json_encode($response);
