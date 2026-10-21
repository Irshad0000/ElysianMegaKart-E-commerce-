<?php
@session_start();
include_once 'db_config.php';

if (empty($_SESSION['forgot_email']) || empty($_SESSION['forgot_otp_verified'])) {
    header("Location: forget.php");
    exit();
}

$email = $_SESSION['forgot_email'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $meets_policy = preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^\\w\\s]).{8,}$/', $new_password);

    if ($new_password !== $confirm_password) {
        $error = "New password and confirm password do not match.";
    } elseif (!$meets_policy) {
        $error = "Password must be 8+ chars with uppercase, lowercase, number and symbol.";
    } else {
        $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = mysqli_prepare($con, "UPDATE register SET password = ? WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($update_stmt, "ss", $hashed_new_password, $email);
        $updated = mysqli_stmt_execute($update_stmt);

        if ($updated && mysqli_stmt_affected_rows($update_stmt) === 1) {
            // Cleanup token and session flags
            $del_stmt = mysqli_prepare($con, "DELETE FROM password_token WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($del_stmt, "s", $email);
            mysqli_stmt_execute($del_stmt);

            unset($_SESSION['forgot_email'], $_SESSION['forgot_otp_verified']);
            $_SESSION['login_notice'] = 'Your password has been changed successfully. Please sign in.';
            header("Location: login.php");
            exit();
        } else {
            $error = "Failed to update password. Please try again.";
        }
    }
}

include 'includes/header.php';
?>

<style>
  .auth-shell {
    min-height: calc(100vh - 120px);
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 16px;
  }
  .auth-card {
    width: 100%;
    max-width: 520px;
    border-radius: 16px;
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
    border: 1px solid rgba(13,110,253,.08);
    background: #fff;
  }
  .btn-gradient { background: linear-gradient(90deg, #00d4ff, #ff6b9d); border: none; color: #fff; }
</style>

<div class="auth-shell">
  <div class="card auth-card p-4 p-md-4">
    <div class="text-center mb-3">
      <i class="fa-solid fa-lock fs-2" style="color:#00d4ff;"></i>
      <h4 class="mb-0 fw-bold">Reset Password</h4>
      <p class="text-muted small mb-0">Set a new password for <?= htmlspecialchars($email); ?>.</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3">
      <div class="col-12">
        <label class="form-label">New Password</label>
        <input type="password" name="new_password" class="form-control" minlength="8" required>
        <div class="form-text">Minimum 8 chars with uppercase, lowercase, number and symbol.</div>
      </div>
      <div class="col-12">
        <label class="form-label">Confirm New Password</label>
        <input type="password" name="confirm_password" class="form-control" minlength="8" required>
      </div>
      <div class="col-12 d-flex justify-content-between align-items-center">
        <a href="login.php" class="text-decoration-none">Back to login</a>
        <button type="submit" class="btn btn-gradient">Reset Password</button>
      </div>
    </form>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
