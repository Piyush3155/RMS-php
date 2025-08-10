<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
include_once 'template/header.php';
$result = $conn->query("SELECT id, name, email, phone, role FROM users WHERE role IN ('waiter','chef','cashier','manager','admin')");
?>
<div class="container mt-4">
    <h2>Staff Profiles</h2>
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>Name</th><th>Email</th><th>Phone</th><th>Role</th>
            </tr>
        </thead>
        <tbody>
        <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['phone']) ?></td>
                <td><span class="badge bg-primary"><?= htmlspecialchars($row['role']) ?></span></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
<?php include_once 'template/footer.php'; ?>
