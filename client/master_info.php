<?php
// client/master_info.php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// id мастера из GET
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT name, phone, photo_path FROM users WHERE id = ? AND role_id = 2");
$stmt->execute([$id]);
$m = $stmt->fetch();
if (!$m) {
  http_response_code(404);
  exit('Мастер не найден');
}

// отдаём фрагмент HTML
?>
<div class="master-card">
  <?php if ($m['photo_path']): ?>
    <img src="/salon/<?= htmlspecialchars($m['photo_path']) ?>" alt="<?= htmlspecialchars($m['name']) ?>">
  <?php endif; ?>
  <h2><?= htmlspecialchars($m['name']) ?></h2>
  <p>Телефон: <?= htmlspecialchars($m['phone']) ?></p>
  <!-- можете добавить ещё поля -->
</div>