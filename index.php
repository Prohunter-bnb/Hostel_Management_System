<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'admin') {
        header("Location: admin/dashboard.php");
        exit();
    } elseif ($_SESSION['user_type'] === 'school_official') {
        header("Location: dashboard/official.php");
        exit();
    } else {
        header("Location: dashboard/student.php");
        exit();
    }
}

header("Location: auth/login.php");
exit();
?>