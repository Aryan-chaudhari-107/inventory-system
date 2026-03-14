<?php
session_start();
require '../config/db.php';
require '../auth/auth.php';
requireLogin();
$page_title = 'Stock History — DockStock IMS';
$current_page = 'history';
$product_filter = trim($_GET['product']??'');
$date_from      = $_GET['date_from']??'';
$date_to        = $_GET['date_to']??'';
$action_filter  = $_GET['action']??'';
$wh_filter      = (int)($_GET['warehouse']??0);
$logs=[];
$warehouses = $pdo->query("SELECT id,name FROM warehouses ORDER BY name")->fetchAll();
try{
    $q="SELECT sl.*, p.name AS product_name, p.sku, w.name AS warehouse_name, u.name AS created_by_name
        FROM stock_logs sl
        LEFT JOIN products p ON sl.product_id=p.id
        LEFT JOIN warehouses w ON sl.warehouse_id=w.id
        LEFT JOIN users u ON sl.created_by=u.id
        WHERE 1=1";
    $params=[];
    if(!empty($product_filter)){$q.=" AND (p.name LIKE ? OR p.sku LIKE ?)";$params[]="%$product_filter%";$params[]="%$product_filter%";}
    if(!empty($date_from)){$q.=" AND DATE(sl.timestamp)>=?";$params[]=$date_from;}
    if(!empty($date_to)){$q.=" AND DATE(sl.timestamp)<=?";$params[]=$date_to;}
    if(!empty($action_filter)){$q.=" AND sl.action_type=?";$params[]=$action_filter;}
    if($wh_filter){$q.=" AND sl.warehouse_id=?";$params[]=$wh_filter;}
    $q.=" ORDER BY sl.timestamp DESC LIMIT 200";
    $stmt=$pdo->prepare($q);$stmt->execute($params);$logs=$stmt->fetchAll();
}catch(PDOException $e){$error=$e->getMessage();}
include '../includes/header.php';
?>
<div class="main-content">
<div class="page-header">
    <h2><i class="fas fa-history"></i> Stock History</h2>
</div>

<!-- Filters -->
<div class="section">
<div class="card">
<div class="card-header"><h5><i class="fas fa-filter"></i> Filter</h5></div>
<div class="card-body">
<form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div style="flex:1;min-width:160px">
        <label class="form-label">Product</label>
        <input type="text" name="product" class="form-control" value="<?=htmlspecialchars($product_filter)?>" placeholder="Name or SKU">
    </div>
    <div style="min-width:150px">
        <label class="form-label">Action Type</label>
        <select name="action" class="form-select">
            <option value="">All Actions</option>
            <?php foreach(['initial','receipt','delivery','transfer_in','transfer_out','adjustment_in','adjustment_out'] as $at): ?>
            <option value="<?=$at?>" <?=$action_filter===$at?'selected':''?>><?=ucwords(str_replace('_',' ',$at))?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div style="min-width:140px">
        <label class="form-label">Warehouse</label>
        <select name="warehouse" class="form-select">
            <option value="">All Warehouses</option>
            <?php foreach($warehouses as $w): ?><option value="<?=$w['id']?>" <?=$wh_filter==$w['id']?'selected':''?>><?=htmlspecialchars($w['name'])?></option><?php endforeach; ?>
        </select>
    </div>
    <div style="min-width:130px">
        <label class="form-label">From Date</label>
        <input type="date" name="date_from" class="form-control" value="<?=htmlspecialchars($date_from)?>">
    </div>
    <div style="min-width:130px">
        <label class="form-label">To Date</label>
        <input type="date" name="date_to" class="form-control" value="<?=htmlspecialchars($date_to)?>">
    </div>
    <div style="display:flex;gap:8px;align-items:flex-end">
        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Filter</button>
        <a href="stock_history.php" class="btn btn-outline-secondary"><i class="fas fa-redo"></i></a>
    </div>
</form>
</div>
</div>
</div>

<!-- Table -->
<div class="section">
<div class="card">
<div class="card-header"><h5><i class="fas fa-list"></i> Stock Movements (<?=count($logs)?> records)</h5></div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover mb-0">
<thead><tr><th>Date & Time</th><th>Product</th><th>Action</th><th>Qty Change</th><th>Warehouse</th><th>Reference</th><th>By</th></tr></thead>
<tbody>
<?php if(empty($logs)): ?>
<tr><td colspan="7"><div class="empty-state"><i class="fas fa-history"></i><p>No stock movements found.</p></div></td></tr>
<?php else: foreach($logs as $log):
$action_colors=['receipt'=>'bg-success','delivery'=>'bg-danger','transfer_in'=>'bg-info','transfer_out'=>'bg-secondary','adjustment_in'=>'bg-warning','adjustment_out'=>'bg-secondary','initial'=>'bg-primary'];
$bc=$action_colors[$log['action_type']]??'bg-secondary';
?>
<tr>
    <td><strong><?=date('M j, Y',strtotime($log['timestamp']))?></strong><br><small class="text-soft"><?=date('H:i',strtotime($log['timestamp']))?></small></td>
    <td><strong><?=htmlspecialchars($log['sku']??'—')?></strong><br><small class="text-soft"><?=htmlspecialchars($log['product_name']??'—')?></small></td>
    <td><span class="badge <?=$bc?>"><?=ucwords(str_replace('_',' ',$log['action_type']))?></span></td>
    <td style="font-weight:700;font-size:1rem"><span style="color:<?=$log['quantity']>=0?'var(--success)':'var(--danger)'?>"><?=$log['quantity']>=0?'+':''?><?=number_format($log['quantity'],2)?></span></td>
    <td><?=htmlspecialchars($log['warehouse_name']??'—')?></td>
    <td><?php if($log['reference_id']): ?><span class="badge bg-secondary"><?=ucfirst($log['reference_type']??'')?> #<?=$log['reference_id']?></span><?php else: ?><small class="text-soft">Direct</small><?php endif; ?></td>
    <td><?=htmlspecialchars($log['created_by_name']??'—')?></td>
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
