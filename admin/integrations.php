<?php
require_once '../config/config.php';
requireAdmin();
require_once '../config/discord.php';
require_once '../config/google_calendar.php';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Initialize integration classes
$discordIntegration = new DiscordIntegration($db);
$calendarIntegration = new GoogleCalendarIntegration($db);

// Fetch current settings
$stmt = $conn->prepare("SELECT `key`, `value` FROM settings");
$stmt->execute();
$settings = [];
foreach ($stmt->fetchAll() as $row) {
    $settings[$row['key']] = $row['value'];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'test_discord') {
        // Test Discord webhook
        if ($discordIntegration->testWebhook()) {
            $success = 'Discord webhook test successful! Check your Discord channel.';
        } else {
            $error = 'Discord webhook test failed. Please check your webhook URL.';
        }
    }
    
    elseif ($action === 'test_calendar') {
        // Test Google Calendar connection
        $result = $calendarIntegration->testConnection();
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
    
    elseif ($action === 'sync_calendar') {
        // Sync all races to calendar
        $result = $calendarIntegration->syncAllRaces();
        if (!empty($result['errors'])) {
            $error = 'Sync completed with errors: ' . implode(', ', $result['errors']);
        } else {
            $success = 'Successfully synced ' . $result['synced'] . ' races to calendar.';
        }
    }
    
    elseif ($action === 'save_integrations') {
        // Save integration settings
        $integrationSettings = [
            // Discord Settings
            'discord_webhook' => trim($_POST['discord_webhook'] ?? ''),
            'notify_driver_register' => isset($_POST['notify_driver_register']) ? '1' : '0',
            'notify_race_result' => isset($_POST['notify_race_result']) ? '1' : '0',
            'notify_team_created' => isset($_POST['notify_team_created']) ? '1' : '0',
            'notify_upcoming_race' => isset($_POST['notify_upcoming_race']) ? '1' : '0',
            'notify_standings_update' => isset($_POST['notify_standings_update']) ? '1' : '0',
            
            // Google Calendar Settings
            'google_client_id' => trim($_POST['google_client_id'] ?? ''),
            'google_client_secret' => trim($_POST['google_client_secret'] ?? ''),
            'google_calendar_id' => trim($_POST['google_calendar_id'] ?? ''),
            'calendar_sync_enabled' => isset($_POST['calendar_sync_enabled']) ? '1' : '0',
            'timezone' => $_POST['timezone'] ?? 'UTC',
            
            // Discord Bot Settings
            'discord_bot_token' => trim($_POST['discord_bot_token'] ?? ''),
            'discord_server_id' => trim($_POST['discord_server_id'] ?? ''),
            'discord_channel_results' => trim($_POST['discord_channel_results'] ?? ''),
            'discord_channel_notifications' => trim($_POST['discord_channel_notifications'] ?? ''),
            'discord_bot_enabled' => isset($_POST['discord_bot_enabled']) ? '1' : '0'
        ];

        foreach ($integrationSettings as $key => $value) {
            $stmt = $conn->prepare("REPLACE INTO settings (`key`, `value`) VALUES (:key, :value)");
            $stmt->bindParam(':key', $key);
            $stmt->bindParam(':value', $value);
            $stmt->execute();
        }

        $success = 'Integration settings saved successfully!';
        
        // Refresh settings
        $stmt = $conn->prepare("SELECT `key`, `value` FROM settings");
        $stmt->execute();
        $settings = [];
        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['key']] = $row['value'];
        }
    }
    
    elseif ($action === 'google_auth') {
        // Initiate Google OAuth flow
        $redirectUri = BASE_URL . '/admin/integrations.php?google_callback=1';
        $authUrl = $calendarIntegration->getAuthUrl($redirectUri);
        header('Location: ' . $authUrl);
        exit();
    }
}

// Handle Google OAuth callback
if (isset($_GET['google_callback']) && isset($_GET['code'])) {
    $redirectUri = BASE_URL . '/admin/integrations.php?google_callback=1';
    if ($calendarIntegration->exchangeAuthCode($_GET['code'], $redirectUri)) {
        $success = 'Google Calendar authorization successful!';
    } else {
        $error = 'Google Calendar authorization failed.';
    }
}

