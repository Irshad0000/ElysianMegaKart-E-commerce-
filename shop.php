<!-- <?php include 'db_config.php'; ?> -->
<?php include 'includes/header.php'; 
$is_admin = (($_SESSION['user_role'] ?? '') === 'admin');
$is_guest = !empty($_SESSION['is_guest']);
$is_logged_in = isset($_SESSION['user_id']);

$search   = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$sort     = trim($_GET['sort'] ?? '');

$query = "SELECT * FROM products WHERE 1=1";
$params = [];
$types  = "";

/* 🔎 SMART SEARCH (name + category) */
/* 🔎 STRICT SEARCH (ONLY CATEGORY EXACT MATCH) */
if($search != ''){

    $search_lower = strtolower($search);

    // Convert singular to plural (shirt → shirts)
    if(substr($search_lower, -1) != 's'){
        $search_lower .= 's';
    }

    $query .= " AND LOWER(category) = ?";
    $params[] = $search_lower;
    $types .= "s";
}


/* 🏷 CATEGORY */
if($category != ''){
    $query .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

/* 🔃 SORT */
if($sort == "low"){
    $query .= " ORDER BY price ASC";
}
elseif($sort == "high"){
    $query .= " ORDER BY price DESC";
}
elseif($sort == "new"){
    $query .= " ORDER BY id DESC";
}
else{
    $query .= " ORDER BY id ASC";
}

/* PREPARE */
$stmt = mysqli_prepare($con, $query);

if(!empty($params)){
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<!-- SHOP BANNER -->
<section class="bg-dark text-white py-5">
    <div class="container text-center">
        <h1 class="fw-bold">Shop Our Collection</h1>
        <p class="lead">Discover the latest fashion & trending products</p>
    </div>
</section>

<main>
<section class="product-section py-5">
<div class="container">

<!-- FILTER + SORT SECTION -->
<div class="row mb-4">

<div class="col-md-4">
<form method="GET">
<input type="hidden" name="search" value="<?= $search ?>">
<input type="hidden" name="sort" value="<?= $sort ?>">

<select name="category" class="form-select" onchange="this.form.submit()">
<option value="">Filter by Category</option>
<option value="hoodies" <?= $category=='hoodies'?'selected':'' ?>>Hoodies</option>
<option value="shirts" <?= $category=='shirts'?'selected':'' ?>>Shirts</option>
<option value="pants" <?= $category=='pants'?'selected':'' ?>>Pants</option>
<option value="watches" <?= $category=='watches'?'selected':'' ?>>Watches</option>
<option value="shoes" <?= $category=='shoes'?'selected':'' ?>>Shoes</option>
<option value="electronics" <?= $category=='electronics'?'selected':'' ?>>Electronics</option>
</select>
</form>
</div>


<div class="col-md-4 text-md-end mt-3 mt-md-0">
<form method="GET">
<input type="hidden" name="search" value="<?= $search ?>">
<input type="hidden" name="category" value="<?= $category ?>">

<select name="sort" class="form-select w-auto d-inline" onchange="this.form.submit()">
<option value="">Sort by</option>
<option value="low" <?= $sort=='low'?'selected':'' ?>>Price: Low to High</option>
<option value="high" <?= $sort=='high'?'selected':'' ?>>Price: High to Low</option>
<option value="new" <?= $sort=='new'?'selected':'' ?>>Newest</option>
</select>
</form>
</div>

</div>

<!-- PRODUCTS -->
<div class="row g-4">

<?php 
if(mysqli_num_rows($result) > 0){
while($row = mysqli_fetch_assoc($result)){
?>

<div class="col-lg-3 col-md-6 col-12">
<div class="card product-card h-100">

<!-- CLICKABLE IMAGE -->
<a href="product.php?id=<?php echo $row['id']; ?>">
<img src="img/<?php echo $row['image']; ?>" class="card-img-top">
</a>

<div class="card-body text-center">

<!-- CLICKABLE TITLE -->
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
