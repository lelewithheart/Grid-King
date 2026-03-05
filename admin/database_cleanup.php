<?php
/**
 * Admin – Database Cleanup & Season Reset (Legacy 1.4.1)
 * Tools for database maintenance and season archival/reset.
 */

require_once '../config/config.php';
requireAdmin();

$db   = new Database();
$conn = $db->getConnection();

$success = '';
$error   = '';
$stats = [];

// Get database statistics
try {
    // Table sizes
    $stmt = $conn->prepare("
        SELECT 
            TABLE_NAME as table_name,
            TABLE_ROWS as row_count,
            ROUND(DATA_LENGTH / 1024 / 1024, 2) as data_size_mb,
            ROUND(INDEX_LENGTH / 1024 / 1024, 2) as index_size_mb
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = DATABASE()
        ORDER BY DATA_LENGTH DESC
    ");
    $stmt->execute();
    $stats['tables'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Total database size
    $stmt = $conn->prepare("
        SELECT 
            ROUND(SUM(DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as total_size_mb
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = DATABASE()
    ");
    $stmt->execute();
    $stats['total_size'] = $stmt->fetch()['total_size_mb'];
    
    // Count records in key tables
    $keyTables = ['users', 'drivers', 'teams', 'races', 'race_results', 'penalties', 'seasons', 'audit_log', 'exports'];
    $stats['counts'] = [];
    foreach ($keyTables as $table) {
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM `$table`");
            $stmt->execute();
            $stats['counts'][$table] = $stmt->fetch()['cnt'];
        } catch (Exception $e) {
            $stats['counts'][$table] = 'N/A';
        }
    }
    
    // Get seasons
    $stmt = $conn->prepare("SELECT id, name, start_date, end_date, is_active FROM seasons ORDER BY start_date DESC");
    $stmt->execute();
    $stats['seasons'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = 'Error fetching database statistics: ' . $e->getMessage();
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        // Log the admin action
        $logStmt = $conn->prepare("INSERT INTO audit_log (user_id, action_type, details, ip_address, created_at) VALUES (:uid, :action, :details, :ip, NOW())");
        
        switch ($action) {
            case 'cleanup_exports':
                // Delete expired exports
                $stmt = $conn->prepare("DELETE FROM exports WHERE expires_at < NOW() OR created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
                $stmt->execute();
                $deleted = $stmt->rowCount();
                
                // Also clean up migration exports
                $stmt2 = $conn->prepare("DELETE FROM migration_exports WHERE expires_at < NOW() OR status = 'expired'");
                $stmt2->execute();
                $deleted2 = $stmt2->rowCount();
                
                $logStmt->execute([
                    ':uid' => $_SESSION['user_id'],
                    ':action' => 'cleanup_exports',
                    ':details' => json_encode(['deleted_exports' => $deleted, 'deleted_migrations' => $deleted2]),
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? ''
                ]);
                
                $success = "Cleaned up $deleted expired exports and $deleted2 expired migration exports.";
                break;
                
            case 'cleanup_audit_log':
                // Keep only last 90 days
                $stmt = $conn->prepare("DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
                $stmt->execute();
                $deleted = $stmt->rowCount();
                
                $logStmt->execute([
                    ':uid' => $_SESSION['user_id'],
                    ':action' => 'cleanup_audit_log',
                    ':details' => json_encode(['deleted_entries' => $deleted]),
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? ''
                ]);
                
                $success = "Removed $deleted audit log entries older than 90 days.";
                break;
                
            case 'optimize_tables':
                $optimized = [];
                foreach ($stats['tables'] as $table) {
                    try {
                        $conn->exec("OPTIMIZE TABLE `" . $table['table_name'] . "`");
                        $optimized[] = $table['table_name'];
                    } catch (Exception $e) {
                        // Skip tables that can't be optimized
                    }
                }
                
                $logStmt->execute([
                    ':uid' => $_SESSION['user_id'],
                    ':action' => 'optimize_tables',
                    ':details' => json_encode(['optimized_tables' => $optimized]),
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? ''
                ]);
                
                $success = "Optimized " . count($optimized) . " tables.";
                break;
                
            case 'archive_season':
                $seasonId = (int)($_POST['season_id'] ?? 0);
                if ($seasonId > 0) {
                    // Mark season as archived
                    $stmt = $conn->prepare("UPDATE seasons SET is_active = 0, archived_at = NOW() WHERE id = :id");
                    $stmt->execute([':id' => $seasonId]);
                    
                    $logStmt->execute([
                        ':uid' => $_SESSION['user_id'],
                        ':action' => 'archive_season',
                        ':details' => json_encode(['season_id' => $seasonId]),
                        ':ip' => $_SERVER['REMOTE_ADDR'] ?? ''
                    ]);
                    
                    $success = "Season archived successfully.";
                    
                    // Refresh seasons list
                    $stmt = $conn->prepare("SELECT id, name, start_date, end_date, is_active FROM seasons ORDER BY start_date DESC");
                    $stmt->execute();
                    $stats['seasons'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                break;
                
            case 'reset_season_data':
                $seasonId = (int)($_POST['season_id'] ?? 0);
                $confirmText = $_POST['confirm_text'] ?? '';
                
                if ($seasonId > 0 && $confirmText === 'RESET') {
                    // Get season info first
                    $stmt = $conn->prepare("SELECT name FROM seasons WHERE id = :id");
                    $stmt->execute([':id' => $seasonId]);
                    $seasonName = $stmt->fetch()['name'] ?? 'Unknown';
                    
                    // Delete race results for this season
                    $stmt = $conn->prepare("DELETE rr FROM race_results rr INNER JOIN races r ON rr.race_id = r.id WHERE r.season_id = :sid");
                    $stmt->execute([':sid' => $seasonId]);
                    $deletedResults = $stmt->rowCount();
                    
                    // Delete penalties for races in this season
                    $stmt = $conn->prepare("DELETE p FROM penalties p INNER JOIN races r ON p.race_id = r.id WHERE r.season_id = :sid");
                    $stmt->execute([':sid' => $seasonId]);
                    $deletedPenalties = $stmt->rowCount();
                    
                    // Reset race statuses
                    $stmt = $conn->prepare("UPDATE races SET status = 'Scheduled' WHERE season_id = :sid");
                    $stmt->execute([':sid' => $seasonId]);
                    
                    $logStmt->execute([
                        ':uid' => $_SESSION['user_id'],
                        ':action' => 'reset_season_data',
                        ':details' => json_encode([
                            'season_id' => $seasonId,
                            'season_name' => $seasonName,
                            'deleted_results' => $deletedResults,
                            'deleted_penalties' => $deletedPenalties
                        ]),
                        ':ip' => $_SERVER['REMOTE_ADDR'] ?? ''
                    ]);
                    
                    $success = "Season '$seasonName' reset: $deletedResults results and $deletedPenalties penalties deleted.";
                } else {
                    $error = 'Invalid confirmation. Type RESET to confirm.';
                }
                break;
        }
    }
}

$page_title = 'Database Cleanup';
include '../includes/header.php';
?>

<div class="container my-5">
    <div class="d-flex align-items-center mb-4 gap-3">
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="mb-0"><i class="bi bi-database-gear me-2"></i>Database Cleanup</h1>
        <span class="badge bg-info">Legacy 1.4.1</span>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Database Overview -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-hdd text-primary display-4"></i>
                    <h3 class="mt-2"><?php echo $stats['total_size'] ?? '?'; ?> MB</h3>
                    <p class="text-muted mb-0">Total Database Size</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-table text-success display-4"></i>
                    <h3 class="mt-2"><?php echo count($stats['tables'] ?? []); ?></h3>
                    <p class="text-muted mb-0">Tables</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-people text-info display-4"></i>
                    <h3 class="mt-2"><?php echo number_format($stats['counts']['users'] ?? 0); ?></h3>
                    <p class="text-muted mb-0">Users</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-flag-checkered text-warning display-4"></i>
                    <h3 class="mt-2"><?php echo number_format($stats['counts']['races'] ?? 0); ?></h3>
                    <p class="text-muted mb-0">Races</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Cleanup Actions -->
        <div class="col-md-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-trash3 me-2"></i>Cleanup Actions</h5>
                </div>
                <div class="card-body">
                    <form method="post" class="mb-3">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="cleanup_exports">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Clean Expired Exports</strong>
                                <p class="text-muted small mb-0">Remove export files older than 30 days</p>
                            </div>
                            <button type="submit" class="btn btn-outline-warning btn-sm">
                                <i class="bi bi-trash me-1"></i>Clean
                            </button>
                        </div>
                    </form>
                    
                    <hr>
                    
                    <form method="post" class="mb-3">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="cleanup_audit_log">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Trim Audit Log</strong>
                                <p class="text-muted small mb-0">Keep only last 90 days of entries</p>
                            </div>
                            <button type="submit" class="btn btn-outline-warning btn-sm">
                                <i class="bi bi-journal-x me-1"></i>Trim
                            </button>
                        </div>
                    </form>
                    
                    <hr>
                    
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="optimize_tables">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Optimize Tables</strong>
                                <p class="text-muted small mb-0">Defragment and reclaim disk space</p>
                            </div>
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-speedometer me-1"></i>Optimize
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Table Sizes -->
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Table Statistics</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Table</th>
                                    <th class="text-end">Rows</th>
                                    <th class="text-end">Size (MB)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['tables'] ?? [] as $table): ?>
                                    <tr>
                                        <td class="font-monospace small"><?php echo htmlspecialchars($table['table_name']); ?></td>
                                        <td class="text-end"><?php echo number_format($table['row_count']); ?></td>
                                        <td class="text-end"><?php echo $table['data_size_mb']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Season Management -->
        <div class="col-md-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-calendar-range me-2"></i>Season Management</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($stats['seasons'])): ?>
                        <p class="text-muted text-center">No seasons found.</p>
                    <?php else: ?>
                        <?php foreach ($stats['seasons'] as $season): ?>
                            <div class="card mb-3 <?php echo $season['is_active'] ? 'border-success' : 'border-secondary'; ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($season['name']); ?>
                                                <?php if ($season['is_active']): ?>
                                                    <span class="badge bg-success ms-2">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary ms-2">Archived</span>
                                                <?php endif; ?>
                                            </h6>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($season['start_date'])); ?> - 
                                                <?php echo $season['end_date'] ? date('M j, Y', strtotime($season['end_date'])) : 'Ongoing'; ?>
                                            </small>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($season['is_active']): ?>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Archive this season?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="archive_season">
                                                    <input type="hidden" name="season_id" value="<?php echo $season['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                                                        <i class="bi bi-archive"></i> Archive
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#resetModal<?php echo $season['id']; ?>">
                                                <i class="bi bi-exclamation-triangle"></i> Reset
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Reset Modal -->
                            <div class="modal fade" id="resetModal<?php echo $season['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header bg-danger text-white">
                                            <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Reset Season Data</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="post">
                                            <div class="modal-body">
                                                <div class="alert alert-danger">
                                                    <strong>Warning!</strong> This will permanently delete:
                                                    <ul class="mb-0 mt-2">
                                                        <li>All race results for this season</li>
                                                        <li>All penalties for races in this season</li>
                                                    </ul>
                                                </div>
                                                <p>Season: <strong><?php echo htmlspecialchars($season['name']); ?></strong></p>
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="action" value="reset_season_data">
                                                <input type="hidden" name="season_id" value="<?php echo $season['id']; ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Type <strong>RESET</strong> to confirm:</label>
                                                    <input type="text" name="confirm_text" class="form-control" required pattern="RESET" autocomplete="off">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Reset Season</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-link-45deg me-2"></i>Related Tools</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="audit_log.php" class="btn btn-outline-primary">
                            <i class="bi bi-journal-text me-2"></i>View Audit Log
                        </a>
                        <a href="debug_panel.php" class="btn btn-outline-secondary">
                            <i class="bi bi-bug me-2"></i>Debug Panel
                        </a>
                        <a href="../utils/migration.php" class="btn btn-outline-info">
                            <i class="bi bi-box-arrow-up me-2"></i>Export/Migration
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
