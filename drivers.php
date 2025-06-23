<?php
/**
 * All Drivers Page - Complete driver listings with profiles and live search
 */

require_once 'config/config.php';

$page_title = 'Drivers';

// Get database connection
$db = new Database();
$conn = $db->getConnection();

// Get all drivers with their stats
$driversQuery = "
    SELECT 
        d.*,
        u.username,
        u.email,
        t.name as team_name,
        t.id as team_id,
        COUNT(rr.id) as races_entered,
        SUM(rr.points) as total_points,
        COUNT(CASE WHEN rr.position = 1 THEN 1 END) as wins,
        COUNT(CASE WHEN rr.pole_position = TRUE THEN 1 END) as poles,
        COUNT(CASE WHEN rr.fastest_lap = TRUE THEN 1 END) as fastest_laps,
        COUNT(CASE WHEN rr.dnf = TRUE THEN 1 END) as dnfs,
        AVG(CASE WHEN rr.position IS NOT NULL THEN rr.position END) as avg_position
    FROM drivers d
    LEFT JOIN users u ON d.user_id = u.id
    LEFT JOIN teams t ON d.team_id = t.id
    LEFT JOIN race_results rr ON d.id = rr.driver_id
    GROUP BY d.id, u.username, u.email, t.name, t.id
    ORDER BY total_points DESC, wins DESC, u.username ASC
";
$driversStmt = $conn->prepare($driversQuery);
$driversStmt->execute();
$drivers = $driversStmt->fetchAll();

// Get current season for standings
$seasonQuery = "SELECT * FROM seasons WHERE is_active = TRUE ORDER BY year DESC LIMIT 1";
$seasonStmt = $conn->prepare($seasonQuery);
$seasonStmt->execute();
$currentSeason = $seasonStmt->fetch();

// Calculate current standings for position info
$standings = [];
if ($currentSeason) {
    $standings = calculateStandings($currentSeason['id']);
}

include 'includes/header.php';
?>

<div class="racing-header">
    <div class="container">
        <h1 class="display-5 fw-bold mb-3">
            <i class="bi bi-people me-3"></i>Championship Drivers
        </h1>
        <p class="lead mb-0">Meet the competitors in the <?php echo htmlspecialchars($currentSeason['name'] ?? 'Current Championship'); ?></p>
    </div>
</div>

