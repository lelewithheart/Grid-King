<?php
/**
 * Admin - Manage Drivers (Add, Edit, Delete, Assign Team)
 */

require_once '../config/config.php';

requireAdmin();

$page_title = 'Manage Drivers';

// Get database connection
$db = new Database();
$conn = $db->getConnection();

// Fetch teams for dropdown
$teamStmt = $conn->prepare("SELECT id, name FROM teams ORDER BY name ASC");
$teamStmt->execute();
$teams = $teamStmt->fetchAll();

// Fetch users for assigning drivers
$userStmt = $conn->prepare("SELECT id, username FROM users WHERE role = 'driver' ORDER BY username ASC");
$userStmt->execute();
$users = $userStmt->fetchAll();

// Handle Add Driver
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_driver'])) {
    $user_id = (int)$_POST['user_id'];
    $team_id = $_POST['team_id'] !== '' ? (int)$_POST['team_id'] : null;
    $driver_number = trim($_POST['driver_number']);
    $platform = $_POST['platform'];
    $country = trim($_POST['country']);
    $livery_image = trim($_POST['livery_image']);
    $bio = trim($_POST['bio']);

    if ($user_id && $driver_number && $platform) {
        $insert = $conn->prepare("INSERT INTO drivers (user_id, team_id, driver_number, platform, country, livery_image, bio) VALUES (:user_id, :team_id, :driver_number, :platform, :country, :livery_image, :bio)");
        $insert->bindParam(':user_id', $user_id);
        $insert->bindParam(':team_id', $team_id);
        $insert->bindParam(':driver_number', $driver_number);
        $insert->bindParam(':platform', $platform);
        $insert->bindParam(':country', $country);
        $insert->bindParam(':livery_image', $livery_image);
        $insert->bindParam(':bio', $bio);
        $insert->execute();
        header("Location: drivers.php");
        exit;
    }
}

// Handle Delete Driver
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $delete = $conn->prepare("DELETE FROM drivers WHERE id = :id");
    $delete->bindParam(':id', $delete_id);
    $delete->execute();
    header("Location: drivers.php");
    exit;
}

// Handle Edit Driver
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_driver'])) {
    $edit_id = (int)$_POST['edit_id'];
    $team_id = $_POST['edit_team_id'] !== '' ? (int)$_POST['edit_team_id'] : null;
    $driver_number = trim($_POST['edit_driver_number']);
    $platform = $_POST['edit_platform'];
    $country = trim($_POST['edit_country']);
    $livery_image = trim($_POST['edit_livery_image']);
    $bio = trim($_POST['edit_bio']);

    if ($driver_number && $platform) {
        $update = $conn->prepare("UPDATE drivers SET team_id = :team_id, driver_number = :driver_number, platform = :platform, country = :country, livery_image = :livery_image, bio = :bio WHERE id = :id");
        $update->bindParam(':team_id', $team_id);
        $update->bindParam(':driver_number', $driver_number);
        $update->bindParam(':platform', $platform);
        $update->bindParam(':country', $country);
        $update->bindParam(':livery_image', $livery_image);
        $update->bindParam(':bio', $bio);
        $update->bindParam(':id', $edit_id);
        $update->execute();
        header("Location: drivers.php");
        exit;
    }
}

// Fetch all drivers
$driverQuery = "SELECT d.*, u.username, t.name AS team_name
                FROM drivers d
                JOIN users u ON d.user_id = u.id
                LEFT JOIN teams t ON d.team_id = t.id
                ORDER BY d.driver_number ASC";
$driverStmt = $conn->prepare($driverQuery);
$driverStmt->execute();
$drivers = $driverStmt->fetchAll();

// For edit modal
$editDriver = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $editStmt = $conn->prepare("SELECT * FROM drivers WHERE id = :id");
    $editStmt->bindParam(':id', $edit_id);
    $editStmt->execute();
    $editDriver = $editStmt->fetch();
}

