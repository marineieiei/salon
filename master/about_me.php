<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(2); // только мастер

$userId = $_SESSION['user']['id'];

// Получаем данные мастера
$stmt = $pdo->prepare("SELECT name, phone, diseases, created_at, photo_path FROM users WHERE id = ?");
$stmt->execute([$userId]);
$me = $stmt->fetch();

// Статистика за месяц
$firstDay = date('Y-m-01');
$lastDay  = date('Y-m-t');
$stmtStats = $pdo->prepare("SELECT COUNT(*) AS cnt, IFNULL(SUM(s.price),0) AS total
                             FROM appointments a
                             JOIN services s ON a.service_id = s.id
                             WHERE a.master_id = ?
                               AND DATE(a.start_datetime) BETWEEN ? AND ?
                               AND a.status != 'cancel'");
$stmtStats->execute([$userId, $firstDay, $lastDay]);
$stats = $stmtStats->fetch();

$photoPath = $me['photo_path'] ? "/salon/{$me['photo_path']}" : "/salon/img/avatar-placeholder.png";
?>

<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>PureMani Studio — Обо мне</title>
  <link rel="stylesheet" href="/salon/css/variables.css">
  <link rel="stylesheet" href="/salon/css/normalize.css"/>
  <link rel="stylesheet" href="/salon/css/base.css">
  <link rel="stylesheet" href="/salon/css/theme.css">
  <link rel="stylesheet" href="/salon/css/pages/master/about_me.css">
</head>
<body>
  <header>
    <nav>
      <a href="/salon/master/about_me.php" class="active">Обо мне</a> |
      <a href="/salon/master/appointments.php">Моё расписание</a> |
      <a href="/salon/index.html">Выйти</a>
    </nav>
  </header>

  <main class="container about-me">
    <h1>Обо мне</h1>
    <div class="about-me-flex">
      <div class="about-me-photo">
        <img src="<?= htmlspecialchars($photoPath) ?>" alt="Фото профиля">
      </div>
      <div class="about-me-info">
        <p><strong>Имя:</strong> <?= htmlspecialchars($me['name']) ?></p>
        <p><strong>Телефон:</strong> <?= htmlspecialchars($me['phone']) ?></p>
        <p><strong>Регистрация:</strong> <?= date('Y-m-d', strtotime($me['created_at'])) ?></p>
        <p><strong>За этот месяц заработано:</strong> <?= htmlspecialchars($stats['total']) ?> MDL</p>
        <p><strong>Записей за месяц:</strong> <?= htmlspecialchars($stats['cnt']) ?></p>
      </div>
    </div>
  </main>

</body>
</html>