<?php
session_start();
require '../config/db.php';
require '../auth/auth.php';
requireLogin();
$page_title = 'Suppliers — DockStock IMS';
$current_page = 'suppliers';
$success=$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=$_POST['action']??'';
    if($action==='add'){
        $name=trim($_POST['name']??'');$contact=trim($_POST['contact_person']??'');$email=trim($_POST['email']??'');$phone=trim($_POST['phone']??'');$address=trim($_POST['address']??'');
        if(!$name){$error='Supplier name required.';}
        else{try{$pdo->prepare("INSERT INTO suppliers (name,contact_person,email,phone,address) VALUES (?,?,?,?,?)")->execute([$name,$contact,$email,$phone,$address]);$success='Supplier added!';}catch(PDOException $e){$error='Error: '.$e->getMessage();}}
    }elseif($action==='delete'){
        $id=(int)($_POST['id']??0);
        if($id){try{$pdo->prepare("DELETE FROM suppliers WHERE id=?")->execute([$id]);$success='Supplier deleted.';}catch(PDOException $e){$error='Cannot delete — may have linked receipts.';}}
    }
}
$suppliers=$pdo->query("SELECT * FROM suppliers ORDER BY name")->fetchAll();
include '../includes/header.php';
?>
<div class="main-content">
<div class="page-header"><h2><i class="fas fa-building"></i> Suppliers</h2></div>
<?php if($success): ?><div class="section"><div class="alert alert-success"><i class="fas fa-check-circle"></i><?=$success?></div></div><?php endif; ?>
<?php if($error): ?><div class="section"><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?=$error?></div></div><?php endif; ?>
<div class="section">
<div class="row g-4">
<div class="col-md-4">
<div class="card card-form">
<div class="card-header"><h5><i class="fas fa-plus"></i> Add Supplier</h5></div>
<div class="card-body">
<form method="POST">
<input type="hidden" name="action" value="add">
<div class="mb-3"><label class="form-label">Name *</label><input type="text" name="name" class="form-control" placeholder="Supplier name" required></div>
<div class="mb-3"><label class="form-label">Contact Person</label><input type="text" name="contact_person" class="form-control" placeholder="Contact name"></div>
<div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" placeholder="email@supplier.com"></div>
<div class="mb-3"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" placeholder="+91-…"></div>
<div class="mb-3"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2" placeholder="Full address"></textarea></div>
<button class="btn btn-primary w-100"><i class="fas fa-save"></i> Add Supplier</button>
</form>
</div>
</div>
</div>
<div class="col-md-8">
<div class="card">
<div class="card-header"><h5><i class="fas fa-list"></i> All Suppliers (<?=count($suppliers)?>)</h5></div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover mb-0">
<thead><tr><th>Name</th><th>Contact</th><th>Email</th><th>Phone</th><th>Actions</th></tr></thead>
<tbody>
<?php if(empty($suppliers)): ?>
<tr><td colspan="5"><div class="empty-state"><i class="fas fa-building"></i><p>No suppliers yet.</p></div></td></tr>
<?php else: foreach($suppliers as $s): ?>
<tr>
<td><strong><?=htmlspecialchars($s['name'])?></strong></td>
<td><?=htmlspecialchars($s['contact_person']??'—')?></td>
<td><?=htmlspecialchars($s['email']??'—')?></td>
<td><?=htmlspecialchars($s['phone']??'—')?></td>
<td>
<form method="POST" style="display:inline" onsubmit="return confirm('Delete this supplier?')">
<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$s['id']?>">
<button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
</form>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
</div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
