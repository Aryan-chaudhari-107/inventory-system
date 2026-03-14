<?php
session_start();
require '../config/db.php';
require '../auth/auth.php';
requireLogin();
$page_title = 'Warehouses — DockStock IMS';
$current_page = 'warehouses';
$success=$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=$_POST['action']??'';
    if($action==='add'){
        $name=trim($_POST['name']??'');$loc=trim($_POST['location']??'');
        if(!$name){$error='Warehouse name required.';}
        else{try{$pdo->prepare("INSERT INTO warehouses (name,location) VALUES (?,?)")->execute([$name,$loc]);$success='Warehouse added!';}catch(PDOException $e){$error='Error: '.$e->getMessage();}}
    }elseif($action==='delete'){
        $id=(int)($_POST['id']??0);
        if($id){try{$pdo->prepare("DELETE FROM warehouses WHERE id=?")->execute([$id]);$success='Warehouse deleted.';}catch(PDOException $e){$error='Cannot delete — has linked inventory or operations.';}}
    }
}
$warehouses=$pdo->query("SELECT w.*, COUNT(DISTINCT i.product_id) AS product_count, COALESCE(SUM(i.quantity),0) AS total_qty FROM warehouses w LEFT JOIN inventory i ON w.id=i.warehouse_id GROUP BY w.id ORDER BY w.name")->fetchAll();
include '../includes/header.php';
?>
<div class="main-content">
<div class="page-header"><h2><i class="fas fa-warehouse"></i> Warehouses</h2></div>
<?php if($success): ?><div class="section"><div class="alert alert-success"><i class="fas fa-check-circle"></i><?=$success?></div></div><?php endif; ?>
<?php if($error): ?><div class="section"><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?=$error?></div></div><?php endif; ?>
<div class="section">
<div class="row g-4">
<div class="col-md-4">
<div class="card card-form">
<div class="card-header"><h5><i class="fas fa-plus"></i> Add Warehouse</h5></div>
<div class="card-body">
<form method="POST">
<input type="hidden" name="action" value="add">
<div class="mb-3"><label class="form-label">Warehouse Name *</label><input type="text" name="name" class="form-control" placeholder="Main Warehouse" required></div>
<div class="mb-3"><label class="form-label">Location / Address</label><input type="text" name="location" class="form-control" placeholder="Block A, Industrial Zone"></div>
<button class="btn btn-primary w-100"><i class="fas fa-save"></i> Add Warehouse</button>
</form>
</div>
</div>
</div>
<div class="col-md-8">
<div class="card">
<div class="card-header"><h5><i class="fas fa-list"></i> All Warehouses (<?=count($warehouses)?>)</h5></div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover mb-0">
<thead><tr><th>Name</th><th>Location</th><th>Products</th><th>Total Qty</th><th>Actions</th></tr></thead>
<tbody>
<?php if(empty($warehouses)): ?>
<tr><td colspan="5"><div class="empty-state"><i class="fas fa-warehouse"></i><p>No warehouses yet.</p></div></td></tr>
<?php else: foreach($warehouses as $w): ?>
<tr>
<td><strong><?=htmlspecialchars($w['name'])?></strong></td>
<td><?=htmlspecialchars($w['location']??'—')?></td>
<td><span class="badge bg-info"><?=$w['product_count']?> products</span></td>
<td><strong><?=number_format($w['total_qty'],2)?></strong></td>
<td>
<form method="POST" style="display:inline" onsubmit="return confirm('Delete this warehouse?')">
<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$w['id']?>">
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
