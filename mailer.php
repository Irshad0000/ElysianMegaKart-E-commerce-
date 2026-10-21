<?php
// Simple PHPMailer helper configured for Gmail app password
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer classes
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

/**
 * Reusable inline-styled OTP/transactional email template.
 */
function buildOtpEmailTemplate(string $headline, string $otp, string $summary, array $bullets = [], ?string $ctaLabel = null, ?string $ctaUrl = null): string
{
    $bulletHtml = '';
    foreach ($bullets as $item) {
        $bulletHtml .= '<li style="margin-bottom:6px;">' . $item . '</li>';
    }

    $ctaBlock = '';
    if ($ctaLabel && $ctaUrl) {
        $ctaBlock = '<a href="' . htmlspecialchars($ctaUrl) . '" style="display:inline-block;background:linear-gradient(90deg,#00d4ff,#ff6b9d);color:#fff;text-decoration:none;padding:12px 20px;border-radius:10px;font-weight:700;margin-top:12px;">' . htmlspecialchars($ctaLabel) . '</a>';
    }

    return '
    <div style="margin:0;padding:0;background:#f4f6fb;font-family:Arial,Helvetica,sans-serif;">
      <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
          <td align="center" style="padding:22px 12px;">
            <table role="presentation" cellpadding="0" cellspacing="0" width="560" style="max-width:560px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 18px 40px rgba(15,23,42,0.12);">
              <tr>
                <td style="padding:18px 22px;background:linear-gradient(135deg,#00c6ff 0%,#5a7bff 55%,#ff6b9d 100%);color:#ffffff;">
                  <div style="font-size:14px;letter-spacing:0.08em;text-transform:uppercase;opacity:0.9;">Elysian MegaKart</div>
                  <div style="font-size:22px;font-weight:700;margin-top:6px;">' . htmlspecialchars($headline) . '</div>
                </td>
              </tr>
              <tr>
                <td style="padding:24px 24px 14px 24px;">
                  <p style="margin:0 0 12px 0;font-size:15px;color:#1d2a44;line-height:1.6;">' . $summary . '</p>
                  <div style="display:inline-block;padding:14px 22px;margin:4px 0 10px 0;background:#f1f6ff;border:1px dashed #b7c4ff;border-radius:14px;font-size:24px;font-weight:800;letter-spacing:4px;color:#0d6efd;">' . htmlspecialchars($otp) . '</div>
                  ' . $ctaBlock . '
                  <ul style="padding-left:18px;margin:14px 0 0 0;color:#475569;font-size:14px;line-height:1.5;">' . $bulletHtml . '</ul>
                </td>
              </tr>
              <tr>
                <td style="padding:14px 22px 20px 22px;color:#6b7280;font-size:12px;background:#f8fafc;">
                  If you didn&apos;t request this, you can ignore this email. Need help? <a href="#" style="color:#0d6efd;text-decoration:none;">Contact support</a>.
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </div>';
}

function sendEmail($toEmail, $subject, $bodyHtml, $attachmentPath = '')
{
    $mail = new PHPMailer(true);

    // Hardcoded Gmail credentials per user request.
    $smtpHost = 'smtp.gmail.com';
    $smtpUser = 'alimdirshad487@gmail.com';
    $smtpPass = 'xuuk xhwl ktpm omwd'; // Gmail app password
    $smtpPort = 587;
    $smtpFrom = $smtpUser;

    try {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtpPort;

        $mail->setFrom($smtpFrom, 'Elysian MegaKart');
        $mail->addAddress($toEmail);

        if (!empty($attachmentPath) && file_exists($attachmentPath)) {
            $mail->addAttachment($attachmentPath);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $bodyHtml;
        $mail->AltBody = strip_tags($bodyHtml);

        return $mail->send();
    } catch (Exception $e) {
        return false;
    }
}
