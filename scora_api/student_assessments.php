<?php
// student_assessments.php
require_once 'config.php';

$request_method = $_SERVER["REQUEST_METHOD"];
$data = json_decode(file_get_contents("php://input"));

switch ($request_method) {
    case 'POST':
        if (isset($data->action)) {
            switch ($data->action) {
                case 'start_attempt':
                    startAssessmentAttempt($conn, $data);
                    break;
                case 'submit_assessment':
                    submitAssessment($conn, $data);
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
        getStudentAssessmentResults($conn);
        break;
    default:
        echo json_encode(["message" => "Invalid request method."]);
        break;
}

function startAssessmentAttempt($conn, $data) {
    if (
        !empty($data->student_id) &&
        !empty($data->assessment_id)
    ) {
        $student_id = intval($data->student_id);
        $assessment_id = intval($data->assessment_id);

        try {
            // Check if there's an active (uncompleted) attempt by this student for this assessment
            $check_query = "SELECT attempt_id FROM student_assessment_attempts WHERE student_id = ? AND assessment_id = ? AND attempt_end_time IS NULL";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->execute([$student_id, $assessment_id]);
            if ($check_stmt->fetch(PDO::FETCH_ASSOC)) {
                echo json_encode(["message" => "An active attempt already exists for this assessment.", "attempt_id" => $check_stmt->fetch(PDO::FETCH_ASSOC)['attempt_id']]);
                return;
            }

            $query = "INSERT INTO student_assessment_attempts (student_id, assessment_id) VALUES (?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->execute([$student_id, $assessment_id]);

            echo json_encode(["message" => "Assessment attempt started.", "attempt_id" => $conn->lastInsertId()]);
        } catch (PDOException $e) {
            echo json_encode(["message" => "Error starting assessment attempt: " . $e->getMessage()]);
        }
    } else {
        echo json_encode(["message" => "Missing required fields for starting attempt."]);
    }
}


function submitAssessment($conn, $data) {
    if (
        !empty($data->attempt_id) &&
        isset($data->answers) &&
        is_array($data->answers)
    ) {
        $attempt_id = intval($data->attempt_id);
        $answers = $data->answers; // Array of {question_id: X, student_answer: Y}

        try {
            $conn->beginTransaction();

            // 1. Get assessment details for scoring
            $attempt_query = "SELECT sa.assessment_id, a.passing_score_percentage
                              FROM student_assessment_attempts sa
                              JOIN assessments a ON sa.assessment_id = a.assessment_id
                              WHERE sa.attempt_id = ?";
            $attempt_stmt = $conn->prepare($attempt_query);
            $attempt_stmt->execute([$attempt_id]);
            $attempt_details = $attempt_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$attempt_details) {
                echo json_encode(["message" => "Attempt not found."]);
                $conn->rollBack();
                return;
            }

            $assessment_id = $attempt_details['assessment_id'];
            $passing_percentage = $attempt_details['passing_score_percentage'];

            // 2. Get all questions and correct answers for this assessment
            $questions_query = "SELECT question_id, correct_answer FROM assessment_questions WHERE assessment_id = ?";
            $questions_stmt = $conn->prepare($questions_query);
            $questions_stmt->execute([$assessment_id]);
            $correct_answers = $questions_stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [question_id => correct_answer]

            $correct_count = 0;
            $total_questions = count($correct_answers);

            // 3. Store student's answers and score
            $insert_answer_query = "INSERT INTO student_assessment_answers (attempt_id, question_id, student_answer, is_correct) VALUES (?, ?, ?, ?)";
            $stmt_insert_answer = $conn->prepare($insert_answer_query);

            foreach ($answers as $answer) {
                $question_id = intval($answer->question_id);
                $student_answer = htmlspecialchars(strip_tags($answer->student_answer));

                $is_correct = false;
                if (isset($correct_answers[$question_id])) {
                    // Simple case-insensitive comparison for now
                    $is_correct = (strtolower($student_answer) == strtolower($correct_answers[$question_id]));
                    if ($is_correct) {
                        $correct_count++;
                    }
                }

                $stmt_insert_answer->execute([$attempt_id, $question_id, $student_answer, $is_correct]);
            }

            // 4. Calculate score and update attempt
            $score = ($total_questions > 0) ? ($correct_count / $total_questions) * 100 : 0;
            $is_passed = ($score >= $passing_percentage);

            $update_attempt_query = "UPDATE student_assessment_attempts SET attempt_end_time = CURRENT_TIMESTAMP, score = ?, is_passed = ? WHERE attempt_id = ?";
            $stmt_update_attempt = $conn->prepare($update_attempt_query);
            $stmt_update_attempt->execute([$score, $is_passed, $attempt_id]);

            $conn->commit();
            echo json_encode([
                "message" => "Assessment submitted and scored successfully.",
                "attempt_id" => $attempt_id,
                "score" => $score,
                "is_passed" => $is_passed,
                "correct_answers_count" => $correct_count,
                "total_questions" => $total_questions
            ]);

        } catch (PDOException $e) {
            $conn->rollBack();
            echo json_encode(["message" => "Error submitting assessment: " . $e->getMessage()]);
        }
    } else {
        echo json_encode(["message" => "Missing required fields for submitting assessment."]);
    }
}

function getStudentAssessmentResults($conn) {
    $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;
    $attempt_id = isset($_GET['attempt_id']) ? intval($_GET['attempt_id']) : null;

    $query = "
        SELECT
            sa.attempt_id,
            sa.attempt_start_time,
            sa.attempt_end_time,
            sa.score,
            sa.is_passed,
            a.assessment_id,
            a.assessment_title,
            a.passing_score_percentage,
            l.lesson_title,
            u.full_name as teacher_name
        FROM student_assessment_attempts sa
        JOIN assessments a ON sa.assessment_id = a.assessment_id
        JOIN lessons l ON a.lesson_id = l.lesson_id
        JOIN teachers t ON a.teacher_id = t.teacher_id
        JOIN users u ON t.user_id = u.user_id
        WHERE sa.attempt_end_time IS NOT NULL"; // Only show completed attempts

    $params = [];
    if ($student_id) {
        $query .= " AND sa.student_id = ?";
        $params[] = $student_id;
    }
    if ($attempt_id) {
        $query .= " AND sa.attempt_id = ?";
        $params[] = $attempt_id;
    }

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If a specific attempt is requested, also fetch the detailed answers
    if ($attempt_id && count($results) > 0) {
        $attempt_result = $results[0];
        $answers_query = "
            SELECT
                sq.question_id,
                sq.question_text,
                sq.question_type,
                sq.options_json,
                sq.correct_answer,
                saa.student_answer,
                saa.is_correct
            FROM student_assessment_answers saa
            JOIN assessment_questions sq ON saa.question_id = sq.question_id
            WHERE saa.attempt_id = ?
        ";
        $answers_stmt = $conn->prepare($answers_query);
        $answers_stmt->execute([$attempt_id]);
        $detailed_answers = $answers_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode options_json for each question
        foreach ($detailed_answers as &$q) {
            if ($q['options_json']) {
                $q['options'] = json_decode($q['options_json']);
            }
            unset($q['options_json']); // Remove the raw JSON string
        }
        $attempt_result['detailed_answers'] = $detailed_answers;
        echo json_encode($attempt_result);
    } else {
        echo json_encode($results);
    }
}
?>