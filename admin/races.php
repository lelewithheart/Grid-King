<?php
/**
 * Admin Race Management - Create, Edit, Delete Races
 */

require_once '../config/config.php';

requireAdmin();

$page_title = 'Race Management';

// Get database connection
$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Handle race creation/editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_race'])) {
    $race_id = !empty($_POST['race_id']) ? (int)$_POST['race_id'] : null;
    $season_id = (int)$_POST['season_id'];
    $name = sanitizeInput($_POST['name']);
    $track = sanitizeInput($_POST['track']);
    $race_date = $_POST['race_date'];
    $format = sanitizeInput($_POST['format']);
    $laps = !empty($_POST['laps']) ? (int)$_POST['laps'] : null;
    $status = sanitizeInput($_POST['status']);
    $track_image = sanitizeInput($_POST['track_image']);
    
    if (empty($name) || empty($track) || empty($race_date) || empty($season_id)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            if ($race_id) {
                // Update existing race
                $query = "
                    UPDATE races 
                    SET season_id = :season_id, name = :name, track = :track, race_date = :race_date, 
                        format = :format, laps = :laps, status = :status, track_image = :track_image
                    WHERE id = :race_id
                ";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':race_id', $race_id);
                $action = 'updated';
            } else {
                // Create new race
                $query = "
                    INSERT INTO races (season_id, name, track, race_date, format, laps, status, track_image)
                    VALUES (:season_id, :name, :track, :race_date, :format, :laps, :status, :track_image)
                ";
                $stmt = $conn->prepare($query);
                $action = 'created';
            }
            
            $stmt->bindParam(':season_id', $season_id);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':track', $track);
            $stmt->bindParam(':race_date', $race_date);
            $stmt->bindParam(':format', $format);
            $stmt->bindParam(':laps', $laps);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':track_image', $track_image);
            
            $stmt->execute();
            $success = "Race {$action} successfully!";
            
        } catch (Exception $e) {
            $error = 'Error saving race: ' . $e->getMessage();
        }
    }
}

// Handle race deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_race'])) {
    $race_id = (int)$_POST['race_id'];
    
    try {
        // Check if race has results
        $checkQuery = "SELECT COUNT(*) as count FROM race_results WHERE race_id = :race_id";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bindParam(':race_id', $race_id);
        $checkStmt->execute();
        $hasResults = $checkStmt->fetch()['count'] > 0;
        
        if ($hasResults) {
            $error = 'Cannot delete race with existing results. Please remove results first.';
        } else {
            $deleteQuery = "DELETE FROM races WHERE id = :race_id";
            $deleteStmt = $conn->prepare($deleteQuery);
            $deleteStmt->bindParam(':race_id', $race_id);
            $deleteStmt->execute();
            
            $success = 'Race deleted successfully!';
        }
    } catch (Exception $e) {
        $error = 'Error deleting race: ' . $e->getMessage();
    }
}

// Get all races with season and result info
$racesQuery = "
    SELECT 
        r.*,
        s.name as season_name,
        COUNT(rr.id) as result_count,
        MAX(CASE WHEN rr.position = 1 THEN u.username END) as winner
    FROM races r
    JOIN seasons s ON r.season_id = s.id
    LEFT JOIN race_results rr ON r.id = rr.race_id
    LEFT JOIN drivers d ON rr.driver_id = d.id
    LEFT JOIN users u ON d.user_id = u.id
    GROUP BY r.id
    ORDER BY r.race_date DESC
";
$racesStmt = $conn->prepare($racesQuery);
$racesStmt->execute();
$races = $racesStmt->fetchAll();

// Get all seasons for dropdown
$seasonsQuery = "SELECT * FROM seasons ORDER BY year DESC";
$seasonsStmt = $conn->prepare($seasonsQuery);
$seasonsStmt->execute();
$seasons = $seasonsStmt->fetchAll();

// Get race for editing if specified
$editRace = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $editQuery = "SELECT * FROM races WHERE id = :id";
    $editStmt = $conn->prepare($editQuery);
    $editStmt->bindParam(':id', $edit_id);
    $editStmt->execute();
    $editRace = $editStmt->fetch();
}

include '../includes/header.php';
?>

<div class="racing-header">
    <div class="container">
        <h1 class="display-5 fw-bold mb-3">
            <i class="bi bi-calendar-plus me-3"></i>Race Management
        </h1>
        <p class="lead mb-0">Create, edit, and manage championship races</p>
    </div>
</div>

