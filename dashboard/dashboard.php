<?php
session_start();
require '../config/db.php';
if (!isLoggedIn()) { header('Location: ../auth/login.php'); exit; }
$page_title = 'Dashboard — DockStock IMS';
$current_page = 'dashboard';

$stats = ['products'=>0,'warehouses'=>0,'total_stock'=>0,'movements'=>0,'low_stock'=>0,'users'=>0];
$recent_movements = [];
$top_products = [];
$low_stock_items = [];

try {
    $stats['products']   = $pdo->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn();
    $stats['warehouses'] = $pdo->query("SELECT COUNT(*) FROM warehouses")->fetchColumn();
    $stats['total_stock']= (int)$pdo->query("SELECT COALESCE(SUM(quantity),0) FROM inventory")->fetchColumn();
    $stats['users']      = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['low_stock']  = $pdo->query("SELECT COUNT(*) FROM products WHERE is_active=1 AND quantity <= reorder_level AND quantity > 0")->fetchColumn();
    $stmt = $pdo->query("SELECT sm.*, p.name AS product_name, p.sku, w.name AS warehouse_name FROM stock_movements sm LEFT JOIN products p ON sm.product_id=p.id LEFT JOIN warehouses w ON sm.warehouse_id=w.id ORDER BY sm.created_at DESC LIMIT 10");
    $recent_movements = $stmt->fetchAll();
    $stats['movements'] = count($recent_movements);
    $stmt = $pdo->query("SELECT p.id, p.name, p.sku, COALESCE(SUM(i.quantity),0) AS total_qty FROM products p LEFT JOIN inventory i ON p.id=i.product_id WHERE p.is_active=1 GROUP BY p.id,p.name,p.sku ORDER BY total_qty DESC LIMIT 5");
    $top_products = $stmt->fetchAll();
    $stmt = $pdo->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_active=1 AND p.quantity <= p.reorder_level ORDER BY p.quantity ASC LIMIT 8");
    $low_stock_items = $stmt->fetchAll();
} catch (PDOException $e) { $db_error = $e->getMessage(); }

