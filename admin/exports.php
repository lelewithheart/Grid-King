<?php
/**
 * Admin Export Management Interface
 * Comprehensive export functionality for race data
 */

require_once '../config/config.php';
require_once '../utils/ExportManager.php';

requireAdmin();

$page_title = 'Export Management';

// Get database connection
$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Initialize export manager
$exportManager = new ExportManager($_SESSION['user_id']);

// Handle export requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        try {
            $exportType = sanitizeInput($_POST['export_type'] ?? '');
            $dataType = sanitizeInput($_POST['data_type'] ?? '');
            $seasonId = !empty($_POST['season_id']) ? intval($_POST['season_id']) : null;
            $raceId = !empty($_POST['race_id']) ? intval($_POST['race_id']) : null;
            
            // Build filters
            $filters = [];
            if (!empty($_POST['date_from'])) {
                $filters['date_from'] = $_POST['date_from'];
            }
            if (!empty($_POST['date_to'])) {
                $filters['date_to'] = $_POST['date_to'];
            }
            if (!empty($_POST['severity'])) {
                $filters['severity'] = sanitizeInput($_POST['severity']);
            }
            
            // Perform export based on data type
            switch ($dataType) {
                case 'results':
                    $result = $exportManager->exportRaceResults($raceId, $seasonId, $filters);
                    break;
                case 'standings':
                    $result = $exportManager->exportStandings($seasonId, $filters);
                    break;
                case 'penalties':
                    $result = $exportManager->exportPenalties($seasonId, $raceId, $filters);
                    break;
                default:
                    throw new Exception('Invalid export data type');
            }
            
            if ($result['success']) {
                $success = "Export completed successfully! " . $result['record_count'] . " records exported.";
                // Auto-download
                echo "<script>
                    setTimeout(function() {
                        window.location.href = '" . $result['download_url'] . "';
                    }, 1000);
                </script>";
            }
            
        } catch (Exception $e) {
            $error = 'Export failed: ' . $e->getMessage();
        }
    }
}

// Get available seasons
$seasonsQuery = "SELECT id, name, is_active FROM seasons ORDER BY is_active DESC, created_at DESC";
$seasonsStmt = $conn->prepare($seasonsQuery);
$seasonsStmt->execute();
$seasons = $seasonsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent races
$racesQuery = "
    SELECT r.id, r.name, r.track, r.race_date, s.name as season_name
    FROM races r
    JOIN seasons s ON r.season_id = s.id
    ORDER BY r.race_date DESC
    LIMIT 50
