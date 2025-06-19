<?php
/**
 * User Logout
 */

require_once 'config/config.php';

// Destroy session and redirect
session_destroy();
header('Location: index.php?logout=success');
exit();
?>