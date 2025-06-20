<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(1);

$id     = $_GET['id'] ?? null;
$isEdit = (bool)$id;
$error  = '';

// --- 1) При редактировании подгружаем существующие данные ---
if ($isEdit) {
    $stmt = $pdo->prepare("SELECT id, name, phone, photo_path FROM users WHERE id = ? AND role_id = 2");
    $stmt->execute([$id]);
    $master = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$master) {
        header('Location: masters.php');
        exit;
    }
    $stmt2 = $pdo->prepare("
      SELECT day_of_week, start_time, end_time
        FROM schedules
       WHERE master_id = ?
    ");
    $stmt2->execute([$id]);
    $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    $workDays = array_column($rows, 'day_of_week');
    $timeFrom = $rows[0]['start_time'] ?? '';
    $timeTo   = $rows[0]['end_time']   ?? '';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $days     = $_POST['days'] ?? [];
    $timeFrom = $_POST['time_from'] ?? '';
    $timeTo   = $_POST['time_to']   ?? '';

    if ($name === '' || $phone === '') {
        $error = 'Имя и телефон обязательны.';
    }
    elseif (!preg_match('/^\S+\s+\S+/u', $name)) {
        $error = 'Имя должно состоять как минимум из двух слов.';
    }
    elseif (!preg_match('/^0\d{8}$/', $phone)) {
        $error = 'Телефон в формате 0XXXXXXXX (9 цифр).';
    }
    elseif ((!$isEdit && $password === '')
         || ($password !== '' && !preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $password))
    ) {
        $error = $isEdit
          ? 'Новый пароль должен быть не менее 8 символов и содержать буквы и цифры.'
          : 'Пароль обязателен и должен быть не менее 8 символов, содержать буквы и цифры.';
    }
    // 2.5) Изображение — обязательно при создании
    elseif (!$isEdit && empty($_FILES['photo']['tmp_name'])) {
        $error = 'Нужно загрузить фото мастера.';
    }
    // 2.6) Рабочие дни и время
    elseif (empty($days)) {
        $error = 'Нужно выбрать хотя бы один рабочий день.';
    } elseif ($timeFrom === '' || $timeTo === '') {
        $error = 'Укажите время работы.';
    } elseif (strtotime($timeFrom) >= strtotime($timeTo)) {
        $error = 'Время начала должно быть раньше времени окончания.';
    }

    // --- если ошибок нет, сохраняем ---
    if ($error === '') {
        $photoPath = $master['photo_path'] ?? null;
        if (!empty($_FILES['photo']['tmp_name'])) {
            $up  = $_FILES['photo'];
            $ext = pathinfo($up['name'], PATHINFO_EXTENSION);
            $newName   = 'photo/' . uniqid() . '.' . $ext;
            $targetDir = __DIR__ . '/../' . $newName;
            if (move_uploaded_file($up['tmp_name'], $targetDir)) {
                $photoPath = $newName;
            } else {
                error_log("Не удалось переместить файл в $targetDir");
            }
        }
        $fields = ['name = ?', 'phone = ?', 'photo_path = ?'];
        $params = [$name, $phone, $photoPath];
        if (!$isEdit) {
            $fields[] = 'role_id = 2';
        }
        if ($password !== '') {
            $fields[] = 'password = ?';
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        $sql = ($isEdit ? "UPDATE users SET " : "INSERT INTO users SET ")
             . implode(', ', $fields);
        if ($isEdit) {
            $sql .= " WHERE id = ?";
            $params[] = $id;
            $pdo->prepare($sql)->execute($params);
        } else {
            $pdo->prepare($sql)->execute($params);
            $id = $pdo->lastInsertId();
        }

        // 2.9) Обновление расписания
        $pdo->prepare("DELETE FROM schedules WHERE master_id = ?")
            ->execute([$id]);
        $ins = $pdo->prepare("
          INSERT INTO schedules (master_id, day_of_week, start_time, end_time)
          VALUES (?, ?, ?, ?)
        ");
        foreach ($days as $d) {
            $ins->execute([$id, $d, $timeFrom, $timeTo]);
        }

        header('Location: masters.php?saved=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Панель управления — <?= $isEdit ? 'Редактировать':'Добавить' ?> мастера</title>
  <link rel="stylesheet" href="/salon/css/variables.css"/>
  <link rel="stylesheet" href="/salon/css/base.css"/>
  <link rel="stylesheet" href="/salon/css/theme.css"/>
  <link rel="stylesheet" href="/salon/css/pages/admin/master_form.css"/>
</head>
<body>
  <header>
    <nav class="admin-nav">
      <a href="dashboard.php">Панель управления</a> |
      <a href="masters.php" class="active">Мастера</a> |
      <a href="clients.php">Клиенты</a> |
      <a href="services.php">Услуги</a> |
      <a href="appointments.php">Записи</a> |
      <a href="settings.php">Настройки</a> |
      <a href="../index.html">Выйти</a>
    </nav>
  </header>

  <main class="container master-form">
    <h1><?= $isEdit ? 'Редактировать':'Добавить' ?> мастера</h1>
    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <label for="name">Имя</label>
      <input id="name" name="name" type="text" required
             value="<?= htmlspecialchars($master['name'] ?? '') ?>"/>

      <label for="phone">Телефон</label>
      <input id="phone" name="phone" type="tel" required
             placeholder="0XXXXXXXX"
             value="<?= htmlspecialchars($master['phone'] ?? '') ?>"/>

      <label for="password"><?= $isEdit ? 'Новый пароль':'Пароль' ?></label>
      <input id="password" name="password" type="text"
             <?= $isEdit?'':'required' ?>
             placeholder="<?= $isEdit?'Оставьте пустым, чтобы не менять':'' ?>"/>

      <label for="photo">Фото</label>
      <input id="photo" name="photo" type="file" accept="image/*"/>
      <?php if ($isEdit && !empty($master['photo_path'])): ?>
        <img src="/salon/<?=htmlspecialchars($master['photo_path'])?>"
             class="current-photo" alt="Текущее фото"/>
      <?php endif; ?>

      <fieldset class="days-fieldset">
        <legend>Рабочие дни</legend>
        <?php
          $daysMap = ['1'=>'Пн','2'=>'Вт','3'=>'Ср','4'=>'Чт','5'=>'Пт','6'=>'Сб','7'=>'Вс'];
          foreach ($daysMap as $num=>$label):
        ?>
        <label>
          <input type="checkbox" name="days[]" value="<?= $num ?>"
            <?= !empty($workDays) && in_array($num,$workDays)?'checked':''?>/>
          <?= $label ?>
        </label>
        <?php endforeach; ?>
      </fieldset>

      <div class="time-row">
        <label>Время работы</label>
        <input name="time_from" type="time"
               value="<?= htmlspecialchars($timeFrom ?? '') ?>"/>
         —
        <input name="time_to"   type="time"
               value="<?= htmlspecialchars($timeTo   ?? '') ?>"/>
      </div>

      <div class="buttons-row">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="masters.php" class="btn btn-secondary">Отмена</a>
      </div>
    </form>
  </main>

</body>
</html>