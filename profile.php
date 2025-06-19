<?php
/**
 * User Profile Editing Page
 */

require_once 'config/config.php';

requireLogin();

$page_title = 'Edit Profile';

// Get database connection
$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Get user details
$userQuery = "SELECT * FROM users WHERE id = :user_id";
$userStmt = $conn->prepare($userQuery);
$userStmt->bindParam(':user_id', $_SESSION['user_id']);
$userStmt->execute();
$user = $userStmt->fetch();

// Get driver profile if exists
$driverProfile = null;
if ($user['role'] === 'driver') {
    $driverQuery = "
        SELECT d.*, t.name as team_name 
        FROM drivers d 
        LEFT JOIN teams t ON d.team_id = t.id 
        WHERE d.user_id = :user_id
    ";
    $driverStmt = $conn->prepare($driverQuery);
    $driverStmt->bindParam(':user_id', $_SESSION['user_id']);
    $driverStmt->execute();
    $driverProfile = $driverStmt->fetch();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Driver specific fields
    $driver_number = !empty($_POST['driver_number']) ? (int)$_POST['driver_number'] : null;
    $platform = sanitizeInput($_POST['platform'] ?? '');
    $country = sanitizeInput($_POST['country'] ?? '');
    $bio = sanitizeInput($_POST['bio'] ?? '');
    
    // Validation
    if (empty($username) || empty($email)) {
        $error = 'Username and email are required.';
    } elseif (!password_verify($current_password, $user['password_hash'])) {
        $error = 'Current password is incorrect.';
    } elseif (!empty($new_password) && strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } else {
        // Check if username/email already exists (excluding current user)
        $checkQuery = "SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :user_id";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bindParam(':username', $username);
        $checkStmt->bindParam(':email', $email);
        $checkStmt->bindParam(':user_id', $_SESSION['user_id']);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            $error = 'Username or email already exists.';
        } else {
            // Check driver number if changed
            if ($driverProfile && $driver_number && $driver_number != $driverProfile['driver_number']) {
                $numberQuery = "SELECT id FROM drivers WHERE driver_number = :number AND id != :driver_id";
                $numberStmt = $conn->prepare($numberQuery);
                $numberStmt->bindParam(':number', $driver_number);
                $numberStmt->bindParam(':driver_id', $driverProfile['id']);
                $numberStmt->execute();
                
                if ($numberStmt->rowCount() > 0) {
                    $error = "Driver number #{$driver_number} is already taken.";
                }
            }
            
            if (!$error) {
                try {
                    $conn->beginTransaction();
                    
                    // Update user account
                    if (!empty($new_password)) {
                        $password_hash = password_hash($new_password, PASSWORD_HASH_ALGO);
                        $userUpdateQuery = "UPDATE users SET username = :username, email = :email, password_hash = :password_hash WHERE id = :user_id";
                        $userUpdateStmt = $conn->prepare($userUpdateQuery);
                        $userUpdateStmt->bindParam(':password_hash', $password_hash);
                    } else {
                        $userUpdateQuery = "UPDATE users SET username = :username, email = :email WHERE id = :user_id";
                        $userUpdateStmt = $conn->prepare($userUpdateQuery);
                    }
                    
                    $userUpdateStmt->bindParam(':username', $username);
                    $userUpdateStmt->bindParam(':email', $email);
                    $userUpdateStmt->bindParam(':user_id', $_SESSION['user_id']);
                    $userUpdateStmt->execute();
                    
                    // Update driver profile if exists
                    if ($driverProfile) {
                        $driverUpdateQuery = "
                            UPDATE drivers 
                            SET driver_number = :driver_number, platform = :platform, country = :country, bio = :bio 
                            WHERE id = :driver_id
                        ";
                        $driverUpdateStmt = $conn->prepare($driverUpdateQuery);
                        $driverUpdateStmt->bindParam(':driver_number', $driver_number);
                        $driverUpdateStmt->bindParam(':platform', $platform);
                        $driverUpdateStmt->bindParam(':country', $country);
                        $driverUpdateStmt->bindParam(':bio', $bio);
                        $driverUpdateStmt->bindParam(':driver_id', $driverProfile['id']);
                        $driverUpdateStmt->execute();
                    }
                    
                    $conn->commit();
                    
                    // Update session username
                    $_SESSION['username'] = $username;
                    $_SESSION['email'] = $email;
                    
                    $success = 'Profile updated successfully!';
                    
                    // Refresh user data
                    $user['username'] = $username;
                    $user['email'] = $email;
                    
                    if ($driverProfile) {
                        $driverProfile['driver_number'] = $driver_number;
                        $driverProfile['platform'] = $platform;
                        $driverProfile['country'] = $country;
                        $driverProfile['bio'] = $bio;
                    }
                    
                } catch (Exception $e) {
                    $conn->rollBack();
                    $error = 'Error updating profile. Please try again.';
                }
            }
        }
    }
}

