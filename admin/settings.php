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
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $league_name = sanitizeInput($_POST['league_name'] ?? '');
        $welcome_text = trim($_POST['welcome_text'] ?? '');
        $theme_color = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['theme_color'] ?? '') ? $_POST['theme_color'] : '#dc2626';
        $theme_mode  = in_array($_POST['theme_mode'] ?? '', ['light', 'dark', 'auto']) ? $_POST['theme_mode'] : 'light';
        $custom_css  = $_POST['custom_css'] ?? '';
        $points_system = $_POST['points_system'] ?? '';
        $discord_webhook = trim($_POST['discord_webhook'] ?? '');
        $default_language = sanitizeInput($_POST['default_language'] ?? 'en');

        // Announcement bar
        $announcement_bar_enabled     = isset($_POST['announcement_bar_enabled']) ? '1' : '0';
        $announcement_bar_text        = trim($_POST['announcement_bar_text'] ?? '');
        $announcement_bar_color       = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['announcement_bar_color'] ?? '')
                                            ? $_POST['announcement_bar_color'] : '#0d6efd';
        $announcement_bar_dismissible = isset($_POST['announcement_bar_dismissible']) ? '1' : '0';

        // Notification toggles
        $notify_driver_register = isset($_POST['notify_driver_register']) ? '1' : '0';
        $notify_race_result = isset($_POST['notify_race_result']) ? '1' : '0';
        $notify_team_created = isset($_POST['notify_team_created']) ? '1' : '0';

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
            $error = implode('<br>', array_map('htmlspecialchars', $errors));
        }

        // Save settings
        $settingsToSave = [
            'league_name'                  => $league_name,
            'welcome_text'                 => $welcome_text,
            'theme_color'                  => $theme_color,
            'theme_mode'                   => $theme_mode,
            'custom_css'                   => $custom_css,
            'points_system'                => $points_system,
            'discord_webhook'              => $discord_webhook,
            'default_language'             => $default_language,
            'announcement_bar_enabled'     => $announcement_bar_enabled,
            'announcement_bar_text'        => $announcement_bar_text,
            'announcement_bar_color'       => $announcement_bar_color,
            'announcement_bar_dismissible' => $announcement_bar_dismissible,
            'notify_driver_register'       => $notify_driver_register,
            'notify_race_result'           => $notify_race_result,
            'notify_team_created'          => $notify_team_created,
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
}

include '../includes/header.php';

// Languages for the language dropdown
$langStmt = $conn->prepare("SELECT code, name, native_name FROM languages WHERE is_active = 1 ORDER BY name");
$langStmt->execute();
$languages = $langStmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($languages)) {
    $languages = [['code' => 'en', 'name' => 'English', 'native_name' => 'English']];
}
?>

