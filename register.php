<?php
/**
 * User Registration Page
 */

require_once 'config/config.php';

$page_title = 'Register';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = sanitizeInput($_POST['role'] ?? 'spectator');
    
    // Driver-specific fields
    $driver_number = !empty($_POST['driver_number']) ? (int)$_POST['driver_number'] : null;
    $platform = sanitizeInput($_POST['platform'] ?? '');
    $country = sanitizeInput($_POST['country'] ?? '');
    
    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif ($role === 'driver' && (empty($platform) || $driver_number < 1 || $driver_number > 999)) {
        $error = 'Drivers must select a platform and choose a valid number (1-999).';
    } else {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Check if username or email already exists
        $checkQuery = "SELECT id FROM users WHERE username = :username OR email = :email";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bindParam(':username', $username);
        $checkStmt->bindParam(':email', $email);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            $error = 'Username or email already exists.';
        } else {
            // Check if driver number is taken (for drivers only)
            if ($role === 'driver' && $driver_number) {
                $numberQuery = "SELECT id FROM drivers WHERE driver_number = :number";
                $numberStmt = $conn->prepare($numberQuery);
                $numberStmt->bindParam(':number', $driver_number);
                $numberStmt->execute();
                
                if ($numberStmt->rowCount() > 0) {
                    $error = "Driver number #{$driver_number} is already taken.";
                }
            }
            
            if (!$error) {
                try {
                    $conn->beginTransaction();
                    
                    // Create user account
                    $password_hash = password_hash($password, PASSWORD_HASH_ALGO);
                    $userQuery = "INSERT INTO users (username, email, password_hash, role, verified) VALUES (:username, :email, :password_hash, :role, TRUE)";
                    $userStmt = $conn->prepare($userQuery);
                    $userStmt->bindParam(':username', $username);
                    $userStmt->bindParam(':email', $email);
                    $userStmt->bindParam(':password_hash', $password_hash);
                    $userStmt->bindParam(':role', $role);
                    $userStmt->execute();
                    
                    $user_id = $conn->lastInsertId();
                    
                    // Create driver profile if role is driver
                    if ($role === 'driver') {
                        $driverQuery = "INSERT INTO drivers (user_id, driver_number, platform, country, team_id) VALUES (:user_id, :driver_number, :platform, :country, 1)";
                        $driverStmt = $conn->prepare($driverQuery);
                        $driverStmt->bindParam(':user_id', $user_id);
                        $driverStmt->bindParam(':driver_number', $driver_number);
                        $driverStmt->bindParam(':platform', $platform);
                        $driverStmt->bindParam(':country', $country);
                        $driverStmt->execute();
                    }
                    
                    $conn->commit();
                    $success = 'Registration successful! You can now log in.';
                    
                } catch (Exception $e) {
                    $conn->rollBack();
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow">
                <div class="card-header bg-dark text-white text-center">
                    <h4 class="mb-0"><i class="bi bi-person-plus me-2"></i>Register</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($success); ?>
                            <div class="mt-2">
                                <a href="login.php" class="btn btn-sm btn-primary">Login Now</a>
                            </div>
                        </div>
                    <?php else: ?>
                    
                    <form method="POST" action="" id="registerForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <div class="form-text">Minimum 6 characters</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="role" class="form-label">Account Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" onchange="toggleDriverFields()">
                                <option value="spectator" <?php echo ($role ?? '') === 'spectator' ? 'selected' : ''; ?>>Spectator</option>
                                <option value="driver" <?php echo ($role ?? '') === 'driver' ? 'selected' : ''; ?>>Driver</option>
                            </select>
                            <div class="form-text">Spectators can view races and standings. Drivers can participate in championships.</div>
                        </div>
                        
                        <!-- Driver-specific fields -->
                        <div id="driverFields" style="display: none;">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Driver Registration:</strong> Please provide additional information for race participation.
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="driver_number" class="form-label">Driver Number</label>
                                        <input type="number" class="form-control" id="driver_number" name="driver_number" 
                                               min="1" max="999" value="<?php echo htmlspecialchars($driver_number ?? ''); ?>">
                                        <div class="form-text">Choose a unique number (1-999)</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="platform" class="form-label">Gaming Platform</label>
                                        <select class="form-select" id="platform" name="platform">
                                            <option value="">Select Platform</option>
                                            <option value="PC" <?php echo ($platform ?? '') === 'PC' ? 'selected' : ''; ?>>PC</option>
                                            <option value="Xbox" <?php echo ($platform ?? '') === 'Xbox' ? 'selected' : ''; ?>>Xbox</option>
                                            <option value="PlayStation" <?php echo ($platform ?? '') === 'PlayStation' ? 'selected' : ''; ?>>PlayStation</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="country" class="form-label">Country</label>
                                <input type="text" class="form-control" id="country" name="country" 
                                       value="<?php echo htmlspecialchars($country ?? ''); ?>" maxlength="3" placeholder="e.g., USA, GBR, GER">
                                <div class="form-text">3-letter country code (optional)</div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-racing w-100">
                            <i class="bi bi-person-plus me-2"></i>Create Account
                        </button>
                    </form>
                    
                    <hr>
                    
                    <div class="text-center">
                        <p class="mb-2">Already have an account?</p>
                        <a href="login.php" class="btn btn-outline-primary">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Login Here
                        </a>
                    </div>
                    
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleDriverFields() {
    const role = document.getElementById('role').value;
    const driverFields = document.getElementById('driverFields');
    
    if (role === 'driver') {
        driverFields.style.display = 'block';
        // Make driver fields required
        document.getElementById('driver_number').required = true;
        document.getElementById('platform').required = true;
    } else {
        driverFields.style.display = 'none';
        // Remove required attribute
        document.getElementById('driver_number').required = false;
        document.getElementById('platform').required = false;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleDriverFields();
});

// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (password !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php include 'includes/footer.php'; ?>