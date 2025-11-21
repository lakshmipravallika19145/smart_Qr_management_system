<?php
require '../db.php';
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

$method = $_SERVER['REQUEST_METHOD'];
file_put_contents('log.txt', "METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);
// Handle different request types
switch ($method) {
    case 'GET':
        // Read users or a specific user by ID
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        } else {
            $stmt = $pdo->query("SELECT * FROM users");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        if (isset($data['full_name'], $data['email'], $data['mobile'], $data['age'], $data['gender'], $data['role'], $data['password'])) {
            $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, mobile, age, gender, role, password, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['full_name'], $data['email'], $data['mobile'],
                $data['age'], $data['gender'], $data['role'],
                $hashedPassword, 1
            ]);
            echo json_encode(["status" => "User created successfully"]);
        } else {
            http_response_code(400);
            echo json_encode(["error" => "Missing required fields"]);
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"), true);
        if (isset($data['id'])) {
            $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, mobile=?, age=?, gender=?, role=? WHERE id=?");
            $stmt->execute([
                $data['full_name'], $data['email'], $data['mobile'],
                $data['age'], $data['gender'], $data['role'],
                $data['id']
            ]);
            echo json_encode(["status" => "User updated successfully"]);
        } else {
            echo json_encode(["error" => "Missing user ID"]);
        }
        break;

    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"), true);
        if (isset($data['id'])) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$data['id']]);
            echo json_encode(["status" => "User deleted"]);
        } else {
            echo json_encode(["error" => "Missing user ID"]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
}
?>
