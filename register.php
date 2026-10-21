<?php
@session_start();
include 'db_config.php';
if (file_exists(__DIR__ . '/mailer.php')) {
    include_once 'mailer.php';
}
include_once 'PHPMailer/PHPMailer.php';
include_once 'PHPMailer/SMTP.php';
include_once 'PHPMailer/Exception.php';

// Ensure email verification columns exist (compatible with MySQL versions without IF NOT EXISTS)
function ensureColumn($con, $table, $column, $definition) {
    $check = mysqli_query($con, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($check && mysqli_num_rows($check) === 0) {
        mysqli_query($con, "ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}
ensureColumn($con, 'register', 'email_verified', "TINYINT(1) NOT NULL DEFAULT 0");
ensureColumn($con, 'register', 'email_verify_token', "VARCHAR(64) DEFAULT NULL");
ensureColumn($con, 'register', 'email_verify_sent_at', "DATETIME DEFAULT NULL");
ensureColumn($con, 'register', 'email_verify_code', "VARCHAR(6) DEFAULT NULL");
ensureColumn($con, 'register', 'email_verify_expires', "DATETIME DEFAULT NULL");
ensureColumn($con, 'register', 'email_verify_attempts', "INT DEFAULT 0");

// Helper: send verification email (6-digit code)
function sendVerificationMail($toEmail, $name, $code) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = dirname($_SERVER['PHP_SELF']);
    $dir = ($dir === '/' || $dir === '\\') ? '' : rtrim(str_replace('\\', '/', $dir), '/');
    $base = rtrim($scheme . '://' . $host . $dir, '/');
    $verifyLink = $base . "/verify_email.php?email=" . urlencode($toEmail);

    if (function_exists('sendEmail')) {
        $subject = "Verify your email - Elysian MegaKart";
        $body = function_exists('buildOtpEmailTemplate')
            ? buildOtpEmailTemplate(
                'Verify your email',
                $code,
                "Hi {$name}, use this verification code to activate your account.",
                [
                    'Code expires in 10 minutes.',
                    'Enter it on the same device you requested it from.',
                    'If you did not try to sign up, you can ignore this email.'
                ],
                'Open verification page',
                $verifyLink
            )
            : "Hi {$name},<br>Your verification code is <strong>{$code}</strong>.<br>It expires in 10 minutes.<br>You can also open the verification page here: <a href='{$verifyLink}'>Verify Email</a>.";
        return sendEmail($toEmail, $subject, $body, "");
    }

    $smtpHost = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
    $smtpUser = $_ENV['SMTP_USER'] ?? 'alimdirshad487@gmail.com';
    $smtpPass = $_ENV['SMTP_PASS'] ?? 'xuuk xhwl ktpm omwd';
    $smtpPort = (int)($_ENV['SMTP_PORT'] ?? 587);
    $smtpFrom = $_ENV['SMTP_FROM'] ?? ($smtpUser ?: 'no-reply@example.com');

    if ($smtpUser === '' || $smtpPass === '') {
        return false;
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtpPort;

        $mail->setFrom($smtpFrom, 'Elysian MegaKart');
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = "Verify your email - Elysian MegaKart";
        $mail->Body = function_exists('buildOtpEmailTemplate')
            ? buildOtpEmailTemplate(
                'Verify your email',
                $code,
                "Hi {$name}, use this verification code to activate your account.",
                [
                    'Code expires in 10 minutes.',
                    'Enter it on the same device you requested it from.',
                    'If you did not try to sign up, you can ignore this email.'
                ],
                'Open verification page',
                $verifyLink
            )
            : "Hi {$name},<br>Your verification code is <strong>{$code}</strong>.<br>It expires in 10 minutes.<br>You can also open the verification page here: <a href='{$verifyLink}'>Verify Email</a>.";
        $mail->AltBody = "Your verification code is {$code}. It expires in 10 minutes. Verify at: {$verifyLink}";
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

if(isset($_POST['reg-btn'])){

    $name = $_POST['name'];
    $email = $_POST['email'];
    $mobile = $_POST['phone'];
    $address = mysqli_real_escape_string($con, $_POST['address']);
    $gender = $_POST['gender'];
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];

    // Password match check
    if($password !== $confirm){
        echo "<script>alert('Passwords do not match');</script>";
        exit();
    }

    // Email duplicate check
    $check_email = "SELECT * FROM register WHERE email='$email'";
    $result = mysqli_query($con, $check_email);

    if(mysqli_num_rows($result) > 0){
        echo "<script>alert('Email already exists');</script>";
        exit();
    }

    // Password Hashing
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Profile Picture Upload
    $profile_pic = $_FILES['profile_pic']['name'];
    $tmp_name = $_FILES['profile_pic']['tmp_name'];

    // Rename image to avoid duplicate name
    $new_image_name = time() . "_" . $profile_pic;

    $upload_path = "uploads/" . $new_image_name;

    move_uploaded_file($tmp_name, $upload_path);

    // Insert Query
    $otp_code = str_pad((string)rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $now = date("Y-m-d H:i:s");
    $otp_expires = date("Y-m-d H:i:s", strtotime('+10 minutes'));

    $insert = "INSERT INTO register 
        (name, email, password, mobile, address, gender, profile_pic, role, status, email_verified, email_verify_token, email_verify_sent_at, email_verify_code, email_verify_expires, email_verify_attempts) 
        VALUES 
        ('$name', '$email', '$hashed_password', '$mobile', '$address', '$gender', '$new_image_name', 'user', 'Active', 0, NULL, '$now', '$otp_code', '$otp_expires', 0)";

    if(mysqli_query($con, $insert)){
        $_SESSION['pending_verify_email'] = $email;
        $_SESSION['verify_notice'] = 'We sent a 6-digit code to your email. Enter it to verify your account.';

        if (!sendVerificationMail($email, $name, $otp_code)) {
            $_SESSION['verify_notice'] = 'Registered successfully. Could not send email right now; please request a new code.';
        }

        $redirectUrl = "verify_email.php?email=" . urlencode($email);
        if (!headers_sent()) {
            header("Location: " . $redirectUrl);
            exit();
        }
        // Fallbacks in case headers were already sent by hosting/env
        echo "<meta http-equiv='refresh' content='0;url={$redirectUrl}'>";
        echo "<script>window.location.href = '" . $redirectUrl . "';</script>";
        // Last-resort: render the verify page inline so user can enter code without navigation
        $_GET['email'] = $email;
        include 'verify_email.php';
        exit();
    } else {
        echo "Error: " . mysqli_error($con);
    }
}
?>

<?php include 'includes/header.php'; ?>

<style>
  .card-register { 
    border-radius:16px; box-shadow:0 18px 45px rgba(15,23,42,0.12); 
}
  .btn-gradient { 
    background: linear-gradient(90deg,#00d4ff,#ff6b9d); border:none; color:#fff; 
}
  .brand-icon { 
    color:#00d4ff; 
}
  @media (max-width:480px){ 
    .card-register{ padding:18px } 
    }
</style>

<div class="d-flex align-items-center justify-content-center" style="min-height:calc(100vh - 120px); background:#f8f9fa; padding:40px 15px;">
  <div class="card card-register p-4" style="max-width:520px; width:100%;">
    <div class="text-center mb-3">
      <i class="fa-solid fa-cart-shopping fs-2 brand-icon"></i>
      <h4 class="mb-0 fw-bold">Create Account</h4>
      <p class="text-muted small">Join Elysian MegaKart</p>
    </div>

    <form action="register.php" method="post" enctype="multipart/form-data">
      <div class="row g-2">
        <div class="col-12 form-floating mb-2">
          <input type="text" class="form-control" id="name" name="name" placeholder="Full name" required>
          <label for="name">Full name</label>
        <div class="invalid-feedback">Name must be at least 3 characters.</div>
        </div>

        <div class="col-12 form-floating mb-2">
          <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
          <label for="email">Email address</label>
          <div class="invalid-feedback">Please enter a valid email.</div>
        </div>

        <div class="col-12 form-floating mb-2">
          <input type="tel" class="form-control" id="phone" name="phone" placeholder="Phone" required>
          <label for="phone">Phone number</label>
          <div class="invalid-feedback">Enter valid 10 digit phone number.</div>
        </div>

        <div class="col-12 form-floating mb-2">
          <textarea class="form-control" id="address" name="address" placeholder="Address" style="height:90px" required></textarea>
          <label for="address">Address</label>
          <div class="invalid-feedback">Address must be at least 10 characters.</div>
        </div>

        <div class="col-12 mb-2">
          <label class="d-block mb-1">Gender</label>
          <div class="btn-group" role="group" aria-label="Gender">
            <input type="radio" class="btn-check" name="gender" id="male" value="male" autocomplete="off" required>
            <label class="btn btn-outline-secondary" for="male">Male</label>

            <input type="radio" class="btn-check" name="gender" id="female" value="female" autocomplete="off">
            <label class="btn btn-outline-secondary" for="female">Female</label>

            <input type="radio" class="btn-check" name="gender" id="other" value="others" autocomplete="off">
            <label class="btn btn-outline-secondary" for="other">Other</label>
          </div>
        </div>
         
        <div class="col-12 form-floating mb-2">
          <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
          <label for="password">Password</label>
          <div class="invalid-feedback">Please enter a password.</div>
        </div>

        <div class="col-12 form-floating mb-2">
          <input type="password" class="form-control" id="confirm" name="confirm" placeholder="Confirm Password" required>
          <label for="confirm">Confirm password</label>
          <div class="invalid-feedback">Passwords do not match.</div>

        </div>
         <div class="col-12 mb-2">
           <label class="form-label">Profile Picture</label>
            <input type="file" name="profile_pic" class="form-control" accept="image/*" required>
          </div>

        <div class="col-12 d-grid mt-2">
          <button class="btn btn-gradient btn-lg" type="submit" name="reg-btn">Create Account</button>
        </div>
      </div>
    </form>

    <div class="text-center mt-3">
      <small>Already have an account? <a href="login.php">Login</a></small>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
