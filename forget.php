<?php
// Forgot Password with OTP flow
@session_start();
include_once 'db_config.php';

// Optional mailer helper; if missing we'll use a lightweight fallback.
if (file_exists(__DIR__ . '/mailer.php')) {
    include_once 'mailer.php';
}
include_once 'PHPMailer/PHPMailer.php';
include_once 'PHPMailer/SMTP.php';
include_once 'PHPMailer/Exception.php';

$title = "Forgot Password - Elysian MegaKart";
$db_success = false;
$send_email = false;
$redirect_url = null;
$user_data = null;

// Ensure password_token table exists (id | email | otp | created_at | expires_at | otp_attempts | last_resend)
mysqli_query(
    $con,
    "CREATE TABLE IF NOT EXISTS password_token (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        otp VARCHAR(6) NOT NULL,
        created_at DATETIME NOT NULL,
        expires_at DATETIME NOT NULL,
        otp_attempts INT DEFAULT 0,
        last_resend TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
);
// Ensure otp column is VARCHAR(6) even if table existed before
mysqli_query($con, "ALTER TABLE password_token MODIFY otp VARCHAR(6) NOT NULL");

// Helper: send OTP email using PHPMailer or fallback mail()
function sendOtpMail($toEmail, $otp, $name = 'User')
{
    // If a project-level helper exists, prefer that
    if (function_exists('sendEmail')) {
        $subject = "Password Reset - OTP";
        $body = function_exists('buildOtpEmailTemplate')
            ? buildOtpEmailTemplate(
                'Password Reset Code',
                $otp,
                "Hi {$name}, use this one-time code to reset your password.",
                [
                    'Code expires in 2 minutes.',
                    'Never share this code with anyone.',
                    'If you did not request a reset, ignore this email.'
                ],
                null,
                null
            )
            : "Hi {$name},<br>Your OTP for password reset is <strong>{$otp}</strong>. It expires in 2 minutes.<br>If you didn't request this, ignore this email.";
        return sendEmail($toEmail, $subject, $body, "");
    }

    $smtpHost = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
    // Default to the provided Gmail/app password if env vars are not set
    $smtpUser = $_ENV['SMTP_USER'] ?? 'alimdirshad487@gmail.com';
    $smtpPass = $_ENV['SMTP_PASS'] ?? 'xuuk xhwl ktpm omwd';
    $smtpPort = (int)($_ENV['SMTP_PORT'] ?? 587);
    $smtpFrom = $_ENV['SMTP_FROM'] ?? ($smtpUser ?: 'no-reply@example.com');

    if ($smtpUser === '' || $smtpPass === '') {
        return false; // Missing credentials
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
        $mail->Subject = "Password Reset - OTP";
        $mail->Body = function_exists('buildOtpEmailTemplate')
            ? buildOtpEmailTemplate(
                'Password Reset Code',
                $otp,
                "Hi {$name}, use this one-time code to reset your password.",
                [
                    'Code expires in 2 minutes.',
                    'Never share this code with anyone.',
                    'If you did not request a reset, ignore this email.'
                ],
                null,
                null
            )
            : "Hi {$name},<br>Your OTP for password reset is <strong>{$otp}</strong>. It expires in 2 minutes.<br>If you didn't request this, ignore this email.";
        $mail->AltBody = "Your OTP for password reset is {$otp}. It expires in 2 minutes.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Process form submission
if (isset($_POST['send_otp'])) {
    $email = trim($_POST['email'] ?? '');

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setcookie('error', "Please enter a valid email address.", time() + 5, "/");
        $redirect_url = "forget.php";
    } else {
        // Ensure the email is registered
        $user_stmt = mysqli_prepare($con, "SELECT id, name, email FROM register WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($user_stmt, "s", $email);
        mysqli_stmt_execute($user_stmt);
        $user_res = mysqli_stmt_get_result($user_stmt);
        $user_data = $user_res ? mysqli_fetch_assoc($user_res) : null;

        if (!$user_data) {
            setcookie('error', "Email is not registered. Please enter a registered email.", time() + 5, "/");
            $redirect_url = "forget.php";
        } else {
            // Check existing token row
            $token_stmt = mysqli_prepare($con, "SELECT otp_attempts FROM password_token WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($token_stmt, "s", $email);
            mysqli_stmt_execute($token_stmt);
            $token_res = mysqli_stmt_get_result($token_stmt);
            $has_token = $token_res && mysqli_num_rows($token_res) === 1;
            $attempts = 0;

            if ($has_token) {
                $row = mysqli_fetch_assoc($token_res);
                $attempts = (int)$row['otp_attempts'];

                if ($attempts >= 3) {
                    setcookie('error', "Maximum OTP limit reached. Please try again after 24 hours.", time() + 5, "/");
                    $redirect_url = "login.php";
                } else {
                    $attempts += 1;
                    $new_otp = str_pad((string)rand(0, 999999), 6, '0', STR_PAD_LEFT);
                    $email_time = date("Y-m-d H:i:s");
                    $expiry_time = date("Y-m-d H:i:s", strtotime('+2 minutes'));

                    $update_stmt = mysqli_prepare(
                        $con,
                        "UPDATE password_token 
                         SET otp = ?, created_at = ?, expires_at = ?, otp_attempts = ?, last_resend = CURRENT_TIMESTAMP 
                         WHERE email = ?"
                    );
                    // types: s (otp), s (created_at), s (expires_at), i (attempts), s (email)
                    mysqli_stmt_bind_param($update_stmt, "sssis", $new_otp, $email_time, $expiry_time, $attempts, $email);
                    $db_success = mysqli_stmt_execute($update_stmt);
                    $send_email = $db_success;
                    $redirect_url = "verify_otp.php";
                }
            } else {
                // First token create
                $attempts = 1;
                $new_otp = str_pad((string)rand(0, 999999), 6, '0', STR_PAD_LEFT);
                $email_time = date("Y-m-d H:i:s");
                $expiry_time = date("Y-m-d H:i:s", strtotime('+2 minutes'));

                $insert_stmt = mysqli_prepare(
                    $con,
                    "INSERT INTO password_token (email, otp, created_at, expires_at, otp_attempts) VALUES (?, ?, ?, ?, ?)"
                );
                mysqli_stmt_bind_param($insert_stmt, "ssssi", $email, $new_otp, $email_time, $expiry_time, $attempts);
                $db_success = mysqli_stmt_execute($insert_stmt);
                $send_email = $db_success;
                $redirect_url = "verify_otp.php";
            }

            // Send OTP email if DB write succeeded
            if ($db_success && $send_email) {
                if (sendOtpMail($email, $new_otp, $user_data['name'] ?? 'User')) {
                    $_SESSION['forgot_email'] = $email;
                    unset($_SESSION['forgot_otp_verified']);
                    setcookie('success', 'OTP sent! Please check your email (and spam folder).', time() + 5, "/");
                    $redirect_url = "verify_otp.php";
                } else {
                    setcookie('error', 'Failed to send the email. Please try again.', time() + 5, "/");
                    $redirect_url = "forget.php";
                }
            } elseif ($db_success === false && $redirect_url === null) {
                setcookie('error', 'Failed to update the database. Please try again.', time() + 5, "/");
                $redirect_url = "forget.php";
            }
        }
    }

    if ($redirect_url) {
        echo "<script>window.location.href = '$redirect_url';</script>";
        exit();
    }
}
?>
<?php include 'includes/header.php'; ?>

<style>
    .forgot-shell {
        max-width: 1120px;
        margin: 0 auto;
    }
    .forgot-wrap {
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 24px 60px rgba(24, 29, 63, 0.22);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.35);
        background: rgba(255, 255, 255, 0.93);
    }
    .forgot-aside {
        position: relative;
        color: #fff;
        padding: 2.75rem 2.25rem;
        background:
            radial-gradient(circle at 15% 12%, rgba(255, 255, 255, 0.18), transparent 44%),
            radial-gradient(circle at 82% 24%, rgba(0, 212, 255, 0.22), transparent 38%),
            linear-gradient(165deg, #00c6ff 0%, #5a7bff 50%, #ff6b9d 100%);
    }
    .forgot-aside::after {
        content: "";
        position: absolute;
        width: 260px;
        height: 260px;
        border-radius: 50%;
        right: -60px;
        bottom: -80px;
        background: rgba(255, 255, 255, 0.1);
        filter: blur(2px);
    }
    .brand-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 999px;
        padding: 0.45rem 0.95rem;
        background: rgba(255, 255, 255, 0.18);
        font-size: 0.86rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        margin-bottom: 1.4rem;
    }
    .forgot-title {
        font-size: clamp(1.8rem, 2.2vw, 2.35rem);
        line-height: 1.2;
        font-weight: 700;
    }
    .feature-list { margin-top: 2rem; }
    .feature-item {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        margin-bottom: 1rem;
        font-size: 0.95rem;
        color: rgba(255, 255, 255, 0.92);
    }
    .feature-item i { margin-top: 0.15rem; color: #ffd66b; }
    .forgot-form-pane {
        padding: 2.75rem 2.2rem;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.95), rgba(249, 250, 255, 0.93));
    }
    .forgot-heading { color: #00b8e6; font-weight: 700; margin-bottom: 0.35rem; }
    .forgot-form-pane .form-control {
        border-radius: 12px;
        border: 1px solid #d6dcfa;
        padding: 0.72rem 0.95rem;
        transition: all 0.2s ease;
    }
    .forgot-form-pane .form-control:focus {
        border-color: #00d4ff;
        box-shadow: 0 0 0 0.2rem rgba(0, 212, 255, 0.18);
    }
    .forgot-form-pane .form-label { margin-bottom: 0.4rem; color: #1d2a44; }
    .forgot-info {
        border: 1px solid rgba(0, 212, 255, 0.2);
        border-radius: 12px;
        background: rgba(0, 212, 255, 0.06);
        color: #1f3b52;
        padding: 0.9rem 1rem;
        margin-bottom: 1.25rem;
    }
    .auth-link { color: #00b8e6; font-weight: 600; text-decoration: none; }
    .auth-link:hover { color: #ff6b9d; }
    @media (max-width: 991.98px) {
        .forgot-aside { padding: 2rem 1.5rem; }
        .forgot-form-pane { padding: 2rem 1.5rem; }
    }
</style>

<style>
    .auth-bg {
        min-height: 100vh;
        background: radial-gradient(circle at 20% 20%, rgba(0,212,255,.10), transparent 45%),
                    radial-gradient(circle at 80% 0%, rgba(255,107,157,.12), transparent 35%),
                    linear-gradient(135deg, #f7fbff 0%, #eef5ff 45%, #f7fbff 100%);
        display: flex;
        align-items: center;
        padding: 32px 16px;
    }
    .auth-card {
        max-width: 1120px;
        margin: 0 auto;
        border: 1px solid rgba(13,110,253,.08);
        border-radius: 24px;
        box-shadow: 0 24px 70px rgba(13,110,253,.12);
        overflow: hidden;
        background: #fff;
    }
    .auth-side {
        background: linear-gradient(165deg, #00c6ff 0%, #5a7bff 50%, #ff6b9d 100%);
        color: #fff;
        padding: 2.75rem 2.25rem;
        position: relative;
    }
    .auth-side::after {
        content: "";
        position: absolute;
        right: -70px; bottom: -80px;
        width: 260px; height: 260px;
        border-radius: 50%;
        background: rgba(255,255,255,0.08);
        filter: blur(2px);
    }
    .auth-pill {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        padding: .45rem .95rem;
        border-radius: 999px;
        background: rgba(255,255,255,0.16);
        letter-spacing: .04em;
        text-transform: uppercase;
        font-size: .85rem;
        margin-bottom: 1.2rem;
    }
    .auth-title { font-size: clamp(1.8rem,2.2vw,2.3rem); font-weight: 700; line-height: 1.2; }
    .auth-list { margin-top: 1.5rem; }
    .auth-list li { margin-bottom: .8rem; display: flex; gap: .6rem; align-items: flex-start; color: rgba(255,255,255,0.9);}
    .auth-list i { color: #ffd166; margin-top: .1rem; }

    .auth-form-pane { padding: 2.6rem 2.1rem; }
    .auth-heading { color: #00b8e6; font-weight: 700; margin-bottom: .35rem; }
    .auth-form-pane .form-control { border-radius: 12px; border: 1px solid #d6dcfa; padding: .75rem .95rem; }
    .auth-form-pane .form-control:focus { border-color: #00d4ff; box-shadow: 0 0 0 .2rem rgba(0,212,255,.16); }
    .auth-info { border: 1px solid rgba(0, 212, 255, 0.18); border-radius: 12px; background: rgba(0, 212, 255, 0.06); color: #1f3b52; padding: .9rem 1rem; margin-bottom: 1.25rem; }
    .btn-gradient { background: linear-gradient(90deg, #00d4ff, #ff6b9d); border: none; color: #fff; padding: .9rem 1rem; border-radius: 12px; }
    .btn-gradient:hover { opacity: .96; }
    @media (max-width: 991.98px) {
        .auth-side { padding: 2rem 1.5rem; }
        .auth-form-pane { padding: 2rem 1.5rem; }
    }
</style>

<div class="auth-bg">
    <div class="auth-card w-100">
        <div class="row g-0">
            <div class="col-lg-4 d-none d-lg-block">
                <aside class="auth-side h-100">
                    <span class="auth-pill"><i class="fas fa-key"></i> Account Recovery</span>
                    <h2 class="auth-title">Reset your password quickly and securely.</h2>
                    <p class="mt-3 mb-0 text-white-50">Enter your registered email and we will send a one-time passcode.</p>
                    <ul class="auth-list list-unstyled">
                        <li><i class="fas fa-clock"></i><span>OTP expires in 2 minutes.</span></li>
                        <li><i class="fas fa-envelope-open-text"></i><span>Check inbox or spam folder.</span></li>
                        <li><i class="fas fa-user-shield"></i><span>Only registered emails can request reset.</span></li>
                    </ul>
                </aside>
            </div>

            <div class="col-lg-8">
                <div class="auth-form-pane">
                    <div class="mb-4 text-center text-lg-start">
                        <h3 class="auth-heading">Forgot Password?</h3>
                        <p class="text-muted mb-0">Enter your registered email to receive an OTP.</p>
                    </div>

                    <form id="forgotForm" method="post" action="forget.php">
                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required>
                            <span class="text-danger small" id="email_error"></span>
                        </div>

                        <div class="forgot-info">
                            <i class="fas fa-circle-info me-2"></i>
                            OTP is valid for 2 minutes. Check spam/junk folder if needed.
                        </div>

                        <button type="submit" class="btn btn-gradient w-100 btn-lg mb-3" name="send_otp" id="sendOtpButton">Send OTP</button>

                        <div class="text-center">
                            <p class="text-muted mb-0">Remember your password?
                                <a href="login.php" class="auth-link">Login</a>
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const emailInput = document.getElementById('email');
    const emailError = document.getElementById('email_error');
    const sendBtn = document.getElementById('sendOtpButton');

    emailInput.addEventListener('blur', () => {
        const email = emailInput.value.trim();
        if (!email) return;

        fetch('check_duplicate_Email.php?email1=' + encodeURIComponent(email))
            .then(r => r.text())
            .then(text => {
                const ok = text.trim() === 'true';
                if (!ok) {
                    emailError.textContent = 'Email is not registered. Please enter a registered email address.';
                    emailInput.classList.add('is-invalid');
                    sendBtn.disabled = true;
                } else {
                    emailError.textContent = '';
                    emailInput.classList.remove('is-invalid');
                    sendBtn.disabled = false;
                }
            })
            .catch(() => {
                emailError.textContent = 'Error validating email.';
                sendBtn.disabled = true;
            });
    });
});
</script>
