<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Extract the 16-digit code from the URI
if (preg_match('#/api/qr.php/([0-9]{16})(/location)?#', $uri, $matches)) {
    $code = $matches[1];
    $is_location = isset($matches[2]);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid endpoint. Use /api/qr.php/{16_digit_code} or /api/qr.php/{16_digit_code}/location']);
    exit();
}

// Fetch QR code info
$stmt = $pdo->prepare('SELECT * FROM qr_codes WHERE code = ?');
$stmt->execute([$code]);
$qr = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$qr) {
    http_response_code(404);
    echo json_encode(['error' => 'QR code not found']);
    exit();
}

// GET: /api/qr.php/{code}
if ($method === 'GET' && !$is_location) {
    // Get assigned user
    $user = null;
    if ($qr['assigned_to']) {
        $stmt = $pdo->prepare('SELECT id, full_name, email, mobile, age, gender, role FROM users WHERE id = ?');
        $stmt->execute([$qr['assigned_to']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get all location entries
    $stmt = $pdo->prepare('SELECT latitude, longitude, location_name, created_at FROM qr_locations WHERE code = ? ORDER BY created_at ASC');
    $stmt->execute([$code]);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'qr' => $qr,
        'user' => $user,
        'locations' => $locations
    ]);
    exit();
}

// POST: /api/qr.php/{code}/location
if ($method === 'POST' && $is_location) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['latitude'], $input['longitude'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing latitude or longitude']);
        exit();
    }

    $latitude = $input['latitude'];
    $longitude = $input['longitude'];
    $location_name = isset($input['location_name']) ? $input['location_name'] : null;

    // Insert new location point
    $stmt = $pdo->prepare('INSERT INTO qr_locations (qr_id, code, latitude, longitude, location_name, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
    $stmt->execute([$qr['id'], $code, $latitude, $longitude, $location_name]);

    // Return updated route
    $stmt = $pdo->prepare('SELECT latitude, longitude, location_name, created_at FROM qr_locations WHERE code = ? ORDER BY created_at ASC');
    $stmt->execute([$code]);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'locations' => $locations
    ]);
    exit();
}

// Invalid method or route
http_response_code(405);
echo json_encode(['error' => 'Method not allowed or invalid endpoint']);
