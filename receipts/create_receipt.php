<?php
session_start();
require '../config/db.php';
require '../auth/auth.php';
requireLogin();
$page_title = 'Receipts — DockStock IMS';
$current_page = 'receipts';
$success = $error = '';
$products   = $pdo->query("SELECT id,name,sku,quantity FROM products WHERE is_active=1 ORDER BY name")->fetchAll();
$suppliers  = $pdo->query("SELECT id,name FROM suppliers ORDER BY name")->fetchAll();
$warehouses = $pdo->query("SELECT id,name FROM warehouses ORDER BY name")->fetchAll();

if($_SERVER['REQUEST_METHOD']==='POST'){
    $supplier_id = (int)($_POST['supplier_id']??0);
    $supplier_txt= trim($_POST['supplier_free']??'');
    $wh_id       = (int)($_POST['warehouse_id']??0);
    $reference   = trim($_POST['reference']??'') ?: generateRefId('RCP');
    $date        = $_POST['date']??date('Y-m-d');
    $pids        = $_POST['product_id']??[];
    $qtys        = $_POST['quantity']??[];
    if(!$wh_id){ $error='Please select a warehouse.'; }
    elseif(empty($pids)){ $error='Add at least one product.'; }
    else{
        $items=[];
        foreach($pids as $i=>$pid){
            $pid=(int)$pid; $qty=(float)($qtys[$i]??0);
            if($pid>0&&$qty>0) $items[]=[$pid,$qty];
        }
        if(empty($items)){ $error='No valid items.'; }
        else{
            try{
                $pdo->beginTransaction();
                $sup_name=$supplier_id?null:($supplier_txt?:null);
                $sup_id=$supplier_id?:null;
                $stmt=$pdo->prepare("INSERT INTO receipts (supplier_id,supplier,warehouse_id,reference,status,created_by,date) VALUES (?,?,?,?,'pending',?,?)");
                $stmt->execute([$sup_id,$sup_name,$wh_id,$reference?:null,getActiveUserId(),$date]);
                $rid=$pdo->lastInsertId();
                foreach($items as [$pid,$qty]){
                    $pdo->prepare("INSERT INTO receipt_items (receipt_id,product_id,quantity) VALUES (?,?,?)")->execute([$rid,$pid,$qty]);
                    $pdo->prepare("UPDATE products SET quantity=quantity+? WHERE id=?")->execute([$qty,$pid]);
                    $pdo->prepare("INSERT INTO inventory (product_id,warehouse_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+?")->execute([$pid,$wh_id,$qty,$qty]);
                    $loc=$pdo->query("SELECT location FROM products WHERE id=$pid")->fetchColumn();
                    $pdo->prepare("INSERT INTO stock_logs (product_id,warehouse_id,action_type,quantity,location,reference_id,reference_type,created_by) VALUES (?,?,'receipt',?,?,?,'receipt',?)")->execute([$pid,$wh_id,$qty,$loc,$rid,getActiveUserId()]);
                    $pdo->prepare("INSERT INTO stock_movements (product_id,warehouse_id,type,quantity,reference_id,reference_type,created_by) VALUES (?,?,'RECEIPT',?,?,'receipt',?)")->execute([$pid,$wh_id,$qty,$rid,getActiveUserId()]);
                }
                $pdo->commit();
                $success="Receipt <strong>#$rid</strong> created with ".count($items)." item(s). Ref: <code>$reference</code>";
            }catch(PDOException $e){$pdo->rollBack();$error='Error: '.$e->getMessage();}
        }
    }
}

