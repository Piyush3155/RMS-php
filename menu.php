<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
include_once 'template/header.php';

// Get table number from URL (when scanned)
$table_no = isset($_GET['table']) ? intval($_GET['table']) : null;

// Initialize cart in session
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// Handle add to cart
if (isset($_POST['add_to_cart'])) {
    $menu_id = intval($_POST['menu_id']);
    $qty = max(1, intval($_POST['qty']));
    if (!isset($_SESSION['cart'][$menu_id])) {
        $_SESSION['cart'][$menu_id] = $qty;
    } else {
        $_SESSION['cart'][$menu_id] += $qty;
    }
}

// Handle remove from cart
if (isset($_POST['remove_from_cart'])) {
    $menu_id = intval($_POST['menu_id']);
    unset($_SESSION['cart'][$menu_id]);
}

// Handle place order
$order_success = false;
if (isset($_POST['place_order']) && $table_no) {
    $user_id = $_SESSION['user_id'] ?? null;
    $order_type = 'dine-in';
    $total = 0;
    $cart = $_SESSION['cart'];
    if ($cart) {
        // Calculate total
        $ids = implode(',', array_keys($cart));
        $menu_items = $conn->query("SELECT id, price FROM menu WHERE id IN ($ids)");
        $prices = [];
        while ($row = $menu_items->fetch_assoc()) {
            $prices[$row['id']] = $row['price'];
        }
        foreach ($cart as $id => $qty) {
            $total += $prices[$id] * $qty;
        }
        // Insert order
        $stmt = $conn->prepare("INSERT INTO orders (user_id, order_type, status, created_at, table_no, total) VALUES (?, ?, 'pending', NOW(), ?, ?)");
        $stmt->bind_param("isid", $user_id, $order_type, $table_no, $total);
        $stmt->execute();
        $order_id = $conn->insert_id;
        // Insert order items
        $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, menu_id, quantity, price) VALUES (?, ?, ?, ?)");
        foreach ($cart as $id => $qty) {
            $stmt_item->bind_param("iiid", $order_id, $id, $qty, $prices[$id]);
            $stmt_item->execute();
        }
        $_SESSION['cart'] = [];
        $order_success = true;
    }
}

// Fetch menu items
$result = $conn->query("SELECT * FROM menu WHERE available=1");

// Number of tables (set as needed)
$total_tables = 20;
?>
<div class="container mt-4">
    <h2 class="mb-4">Menu</h2>
    <?php if ($table_no): ?>
        <div class="alert alert-success">You are ordering from <strong>Table <?= htmlspecialchars($table_no) ?></strong></div>
    <?php endif; ?>

    <?php if ($order_success): ?>
        <div class="alert alert-success">Order placed successfully! Your order is being prepared.</div>
    <?php endif; ?>

    <form method="post">
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>Name</th><th>Description</th><th>Price</th><th>Image</th><th>Qty</th><th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td>₹<?= number_format($row['price'],2) ?></td>
                <td>
                    <?php if ($row['image']): ?>
                        <img src="assets/img/<?= $row['image'] ?>" width="50">
                    <?php endif; ?>
                </td>
                <td>
                    <input type="number" name="qty" value="1" min="1" class="form-control" style="width:70px;">
                    <input type="hidden" name="menu_id" value="<?= $row['id'] ?>">
                </td>
                <td>
                    <button type="submit" name="add_to_cart" class="btn btn-sm btn-primary">Add to Cart</button>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </form>

    <h4 class="mt-5">Your Cart</h4>
    <form method="post">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Item</th><th>Qty</th><th>Price</th><th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $cart = $_SESSION['cart'];
        $total = 0;
        if ($cart) {
            $ids = implode(',', array_keys($cart));
            $menu_items = $conn->query("SELECT id, name, price FROM menu WHERE id IN ($ids)");
            $items = [];
            while ($row = $menu_items->fetch_assoc()) {
                $items[$row['id']] = $row;
            }
            foreach ($cart as $id => $qty):
                $item = $items[$id];
                $total += $item['price'] * $qty;
        ?>
            <tr>
                <td><?= htmlspecialchars($item['name']) ?></td>
                <td><?= $qty ?></td>
                <td>₹<?= number_format($item['price'] * $qty,2) ?></td>
                <td>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="menu_id" value="<?= $id ?>">
                        <button type="submit" name="remove_from_cart" class="btn btn-sm btn-danger">Remove</button>
                    </form>
                </td>
            </tr>
        <?php endforeach;
        } else {
            echo '<tr><td colspan="4" class="text-center">Cart is empty.</td></tr>';
        }
        ?>
        </tbody>
        <?php if ($cart): ?>
        <tfoot>
            <tr>
                <th colspan="2">Total</th>
                <th colspan="2">₹<?= number_format($total,2) ?></th>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
    <?php if ($cart && $table_no): ?>
        <button type="submit" name="place_order" class="btn btn-success">Place Order</button>
    <?php elseif (!$table_no): ?>
        <div class="alert alert-warning">Scan the QR code on your table to start ordering.</div>
    <?php endif; ?>
    </form>
</div>
<?php include_once 'template/footer.php'; ?>