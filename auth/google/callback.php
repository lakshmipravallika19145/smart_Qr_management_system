<?php
session_start();
header('Content-Type: application/json');

// Include Composer autoloader for Google API Client Library
// This path is relative to the current file (callback.php)
// From C:\xampp\htdocs\addwise_7\addwise_4\addwise_1\auth\google\
// go up two directories (../../) to reach C:\xampp\htdocs\addwise_7\addwise_4\addwise_1\
// where the 'vendor' folder is located.
require_once __DIR__ . '/../../vendor/autoload.php';

// --- Configuration Variables ---
// REPLACE THIS WITH YOUR ACTUAL GOOGLE CLIENT ID
$googleClientId = '53102166009-2l3ib41tts0h0hplsc80c66jv95msp2n.apps.googleusercontent.com';

// REPLACE THESE WITH YOUR ACTUAL DATABASE CREDENTIALS
$dbHost = 'localhost';
$dbName = 'addwise'; // Example: 'addwise_db' - CHANGE THIS TO YOUR DATABASE NAME
$dbUser = 'root';       // Example: 'root' - CHANGE THIS TO YOUR DATABASE USERNAME
$dbPass = 'Qazqaz12#';           // Example: '' (empty string if XAMPP root user has no password) - CHANGE THIS TO YOUR DATABASE PASSWORD

// --- Establish Database Connection ---
try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// --- Get the ID Token from the Frontend ---
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['id_token'])) {
    echo json_encode(['success' => false, 'message' => 'No ID token provided.']);
    exit();
}

$idToken = $data['id_token'];

// --- Verify the ID Token ---
try {
    $client = new Google_Client(['client_id' => $googleClientId]);
    $payload = $client->verifyIdToken($idToken);

    if ($payload) {
        // ID Token is valid, extract user information
        $googleId = $payload['sub'];           // Unique Google User ID
        $email = $payload['email'];            // User's email
        $fullName = $payload['name'];          // User's full name
        $isEmailVerified = $payload['email_verified']; // Boolean: if email is verified by Google

        // Ensure the email is verified by Google (should be true for Google Sign-In)
        if (!$isEmailVerified) {
            echo json_encode(['success' => false, 'message' => 'Google email not verified.']);
            exit();
        }

        // --- Database Logic: Check, Create, or Link User ---

        // 1. Check if a user with this Google ID already exists in your database
        $stmt = $pdo->prepare("SELECT id, full_name, email, role FROM users WHERE google_id = ?");
        $stmt->execute([$googleId]);
        $user = $stmt->fetch();

        if ($user) {
            // User exists and is linked via Google ID - Log them in
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['is_logged_in'] = true;

            echo json_encode(['success' => true, 'message' => 'Logged in successfully!', 'role' => $user['role']]);

        } else {
            // User does not exist with this Google ID, now check by email
            $stmt = $pdo->prepare("SELECT id, full_name, email, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $userByEmail = $stmt->fetch();

            if ($userByEmail) {
                // User exists with this email (e.g., they previously signed up with email/password)
                // Link their Google ID to their existing account and log them in
                $stmt = $pdo->prepare("UPDATE users SET google_id = ? WHERE id = ?");
                $stmt->execute([$googleId, $userByEmail['id']]);

                $_SESSION['user_id'] = $userByEmail['id'];
                $_SESSION['full_name'] = $userByEmail['full_name'];
                $_SESSION['email'] = $userByEmail['email'];
                $_SESSION['role'] = $userByEmail['role'];
                $_SESSION['is_logged_in'] = true;

                echo json_encode(['success' => true, 'message' => 'Account linked and logged in successfully!', 'role' => $userByEmail['role']]);

            } else {
                // Completely new user - Register them
                // Note: For Google Sign-in, 'password', 'mobile', 'age', 'gender', 'otp' might be NULL initially.
                // 'is_verified' is 1 because Google has verified the email.
                // Default 'role' is 'User'.
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, google_id, is_verified, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $defaultRole = 'User'; // You can change this if you want new Google sign-ups to be 'Admin' (not recommended)
                $stmt->execute([$fullName, $email, $googleId, 1, $defaultRole]);

                $newUserId = $pdo->lastInsertId();

                // Log the newly registered user in
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['full_name'] = $fullName;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = $defaultRole;
                $_SESSION['is_logged_in'] = true;

                echo json_encode(['success' => true, 'message' => 'Signed up successfully!', 'role' => $defaultRole]);
            }
        }

    } else {
        // Invalid ID Token
        echo json_encode(['success' => false, 'message' => 'Invalid ID Token.']);
    }

} catch (Exception $e) {
    // Catch any exceptions during token verification or database operations
    error_log("Google Callback Error: " . $e->getMessage()); // Log the error for debugging
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>