<?php
/**
 * User Dashboard
 */

require_once 'config/config.php';

requireLogin();

$page_title = 'Dashboard';

// Get database connection
$db = new Database();
$conn = $db->getConnection();

// Get user's driver profile if they are a driver
$driverProfile = null;
if (isDriver()) {
    $driverQuery = "
        SELECT d.*, t.name as team_name
        FROM drivers d
        LEFT JOIN teams t ON d.team_id = t.id
        WHERE d.user_id = :user_id
    ";
    $driverStmt = $conn->prepare($driverQuery);
    $driverStmt->bindParam(':user_id', $_SESSION['user_id']);
    $driverStmt->execute();
    $driverProfile = $driverStmt->fetch();
}

// Get user's recent race results if driver
$recentResults = [];
if ($driverProfile) {
    $resultsQuery = "
        SELECT r.name as race_name, r.track, r.race_date, rr.position, rr.points, rr.dnf
        FROM race_results rr
        JOIN races r ON rr.race_id = r.id
        WHERE rr.driver_id = :driver_id
        ORDER BY r.race_date DESC
        LIMIT 5
    ";
    $resultsStmt = $conn->prepare($resultsQuery);
    $resultsStmt->bindParam(':driver_id', $driverProfile['id']);
    $resultsStmt->execute();
    $recentResults = $resultsStmt->fetchAll();
}

