<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

// Quick stats
$users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$menu = $conn->query("SELECT COUNT(*) as c FROM menu")->fetch_assoc()['c'];
$orders = $conn->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'];
$staff = $conn->query("SELECT COUNT(*) as c FROM users WHERE role IN ('waiter','chef','cashier','manager','admin')")->fetch_assoc()['c'];

include_once 'template/header.php';
?>
<div class="container mt-4">
    <h2 class="mb-4">Welcome, <?= htmlspecialchars($_SESSION['name'] ?? '') ?>!</h2>
    <div class="row g-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="stat-icon text-primary mb-2"><i class="fas fa-users"></i></div>
                    <h5 class="card-title"><?= $users ?></h5>
                    <p class="card-text">Total Users</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="stat-icon text-success mb-2"><i class="fas fa-utensils"></i></div>
                    <h5 class="card-title"><?= $menu ?></h5>
                    <p class="card-text">Menu Items</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="stat-icon text-warning mb-2"><i class="fas fa-clipboard-list"></i></div>
                    <h5 class="card-title"><?= $orders ?></h5>
                    <p class="card-text">Orders</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="stat-icon text-info mb-2"><i class="fas fa-user-tie"></i></div>
                    <h5 class="card-title"><?= $staff ?></h5>
                    <p class="card-text">Staff Members</p>
                </div>
            </div>
        </div>
    </div>
    <hr>
    <div class="row mt-4">
        <div class="col-md-6">
            <h4>Quick Links</h4>
            <ul class="list-group">
                <li class="list-group-item"><a href="menu.php"><i class="fas fa-utensils me-2"></i>Menu Management</a></li>
                <li class="list-group-item"><a href="profiles.php"><i class="fas fa-users me-2"></i>Staff Profiles</a></li>
                <li class="list-group-item"><a href="orders.php"><i class="fas fa-clipboard-list me-2"></i>Order Dashboard</a></li>
                <li class="list-group-item"><a href="stock.php"><i class="fas fa-boxes me-2"></i>Inventory</a></li>
            </ul>
        </div>
        <div class="col-md-6">
            <h4>Recent Activity</h4>
            <div class="alert alert-info">Analytics and logs coming soon.</div>
        </div>
    </div>
</div>
<?php include_once 'template/footer.php'; ?>
        </div>
    </div>
    <hr>
    <div class="row mt-4">
        <div class="col-md-6">
            <h4>Quick Links</h4>
            <ul class="list-group">
                <li class="list-group-item"><a href="menu.php"><i class="fas fa-utensils me-2"></i>Menu Management</a></li>
                <li class="list-group-item"><a href="profiles.php"><i class="fas fa-users me-2"></i>Staff Profiles</a></li>
                <li class="list-group-item"><a href="orders.php"><i class="fas fa-clipboard-list me-2"></i>Order Dashboard</a></li>
                <li class="list-group-item"><a href="stock.php"><i class="fas fa-boxes me-2"></i>Inventory</a></li>
            </ul>
        </div>
        <div class="col-md-6">
            <h4>Recent Activity</h4>
            <div class="alert alert-info">Analytics and logs coming soon.</div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/js/all.min.js"></script>
</body>
</html>
