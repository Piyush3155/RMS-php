<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
include_once 'template/header.php';

// Handle stock operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_stock'])) {
        $id = $_POST['id'];
        $quantity = $_POST['quantity'];
        $stmt = $conn->prepare("UPDATE stock SET quantity = ? WHERE id = ?");
        $stmt->bind_param("di", $quantity, $id);
        $stmt->execute();
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';

// Build query
$where_conditions = [];
$params = [];
$param_types = '';

if ($search) {
    $where_conditions[] = "ingredient LIKE ?";
    $params[] = "%$search%";
    $param_types .= 's';
}

if ($filter === 'low_stock') {
    $where_conditions[] = "quantity < low_stock_threshold";
} elseif ($filter === 'out_of_stock') {
    $where_conditions[] = "quantity = 0";
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

$query = "SELECT * FROM stock $where_clause ORDER BY 
    CASE WHEN quantity = 0 THEN 1
         WHEN quantity < low_stock_threshold THEN 2
         ELSE 3 END,
    ingredient ASC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

// Get statistics
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_items,
        SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN quantity < low_stock_threshold AND quantity > 0 THEN 1 ELSE 0 END) as low_stock
    FROM stock
")->fetch_assoc();
?>

<style>
.inventory-stats { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px; padding: 20px; margin-bottom: 30px; }
.stock-card { border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.1); overflow: hidden; }
.stock-header { background: linear-gradient(135deg, #2d3748, #4a5568); color: white; padding: 15px; }
.search-card { background: #f8f9fa; border-radius: 15px; padding: 20px; margin-bottom: 20px; border: none; }
.quantity-input { width: 80px; text-align: center; border: 2px solid #e2e8f0; border-radius: 8px; }
.stock-status { padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
.in-stock { background: #c6f6d5; color: #22543d; }
.low-stock { background: #fed7d7; color: #742a2a; }
.out-of-stock { background: #feb2b2; color: #742a2a; }
.ingredient-icon { width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem; }
</style>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold text-dark"><i class="fas fa-boxes me-2 text-primary"></i>Inventory Management</h2>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-success" onclick="exportInventory()">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addStockModal">
                        <i class="fas fa-plus me-2"></i>Add Item
                    </button>
                </div>
            </div>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="inventory-stats text-center">
                        <div class="fs-1 mb-2"><i class="fas fa-cubes"></i></div>
                        <h3 class="mb-0"><?= $stats['total_items'] ?></h3>
                        <p class="mb-0 opacity-75">Total Items</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="inventory-stats text-center">
                        <div class="fs-1 mb-2"><i class="fas fa-exclamation-triangle"></i></div>
                        <h3 class="mb-0 text-warning"><?= $stats['low_stock'] ?></h3>
                        <p class="mb-0 opacity-75">Low Stock Items</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="inventory-stats text-center">
                        <div class="fs-1 mb-2"><i class="fas fa-times-circle"></i></div>
                        <h3 class="mb-0 text-danger"><?= $stats['out_of_stock'] ?></h3>
                        <p class="mb-0 opacity-75">Out of Stock</p>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="card search-card">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Search Ingredients</label>
                        <input type="text" name="search" class="form-control" placeholder="Search by ingredient name..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Filter by Status</label>
                        <select name="filter" class="form-select">
                            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Items</option>
                            <option value="low_stock" <?= $filter === 'low_stock' ? 'selected' : '' ?>>Low Stock</option>
                            <option value="out_of_stock" <?= $filter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary me-2"><i class="fas fa-search me-2"></i>Search</button>
                        <a href="stock.php" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Stock Table -->
            <div class="card stock-card">
                <div class="stock-header">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Inventory Items</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Ingredient</th>
                                <th>Current Stock</th>
                                <th>Unit</th>
                                <th>Low Stock Alert</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <?php
                                $status_class = '';
                                $status_text = '';
                                if ($row['quantity'] == 0) {
                                    $status_class = 'out-of-stock';
                                    $status_text = 'Out of Stock';
                                } elseif ($row['quantity'] < $row['low_stock_threshold']) {
                                    $status_class = 'low-stock';
                                    $status_text = 'Low Stock';
                                } else {
                                    $status_class = 'in-stock';
                                    $status_text = 'In Stock';
                                }
                                ?>
                                <tr class="<?= ($row['quantity'] < $row['low_stock_threshold']) ? 'table-warning' : '' ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="ingredient-icon me-3">
                                                <i class="fas fa-seedling"></i>
                                            </div>
                                            <div>
                                                <div class="fw-semibold"><?= htmlspecialchars($row['ingredient']) ?></div>
                                                <small class="text-muted">ID: <?= $row['id'] ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="fs-5 fw-bold me-2 <?= $row['quantity'] == 0 ? 'text-danger' : ($row['quantity'] < $row['low_stock_threshold'] ? 'text-warning' : 'text-success') ?>">
                                                <?= $row['quantity'] ?>
                                            </span>
                                            <?php if ($row['quantity'] < $row['low_stock_threshold']): ?>
                                                <i class="fas fa-exclamation-triangle text-warning"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($row['unit']) ?></span>
                                    </td>
                                    <td><?= $row['low_stock_threshold'] ?> <?= htmlspecialchars($row['unit']) ?></td>
                                    <td>
                                        <span class="stock-status <?= $status_class ?>"><?= $status_text ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="updateStock(<?= $row['id'] ?>, '<?= htmlspecialchars($row['ingredient']) ?>', <?= $row['quantity'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-success" onclick="restockItem(<?= $row['id'] ?>)">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-info" onclick="viewHistory(<?= $row['id'] ?>)">
                                                <i class="fas fa-history"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="fas fa-search fs-1 text-muted mb-3"></i>
                                    <h5 class="text-muted">No items found</h5>
                                    <p class="text-muted">Try adjusting your search or filters.</p>
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

<!-- Update Stock Modal -->
<div class="modal fade" id="updateStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Ingredient</label>
                        <input type="text" id="modal-ingredient" class="form-control" readonly>
                        <input type="hidden" name="id" id="modal-id">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Quantity</label>
                        <input type="number" step="0.01" name="quantity" id="modal-quantity" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_stock" class="btn btn-primary">Update Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateStock(id, ingredient, quantity) {
    document.getElementById('modal-id').value = id;
    document.getElementById('modal-ingredient').value = ingredient;
    document.getElementById('modal-quantity').value = quantity;
    new bootstrap.Modal(document.getElementById('updateStockModal')).show();
}

function restockItem(id) {
    // Implementation for restocking
    alert('Restock functionality for item ID: ' + id);
}

function viewHistory(id) {
    // Implementation for viewing stock history
    alert('View history for item ID: ' + id);
}

function exportInventory() {
    window.location.href = 'export_inventory.php' + window.location.search;
}
</script>

<?php include_once 'template/footer.php'; ?>
