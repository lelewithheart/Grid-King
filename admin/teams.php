<?php
/**
 * Admin - Manage Teams (Add, Edit, Delete)
 */

require_once '../config/config.php';

requireAdmin();

$page_title = 'Manage Teams';

// Get database connection
$db = new Database();
$conn = $db->getConnection();

// Handle Add Team
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_team'])) {
    $name = trim($_POST['name']);
    $logo = trim($_POST['logo']);
    $created_by = $_SESSION['user_id'];

    if ($name !== '') {
        $insert = $conn->prepare("INSERT INTO teams (name, logo, created_by) VALUES (:name, :logo, :created_by)");
        $insert->bindParam(':name', $name);
        $insert->bindParam(':logo', $logo);
        $insert->bindParam(':created_by', $created_by);
        $insert->execute();
        header("Location: teams.php");
        exit;
    }
}

// Handle Delete Team
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $delete = $conn->prepare("DELETE FROM teams WHERE id = :id");
    $delete->bindParam(':id', $delete_id);
    $delete->execute();
    header("Location: teams.php");
    exit;
}

// Handle Edit Team
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_team'])) {
    $edit_id = (int)$_POST['edit_id'];
    $name = trim($_POST['edit_name']);
    $logo = trim($_POST['edit_logo']);

    if ($name !== '') {
        $update = $conn->prepare("UPDATE teams SET name = :name, logo = :logo WHERE id = :id");
        $update->bindParam(':name', $name);
        $update->bindParam(':logo', $logo);
        $update->bindParam(':id', $edit_id);
        $update->execute();
        header("Location: teams.php");
        exit;
    }
}

// Fetch all teams
$teamQuery = "SELECT t.id, t.name, t.logo, t.created_by, t.created_at, u.username AS creator
              FROM teams t
              LEFT JOIN users u ON t.created_by = u.id
              ORDER BY t.name ASC";
$teamStmt = $conn->prepare($teamQuery);
$teamStmt->execute();
$teams = $teamStmt->fetchAll();

// For edit modal
$editTeam = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $editStmt = $conn->prepare("SELECT * FROM teams WHERE id = :id");
    $editStmt->bindParam(':id', $edit_id);
    $editStmt->execute();
    $editTeam = $editStmt->fetch();
}

include '../includes/header.php';
?>

<div class="container my-5">
    <h1 class="mb-4"><i class="bi bi-people-fill me-2"></i>Manage Teams</h1>
    <div class="card card-racing shadow-sm mb-4">
        <div class="card-header bg-dark text-white">
            <strong>Add Team</strong>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-md-5">
                    <input type="text" name="name" class="form-control" placeholder="Team Name" required>
                </div>
                <div class="col-md-5">
                    <input type="text" name="logo" class="form-control" placeholder="Logo URL (optional)">
                </div>
                <div class="col-md-2">
                    <button type="submit" name="add_team" class="btn btn-success w-100">Add Team</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($editTeam): ?>
    <div class="card card-racing shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <strong>Edit Team</strong>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="edit_id" value="<?php echo htmlspecialchars($editTeam['id']); ?>">
                <div class="col-md-5">
                    <input type="text" name="edit_name" class="form-control" value="<?php echo htmlspecialchars($editTeam['name']); ?>" required>
                </div>
                <div class="col-md-5">
                    <input type="url" class="form-control" name="logo" value="<?php echo $editTeam ? htmlspecialchars($editTeam['logo'] ?? '') : ''; ?>" placeholder="https://example.com/logo.png">
                </div>
                <div class="col-md-2">
                    <button type="submit" name="edit_team" class="btn btn-primary w-100">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="card card-racing shadow-sm">
        <div class="card-header bg-dark text-white">
            <strong>Team List</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Logo</th>
                            <th>Created By</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teams as $team): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($team['id']); ?></td>
                            <td><?php echo htmlspecialchars($team['name']); ?></td>
                            <td>
                                <?php if ($team['logo']): ?>
                                    <img src="<?php echo htmlspecialchars($team['logo']); ?>" alt="Logo" style="height:32px;">
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $team['creator'] ? htmlspecialchars($team['creator']) : '<span class="text-muted">System</span>'; ?>
                            </td>
                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($team['created_at']))); ?></td>
                            <td>
                                <a href="teams.php?edit=<?php echo $team['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                <a href="teams.php?delete=<?php echo $team['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this team?');">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($teams)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">No teams found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>