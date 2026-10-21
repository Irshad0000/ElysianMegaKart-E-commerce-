<?php
session_start();
include 'db_config.php';

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_role = $_SESSION['user_role'] ?? 'user';
if ($user_role === 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$error = '';
$success = '';
$step = $_POST['current_step'] ?? 'address';

if (isset($_SESSION['checkout_flash_error'])) {
    $error = (string)$_SESSION['checkout_flash_error'];
    unset($_SESSION['checkout_flash_error']);
}
if (isset($_SESSION['checkout_flash_success'])) {
    $success = (string)$_SESSION['checkout_flash_success'];
    unset($_SESSION['checkout_flash_success']);
}

$address = trim($_POST['address'] ?? '');
$city = trim($_POST['city'] ?? '');
$state = trim($_POST['state'] ?? '');
$zip = trim($_POST['zip'] ?? '');

$payment_method_labels = [
    'upi'        => 'UPI (GPay / PhonePe / Paytm)',
    'card'       => 'Debit / Credit Card',
    'netbanking' => 'Net Banking',
    'cod'        => 'Cash on Delivery'
];
$supported_netbank_banks = ['HDFC Bank','ICICI Bank','State Bank of India','Axis Bank','Kotak','Yes Bank','Punjab National Bank','Bank of Baroda'];

$selected_payment_method = $_POST['payment_method'] ?? 'upi';
$payment_meta = [
    'upi_id' => trim($_POST['upi_id'] ?? ''),
    'card_number' => preg_replace('/[^0-9]/', '', $_POST['card_number'] ?? ''),
    'card_expiry' => trim($_POST['card_expiry'] ?? ''),
    'card_cvv' => preg_replace('/[^0-9]/', '', $_POST['card_cvv'] ?? ''),
    'netbank_bank' => trim($_POST['netbank_bank'] ?? '')
];

function build_checkout_redirect_url(string $target_step): string {
    $query = $_GET;
    $query['step'] = $target_step;
    return basename($_SERVER['PHP_SELF']) . '?' . http_build_query($query);
}

function detect_card_brand(string $num): string {
    if ($num === '') return 'CARD';
    if (str_starts_with($num, '4')) return 'VISA';
    $two = (int)substr($num, 0, 2);
    $four = (int)substr($num, 0, 4);
    if (($two >= 51 && $two <= 55) || ($four >= 2221 && $four <= 2720)) return 'MASTERCARD';
    if (str_starts_with($num, '34') || str_starts_with($num, '37')) return 'AMEX';
    if (str_starts_with($num, '60') || str_starts_with($num, '65') || str_starts_with($num, '81') || str_starts_with($num, '82') || str_starts_with($num, '508') || str_starts_with($num, '353')) return 'RUPAY';
    return 'CARD';
}

function passes_luhn(string $num): bool {
    if ($num === '' || !ctype_digit($num)) return false;
    $sum = 0;
    $alternate = false;
    for ($i = strlen($num) - 1; $i >= 0; $i--) {
        $digit = (int)$num[$i];
        if ($alternate) {
            $digit *= 2;
            if ($digit > 9) $digit -= 9;
        }
        $sum += $digit;
        $alternate = !$alternate;
    }
    return ($sum % 10) === 0;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_SESSION['checkout_form']) && is_array($_SESSION['checkout_form'])) {
    $saved_checkout = $_SESSION['checkout_form'];
    $address = trim($saved_checkout['address'] ?? $address);
    $city = trim($saved_checkout['city'] ?? $city);
    $state = trim($saved_checkout['state'] ?? $state);
    $zip = trim($saved_checkout['zip'] ?? $zip);
    $selected_payment_method = $saved_checkout['payment_method'] ?? $selected_payment_method;
    $payment_meta = [
        'upi_id' => trim($saved_checkout['upi_id'] ?? $payment_meta['upi_id']),
        'card_number' => '',
        'card_expiry' => '',
        'card_cvv' => '',
        'netbank_bank' => trim($saved_checkout['netbank_bank'] ?? $payment_meta['netbank_bank'])
    ];
    $step = ($_GET['step'] ?? ($saved_checkout['step'] ?? $step));
}

$has_cart_table = false;
$cart_table_check = mysqli_query($con, "SHOW TABLES LIKE 'user_cart'");
if ($cart_table_check && mysqli_num_rows($cart_table_check) > 0) {
    $has_cart_table = true;
}

// Fetch user info
$user_query = mysqli_query($con, "SELECT * FROM register WHERE id='$user_id'");
$user = mysqli_fetch_assoc($user_query);

// Prepare checkout items
$checkout_items = [];