// Fetch receipts list
$receipts=[];
try{
    $receipts=$pdo->query("
        SELECT r.id, r.reference, r.date, r.created_at,
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
}catch(PDOException $e){}

include '../includes/header.php';
?>
<div class="main-content">
<div class="page-header">
    <h2><i class="fas fa-truck"></i> Receipts</h2>
</div>

<?php if($success): ?><div class="section"><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?=$success?></div></div><?php endif; ?>
<?php if($error): ?><div class="section"><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?=$error?></div></div><?php endif; ?>

<!-- Create Form -->
<div class="section">
<div class="card card-form">
<div class="card-header">
    <h5><i class="fas fa-inbox"></i> New Incoming Receipt</h5>
</div>
<div class="card-body">
<form method="POST">
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <label class="form-label">Supplier</label>
            <select name="supplier_id" class="form-select form-select-lg" id="supSelect" onchange="toggleFree(this)">
                <option value="">— Free text / Other —</option>
                <?php foreach($suppliers as $s): ?><option value="<?=$s['id']?>"><?=htmlspecialchars($s['name'])?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6" id="freeWrap">
            <label class="form-label">Supplier Name <small style="color:var(--text-muted);font-weight:400">(free text)</small></label>
            <input type="text" name="supplier_free" class="form-control form-control-lg" placeholder="Type supplier name…">
        </div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <label class="form-label">Warehouse *</label>
            <select name="warehouse_id" class="form-select form-select-lg" required>
                <option value="">— Select Warehouse —</option>
                <?php foreach($warehouses as $w): ?><option value="<?=$w['id']?>"><?=htmlspecialchars($w['name'])?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Reference / PO#</label>
            <div style="position:relative">
                <input type="text" name="reference" id="refInput" class="form-control form-control-lg" placeholder="Auto-generated" style="padding-right:110px">
                <button type="button" onclick="genRef()" style="position:absolute;right:6px;top:50%;transform:translateY(-50%);background:var(--primary-light);border:1px solid var(--primary);color:var(--primary-dark);border-radius:var(--radius-sm);padding:4px 10px;font-size:0.75rem;font-weight:600;cursor:pointer;white-space:nowrap">
                    <i class="fas fa-sync-alt"></i> Regenerate
                </button>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Receipt Date *</label>
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
                    <?php foreach($products as $p): ?><option value="<?=$p['id']?>"><?=htmlspecialchars($p['sku'].' - '.$p['name'])?> (<?=$p['quantity']?> in stock)</option><?php endforeach; ?>
                </select>
                <input type="number" name="quantity[]" class="form-control" placeholder="Qty" step="0.01" min="0.01" required>
                <button type="button" class="btn-remove-row" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
            </div>
        </div>
    </div>
    <div style="border-top:1px solid var(--border);padding-top:16px;display:flex;gap:10px;justify-content:flex-end">
        <button type="reset" class="btn btn-outline-secondary" onclick="genRef()">Clear</button>
        <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-plus"></i> Receive Stock</button>
    </div>
</form>
</div>
</div>
</div>

<!-- Receipts History Table -->
<div class="section">
<div class="card">
<div class="card-header">
    <h5><i class="fas fa-history"></i> Receipt History (<?=count($receipts)?>)</h5>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover mb-0">
<thead>
    <tr><th>#ID</th><th>Date</th><th>Supplier</th><th>Warehouse</th><th>Reference</th><th>Items</th><th>Total Qty</th><th>Created By</th></tr>
</thead>
<tbody>
<?php if(empty($receipts)): ?>
<tr><td colspan="8"><div class="empty-state"><i class="fas fa-truck"></i><p>No receipts yet. Create your first one above.</p></div></td></tr>
<?php else: foreach($receipts as $r): ?>
<tr>
    <td><span class="badge bg-success">#<?=$r['id']?></span></td>
    <td><?=date('M d, Y',strtotime($r['date']))?></td>
    <td><strong><?=htmlspecialchars($r['supplier_name']??'—')?></strong></td>
    <td><?=htmlspecialchars($r['warehouse_name']??'—')?></td>
    <td><small style="font-family:monospace;color:var(--primary-dark)"><?=htmlspecialchars($r['reference']??'—')?></small></td>
    <td><span class="badge bg-info"><?=$r['item_count']?> items</span></td>
    <td><strong><?=number_format($r['total_qty'],2)?></strong></td>
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

<script>
const prodOpts=`<?php foreach($products as $p): ?><option value="<?=$p['id']?>"><?=htmlspecialchars($p['sku'].' - '.$p['name'])?> (<?=$p['quantity']?> in stock)</option><?php endforeach; ?>`;
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
    document.getElementById('refInput').value='RCP-'+dt+'-'+r;
}
window.addEventListener('DOMContentLoaded',genRef);
</script>
<?php include '../includes/footer.php'; ?>