include '../includes/header.php';
?>

<div class="container my-5">
    <h1 class="mb-4"><i class="bi bi-person-badge me-2"></i>Manage Drivers</h1>
    <div class="card card-racing shadow-sm mb-4">
        <div class="card-header bg-dark text-white">
            <strong>Add Driver</strong>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-md-2">
                    <select name="user_id" class="form-select" required>
                        <option value="">Select User</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="team_id" class="form-select">
                        <option value="">No Team</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <input type="number" name="driver_number" class="form-control" placeholder="Number" required>
                </div>
                <div class="col-md-2">
                    <select name="platform" class="form-select" required>
                        <option value="">Platform</option>
                        <option value="PC">PC</option>
                        <option value="Xbox">Xbox</option>
                        <option value="PlayStation">PlayStation</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <input type="text" name="country" class="form-control" maxlength="3" placeholder="Country">
                </div>
                <div class="col-md-2">
                    <input type="text" name="livery_image" class="form-control" placeholder="Livery Image URL">
                </div>
                <div class="col-md-2">
                    <input type="text" name="bio" class="form-control" placeholder="Bio">
                </div>
                <div class="col-md-12 mt-2">
                    <button type="submit" name="add_driver" class="btn btn-success">Add Driver</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($editDriver): ?>
    <div class="card card-racing shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <strong>Edit Driver</strong>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="edit_id" value="<?php echo htmlspecialchars($editDriver['id']); ?>">
                <div class="col-md-2">
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($editDriver['user_id']); ?>" disabled>
                </div>
                <div class="col-md-2">
                    <select name="edit_team_id" class="form-select">
                        <option value="">No Team</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?php echo $team['id']; ?>" <?php if ($editDriver['team_id'] == $team['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($team['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <input type="number" name="edit_driver_number" class="form-control" value="<?php echo htmlspecialchars($editDriver['driver_number']); ?>" required>
                </div>
                <div class="col-md-2">
                    <select name="edit_platform" class="form-select" required>
                        <option value="PC" <?php if ($editDriver['platform'] == 'PC') echo 'selected'; ?>>PC</option>
                        <option value="Xbox" <?php if ($editDriver['platform'] == 'Xbox') echo 'selected'; ?>>Xbox</option>
                        <option value="PlayStation" <?php if ($editDriver['platform'] == 'PlayStation') echo 'selected'; ?>>PlayStation</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <input type="text" name="edit_country" class="form-control" maxlength="3" value="<?php echo htmlspecialchars($editDriver['country']); ?>">
                </div>
                <div class="col-md-2">
                    <input type="text" name="edit_livery_image" class="form-control" value="<?php echo htmlspecialchars($editDriver['livery_image']); ?>">
                </div>
                <div class="col-md-2">
                    <input type="text" name="edit_bio" class="form-control" value="<?php echo htmlspecialchars($editDriver['bio']); ?>">
                </div>
                <div class="col-md-12 mt-2">
                    <button type="submit" name="edit_driver" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="card card-racing shadow-sm">
        <div class="card-header bg-dark text-white">
            <strong>Driver List</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Number</th>
                            <th>Platform</th>
                            <th>Country</th>
                            <th>Team</th>
                            <th>Livery</th>
                            <th>Bio</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($drivers as $driver): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)($driver['id'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($driver['username'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($driver['driver_number'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($driver['platform'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($driver['country'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($driver['team_name'] ?? '')); ?></td>
                            <td>
                                <?php if (!empty($driver['livery_image'])): ?>
                                    <img src="<?php echo htmlspecialchars((string)$driver['livery_image']); ?>" alt="Livery" style="height:32px;">
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars((string)($driver['bio'] ?? '')); ?></td>
                            <td>
                                <a href="drivers.php?edit=<?php echo (int)$driver['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                <a href="drivers.php?delete=<?php echo (int)$driver['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this driver?');">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($drivers)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">No drivers found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>