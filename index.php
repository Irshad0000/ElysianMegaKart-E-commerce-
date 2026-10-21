<?php
include 'includes/header.php';
$is_admin = (($_SESSION['user_role'] ?? '') === 'admin');
$is_guest = !empty($_SESSION['is_guest']);
$is_logged_in = isset($_SESSION['user_id']);
include 'db_config.php';?>
<!-- SLIDER SECTION -->
<section class="slider-section">
    <div id="mainCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="3000">
        
        <div class="carousel-indicators">
            <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="0" class="active"></button>
            <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="1"></button>
            <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="2"></button>
        </div>

        <div class="carousel-inner">
            <div class="carousel-item active">
                <img src="img/group-bundle-shots.jpg" class="slider-img">
            </div>
            <div class="carousel-item">
                <img src="img/blog9-img2.webp" class="slider-img">
            </div>
            <div class="carousel-item">
                <img src="img/accessories.webp" class="slider-img">
            </div>
        </div>

        <button class="carousel-control-prev" type="button" data-bs-target="#mainCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#mainCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
        </button>
    </div>
</section>

<!-- PRODUCTS -->
<main>
<section class="product-section">
<div class="container mt-5">
<h2 class="text-center mb-4">Wear</h2>

<div class="row g-4 mb-5">

<?php
$search = trim($_GET['search'] ?? '');

$query = "SELECT * FROM products";
$params = [];
$types = "";

if($search != ''){
    $search_lower = strtolower($search);

    if(substr($search_lower, -1) != 's'){
        $search_lower .= 's';
    }

    $query .= " WHERE LOWER(category) = ?";
    $params[] = $search_lower;
    $types .= "s";
}

$query .= " ORDER BY id DESC LIMIT 16";

$stmt = mysqli_prepare($con, $query);

if(!empty($params)){
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($result) > 0){
    while($row = mysqli_fetch_assoc($result)){
?>

<div class="col-lg-3 col-md-6 col-12">
<div class="card product-card h-100">

<a href="product.php?id=<?php echo $row['id']; ?>">
<img src="img/<?php echo $row['image']; ?>" class="card-img-top">
</a>

<div class="card-body text-center">

<h6 class="card-title">
<a href="product.php?id=<?php echo $row['id']; ?>" 
style="text-decoration:none; color:black;">
<?php echo $row['name']; ?>
</a>
</h6>

<p class="text-muted mb-1">
<?php echo $row['brand']; ?>
</p>

<p class="fw-bold">
$<?php echo $row['price']; ?>
<?php if($row['old_price'] > 0){ ?>
<span class="text-decoration-line-through text-muted">
$<?php echo $row['old_price']; ?>
</span>
<?php } ?>
</p>

<div class="d-flex justify-content-center gap-2">

<?php if(!$is_admin): ?>
    <?php if($is_logged_in): ?>
    <a href="cart.php?add=<?php echo $row['id']; ?>" 
    class="btn btn-primary btn-sm">
    Add to Cart
    </a>
    <?php else: ?>
    <a href="login.php" 
    class="btn btn-primary btn-sm">
    Add to Cart
    </a>
    <?php endif; ?>

<a href="wishlist.php?add=<?php echo $row['id']; ?>" 
class="btn btn-outline-secondary btn-sm">
<i class="fa-regular fa-heart"></i>
</a>
<?php endif; ?>

</div>

</div>
</div>
</div>

<?php
    }
}else{
    echo "<h4 class='text-center'>No Products Found</h4>";
}
?>

</div>
</div>
</section>
</main>

<?php include 'includes/footer.php'; ?>
