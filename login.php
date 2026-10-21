<?php
session_start();

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: index.php');
    }
    exit();
}
?>

<!-- Standard user login uses site header; admin has dedicated page -->
<?php include 'includes/header.php'; ?>
<style>
  .card-login {
    border-radius: 16px;
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
  }
  .btn-gradient {
    background: linear-gradient(90deg, #00d4ff, #ff6b9d);
    border: none;
    color: #fff;
  }
  .brand-icon {
    color: #00d4ff;
  }
  @media (max-width: 480px) {
    .card-login {
      padding: 18px;
    }
  }
</style>

<div class="d-flex align-items-center justify-content-center" style="min-height:calc(100vh - 120px); background:#f8f9fa; padding:40px 15px;">
  <div class="card card-login p-4" style="max-width:460px; width:100%;">
    <?php if (!empty($_SESSION['login_error'])): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['login_error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php unset($_SESSION['login_error']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['login_success'])): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['login_success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php unset($_SESSION['login_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['login_notice'])): ?>
      <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['login_notice']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php unset($_SESSION['login_notice']); ?>
    <?php endif; ?>

    <div class="text-center mb-3">
      <i class="fa-solid fa-cart-shopping fs-2 brand-icon"></i>
      <h4 class="mb-0 fw-bold">Elysian <span style="color:#ff6b9d">MegaKart</span></h4>
      <p class="text-muted small mb-2">Choose how you want to continue</p>
      <a href="guest_login.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">Continue as Guest</a>
    </div>

    <ul class="nav nav-pills nav-fill mb-3" id="loginTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="user-tab" data-bs-toggle="pill" data-bs-target="#user-panel" type="button" role="tab">User Login</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="admin-tab" data-bs-toggle="pill" data-bs-target="#admin-panel" type="button" role="tab">Admin Login</button>
      </li>
    </ul>

    <div class="tab-content" id="loginTabsContent">
      <div class="tab-pane fade show active" id="user-panel" role="tabpanel" aria-labelledby="user-tab" tabindex="0">
        <form action="loginprocess.php" method="post">
          <input type="hidden" name="login_type" value="user">
          <div class="form-floating mb-3">
            <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
            <label for="email">Email address</label>
            <div class="invalid-feedback">Please enter a valid email.</div>
          </div>

          <div class="form-floating mb-3">
            <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
            <label for="password">Password</label>
            <div class="invalid-feedback">Min 8 chars, 1 uppercase, 1 lowercase, 1 number & 1 special char.</div>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="forget.php" class="small">Forgot Password?</a>
          </div>

          <div class="d-grid mb-2">
            <button class="btn btn-gradient btn-lg" type="submit">Sign in as User</button>
          </div>
        </form>
      </div>

      <div class="tab-pane fade" id="admin-panel" role="tabpanel" aria-labelledby="admin-tab" tabindex="0">
        <form action="loginprocess.php" method="post">
          <input type="hidden" name="login_type" value="admin">
          <div class="form-floating mb-3">
            <input type="email" class="form-control" id="admin_email" name="email" placeholder="admin@example.com" required>
            <label for="admin_email">Admin Email</label>
            <div class="invalid-feedback">Please enter a valid admin email.</div>
          </div>

          <div class="form-floating mb-3">
            <input type="password" class="form-control" id="admin_password" name="password" placeholder="Password" required>
            <label for="admin_password">Admin Password</label>
            <div class="invalid-feedback">Min 8 chars, 1 uppercase, 1 lowercase, 1 number & 1 special char.</div>
          </div>

          <div class="d-grid mb-2">
            <button class="btn btn-dark btn-lg" type="submit">Sign in as Admin</button>
          </div>
        </form>
      </div>
    </div>

    <div class="text-center mt-3">
      <small>Don't have an account? <a href="register.php">Sign Up</a></small>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
