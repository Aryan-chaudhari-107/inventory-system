<?php
function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:2rem;text-align:center"><h3>Access Denied</h3><p>Required role: '.$role.'</p><a href="../dashboard/dashboard.php">Back to Dashboard</a></div>');
    }
}
function getUserRole() { return $_SESSION['role'] ?? 'guest'; }