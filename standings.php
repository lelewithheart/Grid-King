<?php
/**
 * Championship Standings Page
 * This is the core feature - live standings with points calculation
 */

require_once 'config/config.php';

$page_title = 'Championship Standings';

// Get database connection
$db = new Database();
$conn = $db->getConnection();

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

// Get selected season (default to current)
$selectedSeasonId = $_GET['season'] ?? $currentSeason['id'] ?? 1;

// Get season details
$seasonDetailQuery = "SELECT * FROM seasons WHERE id = :id";
$seasonDetailStmt = $conn->prepare($seasonDetailQuery);
$seasonDetailStmt->bindParam(':id', $selectedSeasonId);
$seasonDetailStmt->execute();
$selectedSeason = $seasonDetailStmt->fetch();

// Calculate driver standings
$driverStandings = calculateStandings($selectedSeasonId);

// Calculate team standings
$teamStandingsQuery = "
    SELECT 
        t.id,
        t.name,
        SUM(rr.points) as total_points,
        COUNT(CASE WHEN rr.position = 1 THEN 1 END) as wins,
        COUNT(CASE WHEN rr.pole_position = TRUE THEN 1 END) as poles,
        COUNT(CASE WHEN rr.fastest_lap = TRUE THEN 1 END) as fastest_laps,
        COUNT(DISTINCT d.id) as drivers_count
    FROM teams t
    LEFT JOIN drivers d ON t.id = d.team_id
    LEFT JOIN race_results rr ON d.id = rr.driver_id
    LEFT JOIN races r ON rr.race_id = r.id
    WHERE r.season_id = :season_id OR r.id IS NULL
    GROUP BY t.id, t.name
    HAVING total_points > 0 OR drivers_count > 0
    ORDER BY total_points DESC, wins DESC
";
$teamStandingsStmt = $conn->prepare($teamStandingsQuery);
$teamStandingsStmt->bindParam(':season_id', $selectedSeasonId);
$teamStandingsStmt->execute();
$teamStandings = $teamStandingsStmt->fetchAll();

// Get races for this season
$racesQuery = "
    SELECT r.*, 
           COUNT(rr.id) as participants,
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

include 'includes/header.php';
?>

<div class="racing-header">
    <div class="container">
        <h1 class="display-5 fw-bold mb-3">
            <i class="bi bi-trophy me-3"></i>Championship Standings
        </h1>
        <p class="lead mb-0">
            <?php echo htmlspecialchars($selectedSeason['name'] ?? 'Championship'); ?> • 
            Real-time points and rankings
        </p>
    </div>
</div>

