<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
include_once 'template/header.php';

// Get filter parameters
$date_filter = $_GET['date'] ?? '';
$staff_filter = $_GET['staff'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = '';

if ($date_filter) {
    $where_conditions[] = "DATE(t.distributed_at) = ?";
    $params[] = $date_filter;
    $param_types .= 's';
}

if ($staff_filter) {
    $where_conditions[] = "t.staff_id = ?";
    $params[] = $staff_filter;
    $param_types .= 'i';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get tips with filters
$tips_query = "SELECT t.*, u.name FROM tips t JOIN users u ON t.staff_id = u.id $where_clause ORDER BY distributed_at DESC";
if (!empty($params)) {
    $stmt = $conn->prepare($tips_query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $tips = $stmt->get_result();
} else {
    $tips = $conn->query($tips_query);
}

// Get summary statistics
$total_tips = $conn->query("SELECT SUM(amount) as total, COUNT(*) as count FROM tips t $where_clause")->fetch_assoc();
$staff_list = $conn->query("SELECT id, name FROM users WHERE role IN ('waiter','chef','cashier') ORDER BY name");
?>

<style>
.stats-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px; padding: 25px; margin-bottom: 30px; }
.filter-card { background: #f8f9fa; border-radius: 15px; padding: 20px; margin-bottom: 20px; border: none; }
.tips-table { border-radius: 15px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
.tips-table th { background: linear-gradient(135deg, #2d3748, #4a5568); color: white; font-weight: 600; padding: 15px; }
.tips-table td { padding: 15px; vertical-align: middle; }
.amount-badge { background: linear-gradient(135deg, #38a169, #48bb78); color: white; padding: 8px 15px; border-radius: 25px; font-weight: 600; }
.staff-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; margin-right: 10px; }
</style>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold text-dark"><i class="fas fa-hand-holding-usd me-2 text-success"></i>Tip Distribution</h2>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="exportTips()"><i class="fas fa-download me-2"></i>Export</button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTipModal"><i class="fas fa-plus me-2"></i>Add Tip</button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0">₹<?= number_format($total_tips['total'] ?? 0, 2) ?></h3>
                                <p class="mb-0 opacity-75">Total Tips Distributed</p>
                            </div>
                            <div class="fs-1 opacity-75"><i class="fas fa-coins"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0"><?= $total_tips['count'] ?? 0 ?></h3>
                                <p class="mb-0 opacity-75">Total Distributions</p>
                            </div>
                            <div class="fs-1 opacity-75"><i class="fas fa-receipt"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card filter-card">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Filter by Date</label>
                        <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date_filter) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Filter by Staff</label>
                        <select name="staff" class="form-select">
                            <option value="">All Staff</option>
                            <?php while($staff = $staff_list->fetch_assoc()): ?>
                                <option value="<?= $staff['id'] ?>" <?= $staff_filter == $staff['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($staff['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2"><i class="fas fa-search me-2"></i>Filter</button>
                        <a href="tips.php" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Tips Table -->
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover tips-table mb-0">
                        <thead>
                            <tr>
                                <th><i class="fas fa-user me-2"></i>Staff Member</th>
                                <th><i class="fas fa-receipt me-2"></i>Order ID</th>
                                <th><i class="fas fa-money-bill-wave me-2"></i>Amount</th>
                                <th><i class="fas fa-calendar me-2"></i>Date & Time</th>
                                <th><i class="fas fa-cogs me-2"></i>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($tips->num_rows > 0): ?>
                            <?php while($row = $tips->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="staff-avatar">
                                                <?= strtoupper(substr($row['name'], 0, 2)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-semibold"><?= htmlspecialchars($row['name']) ?></div>
                                                <small class="text-muted">Staff ID: <?= $row['staff_id'] ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">#<?= $row['order_id'] ?></span>
                                    </td>
                                    <td>
                                        <span class="amount-badge">₹<?= number_format($row['amount'],2) ?></span>
                                    </td>
                                    <td>
                                        <div><?= date('M d, Y', strtotime($row['distributed_at'])) ?></div>
                                        <small class="text-muted"><?= date('h:i A', strtotime($row['distributed_at'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewTipDetails(<?= $row['id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteTip(<?= $row['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <i class="fas fa-search fs-1 text-muted mb-3"></i>
                                    <h5 class="text-muted">No tips found</h5>
                                    <p class="text-muted">Try adjusting your filters or add a new tip distribution.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Tip Modal -->
<div class="modal fade" id="addTipModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Tip Distribution</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="tips_handler.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Staff Member</label>
                        <select name="staff_id" class="form-select" required>
                            <option value="">Select Staff</option>
                            <!-- Staff options will be populated here -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Order ID</label>
                        <input type="number" name="order_id" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tip Amount</label>
                        <input type="number" step="0.01" name="amount" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Tip</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function exportTips() {
    window.location.href = 'export_tips.php' + window.location.search;
}

function viewTipDetails(tipId) {
    // Implementation for viewing tip details
    alert('View tip details for ID: ' + tipId);
}

function deleteTip(tipId) {
    if (confirm('Are you sure you want to delete this tip record?')) {
        window.location.href = 'delete_tip.php?id=' + tipId;
    }
}
</script>

<?php include_once 'template/footer.php'; ?>