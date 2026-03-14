<?php
session_start();
if (isset($_SESSION['user_id'])) { header('Location: ../dashboard/dashboard.php'); exit; }
require '../config/db.php';
$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'staff';
    $errors = [];
    if (strlen($name) < 3) $errors[] = 'Name must be at least 3 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if (!preg_match('/[A-Z]/', $password)) $errors[] = 'Password needs one uppercase letter.';
    if (!preg_match('/[0-9]/', $password)) $errors[] = 'Password needs one number.';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match.';
    if (!in_array($role, ['admin','manager','staff'])) $errors[] = 'Invalid role.';
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email already registered.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
                $_SESSION['login_message'] = 'Account created! Please sign in.';
                header('Location: login.php'); exit;
            }
        } catch (PDOException $e) { $error = 'Database error. Please try again.'; }
    } else { $error = implode(' ', $errors); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — DockStock IMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#e8f9f9 0%,#f1f5f9 100%);padding:1.5rem}
.auth-card{background:#fff;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,0.10);padding:2.5rem;width:100%;max-width:480px;border-top:4px solid #2CC7C9}
.brand{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:1.5rem}
.brand-icon{width:42px;height:42px;background:linear-gradient(135deg,#2CC7C9,#1da9ab);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.1rem}
.brand-name{font-size:1.4rem;font-weight:800;color:#1e293b}
h2{font-size:1.4rem;font-weight:800;color:#1e293b;text-align:center;margin-bottom:4px}
.subtitle{color:#64748b;font-size:0.88rem;text-align:center;margin-bottom:1.8rem}
.form-label{display:block;font-size:0.8rem;font-weight:600;color:#1e293b;margin-bottom:6px}
.form-control,.form-select{width:100%;padding:10px 13px;border:1px solid #e2e8f0;border-radius:8px;font-size:0.9rem;font-family:inherit;outline:none;transition:all 0.2s;color:#1e293b;background:#fff}
.form-control:focus,.form-select:focus{border-color:#2CC7C9;box-shadow:0 0 0 3px rgba(44,199,201,0.15)}
.form-control::placeholder{color:#94a3b8}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.btn-register{width:100%;padding:11px;background:linear-gradient(135deg,#2CC7C9,#1da9ab);border:none;border-radius:8px;color:#fff;font-size:0.95rem;font-weight:700;cursor:pointer;transition:all 0.2s;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:4px}
.btn-register:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(44,199,201,0.35)}
.alert{padding:12px 14px;border-radius:8px;font-size:0.85rem;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.alert-danger{background:#fee2e2;color:#dc2626;border:1px solid #fecaca}
.password-strength{height:4px;background:#e2e8f0;border-radius:2px;margin-top:6px;overflow:hidden}
.strength-bar{height:100%;width:0;background:#dc2626;border-radius:2px;transition:width 0.3s,background 0.3s}
.strength-bar.fair{width:66%;background:#d97706}
.strength-bar.strong{width:100%;background:#16a34a}
.req{font-size:0.75rem;color:#94a3b8;margin-top:3px}
.req.met{color:#16a34a}
.req::before{content:"✗ ";font-weight:700}
.req.met::before{content:"✓ "}
.mb-3{margin-bottom:14px}
.form-link{color:#2CC7C9;text-decoration:none;font-weight:600}
.form-link:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="auth-card">
    <div class="brand">
        <div class="brand-icon"><i class="fas fa-ship"></i></div>
        <span class="brand-name">DockStock</span>
    </div>
    <h2>Create Account</h2>
    <p class="subtitle">Join DockStock IMS today</p>
    <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST" onsubmit="return validateForm()">
        <div class="row2 mb-3">
            <div>
                <label class="form-label">Full Name *</label>
                <input type="text" name="name" class="form-control" placeholder="Your name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
            </div>
            <div>
                <label class="form-label">Role *</label>
                <select name="role" class="form-select">
                    <option value="staff" <?= (($_POST['role'] ?? '') === 'staff') ? 'selected' : '' ?>>Staff</option>
                    <option value="manager" <?= (($_POST['role'] ?? '') === 'manager') ? 'selected' : '' ?>>Manager</option>
                    <option value="admin" <?= (($_POST['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Email Address *</label>
            <input type="email" name="email" class="form-control" placeholder="your@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password *</label>
            <input type="password" id="pw" name="password" class="form-control" placeholder="Create a strong password" onkeyup="checkStrength()" required>
            <div class="password-strength"><div class="strength-bar" id="sbar"></div></div>
            <div id="req-len" class="req">At least 6 characters</div>
            <div id="req-up" class="req">One uppercase letter</div>
            <div id="req-num" class="req">One number</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Confirm Password *</label>
            <input type="password" id="cpw" name="confirm_password" class="form-control" placeholder="Re-enter password" onkeyup="checkMatch()" required>
            <div id="matchMsg" style="font-size:0.78rem;margin-top:3px;color:#94a3b8"></div>
        </div>
        <button type="submit" class="btn-register"><i class="fas fa-user-plus"></i> Create Account</button>
    </form>
    <p style="text-align:center;margin-top:1.5rem;font-size:0.88rem;color:#64748b">
        Already have an account? <a href="login.php" class="form-link">Sign in</a>
    </p>
</div>
<script>
function checkStrength(){
    const pw=document.getElementById('pw').value;
    const sb=document.getElementById('sbar');
    let s=0;
    const setR=(id,ok)=>{document.getElementById(id).classList.toggle('met',ok);};
    setR('req-len',pw.length>=6); if(pw.length>=6)s++;
    setR('req-up',/[A-Z]/.test(pw)); if(/[A-Z]/.test(pw))s++;
    setR('req-num',/[0-9]/.test(pw)); if(/[0-9]/.test(pw))s++;
    sb.className='strength-bar'+(s===2?' fair':s===3?' strong':'');
}
function checkMatch(){
    const pw=document.getElementById('pw').value;
    const cp=document.getElementById('cpw').value;
    const m=document.getElementById('matchMsg');
    if(!cp){m.textContent='';return;}
    if(pw===cp){m.textContent='✓ Passwords match';m.style.color='#16a34a';}
    else{m.textContent='✗ Passwords do not match';m.style.color='#dc2626';}
}
function validateForm(){
    const pw=document.getElementById('pw').value;
    const cp=document.getElementById('cpw').value;
    if(pw!==cp){alert('Passwords do not match');return false;}
    if(pw.length<6||!/[A-Z]/.test(pw)||!/[0-9]/.test(pw)){alert('Password does not meet requirements');return false;}
    return true;
}
</script>
</body>
</html>
