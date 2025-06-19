<?php
// client/new.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(3);

// 0) Подготовка мастеров и услуг
$masters = $pdo
  ->query("SELECT id, name FROM users WHERE role_id = 2")
  ->fetchAll(PDO::FETCH_ASSOC);

$services = $pdo
  ->query("SELECT id, title, duration_minutes FROM services ORDER BY title")
  ->fetchAll(PDO::FETCH_ASSOC);

// 1) Обработка окончательного POST (только когда выбрано время)
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['time'])) {
  $m_id = (int)($_POST['master_id']  ?? 0);
  $s_id = (int)($_POST['service_id'] ?? 0);
  $date = $_POST['date']            ?? '';
  $time = $_POST['time']            ?? '';

  // Проверим, что всё есть
  if (!$m_id || !$s_id || !$date || !$time) {
    $error = 'Ошибка: недостающие данные для записи.';
  } else {
    // длительность услуги
    $stmtD = $pdo->prepare("SELECT duration_minutes FROM services WHERE id=?");
    $stmtD->execute([$s_id]);
    $duration = (int)$stmtD->fetchColumn();

    $start = new DateTime("$date $time");
    $end   = (clone $start)->add(new DateInterval("PT{$duration}M"));

    // проверка коллизии
    $chk = $pdo->prepare("
      SELECT COUNT(*) FROM appointments
       WHERE master_id=?
         AND start_datetime < ?
         AND end_datetime   > ?
         AND status!='cancel'
    ");
    $chk->execute([
      $m_id,
      $end  ->format('Y-m-d H:i:s'),
      $start->format('Y-m-d H:i:s'),
    ]);

    if ($chk->fetchColumn() > 0) {
      $error = 'Мастер уже занят в этот интервал.';
    } else {
      // вставка
      $ins = $pdo->prepare("
        INSERT INTO appointments
          (client_id,master_id,service_id,start_datetime,end_datetime,status,created_at)
        VALUES (?,?,?,?,?,'new',NOW())
      ");
      $ins->execute([
        $_SESSION['user']['id'],
        $m_id,
        $s_id,
        $start->format('Y-m-d H:i:s'),
        $end  ->format('Y-m-d H:i:s'),
      ]);
      header('Location: appointment.php?added=1');
      exit;
    }
  }
}

// 2) Какие GET-параметры уже выбраны?
$selMaster  = (int)($_GET['master_id']  ?? 0);
$selDate    = $_GET['date']             ?? '';
$selService = (int)($_GET['service_id'] ?? 0);

// 3) Если выбраны мастер+дата+услуга — генерим доступные слоты
$timeslots = [];
if ($selMaster && $selDate && $selService) {
  // длительность
  $stmtD = $pdo->prepare("SELECT duration_minutes FROM services WHERE id=?");
  $stmtD->execute([$selService]);
  $duration = (int)$stmtD->fetchColumn();

  // расписание этого мастера в день недели
  $dow = (new DateTime($selDate))->format('N');
  $stmtS = $pdo->prepare("
    SELECT start_time,end_time FROM schedules
     WHERE master_id=? AND day_of_week=?
  ");
  $stmtS->execute([$selMaster,$dow]);
  $schedule = $stmtS->fetchAll(PDO::FETCH_ASSOC);

  // уже занятые
  $stmtA = $pdo->prepare("
    SELECT start_datetime,end_datetime FROM appointments
     WHERE master_id=? AND DATE(start_datetime)=? AND status!='cancel'
  ");
  $stmtA->execute([$selMaster,$selDate]);
  $busy = $stmtA->fetchAll(PDO::FETCH_ASSOC);

  // шаг 1 час
  $step   = new DateInterval('PT1H');
  $durInt = new DateInterval("PT{$duration}M");

  foreach ($schedule as $seg) {
    $cursor   = new DateTime("{$selDate} {$seg['start_time']}");
    $endSched = new DateTime("{$selDate} {$seg['end_time']}");
    $limit    = (clone $endSched)->sub($durInt);

    while ($cursor <= $limit) {
      $s = clone $cursor;
      $e = (clone $cursor)->add($durInt);

      // проверяем на свободность
      $free = true;
      foreach ($busy as $b) {
        $bS = new DateTime($b['start_datetime']);
        $bE = new DateTime($b['end_datetime']);
        if (!($e <= $bS || $s >= $bE)) {
          $free = false; break;
        }
      }
      if ($free) {
        $timeslots[] = $s->format('H:i');
      }
      $cursor->add($step);
    }
  }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>PureMani Studio — Записаться</title>
  <link rel="stylesheet" href="/salon/css/variables.css"/>
  <link rel="stylesheet" href="/salon/css/base.css"/>
  <link rel="stylesheet" href="/salon/css/theme.css"/>
  <link rel="stylesheet" href="/salon/css/pages/client/new-appointment.css"/>
</head>
<body>

  <header>
    <nav>
      <a href="about_me.php">Обо мне</a> |
      <a href="appointment.php">Мои записи</a> |
      <a href="/salon/index.html">Выйти</a>
    </nav>
  </header>

  <main class="container booking">
   <h1 style="text-align: center;">Добавить запись</h1>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Шаги через GET -->
    <form method="get" class="step-form">
      <label for="master">1. Мастер</label>
      <select id="master" name="master_id" onchange="this.form.submit()">
        <option value="">— выберите мастера —</option>
        <?php foreach($masters as $m): ?>
          <option value="<?=$m['id']?>"<?= $selMaster===$m['id']?' selected':''?>>
            <?=htmlspecialchars($m['name'])?>
          </option>
        <?php endforeach; ?>
      </select>

      <?php if($selMaster): ?>
        <label for="date">2. Дата</label>
        <input id="date" type="date" name="date"
               value="<?=$selDate?>"
               min="<?=date('Y-m-d')?>"
               onchange="this.form.submit()">
      <?php endif; ?>

      <?php if($selMaster && $selDate): ?>
        <label for="service">3. Услуга</label>
        <select id="service" name="service_id" onchange="this.form.submit()">
          <option value="">— выберите услугу —</option>
          <?php foreach($services as $s): ?>
            <option value="<?=$s['id']?>"<?= $selService===$s['id']?' selected':''?>>
              <?=htmlspecialchars($s['title'])?> (<?=$s['duration_minutes']?> мин)
            </option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
    </form>

    <!-- Финальный POST-шаг -->
    <?php if($selMaster && $selDate && $selService): ?>
      <?php if(empty($timeslots)): ?>
        <p class="no-slots">
          Свободных слотов нет.
          <a href="new.php" class="back-link">← Выбрать другую дату</a>
        </p>
      <?php else: ?>
        <form method="post" class="step-form">
          <input type="hidden" name="master_id"  value="<?=$selMaster?>">
          <input type="hidden" name="date"       value="<?=$selDate?>">
          <input type="hidden" name="service_id" value="<?=$selService?>">

          <label for="time">4. Время</label>
          <select id="time" name="time">
            <?php foreach($timeslots as $t): ?>
              <option><?= $t ?></option>
            <?php endforeach; ?>
          </select>

          <div class="buttons-row">
            <button type="submit" class="btn btn-primary">Подтвердить</button>
          </div>
        </form>
      <?php endif; ?>
    <?php endif; ?>

    <!-- Постоянная ссылка назад -->
    <p><a href="appointment.php" class="back-link">← К моим записям</a></p>
  </main>

</body>
</html>