<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode([]);
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
    echo json_encode([]);
    exit();
}

$action = $_GET['action'] ?? '';

if ($action === 'all') {
    // Get all live-tracked users (latest location per QR)
    $sql = "
        SELECT q.id as qr_id, q.code, u.full_name, u.email, u.mobile, l.latitude, l.longitude, l.updated_at
        FROM qr_codes q
        JOIN users u ON q.assigned_to = u.id
        JOIN qr_locations l ON l.qr_id = q.id
        WHERE l.latitude IS NOT NULL AND l.longitude IS NOT NULL
        AND q.assigned_to IS NOT NULL
        AND l.updated_at = (
            SELECT MAX(l2.updated_at) FROM qr_locations l2 WHERE l2.qr_id = q.id
        )
    ";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
    exit();
}

echo json_encode([]); 