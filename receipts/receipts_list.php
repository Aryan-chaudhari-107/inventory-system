<?php
session_start();
require '../config/db.php';
require '../auth/auth.php';
requireLogin();
$page_title = 'Receipts List — DockStock IMS';
$current_page = 'receipts_list';
$receipts = [];
try {
    $receipts = $pdo->query("
        SELECT r.id, r.supplier, r.reference, r.date, r.status, r.created_at,
               COALESCE(s.name, r.supplier) AS supplier_name,
               w.name AS warehouse_name,
               u.name AS created_by_name,
               COUNT(ri.id) AS item_count,
               SUM(ri.quantity) AS total_qty
        FROM receipts r
        LEFT JOIN suppliers s ON r.supplier_id=s.id
        LEFT JOIN warehouses w ON r.warehouse_id=w.id
        LEFT JOIN users u ON r.created_by=u.id
        LEFT JOIN receipt_items ri ON r.id=ri.receipt_id
        GROUP BY r.id ORDER BY r.created_at DESC LIMIT 100
    ")->fetchAll();
} catch(PDOException $e){ $error=$e->getMessage(); }
include '../includes/header.php';
?>
<div class="main-content">
<div class="page-header">
    <h2><i class="fas fa-list-alt"></i> Receipts List</h2>
    <a href="create_receipt.php" class="btn btn-success"><i class="fas fa-plus"></i> New Receipt</a>
</div>
<div class="section">
<div class="card">
<div class="card-header"><h5><i class="fas fa-truck"></i> All Receipts (<?=count($receipts)?>)</h5></div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover mb-0">
<thead><tr><th>#ID</th><th>Date</th><th>Supplier</th><th>Warehouse</th><th>Reference</th><th>Items</th><th>Total Qty</th><th>Status</th><th>Created By</th></tr></thead>
<tbody>
<?php if(empty($receipts)): ?>
<tr><td colspan="9"><div class="empty-state"><i class="fas fa-truck"></i><p>No receipts yet.</p><a href="create_receipt.php" class="btn btn-success btn-sm">Create First Receipt</a></div></td></tr>
<?php else: foreach($receipts as $r): ?>
<tr>
    <td><strong>#<?=$r['id']?></strong></td>
    <td><?=date('M d, Y',strtotime($r['date']))?></td>
    <td><?=htmlspecialchars($r['supplier_name']??'—')?></td>
    <td><?=htmlspecialchars($r['warehouse_name']??'—')?></td>
    <td><small style="font-family:monospace"><?=htmlspecialchars($r['reference']??'—')?></small></td>
    <td><?=$r['item_count']?> items</td>
    <td><strong><?=number_format($r['total_qty'],2)?></strong></td>
    <td>
        <?php $sc=['pending'=>'bg-warning','validated'=>'bg-success','cancelled'=>'bg-danger']; ?>
        <span class="badge <?=$sc[$r['status']]??'bg-secondary'?>"><?=ucfirst($r['status'])?></span>
    </td>
    <td><?=htmlspecialchars($r['created_by_name']??'—')?></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
