<?php
// salon/admin/clients.php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(1); // роль «админ»

// 1) flash-уведомления
$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// 2) поиск
$q = trim($_GET['q'] ?? '');

// 3) получаем клиентов
$sql = "SELECT id, name, phone, diseases FROM users WHERE role_id = 3";
$params = [];
if ($q !== '') {
  $sql .= " AND (name LIKE ? OR phone LIKE ?)";
  $like = "%{$q}%";
  $params[] = $like;
  $params[] = $like;
}
$sql .= " ORDER BY name";
$stmt    = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Панель управления — Клиенты</title>

  <link rel="stylesheet" href="/salon/css/variables.css"/>
  <link rel="stylesheet" href="/salon/css/base.css"/>
  <link rel="stylesheet" href="/salon/css/theme.css"/>
  <link rel="stylesheet" href="/salon/css/pages/admin/clients.css"/>
</head>
<body>
  <header>
    <nav class="admin-nav">
      <a href="/salon/admin/dashboard.php">Панель управления</a> |
      <a href="/salon/admin/masters.php" >Мастера</a> |
      <a href="/salon/admin/clients.php" class="active">Клиенты</a> |
      <a href="/salon/admin/services.php">Услуги</a> |
      <a href="/salon/admin/appointments.php">Записи</a> |
      <a href="/salon/admin/settings.php">Настройки</a> |
      <a href="/salon/index.html">Выйти</a>
    </nav>
  </header>

  <main class="container">
    <h1>Клиенты</h1>

    <!-- flash-уведомления -->
    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php elseif ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="clients-actions">
      <a href="client_form.php" class="btn btn-primary">+ Добавить клиента</a>
      <form method="get" class="search-filter">
        <input
          type="text"
          name="q"
          placeholder="Поиск по имени или телефону"
          value="<?= htmlspecialchars($q) ?>"
        />
        <button type="submit" class="btn btn-secondary">Найти</button>
      </form>
    </div>

    <table class="clients-table">
      <thead>
        <tr>
          <th>Имя</th>
          <th>Телефон</th>
          <th>Противопоказания</th>
          <th>Действия</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($clients)): ?>
          <tr>
            <td colspan="4" class="no-records">Клиенты не найдены.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($clients as $c): ?>
          <tr>
            <td>
              <a href="client_form.php?id=<?= $c['id'] ?>">
                <?= htmlspecialchars($c['name']) ?>
              </a>
            </td>
            <td><?= htmlspecialchars($c['phone']) ?></td>
            <td>
              <?= $c['diseases'] !== ''
                   ? nl2br(htmlspecialchars($c['diseases']))
                   : '<em>нет</em>' ?>
            </td>
            <td>
              <a href="client_form.php?id=<?= $c['id'] ?>" class="btn-action">✎</a>
              <a
                href="client_delete.php?id=<?= $c['id'] ?>"
                class="btn-action"
                onclick="return confirm('Удалить клиента?')"
              >🗑️</a>
              <a href="client_appointments.php?client_id=<?= $c['id'] ?>" class="btn-action">📅</a>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </main>
</body>
</html>