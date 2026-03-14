<?php
session_start();
require '../config/db.php';
if (!isLoggedIn()) { header('Location: ../auth/login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../dashboard/dashboard.php'); exit; }
$current_password = $_POST['current_password'] ?? '';
$new_username = trim($_POST['new_username'] ?? '');
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
try {
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($current_password, $user['password'])) {
        $_SESSION['profile_error'] = 'Current password is incorrect.';
        header('Location: ../dashboard/dashboard.php'); exit;
    }
    $updates = [];
    $params = [];
    if (!empty($new_username)) {
        $updates[] = 'name = ?';
        $params[] = $new_username;
    }
    if (!empty($new_password)) {
        if ($new_password !== $confirm_password) {
            $_SESSION['profile_error'] = 'New passwords do not match.';
            header('Location: ../dashboard/dashboard.php'); exit;
        }
        $updates[] = 'password = ?';
        $params[] = password_hash($new_password, PASSWORD_DEFAULT);
    }
    if (!empty($updates)) {
        $params[] = $_SESSION['user_id'];
        $pdo->prepare("UPDATE users SET ".implode(', ', $updates)." WHERE id = ?")->execute($params);
        if (!empty($new_username)) $_SESSION['username'] = $new_username;
        $_SESSION['profile_success'] = 'Profile updated successfully!';
    }
} catch (PDOException $e) {
    $_SESSION['profile_error'] = 'Update failed. Please try again.';
}
header('Location: ../dashboard/dashboard.php'); exit;
