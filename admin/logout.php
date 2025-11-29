<?php
// admin/logout.php - Destroy admin session and redirect to login

session_start();
session_destroy();
header('Location: login.php');
exit;
?>