<?php
/**
 * Race Results Management - The Core Racing Feature
 * This is where admins input race results and standings update live
 */

require_once '../config/config.php';

requireAdmin();

$page_title = 'Race Results Management';

// Get database connection
$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_results'])) {
    $race_id = (int)$_POST['race_id'];
    $results = $_POST['results'] ?? [];
    
    if (empty($race_id) || empty($results)) {
        $error = 'Please select a race and add at least one result.';
    } else {
        try {
            $conn->beginTransaction();
            
            // Clear existing results for this race
            $clearQuery = "DELETE FROM race_results WHERE race_id = :race_id";
            $clearStmt = $conn->prepare($clearQuery);
            $clearStmt->bindParam(':race_id', $race_id);
            $clearStmt->execute();
            
            // Insert new results
            $insertQuery = "
                INSERT INTO race_results 
                (race_id, driver_id, position, points, fastest_lap, pole_position, dnf, dnf_reason, time_penalty, points_penalty) 
                VALUES 
                (:race_id, :driver_id, :position, :points, :fastest_lap, :pole_position, :dnf, :dnf_reason, :time_penalty, :points_penalty)
            ";
            $insertStmt = $conn->prepare($insertQuery);
            
            foreach ($results as $result) {
                if (empty($result['driver_id'])) continue;
                
                $driver_id = (int)$result['driver_id'];
                $position = !empty($result['position']) ? (int)$result['position'] : null;
                $dnf = isset($result['dnf']) ? 1 : 0;
                $fastest_lap = isset($result['fastest_lap']) ? 1 : 0;
                $pole_position = isset($result['pole_position']) ? 1 : 0;
                $dnf_reason = sanitizeInput($result['dnf_reason'] ?? '');
                $time_penalty = (int)($result['time_penalty'] ?? 0);
                $points_penalty = (int)($result['points_penalty'] ?? 0);
                // Points are now entered by admin
                $points = isset($result['points']) ? (int)$result['points'] : 0;
                $points = max(0, $points - $points_penalty);

                $insertStmt->bindParam(':race_id', $race_id);
                $insertStmt->bindParam(':driver_id', $driver_id);
                $insertStmt->bindParam(':position', $position);
                $insertStmt->bindParam(':points', $points);
                $insertStmt->bindParam(':fastest_lap', $fastest_lap);
                $insertStmt->bindParam(':pole_position', $pole_position);
                $insertStmt->bindParam(':dnf', $dnf);
                $insertStmt->bindParam(':dnf_reason', $dnf_reason);
                $insertStmt->bindParam(':time_penalty', $time_penalty);
                $insertStmt->bindParam(':points_penalty', $points_penalty);
                
                $insertStmt->execute();
            }
            
            // Update race status to completed
            $updateRaceQuery = "UPDATE races SET status = 'Completed' WHERE id = :race_id";
            $updateRaceStmt = $conn->prepare($updateRaceQuery);
            $updateRaceStmt->bindParam(':race_id', $race_id);
            $updateRaceStmt->execute();
            
            $conn->commit();
            $success = 'Race results have been successfully updated! Standings are now live.';
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = 'Error updating results: ' . $e->getMessage();
        }
    }
}

// Get races for selection
$racesQuery = "
    SELECT r.*, s.name as season_name, COUNT(rr.id) as existing_results
    FROM races r
    JOIN seasons s ON r.season_id = s.id
    LEFT JOIN race_results rr ON r.id = rr.race_id
    GROUP BY r.id
    ORDER BY r.race_date DESC
";
$racesStmt = $conn->prepare($racesQuery);
$racesStmt->execute();
$races = $racesStmt->fetchAll();

// Get drivers
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

