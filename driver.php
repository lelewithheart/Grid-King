<?php
/**
 * Individual Driver Profile Page
 */

require_once 'config/config.php';

$page_title = 'Driver Profile';

// Get driver ID from URL
$driver_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$driver_id) {
    header('Location: drivers.php');
    exit();
}

// Get database connection
$db = new Database();
$conn = $db->getConnection();

// Get driver details
$driverQuery = "
    SELECT 
        d.*,
        u.username,
        u.email,
        u.role,
        t.name as team_name,
        t.id as team_id
    FROM drivers d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN teams t ON d.team_id = t.id
    WHERE d.id = :driver_id
";
$driverStmt = $conn->prepare($driverQuery);
$driverStmt->bindParam(':driver_id', $driver_id);
$driverStmt->execute();
$driver = $driverStmt->fetch();

if (!$driver) {
    header('Location: drivers.php');
    exit();
}

$page_title = $driver['username'] . ' - Driver Profile';

// Get driver's race results
$resultsQuery = "
    SELECT 
        rr.*,
        r.name as race_name,
        r.track,
        r.race_date,
        r.format,
        s.name as season_name
    FROM race_results rr
    JOIN races r ON rr.race_id = r.id
    JOIN seasons s ON r.season_id = s.id
    WHERE rr.driver_id = :driver_id
    ORDER BY r.race_date DESC
";
$resultsStmt = $conn->prepare($resultsQuery);
$resultsStmt->bindParam(':driver_id', $driver_id);
$resultsStmt->execute();
$results = $resultsStmt->fetchAll();

// Calculate driver statistics
$stats = [
    'total_races' => count($results),
    'total_points' => array_sum(array_column($results, 'points')),
    'wins' => count(array_filter($results, fn($r) => $r['position'] == 1)),
    'podiums' => count(array_filter($results, fn($r) => $r['position'] <= 3 && !$r['dnf'])),
    'poles' => count(array_filter($results, fn($r) => $r['pole_position'])),
    'fastest_laps' => count(array_filter($results, fn($r) => $r['fastest_lap'])),
    'dnfs' => count(array_filter($results, fn($r) => $r['dnf'])),
    'avg_position' => 0,
    'best_finish' => null,
    'worst_finish' => null
];

$finishedRaces = array_filter($results, fn($r) => !$r['dnf'] && $r['position']);
if (!empty($finishedRaces)) {
    $positions = array_column($finishedRaces, 'position');
    $stats['avg_position'] = array_sum($positions) / count($positions);
    $stats['best_finish'] = min($positions);
    $stats['worst_finish'] = max($positions);
}

// Get current season standings position
$currentPosition = null;
$currentSeason = null;
$seasonQuery = "SELECT * FROM seasons WHERE is_active = TRUE ORDER BY year DESC LIMIT 1";
$seasonStmt = $conn->prepare($seasonQuery);
$seasonStmt->execute();
$currentSeason = $seasonStmt->fetch();

if ($currentSeason) {
    $standings = calculateStandings($currentSeason['id']);
    foreach ($standings as $index => $standingDriver) {
        if ($standingDriver['id'] == $driver_id) {
            $currentPosition = $index + 1;
            break;
        }
    }
}

// Get recent penalties
$penaltiesQuery = "
    SELECT p.*, r.name as race_name, r.race_date
    FROM penalties p
    LEFT JOIN races r ON p.race_id = r.id
    WHERE p.driver_id = :driver_id
    ORDER BY p.created_at DESC
    LIMIT 5
";
$penaltiesStmt = $conn->prepare($penaltiesQuery);
$penaltiesStmt->bindParam(':driver_id', $driver_id);
$penaltiesStmt->execute();
$penalties = $penaltiesStmt->fetchAll();

// Get teammates (if in a team)
$teammates = [];
if ($driver['team_id']) {
    $teammatesQuery = "
        SELECT d.*, u.username
        FROM drivers d
        JOIN users u ON d.user_id = u.id
        WHERE d.team_id = :team_id AND d.id != :driver_id
    ";
    $teammatesStmt = $conn->prepare($teammatesQuery);
    $teammatesStmt->bindParam(':team_id', $driver['team_id']);
    $teammatesStmt->bindParam(':driver_id', $driver_id);
    $teammatesStmt->execute();
    $teammates = $teammatesStmt->fetchAll();
}

include 'includes/header.php';
?>

