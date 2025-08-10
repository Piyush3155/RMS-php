<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
include_once 'template/header.php';

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $order_id = $_POST['order_id'];
    $rating = $_POST['rating'];
    $comments = $_POST['comments'];
    $stmt = $conn->prepare("INSERT INTO feedback (user_id, order_id, rating, comments) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $user_id, $order_id, $rating, $comments);
    $stmt->execute();
}

// List feedback
$fb = $conn->query("SELECT f.*, u.name FROM feedback f JOIN users u ON f.user_id = u.id ORDER BY f.created_at DESC");
?>
<div class="container mt-4">
    <h2>Customer Feedback</h2>
    <form method="post" class="row g-3 mb-4">
        <div class="col-md-2">
            <input type="number" name="order_id" class="form-control" placeholder="Order ID" required>
        </div>
        <div class="col-md-2">
            <input type="number" name="rating" class="form-control" min="1" max="5" placeholder="Rating" required>
        </div>
        <div class="col-md-4">
            <input type="text" name="comments" class="form-control" placeholder="Comments">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary" type="submit">Submit</button>
        </div>
    </form>
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>Customer</th><th>Order ID</th><th>Rating</th><th>Comments</th><th>Date</th>
            </tr>
        </thead>
        <tbody>
        <?php while($row = $fb->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= $row['order_id'] ?></td>
                <td><?= $row['rating'] ?></td>
                <td><?= htmlspecialchars($row['comments']) ?></td>
                <td><?= $row['created_at'] ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
<?php include_once 'template/footer.php'; ?>