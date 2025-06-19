<?php
/**
 * Admin Penalty Management - Apply and manage penalties
 */

require_once '../config/config.php';

requireAdmin();

$page_title = 'Penalty Management';

// Get database connection
$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Handle penalty creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_penalty'])) {
    $driver_id = (int)$_POST['driver_id'];
    $race_id = !empty($_POST['race_id']) ? (int)$_POST['race_id'] : null;
    $type = sanitizeInput($_POST['type']);
    $value = !empty($_POST['value']) ? (int)$_POST['value'] : null;
    $reason = sanitizeInput($_POST['reason']);
    
    if (empty($driver_id) || empty($type) || empty($reason)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $conn->beginTransaction();
            
            // Insert penalty
            $penaltyQuery = "
                INSERT INTO penalties (driver_id, race_id, type, value, reason, applied_by)
                VALUES (:driver_id, :race_id, :type, :value, :reason, :applied_by)
            ";
            $penaltyStmt = $conn->prepare($penaltyQuery);
            $penaltyStmt->bindParam(':driver_id', $driver_id);
            $penaltyStmt->bindParam(':race_id', $race_id);
            $penaltyStmt->bindParam(':type', $type);
            $penaltyStmt->bindParam(':value', $value);
            $penaltyStmt->bindParam(':reason', $reason);
            $penaltyStmt->bindParam(':applied_by', $_SESSION['user_id']);
            $penaltyStmt->execute();
            
            // If it's a points deduction for a specific race, update the race result
            if ($type === 'Points Deduction' && $race_id && $value > 0) {
                $updateQuery = "
                    UPDATE race_results 
                    SET points_penalty = COALESCE(points_penalty, 0) + :value,
                        points = GREATEST(0, points - :value)
                    WHERE race_id = :race_id AND driver_id = :driver_id
                ";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bindParam(':value', $value);
                $updateStmt->bindParam(':race_id', $race_id);
                $updateStmt->bindParam(':driver_id', $driver_id);
                $updateStmt->execute();
            }
            
            $conn->commit();
            $success = 'Penalty applied successfully!';
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = 'Error applying penalty: ' . $e->getMessage();
        }
    }
}

// Handle penalty removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_penalty'])) {
    $penalty_id = (int)$_POST['penalty_id'];
    
    try {
        $conn->beginTransaction();
        
        // Get penalty details first
        $penaltyQuery = "SELECT * FROM penalties WHERE id = :penalty_id";
        $penaltyStmt = $conn->prepare($penaltyQuery);
        $penaltyStmt->bindParam(':penalty_id', $penalty_id);
        $penaltyStmt->execute();
        $penalty = $penaltyStmt->fetch();
        
        if ($penalty) {
            // If it was a points deduction for a specific race, restore the points
            if ($penalty['type'] === 'Points Deduction' && $penalty['race_id'] && $penalty['value'] > 0) {
                $restoreQuery = "
                    UPDATE race_results 
                    SET points_penalty = GREATEST(0, COALESCE(points_penalty, 0) - :value),
                        points = points + :value
                    WHERE race_id = :race_id AND driver_id = :driver_id
                ";
                $restoreStmt = $conn->prepare($restoreQuery);
                $restoreStmt->bindParam(':value', $penalty['value']);
                $restoreStmt->bindParam(':race_id', $penalty['race_id']);
                $restoreStmt->bindParam(':driver_id', $penalty['driver_id']);
                $restoreStmt->execute();
            }
            
            // Delete penalty
            $deleteQuery = "DELETE FROM penalties WHERE id = :penalty_id";
            $deleteStmt = $conn->prepare($deleteQuery);
            $deleteStmt->bindParam(':penalty_id', $penalty_id);
            $deleteStmt->execute();
            
            $conn->commit();
            $success = 'Penalty removed successfully!';
        } else {
            $error = 'Penalty not found.';
        }
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error = 'Error removing penalty: ' . $e->getMessage();
    }
}

// Get all penalties with driver and race info
$penaltiesQuery = "
    SELECT 
        p.*,
        u.username,
        d.driver_number,
        r.name as race_name,
        r.race_date,
        ua.username as applied_by_name
    FROM penalties p
    JOIN drivers d ON p.driver_id = d.id
    JOIN users u ON d.user_id = u.id
    LEFT JOIN races r ON p.race_id = r.id
    LEFT JOIN users ua ON p.applied_by = ua.id
    ORDER BY p.created_at DESC
";
$penaltiesStmt = $conn->prepare($penaltiesQuery);
$penaltiesStmt->execute();
$penalties = $penaltiesStmt->fetchAll();

