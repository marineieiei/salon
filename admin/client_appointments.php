<?php
// salon/admin/client_appointments.php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(1);

// — 1) Получаем ID клиента, иначе возвращаемся
$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
if (!$client_id) {
    header('Location: clients.php');
    exit;
}

// — 2) Подтягиваем информацию о клиенте
$stmtC = $pdo->prepare("SELECT name, phone FROM users WHERE id = ? AND role_id = 3");
$stmtC->execute([$client_id]);
$client = $stmtC->fetch(PDO::FETCH_ASSOC);
if (!$client) {
    header('Location: clients.php');
    exit;
}

// — 3) Фильтр и поиск
$filter = $_GET['filter'] ?? 'month';
$q      = trim($_GET['q'] ?? '');

// — 4) Расчет диапазона дат
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
    $end   = (new DateTime('first day of this month'))->modify('+3 months')->modify('-1 day');
    break;
  default:
    $start = new DateTime('first day of this month');
    $end   = new DateTime('last day of this month');
}
$end->setTime(23,59,59);

// — 5) Выбираем записи клиента
$sql = "
  SELECT 
    a.start_datetime,
    s.title    AS service,
    u.name     AS master,
    u.phone    AS master_phone
  FROM appointments a
  JOIN services s ON a.service_id = s.id
  JOIN users    u ON a.master_id   = u.id
  WHERE a.client_id = ?
    AND a.start_datetime BETWEEN ? AND ?
    AND a.status != 'cancel'
";
$params = [
  $client_id,
  $start->format('Y-m-d H:i:s'),
  $end  ->format('Y-m-d H:i:s'),
];
if ($q !== '') {
  $sql .= " AND (s.title LIKE ? OR u.name LIKE ?)";
  $like = "%{$q}%";
  $params[] = $like;
  $params[] = $like;
}
$sql .= " ORDER BY a.start_datetime";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// — 6) Группируем по дате
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
  <title>Панель управления — Записи клиента</title>
  <link rel="stylesheet" href="/salon/css/variables.css"/>
  <link rel="stylesheet" href="/salon/css/base.css"/>
  <link rel="stylesheet" href="/salon/css/theme.css"/>
  <link rel="stylesheet" href="/salon/css/pages/master/appointments.css"/>
</head>
<body>
  <header>
    <nav class="admin-nav">
      <a href="/salon/admin/dashboard.php">Панель управления</a> |
      <a href="/salon/admin/masters.php">Мастера</a> |
      <a href="/salon/admin/clients.php" class="active">Клиенты</a> |
      <a href="/salon/admin/services.php">Услуги</a> |
      <a href="/salon/admin/appointments.php">Записи</a> |
      <a href="/salon/admin/settings.php">Настройки</a> |
      <a href="/salon/index.html">Выйти</a>
    </nav>
  </header>


  <main class="container">
    <h1>Записи клиента: <?= htmlspecialchars($client['name']) ?></h1>

    <div class="appointments-actions">
      <form method="get" class="search-filter">
        <input type="hidden" name="client_id" value="<?= $client_id ?>">
        <input
          type="text"
          name="q"
          placeholder="Поиск услуги или мастера"
          value="<?= htmlspecialchars($q) ?>"
        />
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
      <p class="no-records">Записи не найдены.</p>
    <?php else: ?>
      <table class="appointments-table">
        <?php
        setlocale(LC_TIME,'ru_RU.UTF-8');
        $todayKey = (new DateTime('today'))->format('Y-m-d');
        foreach ($groups as $day => $list):
          $d = new DateTime($day);
          $weekday = mb_convert_case(strftime('%A',$d->getTimestamp()), MB_CASE_TITLE, "UTF-8");
          if ($day === $todayKey) $weekday .= ' – сегодня';
        ?>
        <tbody class="day-group">
          <tr class="day-header">
            <td colspan="4"><?= htmlspecialchars($weekday) ?></td>
          </tr>
          <?php foreach ($list as $a):
            $dt     = new DateTime($a['start_datetime']);
            $dayKey = $dt->format('Y-m-d');
            if      ($dayKey <  $todayKey) $cls='past';
            elseif  ($dayKey === $todayKey) $cls='today';
            else                             $cls='future';
          ?>
          <tr class="<?= $cls ?>">
            <td><?= $dt->format('d.m.Y') ?></td>
            <td><?= $dt->format('H:i')   ?></td>
            <td><?= htmlspecialchars($a['service']) ?></td>
            <td>
              <?= htmlspecialchars($a['master']) ?>
              (<?= htmlspecialchars($a['master_phone']) ?>)
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