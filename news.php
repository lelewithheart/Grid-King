<?php
/**
 * News Page - Display published news articles
 */

require_once 'config/config.php';

$page_title = 'News';

// Get database connection
$db = new Database();
$conn = $db->getConnection();

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 6;
$offset = ($page - 1) * $per_page;

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as count FROM news WHERE published = TRUE";
$countStmt = $conn->prepare($countQuery);
$countStmt->execute();
$total_articles = $countStmt->fetch()['count'];
$total_pages = ceil($total_articles / $per_page);

// Get news articles
$newsQuery = "
    SELECT n.*, u.username as author_name
    FROM news n
    LEFT JOIN users u ON n.author_id = u.id
    WHERE n.published = TRUE
    ORDER BY n.featured DESC, n.created_at DESC
    LIMIT :limit OFFSET :offset
";
$newsStmt = $conn->prepare($newsQuery);
$newsStmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
$newsStmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$newsStmt->execute();
$articles = $newsStmt->fetchAll();

// Get featured article (latest featured)
$featuredQuery = "
    SELECT n.*, u.username as author_name
    FROM news n
    LEFT JOIN users u ON n.author_id = u.id
    WHERE n.published = TRUE AND n.featured = TRUE
    ORDER BY n.created_at DESC
    LIMIT 1
";
$featuredStmt = $conn->prepare($featuredQuery);
$featuredStmt->execute();
$featured = $featuredStmt->fetch();

include 'includes/header.php';
?>

<div class="racing-header">
    <div class="container">
        <h1 class="display-5 fw-bold mb-3">
            <i class="bi bi-newspaper me-3"></i>Racing News
        </h1>
        <p class="lead mb-0">Latest updates, announcements, and race reports</p>
    </div>
</div>

<div class="container my-5">
    <!-- Featured Article -->
    <?php if ($featured): ?>
        <div class="card card-racing shadow-lg mb-5">
            <?php if ($featured['image']): ?>
                <img src="<?php echo htmlspecialchars($featured['image']); ?>" 
                     class="card-img-top" style="height: 300px; object-fit: cover;" 
                     alt="<?php echo htmlspecialchars($featured['title']); ?>">
            <?php endif; ?>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="badge bg-danger fs-6">Featured</span>
                    <small class="text-muted">
                        By <?php echo htmlspecialchars($featured['author_name'] ?? 'Admin'); ?> • 
                        <?php echo formatDate($featured['created_at']); ?>
                    </small>
                </div>
                <h2 class="card-title">
                    <a href="news_detail.php?id=<?php echo $featured['id']; ?>" class="text-decoration-none">
                        <?php echo htmlspecialchars($featured['title']); ?>
                    </a>
                </h2>
                <p class="card-text">
                    <?php echo substr(strip_tags($featured['content']), 0, 300); ?>...
                </p>
                <a href="news_detail.php?id=<?php echo $featured['id']; ?>" class="btn btn-racing">
                    <i class="bi bi-arrow-right me-2"></i>Read Full Article
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- News Grid -->
    <?php if (!empty($articles)): ?>
        <div class="row">
            <?php foreach ($articles as $article): ?>
                <?php if ($featured && $article['id'] == $featured['id']) continue; // Skip featured article ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card card-racing h-100 shadow-sm">
                        <?php if ($article['image']): ?>
                            <img src="<?php echo htmlspecialchars($article['image']); ?>" 
                                 class="card-img-top" style="height: 200px; object-fit: cover;" 
                                 alt="<?php echo htmlspecialchars($article['title']); ?>">
                        <?php else: ?>
                            <div class="bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                <i class="bi bi-newspaper display-4 text-muted"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body d-flex flex-column">
                            <div class="mb-2">
                                <small class="text-muted">
                                    <i class="bi bi-person me-1"></i>
                                    <?php echo htmlspecialchars($article['author_name'] ?? 'Admin'); ?>
                                </small>
                                <small class="text-muted ms-3">
                                    <i class="bi bi-calendar me-1"></i>
                                    <?php echo formatDate($article['created_at']); ?>
                                </small>
                            </div>
                            
                            <h5 class="card-title">
                                <a href="news_detail.php?id=<?php echo $article['id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($article['title']); ?>
                                </a>
                            </h5>
                            
                            <p class="card-text flex-grow-1">
                                <?php echo substr(strip_tags($article['content']), 0, 150); ?>...
                            </p>
                            
                            <div class="mt-auto">
                                <a href="news_detail.php?id=<?php echo $article['id']; ?>" class="btn btn-outline-primary">
                                    <i class="bi bi-eye me-1"></i>Read More
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="News pagination" class="mt-5">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                <i class="bi bi-chevron-left"></i> Previous
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                Next <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-newspaper display-1 text-muted"></i>
            <h4 class="mt-3">No News Available</h4>
            <p class="text-muted">Check back later for race reports, announcements, and championship updates.</p>
            <?php if (isAdmin()): ?>
                <a href="admin/news.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Create First Article
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Sidebar Content -->
    <div class="row mt-5">
        <div class="col-lg-8">
            <!-- Latest Race Results Widget -->
            <div class="card card-racing shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Latest Race Results</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get latest completed race
                    $latestRaceQuery = "
                        SELECT r.*, COUNT(rr.id) as participants,
                               MAX(CASE WHEN rr.position = 1 THEN u.username END) as winner
                        FROM races r
                        LEFT JOIN race_results rr ON r.id = rr.race_id
                        LEFT JOIN drivers d ON rr.driver_id = d.id
                        LEFT JOIN users u ON d.user_id = u.id
                        WHERE r.status = 'Completed'
                        GROUP BY r.id
                        ORDER BY r.race_date DESC
                        LIMIT 1
                    ";
                    $latestRaceStmt = $conn->prepare($latestRaceQuery);
                    $latestRaceStmt->execute();
                    $latestRace = $latestRaceStmt->fetch();
                    ?>
                    
                    <?php if ($latestRace): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="fw-bold"><?php echo htmlspecialchars($latestRace['name']); ?></h6>
                                <p class="mb-1"><strong>Track:</strong> <?php echo htmlspecialchars($latestRace['track']); ?></p>
                                <p class="mb-1"><strong>Date:</strong> <?php echo formatDate($latestRace['race_date']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <?php if ($latestRace['winner']): ?>
                                    <p class="mb-1"><strong>Winner:</strong> 
                                        <span class="text-success"><?php echo htmlspecialchars($latestRace['winner']); ?></span>
                                    </p>
                                <?php endif; ?>
                                <p class="mb-1"><strong>Participants:</strong> <?php echo $latestRace['participants']; ?></p>
                                <a href="race.php?id=<?php echo $latestRace['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye me-1"></i>View Results
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No completed races yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Quick Links -->
            <div class="card card-racing shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-link me-2"></i>Quick Links</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="standings.php" class="btn btn-outline-primary">
                            <i class="bi bi-trophy me-1"></i>Championship Standings
                        </a>
                        <a href="calendar.php" class="btn btn-outline-info">
                            <i class="bi bi-calendar-event me-1"></i>Race Calendar
                        </a>
                        <a href="drivers.php" class="btn btn-outline-success">
                            <i class="bi bi-people me-1"></i>All Drivers
                        </a>
                        <a href="teams.php" class="btn btn-outline-warning">
                            <i class="bi bi-shield me-1"></i>Teams
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>