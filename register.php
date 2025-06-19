<?php
// salon/register.php
// Запускаем сессию, если ещё не запущена
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/db.php';

$error = '';
// При успешной регистрации редиректим на login.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Сбор данных
    $name        = trim($_POST['name'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $diseases    = trim($_POST['diseases'] ?? '');
    $password    = $_POST['password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    // 1) Проверка имени: минимум два слова
    if (!preg_match('/^\S+\s+\S+/', $name)) {
        $error = 'Имя должно состоять минимум из двух слов.';
    }
    // 2) Проверка телефона: начинается с 0 + 8 цифр
    elseif (!preg_match('/^0\d{8}$/', $phone)) {
        $error = 'Телефон должен начинаться с 0 и содержать 8 цифр.';
    }
    // 3) Проверка уникальности телефона
    else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'Этот телефон уже зарегистрирован.';
        }
    }

    // 4) Проверка паролей
    if (!$error) {
        if ($password === '' || $confirmPass === '') {
            $error = 'Пароль и подтверждение обязательны.';
        } elseif ($password !== $confirmPass) {
            $error = 'Пароли не совпадают.';
        } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/', $password)) {
            $error = 'Пароль минимум 8 символов, латиница и цифры.';
        }
    }

    // Сохраняем пользователя, если ошибок нет
    if (!$error) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ins = $pdo->prepare(
            "INSERT INTO users (name, phone, diseases, password, role_id) VALUES (?, ?, ?, ?, 3)"
        );
        $ins->execute([$name, $phone, $diseases, $hash]);
        header('Location: login.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Регистрация клиента — PureMani Studio</title>
  <link rel="stylesheet" href="/salon/css/variables.css"/>
  <link rel="stylesheet" href="/salon/css/base.css"/>
  <link rel="stylesheet" href="/salon/css/theme.css"/>
  <link rel="stylesheet" href="/salon/css/pages/client/register.css"/>
</head>
<body>
  <main class="container registration-form">
    <h1>Регистрация клиента</h1>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <label for="name">ФИО</label>
      <input
        id="name" name="name" type="text" required
        placeholder="Имя Фамилия"
        value="<?= htmlspecialchars($name ?? '') ?>"
      />

      <label for="phone">Телефон</label>
      <input
        id="phone" name="phone" type="tel" required
        placeholder="0XXXXXXXXX"
        value="<?= htmlspecialchars($phone ?? '') ?>"
      />

      <label for="diseases">Противопоказания / заболевания</label>
      <textarea
        id="diseases" name="diseases" rows="3"
      ><?= htmlspecialchars($diseases ?? '') ?></textarea>

      <label for="password">Пароль</label>
      <input
        id="password" name="password" type="password" required
        placeholder="Мин. 8 символов, буквы и цифры"
      />

      <label for="confirm_password">Повтор пароля</label>
      <input
        id="confirm_password" name="confirm_password" type="password" required
        placeholder="Повторите пароль"
      />

      <div class="buttons-row">
        <button type="submit" class="btn btn-primary">Зарегистрироваться</button>
        <a href="login.php" class="btn btn-secondary">Отмена</a>
      </div>
    </form>
  </main>
</body>
</html>
