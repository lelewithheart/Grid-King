<?php
/**
 * Race Calendar Page - Shows race schedule with countdowns
 */

require_once 'config/config.php';

$page_title = 'Race Calendar';

// Get database connection
$db = new Database();
$conn = $db->getConnection();

// Check if user is logged in
$driverId = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT id FROM drivers WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $driverRow = $stmt->fetch();
    if ($driverRow) {
        $driverId = $driverRow['id'];
    }
}

// Get current season
$seasonQuery = "SELECT * FROM seasons WHERE is_active = TRUE ORDER BY year DESC LIMIT 1";
$seasonStmt = $conn->prepare($seasonQuery);
$seasonStmt->execute();
$currentSeason = $seasonStmt->fetch();

// Get all seasons for dropdown
$allSeasonsQuery = "SELECT * FROM seasons ORDER BY year DESC";
$allSeasonsStmt = $conn->prepare($allSeasonsQuery);
$allSeasonsStmt->execute();
$allSeasons = $allSeasonsStmt->fetchAll();

// Get selected season
$selectedSeasonId = $_GET['season'] ?? $currentSeason['id'] ?? 1;

// Get races for selected season
$racesQuery = "
    SELECT r.*, 
           COUNT(rr.id) as registered_drivers,
           MAX(CASE WHEN rr.position = 1 THEN u.username END) as winner
    FROM races r
    LEFT JOIN race_results rr ON r.id = rr.race_id
    LEFT JOIN drivers d ON rr.driver_id = d.id
    LEFT JOIN users u ON d.user_id = u.id
    WHERE r.season_id = :season_id
    GROUP BY r.id
    ORDER BY r.race_date ASC
";
$racesStmt = $conn->prepare($racesQuery);
$racesStmt->bindParam(':season_id', $selectedSeasonId);
$racesStmt->execute();
$races = $racesStmt->fetchAll();

// Separate races by status
$upcomingRaces = array_filter($races, fn($r) => strtotime($r['race_date']) > time() && $r['status'] === 'Scheduled');
$completedRaces = array_filter($races, fn($r) => $r['status'] === 'Completed');
$currentRaces = array_filter($races, fn($r) => $r['status'] === 'In Progress');

// check who is registered for each race
$registeredRaceIds = [];
if ($driverId) {
    $regStmt = $conn->prepare("SELECT race_id FROM race_registrations WHERE driver_id = :driver_id");
    $regStmt->bindParam(':driver_id', $driverId);
    $regStmt->execute();
    $registeredRaceIds = array_column($regStmt->fetchAll(), 'race_id');
}

// Helper: group races by month
function groupRacesByMonth($races) {
    $grouped = [];
    foreach ($races as $race) {
        $month = date('F Y', strtotime($race['race_date']));
        $grouped[$month][] = $race;
    }
    return $grouped;
}

include 'includes/header.php';
?>

<div class="racing-header">
    <div class="container">
        <h1 class="display-5 fw-bold mb-3">
            <i class="bi bi-calendar-event me-3"></i>Race Calendar
        </h1>
        <p class="lead mb-0">Championship schedule and race information</p>
    </div>
</div>

