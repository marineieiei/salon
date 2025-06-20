<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
function isLoggedIn() {
    return !empty($_SESSION['user']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /salon/login.php');
        exit;
    }
}
function requireRole($roleId) {
    requireLogin();
    if ($_SESSION['user']['role_id'] != $roleId) {
        http_response_code(403);
        echo 'Доступ запрещён';
        exit;
    }
}
?>