<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
include_once 'template/header.php';

// Handle recipe operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_recipe'])) {
        $menu_id = $_POST['menu_id'];
        $ingredients = $_POST['ingredients'];
        $cooking_time = $_POST['cooking_time'];
        $prep_time = $_POST['prep_time'];
        $difficulty_level = $_POST['difficulty_level'];
        $instructions = $_POST['instructions'];
        $nutritional_info = $_POST['nutritional_info'];
        
        $stmt = $conn->prepare("INSERT INTO recipes (menu_id, ingredients, cooking_time, prep_time, difficulty_level, instructions, nutritional_info) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isiisss", $menu_id, $ingredients, $cooking_time, $prep_time, $difficulty_level, $instructions, $nutritional_info);
        $stmt->execute();
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$difficulty_filter = $_GET['difficulty'] ?? '';
$category_filter = $_GET['category'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = '';

if ($search) {
    $where_conditions[] = "(m.name LIKE ? OR r.ingredients LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $param_types .= 'ss';
}

if ($difficulty_filter) {
    $where_conditions[] = "r.difficulty_level = ?";
    $params[] = $difficulty_filter;
    $param_types .= 's';
}

if ($category_filter) {
    $where_conditions[] = "m.category = ?";
    $params[] = $category_filter;
    $param_types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

$recipes_query = "SELECT r.*, m.name, m.category, m.price, m.image FROM recipes r JOIN menu m ON r.menu_id = m.id $where_clause ORDER BY m.name ASC";
if (!empty($params)) {
    $stmt = $conn->prepare($recipes_query);
    if ($stmt) {
        $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $recipes = $stmt->get_result();
    } else {
        $recipes = false;
    }
} else {
    $recipes = $conn->query($recipes_query);
}

// Get statistics and menu items
$stats_result = $conn->query("
    SELECT 
        COUNT(*) as total_recipes,
        AVG(cooking_time) as avg_cooking_time,
        COUNT(CASE WHEN difficulty_level = 'easy' THEN 1 END) as easy_recipes,
        COUNT(CASE WHEN difficulty_level = 'medium' THEN 1 END) as medium_recipes,
        COUNT(CASE WHEN difficulty_level = 'hard' THEN 1 END) as hard_recipes
    FROM recipes r JOIN menu m ON r.menu_id = m.id $where_clause
");
$stats = $stats_result ? $stats_result->fetch_assoc() : [
    'total_recipes' => 0,
    'avg_cooking_time' => 0,
    'easy_recipes' => 0,
    'medium_recipes' => 0,
    'hard_recipes' => 0
];

$menu_items = $conn->query("SELECT id, name, category FROM menu ORDER BY name");
$categories = $conn->query("SELECT DISTINCT category FROM menu ORDER BY category");
?>

<style>
.recipe-stats { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px; padding: 25px; margin-bottom: 30px; }
.recipe-card { border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 20px; transition: all 0.3s ease; }
.recipe-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
.recipe-header { background: linear-gradient(135deg, #2d3748, #4a5568); color: white; padding: 20px; }
.filter-card { background: #f8f9fa; border-radius: 15px; padding: 20px; margin-bottom: 20px; border: none; }
.recipe-image { width: 100px; height: 100px; object-fit: cover; border-radius: 12px; }
.difficulty-badge { padding: 5px 12px; border-radius: 15px; font-size: 0.8rem; font-weight: 600; }
.easy { background: #c6f6d5; color: #22543d; }
.medium { background: #fed7d7; color: #742a2a; }
.hard { background: #fbb6ce; color: #97266d; }
.time-badge { background: #4a5568; color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; }
.ingredient-list { background: #f7fafc; padding: 15px; border-radius: 12px; margin: 10px 0; }
.instruction-step { background: #fff; border-left: 4px solid #667eea; padding: 15px; margin-bottom: 10px; border-radius: 8px; }
.nutrition-info { background: #e6fffa; padding: 12px; border-radius: 12px; font-size: 0.9rem; }
.recipe-details { padding: 20px; }
.cooking-time { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; }
.prep-time { color: #ed8936; }
.cook-time { color: #38a169; }
</style>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold text-dark"><i class="fas fa-book-open me-2 text-primary"></i>Recipe Management</h2>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-success" onclick="exportRecipes()">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                    <button class="btn btn-success" onclick="importRecipes()">
                        <i class="fas fa-upload me-2"></i>Import
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRecipeModal">
                        <i class="fas fa-plus me-2"></i>Add Recipe
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="recipe-stats text-center">
                        <div class="fs-1 mb-2"><i class="fas fa-utensils"></i></div>
                        <h3 class="mb-0"><?= $stats['total_recipes'] ?></h3>
                        <p class="mb-0 opacity-75">Total Recipes</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="recipe-stats text-center">
                        <div class="fs-1 mb-2"><i class="fas fa-clock"></i></div>
                        <h3 class="mb-0"><?= round($stats['avg_cooking_time']) ?> min</h3>
                        <p class="mb-0 opacity-75">Avg Cooking Time</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="recipe-stats text-center">
                        <div class="fs-1 mb-2"><i class="fas fa-thumbs-up"></i></div>
                        <h3 class="mb-0 text-success"><?= $stats['easy_recipes'] ?></h3>
                        <p class="mb-0 opacity-75">Easy Recipes</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="recipe-stats text-center">
                        <div class="fs-1 mb-2"><i class="fas fa-fire"></i></div>
                        <h3 class="mb-0 text-danger"><?= $stats['hard_recipes'] ?></h3>
                        <p class="mb-0 opacity-75">Advanced Recipes</p>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="card filter-card">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Search Recipes</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by name or ingredients..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Difficulty</label>
                        <select name="difficulty" class="form-select">
                            <option value="">All Levels</option>
                            <option value="easy" <?= $difficulty_filter === 'easy' ? 'selected' : '' ?>>Easy</option>
                            <option value="medium" <?= $difficulty_filter === 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="hard" <?= $difficulty_filter === 'hard' ? 'selected' : '' ?>>Hard</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Category</label>
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php 
                            $categories->data_seek(0);
                            while($cat = $categories->fetch_assoc()): 
                            ?>
                                <option value="<?= $cat['category'] ?>" <?= $category_filter === $cat['category'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2"><i class="fas fa-search me-2"></i>Search</button>
                        <a href="recipe.php" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Recipes Grid -->
            <div class="row">
                <?php if ($recipes && $recipes->num_rows > 0): ?>
                    <?php while($row = $recipes->fetch_assoc()): ?>
                        <div class="col-lg-6 col-xl-4">
                            <div class="recipe-card">
                                <div class="recipe-header">
                                    <div class="d-flex align-items-center">
                                        <?php if ($row['image']): ?>
                                            <img src="assets/img/<?= $row['image'] ?>" class="recipe-image me-3" alt="<?= htmlspecialchars($row['name']) ?>">
                                        <?php else: ?>
                                            <div class="recipe-image me-3 d-flex align-items-center justify-content-center bg-secondary text-white">
                                                <i class="fas fa-utensils fs-3"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <h5 class="mb-1"><?= htmlspecialchars($row['name']) ?></h5>
                                            <div class="d-flex gap-2 align-items-center">
                                                <span class="badge bg-light text-dark"><?= htmlspecialchars($row['category']) ?></span>
                                                <span class="difficulty-badge <?= $row['difficulty_level'] ?>">
                                                    <?= ucfirst($row['difficulty_level']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="recipe-details">
                                    <div class="cooking-time">
                                        <span class="time-badge prep-time">
                                            <i class="fas fa-hourglass-start me-1"></i>
                                            Prep: <?= $row['prep_time'] ?>min
                                        </span>
                                        <span class="time-badge cook-time">
                                            <i class="fas fa-fire me-1"></i>
                                            Cook: <?= $row['cooking_time'] ?>min
                                        </span>
                                    </div>
                                    
                                    <div class="ingredient-list">
                                        <h6 class="fw-bold mb-2"><i class="fas fa-list me-2"></i>Ingredients:</h6>
                                        <p class="mb-0 small"><?= nl2br(htmlspecialchars($row['ingredients'])) ?></p>
                                    </div>
                                    
                                    <?php if ($row['instructions']): ?>
                                        <div class="instruction-step">
                                            <h6 class="fw-bold mb-2"><i class="fas fa-clipboard-list me-2"></i>Instructions:</h6>
                                            <p class="mb-0 small"><?= nl2br(htmlspecialchars(substr($row['instructions'], 0, 150))) ?>...</p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($row['nutritional_info']): ?>
                                        <div class="nutrition-info">
                                            <h6 class="fw-bold mb-2"><i class="fas fa-heartbeat me-2"></i>Nutrition Info:</h6>
                                            <p class="mb-0 small"><?= htmlspecialchars($row['nutritional_info']) ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-3 d-flex justify-content-between align-items-center">
                                        <span class="fw-bold text-success fs-5">₹<?= number_format($row['price'], 2) ?></span>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewFullRecipe(<?= $row['id'] ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn btn-sm btn-outline-success" onclick="editRecipe(<?= $row['id'] ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-outline-info" onclick="printRecipe(<?= $row['id'] ?>)">
                                                <i class="fas fa-print"></i> Print
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-book-open fs-1 text-muted mb-3"></i>
                            <h5 class="text-muted">No recipes found</h5>
                            <p class="text-muted">Try adjusting your search or add a new recipe.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Recipe Modal -->
<div class="modal fade" id="addRecipeModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Recipe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Menu Item *</label>
                                <select name="menu_id" class="form-select" required>
                                    <option value="">Select Menu Item</option>
                                    <?php while($item = $menu_items->fetch_assoc()): ?>
                                        <option value="<?= $item['id'] ?>">
                                            <?= htmlspecialchars($item['name']) ?> (<?= htmlspecialchars($item['category']) ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Difficulty Level *</label>
                                <select name="difficulty_level" class="form-select" required>
                                    <option value="easy">Easy</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="hard">Hard</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Prep Time (minutes) *</label>
                                <input type="number" name="prep_time" class="form-control" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Cooking Time (minutes) *</label>
                                <input type="number" name="cooking_time" class="form-control" min="1" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ingredients *</label>
                        <textarea name="ingredients" class="form-control" rows="4" required 
                                  placeholder="List all ingredients with quantities, one per line..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cooking Instructions *</label>
                        <textarea name="instructions" class="form-control" rows="6" required
                                  placeholder="Step-by-step cooking instructions..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nutritional Information</label>
                        <textarea name="nutritional_info" class="form-control" rows="2"
                                  placeholder="Calories, protein, carbs, etc. (optional)"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_recipe" class="btn btn-primary">Add Recipe</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function exportRecipes() {
    window.location.href = 'export_recipes.php' + window.location.search;
}

function importRecipes() {
    alert('Import recipes functionality coming soon');
}

function viewFullRecipe(id) {
    window.open('recipe_detail.php?id=' + id, '_blank');
}

function editRecipe(id) {
    // Implementation for editing recipe
    alert('Edit recipe functionality - ID: ' + id);
}

function printRecipe(id) {
    window.open('print_recipe.php?id=' + id, '_blank');
}
</script>

<?php include_once 'template/footer.php'; ?>