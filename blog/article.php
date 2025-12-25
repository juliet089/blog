<?php
require_once 'config.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$db = getDB();
$articleId = (int)$_GET['id'];

// Получение статьи
$stmt = $db->prepare("
    SELECT a.*, u.username, u.avatar 
    FROM articles a 
    JOIN users u ON a.user_id = u.id 
    WHERE a.id = ?
");
$stmt->execute([$articleId]);
$article = $stmt->fetch();

if (!$article) {
    header('Location: index.php');
    exit;
}

// Увеличение счетчика просмотров
$db->prepare("UPDATE articles SET views = views + 1 WHERE id = ?")->execute([$articleId]);

// Получение рейтинга
$rating = getArticleRating($articleId);
$userRating = isLoggedIn() ? getUserRating($articleId, $_SESSION['user_id']) : 0;

// Получение комментариев с иерархией
function getComments($articleId, $parentId = null) {
    $db = getDB();
    
    // Если parent_id = 0 или NULL, ищем корневые комментарии
    if ($parentId === null || $parentId === 0) {
        $sql = "SELECT c.*, u.username, u.avatar FROM comments c 
                JOIN users u ON c.user_id = u.id 
                WHERE c.article_id = ? AND (c.parent_id IS NULL OR c.parent_id = 0)
                ORDER BY c.created_at ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$articleId]);
    } else {
        // Ищем ответы на конкретный комментарий
        $sql = "SELECT c.*, u.username, u.avatar FROM comments c 
                JOIN users u ON c.user_id = u.id 
                WHERE c.article_id = ? AND c.parent_id = ?
                ORDER BY c.created_at ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$articleId, $parentId]);
    }
    
    $comments = $stmt->fetchAll();
    
    // Рекурсивно получаем ответы на каждый комментарий
    foreach ($comments as &$comment) {
        $comment['replies'] = getComments($articleId, $comment['id']);
    }
    
    return $comments;
}

$comments = getComments($articleId);

// Обработка добавления комментария
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    if (!isLoggedIn()) {
        $error = 'Для добавления комментария необходимо войти в систему';
    } else {
        $content = trim($_POST['content'] ?? '');
        $parentId = isset($_POST['parent_id']) && !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        
        // Проверяем, что parent_id существует в БД, если он указан
        if ($parentId) {
            $checkStmt = $db->prepare("SELECT id FROM comments WHERE id = ? AND article_id = ?");
            $checkStmt->execute([$parentId, $articleId]);
            if (!$checkStmt->fetch()) {
                $parentId = null; // Если комментарий не существует, делаем его корневым
            }
        }
        
        if (empty($content)) {
            $error = 'Комментарий не может быть пустым';
        } elseif (strlen($content) < 3) {
            $error = 'Комментарий должен содержать минимум 3 символа';
        } elseif (strlen($content) > 1000) {
            $error = 'Комментарий слишком длинный (максимум 1000 символов)';
        } else {
            // Вставляем комментарий с правильным parent_id (NULL для корневых)
            $stmt = $db->prepare("
                INSERT INTO comments (article_id, user_id, parent_id, content)
                VALUES (?, ?, ?, ?)
            ");
            
            // Преобразуем 0 в NULL для parent_id
            $parentId = ($parentId === 0 || $parentId === null) ? null : $parentId;
            
            try {
                $stmt->execute([$articleId, $_SESSION['user_id'], $parentId, $content]);
                
                // Обновляем список комментариев
                $comments = getComments($articleId);
                
                // Редирект на ту же страницу
                header("Location: article.php?id=$articleId#comments");
                exit;
            } catch (PDOException $e) {
                $error = 'Ошибка при добавлении комментария: ' . $e->getMessage();
                // $error .= '<br>Parent ID: ' . ($parentId ?: 'NULL');
            }
        }
    }
}

