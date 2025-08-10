<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
include_once 'template/header.php';

// Handle supplier actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_supplier'])) {
        $name = $_POST['name'];
        $contact = $_POST['contact'];
        $email = $_POST['email'];
        $address = $_POST['address'];
        $rating = $_POST['rating'];
        
        $stmt = $conn->prepare("INSERT INTO suppliers (name, contact, email, address, rating) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssd", $name, $contact, $email, $address, $rating);
        $stmt->execute();
    }
}

$suppliers = $conn->query("SELECT * FROM suppliers ORDER BY rating DESC");
?>

<style>
.supplier-card { border-radius: 15px; transition: transform 0.3s ease, box-shadow 0.3s ease; border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.supplier-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
.rating-stars { color: #ffc107; }
.supplier-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px 15px 0 0; padding: 20px; }
.contact-info { background: #f8f9fa; padding: 15px; border-radius: 10px; margin-top: 15px; }
.performance-badge { padding: 8px 15px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
.excellent { background: linear-gradient(135deg, #38a169, #48bb78); color: white; }
.good { background: linear-gradient(135deg, #3182ce, #4299e1); color: white; }
.average { background: linear-gradient(135deg, #ed8936, #f6ad55); color: white; }
.poor { background: linear-gradient(135deg, #e53e3e, #fc8181); color: white; }
</style>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold text-dark"><i class="fas fa-truck me-2 text-primary"></i>Supplier Management</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                    <i class="fas fa-plus me-2"></i>Add New Supplier
                </button>
            </div>

            <div class="row">
                <?php while($row = $suppliers->fetch_assoc()): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card supplier-card h-100">
                        <div class="supplier-header text-center">
                            <div class="supplier-avatar mb-3">
                                <i class="fas fa-building fs-2"></i>
                            </div>
                            <h5 class="mb-0"><?= htmlspecialchars($row['name']) ?></h5>
                            <div class="rating-stars mt-2">
                                <?php 
                                $rating = $row['rating'];
                                for($i = 1; $i <= 5; $i++) {
                                    echo $i <= $rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                }
                                ?>
                                <span class="ms-2">(<?= $rating ?>/5)</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="contact-info">
                                <div class="mb-2">
                                    <i class="fas fa-phone text-primary me-2"></i>
                                    <strong>Phone:</strong> <?= htmlspecialchars($row['contact']) ?>
                                </div>
                                <?php if(isset($row['email'])): ?>
                                <div class="mb-2">
                                    <i class="fas fa-envelope text-primary me-2"></i>
                                    <strong>Email:</strong> <?= htmlspecialchars($row['email']) ?>
                                </div>
                                <?php endif; ?>
                                <?php if(isset($row['address'])): ?>
                                <div class="mb-2">
                                    <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                    <strong>Address:</strong> <?= htmlspecialchars($row['address']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <span class="performance-badge <?php
                                    if($rating >= 4.5) echo 'excellent';
                                    elseif($rating >= 3.5) echo 'good'; 
                                    elseif($rating >= 2.5) echo 'average';
                                    else echo 'poor';
                                ?>">
                                    <?php
                                    if($rating >= 4.5) echo 'Excellent';
                                    elseif($rating >= 3.5) echo 'Good'; 
                                    elseif($rating >= 2.5) echo 'Average';
                                    else echo 'Needs Improvement';
                                    ?>
                                </span>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-outline-primary" onclick="editSupplier(<?= $row['id'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-info" onclick="viewSupplierDetails(<?= $row['id'] ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteSupplier(<?= $row['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Supplier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Supplier Name *</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Contact Number *</label>
                                <input type="tel" name="contact" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Rating</label>
                                <select name="rating" class="form-select">
                                    <option value="5">5 - Excellent</option>
                                    <option value="4">4 - Good</option>
                                    <option value="3" selected>3 - Average</option>
                                    <option value="2">2 - Below Average</option>
                                    <option value="1">1 - Poor</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_supplier" class="btn btn-primary">Add Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editSupplier(id) {
    // Implementation for editing supplier
    alert('Edit supplier functionality - ID: ' + id);
}

function viewSupplierDetails(id) {
    // Implementation for viewing supplier details
    alert('View supplier details - ID: ' + id);
}

function deleteSupplier(id) {
    if (confirm('Are you sure you want to delete this supplier?')) {
        window.location.href = 'delete_supplier.php?id=' + id;
    }
}
</script>

<?php include_once 'template/footer.php'; ?>