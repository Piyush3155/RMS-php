<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
include_once 'template/header.php';

$type = $_GET['type'] ?? 'dine-in';
$orders = $conn->query("SELECT o.*, u.name FROM orders o JOIN users u ON o.user_id = u.id WHERE order_type='$type' ORDER BY created_at DESC");
?>
<div class="container mt-4">
    <h2>Order Dashboard</h2>
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link <?= $type=='dine-in'?'active':'' ?>" href="?type=dine-in">Dine-in</a></li>
        <li class="nav-item"><a class="nav-link <?= $type=='takeaway'?'active':'' ?>" href="?type=takeaway">Takeaway</a></li>
        <li class="nav-item"><a class="nav-link <?= $type=='delivery'?'active':'' ?>" href="?type=delivery">Delivery</a></li>
    </ul>
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>Customer</th><th>Table No</th><th>Total</th><th>Status</th><th>Date</th>
            </tr>
        </thead>
        <tbody>
        <?php while($row = $orders->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= $row['table_no'] ?></td>
                <td>₹<?= number_format($row['total'],2) ?></td>
                <td><?= htmlspecialchars($row['status']) ?></td>
                <td><?= $row['created_at'] ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
<?php include_once 'template/footer.php'; ?>