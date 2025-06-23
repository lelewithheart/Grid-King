<?php
/**
 * Teams Page - Team listings and information
 */

require_once 'config/config.php';

$page_title = 'Teams';

// Get database connection
$db = new Database();
$conn = $db->getConnection();

// Get all teams with driver count and stats
$teamsQuery = "
    SELECT 
        t.*,
        COUNT(d.id) as driver_count,
        SUM(rr.points) as total_points,
        COUNT(CASE WHEN rr.position = 1 THEN 1 END) as wins,
        COUNT(CASE WHEN rr.pole_position = TRUE THEN 1 END) as poles,
        COUNT(CASE WHEN rr.fastest_lap = TRUE THEN 1 END) as fastest_laps,
        uc.username as created_by_username
    FROM teams t
    LEFT JOIN drivers d ON t.id = d.team_id
    LEFT JOIN race_results rr ON d.id = rr.driver_id
    LEFT JOIN users uc ON t.created_by = uc.id
    GROUP BY t.id
    ORDER BY total_points DESC, driver_count DESC, t.name ASC
";
$teamsStmt = $conn->prepare($teamsQuery);
$teamsStmt->execute();
$teams = $teamsStmt->fetchAll();

// Get independent drivers count
$independentQuery = "
    SELECT COUNT(*) as count 
    FROM drivers d 
    LEFT JOIN users u ON d.user_id = u.id 
    WHERE d.team_id IS NULL OR d.team_id = 1
";
$independentStmt = $conn->prepare($independentQuery);
$independentStmt->execute();
$independentCount = $independentStmt->fetch()['count'];

include 'includes/header.php';
?>

<div class="racing-header">
    <div class="container">
        <h1 class="display-5 fw-bold mb-3">
            <i class="bi bi-shield me-3"></i>Racing Teams
        </h1>
        <p class="lead mb-0">Championship teams and their drivers</p>
    </div>
</div>

