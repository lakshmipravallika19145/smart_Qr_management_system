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
    $stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && !empty($user['email'])) {
        $to = $user['email'];
        $name = $user['full_name'];
        $subject = "Your Account Information";
        $message = "Hello $name,\n\nThis is a message regarding your account.";
        $headers = "From: admin@yourdomain.com";
        
        if (mail($to, $subject, $message, $headers)) {
            $_SESSION['success_message'] = "Email sent to $name";
        } else {
            $_SESSION['error_message'] = "Failed to send email to $name";
        }
    }
}

header("Location: admin_dashboard.php");
exit();
?>
