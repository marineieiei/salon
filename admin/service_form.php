<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(1);

$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$isEdit = (bool)$id;
$error  = '';
if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->execute([$id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$service) {
        header('Location: services.php');
        exit;
    }
} else {
    $service = ['title'=>'', 'duration_minutes'=>'', 'price'=>''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title            = trim($_POST['title']            ?? '');
    $duration_minutes = trim($_POST['duration_minutes'] ?? '');
    $price            = trim($_POST['price']            ?? '');

    // валидация
    if ($title === '') {
        $error = 'Название услуги обязательно.';
    } elseif (!ctype_digit($duration_minutes) || (int)$duration_minutes<=0) {
        $error = 'Длительность — целое число минут.';
    } elseif (!is_numeric($price) || (float)$price<0) {
        $error = 'Цена должна быть неотрицательным числом.';
    }

    // проверка уникальности
    if (!$error) {
        $dupSql = "SELECT COUNT(*) FROM services WHERE title = ?";
        $dupParams = [$title];
        if ($isEdit) {
            $dupSql .= " AND id != ?";
            $dupParams[] = $id;
        }
        $dup = $pdo->prepare($dupSql);
        $dup->execute($dupParams);
        if ($dup->fetchColumn()>0) {
            $error = 'Услуга с таким названием уже существует.';
        }
    }

    // сохраняем
    if (!$error) {
        if ($isEdit) {
            $sql = "UPDATE services
                       SET title = ?, duration_minutes = ?, price = ?
                     WHERE id = ?";
            $params = [$title, $duration_minutes, $price, $id];
        } else {
            $sql = "INSERT INTO services
                       SET title = ?, duration_minutes = ?, price = ?";
            $params = [$title, $duration_minutes, $price];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $_SESSION['success'] = 'Услуга успешно ' . ($isEdit ? 'обновлена.' : 'добавлена.');
        header('Location: services.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Панель управления — <?= $isEdit ? 'Редактировать' : 'Добавить' ?> услугу</title>
  <link rel="stylesheet" href="/salon/css/variables.css"/>
  <link rel="stylesheet" href="/salon/css/base.css"/>
  <link rel="stylesheet" href="/salon/css/theme.css"/>
  <link rel="stylesheet" href="/salon/css/pages/admin/service_form.css"/>
</head>
<body>
  <header>
    <nav class="admin-nav">
      <a href="dashboard.php">Панель управления</a> |
      <a href="masters.php">Мастера</a> |
      <a href="clients.php">Клиенты</a> |
      <a href="services.php" class="active">Услуги</a> |
      <a href="appointments.php" >Записи</a> |
      <a href="settings.php">Настройки</a> |
      <a href="../index.html">Выйти</a>
    </nav>
  </header>

  <main class="container service-form">
    <h1><?= $isEdit ? 'Редактировать' : 'Добавить' ?> услугу</h1>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <label for="title">Название услуги</label>
      <input id="title" name="title" type="text" required
             value="<?= htmlspecialchars($service['title']) ?>"/>

      <label for="duration_minutes">Длительность (минуты)</label>
      <input id="duration_minutes" name="duration_minutes"
             type="number" min="1" required
             value="<?= htmlspecialchars($service['duration_minutes']) ?>"/>

      <label for="price">Цена (MDL)</label>
      <input id="price" name="price" type="number" step="0.01" min="0" required
             value="<?= htmlspecialchars($service['price']) ?>"/>

      <div class="buttons-row">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="services.php" class="btn btn-secondary">Отмена</a>
      </div>
    </form>
  </main>

</body>
</html>