<?php
session_start();
require '../config/db.php';
require '../auth/auth.php';
requireLogin();
$page_title = 'Edit Product — DockStock IMS';
$current_page = 'products';
$id=(int)($_GET['id']??0);
if(!$id){header('Location: products.php');exit;}
$categories=$pdo->query("SELECT id,name FROM categories ORDER BY name")->fetchAll();
$product=null;$error=$success='';
try{$stmt=$pdo->prepare("SELECT * FROM products WHERE id=? AND is_active=1");$stmt->execute([$id]);$product=$stmt->fetch();}catch(PDOException $e){}
if(!$product){header('Location: products.php');exit;}
if($_SERVER['REQUEST_METHOD']=='POST'){
    $name=trim($_POST['name']??'');$sku=trim($_POST['sku']??'');$cat=(int)($_POST['category_id']??0);
    $unit=trim($_POST['unit']??'');$qty=(float)($_POST['quantity']??0);$loc=trim($_POST['location']??'');$reorder=(int)($_POST['reorder_level']??10);
    if(empty($name)||empty($sku)||empty($unit)){$error='Name, SKU and Unit required.';}
    elseif($qty<0){$error='Quantity cannot be negative.';}
    else{
        try{
            $pdo->beginTransaction();
            $old_qty=$product['quantity'];
            $pdo->prepare("UPDATE products SET name=?,sku=?,category_id=?,unit=?,quantity=?,location=?,reorder_level=? WHERE id=?")->execute([$name,$sku,$cat?:null,$unit,$qty,$loc,$reorder,$id]);
            $change=$qty-$old_qty;
            if($change!=0){
                $at=$change>0?'adjustment_in':'adjustment_out';
                $pdo->prepare("INSERT INTO stock_logs (product_id,action_type,quantity,location,created_by) VALUES (?,?,?,?,?)")->execute([$id,$at,abs($change),$loc,getActiveUserId()]);
                $pdo->prepare("INSERT INTO stock_movements (product_id,type,quantity,created_by) VALUES (?,'ADJUSTMENT',?,?)")->execute([$id,abs($change),getActiveUserId()]);
                $pdo->prepare("UPDATE inventory SET quantity=quantity+? WHERE product_id=?")->execute([$change,$id]);
            }
            $pdo->commit();
            $product=array_merge($product,['name'=>$name,'sku'=>$sku,'category_id'=>$cat,'unit'=>$unit,'quantity'=>$qty,'location'=>$loc,'reorder_level'=>$reorder]);
            $success='Product updated successfully!';
        }catch(PDOException $e){$pdo->rollBack();if(str_contains($e->getMessage(),'Duplicate'))$error='SKU already exists.';else $error='Update failed.';}
    }
}
include '../includes/header.php';
?>
<div class="main-content">
<div class="page-header">
    <h2><i class="fas fa-edit"></i> Edit Product</h2>
    <a href="products.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>
<?php if($success): ?><div class="section"><div class="alert alert-success"><i class="fas fa-check-circle"></i><?=$success?></div></div><?php endif; ?>
<?php if($error): ?><div class="section"><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?=$error?></div></div><?php endif; ?>
<div class="section">
    <div class="card card-form">
        <div class="card-header"><h5><i class="fas fa-box-open"></i> <?=htmlspecialchars($product['name'])?></h5></div>
        <div class="card-body">
            <form method="POST">
                <div class="row g-4 mb-4">
                    <div class="col-md-6"><label class="form-label">Product Name *</label><input type="text" name="name" class="form-control form-control-lg" value="<?=htmlspecialchars($product['name'])?>" required></div>
                    <div class="col-md-6"><label class="form-label">SKU Code *</label><input type="text" name="sku" class="form-control form-control-lg" value="<?=htmlspecialchars($product['sku'])?>" required></div>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-6"><label class="form-label">Category</label>
                        <select name="category_id" class="form-select form-select-lg">
                            <option value="">— None —</option>
                            <?php foreach($categories as $c): ?><option value="<?=$c['id']?>" <?=$product['category_id']==$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">Unit *</label><input type="text" name="unit" class="form-control form-control-lg" value="<?=htmlspecialchars($product['unit'])?>"></div>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" class="form-control form-control-lg" value="<?=$product['quantity']?>" min="0" step="0.01">
                        <div class="form-text">Current: <?=number_format($product['quantity'],2)?></div>
                    </div>
                    <div class="col-md-4"><label class="form-label">Location</label><input type="text" name="location" class="form-control form-control-lg" value="<?=htmlspecialchars($product['location']?:'')?>"></div>
                    <div class="col-md-4"><label class="form-label">Reorder Level</label><input type="number" name="reorder_level" class="form-control form-control-lg" value="<?=$product['reorder_level']?>" min="0"></div>
                </div>
                <div style="border-top:1px solid var(--border);padding-top:16px;display:flex;gap:10px;justify-content:flex-end">
                    <a href="products.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Product</button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
