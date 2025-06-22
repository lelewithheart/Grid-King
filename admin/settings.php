<?php
require_once '../config/config.php';
requireAdmin();

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // League name
    $league_name = sanitizeInput($_POST['league_name'] ?? '');
    $welcome_text = trim($_POST['welcome_text'] ?? '');
    $theme_color = $_POST['theme_color'] ?? '#dc2626';

    // Handle logo upload
    $errors = [];
    $logo_path = null;
    if (isset($_FILES['league_logo']) && $_FILES['league_logo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['league_logo']['name'], PATHINFO_EXTENSION));
        $target = UPLOAD_DIR . 'league_logo.' . $ext;

        if (!in_array($ext, ALLOWED_IMAGE_TYPES)) {
            $errors[] = 'Invalid logo file type.';
        }
        if (!is_dir(UPLOAD_DIR)) {
            $errors[] = 'Target directory ' . realpath(UPLOAD_DIR) . ' not found';
        }
        // Optional: Check file size (e.g. max 2MB)
        if ($_FILES['league_logo']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Logo file is too large (max 2MB).';
        }

        if (count($errors) === 0) {
            if (move_uploaded_file($_FILES['league_logo']['tmp_name'], $target)) {
                // Save only the relative path
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
    $settings = [
        'league_name' => $league_name,
        'welcome_text' => $welcome_text,
        'theme_color' => $theme_color
    ];
    if ($logo_path) {
        $settings['league_logo'] = $logo_path;
    }

    foreach ($settings as $key => $value) {
        $stmt = $conn->prepare("REPLACE INTO settings (`key`, `value`) VALUES (:key, :value)");
        $stmt->bindParam(':key', $key);
        $stmt->bindParam(':value', $value);
        $stmt->execute();
    }

    if (!$error) $success = 'Settings updated!';
    else $error = 'Failed to update settings: ' . $error;
}

// Fetch current settings
$stmt = $conn->prepare("SELECT `key`, `value` FROM settings");
$stmt->execute();
$settings = [];
foreach ($stmt->fetchAll() as $row) {
    $settings[$row['key']] = $row['value'];
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
                    <img src="../<?php echo htmlspecialchars($settings['league_logo']); ?>" alt="League Logo" style="max-height:80px;">
                </div>
            <?php endif; ?>
            <input type="file" name="league_logo" class="form-control">
            <small class="text-muted">Allowed: jpg, jpeg, png, gif</small>
        </div>
        <button type="submit" class="btn btn-primary">Save Settings</button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>