<div class="racing-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="d-flex align-items-center mb-3">
                    <div class="me-4">
                        <?php if ($driver['livery_image']): ?>
                            <img src="<?php echo htmlspecialchars($driver['livery_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($driver['username']); ?> livery"
                                 class="rounded-circle" style="width: 80px; height: 80px; object-fit: cover;">
                        <?php else: ?>
                            <i class="bi bi-person-circle display-1 text-light"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h1 class="display-5 fw-bold mb-2">
                            <?php echo htmlspecialchars($driver['username']); ?>
                            <?php if ($driver['driver_number']): ?>
                                <span class="badge bg-light text-dark ms-3">#<?php echo $driver['driver_number']; ?></span>
                            <?php endif; ?>
                        </h1>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if ($driver['team_name']): ?>
                                <span class="badge bg-primary fs-6">
                                    <a href="team.php?id=<?php echo $driver['team_id']; ?>" class="text-white text-decoration-none">
                                        <?php echo htmlspecialchars($driver['team_name']); ?>
                                    </a>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary fs-6">Independent Driver</span>
                            <?php endif; ?>
                            <span class="badge bg-info fs-6">
                                <i class="bi bi-<?php 
                                    echo match($driver['platform']) {
                                        'PC' => 'pc-display',
                                        'Xbox' => 'xbox',
                                        'PlayStation' => 'playstation',
                                        default => 'controller'
                                    }; 
                                ?> me-1"></i>
                                <?php echo htmlspecialchars($driver['platform']); ?>
                            </span>
                            <?php if ($driver['country']): ?>
                                <span class="badge bg-success fs-6">
                                    <i class="bi bi-flag me-1"></i><?php echo htmlspecialchars($driver['country']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <?php if ($currentPosition): ?>
                    <div class="badge bg-<?php 
                        echo $currentPosition == 1 ? 'warning' : ($currentPosition <= 3 ? 'success' : 'primary');
                    ?> fs-4 p-3">
                        Championship P<?php echo $currentPosition; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="container my-5">
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Performance Overview -->
            <div class="card card-racing shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h4 class="mb-0"><i class="bi bi-speedometer2 me-2"></i>Performance Overview</h4>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3 mb-3">
                            <div class="card bg-light h-100">
                                <div class="card-body">
                                    <h3 class="text-primary"><?php echo $stats['total_points']; ?></h3>
                                    <p class="mb-0">Total Points</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-light h-100">
                                <div class="card-body">
                                    <h3 class="text-success"><?php echo $stats['wins']; ?></h3>
                                    <p class="mb-0">Race Wins</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-light h-100">
                                <div class="card-body">
                                    <h3 class="text-warning"><?php echo $stats['podiums']; ?></h3>
                                    <p class="mb-0">Podiums</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-light h-100">
                                <div class="card-body">
                                    <h3 class="text-info"><?php echo $stats['total_races']; ?></h3>
                                    <p class="mb-0">Races Entered</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Additional Stats</h6>
                            <div class="row">
                                <div class="col-6 mb-2">
                                    <strong>Pole Positions:</strong>
                                    <span class="float-end badge bg-info"><?php echo $stats['poles']; ?></span>
                                </div>
                                <div class="col-6 mb-2">
                                    <strong>Fastest Laps:</strong>
                                    <span class="float-end badge bg-warning"><?php echo $stats['fastest_laps']; ?></span>
                                </div>
                                <div class="col-6 mb-2">
                                    <strong>DNFs:</strong>
                                    <span class="float-end badge bg-danger"><?php echo $stats['dnfs']; ?></span>
                                </div>
                                <div class="col-6 mb-2">
                                    <strong>Finish Rate:</strong>
                                    <span class="float-end badge bg-success">
                                        <?php echo $stats['total_races'] > 0 ? round((($stats['total_races'] - $stats['dnfs']) / $stats['total_races']) * 100) : 0; ?>%
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Position Stats</h6>
                            <div class="row">
                                <div class="col-6 mb-2">
                                    <strong>Best Finish:</strong>
                                    <span class="float-end">
                                        <?php echo $stats['best_finish'] ? 'P' . $stats['best_finish'] : 'N/A'; ?>
                                    </span>
                                </div>
                                <div class="col-6 mb-2">
                                    <strong>Worst Finish:</strong>
                                    <span class="float-end">
                                        <?php echo $stats['worst_finish'] ? 'P' . $stats['worst_finish'] : 'N/A'; ?>
                                    </span>
                                </div>
                                <div class="col-12 mb-2">
                                    <strong>Average Position:</strong>
                                    <span class="float-end">
                                        <?php echo $stats['avg_position'] ? 'P' . number_format($stats['avg_position'], 1) : 'N/A'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Race History -->
            <div class="card card-racing shadow-sm">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="bi bi-clock-history me-2"></i>Race History</h4>
                    <span class="badge bg-primary"><?php echo count($results); ?> races</span>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($results)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Race</th>
                                        <th>Track</th>
                                        <th class="text-center">Date</th>
                                        <th class="text-center">Position</th>
                                        <th class="text-center">Points</th>
                                        <th class="text-center">Bonuses</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $result): ?>
                                        <tr>
                                            <td>
                                                <a href="race.php?id=<?php echo $result['race_id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($result['race_name']); ?>
                                                </a>
                                                <div class="small text-muted"><?php echo htmlspecialchars($result['season_name']); ?></div>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($result['track']); ?>
                                                <div class="small text-muted"><?php echo htmlspecialchars($result['format']); ?></div>
                                            </td>
                                            <td class="text-center">
                                                <small><?php echo formatDate($result['race_date']); ?></small>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($result['dnf']): ?>
                                                    <span class="badge bg-danger">DNF</span>
                                                    <?php if ($result['dnf_reason']): ?>
                                                        <div class="small text-muted"><?php echo htmlspecialchars($result['dnf_reason']); ?></div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="fw-bold">P<?php echo $result['position']; ?></span>
                                                    <?php if ($result['position'] == 1): ?>
                                                        <i class="bi bi-trophy text-warning ms-1"></i>
                                                    <?php elseif ($result['position'] <= 3): ?>
                                                        <i class="bi bi-award text-success ms-1"></i>
                                                    <?php endif; ?>
                                                <?php endif; ?>
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
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-clock-history display-1 text-muted"></i>
                            <h5 class="mt-3">No Race History</h5>
                            <p class="text-muted">This driver hasn't participated in any races yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Driver Info -->
            <div class="card card-racing shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Driver Information</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-6">
                            <small class="text-muted">Driver Number</small>
                            <div class="fw-bold"><?php echo $driver['driver_number'] ? '#' . $driver['driver_number'] : 'Not set'; ?></div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Platform</small>
                            <div class="fw-bold">
                                <i class="bi bi-<?php 
                                    echo match($driver['platform']) {
                                        'PC' => 'pc-display',
                                        'Xbox' => 'xbox',
                                        'PlayStation' => 'playstation',
                                        default => 'controller'
                                    }; 
                                ?> me-1"></i>
                                <?php echo htmlspecialchars($driver['platform']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <small class="text-muted">Country</small>
                            <div class="fw-bold">
                                <?php if ($driver['country']): ?>
                                    <i class="bi bi-flag me-1"></i><?php echo htmlspecialchars($driver['country']); ?>
                                <?php else: ?>
                                    Not specified
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Team</small>
                            <div class="fw-bold">
                                <?php if ($driver['team_name']): ?>
                                    <a href="team.php?id=<?php echo $driver['team_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($driver['team_name']); ?>
                                    </a>
                                <?php else: ?>
                                    Independent
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($driver['bio']): ?>
                        <div class="mb-3">
                            <small class="text-muted">Bio</small>
                            <div><?php echo nl2br(htmlspecialchars($driver['bio'])); ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="text-center">
                        <small class="text-muted">Joined: <?php echo formatDate($driver['created_at']); ?></small>
                    </div>
                </div>
            </div>

            <!-- Teammates -->
            <?php if (!empty($teammates)): ?>
                <div class="card card-racing shadow-sm mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-people me-2"></i>Teammates</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($teammates as $teammate): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <a href="driver.php?id=<?php echo $teammate['id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($teammate['username']); ?>
                                    </a>
                                    <?php if ($teammate['driver_number']): ?>
                                        <span class="badge bg-secondary ms-1">#<?php echo $teammate['driver_number']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted"><?php echo htmlspecialchars($teammate['platform']); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recent Penalties -->
            <?php if (!empty($penalties)): ?>
                <div class="card card-racing shadow-sm mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Recent Penalties</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($penalties as $penalty): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <div class="d-flex justify-content-between align-items-center">
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
                                    <small class="text-muted"><?php echo formatDate($penalty['created_at']); ?></small>
                                </div>
                                <div class="small mt-1">
                                    <?php if ($penalty['race_name']): ?>
                                        <strong><?php echo htmlspecialchars($penalty['race_name']); ?>:</strong>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($penalty['reason']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="card card-racing shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="standings.php" class="btn btn-outline-primary">
                            <i class="bi bi-trophy me-1"></i>Championship Standings
                        </a>
                        <a href="drivers.php" class="btn btn-outline-info">
                            <i class="bi bi-people me-1"></i>All Drivers
                        </a>
                        <?php if ($driver['team_id']): ?>
                            <a href="team.php?id=<?php echo $driver['team_id']; ?>" class="btn btn-outline-success">
                                <i class="bi bi-shield me-1"></i>View Team
                            </a>
                        <?php endif; ?>
                        <?php if (isLoggedIn() && $_SESSION['user_id'] == $driver['user_id']): ?>
                            <hr>
                            <a href="profile.php" class="btn btn-racing">
                                <i class="bi bi-pencil me-1"></i>Edit Profile
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>