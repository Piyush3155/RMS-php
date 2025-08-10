<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
include_once 'template/header.php';

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $order_id = $_POST['order_id'];
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $status, $order_id);
        $stmt->execute();
    }
    
    if (isset($_POST['assign_waiter'])) {
        $order_id = $_POST['order_id'];
        $waiter_id = $_POST['waiter_id'];
        
        $stmt = $conn->prepare("UPDATE orders SET assigned_waiter = ? WHERE id = ?");
        $stmt->bind_param("ii", $waiter_id, $order_id);
        $stmt->execute();
    }
}

// Get filter parameters
$type = $_GET['type'] ?? 'dine-in';
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? date('Y-m-d');

// Build query with filters
$where_conditions = ["o.order_type = ?"];
$params = [$type];
$param_types = 's';

if ($status_filter && $status_filter !== 'all') {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if ($date_filter) {
    $where_conditions[] = "DATE(o.created_at) = ?";
    $params[] = $date_filter;
    $param_types .= 's';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$orders_query = "SELECT o.*, u.name as customer_name, w.name as waiter_name FROM orders o 
                 LEFT JOIN users u ON o.user_id = u.id 
                 LEFT JOIN users w ON o.assigned_waiter = w.id 
                 $where_clause ORDER BY o.created_at DESC";

$stmt = $conn->prepare($orders_query);
if ($stmt) {
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $orders = $stmt->get_result();
} else {
    $orders = false;
}

// Get statistics
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status = 'preparing' THEN 1 ELSE 0 END) as preparing_orders,
        SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) as ready_orders,
        SUM(CASE WHEN status = 'served' THEN 1 ELSE 0 END) as served_orders,
        AVG(total) as avg_order_value,
        SUM(total) as total_revenue
    FROM orders o $where_clause
")->fetch_assoc();

// Get available waiters
$waiters = $conn->query("SELECT id, name FROM users WHERE role = 'waiter' ORDER BY name");
?>

