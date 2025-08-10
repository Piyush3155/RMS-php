<?php
$current = basename($_SERVER['PHP_SELF']);
function active($file) { return $GLOBALS['current'] === $file ? 'active' : ''; }
?>
<nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= active('dashboard.php') ?>" href="/dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                    <span>Customer</span>
                </h6>
                <a class="nav-link <?= active('menu.php') ?>" href="/menu.php">
                    <i class="fas fa-utensils me-2"></i>Menu
                </a>
                <a class="nav-link <?= active('reservation.php') ?>" href="/reservation.php">
                    <i class="fas fa-calendar-alt me-2"></i>Reservations
                </a>
                <a class="nav-link <?= active('feedback.php') ?>" href="/feedback.php">
                    <i class="fas fa-comments me-2"></i>Feedback
                </a>
                <a class="nav-link <?= active('loyalty.php') ?>" href="/loyalty.php">
                    <i class="fas fa-star me-2"></i>Loyalty
                </a>
                <a class="nav-link <?= active('promo.php') ?>" href="/promo.php">
                    <i class="fas fa-tags me-2"></i>Promos
                </a>
            </li>
            <li class="nav-item">
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                    <span>Orders</span>
                </h6>
                <a class="nav-link <?= active('order_dashboard.php') ?>" href="/order_dashboard.php">
                    <i class="fas fa-clipboard-list me-2"></i>Dashboard
                </a>
                <a class="nav-link <?= active('kds.php') ?>" href="/kds.php">
                    <i class="fas fa-desktop me-2"></i>Kitchen Display
                </a>
                <a class="nav-link <?= active('recipe.php') ?>" href="/recipe.php">
                    <i class="fas fa-book me-2"></i>Recipes
                </a>
            </li>
            <li class="nav-item">
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                    <span>Staff</span>
                </h6>
                <a class="nav-link <?= active('profiles.php') ?>" href="/profiles.php">
                    <i class="fas fa-users me-2"></i>Profiles
                </a>
                <a class="nav-link <?= active('shifts.php') ?>" href="/shifts.php">
                    <i class="fas fa-clock me-2"></i>Shifts
                </a>
                <a class="nav-link <?= active('tips.php') ?>" href="/tips.php">
                    <i class="fas fa-dollar-sign me-2"></i>Tips
                </a>
            </li>
            <li class="nav-item">
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                    <span>Inventory</span>
                </h6>
                <a class="nav-link <?= active('stock.php') ?>" href="/stock.php">
                    <i class="fas fa-boxes me-2"></i>Stock
                </a>
                <a class="nav-link <?= active('purchase.php') ?>" href="/purchase.php">
                    <i class="fas fa-shopping-cart me-2"></i>Purchase
                </a>
                <a class="nav-link <?= active('supplier.php') ?>" href="/supplier.php">
                    <i class="fas fa-truck me-2"></i>Suppliers
                </a>
            </li>
        </ul>
    </div>
</nav>
