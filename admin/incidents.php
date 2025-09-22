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

$can_investigate = false;
$can_issue_penalties = false;
foreach ($user_roles as $role) {
    $permissions = json_decode($role['permissions'], true);
    if (in_array('all', $permissions) || $role['role_code'] === 'admin') {
        $can_investigate = true;
        $can_issue_penalties = true;
        break;
    }
    if (in_array('investigate_incidents', $permissions)) {
        $can_investigate = true;
    }
    if (in_array('issue_penalties', $permissions)) {
        $can_issue_penalties = true;
    }
}

if (!$can_investigate) {
    $_SESSION['error'] = 'Access denied. Incident investigation permissions required.';
    header('Location: dashboard.php');
    exit;
}

// Handle form submissions
if ($_POST && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'create_incident') {
            $drivers_involved = json_encode(array_map('intval', $_POST['drivers_involved'] ?? []));
            
            $stmt = $pdo->prepare("
                INSERT INTO race_incidents 
                (race_id, incident_type, incident_title, incident_description, incident_lap, 
                 incident_time, incident_sector, incident_turn, drivers_involved, reported_by, 
                 severity, weather_conditions, track_conditions)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $incident_time = !empty($_POST['incident_time']) ? $_POST['incident_time'] : null;
            $incident_lap = !empty($_POST['incident_lap']) ? $_POST['incident_lap'] : null;
            $incident_sector = !empty($_POST['incident_sector']) ? $_POST['incident_sector'] : null;
            
            $stmt->execute([
                $_POST['race_id'],
                $_POST['incident_type'],
                $_POST['incident_title'],
                $_POST['incident_description'],
                $incident_lap,
                $incident_time,
                $incident_sector,
                $_POST['incident_turn'],
                $drivers_involved,
                $_SESSION['user_id'],
                $_POST['severity'],
                $_POST['weather_conditions'],
                $_POST['track_conditions']
            ]);
            
            $incident_id = $pdo->lastInsertId();
            
            // Auto-assign to available steward if enabled
            $auto_assign = getSetting('incident_auto_assign');
            if ($auto_assign) {
                $steward_stmt = $pdo->prepare("
                    SELECT u.id FROM users u
                    JOIN user_role_assignments ura ON u.id = ura.user_id
                    JOIN user_roles ur ON ura.role_id = ur.id
                    WHERE JSON_CONTAINS(ur.permissions, '\"investigate_incidents\"') 
                    AND ura.is_active = 1
                    ORDER BY RAND() LIMIT 1
                ");
                $steward_stmt->execute();
                $steward = $steward_stmt->fetch();
                
                if ($steward) {
                    $assign_stmt = $pdo->prepare("UPDATE race_incidents SET steward_assigned = ? WHERE id = ?");
                    $assign_stmt->execute([$steward['id'], $incident_id]);
                }
            }
            
            $_SESSION['success'] = 'Incident reported successfully!';
            
        } elseif ($_POST['action'] === 'update_incident' && $can_investigate) {
            $drivers_involved = json_encode(array_map('intval', $_POST['drivers_involved'] ?? []));
            
            $stmt = $pdo->prepare("
                UPDATE race_incidents 
                SET incident_type = ?, incident_title = ?, incident_description = ?, incident_lap = ?, 
                    incident_time = ?, incident_sector = ?, incident_turn = ?, drivers_involved = ?, 
                    severity = ?, weather_conditions = ?, track_conditions = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $incident_time = !empty($_POST['incident_time']) ? $_POST['incident_time'] : null;
            $incident_lap = !empty($_POST['incident_lap']) ? $_POST['incident_lap'] : null;
            $incident_sector = !empty($_POST['incident_sector']) ? $_POST['incident_sector'] : null;
            
            $stmt->execute([
                $_POST['incident_type'],
                $_POST['incident_title'],
                $_POST['incident_description'],
                $incident_lap,
                $incident_time,
                $incident_sector,
                $_POST['incident_turn'],
                $drivers_involved,
                $_POST['severity'],
                $_POST['weather_conditions'],
                $_POST['track_conditions'],
                $_POST['status'],
                $_POST['incident_id']
            ]);
            
            $_SESSION['success'] = 'Incident updated successfully!';
            
        } elseif ($_POST['action'] === 'issue_decision' && $can_issue_penalties) {
            $penalty_targets = json_encode(array_map('intval', $_POST['penalty_targets'] ?? []));
            
            $stmt = $pdo->prepare("
                INSERT INTO steward_decisions 
                (incident_id, steward_id, decision_type, decision_summary, decision_reasoning, 
                 penalty_value, penalty_target, precedent_reference, appeal_deadline, is_final)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $penalty_value = !empty($_POST['penalty_value']) ? $_POST['penalty_value'] : 0;
            $appeal_deadline = null;
            if ($_POST['decision_type'] !== 'no_action' && $_POST['decision_type'] !== 'warning') {
                $appeal_hours = getSetting('appeal_deadline_hours', 72);
                $appeal_deadline = date('Y-m-d H:i:s', strtotime("+{$appeal_hours} hours"));
            }
            
            $stmt->execute([
                $_POST['incident_id'],
                $_SESSION['user_id'],
                $_POST['decision_type'],
                $_POST['decision_summary'],
                $_POST['decision_reasoning'],
                $penalty_value,
                $penalty_targets,
                $_POST['precedent_reference'],
                $appeal_deadline,
                $_POST['is_final'] ? 1 : 0
            ]);
            
            // Update incident status
            $status_stmt = $pdo->prepare("UPDATE race_incidents SET status = 'penalty_issued', resolved_at = NOW() WHERE id = ?");
            $status_stmt->execute([$_POST['incident_id']]);
            
            // Create traditional penalty record if needed
            if ($_POST['decision_type'] !== 'no_action' && $_POST['decision_type'] !== 'warning') {
                foreach ($_POST['penalty_targets'] ?? [] as $driver_id) {
                    $penalty_stmt = $pdo->prepare("
                        INSERT INTO penalties 
                        (race_id, driver_id, penalty_type, penalty_value, severity, points_deducted, 
                         time_penalty, grid_penalty, incident_description, steward_notes, issued_by)
                        SELECT race_id, ?, ?, ?, ?, 
                               CASE WHEN ? = 'points_deduction' THEN ? ELSE 0 END,
                               CASE WHEN ? = 'time_penalty' THEN ? ELSE 0 END,
                               CASE WHEN ? = 'grid_penalty' THEN ? ELSE 0 END,
                               incident_description, ?, ?
                        FROM race_incidents WHERE id = ?
                    ");
                    
                    $penalty_stmt->execute([
                        $driver_id,
                        $_POST['decision_type'],
                        $penalty_value,
                        $_POST['severity'] ?? 'minor',
                        $_POST['decision_type'], $penalty_value,
                        $_POST['decision_type'], $penalty_value,
                        $_POST['decision_type'], $penalty_value,
                        $_POST['decision_summary'],
                        $_SESSION['user_id'],
                        $_POST['incident_id']
                    ]);
                }
            }
            
            $_SESSION['success'] = 'Steward decision issued successfully!';
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
    
    header('Location: incidents.php');
    exit;
}

// Get races for dropdown
$races_stmt = $pdo->query("
    SELECT r.id, r.name, r.race_date, s.name as season_name 
    FROM races r 
    JOIN seasons s ON r.season_id = s.id 
    WHERE r.race_date >= DATE_SUB(NOW(), INTERVAL 60 DAY)
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

// Get incidents with filters
$filter_race = $_GET['race_id'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_type = $_GET['incident_type'] ?? '';

$incidents_query = "
    SELECT ri.*, r.name as race_name, r.race_date,
           u_reported.username as reported_by_name,
           u_steward.username as steward_name,
           COUNT(sd.id) as decision_count
    FROM race_incidents ri
    JOIN races r ON ri.race_id = r.id
    LEFT JOIN users u_reported ON ri.reported_by = u_reported.id
    LEFT JOIN users u_steward ON ri.steward_assigned = u_steward.id
    LEFT JOIN steward_decisions sd ON ri.id = sd.incident_id
    WHERE 1=1
";

$params = [];
if ($filter_race) {
    $incidents_query .= " AND ri.race_id = ?";
    $params[] = $filter_race;
}
if ($filter_status) {
    $incidents_query .= " AND ri.status = ?";
    $params[] = $filter_status;
}
if ($filter_type) {
    $incidents_query .= " AND ri.incident_type = ?";
    $params[] = $filter_type;
}

$incidents_query .= " GROUP BY ri.id ORDER BY ri.created_at DESC LIMIT 50";

$incidents_stmt = $pdo->prepare($incidents_query);
$incidents_stmt->execute($params);
$incidents = $incidents_stmt->fetchAll();

// Get specific incident for editing/viewing
$view_incident = null;
$incident_decisions = [];
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $view_stmt = $pdo->prepare("
        SELECT ri.*, r.name as race_name, r.race_date,
               u_reported.username as reported_by_name,
               u_steward.username as steward_name
        FROM race_incidents ri
        JOIN races r ON ri.race_id = r.id
        LEFT JOIN users u_reported ON ri.reported_by = u_reported.id
        LEFT JOIN users u_steward ON ri.steward_assigned = u_steward.id
        WHERE ri.id = ?
    ");
    $view_stmt->execute([$_GET['view']]);
    $view_incident = $view_stmt->fetch();
    
    if ($view_incident) {
        $decisions_stmt = $pdo->prepare("
            SELECT sd.*, u.username as steward_name
            FROM steward_decisions sd
            JOIN users u ON sd.steward_id = u.id
            WHERE sd.incident_id = ?
            ORDER BY sd.decision_date DESC
        ");
        $decisions_stmt->execute([$_GET['view']]);
        $incident_decisions = $decisions_stmt->fetchAll();
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <?php include 'includes/sidebar.php'; ?>
        </div>
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>⚠️ Race Incidents & Penalties</h1>
                <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#incidentModal">
                    <i class="fas fa-exclamation-triangle"></i> Report Incident
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
                        <div class="col-md-3">
                            <label for="race_id" class="form-label">Filter by Race</label>
                            <select name="race_id" id="race_id" class="form-select">
                                <option value="">All Races</option>
                                <?php foreach ($races as $race): ?>
                                    <option value="<?= $race['id'] ?>" <?= $filter_race == $race['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($race['name']) ?> - <?= date('M j', strtotime($race['race_date'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="reported" <?= $filter_status == 'reported' ? 'selected' : '' ?>>Reported</option>
                                <option value="investigating" <?= $filter_status == 'investigating' ? 'selected' : '' ?>>Investigating</option>
                                <option value="under_review" <?= $filter_status == 'under_review' ? 'selected' : '' ?>>Under Review</option>
                                <option value="penalty_issued" <?= $filter_status == 'penalty_issued' ? 'selected' : '' ?>>Penalty Issued</option>
                                <option value="no_action" <?= $filter_status == 'no_action' ? 'selected' : '' ?>>No Action</option>
                                <option value="dismissed" <?= $filter_status == 'dismissed' ? 'selected' : '' ?>>Dismissed</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="incident_type" class="form-label">Type</label>
                            <select name="incident_type" id="incident_type" class="form-select">
                                <option value="">All Types</option>
                                <option value="collision" <?= $filter_type == 'collision' ? 'selected' : '' ?>>Collision</option>
                                <option value="track_limits" <?= $filter_type == 'track_limits' ? 'selected' : '' ?>>Track Limits</option>
                                <option value="unsafe_driving" <?= $filter_type == 'unsafe_driving' ? 'selected' : '' ?>>Unsafe Driving</option>
                                <option value="blocking" <?= $filter_type == 'blocking' ? 'selected' : '' ?>>Blocking</option>
                                <option value="false_start" <?= $filter_type == 'false_start' ? 'selected' : '' ?>>False Start</option>
                                <option value="technical" <?= $filter_type == 'technical' ? 'selected' : '' ?>>Technical</option>
                                <option value="other" <?= $filter_type == 'other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-outline-secondary me-2">Filter</button>
                            <a href="incidents.php" class="btn btn-outline-secondary">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Incidents List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">🚨 Recent Incidents</h5>
                    <small class="text-muted"><?= count($incidents) ?> incidents found</small>
                </div>
                <div class="card-body">
                    <?php if (empty($incidents)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-shield-alt fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No incidents reported. Safety first!</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Race & Time</th>
                                        <th>Type</th>
                                        <th>Title</th>
                                        <th>Severity</th>
                                        <th>Status</th>
                                        <th>Steward</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($incidents as $incident): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($incident['race_name']) ?></strong><br>
                                                <small class="text-muted"><?= date('M j, Y', strtotime($incident['race_date'])) ?></small>
                                                <?php if ($incident['incident_lap']): ?>
                                                    <br><small class="badge bg-info">Lap <?= $incident['incident_lap'] ?></small>
                                                <?php endif; ?>
                                                <?php if ($incident['incident_time']): ?>
                                                    <br><small class="text-muted"><?= $incident['incident_time'] ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= getIncidentTypeBadgeClass($incident['incident_type']) ?>">
                                                    <?= ucwords(str_replace('_', ' ', $incident['incident_type'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($incident['incident_title']) ?></strong>
                                                <?php if ($incident['incident_turn']): ?>
                                                    <br><small class="text-muted">@ <?= htmlspecialchars($incident['incident_turn']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= getSeverityBadgeClass($incident['severity']) ?>">
                                                    <?= ucfirst($incident['severity']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= getStatusBadgeClass($incident['status']) ?>">
                                                    <?= ucwords(str_replace('_', ' ', $incident['status'])) ?>
                                                </span>
                                                <?php if ($incident['decision_count'] > 0): ?>
                                                    <br><small class="text-muted"><?= $incident['decision_count'] ?> decision(s)</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= $incident['steward_name'] ? htmlspecialchars($incident['steward_name']) : '<small class="text-muted">Unassigned</small>' ?>
                                            </td>
                                            <td>
                                                <a href="?view=<?= $incident['id'] ?>" class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <?php if ($can_investigate && ($incident['steward_assigned'] == $_SESSION['user_id'] || !$incident['steward_assigned'])): ?>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="editIncident(<?= $incident['id'] ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($can_issue_penalties && in_array($incident['status'], ['investigating', 'under_review'])): ?>
                                                    <button class="btn btn-sm btn-outline-success" onclick="issueDecision(<?= $incident['id'] ?>)">
                                                        <i class="fas fa-gavel"></i> Decide
                                                    </button>
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

            <!-- View Incident Details -->
            <?php if ($view_incident): ?>
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">🔍 Incident Details: <?= htmlspecialchars($view_incident['incident_title']) ?></h5>
                        <a href="incidents.php" class="btn btn-sm btn-outline-secondary">Back to List</a>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Incident Information</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Race:</strong></td><td><?= htmlspecialchars($view_incident['race_name']) ?></td></tr>
                                    <tr><td><strong>Type:</strong></td><td><?= ucwords(str_replace('_', ' ', $view_incident['incident_type'])) ?></td></tr>
                                    <tr><td><strong>Severity:</strong></td><td><span class="badge bg-<?= getSeverityBadgeClass($view_incident['severity']) ?>"><?= ucfirst($view_incident['severity']) ?></span></td></tr>
                                    <tr><td><strong>Status:</strong></td><td><span class="badge bg-<?= getStatusBadgeClass($view_incident['status']) ?>"><?= ucwords(str_replace('_', ' ', $view_incident['status'])) ?></span></td></tr>
                                    <tr><td><strong>Lap:</strong></td><td><?= $view_incident['incident_lap'] ?: 'Not specified' ?></td></tr>
                                    <tr><td><strong>Time:</strong></td><td><?= $view_incident['incident_time'] ?: 'Not specified' ?></td></tr>
                                    <tr><td><strong>Location:</strong></td><td><?= htmlspecialchars($view_incident['incident_turn']) ?: 'Not specified' ?></td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>People Involved</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Reported by:</strong></td><td><?= htmlspecialchars($view_incident['reported_by_name']) ?></td></tr>
                                    <tr><td><strong>Assigned Steward:</strong></td><td><?= $view_incident['steward_name'] ? htmlspecialchars($view_incident['steward_name']) : 'Unassigned' ?></td></tr>
                                    <tr><td><strong>Drivers Involved:</strong></td><td>
                                        <?php
                                        $involved_drivers = json_decode($view_incident['drivers_involved'], true);
                                        if ($involved_drivers) {
                                            foreach ($involved_drivers as $driver_id) {
                                                $driver = array_filter($drivers, function($d) use ($driver_id) { return $d['id'] == $driver_id; });
                                                $driver = reset($driver);
                                                if ($driver) {
                                                    echo '<span class="badge bg-secondary me-1">#' . $driver['driver_number'] . ' ' . htmlspecialchars($driver['username']) . '</span>';
                                                }
                                            }
                                        } else {
                                            echo 'None specified';
                                        }
                                        ?>
                                    </td></tr>
                                </table>
                            </div>
                        </div>
                        
                        <h6>Description</h6>
                        <p class="border p-3 bg-light"><?= nl2br(htmlspecialchars($view_incident['incident_description'])) ?></p>
                        
                        <?php if (!empty($incident_decisions)): ?>
                            <h6>Steward Decisions</h6>
                            <?php foreach ($incident_decisions as $decision): ?>
                                <div class="card mb-2">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <h6 class="card-title">
                                                    <span class="badge bg-<?= getDecisionTypeBadgeClass($decision['decision_type']) ?>">
                                                        <?= ucwords(str_replace('_', ' ', $decision['decision_type'])) ?>
                                                    </span>
                                                    <?= htmlspecialchars($decision['decision_summary']) ?>
                                                </h6>
                                                <p class="card-text"><?= nl2br(htmlspecialchars($decision['decision_reasoning'])) ?></p>
                                                <?php if ($decision['precedent_reference']): ?>
                                                    <small class="text-muted">Precedent: <?= htmlspecialchars($decision['precedent_reference']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <small class="text-muted">
                                                    By: <?= htmlspecialchars($decision['steward_name']) ?><br>
                                                    Date: <?= date('M j, Y H:i', strtotime($decision['decision_date'])) ?><br>
                                                    <?php if ($decision['appeal_deadline']): ?>
                                                        Appeal by: <?= date('M j, Y H:i', strtotime($decision['appeal_deadline'])) ?><br>
                                                    <?php endif; ?>
                                                    <?= $decision['is_final'] ? '<span class="badge bg-success">Final</span>' : '<span class="badge bg-warning">Appealable</span>' ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Report Incident Modal -->
<div class="modal fade" id="incidentModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Report Race Incident</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_incident">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label for="race_id_modal" class="form-label">Race *</label>
                            <select name="race_id" id="race_id_modal" class="form-select" required>
                                <option value="">Select Race</option>
                                <?php foreach ($races as $race): ?>
                                    <option value="<?= $race['id'] ?>">
                                        <?= htmlspecialchars($race['name']) ?> - <?= date('M j, Y', strtotime($race['race_date'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="incident_type_modal" class="form-label">Incident Type *</label>
                            <select name="incident_type" id="incident_type_modal" class="form-select" required>
                                <option value="collision">Collision</option>
                                <option value="track_limits">Track Limits</option>
                                <option value="unsafe_driving">Unsafe Driving</option>
                                <option value="blocking">Blocking</option>
                                <option value="false_start">False Start</option>
                                <option value="technical">Technical Issue</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="incident_title" class="form-label">Incident Title *</label>
                        <input type="text" name="incident_title" id="incident_title" class="form-control" required
                               placeholder="Brief description of the incident">
                    </div>
                    
                    <div class="mb-3">
                        <label for="incident_description" class="form-label">Detailed Description *</label>
                        <textarea name="incident_description" id="incident_description" class="form-control" rows="4" required
                                  placeholder="Provide detailed description of what happened..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <label for="incident_lap" class="form-label">Lap</label>
                            <input type="number" name="incident_lap" id="incident_lap" class="form-control" min="1">
                        </div>
                        <div class="col-md-3">
                            <label for="incident_time" class="form-label">Time</label>
                            <input type="time" name="incident_time" id="incident_time" class="form-control" step="1">
                        </div>
                        <div class="col-md-3">
                            <label for="incident_sector" class="form-label">Sector</label>
                            <select name="incident_sector" id="incident_sector" class="form-select">
                                <option value="">Select sector</option>
                                <option value="1">Sector 1</option>
                                <option value="2">Sector 2</option>
                                <option value="3">Sector 3</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="incident_turn" class="form-label">Turn/Location</label>
                            <input type="text" name="incident_turn" id="incident_turn" class="form-control" 
                                   placeholder="e.g. Turn 1, Start/Finish">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label for="drivers_involved" class="form-label">Drivers Involved</label>
                            <select name="drivers_involved[]" id="drivers_involved" class="form-select" multiple>
                                <?php foreach ($drivers as $driver): ?>
                                    <option value="<?= $driver['id'] ?>">
                                        #<?= $driver['driver_number'] ?> <?= htmlspecialchars($driver['username']) ?>
                                        <?= $driver['team_name'] ? ' (' . htmlspecialchars($driver['team_name']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple drivers</small>
                        </div>
                        <div class="col-md-6">
                            <label for="severity" class="form-label">Severity *</label>
                            <select name="severity" id="severity" class="form-select" required>
                                <option value="minor">Minor</option>
                                <option value="major">Major</option>
                                <option value="severe">Severe</option>
                                <option value="dangerous">Dangerous</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label for="weather_conditions" class="form-label">Weather</label>
                            <input type="text" name="weather_conditions" id="weather_conditions" class="form-control" 
                                   placeholder="e.g. Clear, Light rain, Heavy rain">
                        </div>
                        <div class="col-md-6">
                            <label for="track_conditions" class="form-label">Track Conditions *</label>
                            <select name="track_conditions" id="track_conditions" class="form-select" required>
                                <option value="dry">Dry</option>
                                <option value="wet">Wet</option>
                                <option value="mixed">Mixed</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Report Incident</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editIncident(incidentId) {
    // TODO: Implement edit modal
    alert('Edit functionality coming soon!');
}

function issueDecision(incidentId) {
    // TODO: Implement decision modal
    alert('Decision modal coming soon!');
}
</script>

<?php
// Helper functions for badge classes
function getIncidentTypeBadgeClass($type) {
    switch ($type) {
        case 'collision': return 'danger';
        case 'track_limits': return 'warning';
        case 'unsafe_driving': return 'danger';
        case 'blocking': return 'warning';
        case 'false_start': return 'info';
        case 'technical': return 'secondary';
        default: return 'primary';
    }
}

function getSeverityBadgeClass($severity) {
    switch ($severity) {
        case 'minor': return 'success';
        case 'major': return 'warning';
        case 'severe': return 'danger';
        case 'dangerous': return 'dark';
        default: return 'secondary';
    }
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'reported': return 'info';
        case 'investigating': return 'warning';
        case 'under_review': return 'primary';
        case 'penalty_issued': return 'success';
        case 'no_action': return 'secondary';
        case 'dismissed': return 'dark';
        default: return 'secondary';
    }
}

function getDecisionTypeBadgeClass($type) {
    switch ($type) {
        case 'no_action': return 'success';
        case 'warning': return 'warning';
        case 'time_penalty': return 'danger';
        case 'grid_penalty': return 'danger';
        case 'points_deduction': return 'danger';
        case 'disqualification': return 'dark';
        default: return 'secondary';
    }
}

include '../includes/footer.php';
?>
