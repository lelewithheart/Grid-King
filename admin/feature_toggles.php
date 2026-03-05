<?php
/**
 * Admin – Feature Toggles (Legacy 1.3.0)
 * Enable or disable individual modules / features.
 */

require_once '../config/config.php';
requireAdmin();

$db   = new Database();
$conn = $db->getConnection();

$success = '';
$error   = '';

// Handle POST (toggle update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Fetch all feature codes
        $codesStmt = $conn->prepare("SELECT feature_code FROM feature_toggles");
        $codesStmt->execute();
        $allCodes = array_column($codesStmt->fetchAll(PDO::FETCH_ASSOC), 'feature_code');

        foreach ($allCodes as $code) {
            $enabled = isset($_POST['toggle_' . $code]) ? 1 : 0;
            $stmt    = $conn->prepare(
                "UPDATE feature_toggles SET is_enabled = :v WHERE feature_code = :c"
            );
            $stmt->execute([':v' => $enabled, ':c' => $code]);
        }
        $success = 'Feature toggles updated successfully.';
    }
}

// Fetch all toggles
$stmt = $conn->prepare(
    "SELECT feature_code, feature_name, description, is_enabled, category
     FROM feature_toggles ORDER BY category, feature_name"
);
$stmt->execute();
$allToggles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$togglesByCategory = [];
foreach ($allToggles as $t) {
    $togglesByCategory[$t['category']][] = $t;
}

$categoryLabels = [
    'race_format' => 'Race Format',
    'features'    => 'Features',
    'moderation'  => 'Moderation',
    'access'      => 'Access Control',
];

$page_title = 'Feature Toggles';
include '../includes/header.php';
?>

<div class="container my-5">
    <div class="d-flex align-items-center mb-4 gap-3">
        <a href="settings.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="mb-0"><i class="bi bi-toggles me-2"></i>Feature Toggles</h1>
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

    <form method="post" class="card card-body shadow-sm">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

        <?php foreach ($togglesByCategory as $cat => $toggles): ?>
            <h5 class="mt-3 mb-2 border-bottom pb-1 text-muted">
                <?php echo htmlspecialchars($categoryLabels[$cat] ?? ucwords(str_replace('_', ' ', $cat))); ?>
            </h5>
            <?php foreach ($toggles as $t): ?>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="toggle_<?php echo htmlspecialchars($t['feature_code']); ?>"
                           id="ft_<?php echo htmlspecialchars($t['feature_code']); ?>"
                           <?php if ($t['is_enabled']) echo 'checked'; ?>>
                    <label class="form-check-label" for="ft_<?php echo htmlspecialchars($t['feature_code']); ?>">
                        <span class="fw-semibold"><?php echo htmlspecialchars($t['feature_name']); ?></span>
                        <?php if ($t['description']): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($t['description']); ?></small>
                        <?php endif; ?>
                    </label>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>

        <div class="mt-3">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i>Save Toggles
            </button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