<div class="container my-5">
    <!-- Season Selector -->
    <div class="row mb-4">
        <div class="col-md-6">
            <form method="GET" action="">
                <div class="input-group">
                    <label class="input-group-text" for="season">
                        <i class="bi bi-calendar4-range me-1"></i>Season
                    </label>
                    <select class="form-select" id="season" name="season" onchange="this.form.submit()">
                        <?php foreach ($allSeasons as $season): ?>
                            <option value="<?php echo $season['id']; ?>" 
                                    <?php echo $season['id'] == $selectedSeasonId ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($season['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
        <div class="col-md-6 text-md-end">
            <div class="btn-group" role="group">
                <button class="btn btn-outline-primary" onclick="showSection('upcoming')">
                    <i class="bi bi-clock me-1"></i>Upcoming (<?php echo count($upcomingRaces); ?>)
                </button>
                <button class="btn btn-outline-success" onclick="showSection('completed')">
                    <i class="bi bi-check-circle me-1"></i>Completed (<?php echo count($completedRaces); ?>)
                </button>
                <?php if (!empty($currentRaces)): ?>
                    <button class="btn btn-outline-warning" onclick="showSection('current')">
                        <i class="bi bi-play-circle me-1"></i>Live (<?php echo count($currentRaces); ?>)
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Current/Live Races -->
    <?php if (!empty($currentRaces)): ?>
        <div id="current-section">
            <div class="alert alert-warning">
                <h5 class="alert-heading"><i class="bi bi-broadcast me-2"></i>Live Races</h5>
                <p class="mb-0">The following races are currently in progress:</p>
            </div>
            
            <div class="row">
                <?php foreach ($currentRaces as $race): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="card card-racing border-warning shadow-sm">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0">
                                    <i class="bi bi-broadcast me-2"></i>LIVE: <?php echo htmlspecialchars($race['name']); ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <p class="mb-2">
                                            <i class="bi bi-geo-alt text-muted me-1"></i>
                                            <strong>Track:</strong> <?php echo htmlspecialchars($race['track']); ?>
                                        </p>
                                        <p class="mb-2">
                                            <i class="bi bi-calendar text-muted me-1"></i>
                                            <strong>Started:</strong> <?php echo formatDate($race['race_date']); ?>
                                        </p>
                                        <p class="mb-0">
                                            <i class="bi bi-people text-muted me-1"></i>
                                            <strong>Drivers:</strong> <?php echo $race['registered_drivers']; ?>
                                        </p>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <span class="badge bg-danger fs-6">IN PROGRESS</span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <a href="race.php?id=<?php echo $race['id']; ?>" class="btn btn-warning w-100">
                                    <i class="bi bi-eye me-1"></i>Watch Live Results
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Upcoming Races (Grouped by Month) -->
    <div id="upcoming-section">
        <h3 class="mb-4"><i class="bi bi-clock me-2"></i>Upcoming Races</h3>
        <?php
        $upcomingByMonth = groupRacesByMonth($upcomingRaces);
        if (!empty($upcomingByMonth)):
            foreach ($upcomingByMonth as $month => $racesInMonth): ?>
                <h5 class="mt-4 mb-3 text-primary"><?= htmlspecialchars($month) ?></h5>
                <div class="row">
                    <?php foreach ($racesInMonth as $race): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="card card-racing h-100 shadow-sm">
                                <?php if ($race['track_image']): ?>
                                    <img src="<?php echo htmlspecialchars($race['track_image']); ?>" 
                                         class="card-img-top track-image" 
                                         alt="<?php echo htmlspecialchars($race['track']); ?>">
                                <?php endif; ?>
                                <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0"><?php echo htmlspecialchars($race['name']); ?></h5>
                                        <span class="badge bg-<?php 
                                            echo match($race['format']) {
                                                'Sprint' => 'warning',
                                                'Feature' => 'primary', 
                                                'Endurance' => 'info',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo htmlspecialchars($race['format']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <p class="mb-2">
                                                <i class="bi bi-geo-alt text-muted me-1"></i>
                                                <strong>Track:</strong> <?php echo htmlspecialchars($race['track']); ?>
                                            </p>
                                            <p class="mb-2">
                                                <i class="bi bi-calendar text-muted me-1"></i>
                                                <strong>Date:</strong> <?php echo formatDate($race['race_date']); ?>
                                            </p>
                                            <?php if ($race['laps']): ?>
                                                <p class="mb-2">
                                                    <i class="bi bi-arrow-repeat text-muted me-1"></i>
                                                    <strong>Laps:</strong> <?php echo $race['laps']; ?>
                                                </p>
                                            <?php endif; ?>
                                            <p class="mb-0">
                                                <i class="bi bi-people text-muted me-1"></i>
                                                <strong>Registered:</strong> <?php echo $race['registered_drivers']; ?> drivers
                                            </p>
                                        </div>
                                        <div class="col-md-4 text-center">
                                            <div class="race-countdown" 
                                                 id="countdown-<?php echo $race['id']; ?>" 
                                                 data-countdown="<?php echo $race['race_date']; ?>">
                                            </div>
                                            <small class="text-muted d-block">Time Until Race</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <div class="row g-2">
                                        <div class="col">
                                            <a href="race.php?id=<?php echo $race['id']; ?>" class="btn btn-outline-primary btn-sm w-100">
                                                <i class="bi bi-info-circle me-1"></i>Details
                                            </a>
                                        </div>
                                        <?php if (isDriver()): ?>
                                            <div class="col">
                                                <?php if ($driverId): ?>
                                                <?php if (in_array($race['id'], $registeredRaceIds)): ?>
                                                    <button class="btn btn-success btn-sm w-100" disabled>
                                                        <i class="bi bi-check-circle me-1"></i>Registered
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-primary btn-sm w-100"
                                                            onclick="registerForRace(<?php echo $race['id']; ?>, this)">
                                                        <i class="bi bi-plus-circle me-1"></i>Register
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach;
        else: ?>
            <div class="text-center py-5">
                <i class="bi bi-calendar-x display-1 text-muted"></i>
                <h4 class="mt-3">No Upcoming Races</h4>
                <p class="text-muted">Check back later for new race announcements.</p>
                <?php if (isAdmin()): ?>
                    <a href="admin/races.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Schedule New Race
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Completed Races (Grouped by Month) -->
    <div id="completed-section" style="display: none;">
        <h3 class="mb-4"><i class="bi bi-check-circle me-2"></i>Completed Races</h3>
        <?php
        $completedByMonth = groupRacesByMonth($completedRaces);
        if (!empty($completedByMonth)):
            foreach ($completedByMonth as $month => $racesInMonth): ?>
                <h5 class="mt-4 mb-3 text-success"><?= htmlspecialchars($month) ?></h5>
                <div class="row">
                    <?php foreach ($racesInMonth as $race): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="card card-racing h-100 shadow-sm">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><?php echo htmlspecialchars($race['name']); ?></h5>
                                    <span class="badge bg-success">Completed</span>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <p class="mb-2">
                                                <i class="bi bi-geo-alt text-muted me-1"></i>
                                                <strong>Track:</strong> <?php echo htmlspecialchars($race['track']); ?>
                                            </p>
                                            <p class="mb-2">
                                                <i class="bi bi-calendar text-muted me-1"></i>
                                                <strong>Date:</strong> <?php echo formatDate($race['race_date']); ?>
                                            </p>
                                            <p class="mb-2">
                                                <i class="bi bi-flag text-muted me-1"></i>
                                                <strong>Format:</strong> <?php echo htmlspecialchars($race['format']); ?>
                                            </p>
                                            <?php if ($race['winner']): ?>
                                                <p class="mb-0">
                                                    <i class="bi bi-trophy text-warning me-1"></i>
                                                    <strong>Winner:</strong> 
                                                    <span class="text-success fw-bold"><?php echo htmlspecialchars($race['winner']); ?></span>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4 text-center">
                                            <div class="text-muted">
                                                <i class="bi bi-people display-4"></i>
                                                <div class="fw-bold"><?php echo $race['registered_drivers']; ?></div>
                                                <small>Participants</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <a href="race.php?id=<?php echo $race['id']; ?>" class="btn btn-outline-success w-100">
                                        <i class="bi bi-trophy me-1"></i>View Results
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach;
        else: ?>
            <div class="text-center py-5">
                <i class="bi bi-calendar-check display-1 text-muted"></i>
                <h4 class="mt-3">No Completed Races</h4>
                <p class="text-muted">Race results will appear here after races are finished.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function showSection(section) {
    // Hide all sections
    document.getElementById('upcoming-section').style.display = 'none';
    document.getElementById('completed-section').style.display = 'none';
    if (document.getElementById('current-section')) {
        document.getElementById('current-section').style.display = 'none';
    }
    
    // Show selected section
    document.getElementById(section + '-section').style.display = 'block';
    
    // Update button states
    document.querySelectorAll('.btn-group .btn').forEach(btn => {
        btn.classList.remove('btn-primary', 'btn-success', 'btn-warning');
        btn.classList.add('btn-outline-primary', 'btn-outline-success', 'btn-outline-warning');
    });
    
    // Highlight active button
    event.target.classList.remove('btn-outline-primary', 'btn-outline-success', 'btn-outline-warning');
    if (section === 'upcoming') {
        event.target.classList.add('btn-primary');
    } else if (section === 'completed') {
        event.target.classList.add('btn-success');
    } else if (section === 'current') {
        event.target.classList.add('btn-warning');
    }
}

function registerForRace(raceId, btn) {
    if (!confirm('Register for this race?')) return;
    btn.disabled = true;
    fetch('register_for_race.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'race_id=' + encodeURIComponent(raceId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-success');
            btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Registered';
            btn.disabled = true;
        } else {
            alert(data.message || 'Registration failed.');
            btn.disabled = false;
        }
    })
    .catch(() => {
        alert('Registration failed.');
        btn.disabled = false;
    });
}

// Set initial section
document.addEventListener('DOMContentLoaded', function() {
    // Show upcoming races by default, or current if there are live races
    <?php if (!empty($currentRaces)): ?>
        showSection('current');
        document.querySelector('button[onclick*="current"]').classList.remove('btn-outline-warning');
        document.querySelector('button[onclick*="current"]').classList.add('btn-warning');
    <?php else: ?>
        document.querySelector('button[onclick*="upcoming"]').classList.remove('btn-outline-primary');
        document.querySelector('button[onclick*="upcoming"]').classList.add('btn-primary');
    <?php endif; ?>
});
</script>

<?php include 'includes/footer.php'; ?>