<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$pid = $_GET['pid'] ?? ($_POST['pid'] ?? '');
$pending_map = $_SESSION['pending_gateway_payments'] ?? [];
$pending = (is_array($pending_map) && isset($pending_map[$pid])) ? $pending_map[$pid] : null;

if (!is_array($pending) || (int)($pending['user_id'] ?? 0) !== $user_id) {
    $_SESSION['flash_error'] = 'Payment session not found or already closed.';
    header('Location: checkout.php?step=payment');
    exit();
}

$order_ids = $pending['order_ids'] ?? [];
$ok = true;

mysqli_begin_transaction($con);
foreach ($order_ids as $oid) {
    $oid = (int)$oid;
    if ($oid <= 0) {
        $ok = false;
        break;
    }

    $sql = "UPDATE payments
            SET payment_status='Cancelled'
            WHERE order_id=$oid AND user_id=$user_id";
    if (!mysqli_query($con, $sql)) {
        $ok = false;
        break;
    }
}

if ($ok) {
    mysqli_commit($con);
    unset($_SESSION['pending_gateway_payments'][$pid]);
    $_SESSION['flash_error'] = 'Payment cancelled.';
    header('Location: checkout.php?step=payment');
    exit();
}

mysqli_rollback($con);
$_SESSION['flash_error'] = 'Unable to cancel payment right now. Please try again.';
header('Location: checkout.php?step=payment');
exit();
