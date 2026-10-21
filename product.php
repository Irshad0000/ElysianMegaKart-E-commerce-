<?php
session_start();
include 'db_config.php';

$id = (int)($_GET['id'] ?? 0);
$review_status = $_GET['review'] ?? '';
$review_error = '';

$stmt = mysqli_prepare($con, "SELECT * FROM products WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($result);

if(!$product){
    include 'includes/header.php';
    echo "<h3 class='text-center mt-5'>Product Not Found</h3>";
    include 'includes/footer.php';
    exit();
}

$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? (int)$_SESSION['user_id'] : 0;
$is_admin = (($_SESSION['user_role'] ?? '') === 'admin');
$is_guest = !empty($_SESSION['is_guest']);
$can_review = false;
$my_review = null;

if ($is_logged_in) {
    $has_status_column = false;
    $status_col_query = mysqli_query($con, "SHOW COLUMNS FROM orders LIKE 'order_status'");
    if ($status_col_query && mysqli_num_rows($status_col_query) > 0) {
        $has_status_column = true;
    }

    $purchase_sql = $has_status_column
        ? "SELECT id FROM orders WHERE user_id=$user_id AND product_id=$id AND COALESCE(order_status, 'Pending')='Delivered' LIMIT 1"
        : "SELECT id FROM orders WHERE user_id=$user_id AND product_id=$id LIMIT 1";

    $purchase_query = mysqli_query($con, $purchase_sql);
    $can_review = $purchase_query && mysqli_num_rows($purchase_query) > 0;

    $my_review_query = mysqli_query($con, "SELECT rating, review_text FROM product_reviews WHERE product_id=$id AND user_id=$user_id LIMIT 1");
    if ($my_review_query && mysqli_num_rows($my_review_query) > 0) {
        $my_review = mysqli_fetch_assoc($my_review_query);
    }
}

if ($is_logged_in && $can_review && isset($_POST['submit_review'])) {
    $rating = (int)($_POST['rating'] ?? 0);
    $review_text = trim($_POST['review_text'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $review_error = "Rating must be between 1 and 5 stars.";
    } elseif ($review_text === '') {
        $review_error = "Please add a short review.";
    } else {
        $review_stmt = mysqli_prepare(
            $con,
            "INSERT INTO product_reviews (product_id, user_id, rating, review_text, created_at)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE rating=VALUES(rating), review_text=VALUES(review_text), created_at=NOW()"
        );
        mysqli_stmt_bind_param($review_stmt, "iiis", $id, $user_id, $rating, $review_text);

        if (mysqli_stmt_execute($review_stmt)) {
            header("Location: product.php?id=$id&review=saved");
            exit();
        }

        $review_error = "We couldn't save your review. Please try again.";
    }
}

$review_stats_query = mysqli_query($con, "SELECT COUNT(*) AS total_reviews, AVG(rating) AS avg_rating FROM product_reviews WHERE product_id=$id");
$review_stats = $review_stats_query ? mysqli_fetch_assoc($review_stats_query) : ['total_reviews' => 0, 'avg_rating' => 0];
$avg_rating = isset($review_stats['avg_rating']) ? (float)$review_stats['avg_rating'] : 0;
$total_reviews = isset($review_stats['total_reviews']) ? (int)$review_stats['total_reviews'] : 0;

$rating_breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$breakdown_query = mysqli_query($con, "SELECT rating, COUNT(*) as count FROM product_reviews WHERE product_id=$id GROUP BY rating");
if ($breakdown_query) {
    while ($row = mysqli_fetch_assoc($breakdown_query)) {
        $rating = (int)$row['rating'];
        if (isset($rating_breakdown[$rating])) {
            $rating_breakdown[$rating] = (int)$row['count'];
        }
    }
}

$reviews_query = mysqli_query(
    $con,
    "SELECT pr.rating, pr.review_text, pr.created_at, r.name
     FROM product_reviews pr
     LEFT JOIN register r ON pr.user_id = r.id
     WHERE pr.product_id = $id
     ORDER BY pr.created_at DESC"
);
?>

<?php include 'includes/header.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card product-card shadow-sm">
                <div class="row g-0">

                    <div class="col-md-6 d-flex align-items-center justify-content-center p-4">
                        <img src="img/<?= htmlspecialchars($product['image']); ?>"
                             style="max-width:100%; max-height:420px; object-fit:contain; border-radius:10px;"
                             class="img-fluid" alt="<?= htmlspecialchars($product['name']); ?>">
                    </div>

                    <div class="col-md-6">
                        <div class="card-body p-4">
                            <h4 class="card-title"><?= htmlspecialchars($product['name']); ?></h4>
                            <p class="text-muted mb-2"><?= htmlspecialchars($product['brand']); ?></p>
                            <h5 class="fw-bold text-success mb-3">
                                $<?= number_format((float)$product['price'], 2); ?>
                                <?php if((float)$product['old_price'] > 0){ ?>
                                    <span class="text-decoration-line-through text-muted fs-6">
                                        $<?= number_format((float)$product['old_price'], 2); ?>
                                    </span>
                                <?php } ?>
                            </h5>

                            <p class="mb-2">
                                <strong><?= number_format($avg_rating, 1); ?>/5</strong>
                                (<?= $total_reviews; ?> review<?= $total_reviews === 1 ? '' : 's'; ?>)
                            </p>

                            <div class="mb-3">
                                <label class="form-label fw-semibold mb-2">Select Size</label>
                                <select id="sizeSelect" class="form-select w-75">
                                    <option value="">Choose Size</option>
                                    <option>S</option>
                                    <option>M</option>
                                    <option>L</option>
                                    <option>XL</option>
                                </select>
                            </div>

                            <div class="mb-4 d-flex flex-column align-items-start">
                                <label class="form-label fw-semibold mb-2">Quantity</label>
                                <input type="number" id="qtyInput" class="form-control" value="1" min="1" style="width: 100px;">
                            </div>

                            <div class="d-flex gap-2 mt-4">
                                <button class="btn btn-success" id="buyNowBtn">Buy Now</button>
                                  <?php if(!$is_admin): ?>
                                      <?php if($is_logged_in): ?>
                                      <a href="cart.php?add=<?= (int)$product['id']; ?>" class="btn btn-primary">Add to Cart</a>
                                      <?php else: ?>
                                      <a href="login.php" class="btn btn-primary">Add to Cart</a>
                                      <?php endif; ?>
                                  <?php endif; ?>
                                    <a href="wishlist.php?add=<?= (int)$product['id']; ?>" class="btn btn-outline-secondary">
                                        <i class="fa-regular fa-heart"></i>
                                    </a>
                              </div>

                            <div class="mt-3 p-3 border rounded bg-light">
                                <div class="fw-semibold small mb-1">Secure payment options</div>
                                <div class="d-flex flex-wrap align-items-center gap-3 small text-muted">
                                    <span><i class="fa-solid fa-credit-card me-1 text-primary"></i>Credit / Debit Cards</span>
                                    <span><i class="fa-brands fa-cc-visa me-1 text-primary"></i>Net Banking</span>
                                    <span><i class="fa-solid fa-mobile-screen-button me-1 text-success"></i>UPI / Wallets</span>
                                    <span><i class="fa-solid fa-truck-fast me-1 text-warning"></i>Cash on Delivery</span>
                                    <span><i class="fa-solid fa-shield-halved me-1 text-success"></i>Protected checkout</span>
                                </div>
                            </div>

                            <p class="mt-4 text-muted"><?= htmlspecialchars($product['description']); ?></p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="row justify-content-center mt-4 mb-5">
        <div class="col-lg-10">
            <div class="card shadow-sm border-0 rounded-4 p-4">
                <h5 class="fw-bold mb-3">Product Reviews</h5>

                <div class="d-flex flex-wrap gap-3 align-items-center mb-3">
                    <div class="bg-light border rounded p-3 d-flex align-items-center gap-3" style="min-width:220px;">
                        <div class="display-6 fw-bold mb-0"><?= number_format($avg_rating, 1); ?></div>
                        <div>
                            <div class="text-warning" style="line-height:1;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?= $i <= round($avg_rating) ? '&#9733;' : '&#9734;'; ?>
                                <?php endfor; ?>
                            </div>
                            <div class="text-muted small"><?= $total_reviews; ?> review<?= $total_reviews === 1 ? '' : 's'; ?></div>
                        </div>
                    </div>

                    <div class="flex-grow-1" style="min-width:260px;">
                        <?php for ($i = 5; $i >= 1; $i--):
                            $count = $rating_breakdown[$i] ?? 0;
                            $percent = $total_reviews > 0 ? round(($count / $total_reviews) * 100) : 0;
                        ?>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="small text-muted" style="width:42px;">
                                    <?= $i; ?> ★
                                </span>
                                <div class="progress flex-grow-1" style="height:8px;">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $percent; ?>%" aria-valuenow="<?= $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <span class="small text-muted" style="width:40px; text-align:right;">
                                    <?= $count; ?>
                                </span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <?php if ($review_status === 'saved'): ?>
                    <div class="alert alert-success py-2">Review saved successfully.</div>
                <?php endif; ?>

                <?php if ($review_error !== ''): ?>
                    <div class="alert alert-danger py-2"><?= htmlspecialchars($review_error); ?></div>
                <?php endif; ?>

                <?php if ($is_logged_in && $can_review): ?>
                    <form method="POST" class="mb-4">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <select name="rating" class="form-select" required>
                                    <option value="">Select Rating</option>
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <option value="<?= $i; ?>" <?= ((int)($my_review['rating'] ?? 0) === $i) ? 'selected' : ''; ?>><?= $i; ?> Star</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-9">
                                <input type="text" name="review_text" class="form-control" maxlength="500" placeholder="Share a crisp, helpful review"
                                    value="<?= htmlspecialchars($my_review['review_text'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <button type="submit" name="submit_review" class="btn btn-dark mt-3">Post Review</button>
                    </form>
                <?php elseif (!$is_logged_in): ?>
                    <p class="text-muted">Sign in to share your experience. <a href="login.php">Login</a></p>
                <?php else: ?>
                    <p class="text-muted mb-3">You can review this item once a delivered order is recorded.</p>
                <?php endif; ?>

                <?php if ($reviews_query && mysqli_num_rows($reviews_query) > 0): ?>
                    <?php while ($review = mysqli_fetch_assoc($reviews_query)): ?>
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <strong><?= htmlspecialchars($review['name'] ?? 'User'); ?></strong>
                                <small class="text-muted"><?= date('d M Y', strtotime($review['created_at'])); ?></small>
                            </div>
                            <div class="text-warning mb-2">
                                <?php
                                $r = (int)$review['rating'];
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $r ? '&#9733;' : '&#9734;';
                                }
                                ?>
                            </div>
                            <p class="mb-0"><?= htmlspecialchars($review['review_text']); ?></p>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-muted mb-0">No reviews yet. Be the first to review this product.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('buyNowBtn').addEventListener('click', function() {
    const size = document.getElementById('sizeSelect').value;
    const qty = document.getElementById('qtyInput').value;

    if(size === ''){
        alert("Please select a size");
        return;
    }

    if(qty < 1){
        alert("Quantity must be at least 1");
        return;
    }

    const productId = <?= (int)$product['id']; ?>;
    window.location.href = `checkout.php?buy=${productId}&qty=${qty}&size=${size}`;
});
</script>

<?php include 'includes/footer.php'; ?>