// Обработка рейтинга
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rate'])) {
    if (!isLoggedIn()) {
        $error = 'Для оценки необходимо войти в систему';
    } else {
        $value = (int)$_POST['value'];
        $userId = $_SESSION['user_id'];
        
        // Удаляем предыдущий рейтинг пользователя
        $db->prepare("DELETE FROM ratings WHERE article_id = ? AND user_id = ?")
           ->execute([$articleId, $userId]);
        
        // Добавляем новый
        if ($value !== 0) {
            $db->prepare("INSERT INTO ratings (article_id, user_id, value) VALUES (?, ?, ?)")
               ->execute([$articleId, $userId, $value]);
        }
        
        header("Location: article.php?id=$articleId");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($article['title']) ?> - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .comment { border-left: 2px solid #dee2e6; padding-left: 15px; margin-bottom: 15px; }
        .comment .comment { margin-left: 30px; }
        .rating-btn.active { color: #0d6efd; font-weight: bold; }
        .reply-form { display: none; margin-top: 10px; }
        .reply-form.active { display: block; }
    </style>
</head>
<body>
    <?php 
    
    if (!file_exists('navbar.php')) {
        // Простая навигация
        echo '<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="index.php">'.SITE_NAME.'</a>
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="index.php">Главная</a>
                    '.(isLoggedIn() ? '
                    <a class="nav-link" href="profile.php">'.escape($_SESSION['username']).'</a>
                    <a class="nav-link" href="logout.php">Выход</a>
                    ' : '
                    <a class="nav-link" href="login.php">Вход</a>
                    <a class="nav-link" href="register.php">Регистрация</a>
                    ').'
                </div>
            </div>
        </nav>';
    } else {
        require_once 'navbar.php';
    }
    ?>

    <div class="container mt-4">
        <!-- Навигация -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Главная</a></li>
                <li class="breadcrumb-item active"><?= escape($article['title']) ?></li>
            </ol>
        </nav>

        <!-- Статья -->
        <article class="mb-5">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="d-flex align-items-center">
                            <img src="<?= UPLOAD_DIR . $article['avatar'] ?>" class="avatar me-3" 
                                 alt="<?= escape($article['username']) ?>">
                            <div>
                                <h5 class="mb-0"><?= escape($article['username']) ?></h5>
                                <small class="text-muted"><?= formatDate($article['created_at']) ?></small>
                            </div>
                        </div>
                        
                        <div class="rating-section">
                            <form method="POST" class="d-inline">
                                <button type="submit" name="rate" 
                                        class="rating-btn btn btn-sm <?= $userRating == 1 ? 'btn-primary' : 'btn-outline-primary' ?>"
                                        onclick="this.form.querySelector('input[name=\"value\"]').value = 1">
                                    👍 <?= $rating['likes'] ?? 0 ?>
                                </button>
                                <button type="submit" name="rate" 
                                        class="rating-btn btn btn-sm <?= $userRating == -1 ? 'btn-danger' : 'btn-outline-danger' ?>"
                                        onclick="this.form.querySelector('input[name=\"value\"]').value = -1">
                                    👎 <?= $rating['dislikes'] ?? 0 ?>
                                </button>
                                <input type="hidden" name="value" value="">
                            </form>
                            <span class="badge bg-secondary ms-2">👁️ <?= $article['views'] ?></span>
                        </div>
                    </div>
                    
                    <h1 class="card-title mb-4"><?= escape($article['title']) ?></h1>
                    
                    <div class="article-content">
                        <?= nl2br(escape($article['content'])) ?>
                    </div>
                    
                    <?php if (isLoggedIn() && ($_SESSION['user_id'] == $article['user_id'] || isAdmin())): ?>
                        <div class="mt-4 pt-3 border-top">
                            <a href="edit_article.php?id=<?= $article['id'] ?>" class="btn btn-warning">
                                Редактировать
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </article>

        <!-- Комментарии -->
        <section id="comments" class="mb-5">
            <h3 class="mb-4">
                Комментарии 
                <span class="badge bg-secondary">
                    <?php 
                        $stmt = $db->prepare("SELECT COUNT(*) FROM comments WHERE article_id = ?");
                        $stmt->execute([$articleId]);
                        echo $stmt->fetchColumn();
                    ?>
                </span>
            </h3>

            <!-- Форма добавления комментария -->
            <?php if (isLoggedIn()): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Добавить комментарий</h5>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" id="comment-form">
                            <input type="hidden" name="parent_id" id="parent_id" value="">
                            <div class="mb-3">
                                <label for="content" class="form-label">Ваш комментарий</label>
                                <textarea class="form-control" id="content" name="content" rows="3" 
                                          placeholder="Введите текст комментария..." required></textarea>
                                <div class="form-text">Минимум 3 символа, максимум 1000</div>
                            </div>
                            <div class="mb-2">
                                <span id="reply-to" class="text-muted"></span>
                            </div>
                            <button type="submit" name="add_comment" class="btn btn-primary">
                                Отправить
                            </button>
                            <button type="button" id="cancel-reply" class="btn btn-secondary" style="display: none;">
                                Отменить ответ
                            </button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <a href="login.php">Войдите</a> или <a href="register.php">зарегистрируйтесь</a>, 
                    чтобы оставлять комментарии.
                </div>
            <?php endif; ?>

            <!-- Список комментариев -->
            <div class="comments-list">
                <?php if (empty($comments)): ?>
                    <div class="alert alert-info">Комментариев пока нет. Будьте первым!</div>
                <?php else: ?>
                    <?php 
                    function displayComments($comments, $level = 0) {
                        foreach ($comments as $comment) {
                            ?>
                            <div class="comment" id="comment-<?= $comment['id'] ?>" 
                                 style="margin-left: <?= $level * 30 ?>px;">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="d-flex align-items-center">
                                                <img src="<?= UPLOAD_DIR . $comment['avatar'] ?>" 
                                                     class="avatar me-2" 
                                                     alt="<?= escape($comment['username']) ?>">
                                                <strong><?= escape($comment['username']) ?></strong>
                                                <small class="text-muted ms-2">
                                                    <?= formatDate($comment['created_at']) ?>
                                                </small>
                                            </div>
                                            <?php if (isLoggedIn()): ?>
                                                <button class="btn btn-sm btn-outline-primary reply-btn" 
                                                        data-comment-id="<?= $comment['id'] ?>"
                                                        data-username="<?= escape($comment['username']) ?>">
                                                    Ответить
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <p class="card-text"><?= nl2br(escape($comment['content'])) ?></p>
                                    </div>
                                </div>
                                
                                <?php if (!empty($comment['replies'])): ?>
                                    <?php displayComments($comment['replies'], $level + 1); ?>
                                <?php endif; ?>
                            </div>
                            <?php
                        }
                    }
                    displayComments($comments);
                    ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Обработка кнопок "Ответить"
        document.querySelectorAll('.reply-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const commentId = this.dataset.commentId;
                const username = this.dataset.username;
                
                document.getElementById('parent_id').value = commentId;
                document.getElementById('reply-to').textContent = 'Ответ на комментарий пользователя ' + username;
                document.getElementById('cancel-reply').style.display = 'inline-block';
                document.getElementById('content').focus();
                
                // Прокрутка к форме
                document.getElementById('comment-form').scrollIntoView({ behavior: 'smooth' });
            });
        });
        
        // Отмена ответа
        document.getElementById('cancel-reply').addEventListener('click', function() {
            document.getElementById('parent_id').value = '';
            document.getElementById('reply-to').textContent = '';
            this.style.display = 'none';
            document.getElementById('content').placeholder = 'Введите текст комментария...';
        });
        
        // Обработка рейтинга
        document.querySelectorAll('.rating-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const form = this.closest('form');
                const value = this.textContent.includes('👍') ? 1 : -1;
                form.querySelector('input[name="value"]').value = value;
                form.submit();
            });
        });
    </script>
</body>
</html>