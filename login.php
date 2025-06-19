<?php
// salon/login.php — страница входа в аккаунт
// Включаем вывод ошибок для отладки (уберите в проде)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Стартуем сессию, чтобы почистить старые данные
session_start();
// Удаляем предыдущие данные сессии и куки
session_unset();
session_destroy();
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Запускаем новую сессию
session_start();

require_once __DIR__ . '/db.php';  // подключение к БД

// Если уже авторизован, перенаправляем на соответствующий кабинет
if (!empty($_SESSION['user'])) {
    $role = $_SESSION['user']['role_id'];
    switch ($role) {
        case 1:
            header('Location: /salon/admin/dashboard.php');
            exit;
        case 2:
            header('Location: /salon/master/appointments.php');
            exit;
        case 3:
            header('Location: /salon/client/appointments.php');
            exit;
    }
}

$error = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($phone === '' || $password === '') {
        $error = 'Пожалуйста, заполните все поля.';
    } else {
        $stmt = $pdo->prepare('SELECT id, role_id, name, password FROM users WHERE phone = ?');
        $stmt->execute([$phone]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'      => $user['id'],
                'role_id' => $user['role_id'],
                'name'    => $user['name'],
            ];
            switch ($user['role_id']) {
                case 1:
                    header('Location: /salon/admin/dashboard.php');
                    exit;
                case 2:
                    header('Location: /salon/master/appointments.php');
                    exit;
                case 3:
                    header('Location: /salon/client/appointment.php');
                    exit;
            }
        } else {
            $error = 'Неправильный телефон или пароль.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Войти в аккаунт — PureMani Studio</title>
  <link rel="stylesheet" href="/salon/css/variables.css"/>
  <link rel="stylesheet" href="/salon/css/base.css"/>
  <link rel="stylesheet" href="/salon/css/theme.css"/>
  <link rel="stylesheet" href="/salon/css/pages/login.css"/>
</head>
<body>
  <div class="login-container">
    <h1>Вход в аккаунт</h1>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" action="login.php">
      <label for="phone">Телефон</label>
      <input
        type="tel"
        id="phone"
        name="phone"
        value="<?= htmlspecialchars($phone) ?>"
        required
        pattern="0[0-9]{8}"
        placeholder="0XXXXXXXX"
        oninvalid="this.setCustomValidity('Номер должен начинаться с 0 и содержать 9 цифр.')"
        oninput="this.setCustomValidity('')"
      />

      <label for="password">Пароль</label>
      <input
        type="password"
        id="password"
        name="password"
        required
        minlength="8"
        placeholder="••••••••"
        oninvalid="this.setCustomValidity(this.validity.valueMissing ? 'Пожалуйста, введите пароль.' : 'Пароль должен быть не менее 8 символов.')"
        oninput="this.setCustomValidity('')"
      />

      <button type="submit">Войти</button>
    </form>
    <p class="note">
      Ещё нет аккаунта? <a href="register.php">Зарегистрироваться</a>
    </p>
  </div>
</body>
</html>