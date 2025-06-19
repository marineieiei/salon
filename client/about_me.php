<?php
// client/about_me.php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(3);

$userId  = $_SESSION['user']['id'];
$error   = '';
$success = '';

// Обработка сохранения
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $phone    = trim($_POST['phone']    ?? '');
    $diseases = trim($_POST['diseases'] ?? '');

    if ($name === '' || $phone === '') {
        $error = 'Имя и телефон обязательны.';
    } else {
        // Проверка уникальности телефона
        $chk = $pdo->prepare("
            SELECT COUNT(*) FROM users 
             WHERE phone = ? AND id != ?
        ");
        $chk->execute([$phone, $userId]);
        if ($chk->fetchColumn() > 0) {
            $error = 'Этот номер телефона уже занят.';
        } else {
            // Сохраняем
            $upd = $pdo->prepare("
                UPDATE users
                   SET name     = ?, 
                       phone    = ?, 
                       diseases = ?
                 WHERE id = ? AND role_id = 3
            ");
            $upd->execute([$name, $phone, $diseases, $userId]);
            $success = 'Данные успешно сохранены.';
            $_SESSION['user']['name'] = $name;
        }
    }
}

// Получаем текущее
$stmt = $pdo->prepare("
    SELECT name, phone, diseases 
      FROM users 
     WHERE id = ? AND role_id = 3
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>PureMani Studio — Обо мне</title>

  <!-- 2. Переменные -->
  <link rel="stylesheet" href="/salon/css/variables.css"/>
  <!-- 3. Базовые -->
  <link rel="stylesheet" href="/salon/css/base.css"/>
  <!-- 4. Тема -->
  <link rel="stylesheet" href="/salon/css/theme.css"/>
  <link rel="stylesheet" href="/salon/css/pages/client/about_me.css"/>
</head>
<body>

  <header>
    <nav>

      <a href="/salon/client/about_me.php" class="active">Обо мне</a> |
      <a href="/salon/client/appointment.php">Мои записи</a> |
      <a href="/salon/index.html">Выйти</a>
    </nav>
  </header>

  <main class="container about-me">
    <h1>Обо мне</h1>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Просмотр -->
    <div id="profile-view">
      <p><strong>Имя:</strong> <?= htmlspecialchars($user['name']) ?></p>
      <p><strong>Телефон:</strong> <?= htmlspecialchars($user['phone']) ?></p>
      <p><strong>Противопоказания:</strong>
        <?= $user['diseases']!=='' 
            ? nl2br(htmlspecialchars($user['diseases'])) 
            : '<em>не указано</em>' ?>
      </p>
      <button id="edit-btn" class="btn btn-secondary">Редактировать</button>
    </div>

    <!-- Форма редактирования -->
    <form id="profile-form" method="post" class="profile-form hidden">
      <label for="name">Имя</label>
      <input id="name"      type="text"  name="name"      required
             value="<?= htmlspecialchars($user['name']) ?>"/>

      <label for="phone">Телефон</label>
      <input id="phone"     type="tel"   name="phone"     required
             pattern="0[0-9]{8,9}"
             placeholder="0XXXXXXXX"
             value="<?= htmlspecialchars($user['phone']) ?>"/>

      <label for="diseases">Противопоказания / особенности</label>
      <textarea id="diseases" name="diseases" rows="4"><?= 
        htmlspecialchars($user['diseases']) ?></textarea>

      <div class="buttons-row">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <button type="button" id="cancel-btn" class="btn btn-secondary">Отмена</button>
      </div>
    </form>
  </main>



  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const view    = document.getElementById('profile-view');
    const form    = document.getElementById('profile-form');
    const editBtn = document.getElementById('edit-btn');
    const cancel  = document.getElementById('cancel-btn');

    editBtn.addEventListener('click', () => {
      view.classList.add('hidden');
      form.classList.remove('hidden');
    });
    cancel.addEventListener('click', () => {
      form.classList.add('hidden');
      view.classList.remove('hidden');
    });
  });
  </script>
</body>
</html>