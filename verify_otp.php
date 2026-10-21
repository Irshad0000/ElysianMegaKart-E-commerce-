<?php
@session_start();
include_once 'db_config.php';
if (file_exists(__DIR__ . '/mailer.php')) {
    include_once 'mailer.php';
}
include_once 'PHPMailer/PHPMailer.php';
include_once 'PHPMailer/SMTP.php';
include_once 'PHPMailer/Exception.php';

if (empty($_SESSION['forgot_email'])) {
    header("Location: forget.php");
    exit();
}

$email = $_SESSION['forgot_email'];
$error = '';
$success = '';

// Helper: send OTP email (reuse mailer.php if available, otherwise PHPMailer directly)
if (!function_exists('sendOtpMail')) {
    function sendOtpMail($toEmail, $otp, $name = 'User')
    {
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
}

$token_stmt = mysqli_prepare($con, "SELECT otp, expires_at, otp_attempts FROM password_token WHERE email = ? LIMIT 1");
mysqli_stmt_bind_param($token_stmt, "s", $email);
mysqli_stmt_execute($token_stmt);
$token_res = mysqli_stmt_get_result($token_stmt);
$token = $token_res ? mysqli_fetch_assoc($token_res) : null;

if (!$token) {
    setcookie('error', 'No OTP found. Please request a new one.', time() + 5, '/');
    header("Location: forget.php");
    exit();
}

$expires_at_ts = strtotime($token['expires_at']);
$now = time();
$remaining = max(0, $expires_at_ts - $now);
$expires_at_ms = $expires_at_ts * 1000;

// Resend OTP
if (isset($_POST['resend_otp'])) {
    $attempts = (int)($token['otp_attempts'] ?? 0);
    if ($attempts >= 3) {
        $error = 'Maximum OTP resend attempts reached. Please try again after some time.';
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
        mysqli_stmt_bind_param($update_stmt, "sssis", $new_otp, $email_time, $expiry_time, $attempts, $email);
        $db_ok = mysqli_stmt_execute($update_stmt);

        if ($db_ok) {
            $name_stmt = mysqli_prepare($con, "SELECT name FROM register WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($name_stmt, "s", $email);
            mysqli_stmt_execute($name_stmt);
            $name_res = mysqli_stmt_get_result($name_stmt);
            $user_row = $name_res ? mysqli_fetch_assoc($name_res) : null;
            $user_name = $user_row['name'] ?? 'User';

            if (sendOtpMail($email, $new_otp, $user_name)) {
                $token['otp'] = $new_otp;
                $token['expires_at'] = $expiry_time;
                $token['otp_attempts'] = $attempts;
                $expires_at_ts = strtotime($expiry_time);
                $now = time();
                $remaining = max(0, $expires_at_ts - $now);
                $error = '';
                $success = 'A new OTP has been sent to your email.';
            } else {
                $error = 'Failed to resend OTP. Please try again.';
            }
        } else {
            $error = 'Could not update OTP. Please try again.';
        }
    }
}

// Verify OTP
if (isset($_POST['verify_otp'])) {
    $otp_input = trim($_POST['otp'] ?? '');

    if ($remaining <= 0) {
        $error = 'OTP expired. Please request a new one.';
    } elseif ($otp_input === str_pad((string)$token['otp'], 6, '0', STR_PAD_LEFT)) {
        $_SESSION['forgot_otp_verified'] = true;
        header("Location: reset_password.php");
        exit();
    } else {
        $error = 'Invalid OTP. Please try again.';
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
  .resend-hidden { display: none !important; }
</style>

<div class="auth-shell">
  <div class="card auth-card p-4 p-md-4">
    <div class="text-center mb-3">
      <i class="fa-solid fa-cart-shopping fs-2 brand-icon" style="color:#00d4ff;"></i>
      <h4 class="mb-0 fw-bold">Elysian <span style="color:#ff6b9d">MegaKart</span></h4>
      <p class="text-muted small mb-0">Verify your OTP</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3" id="otpForm">
      <div class="col-12">
        <label class="form-label">Enter OTP</label>
        <div class="otp-grid" id="otpGrid">
          <?php for ($i = 0; $i < 6; $i++): ?>
            <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" class="otp-input" data-index="<?= $i; ?>" aria-label="OTP digit <?= $i + 1; ?>">
          <?php endfor; ?>
        </div>
        <input type="hidden" name="otp" id="otpHidden">
        <div class="form-text text-muted mt-2">OTP expires in <span id="otpTimer"></span></div>
      </div>
      <div class="col-12 d-flex justify-content-end align-items-center flex-wrap gap-3">
        <button type="submit" name="verify_otp" class="btn btn-gradient">Verify OTP</button>
        <button type="submit" name="resend_otp" value="1" class="btn btn-link p-0 resend-btn" id="resendBtn" formnovalidate style="display:none;">Resend OTP</button>
      </div>
    </form>
  </div>
</div>

<script>
(function() {
    const expiryTs = <?= (int)$expires_at_ms; ?>; // epoch millis from server
    const timerEl = document.getElementById('otpTimer');
    const resendBtn = document.getElementById('resendBtn');
    function tick() {
        if (!timerEl) return;
        const now = Date.now();
        const remainingSec = Math.max(0, Math.floor((expiryTs - now) / 1000));
        const mins = Math.floor(remainingSec / 60);
        const secs = remainingSec % 60;
        timerEl.textContent = `${mins}m ${secs.toString().padStart(2,'0')}s`;
        if (remainingSec <= 0) {
            if (resendBtn) resendBtn.style.display = 'inline-block';
            return;
        }
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
            const val = e.target.value.replace(/\D/g, '').slice(0, 1);
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
            const paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, total);
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
    form.addEventListener('submit', (e) => {
        const submitter = e.submitter;
        updateHidden();

        // Skip OTP length validation when resending
        if (submitter && submitter.name === 'resend_otp') {
            hidden.value = ''; // do not send stale OTP
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
