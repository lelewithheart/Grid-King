<?php
/**
 * Discord Notification Trigger für Incidents und Appeals
 * Wird in Incidents/Appeals-Logik eingebunden
 */
require_once __DIR__ . '/../config/discord.php';
require_once __DIR__ . '/discord_notify.php';

function notify_discord_incident($incident_id, $db) {
    global $DISCORD_WEBHOOK_URL;
    $stmt = $db->prepare("SELECT i.*, r.name AS race_name FROM race_incidents i LEFT JOIN races r ON i.race_id = r.id WHERE i.id = ?");
    $stmt->execute([$incident_id]);
    $incident = $stmt->fetch();
    if ($incident) {
        $msg = "🚨 Neues Incident im Rennen " . $incident['race_name'] . " (ID: " . $incident['id'] . ")\nTyp: " . $incident['type'] . "\nSchweregrad: " . $incident['severity'] . "\nStatus: " . $incident['status'];
        send_discord_notification($msg, $DISCORD_WEBHOOK_URL);
    }
}

function notify_discord_appeal($appeal_id, $db) {
    global $DISCORD_WEBHOOK_URL;
    $stmt = $db->prepare("SELECT a.*, u.username AS driver_name, r.name AS race_name FROM penalty_appeals a LEFT JOIN users u ON a.driver_id = u.id LEFT JOIN races r ON a.race_id = r.id WHERE a.id = ?");
    $stmt->execute([$appeal_id]);
    $appeal = $stmt->fetch();
    if ($appeal) {
        $msg = "📢 Neue Berufung von " . $appeal['driver_name'] . " im Rennen " . $appeal['race_name'] . " (ID: " . $appeal['id'] . ")\nBegründung: " . $appeal['reason'];
        send_discord_notification($msg, $DISCORD_WEBHOOK_URL);
    }
}
