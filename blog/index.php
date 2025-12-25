<?php
require_once 'config.php';

$db = getDB();

// Пагинация
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Получение статей
$stmt = $db->prepare("
    SELECT a.*, u.username, u.avatar 
    FROM articles a 
    JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, ITEMS_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$articles = $stmt->fetchAll();

// Общее количество статей
$totalStmt = $db->query("SELECT COUNT(*) as total FROM articles");
$totalArticles = $totalStmt->fetch()['total'];
$totalPages = ceil($totalArticles / ITEMS_PER_PAGE);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .article-card { transition: transform 0.2s; }
        .article-card:hover { transform: translateY(-5px); }
        .avatar { width: 40px; height: 40px; border-radius: 50%; }
        .rating-btn { border: none; background: none; }
        .rating-btn.active { color: #0d6efd; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php"><?= SITE_NAME ?></a>
            <div class="navbar-nav ms-auto">
                <form class="d-flex me-3" action="search.php" method="GET">
                    <input class="form-control me-2" type="search" name="q" placeholder="Поиск...">
                    <button class="btn btn-outline-light" type="submit">Найти</button>
                </form>
                <?php if (isLoggedIn()): ?>
                    <a class="nav-link" href="dashboard.php">Панель</a>
                    <a class="nav-link" href="profile.php">
                        <img src="<?= UPLOAD_DIR . ($_SESSION['avatar'] ?? 'default.png') ?>" 
                             class="avatar me-1" alt="Аватар">
                        <?= escape($_SESSION['username'] ?? '') ?>
                    </a>
                    <a class="nav-link" href="logout.php">Выход</a>
                <?php else: ?>
                    <a class="nav-link" href="login.php">Вход</a>
                    <a class="nav-link" href="register.php">Регистрация</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h1>Последние статьи</h1>
                <?php if (isLoggedIn()): ?>
                    <a href="create_article.php" class="btn btn-primary">Новая статья</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <?php foreach ($articles as $article): ?>
                <?php $rating = getArticleRating($article['id']); ?>
                <div class="col-md-6 mb-4">
                    <div class="card article-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="d-flex align-items-center">
                                    <img src="<?= UPLOAD_DIR . $article['avatar'] ?>" class="avatar me-2">
                                    <span><?= escape($article['username']) ?></span>
                                </div>
                                <small class="text-muted"><?= formatDate($article['created_at']) ?></small>
                            </div>
                            
                            <h5 class="card-title">
                                <a href="article.php?id=<?= $article['id'] ?>" class="text-decoration-none">
                                    <?= escape($article['title']) ?>
                                </a>
                            </h5>
                            
                            <p class="card-text text-truncate">
                                <?= escape(substr($article['content'], 0, 150)) ?>...
                            </p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="rating-buttons">
                                    <span class="badge bg-success">👍 <?= $rating['likes'] ?? 0 ?></span>
                                    <span class="badge bg-danger ms-1">👎 <?= $rating['dislikes'] ?? 0 ?></span>
                                </div>
                                <div>
                                    <span class="badge bg-secondary me-2">
                                        👁️ <?= $article['views'] ?>
                                    </span>
                                    <?php
                                        $stmt = $db->prepare("SELECT COUNT(*) FROM comments WHERE article_id = ?");
                                        $stmt->execute([$article['id']]);
                                        $commentCount = $stmt->fetchColumn();
                                    ?>
                                    <a href="article.php?id=<?= $article['id'] ?>#comments" class="text-decoration-none">
                                        <span class="badge bg-info">💬 <?= $commentCount ?></span>
                                    </a>
                                </div>
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
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>