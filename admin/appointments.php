<?php
// salon/admin/appointments.php

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(1);

// 0) Flash-сообщение
$notice = $_SESSION['success'] ?? $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// 1) Параметры фильтрации и поиска
$filter = $_GET['filter'] ?? 'month';
$q      = trim($_GET['q'] ?? '');

// 2) Расчёт диапазона по фильтру (кроме «all»)
if ($filter !== 'all') {
    switch ($filter) {
      case 'today':
        $start = new DateTime('today');
        $end   = new DateTime('today');
        break;
      case 'this_week':
        $start = new DateTime('monday this week');
        $end   = new DateTime('sunday this week');
        break;
      case 'last_week':
        $start = new DateTime('monday last week');
        $end   = new DateTime('sunday last week');
        break;
      case 'next_week':
        $start = new DateTime('monday next week');
        $end   = new DateTime('sunday next week');
        break;
      case '3months':
        $start = new DateTime('first day of this month');
        $end   = (new DateTime('first day of this month'))->modify('+2 months')->modify('last day of this month');
        break;
      case 'month':
      default:
        $start = new DateTime('first day of this month');
        $end   = new DateTime('last day of this month');
        break;
    }
    $end->setTime(23,59,59);
}

// 3) Построение запроса
$sql    = "
  SELECT
    a.id,
    a.start_datetime,
    s.title    AS service,
    s.price    AS price,
    c.name     AS client,
    c.id       AS client_id,
    u.name     AS master,
    u.id       AS master_id
  FROM appointments a
  JOIN services s ON a.service_id = s.id
  JOIN users    u ON a.master_id   = u.id
  JOIN users    c ON a.client_id   = c.id
";
$params = [];

// Условие фильтрации по дате
if ($filter !== 'all') {
    $sql .= " WHERE a.start_datetime BETWEEN ? AND ?";
    $params[] = $start->format('Y-m-d H:i:s');
    $params[] = $end->format('Y-m-d H:i:s');
    if ($q !== '') {
        $sql .= " AND (s.title LIKE ? OR u.name LIKE ? OR c.name LIKE ?)";
        $like = "%{$q}%";
        $params = array_merge($params, [$like, $like, $like]);
    }
} else {
    if ($q !== '') {
        $sql .= " WHERE (s.title LIKE ? OR u.name LIKE ? OR c.name LIKE ?)";
        $like = "%{$q}%";
        $params = [$like, $like, $like];
    }
}

$sql .= " ORDER BY a.start_datetime";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4) Группировка по дате
$groups = [];
foreach ($rows as $r) {
  $day = (new DateTime($r['start_datetime']))->format('Y-m-d');
  $groups[$day][] = $r;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Панель управления — Записи</title>
  <link rel="stylesheet" href="/salon/css/variables.css"/>
  <link rel="stylesheet" href="/salon/css/base.css"/>
  <link rel="stylesheet" href="/salon/css/theme.css"/>
  <link rel="stylesheet" href="/salon/css/pages/master/appointments.css"/>
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

  <main class="container">
    <h1>Все записи</h1>

    <div class="appointments-actions">
      <a href="appointment_add.php" class="btn btn-primary">＋ Добавить запись</a>
      <form method="get" class="search-filter">
        <input
          type="text"
          name="q"
          placeholder="Поиск услуги, мастера или клиента"
          value="<?= htmlspecialchars($q) ?>"
        />
        <select name="filter" onchange="this.form.submit()">
          <option value="all"     <?= $filter==='all'     ? 'selected':'' ?>>За всё время</option>
          <option value="today"   <?= $filter==='today'   ? 'selected':'' ?>>Сегодня</option>
          <option value="this_week" <?= $filter==='this_week' ? 'selected':'' ?>>Эта неделя</option>
          <option value="last_week" <?= $filter==='last_week' ? 'selected':'' ?>>Прошлая неделя</option>
          <option value="next_week" <?= $filter==='next_week' ? 'selected':'' ?>>Следующая неделя</option>
          <option value="month"   <?= $filter==='month'   ? 'selected':'' ?>>Месяц</option>
          <option value="3months" <?= $filter==='3months' ? 'selected':'' ?>>3 месяца</option>
        </select>
      </form>
    </div>

    <?php if ($notice): ?>
      <div class="alert alert-success"><?= htmlspecialchars($notice) ?></div>
    <?php endif; ?>

    <?php if (empty($groups)): ?>
      <p class="no-records">Записей не найдено.</p>
    <?php else: ?>
      <table class="appointments-table">
        <?php
          setlocale(LC_TIME,'ru_RU.UTF-8');
          $todayKey = (new DateTime('today'))->format('Y-m-d');
          foreach ($groups as $day => $list):
            $d = new DateTime($day);
            $weekday = mb_convert_case(strftime('%A',$d->getTimestamp()), MB_CASE_TITLE, "UTF-8");
            if ($day === $todayKey) $weekday .= ' — сегодня';
        ?>
        <tbody class="day-group">
          <tr class="day-header">
            <td colspan="7"><?= htmlspecialchars($weekday) ?></td>
          </tr>
          <?php foreach ($list as $a):
            $dt     = new DateTime($a['start_datetime']);
            $dayKey = $dt->format('Y-m-d');
            $cls    = $dayKey <  $todayKey ? 'past'
                    : ($dayKey === $todayKey ? 'today' : 'future');
          ?>
          <tr class="<?= $cls ?>">
            <td><?= $dt->format('d.m.Y') ?></td>
            <td><?= $dt->format('H:i')   ?></td>
            <td><?= htmlspecialchars($a['service']) ?></td>
            <td><?= htmlspecialchars($a['price'])   ?> MDL</td>
            <td><?= htmlspecialchars($a['client'])  ?></td>
            <td><?= htmlspecialchars($a['master'])  ?></td>
            <td class="actions">
              <?php if ($cls === 'future'): ?>
                <a href="appointment_form.php?id=<?= $a['id'] ?>" class="btn-action">✎</a>
                <a
                  href="appointment_delete.php?id=<?= $a['id'] ?>"
                  class="btn-action"
                  onclick="return confirm('Вы действительно хотите удалить эту запись?')"
                >🗑️</a>
              <?php else: ?>
                &mdash;
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </main>
</body>
</html>