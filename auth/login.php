<?php
session_start();
if (isset($_SESSION['user_id'])) { header('Location: ../dashboard/dashboard.php'); exit; }
require '../config/db.php';
$error = '';
$success = $_SESSION['login_message'] ?? '';
$email = '';
if (isset($_SESSION['login_message'])) unset($_SESSION['login_message']);
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['name'];
                $_SESSION['email']    = $user['email'];
                $_SESSION['role']     = $user['role'];
                header('Location: ../dashboard/dashboard.php'); exit;
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) { $error = 'Database error. Please try again later.'; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — DockStock IMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;min-height:100vh;display:flex;background:#f1f5f9}
.left-panel{flex:1;background:linear-gradient(145deg,#1da9ab 0%,#2CC7C9 40%,#38d4d6 100%);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:3rem;color:#fff}
.left-panel .brand{display:flex;align-items:center;gap:12px;margin-bottom:3rem}
.brand-icon{width:52px;height:52px;background:rgba(255,255,255,0.2);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem}
.brand-name{font-size:1.8rem;font-weight:800;letter-spacing:-0.5px}
.features{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;max-width:380px;width:100%}
.feat{background:rgba(255,255,255,0.12);border-radius:12px;padding:1.2rem;backdrop-filter:blur(4px)}
.feat i{font-size:1.5rem;margin-bottom:8px;display:block}
.feat h4{font-size:0.9rem;font-weight:700;margin-bottom:4px}
.feat p{font-size:0.78rem;opacity:0.8;margin:0}
.right-panel{width:440px;display:flex;align-items:center;justify-content:center;padding:2rem;background:#fff}
.auth-box{width:100%;max-width:380px}
.auth-box h2{font-size:1.6rem;font-weight:800;color:#1e293b;margin-bottom:4px}
.auth-box .subtitle{color:#64748b;font-size:0.9rem;margin-bottom:2rem}
.form-label{display:block;font-size:0.8rem;font-weight:600;color:#1e293b;margin-bottom:6px}
.form-control{width:100%;padding:10px 13px;border:1px solid #e2e8f0;border-radius:8px;font-size:0.9rem;font-family:inherit;outline:none;transition:all 0.2s;color:#1e293b}
.form-control:focus{border-color:#2CC7C9;box-shadow:0 0 0 3px rgba(44,199,201,0.15)}
.form-control::placeholder{color:#94a3b8}
.pw-wrap{position:relative}
.pw-wrap .form-control{padding-right:40px}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8;font-size:0.95rem}
.pw-toggle:hover{color:#2CC7C9}
.btn-login{width:100%;padding:11px;background:linear-gradient(135deg,#2CC7C9,#1da9ab);border:none;border-radius:8px;color:#fff;font-size:0.95rem;font-weight:700;cursor:pointer;transition:all 0.2s;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:4px}
.btn-login:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(44,199,201,0.35)}
.alert{padding:12px 14px;border-radius:8px;font-size:0.85rem;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.alert-danger{background:#fee2e2;color:#dc2626;border:1px solid #fecaca}
.alert-success{background:#dcfce7;color:#16a34a;border:1px solid #bbf7d0}
.form-link{color:#2CC7C9;text-decoration:none;font-weight:600}
.form-link:hover{text-decoration:underline}
.mb-3{margin-bottom:14px}
.text-end{text-align:right}
@media(max-width:720px){.left-panel{display:none}.right-panel{width:100%}}
</style>
</head>
<body>
<div class="left-panel">
    <div class="brand">
        <div class="brand-icon"><i class="fas fa-ship"></i></div>
        <span class="brand-name">DockStock</span>
    </div>
    <div class="features">
        <div class="feat"><i class="fas fa-boxes"></i><h4>Inventory Tracking</h4><p>Real-time stock management across warehouses</p></div>
        <div class="feat"><i class="fas fa-chart-bar"></i><h4>Analytics</h4><p>Detailed reports & insights</p></div>
        <div class="feat"><i class="fas fa-shield-alt"></i><h4>Secure</h4><p>Role-based access control</p></div>
        <div class="feat"><i class="fas fa-mobile-alt"></i><h4>Responsive</h4><p>Works on all devices</p></div>
    </div>
</div>
<div class="right-panel">
    <div class="auth-box">
        <h2>Welcome back</h2>
        <p class="subtitle">Sign in to your DockStock account</p>
        <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-envelope" style="color:#2CC7C9;margin-right:6px"></i>Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="your@email.com" value="<?= htmlspecialchars($email) ?>" autofocus required>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-lock" style="color:#2CC7C9;margin-right:6px"></i>Password</label>
                <div class="pw-wrap">
                    <input type="password" id="pw" name="password" class="form-control" placeholder="Enter your password" required>
                    <button type="button" class="pw-toggle" onclick="togglePw()"><i class="fas fa-eye" id="pwIcon"></i></button>
                </div>
            </div>
            <div class="text-end mb-3">
                <a href="forgot_password.php" class="form-link" style="font-size:0.85rem">Forgot password?</a>
            </div>
            <button type="submit" class="btn-login"><i class="fas fa-sign-in-alt"></i> Sign In</button>
        </form>
        <p style="text-align:center;margin-top:1.5rem;font-size:0.88rem;color:#64748b">
            Don't have an account? <a href="register.php" class="form-link">Create one</a>
        </p>
    </div>
</div>
<script>
function togglePw(){
    const pw=document.getElementById('pw');
    const ic=document.getElementById('pwIcon');
    if(pw.type==='password'){pw.type='text';ic.className='fas fa-eye-slash';}
    else{pw.type='password';ic.className='fas fa-eye';}
}
</script>
</body>
</html>
