<?php
session_start();
if (isset($_SESSION['user_id'])) { header('Location: ../dashboard/dashboard.php'); exit; }
require '../config/db.php';

$email = $_SESSION['reset_email'] ?? '';
if (!$email) { header('Location: forgot_password.php'); exit; }

$success = $error = '';
$attempts_key = 'otp_attempts_' . md5($email);
$_SESSION[$attempts_key] = $_SESSION[$attempts_key] ?? 0;

if (isset($_GET['resend'])) {
    header('Location: forgot_password.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');

    if ($_SESSION[$attempts_key] >= 5) {
        $error = 'Too many failed attempts. Please <a href="forgot_password.php">request a new OTP</a>.';
    } elseif (!preg_match('/^\d{6}$/', $otp)) {
        $error = 'OTP must be exactly 6 digits.';
    } else {
        try {
            // FIX: Fetch by email + code only — check expiry in PHP, not MySQL NOW()
            // This avoids timezone mismatch between PHP date() and MySQL NOW()
            $stmt = $pdo->prepare("SELECT id, otp_expires_at FROM users WHERE email = ? AND CAST(otp_code AS CHAR) = ?");
            $stmt->execute([$email, $otp]);
            $user = $stmt->fetch();

            if ($user) {
                // Compare expiry using PHP time() — both use same clock
                if (time() > strtotime($user['otp_expires_at'])) {
                    $error = 'This OTP has expired. Please <a href="forgot_password.php">request a new one</a>.';
                } else {
                    $_SESSION['reset_verified'] = true;
                    $_SESSION[$attempts_key]    = 0;
                    header('Location: reset_password.php');
                    exit;
                }
            } else {
                $_SESSION[$attempts_key]++;
                $remaining = 5 - $_SESSION[$attempts_key];
                $error = 'Invalid OTP.' . ($remaining > 0 ? " $remaining attempt(s) remaining." : ' Too many attempts — <a href="forgot_password.php">request a new OTP</a>.');
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
<title>Verify OTP — DockStock</title>
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
.otp-input{width:100%;padding:14px;border:1px solid #e2e8f0;border-radius:8px;font-size:1.8rem;font-family:monospace;outline:none;transition:all .2s;letter-spacing:10px;text-align:center}
.otp-input:focus{border-color:#2CC7C9;box-shadow:0 0 0 3px rgba(44,199,201,.15)}
.btn{width:100%;padding:11px;background:linear-gradient(135deg,#2CC7C9,#1da9ab);border:none;border-radius:8px;color:#fff;font-size:.95rem;font-weight:700;cursor:pointer;transition:all .2s;margin-top:4px}
.btn:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(44,199,201,.35)}
.btn:disabled{opacity:.5;cursor:not-allowed;transform:none}
.alert{padding:12px;border-radius:8px;font-size:.85rem;margin-bottom:16px}
.alert-danger{background:#fee2e2;color:#dc2626;border:1px solid #fecaca}
.alert-success{background:#dcfce7;color:#16a34a;border:1px solid #bbf7d0}
.mb-3{margin-bottom:14px}
.link{color:#2CC7C9;text-decoration:none;font-weight:600}
.timer{text-align:center;font-size:.82rem;color:#64748b;margin-top:10px}
.timer span{font-weight:700;color:#2CC7C9}
</style>
</head>
<body>
<div class="card">
    <div class="brand">
        <div class="brand-icon"><i class="fas fa-key"></i></div>
        <span class="brand-name">DockStock</span>
    </div>
    <h2>Enter OTP</h2>
    <p class="sub">A 6-digit code was sent to <strong><?= htmlspecialchars($email) ?></strong></p>

    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle" style="margin-right:6px"></i><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" id="otpForm">
        <div class="mb-3">
            <label class="form-label">OTP Code</label>
            <input type="text" name="otp" id="otpInput" class="otp-input"
                   maxlength="6" placeholder="000000" required autofocus
                   inputmode="numeric" pattern="\d{6}"
                   oninput="this.value=this.value.replace(/[^0-9]/g,'').substring(0,6);checkReady()">
        </div>
        <button type="submit" class="btn" id="verifyBtn" disabled>
            <i class="fas fa-check" style="margin-right:6px"></i> Verify OTP
        </button>
    </form>

    <div class="timer">OTP expires in <span id="countdown">15:00</span></div>

    <p style="text-align:center;margin-top:1.2rem;font-size:.85rem;color:#64748b">
        Didn't receive it? <a href="forgot_password.php" class="link">Resend OTP</a>
    </p>
    <p style="text-align:center;margin-top:.5rem;font-size:.85rem;color:#64748b">
        <a href="login.php" class="link">← Back to Login</a>
    </p>
</div>
<script>
function checkReady() {
    const v = document.getElementById('otpInput').value;
    document.getElementById('verifyBtn').disabled = v.length !== 6;
}

let seconds = 15 * 60;
const el = document.getElementById('countdown');
const timer = setInterval(() => {
    seconds--;
    if (seconds <= 0) {
        clearInterval(timer);
        el.textContent = 'Expired';
        el.style.color = '#dc2626';
        document.getElementById('verifyBtn').disabled = true;
        document.getElementById('otpInput').disabled = true;
        return;
    }
    const m = Math.floor(seconds / 60), s = seconds % 60;
    el.textContent = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
    if (seconds <= 60) el.style.color = '#dc2626';
}, 1000);
</script>
</body>
</html>