// Get selected race details if race_id in URL
$selectedRace = null;
$existingResults = [];
if (isset($_GET['race_id'])) {
    $race_id = (int)$_GET['race_id'];
    
    $raceQuery = "SELECT * FROM races WHERE id = :id";
    $raceStmt = $conn->prepare($raceQuery);
    $raceStmt->bindParam(':id', $race_id);
    $raceStmt->execute();
    $selectedRace = $raceStmt->fetch();
    
    // Get existing results
    $resultsQuery = "
        SELECT rr.*, u.username, d.driver_number
        FROM race_results rr
        JOIN drivers d ON rr.driver_id = d.id
        JOIN users u ON d.user_id = u.id
        WHERE rr.race_id = :race_id
        ORDER BY 
            CASE WHEN rr.position IS NULL THEN 1 ELSE 0 END,
            rr.position ASC
    ";
    $resultsStmt = $conn->prepare($resultsQuery);
    $resultsStmt->bindParam(':race_id', $race_id);
    $resultsStmt->execute();
    $existingResults = $resultsStmt->fetchAll();
}

include '../includes/header.php';
?>

<div class="racing-header">
    <div class="container">
        <h1 class="display-5 fw-bold mb-3">
            <i class="bi bi-trophy me-3"></i>Race Results Management
        </h1>
        <p class="lead mb-0">Input race results and watch standings update live</p>
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
        <!-- Race Selection -->
        <div class="col-lg-4">
            <div class="card card-racing shadow-sm sticky-top" style="top: 1rem;">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Select Race</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="raceSelect" class="form-label">Choose Race</label>
                        <select class="form-select" id="raceSelect" onchange="selectRace()">
                            <option value="">Select a race...</option>
                            <?php foreach ($races as $race): ?>
                                <option value="<?php echo $race['id']; ?>" 
                                        <?php echo ($selectedRace && $selectedRace['id'] == $race['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($race['name']); ?> 
                                    (<?php echo htmlspecialchars($race['season_name']); ?>)
                                    <?php if ($race['existing_results'] > 0): ?>
                                        - ✓ Results Added
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if ($selectedRace): ?>
                        <div class="alert alert-info">
                            <h6 class="alert-heading">Race Details</h6>
                            <p class="mb-1"><strong>Track:</strong> <?php echo htmlspecialchars($selectedRace['track']); ?></p>
                            <p class="mb-1"><strong>Date:</strong> <?php echo formatDate($selectedRace['race_date']); ?></p>
                            <p class="mb-1"><strong>Format:</strong> <?php echo htmlspecialchars($selectedRace['format']); ?></p>
                            <p class="mb-0"><strong>Status:</strong> 
                                <span class="badge bg-<?php echo $selectedRace['status'] === 'Completed' ? 'success' : 'warning'; ?>">
                                    <?php echo htmlspecialchars($selectedRace['status']); ?>
                                </span>
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" onclick="addDriverRow()">
                            <i class="bi bi-plus-circle me-1"></i>Add Driver
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="fillQuickPositions()">
                            <i class="bi bi-lightning me-1"></i>Quick Fill Positions
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="clearAll()">
                            <i class="bi bi-trash me-1"></i>Clear All
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Input -->
        <div class="col-lg-8">
            <?php if ($selectedRace): ?>
                <form method="POST" action="" id="resultsForm">
                    <input type="hidden" name="race_id" value="<?php echo $selectedRace['id']; ?>">
                    
                    <div class="card card-racing shadow-sm">
                        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>Race Results</h5>
                            <span class="badge bg-primary" id="driverCount">0 drivers</span>
                        </div>
                        <div class="card-body">
                            <div id="resultsContainer">
                                <?php if (!empty($existingResults)): ?>
                                    <!-- Pre-populate with existing results -->
                                    <?php foreach ($existingResults as $index => $result): ?>
                                        <div class="row driver-result mb-3 p-3 border rounded">
                                            <div class="col-md-3">
                                                <label class="form-label">Driver</label>
                                                <select class="form-select" name="results[<?php echo $index; ?>][driver_id]" required>
                                                    <option value="">Select Driver</option>
                                                    <?php foreach ($drivers as $driver): ?>
                                                        <option value="<?php echo $driver['id']; ?>" 
                                                                <?php echo $driver['id'] == $result['driver_id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($driver['username']); ?>
                                                            <?php if ($driver['driver_number']): ?>
                                                                (#<?php echo $driver['driver_number']; ?>)
                                                            <?php endif; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Position</label>
                                                <input type="number" class="form-control" 
                                                       name="results[<?php echo $index; ?>][position]"
                                                       value="<?php echo $result['position']; ?>"
                                                       min="1" max="50" placeholder="1">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Points</label>
                                                <input type="number" class="form-control points-display" 
                                                       name="results[<?php echo $index; ?>][points]"
                                                       value="<?php echo $result['points']; ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Bonuses</label>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="results[<?php echo $index; ?>][pole_position]"
                                                           <?php echo $result['pole_position'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label small">Pole Position</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="results[<?php echo $index; ?>][fastest_lap]"
                                                           <?php echo $result['fastest_lap'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label small">Fastest Lap</label>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">DNF</label>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="results[<?php echo $index; ?>][dnf]"
                                                           <?php echo $result['dnf'] ? 'checked' : ''; ?>
                                                           onchange="toggleDNF(this)">
                                                    <label class="form-check-label small">Did Not Finish</label>
                                                </div>
                                                <input type="text" class="form-control form-control-sm mt-1 dnf-reason" 
                                                       name="results[<?php echo $index; ?>][dnf_reason]"
                                                       value="<?php echo htmlspecialchars($result['dnf_reason']); ?>"
                                                       placeholder="Reason" 
                                                       style="<?php echo $result['dnf'] ? '' : 'display:none;'; ?>">
                                            </div>
                                            <div class="col-12 mt-2">
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <label class="form-label small">Time Penalty (seconds)</label>
                                                        <input type="number" class="form-control form-control-sm" 
                                                               name="results[<?php echo $index; ?>][time_penalty]"
                                                               value="<?php echo $result['time_penalty']; ?>"
                                                               min="0" placeholder="0">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small">Points Penalty</label>
                                                        <input type="number" class="form-control form-control-sm points-penalty" 
                                                               name="results[<?php echo $index; ?>][points_penalty]"
                                                               value="<?php echo $result['points_penalty']; ?>"
                                                               min="0" placeholder="0">
                                                    </div>
                                                    <div class="col-md-4 d-flex align-items-end">
                                                        <button type="button" class="btn btn-outline-danger btn-sm w-100" 
                                                                onclick="removeDriverRow(this)">
                                                            <i class="bi bi-trash"></i> Remove
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-5 text-muted">
                                        <i class="bi bi-plus-circle display-4"></i>
                                        <h5 class="mt-3">No Results Added Yet</h5>
                                        <p>Click "Add Driver" to start adding race results</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="text-center mt-4">
                                <button type="submit" name="add_results" class="btn btn-racing btn-lg">
                                    <i class="bi bi-save me-2"></i>Save Results & Update Standings
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <div class="card card-racing shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-calendar-x display-1 text-muted"></i>
                        <h4 class="mt-3">Select a Race</h4>
                        <p class="text-muted">Choose a race from the left panel to start adding results</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
let driverRowIndex = <?php echo count($existingResults); ?>;

function selectRace() {
    const raceId = document.getElementById('raceSelect').value;
    if (raceId) {
        window.location.href = `?race_id=${raceId}`;
    }
}

function addDriverRow() {
    const container = document.getElementById('resultsContainer');
    
    // Remove empty state if present
    const emptyState = container.querySelector('.py-5');
    if (emptyState) {
        emptyState.remove();
    }
    
    const row = document.createElement('div');
    row.className = 'row driver-result mb-3 p-3 border rounded';
    row.innerHTML = `
        <div class="col-md-3">
            <label class="form-label">Driver</label>
            <select class="form-select" name="results[${driverRowIndex}][driver_id]" required>
                <option value="">Select Driver</option>
                <?php foreach ($drivers as $driver): ?>
                    <option value="<?php echo $driver['id']; ?>">
                        <?php echo htmlspecialchars($driver['username']); ?>
                        <?php if ($driver['driver_number']): ?>
                            (#<?php echo $driver['driver_number']; ?>)
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Position</label>
            <input type="number" class="form-control position-input" 
                   name="results[${driverRowIndex}][position]"
                   min="1" max="50" placeholder="1">
        </div>
        <div class="col-md-2">
            <label class="form-label">Points</label>
            <input type="number" class="form-control points-display" 
                   name="results[${driverRowIndex}][points]" value="0">
        </div>
        <div class="col-md-3">
            <label class="form-label">Bonuses</label>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" 
                       name="results[${driverRowIndex}][pole_position]">
                <label class="form-check-label small">Pole Position</label>
            </div>
            <div class="form-check">
                <input class="form-check-input fastest-lap-check" type="checkbox" 
                       name="results[${driverRowIndex}][fastest_lap]">
                <label class="form-check-label small">Fastest Lap</label>
            </div>
        </div>
        <div class="col-md-2">
            <label class="form-label">DNF</label>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" 
                       name="results[${driverRowIndex}][dnf]" onchange="toggleDNF(this)">
                <label class="form-check-label small">Did Not Finish</label>
            </div>
            <input type="text" class="form-control form-control-sm mt-1 dnf-reason" 
                   name="results[${driverRowIndex}][dnf_reason]"
                   placeholder="Reason" style="display:none;">
        </div>
        <div class="col-12 mt-2">
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label small">Time Penalty (seconds)</label>
                    <input type="number" class="form-control form-control-sm" 
                           name="results[${driverRowIndex}][time_penalty]"
                           min="0" placeholder="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Points Penalty</label>
                    <input type="number" class="form-control form-control-sm points-penalty" 
                           name="results[${driverRowIndex}][points_penalty]"
                           min="0" placeholder="0">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-danger btn-sm w-100" 
                            onclick="removeDriverRow(this)">
                        <i class="bi bi-trash"></i> Remove
                    </button>
                </div>
            </div>
        </div>
    `;
    
    container.appendChild(row);
    driverRowIndex++;
    updateDriverCount();
}

function removeDriverRow(button) {
    button.closest('.driver-result').remove();
    updateDriverCount();
}

function toggleDNF(checkbox) {
    const reasonField = checkbox.closest('.driver-result').querySelector('.dnf-reason');
    const positionField = checkbox.closest('.driver-result').querySelector('.position-input');
    
    if (checkbox.checked) {
        reasonField.style.display = 'block';
        positionField.value = '';
        positionField.disabled = true;
    } else {
        reasonField.style.display = 'none';
        reasonField.value = '';
        positionField.disabled = false;
    }
}

function fillQuickPositions() {
    const rows = document.querySelectorAll('.driver-result');
    rows.forEach((row, index) => {
        const positionInput = row.querySelector('.position-input');
        const dnfCheck = row.querySelector('input[type="checkbox"][name*="[dnf]"]');
        
        if (!dnfCheck.checked) {
            positionInput.value = index + 1;
        }
    });
}

function clearAll() {
    if (confirm('Are you sure you want to clear all results?')) {
        document.getElementById('resultsContainer').innerHTML = `
            <div class="text-center py-5 text-muted">
                <i class="bi bi-plus-circle display-4"></i>
                <h5 class="mt-3">No Results Added Yet</h5>
                <p>Click "Add Driver" to start adding race results</p>
            </div>
        `;
        driverRowIndex = 0;
        updateDriverCount();
    }
}

function updateDriverCount() {
    const count = document.querySelectorAll('.driver-result').length;
    document.getElementById('driverCount').textContent = `${count} driver${count !== 1 ? 's' : ''}`;
}

// Initialize driver count
document.addEventListener('DOMContentLoaded', function() {
    updateDriverCount();
});
</script>

<?php include '../includes/footer.php'; ?>