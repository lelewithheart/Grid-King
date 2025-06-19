<?php
/**
 * Admin News Management - Create, Edit, Delete News Articles
 */

require_once '../config/config.php';

requireAdmin();

$page_title = 'News Management';

// Get database connection
$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Handle article creation/editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_article'])) {
    $article_id = !empty($_POST['article_id']) ? (int)$_POST['article_id'] : null;
    $title = sanitizeInput($_POST['title']);
    $content = $_POST['content']; // Don't sanitize content too much as it might contain formatting
    $image = sanitizeInput($_POST['image']);
    $published = isset($_POST['published']) ? 1 : 0;
    $featured = isset($_POST['featured']) ? 1 : 0;
    
    if (empty($title) || empty($content)) {
        $error = 'Title and content are required.';
    } else {
        try {
            if ($article_id) {
                // Update existing article
                $query = "
                    UPDATE news 
                    SET title = :title, content = :content, image = :image, 
                        published = :published, featured = :featured, updated_at = NOW()
                    WHERE id = :article_id
                ";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':article_id', $article_id);
                $action = 'updated';
            } else {
                // Create new article
                $query = "
                    INSERT INTO news (title, content, image, author_id, published, featured)
                    VALUES (:title, :content, :image, :author_id, :published, :featured)
                ";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':author_id', $_SESSION['user_id']);
                $action = 'created';
            }
            
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':image', $image);
            $stmt->bindParam(':published', $published, PDO::PARAM_BOOL);
            $stmt->bindParam(':featured', $featured, PDO::PARAM_BOOL);
            
            $stmt->execute();
            $success = "Article {$action} successfully!";
            
        } catch (Exception $e) {
            $error = 'Error saving article: ' . $e->getMessage();
        }
    }
}

// Handle article deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_article'])) {
    $article_id = (int)$_POST['article_id'];
    
    try {
        $deleteQuery = "DELETE FROM news WHERE id = :article_id";
        $deleteStmt = $conn->prepare($deleteQuery);
        $deleteStmt->bindParam(':article_id', $article_id);
        $deleteStmt->execute();
        
        $success = 'Article deleted successfully!';
    } catch (Exception $e) {
        $error = 'Error deleting article: ' . $e->getMessage();
    }
}

// Get all articles
$articlesQuery = "
    SELECT n.*, u.username as author_name
    FROM news n
    LEFT JOIN users u ON n.author_id = u.id
    ORDER BY n.created_at DESC
";
$articlesStmt = $conn->prepare($articlesQuery);
$articlesStmt->execute();
$articles = $articlesStmt->fetchAll();

// Get article for editing if specified
$editArticle = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $editQuery = "SELECT * FROM news WHERE id = :id";
    $editStmt = $conn->prepare($editQuery);
    $editStmt->bindParam(':id', $edit_id);
    $editStmt->execute();
    $editArticle = $editStmt->fetch();
}

include '../includes/header.php';
?>

<div class="racing-header">
    <div class="container">
        <h1 class="display-5 fw-bold mb-3">
            <i class="bi bi-newspaper me-3"></i>News Management
        </h1>
        <p class="lead mb-0">Create and manage racing news articles and announcements</p>
    </div>
</div>

