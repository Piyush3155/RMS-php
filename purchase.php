<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
include_once 'template/header.php';

// Handle purchase order operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_order'])) {
        $supplier_id = $_POST['supplier_id'];
        $order_date = $_POST['order_date'];
        $expected_delivery = $_POST['expected_delivery'];
        $notes = $_POST['notes'];
        
        $stmt = $conn->prepare("INSERT INTO purchase_orders (supplier_id, order_date, expected_delivery, notes, status, total) VALUES (?, ?, ?, ?, 'pending', 0)");
        $stmt->bind_param("isss", $supplier_id, $order_date, $expected_delivery, $notes);
        $stmt->execute();
        $order_id = $conn->insert_id;
        
        // Add order items if provided
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            $stmt_item = $conn->prepare("INSERT INTO purchase_order_items (order_id, item_name, quantity, unit_price) VALUES (?, ?, ?, ?)");
            $total = 0;
            foreach ($_POST['items'] as $item) {
                if (!empty($item['name']) && $item['quantity'] > 0) {
                    $stmt_item->bind_param("isid", $order_id, $item['name'], $item['quantity'], $item['price']);
                    $stmt_item->execute();
                    $total += $item['quantity'] * $item['price'];
                }
            }
            
            // Update total
            $stmt_total = $conn->prepare("UPDATE purchase_orders SET total = ? WHERE id = ?");
            $stmt_total->bind_param("di", $total, $order_id);
            $stmt_total->execute();
        }
    }
    
    if (isset($_POST['update_status'])) {
        $order_id = $_POST['order_id'];
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $order_id);
        $stmt->execute();
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$supplier_filter = $_GET['supplier'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = '';

if ($status_filter && $status_filter !== 'all') {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if ($supplier_filter) {
    $where_conditions[] = "p.supplier_id = ?";
    $params[] = $supplier_filter;
    $param_types .= 'i';
}

if ($date_filter) {
    $where_conditions[] = "DATE(p.order_date) = ?";
    $params[] = $date_filter;
    $param_types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

$purchases_query = "SELECT p.*, s.name as supplier_name, s.contact as supplier_contact FROM purchase_orders p JOIN suppliers s ON p.supplier_id = s.id $where_clause ORDER BY p.order_date DESC";
if (!empty($params)) {
    $stmt = $conn->prepare($purchases_query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $purchases = $stmt->get_result();
} else {
    $purchases = $conn->query($purchases_query);
}

// Get suppliers and statistics
$suppliers = $conn->query("SELECT id, name FROM suppliers ORDER BY name");
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
        SUM(total) as total_amount
    FROM purchase_orders p $where_clause
")->fetch_assoc();
?>

<style>
.purchase-stats { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px; padding: 25px; margin-bottom: 30px; }
.purchase-card { border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px; transition: all 0.3s ease; }
.purchase-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
.filter-card { background: #f8f9fa; border-radius: 15px; padding: 20px; margin-bottom: 20px; border: none; }
.status-pending { border-left: 5px solid #ed8936; background: #fffaf0; }
.status-confirmed { border-left: 5px solid #3182ce; background: #ebf8ff; }
.status-delivered { border-left: 5px solid #38a169; background: #f0fff4; }
.status-cancelled { border-left: 5px solid #e53e3e; background: #fed7d7; }
.supplier-info { background: #f7fafc; padding: 15px; border-radius: 12px; margin-bottom: 15px; }
.order-amount { font-size: 1.5rem; font-weight: 700; color: #38a169; }
.order-details { padding: 20px; }
.delivery-date { background: #e6fffa; padding: 8px 12px; border-radius: 8px; font-size: 0.9rem; }
.item-count { background: #667eea; color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; }
.add-item-row { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 10px; }
</style>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold text-dark"><i class="fas fa-shopping-cart me-2 text-primary"></i>Purchase Orders</h2>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-success" onclick="exportOrders()">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                    <button class="btn btn-success" onclick="quickReorder()">
                        <i class="fas fa-redo me-2"></i>Quick Reorder
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createOrderModal">
                        <i class="fas fa-plus me-2"></i>Create Order
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="purchase-stats text-center">
                        <div class="fs-1 mb-2"><i class="fas fa-clipboard-list"></i></div>
                        <h3 class="mb-0"><?= $stats['total_orders'] ?></h3>
                        <p class="mb-0 opacity-75">Total Orders</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="purchase-stats text-center">
                        <div class="fs-1 mb-2"><i class="fas fa-clock"></i></div>
                        <h3 class="mb-0 text-warning"><?= $stats['pending_orders'] ?></h3>
                        <p class="mb-0 opacity-75">Pending</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="purchase-stats text-center">
                        <div class="fs-1 mb-2"><i class="fas fa-truck"></i></div>
                        <h3 class="mb-0 text-success"><?= $stats['delivered_orders'] ?></h3>
                        <p class="mb-0 opacity-75">Delivered</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="purchase-stats text-center">
                        <div class="fs-1 mb-2"><i class="fas fa-rupee-sign"></i></div>
                        <h3 class="mb-0">₹<?= number_format($stats['total_amount'] ?? 0, 2) ?></h3>
                        <p class="mb-0 opacity-75">Total Value</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card filter-card">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Filter by Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                            <option value="delivered" <?= $status_filter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                            <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Filter by Supplier</label>
                        <select name="supplier" class="form-select">
                            <option value="">All Suppliers</option>
                            <?php 
                            $suppliers_filter = $conn->query("SELECT id, name FROM suppliers ORDER BY name");
                            while($supplier = $suppliers_filter->fetch_assoc()): 
                            ?>
                                <option value="<?= $supplier['id'] ?>" <?= $supplier_filter == $supplier['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($supplier['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Filter by Date</label>
                        <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date_filter) ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2"><i class="fas fa-search me-2"></i>Filter</button>
                        <a href="purchase.php" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Purchase Orders List -->
            <div class="row">
                <?php if ($purchases->num_rows > 0): ?>
                    <?php while($row = $purchases->fetch_assoc()): ?>
                        <div class="col-lg-6">
                            <div class="purchase-card status-<?= $row['status'] ?>">
                                <div class="order-details">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h6 class="fw-bold mb-1">Order #<?= $row['id'] ?></h6>
                                            <small class="text-muted"><?= date('M d, Y', strtotime($row['order_date'])) ?></small>
                                        </div>
                                        <span class="badge bg-<?= $row['status'] === 'pending' ? 'warning' : ($row['status'] === 'delivered' ? 'success' : ($row['status'] === 'confirmed' ? 'primary' : 'danger')) ?>">
                                            <?= ucfirst($row['status']) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="supplier-info">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-building text-primary me-2"></i>
                                            <div>
                                                <div class="fw-semibold"><?= htmlspecialchars($row['supplier_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($row['supplier_contact']) ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="order-amount">₹<?= number_format($row['total'], 2) ?></div>
                                        <div class="item-count">
                                            <?php 
                                            $item_count = $conn->query("SELECT COUNT(*) as count FROM purchase_order_items WHERE order_id = " . $row['id'])->fetch_assoc()['count'];
                                            echo $item_count . ' items';
                                            ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($row['expected_delivery']): ?>
                                        <div class="delivery-date mb-3">
                                            <i class="fas fa-truck me-2"></i>
                                            <strong>Expected Delivery:</strong> <?= date('M d, Y', strtotime($row['expected_delivery'])) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($row['notes']): ?>
                                        <div class="mb-3">
                                            <small class="text-muted">
                                                <i class="fas fa-sticky-note me-1"></i>
                                                <?= htmlspecialchars($row['notes']) ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex gap-2">
                                        <?php if ($row['status'] === 'pending'): ?>
                                            <form method="POST" style="flex: 1;">
                                                <input type="hidden" name="order_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="status" value="confirmed">
                                                <button type="submit" name="update_status" class="btn btn-sm btn-success w-100">
                                                    <i class="fas fa-check"></i> Confirm
                                                </button>
                                            </form>
                                        <?php elseif ($row['status'] === 'confirmed'): ?>
                                            <form method="POST" style="flex: 1;">
                                                <input type="hidden" name="order_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="status" value="delivered">
                                                <button type="submit" name="update_status" class="btn btn-sm btn-primary w-100">
                                                    <i class="fas fa-truck"></i> Mark Delivered
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-sm btn-outline-info" onclick="viewOrderDetails(<?= $row['id'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="printOrder(<?= $row['id'] ?>)">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-shopping-cart fs-1 text-muted mb-3"></i>
                            <h5 class="text-muted">No purchase orders found</h5>
                            <p class="text-muted">Create your first purchase order to manage inventory.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Create Order Modal -->
<div class="modal fade" id="createOrderModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Purchase Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Supplier *</label>
                            <select name="supplier_id" class="form-select" required>
                                <option value="">Select Supplier</option>
                                <?php 
                                $suppliers->data_seek(0);
                                while($supplier = $suppliers->fetch_assoc()): 
                                ?>
                                    <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Order Date *</label>
                            <input type="date" name="order_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Expected Delivery</label>
                            <input type="date" name="expected_delivery" class="form-control" min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Order Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Additional notes or special instructions"></textarea>
                    </div>
                    
                    <h6 class="mb-3">Order Items</h6>
                    <div id="orderItems">
                        <div class="add-item-row">
                            <div class="row">
                                <div class="col-md-5">
                                    <input type="text" name="items[0][name]" class="form-control" placeholder="Item name" required>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="items[0][quantity]" class="form-control" placeholder="Qty" min="1" required>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" step="0.01" name="items[0][price]" class="form-control" placeholder="Unit Price" required>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-outline-danger" onclick="removeItem(this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-outline-primary" onclick="addItem()">
                        <i class="fas fa-plus me-2"></i>Add Item
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_order" class="btn btn-primary">Create Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let itemIndex = 1;

function addItem() {
    const container = document.getElementById('orderItems');
    const newItem = document.createElement('div');
    newItem.className = 'add-item-row';
    newItem.innerHTML = `
        <div class="row">
            <div class="col-md-5">
                <input type="text" name="items[${itemIndex}][name]" class="form-control" placeholder="Item name" required>
            </div>
            <div class="col-md-2">
                <input type="number" name="items[${itemIndex}][quantity]" class="form-control" placeholder="Qty" min="1" required>
            </div>
            <div class="col-md-3">
                <input type="number" step="0.01" name="items[${itemIndex}][price]" class="form-control" placeholder="Unit Price" required>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-outline-danger" onclick="removeItem(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    container.appendChild(newItem);
    itemIndex++;
}

function removeItem(button) {
    if (document.querySelectorAll('.add-item-row').length > 1) {
        button.closest('.add-item-row').remove();
    }
}

function exportOrders() {
    window.location.href = 'export_purchase_orders.php' + window.location.search;
}

function quickReorder() {
    alert('Quick reorder functionality coming soon');
}

function viewOrderDetails(id) {
    window.open('purchase_order_details.php?id=' + id, '_blank');
}

function printOrder(id) {
    window.open('print_purchase_order.php?id=' + id, '_blank');
}
</script>

<?php include_once 'template/footer.php'; ?>