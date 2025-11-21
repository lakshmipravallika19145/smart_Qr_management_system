<?php
session_start();
if (!isset($_SESSION['is_logged_in']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index3.php");
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
    die("Database connection failed: " . $e->getMessage());
}

if (isset($_GET['user_id'])) {
    $userId = $_GET['user_id'];
    
    // Fetch user data
    $stmt = $pdo->prepare("SELECT mobile, full_name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $cleanNumber = preg_replace('/[^0-9]/', '', $user['mobile']);
        header("Location: https://wa.me/91$cleanNumber");
        exit();
    }
}

// Fallback if user not found
header("Location: admin_dashboard.php");
exit();
?>
