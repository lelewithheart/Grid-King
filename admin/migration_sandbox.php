<?php
/**
 * Migration Sandbox Test (Legacy 1.4.2)
 * Test export/import functionality in a safe environment before migrating.
 */

require_once '../config/config.php';
requireAdmin();

$db   = new Database();
$conn = $db->getConnection();

$success = '';
$error   = '';
$testResults = [];

// Handle POST action (run test)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $testType = $_POST['test_type'] ?? 'quick';
        
        $testResults = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $testType,
            'tests' => []
        ];
        
        // Test 1: Export Format Validation
        $test1 = ['name' => 'Export Format Validation', 'status' => 'passed', 'details' => ''];
        try {
            // Check if collectExportData function exists (from migration.php)
            if (function_exists('collectExportData')) {
                $test1['details'] = 'Export function available';
            } else {
                // Test data structure manually
                $testData = [
                    'metadata' => [
                        'app_name' => APP_NAME,
                        'app_version' => APP_VERSION,
                        'export_date' => date('c'),
                        'format_version' => '1.4.0'
                    ],
                    'data' => [
                        'users' => [],
                        'drivers' => [],
                        'teams' => [],
                        'seasons' => [],
                        'races' => [],
                        'race_results' => []
                    ]
                ];
                $json = json_encode($testData, JSON_PRETTY_PRINT);
                $decoded = json_decode($json, true);
                
                if ($decoded && isset($decoded['metadata']) && isset($decoded['data'])) {
                    $test1['details'] = 'Export structure valid. JSON encoding works.';
                } else {
                    throw new Exception('JSON encoding/decoding failed');
                }
            }
        } catch (Exception $e) {
            $test1['status'] = 'failed';
            $test1['details'] = $e->getMessage();
        }
        $testResults['tests'][] = $test1;
        
        // Test 2: Database Tables Check
        $test2 = ['name' => 'Database Tables Check', 'status' => 'passed', 'details' => ''];
        try {
            $requiredTables = ['users', 'drivers', 'teams', 'seasons', 'races', 'race_results', 'settings'];
            $missingTables = [];
            
            foreach ($requiredTables as $table) {
                $stmt = $conn->prepare("SHOW TABLES LIKE :table");
                $stmt->execute([':table' => $table]);
                if (!$stmt->fetch()) {
                    $missingTables[] = $table;
                }
            }
            
            if (empty($missingTables)) {
                $test2['details'] = 'All required tables present (' . count($requiredTables) . ' tables checked)';
            } else {
                throw new Exception('Missing tables: ' . implode(', ', $missingTables));
            }
        } catch (Exception $e) {
            $test2['status'] = 'failed';
            $test2['details'] = $e->getMessage();
        }
        $testResults['tests'][] = $test2;
        
        // Test 3: Data Integrity Check
        $test3 = ['name' => 'Data Integrity Check', 'status' => 'passed', 'details' => ''];
        try {
            // Check foreign key relationships
            $integrityChecks = [];
            
            // Drivers -> Users
            $stmt = $conn->query("SELECT COUNT(*) as cnt FROM drivers d LEFT JOIN users u ON d.user_id = u.id WHERE d.user_id IS NOT NULL AND u.id IS NULL");
            $orphanDrivers = $stmt->fetch()['cnt'];
            if ($orphanDrivers > 0) {
                $integrityChecks[] = "$orphanDrivers orphaned driver records";
            }
            
            // Race Results -> Races
            $stmt = $conn->query("SELECT COUNT(*) as cnt FROM race_results rr LEFT JOIN races r ON rr.race_id = r.id WHERE r.id IS NULL");
            $orphanResults = $stmt->fetch()['cnt'];
            if ($orphanResults > 0) {
                $integrityChecks[] = "$orphanResults orphaned result records";
            }
            
            if (empty($integrityChecks)) {
                $test3['details'] = 'All foreign key relationships valid';
            } else {
                $test3['status'] = 'warning';
                $test3['details'] = implode('; ', $integrityChecks);
            }
        } catch (Exception $e) {
            $test3['status'] = 'failed';
            $test3['details'] = $e->getMessage();
        }
        $testResults['tests'][] = $test3;
        
        // Test 4: Export Size Estimation
        $test4 = ['name' => 'Export Size Estimation', 'status' => 'passed', 'details' => ''];
        try {
            $totalRows = 0;
            $tables = ['users', 'drivers', 'teams', 'seasons', 'races', 'race_results', 'penalties', 'settings'];
            foreach ($tables as $table) {
                try {
                    $stmt = $conn->query("SELECT COUNT(*) as cnt FROM `$table`");
                    $totalRows += $stmt->fetch()['cnt'];
                } catch (Exception $e) {
                    // Table might not exist
                }
            }
            
            // Rough estimation: ~500 bytes per record average
            $estimatedSizeKB = round($totalRows * 0.5);
            $test4['details'] = "Estimated export size: ~{$estimatedSizeKB} KB ({$totalRows} records)";
            
            if ($estimatedSizeKB > 10240) { // > 10MB
                $test4['status'] = 'warning';
                $test4['details'] .= ' (Large export - may take longer)';
            }
        } catch (Exception $e) {
            $test4['status'] = 'failed';
            $test4['details'] = $e->getMessage();
        }
        $testResults['tests'][] = $test4;
        
        // Test 5: File System Permissions
        $test5 = ['name' => 'File System Permissions', 'status' => 'passed', 'details' => ''];
        try {
            $checks = [];
            
            // Check upload directory
            if (!is_writable(UPLOAD_DIR)) {
                $checks[] = 'Upload directory not writable';
            }
            
            // Check temp directory
            $tempDir = sys_get_temp_dir();
            if (!is_writable($tempDir)) {
                $checks[] = 'Temp directory not writable';
            }
            
            // Try creating a test file
            $testFile = $tempDir . '/gridking_test_' . time() . '.tmp';
            if (@file_put_contents($testFile, 'test') === false) {
                $checks[] = 'Cannot create temp files';
            } else {
                @unlink($testFile);
            }
            
            if (empty($checks)) {
                $test5['details'] = 'All file system permissions OK';
            } else {
                $test5['status'] = 'failed';
                $test5['details'] = implode('; ', $checks);
            }
        } catch (Exception $e) {
            $test5['status'] = 'failed';
            $test5['details'] = $e->getMessage();
        }
        $testResults['tests'][] = $test5;
        
        // Test 6: ZIP Extension
        $test6 = ['name' => 'ZIP Support', 'status' => 'passed', 'details' => ''];
        if (class_exists('ZipArchive')) {
            $test6['details'] = 'ZipArchive class available';
        } else {
            $test6['status'] = 'warning';
            $test6['details'] = 'ZipArchive not available - ZIP exports will not work';
        }
        $testResults['tests'][] = $test6;
        
        // Full test includes more checks
        if ($testType === 'full') {
            // Test 7: Actual mini-export test
            $test7 = ['name' => 'Mini Export Test', 'status' => 'passed', 'details' => ''];
            try {
                $miniExport = [
                    'metadata' => [
                        'app_version' => APP_VERSION,
                        'test_export' => true,
                        'timestamp' => date('c')
                    ],
                    'sample_data' => []
                ];
                
                // Get a sample of data
                $stmt = $conn->query("SELECT * FROM settings LIMIT 5");
                $miniExport['sample_data']['settings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $json = json_encode($miniExport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $decoded = json_decode($json, true);
                
                if ($decoded && json_last_error() === JSON_ERROR_NONE) {
                    $test7['details'] = 'Mini export successful (' . strlen($json) . ' bytes)';
                } else {
                    throw new Exception('JSON encoding error: ' . json_last_error_msg());
                }
            } catch (Exception $e) {
                $test7['status'] = 'failed';
                $test7['details'] = $e->getMessage();
            }
            $testResults['tests'][] = $test7;
            
            // Test 8: Version Compatibility
            $test8 = ['name' => 'Version Compatibility', 'status' => 'passed', 'details' => ''];
            try {
                $currentVersion = APP_VERSION;
                $supportedVersions = ['1.3.0', '1.3.1', '1.3.2', '1.4.0', '1.4.1', '1.4.2'];
                
                if (in_array($currentVersion, $supportedVersions)) {
                    $test8['details'] = "Version $currentVersion is supported for migration";
                } else {
                    $test8['status'] = 'warning';
                    $test8['details'] = "Version $currentVersion may require manual upgrade before migration";
                }
            } catch (Exception $e) {
                $test8['status'] = 'failed';
                $test8['details'] = $e->getMessage();
            }
            $testResults['tests'][] = $test8;
        }
        
        // Calculate summary
        $testResults['summary'] = [
            'passed' => count(array_filter($testResults['tests'], fn($t) => $t['status'] === 'passed')),
            'warnings' => count(array_filter($testResults['tests'], fn($t) => $t['status'] === 'warning')),
            'failed' => count(array_filter($testResults['tests'], fn($t) => $t['status'] === 'failed')),
            'total' => count($testResults['tests'])
        ];
        
        // Log the test run
        $logStmt = $conn->prepare("INSERT INTO audit_log (user_id, action_type, details, ip_address, created_at) VALUES (:uid, :action, :details, :ip, NOW())");
        $logStmt->execute([
            ':uid' => $_SESSION['user_id'],
            ':action' => 'migration_sandbox_test',
            ':details' => json_encode($testResults['summary']),
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
        
        if ($testResults['summary']['failed'] === 0) {
            $success = 'All migration tests passed! Your system is ready for migration.';
        } else {
            $error = $testResults['summary']['failed'] . ' test(s) failed. Please review the results below.';
        }
    }
}

$page_title = 'Migration Sandbox';
include '../includes/header.php';
?>

<div class="container my-5">
    <div class="d-flex align-items-center mb-4 gap-3">
        <a href="../utils/migration.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="mb-0"><i class="bi bi-box-seam me-2"></i>Migration Sandbox</h1>
        <span class="badge bg-info">Legacy 1.4.2</span>
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
    
    <div class="row">
        <div class="col-md-6">
            <!-- Run Tests -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-play-circle me-2"></i>Run Migration Tests</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Run these tests before migrating to the hosted platform (v2.0) to ensure your data is ready.
                    </p>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <div class="mb-3">
                            <label class="form-label">Test Type</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="test_type" value="quick" id="testQuick" checked>
                                <label class="form-check-label" for="testQuick">
                                    <strong>Quick Test</strong> - Basic checks (6 tests)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="test_type" value="full" id="testFull">
                                <label class="form-check-label" for="testFull">
                                    <strong>Full Test</strong> - Comprehensive checks (8 tests)
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-lightning me-2"></i>Run Tests
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- LTS Information -->
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-shield-check me-2"></i>LTS Support</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        <strong>Legacy 1.4</strong> is the final version of the self-hosted GridKing platform and will receive:
                    </p>
                    <ul class="mb-3">
                        <li>Security patches until Q4 2026</li>
                        <li>Critical bug fixes</li>
                        <li>Database compatibility updates</li>
                    </ul>
                    <p class="text-muted small mb-0">
                        For new features and cloud capabilities, migrate to GridKing v2.0 (Hosted Platform).
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <!-- Test Results -->
            <?php if (!empty($testResults)): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Test Results</h5>
                        <span class="badge bg-secondary"><?php echo $testResults['type'] === 'full' ? 'Full' : 'Quick'; ?> Test</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($testResults['tests'] as $test): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo htmlspecialchars($test['name']); ?></strong>
                                            <p class="mb-0 small text-muted"><?php echo htmlspecialchars($test['details']); ?></p>
                                        </div>
                                        <span class="badge <?php 
                                            echo $test['status'] === 'passed' ? 'bg-success' : 
                                                ($test['status'] === 'warning' ? 'bg-warning text-dark' : 'bg-danger'); 
                                        ?>">
                                            <?php echo ucfirst($test['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="row text-center">
                            <div class="col">
                                <span class="text-success fw-bold"><?php echo $testResults['summary']['passed']; ?></span>
                                <br><small class="text-muted">Passed</small>
                            </div>
                            <div class="col">
                                <span class="text-warning fw-bold"><?php echo $testResults['summary']['warnings']; ?></span>
                                <br><small class="text-muted">Warnings</small>
                            </div>
                            <div class="col">
                                <span class="text-danger fw-bold"><?php echo $testResults['summary']['failed']; ?></span>
                                <br><small class="text-muted">Failed</small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-body text-center py-5 text-muted">
                        <i class="bi bi-clipboard display-1"></i>
                        <h4 class="mt-3">No Test Results</h4>
                        <p>Run a test to see the results here.</p>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Migration Checklist -->
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Migration Checklist</h5>
                </div>
                <div class="card-body">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="check1">
                        <label class="form-check-label" for="check1">All sandbox tests passed</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="check2">
                        <label class="form-check-label" for="check2">Created full export backup</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="check3">
                        <label class="form-check-label" for="check3">Downloaded export file locally</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="check4">
                        <label class="form-check-label" for="check4">Verified export file integrity</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="check5">
                        <label class="form-check-label" for="check5">Read v2.0 migration documentation</label>
                    </div>
                    <hr>
                    <a href="../utils/migration.php" class="btn btn-outline-primary w-100">
                        <i class="bi bi-box-arrow-up me-2"></i>Go to Export/Migration
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
