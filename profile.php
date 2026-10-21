<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Admins use dedicated profile view
if (($_SESSION['user_role'] ?? 'user') === 'admin') {
    header("Location: admin_profile.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Fetch user data
$user_query = mysqli_query($con, "SELECT * FROM register WHERE id='$user_id'");
if ($user_query && mysqli_num_rows($user_query) > 0) {
    $user = mysqli_fetch_assoc($user_query);
} else {
    $user = [
        'name' => 'Unknown',
        'email' => 'Unknown',
        'mobile' => 'N/A',
        'gender' => 'N/A',
        'address' => 'N/A',
        'profile_pic' => 'default.png'
    ];
}

// Handle profile update from modal
if (isset($_POST['update_profile'])) {
    $name = mysqli_real_escape_string($con, $_POST['name']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $mobile = mysqli_real_escape_string($con, $_POST['mobile']);
    $gender = mysqli_real_escape_string($con, $_POST['gender']);
    $address = mysqli_real_escape_string($con, $_POST['address']);

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['name'] !== '') {
        $file_name = time() . '_' . $_FILES['profile_pic']['name'];
        $file_tmp = $_FILES['profile_pic']['tmp_name'];
        move_uploaded_file($file_tmp, "uploads/" . $file_name);
        $profile_pic_sql = ", profile_pic='$file_name'";
    } else {
        $profile_pic_sql = "";
    }

    $update_sql = "UPDATE register SET name='$name', email='$email', mobile='$mobile', gender='$gender', address='$address' $profile_pic_sql WHERE id='$user_id'";
    if (mysqli_query($con, $update_sql)) {
        $user_query = mysqli_query($con, "SELECT * FROM register WHERE id='$user_id'");
        if ($user_query && mysqli_num_rows($user_query) > 0) {
            $user = mysqli_fetch_assoc($user_query);
        }
        $_SESSION['user_name'] = $user['name'];
        $success = "Profile updated successfully!";
    } else {
        $error = "Failed to update profile.";
    }
}

// Load cart and wishlist (DB for logged-in users, session fallback otherwise)
$cart_items = [];
$wishlist_items = [];

$has_cart_table = false;
$cart_table_check = mysqli_query($con, "SHOW TABLES LIKE 'user_cart'");
if ($cart_table_check && mysqli_num_rows($cart_table_check) > 0) {
    $has_cart_table = true;
}

$has_wishlist_table = false;
$wishlist_table_check = mysqli_query($con, "SHOW TABLES LIKE 'user_wishlist'");
if ($wishlist_table_check && mysqli_num_rows($wishlist_table_check) > 0) {
    $has_wishlist_table = true;
}

if ($has_cart_table) {
    $cart_query = mysqli_query($con, "
        SELECT c.product_id AS product_id, c.quantity, p.*
        FROM user_cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = $user_id
        ORDER BY c.id DESC
    ");
    if ($cart_query) {
        while ($row = mysqli_fetch_assoc($cart_query)) {
            $cart_items[] = $row;
        }
    }
} else {
    $cart = $_SESSION['cart'] ?? [];
    if (!empty($cart)) {
        foreach ($cart as $id => $qty) {
            $product_id = (int) $id;
            $quantity = (int) $qty;
            $result = mysqli_query($con, "SELECT * FROM products WHERE id=$product_id");
            $product = $result ? mysqli_fetch_assoc($result) : null;
            if (!$product) {
                continue;
            }
            $product['product_id'] = $product_id;
            $product['quantity'] = $quantity;
            $cart_items[] = $product;
        }
    }
}

if ($has_wishlist_table) {
    $wishlist_query = mysqli_query($con, "
        SELECT w.product_id AS product_id, p.*
        FROM user_wishlist w
        JOIN products p ON w.product_id = p.id
        WHERE w.user_id = $user_id
        ORDER BY w.id DESC
    ");
    if ($wishlist_query) {
        while ($row = mysqli_fetch_assoc($wishlist_query)) {
            $wishlist_items[] = $row;
        }
    }
} else {
    $wishlist = $_SESSION['wishlist'] ?? [];
    if (!empty($wishlist)) {
        foreach ($wishlist as $id) {
            $product_id = (int) $id;
            $result = mysqli_query($con, "SELECT * FROM products WHERE id=$product_id");
            $product = $result ? mysqli_fetch_assoc($result) : null;
            if (!$product) {
                continue;
            }
            $product['product_id'] = $product_id;
            $wishlist_items[] = $product;
        }
    }
}

// Load orders once for stats + listing
$orders = [];
$orders_query = mysqli_query($con, "
    SELECT o.*, p.name AS product_name, p.image AS product_image
    FROM orders o
    JOIN products p ON o.product_id = p.id
    WHERE o.user_id = $user_id
    ORDER BY o.created_at DESC
");

if ($orders_query) {
    while ($row = mysqli_fetch_assoc($orders_query)) {
        $orders[] = $row;
    }
}

$order_count = count($orders);
$cart_count = count($cart_items);
$wishlist_count = count($wishlist_items);
$profile_pic = !empty($user['profile_pic']) ? $user['profile_pic'] : 'default.png';
?>

<?php include 'includes/header.php'; ?>

<div class="account-page">
    <div class="container py-5">
        <div class="account-hero mb-4">
            <h2>My Account</h2>
            <p>Manage your profile, orders, wishlist and cart from one place.</p>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="account-card account-sidebar h-100">
                    <div class="text-center">
                        <img src="uploads/<?= htmlspecialchars($profile_pic); ?>" alt="Profile" class="account-avatar">
                        <h4 class="mb-1"><?= htmlspecialchars($user['name']); ?></h4>
                        <p class="text-muted mb-0"><?= htmlspecialchars($user['email']); ?></p>
                    </div>

                    <div class="account-stat-grid mt-4">
                        <div class="account-stat-item">
                            <span class="account-stat-label">Cart</span>
                            <strong><?= $cart_count; ?></strong>
                        </div>
                        <div class="account-stat-item">
                            <span class="account-stat-label">Wishlist</span>
                            <strong><?= $wishlist_count; ?></strong>
                        </div>
                        <div class="account-stat-item">
                            <span class="account-stat-label">Orders</span>
                            <strong><?= $order_count; ?></strong>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                            Edit Profile
                        </button>
                        <a href="logout.php" class="btn btn-outline-danger">Logout</a>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <?php if (isset($success)) { ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
                <?php } elseif (isset($error)) { ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
                <?php } ?>

                <div class="account-card mb-4">
                    <div class="account-section-head">
                        <h5>Profile Details</h5>
                    </div>
                    <div class="account-info-grid">
                        <div class="account-info-item">
                            <span>Name</span>
                            <strong><?= htmlspecialchars($user['name']); ?></strong>
                        </div>
                        <div class="account-info-item">
                            <span>Email</span>
                            <strong><?= htmlspecialchars($user['email']); ?></strong>
                        </div>
                        <div class="account-info-item">
                            <span>Mobile</span>
                            <strong><?= htmlspecialchars($user['mobile']); ?></strong>
                        </div>
                        <div class="account-info-item">
                            <span>Gender</span>
                            <strong><?= htmlspecialchars(ucfirst($user['gender'])); ?></strong>
                        </div>
                        <div class="account-info-item" style="grid-column: span 2;">
                            <span>Address</span>
                            <strong><?= htmlspecialchars($user['address'] ?? ''); ?></strong>
                        </div>
                    </div>
                </div>

                <div class="account-card mb-4">
                    <div class="account-section-head">
                        <h5>My Cart</h5>
                    </div>

                    <div class="row g-3">
                        <?php
                        $total = 0;
                        if (!empty($cart_items)) {
                            foreach ($cart_items as $product) {
                                $product_id = (int) $product['product_id'];
                                $quantity = (int) $product['quantity'];
                                $price = (float) $product['price'];
                                $subtotal = $price * $quantity;
                                $total += $subtotal;
                        ?>
                            <div class="col-md-6 col-xl-4">
                                <div class="card product-card h-100">
                                    <a href="product.php?id=<?= (int) $product['id']; ?>">
                                        <img src="img/<?= htmlspecialchars($product['image']); ?>" class="card-img-top" alt="<?= htmlspecialchars($product['name']); ?>">
                                    </a>
                                    <div class="card-body text-center">
                                        <h6 class="card-title mb-1">
                                            <a href="product.php?id=<?= (int) $product['id']; ?>" class="text-decoration-none text-dark">
                                                <?= htmlspecialchars($product['name']); ?>
                                            </a>
                                        </h6>
                                        <p class="text-muted mb-1"><?= htmlspecialchars($product['brand']); ?></p>
                                        <p class="fw-bold mb-1">$<?= number_format($price, 2); ?></p>
                                        <p class="small mb-1">Qty: <strong><?= $quantity; ?></strong></p>
                                        <p class="text-success small">Subtotal: $<?= number_format($subtotal, 2); ?></p>
                                        <div class="d-flex justify-content-center gap-2 mt-2">
                                            <a href="cart.php?remove=<?= $product_id; ?>" class="btn btn-danger btn-sm">Remove</a>
                                            <a href="checkout.php" class="btn btn-success btn-sm">Buy Now</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php
                            }
                        } else {
                            echo '<div class="col-12"><div class="account-empty">Cart is empty.</div></div>';
                        }
                        ?>
                    </div>

                    <?php if ($total > 0) { ?>
                        <div class="account-total mt-3">
                            <span>Cart Total</span>
                            <strong>$<?= number_format($total, 2); ?></strong>
                        </div>
                    <?php } ?>
                </div>

                <div class="account-card mb-4">
                    <div class="account-section-head">
                        <h5>My Wishlist</h5>
                    </div>

                    <div class="row g-3">
                        <?php
                        if (!empty($wishlist_items)) {
                            foreach ($wishlist_items as $product) {
                        ?>
                            <div class="col-md-6 col-xl-4">
                                <div class="card product-card h-100">
                                    <a href="product.php?id=<?= (int) $product['id']; ?>">
                                        <img src="img/<?= htmlspecialchars($product['image']); ?>" class="card-img-top" alt="<?= htmlspecialchars($product['name']); ?>">
                                    </a>
                                    <div class="card-body text-center">
                                        <h6 class="card-title mb-1">
                                            <a href="product.php?id=<?= (int) $product['id']; ?>" class="text-decoration-none text-dark">
                                                <?= htmlspecialchars($product['name']); ?>
                                            </a>
                                        </h6>
                                        <p class="text-muted mb-1"><?= htmlspecialchars($product['brand']); ?></p>
                                        <p class="fw-bold mb-2">
                                            $<?= number_format((float) $product['price'], 2); ?>
                                            <?php if ((float) $product['old_price'] > 0) { ?>
                                                <span class="text-decoration-line-through text-muted fs-6">$<?= number_format((float) $product['old_price'], 2); ?></span>
                                            <?php } ?>
                                        </p>
                                        <div class="d-flex justify-content-center gap-2">
                                            <a href="cart.php?add=<?= (int) $product['id']; ?>" class="btn btn-primary btn-sm">Add to Cart</a>
                                            <a href="wishlist.php?remove=<?= (int) $product['id']; ?>" class="btn btn-outline-danger btn-sm">
                                                <i class="fa-solid fa-heart"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php
                            }
                        } else {
                            echo '<div class="col-12"><div class="account-empty">Wishlist is empty.</div></div>';
                        }
                        ?>
                    </div>
                </div>

                <div class="account-card">
                    <div class="account-section-head">
                        <h5>My Orders</h5>
                        <a href="my_orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>

                    <?php if (!empty($orders)) { ?>
                        <div class="account-orders-wrap">
                            <?php foreach ($orders as $order) { ?>
                                <div class="account-order-item">
                                    <img src="img/<?= htmlspecialchars($order['product_image']); ?>" alt="<?= htmlspecialchars($order['product_name']); ?>" class="account-order-img">
                                    <div class="account-order-content">
                                        <h6><?= htmlspecialchars($order['product_name']); ?></h6>
                                        <?php if (!empty($order['size'])) { ?>
                                            <p>Size: <?= htmlspecialchars($order['size']); ?></p>
                                        <?php } ?>
                                        <p>Qty: <?= (int) $order['quantity']; ?></p>
                                        <p>Ordered on: <?= date('d M Y', strtotime($order['created_at'])); ?></p>
                                    </div>
                                    <div class="account-order-price">$<?= number_format((float) $order['total_price'], 2); ?></div>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <div class="account-empty">You have no orders yet.</div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- EDIT PROFILE MODAL -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mobile</label>
                        <input type="text" name="mobile" class="form-control" value="<?= htmlspecialchars($user['mobile']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select" required>
                            <option value="male" <?= $user['gender'] == 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?= $user['gender'] == 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?= $user['gender'] == 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="3" required><?= htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Profile Picture</label>
                        <input type="file" name="profile_pic" class="form-control">
                        <?php if (!empty($user['profile_pic'])) { ?>
                            <img src="uploads/<?= htmlspecialchars($user['profile_pic']); ?>" width="80" class="mt-2 rounded-circle" alt="Profile Preview">
                        <?php } ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_profile" class="btn btn-success rounded-pill px-4">Update</button>
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
