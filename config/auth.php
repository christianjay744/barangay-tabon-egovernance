<?php
session_start();

function require_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}
function require_admin() {
    require_login();
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        exit('Admin access only.');
    }
}
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
