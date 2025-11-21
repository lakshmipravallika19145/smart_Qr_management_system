<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$code = isset($_GET['code']) ? $_GET['code'] : null;
$start = isset($_GET['start']) ? $_GET['start'] : null;
$end = isset($_GET['end']) ? $_GET['end'] : null;

if (!$code || !$start || !$end) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit();
}

$stmt = $pdo->prepare('SELECT latitude, longitude, location_name, created_at FROM qr_locations WHERE code = ? AND created_at BETWEEN ? AND ? ORDER BY created_at ASC');
$stmt->execute([$code, $start, $end]);
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'code' => $code,
    'locations' => $locations
]); 