<?php
// salon/admin/service_delete.php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(1);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['error'] = 'Некорректный ID услуги.';
    header('Location: services.php');
    exit;
}

// Проверка: используется ли услуга где-либо (например, в таблице записей)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE service_id = ?");
$stmt->execute([$id]);
$isUsed = $stmt->fetchColumn() > 0;

if ($isUsed) {
    $_SESSION['error'] = 'Невозможно удалить услугу: она используется в одной или нескольких записях.';
    header('Location: services.php');
    exit;
}

// Удаление
$stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
$stmt->execute([$id]);

$_SESSION['success'] = 'Услуга успешно удалена.';
header('Location: services.php');
exit;