<style>
.order-stats { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px; padding: 20px; margin-bottom: 30px; }
.order-card { border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px; transition: all 0.3s ease; overflow: hidden; }
.order-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
.filter-card { background: #f8f9fa; border-radius: 15px; padding: 20px; margin-bottom: 20px; border: none; }
.status-pending { border-left: 5px solid #ed8936; background: #fffaf0; }
.status-preparing { border-left: 5px solid #3182ce; background: #ebf8ff; }
.status-ready { border-left: 5px solid #38a169; background: #f0fff4; }
.status-served { border-left: 5px solid #805ad5; background: #faf5ff; }
.status-cancelled { border-left: 5px solid #e53e3e; background: #fed7d7; }
.order-header { background: linear-gradient(135deg, #2d3748, #4a5568); color: white; padding: 15px; }
.order-details { padding: 20px; }
.order-id { font-size: 1.2rem; font-weight: 700; color: #2d3748; }
.order-total { font-size: 1.3rem; font-weight: 700; color: #38a169; }
.table-badge { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
.time-badge { background: #4a5568; color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; }
.order-items { background: #f7fafc; padding: 15px; border-radius: 12px; margin: 10px 0; max-height: 150px; overflow-y: auto; }
.waiter-badge { background: #e6fffa; color: #00695c; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; }
.priority-high { border-top: 4px solid #e53e3e; }
.priority-medium { border-top: 4px solid #ed8936; }
.priority-normal { border-top: 4px solid #38a169; }
</style>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold text-dark"><i class="fas fa-clipboard-list me-2 text-primary"></i>Order Dashboard</h2>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-success" onclick="exportOrders()">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                    <button class="btn btn-success" onclick="refreshOrders()" id="refreshBtn">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                </div>
            </div>

            <!-- Order Type Tabs -->
            <ul class="nav nav-pills mb-4 justify-content-center">
                <li class="nav-item">
                    <a class="nav-link <?= $type=='dine-in'?'active':'' ?>" href="?type=dine-in">
                        <i class="fas fa-utensils me-2"></i>Dine-in
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $type=='takeaway'?'active':'' ?>" href="?type=takeaway">
                        <i class="fas fa-shopping-bag me-2"></i>Takeaway
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $type=='delivery'?'active':'' ?>" href="?type=delivery">
                        <i class="fas fa-motorcycle me-2"></i>Delivery
                    </a>
                </li>
            </ul>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="order-stats text-center">
                        <h4 class="mb-0"><?= $stats['total_orders'] ?></h4>
                        <small class="opacity-75">Total Orders</small>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="order-stats text-center">
                        <h4 class="mb-0 text-warning"><?= $stats['pending_orders'] ?></h4>
                        <small class="opacity-75">Pending</small>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="order-stats text-center">
                        <h4 class="mb-0 text-primary"><?= $stats['preparing_orders'] ?></h4>
                        <small class="opacity-75">Preparing</small>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="order-stats text-center">
                        <h4 class="mb-0 text-success"><?= $stats['ready_orders'] ?></h4>
                        <small class="opacity-75">Ready</small>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="order-stats text-center">
                        <h4 class="mb-0 text-info"><?= $stats['served_orders'] ?></h4>
                        <small class="opacity-75">Served</small>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="order-stats text-center">
                        <h4 class="mb-0">₹<?= number_format($stats['total_revenue'] ?? 0, 0) ?></h4>
                        <small class="opacity-75">Revenue</small>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card filter-card">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="type" value="<?= $type ?>">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Filter by Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="preparing" <?= $status_filter === 'preparing' ? 'selected' : '' ?>>Preparing</option>
                            <option value="ready" <?= $status_filter === 'ready' ? 'selected' : '' ?>>Ready</option>
                            <option value="served" <?= $status_filter === 'served' ? 'selected' : '' ?>>Served</option>
                            <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Filter by Date</label>
                        <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date_filter) ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2"><i class="fas fa-search me-2"></i>Filter</button>
                        <a href="order_dashboard.php?type=<?= $type ?>" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Orders Grid -->
            <div class="row" id="ordersContainer">
                <?php if ($orders && $orders->num_rows > 0): ?>
                    <?php while($row = $orders->fetch_assoc()): ?>
                        <?php
                        // Calculate priority based on time elapsed
                        $created_time = strtotime($row['created_at']);
                        $elapsed_minutes = (time() - $created_time) / 60;
                        $priority_class = 'priority-normal';
                        if ($elapsed_minutes > 30) $priority_class = 'priority-high';
                        elseif ($elapsed_minutes > 15) $priority_class = 'priority-medium';
                        ?>
                        <div class="col-lg-6 col-xl-4">
                            <div class="order-card status-<?= $row['status'] ?> <?= $priority_class ?>">
                                <div class="order-details">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <div class="order-id">Order #<?= $row['id'] ?></div>
                                            <small class="text-muted">
                                                <?= date('M d, Y - g:i A', strtotime($row['created_at'])) ?>
                                                <span class="ms-2 time-badge">
                                                    <?= round($elapsed_minutes) ?> min ago
                                                </span>
                                            </small>
                                        </div>
                                        <span class="badge bg-<?= $row['status'] === 'pending' ? 'warning' : ($row['status'] === 'preparing' ? 'primary' : ($row['status'] === 'ready' ? 'success' : ($row['status'] === 'served' ? 'info' : 'danger'))) ?>">
                                            <?= ucfirst($row['status']) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-user text-primary me-2"></i>
                                                <span class="fw-semibold">
                                                    <?= htmlspecialchars($row['customer_name'] ?? 'Walk-in Customer') ?>
                                                </span>
                                            </div>
                                        </div>
                                        <?php if ($type === 'dine-in' && $row['table_no']): ?>
                                            <div class="col-6">
                                                <div class="table-badge text-center">
                                                    <i class="fas fa-table me-1"></i>Table <?= $row['table_no'] ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="order-total">₹<?= number_format($row['total'], 2) ?></div>
                                        <?php if ($row['waiter_name']): ?>
                                            <div class="waiter-badge">
                                                <i class="fas fa-user-tie me-1"></i><?= htmlspecialchars($row['waiter_name']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Order Items -->
                                    <div class="order-items">
                                        <h6 class="fw-bold mb-2"><i class="fas fa-list me-2"></i>Items:</h6>
                                        <?php
                                        $items_query = $conn->prepare("SELECT oi.*, m.name FROM order_items oi JOIN menu m ON oi.menu_id = m.id WHERE oi.order_id = ?");
                                        $items_query->bind_param("i", $row['id']);
                                        $items_query->execute();
                                        $items = $items_query->get_result();
                                        ?>
                                        <ul class="mb-0 small">
                                            <?php while($item = $items->fetch_assoc()): ?>
                                                <li><?= $item['quantity'] ?>x <?= htmlspecialchars($item['name']) ?></li>
                                            <?php endwhile; ?>
                                        </ul>
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="mt-3 d-flex gap-2 flex-wrap">
                                        <?php if ($row['status'] === 'pending'): ?>
                                            <form method="POST" style="flex: 1;">
                                                <input type="hidden" name="order_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="status" value="preparing">
                                                <button type="submit" name="update_status" class="btn btn-sm btn-primary w-100">
                                                    <i class="fas fa-play"></i> Start Preparing
                                                </button>
                                            </form>
                                        <?php elseif ($row['status'] === 'preparing'): ?>
                                            <form method="POST" style="flex: 1;">
                                                <input type="hidden" name="order_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="status" value="ready">
                                                <button type="submit" name="update_status" class="btn btn-sm btn-success w-100">
                                                    <i class="fas fa-check"></i> Mark Ready
                                                </button>
                                            </form>
                                        <?php elseif ($row['status'] === 'ready'): ?>
                                            <form method="POST" style="flex: 1;">
                                                <input type="hidden" name="order_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="status" value="served">
                                                <button type="submit" name="update_status" class="btn btn-sm btn-info w-100">
                                                    <i class="fas fa-utensils"></i> Mark Served
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if (!$row['waiter_name'] && $type === 'dine-in'): ?>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="assignWaiter(<?= $row['id'] ?>)">
                                                <i class="fas fa-user-plus"></i>
                                            </button>
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
                            <i class="fas fa-clipboard-list fs-1 text-muted mb-3"></i>
                            <h5 class="text-muted">No orders found</h5>
                            <p class="text-muted">No <?= $type ?> orders match your current filters.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Assign Waiter Modal -->
<div class="modal fade" id="assignWaiterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Waiter</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Waiter</label>
                        <select name="waiter_id" class="form-select" required>
                            <option value="">Choose Waiter</option>
                            <?php while($waiter = $waiters->fetch_assoc()): ?>
                                <option value="<?= $waiter['id'] ?>"><?= htmlspecialchars($waiter['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <input type="hidden" name="order_id" id="assignOrderId">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="assign_waiter" class="btn btn-primary">Assign Waiter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function refreshOrders() {
    const btn = document.getElementById('refreshBtn');
    const icon = btn.querySelector('i');
    icon.classList.add('fa-spin');
    
    setTimeout(() => {
        location.reload();
    }, 500);
}

function assignWaiter(orderId) {
    document.getElementById('assignOrderId').value = orderId;
    new bootstrap.Modal(document.getElementById('assignWaiterModal')).show();
}

function viewOrderDetails(orderId) {
    window.open('order_details.php?id=' + orderId, '_blank');
}

function printOrder(orderId) {
    window.open('print_order.php?id=' + orderId, '_blank');
}

function exportOrders() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = 'export_orders.php?' + params.toString();
}

// Auto-refresh every 30 seconds
setInterval(function() {
    if (document.visibilityState === 'visible') {
        const container = document.getElementById('ordersContainer');
        fetch(window.location.href + '&ajax=1')
            .then(response => response.text())
            .then(html => {
                // Update only the orders container without full page reload
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newContainer = doc.getElementById('ordersContainer');
                if (newContainer) {
                    container.innerHTML = newContainer.innerHTML;
                }
            })
            .catch(error => console.log('Auto-refresh failed:', error));
    }
}, 30000);

// Sound notification for new orders (optional)
let lastOrderCount = <?= $orders->num_rows ?>;
function checkNewOrders() {
    fetch('check_new_orders.php?type=<?= $type ?>')
        .then(response => response.json())
        .then(data => {
            if (data.count > lastOrderCount) {
                // Play notification sound (you can add audio element)
                showNotification('New order received!');
                lastOrderCount = data.count;
            }
        })
        .catch(error => console.log('Check new orders failed:', error));
}

function showNotification(message) {
    if (Notification.permission === 'granted') {
        new Notification('RMS Order Update', {
            body: message,
            icon: '/assets/img/logo.png'
        });
    }
}

// Request notification permission
if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
}
</script>

<?php include_once 'template/footer.php'; ?>