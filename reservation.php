<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
include_once 'template/header.php';

// Handle reservation operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_reservation'])) {
        $customer_name = $_POST['customer_name'];
        $customer_phone = $_POST['customer_phone'];
        $table_no = $_POST['table_no'];
        $reservation_time = $_POST['reservation_time'];
        $party_size = $_POST['party_size'];
        $special_requests = $_POST['special_requests'];
        
        $stmt = $conn->prepare("INSERT INTO reservations (customer_name, customer_phone, table_no, reservation_time, party_size, special_requests, status) VALUES (?, ?, ?, ?, ?, ?, 'confirmed')");
        $stmt->bind_param("ssisss", $customer_name, $customer_phone, $table_no, $reservation_time, $party_size, $special_requests);
        $stmt->execute();
    }
    
    if (isset($_POST['update_status'])) {
        $reservation_id = $_POST['reservation_id'];
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE reservations SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $reservation_id);
        $stmt->execute();
    }
}

// Get filter parameters
$date_filter = $_GET['date'] ?? date('Y-m-d');
$status_filter = $_GET['status'] ?? '';

// Build query with filters
$where_conditions = ["DATE(reservation_time) = ?"];
$params = [$date_filter];
$param_types = 's';

if ($status_filter) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$reservations_query = "SELECT * FROM reservations $where_clause ORDER BY reservation_time ASC";
$stmt = $conn->prepare($reservations_query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$reservations = $stmt->get_result();

// Get statistics
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_reservations,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(party_size) as total_guests
    FROM reservations 
    WHERE DATE(reservation_time) = '$date_filter'
")->fetch_assoc();
?>

<style>
.reservation-stats { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px; padding: 20px; margin-bottom: 30px; }
.reservation-card { border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px; transition: all 0.3s ease; }
.reservation-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
.filter-card { background: #f8f9fa; border-radius: 15px; padding: 20px; margin-bottom: 20px; border: none; }
.status-confirmed { border-left: 5px solid #38a169; background: #f0fff4; }
.status-completed { border-left: 5px solid #3182ce; background: #ebf8ff; }
.status-cancelled { border-left: 5px solid #e53e3e; background: #fed7d7; }
.status-pending { border-left: 5px solid #ed8936; background: #fffaf0; }
.table-badge { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 8px 15px; border-radius: 25px; font-weight: 600; }
.party-size { background: #e6fffa; color: #00695c; padding: 5px 10px; border-radius: 15px; font-size: 0.85rem; font-weight: 600; }
.time-slot { background: #4a5568; color: white; padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
.customer-info { padding: 15px; }
.special-request { background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 10px; margin-top: 10px; font-size: 0.9rem; }
.reservation-timeline { position: relative; padding: 20px 0; }
.timeline-item { position: relative; padding: 15px 0 15px 40px; border-left: 2px solid #e2e8f0; }
.timeline-item:last-child { border-left: none; }
.timeline-dot { position: absolute; left: -6px; top: 20px; width: 12px; height: 12px; border-radius: 50%; background: #667eea; }
.table-layout { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; padding: 20px; background: #f8f9fa; border-radius: 15px; margin-bottom: 20px; }
.table-item { aspect-ratio: 1; display: flex; align-items: center; justify-content: center; border-radius: 12px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
.table-available { background: #c6f6d5; color: #22543d; }
.table-reserved { background: #fed7d7; color: #742a2a; }
.table-occupied { background: #bee3f8; color: #2c5282; }
</style>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold text-dark"><i class="fas fa-calendar-check me-2 text-primary"></i>Reservation Management</h2>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="exportReservations()">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#tableLayoutModal">
                        <i class="fas fa-th me-2"></i>Table Layout
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addReservationModal">
                        <i class="fas fa-plus me-2"></i>New Reservation
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="reservation-stats text-center">
                        <h4 class="mb-0"><?= $stats['total_reservations'] ?></h4>
                        <p class="mb-0 opacity-75 small">Total</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="reservation-stats text-center">
                        <h4 class="mb-0 text-success"><?= $stats['confirmed'] ?></h4>
                        <p class="mb-0 opacity-75 small">Confirmed</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="reservation-stats text-center">
                        <h4 class="mb-0 text-primary"><?= $stats['completed'] ?></h4>
                        <p class="mb-0 opacity-75 small">Completed</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="reservation-stats text-center">
                        <h4 class="mb-0 text-danger"><?= $stats['cancelled'] ?></h4>
                        <p class="mb-0 opacity-75 small">Cancelled</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="reservation-stats text-center">
                        <h4 class="mb-0"><?= $stats['total_guests'] ?></h4>
                        <p class="mb-0 opacity-75 small">Total Guests Expected</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card filter-card">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Reservation Date</label>
                        <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date_filter) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Status Filter</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                            <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            <option value="no-show" <?= $status_filter === 'no-show' ? 'selected' : '' ?>>No Show</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2"><i class="fas fa-search me-2"></i>Filter</button>
                        <a href="reservation.php" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Reservations List -->
            <div class="row">
                <?php if ($reservations->num_rows > 0): ?>
                    <?php while($row = $reservations->fetch_assoc()): ?>
                        <div class="col-lg-6 col-xl-4">
                            <div class="reservation-card status-<?= $row['status'] ?>">
                                <div class="customer-info">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="fw-bold mb-1">
                                                <i class="fas fa-user me-1"></i><?= htmlspecialchars($row['customer_name']) ?>
                                            </h6>
                                            <div class="text-muted small">
                                                <i class="fas fa-phone me-1"></i><?= htmlspecialchars($row['customer_phone']) ?>
                                            </div>
                                        </div>
                                        <span class="badge bg-<?= $row['status'] === 'confirmed' ? 'success' : ($row['status'] === 'completed' ? 'primary' : 'danger') ?>">
                                            <?= ucfirst($row['status']) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="row g-2 mb-2">
                                        <div class="col-6">
                                            <div class="table-badge text-center">
                                                <i class="fas fa-table me-1"></i>Table <?= $row['table_no'] ?>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="party-size text-center">
                                                <i class="fas fa-users me-1"></i><?= $row['party_size'] ?> Guests
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-center mb-3">
                                        <div class="time-slot">
                                            <i class="fas fa-clock me-1"></i>
                                            <?= date('M d, Y - g:i A', strtotime($row['reservation_time'])) ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($row['special_requests']): ?>
                                        <div class="special-request">
                                            <i class="fas fa-sticky-note me-2"></i>
                                            <strong>Special Requests:</strong><br>
                                            <?= htmlspecialchars($row['special_requests']) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-3 d-flex gap-2">
                                        <?php if ($row['status'] === 'confirmed'): ?>
                                            <form method="POST" style="flex: 1;">
                                                <input type="hidden" name="reservation_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="status" value="completed">
                                                <button type="submit" name="update_status" class="btn btn-sm btn-success w-100">
                                                    <i class="fas fa-check"></i> Complete
                                                </button>
                                            </form>
                                            <form method="POST" style="flex: 1;">
                                                <input type="hidden" name="reservation_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="status" value="no-show">
                                                <button type="submit" name="update_status" class="btn btn-sm btn-warning w-100">
                                                    <i class="fas fa-times"></i> No Show
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-sm btn-outline-primary" onclick="editReservation(<?= $row['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fs-1 text-muted mb-3"></i>
                            <h5 class="text-muted">No reservations found</h5>
                            <p class="text-muted">No reservations for the selected date and filters.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Reservation Modal -->
<div class="modal fade" id="addReservationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Reservation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Customer Name *</label>
                                <input type="text" name="customer_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Phone Number *</label>
                                <input type="tel" name="customer_phone" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Table Number *</label>
                                <select name="table_no" class="form-select" required>
                                    <option value="">Select Table</option>
                                    <?php for($i = 1; $i <= 20; $i++): ?>
                                        <option value="<?= $i ?>">Table <?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Party Size *</label>
                                <input type="number" name="party_size" class="form-control" min="1" max="20" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Reservation Time *</label>
                                <input type="datetime-local" name="reservation_time" class="form-control" required 
                                       min="<?= date('Y-m-d\TH:i') ?>">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Special Requests</label>
                        <textarea name="special_requests" class="form-control" rows="3" 
                                  placeholder="Any special requirements, dietary restrictions, or celebration details..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_reservation" class="btn btn-primary">Create Reservation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Table Layout Modal -->
<div class="modal fade" id="tableLayoutModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Restaurant Table Layout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-layout">
                    <?php for($i = 1; $i <= 20; $i++): ?>
                        <div class="table-item table-available" onclick="selectTable(<?= $i ?>)">
                            Table <?= $i ?>
                        </div>
                    <?php endfor; ?>
                </div>
                <div class="d-flex gap-3 mt-3">
                    <div><span class="badge table-available">Available</span> Available</div>
                    <div><span class="badge table-reserved">Reserved</span> Reserved</div>
                    <div><span class="badge table-occupied">Occupied</span> Occupied</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportReservations() {
    window.location.href = 'export_reservations.php' + window.location.search;
}

function editReservation(id) {
    // Implementation for editing reservation
    alert('Edit reservation functionality - ID: ' + id);
}

function selectTable(tableNumber) {
    document.querySelector('select[name="table_no"]').value = tableNumber;
    bootstrap.Modal.getInstance(document.getElementById('tableLayoutModal')).hide();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('addReservationModal')).show();
}

// Auto-refresh for real-time updates
setInterval(function() {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 60000);
</script>

<?php include_once 'template/footer.php'; ?>