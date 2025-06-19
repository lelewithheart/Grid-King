<?php
/**
 * News Article Detail Page
 */

require_once 'config/config.php';

// Get article ID from URL
$article_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$article_id) {
    header('Location: news.php');
    exit();
}

// Get database connection
$db = new Database();
$conn = $db->getConnection();

// Get article details
$articleQuery = "
    SELECT n.*, u.username as author_name
    FROM news n
    LEFT JOIN users u ON n.author_id = u.id
    WHERE n.id = :article_id AND n.published = TRUE
";
$articleStmt = $conn->prepare($articleQuery);
$articleStmt->bindParam(':article_id', $article_id);
$articleStmt->execute();
$article = $articleStmt->fetch();

if (!$article) {
    header('Location: news.php');
    exit();
}

$page_title = $article['title'] . ' - News';

// Get related articles (same author or recent)
$relatedQuery = "
    SELECT n.*, u.username as author_name
    FROM news n
    LEFT JOIN users u ON n.author_id = u.id
    WHERE n.published = TRUE AND n.id != :article_id
    ORDER BY 
        CASE WHEN n.author_id = :author_id THEN 0 ELSE 1 END,
        n.created_at DESC
    LIMIT 3
";
$relatedStmt = $conn->prepare($relatedQuery);
$relatedStmt->bindParam(':article_id', $article_id);
$relatedStmt->bindParam(':author_id', $article['author_id']);
$relatedStmt->execute();
$relatedArticles = $relatedStmt->fetchAll();

include 'includes/header.php';
?>

