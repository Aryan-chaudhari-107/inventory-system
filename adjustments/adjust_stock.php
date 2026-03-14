<?php
session_start();
require '../config/db.php';
require '../auth/auth.php';
requireLogin();
$page_title = 'Stock Adjustment — DockStock IMS';
$current_page = 'adjustments';
$success = $error = '';
$products   = $pdo->query("SELECT id,name,sku,quantity FROM products WHERE is_active=1 ORDER BY name")->fetchAll();
$warehouses = $pdo->query("SELECT id,name FROM warehouses ORDER BY name")->fetchAll();

if($_SERVER['REQUEST_METHOD']==='POST'){
    $pid    = (int)($_POST['product_id']??0);
    $wh_id  = (int)($_POST['warehouse_id']??0);
    $change = (float)($_POST['quantity_change']??0);
    $reason = trim($_POST['reason']??'');
    $date   = $_POST['date']??date('Y-m-d');
    if(!$pid||empty($reason)){ $error='Product and reason are required.'; }
    elseif($change==0){ $error='Quantity change must be non-zero.'; }
    else{
        try{
            $pdo->beginTransaction();
            $stmt=$pdo->prepare("SELECT quantity,location FROM products WHERE id=?");
            $stmt->execute([$pid]); $product=$stmt->fetch();
            if(!$product) throw new Exception('Product not found.');
            $new_qty=$product['quantity']+$change;
            if($new_qty<0) throw new Exception('Stock cannot go negative. Current: '.$product['quantity']);
            $pdo->prepare("UPDATE products SET quantity=? WHERE id=?")->execute([$new_qty,$pid]);
            if($wh_id){
                $pdo->prepare("UPDATE inventory SET quantity=quantity+? WHERE product_id=? AND warehouse_id=?")->execute([$change,$pid,$wh_id]);
            }
            $stmt=$pdo->prepare("INSERT INTO adjustments (product_id,warehouse_id,quantity_change,reason,created_by,date) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$pid,$wh_id?:null,$change,$reason,getActiveUserId(),$date]);
            $adj_id=$pdo->lastInsertId();
            $at=$change>0?'adjustment_in':'adjustment_out';
            $pdo->prepare("INSERT INTO stock_logs (product_id,warehouse_id,action_type,quantity,location,reference_id,reference_type,created_by) VALUES (?,?,?,?,?,?,'adjustment',?)")->execute([$pid,$wh_id?:null,$at,abs($change),$product['location'],$adj_id,getActiveUserId()]);
            $pdo->prepare("INSERT INTO stock_movements (product_id,warehouse_id,type,quantity,reference_id,reference_type,created_by) VALUES (?,?,'ADJUSTMENT',?,?,'adjustment',?)")->execute([$pid,$wh_id?:null,abs($change),$adj_id,getActiveUserId()]);
            $pdo->commit();
            $sym=$change>0?'+':'';
            $success="Adjustment applied: {$sym}{$change} units. New stock: {$new_qty}";
        }catch(Exception $e){$pdo->rollBack();$error='Error: '.$e->getMessage();}
    }
}
include '../includes/header.php';
?>
<div class="main-content">
<div class="page-header">
    <h2><i class="fas fa-sliders-h"></i> Stock Adjustment</h2>
    <a href="../dashboard/dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-home"></i> Dashboard</a>
</div>
<?php if($success): ?><div class="section"><div class="alert alert-success"><i class="fas fa-check-circle"></i><?=$success?></div></div><?php endif; ?>
<?php if($error): ?><div class="section"><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?=$error?></div></div><?php endif; ?>
<div class="section">
<div class="card card-form">
<div class="card-header"><h5><i class="fas fa-balance-scale"></i> Physical Count Adjustment</h5></div>
<div class="card-body">
<form method="POST">
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <label class="form-label">Product *</label>
            <select name="product_id" class="form-select form-select-lg" id="prodSel" onchange="updateInfo(this)" required>
                <option value="">— Select Product —</option>
                <?php foreach($products as $p): ?><option value="<?=$p['id']?>" data-qty="<?=$p['quantity']?>"><?=htmlspecialchars($p['sku'].' - '.$p['name'])?> (<?=number_format($p['quantity'],2)?> in stock)</option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Warehouse (optional)</label>
            <select name="warehouse_id" class="form-select form-select-lg">
                <option value="">— All / Not Specific —</option>
                <?php foreach($warehouses as $w): ?><option value="<?=$w['id']?>"><?=htmlspecialchars($w['name'])?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <label class="form-label">Quantity Change *</label>
            <input type="number" name="quantity_change" id="qtyChange" class="form-control form-control-lg" step="0.01" placeholder="+50 to add, -10 to remove" required onchange="updatePreview()">
            <div class="form-text">Positive = add stock &nbsp;|&nbsp; Negative = remove stock</div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Date</label>
            <input type="date" name="date" class="form-control form-control-lg" value="<?=date('Y-m-d')?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Adjustment Ref# <small style="color:var(--text-muted);font-weight:400">(auto-generated)</small></label>
            <div style="position:relative">
                <input type="text" name="adj_ref" id="refInput" class="form-control form-control-lg" placeholder="e.g. ADJ-20260314-0003" style="padding-right:110px">
                <button type="button" onclick="genRef()" style="position:absolute;right:6px;top:50%;transform:translateY(-50%);background:var(--warning-light);border:1px solid #fde68a;color:var(--warning);border-radius:var(--radius-sm);padding:4px 10px;font-size:0.75rem;font-weight:600;cursor:pointer;white-space:nowrap">
                    <i class="fas fa-sync-alt"></i> Generate
                </button>
            </div>
        </div>
        <div class="col-md-4">
            <!-- Live preview box -->
            <label class="form-label">Preview</label>
            <div id="preview" style="background:var(--bg-page);border:1px solid var(--border);border-radius:var(--radius-md);padding:10px 14px;font-size:0.85rem;color:var(--text-soft)">Select a product and enter quantity</div>
        </div>
    </div>
    <div class="mb-4">
        <label class="form-label">Reason *</label>
        <textarea name="reason" class="form-control" rows="3" placeholder="Physical count mismatch, damage, expiration, shrinkage…" required></textarea>
    </div>
    <div style="border-top:1px solid var(--border);padding-top:16px;display:flex;gap:10px;justify-content:flex-end">
        <a href="../dashboard/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-warning btn-lg"><i class="fas fa-sync"></i> Apply Adjustment</button>
    </div>
</form>
</div>
</div>
</div>
</div>
<script>
function updateInfo(sel){updatePreview();}
function genRef(){
    const d=new Date();
    const pad=n=>String(n).padStart(2,'0');
    const dt=d.getFullYear()+pad(d.getMonth()+1)+pad(d.getDate());
    const r=String(Math.floor(Math.random()*9999)+1).padStart(4,'0');
    document.getElementById('refInput').value='ADJ-'+dt+'-'+r;
}
window.addEventListener('DOMContentLoaded', genRef);
function updatePreview(){
    const sel=document.getElementById('prodSel');
    const opt=sel.options[sel.selectedIndex];
    const qty=parseFloat(opt?.dataset?.qty||0);
    const change=parseFloat(document.getElementById('qtyChange').value||0);
    const box=document.getElementById('preview');
    if(!sel.value){box.textContent='Select a product and enter quantity';box.style.borderColor='var(--border)';return;}
    const newQty=qty+change;
    const color=change>0?'var(--success)':change<0?'var(--danger)':'var(--text-soft)';
    box.innerHTML=`<div>Current: <strong>${qty.toFixed(2)}</strong></div><div style="color:${color}">Change: <strong>${change>=0?'+':''}${change.toFixed(2)}</strong></div><div style="font-weight:700;font-size:1rem;margin-top:4px">New: <span style="color:${newQty<0?'var(--danger)':'var(--success)'}">${newQty.toFixed(2)}</span></div>`;
    box.style.borderColor=newQty<0?'var(--danger)':change!==0?'var(--primary)':'var(--border)';
}
</script>
<?php include '../includes/footer.php'; ?>  