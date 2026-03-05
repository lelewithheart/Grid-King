<?php
/**
 * GridKing – Setup Wizard (Legacy 1.3.0)
 *
 * Walks an admin through the initial configuration of the league.
 * Steps:
 *   1 – Welcome & league identity
 *   2 – Feature toggles
 *   3 – Appearance & theme
 *   4 – Integrations (Discord / Google Calendar)
 *   5 – Review & finish
 *
 * The wizard is accessible at /setup.php.
 * After completion the `setup_completed` setting is set to '1'
 * and further visits redirect to the admin dashboard.
 */

require_once 'config/config.php';

$db   = new Database();
$conn = $db->getConnection();

// ------------------------------------------------------------------
// Helper: load all settings into an associative array
// ------------------------------------------------------------------
function loadSettings(PDO $conn): array
{
    $stmt = $conn->prepare("SELECT `key`, `value` FROM settings");
    $stmt->execute();
    $s = [];
    foreach ($stmt->fetchAll() as $row) {
        $s[$row['key']] = $row['value'];
    }
    return $s;
}

// ------------------------------------------------------------------
// Helper: upsert a single setting
// ------------------------------------------------------------------
function saveSetting(PDO $conn, string $key, string $value): void
{
    $stmt = $conn->prepare(
        "INSERT INTO settings (`key`, `value`) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
    );
    $stmt->execute([':k' => $key, ':v' => $value]);
}

// ------------------------------------------------------------------
// Redirect already-completed setup to admin dashboard (admin only)
// ------------------------------------------------------------------
$settings = loadSettings($conn);

// Allow the wizard to be re-opened via ?reset=1 by an admin
$forceReset = isLoggedIn() && isAdmin() && isset($_GET['reset']);

if (!$forceReset && ($settings['setup_completed'] ?? '0') === '1') {
    if (isLoggedIn() && isAdmin()) {
        header('Location: /admin/dashboard.php');
    } else {
        header('Location: /index.php');
    }
    exit();
}

// ------------------------------------------------------------------
// Wizard state
// ------------------------------------------------------------------
$totalSteps  = 5;
$currentStep = max(1, min($totalSteps, (int)($_POST['step'] ?? $_GET['step'] ?? 1)));
$error       = '';
$success     = '';

// ------------------------------------------------------------------
// Load feature toggles
// ------------------------------------------------------------------
$togglesStmt = $conn->prepare(
    "SELECT feature_code, feature_name, description, is_enabled, category
     FROM feature_toggles ORDER BY category, feature_name"
);
$togglesStmt->execute();
$allToggles  = $togglesStmt->fetchAll(PDO::FETCH_ASSOC);
$togglesByCategory = [];
foreach ($allToggles as $t) {
    $togglesByCategory[$t['category']][] = $t;
}