<div class="container my-5">
    <h1 class="mb-4"><i class="bi bi-gear me-2"></i>League Settings</h1>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Quick-link to feature toggles and setup wizard -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="feature_toggles.php" class="btn btn-outline-secondary">
            <i class="bi bi-toggles me-1"></i>Feature Toggles
        </a>
        <a href="/setup.php?reset=1" class="btn btn-outline-secondary">
            <i class="bi bi-magic me-1"></i>Re-run Setup Wizard
        </a>
        <a href="../utils/import_gklm.php" class="btn btn-outline-primary">
            <i class="bi bi-upload me-1"></i> Import League Data (.gklm)
        </a>
        <a href="../utils/migration.php" class="btn btn-outline-primary">
            <i class="bi bi-box-arrow-up me-1"></i> Migration &amp; Export
        </a>
    </div>

    <form method="post" enctype="multipart/form-data" class="card card-body shadow-sm">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

        <!-- ── League Identity ───────────────────────────────── -->
        <h5 class="mt-2 mb-3 border-bottom pb-1"><i class="bi bi-trophy me-1"></i>League Identity</h5>
        <div class="mb-3">
            <label class="form-label">League Name</label>
            <input type="text" name="league_name" class="form-control" value="<?php echo htmlspecialchars($settings['league_name'] ?? APP_NAME); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Welcome Text</label>
            <textarea name="welcome_text" class="form-control" rows="3"><?php echo htmlspecialchars($settings['welcome_text'] ?? ''); ?></textarea>
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

        <!-- ── Appearance & Themes (1.3.1) ───────────────────── -->
        <h5 class="mt-4 mb-3 border-bottom pb-1"><i class="bi bi-palette me-1"></i>Appearance &amp; Themes</h5>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Theme Mode</label>
                <select name="theme_mode" class="form-select">
                    <?php foreach (['light' => 'Light', 'dark' => 'Dark', 'auto' => 'Auto (System Preference)'] as $v => $l): ?>
                        <option value="<?php echo $v; ?>"
                            <?php if (($settings['theme_mode'] ?? 'light') === $v) echo 'selected'; ?>>
                            <?php echo $l; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Dark mode uses Bootstrap's built-in dark theme.</small>
            </div>
            <div class="col-md-6">
                <label class="form-label">Primary Accent Color</label>
                <input type="color" name="theme_color" class="form-control form-control-color" value="<?php echo htmlspecialchars($settings['theme_color'] ?? '#dc2626'); ?>">
                <small class="text-muted">Pick your league's primary color.</small>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Custom CSS</label>
            <textarea name="custom_css" class="form-control font-monospace" rows="5"
                      placeholder="/* Enter custom CSS here */"><?php echo htmlspecialchars($settings['custom_css'] ?? ''); ?></textarea>
            <small class="text-muted">Injected into the <code>&lt;head&gt;</code> of every page. Admin-only.</small>
        </div>

        <!-- Announcement bar -->
        <div class="card card-body bg-light mb-3">
            <h6 class="fw-semibold mb-2"><i class="bi bi-megaphone me-1"></i>Announcement Bar</h6>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="announcement_bar_enabled" id="ann_enabled"
                       <?php if (($settings['announcement_bar_enabled'] ?? '0') === '1') echo 'checked'; ?>>
                <label class="form-check-label" for="ann_enabled">Enable announcement bar</label>
            </div>
            <div class="mb-2">
                <label class="form-label small mb-1">Announcement Text</label>
                <input type="text" name="announcement_bar_text" class="form-control"
                       value="<?php echo htmlspecialchars($settings['announcement_bar_text'] ?? ''); ?>"
                       placeholder="e.g. Season 3 registration is now open!">
            </div>
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label small mb-1">Bar Background Color</label>
                    <input type="color" name="announcement_bar_color" class="form-control form-control-color"
                           value="<?php echo htmlspecialchars($settings['announcement_bar_color'] ?? '#0d6efd'); ?>">
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="announcement_bar_dismissible" id="ann_dismiss"
                               <?php if (($settings['announcement_bar_dismissible'] ?? '1') === '1') echo 'checked'; ?>>
                        <label class="form-check-label small" for="ann_dismiss">Allow users to dismiss</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- i18n -->
        <div class="mb-3">
            <label class="form-label">Default Language</label>
            <select name="default_language" class="form-select">
                <?php foreach ($languages as $lng): ?>
                    <option value="<?php echo htmlspecialchars($lng['code']); ?>"
                        <?php if (($settings['default_language'] ?? 'en') === $lng['code']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($lng['name']); ?> (<?php echo htmlspecialchars($lng['native_name']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">Users can override this via the language switcher in the nav bar.</small>
        </div>

        <!-- ── Points System ──────────────────────────────────── -->
        <h5 class="mt-4 mb-3 border-bottom pb-1"><i class="bi bi-123 me-1"></i>Points System</h5>
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

        <!-- ── Integrations ───────────────────────────────────── -->
        <h5 class="mt-4 mb-3 border-bottom pb-1"><i class="bi bi-discord me-1"></i>Discord Integration</h5>
        <div class="mb-3">
            <label class="form-label">Discord Webhook URL</label>
            <input type="url" name="discord_webhook" class="form-control" value="<?php echo htmlspecialchars($settings['discord_webhook'] ?? ''); ?>" placeholder="https://discord.com/api/webhooks/..." autocomplete="off">
            <small class="text-muted">Paste your Discord webhook URL here to enable Discord notifications.</small>
        </div>
        <div class="mb-3">
            <label class="form-label">Discord Notifications</label>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="notify_driver_register" id="notify_driver_register" value="1"
                    <?php if (($settings['notify_driver_register'] ?? '1') === '1') echo 'checked'; ?>>
                <label class="form-check-label" for="notify_driver_register">
                    Notify on new driver registration
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="notify_race_result" id="notify_race_result" value="1"
                    <?php if (($settings['notify_race_result'] ?? '1') === '1') echo 'checked'; ?>>
                <label class="form-check-label" for="notify_race_result">
                    Notify when race results are posted
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="notify_team_created" id="notify_team_created" value="1"
                    <?php if (($settings['notify_team_created'] ?? '1') === '1') echo 'checked'; ?>>
                <label class="form-check-label" for="notify_team_created">
                    Notify when a new team is created
                </label>
            </div>
            <small class="text-muted">Enable or disable Discord notifications for specific events.</small>
        </div>

        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Settings</button>
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