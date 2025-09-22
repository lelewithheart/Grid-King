<?php
/**
 * Admin - Rollen- und Rechteverwaltung
 * Übersicht, Zuweisung und Bearbeitung von Rollen und Rechten
 */
require_once '../config/config.php';
requireAdmin();

$db = new Database();
$conn = $db->getConnection();

// Rollen abrufen
$roles = $conn->query("SELECT * FROM user_roles ORDER BY id")->fetchAll();
// User abrufen
$users = $conn->query("SELECT id, username FROM users ORDER BY username")->fetchAll();

// Neue Rolle anlegen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_role'])) {
    $role_code = trim($_POST['role_code']);
    $role_name = trim($_POST['role_name']);
    $permissions = json_encode($_POST['permissions'] ?? []);
    $stmt = $conn->prepare("INSERT INTO user_roles (role_code, role_name, permissions) VALUES (?, ?, ?)");
    $stmt->execute([$role_code, $role_name, $permissions]);
    header('Location: roles.php?created=1'); exit();
}
// Rolle löschen
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM user_roles WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header('Location: roles.php?deleted=1'); exit();
}
// User zu Rolle zuweisen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_role'])) {
    $user_id = intval($_POST['user_id']);
    $role_id = intval($_POST['role_id']);
    $stmt = $conn->prepare("INSERT INTO user_role_assignments (user_id, role_id, is_active) VALUES (?, ?, 1)");
    $stmt->execute([$user_id, $role_id]);
    header('Location: roles.php?assigned=1'); exit();
}
include '../includes/header.php';
?>
<div class="container my-5">
    <h2>Rollen- und Rechteverwaltung</h2>
    <?php if (isset($_GET['created'])): ?><div class="alert alert-success">Rolle erfolgreich angelegt!</div><?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Rolle gelöscht!</div><?php endif; ?>
    <?php if (isset($_GET['assigned'])): ?><div class="alert alert-success">Rolle zugewiesen!</div><?php endif; ?>
    <div class="row">
        <div class="col-md-6">
            <h4>Rollen</h4>
            <table class="table table-bordered">
                <thead><tr><th>ID</th><th>Code</th><th>Name</th><th>Aktionen</th></tr></thead>
                <tbody>
                <?php foreach ($roles as $role): ?>
                    <tr>
                        <td><?= $role['id'] ?></td>
                        <td><?= htmlspecialchars($role['role_code']) ?></td>
                        <td><?= htmlspecialchars($role['role_name']) ?></td>
                        <td>
                            <a href="roles.php?delete=<?= $role['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Rolle wirklich löschen?')">Löschen</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <h5>Neue Rolle anlegen</h5>
            <form method="post">
                <div class="mb-2">
                    <input type="text" name="role_code" class="form-control" placeholder="Rollen-Code (z.B. steward)" required>
                </div>
                <div class="mb-2">
                    <input type="text" name="role_name" class="form-control" placeholder="Rollenname" required>
                </div>
                <div class="mb-2">
                    <label>Berechtigungen (JSON Array, z.B. [\"all\",\"create_notes\"])</label>
                    <input type="text" name="permissions[]" class="form-control" placeholder="Berechtigung (z.B. all)">
                </div>
                <button type="submit" name="create_role" class="btn btn-success">Rolle anlegen</button>
            </form>
        </div>
        <div class="col-md-6">
            <h4>User zu Rolle zuweisen</h4>
            <form method="post">
                <div class="mb-2">
                    <select name="user_id" class="form-select" required>
                        <option value="">User wählen...</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <select name="role_id" class="form-select" required>
                        <option value="">Rolle wählen...</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="assign_role" class="btn btn-primary">Zuweisen</button>
            </form>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
