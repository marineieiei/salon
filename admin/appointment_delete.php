<?php
// salon/admin/appointment_delete.php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(1);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    // Если нет ID — просто возвращаемся
    header('Location: appointments.php');
    exit;
}

// Удаляем запись
$stmt = $pdo->prepare("DELETE FROM appointments WHERE id = ?");
$stmt->execute([$id]);

// Сохраняем сообщение об успехе и возвращаемся
$_SESSION['success'] = 'Запись успешно удалена.';
header('Location: appointments.php');
exit;