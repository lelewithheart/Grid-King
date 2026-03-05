<?php
/**
 * Admin – Debug Panel (Legacy 1.4.1)
 * System diagnostics and troubleshooting tools.
 */

require_once '../config/config.php';
requireAdmin();

$db   = new Database();
$conn = $db->getConnection();

// Gather system information
$systemInfo = [];

// PHP Info
$systemInfo['php'] = [
    'version' => PHP_VERSION,
    'sapi' => php_sapi_name(),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'extensions' => get_loaded_extensions()
];

// Required extensions check
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'curl', 'mbstring', 'openssl', 'zip'];
$systemInfo['extensions_check'] = [];
foreach ($requiredExtensions as $ext) {
    $systemInfo['extensions_check'][$ext] = extension_loaded($ext);
}

// Server Info
$systemInfo['server'] = [
    'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
    'request_time' => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] ?? time())
];

// Database Info
try {
    $stmt = $conn->query("SELECT VERSION() as version");
    $systemInfo['database']['version'] = $stmt->fetch()['version'];
    
    $stmt = $conn->query("SHOW VARIABLES LIKE 'max_connections'");
    $systemInfo['database']['max_connections'] = $stmt->fetch()['Value'] ?? 'Unknown';
    
    $stmt = $conn->query("SHOW STATUS LIKE 'Threads_connected'");
    $systemInfo['database']['active_connections'] = $stmt->fetch()['Value'] ?? 'Unknown';
    
    $systemInfo['database']['connected'] = true;
} catch (Exception $e) {
    $systemInfo['database']['connected'] = false;
    $systemInfo['database']['error'] = $e->getMessage();
}

// GridKing Info
$systemInfo['gridking'] = [
    'app_version' => APP_VERSION,
    'base_url' => BASE_URL,
    'upload_dir' => UPLOAD_DIR,
    'upload_dir_writable' => is_writable(UPLOAD_DIR),
    'plugins_dir' => defined('PLUGINS_DIR') ? PLUGINS_DIR : 'Not defined',
    'plugins_dir_exists' => defined('PLUGINS_DIR') && is_dir(PLUGINS_DIR)
];

// Disk Space
$systemInfo['disk'] = [
    'total' => function_exists('disk_total_space') ? round(disk_total_space('/') / 1024 / 1024 / 1024, 2) : 'N/A',
    'free' => function_exists('disk_free_space') ? round(disk_free_space('/') / 1024 / 1024 / 1024, 2) : 'N/A'
];

// Check for common issues
$warnings = [];
$errors = [];

// Check upload directory
if (!is_dir(UPLOAD_DIR)) {
    $errors[] = 'Upload directory does not exist: ' . UPLOAD_DIR;
} elseif (!is_writable(UPLOAD_DIR)) {
    $warnings[] = 'Upload directory is not writable: ' . UPLOAD_DIR;
}

// Check logs directory
$logsDir = __DIR__ . '/../logs';
if (!is_dir($logsDir)) {
    $warnings[] = 'Logs directory does not exist: ' . $logsDir;
} elseif (!is_writable($logsDir)) {
    $warnings[] = 'Logs directory is not writable: ' . $logsDir;
}

// Check required extensions
foreach ($systemInfo['extensions_check'] as $ext => $loaded) {
    if (!$loaded) {
        $errors[] = "Required PHP extension not loaded: $ext";
    }
}

// Check PHP version
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    $warnings[] = 'PHP version 8.0+ is recommended. Current: ' . PHP_VERSION;
}

// Memory check
$memoryLimit = ini_get('memory_limit');
$memoryBytes = preg_replace_callback('/(\d+)([MG])/i', function($m) {
    return $m[1] * ($m[2] == 'G' ? 1073741824 : 1048576);
}, $memoryLimit);
if ($memoryBytes < 134217728) { // 128MB
    $warnings[] = 'Memory limit is low (' . $memoryLimit . '). Recommend at least 128M.';
}

// Recent error log entries (if accessible)
$recentErrors = [];
$errorLogPath = __DIR__ . '/../logs/app.log';
if (file_exists($errorLogPath) && is_readable($errorLogPath)) {
    $logContent = file_get_contents($errorLogPath);
    $logLines = array_filter(explode("\n", $logContent));
    $recentErrors = array_slice($logLines, -20);
}

