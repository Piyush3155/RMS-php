<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
include_once 'template/header.php';

// Handle shift operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_shift'])) {
        $staff_id = $_POST['staff_id'];
        $shift_date = $_POST['shift_date'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        
        $stmt = $conn->prepare("INSERT INTO shifts (staff_id, shift_date, start_time, end_time, attendance) VALUES (?, ?, ?, ?, 0)");
        $stmt->bind_param("isss", $staff_id, $shift_date, $start_time, $end_time);
        $stmt->execute();
    }
    
    if (isset($_POST['update_attendance'])) {
        $shift_id = $_POST['shift_id'];
        $attendance = $_POST['attendance'];
        
        $stmt = $conn->prepare("UPDATE shifts SET attendance = ? WHERE id = ?");
        $stmt->bind_param("ii", $attendance, $shift_id);
        $stmt->execute();
    }
}

// Get filter parameters
$date_filter = $_GET['date'] ?? date('Y-m-d');
$staff_filter = $_GET['staff'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = '';

if ($date_filter) {
    $where_conditions[] = "s.shift_date = ?";
    $params[] = $date_filter;
    $param_types .= 's';
}

if ($staff_filter) {
    $where_conditions[] = "s.staff_id = ?";
    $params[] = $staff_filter;
    $param_types .= 'i';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

$shifts_query = "SELECT s.*, u.name, u.role FROM shifts s JOIN users u ON s.staff_id = u.id $where_clause ORDER BY shift_date DESC, start_time ASC";
if (!empty($params)) {
    $stmt = $conn->prepare($shifts_query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $shifts = $stmt->get_result();
} else {
    $shifts = $conn->query($shifts_query);
}

// Get staff list and statistics
$staff_list = $conn->query("SELECT id, name, role FROM users WHERE role IN ('waiter','chef','cashier','manager') ORDER BY name");
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_shifts,
        SUM(CASE WHEN attendance = 1 THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN attendance = 0 AND shift_date <= CURDATE() THEN 1 ELSE 0 END) as absent_count
    FROM shifts s $where_clause
")->fetch_assoc();
?>

<style>
.shift-stats { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px; padding: 25px; margin-bottom: 30px; }
.shift-card { border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 20px; }
.shift-header { background: linear-gradient(135deg, #2d3748, #4a5568); color: white; padding: 15px; }
.filter-card { background: #f8f9fa; border-radius: 15px; padding: 20px; margin-bottom: 20px; border: none; }
.staff-card { border-radius: 12px; padding: 20px; margin-bottom: 15px; transition: all 0.3s ease; }
.staff-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
.present { background: linear-gradient(135deg, #c6f6d5, #9ae6b4); border-left: 4px solid #38a169; }
.absent { background: linear-gradient(135deg, #fed7d7, #fbb6ce); border-left: 4px solid #e53e3e; }
.scheduled { background: linear-gradient(135deg, #bee3f8, #90cdf4); border-left: 4px solid #3182ce; }
.time-badge { background: #4a5568; color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; margin: 2px; }
.role-badge { padding: 4px 10px; border-radius: 15px; font-size: 0.75rem; font-weight: 600; }
.waiter { background: #e6fffa; color: #00695c; }
.chef { background: #fff3e0; color: #ef6c00; }
.cashier { background: #f3e5f5; color: #7b1fa2; }
.manager { background: #e8f5e8; color: #2e7d32; }
.attendance-toggle { background: none; border: none; padding: 8px 15px; border-radius: 20px; font-weight: 600; transition: all 0.3s ease; }
.attendance-toggle.present { background: #c6f6d5; color: #22543d; }
.attendance-toggle.absent { background: #fed7d7; color: #742a2a; }
.calendar-view { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
</style>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold text-dark"><i class="fas fa-calendar-alt me-2 text-primary"></i>Shift Management</h2>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="exportShifts()">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkScheduleModal">
                        <i class="fas fa-calendar-plus me-2"></i>Bulk Schedule
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addShiftModal">
                        <i class="fas fa-plus me-2"></i>Add Shift
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="shift-stats text-center">
                        <div class="fs-1 mb-2"><i class="fas fa-calendar-check"></i></div>
                        <h3 class="mb-0"><?= $stats['total_shifts'] ?></h3>
                        <p class="mb-0 opacity-75">Total Shifts</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="shift-stats text-center">
                        <div class="fs-1 mb-2"><i class="fas fa-user-check"></i></div>
                        <h3 class="mb-0 text-success"><?= $stats['present_count'] ?></h3>
                        <p class="mb-0 opacity-75">Present</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="shift-stats text-center">
                        <div class="fs-1 mb-2"><i class="fas fa-user-times"></i></div>
                        <h3 class="mb-0 text-danger"><?= $stats['absent_count'] ?></h3>
                        <p class="mb-0 opacity-75">Absent</p>
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
                            <?php 
                            $staff_list_filter = $conn->query("SELECT id, name, role FROM users WHERE role IN ('waiter','chef','cashier','manager') ORDER BY name");
                            while($staff = $staff_list_filter->fetch_assoc()): 
                            ?>
                                <option value="<?= $staff['id'] ?>" <?= $staff_filter == $staff['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($staff['name']) ?> (<?= ucfirst($staff['role']) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2"><i class="fas fa-search me-2"></i>Filter</button>
                        <a href="shifts.php" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Shifts Display -->
            <div class="row">
                <?php if ($shifts->num_rows > 0): ?>
                    <?php while($row = $shifts->fetch_assoc()): ?>
                        <?php
                        $status_class = '';
                        $status_text = '';
                        $current_date = date('Y-m-d');
                        
                        if ($row['shift_date'] < $current_date) {
                            if ($row['attendance'] == 1) {
                                $status_class = 'present';
                                $status_text = 'Present';
                            } else {
                                $status_class = 'absent';
                                $status_text = 'Absent';
                            }
                        } else {
                            $status_class = 'scheduled';
                            $status_text = 'Scheduled';
                        }
                        ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="staff-card <?= $status_class ?>">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($row['name']) ?></h6>
                                        <span class="role-badge <?= $row['role'] ?>"><?= ucfirst($row['role']) ?></span>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-muted"><?= date('M d', strtotime($row['shift_date'])) ?></div>
                                        <small class="text-muted"><?= date('D', strtotime($row['shift_date'])) ?></small>
                                    </div>
                                </div>
                                
                                <div class="mb-2">
                                    <span class="time-badge">
                                        <i class="fas fa-clock me-1"></i>
                                        <?= date('g:i A', strtotime($row['start_time'])) ?> - <?= date('g:i A', strtotime($row['end_time'])) ?>
                                    </span>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="fw-semibold text-<?= $status_class === 'present' ? 'success' : ($status_class === 'absent' ? 'danger' : 'primary') ?>">
                                            <?= $status_text ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($row['shift_date'] <= $current_date && $status_text !== 'Present'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="shift_id" value="<?= $row['id'] ?>">
                                            <input type="hidden" name="attendance" value="1">
                                            <button type="submit" name="update_attendance" class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-check"></i> Mark Present
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="calendar-view text-center py-5">
                            <i class="fas fa-calendar-times fs-1 text-muted mb-3"></i>
                            <h5 class="text-muted">No shifts scheduled</h5>
                            <p class="text-muted">Add shifts for the selected date to see them here.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Shift Modal -->
<div class="modal fade" id="addShiftModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule New Shift</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Staff Member</label>
                        <select name="staff_id" class="form-select" required>
                            <option value="">Select Staff</option>
                            <?php 
                            $staff_list->data_seek(0);
                            while($staff = $staff_list->fetch_assoc()): 
                            ?>
                                <option value="<?= $staff['id'] ?>">
                                    <?= htmlspecialchars($staff['name']) ?> (<?= ucfirst($staff['role']) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Shift Date</label>
                        <input type="date" name="shift_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="mb-3">
                                <label class="form-label">Start Time</label>
                                <input type="time" name="start_time" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <label class="form-label">End Time</label>
                                <input type="time" name="end_time" class="form-control" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_shift" class="btn btn-primary">Schedule Shift</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function exportShifts() {
    window.location.href = 'export_shifts.php' + window.location.search;
}

// Auto-refresh every 30 seconds for real-time updates
setInterval(function() {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 30000);
</script>

<?php include_once 'template/footer.php'; ?>