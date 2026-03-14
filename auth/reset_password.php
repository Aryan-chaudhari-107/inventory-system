<?php
session_start();
if (isset($_SESSION['user_id'])) { header('Location: ../dashboard/dashboard.php'); exit; }

if (empty($_SESSION['reset_email']) || empty($_SESSION['reset_verified'])) {
    header('Location: forgot_password.php');
    exit;
}

require '../config/db.php';
$error = '';
$email = $_SESSION['reset_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw  = $_POST['password']         ?? '';
    $cpw = $_POST['confirm_password'] ?? '';

    if (strlen($pw) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($pw !== $cpw) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $hashed = password_hash($pw, PASSWORD_DEFAULT);

            // FIX: Check rowCount to confirm the UPDATE actually worked
            $stmt = $pdo->prepare("UPDATE users SET password = ?, otp_code = NULL, otp_expires_at = NULL WHERE email = ?");
            $stmt->execute([$hashed, $email]);

            if ($stmt->rowCount() === 0) {
                $error = 'Could not update password. Please try again.';
            } else {
                // FIX: Set login_message FIRST, then unset reset keys
                // (unsetting after ensures the session is still alive for the message)
                $_SESSION['login_message'] = 'Password reset successfully! Please sign in with your new password.';
                unset($_SESSION['reset_email'], $_SESSION['reset_verified']);
                $key = 'otp_attempts_' . md5($email);
                unset($_SESSION[$key]);

                header('Location: login.php');
                exit;
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
<title>Reset Password — DockStock</title>
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
.input-wrap{position:relative}
.form-control{width:100%;padding:10px 42px 10px 13px;border:1px solid #e2e8f0;border-radius:8px;font-size:.9rem;font-family:inherit;outline:none;transition:all .2s}
.form-control:focus{border-color:#2CC7C9;box-shadow:0 0 0 3px rgba(44,199,201,.15)}
.toggle-pw{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94a3b8;cursor:pointer;font-size:.9rem;padding:0}
.toggle-pw:hover{color:#2CC7C9}
.btn{width:100%;padding:11px;background:linear-gradient(135deg,#2CC7C9,#1da9ab);border:none;border-radius:8px;color:#fff;font-size:.95rem;font-weight:700;cursor:pointer;transition:all .2s;margin-top:4px}
.btn:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(44,199,201,.35)}
.btn:disabled{opacity:.6;cursor:not-allowed;transform:none}
.alert{padding:12px;border-radius:8px;font-size:.85rem;margin-bottom:16px}
.alert-danger{background:#fee2e2;color:#dc2626;border:1px solid #fecaca}
.mb-3{margin-bottom:14px}
.strength-bar{height:4px;border-radius:4px;background:#e2e8f0;margin-top:6px;overflow:hidden}
.strength-fill{height:100%;border-radius:4px;transition:width .3s,background .3s;width:0}
.strength-hint{font-size:.75rem;color:#94a3b8;margin-top:4px}
</style>
</head>
<body>
<div class="card">
    <div class="brand">
        <div class="brand-icon"><i class="fas fa-lock"></i></div>
        <span class="brand-name">DockStock</span>
    </div>
    <h2>Set New Password</h2>
    <p class="sub">For <strong><?= htmlspecialchars($email) ?></strong></p>

    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle" style="margin-right:6px"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="resetForm">
        <div class="mb-3">
            <label class="form-label">New Password</label>
            <div class="input-wrap">
                <input type="password" name="password" id="pw1" class="form-control"
                       placeholder="At least 6 characters" required
                       oninput="checkStrength();matchCheck()">
                <button type="button" class="toggle-pw" onclick="togglePw('pw1',this)"><i class="fas fa-eye"></i></button>
            </div>
            <div class="strength-bar"><div class="strength-fill" id="strengthBar"></div></div>
            <div class="strength-hint" id="strengthHint"></div>
        </div>
        <div class="mb-3">
            <label class="form-label">Confirm Password</label>
            <div class="input-wrap">
                <input type="password" name="confirm_password" id="pw2" class="form-control"
                       placeholder="Repeat password" required oninput="matchCheck()">
                <button type="button" class="toggle-pw" onclick="togglePw('pw2',this)"><i class="fas fa-eye"></i></button>
            </div>
            <div class="strength-hint" id="matchHint"></div>
        </div>
        <button type="submit" class="btn" id="submitBtn" disabled>
            <i class="fas fa-save" style="margin-right:6px"></i> Reset Password
        </button>
    </form>
</div>
<script>
function togglePw(id, btn) {
    const input = document.getElementById(id);
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    btn.innerHTML = isText ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
}
function checkStrength() {
    const pw = document.getElementById('pw1').value;
    const bar = document.getElementById('strengthBar');
    const hint = document.getElementById('strengthHint');
    let score = 0;
    if (pw.length >= 6)  score++;
    if (pw.length >= 10) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    const levels = [
        {w:'0%',  c:'#e2e8f0', t:''},
        {w:'20%', c:'#dc2626', t:'Very weak'},
        {w:'40%', c:'#f97316', t:'Weak'},
        {w:'60%', c:'#eab308', t:'Fair'},
        {w:'80%', c:'#22c55e', t:'Good'},
        {w:'100%',c:'#16a34a', t:'Strong'},
    ];
    const l = levels[score] || levels[0];
    bar.style.width = l.w; bar.style.background = l.c;
    hint.textContent = l.t; hint.style.color = l.c;
}
function matchCheck() {
    const p1 = document.getElementById('pw1').value;
    const p2 = document.getElementById('pw2').value;
    const hint = document.getElementById('matchHint');
    const btn  = document.getElementById('submitBtn');
    if (!p2) { hint.textContent = ''; btn.disabled = true; return; }
    if (p1 === p2 && p1.length >= 6) {
        hint.textContent = '✓ Passwords match'; hint.style.color = '#16a34a';
        btn.disabled = false;
    } else {
        hint.textContent = p2 ? '✗ Passwords do not match' : '';
        hint.style.color = '#dc2626';
        btn.disabled = true;
    }
}
</script>
</body>
</html>