// Settings check
try {
    $stmt = $conn->prepare("SELECT `key`, `value` FROM settings LIMIT 50");
    $stmt->execute();
    $systemInfo['settings'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $systemInfo['settings'] = [];
}

$page_title = 'Debug Panel';
include '../includes/header.php';
?>

<div class="container my-5">
    <div class="d-flex align-items-center mb-4 gap-3">
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="mb-0"><i class="bi bi-bug me-2"></i>Debug Panel</h1>
        <span class="badge bg-info">Legacy 1.4.1</span>
    </div>
    
    <!-- Status Overview -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <h5><i class="bi bi-exclamation-octagon me-2"></i>Critical Issues</h5>
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($warnings)): ?>
        <div class="alert alert-warning">
            <h5><i class="bi bi-exclamation-triangle me-2"></i>Warnings</h5>
            <ul class="mb-0">
                <?php foreach ($warnings as $warn): ?>
                    <li><?php echo htmlspecialchars($warn); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (empty($errors) && empty($warnings)): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>All system checks passed. No issues detected.
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- GridKing Info -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-app me-2"></i>GridKing</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <th width="40%">Version</th>
                            <td><span class="badge bg-primary"><?php echo htmlspecialchars($systemInfo['gridking']['app_version']); ?></span></td>
                        </tr>
                        <tr>
                            <th>Base URL</th>
                            <td class="font-monospace small"><?php echo htmlspecialchars($systemInfo['gridking']['base_url']); ?></td>
                        </tr>
                        <tr>
                            <th>Upload Directory</th>
                            <td>
                                <span class="badge <?php echo $systemInfo['gridking']['upload_dir_writable'] ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $systemInfo['gridking']['upload_dir_writable'] ? 'Writable' : 'Not Writable'; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Plugins Directory</th>
                            <td>
                                <span class="badge <?php echo $systemInfo['gridking']['plugins_dir_exists'] ? 'bg-success' : 'bg-warning'; ?>">
                                    <?php echo $systemInfo['gridking']['plugins_dir_exists'] ? 'Exists' : 'Not Found'; ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- PHP Info -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-filetype-php me-2"></i>PHP</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <th width="40%">Version</th>
                            <td><span class="badge bg-info"><?php echo htmlspecialchars($systemInfo['php']['version']); ?></span></td>
                        </tr>
                        <tr>
                            <th>SAPI</th>
                            <td><?php echo htmlspecialchars($systemInfo['php']['sapi']); ?></td>
                        </tr>
                        <tr>
                            <th>Memory Limit</th>
                            <td><?php echo htmlspecialchars($systemInfo['php']['memory_limit']); ?></td>
                        </tr>
                        <tr>
                            <th>Max Execution Time</th>
                            <td><?php echo htmlspecialchars($systemInfo['php']['max_execution_time']); ?>s</td>
                        </tr>
                        <tr>
                            <th>Upload Max Size</th>
                            <td><?php echo htmlspecialchars($systemInfo['php']['upload_max_filesize']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Database Info -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-database me-2"></i>Database</h5>
                </div>
                <div class="card-body">
                    <?php if ($systemInfo['database']['connected']): ?>
                        <table class="table table-sm mb-0">
                            <tr>
                                <th width="40%">Status</th>
                                <td><span class="badge bg-success">Connected</span></td>
                            </tr>
                            <tr>
                                <th>Version</th>
                                <td><?php echo htmlspecialchars($systemInfo['database']['version']); ?></td>
                            </tr>
                            <tr>
                                <th>Max Connections</th>
                                <td><?php echo htmlspecialchars($systemInfo['database']['max_connections']); ?></td>
                            </tr>
                            <tr>
                                <th>Active Connections</th>
                                <td><?php echo htmlspecialchars($systemInfo['database']['active_connections']); ?></td>
                            </tr>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-danger mb-0">
                            <strong>Not Connected</strong><br>
                            <?php echo htmlspecialchars($systemInfo['database']['error'] ?? 'Unknown error'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Server/Disk Info -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-server me-2"></i>Server</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <th width="40%">Software</th>
                            <td class="small"><?php echo htmlspecialchars($systemInfo['server']['software']); ?></td>
                        </tr>
                        <tr>
                            <th>Disk Total</th>
                            <td><?php echo $systemInfo['disk']['total']; ?> GB</td>
                        </tr>
                        <tr>
                            <th>Disk Free</th>
                            <td><?php echo $systemInfo['disk']['free']; ?> GB</td>
                        </tr>
                        <tr>
                            <th>Your IP</th>
                            <td class="font-monospace small"><?php echo htmlspecialchars($systemInfo['server']['remote_addr']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Extension Check -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="bi bi-puzzle me-2"></i>Required PHP Extensions</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($systemInfo['extensions_check'] as $ext => $loaded): ?>
                    <div class="col-md-3 col-6 mb-2">
                        <span class="badge <?php echo $loaded ? 'bg-success' : 'bg-danger'; ?> w-100 py-2">
                            <i class="bi <?php echo $loaded ? 'bi-check' : 'bi-x'; ?> me-1"></i>
                            <?php echo htmlspecialchars($ext); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Error Log -->
    <?php if (!empty($recentErrors)): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-journal-code me-2"></i>Recent Log Entries</h5>
                <span class="badge bg-secondary"><?php echo count($recentErrors); ?> entries</span>
            </div>
            <div class="card-body p-0">
                <pre class="mb-0 p-3 bg-light small" style="max-height: 300px; overflow-y: auto;"><?php 
                    foreach ($recentErrors as $line) {
                        echo htmlspecialchars($line) . "\n";
                    }
                ?></pre>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Current Settings -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="bi bi-sliders me-2"></i>Current Settings</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-sm table-striped mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Key</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($systemInfo['settings'] as $key => $value): ?>
                            <tr>
                                <td class="font-monospace small"><?php echo htmlspecialchars($key); ?></td>
                                <td class="small">
                                    <?php 
                                    if (strlen($value) > 100) {
                                        echo htmlspecialchars(substr($value, 0, 100)) . '...';
                                    } elseif (in_array($key, ['discord_webhook', 'api_key', 'secret'])) {
                                        echo '<span class="text-muted">[hidden]</span>';
                                    } else {
                                        echo htmlspecialchars($value ?: '<em class="text-muted">empty</em>');
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Quick Links -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="bi bi-link-45deg me-2"></i>Related Tools</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-2">
                    <a href="audit_log.php" class="btn btn-outline-primary w-100">
                        <i class="bi bi-journal-text me-2"></i>Audit Log
                    </a>
                </div>
                <div class="col-md-4 mb-2">
                    <a href="database_cleanup.php" class="btn btn-outline-warning w-100">
                        <i class="bi bi-database-gear me-2"></i>Database Cleanup
                    </a>
                </div>
                <div class="col-md-4 mb-2">
                    <a href="plugins.php" class="btn btn-outline-info w-100">
                        <i class="bi bi-puzzle me-2"></i>Plugins
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
