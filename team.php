<?php
/**
 * Team Details Page
 */

require_once 'config/config.php';

$page_title = 'Team Details';

// Get database connection
$db = new Database();
$conn = $db->getConnection();

// Get team ID from query
$team_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$team_id) {
    header('Location: teams.php');
    exit;
}

// Fetch team info and stats
$teamQuery = "
    SELECT 
        t.*,
        uc.username as created_by_username,
        COUNT(d.id) as driver_count,
        SUM(rr.points) as total_points,
        COUNT(CASE WHEN rr.position = 1 THEN 1 END) as wins,
        COUNT(CASE WHEN rr.pole_position = TRUE THEN 1 END) as poles,
        COUNT(CASE WHEN rr.fastest_lap = TRUE THEN 1 END) as fastest_laps
    FROM teams t
    LEFT JOIN drivers d ON t.id = d.team_id
    LEFT JOIN race_results rr ON d.id = rr.driver_id
    LEFT JOIN users uc ON t.created_by = uc.id
    WHERE t.id = :team_id
    GROUP BY t.id
    LIMIT 1
";
$teamStmt = $conn->prepare($teamQuery);
$teamStmt->bindParam(':team_id', $team_id);
$teamStmt->execute();
$team = $teamStmt->fetch();

if (!$team) {
    header('Location: teams.php');
    exit;
}

// Fetch team drivers with stats
$driversQuery = "
    SELECT d.*, u.username, u.email, 
           SUM(rr.points) as driver_points,
           COUNT(rr.id) as races_entered,
           COUNT(CASE WHEN rr.position = 1 THEN 1 END) as wins,
           COUNT(CASE WHEN rr.pole_position = TRUE THEN 1 END) as poles,
           COUNT(CASE WHEN rr.fastest_lap = TRUE THEN 1 END) as fastest_laps
    FROM drivers d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN race_results rr ON d.id = rr.driver_id
    WHERE d.team_id = :team_id
    GROUP BY d.id
    ORDER BY driver_points DESC, u.username ASC
";
$driversStmt = $conn->prepare($driversQuery);
$driversStmt->bindParam(':team_id', $team_id);
$driversStmt->execute();
$drivers = $driversStmt->fetchAll();

include 'includes/header.php';
?>

<div class="racing-header">
    <div class="container">
        <h1 class="display-5 fw-bold mb-3">
            <i class="bi bi-shield me-3"></i><?php echo htmlspecialchars($team['name']); ?>
        </h1>
        <p class="lead mb-0">Team details, stats, and drivers</p>
    </div>
</div>

<div class="container my-5">
    <div class="row mb-4">
        <div class="col-md-3 text-center">
            <?php if ($team['logo']): ?>
                <img src="<?php echo htmlspecialchars($team['logo']); ?>" 
                     alt="<?php echo htmlspecialchars($team['name']); ?> logo"
                     class="rounded mb-3" style="width: 100px; height: 100px; object-fit: cover;">
            <?php else: ?>
                <i class="bi bi-shield-check display-1 text-primary"></i>
            <?php endif; ?>
            <div class="mt-2">
                <span class="badge bg-secondary"><?php echo $team['driver_count']; ?> drivers</span>
            </div>
        </div>
        <div class="col-md-9">
            <h2><?php echo htmlspecialchars($team['name']); ?></h2>
            <p class="mb-1">
                <strong>Founded by:</strong> <?php echo htmlspecialchars($team['created_by_username'] ?? 'Unknown'); ?>
            </p>
            <p class="mb-1">
                <strong>Created:</strong> <?php echo htmlspecialchars($team['created_at']); ?>
            </p>
            <div class="row text-center mt-3">
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
        </div>
    </div>

    <div class="card card-racing shadow-sm">
        <div class="card-header bg-dark text-white">
            <strong>Team Drivers</strong>
        </div>
        <div class="card-body">
            <?php if ($drivers): ?>
                <?php foreach ($drivers as $driver): ?>
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
                            <span class="badge bg-success ms-2"><?php echo $driver['wins'] ?: 0; ?> Wins</span>
                            <span class="badge bg-info ms-2"><?php echo $driver['poles'] ?: 0; ?> Poles</span>
                            <span class="badge bg-warning ms-2"><?php echo $driver['fastest_laps'] ?: 0; ?> FL</span>
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
    </div>
</div>

<?php include 'includes/footer.php'; ?>