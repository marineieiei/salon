<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// salon/admin/masters.php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(1); // админ

// 1) поиск
$q = trim($_GET['q'] ?? '');

// 2) получаем мастеров
$sql = "SELECT id, name, phone, photo_path FROM users WHERE role_id = 2";
$params = [];
if ($q !== '') {
  $sql .= " AND name LIKE ? ";
  $params[] = "%{$q}%";
}
$sql .= " ORDER BY name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$masters = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Панель управления — Мастера</title>
  <link rel="stylesheet" href="/salon/css/variables.css"/>
  <link rel="stylesheet" href="/salon/css/base.css"/>
  <link rel="stylesheet" href="/salon/css/theme.css"/>
  <link rel="stylesheet" href="/salon/css/pages/admin/masters.css"/>
</head>
<body>
  <header>
    <nav class="admin-nav">
      <a href="/salon/admin/dashboard.php">Панель управления</a> |
      <a href="/salon/admin/masters.php" class="active">Мастера</a> |
      <a href="/salon/admin/clients.php">Клиенты</a> |
      <a href="/salon/admin/services.php">Услуги</a> |
      <a href="/salon/admin/appointments.php">Записи</a> |
      <a href="/salon/admin/settings.php">Настройки</a> |
      <a href="/salon/index.html">Выйти</a>
    </nav>
  </header>

  <main class="container">
    <?php if (!empty($_SESSION['error'])): ?>
  <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error']); ?></div>
  <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); ?></div>
  <?php unset($_SESSION['success']); ?>
<?php endif; ?>
    <h1>Мастера</h1>
    <div class="masters-actions">
      <a href="master_form.php" class="btn btn-primary">+ Добавить мастера</a>
      <form method="get" class="search-filter">
        <input type="text" name="q" placeholder="Поиск по имени" value="<?=htmlspecialchars($q)?>">
        <button type="submit" class="btn btn-secondary">Найти</button>
      </form>
    </div>

    <table class="masters-table">
      <thead>
        <tr>
          <th>Фото</th>
          <th>Имя</th>
          <th>Телефон</th>
          <th>Действия</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($masters as $m): ?>
        <tr>
          <td>
            <?php if($m['photo_path']): ?>
              <img src="/salon/<?=htmlspecialchars($m['photo_path'])?>" alt="" class="thumb"/>
            <?php endif; ?>
          </td>
          <td>
            <a href="master_form.php?id=<?=$m['id']?>">
              <?=htmlspecialchars($m['name'])?>
            </a>
          </td>
          <td><?=htmlspecialchars($m['phone'])?></td>
          <td>
            <a href="master_form.php?id=<?=$m['id']?>" class="btn-action">✎</a>
            <a href="master_delete.php?id=<?=$m['id']?>" class="btn-action"
               onclick="return confirm('Удалить мастера?')">🗑️</a>
            <a href="master_appointments.php?master_id=<?=$m['id']?>" class="btn-action">📅</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($masters)): ?>
        <tr><td colspan="4" class="no-records">Мастера не найдены.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </main>
  
</body>
</html>