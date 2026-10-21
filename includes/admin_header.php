<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit();
}

include_once __DIR__ . '/../db_config.php';

$admin_id = (int)($_SESSION['user_id'] ?? 0);
$admin_name = $_SESSION['user_name'] ?? 'Admin';
$admin_pic = 'default.png';

$admin_q = mysqli_query($con, "SELECT name, profile_pic FROM register WHERE id=$admin_id LIMIT 1");
if ($admin_q && mysqli_num_rows($admin_q) === 1) {
    $adm = mysqli_fetch_assoc($admin_q);
    $admin_name = $adm['name'] ?? $admin_name;
    $admin_pic = !empty($adm['profile_pic']) ? $adm['profile_pic'] : 'default.png';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Elysian Admin</title>
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <script src="js/bootstrap.bundle.min.js"></script>
</head>
<body class="bg-light">
  <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-3">
    <div class="container-fluid">
      <a class="navbar-brand d-flex align-items-center" href="admin_dashboard.php">
        <i class="fa-solid fa-gear text-primary fs-4 me-2"></i>
        <span class="fw-bold fs-5">Elysian MegaKart</span>
      </a>
      <div class="dropdown ms-auto">
        <a class="d-flex align-items-center text-decoration-none dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
          <img src="uploads/<?= htmlspecialchars($admin_pic); ?>" alt="Admin" class="rounded-circle me-2" style="width:36px;height:36px;object-fit:cover;">
          <span class="fw-semibold text-white"><?= htmlspecialchars($admin_name); ?></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="admin_profile.php">View Profile</a></li>
          <li><a class="dropdown-item" href="admin_change_password.php">Change Password</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
        </ul>
      </div>
    </div>
  </nav>
