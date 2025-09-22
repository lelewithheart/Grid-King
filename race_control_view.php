<?php
/**
 * Einzelne Race Control Session anzeigen
 */
require_once 'config/config.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
if (!isset($_GET['id'])) {
    header('Location: race_control.php');
    exit();
}
$session_id = intval($_GET['id']);
$db = new Database();
$conn = $db->getConnection();
// Session-Daten abrufen
$stmt = $conn->prepare("SELECT s.*, r.name AS race_name FROM race_control_sessions s LEFT JOIN races r ON s.race_id = r.id WHERE s.id = ?");
$stmt->execute([$session_id]);
$session = $stmt->fetch();
if (!$session) {
    echo '<div class="alert alert-danger">Session nicht gefunden.</div>';
    exit();
}
include 'includes/header.php';
?>
<div class="container my-5">
    <h2>Race Control Session: <?= htmlspecialchars($session['race_name']) ?></h2>
    <p>Status: <strong><?= htmlspecialchars($session['status']) ?></strong></p>
    <p>Gestartet am: <?= $session['started_at'] ?></p>
    <p>Gestartet von User-ID: <?= $session['started_by'] ?></p>
    <!-- Hier können weitere Live-Management-Features ergänzt werden -->
    <a href="race_control.php" class="btn btn-secondary mt-3">Zurück zur Übersicht</a>
</div>
<?php include 'includes/footer.php'; ?>
