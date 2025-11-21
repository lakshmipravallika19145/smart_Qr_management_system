<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_code'])) {
    $qr_code = $_POST['qr_code'];

    // Extract numeric code from scanned data
    if (preg_match('/\b(\d{16})\b/', $qr_code, $matches)) {
        $qr_code = $matches[1]; // Use 16-digit numeric code
    } else if (strpos($qr_code, 'code=') !== false) {
        parse_str(parse_url($qr_code, PHP_URL_QUERY), $params);
        $qr_code = $params['code'] ?? $qr_code;
    }

    if (!isset($_SESSION['user_id'])) {
        echo "Session expired. Please log in again.";
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $host = 'localhost';
    $dbname = 'addwise';
    $username = 'root';
    $password = 'Qazqaz12#';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check QR availability
        $stmt = $pdo->prepare("SELECT id, code FROM qr_codes WHERE code = ? AND assigned_to IS NULL");
        $stmt->execute([$qr_code]);
        $qr = $stmt->fetch();

        if ($qr) {
            // Assign QR to user
            $stmt = $pdo->prepare("UPDATE qr_codes SET assigned_to = ? WHERE id = ?");
            $stmt->execute([$user_id, $qr['id']]);
            
            // Return success with code for confirmation
            echo "SUCCESS:" . $qr['code'];
        } else {
            echo "ERROR: QR Code not valid or already assigned.";
        }
    } catch (PDOException $e) {
        echo "ERROR: Database error - " . $e->getMessage();
    }
} else {
    echo "ERROR: Invalid request.";
}
?>
