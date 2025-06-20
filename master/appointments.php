<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(2);

$filter = $_GET['filter'] ?? 'month';
$q      = trim($_GET['q'] ?? '');

switch ($filter) {
  case 'today':      $start = new DateTime('today'); $end = new DateTime('today'); break;
  case 'this_week':  $start = new DateTime('monday this week'); $end = new DateTime('sunday this week'); break;
  case 'last_week':  $start = new DateTime('monday last week'); $end = new DateTime('sunday last week'); break;
  case 'next_week':  $start = new DateTime('monday next week'); $end = new DateTime('sunday next week'); break;
  case '3months':    $start = new DateTime('first day of this month'); $end = (clone $start)->modify('+2 months')->modify('last day of this month'); break;
  default:           $start = new DateTime('first day of this month'); $end = new DateTime('last day of this month');
}
$end->setTime(23,59,59);
$sql = "
  SELECT 
    a.start_datetime,
    s.title  AS service,
    u.name   AS client_name,
    u.phone  AS client_phone,
    u.diseases AS contraindications
  FROM appointments a
  JOIN services s ON a.service_id = s.id
  JOIN users u     ON a.client_id = u.id
  WHERE a.master_id = ?
    AND a.start_datetime BETWEEN ? AND ?
    AND a.status != 'cancel'
";
$params = [
  $_SESSION['user']['id'],
  $start->format('Y-m-d H:i:s'),
  $end  ->format('Y-m-d H:i:s'),
];
if ($q !== '') {
  $sql .= " AND (s.title LIKE ? OR u.name LIKE ?)";
  $like = "%$q%";
  $params[] = $like;
  $params[] = $like;
}
$sql .= " ORDER BY a.start_datetime";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$groups = [];
foreach ($rows as $r) {
  $day = (new DateTime($r['start_datetime']))->format('Y-m-d');
  $groups[$day][] = $r;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Моё расписание</title>
  <link rel="stylesheet" href="/salon/css/variables.css"/>
  <link rel="stylesheet" href="/salon/css/normalize.css"/>
  <link rel="stylesheet" href="/salon/css/base.css"/>
  <link rel="stylesheet" href="/salon/css/theme.css"/>
  <link rel="stylesheet" href="/salon/css/pages/master/appointments.css"/>

</head>
<body>
  <header>
    <nav>
      <a href="/salon/master/about_me.php">Обо мне</a> |
      <a href="/salon/master/appointments.php" class="active">Моё расписание</a> |
      <a href="/salon/index.html">Выйти</a>
    </nav>
  </header>

  <main class="container">
    <h1>Мои записи</h1>

    <div class="appointments-actions">
      <form method="get" class="search-filter">
        <input type="text" name="q" placeholder="Поиск услуги или клиента" value="<?= htmlspecialchars($q) ?>" />
        <select name="filter" onchange="this.form.submit()">
          <option value="today"      <?= $filter==='today'      ? 'selected':'' ?>>Сегодня</option>
          <option value="this_week"  <?= $filter==='this_week'  ? 'selected':'' ?>>Эта неделя</option>
          <option value="last_week"  <?= $filter==='last_week'  ? 'selected':'' ?>>Прошлая неделя</option>
          <option value="next_week"  <?= $filter==='next_week'  ? 'selected':'' ?>>Следующая неделя</option>
          <option value="month"      <?= $filter==='month'      ? 'selected':'' ?>>Месяц</option>
          <option value="3months"    <?= $filter==='3months'    ? 'selected':'' ?>>3 месяца</option>
        </select>
      </form>
    </div>
    <?php if (empty($groups)): ?>
      <p class="no-records">Записей не найдено.</p>
    <?php else: ?>
      <table class="appointments-table">
        <thead>
          <tr>
            <th>Дата</th>
            <th>Время</th>
            <th class="wide">Услуга</th>
            <th>Клиент</th>
            <th>Телефон</th>
            <th class="wide">Противопоказания</th>
          </tr>
        </thead>
        <?php
        setlocale(LC_TIME, 'ru_RU.UTF-8');
        $today = (new DateTime('today'))->format('Y-m-d');
        foreach ($groups as $day => $list):
          $weekday = mb_convert_case(strftime('%A', strtotime($day)), MB_CASE_TITLE, 'UTF-8');
          if ($day === $today) $weekday .= ' – сегодня';
        ?>
        <tbody class="day-group">
          <tr class="day-header">
            <td colspan="6"><?= $weekday ?></td>
          </tr>
          <?php foreach ($list as $row):
            $dt = new DateTime($row['start_datetime']);
            $dayKey = $dt->format('Y-m-d');
            $cls = $dayKey < $today ? 'past' : ($dayKey === $today ? 'today' : 'future');
          ?>
          <tr class="<?= $cls ?>">
            <td><?= $dt->format('d.m.Y') ?></td>
            <td><?= $dt->format('H:i') ?></td>
            <td><?= htmlspecialchars($row['service']) ?></td>
            <td><?= htmlspecialchars($row['client_name']) ?></td>
            <td><?= htmlspecialchars($row['client_phone']) ?></td>
            <td><?= nl2br(htmlspecialchars($row['contraindications'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </main>
</body>
</html>


