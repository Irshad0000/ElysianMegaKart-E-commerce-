<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id = (int) $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $meets_policy = preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^\\w\\s]).{8,}$/', $new_password);

    if ($new_password !== $confirm_password) {
        $error = "New password and confirm password do not match.";
    } elseif (!$meets_policy) {
        $error = "Password must be 8+ chars with uppercase, lowercase, number and symbol.";
    } else {
        $user_query = mysqli_query($con, "SELECT password FROM register WHERE id='$admin_id'");

        if ($user_query && mysqli_num_rows($user_query) === 1) {
            $user = mysqli_fetch_assoc($user_query);
            if (!password_verify($current_password, $user['password'])) {
                $error = "Current password is incorrect.";
            } elseif (password_verify($new_password, $user['password'])) {
                $error = "New password cannot be the same as current password.";
            } else {
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                if (mysqli_query($con, "UPDATE register SET password='$hashed_new_password' WHERE id='$admin_id'")) {
                    $success = "Password updated successfully.";
                } else {
                    $error = "Failed to update password. Please try again.";
                }
            }
        } else {
            $error = "Admin record not found.";
        }
    }
}
?>

<?php include 'includes/admin_header.php'; ?>

<div class="account-page">
    <div class="container py-5">
        <div class="account-hero mb-4">
            <h2>Change Password</h2>
            <p>Secure your administrator account with a fresh password.</p>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="account-card">
                    <?php if (!empty($success)) { ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
                    <?php } ?>

                    <?php if (!empty($error)) { ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
                    <?php } ?>

                    <form method="POST" novalidate class="row g-3" id="adminChangePwdForm">
                        <div class="col-12">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" minlength="8" required>
                            <div class="form-text" id="pwdPolicyAdmin">Minimum 8 chars with uppercase, lowercase, number and symbol.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                            <div class="form-text text-danger d-none" id="matchMsgAdmin">Passwords do not match.</div>
                        </div>
                        <div class="col-12 d-flex justify-content-between align-items-center">
                            <a href="admin_dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                            <button type="submit" class="btn btn-primary" id="submitAdminPwd">Update Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
  const form = document.getElementById('adminChangePwdForm');
  if (!form) return;
  const newPwd = form.elements['new_password'];
  const confirmPwd = form.elements['confirm_password'];
  const policyMsg = document.getElementById('pwdPolicyAdmin');
  const matchMsg = document.getElementById('matchMsgAdmin');
  const submitBtn = document.getElementById('submitAdminPwd');
  // Use single backslashes so the regex enforces digit, word and symbol classes correctly
  const policy = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{8,}$/;

  function validate() {
    const goodPolicy = policy.test(newPwd.value);
    const match = newPwd.value === confirmPwd.value && newPwd.value.length > 0;
    policyMsg.classList.toggle('text-success', goodPolicy);
    policyMsg.classList.toggle('text-danger', !goodPolicy && newPwd.value.length > 0);
    matchMsg.classList.toggle('d-none', match || confirmPwd.value.length === 0);
    submitBtn.disabled = !(goodPolicy && match);
  }

  newPwd.addEventListener('input', validate);
  confirmPwd.addEventListener('input', validate);
  validate();
})();
</script>

</body>
</html>
