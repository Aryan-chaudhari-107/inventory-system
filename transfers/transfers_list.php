<?php
session_start();
require '../config/db.php';
require '../auth/auth.php';
requireLogin();
$page_title = 'Transfers List — DockStock IMS';
$current_page = 'transfers_list';
$transfers = [];
try {
    $transfers = $pdo->query("
        SELECT t.id, t.date, t.status, t.created_at,
               fw.name AS from_wh, tw.name AS to_wh,
               u.name AS created_by_name,
               COUNT(ti.id) AS item_count, SUM(ti.quantity) AS total_qty
        FROM transfers t
        LEFT JOIN warehouses fw ON t.from_warehouse_id=fw.id
        LEFT JOIN warehouses tw ON t.to_warehouse_id=tw.id
        LEFT JOIN users u ON t.created_by=u.id
        LEFT JOIN transfer_items ti ON t.id=ti.transfer_id
        GROUP BY t.id ORDER BY t.created_at DESC LIMIT 100
    ")->fetchAll();
} catch(PDOException $e){ $error=$e->getMessage(); }
include '../includes/header.php';
?>
<div class="main-content">
<div class="page-header">
    <h2><i class="fas fa-random"></i> Transfers List</h2>
    <a href="create_transfer.php" class="btn btn-info"><i class="fas fa-plus"></i> New Transfer</a>
</div>
<div class="section">
<div class="card">
<div class="card-header"><h5><i class="fas fa-exchange-alt"></i> All Transfers (<?=count($transfers)?>)</h5></div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover mb-0">
<thead><tr><th>#ID</th><th>Date</th><th>From Warehouse</th><th>To Warehouse</th><th>Items</th><th>Total Qty</th><th>Status</th><th>Created By</th></tr></thead>
<tbody>
<?php if(empty($transfers)): ?>
<tr><td colspan="8"><div class="empty-state"><i class="fas fa-exchange-alt"></i><p>No transfers yet.</p><a href="create_transfer.php" class="btn btn-info btn-sm">Create First Transfer</a></div></td></tr>
<?php else: foreach($transfers as $t): ?>
<tr>
    <td><strong>#<?=$t['id']?></strong></td>
    <td><?=date('M d, Y',strtotime($t['date']))?></td>
    <td><?=htmlspecialchars($t['from_wh']??'—')?></td>
    <td><?=htmlspecialchars($t['to_wh']??'—')?></td>
    <td><?=$t['item_count']?> items</td>
    <td><strong><?=number_format($t['total_qty'],2)?></strong></td>
    <td><?php $sc=['pending'=>'bg-warning','validated'=>'bg-success','cancelled'=>'bg-danger']; ?><span class="badge <?=$sc[$t['status']]??'bg-secondary'?>"><?=ucfirst($t['status'])?></span></td>
    <td><?=htmlspecialchars($t['created_by_name']??'—')?></td>
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
