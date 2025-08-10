<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
include_once 'template/header.php';

// Handle menu operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_item'])) {
        $name = $_POST['name'];
        $description = $_POST['description'];
        $price = $_POST['price'];
        $category = $_POST['category'];
        $available = isset($_POST['available']) ? 1 : 0;
        
        $stmt = $conn->prepare("INSERT INTO menu (name, description, price, category, available) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdsi", $name, $description, $price, $category, $available);
        $stmt->execute();
    }
    
    if (isset($_POST['toggle_availability'])) {
        $id = $_POST['menu_id'];
        $available = $_POST['available'];
        
        $stmt = $conn->prepare("UPDATE menu SET available = ? WHERE id = ?");
        $stmt->bind_param("ii", $available, $id);
        $stmt->execute();
    }
    
    if (isset($_POST['update_price'])) {
        $id = $_POST['menu_id'];
        $price = $_POST['price'];
        
        $stmt = $conn->prepare("UPDATE menu SET price = ? WHERE id = ?");
        $stmt->bind_param("di", $price, $id);
        $stmt->execute();
    }
}

// Get filter parameters
$category_filter = $_GET['category'] ?? 'all';
$availability_filter = $_GET['availability'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = '';

if ($category_filter && $category_filter !== 'all') {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
    $param_types .= 's';
}

if ($availability_filter && $availability_filter !== 'all') {
    $where_conditions[] = "available = ?";
    $params[] = $availability_filter === 'available' ? 1 : 0;
    $param_types .= 'i';
}

if ($search) {
    $where_conditions[] = "(name LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $param_types .= 'ss';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
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

// Get categories and statistics
$categories = $conn->query("SELECT DISTINCT category FROM menu ORDER BY category");
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_items,
        COUNT(CASE WHEN available = 1 THEN 1 END) as available_items,
        COUNT(CASE WHEN available = 0 THEN 1 END) as unavailable_items,
        AVG(price) as avg_price
    FROM menu $where_clause
")->fetch_assoc();
?>

<style>
.menu-stats { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px; padding: 25px; margin-bottom: 30px; }
.menu-card { border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px; transition: all 0.3s ease; overflow: hidden; }
.menu-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
.filter-card { background: #f8f9fa; border-radius: 15px; padding: 20px; margin-bottom: 20px; border: none; }
.menu-image { width: 100px; height: 100px; object-fit: cover; border-radius: 12px; }
.menu-details { padding: 20px; }
.menu-name { font-size: 1.1rem; font-weight: 600; color: #2d3748; margin-bottom: 8px; }
.menu-description { color: #718096; font-size: 0.9rem; margin-bottom: 12px; line-height: 1.4; }
.menu-price { font-size: 1.3rem; font-weight: 700; color: #38a169; }
.category-badge { padding: 5px 12px; border-radius: 15px; font-size: 0.8rem; font-weight: 600; }
.appetizers { background: #fed7d7; color: #742a2a; }
.main-course { background: #c6f6d5; color: #22543d; }
.desserts { background: #fbb6ce; color: #97266d; }
.beverages { background: #bee3f8; color: #2c5282; }
.availability-toggle { border: none; padding: 8px 15px; border-radius: 20px; font-weight: 600; transition: all 0.3s ease; }
.available { background: #c6f6d5; color: #22543d; }
.unavailable { background: #fed7d7; color: #742a2a; }
.price-edit { width: 80px; text-align: center; border: 1px solid #e2e8f0; border-radius: 6px; }
.quick-actions { display: flex; gap: 5px; align-items: center; }
</style>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold text-dark"><i class="fas fa-utensils me-2 text-success"></i>Menu Management</h2>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="exportMenu()">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                    <button class="btn btn-success" onclick="generateQRCodes()">
                        <i class="fas fa-qrcode me-2"></i>Generate QR Codes
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMenuModal">
                        <i class="fas fa-plus me-2"></i>Add Menu Item
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="menu-stats text-center">
                        <div class="fs-1 mb-2"><i class="fas fa-clipboard-list"></i></div>
                        <h3 class="mb-0"><?= $stats['total_items'] ?></h3>
                        <p class="mb-0 opacity-75">Total Items</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="menu-stats text-center">
                        <div class="fs-1 mb-2"><i class="fas fa-check-circle"></i></div>
                        <h3 class="mb-0 text-success"><?= $stats['available_items'] ?></h3>
                        <p class="mb-0 opacity-75">Available</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="menu-stats text-center">
                        <div class="fs-1 mb-2"><i class="fas fa-times-circle"></i></div>
                        <h3 class="mb-0 text-danger"><?= $stats['unavailable_items'] ?></h3>
                        <p class="mb-0 opacity-75">Unavailable</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="menu-stats text-center">
                        <div class="fs-1 mb-2"><i class="fas fa-rupee-sign"></i></div>
                        <h3 class="mb-0">₹<?= number_format($stats['avg_price'], 0) ?></h3>
                        <p class="mb-0 opacity-75">Avg Price</p>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="card filter-card">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Search Items</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by name or description..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Filter by Category</label>
                        <select name="category" class="form-select">
                            <option value="all" <?= $category_filter === 'all' ? 'selected' : '' ?>>All Categories</option>
                            <?php 
                            $categories->data_seek(0);
                            while($cat = $categories->fetch_assoc()): 
                            ?>
                                <option value="<?= $cat['category'] ?>" <?= $category_filter === $cat['category'] ? 'selected' : '' ?>>
                                    <?= ucfirst($cat['category']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Availability</label>
                        <select name="availability" class="form-select">
                            <option value="all" <?= $availability_filter === 'all' ? 'selected' : '' ?>>All Items</option>
                            <option value="available" <?= $availability_filter === 'available' ? 'selected' : '' ?>>Available</option>
                            <option value="unavailable" <?= $availability_filter === 'unavailable' ? 'selected' : '' ?>>Unavailable</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2"><i class="fas fa-search me-2"></i>Filter</button>
                        <a href="menu.php" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Menu Items Grid -->
            <div class="row">
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <div class="col-lg-6 col-xl-4">
                            <div class="menu-card">
                                <div class="menu-details">
                                    <div class="d-flex align-items-start gap-3 mb-3">
                                        <?php if ($row['image']): ?>
                                            <img src="assets/img/<?= $row['image'] ?>" class="menu-image" alt="<?= htmlspecialchars($row['name']) ?>">
                                        <?php else: ?>
                                            <div class="menu-image d-flex align-items-center justify-content-center bg-light">
                                                <i class="fas fa-image text-muted fs-3"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div class="menu-name"><?= htmlspecialchars($row['name']) ?></div>
                                                <span class="category-badge <?= str_replace([' ', '-'], '', strtolower($row['category'])) ?>">
                                                    <?= ucfirst($row['category']) ?>
                                                </span>
                                            </div>
                                            <div class="menu-description"><?= htmlspecialchars($row['description']) ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="menu-price">₹</span>
                                            <form method="POST" class="d-inline">
                                                <input type="number" step="0.01" name="price" value="<?= $row['price'] ?>" 
                                                       class="price-edit" onchange="this.form.submit()">
                                                <input type="hidden" name="menu_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="update_price" value="1">
                                            </form>
                                        </div>
                                        <form method="POST">
                                            <input type="hidden" name="menu_id" value="<?= $row['id'] ?>">
                                            <input type="hidden" name="available" value="<?= $row['available'] ? 0 : 1 ?>">
                                            <button type="submit" name="toggle_availability" 
                                                    class="availability-toggle <?= $row['available'] ? 'available' : 'unavailable' ?>">
                                                <?= $row['available'] ? 'Available' : 'Unavailable' ?>
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <div class="quick-actions">
                                        <button class="btn btn-sm btn-outline-primary" onclick="editMenuItem(<?= $row['id'] ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-outline-success" onclick="viewRecipe(<?= $row['id'] ?>)">
                                            <i class="fas fa-book-open"></i> Recipe
                                        </button>
                                        <button class="btn btn-sm btn-outline-info" onclick="viewStats(<?= $row['id'] ?>)">
                                            <i class="fas fa-chart-bar"></i> Stats
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteMenuItem(<?= $row['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-utensils fs-1 text-muted mb-3"></i>
                            <h5 class="text-muted">No menu items found</h5>
                            <p class="text-muted">Try adjusting your filters or add a new menu item.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Menu Item Modal -->
<div class="modal fade" id="addMenuModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Menu Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Item Name *</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Category *</label>
                                <select name="category" class="form-select" required>
                                    <option value="">Select Category</option>
                                    <option value="appetizers">Appetizers</option>
                                    <option value="main-course">Main Course</option>
                                    <option value="desserts">Desserts</option>
                                    <option value="beverages">Beverages</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" 
                                  placeholder="Describe the dish, ingredients, preparation style..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Price (₹) *</label>
                                <input type="number" step="0.01" name="price" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="available" checked>
                                    <label class="form-check-label">
                                        Available for ordering
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_item" class="btn btn-primary">Add Menu Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function exportMenu() {
    window.location.href = 'export_menu.php' + window.location.search;
}

function generateQRCodes() {
    window.open('generate_qr_codes.php', '_blank');
}

function editMenuItem(id) {
    // Implementation for editing menu item
    alert('Edit menu item functionality - ID: ' + id);
}

function viewRecipe(id) {
    window.open('recipe.php?menu_id=' + id, '_blank');
}

function viewStats(id) {
    // Implementation for viewing item statistics
    alert('View statistics for menu item ID: ' + id);
}

function deleteMenuItem(id) {
    if (confirm('Are you sure you want to delete this menu item?')) {
        window.location.href = 'delete_menu_item.php?id=' + id;
    }
}
</script>

<?php include_once 'template/footer.php'; ?>