// Buy Now product
if(isset($_GET['buy'])){
    $product_id = (int)$_GET['buy'];
    $qty = isset($_GET['qty']) ? (int)$_GET['qty'] : 1;
    $size = $_GET['size'] ?? '';
    if ($product_id > 0 && $qty > 0) {
        $checkout_items[$product_id] = ['qty' => $qty, 'size' => $size];
    }
}
// Cart items
elseif($has_cart_table){
    $cart_query = mysqli_query($con, "SELECT product_id, quantity FROM user_cart WHERE user_id=$user_id");
    if($cart_query && mysqli_num_rows($cart_query) > 0){
        while($row = mysqli_fetch_assoc($cart_query)){
            $pid = (int)$row['product_id'];
            $qty = (int)$row['quantity'];
            $checkout_items[$pid] = ['qty' => $qty, 'size' => ''];
        }
    }
}
elseif(!empty($_SESSION['cart'])){
    foreach($_SESSION['cart'] as $id => $qty){
        $checkout_items[(int)$id] = ['qty' => (int)$qty, 'size' => ''];
    }
}

if (empty($checkout_items)) {
    include 'includes/header.php';
    echo "<h3 class='text-center mt-5'>Your cart is empty!</h3>";
    include 'includes/footer.php';
    exit();
}

// Pre-calc total for UI button label
$display_total = 0;
foreach ($checkout_items as $pid => $details) {
    $product_query = mysqli_query($con, "SELECT price FROM products WHERE id=".(int)$pid." LIMIT 1");
    if ($product_query && mysqli_num_rows($product_query) > 0) {
        $row = mysqli_fetch_assoc($product_query);
        $display_total += (float)$row['price'] * max(1, (int)$details['qty']);
    }
}

// Step flow
if (isset($_POST['address_next'])) {
    if ($address === '' || $city === '' || $state === '' || $zip === '') {
        $error = "Please fill all shipping fields.";
        $step = 'address';
    } else {
        $_SESSION['checkout_form'] = [
            'step' => 'payment',
            'address' => $address,
            'city' => $city,
            'state' => $state,
            'zip' => $zip,
            'payment_method' => $selected_payment_method,
            'upi_id' => $payment_meta['upi_id'],
            'netbank_bank' => $payment_meta['netbank_bank']
        ];
        header('Location: ' . build_checkout_redirect_url('payment'));
        exit();
    }
}

// If user clicks edit in payment step, go back to address
if (isset($_POST['edit_address'])) {
    $_SESSION['checkout_form'] = [
        'step' => 'address',
        'address' => $address,
        'city' => $city,
        'state' => $state,
        'zip' => $zip,
        'payment_method' => $selected_payment_method,
        'upi_id' => $payment_meta['upi_id'],
        'netbank_bank' => $payment_meta['netbank_bank']
    ];
    header('Location: ' . build_checkout_redirect_url('address'));
    exit();
}

