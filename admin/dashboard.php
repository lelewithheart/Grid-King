<?php
/**
 * Admin Dashboard - Racing League Management
 */

require_once '../config/config.php';

requireAdmin();

$page_title = 'Admin Dashboard';

// Get database connection
$db = new Database();
$conn = $db->getConnection();

// Get statistics
$stats = [];

// Total users
$userQuery = "SELECT COUNT(*) as count FROM users";
$userStmt = $conn->prepare($userQuery);
$userStmt->execute();
$stats['users'] = $userStmt->fetch()['count'];

// Total drivers
$driverQuery = "SELECT COUNT(*) as count FROM drivers";
$driverStmt = $conn->prepare($driverQuery);
$driverStmt->execute();
$stats['drivers'] = $driverStmt->fetch()['count'];

// Total races
$raceQuery = "SELECT COUNT(*) as count FROM races";
$raceStmt = $conn->prepare($raceQuery);
$raceStmt->execute();
$stats['races'] = $raceStmt->fetch()['count'];

// Completed races
$completedQuery = "SELECT COUNT(*) as count FROM races WHERE status = 'Completed'";
$completedStmt = $conn->prepare($completedQuery);
$completedStmt->execute();
$stats['completed_races'] = $completedStmt->fetch()['count'];

// Recent activities
$activityQuery = "
    SELECT 'race_result' as type, r.name, rr.created_at, u.username
    FROM race_results rr
    JOIN races r ON rr.race_id = r.id
    JOIN drivers d ON rr.driver_id = d.id  
    JOIN users u ON d.user_id = u.id
    WHERE rr.position = 1
    UNION ALL
    SELECT 'penalty' as type, CONCAT('Penalty: ', p.reason), p.created_at, u.username
    FROM penalties p
    JOIN drivers d ON p.driver_id = d.id
    JOIN users u ON d.user_id = u.id
    ORDER BY created_at DESC
    LIMIT 10
";
$activityStmt = $conn->prepare($activityQuery);
$activityStmt->execute();
$activities = $activityStmt->fetchAll();

// Pending races
$pendingQuery = "
    SELECT * FROM races 
    WHERE status = 'Scheduled' AND race_date > NOW()
    ORDER BY race_date ASC LIMIT 5
";
$pendingStmt = $conn->prepare($pendingQuery);
$pendingStmt->execute();
$pendingRaces = $pendingStmt->fetchAll();

include '../includes/header.php';
?>

<div class="racing-header">
    <div class="container">
        <h1 class="display-5 fw-bold mb-3">
            <i class="bi bi-speedometer2 me-3"></i>Admin Dashboard
        </h1>
        <p class="lead mb-0">Racing League Management System</p>
    </div>
</div>

<div class="container my-5">
    <!-- Statistics Cards -->
    <div class="row mb-5">
        <div class="col-md-3 mb-3">
            <div class="card card-racing h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-people display-4 text-primary mb-3"></i>
                    <h3 class="text-primary"><?php echo $stats['users']; ?></h3>
                    <p class="card-text">Total Users</p>
                    <a href="/admin/users.php" class="btn btn-outline-primary btn-sm">Manage</a>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card card-racing h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-person-check display-4 text-success mb-3"></i>
                    <h3 class="text-success"><?php echo $stats['drivers']; ?></h3>
                    <p class="card-text">Active Drivers</p>
                    <a href="/admin/drivers.php" class="btn btn-outline-success btn-sm">View</a>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card card-racing h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-flag-checkered display-4 text-warning mb-3"></i>
                    <h3 class="text-warning"><?php echo $stats['races']; ?></h3>
                    <p class="card-text">Total Races</p>
                    <a href="/admin/races.php" class="btn btn-outline-warning btn-sm">Manage</a>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card card-racing h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-trophy display-4 text-danger mb-3"></i>
                    <h3 class="text-danger"><?php echo $stats['completed_races']; ?></h3>
                    <p class="card-text">Completed</p>
                    <a href="/admin/results.php" class="btn btn-outline-danger btn-sm">Results</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Quick Actions -->
        <div class="col-lg-8">
            <div class="card card-racing shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h4 class="mb-0"><i class="bi bi-lightning-fill me-2"></i>Quick Actions</h4>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <a href="/admin/results.php" class="btn btn-racing w-100 d-flex align-items-center">
                                <i class="bi bi-plus-circle me-2"></i>
                                <div class="text-start">
                                    <div class="fw-bold">Add Race Results</div>
                                    <small>Input race positions and points</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="/admin/races.php" class="btn btn-outline-primary w-100 d-flex align-items-center">
                                <i class="bi bi-calendar-plus me-2"></i>
                                <div class="text-start">
                                    <div class="fw-bold">Create New Race</div>
                                    <small>Schedule upcoming races</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="/admin/penalties.php" class="btn btn-outline-warning w-100 d-flex align-items-center">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <div class="text-start">
                                    <div class="fw-bold">Apply Penalty</div>
                                    <small>Time penalties and point deductions</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="/admin/news.php" class="btn btn-outline-info w-100 d-flex align-items-center">
                                <i class="bi bi-newspaper me-2"></i>
                                <div class="text-start">
                                    <div class="fw-bold">Publish News</div>
                                    <small>Share updates and announcements</small>
                                </div>
                            </a>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Advanced Actions -->
                    <h6 class="text-muted mb-3">Advanced Management</h6>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <a href="/admin/seasons.php" class="btn btn-outline-secondary btn-sm w-100">
                                <i class="bi bi-calendar4-range me-1"></i>Seasons
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="/admin/teams.php" class="btn btn-outline-secondary btn-sm w-100">
                                <i class="bi bi-shield me-1"></i>Teams
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="/admin/settings.php" class="btn btn-outline-secondary btn-sm w-100">
                                <i class="bi bi-gear me-1"></i>Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <?php if (!empty($activities)): ?>
                <div class="card card-racing shadow-sm mt-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-activity me-2"></i>Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($activities as $activity): ?>
                            <div class="d-flex align-items-center mb-3">
                                <div class="me-3">
                                    <?php if ($activity['type'] === 'race_result'): ?>
                                        <i class="bi bi-trophy text-success"></i>
                                    <?php else: ?>
                                        <i class="bi bi-exclamation-triangle text-warning"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">
                                        <?php if ($activity['type'] === 'race_result'): ?>
                                            <?php echo htmlspecialchars($activity['username']); ?> won 
                                            <?php echo htmlspecialchars($activity['name']); ?>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($activity['name']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo formatDate($activity['created_at']); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Pending Races -->
            <?php if (!empty($pendingRaces)): ?>
                <div class="card card-racing shadow-sm mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-clock me-2"></i>Upcoming Races</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($pendingRaces as $race): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <div class="fw-semibold">
                                        <a href="race_detail.php?id=<?php echo $race['id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($race['name']); ?>
                                        </a>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($race['track']); ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted d-block">
                                        <?php echo formatDate($race['race_date']); ?>
                                    </small>
                                    <span class="badge bg-primary"><?php echo $race['format']; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-center mt-3">
                            <a href="races.php" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-calendar-event me-1"></i>Manage All Races
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- System Status -->
            <div class="card card-racing shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>System Status</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Database</span>
                        <span class="badge bg-success">Connected</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Active Season</span>
                        <span class="badge bg-primary">2025</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Points System</span>
                        <span class="badge bg-info">F1 Style</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Version</span>
                        <span class="badge bg-secondary"><?php echo APP_VERSION; ?></span>
                    </div>
                    
                    <hr>
                    
                    <div class="text-center">
                        <a href="../standings.php" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-trophy me-1"></i>View Live Standings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>