// Get all drivers for dropdown
$driversQuery = "
    SELECT d.*, u.username, t.name as team_name
    FROM drivers d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN teams t ON d.team_id = t.id
    ORDER BY u.username
";
$driversStmt = $conn->prepare($driversQuery);
$driversStmt->execute();
$drivers = $driversStmt->fetchAll();

// Get recent races for dropdown
$racesQuery = "
    SELECT r.*, s.name as season_name
    FROM races r
    JOIN seasons s ON r.season_id = s.id
    WHERE r.race_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY r.race_date DESC
";
$racesStmt = $conn->prepare($racesQuery);
$racesStmt->execute();
$races = $racesStmt->fetchAll();

include '../includes/header.php';
?>

<div class="racing-header">
    <div class="container">
        <h1 class="display-5 fw-bold mb-3">
            <i class="bi bi-exclamation-triangle me-3"></i>Penalty Management
        </h1>
        <p class="lead mb-0">Apply and manage driver penalties and sanctions</p>
    </div>
</div>

<div class="container my-5">
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            <div class="mt-2">
                <a href="../standings.php" class="btn btn-sm btn-success">
                    <i class="bi bi-trophy me-1"></i>View Updated Standings
                </a>
            </div>
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
        <!-- Penalty Application Form -->
        <div class="col-lg-4">
            <div class="card card-racing shadow-sm sticky-top" style="top: 1rem;">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Apply Penalty</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="driver_id" class="form-label">Driver <span class="text-danger">*</span></label>
                            <select class="form-select" id="driver_id" name="driver_id" required>
                                <option value="">Select Driver</option>
                                <?php foreach ($drivers as $driver): ?>
                                    <option value="<?php echo $driver['id']; ?>">
                                        <?php echo htmlspecialchars($driver['username']); ?>
                                        <?php if ($driver['driver_number']): ?>
                                            (#<?php echo $driver['driver_number']; ?>)
                                        <?php endif; ?>
                                        <?php if ($driver['team_name']): ?>
                                            - <?php echo htmlspecialchars($driver['team_name']); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="type" class="form-label">Penalty Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="type" name="type" required onchange="updateValueField()">
                                <option value="">Select Type</option>
                                <option value="Time Penalty">Time Penalty</option>
                                <option value="Points Deduction">Points Deduction</option>
                                <option value="Grid Drop">Grid Drop</option>
                                <option value="Warning">Warning</option>
                                <option value="Disqualification">Disqualification</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="race_id" class="form-label">Related Race</label>
                            <select class="form-select" id="race_id" name="race_id">
                                <option value="">General Penalty (No specific race)</option>
                                <?php foreach ($races as $race): ?>
                                    <option value="<?php echo $race['id']; ?>">
                                        <?php echo htmlspecialchars($race['name']); ?> 
                                        (<?php echo htmlspecialchars($race['season_name']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Optional: Link penalty to a specific race</div>
                        </div>

                        <div class="mb-3" id="valueField" style="display: none;">
                            <label for="value" class="form-label">Value</label>
                            <input type="number" class="form-control" id="value" name="value" min="0">
                            <div class="form-text" id="valueHelp"></div>
                        </div>

                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" 
                                      placeholder="Explain the incident and penalty reason..." required></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" name="apply_penalty" class="btn btn-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>Apply Penalty
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Penalty Guide -->
            <div class="card card-racing shadow-sm mt-4">
                <div class="card-header bg-dark text-white">
                    <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Penalty Guide</h6>
                </div>
                <div class="card-body">
                    <small>
                        <strong>Time Penalty:</strong> Seconds added to race time<br>
                        <strong>Points Deduction:</strong> Points removed from total<br>
                        <strong>Grid Drop:</strong> Starting position penalty for next race<br>
                        <strong>Warning:</strong> Official warning with no immediate penalty<br>
                        <strong>Disqualification:</strong> Exclusion from race/championship
                    </small>
                </div>
            </div>
        </div>

        <!-- Penalties List -->
        <div class="col-lg-8">
            <div class="card card-racing shadow-sm">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="bi bi-list me-2"></i>Applied Penalties</h4>
                    <span class="badge bg-danger"><?php echo count($penalties); ?> penalties</span>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($penalties)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Driver</th>
                                        <th>Penalty</th>
                                        <th>Race</th>
                                        <th>Reason</th>
                                        <th>Applied By</th>
                                        <th>Date</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($penalties as $penalty): ?>
                                        <tr>
                                            <td>
                                                <a href="../driver.php?id=<?php echo $penalty['driver_id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($penalty['username']); ?>
                                                </a>
                                                <?php if ($penalty['driver_number']): ?>
                                                    <span class="badge bg-secondary ms-1">#<?php echo $penalty['driver_number']; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($penalty['type']) {
                                                        'Time Penalty' => 'warning',
                                                        'Points Deduction' => 'danger',
                                                        'Grid Drop' => 'info',
                                                        'Disqualification' => 'dark',
                                                        'Warning' => 'secondary',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo htmlspecialchars($penalty['type']); ?>
                                                </span>
                                                <?php if ($penalty['value']): ?>
                                                    <div class="small text-muted">
                                                        <?php 
                                                        echo $penalty['value'];
                                                        if ($penalty['type'] === 'Time Penalty') echo 's';
                                                        elseif ($penalty['type'] === 'Points Deduction') echo ' pts';
                                                        elseif ($penalty['type'] === 'Grid Drop') echo ' places';
                                                        ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($penalty['race_name']): ?>
                                                    <a href="../race.php?id=<?php echo $penalty['race_id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($penalty['race_name']); ?>
                                                    </a>
                                                    <div class="small text-muted"><?php echo formatDate($penalty['race_date']); ?></div>
                                                <?php else: ?>
                                                    <span class="text-muted">General</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="max-width: 200px;">
                                                    <?php echo htmlspecialchars($penalty['reason']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($penalty['applied_by_name'] ?? 'System'); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo formatDate($penalty['created_at']); ?>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-outline-danger btn-sm" 
                                                        onclick="removePenalty(<?php echo $penalty['id']; ?>, '<?php echo htmlspecialchars($penalty['username']); ?>', '<?php echo htmlspecialchars($penalty['type']); ?>')"
                                                        title="Remove Penalty">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-shield-check display-1 text-muted"></i>
                            <h5 class="mt-3">No Penalties Applied</h5>
                            <p class="text-muted">Clean racing championship! No penalties have been applied yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Penalty Statistics -->
            <?php if (!empty($penalties)): ?>
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card card-racing shadow-sm">
                            <div class="card-header bg-dark text-white">
                                <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Penalty Types</h6>
                            </div>
                            <div class="card-body">
                                <?php
                                $penaltyTypes = array_count_values(array_column($penalties, 'type'));
                                foreach ($penaltyTypes as $type => $count):
                                    $percentage = round(($count / count($penalties)) * 100);
                                ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span><?php echo htmlspecialchars($type); ?></span>
                                        <div class="d-flex align-items-center">
                                            <div class="progress me-2" style="width: 100px; height: 15px;">
                                                <div class="progress-bar bg-danger" style="width: <?php echo $percentage; ?>%"></div>
                                            </div>
                                            <span class="fw-bold"><?php echo $count; ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card card-racing shadow-sm">
                            <div class="card-header bg-dark text-white">
                                <h6 class="mb-0"><i class="bi bi-calendar me-2"></i>Recent Activity</h6>
                            </div>
                            <div class="card-body">
                                <?php 
                                $recentPenalties = array_slice($penalties, 0, 5);
                                foreach ($recentPenalties as $penalty): 
                                ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($penalty['username']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($penalty['type']); ?></small>
                                        </div>
                                        <small class="text-muted"><?php echo formatDate($penalty['created_at']); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Remove Penalty Modal -->
<div class="modal fade" id="removePenaltyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Remove Penalty</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove the <strong id="penaltyType"></strong> penalty from <strong id="penaltyDriver"></strong>?</p>
                <p class="text-warning">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    If this was a points deduction, the points will be restored.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="penalty_id" id="removePenaltyId">
                    <button type="submit" name="remove_penalty" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Remove Penalty
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function updateValueField() {
    const type = document.getElementById('type').value;
    const valueField = document.getElementById('valueField');
    const valueInput = document.getElementById('value');
    const valueHelp = document.getElementById('valueHelp');
    
    if (type === 'Time Penalty') {
        valueField.style.display = 'block';
        valueInput.required = true;
        valueInput.placeholder = '5';
        valueHelp.textContent = 'Seconds to add to race time';
    } else if (type === 'Points Deduction') {
        valueField.style.display = 'block';
        valueInput.required = true;
        valueInput.placeholder = '5';
        valueHelp.textContent = 'Points to deduct from total';
    } else if (type === 'Grid Drop') {
        valueField.style.display = 'block';
        valueInput.required = true;
        valueInput.placeholder = '3';
        valueHelp.textContent = 'Grid positions to drop for next race';
    } else {
        valueField.style.display = 'none';
        valueInput.required = false;
        valueInput.value = '';
    }
}

function removePenalty(penaltyId, driverName, penaltyType) {
    document.getElementById('removePenaltyId').value = penaltyId;
    document.getElementById('penaltyDriver').textContent = driverName;
    document.getElementById('penaltyType').textContent = penaltyType;
    new bootstrap.Modal(document.getElementById('removePenaltyModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>