<div class="container my-5">
    <!-- Driver Statistics Overview -->
    <div class="row mb-4">
        <div class="col-md-3 text-center">
            <h3 class="text-primary"><?php echo count($drivers); ?></h3>
            <p class="mb-0">Active Drivers</p>
        </div>
        <div class="col-md-3 text-center">
            <h3 class="text-success"><?php echo count(array_filter($drivers, fn($d) => $d['races_entered'] > 0)); ?></h3>
            <p class="mb-0">Race Participants</p>
        </div>
        <div class="col-md-3 text-center">
            <h3 class="text-warning"><?php echo count(array_unique(array_column($drivers, 'platform'))); ?></h3>
            <p class="mb-0">Platforms</p>
        </div>
        <div class="col-md-3 text-center">
            <h3 class="text-info"><?php echo count(array_unique(array_filter(array_column($drivers, 'team_name')))); ?></h3>
            <p class="mb-0">Teams</p>
        </div>
    </div>

    <!-- Driver Search -->
    <div class="row mb-4">
        <div class="col-md-6 mx-auto">
            <input type="text" id="driverSearch" class="form-control" placeholder="Search drivers by name, number, team, or country...">
        </div>
    </div>

    <!-- Drivers Grid -->
    <div class="row" id="driversGrid">
        <?php foreach ($drivers as $index => $driver): ?>
            <?php 
            // Find current championship position
            $currentPosition = null;
            foreach ($standings as $pos => $standingDriver) {
                if ($standingDriver['id'] == $driver['id']) {
                    $currentPosition = $pos + 1;
                    break;
                }
            }
            ?>
            <div class="col-lg-6 col-xl-4 mb-4">
                <div class="card card-racing h-100 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="bi bi-person-circle display-6 text-muted"></i>
                            </div>
                            <div>
                                <h5 class="mb-0">
                                    <a href="driver.php?id=<?php echo $driver['id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($driver['username']); ?>
                                    </a>
                                </h5>
                                <?php if ($driver['driver_number']): ?>
                                    <span class="badge bg-secondary">#<?php echo $driver['driver_number']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($currentPosition): ?>
                            <div class="text-end">
                                <div class="badge bg-<?php 
                                    echo $currentPosition == 1 ? 'warning' : ($currentPosition <= 3 ? 'success' : 'primary');
                                ?> fs-6">
                                    P<?php echo $currentPosition; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-body">
                        <!-- Driver Info -->
                        <div class="row mb-3">
                            <div class="col-6">
                                <small class="text-muted">Platform</small>
                                <div class="fw-semibold">
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
                            <div class="col-6">
                                <small class="text-muted">Team</small>
                                <div class="fw-semibold">
                                    <?php if ($driver['team_name']): ?>
                                        <a href="team.php?id=<?php echo $driver['team_id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($driver['team_name']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">Independent</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($driver['country']): ?>
                            <div class="mb-3">
                                <small class="text-muted">Country</small>
                                <div class="fw-semibold">
                                    <i class="bi bi-flag me-1"></i><?php echo htmlspecialchars($driver['country']); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Performance Stats -->
                        <div class="row text-center">
                            <div class="col-3">
                                <div class="fw-bold text-primary"><?php echo $driver['total_points'] ?: 0; ?></div>
                                <small class="text-muted">Points</small>
                            </div>
                            <div class="col-3">
                                <div class="fw-bold text-success"><?php echo $driver['wins'] ?: 0; ?></div>
                                <small class="text-muted">Wins</small>
                            </div>
                            <div class="col-3">
                                <div class="fw-bold text-info"><?php echo $driver['poles'] ?: 0; ?></div>
                                <small class="text-muted">Poles</small>
                            </div>
                            <div class="col-3">
                                <div class="fw-bold text-warning"><?php echo $driver['fastest_laps'] ?: 0; ?></div>
                                <small class="text-muted">FL</small>
                            </div>
                        </div>

                        <?php if ($driver['races_entered'] > 0): ?>
                            <hr>
                            <div class="row text-center small">
                                <div class="col-4">
                                    <div><?php echo $driver['races_entered']; ?></div>
                                    <small class="text-muted">Races</small>
                                </div>
                                <div class="col-4">
                                    <div><?php echo $driver['avg_position'] ? number_format($driver['avg_position'], 1) : '-'; ?></div>
                                    <small class="text-muted">Avg Pos</small>
                                </div>
                                <div class="col-4">
                                    <div class="text-danger"><?php echo $driver['dnfs'] ?: 0; ?></div>
                                    <small class="text-muted">DNFs</small>
                                </div>
                            </div>
                        <?php else: ?>
                            <hr>
                            <div class="text-center text-muted">
                                <small>No race entries yet</small>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-footer">
                        <div class="d-grid">
                            <a href="driver.php?id=<?php echo $driver['id']; ?>" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-person me-1"></i>View Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($drivers)): ?>
        <div class="text-center py-5">
            <i class="bi bi-people display-1 text-muted"></i>
            <h4 class="mt-3">No Drivers Found</h4>
            <p class="text-muted">No drivers have registered yet.</p>
            <?php if (!isLoggedIn()): ?>
                <a href="register.php" class="btn btn-primary">
                    <i class="bi bi-person-plus me-2"></i>Register as Driver
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Platform Distribution -->
    <?php if (!empty($drivers)): ?>
        <div class="row mt-5">
            <div class="col-lg-6">
                <div class="card card-racing shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Platform Distribution</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $platforms = array_count_values(array_column($drivers, 'platform'));
                        foreach ($platforms as $platform => $count):
                            $percentage = round(($count / count($drivers)) * 100);
                        ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>
                                    <i class="bi bi-<?php 
                                        echo match($platform) {
                                            'PC' => 'pc-display',
                                            'Xbox' => 'xbox', 
                                            'PlayStation' => 'playstation',
                                            default => 'controller'
                                        }; 
                                    ?> me-2"></i>
                                    <?php echo htmlspecialchars($platform); ?>
                                </span>
                                <div class="d-flex align-items-center">
                                    <div class="progress me-2" style="width: 100px; height: 20px;">
                                        <div class="progress-bar bg-primary" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                    <span class="fw-bold"><?php echo $count; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card card-racing shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Top Performers</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $topDrivers = array_slice($drivers, 0, 5);
                        foreach ($topDrivers as $index => $driver): 
                        ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-<?php 
                                        echo $index === 0 ? 'warning' : ($index < 3 ? 'success' : 'primary');
                                    ?> me-2">
                                        <?php echo $index + 1; ?>
                                    </span>
                                    <span class="fw-semibold"><?php echo htmlspecialchars($driver['username']); ?></span>
                                </div>
                                <span class="text-primary fw-bold"><?php echo $driver['total_points'] ?: 0; ?> pts</span>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center mt-3">
                            <a href="standings.php" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-trophy me-1"></i>Full Standings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('driverSearch').addEventListener('input', function() {
    const filter = this.value.toLowerCase();
    document.querySelectorAll('#driversGrid .card').forEach(card => {
        const text = card.textContent.toLowerCase();
        card.parentElement.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>

<?php include 'includes/footer.php'; ?>