<?php
/**
 * Race Control Dashboard
 * Übersicht und Management aktiver Race Control Sessions
 */
require_once 'config/config.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$page_title = 'Race Control Dashboard';
$db = new Database();
$conn = $db->getConnection();
// Aktive Sessions abrufen
$sessions = [];
$stmt = $conn->prepare("SELECT s.*, r.name AS race_name FROM race_control_sessions s LEFT JOIN races r ON s.race_id = r.id WHERE s.status = 'active' ORDER BY s.started_at DESC");
$stmt->execute();
$sessions = $stmt->fetchAll();
include 'includes/header.php';
?>
<div class="container my-5">
    <h2>Race Control Dashboard</h2>
    <a href="race_control_new.php" class="btn btn-success mb-3">Neue Session starten</a>
    <h3>Aktive Sessions</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Rennen</th>
                <th>Status</th>
                <th>Gestartet am</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sessions as $session): ?>
                <tr>
                    <td><?= $session['id'] ?></td>
                    <td><?= htmlspecialchars($session['race_name']) ?></td>
                    <td><?= htmlspecialchars($session['status']) ?></td>
                    <td><?= $session['started_at'] ?></td>
                    <td>
                        <a href="race_control_view.php?id=<?= $session['id'] ?>" class="btn btn-primary btn-sm">Anzeigen</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include 'includes/footer.php'; ?>
