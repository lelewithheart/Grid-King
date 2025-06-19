<?php
/**
 * Admin - Site Settings
 */

require_once '../config/config.php';

requireAdmin();

$page_title = 'Site Settings';

// No settings table in your database, so skip fetching settings
$settings = [];

include '../includes/header.php';
?>

<div class="container my-5">
    <h1 class="mb-4"><i class="bi bi-gear me-2"></i>Site Settings</h1>
    <div class="card card-racing shadow-sm">
        <div class="card-header bg-dark text-white">
            <strong>Current Settings</strong>
        </div>
        <div class="card-body">
            <div class="text-muted">No settings found. (No settings table in database.)</div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>