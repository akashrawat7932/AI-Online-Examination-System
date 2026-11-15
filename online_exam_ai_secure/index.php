<?php require_once __DIR__ . '/config/config.php';
if (!empty($_SESSION['user_id'])) {
    $r = $_SESSION['role'];
    header('Location: ' . BASE_URL . '/' . ($r === 'admin' ? 'admin/dashboard.php' :
     ($r === 'teacher' ? 'teacher/dashboard.php' : 'index.php')));//change /student/dashboard.php
} else header('Location: ' . BASE_URL . 'empoly_login.php');//change index.php
exit;
