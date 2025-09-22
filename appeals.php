<?php
/**
 * Appeals System – Berufungsverfahren
 * Ermöglicht das Einreichen, Anzeigen und Bearbeiten von Berufungen (Appeals)
 */

require_once 'config/config.php';

// Session und Permission-Check (nur eingeloggte User)
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$page_title = 'Appeals (Berufungen)';

// DB Connection
$db = new Database();
$conn = $db->getConnection();

// Appeals abrufen
$appeals = [];
$stmt = $conn->prepare("SELECT a.*, u.username AS driver_name, r.name AS race_name FROM penalty_appeals a LEFT JOIN users u ON a.driver_id = u.id LEFT JOIN races r ON a.race_id = r.id ORDER BY a.created_at DESC");
$stmt->execute();
$appeals = $stmt->fetchAll();

// Appeal einreichen (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['race_id'], $_POST['reason'])) {
    $driver_id = $_SESSION['user_id'];
    $race_id = intval($_POST['race_id']);
    $reason = trim($_POST['reason']);
    $stmt = $conn->prepare("INSERT INTO penalty_appeals (driver_id, race_id, reason, status, created_at) VALUES (?, ?, ?, 'Pending', NOW())");
    $stmt->execute([$driver_id, $race_id, $reason]);
    header('Location: appeals.php?success=1');
    exit();
}

// Rennen für Auswahl abrufen
$races = [];
$stmt = $conn->prepare("SELECT id, name FROM races WHERE status = 'Completed' ORDER BY race_date DESC");
$stmt->execute();
$races = $stmt->fetchAll();

include 'includes/header.php';
?>
<div class="container my-5">
    <h2>Berufungen (Appeals)</h2>
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">Berufung erfolgreich eingereicht!</div>
    <?php endif; ?>
    <form method="post" class="mb-4">
        <div class="mb-3">
            <label for="race_id" class="form-label">Rennen</label>
            <select name="race_id" id="race_id" class="form-select" required>
                <option value="">Bitte wählen...</option>
                <?php foreach ($races as $race): ?>
                    <option value="<?= $race['id'] ?>"><?= htmlspecialchars($race['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="reason" class="form-label">Begründung</label>
            <textarea name="reason" id="reason" class="form-control" rows="3" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Berufung einreichen</button>
    </form>
    <h3>Alle Berufungen</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Fahrer</th>
                <th>Rennen</th>
                <th>Begründung</th>
                <th>Status</th>
                <th>Erstellt am</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($appeals as $appeal): ?>
                <tr>
                    <td><?= $appeal['id'] ?></td>
                    <td><?= htmlspecialchars($appeal['driver_name']) ?></td>
                    <td><?= htmlspecialchars($appeal['race_name']) ?></td>
                    <td><?= nl2br(htmlspecialchars($appeal['reason'])) ?></td>
                    <td><?= htmlspecialchars($appeal['status']) ?></td>
                    <td><?= $appeal['created_at'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include 'includes/footer.php'; ?>