";
$racesStmt = $conn->prepare($racesQuery);
$racesStmt->execute();
$races = $racesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get export history
$exportHistory = $exportManager->getExportHistory(20);

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><?php echo $page_title; ?></h1>
                <div>
                    <span class="badge bg-info">Export System v1.2.1</span>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Export Form -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-download me-2"></i>Create New Export</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="data_type" class="form-label">Data Type <span class="text-danger">*</span></label>
                                            <select class="form-select" id="data_type" name="data_type" required onchange="updateFormFields()">
                                                <option value="">Select Data Type</option>
                                                <option value="results">Race Results</option>
                                                <option value="standings">Championship Standings</option>
                                                <option value="penalties">Penalties</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="export_type" class="form-label">Export Format <span class="text-danger">*</span></label>
                                            <select class="form-select" id="export_type" name="export_type" required>
                                                <option value="csv">CSV (Comma Separated)</option>
                                                <option value="pdf" disabled>PDF (Coming Soon)</option>
                                                <option value="json" disabled>JSON (Coming Soon)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="season_id" class="form-label">Season</label>
                                            <select class="form-select" id="season_id" name="season_id" onchange="loadSeasonRaces()">
                                                <option value="">All Seasons</option>
                                                <?php foreach ($seasons as $season): ?>
                                                    <option value="<?php echo $season['id']; ?>" 
                                                            <?php echo $season['is_active'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($season['name']); ?>
                                                        <?php echo $season['is_active'] ? ' (Active)' : ''; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3" id="race_selection" style="display: none;">
                                            <label for="race_id" class="form-label">Specific Race (Optional)</label>
                                            <select class="form-select" id="race_id" name="race_id">
                                                <option value="">All Races</option>
                                                <?php foreach ($races as $race): ?>
                                                    <option value="<?php echo $race['id']; ?>">
                                                        <?php echo htmlspecialchars($race['name']); ?> - <?php echo htmlspecialchars($race['track']); ?>
                                                        (<?php echo date('M j, Y', strtotime($race['race_date'])); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Date Range Filters -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="date_from" class="form-label">From Date</label>
                                            <input type="date" class="form-control" id="date_from" name="date_from">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="date_to" class="form-label">To Date</label>
                                            <input type="date" class="form-control" id="date_to" name="date_to">
                                        </div>
                                    </div>
                                </div>

                                <!-- Penalty-specific filters -->
                                <div class="row" id="penalty_filters" style="display: none;">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="severity" class="form-label">Penalty Severity</label>
                                            <select class="form-select" id="severity" name="severity">
                                                <option value="">All Severities</option>
                                                <option value="warning">Warning</option>
                                                <option value="minor">Minor</option>
                                                <option value="major">Major</option>
                                                <option value="severe">Severe</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-download me-2"></i>Generate Export
                                    </button>
                                    <small class="text-muted align-self-center">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Max 10,000 records per export
                                    </small>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Export History -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-history me-2"></i>Recent Exports</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($exportHistory)): ?>
                                <p class="text-muted text-center">No exports yet</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($exportHistory as $export): ?>
                                        <div class="list-group-item p-2">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1">
                                                        <span class="badge bg-secondary me-1"><?php echo strtoupper($export['export_type']); ?></span>
                                                        <?php echo ucfirst($export['data_type']); ?>
                                                    </h6>
                                                    <p class="mb-1 small text-muted">
                                                        <?php echo $export['record_count']; ?> records
                                                        <?php if ($export['season_name']): ?>
                                                            • <?php echo htmlspecialchars($export['season_name']); ?>
                                                        <?php endif; ?>
                                                        <?php if ($export['race_name']): ?>
                                                            • <?php echo htmlspecialchars($export['race_name']); ?>
                                                        <?php endif; ?>
                                                    </p>
                                                    <small class="text-muted">
                                                        <?php echo date('M j, Y H:i', strtotime($export['created_at'])); ?>
                                                    </small>
                                                </div>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="/admin/exports/download.php?file=<?php echo urlencode($export['filename']); ?>" 
                                                       class="btn btn-outline-primary btn-sm" title="Download">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Export Statistics -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-bar me-2"></i>Export Statistics</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $totalExports = count($exportHistory);
                            $totalRecords = array_sum(array_column($exportHistory, 'record_count'));
                            $totalSize = array_sum(array_column($exportHistory, 'file_size'));
                            ?>
                            <div class="row text-center">
                                <div class="col-12 mb-2">
                                    <div class="h4 text-primary"><?php echo $totalExports; ?></div>
                                    <small class="text-muted">Total Exports</small>
                                </div>
                                <div class="col-12 mb-2">
                                    <div class="h5 text-success"><?php echo number_format($totalRecords); ?></div>
                                    <small class="text-muted">Records Exported</small>
                                </div>
                                <div class="col-12">
                                    <div class="h6 text-info"><?php echo formatBytes($totalSize); ?></div>
                                    <small class="text-muted">Total Size</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateFormFields() {
    const dataType = document.getElementById('data_type').value;
    const raceSelection = document.getElementById('race_selection');
    const penaltyFilters = document.getElementById('penalty_filters');
    
    // Show/hide race selection for results and penalties
    if (dataType === 'results' || dataType === 'penalties') {
        raceSelection.style.display = 'block';
    } else {
        raceSelection.style.display = 'none';
        document.getElementById('race_id').value = '';
    }
    
    // Show/hide penalty-specific filters
    if (dataType === 'penalties') {
        penaltyFilters.style.display = 'block';
    } else {
        penaltyFilters.style.display = 'none';
        document.getElementById('severity').value = '';
    }
}

function loadSeasonRaces() {
    // This would be enhanced with AJAX to load races for selected season
    // For now, we show all races
}

// Auto-hide success messages
setTimeout(function() {
    const alert = document.querySelector('.alert-success');
    if (alert) {
        alert.style.display = 'none';
    }
}, 5000);
</script>

<?php
function formatBytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

include '../includes/footer.php';
?>
