<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Detect whether order_status column exists
$has_status_column = false;
$status_col_query = mysqli_query($con, "SHOW COLUMNS FROM orders LIKE 'order_status'");
if ($status_col_query && mysqli_num_rows($status_col_query) > 0) {
    $has_status_column = true;
}

$status_select = $has_status_column
    ? "COALESCE(o.order_status, 'Pending') AS order_status"
    : "'Pending' AS order_status";

$orders_query = mysqli_query($con, "
    SELECT o.id, o.quantity, o.size, o.total_price, o.address, o.city, o.state, o.zip, o.created_at,
           $status_select,
           p.name AS product_name, p.image AS product_image, p.brand AS product_brand,
           COALESCE(pm.payment_method, 'cod') AS payment_method,
           COALESCE(pm.payment_status, 'Pending') AS payment_status,
           pm.transaction_ref
    FROM orders o
    JOIN products p ON o.product_id = p.id
    LEFT JOIN payments pm ON pm.order_id = o.id
    WHERE o.user_id = $user_id
    ORDER BY o.created_at DESC
");

$orders = [];
$status_counts = ['Pending' => 0, 'Shipped' => 0, 'Delivered' => 0];

if ($orders_query) {
    while ($row = mysqli_fetch_assoc($orders_query)) {
        $status_raw = trim($row['order_status']);
        $status_lower = strtolower($status_raw);
        $status = in_array($status_lower, ['pending', 'shipped', 'delivered'], true)
            ? ucfirst($status_lower)
            : 'Pending';
        $row['order_status'] = $status;
        // Show delivered for COD payments once the order itself is delivered
        $row['display_payment_status'] = ($status === 'Delivered' && strtolower($row['payment_method']) === 'cod')
            ? 'Delivered'
            : $row['payment_status'];
        $status_counts[$status]++;
        $orders[] = $row;
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="orders-page py-4">
    <div class="container">
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']); ?></div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']); ?></div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <div class="orders-hero mb-4">
            <h2>My Orders</h2>
            <p>Track all your orders with real-time delivery status.</p>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="orders-stat-card">
                    <span>Pending</span>
                    <strong><?= (int) $status_counts['Pending']; ?></strong>
                </div>
            </div>
            <div class="col-md-4">
                <div class="orders-stat-card">
                    <span>Shipped</span>
                    <strong><?= (int) $status_counts['Shipped']; ?></strong>
                </div>
            </div>
            <div class="col-md-4">
                <div class="orders-stat-card">
                    <span>Delivered</span>
                    <strong><?= (int) $status_counts['Delivered']; ?></strong>
                </div>
            </div>
        </div>

        <?php if (!empty($orders)): ?>
            <div class="orders-list">
                <?php foreach ($orders as $order): ?>
                    <?php
                    $status = $order['order_status'];
                    $step_no = ($status === 'Delivered') ? 3 : (($status === 'Shipped') ? 2 : 1);
                    ?>
                    <div class="order-track-card">
                        <div class="order-track-head">
                            <div>
                                <h6 class="mb-1">Order #<?= (int) $order['id']; ?></h6>
                                <small class="text-muted">Placed on <?= date('d M Y, h:i A', strtotime($order['created_at'])); ?></small>
                            </div>
                            <span class="order-status-badge order-status-<?= strtolower($status); ?>"><?= htmlspecialchars($status); ?></span>
                        </div>

                        <div class="order-track-body">
                            <div class="order-product">
                                <img src="img/<?= htmlspecialchars($order['product_image']); ?>" alt="<?= htmlspecialchars($order['product_name']); ?>" class="order-product-img">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($order['product_name']); ?></h6>
                                    <p class="mb-1 text-muted"><?= htmlspecialchars($order['product_brand']); ?></p>
                                    <?php if (!empty($order['size'])): ?>
                                        <p class="mb-1 small">Size: <strong><?= htmlspecialchars($order['size']); ?></strong></p>
                                    <?php endif; ?>
                                    <p class="mb-1 small">Qty: <strong><?= (int) $order['quantity']; ?></strong></p>
                                    <p class="mb-0 fw-bold">$<?= number_format((float) $order['total_price'], 2); ?></p>
                                </div>
                            </div>

                            <div class="order-timeline">
                                <div class="timeline-step <?= $step_no >= 1 ? 'active' : ''; ?>">
                                    <span></span>
                                    <p>Pending</p>
                                </div>
                                <div class="timeline-line <?= $step_no >= 2 ? 'active' : ''; ?>"></div>
                                <div class="timeline-step <?= $step_no >= 2 ? 'active' : ''; ?>">
                                    <span></span>
                                    <p>Shipped</p>
                                </div>
                                <div class="timeline-line <?= $step_no >= 3 ? 'active' : ''; ?>"></div>
                                <div class="timeline-step <?= $step_no >= 3 ? 'active' : ''; ?>">
                                    <span></span>
                                    <p>Delivered</p>
                                </div>
                            </div>

                            <div class="order-address">
                                <h6 class="mb-1">Delivery Address</h6>
                                <p class="mb-0">
                                    <?= htmlspecialchars($order['address']); ?>,
                                    <?= htmlspecialchars($order['city']); ?>,
                                    <?= htmlspecialchars($order['state']); ?> -
                                    <?= htmlspecialchars($order['zip']); ?>
                                </p>
                            </div>

                            <div class="order-address mt-3">
                                <h6 class="mb-1">Payment</h6>
                                <p class="mb-0">
                                    Method: <strong><?= htmlspecialchars(strtoupper($order['payment_method'])); ?></strong>
                                    | Status: <strong><?= htmlspecialchars($order['display_payment_status']); ?></strong>
                                    <?php if (!empty($order['transaction_ref'])): ?>
                                        | Ref: <strong><?= htmlspecialchars($order['transaction_ref']); ?></strong>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="account-empty">No orders found yet. Start shopping now.</div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
