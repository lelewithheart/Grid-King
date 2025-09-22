<?php
/**
 * Discord Notification Service
 * Sende Benachrichtigungen an Discord Webhook
 */
function send_discord_notification($message, $webhook_url) {
    $data = json_encode(["content" => $message]);
    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}
