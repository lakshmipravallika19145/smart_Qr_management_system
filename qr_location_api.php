<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['role'] !== 'User') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$host = 'localhost';
$dbname = 'addwise';
$username = 'root';
$password = 'Qazqaz12#';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_REQUEST['action'] ?? '';
$qr_id = isset($_REQUEST['qr_id']) ? intval($_REQUEST['qr_id']) : 0;

// Check QR code ownership
$stmt = $pdo->prepare("SELECT id FROM qr_codes WHERE id = ? AND assigned_to = ?");
$stmt->execute([$qr_id, $user_id]);
$qr = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$qr) {
    echo json_encode(['success' => false, 'message' => 'QR not found or not assigned to you']);
    exit();
}

if ($action === 'get') {
    $stmt = $pdo->prepare("SELECT * FROM qr_locations WHERE qr_id = ? ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute([$qr_id]);
    $loc = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($loc) {
        echo json_encode([
            'latitude' => $loc['latitude'],
            'longitude' => $loc['longitude'],
            'location_name' => $loc['location_name'],
            'updated_at' => $loc['updated_at'],
        ]);
    } else {
        echo json_encode([
            'latitude' => null,
            'longitude' => null,
            'location_name' => null,
            'updated_at' => null,
        ]);
    }
    exit();
}

if ($action === 'update') {
    $lat = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $lng = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    $name = isset($_POST['location_name']) ? trim($_POST['location_name']) : null;
    if ($lat === null || $lng === null) {
        echo json_encode(['success' => false, 'message' => 'Missing coordinates']);
        exit();
    }
    // Upsert location
    $stmt = $pdo->prepare("SELECT id FROM qr_locations WHERE qr_id = ?");
    $stmt->execute([$qr_id]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($exists) {
        $stmt = $pdo->prepare("UPDATE qr_locations SET latitude = ?, longitude = ?, location_name = ?, updated_at = NOW() WHERE qr_id = ?");
        $stmt->execute([$lat, $lng, $name, $qr_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO qr_locations (qr_id, latitude, longitude, location_name, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$qr_id, $lat, $lng, $name]);
    }
    echo json_encode(['success' => true]);
    exit();
}

if ($action === 'stop') {
    $stmt = $pdo->prepare("DELETE FROM qr_locations WHERE qr_id = ?");
    $stmt->execute([$qr_id]);
    echo json_encode(['success' => true]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action']); 