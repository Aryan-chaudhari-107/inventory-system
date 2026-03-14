<?php
session_start();
require '../config/db.php';
require '../auth/auth.php';
requireLogin();
$page_title = 'Transfers — DockStock IMS';
$current_page = 'transfers';
$success = $error = '';
$products   = $pdo->query("SELECT id,name,sku,quantity FROM products WHERE is_active=1 AND quantity>0 ORDER BY name")->fetchAll();
$warehouses = $pdo->query("SELECT id,name FROM warehouses ORDER BY name")->fetchAll();

if($_SERVER['REQUEST_METHOD']==='POST'){
    $from_wh = (int)($_POST['from_warehouse_id']??0);
    $to_wh   = (int)($_POST['to_warehouse_id']??0);
    $date    = $_POST['date']??date('Y-m-d');
    $pids    = $_POST['product_id']??[];
    $qtys    = $_POST['quantity']??[];
    if(!$from_wh||!$to_wh){ $error='Both warehouses required.'; }
    elseif($from_wh===$to_wh){ $error='From and To warehouses must be different.'; }
    elseif(empty($pids)){ $error='Add at least one product.'; }
    else{
        $items=[];
        foreach($pids as $i=>$pid){$pid=(int)$pid;$qty=(float)($qtys[$i]??0);if($pid>0&&$qty>0)$items[]=[$pid,$qty];}
        if(empty($items)) $error='No valid items.';
        else{
            try{
                $pdo->beginTransaction();
                foreach($items as [$pid,$qty]){
                    $avail=(float)($pdo->query("SELECT COALESCE(quantity,0) FROM inventory WHERE product_id=$pid AND warehouse_id=$from_wh")->fetchColumn()??0);
                    if($qty>$avail) throw new Exception("Insufficient stock in source warehouse for product ID $pid. Available: $avail");
                }
                $stmt=$pdo->prepare("INSERT INTO transfers (from_warehouse_id,to_warehouse_id,status,created_by,date) VALUES (?,?,'pending',?,?)");
                $stmt->execute([$from_wh,$to_wh,getActiveUserId(),$date]);
                $tid=$pdo->lastInsertId();
                foreach($items as [$pid,$qty]){
                    $pdo->prepare("INSERT INTO transfer_items (transfer_id,product_id,quantity) VALUES (?,?,?)")->execute([$tid,$pid,$qty]);
                    $pdo->prepare("UPDATE inventory SET quantity=quantity-? WHERE product_id=? AND warehouse_id=?")->execute([$qty,$pid,$from_wh]);
                    $pdo->prepare("INSERT INTO inventory (product_id,warehouse_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+?")->execute([$pid,$to_wh,$qty,$qty]);
                    $loc=$pdo->query("SELECT location FROM products WHERE id=$pid")->fetchColumn();
                    $pdo->prepare("INSERT INTO stock_logs (product_id,warehouse_id,action_type,quantity,location,reference_id,reference_type,created_by) VALUES (?,?,'transfer_out',?,?,?,'transfer',?)")->execute([$pid,$from_wh,-$qty,$loc,$tid,getActiveUserId()]);
                    $pdo->prepare("INSERT INTO stock_logs (product_id,warehouse_id,action_type,quantity,location,reference_id,reference_type,created_by) VALUES (?,?,'transfer_in',?,?,?,'transfer',?)")->execute([$pid,$to_wh,$qty,$loc,$tid,getActiveUserId()]);
                    $pdo->prepare("INSERT INTO stock_movements (product_id,warehouse_id,type,quantity,reference_id,reference_type,created_by) VALUES (?,?,'TRANSFER_OUT',?,?,'transfer',?)")->execute([$pid,$from_wh,$qty,$tid,getActiveUserId()]);
                    $pdo->prepare("INSERT INTO stock_movements (product_id,warehouse_id,type,quantity,reference_id,reference_type,created_by) VALUES (?,?,'TRANSFER_IN',?,?,'transfer',?)")->execute([$pid,$to_wh,$qty,$tid,getActiveUserId()]);
                }
                $pdo->commit();
                $success="Transfer <strong>#$tid</strong> completed with ".count($items)." item(s)!";
            }catch(Exception $e){$pdo->rollBack();$error='Error: '.$e->getMessage();}
        }
    }
}

