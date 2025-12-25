<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$userId = $_SESSION['user_id'];

// Статистика
$stmt = $db->prepare("
    SELECT 
        (SELECT COUNT(*) FROM articles WHERE user_id = ?) as article_count,
        (SELECT COUNT(*) FROM comments WHERE user_id = ?) as comment_count,
        (SELECT SUM(views) FROM articles WHERE user_id = ?) as total_views
");
$stmt->execute([$userId, $userId, $userId]);
$stats = $stmt->fetch();

// Статьи пользователя
$stmt = $db->prepare("
    SELECT * FROM articles 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$userId]);
$articles = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель управления - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php require_once 'navbar.php'; ?>

    <div class="container mt-4">
        <h1 class="mb-4">Панель управления</h1>
        
        <!-- Статистика -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Статьи</h5>
                                <h2><?= $stats['article_count'] ?></h2>
                            </div>
                            <span style="font-size: 2rem;">📝</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Комментарии</h5>
                                <h2><?= $stats['comment_count'] ?></h2>
                            </div>
                            <span style="font-size: 2rem;">💬</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Просмотры</h5>
                                <h2><?= $stats['total_views'] ?></h2>
                            </div>
                            <span style="font-size: 2rem;">👁️</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Действия -->
        <div class="row mb-4">
            <div class="col">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Быстрые действия</h5>
                        <div class="d-grid gap-2 d-md-block">
                            <a href="create_article.php" class="btn btn-primary me-2">Новая статья</a>
                            <a href="profile.php" class="btn btn-secondary">Редактировать профиль</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Последние статьи -->
        <div class="row">
            <div class="col">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Последние статьи</h5>
                        <?php if (empty($articles)): ?>
                            <p class="text-muted">У вас пока нет статей</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Заголовок</th>
                                            <th>Дата</th>
                                            <th>Просмотры</th>
                                            <th>Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($articles as $article): ?>
                                            <tr>
                                                <td>
                                                    <a href="article.php?id=<?= $article['id'] ?>">
                                                        <?= escape(substr($article['title'], 0, 50)) ?>
                                                    </a>
                                                </td>
                                                <td><?= formatDate($article['created_at']) ?></td>
                                                <td><?= $article['views'] ?></td>
                                                <td>
                                                    <a href="edit_article.php?id=<?= $article['id'] ?>" 
                                                       class="btn btn-sm btn-warning">Редактировать</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="search.php?q=&author=<?= $userId ?>" class="btn btn-outline-primary">
                                Все статьи
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>