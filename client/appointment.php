<?php
// client/appointment.php

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(3);

// 1) фильтр и поиск
$filter = $_GET['filter'] ?? 'month';
$q      = trim($_GET['q'] ?? '');

// 2) рассчитываем диапазон дат
switch ($filter) {
  case 'today':
    $start = new DateTime('today'); $end = new DateTime('today'); break;
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
  default: // month
    $start = new DateTime('first day of this month');
    $end   = new DateTime('last day of this month');
}
$end->setTime(23,59,59);

// 3) выборка (теперь с ценой)
$sql = "
  SELECT 
    a.id,
    a.start_datetime,
    s.title   AS service,
    s.price   AS price,
    u.name    AS master,
    u.id      AS master_id
  FROM appointments a
  JOIN services s ON a.service_id = s.id
  JOIN users    u ON a.master_id   = u.id
  WHERE a.client_id = ?
    AND a.start_datetime BETWEEN ? AND ?
";
$params = [
  $_SESSION['user']['id'],
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

// 4) группируем по дате
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
  <title>PureMani Studio — Мои записи</title>

  <link rel="stylesheet" href="/salon/css/variables.css"/>
  <link rel="stylesheet" href="/salon/css/base.css"/>
  <link rel="stylesheet" href="/salon/css/theme.css"/>
  <link rel="stylesheet" href="/salon/css/pages/client/appointments.css"/>
</head>
<body>

  <header>
    <nav>
      <a href="/salon/client/about_me.php">Обо мне</a> |
      <a href="/salon/client/appointment.php" class="active">Мои записи</a> |
      <a href="/salon/index.html">Выйти</a>
    </nav>
  </header>

  <main class="container">
    <div class="appointments-actions">
      <a href="new.php" class="btn btn-primary">
        <span class="icon">＋</span> Добавить запись
      </a>
      <form method="get" class="search-filter">
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
            <td colspan="6"><?= htmlspecialchars($weekday) ?></td>
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
            <td><?= htmlspecialchars($a['price']) ?> MDL</td>
            <td>
              <a href="#" class="master-link" data-id="<?= $a['master_id'] ?>">
                <?= htmlspecialchars($a['master']) ?>
              </a>
            </td>
            <td class="actions">
              <?php if ($cls==='today' || $cls==='future'): ?>
                <a href="edit.php?id=<?= $a['id'] ?>" class="btn-action">✎</a>
                <a
                  href="delete.php?id=<?= $a['id'] ?>"
                  class="btn-action"
                  onclick="return confirm('Удалить эту запись?')"
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

    <!-- Модалка мастера -->
    <div id="master-modal" class="modal">
      <div class="modal-content">
        <button class="modal-close">&times;</button>
        <div class="modal-body"></div>
      </div>
    </div>
  </main>


  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('master-modal');
    const body  = modal.querySelector('.modal-body');
    const close = modal.querySelector('.modal-close');

    document.querySelectorAll('.master-link').forEach(el => {
      el.addEventListener('click', e => {
        e.preventDefault();
        fetch(`/salon/client/master_info.php?id=${el.dataset.id}`)
          .then(r => r.text())
          .then(html => {
            body.innerHTML = html;
            modal.classList.add('show');
          });
      });
    });

    close.addEventListener('click', () => modal.classList.remove('show'));
    modal.addEventListener('click', e => {
      if (e.target === modal) modal.classList.remove('show');
    });
  });
  </script>
</body>
</html>