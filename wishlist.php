<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db_config.php';

$is_logged_in = isset($_SESSION['user_id']);
$is_guest = !empty($_SESSION['is_guest']);
$user_id = $is_logged_in ? (int)$_SESSION['user_id'] : 0;
$user_role = $_SESSION['user_role'] ?? 'user';
$is_admin = ($user_role === 'admin');

if ($is_admin) {
    header("Location: admin_dashboard.php");
    exit();
}

$login_prompt = 'Please log in to use the wishlist.';
if ($is_guest && !$is_logged_in) {
    $_SESSION['login_notice'] = $login_prompt;
    header("Location: login.php");
    exit();
}

$has_wishlist_table = false;
$wishlist_table_check = mysqli_query($con, "SHOW TABLES LIKE 'user_wishlist'");
if ($wishlist_table_check && mysqli_num_rows($wishlist_table_check) > 0) {
    $has_wishlist_table = true;
}

/* ADD TO WISHLIST */
if (isset($_GET['add'])) {
    $product_id = (int)$_GET['add'];

    if ($is_admin) {
        header("Location: admin_dashboard.php");
        exit();
    }

    if ($is_logged_in && $has_wishlist_table) {
        $check_sql = mysqli_query($con, "SELECT id FROM user_wishlist WHERE user_id=$user_id AND product_id=$product_id LIMIT 1");
        if (!$check_sql || mysqli_num_rows($check_sql) === 0) {
            mysqli_query($con, "INSERT INTO user_wishlist (user_id, product_id) VALUES ($user_id, $product_id)");
        }
    } else {
        if (!isset($_SESSION['wishlist'])) {
            $_SESSION['wishlist'] = [];
        }
        if (!in_array($product_id, $_SESSION['wishlist'])) {
            $_SESSION['wishlist'][] = $product_id;
        }
    }

    $redirect = $_SERVER['HTTP_REFERER'] ?? 'wishlist.php';
    header("Location: $redirect");
    exit();
}

/* REMOVE FROM WISHLIST */
if (isset($_GET['remove'])) {
    $product_id = (int)$_GET['remove'];

    if ($is_admin) {
        header("Location: admin_dashboard.php");
        exit();
    }

    if ($is_logged_in && $has_wishlist_table) {
        mysqli_query($con, "DELETE FROM user_wishlist WHERE user_id=$user_id AND product_id=$product_id");
    } else {
        if (isset($_SESSION['wishlist'])) {
            $_SESSION['wishlist'] = array_diff($_SESSION['wishlist'], [$product_id]);
        }
    }

    $redirect = $_SERVER['HTTP_REFERER'] ?? 'wishlist.php';
    header("Location: $redirect");
    exit();
}

include 'includes/header.php';
?>

<main>
<section class="product-section">
<div class="container mt-5">
<h2 class="text-center mb-4">Your Wishlist</h2>

<div class="row g-4 mb-5">

<?php
$has_items = false;

if ($is_logged_in && $has_wishlist_table) {
    $wishlist_query = mysqli_query($con, "
        SELECT w.product_id, p.*
        FROM user_wishlist w
        JOIN products p ON w.product_id = p.id
        WHERE w.user_id = $user_id
        ORDER BY w.id DESC
    ");

    if ($wishlist_query && mysqli_num_rows($wishlist_query) > 0) {
        $has_items = true;
        while ($product = mysqli_fetch_assoc($wishlist_query)) {
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

<p class="fw-bold">
$<?= number_format((float)$product['price'], 2); ?>
<?php if ((float)$product['old_price'] > 0) { ?>
<span class="text-decoration-line-through text-muted">$<?= number_format((float)$product['old_price'], 2); ?></span>
<?php } ?>
</p>

<div class="d-flex justify-content-center gap-2">
<a href="cart.php?add=<?= (int)$product['id']; ?>" class="btn btn-primary btn-sm">Add to Cart</a>
<a href="wishlist.php?remove=<?= (int)$product['id']; ?>" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-heart"></i></a>
</div>

</div>
</div>
</div>

<?php
        }
    }
} else {
    if (!empty($_SESSION['wishlist'])) {
        $has_items = true;
        foreach ($_SESSION['wishlist'] as $id) {
            $id = (int)$id;
            $query = mysqli_query($con, "SELECT * FROM products WHERE id=$id");
            $product = $query ? mysqli_fetch_assoc($query) : null;

            if (!$product) {
                continue;
            }
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

<p class="fw-bold">
$<?= number_format((float)$product['price'], 2); ?>
<?php if ((float)$product['old_price'] > 0) { ?>
<span class="text-decoration-line-through text-muted">$<?= number_format((float)$product['old_price'], 2); ?></span>
<?php } ?>
</p>

<div class="d-flex justify-content-center gap-2">
<a href="cart.php?add=<?= (int)$product['id']; ?>" class="btn btn-primary btn-sm">Add to Cart</a>
<a href="wishlist.php?remove=<?= (int)$product['id']; ?>" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-heart"></i></a>
</div>

</div>
</div>
</div>

<?php
        }
    }
}

if (!$has_items) {
    echo "<h4 class='text-center'>Your Wishlist is Empty</h4>";
}
?>

</div>
</div>
</section>
</main>

<?php include 'includes/footer.php'; ?>
