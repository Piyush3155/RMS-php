<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
include_once 'template/header.php';

// Handle promo code operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_promo'])) {
        $code = strtoupper($_POST['code']);
        $type = $_POST['type'];
        $value = $_POST['value'];
        $valid_from = $_POST['valid_from'];
        $valid_to = $_POST['valid_to'];
        $max_uses = $_POST['max_uses'];
        $min_order_value = $_POST['min_order_value'];
        $description = $_POST['description'];

        $stmt = $conn->prepare("INSERT INTO promo_codes (code, type, value, valid_from, valid_to, max_uses, min_order_value, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        if ($stmt) {
            $stmt->bind_param("ssdssids", $code, $type, $value, $valid_from, $valid_to, $max_uses, $min_order_value, $description);
            $stmt->execute();
            $stmt->close();
        } else {
            // Optionally log or display error: $conn->error
        }
    }

    if (isset($_POST['toggle_status'])) {
        $id = $_POST['promo_id'];
        $new_status = $_POST['new_status'];

        $stmt = $conn->prepare("UPDATE promo_codes SET status = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $new_status, $id);
            $stmt->execute();
            $stmt->close();
        } else {
            // Optionally log or display error: $conn->error
        }
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = '';

if ($search) {
    $where_conditions[] = "(code LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $param_types .= 'ss';
}

if ($status_filter && $status_filter !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

$promo_query = "SELECT * FROM promo_codes $where_clause ORDER BY created_at DESC";
if (!empty($params)) {
    $stmt = $conn->prepare($promo_query);
    if ($stmt) {
        $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $promo = $stmt->get_result();
    } else {
        $promo = false;
    }
} else {
    $promo = $conn->query($promo_query);
}

// Get statistics
$stats_result = $conn->query("
    SELECT 
        COUNT(*) as total_promos,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_promos,
        COUNT(CASE WHEN status = 'expired' THEN 1 END) as expired_promos,
        COUNT(CASE WHEN status = 'disabled' THEN 1 END) as disabled_promos
    FROM promo_codes $where_clause
");
$stats = $stats_result ? $stats_result->fetch_assoc() : [
    'total_promos' => 0,
    'active_promos' => 0,
    'expired_promos' => 0,
    'disabled_promos' => 0
];
?>

<style>
.promo-stats { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px; padding: 25px; margin-bottom: 30px; }
.promo-card { border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px; transition: all 0.3s ease; overflow: hidden; }
.promo-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
.promo-header { background: linear-gradient(135deg, #2d3748, #4a5568); color: white; padding: 15px; }
.filter-card { background: #f8f9fa; border-radius: 15px; padding: 20px; margin-bottom: 20px; border: none; }
.promo-code { font-family: 'Courier New', monospace; font-size: 1.2rem; font-weight: 700; padding: 8px 15px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 8px; display: inline-block; }
.promo-type { padding: 5px 12px; border-radius: 15px; font-size: 0.8rem; font-weight: 600; margin-left: 10px; }
.type-fixed { background: #c6f6d5; color: #22543d; }
.type-percent { background: #bee3f8; color: #2c5282; }
.type-bogo { background: #fed7d7; color: #742a2a; }
.status-badge { padding: 5px 12px; border-radius: 15px; font-size: 0.8rem; font-weight: 600; }
.status-active { background: #c6f6d5; color: #22543d; }
.status-expired { background: #fed7d7; color: #742a2a; }
.status-disabled { background: #e2e8f0; color: #4a5568; }
.usage-progress { background: #e2e8f0; height: 8px; border-radius: 4px; overflow: hidden; }
.usage-bar { background: linear-gradient(135deg, #38a169, #48bb78); height: 100%; transition: width 0.3s ease; }
.promo-details { padding: 20px; }
.value-display { font-size: 1.5rem; font-weight: 700; color: #38a169; }
.date-range { background: #f7fafc; padding: 10px; border-radius: 8px; font-size: 0.9rem; }
.copy-code-btn { background: #4a5568; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; margin-left: 8px; }
.copy-code-btn:hover { background: #2d3748; color: white; }
</style>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold text-dark"><i class="fas fa-tags me-2 text-success"></i>Promo Codes & Discounts</h2>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="exportPromos()">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                    <button class="btn btn-success" onclick="generateBulkPromos()">
                        <i class="fas fa-magic me-2"></i>Generate Bulk
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPromoModal">
                        <i class="fas fa-plus me-2"></i>Create Promo
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="promo-stats text-center">
                        <div class="fs-1 mb-2"><i class="fas fa-tags"></i></div>
                        <h3 class="mb-0"><?= $stats['total_promos'] ?></h3>
                        <p class="mb-0 opacity-75">Total Promos</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="promo-stats text-center">
                        <div class="fs-1 mb-2"><i class="fas fa-play-circle"></i></div>
                        <h3 class="mb-0 text-success"><?= $stats['active_promos'] ?></h3>
                        <p class="mb-0 opacity-75">Active</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="promo-stats text-center">
                        <div class="fs-1 mb-2"><i class="fas fa-clock"></i></div>
                        <h3 class="mb-0 text-danger"><?= $stats['expired_promos'] ?></h3>
                        <p class="mb-0 opacity-75">Expired</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="promo-stats text-center">
                        <div class="fs-1 mb-2"><i class="fas fa-pause-circle"></i></div>
                        <h3 class="mb-0 text-secondary"><?= $stats['disabled_promos'] ?></h3>
                        <p class="mb-0 opacity-75">Disabled</p>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="card filter-card">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Search Promo Codes</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by code or description..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Filter by Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="expired" <?= $status_filter === 'expired' ? 'selected' : '' ?>>Expired</option>
                            <option value="disabled" <?= $status_filter === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2"><i class="fas fa-search me-2"></i>Search</button>
                        <a href="promo.php" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Promo Codes Grid -->
            <div class="row">
                <?php if ($promo && $promo->num_rows > 0): ?>
                    <?php while($row = $promo->fetch_assoc()): ?>
                        <div class="col-lg-6 col-xl-4">
                            <div class="promo-card">
                                <div class="promo-details">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <div class="promo-code" onclick="copyToClipboard('<?= $row['code'] ?>')">
                                                <?= htmlspecialchars($row['code']) ?>
                                                <button class="copy-code-btn" title="Copy Code">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                            <span class="promo-type type-<?= $row['type'] ?>">
                                                <?= ucfirst($row['type']) ?>
                                            </span>
                                        </div>
                                        <span class="status-badge status-<?= $row['status'] ?>">
                                            <?= ucfirst($row['status']) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="value-display">
                                            <?php if ($row['type'] === 'percent'): ?>
                                                <?= $row['value'] ?>% OFF
                                            <?php elseif ($row['type'] === 'fixed'): ?>
                                                ₹<?= number_format($row['value'], 2) ?> OFF
                                            <?php else: ?>
                                                Buy 1 Get 1
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($row['description']): ?>
                                            <p class="text-muted mb-2"><?= htmlspecialchars($row['description']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="date-range mb-3">
                                        <div class="row">
                                            <div class="col-6">
                                                <strong>Valid From:</strong><br>
                                                <?= date('M d, Y', strtotime($row['valid_from'])) ?>
                                            </div>
                                            <div class="col-6">
                                                <strong>Valid Until:</strong><br>
                                                <?= date('M d, Y', strtotime($row['valid_to'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($row['min_order_value'] > 0): ?>
                                        <div class="mb-2">
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Minimum order: ₹<?= number_format($row['min_order_value'], 2) ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <small class="text-muted">Usage</small>
                                            <small class="text-muted"><?= $row['used_count'] ?? 0 ?> / <?= $row['max_uses'] ?></small>
                                        </div>
                                        <div class="usage-progress">
                                            <div class="usage-bar" style="width: <?= $row['max_uses'] > 0 ? min(100, (($row['used_count'] ?? 0) / $row['max_uses']) * 100) : 0 ?>%"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <?php if ($row['status'] === 'active'): ?>
                                            <form method="POST" style="flex: 1;">
                                                <input type="hidden" name="promo_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="new_status" value="disabled">
                                                <button type="submit" name="toggle_status" class="btn btn-sm btn-outline-warning w-100">
                                                    <i class="fas fa-pause"></i> Disable
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="flex: 1;">
                                                <input type="hidden" name="promo_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="new_status" value="active">
                                                <button type="submit" name="toggle_status" class="btn btn-sm btn-outline-success w-100">
                                                    <i class="fas fa-play"></i> Enable
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-sm btn-outline-primary" onclick="editPromo(<?= $row['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-info" onclick="viewUsageStats(<?= $row['id'] ?>)">
                                            <i class="fas fa-chart-bar"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-tags fs-1 text-muted mb-3"></i>
                            <h5 class="text-muted">No promo codes found</h5>
                            <p class="text-muted">Create your first promo code to attract customers.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Promo Modal -->
<div class="modal fade" id="addPromoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Promo Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Promo Code *</label>
                                <input type="text" name="code" class="form-control text-uppercase" 
                                       placeholder="e.g., SAVE20" required 
                                       onkeyup="this.value = this.value.toUpperCase()">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Discount Type *</label>
                                <select name="type" class="form-select" required onchange="updateValueField(this.value)">
                                    <option value="">Select Type</option>
                                    <option value="fixed">Fixed Amount</option>
                                    <option value="percent">Percentage</option>
                                    <option value="bogo">Buy One Get One</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Discount Value *</label>
                                <div class="input-group">
                                    <span class="input-group-text" id="valuePrefix">₹</span>
                                    <input type="number" step="0.01" name="value" class="form-control" 
                                           placeholder="Enter value" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Maximum Uses</label>
                                <input type="number" name="max_uses" class="form-control" 
                                       placeholder="Unlimited" min="1">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Valid From *</label>
                                <input type="date" name="valid_from" class="form-control" 
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Valid Until *</label>
                                <input type="date" name="valid_to" class="form-control" 
                                       min="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Minimum Order Value</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" step="0.01" name="min_order_value" class="form-control" 
                                   placeholder="0.00">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2" 
                                  placeholder="Optional description for internal use"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_promo" class="btn btn-primary">Create Promo Code</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateValueField(type) {
    const prefix = document.getElementById('valuePrefix');
    const valueInput = document.querySelector('input[name="value"]');
    
    if (type === 'percent') {
        prefix.textContent = '%';
        valueInput.max = 100;
        valueInput.placeholder = 'e.g., 20';
    } else if (type === 'fixed') {
        prefix.textContent = '₹';
        valueInput.removeAttribute('max');
        valueInput.placeholder = 'e.g., 100';
    } else if (type === 'bogo') {
        prefix.textContent = '';
        valueInput.value = 1;
        valueInput.disabled = true;
    } else {
        prefix.textContent = '₹';
        valueInput.disabled = false;
        valueInput.removeAttribute('max');
    }
}

function copyToClipboard(code) {
    navigator.clipboard.writeText(code).then(function() {
        // Show toast notification
        const toast = document.createElement('div');
        toast.className = 'toast show position-fixed top-0 end-0 m-3';
        toast.innerHTML = `
            <div class="toast-body bg-success text-white">
                <i class="fas fa-check me-2"></i>Code "${code}" copied to clipboard!
            </div>
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    });
}

function exportPromos() {
    window.location.href = 'export_promos.php' + window.location.search;
}

function generateBulkPromos() {
    alert('Bulk promo generation functionality coming soon');
}

function editPromo(id) {
    alert('Edit promo functionality - ID: ' + id);
}

function viewUsageStats(id) {
    alert('Usage statistics for promo ID: ' + id);
}
</script>

<?php include_once 'template/footer.php'; ?>