<?php
include_once 'db_config.php';

$email = trim($_GET['email1'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo 'false';
    exit;
}

$stmt = mysqli_prepare($con, "SELECT id FROM register WHERE email = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

echo ($res && mysqli_num_rows($res) === 1) ? 'true' : 'false';
