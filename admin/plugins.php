<?php
/**
 * Admin – Plugins Management (Legacy 1.4.0)
 * Enable, disable, and manage installed plugins.
 */

require_once '../config/config.php';
require_once '../config/plugins.php';
requireAdmin();

$db   = new Database();
$conn = $db->getConnection();

$success = '';
$error   = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $pluginId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['plugin_id'] ?? '');
        
        if ($action === 'toggle' && $pluginId) {
            // Check if plugin exists in database
            $stmt = $conn->prepare("SELECT id, is_enabled FROM plugins WHERE plugin_id = :id");
            $stmt->execute([':id' => $pluginId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Toggle status
                $newStatus = $existing['is_enabled'] ? 0 : 1;
                $stmt = $conn->prepare("UPDATE plugins SET is_enabled = :status, updated_at = NOW() WHERE plugin_id = :id");
                $stmt->execute([':status' => $newStatus, ':id' => $pluginId]);
                $success = 'Plugin ' . ($newStatus ? 'enabled' : 'disabled') . ' successfully.';
            } else {
                // Insert new record (enabled by default toggle = disable)
                $stmt = $conn->prepare("INSERT INTO plugins (plugin_id, is_enabled, installed_at, updated_at) VALUES (:id, 0, NOW(), NOW())");
                $stmt->execute([':id' => $pluginId]);
                $success = 'Plugin disabled successfully.';
            }
        }
    }
}

// Discover all available plugins
$availablePlugins = PluginLoader::discoverPlugins();

// Get plugin status from database
$stmt = $conn->prepare("SELECT plugin_id, is_enabled, installed_at, updated_at FROM plugins");
$stmt->execute();
$pluginStatus = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $pluginStatus[$row['plugin_id']] = $row;
}

// Check feature toggle status
$stmt = $conn->prepare("SELECT feature_code, is_enabled FROM feature_toggles WHERE feature_code IN ('plugins', 'lite_mode')");
$stmt->execute();
$toggles = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $toggles[$row['feature_code']] = (bool)$row['is_enabled'];
}

$pluginsEnabled = $toggles['plugins'] ?? true;
$liteMode = $toggles['lite_mode'] ?? false;

$page_title = 'Plugin Management';
include '../includes/header.php';
?>

<div class="container my-5">
    <div class="d-flex align-items-center mb-4 gap-3">
        <a href="settings.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="mb-0"><i class="bi bi-puzzle me-2"></i>Plugin Management</h1>
        <span class="badge bg-info">Legacy 1.4.0</span>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Status Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card <?php echo $pluginsEnabled && !$liteMode ? 'border-success' : 'border-warning'; ?>">
                <div class="card-body text-center">
                    <i class="bi <?php echo $pluginsEnabled && !$liteMode ? 'bi-check-circle text-success' : 'bi-pause-circle text-warning'; ?> display-4"></i>
                    <h5 class="mt-2"><?php echo $pluginsEnabled && !$liteMode ? 'Plugins Active' : 'Plugins Disabled'; ?></h5>
                    <small class="text-muted">
                        <?php if ($liteMode): ?>
                            Lite Mode is enabled
                        <?php elseif (!$pluginsEnabled): ?>
                            Plugins feature is disabled
                        <?php else: ?>
                            System ready for plugins
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <i class="bi bi-folder2-open text-primary display-4"></i>
                    <h5 class="mt-2"><?php echo count($availablePlugins); ?> Plugins Found</h5>
                    <small class="text-muted">In /plugins directory</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <i class="bi bi-play-circle text-success display-4"></i>
                    <h5 class="mt-2">
                        <?php 
                        $enabledCount = 0;
                        foreach ($availablePlugins as $id => $p) {
                            $status = $pluginStatus[$id] ?? null;
                            if (!$status || $status['is_enabled']) $enabledCount++;
                        }
                        echo $enabledCount;
                        ?> Enabled
                    </h5>
                    <small class="text-muted">Ready to load</small>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($liteMode): ?>
        <div class="alert alert-warning">
            <i class="bi bi-lightning me-2"></i>
            <strong>Lite Mode Active:</strong> All plugins are currently disabled to improve performance. 
            <a href="feature_toggles.php">Disable Lite Mode</a> to enable plugin loading.
        </div>
    <?php elseif (!$pluginsEnabled): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            The plugins feature is disabled. <a href="feature_toggles.php">Enable it in Feature Toggles</a> to use plugins.
        </div>
    <?php endif; ?>
    
    <!-- Plugins List -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Installed Plugins</h5>
        </div>
        <div class="card-body">
            <?php if (empty($availablePlugins)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-puzzle display-1"></i>
                    <h4 class="mt-3">No Plugins Installed</h4>
                    <p>Create a new plugin by adding a directory to <code>/plugins/</code> with a <code>plugin.php</code> file.</p>
                    <a href="https://github.com/lelewithheart/Grid-King/blob/main/plugins/README.md" target="_blank" class="btn btn-outline-primary">
                        <i class="bi bi-book me-1"></i>Read Plugin Documentation
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Plugin</th>
                                <th>Version</th>
                                <th>Author</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($availablePlugins as $id => $plugin): 
                                $status = $pluginStatus[$id] ?? null;
                                $isEnabled = !$status || $status['is_enabled'];
                            ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($plugin['name']); ?></strong>
                                        <?php if ($plugin['has_manifest']): ?>
                                            <i class="bi bi-patch-check-fill text-success ms-1" title="Has manifest.json"></i>
                                        <?php endif; ?>
                                        <?php if ($plugin['description']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($plugin['description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($plugin['version']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($plugin['author']); ?></td>
                                    <td>
                                        <?php if ($isEnabled): ?>
                                            <span class="badge bg-success"><i class="bi bi-check me-1"></i>Enabled</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><i class="bi bi-x me-1"></i>Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="plugin_id" value="<?php echo htmlspecialchars($id); ?>">
                                            <button type="submit" class="btn btn-sm <?php echo $isEnabled ? 'btn-outline-warning' : 'btn-outline-success'; ?>">
                                                <?php echo $isEnabled ? '<i class="bi bi-pause"></i> Disable' : '<i class="bi bi-play"></i> Enable'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Plugin Development Help -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-code-slash me-2"></i>Plugin Development</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Creating a Plugin</h6>
                    <ol class="small">
                        <li>Create a directory in <code>/plugins/</code> (e.g., <code>/plugins/my-plugin/</code>)</li>
                        <li>Add a <code>plugin.php</code> file with your plugin code</li>
                        <li>Optionally add a <code>manifest.json</code> for metadata</li>
                        <li>Use <code>PluginHooks::register()</code> to hook into events</li>
                    </ol>
                </div>
                <div class="col-md-6">
                    <h6>Example Hook Usage</h6>
                    <pre class="bg-light p-2 rounded small"><code>PluginHooks::register('race_result_saved', function($data) {
    // Your code here
    return $data;
});</code></pre>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