// Handle file upload for livery
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_livery']) && $driverProfile) {
    if (isset($_FILES['livery_image']) && $_FILES['livery_image']['error'] === 0) {
        $file = $_FILES['livery_image'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        
        if (!in_array($file['type'], $allowed_types)) {
            $error = 'Only JPEG, PNG, and GIF images are allowed.';
        } elseif ($file['size'] > MAX_FILE_SIZE) {
            $error = 'File size must be less than 5MB.';
        } else {
            $upload_dir = 'uploads/liveries/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'livery_' . $driverProfile['id'] . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Update database
                $updateQuery = "UPDATE drivers SET livery_image = :livery_image WHERE id = :driver_id";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bindParam(':livery_image', $upload_path);
                $updateStmt->bindParam(':driver_id', $driverProfile['id']);
                
                if ($updateStmt->execute()) {
                    $success = 'Livery image uploaded successfully!';
                    $driverProfile['livery_image'] = $upload_path;
                } else {
                    $error = 'Error saving livery image.';
                    unlink($upload_path);
                }
            } else {
                $error = 'Error uploading file.';
            }
        }
    } else {
        $error = 'Please select a valid image file.';
    }
}

include 'includes/header.php';
?>

<div class="racing-header">
    <div class="container">
        <h1 class="display-5 fw-bold mb-3">
            <i class="bi bi-person-gear me-3"></i>Edit Profile
        </h1>
        <p class="lead mb-0">Update your account settings and driver information</p>
    </div>
</div>

