<?php
// salon/admin/appointment_form.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(1);

$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$isEdit = (bool)$id;
$error  = '';

// --- 1) Если редактируем — подгружаем все данные из БД ---
if ($isEdit) {
    $stmt = $pdo->prepare("
      SELECT 
        a.*, 
        c.name AS client_name, 
        u.name AS master_name, 
        s.title AS service_title, 
        s.duration_minutes
      FROM appointments a
      JOIN users    c ON a.client_id = c.id
      JOIN users    u ON a.master_id = u.id
      JOIN services s ON a.service_id = s.id
      WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $appt = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$appt) {
      $_SESSION['error'] = 'Запись не найдена.';
      header('Location: appointments.php');
      exit;
    }

    // Выносим в переменные
    $clientId     = $appt['client_id'];
    $clientName   = $appt['client_name'];
    $masterId     = $appt['master_id'];
    $masterName   = $appt['master_name'];
    $serviceId    = $appt['service_id'];
    $serviceTitle = $appt['service_title'];
    $duration     = (int)$appt['duration_minutes'];

    // Дата/время по умолчанию
    $origDt   = new DateTime($appt['start_datetime']);
    $selDate  = $_GET['date'] ?? $origDt->format('Y-m-d');
    $origTime = $origDt->format('H:i');

} else {
    // --- 2) Если добавляем — грузим списки для селектов ---
    $clients  = $pdo->query("SELECT id, name FROM users WHERE role_id=3 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $masters  = $pdo->query("SELECT id, name FROM users WHERE role_id=2 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $services = $pdo->query("SELECT id, title, duration_minutes FROM services ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);

    // инициализация для добавления
    $clientId  = $_POST['client_id']  ?? 0;
    $masterId  = $_POST['master_id']  ?? 0;
    $serviceId = $_POST['service_id'] ?? 0;
    $duration  = 0;

    $selDate  = $_GET['date'] ?? '';
    $origTime = '';
}

// --- 3) Генерация свободных слотов (только если есть мастер, услуга и длительность) ---
$timeslots = [];
if ($selDate && $masterId && $serviceId && $duration > 0) {
  // расписание
  $dow    = (new DateTime($selDate))->format('N');
  $schedQ = $pdo->prepare("
    SELECT start_time,end_time
      FROM schedules
     WHERE master_id = ? AND day_of_week = ?
  ");
  $schedQ->execute([$masterId, $dow]);
  $schedule = $schedQ->fetchAll(PDO::FETCH_ASSOC);

  // занятость (для редактирования — исключаем текущую запись)
  $busySql    = "
    SELECT start_datetime,end_datetime
      FROM appointments
     WHERE master_id = ?
       AND status != 'cancel'
       AND DATE(start_datetime) = ?";
  $busyParams = [$masterId, $selDate];
  if ($isEdit) {
    $busySql    .= " AND id != ?";
    $busyParams[] = $id;
  }
  $busyQ = $pdo->prepare($busySql);
  $busyQ->execute($busyParams);
  $busy = $busyQ->fetchAll(PDO::FETCH_ASSOC);

  $step = new DateInterval('PT1H');
  $dur  = new DateInterval("PT{$duration}M");

  foreach ($schedule as $seg) {
    $cursor   = new DateTime("{$selDate} {$seg['start_time']}");
    $endSched = new DateTime("{$selDate} {$seg['end_time']}");
    $limit    = (clone $endSched)->sub($dur);
    while ($cursor <= $limit) {
      $s = clone $cursor;
      $e = (clone $cursor)->add($dur);
      $free = true;
      foreach ($busy as $b) {
        $bS = new DateTime($b['start_datetime']);
        $bE = new DateTime($b['end_datetime']);
        if (!($e <= $bS || $s >= $bE)) { $free = false; break; }
      }
      if ($free) {
        $timeslots[] = $s->format('H:i');
      }
      $cursor->add($step);
    }
  }
}

// --- 4) Обработка POST (сохранение) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $date = $_POST['date'] ?? '';
  $time = $_POST['time'] ?? '';

  $tomorrow = (new DateTime('tomorrow'))->format('Y-m-d');
  if (!$date || !$time || $date < $tomorrow) {
    $error = 'Нельзя выбрать сегодняшний или прошедшую дату.';
  } else {
    $start = DateTime::createFromFormat('Y-m-d H:i', "$date $time");
    $end   = (clone $start)->add(new DateInterval("PT{$duration}M"));

    $chkSql    = "
      SELECT COUNT(*) FROM appointments
       WHERE master_id = ?
         AND status != 'cancel'
         AND start_datetime < ?
         AND end_datetime   > ?";
    $chkParams = [$masterId, $end->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s')];
    if ($isEdit) {
      $chkSql    .= " AND id != ?";
      $chkParams[] = $id;
    }
    $chk = $pdo->prepare($chkSql);
    $chk->execute($chkParams);

    if ($chk->fetchColumn() > 0) {
      $error = 'Мастер занят в этот промежуток.';
    } else {
      if ($isEdit) {
        $upd = $pdo->prepare("
          UPDATE appointments
             SET start_datetime = ?, end_datetime = ?
           WHERE id = ?");
        $upd->execute([
          $start->format('Y-m-d H:i:s'),
          $end->format('Y-m-d H:i:s'),
          $id
        ]);
        $_SESSION['success'] = 'Запись обновлена.';
      } else {
        $ins = $pdo->prepare("
          INSERT INTO appointments
            (client_id, master_id, service_id, start_datetime, end_datetime)
          VALUES (?,?,?,?,?)");
        $ins->execute([
          $clientId, $masterId, $serviceId,
          $start->format('Y-m-d H:i:s'),
          $end->format('Y-m-d H:i:s'),
        ]);
        $_SESSION['success'] = 'Запись создана.';
      }
      header('Location: appointments.php');
      exit;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>
    Панель управления —
    <?= $isEdit ? 'Редактировать запись' : 'Добавить запись' ?>
  </title>
  <link rel="stylesheet" href="/salon/css/variables.css"/>
  <link rel="stylesheet" href="/salon/css/base.css"/>
  <link rel="stylesheet" href="/salon/css/theme.css"/>
  <link rel="stylesheet" href="/salon/css/pages/admin/appointment_form.css"/>
</head>
<body>
  <header>
    <nav class="admin-nav">
      <a href="/salon/admin/dashboard.php">Панель управления</a> |
      <a href="/salon/admin/masters.php" >Мастера</a> |
      <a href="/salon/admin/clients.php">Клиенты</a> |
      <a href="/salon/admin/services.php">Услуги</a> |
      <a href="/salon/admin/appointments.php" class="active">Записи</a> |
      <a href="/salon/admin/settings.php">Настройки</a> |
      <a href="/salon/index.html">Выйти</a>
    </nav>
  </header>

  <main class="container edit-appointment">
    <h1><?= $isEdit ? 'Редактировать запись' : 'Добавить запись' ?></h1>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" class="step-form">
      <?php if ($isEdit): ?>
        <p><strong>Клиент:</strong>  <?= htmlspecialchars($clientName)  ?></p>
        <p><strong>Мастер:</strong>  <?= htmlspecialchars($masterName)  ?></p>
        <p><strong>Услуга:</strong>  <?= htmlspecialchars($serviceTitle) ?> (<?= $duration ?> мин)</p>
      <?php else: ?>
        <label for="client_id">Клиент</label>
        <select id="client_id" name="client_id" required>
          <option value="">— выберите —</option>
          <?php foreach($clients as $c): ?>
            <option value="<?=$c['id']?>" <?=isset($clientId)&&$clientId==$c['id']?'selected':''?>>
              <?=htmlspecialchars($c['name'])?>
            </option>
          <?php endforeach;?>
        </select>

        <label for="master_id">Мастер</label>
        <select id="master_id" name="master_id" required>
          <option value="">— выберите —</option>
          <?php foreach($masters as $m): ?>
            <option value="<?=$m['id']?>" <?=isset($masterId)&&$masterId==$m['id']?'selected':''?>>
              <?=htmlspecialchars($m['name'])?>
            </option>
          <?php endforeach;?>
        </select>

        <label for="service_id">Услуга</label>
        <select id="service_id" name="service_id" required>
          <option value="">— выберите —</option>
          <?php foreach($services as $s): ?>
            <option value="<?=$s['id']?>" <?=isset($serviceId)&&$serviceId==$s['id']?'selected':''?>>
              <?=htmlspecialchars($s['title'])?> (<?=$s['duration_minutes']?> мин)
            </option>
          <?php endforeach;?>
        </select>
      <?php endif; ?>

      <label for="date">Дата</label>
      <input id="date" name="date" type="date"
             value="<?= htmlspecialchars($selDate) ?>"
             min="<?= (new DateTime('tomorrow'))->format('Y-m-d') ?>"
             required/>

      <label for="time">Время</label>
      <select id="time" name="time" required>
        <?php if (empty($timeslots)): ?>
          <option value="">— свободных слотов нет —</option>
        <?php else: ?>
          <?php foreach($timeslots as $t): ?>
            <option value="<?=$t?>" <?= $t === $origTime ? 'selected':''?>>
              <?=$t?>
            </option>
          <?php endforeach;?>
        <?php endif;?>
      </select>

      <div class="buttons-row">
        <button type="submit" class="btn btn-primary">
          <?= $isEdit ? 'Сохранить' : 'Создать' ?>
        </button>
        <a href="appointments.php" class="btn btn-secondary">Отмена</a>
      </div>
    </form>
  </main>

  <script>
    document.getElementById('date').addEventListener('change', ()=>{
      const params = new URLSearchParams(location.search);
      params.set('id', <?= $isEdit ? $id : 0 ?>);
      params.set('date', document.getElementById('date').value);
      location.search = params.toString();
    });
  </script>
</body>
</html>