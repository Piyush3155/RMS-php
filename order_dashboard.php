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
    
    if (isset($_POST['assign_staff'])) {
        $order_id = $_POST['order_id'];
        $staff_id = $_POST['staff_id'];
        
        $stmt = $conn->prepare("UPDATE orders SET assigned_staff = ? WHERE id = ?");
        $stmt->bind_param("ii", $staff_id, $order_id);
        $stmt->execute();
    }
}

$type = $_GET['type'] ?? 'dine-in';
$status_filter = $_GET['status'] ?? 'all';

// Build query with filters
$where_conditions = ["o.order_type = ?"];
$params = [$type];
$param_types = 's';

if ($status_filter && $status_filter !== 'all') {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$orders_query = "
    SELECT o.*, u.name as staff_name, 
           COUNT(oi.id) as item_count,
           GROUP_CONCAT(CONCAT(m.name, ' (', oi.quantity, ')') SEPARATOR ', ') as items
    FROM orders o 
    LEFT JOIN users u ON o.assigned_staff = u.id 
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN menu m ON oi.menu_id = m.id
    $where_clause 
    GROUP BY o.id 
    ORDER BY 
        CASE 
            WHEN o.status = 'pending' THEN 1
            WHEN o.status = 'preparing' THEN 2
            WHEN o.status = 'ready' THEN 3
            ELSE 4
        END,
        o.created_at DESC
";

$stmt = $conn->prepare($orders_query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$orders = $stmt->get_result();

// Get statistics
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_orders,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
        COUNT(CASE WHEN status = 'preparing' THEN 1 END) as preparing_orders,
        COUNT(CASE WHEN status = 'ready' THEN 1 END) as ready_orders,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
        SUM(total) as total_revenue
    FROM orders 
    WHERE order_type = '$type' AND DATE(created_at) = CURDATE()
")->fetch_assoc();

// Get staff list
$staff_list = $conn->query("SELECT id, name FROM users WHERE role IN ('waiter', 'chef') ORDER BY name");
?>

<style>
.order-stats { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px; padding: 20px; margin-bottom: 30px; }
.order-card { border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px; transition: all 0.3s ease; overflow: hidden; }
.order-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
.filter-card { background: #f8f9fa; border-radius: 15px; padding: 20px; margin-bottom: 20px; border: none; }
.status-pending { border-left: 5px solid #ed8936; background: #fffaf0; }
.status-preparing { border-left: 5px solid #3182ce; background: #ebf8ff; }
.status-ready { border-left: 5px solid #38a169; background: #f0fff4; }
.status-completed { border-left: 5px solid #718096; background: #f7fafc; }
.status-cancelled { border-left: 5px solid #e53e3e; background: #fed7d7; }
.order-header { padding: 20px; border-bottom: 1px solid #e2e8f0; }
.order-details { padding: 20px; }
.order-id { font-family: 'Courier New', monospace; font-weight: 700; font-size: 1.1rem; color: #667eea; }
.table-badge { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
.time-badge { background: #4a5568; color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; }
.priority-high { border-left-color: #e53e3e !important; }
.priority-medium { border-left-color: #ed8936 !important; }
.priority-low { border-left-color: #38a169 !important; }
.customer-info { background: #f7fafc; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
.order-items { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 15px; }
.item-list { font-size: 0.9rem; line-height: 1.6; }
.total-amount { font-size: 1.3rem; font-weight: 700; color: #38a169; }
.action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
.status-btn { padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; border: none; }
.btn-pending { background: #fed7d7; color: #742a2a; }
.btn-preparing { background: #bee3f8; color: #2c5282; }
.btn-ready { background: #c6f6d5; color: #22543d; }
.btn-completed { background: #e2e8f0; color: #4a5568; }
.timer-display { background: #fff3cd; color: #856404; padding: 8px 12px; border-radius: 20px; font-weight: 600; font-size: 0.85rem; }
.staff-assign { background: #e6fffa; padding: 10px; border-radius: 8px; margin-top: 10px; }
</style>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold text-dark"><i class="fas fa-clipboard-list me-2 text-primary"></i>Order Management</h2>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-success" onclick="refreshOrders()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                    <button class="btn btn-outline-primary" onclick="exportOrders()">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                    <button class="btn btn-primary" onclick="printKitchenTickets()">
                        <i class="fas fa-print me-2"></i>Kitchen Tickets
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="order-stats text-center">
                        <h4 class="mb-0"><?= $stats['total_orders'] ?></h4>
                        <p class="mb-0 opacity-75 small">Total Today</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="order-stats text-center">
                        <h4 class="mb-0 text-warning"><?= $stats['pending_orders'] ?></h4>
                        <p class="mb-0 opacity-75 small">Pending</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="order-stats text-center">
                        <h4 class="mb-0 text-primary"><?= $stats['preparing_orders'] ?></h4>
                        <p class="mb-0 opacity-75 small">Preparing</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="order-stats text-center">
                        <h4 class="mb-0 text-success"><?= $stats['ready_orders'] ?></h4>
                        <p class="mb-0 opacity-75 small">Ready</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="order-stats text-center">
                        <h4 class="mb-0 text-secondary"><?= $stats['completed_orders'] ?></h4>
                        <p class="mb-0 opacity-75 small">Completed</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="order-stats text-center">
                        <h4 class="mb-0">₹<?= number_format($stats['total_revenue'] ?? 0, 0) ?></h4>
                        <p class="mb-0 opacity-75 small">Revenue</p>
                    </div>
                </div>
            </div>

            <!-- Order Type Tabs and Filters -->
            <div class="card filter-card">
                <div class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <ul class="nav nav-pills mb-0">
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
                                    <i class="fas fa-truck me-2"></i>Delivery
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" onchange="filterByStatus(this.value)">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="preparing" <?= $status_filter === 'preparing' ? 'selected' : '' ?>>Preparing</option>
                            <option value="ready" <?= $status_filter === 'ready' ? 'selected' : '' ?>>Ready</option>
                            <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="autoRefresh" checked>
                            <label class="form-check-label" for="autoRefresh">Auto-refresh</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders Grid -->
            <div class="row">
                <?php if ($orders->num_rows > 0): ?>
                    <?php while($row = $orders->fetch_assoc()): ?>
                        <?php
                        // Calculate order age and priority
                        $order_time = new DateTime($row['created_at']);
                        $now = new DateTime();
                        $age_minutes = $now->diff($order_time)->i + ($now->diff($order_time)->h * 60);
                        
                        $priority_class = '';
                        if ($age_minutes > 30) $priority_class = 'priority-high';
                        elseif ($age_minutes > 15) $priority_class = 'priority-medium';
                        else $priority_class = 'priority-low';
                        ?>
                        <div class="col-lg-6 col-xl-4">
                            <div class="order-card status-<?= $row['status'] ?> <?= $priority_class ?>">
                                <div class="order-header">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <div class="order-id">#<?= str_pad($row['id'], 4, '0', STR_PAD_LEFT) ?></div>
                                            <div class="d-flex gap-2 align-items-center mt-1">
                                                <?php if ($row['table_no']): ?>
                                                    <span class="table-badge">
                                                        <i class="fas fa-table me-1"></i>Table <?= $row['table_no'] ?>
                                                    </span>
                                                <?php endif; ?>
                                                <span class="time-badge">
                                                    <i class="fas fa-clock me-1"></i><?= $age_minutes ?>min ago
                                                </span>
                                            </div>
                                        </div>
                                        <span class="badge bg-<?= $row['status'] === 'pending' ? 'warning' : ($row['status'] === 'preparing' ? 'primary' : ($row['status'] === 'ready' ? 'success' : 'secondary')) ?>">
                                            <?= ucfirst($row['status']) ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="order-details">
                                    <?php if ($row['customer_name'] || $row['customer_phone']): ?>
                                        <div class="customer-info">
                                            <div class="fw-semibold">
                                                <i class="fas fa-user me-2"></i><?= htmlspecialchars($row['customer_name'] ?: 'Walk-in Customer') ?>
                                            </div>
                                            <?php if ($row['customer_phone']): ?>
                                                <div class="text-muted small">
                                                    <i class="fas fa-phone me-2"></i><?= htmlspecialchars($row['customer_phone']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="order-items">
                                        <div class="fw-semibold mb-2">
                                            <i class="fas fa-utensils me-2"></i>Order Items (<?= $row['item_count'] ?>)
                                        </div>
                                        <div class="item-list"><?= htmlspecialchars($row['items']) ?></div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="total-amount">₹<?= number_format($row['total'], 2) ?></div>
                                        <div class="timer-display" id="timer-<?= $row['id'] ?>">
                                            <i class="fas fa-stopwatch me-1"></i>
                                            <span class="timer-minutes"><?= $age_minutes ?></span>m
                                        </div>
                                    </div>
                                    
                                    <?php if ($row['staff_name']): ?>
                                        <div class="staff-assign">
                                            <i class="fas fa-user-tie me-2"></i>
                                            <strong>Assigned to:</strong> <?= htmlspecialchars($row['staff_name']) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="action-buttons mt-3">
                                        <?php if ($row['status'] === 'pending'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="order_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="status" value="preparing">
                                                <button type="submit" name="update_status" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-play"></i> Start Preparing
                                                </button>
                                            </form>
                                        <?php elseif ($row['status'] === 'preparing'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="order_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="status" value="ready">
                                                <button type="submit" name="update_status" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check"></i> Mark Ready
                                                </button>
                                            </form>
                                        <?php elseif ($row['status'] === 'ready'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="order_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="status" value="completed">
                                                <button type="submit" name="update_status" class="btn btn-outline-success btn-sm">
                                                    <i class="fas fa-handshake"></i> Complete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-outline-primary btn-sm" onclick="viewOrderDetails(<?= $row['id'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-secondary btn-sm" onclick="printOrder(<?= $row['id'] ?>)">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        
                                        <?php if (!$row['staff_name'] && in_array($row['status'], ['pending', 'preparing'])): ?>
                                            <button class="btn btn-outline-info btn-sm" onclick="assignStaff(<?= $row['id'] ?>)">
                                                <i class="fas fa-user-plus"></i> Assign
                                            </button>
                                        <?php endif; ?>
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
                            <p class="text-muted">Orders will appear here when customers place them.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Assign Staff Modal -->
<div class="modal fade" id="assignStaffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Staff Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="order_id" id="assignOrderId">
                    <div class="mb-3">
                        <label class="form-label">Select Staff Member</label>
                        <select name="staff_id" class="form-select" required>
                            <option value="">Choose staff...</option>
                            <?php while($staff = $staff_list->fetch_assoc()): ?>
                                <option value="<?= $staff['id'] ?>"><?= htmlspecialchars($staff['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="assign_staff" class="btn btn-primary">Assign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let autoRefreshInterval;

function filterByStatus(status) {
    const url = new URL(window.location);
    if (status === 'all') {
        url.searchParams.delete('status');
    } else {
        url.searchParams.set('status', status);
    }
    window.location.href = url.toString();
}

function refreshOrders() {
    window.location.reload();
}

function exportOrders() {
    window.open('export_orders.php?type=<?= $type ?>', '_blank');
}

function printKitchenTickets() {
    window.open('print_kitchen_tickets.php?type=<?= $type ?>', '_blank');
}

function viewOrderDetails(orderId) {
    window.open('order_details.php?id=' + orderId, '_blank');
}

function printOrder(orderId) {
    window.open('print_order.php?id=' + orderId, '_blank');
}

function assignStaff(orderId) {
    document.getElementById('assignOrderId').value = orderId;
    new bootstrap.Modal(document.getElementById('assignStaffModal')).show();
}

// Auto-refresh functionality
function toggleAutoRefresh() {
    const checkbox = document.getElementById('autoRefresh');
    if (checkbox.checked) {
        autoRefreshInterval = setInterval(refreshOrders, 30000); // Refresh every 30 seconds
    } else {
        clearInterval(autoRefreshInterval);
    }
}

// Update timers
function updateTimers() {
    document.querySelectorAll('.timer-minutes').forEach(timer => {
        const currentMinutes = parseInt(timer.textContent);
        timer.textContent = currentMinutes + 1;
        
        // Update priority colors based on time
        const orderCard = timer.closest('.order-card');
        orderCard.classList.remove('priority-low', 'priority-medium', 'priority-high');
        
        if (currentMinutes > 30) {
            orderCard.classList.add('priority-high');
        } else if (currentMinutes > 15) {
            orderCard.classList.add('priority-medium');
        } else {
            orderCard.classList.add('priority-low');
        }
    });
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    toggleAutoRefresh();
    document.getElementById('autoRefresh').addEventListener('change', toggleAutoRefresh);
    
    // Update timers every minute
    setInterval(updateTimers, 60000);
});

// Sound notification for new orders (optional)
function playNotificationSound() {
    const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+L2umkcBTGH0+/PfC8GM2+57+GVSA0PVqzn77BdGAg+ltryxnkpBSl+zPLaizsIGGS57OOYTgwOUarm9bhjHgU2jdXzzn0vBSF1xe/glEgODlOq5O+zYBoGPJPY88p9KwUme8rx3I4+CRZiturqpVITC0ml4/a2ZRsGNIzU8tGAMQYhccTv45ZFDBFYrObxu2EaBDuS2fPKfSsFJnnI8tyOOQkXZL3s5ZdPDAxPqOX0t2MeBDON1vTOgC4GM2+675+Sag0PVKzl87ZjHAU4k9n1unEiBC13yO/eizEKH2q+6OWYTgwKVKjj7blmGgM1jdTy0H4wBiFxxPDak0IND1as5O2yXxkJPpPX88p9LAUmecnw34xQQwAAAPA'); // Simple beep sound
    audio.play().catch(() => {}); // Ignore errors if audio can't play
}
</script>

<?php include_once 'template/footer.php'; ?>