<div class="container my-5">
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Race Form -->
        <div class="col-lg-4">
            <div class="card card-racing shadow-sm sticky-top" style="top: 1rem;">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-<?php echo $editRace ? 'pencil' : 'plus-circle'; ?> me-2"></i>
                        <?php echo $editRace ? 'Edit Race' : 'Create New Race'; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <?php if ($editRace): ?>
                            <input type="hidden" name="race_id" value="<?php echo $editRace['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="season_id" class="form-label">Season <span class="text-danger">*</span></label>
                            <select class="form-select" id="season_id" name="season_id" required>
                                <option value="">Select Season</option>
                                <?php foreach ($seasons as $season): ?>
                                    <option value="<?php echo $season['id']; ?>" 
                                            <?php echo ($editRace && $editRace['season_id'] == $season['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($season['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">Race Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo $editRace ? htmlspecialchars($editRace['name']) : ''; ?>" 
                                   placeholder="e.g., Season Opener" required>
                        </div>

                        <div class="mb-3">
                            <label for="track" class="form-label">Track <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="track" name="track" 
                                   value="<?php echo $editRace ? htmlspecialchars($editRace['track']) : ''; ?>" 
                                   placeholder="e.g., Silverstone" required>
                        </div>

                        <div class="mb-3">
                            <label for="race_date" class="form-label">Race Date & Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="race_date" name="race_date" 
                                   value="<?php echo $editRace ? date('Y-m-d\TH:i', strtotime($editRace['race_date'])) : ''; ?>" required>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="mb-3">
                                    <label for="format" class="form-label">Format</label>
                                    <select class="form-select" id="format" name="format">
                                        <option value="Feature" <?php echo ($editRace && $editRace['format'] === 'Feature') ? 'selected' : ''; ?>>Feature</option>
                                        <option value="Sprint" <?php echo ($editRace && $editRace['format'] === 'Sprint') ? 'selected' : ''; ?>>Sprint</option>
                                        <option value="Endurance" <?php echo ($editRace && $editRace['format'] === 'Endurance') ? 'selected' : ''; ?>>Endurance</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label for="laps" class="form-label">Laps</label>
                                    <input type="number" class="form-control" id="laps" name="laps" 
                                           value="<?php echo $editRace ? $editRace['laps'] : ''; ?>" 
                                           min="1" placeholder="52">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="Scheduled" <?php echo ($editRace && $editRace['status'] === 'Scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                                <option value="In Progress" <?php echo ($editRace && $editRace['status'] === 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Completed" <?php echo ($editRace && $editRace['status'] === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                <option value="Cancelled" <?php echo ($editRace && $editRace['status'] === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="track_image" class="form-label">Track Image URL</label>
                            <input type="url" class="form-control" id="track_image" name="track_image" 
                                value="<?php echo $editRace ? htmlspecialchars($editRace['track_image'] ?? '') : ''; ?>" 
                                placeholder="https://example.com/track.jpg">
                            <div class="form-text">Optional: URL to track image</div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="save_race" class="btn btn-racing">
                                <i class="bi bi-save me-2"></i><?php echo $editRace ? 'Update Race' : 'Create Race'; ?>
                            </button>
                            <?php if ($editRace): ?>
                                <a href="races.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle me-2"></i>Cancel Edit
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Races List -->
        <div class="col-lg-8">
            <div class="card card-racing shadow-sm">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="bi bi-list me-2"></i>All Races</h4>
                    <span class="badge bg-primary"><?php echo count($races); ?> races</span>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($races)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Race</th>
                                        <th>Track</th>
                                        <th class="text-center">Date</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Results</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($races as $race): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($race['name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($race['season_name']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($race['track']); ?>
                                                <div class="small text-muted"><?php echo htmlspecialchars($race['format']); ?></div>
                                            </td>
                                            <td class="text-center">
                                                <div><?php echo date('M j, Y', strtotime($race['race_date'])); ?></div>
                                                <small class="text-muted"><?php echo date('g:i A', strtotime($race['race_date'])); ?></small>
                                            </td>
                                            <td class="text-center">
                                                <?php
                                                $statusClass = match($race['status']) {
                                                    'Completed' => 'success',
                                                    'In Progress' => 'warning',
                                                    'Scheduled' => 'primary',
                                                    'Cancelled' => 'danger',
                                                    default => 'secondary'
                                                };
                                                ?>
                                                <span class="badge bg-<?php echo $statusClass; ?>">
                                                    <?php echo htmlspecialchars($race['status']); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($race['result_count'] > 0): ?>
                                                    <span class="badge bg-success"><?php echo $race['result_count']; ?> drivers</span>
                                                    <?php if ($race['winner']): ?>
                                                        <div class="small text-muted">Won by <?php echo htmlspecialchars($race['winner']); ?></div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">No results</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    <a href="?edit=<?php echo $race['id']; ?>" class="btn btn-outline-primary btn-sm" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="results.php?race_id=<?php echo $race['id']; ?>" class="btn btn-outline-success btn-sm" title="Results">
                                                        <i class="bi bi-trophy"></i>
                                                    </a>
                                                    <a href="../race.php?id=<?php echo $race['id']; ?>" class="btn btn-outline-info btn-sm" title="View">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if ($race['result_count'] == 0): ?>
                                                        <button class="btn btn-outline-danger btn-sm" title="Delete" 
                                                                onclick="deleteRace(<?php echo $race['id']; ?>, '<?php echo htmlspecialchars($race['name']); ?>')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-calendar-x display-1 text-muted"></i>
                            <h5 class="mt-3">No Races Created</h5>
                            <p class="text-muted">Create your first race using the form on the left.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card card-racing shadow-sm">
                        <div class="card-body text-center">
                            <i class="bi bi-trophy display-4 text-primary mb-3"></i>
                            <h5>Manage Results</h5>
                            <p class="text-muted mb-3">Add race results and update standings</p>
                            <a href="results.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-1"></i>Add Results
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card card-racing shadow-sm">
                        <div class="card-body text-center">
                            <i class="bi bi-calendar4-range display-4 text-success mb-3"></i>
                            <h5>Season Management</h5>
                            <p class="text-muted mb-3">Create and manage championship seasons</p>
                            <a href="seasons.php" class="btn btn-success">
                                <i class="bi bi-gear me-1"></i>Manage Seasons
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the race <strong id="deleteRaceName"></strong>?</p>
                <p class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="race_id" id="deleteRaceId">
                    <button type="submit" name="delete_race" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Delete Race
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function deleteRace(raceId, raceName) {
    document.getElementById('deleteRaceId').value = raceId;
    document.getElementById('deleteRaceName').textContent = raceName;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Auto-fill track suggestions
document.getElementById('track').addEventListener('input', function() {
    // You could add track suggestions here
    const commonTracks = ['Silverstone', 'Monza', 'Spa-Francorchamps', 'Monaco', 'Suzuka', 'Interlagos', 'Nürburgring'];
    // Implementation for autocomplete could go here
});
</script>

<?php include '../includes/footer.php'; ?>