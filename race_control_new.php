<?php
/**
 * Neue Race Control Session starten
 */
require_once 'config/config.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$db = new Database();
$conn = $db->getConnection();
// Rennen abrufen
$races = [];
$stmt = $conn->prepare("SELECT id, name FROM races WHERE status = 'Scheduled' OR status = 'Running' ORDER BY race_date DESC");
$stmt->execute();
$races = $stmt->fetchAll();
// Session anlegen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['race_id'])) {
    $race_id = intval($_POST['race_id']);
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT INTO race_control_sessions (race_id, started_by, status, started_at) VALUES (?, ?, 'active', NOW())");
    $stmt->execute([$race_id, $user_id]);
    header('Location: race_control.php?started=1');
    exit();
}
include 'includes/header.php';
?>
<div class="container my-5">
    <h2>Neue Race Control Session starten</h2>
    <form method="post">
        <div class="mb-3">
            <label for="race_id" class="form-label">Rennen</label>
            <select name="race_id" id="race_id" class="form-select" required>
                <option value="">Bitte wählen...</option>
                <?php foreach ($races as $race): ?>
                    <option value="<?= $race['id'] ?>"><?= htmlspecialchars($race['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-success">Session starten</button>
    </form>
</div>
<?php include 'includes/footer.php'; ?>
