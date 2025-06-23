<?php
// Fetch settings for theme color
if (!isset($settings)) {
    $settings = [];
    try {
        $db = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT `key`, `value` FROM settings");
        $stmt->execute();
        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['key']] = $row['value'];
        }
    } catch (Exception $e) {
        // fallback: use default color
    }
}
$themeColor = $settings['theme_color'] ?? '#dc2626';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Chart.js for statistics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --racing-red: <?php echo htmlspecialchars($themeColor); ?>;
            --racing-black: #1f2937;
            --racing-gold: #f59e0b;
        }
        
        .navbar-brand {
            font-weight: bold;
            color: var(--racing-red) !important;
        }
        
        .racing-header {
            background: linear-gradient(135deg, var(--racing-black) 0%, var(--racing-red) 100%);
            color: white;
            padding: 2rem 0;
        }
        
        .card-racing {
            border-left: 4px solid var(--racing-red);
            transition: transform 0.2s;
        }
        
        .card-racing:hover {
            transform: translateY(-2px);
        }
        
        .btn-racing {
            background-color: var(--racing-red);
            border-color: var(--racing-red);
            color: white;
        }
        
        .btn-racing:hover {
            background-color: #b91c1c;
            border-color: #b91c1c;
            color: white;
        }
        
        .standings-table {
            font-size: 0.9rem;
        }
        
        .position-1 { background-color: #fef3c7; }
        .position-2 { background-color: #f3f4f6; }
        .position-3 { background-color: #fde68a; }
        
        .race-countdown {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: var(--racing-red);
        }
        
        .track-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .sidebar {
            background: #181c20;
            min-height: 100vh;
            border-right: 2px solid var(--racing-red);
        }
        .sidebar .nav-link {
            font-size: 1.1rem;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            transition: background 0.2s;
        }
        .sidebar .nav-link.active, .sidebar .nav-link:hover {
            background: var(--racing-red);
            color: #fff !important;
        }
        
        /* Sidebar overlay style */
        #sidebarMenu {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1040;
            height: 100vh;
            min-width: 220px;
            background: #181c20;
            border-right: 2px solid var(--racing-red);
            overflow-y: auto;
            transition: left 0.2s;
        }
        #sidebarMenu.active {
            display: block;
        }
        .sidebar-backdrop {
            display: none;
            position: fixed;
            z-index: 1039;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.3);
        }
        .sidebar-backdrop.active {
            display: block;
        }
        
        @media (max-width: 991px) {
            #sidebarMenu { display: none !important; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/index.php">
                <i class="bi bi-flag-checkered me-2"></i><?php echo APP_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/index.php"><i class="bi bi-house me-1"></i>Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/standings.php"><i class="bi bi-trophy me-1"></i>Standings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/calendar.php"><i class="bi bi-calendar-event me-1"></i>Calendar</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/drivers.php"><i class="bi bi-people me-1"></i>Drivers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/teams.php"><i class="bi bi-shield me-1"></i>Teams</a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/news.php"><i class="bi bi-newspaper me-1"></i>News</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <?php if (isAdmin()): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-gear me-1"></i>Admin
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="/admin/dashboard.php">Dashboard</a></li>
                                    <li><a class="dropdown-item" href="/admin/races.php">Manage Races</a></li>
                                    <li><a class="dropdown-item" href="/admin/results.php">Race Results</a></li>
                                    <li><a class="dropdown-item" href="/admin/penalties.php">Penalties</a></li>
                                    <li><a class="dropdown-item" href="/admin/news.php">News</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle me-1"></i><?php echo $_SESSION['username']; ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="/profile.php">Profile</a></li>
                                <li><a class="dropdown-item" href="/dashboard.php">Dashboard</a></li>
                                <li><a class="dropdown-item" href="/logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php"><i class="bi bi-box-arrow-in-right me-1"></i>Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php"><i class="bi bi-person-plus me-1"></i>Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar Navigation (hidden by default, toggled only by JS if you want to add a custom button in the future) -->
    <div class="d-flex">
        <nav id="sidebarMenu" class="bg-dark sidebar px-3 py-4" style="min-width:220px; min-height:100vh; display:none;">
            <ul class="nav flex-column">
                <li class="nav-item mb-2">
                    <a class="nav-link text-white" href="/index.php"><i class="bi bi-house me-2"></i>Home</a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link text-white" href="/standings.php"><i class="bi bi-trophy me-2"></i>Standings</a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link text-white" href="/calendar.php"><i class="bi bi-calendar-event me-2"></i>Calendar</a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link text-white" href="/drivers.php"><i class="bi bi-people me-2"></i>Drivers</a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link text-white" href="/teams.php"><i class="bi bi-shield me-2"></i>Teams</a>
                </li>
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="/news.php"><i class="bi bi-newspaper me-2"></i>News</a>
                    </li>
                <?php endif; ?>
                <?php if (isAdmin()): ?>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="/admin/dashboard.php"><i class="bi bi-gear me-2"></i>Admin Panel</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <main class="flex-grow-1" style="padding:0;">
            <script>
const sidebar = document.getElementById('sidebarMenu');
const toggleBtn = document.getElementById('sidebarToggle');
const backdrop = document.getElementById('sidebarBackdrop');

function openSidebar() {
    sidebar.classList.add('active');
    backdrop.classList.add('active');
}
function closeSidebar() {
    sidebar.classList.remove('active');
    backdrop.classList.remove('active');
}
toggleBtn.addEventListener('click', function() {
    if (sidebar.classList.contains('active')) {
        closeSidebar();
    } else {
        openSidebar();
    }
});
backdrop.addEventListener('click', closeSidebar);

// Optional: close sidebar on resize to large screens
window.addEventListener('resize', function() {
    if (window.innerWidth >= 992) {
        closeSidebar();
    }
});
</script>
<div id="sidebarBackdrop" class="sidebar-backdrop"></div>
<div class="container-fluid">
    <!-- Your main content goes here -->
</div>