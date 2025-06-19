<?php
/**
 * Racing League Management System - Homepage
 * Shows latest news, upcoming races, and current standings
 */

require_once 'config/config.php';

$page_title = 'Home';

// Get database connection
$db = new Database();
$conn = $db->getConnection();

// Get current season
$seasonQuery = "SELECT * FROM seasons WHERE is_active = TRUE ORDER BY year DESC LIMIT 1";
$seasonStmt = $conn->prepare($seasonQuery);
$seasonStmt->execute();
$currentSeason = $seasonStmt->fetch();

// Get next race
$nextRaceQuery = "
    SELECT * FROM races 
    WHERE season_id = :season_id AND race_date > NOW() AND status = 'Scheduled'
    ORDER BY race_date ASC LIMIT 1
";
$nextRaceStmt = $conn->prepare($nextRaceQuery);
$nextRaceStmt->bindParam(':season_id', $currentSeason['id']);
$nextRaceStmt->execute();
$nextRace = $nextRaceStmt->fetch();

// Get recent news
$newsQuery = "
    SELECT n.*, u.username as author_name 
    FROM news n 
    LEFT JOIN users u ON n.author_id = u.id 
    WHERE n.published = TRUE 
    ORDER BY n.created_at DESC LIMIT 3
";
$newsStmt = $conn->prepare($newsQuery);
$newsStmt->execute();
$recentNews = $newsStmt->fetchAll();

// Get current standings (top 10)
$standings = [];
if ($currentSeason) {
    $standings = calculateStandings($currentSeason['id']);
    $standings = array_slice($standings, 0, 10); // Top 10 only
}

// Get recent race results
$recentRaceQuery = "
    SELECT r.*, COUNT(rr.id) as participants
    FROM races r
    LEFT JOIN race_results rr ON r.id = rr.race_id
    WHERE r.season_id = :season_id AND r.status = 'Completed'
    GROUP BY r.id
    ORDER BY r.race_date DESC LIMIT 3
";
$recentRaceStmt = $conn->prepare($recentRaceQuery);
$recentRaceStmt->bindParam(':season_id', $currentSeason['id']);
$recentRaceStmt->execute();
$recentRaces = $recentRaceStmt->fetchAll();

include 'includes/header.php';
?>

<!-- Hero Section -->
<div class="racing-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1 class="display-4 fw-bold mb-3">
                    <i class="bi bi-flag-checkered me-3"></i>
                    Welcome to Racing League
                </h1>
                <p class="lead mb-4">
                    Professional sim racing championship management. Track standings, manage races, and follow your favorite drivers.
                </p>
                <?php if (!isLoggedIn()): ?>
                    <div class="d-flex gap-3">
                        <a href="register.php" class="btn btn-racing btn-lg">
                            <i class="bi bi-person-add me-2"></i>Join as Driver
                        </a>
                        <a href="standings.php" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-trophy me-2"></i>View Standings
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-lg-4 text-center">
                <?php if ($currentSeason): ?>
                    <div class="bg-light bg-opacity-10 rounded p-4">
                        <h3 class="text-warning mb-2"><?php echo htmlspecialchars($currentSeason['name']); ?></h3>
                        <p class="mb-0">Current Championship</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="container my-5">
    <div class="row">
        <!-- Next Race Section -->
        <div class="col-lg-8">
            <?php if ($nextRace): ?>
                <div class="card card-racing shadow-sm mb-4">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Next Race</h4>
                        <span class="badge bg-danger">Upcoming</span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h5 class="card-title text-primary"><?php echo htmlspecialchars($nextRace['name']); ?></h5>
                                <p class="card-text">
                                    <i class="bi bi-geo-alt text-muted me-1"></i>
                                    <strong>Track:</strong> <?php echo htmlspecialchars($nextRace['track']); ?>
                                </p>
                                <p class="card-text">
                                    <i class="bi bi-clock text-muted me-1"></i>
                                    <strong>Date:</strong> <?php echo formatDate($nextRace['race_date']); ?>
                                </p>
                                <p class="card-text">
                                    <i class="bi bi-flag text-muted me-1"></i>
                                    <strong>Format:</strong> <?php echo htmlspecialchars($nextRace['format']); ?>
                                    <?php if ($nextRace['laps']): ?>
                                        (<?php echo $nextRace['laps']; ?> laps)
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="race-countdown display-6" 
                                     id="race-countdown" 
                                     data-countdown="<?php echo $nextRace['race_date']; ?>">
                                </div>
                                <small class="text-muted">Time Remaining</small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recent News Section -->
            <?php if (!empty($recentNews)): ?>
                <div class="card card-racing shadow-sm mb-4">
                    <div class="card-header bg-dark text-white">
                        <h4 class="mb-0"><i class="bi bi-newspaper me-2"></i>Latest News</h4>
                    </div>
                    <div class="card-body">
                        <?php foreach ($recentNews as $index => $article): ?>
                            <div class="row <?php echo $index < count($recentNews) - 1 ? 'border-bottom pb-3 mb-3' : ''; ?>">
                                <div class="col-md-8">
                                    <h6 class="mb-2">
                                        <a href="news_detail.php?id=<?php echo $article['id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($article['title']); ?>
                                        </a>
                                    </h6>
                                    <p class="text-muted small mb-2">
                                        <?php echo substr(strip_tags($article['content']), 0, 150); ?>...
                                    </p>
                                    <small class="text-muted">
                                        By <?php echo htmlspecialchars($article['author_name'] ?? 'Admin'); ?> • 
                                        <?php echo formatDate($article['created_at']); ?>
                                    </small>
                                </div>
                                <?php if ($article['image']): ?>
                                    <div class="col-md-4">
                                        <img src="<?php echo htmlspecialchars($article['image']); ?>" 
                                             alt="News image" class="img-fluid rounded">
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-center mt-3">
                            <a href="news.php" class="btn btn-outline-primary">
                                <i class="bi bi-arrow-right me-1"></i>View All News
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Current Standings -->
            <?php if (!empty($standings)): ?>
                <div class="card card-racing shadow-sm mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Championship Standings</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm standings-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Pos</th>
                                        <th>Driver</th>
                                        <th>Pts</th>
                                        <th>Wins</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($standings as $index => $driver): ?>
                                        <tr class="<?php 
                                            if ($index === 0) echo 'position-1';
                                            elseif ($index === 1) echo 'position-2';
                                            elseif ($index === 2) echo 'position-3';
                                        ?>">
                                            <td class="fw-bold"><?php echo $index + 1; ?></td>
                                            <td>
                                                <a href="driver.php?id=<?php echo $driver['id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($driver['username']); ?>
                                                </a>
                                                <?php if ($driver['driver_number']): ?>
                                                    <small class="text-muted">#<?php echo $driver['driver_number']; ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-bold"><?php echo $driver['total_points'] ?: 0; ?></td>
                                            <td><?php echo $driver['wins'] ?: 0; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer text-center">
                            <a href="standings.php" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-arrow-right me-1"></i>Full Standings
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recent Races -->
            <?php if (!empty($recentRaces)): ?>
                <div class="card card-racing shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-flag-checkered me-2"></i>Recent Races</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($recentRaces as $race): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="mb-1">
                                        <a href="race.php?id=<?php echo $race['id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($race['name']); ?>
                                        </a>
                                    </h6>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($race['track']); ?> • 
                                        <?php echo formatDate($race['race_date']); ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted"><?php echo $race['participants']; ?> drivers</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-center">
                            <a href="calendar.php" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-calendar-event me-1"></i>Full Calendar
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>