// ------------------------------------------------------------------
// Process POST
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid. Please try again.';
    } else {
        $nextStep = $currentStep + 1;

        switch ($currentStep) {
            // ---- Step 1: League identity -------------------------
            case 1:
                $leagueName  = sanitizeInput($_POST['league_name'] ?? '');
                $welcomeText = trim($_POST['welcome_text'] ?? '');
                $timezone    = sanitizeInput($_POST['timezone'] ?? 'Europe/Berlin');

                if (empty($leagueName)) {
                    $error = 'League name is required.';
                    $nextStep = 1;
                    break;
                }

                // Logo upload (optional)
                $logoPath = null;
                if (isset($_FILES['league_logo']) && $_FILES['league_logo']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['league_logo']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ALLOWED_IMAGE_TYPES) && $_FILES['league_logo']['size'] <= 2 * 1024 * 1024) {
                        if (!is_dir(UPLOAD_DIR)) {
                            @mkdir(UPLOAD_DIR, 0755, true);
                        }
                        $target = UPLOAD_DIR . 'league_logo.' . $ext;
                        if (move_uploaded_file($_FILES['league_logo']['tmp_name'], $target)) {
                            $logoPath = 'uploads/league_logo.' . $ext;
                        }
                    }
                }

                saveSetting($conn, 'league_name',  $leagueName);
                saveSetting($conn, 'welcome_text', $welcomeText);
                saveSetting($conn, 'timezone',     $timezone);
                if ($logoPath) {
                    saveSetting($conn, 'league_logo', $logoPath);
                }
                break;

            // ---- Step 2: Feature toggles -------------------------
            case 2:
                foreach ($allToggles as $toggle) {
                    $code    = $toggle['feature_code'];
                    $enabled = isset($_POST['toggle_' . $code]) ? 1 : 0;
                    $stmt    = $conn->prepare(
                        "UPDATE feature_toggles SET is_enabled = :v WHERE feature_code = :c"
                    );
                    $stmt->execute([':v' => $enabled, ':c' => $code]);
                }
                break;

            // ---- Step 3: Appearance / theme ----------------------
            case 3:
                $themeMode   = in_array($_POST['theme_mode'] ?? '', ['light', 'dark', 'auto'])
                                   ? $_POST['theme_mode']
                                   : 'light';
                $themeColor  = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['theme_color'] ?? '')
                                   ? $_POST['theme_color']
                                   : '#dc2626';
                $customCss   = $_POST['custom_css'] ?? '';
                $announcementEnabled = isset($_POST['announcement_bar_enabled']) ? '1' : '0';
                $announcementText    = trim($_POST['announcement_bar_text'] ?? '');
                $announcementColor   = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['announcement_bar_color'] ?? '')
                                           ? $_POST['announcement_bar_color']
                                           : '#0d6efd';
                $announcementDismissible = isset($_POST['announcement_bar_dismissible']) ? '1' : '0';
                $defaultLang = sanitizeInput($_POST['default_language'] ?? 'en');

                saveSetting($conn, 'theme_mode',   $themeMode);
                saveSetting($conn, 'theme_color',  $themeColor);
                saveSetting($conn, 'custom_css',   $customCss);
                saveSetting($conn, 'announcement_bar_enabled',     $announcementEnabled);
                saveSetting($conn, 'announcement_bar_text',        $announcementText);
                saveSetting($conn, 'announcement_bar_color',       $announcementColor);
                saveSetting($conn, 'announcement_bar_dismissible', $announcementDismissible);
                saveSetting($conn, 'default_language',             $defaultLang);
                break;

            // ---- Step 4: Integrations ----------------------------
            case 4:
                $discordWebhook = trim($_POST['discord_webhook'] ?? '');
                $registrationOpen = isset($_POST['registration_open']) ? '1' : '0';
                $requireApproval  = isset($_POST['require_approval']) ? '1' : '0';

                saveSetting($conn, 'discord_webhook',    $discordWebhook);
                saveSetting($conn, 'registration_open',  $registrationOpen);
                saveSetting($conn, 'require_approval',   $requireApproval);
                break;

            // ---- Step 5: Finish ----------------------------------
            case 5:
                saveSetting($conn, 'setup_completed',    '1');
                saveSetting($conn, 'setup_completed_at', date('Y-m-d H:i:s'));
                $success = 'Setup complete! Redirecting to Admin Dashboard…';
                header('Refresh: 2; url=/admin/dashboard.php');
                break;
        }

        if (!$error && $currentStep < $totalSteps) {
            $currentStep = $nextStep;
        } elseif (!$error && $currentStep === $totalSteps) {
            // Already handled above
        }

        // Reload settings after saves
        $settings = loadSettings($conn);
    }
}

// Timezone list (abbreviated)
$timezones = DateTimeZone::listIdentifiers(DateTimeZone::ALL);

// Languages for step 3
$langStmt = $conn->prepare("SELECT code, name, native_name FROM languages WHERE is_active = 1 ORDER BY name");
$langStmt->execute();
$languages = $langStmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($languages)) {
    $languages = [['code' => 'en', 'name' => 'English', 'native_name' => 'English']];
}