<div class="container my-5">
    <div class="row">
        <!-- Main Form -->
        <div class="col-lg-8">
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Account Information -->
            <div class="card card-racing shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h4 class="mb-0"><i class="bi bi-person me-2"></i>Account Information</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    <div class="form-text">Required to save any changes</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                    <div class="form-text">Leave blank to keep current password</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                            </div>
                        </div>

                        <?php if ($driverProfile): ?>
                            <hr>
                            <h5 class="mb-3"><i class="bi bi-car-front me-2"></i>Driver Information</h5>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="driver_number" class="form-label">Driver Number</label>
                                        <input type="number" class="form-control" id="driver_number" name="driver_number" 
                                               value="<?php echo $driverProfile['driver_number']; ?>" min="1" max="999">
                                        <div class="form-text">Choose a unique number (1-999)</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="platform" class="form-label">Gaming Platform</label>
                                        <select class="form-select" id="platform" name="platform">
                                            <option value="PC" <?php echo $driverProfile['platform'] === 'PC' ? 'selected' : ''; ?>>PC</option>
                                            <option value="Xbox" <?php echo $driverProfile['platform'] === 'Xbox' ? 'selected' : ''; ?>>Xbox</option>
                                            <option value="PlayStation" <?php echo $driverProfile['platform'] === 'PlayStation' ? 'selected' : ''; ?>>PlayStation</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="country" class="form-label">Country</label>
                                        <input type="text" class="form-control" id="country" name="country" 
                                               value="<?php echo htmlspecialchars($driverProfile['country']); ?>" 
                                               maxlength="3" placeholder="e.g., USA, GBR, GER">
                                        <div class="form-text">3-letter country code</div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="bio" class="form-label">Bio</label>
                                <textarea class="form-control" id="bio" name="bio" rows="3" 
                                          placeholder="Tell others about yourself..."><?php echo htmlspecialchars($driverProfile['bio']); ?></textarea>
                                <div class="form-text">Share your racing background, achievements, or goals</div>
                            </div>
                        <?php endif; ?>

                        <div class="text-end">
                            <button type="submit" class="btn btn-racing">
                                <i class="bi bi-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Livery Upload (Driver Only) -->
            <?php if ($driverProfile): ?>
                <div class="card card-racing shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <h4 class="mb-0"><i class="bi bi-image me-2"></i>Driver Livery</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Current Livery:</h6>
                                <?php if ($driverProfile['livery_image']): ?>
                                    <img src="<?php echo htmlspecialchars($driverProfile['livery_image']); ?>" 
                                         alt="Current livery" class="img-fluid rounded mb-3" style="max-height: 200px;">
                                <?php else: ?>
                                    <div class="bg-light rounded p-4 text-center mb-3">
                                        <i class="bi bi-image display-4 text-muted"></i>
                                        <p class="text-muted mb-0">No livery uploaded</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <form method="POST" action="" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label for="livery_image" class="form-label">Upload New Livery</label>
                                        <input type="file" class="form-control" id="livery_image" name="livery_image" 
                                               accept="image/jpeg,image/jpg,image/png,image/gif">
                                        <div class="form-text">
                                            JPEG, PNG, or GIF. Max 5MB.<br>
                                            Recommended: 1920x1080 or similar racing car image.
                                        </div>
                                    </div>
                                    <button type="submit" name="upload_livery" class="btn btn-outline-primary">
                                        <i class="bi bi-upload me-2"></i>Upload Livery
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Profile Summary -->
            <div class="card card-racing shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-person-circle me-2"></i>Profile Summary</h5>
                </div>
                <div class="card-body text-center">
                    <?php if ($driverProfile && $driverProfile['livery_image']): ?>
                        <img src="<?php echo htmlspecialchars($driverProfile['livery_image']); ?>" 
                             alt="Profile" class="rounded-circle mb-3" style="width: 80px; height: 80px; object-fit: cover;">
                    <?php else: ?>
                        <i class="bi bi-person-circle display-1 text-muted mb-3"></i>
                    <?php endif; ?>
                    
                    <h5><?php echo htmlspecialchars($user['username']); ?></h5>
                    <span class="badge bg-<?php 
                        echo match($user['role']) {
                            'admin' => 'danger',
                            'driver' => 'success',
                            default => 'primary'
                        };
                    ?> mb-3">
                        <?php echo ucfirst($user['role']); ?>
                    </span>

                    <?php if ($driverProfile): ?>
                        <div class="row text-center">
                            <div class="col-6">
                                <strong>Number</strong>
                                <div class="text-muted"><?php echo $driverProfile['driver_number'] ? '#' . $driverProfile['driver_number'] : 'Not set'; ?></div>
                            </div>
                            <div class="col-6">
                                <strong>Platform</strong>
                                <div class="text-muted"><?php echo htmlspecialchars($driverProfile['platform']); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="card card-racing shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-link me-2"></i>Quick Links</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="dashboard.php" class="btn btn-outline-primary">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                        <?php if ($driverProfile): ?>
                            <a href="driver.php?id=<?php echo $driverProfile['id']; ?>" class="btn btn-outline-success">
                                <i class="bi bi-person me-1"></i>View Public Profile
                            </a>
                        <?php endif; ?>
                        <a href="standings.php" class="btn btn-outline-info">
                            <i class="bi bi-trophy me-1"></i>Standings
                        </a>
                        <a href="calendar.php" class="btn btn-outline-warning">
                            <i class="bi bi-calendar-event me-1"></i>Calendar
                        </a>
                        <hr>
                        <a href="logout.php" class="btn btn-outline-secondary">
                            <i class="bi bi-box-arrow-right me-1"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword && confirmPassword && newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

// Clear confirm password when new password is cleared
document.getElementById('new_password').addEventListener('input', function() {
    if (!this.value) {
        document.getElementById('confirm_password').value = '';
    }
});
</script>

<?php include 'includes/footer.php'; ?>