// Fetch transfers list
$transfers=[];
try{
    $transfers=$pdo->query("
        SELECT t.id, t.date, t.created_at,
               fw.name AS from_wh, tw.name AS to_wh,
               u.name AS created_by_name,
               COUNT(ti.id) AS item_count,
               SUM(ti.quantity) AS total_qty
        FROM transfers t
        LEFT JOIN warehouses fw ON t.from_warehouse_id=fw.id
        LEFT JOIN warehouses tw ON t.to_warehouse_id=tw.id
        LEFT JOIN users u ON t.created_by=u.id
        LEFT JOIN transfer_items ti ON t.id=ti.transfer_id
        GROUP BY t.id ORDER BY t.created_at DESC LIMIT 100
    ")->fetchAll();
}catch(PDOException $e){}

include '../includes/header.php';
?>
<div class="main-content">
<div class="page-header">
    <h2><i class="fas fa-exchange-alt"></i> Transfers</h2>
</div>

<?php if($success): ?><div class="section"><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?=$success?></div></div><?php endif; ?>
<?php if($error): ?><div class="section"><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?=$error?></div></div><?php endif; ?>

<!-- Create Form -->
<div class="section">
<div class="card card-form">
<div class="card-header">
    <h5><i class="fas fa-random"></i> New Warehouse Transfer</h5>
</div>
<div class="card-body">
<form method="POST">
    <!-- Warehouse arrow selector -->
    <div style="display:grid;grid-template-columns:1fr 44px 1fr;gap:10px;align-items:end;margin-bottom:20px">
        <div>
            <label class="form-label">From Warehouse *</label>
            <select name="from_warehouse_id" class="form-select form-select-lg" required>
                <option value="">— Source —</option>
                <?php foreach($warehouses as $w): ?><option value="<?=$w['id']?>"><?=htmlspecialchars($w['name'])?></option><?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex;align-items:center;justify-content:center;padding-bottom:4px">
            <div style="width:36px;height:36px;background:var(--primary-light);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:1rem">
                <i class="fas fa-arrow-right"></i>
            </div>
        </div>
        <div>
            <label class="form-label">To Warehouse *</label>
            <select name="to_warehouse_id" class="form-select form-select-lg" required>
                <option value="">— Destination —</option>
                <?php foreach($warehouses as $w): ?><option value="<?=$w['id']?>"><?=htmlspecialchars($w['name'])?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <label class="form-label">Transfer Date *</label>
            <input type="date" name="date" class="form-control form-control-lg" value="<?=date('Y-m-d')?>" required>
        </div>
    </div>
    <div style="border-top:1px solid var(--border);padding-top:16px;margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <label class="form-label" style="margin:0;font-size:0.9rem;font-weight:700">Products *</label>
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addRow()"><i class="fas fa-plus"></i> Add Product</button>
        </div>
        <div id="itemRows">
            <div class="item-row">
                <select name="product_id[]" class="form-select" required>
                    <option value="">— Select Product —</option>
                    <?php foreach($products as $p): ?><option value="<?=$p['id']?>"><?=htmlspecialchars($p['sku'].' - '.$p['name'])?> (<?=$p['quantity']?> total)</option><?php endforeach; ?>
                </select>
                <input type="number" name="quantity[]" class="form-control" placeholder="Qty" step="0.01" min="0.01" required>
                <button type="button" class="btn-remove-row" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
            </div>
        </div>
    </div>
    <div style="border-top:1px solid var(--border);padding-top:16px;display:flex;gap:10px;justify-content:flex-end">
        <button type="reset" class="btn btn-outline-secondary">Clear</button>
        <button type="submit" class="btn btn-info btn-lg"><i class="fas fa-exchange-alt"></i> Execute Transfer</button>
    </div>
</form>
</div>
</div>
</div>

<!-- Transfers History Table -->
<div class="section">
<div class="card">
<div class="card-header">
    <h5><i class="fas fa-history"></i> Transfer History (<?=count($transfers)?>)</h5>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover mb-0">
<thead>
    <tr><th>#ID</th><th>Date</th><th>From</th><th>To</th><th>Items</th><th>Total Qty</th><th>Created By</th></tr>
</thead>
<tbody>
<?php if(empty($transfers)): ?>
<tr><td colspan="7"><div class="empty-state"><i class="fas fa-exchange-alt"></i><p>No transfers yet. Create your first one above.</p></div></td></tr>
<?php else: foreach($transfers as $t): ?>
<tr>
    <td><span class="badge bg-info">#<?=$t['id']?></span></td>
    <td><?=date('M d, Y',strtotime($t['date']))?></td>
    <td>
        <span style="display:inline-flex;align-items:center;gap:6px">
            <i class="fas fa-warehouse" style="color:var(--text-muted);font-size:0.75rem"></i>
            <strong><?=htmlspecialchars($t['from_wh']??'—')?></strong>
        </span>
    </td>
    <td>
        <span style="display:inline-flex;align-items:center;gap:6px">
            <i class="fas fa-arrow-right" style="color:var(--primary);font-size:0.75rem"></i>
            <strong><?=htmlspecialchars($t['to_wh']??'—')?></strong>
        </span>
    </td>
    <td><span class="badge bg-secondary"><?=$t['item_count']?> items</span></td>
    <td><strong><?=number_format($t['total_qty'],2)?></strong></td>
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

<script>
const prodOpts=`<?php foreach($products as $p): ?><option value="<?=$p['id']?>"><?=htmlspecialchars($p['sku'].' - '.$p['name'])?> (<?=$p['quantity']?> total)</option><?php endforeach; ?>`;
function addRow(){
    const row=document.createElement('div');row.className='item-row';
    row.innerHTML=`<select name="product_id[]" class="form-select" required><option value="">— Select Product —</option>${prodOpts}</select><input type="number" name="quantity[]" class="form-control" placeholder="Qty" step="0.01" min="0.01" required><button type="button" class="btn-remove-row" onclick="removeRow(this)"><i class="fas fa-times"></i></button>`;
    document.getElementById('itemRows').appendChild(row);
}
function removeRow(btn){
    const rows=document.getElementById('itemRows').querySelectorAll('.item-row');
    if(rows.length>1)btn.closest('.item-row').remove();
}
</script>
<?php include '../includes/footer.php'; ?>