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
    $special_instructions = $_POST['special_instructions'] ?? '';
    
    $cart_item = [
        'qty' => $qty,
        'special_instructions' => $special_instructions
    ];
    
    if (!isset($_SESSION['cart'][$menu_id])) {
        $_SESSION['cart'][$menu_id] = $cart_item;
    } else {
        $_SESSION['cart'][$menu_id]['qty'] += $qty;
        if ($special_instructions) {
            $_SESSION['cart'][$menu_id]['special_instructions'] = $special_instructions;
        }
    }
    
    // Return JSON for AJAX requests
    if (isset($_POST['ajax'])) {
        echo json_encode(['success' => true, 'cart_count' => array_sum(array_column($_SESSION['cart'], 'qty'))]);
        exit;
    }
}

// Handle update cart quantity
if (isset($_POST['update_cart'])) {
    $menu_id = intval($_POST['menu_id']);
    $qty = max(0, intval($_POST['qty']));
    
    if ($qty == 0) {
        unset($_SESSION['cart'][$menu_id]);
    } else {
        $_SESSION['cart'][$menu_id]['qty'] = $qty;
    }
    
    if (isset($_POST['ajax'])) {
        $cart = $_SESSION['cart'];
        $total = 0;
        if ($cart) {
            $ids = implode(',', array_keys($cart));
            $menu_items = $conn->query("SELECT id, price FROM menu WHERE id IN ($ids)");
            while ($row = $menu_items->fetch_assoc()) {
                $total += $row['price'] * $cart[$row['id']]['qty'];
            }
        }
        echo json_encode(['success' => true, 'total' => $total, 'cart_count' => array_sum(array_column($cart, 'qty'))]);
        exit;
    }
}

// Handle place order
$order_success = false;
if (isset($_POST['place_order']) && $table_no) {
    $customer_name = $_POST['customer_name'] ?? '';
    $customer_phone = $_POST['customer_phone'] ?? '';
    $order_type = 'dine-in';
    $total = 0;
    $cart = $_SESSION['cart'];
    
    if ($cart && ($customer_name || $customer_phone)) {
        $ids = implode(',', array_keys($cart));
        $menu_items = $conn->query("SELECT id, price, name FROM menu WHERE id IN ($ids)");
        $prices = [];
        while ($row = $menu_items->fetch_assoc()) {
            $prices[$row['id']] = $row;
        }
        
        foreach ($cart as $id => $item) {
            $total += $prices[$id]['price'] * $item['qty'];
        }
        
        // Insert order
        $stmt = $conn->prepare("INSERT INTO orders (customer_name, customer_phone, order_type, status, created_at, table_no, total) VALUES (?, ?, ?, 'pending', NOW(), ?, ?)");
        $stmt->bind_param("sssid", $customer_name, $customer_phone, $order_type, $table_no, $total);
        $stmt->execute();
        $order_id = $conn->insert_id;
        
        // Insert order items
        $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, menu_id, quantity, price, special_instructions) VALUES (?, ?, ?, ?, ?)");
        foreach ($cart as $id => $item) {
            $stmt_item->bind_param("iiids", $order_id, $id, $item['qty'], $prices[$id]['price'], $item['special_instructions']);
            $stmt_item->execute();
        }
        
        $_SESSION['cart'] = [];
        $_SESSION['last_order_id'] = $order_id;
        $order_success = true;
    }
}

// Get categories and menu items
$categories = $conn->query("SELECT DISTINCT category FROM menu WHERE available=1 ORDER BY category");
$selected_category = $_GET['category'] ?? '';

$where_clause = "WHERE available=1";
$params = [];
$param_types = '';

if ($selected_category) {
    $where_clause .= " AND category = ?";
    $params[] = $selected_category;
    $param_types .= 's';
}

