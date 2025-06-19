<?php
// database.php — подключение к БД

$host     = 'localhost';
$dbname   = 'salon';  // имя БД, маленькими буквами — потом в DSN тоже $dbname
$user     = 'root';   // твой MySQL-пользователь
$password = '';       // твой MySQL-пароль

$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

try {
    $pdo = new PDO(
        $dsn,
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}