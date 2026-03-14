<?php
session_start();
if (isset($_SESSION['user_id'])) { header('Location: ../dashboard/dashboard.php'); exit; }
require '../config/db.php';

// ── PHPMailer ────────────────────────────────────────────────────────────────
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

// ── SMTP CONFIG — fill these in ──────────────────────────────────────────────
define('MAIL_HOST',       'smtp.gmail.com');        // e.g. smtp.gmail.com
define('MAIL_PORT',       587);                     // 587 = TLS, 465 = SSL
define('MAIL_USERNAME',   'nirajkotadiya28@gmail.com');  // YOUR email
define('MAIL_PASSWORD',   'lxkp rfjf admr zbnr');     // Gmail App Password
define('MAIL_FROM_EMAIL', 'nirajkotadiya28@gmail.com');
define('MAIL_FROM_NAME',  'DockStock IMS');
// ─────────────────────────────────────────────────────────────────────────────

$success = $error = '';

function sendOtpEmail(string $toEmail, string $otp): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Your DockStock Password Reset OTP';
        $mail->Body    = '
        <div style="font-family:Inter,sans-serif;max-width:480px;margin:0 auto;padding:32px;background:#f8fafc;border-radius:12px">
            <div style="text-align:center;margin-bottom:24px">
                <div style="display:inline-flex;align-items:center;gap:8px">
                    <div style="width:38px;height:38px;background:linear-gradient(135deg,#2CC7C9,#1da9ab);border-radius:9px;display:inline-flex;align-items:center;justify-content:center">
                        <span style="color:#fff;font-size:18px">&#9875;</span>
                    </div>
                    <span style="font-size:1.2rem;font-weight:800;color:#1e293b">DockStock</span>
                </div>
            </div>
            <div style="background:#fff;border-radius:10px;padding:28px;border-top:4px solid #2CC7C9;box-shadow:0 2px 12px rgba(0,0,0,.06)">
                <h2 style="margin:0 0 8px;font-size:1.1rem;color:#1e293b">Password Reset Request</h2>
                <p style="color:#64748b;font-size:.9rem;margin:0 0 24px">Use the OTP below to reset your password. It expires in <strong>15 minutes</strong>.</p>
                <div style="text-align:center;background:#f1f5f9;border-radius:8px;padding:20px;margin-bottom:24px">
                    <div style="font-size:2.4rem;font-weight:800;letter-spacing:12px;color:#2CC7C9;font-family:monospace">' . $otp . '</div>
                </div>
                <p style="color:#94a3b8;font-size:.8rem;margin:0">If you did not request this, please ignore this email. Your password will not change.</p>
            </div>
        </div>';
        $mail->AltBody = "Your DockStock OTP is: $otp  (valid for 15 minutes)";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('PHPMailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                $pdo->prepare("UPDATE users SET otp_code=?, otp_expires_at=? WHERE email=?")
                    ->execute([$otp, $expires, $email]);

                $_SESSION['reset_email'] = $email;

                $sent = sendOtpEmail($email, $otp);

                if ($sent) {
                    $success = "OTP sent to <strong>" . htmlspecialchars($email) . "</strong>. Please check your inbox (and spam folder).";
                } else {
                    $error = 'Failed to send OTP email. Please check SMTP settings in <code>forgot_password.php</code> or contact your administrator.';
                    $pdo->prepare("UPDATE users SET otp_code=NULL, otp_expires_at=NULL WHERE email=?")->execute([$email]);
                    unset($_SESSION['reset_email']);
                }
            } else {
                $success = "If that email is registered, an OTP has been sent.";
                $_SESSION['reset_email'] = $email;
            }
        } catch (PDOException $e) {
            error_log('DB Error: ' . $e->getMessage());
            $error = 'A database error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Forgot Password — DockStock</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#e8f9f9,#f1f5f9);padding:1.5rem}
.card{background:#fff;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.1);padding:2.5rem;width:100%;max-width:420px;border-top:4px solid #2CC7C9}
.brand{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:1.5rem}
.brand-icon{width:42px;height:42px;background:linear-gradient(135deg,#2CC7C9,#1da9ab);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.1rem}
.brand-name{font-size:1.3rem;font-weight:800;color:#1e293b}
h2{font-size:1.3rem;font-weight:800;color:#1e293b;text-align:center;margin-bottom:4px}
.sub{color:#64748b;font-size:.88rem;text-align:center;margin-bottom:1.8rem}
.form-label{display:block;font-size:.82rem;font-weight:600;color:#1e293b;margin-bottom:6px}
.form-control{width:100%;padding:10px 13px;border:1px solid #e2e8f0;border-radius:8px;font-size:.9rem;font-family:inherit;outline:none;transition:all .2s}
.form-control:focus{border-color:#2CC7C9;box-shadow:0 0 0 3px rgba(44,199,201,.15)}
.btn{width:100%;padding:11px;background:linear-gradient(135deg,#2CC7C9,#1da9ab);border:none;border-radius:8px;color:#fff;font-size:.95rem;font-weight:700;cursor:pointer;transition:all .2s;margin-top:4px}
.btn:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(44,199,201,.35)}
.alert{padding:12px;border-radius:8px;font-size:.85rem;margin-bottom:16px}
.alert-danger{background:#fee2e2;color:#dc2626;border:1px solid #fecaca}
.alert-success{background:#dcfce7;color:#16a34a;border:1px solid #bbf7d0}
.mb-3{margin-bottom:14px}
.link{color:#2CC7C9;text-decoration:none;font-weight:600}
</style>
</head>
<body>
<div class="card">
    <div class="brand">
        <div class="brand-icon"><i class="fas fa-ship"></i></div>
        <span class="brand-name">DockStock</span>
    </div>
    <h2>Forgot Password</h2>
    <p class="sub">Enter your registered email to receive an OTP</p>

    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle" style="margin-right:6px"></i><?= $error ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle" style="margin-right:6px"></i><?= $success ?></div>
    <a href="verify_otp.php" class="btn" style="display:block;text-align:center;text-decoration:none;margin-top:0">
        <i class="fas fa-key" style="margin-right:6px"></i> Enter OTP
    </a>
    <?php else: ?>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="your@email.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
        </div>
        <button type="submit" class="btn">
            <i class="fas fa-paper-plane" style="margin-right:6px"></i> Send OTP
        </button>
    </form>
    <?php endif; ?>

    <p style="text-align:center;margin-top:1.5rem;font-size:.88rem;color:#64748b">
        <a href="login.php" class="link">← Back to Login</a>
    </p>
</div>
</body>
</html>