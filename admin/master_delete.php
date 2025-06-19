<?php
// salon/admin/master_delete.php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
requireRole(1); // только админ

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    $_SESSION['error'] = 'Некорректный ID мастера.';
    header('Location: masters.php');
    exit;
}

try {
    // 1) Проверяем, нет ли запланированных приёмов
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE master_id = ?");
    $stmt->execute([$id]);
    $cntAppointments = (int)$stmt->fetchColumn();

    if ($cntAppointments > 0) {
        $_SESSION['error'] = 'Нельзя удалить мастера: существуют записи на приём.';
    } else {
        // 2) Удаляем его расписания (schedules)
        $delSched = $pdo->prepare("DELETE FROM schedules WHERE master_id = ?");
        $delSched->execute([$id]);

        // 3) Удаляем самого мастера
        $delUser = $pdo->prepare("DELETE FROM users WHERE id = ? AND role_id = 2");
        $delUser->execute([$id]);

        if ($delUser->rowCount() > 0) {
            $_SESSION['success'] = 'Мастер успешно удалён';
        } else {
            $_SESSION['error'] = 'Мастер не найден или уже удалён.';
        }
    }
} catch (\Throwable $e) {

}

// 4) Возвращаемся на список
header('Location: masters.php');
exit;