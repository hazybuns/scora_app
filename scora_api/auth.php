<?php
// auth.php - Updated for 'password' column name (STILL PLAIN TEXT - NOT RECOMMENDED FOR PRODUCTION!)
require_once 'config.php'; // Include your database connection

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$data = json_decode(file_get_contents("php://input"));
$request_method = $_SERVER["REQUEST_METHOD"];

switch ($request_method) {
    case 'POST':
        if (isset($data->action)) {
            switch ($data->action) {
                case 'register':
                    registerUser($conn, $data);
                    break;
                case 'login':
                    loginUser($conn, $data);
                    break;
                case 'get_teachers':
                    getTeachers($conn);
                    break;
                default:
                    echo json_encode(["message" => "Invalid action."]);
                    break;
            }
        } else {
            echo json_encode(["message" => "Action not specified."]);
        }
        break;
    default:
        echo json_encode(["message" => "Invalid request method."]);
        break;
}

function registerUser($conn, $data) {
    if (
        !empty($data->username) &&
        !empty($data->password) &&
        !empty($data->full_name) &&
        !empty($data->role_name) // 'Admin', 'Teacher', 'Student'
    ) {
        $username = htmlspecialchars(strip_tags($data->username));
        $password = htmlspecialchars(strip_tags($data->password)); // Store plain text password directly
        $full_name = htmlspecialchars(strip_tags($data->full_name));
        $email = isset($data->email) ? htmlspecialchars(strip_tags($data->email)) : null;
        $role_name = htmlspecialchars(strip_tags($data->role_name));

        // Get role_id
        $stmt_role = $conn->prepare("SELECT role_id FROM user_roles WHERE role_name = ?");
        $stmt_role->execute([$role_name]);
        $role = $stmt_role->fetch(PDO::FETCH_ASSOC);

        if (!$role) {
            echo json_encode(["message" => "Invalid role specified."]);
            return;
        }
        $role_id = $role['role_id'];

        try {
            $conn->beginTransaction();

            // SQL query now uses 'password' instead of 'password_hash'
            $query = "INSERT INTO users (username, password, full_name, email, role_id) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->execute([$username, $password, $full_name, $email, $role_id]);
            $user_id = $conn->lastInsertId();

            // Insert into specific role table
            if ($role_name == 'Teacher') {
                $subject_info = isset($data->subject_info) ? htmlspecialchars(strip_tags($data->subject_info)) : null;
                $stmt_teacher = $conn->prepare("INSERT INTO teachers (user_id, subject_info) VALUES (?, ?)");
                $stmt_teacher->execute([$user_id, $subject_info]);
            } elseif ($role_name == 'Student') {
                $grade_level = isset($data->grade_level) ? htmlspecialchars(strip_tags($data->grade_level)) : null;
                $teacher_id = isset($data->teacher_id) ? intval($data->teacher_id) : null;
                $stmt_student = $conn->prepare("INSERT INTO students (user_id, teacher_id, grade_level) VALUES (?, ?, ?)");
                $stmt_student->execute([$user_id, $teacher_id, $grade_level]);
            }

            $conn->commit();
            echo json_encode(["message" => "User registered successfully.", "user_id" => $user_id, "role_name" => $role_name]);

        } catch (PDOException $e) {
            $conn->rollBack();
            if ($e->getCode() == '23000') {
                echo json_encode(["message" => "Username or email already exists."]);
            } else {
                echo json_encode(["message" => "Error registering user: " . $e->getMessage()]);
            }
        }
    } else {
        echo json_encode(["message" => "Missing required fields for registration."]);
    }
}

function loginUser($conn, $data) {
    if (!empty($data->username) && !empty($data->password)) {
        $username = htmlspecialchars(strip_tags($data->username));
        $password = htmlspecialchars(strip_tags($data->password));

        $query = "
            SELECT u.user_id, u.username, u.password, ur.role_name, u.full_name, t.teacher_id, s.student_id, t.subject_info, s.grade_level
            FROM users u
            JOIN user_roles ur ON u.role_id = ur.role_id
            LEFT JOIN teachers t ON u.user_id = t.user_id
            LEFT JOIN students s ON u.user_id = s.user_id
            WHERE u.username = ? LIMIT 0,1
        ";

        $stmt = $conn->prepare($query);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Compare plain text password directly with the 'password' column
        if ($user && $password === $user['password']) {
            // Login successful
            unset($user['password']); // Don't send password to client

            if ($user['role_name'] == 'Teacher' && $user['teacher_id']) {
                $user['specific_id'] = $user['teacher_id'];
            } elseif ($user['role_name'] == 'Student' && $user['student_id']) {
                $user['specific_id'] = $user['student_id'];
            }
            if ($user['role_name'] == 'Teacher') {
                $user['subject_info'] = $user['subject_info'];
            } elseif ($user['role_name'] == 'Student') {
                $user['grade_level'] = $user['grade_level'];
            }

            echo json_encode([
                "message" => "Login successful.",
                "user" => $user
            ]);
        } else {
            echo json_encode(["message" => "Invalid username or password."]);
        }
    } else {
        echo json_encode(["message" => "Missing username or password."]);
    }
}

function getTeachers($conn) {
    try {
        $query = "
            SELECT t.teacher_id, u.full_name, t.subject_info
            FROM teachers t
            JOIN users u ON t.user_id = u.user_id
            ORDER BY u.full_name
        ";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($teachers);
    } catch (PDOException $e) {
        echo json_encode(["message" => "Error fetching teachers: " . $e->getMessage()]);
    }
}
?>