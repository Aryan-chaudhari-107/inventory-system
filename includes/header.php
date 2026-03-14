<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'DockStock IMS'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="page-wrapper">
<?php if (isLoggedIn()): ?>
<?php
$cp = $current_page ?? '';
$sf = basename($_SERVER['PHP_SELF']);
function isActive(string $page, string $cp, string $sf): string {
    return $cp === $page ? 'active' : '';
}
?>
<!-- Sidebar overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="sidebar-logo-icon"><i class="fas fa-ship"></i></div>
        <span class="sidebar-logo-text">DockStock</span>
    </div>
    <nav class="sidebar-nav">
        <div class="sidebar-section-label">Main</div>
        <a href="../dashboard/dashboard.php" class="<?= isActive('dashboard',$cp,$sf) ?>"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a href="../products/products.php" class="<?= isActive('products',$cp,$sf) ?>"><i class="fas fa-box"></i> Products</a>

        <div class="sidebar-section-label">Operations</div>
        <a href="../receipts/create_receipt.php" class="<?= isActive('receipts',$cp,$sf) ?>"><i class="fas fa-truck"></i> Receipts</a>
        <a href="../deliveries/create_delivery.php" class="<?= isActive('deliveries',$cp,$sf) ?>"><i class="fas fa-shipping-fast"></i> Deliveries</a>
        <a href="../transfers/create_transfer.php" class="<?= isActive('transfers',$cp,$sf) ?>"><i class="fas fa-exchange-alt"></i> Transfers</a>
        <a href="../adjustments/adjust_stock.php" class="<?= isActive('adjustments',$cp,$sf) ?>"><i class="fas fa-sliders-h"></i> Adjustments</a>

        <div class="sidebar-section-label">Reports</div>
        <a href="../history/stock_history.php" class="<?= isActive('history',$cp,$sf) ?>"><i class="fas fa-history"></i> Stock History</a>

        <div class="sidebar-section-label">Manage</div>
        <a href="../suppliers/suppliers.php" class="<?= isActive('suppliers',$cp,$sf) ?>"><i class="fas fa-building"></i> Suppliers</a>
        <a href="../customers/customers.php" class="<?= isActive('customers',$cp,$sf) ?>"><i class="fas fa-users"></i> Customers</a>
        <a href="../warehouses/warehouses.php" class="<?= isActive('warehouses',$cp,$sf) ?>"><i class="fas fa-warehouse"></i> Warehouses</a>

        <div class="nav-divider"></div>
        <a href="../auth/logout.php" style="color:#f87171"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?></div>
            <div>
                <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></div>
                <div class="sidebar-user-role"><?= ucfirst($_SESSION['role'] ?? 'staff') ?></div>
            </div>
        </div>
    </div>
</aside>

<!-- Top navbar -->
<nav class="topnav">
    <button class="hamburger-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <span style="font-size:0.85rem;color:var(--text-muted)">
        <i class="fas fa-ship" style="color:var(--primary);margin-right:6px"></i>
        DockStock IMS
    </span>
    <div class="topnav-right">
        <div class="profile-dropdown-wrap">
            <button class="profile-btn" onclick="toggleProfileDD()">
                <div class="nav-avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?></div>
                <span><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
                <i class="fas fa-chevron-down" style="font-size:0.65rem;opacity:0.5"></i>
            </button>
            <div class="profile-dropdown-menu" id="profileDD">
                <div class="dd-header">
                    <div class="dd-name"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></div>
                    <div class="dd-email" style="font-size:0.72rem;color:var(--text-muted)"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
                    <div class="dd-role"><?= ucfirst($_SESSION['role'] ?? 'staff') ?></div>
                </div>
                <a href="#" data-bs-toggle="modal" data-bs-target="#editProfileModal"><i class="fas fa-edit"></i> Edit Profile</a>
                <div class="dd-divider"></div>
                <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
            </div>
        </div>
    </div>
</nav>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5><i class="fas fa-user-edit" style="color:var(--primary);margin-right:8px"></i>Edit Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="../auth/update_profile.php">
                <div class="modal-body">
                    <div style="text-align:center;margin-bottom:16px">
                        <div style="width:64px;height:64px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:800;color:#fff">
                            <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Current Password *</label>
                        <input type="password" name="current_password" class="form-control" placeholder="Enter current password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Username <small style="color:var(--text-muted);font-weight:400">(leave blank to keep)</small></label>
                        <input type="text" name="new_username" class="form-control" placeholder="New username">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password <small style="color:var(--text-muted);font-weight:400">(leave blank to keep)</small></label>
                        <input type="password" name="new_password" class="form-control" placeholder="New password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleProfileDD() {
    document.getElementById('profileDD').classList.toggle('show');
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('.profile-dropdown-wrap')) {
        document.getElementById('profileDD')?.classList.remove('show');
    }
});
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('show');
}
window.addEventListener('resize', function() { if (window.innerWidth > 768) closeSidebar(); });
</script>
<?php endif; ?>