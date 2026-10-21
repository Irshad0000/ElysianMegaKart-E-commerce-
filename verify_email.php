<?php
@session_start();
include 'db_config.php';
if (file_exists(__DIR__ . '/mailer.php')) {
    include_once 'mailer.php';
}
include_once 'PHPMailer/PHPMailer.php';
include_once 'PHPMailer/SMTP.php';
include_once 'PHPMailer/Exception.php';

// Ensure required verification columns exist (in case legacy tables lack them)
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

// Reusable mail helper (uses mailer.php's sendEmail when available)
if (!function_exists('sendVerificationMail')) {
    function sendVerificationMail($toEmail, $name, $code)
    {
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
}

// Find which email we are verifying
$email = $_GET['email'] ?? ($_SESSION['pending_verify_email'] ?? '');
$email = trim($email);

if ($email === '') {
    header("Location: register.php");
    exit();
}

// Fetch the user row
$stmt = mysqli_prepare($con, "SELECT id, name, email_verified, email_verify_code, email_verify_expires, email_verify_attempts FROM register WHERE email = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$user = $res ? mysqli_fetch_assoc($res) : null;

if (!$user) {
    $_SESSION['login_error'] = 'Account not found. Please register first.';
    header("Location: register.php");
    exit();
}

$alreadyVerified = !empty($user['email_verified']);
$name = $user['name'] ?? 'User';

// Persist email so we can reuse on reloads
$_SESSION['pending_verify_email'] = $email;

// Notices carried from register/login
$flash_notice = $_SESSION['verify_notice'] ?? ($_SESSION['login_notice'] ?? '');
unset($_SESSION['verify_notice'], $_SESSION['login_notice']);

$error = '';
$success = '';

// Remaining seconds for timer
$nowTs = time();
$expiresTs = $user['email_verify_expires'] ? strtotime($user['email_verify_expires']) : 0;
$remaining = $expiresTs > $nowTs ? ($expiresTs - $nowTs) : 0;

// Handle resend code
if (isset($_POST['resend_code'])) {
    $attempts = (int)$user['email_verify_attempts'];
    if ($attempts >= 5) {
        $error = 'You have reached the maximum resend attempts. Please try again later.';
    } else {
        $code = str_pad((string)rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $sentAt = date("Y-m-d H:i:s");
        $expiresAt = date("Y-m-d H:i:s", strtotime('+10 minutes'));
        $attempts++;

        $upd = mysqli_prepare($con, "UPDATE register SET email_verify_code = ?, email_verify_sent_at = ?, email_verify_expires = ?, email_verify_attempts = ? WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($upd, "sssis", $code, $sentAt, $expiresAt, $attempts, $email);
        $db_ok = mysqli_stmt_execute($upd);

        if ($db_ok && sendVerificationMail($email, $name, $code)) {
            $success = 'A new code was sent to your email.';
            $user['email_verify_code'] = $code;
            $user['email_verify_expires'] = $expiresAt;
            $user['email_verify_attempts'] = $attempts;
            $expiresTs = strtotime($expiresAt);
            $remaining = $expiresTs - time();
        } else {
            $error = 'Could not send the code. Please try again.';
        }
    }
}

// Handle verify submit
if (isset($_POST['verify_code'])) {
    if ($alreadyVerified) {
        $_SESSION['login_notice'] = 'Email already verified. Please login.';
        header("Location: login.php");
        exit();
    }

    $inputCode = trim($_POST['code'] ?? '');

    if ($inputCode === '') {
        $error = 'Please enter the 6-digit code.';
    } elseif (strlen($inputCode) !== 6 || !ctype_digit($inputCode)) {
        $error = 'Code must be exactly 6 digits.';
    } elseif ($expiresTs !== 0 && time() > $expiresTs) {
        $error = 'Code expired. Please request a new one.';
    } elseif (hash_equals($user['email_verify_code'] ?? '', $inputCode)) {
        $verifiedAt = date("Y-m-d H:i:s");
        $upd = mysqli_prepare(
            $con,
            "UPDATE register 
             SET email_verified = 1, email_verify_code = NULL, email_verify_expires = NULL, email_verify_attempts = 0, email_verify_token = NULL 
             WHERE email = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($upd, "s", $email);
        $db_ok = mysqli_stmt_execute($upd);

        if ($db_ok) {
            unset($_SESSION['pending_verify_email']);
            $_SESSION['login_notice'] = 'Email verified successfully. You can login now.';
            header("Location: login.php");
            exit();
        } else {
            $error = 'Could not update verification status. Please try again.';
        }
    } else {
        $error = 'Invalid code. Please try again.';
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
  .otp-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(44px, 1fr));
    gap: 12px;
    width: 100%;
  }
  .otp-input {
    text-align: center;
    font-size: 1.2rem;
    padding: 0.55rem 0.4rem;
    border: 1px solid #d6dcfa;
    border-radius: 10px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    width: 100%;
  }
  .otp-input:focus {
    border-color: #00d4ff;
    box-shadow: 0 0 0 0.18rem rgba(0, 212, 255, 0.18);
    outline: none;
  }
  .resend-btn { color: #0d6efd; font-weight: 600; }
  .resend-btn:hover { color: #0a58ca; text-decoration: underline; }
</style>

<div class="auth-shell">
  <div class="card auth-card p-4 p-md-4">
    <div class="text-center mb-3">
      <i class="fa-solid fa-cart-shopping fs-2 brand-icon" style="color:#00d4ff;"></i>
      <h4 class="mb-0 fw-bold">Verify Email</h4>
      <p class="text-muted small mb-0">We sent a 6-digit code to <strong><?= htmlspecialchars($email); ?></strong></p>
    </div>

    <?php if ($flash_notice): ?>
      <div class="alert alert-info"><?= htmlspecialchars($flash_notice); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($alreadyVerified): ?>
      <div class="alert alert-success mb-3">Email already verified. You can login now.</div>
      <a class="btn btn-gradient w-100" href="login.php">Go to Login</a>
    <?php else: ?>
      <form method="post" class="row g-3" id="otpForm" autocomplete="off">
        <div class="col-12">
          <label class="form-label">Enter verification code</label>
          <div class="otp-grid" id="otpGrid">
            <?php for ($i = 0; $i < 6; $i++): ?>
              <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" class="otp-input" data-index="<?= $i; ?>" aria-label="Code digit <?= $i + 1; ?>">
            <?php endfor; ?>
          </div>
          <input type="hidden" name="code" id="otpHidden">
          <?php if ($remaining > 0): ?>
            <div class="form-text text-muted mt-2">Code expires in <span id="otpTimer"></span></div>
          <?php else: ?>
            <div class="form-text text-muted mt-2">Code expired. Please request a new one.</div>
          <?php endif; ?>
        </div>
        <div class="col-12 d-flex justify-content-end align-items-center flex-wrap gap-3">
          <button type="submit" name="verify_code" class="btn btn-gradient">Verify Email</button>
          <button type="submit" name="resend_code" value="1" class="btn btn-link p-0 resend-btn">Resend code</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<script>
(function() {
    let remaining = <?= (int)$remaining; ?>;
    const timerEl = document.getElementById('otpTimer');
    function tick() {
        if (!timerEl) return;
        if (remaining <= 0) {
            timerEl.textContent = '0m 00s';
            return;
        }
        const mins = Math.floor(remaining / 60);
        const secs = remaining % 60;
        timerEl.textContent = `${mins}m ${secs.toString().padStart(2,'0')}s`;
        remaining -= 1;
        setTimeout(tick, 1000);
    }
    tick();
})();

// OTP input handling: 6 individual boxes + hidden combined value
(function() {
    const inputs = Array.from(document.querySelectorAll('.otp-input'));
    const hidden = document.getElementById('otpHidden');
    const form = document.getElementById('otpForm');
    const total = inputs.length;

    function updateHidden() {
        hidden.value = inputs.map(i => i.value.trim()).join('');
    }

    inputs.forEach((input, idx) => {
        input.addEventListener('input', (e) => {
            const val = e.target.value.replace(/\\D/g, '').slice(0, 1);
            e.target.value = val;
            updateHidden();
            if (val && idx < total - 1) inputs[idx + 1].focus();
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && idx > 0) {
                inputs[idx - 1].focus();
            }
        });

        input.addEventListener('paste', (e) => {
            const paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\\D/g, '').slice(0, total);
            if (!paste) return;
            e.preventDefault();
            paste.split('').forEach((char, i) => {
                if (inputs[i]) inputs[i].value = char;
            });
            inputs[Math.min(paste.length, total) - 1]?.focus();
            updateHidden();
        });
    });

    // Ensure hidden field contains OTP before submit
    form?.addEventListener('submit', (e) => {
        const submitter = e.submitter;
        updateHidden();

        // Skip code length validation when resending
        if (submitter && submitter.name === 'resend_code') {
            hidden.value = ''; // do not send stale code
            return;
        }

        if (hidden.value.length !== total) {
            e.preventDefault();
            const firstEmpty = inputs.find(inp => !inp.value);
            (firstEmpty || inputs[0]).focus();
        }
    });
})();
</script>

<?php include 'includes/footer.php'; ?>