$page_title = 'Setup Wizard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Wizard – <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f0f2f5; }
        .wizard-card { max-width: 760px; margin: 3rem auto; }
        .step-indicator .step { width: 2rem; height: 2rem; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; font-size: .85rem; }
        .step.done   { background: #198754; color: #fff; }
        .step.active { background: #0d6efd; color: #fff; }
        .step.todo   { background: #dee2e6; color: #6c757d; }
        .step-label  { font-size: .78rem; margin-top: .25rem; }
        .connector   { flex: 1; height: 2px; background: #dee2e6; margin: 0 .25rem; align-self: center; }
        .connector.done { background: #198754; }
    </style>
</head>
<body>
<div class="container">
    <div class="wizard-card card shadow-sm">
        <!-- Header -->
        <div class="card-header bg-dark text-white py-3">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-flag-checkered fs-4"></i>
                <span class="fw-bold fs-5"><?php echo APP_NAME; ?> – Setup Wizard</span>
                <span class="ms-auto badge bg-secondary">Step <?php echo $currentStep; ?> / <?php echo $totalSteps; ?></span>
            </div>
        </div>

        <!-- Step indicator -->
        <div class="card-body border-bottom pb-3">
            <?php
            $stepLabels = ['League', 'Features', 'Appearance', 'Integrations', 'Finish'];
            ?>
            <div class="d-flex align-items-start step-indicator">
                <?php for ($i = 1; $i <= $totalSteps; $i++): ?>
                    <?php if ($i > 1): ?>
                        <div class="connector<?php echo $i <= $currentStep ? ' done' : ''; ?>"></div>
                    <?php endif; ?>
                    <div class="text-center">
                        <div class="step <?php
                            if ($i < $currentStep) echo 'done';
                            elseif ($i === $currentStep) echo 'active';
                            else echo 'todo';
                        ?>">
                            <?php if ($i < $currentStep): ?>
                                <i class="bi bi-check-lg"></i>
                            <?php else: ?>
                                <?php echo $i; ?>
                            <?php endif; ?>
                        </div>
                        <div class="step-label text-muted"><?php echo $stepLabels[$i - 1]; ?></div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="mx-3 mt-3 alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="mx-3 mt-3 alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Step content -->
        <form method="post" enctype="multipart/form-data" class="card-body">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="step"       value="<?php echo $currentStep; ?>">

            <?php /* ======================================================
                     STEP 1 – League Identity
                     ====================================================== */ ?>
            <?php if ($currentStep === 1): ?>
                <h4 class="mb-3"><i class="bi bi-trophy me-2"></i>League Identity</h4>
                <p class="text-muted mb-4">Set your league name, logo, welcome message and timezone.</p>

                <div class="mb-3">
                    <label class="form-label fw-semibold">League Name <span class="text-danger">*</span></label>
                    <input type="text" name="league_name" class="form-control"
                           value="<?php echo htmlspecialchars($settings['league_name'] ?? ''); ?>"
                           placeholder="e.g. Apex Racing League" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Welcome Text</label>
                    <textarea name="welcome_text" class="form-control" rows="3"
                              placeholder="A short welcome message shown on the homepage…"><?php echo htmlspecialchars($settings['welcome_text'] ?? ''); ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">League Logo</label>
                    <?php if (!empty($settings['league_logo'])): ?>
                        <div class="mb-2">
                            <img src="/<?php echo htmlspecialchars($settings['league_logo']); ?>" alt="Current Logo" style="max-height:60px;">
                            <small class="text-muted ms-2">Current logo</small>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="league_logo" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp">
                    <small class="text-muted">jpg/png/gif/webp, max 2 MB</small>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Default Timezone</label>
                    <select name="timezone" class="form-select">
                        <?php foreach ($timezones as $tz): ?>
                            <option value="<?php echo $tz; ?>"
                                <?php if (($settings['timezone'] ?? 'Europe/Berlin') === $tz) echo 'selected'; ?>>
                                <?php echo $tz; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            <?php /* ======================================================
                     STEP 2 – Feature Toggles
                     ====================================================== */ ?>
            <?php elseif ($currentStep === 2): ?>
                <h4 class="mb-3"><i class="bi bi-toggles me-2"></i>Feature Toggles</h4>
                <p class="text-muted mb-4">Enable or disable individual modules. You can change these later in Admin → Settings.</p>

                <?php foreach ($togglesByCategory as $cat => $toggles): ?>
                    <h6 class="text-uppercase text-muted mt-4 mb-2 small fw-bold"><?php echo htmlspecialchars(str_replace('_', ' ', $cat)); ?></h6>
                    <?php foreach ($toggles as $toggle): ?>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox"
                                   name="toggle_<?php echo htmlspecialchars($toggle['feature_code']); ?>"
                                   id="toggle_<?php echo htmlspecialchars($toggle['feature_code']); ?>"
                                   <?php if ($toggle['is_enabled']) echo 'checked'; ?>>
                            <label class="form-check-label" for="toggle_<?php echo htmlspecialchars($toggle['feature_code']); ?>">
                                <span class="fw-semibold"><?php echo htmlspecialchars($toggle['feature_name']); ?></span>
                                <?php if ($toggle['description']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($toggle['description']); ?></small>
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>

            <?php /* ======================================================
                     STEP 3 – Appearance & Themes
                     ====================================================== */ ?>
            <?php elseif ($currentStep === 3): ?>
                <h4 class="mb-3"><i class="bi bi-palette me-2"></i>Appearance & Themes</h4>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Theme Mode</label>
                        <select name="theme_mode" class="form-select" id="themeModeSelect">
                            <?php foreach (['light' => 'Light', 'dark' => 'Dark', 'auto' => 'Auto (System)'] as $v => $l): ?>
                                <option value="<?php echo $v; ?>"
                                    <?php if (($settings['theme_mode'] ?? 'light') === $v) echo 'selected'; ?>>
                                    <?php echo $l; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Dark mode applies Bootstrap dark theme site-wide.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Primary Accent Color</label>
                        <input type="color" name="theme_color" class="form-control form-control-color w-100"
                               value="<?php echo htmlspecialchars($settings['theme_color'] ?? '#dc2626'); ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Custom CSS</label>
                    <textarea name="custom_css" class="form-control font-monospace" rows="5"
                              placeholder="/* Your custom CSS here */"><?php echo htmlspecialchars($settings['custom_css'] ?? ''); ?></textarea>
                    <small class="text-muted">Injected in the <code>&lt;head&gt;</code> of every page.</small>
                </div>

                <hr>
                <h6 class="fw-semibold mb-2"><i class="bi bi-megaphone me-1"></i>Announcement Bar</h6>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="announcement_bar_enabled"
                           id="annEnabled"
                           <?php if (($settings['announcement_bar_enabled'] ?? '0') === '1') echo 'checked'; ?>>
                    <label class="form-check-label" for="annEnabled">Enable announcement bar</label>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Announcement Text</label>
                    <input type="text" name="announcement_bar_text" class="form-control"
                           value="<?php echo htmlspecialchars($settings['announcement_bar_text'] ?? ''); ?>"
                           placeholder="e.g. Season 3 registration is now open!">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Bar Background Color</label>
                        <input type="color" name="announcement_bar_color" class="form-control form-control-color w-100"
                               value="<?php echo htmlspecialchars($settings['announcement_bar_color'] ?? '#0d6efd'); ?>">
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="announcement_bar_dismissible"
                                   id="annDismiss"
                                   <?php if (($settings['announcement_bar_dismissible'] ?? '1') === '1') echo 'checked'; ?>>
                            <label class="form-check-label" for="annDismiss">Allow users to dismiss it</label>
                        </div>
                    </div>
                </div>

                <hr>
                <h6 class="fw-semibold mb-2"><i class="bi bi-translate me-1"></i>Language</h6>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Default Language</label>
                    <select name="default_language" class="form-select">
                        <?php foreach ($languages as $lng): ?>
                            <option value="<?php echo htmlspecialchars($lng['code']); ?>"
                                <?php if (($settings['default_language'] ?? 'en') === $lng['code']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($lng['name']); ?> (<?php echo htmlspecialchars($lng['native_name']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            <?php /* ======================================================
                     STEP 4 – Integrations
                     ====================================================== */ ?>
            <?php elseif ($currentStep === 4): ?>
                <h4 class="mb-3"><i class="bi bi-plug me-2"></i>Integrations & Access</h4>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Discord Webhook URL</label>
                    <input type="url" name="discord_webhook" class="form-control"
                           value="<?php echo htmlspecialchars($settings['discord_webhook'] ?? ''); ?>"
                           placeholder="https://discord.com/api/webhooks/…" autocomplete="off">
                    <small class="text-muted">Leave empty to disable Discord notifications.</small>
                </div>

                <hr>
                <h6 class="fw-semibold mb-2">Registration</h6>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="registration_open" id="regOpen"
                           <?php if (($settings['registration_open'] ?? '1') === '1') echo 'checked'; ?>>
                    <label class="form-check-label" for="regOpen">Allow new user registration</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="require_approval" id="reqApproval"
                           <?php if (($settings['require_approval'] ?? '0') === '1') echo 'checked'; ?>>
                    <label class="form-check-label" for="reqApproval">Require admin approval for new drivers</label>
                </div>

            <?php /* ======================================================
                     STEP 5 – Review & Finish
                     ====================================================== */ ?>
            <?php elseif ($currentStep === 5): ?>
                <h4 class="mb-3"><i class="bi bi-check-circle me-2"></i>Review & Finish</h4>
                <p class="text-muted mb-4">Here is a summary of your current configuration. Click <strong>Finish Setup</strong> to apply.</p>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <tbody>
                            <tr><th class="table-secondary w-35">League Name</th><td><?php echo htmlspecialchars($settings['league_name'] ?? '—'); ?></td></tr>
                            <tr><th class="table-secondary">Timezone</th><td><?php echo htmlspecialchars($settings['timezone'] ?? '—'); ?></td></tr>
                            <tr><th class="table-secondary">Theme Mode</th><td><?php echo htmlspecialchars(ucfirst($settings['theme_mode'] ?? 'light')); ?></td></tr>
                            <tr><th class="table-secondary">Primary Color</th>
                                <td>
                                    <span class="d-inline-block rounded me-2" style="width:1rem;height:1rem;background:<?php echo htmlspecialchars($settings['theme_color'] ?? '#dc2626'); ?>;vertical-align:middle;"></span>
                                    <?php echo htmlspecialchars($settings['theme_color'] ?? '#dc2626'); ?>
                                </td>
                            </tr>
                            <tr><th class="table-secondary">Announcement Bar</th><td><?php echo ($settings['announcement_bar_enabled'] ?? '0') === '1' ? 'Enabled' : 'Disabled'; ?></td></tr>
                            <tr><th class="table-secondary">Default Language</th><td><?php echo htmlspecialchars(strtoupper($settings['default_language'] ?? 'EN')); ?></td></tr>
                            <tr><th class="table-secondary">Registration</th><td><?php echo ($settings['registration_open'] ?? '1') === '1' ? 'Open' : 'Closed'; ?></td></tr>
                            <tr><th class="table-secondary">Discord Webhook</th><td><?php echo !empty($settings['discord_webhook']) ? '✅ Configured' : '—'; ?></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="mb-3 mt-3">
                    <h6 class="fw-semibold">Active Features</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($allToggles as $t): ?>
                            <span class="badge <?php echo $t['is_enabled'] ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo htmlspecialchars($t['feature_name']); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-1"></i>
                    All settings can be changed later via <strong>Admin → Settings</strong>.
                </div>
            <?php endif; ?>

            <!-- Navigation buttons -->
            <div class="d-flex justify-content-between mt-4">
                <?php if ($currentStep > 1): ?>
                    <a href="?step=<?php echo $currentStep - 1; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Previous
                    </a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>

                <?php if ($currentStep < $totalSteps): ?>
                    <button type="submit" class="btn btn-primary">
                        Next <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                <?php else: ?>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i>Finish Setup
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
