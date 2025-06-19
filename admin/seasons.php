<?php
/**
 * Admin - Manage Seasons (Add, Edit, Delete)
 */

require_once '../config/config.php';

requireAdmin();

$page_title = 'Manage Seasons';

// Get database connection
$db = new Database();
$conn = $db->getConnection();

// Handle Add Season
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_season'])) {
    $name = trim($_POST['name']);
    $year = (int)$_POST['year'];
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($name !== '' && $year > 0) {
        $insert = $conn->prepare("INSERT INTO seasons (name, year, description, is_active) VALUES (:name, :year, :description, :is_active)");
        $insert->bindParam(':name', $name);
        $insert->bindParam(':year', $year);
        $insert->bindParam(':description', $description);
        $insert->bindParam(':is_active', $is_active);
        $insert->execute();
        header("Location: seasons.php");
        exit;
    }
}

// Handle Delete Season
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $delete = $conn->prepare("DELETE FROM seasons WHERE id = :id");
    $delete->bindParam(':id', $delete_id);
    $delete->execute();
    header("Location: seasons.php");
    exit;
}

// Handle Edit Season
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_season'])) {
    $edit_id = (int)$_POST['edit_id'];
    $name = trim($_POST['edit_name']);
    $year = (int)$_POST['edit_year'];
    $description = trim($_POST['edit_description']);
    $is_active = isset($_POST['edit_is_active']) ? 1 : 0;

    if ($name !== '' && $year > 0) {
        $update = $conn->prepare("UPDATE seasons SET name = :name, year = :year, description = :description, is_active = :is_active WHERE id = :id");
        $update->bindParam(':name', $name);
        $update->bindParam(':year', $year);
        $update->bindParam(':description', $description);
        $update->bindParam(':is_active', $is_active);
        $update->bindParam(':id', $edit_id);
        $update->execute();
        header("Location: seasons.php");
        exit;
    }
}

// Fetch all seasons
$seasonQuery = "SELECT id, name, year, description, is_active, created_at, updated_at FROM seasons ORDER BY created_at DESC";
$seasonStmt = $conn->prepare($seasonQuery);
$seasonStmt->execute();
$seasons = $seasonStmt->fetchAll();

// For edit modal
$editSeason = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $editStmt = $conn->prepare("SELECT * FROM seasons WHERE id = :id");
    $editStmt->bindParam(':id', $edit_id);
    $editStmt->execute();
    $editSeason = $editStmt->fetch();
}

include '../includes/header.php';
?>

<div class="container my-5">
    <h1 class="mb-4"><i class="bi bi-calendar-range me-2"></i>Manage Seasons</h1>
    <div class="card card-racing shadow-sm mb-4">
        <div class="card-header bg-dark text-white">
            <strong>Add Season</strong>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="name" class="form-control" placeholder="Season Name" required>
                </div>
                <div class="col-md-2">
                    <input type="number" name="year" class="form-control" placeholder="Year" required>
                </div>
                <div class="col-md-4">
                    <input type="text" name="description" class="form-control" placeholder="Description">
                </div>
                <div class="col-md-1 d-flex align-items-center">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" name="add_season" class="btn btn-success w-100">Add Season</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($editSeason): ?>
    <div class="card card-racing shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <strong>Edit Season</strong>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="edit_id" value="<?php echo (int)$editSeason['id']; ?>">
                <div class="col-md-3">
                    <input type="text" name="edit_name" class="form-control" value="<?php echo htmlspecialchars((string)$editSeason['name'] ?? ''); ?>" required>
                </div>
                <div class="col-md-2">
                    <input type="number" name="edit_year" class="form-control" value="<?php echo htmlspecialchars((string)$editSeason['year'] ?? ''); ?>" required>
                </div>
                <div class="col-md-4">
                    <input type="text" name="edit_description" class="form-control" value="<?php echo htmlspecialchars((string)$editSeason['description'] ?? ''); ?>">
                </div>
                <div class="col-md-1 d-flex align-items-center">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="edit_is_active" id="edit_is_active" <?php if (!empty($editSeason['is_active'])) echo 'checked'; ?>>
                        <label class="form-check-label" for="edit_is_active">Active</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" name="edit_season" class="btn btn-primary w-100">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="card card-racing shadow-sm">
        <div class="card-header bg-dark text-white">
            <strong>Season List</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Year</th>
                            <th>Description</th>
                            <th>Active</th>
                            <th>Created</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($seasons as $season): ?>
                        <tr>
                            <td><?php echo (int)$season['id']; ?></td>
                            <td><?php echo htmlspecialchars((string)$season['name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars((string)$season['year'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars((string)$season['description'] ?? ''); ?></td>
                            <td>
                                <?php echo !empty($season['is_active']) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'; ?>
                            </td>
                            <td><?php echo htmlspecialchars((string)$season['created_at'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars((string)$season['updated_at'] ?? ''); ?></td>
                            <td>
                                <a href="seasons.php?edit=<?php echo (int)$season['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                <a href="seasons.php?delete=<?php echo (int)$season['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this season?');">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($seasons)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">No seasons found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>