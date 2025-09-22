<?php
require_once '../config/config.php';
require_once '../includes/header.php';

// Check if user is logged in and has steward permissions
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Check steward permissions
$stmt = $pdo->prepare("
    SELECT ur.role_code, ur.permissions 
    FROM user_role_assignments ura 
    JOIN user_roles ur ON ura.role_id = ur.id 
    WHERE ura.user_id = ? AND ura.is_active = 1
");
$stmt->execute([$_SESSION['user_id']]);
$user_roles = $stmt->fetchAll();

$can_steward = false;
foreach ($user_roles as $role) {
    $permissions = json_decode($role['permissions'], true);
    if (in_array('all', $permissions) || 
        in_array('create_notes', $permissions) || 
        in_array('investigate_incidents', $permissions) ||
        $role['role_code'] === 'admin') {
        $can_steward = true;
        break;
    }
}

if (!$can_steward) {
    $_SESSION['error'] = 'Access denied. Steward permissions required.';
    header('Location: dashboard.php');
    exit;
}

// Handle form submission
if ($_POST && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'create_note') {
            $stmt = $pdo->prepare("
                INSERT INTO steward_notes 
                (race_id, steward_id, note_type, note_title, note_content, related_lap, related_driver_id, visibility, priority)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $related_driver = !empty($_POST['related_driver_id']) ? $_POST['related_driver_id'] : null;
            $related_lap = !empty($_POST['related_lap']) ? $_POST['related_lap'] : null;
            
            $stmt->execute([
                $_POST['race_id'],
                $_SESSION['user_id'],
                $_POST['note_type'],
                $_POST['note_title'],
                $_POST['note_content'],
                $related_lap,
                $related_driver,
                $_POST['visibility'],
                $_POST['priority']
            ]);
            
            $_SESSION['success'] = 'Steward note created successfully!';
        } elseif ($_POST['action'] === 'update_note') {
            $stmt = $pdo->prepare("
                UPDATE steward_notes 
                SET note_type = ?, note_title = ?, note_content = ?, related_lap = ?, 
                    related_driver_id = ?, visibility = ?, priority = ?, updated_at = NOW()
                WHERE id = ? AND steward_id = ?
            ");
            
            $related_driver = !empty($_POST['related_driver_id']) ? $_POST['related_driver_id'] : null;
            $related_lap = !empty($_POST['related_lap']) ? $_POST['related_lap'] : null;
            
            $stmt->execute([
                $_POST['note_type'],
                $_POST['note_title'],
                $_POST['note_content'],
                $related_lap,
                $related_driver,
                $_POST['visibility'],
                $_POST['priority'],
                $_POST['note_id'],
                $_SESSION['user_id']
            ]);
            
            $_SESSION['success'] = 'Steward note updated successfully!';
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
    
    header('Location: stewards.php');
    exit;
}

// Get races for dropdown
$races_stmt = $pdo->query("
    SELECT r.id, r.name, r.race_date, s.name as season_name 
    FROM races r 
    JOIN seasons s ON r.season_id = s.id 
    WHERE r.race_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY r.race_date DESC
");
$races = $races_stmt->fetchAll();

// Get drivers for dropdown
$drivers_stmt = $pdo->query("
    SELECT d.id, u.username, d.driver_number, t.name as team_name 
    FROM drivers d 
    JOIN users u ON d.user_id = u.id 
    LEFT JOIN teams t ON d.team_id = t.id 
    ORDER BY u.username
");
$drivers = $drivers_stmt->fetchAll();

// Get steward notes
$filter_race = $_GET['race_id'] ?? '';
$filter_type = $_GET['note_type'] ?? '';

$notes_query = "
    SELECT sn.*, r.name as race_name, r.race_date, u.username as steward_name,
           d.driver_number, du.username as driver_name, t.name as team_name
    FROM steward_notes sn
    JOIN races r ON sn.race_id = r.id
    JOIN users u ON sn.steward_id = u.id
    LEFT JOIN drivers d ON sn.related_driver_id = d.id
    LEFT JOIN users du ON d.user_id = du.id
    LEFT JOIN teams t ON d.team_id = t.id
    WHERE 1=1
";

$params = [];
if ($filter_race) {
    $notes_query .= " AND sn.race_id = ?";
    $params[] = $filter_race;
}
if ($filter_type) {
    $notes_query .= " AND sn.note_type = ?";
    $params[] = $filter_type;
}

$notes_query .= " ORDER BY sn.created_at DESC LIMIT 50";

$notes_stmt = $pdo->prepare($notes_query);
$notes_stmt->execute($params);
$notes = $notes_stmt->fetchAll();

// Get edit note if requested
$edit_note = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_stmt = $pdo->prepare("SELECT * FROM steward_notes WHERE id = ? AND steward_id = ?");
    $edit_stmt->execute([$_GET['edit'], $_SESSION['user_id']]);
    $edit_note = $edit_stmt->fetch();
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <?php include 'includes/sidebar.php'; ?>
        </div>
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>🏁 Steward Notes & Race Management</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#noteModal">
                    <i class="fas fa-plus"></i> New Note
                </button>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="race_id" class="form-label">Filter by Race</label>
                            <select name="race_id" id="race_id" class="form-select">
                                <option value="">All Races</option>
                                <?php foreach ($races as $race): ?>
                                    <option value="<?= $race['id'] ?>" <?= $filter_race == $race['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($race['name']) ?> - <?= date('M j, Y', strtotime($race['race_date'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="note_type" class="form-label">Filter by Type</label>
                            <select name="note_type" id="note_type" class="form-select">
                                <option value="">All Types</option>
                                <option value="general" <?= $filter_type == 'general' ? 'selected' : '' ?>>General</option>
                                <option value="driver_warning" <?= $filter_type == 'driver_warning' ? 'selected' : '' ?>>Driver Warning</option>
                                <option value="track_condition" <?= $filter_type == 'track_condition' ? 'selected' : '' ?>>Track Condition</option>
                                <option value="safety_concern" <?= $filter_type == 'safety_concern' ? 'selected' : '' ?>>Safety Concern</option>
                                <option value="rule_clarification" <?= $filter_type == 'rule_clarification' ? 'selected' : '' ?>>Rule Clarification</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-outline-secondary me-2">Filter</button>
                            <a href="stewards.php" class="btn btn-outline-secondary">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Steward Notes List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">📝 Recent Steward Notes</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($notes)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No steward notes found. Create your first note!</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Race</th>
                                        <th>Type</th>
                                        <th>Title</th>
                                        <th>Priority</th>
                                        <th>Steward</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($notes as $note): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($note['race_name']) ?></strong><br>
                                                <small class="text-muted"><?= date('M j, Y', strtotime($note['race_date'])) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= getNoteTypeBadgeClass($note['note_type']) ?>">
                                                    <?= ucwords(str_replace('_', ' ', $note['note_type'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($note['note_title']) ?></strong>
                                                <?php if ($note['related_lap']): ?>
                                                    <br><small class="text-muted">Lap <?= $note['related_lap'] ?></small>
                                                <?php endif; ?>
                                                <?php if ($note['driver_name']): ?>
                                                    <br><small class="text-muted">Driver: #<?= $note['driver_number'] ?> <?= htmlspecialchars($note['driver_name']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= getPriorityBadgeClass($note['priority']) ?>">
                                                    <?= ucfirst($note['priority']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($note['steward_name']) ?></td>
                                            <td>
                                                <?= date('M j, H:i', strtotime($note['created_at'])) ?>
                                                <?php if ($note['updated_at'] != $note['created_at']): ?>
                                                    <br><small class="text-muted">Updated: <?= date('M j, H:i', strtotime($note['updated_at'])) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-info" onclick="viewNote(<?= $note['id'] ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($note['steward_id'] == $_SESSION['user_id']): ?>
                                                    <a href="?edit=<?= $note['id'] ?>" class="btn btn-sm btn-outline-warning">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Note Modal -->
<div class="modal fade" id="noteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <?= $edit_note ? 'Edit Steward Note' : 'Create New Steward Note' ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?= $edit_note ? 'update_note' : 'create_note' ?>">
                    <?php if ($edit_note): ?>
                        <input type="hidden" name="note_id" value="<?= $edit_note['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label for="race_id_modal" class="form-label">Race *</label>
                            <select name="race_id" id="race_id_modal" class="form-select" required>
                                <option value="">Select Race</option>
                                <?php foreach ($races as $race): ?>
                                    <option value="<?= $race['id'] ?>" 
                                        <?= ($edit_note && $edit_note['race_id'] == $race['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($race['name']) ?> - <?= date('M j, Y', strtotime($race['race_date'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="note_type_modal" class="form-label">Note Type *</label>
                            <select name="note_type" id="note_type_modal" class="form-select" required>
                                <option value="general" <?= ($edit_note && $edit_note['note_type'] == 'general') ? 'selected' : '' ?>>General</option>
                                <option value="driver_warning" <?= ($edit_note && $edit_note['note_type'] == 'driver_warning') ? 'selected' : '' ?>>Driver Warning</option>
                                <option value="track_condition" <?= ($edit_note && $edit_note['note_type'] == 'track_condition') ? 'selected' : '' ?>>Track Condition</option>
                                <option value="safety_concern" <?= ($edit_note && $edit_note['note_type'] == 'safety_concern') ? 'selected' : '' ?>>Safety Concern</option>
                                <option value="rule_clarification" <?= ($edit_note && $edit_note['note_type'] == 'rule_clarification') ? 'selected' : '' ?>>Rule Clarification</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="note_title" class="form-label">Note Title *</label>
                        <input type="text" name="note_title" id="note_title" class="form-control" 
                               value="<?= $edit_note ? htmlspecialchars($edit_note['note_title']) : '' ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="note_content" class="form-label">Note Content *</label>
                        <textarea name="note_content" id="note_content" class="form-control" rows="4" required><?= $edit_note ? htmlspecialchars($edit_note['note_content']) : '' ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <label for="related_lap" class="form-label">Related Lap</label>
                            <input type="number" name="related_lap" id="related_lap" class="form-control" min="1"
                                   value="<?= $edit_note ? $edit_note['related_lap'] : '' ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="related_driver_id" class="form-label">Related Driver</label>
                            <select name="related_driver_id" id="related_driver_id" class="form-select">
                                <option value="">No specific driver</option>
                                <?php foreach ($drivers as $driver): ?>
                                    <option value="<?= $driver['id'] ?>"
                                        <?= ($edit_note && $edit_note['related_driver_id'] == $driver['id']) ? 'selected' : '' ?>>
                                        #<?= $driver['driver_number'] ?> <?= htmlspecialchars($driver['username']) ?>
                                        <?= $driver['team_name'] ? ' (' . htmlspecialchars($driver['team_name']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="priority" class="form-label">Priority *</label>
                            <select name="priority" id="priority" class="form-select" required>
                                <option value="low" <?= ($edit_note && $edit_note['priority'] == 'low') ? 'selected' : '' ?>>Low</option>
                                <option value="medium" <?= ($edit_note && $edit_note['priority'] == 'medium') ? 'selected' : 'selected' ?>>Medium</option>
                                <option value="high" <?= ($edit_note && $edit_note['priority'] == 'high') ? 'selected' : '' ?>>High</option>
                                <option value="urgent" <?= ($edit_note && $edit_note['priority'] == 'urgent') ? 'selected' : '' ?>>Urgent</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="visibility" class="form-label">Visibility *</label>
                        <select name="visibility" id="visibility" class="form-select" required>
                            <option value="stewards_only" <?= ($edit_note && $edit_note['visibility'] == 'stewards_only') ? 'selected' : 'selected' ?>>Stewards Only</option>
                            <option value="race_director_only" <?= ($edit_note && $edit_note['visibility'] == 'race_director_only') ? 'selected' : '' ?>>Race Director Only</option>
                            <option value="public" <?= ($edit_note && $edit_note['visibility'] == 'public') ? 'selected' : '' ?>>Public</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <?= $edit_note ? 'Update Note' : 'Create Note' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Note Modal -->
<div class="modal fade" id="viewNoteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Steward Note Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewNoteContent">
                <!-- Content loaded via JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
<?php if ($edit_note): ?>
    // Show edit modal if edit parameter is present
    document.addEventListener('DOMContentLoaded', function() {
        var modal = new bootstrap.Modal(document.getElementById('noteModal'));
        modal.show();
    });
<?php endif; ?>

function viewNote(noteId) {
    // Fetch note details via AJAX and show in modal
    fetch('ajax/get_steward_note.php?id=' + noteId)
        .then(response => response.json())
        .then(data => {
            document.getElementById('viewNoteContent').innerHTML = data.html;
            var modal = new bootstrap.Modal(document.getElementById('viewNoteModal'));
            modal.show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading note details');
        });
}
</script>

<?php
function getNoteTypeBadgeClass($type) {
    switch ($type) {
        case 'general': return 'secondary';
        case 'driver_warning': return 'warning';
        case 'track_condition': return 'info';
        case 'safety_concern': return 'danger';
        case 'rule_clarification': return 'primary';
        default: return 'secondary';
    }
}

function getPriorityBadgeClass($priority) {
    switch ($priority) {
        case 'low': return 'success';
        case 'medium': return 'secondary';
        case 'high': return 'warning';
        case 'urgent': return 'danger';
        default: return 'secondary';
    }
}

include '../includes/footer.php';
?>
