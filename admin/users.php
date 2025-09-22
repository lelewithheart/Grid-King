<?php
/**
 * Admin - Manage Users
 */

require_once '../config/config.php';

requireAdmin();

$page_title = 'Manage Users';

// Get database connection
$db = new Database();
$conn = $db->getConnection();

// Fetch all users

// Neue Rollenstruktur: User mit Rollen und Zuweisungen anzeigen
$userQuery = "
    SELECT u.id, u.username, u.email, u.created_at, 
           GROUP_CONCAT(ur.role_code ORDER BY ur.role_code SEPARATOR ', ') AS roles
    FROM users u
    LEFT JOIN user_role_assignments ura ON ura.user_id = u.id AND ura.is_active = 1
    LEFT JOIN user_roles ur ON ura.role_id = ur.id
    GROUP BY u.id, u.username, u.email, u.created_at
    ORDER BY u.created_at DESC
";
$userStmt = $conn->prepare($userQuery);
$userStmt->execute();
$users = $userStmt->fetchAll();

include '../includes/header.php';
?>

<div class="container my-5">
    <h1 class="mb-4"><i class="bi bi-people me-2"></i>Manage Users</h1>
    <div class="card card-racing shadow-sm">
        <div class="card-header bg-dark text-white">
            <strong>User List</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Roles</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($user['roles'] ? $user['roles'] : 'none'); ?>
                            </td>
                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($user['created_at']))); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No users found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>