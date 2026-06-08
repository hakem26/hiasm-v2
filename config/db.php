<?php
/**
 * HIASM v2 — Database Connection
 * PDO با error handling کامل
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'hiasm');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_persian_ci",
            ]);
        } catch (PDOException $e) {
            // در production فقط log کن، پیام دقیق نشون نده
            error_log('[HIASM-DB] ' . $e->getMessage());
            die(json_encode([
                'success' => false,
                'message' => 'خطا در اتصال به پایگاه داده'
            ]));
        }
    }
    return $pdo;
}