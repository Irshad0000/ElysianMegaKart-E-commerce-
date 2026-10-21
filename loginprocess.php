<?php
session_start();
include 'db_config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = mysqli_real_escape_string($con, $_POST['email']);
    $password = $_POST['password'];
    $login_type = $_POST['login_type'] ?? 'user';
    $expected_role = ($login_type === 'admin') ? 'admin' : 'user';

    $query = "SELECT * FROM register WHERE email='$email' AND status='Active'";
    $result = mysqli_query($con, $query);

    if (mysqli_num_rows($result) > 0) {

        $user = mysqli_fetch_assoc($result);

        // Password verify (supports legacy plain-text and upgrades to hash)
        $stored = $user['password'] ?? '';
        $is_valid = password_verify($password, $stored);

        // Legacy plain-text support: if not hashed, compare directly and upgrade to hash
        if (!$is_valid && $stored !== '' && $stored === $password) {
            $is_valid = true;
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            mysqli_query($con, "UPDATE register SET password='$new_hash' WHERE id={$user['id']} LIMIT 1");
        }

        if ($is_valid) {
            if (!isset($user['role']) || $user['role'] !== $expected_role) {
                $_SESSION['login_error'] = 'Invalid username/email or password.';
                header("Location: login.php");
                exit();
            }

            // Enforce email verification only for regular users
            if ($user['role'] !== 'admin' && empty($user['email_verified'])) {
                $_SESSION['pending_verify_email'] = $email;
                $_SESSION['login_notice'] = 'Please verify your email before logging in.';
                header("Location: verify_email.php?email=" . urlencode($email));
                exit();
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            unset($_SESSION['is_guest']);

            if ($user['role'] === 'admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: index.php");
            }
            exit();

        } else {
            $_SESSION['login_error'] = 'Invalid username/email or password.';
            header("Location: login.php");
            exit();
        }

    } else {
        $_SESSION['login_error'] = 'Invalid username/email or password.';
        header("Location: login.php");
        exit();
    }
}
?>
