<?php
/**
 * Individual Race Details and Results Page
 */

require_once 'config/config.php';

$page_title = 'Race Details';

// Get race ID from URL
$race_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$race_id) {
    header('Location: calendar.php');
    exit();
}

// Get database connection
$db = new Database();
$conn = $db->getConnection();

// Get race details
$raceQuery = "
    SELECT r.*, s.name as season_name, s.points_system
    FROM races r
    JOIN seasons s ON r.season_id = s.id
    WHERE r.id = :race_id
";
$raceStmt = $conn->prepare($raceQuery);
$raceStmt->bindParam(':race_id', $race_id);
$raceStmt->execute();
$race = $raceStmt->fetch();

if (!$race) {
    header('Location: calendar.php');
    exit();
}

$page_title = $race['name'];

// Get race results (now includes attendance)
$resultsQuery = "
    SELECT 
        rr.*,
        u.username,
        d.driver_number,
        d.platform,
        d.country,
        t.name as team_name
    FROM race_results rr
    JOIN drivers d ON rr.driver_id = d.id
    JOIN users u ON d.user_id = u.id
    LEFT JOIN teams t ON d.team_id = t.id
    WHERE rr.race_id = :race_id
    ORDER BY 
        CASE WHEN rr.position IS NULL THEN 1 ELSE 0 END,
        rr.position ASC,
        rr.created_at ASC
";
$resultsStmt = $conn->prepare($resultsQuery);
$resultsStmt->bindParam(':race_id', $race_id);
$resultsStmt->execute();
$results = $resultsStmt->fetchAll();

// Get penalties for this race
$penaltiesQuery = "
    SELECT p.*, u.username, d.driver_number
    FROM penalties p
    JOIN drivers d ON p.driver_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE p.race_id = :race_id
    ORDER BY p.created_at DESC
";
$penaltiesStmt = $conn->prepare($penaltiesQuery);
$penaltiesStmt->bindParam(':race_id', $race_id);
$penaltiesStmt->execute();
$penalties = $penaltiesStmt->fetchAll();

// Calculate race statistics
$stats = [
    'total_participants' => count($results),
    'dnf_count' => count(array_filter($results, fn($r) => $r['dnf'])),
    'fastest_lap_driver' => null,
    'pole_position_driver' => null
];

foreach ($results as $result) {
    if ($result['fastest_lap']) {
        $stats['fastest_lap_driver'] = $result['username'];
    }
    if ($result['pole_position']) {
        $stats['pole_position_driver'] = $result['username'];
    }
}

$regStmt = $conn->prepare("SELECT COUNT(*) FROM race_registrations WHERE race_id = :race_id");
$regStmt->bindParam(':race_id', $race_id);
$regStmt->execute();
$stats['total_participants'] = (int)$regStmt->fetchColumn();

include 'includes/header.php';
?>