<div class="container my-5">
    <!-- Team Statistics -->
    <div class="row mb-5">
        <div class="col-md-3 text-center">
            <h3 class="text-primary"><?php echo count($teams); ?></h3>
            <p class="mb-0">Total Teams</p>
        </div>
        <div class="col-md-3 text-center">
            <h3 class="text-success"><?php echo array_sum(array_column($teams, 'driver_count')); ?></h3>
            <p class="mb-0">Team Drivers</p>
        </div>
        <div class="col-md-3 text-center">
            <h3 class="text-warning"><?php echo $independentCount; ?></h3>
            <p class="mb-0">Independent Drivers</p>
        </div>
        <div class="col-md-3 text-center">
            <h3 class="text-info"><?php echo array_sum(array_column($teams, 'total_points')); ?></h3>
            <p class="mb-0">Total Points</p>
        </div>
    </div>

    <!-- Team Search -->
    <div class="row mb-4">
        <div class="col-md-6 mx-auto">
            <input type="text" id="teamSearch" class="form-control" placeholder="Search teams by name or driver...">
        </div>
    </div>

    <!-- Teams Grid -->
    <div class="row" id="teamsGrid">
        <?php foreach ($teams as $team): ?>
            <div class="col-lg-6 mb-4">
                <div class="card card-racing h-100 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <?php if ($team['logo']): ?>
                                    <img src="<?php echo htmlspecialchars($team['logo']); ?>" 
                                         alt="<?php echo htmlspecialchars($team['name']); ?> logo"
                                         class="rounded" style="width: 50px; height: 50px; object-fit: cover;">
                                <?php else: ?>
                                    <i class="bi bi-shield-check display-6 text-primary"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h5 class="mb-0">
                                    <a href="team.php?id=<?php echo $team['id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($team['name']); ?>
                                    </a>
                                </h5>
                                <small class="text-muted">
                                    Founded by <?php echo htmlspecialchars($team['created_by_username'] ?? 'Unknown'); ?>
                                </small>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-secondary"><?php echo $team['driver_count']; ?> drivers</span>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Team Performance Stats -->
                        <div class="row text-center mb-3">
                            <div class="col-3">
                                <div class="fw-bold text-primary"><?php echo $team['total_points'] ?: 0; ?></div>
                                <small class="text-muted">Points</small>
                            </div>
                            <div class="col-3">
                                <div class="fw-bold text-success"><?php echo $team['wins'] ?: 0; ?></div>
                                <small class="text-muted">Wins</small>
                            </div>
                            <div class="col-3">
                                <div class="fw-bold text-info"><?php echo $team['poles'] ?: 0; ?></div>
                                <small class="text-muted">Poles</small>
                            </div>
                            <div class="col-3">
                                <div class="fw-bold text-warning"><?php echo $team['fastest_laps'] ?: 0; ?></div>
                                <small class="text-muted">FL</small>
                            </div>
                        </div>

                        <!-- Team Drivers -->
                        <?php if ($team['driver_count'] > 0): ?>
                            <?php
                            // Get team drivers
                            $driversQuery = "
                                SELECT d.*, u.username, u.email, 
                                       SUM(rr.points) as driver_points,
                                       COUNT(rr.id) as races_entered
                                FROM drivers d
                                JOIN users u ON d.user_id = u.id
                                LEFT JOIN race_results rr ON d.id = rr.driver_id
                                WHERE d.team_id = :team_id
                                GROUP BY d.id
                                ORDER BY driver_points DESC, u.username ASC
                            ";
                            $driversStmt = $conn->prepare($driversQuery);
                            $driversStmt->bindParam(':team_id', $team['id']);
                            $driversStmt->execute();
                            $teamDrivers = $driversStmt->fetchAll();
                            ?>
                            
                            <h6 class="mb-2">Team Drivers:</h6>
                            <?php foreach ($teamDrivers as $driver): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                    <div class="d-flex align-items-center">
                                        <a href="driver.php?id=<?php echo $driver['id']; ?>" class="text-decoration-none fw-semibold">
                                            <?php echo htmlspecialchars($driver['username']); ?>
                                        </a>
                                        <?php if ($driver['driver_number']): ?>
                                            <span class="badge bg-secondary ms-2">#<?php echo $driver['driver_number']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-primary"><?php echo $driver['driver_points'] ?: 0; ?> pts</div>
                                        <small class="text-muted"><?php echo $driver['races_entered']; ?> races</small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="bi bi-person-plus-fill text-muted display-4"></i>
                                <p class="text-muted mb-0">No drivers yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-footer">
                        <div class="d-grid">
                            <a href="team.php?id=<?php echo $team['id']; ?>" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-shield me-1"></i>View Team Details
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Create Team Section -->
    <?php if (isDriver()): ?>
        <div class="row mt-5">
            <div class="col-lg-8 mx-auto">
                <div class="card card-racing shadow-sm">
                    <div class="card-header bg-dark text-white text-center">
                        <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Create Your Own Team</h5>
                    </div>
                    <div class="card-body text-center">
                        <p class="mb-3">Want to start your own racing team? Recruit drivers and compete for the team championship!</p>
                        <a href="create_team.php" class="btn btn-racing">
                            <i class="bi bi-shield-plus me-2"></i>Create New Team
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Empty State -->
    <?php if (empty($teams)): ?>
        <div class="text-center py-5">
            <i class="bi bi-shield display-1 text-muted"></i>
            <h4 class="mt-3">No Teams Found</h4>
            <p class="text-muted">No teams have been created yet.</p>
            <?php if (isDriver()): ?>
                <a href="create_team.php" class="btn btn-primary">
                    <i class="bi bi-shield-plus me-2"></i>Create First Team
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('teamSearch').addEventListener('input', function() {
    const filter = this.value.toLowerCase();
    document.querySelectorAll('#teamsGrid .card').forEach(card => {
        const text = card.textContent.toLowerCase();
        card.parentElement.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>

<?php include 'includes/footer.php'; ?>