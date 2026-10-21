<?php
session_start();
include 'db_config.php';
require_once __DIR__ . '/payment_gateway.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$pid = $_GET['pid'] ?? '';
$pending_map = $_SESSION['pending_gateway_payments'] ?? [];
$pending = (is_array($pending_map) && isset($pending_map[$pid])) ? $pending_map[$pid] : null;

if (!is_array($pending) || (int)($pending['user_id'] ?? 0) !== $user_id) {
    include 'includes/header.php';
    echo "<div class='container my-5'><div class='alert alert-danger'>Payment session not found or expired.</div></div>";
    include 'includes/footer.php';
    exit();
}

if (!is_razorpay_configured()) {
    include 'includes/header.php';
    echo "<div class='container my-5'><div class='alert alert-warning'>Razorpay keys missing. Set RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET in your server environment.</div></div>";
    include 'includes/footer.php';
    exit();
}

$user_query = mysqli_query($con, "SELECT name, email, mobile FROM register WHERE id=$user_id LIMIT 1");
$user = $user_query ? mysqli_fetch_assoc($user_query) : ['name' => '', 'email' => '', 'mobile' => ''];

$cfg = get_razorpay_config();
$amount_paise = (int)($pending['amount_paise'] ?? 0);
$amount_display = (string)($pending['amount_display'] ?? '0.00');
$gateway_order_id = (string)($pending['gateway_order_id'] ?? '');
?>
<?php include 'includes/header.php'; ?>

<div class="container my-5">
    <div class="card shadow-sm border-0 rounded-4 p-4">
        <h4 class="mb-2">Complete Payment</h4>
        <p class="text-muted mb-4">Amount: <strong>Rs. <?= htmlspecialchars($amount_display); ?></strong></p>
        <button id="rzpPayBtn" class="btn btn-primary rounded-pill px-4">Pay Securely</button>
        <a href="payment_cancel.php?pid=<?= urlencode($pid); ?>" class="btn btn-link">Cancel</a>
    </div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const options = {
        key: <?= json_encode($cfg['key_id']); ?>,
        amount: <?= json_encode($amount_paise); ?>,
        currency: "INR",
        name: "Elysian MegaKart",
        description: "Order Payment",
        order_id: <?= json_encode($gateway_order_id); ?>,
        prefill: {
            name: <?= json_encode((string)($user['name'] ?? '')); ?>,
            email: <?= json_encode((string)($user['email'] ?? '')); ?>,
            contact: <?= json_encode((string)($user['mobile'] ?? '')); ?>
        },
        notes: {
            payment_session: <?= json_encode($pid); ?>
        },
        theme: {
            color: "#0077ff"
        },
        handler: function (response) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'payment_success.php';

            const fields = {
                pid: <?= json_encode($pid); ?>,
                razorpay_payment_id: response.razorpay_payment_id || '',
                razorpay_order_id: response.razorpay_order_id || '',
                razorpay_signature: response.razorpay_signature || ''
            };

            Object.keys(fields).forEach((key) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = fields[key];
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
        },
        modal: {
            ondismiss: function () {
                window.location.href = 'payment_cancel.php?pid=' + encodeURIComponent(<?= json_encode($pid); ?>);
            }
        }
    };

    const rzp = new Razorpay(options);
    const btn = document.getElementById('rzpPayBtn');
    if (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            rzp.open();
        });
        rzp.open();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
