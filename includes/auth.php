<?php
// includes/auth.php — функции авторизации и защиты страниц
// Гарантируем, что сессия запущена
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Проверяет, есть ли авторизованный пользователь
function isLoggedIn() {
    return !empty($_SESSION['user']);
}

// Проверяет, что пользователь залогинен, иначе редиректит на логин
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /salon/login.php');
        exit;
    }
}

// Проверяет роль пользователя, иначе выдаёт 403
function requireRole($roleId) {
    requireLogin();
    if ($_SESSION['user']['role_id'] != $roleId) {
        http_response_code(403);
        echo 'Доступ запрещён';
        exit;
    }
}
?>