include '../includes/header.php';
?>

<div class="container my-5">
    <h1 class="mb-4"><i class="bi bi-puzzle me-2"></i>Integration Settings</h1>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Discord Integration -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-discord me-2"></i>Discord Integration</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_integrations">
                        
                        <div class="mb-3">
                            <label for="discord_webhook" class="form-label">Webhook URL</label>
                            <input type="url" name="discord_webhook" id="discord_webhook" class="form-control" 
                                   value="<?php echo htmlspecialchars($settings['discord_webhook'] ?? ''); ?>" 
                                   placeholder="https://discord.com/api/webhooks/...">
                            <div class="form-text">Create a webhook in your Discord server settings</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notification Settings</label>
                            <div class="form-check">
                                <input type="checkbox" name="notify_driver_register" id="notify_driver_register" 
                                       class="form-check-input" <?php echo ($settings['notify_driver_register'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                <label for="notify_driver_register" class="form-check-label">New driver registrations</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="notify_race_result" id="notify_race_result" 
                                       class="form-check-input" <?php echo ($settings['notify_race_result'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                <label for="notify_race_result" class="form-check-label">Race results</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="notify_team_created" id="notify_team_created" 
                                       class="form-check-input" <?php echo ($settings['notify_team_created'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                <label for="notify_team_created" class="form-check-label">New team creation</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="notify_upcoming_race" id="notify_upcoming_race" 
                                       class="form-check-input" <?php echo ($settings['notify_upcoming_race'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                <label for="notify_upcoming_race" class="form-check-label">Upcoming race reminders</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="notify_standings_update" id="notify_standings_update" 
                                       class="form-check-input" <?php echo ($settings['notify_standings_update'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                <label for="notify_standings_update" class="form-check-label">Championship standings updates</label>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Save Discord Settings</button>
                        </div>
                    </form>
                    
                    <hr>
                    
                    <form method="POST" class="d-grid">
                        <input type="hidden" name="action" value="test_discord">
                        <button type="submit" class="btn btn-outline-primary" 
                                <?php echo empty($settings['discord_webhook']) ? 'disabled' : ''; ?>>
                            <i class="bi bi-send me-2"></i>Test Webhook
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Google Calendar Integration -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-calendar me-2"></i>Google Calendar</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_integrations">
                        
                        <div class="mb-3">
                            <label for="google_client_id" class="form-label">Client ID</label>
                            <input type="text" name="google_client_id" id="google_client_id" class="form-control" 
                                   value="<?php echo htmlspecialchars($settings['google_client_id'] ?? ''); ?>" 
                                   placeholder="Your Google OAuth Client ID">
                        </div>

                        <div class="mb-3">
                            <label for="google_client_secret" class="form-label">Client Secret</label>
                            <input type="password" name="google_client_secret" id="google_client_secret" class="form-control" 
                                   value="<?php echo htmlspecialchars($settings['google_client_secret'] ?? ''); ?>" 
                                   placeholder="Your Google OAuth Client Secret">
                        </div>

                        <div class="mb-3">
                            <label for="google_calendar_id" class="form-label">Calendar ID</label>
                            <input type="text" name="google_calendar_id" id="google_calendar_id" class="form-control" 
                                   value="<?php echo htmlspecialchars($settings['google_calendar_id'] ?? ''); ?>" 
                                   placeholder="your-calendar@gmail.com">
                            <div class="form-text">Find in Google Calendar settings</div>
                        </div>

                        <div class="mb-3">
                            <label for="timezone" class="form-label">Timezone</label>
                            <select name="timezone" id="timezone" class="form-select">
                                <option value="UTC" <?php echo ($settings['timezone'] ?? 'UTC') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                <option value="Europe/London" <?php echo ($settings['timezone'] ?? '') === 'Europe/London' ? 'selected' : ''; ?>>Europe/London</option>
                                <option value="Europe/Berlin" <?php echo ($settings['timezone'] ?? '') === 'Europe/Berlin' ? 'selected' : ''; ?>>Europe/Berlin</option>
                                <option value="America/New_York" <?php echo ($settings['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>America/New_York</option>
                                <option value="America/Los_Angeles" <?php echo ($settings['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : ''; ?>>America/Los_Angeles</option>
                            </select>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" name="calendar_sync_enabled" id="calendar_sync_enabled" 
                                   class="form-check-input" <?php echo ($settings['calendar_sync_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
                            <label for="calendar_sync_enabled" class="form-check-label">Enable automatic synchronization</label>
                        </div>

                        <div class="d-grid gap-2 mb-3">
                            <button type="submit" class="btn btn-danger">Save Calendar Settings</button>
                        </div>
                    </form>

                    <div class="d-grid gap-2">
                        <?php if (empty($settings['google_access_token'])): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="google_auth">
                                <button type="submit" class="btn btn-outline-danger w-100">
                                    <i class="bi bi-key me-2"></i>Authorize Google Calendar
                                </button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-success w-100 mb-2" disabled>
                                <i class="bi bi-check-circle me-2"></i>Authorized
                            </button>
                            <form method="POST" class="d-grid gap-2">
                                <input type="hidden" name="action" value="test_calendar">
                                <button type="submit" class="btn btn-outline-danger">Test Connection</button>
                            </form>
                            <form method="POST" class="d-grid">
                                <input type="hidden" name="action" value="sync_calendar">
                                <button type="submit" class="btn btn-outline-danger">Sync All Races</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Discord Bot Settings -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="bi bi-robot me-2"></i>Discord Bot (Optional)</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>Note:</strong> The Discord bot is a separate application that provides interactive commands. 
                        See the documentation for setup instructions.
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="save_integrations">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="discord_bot_token" class="form-label">Bot Token</label>
                                    <input type="password" name="discord_bot_token" id="discord_bot_token" class="form-control" 
                                           value="<?php echo htmlspecialchars($settings['discord_bot_token'] ?? ''); ?>" 
                                           placeholder="Your Discord Bot Token">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="discord_server_id" class="form-label">Server ID</label>
                                    <input type="text" name="discord_server_id" id="discord_server_id" class="form-control" 
                                           value="<?php echo htmlspecialchars($settings['discord_server_id'] ?? ''); ?>" 
                                           placeholder="Your Discord Server ID">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="discord_channel_results" class="form-label">Results Channel ID</label>
                                    <input type="text" name="discord_channel_results" id="discord_channel_results" class="form-control" 
                                           value="<?php echo htmlspecialchars($settings['discord_channel_results'] ?? ''); ?>" 
                                           placeholder="Channel for race results">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="discord_channel_notifications" class="form-label">Notifications Channel ID</label>
                                    <input type="text" name="discord_channel_notifications" id="discord_channel_notifications" class="form-control" 
                                           value="<?php echo htmlspecialchars($settings['discord_channel_notifications'] ?? ''); ?>" 
                                           placeholder="Channel for general notifications">
                                </div>
                            </div>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" name="discord_bot_enabled" id="discord_bot_enabled" 
                                   class="form-check-input" <?php echo ($settings['discord_bot_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
                            <label for="discord_bot_enabled" class="form-check-label">Enable Discord Bot</label>
                        </div>

                        <button type="submit" class="btn btn-secondary">Save Bot Settings</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Integration Status -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-activity me-2"></i>Integration Status</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get integration status
                    $statusQuery = "SELECT * FROM integration_status";
                    $statusResult = $conn->query($statusQuery);
                    ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Integration</th>
                                    <th>Status</th>
                                    <th>Configuration</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($status = $statusResult->fetch()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($status['integration_name']); ?></td>
                                    <td>
                                        <?php
                                        $statusClass = 'text-secondary';
                                        if (strpos($status['status'], 'Configured') !== false) $statusClass = 'text-success';
                                        if (strpos($status['status'], 'Enabled') !== false) $statusClass = 'text-success';
                                        if (strpos($status['status'], 'Not Configured') !== false) $statusClass = 'text-danger';
                                        ?>
                                        <span class="<?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($status['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo $status['config_value'] ? 'Configured' : 'Not set'; ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
