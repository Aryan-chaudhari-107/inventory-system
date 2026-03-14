<?php
session_start();
require '../config/db.php';
require '../auth/auth.php';
requireLogin();
$page_title = 'Deliveries — DockStock IMS';
$current_page = 'deliveries';
$success = $error = '';
$products   = $pdo->query("SELECT id,name,sku,quantity FROM products WHERE is_active=1 AND quantity>0 ORDER BY name")->fetchAll();
$customers  = $pdo->query("SELECT id,name FROM customers ORDER BY name")->fetchAll();
$warehouses = $pdo->query("SELECT id,name FROM warehouses ORDER BY name")->fetchAll();

if($_SERVER['REQUEST_METHOD']==='POST'){
    $cust_id  = (int)($_POST['customer_id']??0);
    $cust_txt = trim($_POST['customer_free']??'');
    $wh_id    = (int)($_POST['warehouse_id']??0);
    $reference= trim($_POST['reference']??'') ?: generateRefId('DLV');
    $date     = $_POST['date']??date('Y-m-d');
    $pids     = $_POST['product_id']??[];
    $qtys     = $_POST['quantity']??[];
    if(!$wh_id){ $error='Please select a warehouse.'; }
    elseif(empty($pids)){ $error='Add at least one product.'; }
    else{
        $items=[];
        foreach($pids as $i=>$pid){$pid=(int)$pid;$qty=(float)($qtys[$i]??0);if($pid>0&&$qty>0)$items[]=[$pid,$qty];}
        if(empty($items)) $error='No valid items.';
        else{
            try{
                $pdo->beginTransaction();
                foreach($items as [$pid,$qty]){
                    $avail=(float)$pdo->query("SELECT quantity FROM products WHERE id=$pid")->fetchColumn();
                    if($qty>$avail) throw new Exception("Insufficient stock for product ID $pid. Available: $avail");
                }
                $cust_name=$cust_id?null:($cust_txt?:null);$cust_id_val=$cust_id?:null;
                $stmt=$pdo->prepare("INSERT INTO deliveries (customer_id,customer,warehouse_id,reference,status,created_by,date) VALUES (?,?,?,?,'pending',?,?)");
                $stmt->execute([$cust_id_val,$cust_name,$wh_id,$reference?:null,getActiveUserId(),$date]);
                $did=$pdo->lastInsertId();
                foreach($items as [$pid,$qty]){
                    $pdo->prepare("INSERT INTO delivery_items (delivery_id,product_id,quantity) VALUES (?,?,?)")->execute([$did,$pid,$qty]);
                    $pdo->prepare("UPDATE products SET quantity=quantity-? WHERE id=?")->execute([$qty,$pid]);
                    $pdo->prepare("UPDATE inventory SET quantity=quantity-? WHERE product_id=? AND warehouse_id=?")->execute([$qty,$pid,$wh_id]);
                    $loc=$pdo->query("SELECT location FROM products WHERE id=$pid")->fetchColumn();
                    $pdo->prepare("INSERT INTO stock_logs (product_id,warehouse_id,action_type,quantity,location,reference_id,reference_type,created_by) VALUES (?,?,'delivery',?,?,?,'delivery',?)")->execute([$pid,$wh_id,-$qty,$loc,$did,getActiveUserId()]);
                    $pdo->prepare("INSERT INTO stock_movements (product_id,warehouse_id,type,quantity,reference_id,reference_type,created_by) VALUES (?,?,'DELIVERY',?,?,'delivery',?)")->execute([$pid,$wh_id,$qty,$did,getActiveUserId()]);
                }
                $pdo->commit();
                $success="Delivery <strong>#$did</strong> dispatched with ".count($items)." item(s). Ref: <code>$reference</code>";
            }catch(Exception $e){$pdo->rollBack();$error='Error: '.$e->getMessage();}
        }
    }
}

