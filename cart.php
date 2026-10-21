<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db_config.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? (int)$_SESSION['user_id'] : 0;
$user_role = $_SESSION['user_role'] ?? 'user';
$is_admin = ($user_role === 'admin');

if ($is_admin) {
    header("Location: admin_dashboard.php");
    exit();
}

$has_cart_table = false;
$cart_table_check = mysqli_query($con, "SHOW TABLES LIKE 'user_cart'");
if ($cart_table_check && mysqli_num_rows($cart_table_check) > 0) {
    $has_cart_table = true;
}

/* ================= ADD TO CART ================= */
if (isset($_GET['add'])) {
    if (!$is_logged_in) {
        // Force sign-in before adding items
        header("Location: login.php");
        exit();
    }

    $product_id = (int)$_GET['add'];

    if ($is_admin) {
        header("Location: admin_dashboard.php");
        exit();
    }

    if ($is_logged_in && $has_cart_table) {
        $check_sql = mysqli_query($con, "SELECT quantity FROM user_cart WHERE user_id=$user_id AND product_id=$product_id LIMIT 1");
        if ($check_sql && mysqli_num_rows($check_sql) > 0) {
            mysqli_query($con, "UPDATE user_cart SET quantity = quantity + 1 WHERE user_id=$user_id AND product_id=$product_id");
        } else {
            mysqli_query($con, "INSERT INTO user_cart (user_id, product_id, quantity) VALUES ($user_id, $product_id, 1)");
        }
    } else {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]++;
        } else {
            $_SESSION['cart'][$product_id] = 1;
        }
    }

    $redirect = $_SERVER['HTTP_REFERER'] ?? 'cart.php';
    header("Location: $redirect");
    exit();
}

/* ================= REMOVE ================= */
if (isset($_GET['remove'])) {
    if (!$is_logged_in) {
        header("Location: login.php");
        exit();
    }

    $product_id = (int)$_GET['remove'];

    if ($is_admin) {
        header("Location: admin_dashboard.php");
        exit();
    }

    if ($is_logged_in && $has_cart_table) {
        mysqli_query($con, "DELETE FROM user_cart WHERE user_id=$user_id AND product_id=$product_id");
    } else {
        if (isset($_SESSION['cart'][$product_id])) {
            unset($_SESSION['cart'][$product_id]);
        }
    }

    $redirect = $_SERVER['HTTP_REFERER'] ?? 'cart.php';
    header("Location: $redirect");
    exit();
}

include 'includes/header.php';
?>

<main>
<section class="product-section">
<div class="container mt-5">
<h2 class="text-center mb-4">Your Cart</h2>

<div class="row g-4 mb-5">

<?php
$total = 0;
$has_items = false;

if ($is_logged_in && $has_cart_table) {
    $cart_query = mysqli_query($con, "
        SELECT c.product_id, c.quantity, p.*
        FROM user_cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = $user_id
        ORDER BY c.id DESC
    ");

    if ($cart_query && mysqli_num_rows($cart_query) > 0) {
        $has_items = true;
        while ($item = mysqli_fetch_assoc($cart_query)) {
            $qty = (int)$item['quantity'];
            $subtotal = (float)$item['price'] * $qty;
            $total += $subtotal;
?>

<div class="col-lg-3 col-md-6 col-12">
<div class="card product-card h-100">

<a href="product.php?id=<?= (int)$item['id']; ?>">
<img src="img/<?= htmlspecialchars($item['image']); ?>" class="card-img-top">
</a>

<div class="card-body text-center">

<h6 class="card-title">
<a href="product.php?id=<?= (int)$item['id']; ?>" style="text-decoration:none; color:black;">
<?= htmlspecialchars($item['name']); ?>
</a>
</h6>

<p class="text-muted mb-1"><?= htmlspecialchars($item['brand']); ?></p>

<p class="fw-bold">$<?= number_format((float)$item['price'], 2); ?></p>

<p class="mb-1">Quantity: <strong><?= $qty; ?></strong></p>

<p class="text-success">Subtotal: $<?= number_format($subtotal, 2); ?></p>

<div class="d-flex justify-content-center gap-2">
<a href="cart.php?remove=<?= (int)$item['product_id']; ?>" class="btn btn-danger btn-sm">Remove</a>
</div>

</div>
</div>
</div>

<?php
        }
    }
} else {
    if (!empty($_SESSION['cart'])) {
        $has_items = true;
        foreach ($_SESSION['cart'] as $id => $qty) {
            $id = (int)$id;
            $qty = (int)$qty;
            $result = mysqli_query($con, "SELECT * FROM products WHERE id=$id");
            $product = $result ? mysqli_fetch_assoc($result) : null;

            if (!$product) {
                continue;
            }

            $subtotal = (float)$product['price'] * $qty;
            $total += $subtotal;
?>

<div class="col-lg-3 col-md-6 col-12">
<div class="card product-card h-100">

<a href="product.php?id=<?= (int)$product['id']; ?>">
<img src="img/<?= htmlspecialchars($product['image']); ?>" class="card-img-top">
</a>

<div class="card-body text-center">

<h6 class="card-title">
<a href="product.php?id=<?= (int)$product['id']; ?>" style="text-decoration:none; color:black;">
<?= htmlspecialchars($product['name']); ?>
</a>
</h6>

<p class="text-muted mb-1"><?= htmlspecialchars($product['brand']); ?></p>

<p class="fw-bold">$<?= number_format((float)$product['price'], 2); ?></p>

<p class="mb-1">Quantity: <strong><?= $qty; ?></strong></p>

<p class="text-success">Subtotal: $<?= number_format($subtotal, 2); ?></p>

<div class="d-flex justify-content-center gap-2">
<a href="cart.php?remove=<?= $id; ?>" class="btn btn-danger btn-sm">Remove</a>
</div>

</div>
</div>
</div>

<?php
        }
    }
}

if (!$has_items) {
    echo "<h4 class='text-center'>Your Cart is Empty</h4>";
}
?>

</div>

<?php if ($total > 0) { ?>
<div class="text-center mt-4">
<h4>Total: $<?= number_format($total, 2); ?></h4>
<a href="checkout.php" class="btn btn-success mt-2">Proceed to Checkout</a>
</div>
<?php } ?>

</div>
</section>
</main>

<?php include 'includes/footer.php'; ?>
