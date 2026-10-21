<?php
session_start();
include 'db_config.php';
require_once __DIR__ . '/payment_gateway.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$pid = $_POST['pid'] ?? '';
$payment_id = trim((string)($_POST['razorpay_payment_id'] ?? ''));
$order_id = trim((string)($_POST['razorpay_order_id'] ?? ''));
$signature = trim((string)($_POST['razorpay_signature'] ?? ''));

$pending_map = $_SESSION['pending_gateway_payments'] ?? [];
$pending = (is_array($pending_map) && isset($pending_map[$pid])) ? $pending_map[$pid] : null;

if (!is_array($pending) || (int)($pending['user_id'] ?? 0) !== $user_id) {
    $_SESSION['flash_error'] = 'Payment session not found or expired.';
    header('Location: checkout.php?step=payment');
    exit();
}

$expected_order_id = (string)($pending['gateway_order_id'] ?? '');
if ($order_id === '' || $expected_order_id === '' || $order_id !== $expected_order_id) {
    $_SESSION['flash_error'] = 'Payment order mismatch. Please try again.';
    header('Location: checkout.php?step=payment');
    exit();
}

if (!verify_razorpay_signature($order_id, $payment_id, $signature)) {
    $_SESSION['flash_error'] = 'Payment verification failed.';
    header('Location: checkout.php?step=payment');
    exit();
}

$order_ids = $pending['order_ids'] ?? [];
if (!is_array($order_ids) || empty($order_ids)) {
    $_SESSION['flash_error'] = 'No linked orders found for this payment.';
    header('Location: checkout.php?step=payment');
    exit();
}

mysqli_begin_transaction($con);
$ok = true;
$payment_id_db = mysqli_real_escape_string($con, $payment_id);

foreach ($order_ids as $oid) {
    $oid = (int)$oid;
    if ($oid <= 0) {
        $ok = false;
        break;
    }

    $sql = "UPDATE payments
            SET payment_status='Paid', transaction_ref='RZP_PAY:$payment_id_db'
            WHERE order_id=$oid AND user_id=$user_id";
    if (!mysqli_query($con, $sql)) {
        $ok = false;
        break;
    }
}

if ($ok) {
    mysqli_commit($con);
    unset($_SESSION['pending_gateway_payments'][$pid]);
    $_SESSION['flash_success'] = 'Payment successful. Your order has been placed.';
    header('Location: my_orders.php');
    exit();
}

mysqli_rollback($con);
$_SESSION['flash_error'] = 'Payment was captured but order update failed. Please contact support.';
header('Location: my_orders.php');
exit();
