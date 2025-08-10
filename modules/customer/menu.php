<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../config/config.php';
include_once '../../template/header.php';

// Security: Only admin/manager can modify menu
$can_modify = is_logged_in() && (has_role('admin') || has_role('manager'));

// Handle Create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add']) && $can_modify) {
    $name = $_POST['name'];
    $desc = $_POST['description'];
    $price = $_POST['price'];
    $lang = $_POST['lang'];
    $available = isset($_POST['available']) ? 1 : 0;
    $image = $_FILES['image']['name'] ?? '';
    $video = $_FILES['video']['name'] ?? '';
    // Upload files (simple version)
    if ($image) move_uploaded_file($_FILES['image']['tmp_name'], "../../assets/img/$image");
    if ($video) move_uploaded_file($_FILES['video']['tmp_name'], "../../assets/img/$video");
    $stmt = $conn->prepare("INSERT INTO menu (name, description, price, image, video, lang, available) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdsssi", $name, $desc, $price, $image, $video, $lang, $available);
    $stmt->execute();
    echo "<script>playAlert();</script>"; // Sound alert on add
}

// Handle Delete
if (isset($_GET['delete']) && $can_modify) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM menu WHERE id=$id");
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit']) && $can_modify) {
    $id = intval($_POST['id']);
    $name = $_POST['name'];
    $desc = $_POST['description'];
    $price = $_POST['price'];
    $lang = $_POST['lang'];
    $available = isset($_POST['available']) ? 1 : 0;
    $image = $_FILES['image']['name'] ?? $_POST['old_image'];
    $video = $_FILES['video']['name'] ?? $_POST['old_video'];
    if ($_FILES['image']['name']) move_uploaded_file($_FILES['image']['tmp_name'], "../../assets/img/$image");
    if ($_FILES['video']['name']) move_uploaded_file($_FILES['video']['tmp_name'], "../../assets/img/$video");
    $stmt = $conn->prepare("UPDATE menu SET name=?, description=?, price=?, image=?, video=?, lang=?, available=? WHERE id=?");
    $stmt->bind_param("ssdsssii", $name, $desc, $price, $image, $video, $lang, $available, $id);
    $stmt->execute();
}

// Fetch menu items
$lang = $_GET['lang'] ?? SITE_LANG;
$result = $conn->query("SELECT * FROM menu WHERE lang='$lang'");
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-utensils me-2"></i>Menu Management</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addMenuModal">
                <i class="fas fa-plus me-1"></i>Add Item
            </button>
        </div>
    </div>
</div>

<!-- Language Tabs -->
<ul class="nav nav-pills mb-3" id="langTabs">
    <li class="nav-item">
        <a class="nav-link <?= ($lang == 'en') ? 'active' : '' ?>" href="?lang=en">
            <i class="fas fa-flag-usa me-1"></i>English
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= ($lang == 'hi') ? 'active' : '' ?>" href="?lang=hi">
            <i class="fas fa-flag me-1"></i>Hindi
        </a>
    </li>
</ul>

<!-- Menu Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Menu Items</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="menuTable" class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Image</th><th>Name</th><th>Description</th><th>Price</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?php if ($row['image']): ?>
                                <img src="../../assets/img/<?= $row['image'] ?>" class="rounded" width="50" height="50">
                            <?php else: ?>
                                <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:50px;height:50px;">
                                    <i class="fas fa-image text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                        <td><?= htmlspecialchars(substr($row['description'], 0, 50)) ?>...</td>
                        <td><span class="badge bg-success fs-6">₹<?= number_format($row['price'], 2) ?></span></td>
                        <td>
                            <?php if ($row['available']): ?>
                                <span class="badge bg-success">Available</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Unavailable</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <button class="btn btn-sm btn-outline-info qr-btn" data-id="<?= $row['id'] ?>" data-name="<?= htmlspecialchars($row['name']) ?>">
                                    <i class="fas fa-qrcode"></i>
                                </button>
                                <?php if ($can_modify): ?>
                                <a href="?edit=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger delete-confirm">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- QR Modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">QR Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="qrContainer"></div>
                <p class="mt-3 text-muted">Scan to view item details</p>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#menuTable').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[1, 'asc']]
    });

    // Tabs for language selection
    $('#langTabs .nav-link').click(function(e){
        e.preventDefault();
        var lang = $(this).data('lang');
        window.location.search = '?lang=' + lang;
    });

    // Sound alert function
    window.playAlert = function() {
        var audio = new Audio('../../assets/js/alert.mp3');
        audio.play();
    };

    // QR code button click
    $('.qr-btn').click(function(){
        var id = $(this).data('id');
        var name = $(this).data('name');
        $('#qrContainer').html('');
        var qr = new QRCode(document.getElementById("qrContainer"), {
            text: window.location.origin + '/modules/customer/menu.php?item=' + id,
            width: 200,
            height: 200
        });
        $('#qrModal').modal('show');
    });
});
</script>

<?php include_once '../../template/footer.php'; ?>