<div class="racing-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1 class="display-5 fw-bold mb-3">
                    <i class="bi bi-flag-checkered me-3"></i><?php echo htmlspecialchars($race['name']); ?>
                </h1>
                <div class="d-flex flex-wrap gap-3 mb-3">
                    <span class="badge bg-primary fs-6"><?php echo htmlspecialchars($race['season_name']); ?></span>
                    <span class="badge bg-<?php 
                        echo match($race['status']) {
                            'Completed' => 'success',
                            'In Progress' => 'warning',
                            'Scheduled' => 'info',
                            'Cancelled' => 'danger',
                            default => 'secondary'
                        };
                    ?> fs-6">
                        <?php echo htmlspecialchars($race['status']); ?>
                    </span>
                    <span class="badge bg-secondary fs-6"><?php echo htmlspecialchars($race['format']); ?></span>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <?php if ($race['status'] === 'Scheduled' && strtotime($race['race_date']) > time()): ?>
                    <div class="race-countdown display-6" 
                         id="race-countdown" 
                         data-countdown="<?php echo $race['race_date']; ?>">
                    </div>
                    <small class="text-light">Time Until Race</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="container my-5">
    <!-- Race Information -->
    <div class="row mb-5">
        <div class="col-lg-8">
            <div class="card card-racing shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h4 class="mb-0"><i class="bi bi-info-circle me-2"></i>Race Information</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Track Details</h6>
                            <p class="mb-2">
                                <i class="bi bi-geo-alt text-primary me-2"></i>
                                <strong>Circuit:</strong> <?php echo htmlspecialchars($race['track']); ?>
                            </p>
                            <p class="mb-2">
                                <i class="bi bi-calendar text-primary me-2"></i>
                                <strong>Date:</strong> <?php echo formatDate($race['race_date']); ?>
                            </p>
                            <p class="mb-2">
                                <i class="bi bi-flag text-primary me-2"></i>
                                <strong>Format:</strong> <?php echo htmlspecialchars($race['format']); ?>
                            </p>
                            <?php if ($race['laps']): ?>
                                <p class="mb-0">
                                    <i class="bi bi-arrow-repeat text-primary me-2"></i>
                                    <strong>Laps:</strong> <?php echo $race['laps']; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Race Statistics</h6>
                            <p class="mb-2">
                                <i class="bi bi-people text-success me-2"></i>
                                <strong>Participants:</strong> <?php echo $stats['total_participants']; ?>
                            </p>
                            <p class="mb-2">
                                <i class="bi bi-x-circle text-danger me-2"></i>
                                <strong>DNFs:</strong> <?php echo $stats['dnf_count']; ?>
                            </p>
                            <?php if ($stats['pole_position_driver']): ?>
                                <p class="mb-2">
                                    <i class="bi bi-lightning text-info me-2"></i>
                                    <strong>Pole Position:</strong> <?php echo htmlspecialchars($stats['pole_position_driver']); ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($stats['fastest_lap_driver']): ?>
                                <p class="mb-0">
                                    <i class="bi bi-stopwatch text-warning me-2"></i>
                                    <strong>Fastest Lap:</strong> <?php echo htmlspecialchars($stats['fastest_lap_driver']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="col-lg-4">
            <div class="card card-racing shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="standings.php" class="btn btn-outline-primary">
                            <i class="bi bi-trophy me-1"></i>Championship Standings
                        </a>
                        <a href="calendar.php" class="btn btn-outline-info">
                            <i class="bi bi-calendar-event me-1"></i>Race Calendar
                        </a>
                        <?php if (isAdmin()): ?>
                            <hr>
                            <a href="admin/results.php?race_id=<?php echo $race['id']; ?>" class="btn btn-racing">
                                <i class="bi bi-pencil me-1"></i>Edit Results
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Race Results -->
    <?php if (!empty($results)): ?>
        <div class="card card-racing shadow-sm mb-4">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="bi bi-trophy me-2"></i>Race Results</h4>
                <span class="badge bg-primary"><?php echo count($results); ?> drivers</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center">Pos</th>
                                <th>Driver</th>
                                <th class="text-center">Team</th>
                                <th class="text-center">Platform</th>
                                <th class="text-center">Points</th>
                                <th class="text-center">Bonuses</th>
                                <th class="text-center">Penalties</th>
                                <th class="text-center">Attendance</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $result): ?>
                                <tr class="<?php 
                                    if ($result['position'] == 1) echo 'position-1';
                                    elseif ($result['position'] == 2) echo 'position-2';
                                    elseif ($result['position'] == 3) echo 'position-3';
                                ?>">
                                    <td class="text-center fw-bold">
                                        <?php if ($result['dnf']): ?>
                                            <span class="text-danger">DNF</span>
                                        <?php else: ?>
                                            <?php echo $result['position']; ?>
                                            <?php if ($result['position'] == 1): ?>
                                                <i class="bi bi-trophy text-warning ms-1"></i>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div>
                                                <a href="driver.php?id=<?php echo $result['driver_id']; ?>" 
                                                   class="text-decoration-none fw-semibold">
                                                    <?php echo htmlspecialchars($result['username']); ?>
                                                </a>
                                                <?php if ($result['driver_number']): ?>
                                                    <span class="badge bg-secondary ms-2">#<?php echo $result['driver_number']; ?></span>
                                                <?php endif; ?>
                                                <?php if ($result['country']): ?>
                                                    <small class="text-muted ms-2"><?php echo htmlspecialchars($result['country']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($result['team_name']): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($result['team_name']); ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">Independent</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <i class="bi bi-<?php 
                                            echo match($result['platform']) {
                                                'PC' => 'pc-display',
                                                'Xbox' => 'xbox',
                                                'PlayStation' => 'playstation',
                                                default => 'controller'
                                            }; 
                                        ?> text-muted"></i>
                                        <small class="text-muted"><?php echo htmlspecialchars($result['platform']); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-primary"><?php echo $result['points']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($result['pole_position']): ?>
                                            <span class="badge bg-info me-1" title="Pole Position">P</span>
                                        <?php endif; ?>
                                        <?php if ($result['fastest_lap']): ?>
                                            <span class="badge bg-warning" title="Fastest Lap">FL</span>
                                        <?php endif; ?>
                                        <?php if (!$result['pole_position'] && !$result['fastest_lap']): ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($result['time_penalty'] > 0 || $result['points_penalty'] > 0): ?>
                                            <?php if ($result['time_penalty'] > 0): ?>
                                                <span class="badge bg-warning me-1" title="Time Penalty">+<?php echo $result['time_penalty']; ?>s</span>
                                            <?php endif; ?>
                                            <?php if ($result['points_penalty'] > 0): ?>
                                                <span class="badge bg-danger" title="Points Penalty">-<?php echo $result['points_penalty']; ?>pts</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                            $att = $result['attendance'] ?? 'Present';
                                            if ($att === 'Present') {
                                                echo '<span class="badge bg-success">Present</span>';
                                            } elseif ($att === 'Absent') {
                                                echo '<span class="badge bg-danger">Absent</span>';
                                            } elseif ($att === 'Excused') {
                                                echo '<span class="badge bg-warning text-dark">Excused</span>';
                                            } else {
                                                echo '<span class="badge bg-secondary">Unknown</span>';
                                            }
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($result['dnf']): ?>
                                            <span class="badge bg-danger" title="<?php echo htmlspecialchars($result['dnf_reason']); ?>">
                                                DNF
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Finished</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card card-racing shadow-sm mb-4">
            <div class="card-header bg-dark text-white">
                <h4 class="mb-0"><i class="bi bi-trophy me-2"></i>Race Results</h4>
            </div>
            <div class="card-body text-center py-5">
                <?php if ($race['status'] === 'Completed'): ?>
                    <i class="bi bi-exclamation-triangle display-1 text-muted"></i>
                    <h5 class="mt-3">No Results Available</h5>
                    <p class="text-muted">Race results haven't been uploaded yet.</p>
                    <?php if (isAdmin()): ?>
                        <a href="admin/results.php?race_id=<?php echo $race['id']; ?>" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Add Results
                        </a>
                    <?php endif; ?>
                <?php elseif ($race['status'] === 'In Progress'): ?>
                    <i class="bi bi-hourglass-split display-1 text-warning"></i>
                    <h5 class="mt-3">Race In Progress</h5>
                    <p class="text-muted">Results will appear here when the race is completed.</p>
                <?php else: ?>
                    <i class="bi bi-clock display-1 text-muted"></i>
                    <h5 class="mt-3">Race Not Started</h5>
                    <p class="text-muted">Results will appear here after the race is completed.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Penalties Section -->
    <?php if (!empty($penalties)): ?>
        <div class="card card-racing shadow-sm">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Race Penalties</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Driver</th>
                                <th>Penalty Type</th>
                                <th>Value</th>
                                <th>Reason</th>
                                <th>Applied</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($penalties as $penalty): ?>
                                <tr>
                                    <td>
                                        <a href="driver.php?id=<?php echo $penalty['driver_id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($penalty['username']); ?>
                                        </a>
                                        <?php if ($penalty['driver_number']): ?>
                                            <span class="badge bg-secondary ms-2">#<?php echo $penalty['driver_number']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo match($penalty['type']) {
                                                'Time Penalty' => 'warning',
                                                'Points Deduction' => 'danger',
                                                'Grid Drop' => 'info',
                                                'Disqualification' => 'dark',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo htmlspecialchars($penalty['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($penalty['value']): ?>
                                            <?php echo $penalty['value']; ?>
                                            <?php echo $penalty['type'] === 'Time Penalty' ? 's' : ($penalty['type'] === 'Points Deduction' ? ' pts' : ''); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($penalty['reason']); ?></td>
                                    <td>
                                        <small class="text-muted"><?php echo formatDate($penalty['created_at']); ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>