// Fetch deliveries list
$deliveries=[];
try{
    $deliveries=$pdo->query("
        SELECT d.id, d.reference, d.date, d.created_at,
               COALESCE(c.name, d.customer) AS customer_name,
               w.name AS warehouse_name,
               u.name AS created_by_name,
               COUNT(di.id) AS item_count,
               SUM(di.quantity) AS total_qty
        FROM deliveries d
        LEFT JOIN customers c ON d.customer_id=c.id
        LEFT JOIN warehouses w ON d.warehouse_id=w.id
        LEFT JOIN users u ON d.created_by=u.id
        LEFT JOIN delivery_items di ON d.id=di.delivery_id
        GROUP BY d.id ORDER BY d.created_at DESC LIMIT 100
    ")->fetchAll();
}catch(PDOException $e){}

include '../includes/header.php';
?>
<div class="main-content">
<div class="page-header">
    <h2><i class="fas fa-shipping-fast"></i> Deliveries</h2>
</div>

<?php if($success): ?><div class="section"><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?=$success?></div></div><?php endif; ?>
<?php if($error): ?><div class="section"><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?=$error?></div></div><?php endif; ?>

<!-- Create Form -->
<div class="section">
<div class="card card-form">
<div class="card-header">
    <h5><i class="fas fa-box-open"></i> New Outgoing Delivery</h5>
</div>
<div class="card-body">
<form method="POST">
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <label class="form-label">Customer</label>
            <select name="customer_id" class="form-select form-select-lg" onchange="toggleFree(this)">
                <option value="">— Free text / Other —</option>
                <?php foreach($customers as $c): ?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6" id="freeWrap">
            <label class="form-label">Customer Name <small style="color:var(--text-muted);font-weight:400">(free text)</small></label>
            <input type="text" name="customer_free" class="form-control form-control-lg" placeholder="Type customer name…">
        </div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <label class="form-label">Dispatch From Warehouse *</label>
            <select name="warehouse_id" class="form-select form-select-lg" required>
                <option value="">— Select Warehouse —</option>
                <?php foreach($warehouses as $w): ?><option value="<?=$w['id']?>"><?=htmlspecialchars($w['name'])?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Reference / Order#</label>
            <div style="position:relative">
                <input type="text" name="reference" id="refInput" class="form-control form-control-lg" placeholder="Auto-generated" style="padding-right:110px">
                <button type="button" onclick="genRef()" style="position:absolute;right:6px;top:50%;transform:translateY(-50%);background:var(--danger-light);border:1px solid #fca5a5;color:var(--danger);border-radius:var(--radius-sm);padding:4px 10px;font-size:0.75rem;font-weight:600;cursor:pointer;white-space:nowrap">
                    <i class="fas fa-sync-alt"></i> Regenerate
                </button>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Delivery Date *</label>
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
                    <?php foreach($products as $p): ?><option value="<?=$p['id']?>"><?=htmlspecialchars($p['sku'].' - '.$p['name'])?> (<?=$p['quantity']?> available)</option><?php endforeach; ?>
                </select>
                <input type="number" name="quantity[]" class="form-control" placeholder="Qty" step="0.01" min="0.01" required>
                <button type="button" class="btn-remove-row" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
            </div>
        </div>
    </div>
    <div style="border-top:1px solid var(--border);padding-top:16px;display:flex;gap:10px;justify-content:flex-end">
        <button type="reset" class="btn btn-outline-secondary" onclick="genRef()">Clear</button>
        <button type="submit" class="btn btn-danger btn-lg"><i class="fas fa-shipping-fast"></i> Dispatch Delivery</button>
    </div>
</form>
</div>
</div>
</div>

<!-- Deliveries History Table -->
<div class="section">
<div class="card">
<div class="card-header">
    <h5><i class="fas fa-history"></i> Delivery History (<?=count($deliveries)?>)</h5>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover mb-0">
<thead>
    <tr><th>#ID</th><th>Date</th><th>Customer</th><th>Warehouse</th><th>Reference</th><th>Items</th><th>Total Qty</th><th>Created By</th></tr>
</thead>
<tbody>
<?php if(empty($deliveries)): ?>
<tr><td colspan="8"><div class="empty-state"><i class="fas fa-shipping-fast"></i><p>No deliveries yet. Create your first one above.</p></div></td></tr>
<?php else: foreach($deliveries as $d): ?>
<tr>
    <td><span class="badge bg-danger">#<?=$d['id']?></span></td>
    <td><?=date('M d, Y',strtotime($d['date']))?></td>
    <td><strong><?=htmlspecialchars($d['customer_name']??'—')?></strong></td>
    <td><?=htmlspecialchars($d['warehouse_name']??'—')?></td>
    <td><small style="font-family:monospace;color:var(--danger)"><?=htmlspecialchars($d['reference']??'—')?></small></td>
    <td><span class="badge bg-info"><?=$d['item_count']?> items</span></td>
    <td><strong><?=number_format($d['total_qty'],2)?></strong></td>
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

<script>
const prodOpts=`<?php foreach($products as $p): ?><option value="<?=$p['id']?>"><?=htmlspecialchars($p['sku'].' - '.$p['name'])?> (<?=$p['quantity']?> available)</option><?php endforeach; ?>`;
function addRow(){
    const row=document.createElement('div');row.className='item-row';
    row.innerHTML=`<select name="product_id[]" class="form-select" required><option value="">— Select Product —</option>${prodOpts}</select><input type="number" name="quantity[]" class="form-control" placeholder="Qty" step="0.01" min="0.01" required><button type="button" class="btn-remove-row" onclick="removeRow(this)"><i class="fas fa-times"></i></button>`;
    document.getElementById('itemRows').appendChild(row);
}
function removeRow(btn){
    const rows=document.getElementById('itemRows').querySelectorAll('.item-row');
    if(rows.length>1)btn.closest('.item-row').remove();
}
function toggleFree(sel){document.getElementById('freeWrap').style.display=sel.value?'none':'block';}
function genRef(){
    const d=new Date(),pad=n=>String(n).padStart(2,'0');
    const dt=d.getFullYear()+pad(d.getMonth()+1)+pad(d.getDate());
    const r=String(Math.floor(Math.random()*9999)+1).padStart(4,'0');
    document.getElementById('refInput').value='DLV-'+dt+'-'+r;
}
window.addEventListener('DOMContentLoaded',genRef);
</script>
<?php include '../includes/footer.php'; ?>