<div class="container my-5">
    <!-- Article Content -->
    <div class="row">
        <div class="col-lg-8">
            <article class="card card-racing shadow-sm">
                <?php if ($article['image']): ?>
                    <img src="<?php echo htmlspecialchars($article['image']); ?>" 
                         class="card-img-top" style="height: 400px; object-fit: cover;" 
                         alt="<?php echo htmlspecialchars($article['title']); ?>">
                <?php endif; ?>
                
                <div class="card-body">
                    <!-- Article Header -->
                    <div class="mb-4">
                        <?php if ($article['featured']): ?>
                            <span class="badge bg-danger mb-2">Featured</span>
                        <?php endif; ?>
                        <h1 class="display-6 fw-bold mb-3"><?php echo htmlspecialchars($article['title']); ?></h1>
                        
                        <div class="d-flex flex-wrap align-items-center text-muted mb-3">
                            <div class="me-4">
                                <i class="bi bi-person-circle me-1"></i>
                                By <strong><?php echo htmlspecialchars($article['author_name'] ?? 'Admin'); ?></strong>
                            </div>
                            <div class="me-4">
                                <i class="bi bi-calendar me-1"></i>
                                <?php echo formatDate($article['created_at']); ?>
                            </div>
                            <?php if ($article['updated_at'] && $article['updated_at'] != $article['created_at']): ?>
                                <div>
                                    <i class="bi bi-pencil me-1"></i>
                                    Updated <?php echo formatDate($article['updated_at']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <hr>
                    </div>
                    
                    <!-- Article Content -->
                    <div class="article-content">
                        <?php echo nl2br(htmlspecialchars($article['content'])); ?>
                    </div>
                    
                    <!-- Article Footer -->
                    <hr class="mt-5">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted">
                            <small>Published on <?php echo formatDate($article['created_at']); ?></small>
                        </div>
                        <div>
                            <!-- Social Share Buttons -->
                            <div class="btn-group" role="group">
                                <button class="btn btn-outline-primary btn-sm" onclick="shareArticle('twitter')" title="Share on Twitter">
                                    <i class="bi bi-twitter"></i>
                                </button>
                                <button class="btn btn-outline-primary btn-sm" onclick="shareArticle('facebook')" title="Share on Facebook">
                                    <i class="bi bi-facebook"></i>
                                </button>
                                <button class="btn btn-outline-secondary btn-sm" onclick="copyLink()" title="Copy Link">
                                    <i class="bi bi-link-45deg"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </article>

            <!-- Navigation -->
            <div class="d-flex justify-content-between align-items-center mt-4">
                <a href="news.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to News
                </a>
                <?php if (isAdmin()): ?>
                    <a href="admin/news.php?edit=<?php echo $article['id']; ?>" class="btn btn-outline-warning">
                        <i class="bi bi-pencil me-2"></i>Edit Article
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Related Articles -->
            <?php if (!empty($relatedArticles)): ?>
                <div class="card card-racing shadow-sm mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-newspaper me-2"></i>Related Articles</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($relatedArticles as $related): ?>
                            <div class="mb-3 <?php echo $related !== end($relatedArticles) ? 'border-bottom pb-3' : ''; ?>">
                                <?php if ($related['image']): ?>
                                    <img src="<?php echo htmlspecialchars($related['image']); ?>" 
                                         class="img-fluid rounded mb-2" style="height: 80px; width: 100%; object-fit: cover;" 
                                         alt="<?php echo htmlspecialchars($related['title']); ?>">
                                <?php endif; ?>
                                <h6 class="mb-1">
                                    <a href="news_detail.php?id=<?php echo $related['id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($related['title']); ?>
                                    </a>
                                </h6>
                                <small class="text-muted">
                                    <?php echo formatDate($related['created_at']); ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Latest Results -->
            <div class="card card-racing shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Latest Results</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get recent race results
                    $resultsQuery = "
                        SELECT r.name, r.track, r.race_date, u.username as winner
                        FROM races r
                        LEFT JOIN race_results rr ON r.id = rr.race_id AND rr.position = 1
                        LEFT JOIN drivers d ON rr.driver_id = d.id
                        LEFT JOIN users u ON d.user_id = u.id
                        WHERE r.status = 'Completed'
                        ORDER BY r.race_date DESC
                        LIMIT 3
                    ";
                    $resultsStmt = $conn->prepare($resultsQuery);
                    $resultsStmt->execute();
                    $recentResults = $resultsStmt->fetchAll();
                    ?>
                    
                    <?php if (!empty($recentResults)): ?>
                        <?php foreach ($recentResults as $result): ?>
                            <div class="mb-3 <?php echo $result !== end($recentResults) ? 'border-bottom pb-3' : ''; ?>">
                                <h6 class="mb-1"><?php echo htmlspecialchars($result['name']); ?></h6>
                                <div class="small text-muted">
                                    <?php echo htmlspecialchars($result['track']); ?> • 
                                    <?php echo formatDate($result['race_date']); ?>
                                </div>
                                <?php if ($result['winner']): ?>
                                    <div class="small">
                                        <i class="bi bi-trophy text-warning me-1"></i>
                                        Won by <strong><?php echo htmlspecialchars($result['winner']); ?></strong>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-center">
                            <a href="standings.php" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-trophy me-1"></i>Full Standings
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No recent results available.</p>
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
                        <a href="standings.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-trophy me-1"></i>Championship Standings
                        </a>
                        <a href="calendar.php" class="btn btn-outline-info btn-sm">
                            <i class="bi bi-calendar-event me-1"></i>Race Calendar
                        </a>
                        <a href="drivers.php" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-people me-1"></i>All Drivers
                        </a>
                        <a href="teams.php" class="btn btn-outline-warning btn-sm">
                            <i class="bi bi-shield me-1"></i>Teams
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function shareArticle(platform) {
    const url = window.location.href;
    const title = "<?php echo addslashes($article['title']); ?>";
    
    let shareUrl = '';
    if (platform === 'twitter') {
        shareUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(title)}&url=${encodeURIComponent(url)}`;
    } else if (platform === 'facebook') {
        shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
    }
    
    if (shareUrl) {
        window.open(shareUrl, '_blank', 'width=600,height=400');
    }
}

function copyLink() {
    navigator.clipboard.writeText(window.location.href).then(function() {
        // Show temporary feedback
        const btn = event.target.closest('button');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check"></i>';
        btn.classList.add('btn-success');
        btn.classList.remove('btn-outline-secondary');
        
        setTimeout(() => {
            btn.innerHTML = originalHTML;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-secondary');
        }, 2000);
    });
}
</script>

<style>
.article-content {
    font-size: 1.1rem;
    line-height: 1.8;
}

.article-content p {
    margin-bottom: 1.5rem;
}
</style>

<?php include 'includes/footer.php'; ?>