$menu_query = "SELECT * FROM menu $where_clause ORDER BY category, name";
if (!empty($params)) {
    $stmt = $conn->prepare($menu_query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($menu_query);
}
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
        .main-container { background: #fff; border-radius: 20px 20px 0 0; margin-top: 60px; min-height: calc(100vh - 60px); box-shadow: 0 -4px 20px rgba(0,0,0,0.1); position: relative; }
        .header-section { background: linear-gradient(135deg, #ff6b6b, #ffa500); color: white; padding: 30px 20px; border-radius: 20px 20px 0 0; position: sticky; top: 0; z-index: 100; }
        .category-pills { padding: 15px 20px; background: #f8f9fa; border-bottom: 1px solid #e9ecef; position: sticky; top: 130px; z-index: 99; }
        .category-pill { background: #fff; border: 2px solid #e2e8f0; color: #4a5568; padding: 8px 16px; border-radius: 25px; text-decoration: none; margin-right: 10px; font-weight: 600; transition: all 0.3s ease; }
        .category-pill:hover, .category-pill.active { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-color: #667eea; }
        .menu-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); margin-bottom: 20px; overflow: hidden; transition: transform 0.3s ease, box-shadow 0.3s ease; border: none; }
        .menu-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .menu-img { width: 120px; height: 120px; object-fit: cover; border-radius: 12px; margin: 16px; }
        .menu-details { padding: 20px 20px 20px 0; flex: 1; }
        .menu-name { font-size: 1.2rem; font-weight: 700; color: #2d3748; margin-bottom: 8px; }
        .menu-desc { color: #718096; font-size: 0.9rem; margin-bottom: 12px; line-height: 1.5; }
        .menu-price { font-size: 1.4rem; font-weight: 800; color: #38a169; margin-bottom: 15px; }
        .qty-controls { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .qty-btn { width: 35px; height: 35px; border: 2px solid #667eea; background: #fff; color: #667eea; border-radius: 8px; font-weight: 700; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s ease; }
        .qty-btn:hover { background: #667eea; color: white; }
        .qty-display { font-size: 1.1rem; font-weight: 700; color: #2d3748; min-width: 30px; text-align: center; }
        .add-btn { background: linear-gradient(135deg, #38a169, #48bb78); border: none; padding: 12px 24px; border-radius: 25px; color: white; font-weight: 600; transition: all 0.3s ease; width: 100%; }
        .add-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(56, 161, 105, 0.4); color: white; }
        .add-btn:disabled { background: #cbd5e0; cursor: not-allowed; transform: none; box-shadow: none; }
        .special-instructions { margin-top: 10px; }
        .special-instructions input { border: 2px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 0.9rem; }
        .cart-fab { position: fixed; bottom: 20px; right: 20px; width: 60px; height: 60px; background: linear-gradient(135deg, #ff6b6b, #ffa500); border: none; border-radius: 50%; color: white; font-size: 1.5rem; box-shadow: 0 4px 20px rgba(255, 107, 107, 0.4); z-index: 1000; transition: all 0.3s ease; }
        .cart-fab:hover { transform: scale(1.1); color: white; }
        .cart-badge { position: absolute; top: -5px; right: -5px; background: #fff; color: #ff6b6b; border-radius: 50%; width: 25px; height: 25px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; }
        .success-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center; z-index: 2000; }
        .success-card { background: white; padding: 40px; border-radius: 20px; text-align: center; max-width: 400px; margin: 20px; }
        .success-icon { font-size: 4rem; color: #38a169; margin-bottom: 20px; }
        .order-track-btn { background: linear-gradient(135deg, #3182ce, #4299e1); border: none; padding: 12px 24px; border-radius: 25px; color: white; font-weight: 600; }
        .table-badge { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 12px 20px; border-radius: 25px; font-weight: 600; display: inline-block; }
        .loading-spinner { display: none; width: 20px; height: 20px; border: 2px solid #fff; border-top: 2px solid transparent; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        @media (max-width: 991px) {
            .main-container { margin-top: 0; border-radius: 0; }
            .header-section { border-radius: 0; position: relative; }
            .category-pills { position: relative; top: 0; }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="header-section">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-8">
                        <h2 class="mb-0 fw-bold"><i class="fas fa-utensils me-3"></i>Delicious Menu</h2>
                        <p class="mb-2 opacity-75">Fresh ingredients, exceptional taste</p>
                        <?php if ($table_no): ?>
                            <div class="table-badge">
                                <i class="fas fa-table me-2"></i>Table <?= htmlspecialchars($table_no) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-4 text-end">
                        <div class="text-white">
                            <div class="fs-5 fw-bold">₹<span id="headerTotal">0.00</span></div>
                            <small class="opacity-75"><span id="headerItems">0</span> items</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Pills -->
        <div class="category-pills">
            <div class="container">
                <div class="d-flex overflow-auto pb-2">
                    <a href="?<?= $table_no ? "table=$table_no" : '' ?>" class="category-pill <?= !$selected_category ? 'active' : '' ?>">
                        <i class="fas fa-th-large me-2"></i>All Items
                    </a>
                    <?php while($cat = $categories->fetch_assoc()): ?>
                        <a href="?category=<?= urlencode($cat['category']) ?><?= $table_no ? "&table=$table_no" : '' ?>" 
                           class="category-pill <?= $selected_category === $cat['category'] ? 'active' : '' ?>">
                            <?= htmlspecialchars($cat['category']) ?>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <div class="container py-4">
            <!-- Menu Items -->
            <div class="row">
                <?php 
                $current_category = '';
                while($row = $result->fetch_assoc()): 
                    if ($current_category !== $row['category'] && !$selected_category):
                        if ($current_category !== '') echo '</div>';
                        $current_category = $row['category'];
                        echo '<div class="col-12 mb-3"><h4 class="fw-bold text-primary border-bottom pb-2">' . htmlspecialchars($current_category) . '</h4></div>';
                    endif;
                ?>
                <div class="col-12">
                    <div class="menu-card d-flex align-items-center" data-menu-id="<?= $row['id'] ?>">
                        <?php if($row['image']): ?>
                            <img src="assets/img/<?= $row['image'] ?>" class="menu-img" alt="<?= htmlspecialchars($row['name']) ?>">
                        <?php else: ?>
                            <div class="menu-img d-flex align-items-center justify-content-center" style="background: linear-gradient(135deg, #f7fafc, #edf2f7);">
                                <i class="fas fa-utensils text-muted" style="font-size: 2rem;"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="menu-details">
                            <div class="menu-name"><?= htmlspecialchars($row['name']) ?></div>
                            <div class="menu-desc"><?= htmlspecialchars($row['description']) ?></div>
                            <div class="menu-price">₹<?= number_format($row['price'],2) ?></div>
                            
                            <div class="qty-controls">
                                <div class="qty-btn" onclick="changeQty(<?= $row['id'] ?>, -1)">
                                    <i class="fas fa-minus"></i>
                                </div>
                                <div class="qty-display" id="qty-<?= $row['id'] ?>">1</div>
                                <div class="qty-btn" onclick="changeQty(<?= $row['id'] ?>, 1)">
                                    <i class="fas fa-plus"></i>
                                </div>
                            </div>
                            
                            <div class="special-instructions">
                                <input type="text" id="instructions-<?= $row['id'] ?>" placeholder="Special instructions (optional)" class="form-control form-control-sm">
                            </div>
                            
                            <button class="add-btn mt-3" onclick="addToCart(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name']) ?>', <?= $row['price'] ?>)">
                                <i class="fas fa-plus me-2"></i>Add to Cart
                                <div class="loading-spinner d-inline-block ms-2"></div>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Cart FAB -->
    <button class="cart-fab" onclick="showCart()" id="cartFab" style="display: none;">
        <i class="fas fa-shopping-cart"></i>
        <span class="cart-badge" id="cartBadge">0</span>
    </button>

    <!-- Success Overlay -->
    <?php if ($order_success): ?>
    <div class="success-overlay" id="successOverlay">
        <div class="success-card">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3 class="fw-bold mb-3">Order Placed Successfully!</h3>
            <p class="text-muted mb-4">Your order #<?= $_SESSION['last_order_id'] ?> is being prepared. Estimated time: 15-20 minutes.</p>
            <button class="order-track-btn" onclick="closeSuccess()">
                <i class="fas fa-eye me-2"></i>Track Order
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Cart Modal -->
    <div class="modal fade" id="cartModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                    <h5 class="modal-title fw-bold"><i class="fas fa-shopping-cart me-2"></i>Your Order</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="cartContent">
                    <!-- Cart content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let cartData = <?= json_encode($_SESSION['cart'] ?? []) ?>;
let menuPrices = {};

// Load menu prices
<?php 
$result->data_seek(0);
while($row = $result->fetch_assoc()) {
    echo "menuPrices[{$row['id']}] = {$row['price']};\n";
}
?>

function changeQty(menuId, change) {
    const qtyElement = document.getElementById(`qty-${menuId}`);
    let qty = parseInt(qtyElement.textContent) + change;
    qty = Math.max(1, qty);
    qtyElement.textContent = qty;
}

function addToCart(menuId, menuName, price) {
    const qty = parseInt(document.getElementById(`qty-${menuId}`).textContent);
    const instructions = document.getElementById(`instructions-${menuId}`).value;
    const button = event.target;
    const spinner = button.querySelector('.loading-spinner');
    
    // Show loading
    button.disabled = true;
    spinner.style.display = 'inline-block';
    
    // AJAX request
    fetch('order_menu.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `ajax=1&add_to_cart=1&menu_id=${menuId}&qty=${qty}&special_instructions=${encodeURIComponent(instructions)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update cart data
            if (!cartData[menuId]) {
                cartData[menuId] = {qty: 0, special_instructions: ''};
            }
            cartData[menuId].qty += qty;
            cartData[menuId].special_instructions = instructions;
            
            updateCartUI();
            showToast(`${menuName} added to cart!`, 'success');
            
            // Reset form
            document.getElementById(`qty-${menuId}`).textContent = '1';
            document.getElementById(`instructions-${menuId}`).value = '';
        }
    })
    .catch(error => {
        showToast('Error adding item to cart', 'error');
    })
    .finally(() => {
        button.disabled = false;
        spinner.style.display = 'none';
    });
}

function updateCartUI() {
    const totalItems = Object.values(cartData).reduce((sum, item) => sum + item.qty, 0);
    const totalAmount = Object.keys(cartData).reduce((sum, menuId) => {
        return sum + (menuPrices[menuId] * cartData[menuId].qty);
    }, 0);
    
    // Update header
    document.getElementById('headerItems').textContent = totalItems;
    document.getElementById('headerTotal').textContent = totalAmount.toFixed(2);
    
    // Update FAB
    const cartFab = document.getElementById('cartFab');
    const cartBadge = document.getElementById('cartBadge');
    
    if (totalItems > 0) {
        cartFab.style.display = 'flex';
        cartBadge.textContent = totalItems;
    } else {
        cartFab.style.display = 'none';
    }
}

function showCart() {
    // Load cart content via AJAX or build it dynamically
    loadCartContent();
    new bootstrap.Modal(document.getElementById('cartModal')).show();
}

function loadCartContent() {
    let content = '';
    const totalItems = Object.values(cartData).reduce((sum, item) => sum + item.qty, 0);
    
    if (totalItems === 0) {
        content = `
            <div class="text-center py-5">
                <i class="fas fa-shopping-cart fs-1 text-muted mb-3"></i>
                <h5 class="text-muted">Your cart is empty</h5>
                <p class="text-muted">Add items from the menu to get started</p>
            </div>
        `;
    } else {
        let total = 0;
        content = '<div class="cart-items">';
        
        // Here you would fetch menu details for cart items
        // For now, showing basic structure
        content += '</div>';
        
        content += `
            <div class="cart-summary mt-4 p-3" style="background: #f8f9fa; border-radius: 12px;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="fw-bold fs-5">Total:</span>
                    <span class="fw-bold fs-4 text-success">₹${total.toFixed(2)}</span>
                </div>
                ${<?= $table_no ? 'true' : 'false' ?> ? `
                    <form method="post" id="checkoutForm">
                        <div class="row mb-3">
                            <div class="col-6">
                                <input type="text" name="customer_name" class="form-control" placeholder="Your name" required>
                            </div>
                            <div class="col-6">
                                <input type="tel" name="customer_phone" class="form-control" placeholder="Phone number">
                            </div>
                        </div>
                        <button type="submit" name="place_order" class="btn btn-success btn-lg w-100">
                            <i class="fas fa-check me-2"></i>Place Order
                        </button>
                    </form>
                ` : `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>Please scan the QR code on your table to complete your order.
                    </div>
                `}
            </div>
        `;
    }
    
    document.getElementById('cartContent').innerHTML = content;
}

function showToast(message, type = 'info') {
    const toastColor = type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : 'bg-info';
    const toast = document.createElement('div');
    toast.className = `toast show position-fixed top-0 end-0 m-3 ${toastColor} text-white`;
    toast.style.zIndex = '9999';
    toast.innerHTML = `
        <div class="toast-body">
            <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'} me-2"></i>
            ${message}
        </div>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

function closeSuccess() {
    document.getElementById('successOverlay').style.display = 'none';
}

// Initialize cart UI
document.addEventListener('DOMContentLoaded', function() {
    updateCartUI();
});

// Auto-hide success overlay after 5 seconds
<?php if ($order_success): ?>
setTimeout(closeSuccess, 5000);
<?php endif; ?>
</script>
</body>