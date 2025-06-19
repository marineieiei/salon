<?php
// salon/admin/settings.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(1);

$error           = '';
$success         = '';
$admin_error     = '';
$admin_success   = '';

// --- 1) Убедимся, что таблица settings существует ---
$pdo->exec(
  "CREATE TABLE IF NOT EXISTS settings (
     `key` VARCHAR(50) PRIMARY KEY,
     `value` TEXT NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
);

// --- 2) Загрузка настроек (с дефолтами) ---
$defaults = [
  'site_name'     => 'PureMani Studio',
  'address'       => 'Кишинёв, ул. Примэрий 1',
  'contact_phone' => '0XXXXXXXXX',
];
$stmt = $pdo->prepare(
  "SELECT `key`,`value` FROM settings WHERE `key` IN ('site_name','address','contact_phone')"
);
$stmt->execute();
$cfg = $defaults;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $cfg[$row['key']] = $row['value'];
}

// --- 3) Сохранение общих настроек ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $site_name     = trim($_POST['site_name'] ?? '');
    $address       = trim($_POST['address'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');

    if ($site_name === '' || $address === '' || !preg_match('/^0\d{8}$/', $contact_phone)) {
        $error = 'Заполните все поля и внесите телефон в формате 0XXXXXXXXX.';
    } else {
        $upd = $pdo->prepare(
          "INSERT INTO settings (`key`,`value`) VALUES
            ('site_name',:site_name),
            ('address',:address),
            ('contact_phone',:contact_phone)
           ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
        );
        $upd->execute([':site_name'=>$site_name, ':address'=>$address, ':contact_phone'=>$contact_phone]);
        $success = 'Настройки успешно сохранены.';
        $cfg = ['site_name'=>$site_name, 'address'=>$address, 'contact_phone'=>$contact_phone];
    }
}

// --- 4) Удаление администратора ---
if (isset($_GET['delete_admin'])) {
    $delId = (int)$_GET['delete_admin'];
    if ($delId === $_SESSION['user']['id']) {
        $admin_error = 'Нельзя удалить самого себя.';
    } else {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id=? AND role_id=1");
        $chk->execute([$delId]);
        if ($chk->fetchColumn()) {
            $del = $pdo->prepare("DELETE FROM users WHERE id=? AND role_id=1");
            $del->execute([$delId]);
            $admin_success = 'Администратор удалён.';
        } else {
            $admin_error = 'Администратор не найден.';
        }
    }
}

// --- 5) Добавление администратора ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $name  = trim($_POST['admin_name'] ?? '');
    $phone = trim($_POST['admin_phone'] ?? '');
    $pass  = trim($_POST['admin_pass'] ?? '');

    if ($name === '') {
        $admin_error = 'Укажите имя администратора.';
    } elseif (!preg_match('/^0\d{8}$/', $phone)) {
        $admin_error = 'Телефон должен быть в формате 0XXXXXXXXX.';
    } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/', $pass)) {
        $admin_error = 'Пароль минимум 8 символов, латиница и цифры.';
    } else {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE phone=?");
        $chk->execute([$phone]);
        if ($chk->fetchColumn()) {
            $admin_error = 'Пользователь с таким телефоном уже существует.';
        } else {
            $ins = $pdo->prepare(
              "INSERT INTO users (name,phone,password,role_id) VALUES (?,?,?,1)"
            );
            $ins->execute([$name, $phone, password_hash($pass, PASSWORD_DEFAULT)]);
            $admin_success = 'Новый администратор добавлен.';
        }
    }
}

// --- 6) Загрузка администраторов ---
$admins = $pdo->query("SELECT id,name,phone FROM users WHERE role_id=1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Настройки — Admin</title>
  <link rel="stylesheet" href="/salon/css/variables.css"/>
  <link rel="stylesheet" href="/salon/css/base.css"/>
  <link rel="stylesheet" href="/salon/css/theme.css"/>
  <link rel="stylesheet" href="/salon/css/pages/admin/settings.css"/>
</head>
<body>
  <header>
    <nav class="admin-nav">
      <a href="dashboard.php">Панель управления</a> |
      <a href="masters.php">Мастера</a> |
      <a href="clients.php">Клиенты</a> |
      <a href="services.php">Услуги</a> |
      <a href="appointments.php">Записи</a> |
      <a href="settings.php" class="active">Настройки</a> |
      <a href="../index.html">Выйти</a>
    </nav>
  </header>

  <main class="container settings-page">
    <h1>Настройки</h1>

    <?php if ($success): ?>
      <div class="alert alert-success"><?=htmlspecialchars($success)?></div>
    <?php elseif ($error): ?>
      <div class="alert alert-error"><?=htmlspecialchars($error)?></div>
    <?php endif; ?>

    <form method="post" class="settings-form">
      <input type="hidden" name="save_settings" value="1"/>
      <label>Название салона
        <input type="text" name="site_name" value="<?=htmlspecialchars($cfg['site_name'])?>" required/>
      </label>
      <label>Адрес
        <textarea name="address" rows="2" required><?=htmlspecialchars($cfg['address'])?></textarea>
      </label>
      <label>Контактный телефон
        <input type="tel" name="contact_phone" placeholder="0XXXXXXXXX" value="<?=htmlspecialchars($cfg['contact_phone'])?>" required/>
      </label>
      <div class="buttons-row">
        <button type="submit" class="btn btn-primary">Отредактировать</button>
      </div>
    </form>

    <hr style="margin:2rem 0">
    <h2>Администраторы</h2>
    <?php if ($admin_success): ?>
      <div class="alert alert-success"><?=htmlspecialchars($admin_success)?></div>
    <?php elseif ($admin_error): ?>
      <div class="alert alert-error"><?=htmlspecialchars($admin_error)?></div>
    <?php endif; ?>

    <!-- Таблица админов на всю ширину -->
    <table class="masters-table" style="width:100%; margin-bottom:1.5rem;">
      <thead><tr><th>Телефон</th><th>Имя</th><th>Действия</th></tr></thead>
      <tbody>
        <?php foreach($admins as $a): ?>
        <tr>
          <td><?=htmlspecialchars($a['phone'])?></td>
          <td><?=htmlspecialchars($a['name'])?></td>
          <td>
            <?php if($a['id']!==$_SESSION['user']['id']): ?>
              <a href="?delete_admin=<?=$a['id']?>" class="btn-action" onclick="return confirm('Удалить администратора <?=htmlspecialchars($a['name'])?>?')">🗑️</a>
            <?php else: ?>—<?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Кнопка и форма добавления админа -->
    <button id="showAddAdminBtn" class="btn btn-primary">Добавить администратора</button>
    <div id="addAdminForm" style="display:<?= $admin_error ? 'block' : 'none'; ?>; margin-top:1rem;">
      <form method="post" class="settings-form">
        <input type="hidden" name="add_admin" value="1"/>
        <label>Имя администратора
          <input type="text" name="admin_name" placeholder="Иван Иванов" required/>
        </label>
        <label>Телефон нового администратора
          <input type="tel" name="admin_phone" placeholder="0XXXXXXXXX" required/>
        </label>
        <label>Пароль
          <input type="password" name="admin_pass" required/>
          <small>Минимум 8 символов, латиница и цифры</small>
        </label>
        <div class="buttons-row">
          <button type="submit" class="btn btn-primary">Добавить администратора</button>
        </div>
      </form>
    </div>
    <script>
      document.getElementById('showAddAdminBtn').addEventListener('click', function(){
        document.getElementById('addAdminForm').style.display='block';
        this.style.display='none';
      });
    </script>
  </main>
</body>
</html>
