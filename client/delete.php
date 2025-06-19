<?php
// client/delete.php

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(3);

$id   = isset($_GET['id'])   ? (int)$_GET['id']   : 0;
$from = isset($_GET['from']) ? basename($_GET['from']) : 'appointment.php';

// 1) Найдём запись и проверим, что она будущая, и что она принадлежит текущему клиенту
$stmt = $pdo->prepare("
  SELECT start_datetime 
    FROM appointments 
   WHERE id = ? 
     AND client_id = ?
");
$stmt->execute([$id, $_SESSION['user']['id']]);
$appt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appt) {
    // либо не найдено, либо не ваше
    header("Location: {$from}?error=notfound");
    exit;
}

$dt = new DateTime($appt['start_datetime']);
$today = new DateTime('today');
if ($dt <= $today) {
    // нельзя удалять сегодня или в прошлом
    header("Location: {$from}?error=cannot_delete_past");
    exit;
}

// 2) Удаляем
$del = $pdo->prepare("DELETE FROM appointments WHERE id = ?");
$del->execute([$id]);

// 3) Редиректим обратно с флагом
header("Location: {$from}?deleted=1");
exit;