<?php
require_once __DIR__ . '/../core/init.php';

$user = new User($pdo);
$user->logout();

header('Location: login.php');
exit();
?>
