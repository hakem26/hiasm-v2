<?php
// config/db.php - اتصال امن به دیتابیس
$host = 'localhost';
$dbname = 'hiasm';
$username = 'root';     // <<< اینجا تغییر بده
$password = ''; // <<< اینجا تغییر بده

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("خطای اتصال به دیتابیس: " . $e->getMessage());
}
?>