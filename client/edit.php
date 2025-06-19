<?php
// client/edit.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(3);

// 1) ИД записи
$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
if (!$id) {
  header('Location: appointment.php');
  exit;
}

// 2) Обработка POST — сохраняем новую дату/время
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $newDate = $_POST['date'] ?? '';
  $newTime = $_POST['time'] ?? '';

  // базовая валидация
  $tomorrow = (new DateTime('tomorrow'))->format('Y-m-d');
  if (!$newDate || !$newTime || $newDate < $tomorrow) {
    $error = 'Нельзя выбрать сегодня или прошлую дату.';
  } else {
    // получаем длительность из БД
    $stmt = $pdo->prepare("
      SELECT a.master_id, s.duration_minutes
        FROM appointments a
        JOIN services s ON a.service_id = s.id
       WHERE a.id = ? AND a.client_id = ?
    ");
    $stmt->execute([$id, $_SESSION['user']['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      header('Location: appointment.php');
      exit;
    }
    $duration = (int)$row['duration_minutes'];
    $masterId = (int)$row['master_id'];

    // формируем новые DateTime
    $start = DateTime::createFromFormat('Y-m-d H:i', "$newDate $newTime");
    $end   = (clone $start)->add(new DateInterval("PT{$duration}M"));

    // проверяем пересечение
    $chk = $pdo->prepare("
      SELECT COUNT(*) FROM appointments
       WHERE master_id = ?
         AND id != ?
         AND start_datetime < ?
         AND end_datetime   > ?
         AND status != 'cancel'
    ");
    $chk->execute([
      $masterId,
      $id,
      $end->format('Y-m-d H:i:s'),
      $start->format('Y-m-d H:i:s'),
    ]);

    if ($chk->fetchColumn() > 0) {
      $error = 'Мастер занят в этот промежуток. Выберите другое время.';
    } else {
      // обновляем
      $upd = $pdo->prepare("
        UPDATE appointments
           SET start_datetime = ?, end_datetime = ?
         WHERE id = ?
      ");
      $upd->execute([
        $start->format('Y-m-d H:i:s'),
        $end  ->format('Y-m-d H:i:s'),
        $id
      ]);
      header('Location: appointment.php?edited=1');
      exit;
    }
  }
}

// 3) Загрузка исходных данных
$stmt = $pdo->prepare("
  SELECT a.master_id, a.service_id, a.start_datetime, s.duration_minutes
    FROM appointments a
    JOIN services s ON a.service_id = s.id
   WHERE a.id = ? AND a.client_id = ?
");
$stmt->execute([$id, $_SESSION['user']['id']]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$app) {
  header('Location: appointment.php');
  exit;
}

// исходные
$origDt     = new DateTime($app['start_datetime']);
$origDate   = $origDt->format('Y-m-d');
$origTime   = $origDt->format('H:i');
$masterId   = $app['master_id'];
$serviceId  = $app['service_id'];
$duration   = $app['duration_minutes'];

// 4) Выбранная дата — из GET (если передана) или исходная
$selDate = $_GET['date'] ?? $origDate;
$tomorrow = (new DateTime('tomorrow'))->format('Y-m-d');
if ($selDate < $tomorrow) {
  $selDate = $origDate;
}

// 5) Генерация слотов (шаг 1 час)
$timeslots = [];
// только если мы на GET-этапе (не сохраняем) — или всегда, но ошибку не мешает
$dow = (new DateTime($selDate))->format('N');
$schedQ = $pdo->prepare("
  SELECT start_time,end_time
    FROM schedules
   WHERE master_id = ? AND day_of_week = ?
");
$schedQ->execute([$masterId, $dow]);
$schedule = $schedQ->fetchAll(PDO::FETCH_ASSOC);

// занятость, исключая текущую запись
$busyQ = $pdo->prepare("
  SELECT start_datetime,end_datetime
    FROM appointments
   WHERE master_id = ?
     AND id != ?
     AND DATE(start_datetime) = ?
     AND status != 'cancel'
");
$busyQ->execute([$masterId, $id, $selDate]);
$busy = $busyQ->fetchAll(PDO::FETCH_ASSOC);

$step   = new DateInterval('PT1H');
$durInt = new DateInterval("PT{$duration}M");

foreach ($schedule as $seg) {
  $cursor   = new DateTime("{$selDate} {$seg['start_time']}");
  $endSched = new DateTime("{$selDate} {$seg['end_time']}");
  $limit    = (clone $endSched)->sub($durInt);

  while ($cursor <= $limit) {
    $s = clone $cursor;
    $e = (clone $cursor)->add($durInt);
    // проверка пересечения
    $free = true;
    foreach ($busy as $b) {
      $bS = new DateTime($b['start_datetime']);
      $bE = new DateTime($b['end_datetime']);
      if (!($e <= $bS || $s >= $bE)) {
        $free = false;
        break;
      }
    }
    if ($free) {
      $timeslots[] = $s->format('H:i');
    }
    $cursor->add($step);
  }
}
?><!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Редактировать запись — PureMani Studio</title>

  <link rel="stylesheet" href="/salon/css/variables.css"/>
  <link rel="stylesheet" href="/salon/css/base.css"/>
  <link rel="stylesheet" href="/salon/css/theme.css"/>
  <link rel="stylesheet" href="/salon/css/pages/client/edit.css"/>
</head>
<body>
  <header>
    <nav>
      <a href="about_me.php">Обо мне</a> |
      <a href="appointment.php">Мои записи</a> |
      <a href="/salon/index.html">Выйти</a>
    </nav>
  </header>

  <main class="container edit-appointment">
    <h1 style="text-align: center">Редактировать запись</h1>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="edit.php?id=<?= $id ?>" class="step-form">
      <!-- мастер и услуга только отображаются -->
      <p><strong>Мастер:</strong>
        <?= htmlspecialchars(
             // получаем имя мастера из таблицы
             $pdo->query("SELECT name FROM users WHERE id={$masterId}")
                 ->fetchColumn()
           ) ?>
      </p>
      <p><strong>Услуга:</strong>
        <?= htmlspecialchars(
             $pdo->query("SELECT title FROM services WHERE id={$serviceId}")
                 ->fetchColumn()
           ) ?> (<?= $duration ?> мин)
      </p>

      <!-- 1. Дата -->
      <label>Дата</label>
      <input id="date-picker"
             type="date"
             name="date"
             min="<?= $tomorrow ?>"
             value="<?= htmlspecialchars($selDate) ?>"
             required>

      <!-- 2. Время -->
      <label>Время</label>
      <select name="time" required>
        <?php if (empty($timeslots)): ?>
          <option value="">Свободных слотов нет</option>
        <?php else: ?>
          <?php foreach ($timeslots as $t): ?>
            <option <?= $t === $origTime && $selDate === $origDate ? 'selected' : '' ?>>
              <?= $t ?>
            </option>
          <?php endforeach; ?>
        <?php endif; ?>
      </select>

      <div class="buttons-row">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="appointment.php" class="btn btn-secondary">Отмена</a>
      </div>
    </form>
  </main>


  <script>
    // при смене даты — перезагрузить страницу с GET-параметром ?id=…&date=…
    document
      .getElementById('date-picker')
      .addEventListener('change', function(){
        const d = this.value;
        if (d) {
          const params = new URLSearchParams(location.search);
          params.set('date', d);
          location.search = params.toString();
        }
      });
  </script>
</body>
</html>