// Get current season standings position
$currentPosition = null;
if ($driverProfile) {
    $seasonQuery = "SELECT id FROM seasons WHERE is_active = TRUE LIMIT 1";
    $seasonStmt = $conn->prepare($seasonQuery);
    $seasonStmt->execute();
    $currentSeason = $seasonStmt->fetch();
    
    if ($currentSeason) {
        $standings = calculateStandings($currentSeason['id']);
        foreach ($standings as $index => $driver) {
            if ($driver['id'] == $driverProfile['id']) {
                $currentPosition = $index + 1;
                $currentPoints = $driver['total_points'];
                break;
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="racing-header">
    <div class="container">
        <h1 class="display-5 fw-bold mb-3">
            <i class="bi bi-speedometer2 me-3"></i>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!
        </h1>
        <p class="lead mb-0">
            <?php if (isAdmin()): ?>
                Racing League Administrator Dashboard
            <?php elseif (isDriver()): ?>
                Driver Dashboard - Track your racing performance
            <?php else: ?>
                Spectator Dashboard - Follow the championship
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="container my-5">
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <?php if (isAdmin()): ?>
                <!-- Admin Quick Actions -->
                <div class="card card-racing shadow-sm mb-4">
                    <div class="card-header bg-dark text-white">
                        <h4 class="mb-0"><i class="bi bi-lightning-fill me-2"></i>Admin Actions</h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <a href="admin/results.php" class="btn btn-racing w-100 d-flex align-items-center">
                                    <i class="bi bi-trophy me-3"></i>
                                    <div>
                                        <div class="fw-bold">Manage Race Results</div>
                                        <small>Add results and update standings</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="admin/dashboard.php" class="btn btn-outline-primary w-100 d-flex align-items-center">
                                    <i class="bi bi-speedometer2 me-3"></i>
                                    <div>
                                        <div class="fw-bold">Full Admin Dashboard</div>
                                        <small>Complete management interface</small>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isDriver() && $driverProfile): ?>
                <!-- Driver Performance -->
                <div class="card card-racing shadow-sm mb-4">
                    <div class="card-header bg-dark text-white">
                        <h4 class="mb-0"><i class="bi bi-person-check me-2"></i>Your Performance</h4>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <h3 class="text-primary"><?php echo $currentPosition ?: 'N/A'; ?></h3>
                                <p class="mb-0">Championship Position</p>
                            </div>
                            <div class="col-md-3">
                                <h3 class="text-success"><?php echo $currentPoints ?? 0; ?></h3>
                                <p class="mb-0">Total Points</p>
                            </div>
                            <div class="col-md-3">
                                <h3 class="text-warning"><?php echo count($recentResults); ?></h3>
                                <p class="mb-0">Races Completed</p>
                            </div>
                            <div class="col-md-3">
                                <h3 class="text-info">#<?php echo $driverProfile['driver_number'] ?: 'TBD'; ?></h3>
                                <p class="mb-0">Driver Number</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Results -->
                <?php if (!empty($recentResults)): ?>
                    <div class="card card-racing shadow-sm">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Race Results</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Race</th>
                                            <th>Track</th>
                                            <th class="text-center">Date</th>
                                            <th class="text-center">Position</th>
                                            <th class="text-center">Points</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentResults as $result): ?>
                                            <tr>
                                                <td class="fw-semibold"><?php echo htmlspecialchars($result['race_name']); ?></td>
                                                <td><?php echo htmlspecialchars($result['track']); ?></td>
                                                <td class="text-center">
                                                    <small><?php echo formatDate($result['race_date']); ?></small>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($result['dnf']): ?>
                                                        <span class="badge bg-danger">DNF</span>
                                                    <?php else: ?>
                                                        <span class="fw-bold"><?php echo $result['position']; ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="fw-bold text-primary"><?php echo $result['points']; ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!isDriver() || !$driverProfile): ?>
                <!-- Non-driver content -->
                <div class="card card-racing shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <h4 class="mb-0"><i class="bi bi-info-circle me-2"></i>Championship Information</h4>
                    </div>
                    <div class="card-body">
                        <p class="lead">Welcome to the Racing League Management System!</p>
                        
                        <?php if (!isDriver()): ?>
                            <div class="alert alert-info">
                                <h6 class="alert-heading">Want to become a driver?</h6>
                                <p class="mb-2">Join the championship and compete for the title!</p>
                                <a href="register.php" class="btn btn-primary btn-sm">
                                    <i class="bi bi-person-plus me-1"></i>Register as Driver
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row g-3 mt-3">
                            <div class="col-md-6">
                                <a href="standings.php" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-trophy me-2"></i>View Championship Standings
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="calendar.php" class="btn btn-outline-info w-100">
                                    <i class="bi bi-calendar-event me-2"></i>Race Calendar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Profile Card -->
            <div class="card card-racing shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-person-circle me-2"></i>Profile</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-person-circle display-1 text-muted"></i>
                        <h5 class="mt-2"><?php echo htmlspecialchars($_SESSION['username']); ?></h5>
                        <span class="badge bg-<?php 
                            echo match($_SESSION['role']) {
                                'admin' => 'danger',
                                'driver' => 'success', 
                                default => 'primary'
                            };
                        ?>">
                            <?php echo ucfirst($_SESSION['role']); ?>
                        </span>
                    </div>
                    
                    <?php if ($driverProfile): ?>
                        <div class="row text-center">
                            <div class="col-6">
                                <strong>Platform</strong>
                                <div class="text-muted"><?php echo htmlspecialchars($driverProfile['platform']); ?></div>
                            </div>
                            <div class="col-6">
                                <strong>Team</strong>
                                <div class="text-muted"><?php echo htmlspecialchars($driverProfile['team_name'] ?: 'Independent'); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2 mt-3">
                        <a href="profile.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-pencil me-1"></i>Edit Profile
                        </a>
                        <a href="logout.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-box-arrow-right me-1"></i>Logout
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="card card-racing shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-link me-2"></i>Quick Links</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="standings.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-trophy me-1"></i>Current Standings
                        </a>
                        <a href="drivers.php" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-people me-1"></i>All Drivers
                        </a>
                        <a href="teams.php" class="btn btn-outline-info btn-sm">
                            <i class="bi bi-shield me-1"></i>Teams
                        </a>
                        <a href="news.php" class="btn btn-outline-warning btn-sm">
                            <i class="bi bi-newspaper me-1"></i>Latest News
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>