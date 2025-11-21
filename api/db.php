<?php
$host = "localhost";
$db = "addwise"; // your database name
$user = "root";
$pass = "Qazqaz12#";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["error" => "DB connection failed: " . $e->getMessage()]);
    exit();
}
?>
