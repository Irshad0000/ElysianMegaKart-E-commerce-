<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db_config.php'; // Include DB config to fetch user info

// Auth gate: force login/guest/admin before accessing site pages
$current_page = basename($_SERVER['PHP_SELF']);
$auth_whitelist = ['login.php','loginprocess.php','register.php','guest_login.php','forget.php','verify_otp.php','reset_password.php','verify_email.php'];
$is_logged_in_user = isset($_SESSION['user_id']);
$is_guest = !empty($_SESSION['is_guest']);
$user_role = $_SESSION['user_role'] ?? 'user';

if (!$is_logged_in_user && !$is_guest && !in_array($current_page, $auth_whitelist, true)) {
    header('Location: login.php');
    exit();
}

// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Count cart and wishlist items
$cart_count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
$wishlist_count = isset($_SESSION['wishlist']) ? count($_SESSION['wishlist']) : 0;

// Get search query safely
$search_query = htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES);

// Get logged-in user info
$user_profile_pic = 'default.png';
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_query = mysqli_query($con, "SELECT name, profile_pic FROM register WHERE id='$user_id'");
    if ($user_query && mysqli_num_rows($user_query) > 0) {
        $user_data = mysqli_fetch_assoc($user_query);
        $user_profile_pic = !empty($user_data['profile_pic']) ? $user_data['profile_pic'] : 'default.png';
        $_SESSION['user_name'] = $user_data['name']; // store name in session for convenience
    }

    $cart_table_check = mysqli_query($con, "SHOW TABLES LIKE 'user_cart'");
    if ($cart_table_check && mysqli_num_rows($cart_table_check) > 0) {
        $cart_count_query = mysqli_query($con, "SELECT COUNT(*) AS total FROM user_cart WHERE user_id='$user_id'");
        if ($cart_count_query) {
            $cart_count_data = mysqli_fetch_assoc($cart_count_query);
            $cart_count = (int)($cart_count_data['total'] ?? 0);
        }
    }

    $wishlist_table_check = mysqli_query($con, "SHOW TABLES LIKE 'user_wishlist'");
    if ($wishlist_table_check && mysqli_num_rows($wishlist_table_check) > 0) {
        $wishlist_count_query = mysqli_query($con, "SELECT COUNT(*) AS total FROM user_wishlist WHERE user_id='$user_id'");
        if ($wishlist_count_query) {
            $wishlist_count_data = mysqli_fetch_assoc($wishlist_count_query);
            $wishlist_count = (int)($wishlist_count_data['total'] ?? 0);
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Elysian MegaKart</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <!-- jQuery is required by validation.js; load it before any dependent scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-3fjrqGv1A1kyK3mkaW1YyXd/hkUWLWf4Lm2B07wZ9Qc=" crossorigin="anonymous"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
</head>
<body>
<header>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container-fluid px-3">

            <!-- LOGO -->
            <a class="navbar-brand d-flex align-items-center me-5" href="index.php">
                <i class="fa-solid fa-cart-shopping me-2 fs-4 text-primary"></i>
                <span class="fw-bold fs-5 logo-primary">Elysian</span>
                <span class="fw-bold fs-5 ms-1">MegaKart</span>
            </a>

            <!-- TOGGLER -->
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-controls="mobileMenu">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- DESKTOP MENU -->
            <div class="collapse navbar-collapse d-none d-lg-flex" id="desktopMenu">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 gap-lg-4">
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_page == 'index.php') ? 'active' : '' ?>" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_page == 'shop.php') ? 'active' : '' ?>" href="shop.php">Shop</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_page == 'aboutus.php') ? 'active' : '' ?>" href="aboutus.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_page == 'contact.php') ? 'active' : '' ?>" href="contact.php">Contact</a>
                    </li>
                </ul>

                <!-- RIGHT SIDE -->
                <div class="d-flex align-items-center gap-4 ms-auto">

                    <!-- SEARCH -->
                    <form class="input-group" method="GET" action="<?= $current_page ?>">
                        <input type="text" name="search" class="form-control search-input" placeholder="Search products" value="<?= $search_query ?>">
                        <button class="input-group-text bg-white border-0" type="submit">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                    </form>

                    <?php if($user_role !== 'admin'): ?>
                        <!-- WISHLIST -->
                        <a href="wishlist.php" class="position-relative">
                            <i class="fa-regular fa-heart icon-btn" title="View Your Wishlist"></i>
                            <?php if ($wishlist_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?= $wishlist_count ?>
                            </span>
                            <?php endif; ?>
                        </a>

                        <!-- CART -->
                        <a href="cart.php" class="position-relative">
                            <i class="fa-solid fa-cart-shopping icon-btn" title="View Your Cart"></i>
                            <?php if ($cart_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?= $cart_count ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>

                    <!-- USER LOGIN -->
                    <?php if($is_logged_in_user): ?>
                        <div class="dropdown">
                        <a class="dropdown-toggle text-decoration-none d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <img src="uploads/<?= $user_profile_pic ?>" class="profile-img me-2" alt="Profile">
                           <span class="user-name"><?= htmlspecialchars($_SESSION['user_name']); ?></span>
                        </a>

                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php if($user_role === 'admin'): ?>
                                <li><a class="dropdown-item" href="admin_dashboard.php">Admin Dashboard</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
                                <li><a class="dropdown-item" href="my_orders.php">My Orders</a></li>
                                <li><a class="dropdown-item" href="change_password.php">Change Password</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
                            </ul>
                        </div>
                    <?php elseif($is_guest): ?>
                        <div class="dropdown">
                            <a class="dropdown-toggle text-decoration-none d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                                <img src="uploads/default.png" class="profile-img me-2" alt="Guest">
                                <span class="user-name">Guest</span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="login.php">Login as User/Admin</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php">Exit Guest</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php">
                            <i class="fa-solid fa-user icon-btn" title="Login"></i>
                        </a>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </nav>

    <!-- MOBILE OFFCANVAS MENU -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="mobileMenu" aria-labelledby="mobileMenuLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="mobileMenuLabel">Menu</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'index.php') ? 'active' : '' ?>" href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'shop.php') ? 'active' : '' ?>" href="shop.php">Shop</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'aboutus.php') ? 'active' : '' ?>" href="aboutus.php">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'contact.php') ? 'active' : '' ?>" href="contact.php">Contact</a>
                </li>
            </ul>

            <!-- SEARCH IN MOBILE -->
            <form class="input-group mt-3" method="GET" action="<?= $current_page ?>">
                <input type="text" name="search" class="form-control search-input" placeholder="Search products" value="<?= $search_query ?>">
                <button class="input-group-text bg-white border-0" type="submit">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </form>

            <!-- WISHLIST & CART MOBILE -->
            <div class="d-flex align-items-center gap-3 mt-3">
                <?php if($user_role !== 'admin'): ?>
                    <a href="wishlist.php" class="position-relative">
                        <i class="fa-regular fa-heart icon-btn"></i>
                        <?php if ($wishlist_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?= $wishlist_count ?>
                        </span>
                        <?php endif; ?>
                    </a>

                    <a href="cart.php" class="position-relative">
                        <i class="fa-solid fa-cart-shopping icon-btn"></i>
                        <?php if ($cart_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?= $cart_count ?>
                        </span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>

                <?php if($is_logged_in_user): ?>
                    <a href="profile.php" class="mt-3 d-inline-flex align-items-center" title="My Profile">
                        <img src="uploads/<?= $user_profile_pic ?>" class="profile-img profile-img-mobile" alt="Profile">
                    </a>
                <?php elseif($is_guest): ?>
                    <a href="login.php" class="mt-3 d-inline-flex align-items-center text-decoration-none text-light" title="Guest">
                        <img src="uploads/default.png" class="profile-img profile-img-mobile me-2" alt="Guest">
                        <span>Guest</span>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="mt-3 d-inline-block">
                        <i class="fa-solid fa-user icon-btn"></i>
                    </a>
                <?php endif; ?>
            </div>

            <?php if($is_logged_in_user || $is_guest): ?>
                <?php if($is_logged_in_user): ?>
                    <a href="my_orders.php" class="text-decoration-none d-inline-block mt-3">My Orders</a><br>
                    <a href="change_password.php" class="text-decoration-none d-inline-block mt-2">Change Password</a><br>
                <?php endif; ?>
                <a href="logout.php" class="text-danger text-decoration-none d-inline-block mt-3">
                    <?= $is_guest ? 'Exit Guest' : 'Logout'; ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>
