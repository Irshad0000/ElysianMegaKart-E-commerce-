<?php
include 'db_config.php';

// Escape helper
if (!function_exists('h')) {
    function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES); }
}

$contact_success = '';
$contact_error = '';

// Static defaults (used for first run and fallback)
$contact_default = [
    'info_label'      => 'INFORMATION',
    'title'           => 'Contact Us',
    'description'     => 'As you might expect of a company that began as a high-quality daily wears brand, we pay strict attention.',
    'address1_title'  => 'Madesh Province',
    'address1_text'   => 'Janakpurdham-24, Bashaiya Road, Dhanusa (Nepal)',
    'phone1'          => '+977 9822034486',
    'address2_title'  => 'Dhanusa District',
    'address2_text'   => 'Janakpurdham-24, Bashaiya Road, Dhanusa (Nepal)',
    'phone2'          => '+977 9822034486',
    'map_embed_url'   => 'https://www.google.com/maps/embed?pb=!3m2!1sen!2sin!4v1770671383666!5m2!1sen!2sin!6m8!1m7!1sZJV-T3TRKPWv6Im2G0AZrQ!2m2!1d26.71552711104154!2d85.91636233004483!3f90!4f0!5f0.7820865974627469'
];

// Load dynamic content (auto-create table and seed on first run)
$contact_content = $contact_default;
$contact_table_check = mysqli_query($con, "SHOW TABLES LIKE 'cms_contact'");

if ($contact_table_check && mysqli_num_rows($contact_table_check) > 0) {
    $res = mysqli_query($con, "SELECT * FROM cms_contact LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        $contact_content = array_merge($contact_default, array_filter($row, 'strlen'));
    }
} else {
    mysqli_query($con, "
        CREATE TABLE IF NOT EXISTS cms_contact (
            id INT AUTO_INCREMENT PRIMARY KEY,
            info_label VARCHAR(100) DEFAULT '',
            title VARCHAR(255) DEFAULT '',
            description TEXT,
            address1_title VARCHAR(255) DEFAULT '',
            address1_text VARCHAR(255) DEFAULT '',
            phone1 VARCHAR(100) DEFAULT '',
            address2_title VARCHAR(255) DEFAULT '',
            address2_text VARCHAR(255) DEFAULT '',
            phone2 VARCHAR(100) DEFAULT '',
            map_embed_url TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $stmt = mysqli_prepare(
        $con,
        "INSERT INTO cms_contact
        (info_label, title, description, address1_title, address1_text, phone1,
         address2_title, address2_text, phone2, map_embed_url)
         VALUES (?,?,?,?,?,?,?,?,?,?)"
    );
    if ($stmt) {
        mysqli_stmt_bind_param(
            $stmt,
            "ssssssssss",
            $contact_default['info_label'],
            $contact_default['title'],
            $contact_default['description'],
            $contact_default['address1_title'],
            $contact_default['address1_text'],
            $contact_default['phone1'],
            $contact_default['address2_title'],
            $contact_default['address2_text'],
            $contact_default['phone2'],
            $contact_default['map_embed_url']
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

if (isset($_GET['sent']) && $_GET['sent'] === '1') {
    $contact_success = 'Your message has been sent successfully.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (strlen($name) < 3) {
        $contact_error = 'Name must be at least 3 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contact_error = 'Please enter a valid email.';
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $contact_error = 'Please enter a valid 10-digit phone number.';
    } elseif (strlen($message) < 10) {
        $contact_error = 'Message must be at least 10 characters.';
    } else {
        $stmt = mysqli_prepare($con, "INSERT INTO contact_messages (name, email, phone, message) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $phone, $message);
            if (mysqli_stmt_execute($stmt)) {
                header("Location: contact.php?sent=1");
                exit();
            } else {
                $contact_error = 'Failed to send message. Please try again.';
            }
            mysqli_stmt_close($stmt);
        } else {
            $contact_error = 'Failed to send message. Please try again.';
        }
    }
}

include 'includes/header.php';
?>

<!-- ================= MAP ================= -->
<section class="map-section">
    <iframe
        src="<?= h($contact_content['map_embed_url']); ?>"
        width="600" height="450" style="border: 0" allowfullscreen="" loading="lazy"
        referrerpolicy="no-referrer-when-downgrade"></iframe>
</section>

<!-- ================= BREADCRUMB ================= -->
<div class="breadcrumb-bar">
    <div class="container">
        <nav class="breadcrumb">
            <a class="breadcrumb-item" href="index.php">Home</a>
            <span class="breadcrumb-item active">Contact</span>
        </nav>
    </div>
</div>

<!-- ================= CONTACT ================= -->
<section class="contact-section">
    <div class="container">
        <div class="row g-5 align-items-start">
            <div class="col-lg-6 contact-info">
                <span class="info-label"><?= h($contact_content['info_label']); ?></span>
                <h2 class="contact-title"><?= h($contact_content['title']); ?></h2>
                <p class="contact-desc">
                    <?= h($contact_content['description']); ?>
                </p>

                <h5><?= h($contact_content['address1_title']); ?></h5>
                <p><?= h($contact_content['address1_text']); ?></p>
                <p><?= h($contact_content['phone1']); ?></p>

                <h5><?= h($contact_content['address2_title']); ?></h5>
                <p><?= h($contact_content['address2_text']); ?></p>
                <p><?= h($contact_content['phone2']); ?></p>
            </div>

            <div class="col-lg-6">
                <?php if ($contact_success !== ''): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($contact_success); ?></div>
                <?php elseif ($contact_error !== ''): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($contact_error); ?></div>
                <?php endif; ?>

                <form class="contact-form" id="contactForm" method="POST" action="contact.php" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <input type="text" class="form-control" id="contact_name" name="name" placeholder="Name"
                                required />
                            <div class="invalid-feedback">Name must be at least 3 characters.</div>

                        </div>

                        <div class="col-md-6 mb-3">
                            <input type="email" class="form-control" id="contact_email" name="email" placeholder="Email"
                                required />
                            <div class="invalid-feedback">Please enter a valid email.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <input type="text" class="form-control" id="contact_phone" name="phone"
                            placeholder="Phone Number" required />
                        <div class="invalid-feedback">
                            Please enter a valid 10-digit phone number.
                        </div>
                    </div>

                    <div class="mb-3">
                        <textarea class="form-control" id="contact_message" name="message" placeholder="Message"
                            required></textarea>
                        <div class="invalid-feedback">
                            Message must be at least 10 characters.
                        </div>
                    </div>

                    <button type="submit">SEND MESSAGE</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
