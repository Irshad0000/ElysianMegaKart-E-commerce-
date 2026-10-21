<?php
include 'includes/header.php';

// Helper to escape output
if (!function_exists('h')) {
    function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES); }
}

// Default content (used for first run and as fallback)
$about_default = [
    'hero_title'     => 'About Elysian MegaKart',
    'hero_subtitle'  => 'Your One-Stop Destination for Everyday Fashion & Essentials',
    'hero_image'     => 'img/group-bundle-shots.jpg',
    'who_we_are'     => "Elysian MegaKart is a modern online shopping platform dedicated to providing high-quality daily wear products at affordable prices. We believe fashion should be accessible, comfortable, and stylish for everyone.\n\nOur goal is to make your shopping experience smooth, enjoyable, and secure. From browsing to checkout, we focus on simplicity and customer satisfaction.",
    'mission'        => "At Elysian MegaKart, our mission is to provide trendy, comfortable, and affordable fashion products while maintaining quality and customer trust. We strive to continuously improve and expand our collections to meet your needs.",
    'feature1_icon'  => 'fa-truck-fast',
    'feature1_title' => 'Fast Delivery',
    'feature1_desc'  => 'We ensure quick and reliable shipping so your products reach you on time.',
    'feature2_icon'  => 'fa-shield-halved',
    'feature2_title' => 'Secure Payment',
    'feature2_desc'  => 'Your payments and personal data are protected with secure systems.',
    'feature3_icon'  => 'fa-headset',
    'feature3_title' => 'Customer Support',
    'feature3_desc'  => 'Our support team is always ready to help you with your queries.'
];

// Try to load content from DB (auto-create table and seed with defaults on first visit)
$about_content = $about_default;
$about_table_check = mysqli_query($con, "SHOW TABLES LIKE 'cms_about'");

if ($about_table_check && mysqli_num_rows($about_table_check) > 0) {
    $about_result = mysqli_query($con, "SELECT * FROM cms_about LIMIT 1");
    if ($about_result && mysqli_num_rows($about_result) > 0) {
        $row = mysqli_fetch_assoc($about_result);
        $about_content = array_merge($about_default, array_filter($row, 'strlen'));
    }
} else {
    // Create table and seed with defaults
    mysqli_query($con, "
        CREATE TABLE IF NOT EXISTS cms_about (
            id INT AUTO_INCREMENT PRIMARY KEY,
            hero_title VARCHAR(255) DEFAULT '',
            hero_subtitle VARCHAR(255) DEFAULT '',
            hero_image VARCHAR(255) DEFAULT '',
            who_we_are TEXT,
            mission TEXT,
            feature1_icon VARCHAR(100) DEFAULT 'fa-truck-fast',
            feature1_title VARCHAR(255) DEFAULT '',
            feature1_desc TEXT,
            feature2_icon VARCHAR(100) DEFAULT 'fa-shield-halved',
            feature2_title VARCHAR(255) DEFAULT '',
            feature2_desc TEXT,
            feature3_icon VARCHAR(100) DEFAULT 'fa-headset',
            feature3_title VARCHAR(255) DEFAULT '',
            feature3_desc TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $stmt = mysqli_prepare(
        $con,
        "INSERT INTO cms_about
        (hero_title, hero_subtitle, hero_image, who_we_are, mission,
         feature1_icon, feature1_title, feature1_desc,
         feature2_icon, feature2_title, feature2_desc,
         feature3_icon, feature3_title, feature3_desc)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );

    if ($stmt) {
        mysqli_stmt_bind_param(
            $stmt,
            "ssssssssssssss",
            $about_default['hero_title'],
            $about_default['hero_subtitle'],
            $about_default['hero_image'],
            $about_default['who_we_are'],
            $about_default['mission'],
            $about_default['feature1_icon'],
            $about_default['feature1_title'],
            $about_default['feature1_desc'],
            $about_default['feature2_icon'],
            $about_default['feature2_title'],
            $about_default['feature2_desc'],
            $about_default['feature3_icon'],
            $about_default['feature3_title'],
            $about_default['feature3_desc']
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

$hero_image = trim($about_content['hero_image'] ?: $about_default['hero_image']);
$hero_image = str_replace('\\', '/', $hero_image);           // normalize slashes (handles Windows copy paths)
if (strpos($hero_image, ':') !== false || str_starts_with($hero_image, '/')) {
    // If someone pasted an absolute OS path like C:/laragon/www/Elysian MegaKart/img/pic.jpg,
    // strip it down to just the filename
    $hero_image = basename($hero_image);
}

// Resolve image path: allow URLs; for relative paths try given path, uploads/, then img/
$hero_image_path = $hero_image;
if (!preg_match('~^(https?:)?//~', $hero_image) && substr($hero_image, 0, 1) !== '/') {
    if ($hero_image && file_exists(__DIR__ . '/' . $hero_image)) {
        $hero_image_path = $hero_image; // respects values like "img/pic.jpg" or "uploads/pic.jpg"
    } elseif ($hero_image && file_exists(__DIR__ . '/uploads/' . $hero_image)) {
        $hero_image_path = 'uploads/' . $hero_image;
    } elseif ($hero_image && file_exists(__DIR__ . '/img/' . $hero_image)) {
        $hero_image_path = 'img/' . $hero_image;
    }
}

// Fallback to default if still not reachable
if (!$hero_image_path || (!preg_match('~^(https?:)?//~', $hero_image_path) && !file_exists(__DIR__ . '/' . $hero_image_path))) {
    $hero_image_path = $about_default['hero_image'];
}
?>

<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h1 class="fw-bold"><?= h($about_content['hero_title']); ?></h1>
            <p class="text-muted"><?= h($about_content['hero_subtitle']); ?></p>
        </div>

        <div class="row align-items-center mb-5">
            <div class="col-lg-6">
                <img src="<?= h($hero_image_path); ?>" class="img-fluid rounded shadow" alt="About">
            </div>
            <div class="col-lg-6">
                <h3 class="fw-bold mb-3">Who We Are</h3>
                <?php foreach (explode("\n\n", $about_content['who_we_are']) as $para): ?>
                    <p><?= h($para); ?></p>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="row text-center mb-5">
            <div class="col-md-4">
                <div class="p-4 shadow rounded bg-white">
                    <i class="fa-solid <?= h($about_content['feature1_icon']); ?> fa-2x text-primary mb-3"></i>
                    <h5 class="fw-bold"><?= h($about_content['feature1_title']); ?></h5>
                    <p class="text-muted"><?= h($about_content['feature1_desc']); ?></p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="p-4 shadow rounded bg-white">
                    <i class="fa-solid <?= h($about_content['feature2_icon']); ?> fa-2x text-primary mb-3"></i>
                    <h5 class="fw-bold"><?= h($about_content['feature2_title']); ?></h5>
                    <p class="text-muted"><?= h($about_content['feature2_desc']); ?></p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="p-4 shadow rounded bg-white">
                    <i class="fa-solid <?= h($about_content['feature3_icon']); ?> fa-2x text-primary mb-3"></i>
                    <h5 class="fw-bold"><?= h($about_content['feature3_title']); ?></h5>
                    <p class="text-muted"><?= h($about_content['feature3_desc']); ?></p>
                </div>
            </div>
        </div>

        <div class="text-center">
            <h3 class="fw-bold mb-3">Our Mission</h3>
            <p class="text-muted mx-auto" style="max-width: 700px;">
                <?= h($about_content['mission']); ?>
            </p>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