<div class="container my-5">
    <!-- Season Selector -->
    <div class="row mb-4">
        <div class="col-md-6">
            <form method="GET" action="">
                <div class="input-group">
                    <label class="input-group-text" for="season">
                        <i class="bi bi-calendar me-1"></i>Season
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
            <div class="d-flex gap-2 justify-content-md-end">
                <button class="btn btn-outline-primary" onclick="showTab('drivers')">
                    <i class="bi bi-person me-1"></i>Drivers
                </button>
                <button class="btn btn-outline-success" onclick="showTab('teams')">
                    <i class="bi bi-shield me-1"></i>Teams
                </button>
                <button class="btn btn-outline-info" onclick="showTab('races')">
                    <i class="bi bi-flag me-1"></i>Races
                </button>
            </div>
        </div>
    </div>

    <!-- Driver Standings Tab -->
    <div id="drivers-tab">
        <div class="card card-racing shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="bi bi-person-check me-2"></i>Driver Standings</h4>
                <span class="badge bg-primary"><?php echo count($driverStandings); ?> Drivers</span>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($driverStandings)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover standings-table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center">Pos</th>
                                    <th>Driver</th>
                                    <th class="text-center">Team</th>
                                    <th class="text-center">Points</th>
                                    <th class="text-center">Wins</th>
                                    <th class="text-center">Poles</th>
                                    <th class="text-center">Fastest</th>
                                    <th class="text-center">DNFs</th>
                                    <th class="text-center">Avg Finish</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($driverStandings as $index => $driver): ?>
                                    <tr class="<?php 
                                        if ($index === 0) echo 'position-1';
                                        elseif ($index === 1) echo 'position-2';
                                        elseif ($index === 2) echo 'position-3';
                                    ?>">
                                        <td class="text-center fw-bold fs-5">
                                            <?php echo $index + 1; ?>
                                            <?php if ($index === 0): ?>
                                                <i class="bi bi-trophy text-warning ms-1"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div>
                                                    <a href="driver.php?id=<?php echo $driver['id']; ?>" 
                                                       class="text-decoration-none fw-bold">
                                                        <?php echo htmlspecialchars($driver['username']); ?>
                                                    </a>
                                                    <?php if ($driver['driver_number']): ?>
                                                        <span class="badge bg-secondary ms-2">
                                                            #<?php echo $driver['driver_number']; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($driver['team_name']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($driver['team_name']); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">Independent</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="fw-bold fs-5 text-primary">
                                                <?php echo $driver['total_points'] ?: 0; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($driver['wins'] > 0): ?>
                                                <span class="badge bg-success"><?php echo $driver['wins']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($driver['poles'] > 0): ?>
                                                <span class="badge bg-info"><?php echo $driver['poles']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($driver['fastest_laps'] > 0): ?>
                                                <span class="badge bg-warning"><?php echo $driver['fastest_laps']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($driver['dnfs'] > 0): ?>
                                                <span class="text-danger"><?php echo $driver['dnfs']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($driver['avg_position']): ?>
                                                <small class="text-muted">
                                                    <?php echo number_format($driver['avg_position'], 1); ?>
                                                </small>
                                            <?php else: ?>
                                                <small class="text-muted">-</small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-info-circle display-1 text-muted"></i>
                        <h5 class="mt-3">No Driver Data Available</h5>
                        <p class="text-muted">Race results will appear here once races are completed.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Team Standings Tab -->
    <div id="teams-tab" style="display: none;">
        <div class="card card-racing shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="bi bi-shield-check me-2"></i>Team Standings</h4>
                <span class="badge bg-success"><?php echo count($teamStandings); ?> Teams</span>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($teamStandings)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover standings-table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center">Pos</th>
                                    <th>Team</th>
                                    <th class="text-center">Points</th>
                                    <th class="text-center">Wins</th>
                                    <th class="text-center">Poles</th>
                                    <th class="text-center">Fastest</th>
                                    <th class="text-center">Drivers</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teamStandings as $index => $team): ?>
                                    <tr class="<?php 
                                        if ($index === 0) echo 'position-1';
                                        elseif ($index === 1) echo 'position-2';
                                        elseif ($index === 2) echo 'position-3';
                                    ?>">
                                        <td class="text-center fw-bold fs-5">
                                            <?php echo $index + 1; ?>
                                            <?php if ($index === 0): ?>
                                                <i class="bi bi-trophy text-warning ms-1"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="team.php?id=<?php echo $team['id']; ?>" 
                                               class="text-decoration-none fw-bold">
                                                <?php echo htmlspecialchars($team['name']); ?>
                                            </a>
                                        </td>
                                        <td class="text-center">
                                            <span class="fw-bold fs-5 text-primary">
                                                <?php echo $team['total_points'] ?: 0; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($team['wins'] > 0): ?>
                                                <span class="badge bg-success"><?php echo $team['wins']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($team['poles'] > 0): ?>
                                                <span class="badge bg-info"><?php echo $team['poles']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($team['fastest_laps'] > 0): ?>
                                                <span class="badge bg-warning"><?php echo $team['fastest_laps']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary"><?php echo $team['drivers_count']; ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-info-circle display-1 text-muted"></i>
                        <h5 class="mt-3">No Team Data Available</h5>
                        <p class="text-muted">Team standings will appear here once races are completed.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Races Tab -->
    <div id="races-tab" style="display: none;">
        <div class="card card-racing shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="bi bi-flag-checkered me-2"></i>Season Races</h4>
                <span class="badge bg-info"><?php echo count($races); ?> Races</span>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($races)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Race</th>
                                    <th>Track</th>
                                    <th class="text-center">Date</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Winner</th>
                                    <th class="text-center">Participants</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($races as $race): ?>
                                    <tr>
                                        <td>
                                            <a href="race.php?id=<?php echo $race['id']; ?>" 
                                               class="text-decoration-none fw-semibold">
                                                <?php echo htmlspecialchars($race['name']); ?>
                                            </a>
                                            <div class="small text-muted"><?php echo htmlspecialchars($race['format']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($race['track']); ?></td>
                                        <td class="text-center">
                                            <small><?php echo formatDate($race['race_date']); ?></small>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $statusClass = match($race['status']) {
                                                'Completed' => 'success',
                                                'In Progress' => 'warning',
                                                'Scheduled' => 'primary',
                                                'Cancelled' => 'danger',
                                                default => 'secondary'
                                            };
                                            ?>
                                            <span class="badge bg-<?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($race['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($race['winner']): ?>
                                                <span class="fw-semibold text-success">
                                                    <?php echo htmlspecialchars($race['winner']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary"><?php echo $race['participants']; ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-calendar-x display-1 text-muted"></i>
                        <h5 class="mt-3">No Races Scheduled</h5>
                        <p class="text-muted">Race calendar will appear here once races are added.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all tabs
    document.getElementById('drivers-tab').style.display = 'none';
    document.getElementById('teams-tab').style.display = 'none';
    document.getElementById('races-tab').style.display = 'none';
    
    // Show selected tab
    document.getElementById(tabName + '-tab').style.display = 'block';
    
    // Update button states
    document.querySelectorAll('button[onclick*="showTab"]').forEach(btn => {
        btn.classList.remove('btn-primary', 'btn-success', 'btn-info');
        if (btn.onclick.toString().includes("'drivers'")) {
            btn.classList.add('btn-outline-primary');
        } else if (btn.onclick.toString().includes("'teams'")) {
            btn.classList.add('btn-outline-success');
        } else if (btn.onclick.toString().includes("'races'")) {
            btn.classList.add('btn-outline-info');
        }
    });
    
    // Highlight active button
    event.target.classList.remove('btn-outline-primary', 'btn-outline-success', 'btn-outline-info');
    if (tabName === 'drivers') {
        event.target.classList.add('btn-primary');
    } else if (tabName === 'teams') {
        event.target.classList.add('btn-success');  
    } else if (tabName === 'races') {
        event.target.classList.add('btn-info');
    }
}

// Set initial tab state
document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('button[onclick*="drivers"]').classList.remove('btn-outline-primary');
    document.querySelector('button[onclick*="drivers"]').classList.add('btn-primary');
});
</script>

<?php include 'includes/footer.php'; ?>