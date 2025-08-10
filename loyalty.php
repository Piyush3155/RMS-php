<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
include_once 'template/header.php';

$loyalty = $conn->query("SELECT l.*, u.name FROM loyalty l JOIN users u ON l.user_id = u.id ORDER BY points DESC");
?>
<div class="container mt-4">
    <h2>Loyalty & Rewards</h2>
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>Customer</th><th>Points</th>
            </tr>
        </thead>
        <tbody>
        <?php while($row = $loyalty->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= $row['points'] ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
<?php include_once 'template/footer.php'; ?>