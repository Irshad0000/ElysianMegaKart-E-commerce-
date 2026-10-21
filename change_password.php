<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Redirect admins to their dedicated page
if (($_SESSION['user_role'] ?? 'user') === 'admin') {
    header("Location: admin_change_password.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
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
        $user_query = mysqli_query($con, "SELECT password FROM register WHERE id='$user_id'");

        if ($user_query && mysqli_num_rows($user_query) === 1) {
            $user = mysqli_fetch_assoc($user_query);

            if (!password_verify($current_password, $user['password'])) {
                $error = "Current password is incorrect.";
            } elseif (password_verify($new_password, $user['password'])) {
                $error = "New password cannot be the same as current password.";
            } else {
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE register SET password='$hashed_new_password' WHERE id='$user_id'";

                if (mysqli_query($con, $update_sql)) {
                    $success = "Password updated successfully.";
                } else {
                    $error = "Failed to update password. Please try again.";
                }
            }
        } else {
            $error = "User not found.";
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-3 text-center">Change Password</h4>
                    <p class="text-muted text-center mb-4">Update your account password securely.</p>

                    <?php if (!empty($success)) { ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
                    <?php } ?>

                    <?php if (!empty($error)) { ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
                    <?php } ?>

                    <form method="POST" novalidate id="userChangePwdForm">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" minlength="8" required>
                            <div class="form-text" id="pwdPolicyUser">Minimum 8 chars with uppercase, lowercase, number and symbol.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                            <div class="form-text text-danger d-none" id="matchMsgUser">Passwords do not match.</div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="profile.php" class="btn btn-outline-secondary rounded-pill px-4">Back to Profile</a>
                            <button type="submit" class="btn btn-primary rounded-pill px-4" id="submitUserPwd">Update Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
  const form = document.getElementById('userChangePwdForm');
  if (!form) return;
  const newPwd = form.elements['new_password'];
  const confirmPwd = form.elements['confirm_password'];
  const policyMsg = document.getElementById('pwdPolicyUser');
  const matchMsg = document.getElementById('matchMsgUser');
  const submitBtn = document.getElementById('submitUserPwd');
  // JS regex literals don't need double escaping; single backslashes keep digit/word classes working
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

<?php include 'includes/footer.php'; ?>
