<?php
require_once 'config/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $race_id = (int)($_POST['race_id'] ?? 0);
    $user_id = $_SESSION['user_id'] ?? 0;

    // Get driver_id for this user
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT id FROM drivers WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $driver = $stmt->fetch();

    if (!$driver) {
        echo json_encode(['success' => false, 'message' => 'Driver profile not found.']);
        exit;
    }

    $driver_id = $driver['id'];

    // Insert registration if not already registered
    $stmt = $conn->prepare("INSERT IGNORE INTO race_registrations (race_id, driver_id) VALUES (:race_id, :driver_id)");
    $stmt->bindParam(':race_id', $race_id);
    $stmt->bindParam(':driver_id', $driver_id);
    $stmt->execute();

    echo json_encode(['success' => true]);
}
?>