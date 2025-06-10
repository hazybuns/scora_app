<?php
// assessments.php
require_once 'config.php';

$request_method = $_SERVER["REQUEST_METHOD"];
$data = json_decode(file_get_contents("php://input"));

switch ($request_method) {
    case 'POST':
        if (isset($data->action)) {
            switch ($data->action) {
                case 'create_assessment':
                    createAssessment($conn, $data);
                    break;
                case 'add_question':
                    addQuestion($conn, $data);
                    break;
                default:
                    echo json_encode(["message" => "Invalid action."]);
                    break;
            }
        } else {
            echo json_encode(["message" => "Action not specified."]);
        }
        break;
    case 'GET':
        getAssessments($conn);
        break;
    default:
        echo json_encode(["message" => "Invalid request method."]);
        break;
}

function createAssessment($conn, $data) {
    if (
        !empty($data->lesson_id) &&
        !empty($data->teacher_id) &&
        !empty($data->assessment_title)
    ) {
        $lesson_id = intval($data->lesson_id);
        $teacher_id = intval($data->teacher_id);
        $assessment_title = htmlspecialchars(strip_tags($data->assessment_title));
        $assessment_description = isset($data->assessment_description) ? htmlspecialchars(strip_tags($data->assessment_description)) : null;
        $passing_score_percentage = isset($data->passing_score_percentage) ? floatval($data->passing_score_percentage) : 70.00;

        try {
            $query = "INSERT INTO assessments (lesson_id, teacher_id, assessment_title, assessment_description, passing_score_percentage) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->execute([$lesson_id, $teacher_id, $assessment_title, $assessment_description, $passing_score_percentage]);

            echo json_encode(["message" => "Assessment created successfully.", "assessment_id" => $conn->lastInsertId()]);
        } catch (PDOException $e) {
            echo json_encode(["message" => "Error creating assessment: " . $e->getMessage()]);
        }
    } else {
        echo json_encode(["message" => "Missing required fields for assessment creation."]);
    }
}

function addQuestion($conn, $data) {
    if (
        !empty($data->assessment_id) &&
        !empty($data->question_text) &&
        !empty($data->question_type) &&
        !empty($data->correct_answer)
    ) {
        $assessment_id = intval($data->assessment_id);
        $question_text = htmlspecialchars(strip_tags($data->question_text));
        $question_type = htmlspecialchars(strip_tags($data->question_type));
        $correct_answer = htmlspecialchars(strip_tags($data->correct_answer));
        $options_json = isset($data->options_json) ? json_encode($data->options_json) : null; // Ensure it's a JSON string

        try {
            $query = "INSERT INTO assessment_questions (assessment_id, question_text, question_type, options_json, correct_answer) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->execute([$assessment_id, $question_text, $question_type, $options_json, $correct_answer]);

            echo json_encode(["message" => "Question added successfully.", "question_id" => $conn->lastInsertId()]);
        } catch (PDOException $e) {
            echo json_encode(["message" => "Error adding question: " . $e->getMessage()]);
        }
    } else {
        echo json_encode(["message" => "Missing required fields for adding question."]);
    }
}

function getAssessments($conn) {
    $teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : null;
    $lesson_id = isset($_GET['lesson_id']) ? intval($_GET['lesson_id']) : null;
    $assessment_id = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : null;

    $query = "SELECT a.*, l.lesson_title, u.full_name as teacher_name
              FROM assessments a
              JOIN lessons l ON a.lesson_id = l.lesson_id
              JOIN teachers t ON a.teacher_id = t.teacher_id
              JOIN users u ON t.user_id = u.user_id";
    $params = [];
    $where_clauses = [];

    if ($teacher_id) {
        $where_clauses[] = "a.teacher_id = ?";
        $params[] = $teacher_id;
    }
    if ($lesson_id) {
        $where_clauses[] = "a.lesson_id = ?";
        $params[] = $lesson_id;
    }
    if ($assessment_id) {
        $where_clauses[] = "a.assessment_id = ?";
        $params[] = $assessment_id;
    }

    if (count($where_clauses) > 0) {
        $query .= " WHERE " . implode(" AND ", $where_clauses);
    }

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If a single assessment is requested, fetch its questions too
    if ($assessment_id && count($assessments) > 0) {
        $assessment = $assessments[0]; // Get the single assessment
        $question_query = "SELECT question_id, question_text, question_type, options_json FROM assessment_questions WHERE assessment_id = ?";
        $question_stmt = $conn->prepare($question_query);
        $question_stmt->execute([$assessment_id]);
        $questions = $question_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode options_json for each question
        foreach ($questions as &$q) {
            if ($q['options_json']) {
                $q['options'] = json_decode($q['options_json']);
            }
            unset($q['options_json']); // Remove the raw JSON string
        }
        $assessment['questions'] = $questions;
        echo json_encode($assessment); // Return the single assessment with questions
    } else {
        echo json_encode($assessments); // Return a list of assessments
    }
}
?>