<?php
session_start();

unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_role']);
$_SESSION['is_guest'] = true;
$_SESSION['user_name'] = 'Guest';
$_SESSION['user_role'] = 'guest';

header('Location: index.php');
exit();
?>
