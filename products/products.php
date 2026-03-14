<?php
session_start();
require '../config/db.php';
require '../auth/auth.php';
requireLogin();
$page_title = 'Products — DockStock IMS';
$current_page = 'products';
$search = $_GET['search'] ?? '';
$cat_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$products = [];
$categories = [];
$stats = ['total'=>0,'in_stock'=>0,'low_stock'=>0,'out_stock'=>0];
try {
    $categories = $pdo->query("SELECT id,name FROM categories ORDER BY name")->fetchAll();
    $q = "SELECT p.id,p.name,p.sku,p.unit,p.quantity,p.location,p.reorder_level,c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_active=1";
    $params=[];
    if(!empty($search)){$q.=" AND (p.name LIKE ? OR p.sku LIKE ?)";$params[]="%$search%";$params[]="%$search%";}
    if(!empty($cat_filter)){$q.=" AND p.category_id=?";$params[]=$cat_filter;}
    if($status_filter==='in_stock')$q.=" AND p.quantity > p.reorder_level";
    elseif($status_filter==='low_stock')$q.=" AND p.quantity <= p.reorder_level AND p.quantity > 0";
    elseif($status_filter==='out_stock')$q.=" AND p.quantity = 0";
    $q.=" ORDER BY p.name ASC";
    $stmt=$pdo->prepare($q);$stmt->execute($params);$products=$stmt->fetchAll();
    foreach($products as $p){
        $stats['total']++;
        if($p['quantity']==0)$stats['out_stock']++;
        elseif($p['quantity']<=$p['reorder_level'])$stats['low_stock']++;
        else $stats['in_stock']++;
    }
} catch(PDOException $e){$error=$e->getMessage();}
include '../includes/header.php';
?>
<div class="main-content">
<div class="page-header">
    <h2><i class="fas fa-box"></i> Products</h2>
    <a href="add_product.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Product</a>
</div>

<!-- Stats bar -->
<div class="stats-bar">
    <div class="stat-pill"><div class="dot" style="background:#2CC7C9"></div><strong><?= $stats['total'] ?></strong> Total</div>
    <div class="stat-pill"><div class="dot" style="background:#16a34a"></div><strong><?= $stats['in_stock'] ?></strong> In Stock</div>
    <div class="stat-pill"><div class="dot" style="background:#d97706"></div><strong><?= $stats['low_stock'] ?></strong> Low Stock</div>
    <div class="stat-pill"><div class="dot" style="background:#dc2626"></div><strong><?= $stats['out_stock'] ?></strong> Out of Stock</div>
</div>

<!-- Search/filter -->
<div class="section">
    <div class="card">
        <div class="card-body">
            <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
                <div style="flex:1;min-width:180px">
                    <label class="form-label">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Name or SKU…">
                    </div>
                </div>
                <div style="min-width:150px">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach($categories as $c): ?>
                        <option value="<?=$c['id']?>" <?=($cat_filter==$c['id'])?'selected':''?>><?=htmlspecialchars($c['name'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="min-width:140px">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="in_stock" <?=($status_filter==='in_stock')?'selected':''?>>In Stock</option>
                        <option value="low_stock" <?=($status_filter==='low_stock')?'selected':''?>>Low Stock</option>
                        <option value="out_stock" <?=($status_filter==='out_stock')?'selected':''?>>Out of Stock</option>
                    </select>
                </div>
                <div style="display:flex;gap:8px;align-items:flex-end">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Filter</button>
                    <a href="products.php" class="btn btn-outline-secondary"><i class="fas fa-redo"></i></a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Table -->
<div class="section">
    <div class="card">
        <div class="card-header"><h5><i class="fas fa-list"></i> All Products (<?= count($products) ?>)</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>SKU</th><th>Name</th><th>Category</th><th>Qty</th><th>Location</th><th>Reorder</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if(empty($products)): ?>
                        <tr><td colspan="8"><div class="empty-state"><i class="fas fa-box-open"></i><p>No products found.</p><a href="add_product.php" class="btn btn-primary btn-sm">Add First Product</a></div></td></tr>
                    <?php else: foreach($products as $p): ?>
                        <tr>
                            <td><strong style="font-family:monospace"><?=htmlspecialchars($p['sku'])?></strong></td>
                            <td><?=htmlspecialchars($p['name'])?></td>
                            <td><?=htmlspecialchars($p['category_name']??'—')?></td>
                            <td>
                                <span class="badge <?=$p['quantity']==0?'bg-danger':($p['quantity']<=$p['reorder_level']?'bg-warning':'bg-success')?>">
                                    <?=number_format($p['quantity'])?> <?=htmlspecialchars($p['unit']?:'')?>
                                </span>
                            </td>
                            <td><?=htmlspecialchars($p['location']??'—')?></td>
                            <td><?=$p['reorder_level']?></td>
                            <td>
                                <?php if($p['quantity']==0): ?>
                                    <span class="badge bg-danger">Out of Stock</span>
                                <?php elseif($p['quantity']<=$p['reorder_level']): ?>
                                    <span class="badge bg-warning">Low Stock</span>
                                <?php else: ?>
                                    <span class="badge bg-success">In Stock</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="edit_product.php?id=<?=$p['id']?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                    <a href="delete_product.php?id=<?=$p['id']?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete <?=htmlspecialchars($p['name'])?>?')"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
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
