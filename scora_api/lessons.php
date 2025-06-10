<?php
// lessons.php
require_once 'config.php';

$request_method = $_SERVER["REQUEST_METHOD"];

switch ($request_method) {
    case 'POST':
        uploadLesson($conn);
        break;
    case 'GET':
        getLessons($conn);
        break;
    default:
        echo json_encode(["message" => "Invalid request method."]);
        break;
}

function uploadLesson($conn) {
    // Note: File uploads need special handling.
    // This example assumes you'll send JSON metadata and then the file separately,
    // or handle a multipart/form-data request.
    // For simplicity here, we'll assume content_path is sent in JSON for now.
    // **For actual file uploads, you'd use $_FILES and move_uploaded_file()**

    $data = json_decode(file_get_contents("php://input"));

    if (
        !empty($data->teacher_id) &&
        !empty($data->lesson_title) &&
        !empty($data->content_path) // This would be the path AFTER file upload
    ) {
        $teacher_id = intval($data->teacher_id);
        $lesson_title = htmlspecialchars(strip_tags($data->lesson_title));
        $lesson_description = isset($data->lesson_description) ? htmlspecialchars(strip_tags($data->lesson_description)) : null;
        $content_path = htmlspecialchars(strip_tags($data->content_path)); // e.g., "uploads/lessons/my_lesson.pdf"

        try {
            $query = "INSERT INTO lessons (teacher_id, lesson_title, lesson_description, content_path) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->execute([$teacher_id, $lesson_title, $lesson_description, $content_path]);

            echo json_encode(["message" => "Lesson uploaded successfully.", "lesson_id" => $conn->lastInsertId()]);
        } catch (PDOException $e) {
            echo json_encode(["message" => "Error uploading lesson: " . $e->getMessage()]);
        }
    } else {
        echo json_encode(["message" => "Missing required fields for lesson upload."]);
    }
}

function getLessons($conn) {
    $teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : null;
    $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;

    $query = "SELECT l.*, t.subject_info, u.full_name as teacher_name
              FROM lessons l
              JOIN teachers t ON l.teacher_id = t.teacher_id
              JOIN users u ON t.user_id = u.user_id";
    $params = [];

    if ($teacher_id) {
        // Teacher fetching their own lessons
        $query .= " WHERE l.teacher_id = ?";
        $params[] = $teacher_id;
    } elseif ($student_id) {
        // Student fetching lessons assigned to their teacher
        $query .= " JOIN students s ON t.teacher_id = s.teacher_id WHERE s.student_id = ?";
        $params[] = $student_id;
    }

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($lessons);
}
?>