// Handle order placement (step: payment)
if(isset($_POST['place_order'])){
    if ($address === '' || $city === '' || $state === '' || $zip === '') {
        $error = "Please fill all shipping fields.";
        $step = 'address';
    } elseif (!array_key_exists($selected_payment_method, $payment_method_labels)) {
        $error = "Invalid payment method selected.";
        $step = 'payment';
    } else {
        // Validate payment details per method
        if ($selected_payment_method === 'upi') {
            $upi_clean = str_replace(' ', '', $payment_meta['upi_id']);
            if ($upi_clean === '' || !preg_match('/^[a-zA-Z0-9\\.\\-_]{2,}@[a-zA-Z]{2,}$/', $upi_clean)) {
                $error = "Enter a valid UPI ID (example: name@bank).";
                $step = 'payment';
            }
        } elseif ($selected_payment_method === 'card') {
            $num = $payment_meta['card_number'];
            $brand = detect_card_brand($num);

            $len = strlen($num);
            $valid_len = match($brand) {
                'AMEX' => $len === 15,
                'MASTERCARD','RUPAY','VISA','CARD' => ($len >= 16 && $len <= 19),
                default => false
            };
            $valid_luhn = passes_luhn($num);
            if (!$valid_len || !$valid_luhn) {
                $error = "Enter a valid {$brand} card number.";
                $step = 'payment';
            }
            if (!preg_match('/^(0[1-9]|1[0-2])\\/([0-9]{2})$/', $payment_meta['card_expiry'])) {
                $error = "Enter expiry as MM/YY.";
                $step = 'payment';
            } else {
                [$expMonth, $expYear] = explode('/', $payment_meta['card_expiry']);
                $expMonth = (int)$expMonth;
                $expYear = 2000 + (int)$expYear;
                $currentYear = (int)date('Y');
                $currentMonth = (int)date('n');
                if ($expYear < $currentYear || ($expYear === $currentYear && $expMonth < $currentMonth)) {
                    $error = "Card appears expired.";
                    $step = 'payment';
                }
            }
            $cvvLen = strlen($payment_meta['card_cvv']);
            if (($brand === 'AMEX' && $cvvLen !== 4) || ($brand !== 'AMEX' && $cvvLen !== 3)) {
                $error = "Enter a valid CVV.";
                $step = 'payment';
            }
        } elseif ($selected_payment_method === 'netbanking') {
            if ($payment_meta['netbank_bank'] === '') {
                $error = "Please select your bank for net banking.";
                $step = 'payment';
            } elseif (!in_array($payment_meta['netbank_bank'], $supported_netbank_banks, true)) {
                $error = "Selected bank is not supported.";
                $step = 'payment';
            }
        }
    }

    if ($error === '') {
        $address_db = mysqli_real_escape_string($con, $address);
        $city_db = mysqli_real_escape_string($con, $city);
        $state_db = mysqli_real_escape_string($con, $state);
        $zip_db = mysqli_real_escape_string($con, $zip);

        $is_online_payment = in_array($selected_payment_method, ['upi', 'card', 'netbanking'], true);
        $payment_status = $is_online_payment ? 'Initiated' : 'Pending';
        $total_price = 0;
        $all_ok = true;
        $order_count = 0;
        $created_order_ids = [];
        $gateway_order_id = '';

        mysqli_begin_transaction($con);

        foreach($checkout_items as $pid => $details){
            $product_query = mysqli_query($con, "SELECT id, price FROM products WHERE id=$pid LIMIT 1");
            $product = $product_query ? mysqli_fetch_assoc($product_query) : null;

            if(!$product){
                $all_ok = false;
                $error = "One of the selected products does not exist anymore.";
                break;
            }

            $qty = max(1, (int)$details['qty']);
            $size = mysqli_real_escape_string($con, $details['size'] ?? '');
            $subtotal = (float)$product['price'] * $qty;
            $total_price += $subtotal;

            $order_sql = "INSERT INTO orders (user_id, product_id, quantity, size, total_price, address, city, state, zip, created_at)
                          VALUES ($user_id, $pid, $qty, '$size', $subtotal, '$address_db', '$city_db', '$state_db', '$zip_db', NOW())";

            if(!mysqli_query($con, $order_sql)){
                $all_ok = false;
                $error = "Failed to place order. Please try again.";
                break;
            }

            $order_id = (int)mysqli_insert_id($con);
            $created_order_ids[] = $order_id;

            // Keep only non-sensitive references; never store card number/CVV.
            $payment_ref_db = '';
            if ($is_online_payment) {
                if ($selected_payment_method === 'upi') {
                    $payment_ref_db = 'UPI:PENDING';
                } elseif ($selected_payment_method === 'card') {
                    $payment_ref_db = 'CARD:PENDING';
                } else {
                    $payment_ref_db = 'NETBANK:PENDING';
                }
            } else {
                $payment_ref_db = 'COD';
            }

            $payment_sql = "INSERT INTO payments (order_id, user_id, payment_method, payment_status, amount, transaction_ref, created_at)
                            VALUES ($order_id, $user_id, '$selected_payment_method', '$payment_status', $subtotal, "
                            . ($payment_ref_db !== '' ? "'$payment_ref_db'" : "NULL") . ", NOW())";

            if(!mysqli_query($con, $payment_sql)){
                $all_ok = false;
                $error = "Payment record could not be saved.";
                break;
            }

            $order_count++;
        }

        if($all_ok){
            if ($is_online_payment) {
                require_once __DIR__ . '/payment_gateway.php';
                $receipt = 'emk_' . $user_id . '_' . time();
                $amount_paise = (int)round($total_price * 100);
                $gateway_order = create_razorpay_order($amount_paise, 'INR', $receipt);

                if (!$gateway_order['ok']) {
                    $all_ok = false;
                    $reason = trim((string)($gateway_order['error'] ?? 'Unknown gateway error'));
                    $error = "Online payment unavailable: " . $reason . ". Check Razorpay keys in .env or config/payment.local.php.";
                } else {
                    $gateway_order_id = $gateway_order['order_id'];
                    $gateway_order_db = mysqli_real_escape_string($con, $gateway_order_id);
                    foreach ($created_order_ids as $created_order_id) {
                        $updated = mysqli_query($con, "UPDATE payments 
                            SET transaction_ref='RZP_ORDER:$gateway_order_db'
                            WHERE order_id=" . (int)$created_order_id . " AND user_id=$user_id");
                        if (!$updated) {
                            $all_ok = false;
                            $error = "Payment initialization failed.";
                            break;
                        }
                    }
                }
            }
        }

        if($all_ok){
            mysqli_commit($con);
            unset($_SESSION['checkout_form']);

            // Clear cart after successful order
            if(!isset($_GET['buy'])) {
                if($has_cart_table){
                    mysqli_query($con, "DELETE FROM user_cart WHERE user_id=$user_id");
                } else {
                    unset($_SESSION['cart']);
                }
            }
            unset($_SESSION['buy_now']);

            if ($is_online_payment) {
                try {
                    $payment_token = bin2hex(random_bytes(16));
                } catch (Throwable $e) {
                    $payment_token = sha1(uniqid((string)$user_id, true));
                }
                if (!isset($_SESSION['pending_gateway_payments']) || !is_array($_SESSION['pending_gateway_payments'])) {
                    $_SESSION['pending_gateway_payments'] = [];
                }
                $_SESSION['pending_gateway_payments'][$payment_token] = [
                    'user_id' => $user_id,
                    'order_ids' => $created_order_ids,
                    'amount_paise' => (int)round($total_price * 100),
                    'amount_display' => number_format($total_price, 2, '.', ''),
                    'gateway_order_id' => $gateway_order_id,
                    'method' => $selected_payment_method,
                    'created_at' => time()
                ];

                header('Location: pay_now.php?pid=' . urlencode($payment_token));
                exit();
            }

            $success = "Order placed successfully! Orders: $order_count | Total: $" . number_format($total_price, 2) . " | Payment: " . $payment_method_labels[$selected_payment_method];
        } else {
            mysqli_rollback($con);
            if ($error === '') {
                $error = "Order could not be completed.";
            }
        }

        $step = $error ? 'payment' : 'done';
    }

    // PRG: avoid browser "Resubmit form?" on refresh after place_order POST.
    if ($error !== '') {
        $_SESSION['checkout_flash_error'] = $error;
        $_SESSION['checkout_form'] = [
            'step' => $step,
            'address' => $address,
            'city' => $city,
            'state' => $state,
            'zip' => $zip,
            'payment_method' => $selected_payment_method,
            'upi_id' => $payment_meta['upi_id'],
            'netbank_bank' => $payment_meta['netbank_bank']
        ];
        header('Location: ' . build_checkout_redirect_url($step));
        exit();
    }

    if ($success !== '') {
        $_SESSION['checkout_flash_success'] = $success;
        header('Location: ' . build_checkout_redirect_url('done'));
        exit();
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container my-5">

<?php if($success !== ''){ ?>
    <div class="alert alert-success text-center"><?= htmlspecialchars($success); ?></div>
<?php } ?>

<?php if($error !== ''){ ?>
    <div class="alert alert-danger text-center"><?= htmlspecialchars($error); ?></div>
<?php } ?>

<h3 class="mb-4">Checkout</h3>
<div class="d-flex gap-3 mb-4 align-items-center">
    <div class="badge step-badge <?= $step === 'address' ? '' : 'muted'; ?> px-3 py-2">1. Address</div>
    <div class="badge step-badge <?= $step === 'payment' ? '' : 'muted'; ?> px-3 py-2">2. Payment</div>
</div>

<div class="row">

    <!-- ORDER SUMMARY -->
    <div class="col-lg-6 mb-4 order-lg-2">
        <div class="card ck-card shadow-sm border-0 rounded-4 p-4">
            <h5 class="fw-bold mb-3">Order Summary</h5>

            <?php $total = 0; ?>
            <?php foreach($checkout_items as $pid => $details):
                $product_query = mysqli_query($con, "SELECT * FROM products WHERE id=$pid");
                $product = $product_query ? mysqli_fetch_assoc($product_query) : null;
                if (!$product) {
                    continue;
                }
                $subtotal = (float)$product['price'] * (int)$details['qty'];
                $total += $subtotal;
            ?>
            <div class="d-flex align-items-center mb-3">
                <div class="me-3">
                    <img src="img/<?= htmlspecialchars($product['image']); ?>" alt="<?= htmlspecialchars($product['name']); ?>" style="width:70px; height:70px; object-fit:cover; border-radius:5px;">
                </div>
                <div class="flex-grow-1">
                    <strong><?= htmlspecialchars($product['name']); ?></strong>
                    <?php if($details['size'] !== '') echo "<br><small>Size: " . htmlspecialchars($details['size']) . "</small>"; ?>
                    <br><small>Qty: <?= (int)$details['qty']; ?></small>
                </div>
                <div class="fw-bold">$<?= number_format($subtotal, 2); ?></div>
            </div>
            <?php endforeach; ?>

            <hr>
            <div class="d-flex justify-content-between fw-bold">
                <span>Total</span>
                <span>$<?= number_format($total, 2); ?></span>
            </div>
        </div>
    </div>

    <!-- SHIPPING + PAYMENT -->
    <div class="col-lg-6 order-lg-1">
        <div class="card ck-card shadow-sm border-0 rounded-4 p-4">
            <h5 class="fw-bold mb-3"><?= $step === 'payment' ? 'Review Address' : 'Shipping Information'; ?></h5>

            <form method="POST" id="checkoutForm" novalidate>
                <input type="hidden" name="current_step" value="<?= htmlspecialchars($step); ?>">

                <?php if($step === 'address'): ?>
                    <!-- preserve payment fields when user goes back to address step -->
                    <input type="hidden" name="upi_id" value="<?= htmlspecialchars($payment_meta['upi_id']); ?>">
                    <input type="hidden" name="netbank_bank" value="<?= htmlspecialchars($payment_meta['netbank_bank']); ?>">

                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($user['name'] ?? ''); ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? ''); ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mobile</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($user['mobile'] ?? ''); ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" required><?= htmlspecialchars($address); ?></textarea>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col">
                            <input type="text" name="city" class="form-control" placeholder="City" value="<?= htmlspecialchars($city); ?>" required>
                        </div>
                        <div class="col">
                            <input type="text" name="state" class="form-control" placeholder="State" value="<?= htmlspecialchars($state); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <input type="text" name="zip" class="form-control" placeholder="ZIP / Postal Code" value="<?= htmlspecialchars($zip); ?>" required>
                    </div>

                    <button type="submit" name="address_next" class="btn btn-gradient w-100 rounded-pill">
                        Continue to Payment
                    </button>
                <?php else: ?>
                    <!-- Hidden fields to carry address -->
                    <input type="hidden" name="address" value="<?= htmlspecialchars($address); ?>">
                    <input type="hidden" name="city" value="<?= htmlspecialchars($city); ?>">
                    <input type="hidden" name="state" value="<?= htmlspecialchars($state); ?>">
                    <input type="hidden" name="zip" value="<?= htmlspecialchars($zip); ?>">

                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="fw-bold">Delivery To</div>
                                <div><?= htmlspecialchars($user['name'] ?? ''); ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($address); ?>, <?= htmlspecialchars($city); ?>, <?= htmlspecialchars($state); ?> - <?= htmlspecialchars($zip); ?></div>
                                <div class="text-muted small">Mob: <?= htmlspecialchars($user['mobile'] ?? ''); ?></div>
                            </div>
                            <button type="submit" name="edit_address" value="1" class="btn btn-link" formnovalidate>Edit</button>
                        </div>
                    </div>

                    <h6 class="fw-bold mt-4 mb-3">Payment Method</h6>

                    <div class="payment-list mb-3" id="paymentGrid">
                        <label class="payment-option" data-method="upi">
                            <div class="form-check mb-0">
                                <input class="form-check-input payment-method-input" type="radio" name="payment_method" id="paymentUpi" value="upi" <?= $selected_payment_method === 'upi' ? 'checked' : ''; ?> required>
                                <div class="payment-option-body">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-semibold">UPI</span>
                                        <span class="text-muted small">GPay / PhonePe / Paytm</span>
                                    </div>
                                    <div class="mt-2 d-flex gap-2 align-items-center logos">
                                        <span class="upi-logo gpay">GPay</span>
                                        <span class="upi-logo phonepe">PhonePe</span>
                                        <span class="upi-logo paytm">Paytm</span>
                                    </div>
                                    <div class="payment-inline-detail mt-3">
                                        <label class="form-label">UPI ID</label>
                                        <input type="text" name="upi_id" id="upiId" class="form-control" placeholder="name@bank" value="<?= htmlspecialchars($payment_meta['upi_id']); ?>">
                                        <div class="invalid-feedback">Valid UPI ID required (e.g. user@okhdfcbank).</div>
                                        <div class="text-muted small mt-1">Example: user@okhdfcbank</div>
                                    </div>
                                </div>
                            </div>
                        </label>

                        <label class="payment-option" data-method="card">
                            <div class="form-check mb-0">
                                <input class="form-check-input payment-method-input" type="radio" name="payment_method" id="paymentCard" value="card" <?= $selected_payment_method === 'card' ? 'checked' : ''; ?> required>
                                <div class="payment-option-body">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fa-regular fa-credit-card text-primary"></i>
                                        <span class="fw-semibold">Card (Visa / Master / Rupay)</span>
                                    </div>
                                    <div class="payment-inline-detail mt-3">
                                        <div class="row g-2">
                                            <div class="col-12">
                                                <label class="form-label">Card Number</label>
                                                <div class="position-relative">
                                                    <input type="text" name="card_number" id="cardNumber" class="form-control pe-5" maxlength="19" inputmode="numeric" value="<?= htmlspecialchars($payment_meta['card_number']); ?>" placeholder="16 digits">
                                                    <span class="card-brand-badge" id="cardBrandBadge">Card</span>
                                                    <div class="invalid-feedback">Enter a valid card number.</div>
                                                </div>
                                                <div class="text-muted small mt-1" id="cardBrandText">Card type will appear here</div>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label">Expiry (MM/YY)</label>
                                                <input type="text" name="card_expiry" id="cardExpiry" class="form-control" placeholder="MM/YY" maxlength="5" value="<?= htmlspecialchars($payment_meta['card_expiry']); ?>">
                                                <div class="invalid-feedback">Enter expiry as MM/YY.</div>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label">CVV</label>
                                                <input type="password" name="card_cvv" id="cardCvv" class="form-control" maxlength="4" inputmode="numeric" value="" placeholder="3 or 4 digits">
                                                <div class="invalid-feedback">Enter a valid CVV.</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </label>

                        <label class="payment-option" data-method="netbanking">
                            <div class="form-check mb-0">
                                <input class="form-check-input payment-method-input" type="radio" name="payment_method" id="paymentNetbanking" value="netbanking" <?= $selected_payment_method === 'netbanking' ? 'checked' : ''; ?> required>
                                <div class="payment-option-body">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-building-columns text-primary"></i>
                                        <span class="fw-semibold">Net Banking</span>
                                    </div>
                                    <div class="payment-inline-detail mt-3">
                                        <label class="form-label">Select Bank</label>
                                        <select name="netbank_bank" id="netBank" class="form-select">
                                            <option value="">-- Choose Bank --</option>
                                            <?php
                                            foreach ($supported_netbank_banks as $bank):
                                                $sel = ($payment_meta['netbank_bank'] === $bank) ? 'selected' : '';
                                                echo "<option value=\"".htmlspecialchars($bank)."\" $sel>".htmlspecialchars($bank)."</option>";
                                            endforeach;
                                            ?>
                                        </select>
                                        <div class="invalid-feedback">Please select your bank.</div>
                                    </div>
                                </div>
                            </div>
                        </label>

                        <label class="payment-option" data-method="cod">
                            <div class="form-check mb-0">
                                <input class="form-check-input payment-method-input" type="radio" name="payment_method" id="paymentCod" value="cod" <?= $selected_payment_method === 'cod' ? 'checked' : ''; ?> required>
                                <div class="payment-option-body">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-box-open text-primary"></i>
                                        <span class="fw-semibold">Cash on Delivery</span>
                                    </div>
                                    <div class="text-muted small mt-1">Pay when the package arrives.</div>
                                </div>
                            </div>
                        </label>
                    </div>

                    <?php
                        $cta_label = ($selected_payment_method === 'cod')
                            ? 'Place Order'
                            : 'Pay $' . number_format($display_total, 2);
                    ?>
                    <button type="submit" name="place_order" class="btn btn-gradient w-100 rounded-pill" id="payCta">
                        <?= htmlspecialchars($cta_label); ?>
                    </button>
                <?php endif; ?>
            </form>
        </div>
    </div>

</div>
</div>

<style>
/* Steps */
.badge.bg-secondary { background: #c5ccd9!important; }
.step-badge {
    background: linear-gradient(135deg, #00d4ff, #7c5dff);
    color: #fff;
    box-shadow: 0 6px 18px rgba(0, 212, 255, 0.25);
}
.step-badge.muted {
    background: #e8edf5;
    color: #6b7280;
    box-shadow: none;
}

/* Cards aligned with site look */
.ck-card {
    background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
    border: 1px solid #e7ecf3;
    box-shadow: 0 10px 30px rgba(0,0,0,0.06);
}
.ck-card h5 { color: #1f2937; }

/* Payment radios */
.payment-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.payment-option {
    border: 1px solid #d7e2f0;
    border-radius: 14px;
    padding: 14px 16px;
    background: #ffffff;
    cursor: pointer;
    transition: all 0.2s ease;
}
.payment-option .form-check {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.payment-option .form-check-input {
    margin-top: 0.25rem;
    flex-shrink: 0;
}
.payment-option .form-check-label {
    cursor: pointer;
}
.payment-option:hover {
    border-color: #00d4ff;
    box-shadow: 0 6px 18px rgba(0,212,255,0.12);
}
.payment-option:has(.payment-method-input:checked) {
    border-color: #7c5dff !important;
    box-shadow: 0 10px 24px rgba(124,93,255,0.18) !important;
    background: #fbf9ff;
}
.payment-option-body {
    flex: 1;
}
.payment-inline-detail {
    display: none;
}
.payment-option:has(.payment-method-input:checked) .payment-inline-detail {
    display: block;
}
.payment-option .logos .upi-logo {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    color: #fff;
}
.upi-text { background: linear-gradient(90deg,#ff8c00,#ff3c00); }
.gpay { background: #1a73e8; }
.phonepe { background: #5c2d91; }
.paytm { background: #00baf2; color:#033f4f; }
.payment-inline-detail label { font-weight: 600; margin-bottom: 6px; color: #1f2937; }
.payment-inline-detail .form-control, .payment-inline-detail .form-select {
    border-radius: 12px;
    border-color: #d8deea;
    padding: 10px 12px;
}
.payment-inline-detail .form-control:focus, .payment-inline-detail .form-select:focus {
    border-color: #00d4ff;
    box-shadow: 0 0 0 0.15rem rgba(0,212,255,.2);
}
.card-brand-badge {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: #e8edf5;
    color: #1f2937;
    border-radius: 999px;
    padding: 4px 10px;
    font-size: 11px;
    font-weight: 700;
    border: 1px solid #d7e2f0;
}
.form-control.is-invalid, .form-select.is-invalid {
    border-color: #dc3545!important;
    box-shadow: 0 0 0 0.1rem rgba(220,53,69,.25)!important;
}
.pay-cta-note {
    font-size: 12px;
    color: #6b7280;
    margin-top: 6px;
}

/* Buttons */
.btn-gradient {
    background: linear-gradient(135deg, #00d4ff, #7c5dff);
    color: #fff;
    border: none;
    box-shadow: 0 10px 25px rgba(0,212,255,0.25);
}
.btn-gradient:hover {
    color: #fff;
    box-shadow: 0 12px 28px rgba(124,93,255,0.3);
    transform: translateY(-1px);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const grid = document.getElementById('paymentGrid');
    const payCta = document.getElementById('payCta');
    const totalAmount = <?= json_encode(number_format($display_total, 2)); ?>;
    const cardNumber = document.getElementById('cardNumber');
    const cardBrand = document.getElementById('cardBrandBadge');
    const cardBrandText = document.getElementById('cardBrandText');
    const upiId = document.getElementById('upiId');
    const cardExpiry = document.getElementById('cardExpiry');
    const cardCvv = document.getElementById('cardCvv');
    const netBank = document.getElementById('netBank');
    const form = document.getElementById('checkoutForm');
    const paymentInputs = grid ? Array.from(grid.querySelectorAll('input[name="payment_method"]')) : [];

    function detectBrand(num) {
        if (!num) return 'Card';
        if (num.startsWith('4')) return 'VISA';
        const two = parseInt(num.slice(0,2));
        const four = parseInt(num.slice(0,4));
        if ((two >= 51 && two <= 55) || (four >= 2221 && four <= 2720)) return 'MASTERCARD';
        if (num.startsWith('34') || num.startsWith('37')) return 'AMEX';
        if (num.startsWith('60') || num.startsWith('65') || num.startsWith('81') || num.startsWith('82') || num.startsWith('508') || num.startsWith('353')) return 'RUPAY';
        return 'CARD';
    }

    function brandDisplayName(brand) {
        if (brand === 'VISA') return 'Visa';
        if (brand === 'MASTERCARD') return 'Mastercard';
        if (brand === 'AMEX') return 'American Express';
        if (brand === 'RUPAY') return 'RuPay';
        return 'Unknown';
    }

    function luhnCheck(num) {
        if (!/^[0-9]+$/.test(num)) return false;
        let sum = 0;
        let alternate = false;
        for (let i = num.length - 1; i >= 0; i--) {
            let digit = parseInt(num.charAt(i), 10);
            if (alternate) {
                digit *= 2;
                if (digit > 9) digit -= 9;
            }
            sum += digit;
            alternate = !alternate;
        }
        return (sum % 10) === 0;
    }

    function updateBrand() {
        if (!cardNumber || !cardBrand) return;
        const digits = cardNumber.value.replace(/\D/g,'');
        const detected = detectBrand(digits);
        cardBrand.textContent = detected;

        if (cardBrandText) {
            if (!digits) {
                cardBrandText.textContent = 'Card type will appear here';
            } else {
                cardBrandText.textContent = `Detected card: ${brandDisplayName(detected)}`;
            }
        }

        // Adjust max length & placeholders by brand
        let maxLen = 19;
        let ph = '16 digits';
        const brand = detected;
        if (brand === 'AMEX') { maxLen = 15; ph = '15 digits'; }
        if (brand === 'MASTERCARD' || brand === 'RUPAY') { maxLen = 16; ph = '16 digits'; }
        cardNumber.maxLength = maxLen;
        if (!cardNumber.value) cardNumber.placeholder = ph;
    }

    function markInvalid(el, condition) {
        if (!el) return condition;
        if (condition) el.classList.remove('is-invalid');
        else el.classList.add('is-invalid');
        return condition;
    }

    function frontValidate() {
        let ok = true;
        const method = (grid.querySelector('input[name=\"payment_method\"]:checked') || {}).value;

        if (method === 'upi') {
            const val = (upiId && upiId.value ? upiId.value : '').replace(/\s+/g,'');
            const valid = /^[a-zA-Z0-9.\-_]{2,}@[a-zA-Z]{2,}$/.test(val);
            ok = markInvalid(upiId, valid) && ok;
        }

        if (method === 'card') {
            const digits = (cardNumber && cardNumber.value ? cardNumber.value : '').replace(/\D/g,'');
            const brand = detectBrand(digits);
            let lenOK = false;
            if (brand === 'AMEX') lenOK = digits.length === 15;
            else lenOK = digits.length >= 16 && digits.length <= 19;
            const luhnOK = luhnCheck(digits);
            ok = markInvalid(cardNumber, lenOK && luhnOK) && ok;

            const expiryOK = /^(0[1-9]|1[0-2])\/[0-9]{2}$/.test(cardExpiry && cardExpiry.value ? cardExpiry.value : '');
            let expiryNotPast = false;
            if (expiryOK) {
                const [mm, yy] = cardExpiry.value.split('/');
                const expMonth = parseInt(mm, 10);
                const expYear = 2000 + parseInt(yy, 10);
                const now = new Date();
                const currentMonth = now.getMonth() + 1;
                const currentYear = now.getFullYear();
                expiryNotPast = expYear > currentYear || (expYear === currentYear && expMonth >= currentMonth);
            }
            ok = markInvalid(cardExpiry, expiryOK && expiryNotPast) && ok;

            const cvv = (cardCvv && cardCvv.value ? cardCvv.value : '').trim();
            const cvvOK = (brand === 'AMEX') ? cvv.length === 4 : cvv.length === 3;
            ok = markInvalid(cardCvv, cvvOK) && ok;
        }

        if (method === 'netbanking') {
            const bankOK = !!(netBank && netBank.value);
            ok = markInvalid(netBank, bankOK) && ok;
        }

        return ok;
    }

    if (!grid) return;

    function syncPaymentState() {
        const active = paymentInputs.find((radio) => radio.checked);
        const method = active ? active.value : 'upi';

        const upiFields = [upiId].filter(Boolean);
        const cardFields = [cardNumber, cardExpiry, cardCvv].filter(Boolean);
        const netBankFields = [netBank].filter(Boolean);

        upiFields.forEach((field) => {
            field.disabled = method !== 'upi';
            field.required = method === 'upi';
        });
        cardFields.forEach((field) => {
            field.disabled = method !== 'card';
            field.required = method === 'card';
        });
        netBankFields.forEach((field) => {
            field.disabled = method !== 'netbanking';
            field.required = method === 'netbanking';
        });

        if (payCta) {
            payCta.textContent = (method === 'cod') ? 'Place Order' : `Pay $${totalAmount}`;
        }
    }

    if (cardNumber) {
        cardNumber.addEventListener('input', updateBrand);
        updateBrand();
    }

    paymentInputs.forEach((radio) => {
        radio.addEventListener('change', syncPaymentState);
    });

    if (form) {
        form.addEventListener('submit', (e) => {
            if (!frontValidate()) {
                e.preventDefault(); 
                e.stopPropagation();
            }
        });
    }

    const checked = paymentInputs.find((radio) => radio.checked);
    if (!checked && paymentInputs[0]) {
        paymentInputs[0].checked = true;
    }
    syncPaymentState();
});
</script>

<?php include 'includes/footer.php'; ?>
