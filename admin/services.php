<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(1);

// 1) Поиск
$q = trim($_GET['q'] ?? '');

// 2) Получаем услуги
$sql = "SELECT id, title, price, duration_minutes FROM services";
$params = [];
if ($q !== '') {
    $sql .= " WHERE title LIKE ?";
    $params[] = "%{$q}%";
}
$sql .= " ORDER BY title";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3) Flash-сообщения
$type = isset($_SESSION['success']) ? 'success' : (isset($_SESSION['error']) ? 'error' : '');
$notice = '';
if ($type === 'success') {
    $notice = $_SESSION['success'];
} elseif ($type === 'error') {
    $notice = $_SESSION['error'];
}
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Панель управления — Услуги</title>
  <link rel="stylesheet" href="/salon/css/variables.css"/>
  <link rel="stylesheet" href="/salon/css/base.css"/>
  <link rel="stylesheet" href="/salon/css/theme.css"/>
  <link rel="stylesheet" href="/salon/css/pages/admin/services.css"/>
</head>
<body>
  <header>
    <nav class="admin-nav">
      <a href="dashboard.php">Панель управления</a> |
      <a href="masters.php">Мастера</a> |
      <a href="clients.php">Клиенты</a> |
      <a href="services.php" class="active">Услуги</a> |
      <a href="appointments.php">Записи</a> |
      <a href="settings.php">Настройки</a> |
      <a href="../index.html">Выйти</a>
    </nav>
  </header>

  <main class="container">
    <h1>Услуги</h1>

    <?php if ($notice): ?>
      <div class="alert alert-<?= $type ?>">
        <?= htmlspecialchars($notice) ?>
      </div>
    <?php endif; ?>

    <div class="services-actions">
      <a href="service_form.php" class="btn btn-primary">+ Добавить услугу</a>
      <form method="get" class="search-filter">
        <input type="text" name="q" placeholder="Поиск по названию" value="<?= htmlspecialchars($q) ?>">
        <button type="submit" class="btn btn-secondary">Найти</button>
      </form>
    </div>

    <table class="services-table">
      <thead>
        <tr>
          <th>Название</th>
          <th>Длительность (мин)</th>
          <th>Цена (MDL)</th>
          <th>Действия</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($services)): ?>
          <tr><td colspan="4" class="no-records">Услуг не найдено.</td></tr>
        <?php else: foreach ($services as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['title']) ?></td>
            <td><?= (int)$s['duration_minutes'] ?></td>
            <td><?= number_format($s['price'], 2, '.', ' ') ?></td>
            <td>
              <a href="service_form.php?id=<?= $s['id'] ?>" class="btn-action">✎</a>
              <a href="service_delete.php?id=<?= $s['id'] ?>" class="btn-action" onclick="return confirm('Удалить услугу «<?= htmlspecialchars($s['title']) ?>»?')">🗑️</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </main>
</body>
</html>