<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(1); 

// --- Статистика: кол-во мастеров и клиентов ---
$totals = $pdo->query("
    SELECT
      (SELECT COUNT(*) FROM users WHERE role_id=2) AS masters,
      (SELECT COUNT(*) FROM users WHERE role_id=3) AS clients
")->fetch();

// --- Доход за месяц и неделю ---
$firstDay = date('Y-m-01');
$lastDay  = date('Y-m-t');
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd   = date('Y-m-d', strtotime('sunday this week'));

$incStmt = $pdo->prepare("
    SELECT
      SUM(CASE WHEN DATE(a.start_datetime) BETWEEN ? AND ? THEN s.price ELSE 0 END) AS month_income,
      SUM(CASE WHEN DATE(a.start_datetime) BETWEEN ? AND ? THEN s.price ELSE 0 END) AS week_income
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    WHERE a.status != 'cancel'
");
$incStmt->execute([$firstDay,$lastDay,$weekStart,$weekEnd]);
$inc = $incStmt->fetch();

// --- Сегодняшние записи ---
$today = date('Y-m-d');
$todayStmt = $pdo->prepare("
    SELECT
      TIME(a.start_datetime)      AS time,
      s.title                     AS service,
      c.name                      AS client_name,
      c.phone                     AS client_phone,
      m.name                      AS master_name,
      m.phone                     AS master_phone
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    JOIN users    c ON a.client_id   = c.id
    JOIN users    m ON a.master_id   = m.id
    WHERE DATE(a.start_datetime) = ?
      AND a.status != 'cancel'
    ORDER BY a.start_datetime
");
$todayStmt->execute([$today]);
$todayRows = $todayStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Админ — Панель управления</title>
  <link rel="stylesheet" href="/salon/css/variables.css"/>
  <link rel="stylesheet" href="/salon/css/base.css"/>
  <link rel="stylesheet" href="/salon/css/theme.css"/>
  <link rel="stylesheet" href="/salon/css/pages/admin/dashboard.css"/>
</head>
<body>
  <header>
    <nav class="admin-nav">
      <a href="/salon/admin/dashboard.php" class="active">Панель управления</a> |
      <a href="/salon/admin/masters.php" >Мастера</a> |
      <a href="/salon/admin/clients.php">Клиенты</a> |
      <a href="/salon/admin/services.php">Услуги</a> |
      <a href="/salon/admin/appointments.php">Записи</a> |
      <a href="/salon/admin/settings.php">Настройки</a> |
      <a href="/salon/index.html">Выйти</a>
    </nav>
  </header>

  <main class="container dashboard">

    <!-- Статистика -->
    <div class="stats-grid">
      <div class="stat-card">
        <h2><?= $totals['masters'] ?></h2>
        <p>Мастеров</p>
      </div>
      <div class="stat-card">
        <h2><?= $totals['clients'] ?></h2>
        <p>Клиентов</p>
      </div>
      <div class="stat-card">
        <h2><?= $inc['month_income'] ?> MDL</h2>
        <p>Доход за месяц</p>
      </div>
      <div class="stat-card">
        <h2><?= $inc['week_income'] ?> MDL</h2>
        <p>Доход за неделю</p>
      </div>
    </div>

    <!-- Сегодняшние записи -->
    <h2 class="section-title">Записи за сегодня (<?= date('d.m.Y') ?>)</h2>
    <?php if (empty($todayRows)): ?>
      <p class="no-records">Сегодня записей нет.</p>
    <?php else: ?>
      <table class="reports-table">
        <thead>
          <tr>
            <th class="time">Время</th>
            <th class="service">Услуга</th>
            <th class="client">Клиент</th>
            <th class="phone-client">Телефон клиента</th>
            <th class="master">Мастер</th>
            <th class="phone-master">Телефон мастера</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($todayRows as $r): ?>
          <tr class="today">
            <td class="time"><?= htmlspecialchars($r['time']) ?></td>
            <td class="service"><?= htmlspecialchars($r['service']) ?></td>
            <td class="client"><?= htmlspecialchars($r['client_name']) ?></td>
            <td class="phone-client"><?= htmlspecialchars($r['client_phone']) ?></td>
            <td class="master"><?= htmlspecialchars($r['master_name']) ?></td>
            <td class="phone-master"><?= htmlspecialchars($r['master_phone']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

  </main>


</body>
</html>