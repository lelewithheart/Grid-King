<?php
require_once '../config/config.php';
requireAdmin();
require_once '../config/scoringpresets.php'; // Contains $scoring_presets

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Fetch current settings
$stmt = $conn->prepare("SELECT `key`, `value` FROM settings");
$stmt->execute();
$settings = [];
foreach ($stmt->fetchAll() as $row) {
    $settings[$row['key']] = $row['value'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $league_name = sanitizeInput($_POST['league_name'] ?? '');
    $welcome_text = trim($_POST['welcome_text'] ?? '');
    $theme_color = $_POST['theme_color'] ?? '#dc2626';
    $points_system = $_POST['points_system'] ?? '';
    $errors = [];
    $logo_path = null;

    // Handle logo upload
    if (isset($_FILES['league_logo']) && $_FILES['league_logo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['league_logo']['name'], PATHINFO_EXTENSION));
        $target = UPLOAD_DIR . 'league_logo.' . $ext;

        if (!in_array($ext, ALLOWED_IMAGE_TYPES)) {
            $errors[] = 'Invalid logo file type.';
        }
        if (!is_dir(UPLOAD_DIR)) {
            $errors[] = 'Target directory ' . realpath(UPLOAD_DIR) . ' not found';
        }
        if ($_FILES['league_logo']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Logo file is too large (max 2MB).';
        }

        if (count($errors) === 0) {
            if (move_uploaded_file($_FILES['league_logo']['tmp_name'], $target)) {
                $logo_path = 'uploads/league_logo.' . $ext;
            } else {
                $errors[] = 'Logo upload failed.';
            }
        }
    }

    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    }

    // Save settings
    $settingsToSave = [
        'league_name' => $league_name,
        'welcome_text' => $welcome_text,
        'theme_color' => $theme_color,
        'points_system' => $points_system
    ];
    if ($logo_path) {
        $settingsToSave['league_logo'] = $logo_path;
    }

    foreach ($settingsToSave as $key => $value) {
        $stmt = $conn->prepare("REPLACE INTO settings (`key`, `value`) VALUES (:key, :value)");
        $stmt->bindParam(':key', $key);
        $stmt->bindParam(':value', $value);
        $stmt->execute();
    }

    if (!$error) $success = 'Settings updated!';
    // Refresh settings for display
    $stmt = $conn->prepare("SELECT `key`, `value` FROM settings");
    $stmt->execute();
    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[$row['key']] = $row['value'];
    }
}

include '../includes/header.php';
?>

<div class="container my-5">
    <h1 class="mb-4"><i class="bi bi-gear me-2"></i>League Settings</h1>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="card card-body shadow-sm">
        <div class="mb-3">
            <label class="form-label">League Name</label>
            <input type="text" name="league_name" class="form-control" value="<?php echo htmlspecialchars($settings['league_name'] ?? APP_NAME); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Welcome Text</label>
            <textarea name="welcome_text" class="form-control" rows="3"><?php echo htmlspecialchars($settings['welcome_text'] ?? ''); ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Primary Theme Color</label>
            <input type="color" name="theme_color" class="form-control form-control-color" value="<?php echo htmlspecialchars($settings['theme_color'] ?? '#dc2626'); ?>">
            <small class="text-muted">Pick your league's primary color.</small>
        </div>
        <div class="mb-3">
            <label class="form-label">League Logo</label>
            <?php if (!empty($settings['league_logo'])): ?>
                <div class="mb-2">
                    <img src="/<?php echo htmlspecialchars($settings['league_logo']); ?>" alt="League Logo" style="max-height:80px;">
                </div>
            <?php endif; ?>
            <input type="file" name="league_logo" class="form-control">
            <small class="text-muted">Allowed: jpg, jpeg, png, gif. Max 2MB.</small>
        </div>
        <div class="mb-3">
            <label class="form-label">Points System Preset</label>
            <select class="form-select" id="preset_select">
                <option value="">-- Select Preset --</option>
                <?php foreach ($scoring_presets as $key => $preset): ?>
                    <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($preset['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">Choose a preset to auto-fill the points system, or edit manually below.</small>
        </div>
        <div class="mb-3">
            <label class="form-label">Points System (JSON)</label>
            <textarea name="points_system" id="points_system" class="form-control" rows="6"><?php echo htmlspecialchars($settings['points_system'] ?? ''); ?></textarea>
            <small class="text-muted">Example: {"main":{"1":25,"2":18,...},"sprint":{...},"bonus":{"fastest_lap":1,"pole":1}}</small>
        </div>
        <button type="submit" class="btn btn-primary">Save Settings</button>
    </form>
</div>
<script>
const presets = <?php echo json_encode(array_map(fn($p) => $p['json'], $scoring_presets)); ?>;
document.getElementById('preset_select').addEventListener('change', function() {
    const val = this.value;
    if (val && presets[val]) {
        document.getElementById('points_system').value = JSON.stringify(presets[val], null, 2);
    }
});
</script>
<?php include '../includes/footer.php'; ?>