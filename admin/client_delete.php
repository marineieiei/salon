<?php
// salon/admin/client_delete.php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(1); // только админ

// 1) Получаем ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    $_SESSION['error'] = 'Некорректный ID клиента.';
    header('Location: clients.php');
    exit;
}

// 2) Проверяем, есть ли у клиента записи
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE client_id = ?");
$stmt->execute([$id]);
$count = (int)$stmt->fetchColumn();

if ($count > 0) {
    $_SESSION['error'] = 'Нельзя удалить клиента: у него есть записи.';
} else {
    // 3) Удаляем
    $del = $pdo->prepare("DELETE FROM users WHERE id = ? AND role_id = 3");
    $del->execute([$id]);
    $_SESSION['success'] = 'Клиент удалён.';
}

// 4) Возвращаемся на список
header('Location: clients.php');
exit;