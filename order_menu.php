<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

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
        $ids = implode(',', array_keys($cart));
        $menu_items = $conn->query("SELECT id, price FROM menu WHERE id IN ($ids)");
        $prices = [];
        while ($row = $menu_items->fetch_assoc()) {
            $prices[$row['id']] = $row['price'];
        }
        foreach ($cart as $id => $qty) {
            $total += $prices[$id] * $qty;
        }
        $stmt = $conn->prepare("INSERT INTO orders (user_id, order_type, status, created_at, table_no, total) VALUES (?, ?, 'pending', NOW(), ?, ?)");
        $stmt->bind_param("isid", $user_id, $order_type, $table_no, $total);
        $stmt->execute();
        $order_id = $conn->insert_id;
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Food | RMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; margin: 0; }
        .main-container { background: #fff; border-radius: 20px 20px 0 0; margin-top: 60px; min-height: calc(100vh - 60px); box-shadow: 0 -4px 20px rgba(0,0,0,0.1); }
        .header-section { background: linear-gradient(135deg, #ff6b6b, #ffa500); color: white; padding: 30px 20px; border-radius: 20px 20px 0 0; }
        .menu-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); margin-bottom: 20px; overflow: hidden; transition: transform 0.3s ease, box-shadow 0.3s ease; border: none; }
        .menu-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .menu-img { width: 120px; height: 120px; object-fit: cover; border-radius: 12px; margin: 16px; }
        .menu-details { padding: 20px 20px 20px 0; flex: 1; }
        .menu-name { font-size: 1.1rem; font-weight: 600; color: #2d3748; margin-bottom: 8px; }
        .menu-desc { color: #718096; font-size: 0.9rem; margin-bottom: 12px; line-height: 1.4; }
        .menu-price { font-size: 1.25rem; font-weight: 700; color: #38a169; }
        .qty-input { width: 60px; height: 36px; border: 2px solid #e2e8f0; border-radius: 8px; text-align: center; font-weight: 600; }
        .add-btn { background: linear-gradient(135deg, #667eea, #764ba2); border: none; padding: 8px 20px; border-radius: 25px; color: white; font-weight: 600; transition: all 0.3s ease; }
        .add-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4); color: white; }
        .cart-panel { position: fixed; right: 0; top: 0; width: 380px; height: 100vh; background: #fff; box-shadow: -4px 0 20px rgba(0,0,0,0.1); z-index: 1000; overflow-y: auto; }
        .cart-header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 20px; }
        .cart-item { border-bottom: 1px solid #e2e8f0; padding: 15px; }
        .cart-item:last-child { border-bottom: none; }
        .cart-item-name { font-weight: 600; color: #2d3748; margin-bottom: 5px; }
        .cart-item-price { color: #38a169; font-weight: 600; }
        .remove-btn { background: #e53e3e; color: white; border: none; width: 25px; height: 25px; border-radius: 50%; font-size: 12px; }
        .place-order-btn { background: linear-gradient(135deg, #38a169, #48bb78); border: none; padding: 15px; border-radius: 12px; color: white; font-weight: 600; font-size: 1.1rem; width: calc(100% - 32px); margin: 16px; }
        .place-order-btn:hover { color: white; transform: translateY(-2px); }
        .total-section { background: #f7fafc; padding: 15px; margin: 16px; border-radius: 12px; }
        .table-badge { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 12px 20px; border-radius: 25px; font-weight: 600; display: inline-block; }
        .success-alert { background: linear-gradient(135deg, #38a169, #48bb78); color: white; border: none; border-radius: 12px; }
        .warning-alert { background: linear-gradient(135deg, #ed8936, #f6ad55); color: white; border: none; border-radius: 12px; }
        .empty-cart { text-align: center; padding: 40px 20px; color: #718096; }
        .empty-cart i { font-size: 3rem; margin-bottom: 15px; opacity: 0.5; }
        
        @media (max-width: 991px) {
            .cart-panel { position: static; width: 100%; height: auto; box-shadow: 0 -4px 20px rgba(0,0,0,0.1); border-radius: 20px 20px 0 0; margin-top: 20px; }
            .main-container { margin-top: 0; border-radius: 0; }
            .header-section { border-radius: 0; }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="header-section">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-8">
                        <h2 class="mb-0 fw-bold"><i class="fas fa-utensils me-2"></i>Restaurant Menu</h2>
                        <?php if ($table_no): ?>
                            <div class="table-badge mt-3">
                                <i class="fas fa-table me-2"></i>Table <?= htmlspecialchars($table_no) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-4 text-end">
                        <div class="d-lg-none">
                            <button class="btn btn-light" onclick="toggleCart()">
                                <i class="fas fa-shopping-cart"></i> Cart (<span id="cartCount">0</span>)
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container py-4">
            <div class="row">
                <div class="col-lg-8">
                    <?php if ($order_success): ?>
                        <div class="alert success-alert">
                            <i class="fas fa-check-circle me-2"></i>Order placed successfully! Your order is being prepared.
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <?php while($row = $result->fetch_assoc()): ?>
                        <div class="col-12">
                            <div class="menu-card d-flex align-items-center">
                                <?php if($row['image']): ?>
                                    <img src="assets/img/<?= $row['image'] ?>" class="menu-img" alt="<?= htmlspecialchars($row['name']) ?>">
                                <?php else: ?>
                                    <div class="menu-img d-flex align-items-center justify-content-center bg-light">
                                        <i class="fas fa-image text-muted" style="font-size: 2rem;"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="menu-details">
                                    <div class="menu-name"><?= htmlspecialchars($row['name']) ?></div>
                                    <div class="menu-desc"><?= htmlspecialchars($row['description']) ?></div>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="menu-price">₹<?= number_format($row['price'],2) ?></div>
                                        <form method="post" class="d-flex align-items-center gap-2">
                                            <input type="number" name="qty" value="1" min="1" class="qty-input">
                                            <input type="hidden" name="menu_id" value="<?= $row['id'] ?>">
                                            <button type="submit" name="add_to_cart" class="add-btn">
                                                <i class="fas fa-plus me-1"></i>Add
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <div class="col-lg-4 d-none d-lg-block">
                    <div class="cart-panel">
                        <div class="cart-header">
                            <h4 class="mb-0 fw-bold"><i class="fas fa-shopping-cart me-2"></i>Your Cart</h4>
                        </div>
                        
                        <?php
                        $cart = $_SESSION['cart'];
                        $total = 0;
                        if ($cart && count($cart) > 0):
                            $ids = implode(',', array_keys($cart));
                            $menu_items = $conn->query("SELECT id, name, price FROM menu WHERE id IN ($ids)");
                            $items = [];
                            while ($row = $menu_items->fetch_assoc()) {
                                $items[$row['id']] = $row;
                            }
                        ?>
                            <?php foreach ($cart as $id => $qty):
                                $item = $items[$id];
                                $total += $item['price'] * $qty;
                            ?>
                            <div class="cart-item d-flex justify-content-between align-items-center">
                                <div class="flex-grow-1">
                                    <div class="cart-item-name"><?= htmlspecialchars($item['name']) ?></div>
                                    <div class="cart-item-price">₹<?= number_format($item['price'] * $qty, 2) ?></div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-secondary"><?= $qty ?></span>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="menu_id" value="<?= $id ?>">
                                        <button type="submit" name="remove_from_cart" class="remove-btn">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <div class="total-section">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold fs-5">Total:</span>
                                    <span class="fw-bold fs-4 text-success">₹<?= number_format($total, 2) ?></span>
                                </div>
                            </div>
                            
                            <?php if ($table_no): ?>
                                <form method="post">
                                    <input type="hidden" name="table_no" value="<?= $table_no ?>">
                                    <button type="submit" name="place_order" class="place-order-btn">
                                        <i class="fas fa-check me-2"></i>Place Order
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="alert warning-alert m-3">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Scan the QR code on your table to start ordering.
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="empty-cart">
                                <i class="fas fa-shopping-cart"></i>
                                <div>Your cart is empty</div>
                                <small>Add items from the menu to get started</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Mobile cart toggle
function toggleCart() {
    // Implementation for mobile cart toggle
    console.log('Cart toggle for mobile');
}

// Update cart count
document.addEventListener('DOMContentLoaded', function() {
    const cartCount = <?= count($cart ?? []) ?>;
    const cartCountElement = document.getElementById('cartCount');
    if (cartCountElement) {
        cartCountElement.textContent = cartCount;
    }
});
</script>
</body>
</html>
