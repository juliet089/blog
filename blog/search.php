<?php
require_once 'config.php';

$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * ITEMS_PER_PAGE;

if (empty($searchQuery)) {
    header('Location: index.php');
    exit;
}

$db = getDB();

// Поиск статей
$sql = "SELECT a.*, u.username, u.avatar FROM articles a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.title LIKE ? 
        ORDER BY a.created_at DESC 
        LIMIT ? OFFSET ?";
        
$stmt = $db->prepare($sql);
$searchParam = "%$searchQuery%";
$stmt->bindValue(1, $searchParam, PDO::PARAM_STR);
$stmt->bindValue(2, ITEMS_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$articles = $stmt->fetchAll();

// Общее количество результатов
$countStmt = $db->prepare("SELECT COUNT(*) as total FROM articles WHERE title LIKE ?");
$countStmt->execute([$searchParam]);
$totalResults = $countStmt->fetch()['total'];
$totalPages = ceil($totalResults / ITEMS_PER_PAGE);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Поиск - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php require_once 'navbar.php'; ?>

    <div class="container mt-4">
        <h1 class="mb-4">Результаты поиска</h1>
        
        <div class="mb-4">
            <form action="search.php" method="GET" class="row g-3">
                <div class="col-md-8">
                    <input type="text" class="form-control" name="q" 
                           value="<?= escape($searchQuery) ?>" placeholder="Поиск...">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">Найти</button>
                </div>
            </form>
        </div>

        <?php if (empty($articles)): ?>
            <div class="alert alert-info">
                По запросу "<?= escape($searchQuery) ?>" ничего не найдено.
            </div>
        <?php else: ?>
            <p class="text-muted mb-4">
                Найдено результатов: <strong><?= $totalResults ?></strong>
            </p>
            
            <div class="row">
                <?php foreach ($articles as $article): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <a href="article.php?id=<?= $article['id'] ?>" class="text-decoration-none">
                                        <?= highlightText($article['title'], $searchQuery) ?>
                                    </a>
                                </h5>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="d-flex align-items-center">
                                        <img src="<?= UPLOAD_DIR . $article['avatar'] ?>" 
                                             class="avatar me-2" style="width: 30px; height: 30px;">
                                        <small><?= escape($article['username']) ?></small>
                                    </div>
                                    <small class="text-muted"><?= formatDate($article['created_at']) ?></small>
                                </div>
                                <p class="card-text">
                                    <?= highlightText(substr($article['content'], 0, 200), $searchQuery) ?>...
                                </p>
                                <div class="d-flex justify-content-between">
                                    <span class="badge bg-secondary">👁️ <?= $article['views'] ?></span>
                                    <a href="article.php?id=<?= $article['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        Читать
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?q=<?= urlencode($searchQuery) ?>&page=<?= $i ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
function highlightText($text, $query) {
    $words = explode(' ', $query);
    foreach ($words as $word) {
        if (strlen($word) > 2) {
            $text = preg_replace(
                "/(" . preg_quote($word, '/') . ")/i",
                '<mark>$1</mark>',
                $text
            );
        }
    }
    return $text;
}
?>