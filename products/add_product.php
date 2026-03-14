<?php
session_start();
require '../config/db.php';
require '../auth/auth.php';
requireLogin();
$page_title = 'Add Product — DockStock IMS';
$current_page = 'products';
$success = $error = '';
$categories = $pdo->query("SELECT id,name FROM categories ORDER BY name")->fetchAll();
$warehouses  = $pdo->query("SELECT id,name FROM warehouses ORDER BY name")->fetchAll();

if($_SERVER['REQUEST_METHOD']=='POST'){
    $name=(string)trim($_POST['name']??'');
    $sku=(string)trim($_POST['sku']??'');
    $cat=(int)($_POST['category_id']??0);
    $unit=(string)trim($_POST['unit']??'');
    $qty=(float)($_POST['initial_quantity']??0);
    $loc=(string)trim($_POST['location']??'');
    $reorder=(int)($_POST['reorder_level']??10);
    $wh=(int)($_POST['warehouse_id']??0);
    if(empty($name)||empty($sku)||empty($unit)){$error='Name, SKU and Unit are required.';}
    elseif($qty<0){$error='Quantity cannot be negative.';}
    else{
        try{
            $pdo->beginTransaction();
            $stmt=$pdo->prepare("INSERT INTO products (name,sku,category_id,unit,quantity,location,reorder_level) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$name,$sku,$cat?:null,$unit,$qty,$loc,$reorder]);
            $pid=$pdo->lastInsertId();
            if($wh>0&&$qty>0){
                $pdo->prepare("INSERT INTO inventory (product_id,warehouse_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+?")->execute([$pid,$wh,$qty,$qty]);
            }
            if($qty>0){
                $pdo->prepare("INSERT INTO stock_logs (product_id,warehouse_id,action_type,quantity,location,created_by) VALUES (?,?,'initial',?,?,?)")->execute([$pid,$wh?:null,$qty,$loc,getActiveUserId()]);
            }
            $pdo->commit();
            $success="Product \"".htmlspecialchars($name)."\" added successfully!";
        }catch(PDOException $e){$pdo->rollBack();if(str_contains($e->getMessage(),'Duplicate entry'))$error='SKU already exists.';else $error='Error: '.$e->getMessage();}
    }
}
include '../includes/header.php';
?>
<div class="main-content">
<div class="page-header">
    <h2><i class="fas fa-plus"></i> Add New Product</h2>
    <a href="products.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>
<?php if($success): ?><div class="section"><div class="alert alert-success"><i class="fas fa-check-circle"></i><?=$success?></div></div><?php endif; ?>
<?php if($error): ?><div class="section"><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?=$error?></div></div><?php endif; ?>
<div class="section">
    <div class="card card-form">
        <div class="card-header"><h5><i class="fas fa-box-open"></i> Product Information</h5></div>
        <div class="card-body">
            <form method="POST">
                <div class="row g-4 mb-4">
                    <div class="col-md-6"><label class="form-label">Product Name *</label><input type="text" name="name" class="form-control form-control-lg" value="<?=htmlspecialchars($_POST['name']??'')?>" placeholder="Enter product name" required></div>
                    <div class="col-md-6"><label class="form-label">SKU Code *</label><input type="text" name="sku" class="form-control form-control-lg" value="<?=htmlspecialchars($_POST['sku']??'')?>" placeholder="e.g. SKU-001" required></div>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-6"><label class="form-label">Category</label>
                        <select name="category_id" class="form-select form-select-lg">
                            <option value="">— Select Category —</option>
                            <?php foreach($categories as $c): ?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">Unit of Measure *</label><input type="text" name="unit" class="form-control form-control-lg" value="<?=htmlspecialchars($_POST['unit']??'')?>" placeholder="kg, pcs, box, litre…" required></div>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-4"><label class="form-label">Initial Quantity</label><input type="number" name="initial_quantity" class="form-control form-control-lg" value="<?=$_POST['initial_quantity']??0?>" min="0" step="0.01"></div>
                    <div class="col-md-4"><label class="form-label">Location</label><input type="text" name="location" class="form-control form-control-lg" value="<?=htmlspecialchars($_POST['location']??'')?>" placeholder="A-01, Shelf-5…"></div>
                    <div class="col-md-4"><label class="form-label">Reorder Level</label><input type="number" name="reorder_level" class="form-control form-control-lg" value="<?=$_POST['reorder_level']??10?>" min="0"></div>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-6"><label class="form-label">Assign to Warehouse</label>
                        <select name="warehouse_id" class="form-select form-select-lg">
                            <option value="">— None —</option>
                            <?php foreach($warehouses as $w): ?><option value="<?=$w['id']?>"><?=htmlspecialchars($w['name'])?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="border-top:1px solid var(--border);padding-top:16px;display:flex;gap:10px;justify-content:flex-end">
                    <a href="products.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>
<?php include '../includes/footer.php'; ?>