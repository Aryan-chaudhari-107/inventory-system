<?php
session_start();
require '../config/db.php';
require '../auth/auth.php';
requireLogin();
$id=(int)($_GET['id']??0);
if(!$id){header('Location: products.php');exit;}
try{
    $pdo->prepare("UPDATE products SET is_active=0 WHERE id=?")->execute([$id]);
}catch(PDOException $e){}
header('Location: products.php?deleted=1');exit;