include '../includes/header.php';
?>
<div class="main-content">
    <?php if (isset($_SESSION['profile_success'])): ?>
        <div class="section"><div class="alert alert-success"><i class="fas fa-check-circle"></i><?= $_SESSION['profile_success'] ?></div></div>
        <?php unset($_SESSION['profile_success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['profile_error'])): ?>
        <div class="section"><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?= $_SESSION['profile_error'] ?></div></div>
        <?php unset($_SESSION['profile_error']); ?>
    <?php endif; ?>

    <div class="page-header">
        <h2><i class="fas fa-chart-line"></i> Dashboard</h2>
        <span class="small" style="color:var(--text-soft)">Welcome, <strong><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></strong></span>
    </div>

    <!-- KPI Cards -->
    <div class="section">
        <div class="row g-4">
            <div class="col-xl-3 col-md-6"><div class="card kpi-card kpi-cyan"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><div class="kpi-number"><?= $stats['products'] ?></div><div class="kpi-label">Total Products</div></div><div class="kpi-icon-wrap"><i class="fas fa-box"></i></div></div></div></div></div>
            <div class="col-xl-3 col-md-6"><div class="card kpi-card kpi-blue"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><div class="kpi-number"><?= $stats['warehouses'] ?></div><div class="kpi-label">Warehouses</div></div><div class="kpi-icon-wrap"><i class="fas fa-warehouse"></i></div></div></div></div></div>
            <div class="col-xl-3 col-md-6"><div class="card kpi-card kpi-green"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><div class="kpi-number"><?= number_format($stats['total_stock']) ?></div><div class="kpi-label">Total Stock Units</div></div><div class="kpi-icon-wrap"><i class="fas fa-layer-group"></i></div></div></div></div></div>
            <div class="col-xl-3 col-md-6"><div class="card kpi-card kpi-orange"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><div class="kpi-number"><?= $stats['movements'] ?></div><div class="kpi-label">Recent Movements</div></div><div class="kpi-icon-wrap"><i class="fas fa-exchange-alt"></i></div></div></div></div></div>
            <div class="col-xl-3 col-md-6"><div class="card kpi-card kpi-red"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><div class="kpi-number"><?= $stats['low_stock'] ?></div><div class="kpi-label">Low Stock Alerts</div></div><div class="kpi-icon-wrap"><i class="fas fa-exclamation-triangle"></i></div></div></div></div></div>
            <div class="col-xl-3 col-md-6"><div class="card kpi-card kpi-purple"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><div class="kpi-number"><?= $stats['users'] ?></div><div class="kpi-label">Total Users</div></div><div class="kpi-icon-wrap"><i class="fas fa-users"></i></div></div></div></div></div>
        </div>
    </div>

    <!-- Main content row -->
    <div class="section">
        <div class="row g-4">
            <!-- Recent Movements -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header"><h5><i class="fas fa-history"></i> Recent Stock Movements</h5></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>Date</th><th>Product</th><th>Type</th><th>Qty</th><th>Warehouse</th></tr></thead>
                                <tbody>
                                <?php if (empty($recent_movements)): ?>
                                    <tr><td colspan="5"><div class="empty-state"><i class="fas fa-exchange-alt"></i><p>No movements yet.</p></div></td></tr>
                                <?php else: foreach ($recent_movements as $m): ?>
                                    <?php $types=['RECEIPT'=>'bg-success','DELIVERY'=>'bg-danger','TRANSFER_IN'=>'bg-info','TRANSFER_OUT'=>'bg-warning','ADJUSTMENT'=>'bg-secondary']; ?>
                                    <tr>
                                        <td><small><?= date('M d, Y', strtotime($m['created_at'])) ?></small></td>
                                        <td><strong><?= htmlspecialchars($m['product_name'] ?? '—') ?></strong><br><small class="text-soft"><?= htmlspecialchars($m['sku'] ?? '') ?></small></td>
                                        <td><span class="badge <?= $types[$m['type']] ?? 'bg-secondary' ?>"><?= str_replace('_',' ',$m['type']) ?></span></td>
                                        <td><strong><?= $m['quantity'] ?></strong></td>
                                        <td><?= htmlspecialchars($m['warehouse_name'] ?? '—') ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Products Chart -->
            <div class="col-md-4">
                <div class="card" style="margin-bottom:16px">
                    <div class="card-header"><h5><i class="fas fa-trophy"></i> Top Products</h5></div>
                    <div class="card-body">
                        <canvas id="topChart" height="180"></canvas>
                    </div>
                </div>
                <!-- Low Stock -->
                <?php if (!empty($low_stock_items)): ?>
                <div class="card" style="border-left:3px solid var(--warning)">
                    <div class="card-header"><h5><i class="fas fa-exclamation-triangle" style="color:var(--warning)"></i> Low Stock Alerts</h5></div>
                    <div class="card-body p-0">
                        <?php foreach ($low_stock_items as $item): ?>
                        <div style="padding:10px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
                            <div>
                                <div style="font-weight:600;font-size:0.85rem"><?= htmlspecialchars($item['name']) ?></div>
                                <div style="font-size:0.75rem;color:var(--text-muted)"><?= $item['sku'] ?></div>
                            </div>
                            <span class="badge <?= $item['quantity'] == 0 ? 'bg-danger' : 'bg-warning' ?>">
                                <?= $item['quantity'] ?> / <?= $item['reorder_level'] ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const tp = <?= json_encode(array_map(fn($p)=>['name'=>$p['name'],'qty'=>(float)$p['total_qty']], $top_products)) ?>;
if(tp.length){
    new Chart(document.getElementById('topChart'),{
        type:'bar',
        data:{
            labels:tp.map(p=>p.name.length>12?p.name.substring(0,12)+'…':p.name),
            datasets:[{
                label:'Stock',
                data:tp.map(p=>p.qty),
                backgroundColor:['#2CC7C9','#3b82f6','#22c55e','#f59e0b','#a855f7'],
                borderRadius:6,
                borderSkipped:false
            }]
        },
        options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{color:'#64748b'}},x:{grid:{display:false},ticks:{color:'#64748b'}}}}
    });
}
</script>
<?php include '../includes/footer.php'; ?>