<div class="container my-5">
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            <div class="mt-2">
                <a href="../news.php" class="btn btn-sm btn-success">
                    <i class="bi bi-eye me-1"></i>View Published News
                </a>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Article Form -->
        <div class="col-lg-4">
            <div class="card card-racing shadow-sm sticky-top" style="top: 1rem;">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-<?php echo $editArticle ? 'pencil' : 'plus-circle'; ?> me-2"></i>
                        <?php echo $editArticle ? 'Edit Article' : 'Create New Article'; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <?php if ($editArticle): ?>
                            <input type="hidden" name="article_id" value="<?php echo $editArticle['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   value="<?php echo $editArticle ? htmlspecialchars($editArticle['title']) : ''; ?>" 
                                   placeholder="Article headline..." required>
                        </div>

                        <div class="mb-3">
                            <label for="content" class="form-label">Content <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="content" name="content" rows="8" 
                                      placeholder="Write your article content here..." required><?php echo $editArticle ? htmlspecialchars($editArticle['content']) : ''; ?></textarea>
                            <div class="form-text">Use line breaks for paragraphs. HTML tags are not supported.</div>
                        </div>

                        <div class="mb-3">
                            <label for="image" class="form-label">Featured Image URL</label>
                            <input type="url" class="form-control" id="image" name="image" 
                                   value="<?php echo $editArticle ? htmlspecialchars($editArticle['image']) : ''; ?>" 
                                   placeholder="https://example.com/image.jpg">
                            <div class="form-text">Optional: URL to featured image</div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="published" name="published" 
                                       <?php echo ($editArticle && $editArticle['published']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="published">
                                    Publish immediately
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="featured" name="featured" 
                                       <?php echo ($editArticle && $editArticle['featured']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="featured">
                                    Feature on homepage
                                </label>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="save_article" class="btn btn-racing">
                                <i class="bi bi-save me-2"></i><?php echo $editArticle ? 'Update Article' : 'Create Article'; ?>
                            </button>
                            <?php if ($editArticle): ?>
                                <a href="news.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle me-2"></i>Cancel Edit
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Articles List -->
        <div class="col-lg-8">
            <div class="card card-racing shadow-sm">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="bi bi-list me-2"></i>All Articles</h4>
                    <span class="badge bg-primary"><?php echo count($articles); ?> articles</span>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($articles)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Title</th>
                                        <th>Author</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Created</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($articles as $article): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($article['title']); ?></div>
                                                <small class="text-muted">
                                                    <?php echo substr(strip_tags($article['content']), 0, 80); ?>...
                                                </small>
                                                <?php if ($article['featured']): ?>
                                                    <div><span class="badge bg-warning text-dark">Featured</span></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($article['author_name'] ?? 'Unknown'); ?>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($article['published']): ?>
                                                    <span class="badge bg-success">Published</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Draft</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <small class="text-muted">
                                                    <?php echo formatDate($article['created_at']); ?>
                                                </small>
                                                <?php if ($article['updated_at'] && $article['updated_at'] != $article['created_at']): ?>
                                                    <div><small class="text-warning">Updated</small></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    <a href="?edit=<?php echo $article['id']; ?>" class="btn btn-outline-primary btn-sm" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <?php if ($article['published']): ?>
                                                        <a href="../news_detail.php?id=<?php echo $article['id']; ?>" class="btn btn-outline-info btn-sm" title="View" target="_blank">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <button class="btn btn-outline-danger btn-sm" title="Delete" 
                                                            onclick="deleteArticle(<?php echo $article['id']; ?>, '<?php echo htmlspecialchars($article['title']); ?>')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-newspaper display-1 text-muted"></i>
                            <h5 class="mt-3">No Articles Created</h5>
                            <p class="text-muted">Create your first news article using the form on the left.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Article Templates -->
            <div class="card card-racing shadow-sm mt-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-file-text me-2"></i>Quick Templates</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Use these templates to quickly create common article types:</p>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <button class="btn btn-outline-primary btn-sm w-100" onclick="useTemplate('race_report')">
                                <i class="bi bi-flag-checkered me-1"></i>Race Report
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-outline-success btn-sm w-100" onclick="useTemplate('driver_spotlight')">
                                <i class="bi bi-person-star me-1"></i>Driver Spotlight
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-outline-info btn-sm w-100" onclick="useTemplate('announcement')">
                                <i class="bi bi-megaphone me-1"></i>Announcement
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the article <strong id="deleteArticleTitle"></strong>?</p>
                <p class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="article_id" id="deleteArticleId">
                    <button type="submit" name="delete_article" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Delete Article
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function deleteArticle(articleId, articleTitle) {
    document.getElementById('deleteArticleId').value = articleId;
    document.getElementById('deleteArticleTitle').textContent = articleTitle;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function useTemplate(type) {
    const titleField = document.getElementById('title');
    const contentField = document.getElementById('content');
    
    let title = '';
    let content = '';
    
    switch(type) {
        case 'race_report':
            title = 'Race Report: [Race Name] at [Track]';
            content = `What a thrilling race we witnessed today at [Track Name]!

RACE SUMMARY:
- Winner: [Driver Name]
- Pole Position: [Driver Name]  
- Fastest Lap: [Driver Name]
- Total Participants: [Number]

RACE HIGHLIGHTS:
[Describe key moments, overtakes, incidents, and drama]

CHAMPIONSHIP IMPLICATIONS:
[How this result affects the championship standings]

QUOTES:
"[Winner's quote about the race]" - [Winner Name]

The next race will be held at [Next Track] on [Date]. Don't miss it!`;
            break;
            
        case 'driver_spotlight':
            title = 'Driver Spotlight: [Driver Name]';
            content = `This week we're featuring [Driver Name], one of our championship contenders.

DRIVER PROFILE:
- Number: #[Number]
- Team: [Team Name]
- Platform: [PC/Xbox/PlayStation]
- Current Championship Position: P[Position]

RACING BACKGROUND:
[Tell us about their racing history and achievements]

RECENT PERFORMANCE:
[Discuss their recent race results and momentum]

GOALS FOR THE SEASON:
"[Quote about their championship goals]" - [Driver Name]

Fun fact: [Interesting personal detail about the driver]

Follow [Driver Name]'s progress in the championship standings!`;
            break;
            
        case 'announcement':
            title = '[ANNOUNCEMENT] Important Championship Update';
            content = `Dear Racing League Community,

We have an important announcement regarding [Topic]:

[Main announcement content with details]

WHAT THIS MEANS:
- [Point 1]
- [Point 2]  
- [Point 3]

NEXT STEPS:
[What drivers/teams need to do]

TIMELINE:
[When these changes take effect]

If you have any questions, please contact the race administration team.

Thank you for your continued participation in our championship!

- Championship Management Team`;
            break;
    }
    
    titleField.value = title;
    contentField.value = content;
    contentField.focus();
}

// Auto-save draft functionality (optional enhancement)
let autoSaveTimer;
function autoSave() {
    const title = document.getElementById('title').value;
    const content = document.getElementById('content').value;
    
    if (title.trim() || content.trim()) {
        // In a real implementation, you'd save this as a draft
        console.log('Auto-saving draft...');
    }
}

document.getElementById('title').addEventListener('input', () => {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(autoSave, 2000);
});

document.getElementById('content').addEventListener('input', () => {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(autoSave, 2000);
});
</script>

<?php include '../includes/footer.php'; ?>