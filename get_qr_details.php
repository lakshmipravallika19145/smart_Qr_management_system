<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// DB connection
$host = 'localhost';
$dbname = 'addwise';
$username = 'root';
$password = 'Qazqaz12#';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (!isset($_GET['code']) || !preg_match('/^\d{16}$/', $_GET['code'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid or missing 16-digit QR code']);
            exit();
        }

        $code = $_GET['code'];

        $sql = "
            SELECT 
                c.id AS qr_id,
                c.code,
                c.image_data,
                c.assigned_to,
                c.created_at AS qr_created_at,
                c.is_active,
                l.latitude,
                l.longitude,
                l.location_name,
                l.created_at AS location_created_at,
                l.updated_at
            FROM qr_codes c
            LEFT JOIN qr_locations l ON c.code = l.code
            WHERE c.code = ?
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$code]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            echo json_encode(['status' => 'success', 'message' => 'QR code details found', 'data' => $result]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'No QR details found for this code']);
        }
        break;

   case 'POST':
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['code'], $data['latitude'], $data['longitude'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        exit();
    }

    $code = $data['code'];
    $lat = $data['latitude'];
    $lng = $data['longitude'];
    $location_name = $data['location_name'] ?? null;

    // Get qr_id from qr_codes table
    $qrStmt = $pdo->prepare("SELECT id FROM qr_codes WHERE code = ?");
    $qrStmt->execute([$code]);
    $qr = $qrStmt->fetch(PDO::FETCH_ASSOC);

    if (!$qr) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'QR code not found']);
        exit();
    }

    $qr_id = $qr['id'];

    // Insert or update location
    $insert = $pdo->prepare("
        INSERT INTO qr_locations (qr_id, code, latitude, longitude, location_name)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            latitude = VALUES(latitude),
            longitude = VALUES(longitude),
            location_name = VALUES(location_name),
            updated_at = CURRENT_TIMESTAMP
    ");

    $insert->execute([$qr_id, $code, $lat, $lng, $location_name]);

    echo json_encode(['status' => 'success', 'message' => 'Location added or updated']);
    break;

    case 'PUT':
        parse_str(file_get_contents("php://input"), $data);

        if (!isset($data['code'], $data['latitude'], $data['longitude'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            exit();
        }

        $code = $data['code'];
        $lat = $data['latitude'];
        $lng = $data['longitude'];
        $location_name = $data['location_name'] ?? null;

        $update = $pdo->prepare("UPDATE qr_locations SET latitude = ?, longitude = ?, location_name = ?, updated_at = CURRENT_TIMESTAMP WHERE code = ?");
        $updated = $update->execute([$lat, $lng, $location_name, $code]);

        if ($updated) {
            echo json_encode(['status' => 'success', 'message' => 'Location updated']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Update failed']);
        }
        break;

    case 'DELETE':
        parse_str(file_get_contents("php://input"), $data);

        if (!isset($data['code'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing QR code']);
            exit();
        }

        $code = $data['code'];
        $delete = $pdo->prepare("DELETE FROM qr_locations WHERE code = ?");
        $delete->execute([$code]);

        echo json_encode(['status' => 'success', 'message' => 'Location deleted']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
}
?>
