<?php
session_start();
require '../config/db.php';
require '../auth/auth.php';
requireLogin();
$page_title = 'Deliveries List — DockStock IMS';
$current_page = 'deliveries_list';
$deliveries = [];
try {
    $deliveries = $pdo->query("
        SELECT d.id, d.customer, d.reference, d.date, d.status, d.created_at,
               COALESCE(c.name, d.customer) AS customer_name,
               w.name AS warehouse_name, u.name AS created_by_name,
               COUNT(di.id) AS item_count, SUM(di.quantity) AS total_qty
        FROM deliveries d
        LEFT JOIN customers c ON d.customer_id=c.id
        LEFT JOIN warehouses w ON d.warehouse_id=w.id
        LEFT JOIN users u ON d.created_by=u.id
        LEFT JOIN delivery_items di ON d.id=di.delivery_id
        GROUP BY d.id ORDER BY d.created_at DESC LIMIT 100
    ")->fetchAll();
} catch(PDOException $e){ $error=$e->getMessage(); }
include '../includes/header.php';
?>
<div class="main-content">
<div class="page-header">
    <h2><i class="fas fa-clipboard-list"></i> Deliveries List</h2>
    <a href="create_delivery.php" class="btn btn-danger"><i class="fas fa-plus"></i> New Delivery</a>
</div>
<div class="section">
<div class="card">
<div class="card-header"><h5><i class="fas fa-shipping-fast"></i> All Deliveries (<?=count($deliveries)?>)</h5></div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover mb-0">
<thead><tr><th>#ID</th><th>Date</th><th>Customer</th><th>Warehouse</th><th>Reference</th><th>Items</th><th>Total Qty</th><th>Status</th><th>Created By</th></tr></thead>
<tbody>
<?php if(empty($deliveries)): ?>
<tr><td colspan="9"><div class="empty-state"><i class="fas fa-shipping-fast"></i><p>No deliveries yet.</p><a href="create_delivery.php" class="btn btn-danger btn-sm">Create First Delivery</a></div></td></tr>
<?php else: foreach($deliveries as $d): ?>
<tr>
    <td><strong>#<?=$d['id']?></strong></td>
    <td><?=date('M d, Y',strtotime($d['date']))?></td>
    <td><?=htmlspecialchars($d['customer_name']??'—')?></td>
    <td><?=htmlspecialchars($d['warehouse_name']??'—')?></td>
    <td><small style="font-family:monospace"><?=htmlspecialchars($d['reference']??'—')?></small></td>
    <td><?=$d['item_count']?> items</td>
    <td><strong><?=number_format($d['total_qty'],2)?></strong></td>
    <td><?php $sc=['pending'=>'bg-warning','validated'=>'bg-success','cancelled'=>'bg-danger']; ?><span class="badge <?=$sc[$d['status']]??'bg-secondary'?>"><?=ucfirst($d['status'])?></span></td>
    <td><?=htmlspecialchars($d['created_by_name']??'—')?></td>
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
