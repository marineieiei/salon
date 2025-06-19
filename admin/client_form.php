<?php
// salon/admin/client_form.php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(1);

$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$isEdit = (bool)$id;
$error  = '';
$client = ['name'=>'','phone'=>'','diseases'=>''];

// --- 1) При редактировании подгружаем данные ---
if ($isEdit) {
    $stmt = $pdo->prepare("
      SELECT id, name, phone, diseases
        FROM users
       WHERE id = ? AND role_id = 3
    ");
    $stmt->execute([$id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC) ?: $client;
}

// --- 2) Обработка сабмита ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 2.1) Читаем из POST
    $name     = trim($_POST['name']     ?? '');
    $phone    = trim($_POST['phone']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $diseases = trim($_POST['diseases'] ?? '');

    // 2.2) Базовая валидация
    if ($name === '') {
        $error = 'Имя обязательно.';
    } elseif (!preg_match('/^\S+\s+\S+/', $name)) {
        $error = 'Имя должно состоять как минимум из двух слов.';
    } elseif (!preg_match('/^0\d{8}$/', $phone)) {
        $error = 'Телефон должен начинаться с 0 и содержать 9 цифр.';
    } elseif (!$isEdit && $password === '') {
        $error = 'Пароль обязателен.';
    } elseif ($password !== '' && !preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/', $password)) {
        $error = 'Пароль минимум 8 символов, латиница и цифры.';
    }

    // 2.3) Проверка на дубликат телефона
    if ($error === '') {
        $dupSql    = "SELECT COUNT(*) FROM users WHERE phone = ? ";
        $dupParams = [$phone];
        if ($isEdit) {
            $dupSql .= " AND id != ?";
            $dupParams[] = $id;
        }
        $dupStmt = $pdo->prepare($dupSql);
        $dupStmt->execute($dupParams);
        if ($dupStmt->fetchColumn() > 0) {
            $error = 'Такой номер телефона уже существует.';
        }
    }

    // 2.4) Если всё ок — вставляем или обновляем
    if ($error === '') {
        $fields = ['name = ?', 'phone = ?', 'diseases = ?'];
        $params = [$name, $phone, $diseases];
        if ($password !== '') {
            $fields[]  = 'password = ?';
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        if (!$isEdit) {
            $fields[] = 'role_id = 3';
        }

        if ($isEdit) {
            $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
            $params[] = $id;
        } else {
            $sql = "INSERT INTO users SET " . implode(', ', $fields);
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        header('Location: clients.php?saved=1');
        exit;
    }

    // Если есть $error — форма покажет его сама ниже
}?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Панель управления — <?= $isEdit ? 'Редактировать' : 'Добавить' ?> клиента</title>

  <link rel="stylesheet" href="/salon/css/variables.css"/>
  <link rel="stylesheet" href="/salon/css/base.css"/>
  <link rel="stylesheet" href="/salon/css/theme.css"/>
  <link rel="stylesheet" href="/salon/css/pages/admin/client_form.css"/>
</head>
<body>
  <header>
    <nav class="admin-nav">
      <a href="dashboard.php">Панель управления</a> |
      <a href="masters.php">Мастера</a> |
      <a href="clients.php" class="active">Клиенты</a> |
      <a href="services.php">Услуги</a> |
      <a href="appointments.php">Записи</a> |
      <a href="settings.php">Настройки</a> |
      <a href="../index.html">Выйти</a>
    </nav>
  </header>

  <main class="container client-form">
    <h1><?= $isEdit ? 'Редактировать' : 'Добавить' ?> клиента</h1>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <label for="name">Имя</label>
      <input id="name" name="name" type="text" required
             value="<?= htmlspecialchars($client['name']) ?>"/>

      <label for="phone">Телефон</label>
      <input id="phone" name="phone" type="tel" required
             placeholder="0XXXXXXXXX"
             value="<?= htmlspecialchars($client['phone']) ?>"/>

      <label for="password"><?= $isEdit ? 'Новый пароль' : 'Пароль' ?></label>
      <input id="password" name="password" type="password"
             <?= $isEdit ? '' : 'required' ?>
             placeholder="<?= $isEdit
               ? 'Оставьте пустым, чтобы не менять'
               : '' ?>"/>

      <label for="diseases">Противопоказания / заболевания</label>
      <textarea id="diseases" name="diseases" rows="4"><?= 
        htmlspecialchars($client['diseases']) ?></textarea>

      <div class="buttons-row">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="clients.php" class="btn btn-secondary">Отмена</a>
      </div>
    </form>
  </main>

</body>
</html>