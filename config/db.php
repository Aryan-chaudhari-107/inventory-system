<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'inventory_system');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(503);
    die('<!DOCTYPE html><html><head><title>DB Error</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh"><div class="card p-4 shadow text-center" style="max-width:500px"><h4 class="text-danger mb-3"><i class="fas fa-database"></i> Database Connection Failed</h4><p class="text-muted">Please import <strong>inventory_system.sql</strong> via phpMyAdmin and check credentials in <code>config/db.php</code>.</p><small class="text-muted d-block mt-2">'.htmlspecialchars($e->getMessage()).'</small></div></body></html>');
}

function getActiveUserId() {
    if (!isset($_SESSION['user_id'])) return null;
    global $pdo;
    try {
        $s = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $s->execute([$_SESSION['user_id']]);
        return $s->fetchColumn() ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        // Determine depth from document root
        $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
        $root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
        $rel = str_replace($root, '', $script);
        $depth = substr_count(dirname($rel), '/') - 1;
        $prefix = str_repeat('../', max(0, $depth));
        header('Location: '.$prefix.'auth/login.php');
        exit;
    }
}

function generateRefId(string $prefix): string {
    // e.g. RCP-20260314-0047
    return strtoupper($prefix) . '-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}