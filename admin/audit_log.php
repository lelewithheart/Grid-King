<?php
/**
 * Admin – Audit Log (Legacy 1.4.1)
 * View and search admin action history for accountability and debugging.
 */

require_once '../config/config.php';
requireAdmin();

$db   = new Database();
$conn = $db->getConnection();

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Filters
$filterUser = sanitizeInput($_GET['user'] ?? '');
$filterAction = sanitizeInput($_GET['action'] ?? '');
$filterDateFrom = sanitizeInput($_GET['date_from'] ?? '');
$filterDateTo = sanitizeInput($_GET['date_to'] ?? '');

// Build query
$where = [];
$params = [];

if ($filterUser) {
    $where[] = "(u.username LIKE :user OR al.user_id = :user_id)";
    $params[':user'] = '%' . $filterUser . '%';
    $params[':user_id'] = is_numeric($filterUser) ? (int)$filterUser : -1;
}
if ($filterAction) {
    $where[] = "al.action_type LIKE :action";
    $params[':action'] = '%' . $filterAction . '%';
}
if ($filterDateFrom) {
    $where[] = "al.created_at >= :date_from";
    $params[':date_from'] = $filterDateFrom . ' 00:00:00';
}
if ($filterDateTo) {
    $where[] = "al.created_at <= :date_to";
    $params[':date_to'] = $filterDateTo . ' 23:59:59';
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM audit_log al LEFT JOIN users u ON al.user_id = u.id $whereClause");
$countStmt->execute($params);
$totalRows = $countStmt->fetch()['total'];
$totalPages = ceil($totalRows / $perPage);

// Get logs
$query = "
    SELECT al.*, u.username
    FROM audit_log al
    LEFT JOIN users u ON al.user_id = u.id
    $whereClause
    ORDER BY al.created_at DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $conn->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique action types for filter dropdown
$actionsStmt = $conn->prepare("SELECT DISTINCT action_type FROM audit_log ORDER BY action_type");
$actionsStmt->execute();
$actionTypes = array_column($actionsStmt->fetchAll(PDO::FETCH_ASSOC), 'action_type');

$page_title = 'Audit Log';
include '../includes/header.php';
?>

<div class="container my-5">
    <div class="d-flex align-items-center mb-4 gap-3">
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="mb-0"><i class="bi bi-journal-text me-2"></i>Audit Log</h1>
        <span class="badge bg-info">Legacy 1.4.1</span>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-funnel me-2"></i>Filters
        </div>
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">User</label>
                    <input type="text" name="user" class="form-control" placeholder="Username or ID" value="<?php echo htmlspecialchars($filterUser); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Action Type</label>
                    <select name="action" class="form-select">
                        <option value="">All Actions</option>
                        <?php foreach ($actionTypes as $at): ?>
                            <option value="<?php echo htmlspecialchars($at); ?>" <?php echo $filterAction === $at ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($at); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filterDateTo); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Filter</button>
                    <a href="audit_log.php" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Results -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Action History</h5>
            <span class="badge bg-secondary"><?php echo number_format($totalRows); ?> records</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox display-1"></i>
                    <h4 class="mt-3">No Audit Logs Found</h4>
                    <p>Admin actions will appear here once the audit system records them.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 160px;">Timestamp</th>
                                <th style="width: 120px;">User</th>
                                <th style="width: 150px;">Action</th>
                                <th>Details</th>
                                <th style="width: 120px;">IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="small text-muted"><?php echo formatDate($log['created_at']); ?></td>
                                    <td>
                                        <?php if ($log['username']): ?>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($log['username']); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $actionClass = 'secondary';
                                        if (str_contains($log['action_type'], 'create')) $actionClass = 'success';
                                        elseif (str_contains($log['action_type'], 'update') || str_contains($log['action_type'], 'edit')) $actionClass = 'info';
                                        elseif (str_contains($log['action_type'], 'delete') || str_contains($log['action_type'], 'remove')) $actionClass = 'danger';
                                        elseif (str_contains($log['action_type'], 'login') || str_contains($log['action_type'], 'logout')) $actionClass = 'warning';
                                        ?>
                                        <span class="badge bg-<?php echo $actionClass; ?>"><?php echo htmlspecialchars($log['action_type']); ?></span>
                                    </td>
                                    <td class="small">
                                        <?php 
                                        $details = json_decode($log['details'], true);
                                        if (is_array($details)) {
                                            $summary = [];
                                            foreach ($details as $key => $val) {
                                                if (is_array($val)) $val = json_encode($val);
                                                $summary[] = htmlspecialchars($key) . ': ' . htmlspecialchars(substr((string)$val, 0, 50));
                                            }
                                            echo implode(' | ', array_slice($summary, 0, 3));
                                            if (count($summary) > 3) echo ' ...';
                                        } else {
                                            echo htmlspecialchars(substr($log['details'] ?? '', 0, 100));
                                        }
                                        ?>
                                    </td>
                                    <td class="small text-muted font-monospace"><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <nav>
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&user=<?php echo urlencode($filterUser); ?>&action=<?php echo urlencode($filterAction); ?>&date_from=<?php echo urlencode($filterDateFrom); ?>&date_to=<?php echo urlencode($filterDateTo); ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php 
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        for ($p = $startPage; $p <= $endPage; $p++): 
                        ?>
                            <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $p; ?>&user=<?php echo urlencode($filterUser); ?>&action=<?php echo urlencode($filterAction); ?>&date_from=<?php echo urlencode($filterDateFrom); ?>&date_to=<?php echo urlencode($filterDateTo); ?>"><?php echo $p; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&user=<?php echo urlencode($filterUser); ?>&action=<?php echo urlencode($filterAction); ?>&date_from=<?php echo urlencode($filterDateFrom); ?>&date_to=<?php echo urlencode($filterDateTo); ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
