<?php
// salon/admin/add_appointment.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(1);

// --- 1) Справочники ---
$clients  = $pdo->query("SELECT id, name FROM users WHERE role_id=3 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$masters  = $pdo->query("SELECT id, name FROM users WHERE role_id=2 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$services = $pdo->query("SELECT id, title, duration_minutes FROM services ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);

$error = '';

// --- 2) Обработка POST (финальный шаг) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['time'])) {
    $clientId  = (int)($_POST['client_id']  ?? 0);
    $masterId  = (int)($_POST['master_id']  ?? 0);
    $serviceId = (int)($_POST['service_id'] ?? 0);
    $date      = trim($_POST['date']       ?? '');
    $time      = trim($_POST['time']       ?? '');

    // валидация
    if (!$clientId || !$masterId || !$serviceId || !$date || !$time) {
        $error = 'Нужно заполнить все поля.';
    } else {
        $start = DateTime::createFromFormat('Y-m-d H:i', "$date $time");
        if (!$start) {
            $error = 'Неправильный формат даты/времени.';
        } elseif ($start < new DateTime('now')) {
            $error = 'Нельзя записаться в прошлое.';
        }
    }

    // длительность услуги и проверка занятости
    if (!$error) {
        $stmt = $pdo->prepare("SELECT duration_minutes FROM services WHERE id = ?");
        $stmt->execute([$serviceId]);
        $duration = (int)$stmt->fetchColumn();
        $end = (clone $start)->add(new DateInterval("PT{$duration}M"));

        $chk = $pdo->prepare("
            SELECT COUNT(*) FROM appointments
             WHERE master_id = ?
               AND start_datetime < ?
               AND end_datetime   > ?
               AND status != 'cancel'
        ");
        $chk->execute([
            $masterId,
            $end->format('Y-m-d H:i:s'),
            $start->format('Y-m-d H:i:s'),
        ]);
        if ($chk->fetchColumn() > 0) {
            $error = 'Мастер занят в этот промежуток.';
        }
    }

    // вставка и редирект
    if (!$error) {
        $ins = $pdo->prepare("
            INSERT INTO appointments
              (client_id, master_id, service_id, start_datetime, end_datetime)
            VALUES (?, ?, ?, ?, ?)
        ");
        $ins->execute([
            $clientId,
            $masterId,
            $serviceId,
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
        ]);
        header('Location: appointments.php?added=1');
        exit;
    }
}

// --- 3) Текущие шаги (из GET или POST при ошибке) ---
$selClient  = $_REQUEST['client_id']  ?? '';
$selMaster  = $_REQUEST['master_id']  ?? '';
$selService = $_REQUEST['service_id'] ?? '';
$selDate    = $_REQUEST['date']       ?? '';

// --- 4) Генерация свободных слотов ---
$timeslots = [];
if ($selMaster && $selService && $selDate) {
    // длительность
    $stmt = $pdo->prepare("SELECT duration_minutes FROM services WHERE id = ?");
    $stmt->execute([$selService]);
    $duration = (int)$stmt->fetchColumn();

    // расписание мастера
    $dow = (new DateTime($selDate))->format('N');
    $sched = $pdo->prepare("
        SELECT start_time, end_time
          FROM schedules
         WHERE master_id = ? AND day_of_week = ?
    ");
    $sched->execute([$selMaster, $dow]);
    $schedule = $sched->fetchAll(PDO::FETCH_ASSOC);

    // занятость
    $busyQ = $pdo->prepare("
        SELECT start_datetime, end_datetime
          FROM appointments
         WHERE master_id = ?
           AND status != 'cancel'
           AND DATE(start_datetime) = ?
    ");
    $busyQ->execute([$selMaster, $selDate]);
    $busy = $busyQ->fetchAll(PDO::FETCH_ASSOC);

    $stepInt = new DateInterval('PT1H');
    $durInt  = new DateInterval("PT{$duration}M");

    foreach ($schedule as $seg) {
        $cursor   = new DateTime("{$selDate} {$seg['start_time']}");
        $endSched = new DateTime("{$selDate} {$seg['end_time']}");
        $limit    = (clone $endSched)->sub($durInt);

        while ($cursor <= $limit) {
            $s = clone $cursor;
            $e = (clone $cursor)->add($durInt);
            $free = true;
            foreach ($busy as $b) {
                $bS = new DateTime($b['start_datetime']);
                $bE = new DateTime($b['end_datetime']);
                if (!($e <= $bS || $s >= $bE)) { $free = false; break; }
            }
            if ($free) {
                $timeslots[] = $s->format('H:i');
            }
            $cursor->add($stepInt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Панель управления — Добавить запись</title>
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
    <h1>Добавить запись</h1>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Кнопка «Отмена» всегда видна -->
    <div class="buttons-row">
      <a href="appointments.php" class="btn btn-secondary">Отмена</a>
    </div>

    <!-- Шаги через GET -->
    <form id="step-form" method="get" class="step-form">
      <label for="client">1. Клиент</label>
      <select id="client" name="client_id" onchange="this.form.submit()">
        <option value="">— выберите —</option>
        <?php foreach($clients as $c): ?>
          <option value="<?=$c['id']?>" <?= $c['id']==$selClient ? 'selected' : ''?>>
            <?= htmlspecialchars($c['name'])?>
          </option>
        <?php endforeach; ?>
      </select>

      <?php if ($selClient): ?>
        <label for="master">2. Мастер</label>
        <select id="master" name="master_id" onchange="this.form.submit()">
          <option value="">— выберите —</option>
          <?php foreach($masters as $m): ?>
            <option value="<?=$m['id']?>" <?= $m['id']==$selMaster ? 'selected' : ''?>>
              <?= htmlspecialchars($m['name'])?>
            </option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>

      <?php if ($selMaster): ?>
        <label for="service">3. Услуга</label>
        <select id="service" name="service_id" onchange="this.form.submit()">
          <option value="">— выберите —</option>
          <?php foreach($services as $s): ?>
            <option value="<?=$s['id']?>" <?= $s['id']==$selService ? 'selected' : ''?>>
              <?= htmlspecialchars($s['title'])?> (<?=$s['duration_minutes']?> мин)
            </option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>

      <?php if ($selService): ?>
        <label for="date">4. Дата</label>
        <input
          id="date"
          name="date"
          type="date"
          value="<?= htmlspecialchars($selDate)?>"
          min="<?= date('Y-m-d')?>"
          onchange="this.form.submit()"
        />
      <?php endif; ?>
    </form>

    <!-- Финальный POST -->
    <?php if ($selClient && $selMaster && $selService && $selDate): ?>
      <form id="final-form" method="post" class="step-form">
        <input type="hidden" name="client_id"  value="<?=$selClient?>">
        <input type="hidden" name="master_id"  value="<?=$selMaster?>">
        <input type="hidden" name="service_id" value="<?=$selService?>">
        <input type="hidden" name="date"       value="<?=$selDate?>">

        <label for="time">5. Время</label>
        <select id="time" name="time">
          <option value="">— выберите —</option>
          <?php foreach($timeslots as $t): ?>
            <option><?= htmlspecialchars($t) ?></option>
          <?php endforeach; ?>
        </select>

        <div class="buttons-row">
          <button
            id="create-button"
            type="submit"
            class="btn btn-primary"
            disabled
          >
            Создать запись
          </button>
        </div>
      </form>
    <?php endif; ?>

  </main>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const timeSelect   = document.getElementById('time');
      const createButton = document.getElementById('create-button');
      if (timeSelect && timeSelect.options.length > 1) {
        createButton.disabled = false;
      }
      if (createButton) {
        document.getElementById('final-form')?.addEventListener('submit', e => {
          if (!timeSelect.value) {
            alert('Пожалуйста, выберите время.');
            e.preventDefault();
          }
        });
      }
    });
  </script>
</body>
</html>