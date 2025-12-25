<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$db = getDB();
$articleId = (int)$_GET['id'];

// Получение статьи
$stmt = $db->prepare("SELECT * FROM articles WHERE id = ?");
$stmt->execute([$articleId]);
$article = $stmt->fetch();

// Проверка прав доступа
if (!$article || ($_SESSION['user_id'] != $article['user_id'] && !isAdmin())) {
    header('Location: index.php');
    exit;
}

$errors = [];
$title = $article['title'];
$content = $article['content'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    
    // Валидация
    if (empty($title) || strlen($title) < 5) {
        $errors[] = 'Заголовок должен содержать минимум 5 символов';
    }
    
    if (empty($content) || strlen($content) < 10) {
        $errors[] = 'Содержание должно содержать минимум 10 символов';
    }
    
    if (empty($errors)) {
        $stmt = $db->prepare("
            UPDATE articles 
            SET title = ?, content = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        
        if ($stmt->execute([$title, $content, $articleId])) {
            header("Location: article.php?id=$articleId");
            exit;
        } else {
            $errors[] = 'Ошибка при обновлении статьи';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование статьи - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php require_once 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h1 class="mb-4">Редактирование статьи</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label for="title" class="form-label">Заголовок *</label>
                <input type="text" class="form-control" id="title" name="title" 
                       value="<?= escape($title) ?>" required minlength="5" maxlength="200">
            </div>
            
            <div class="mb-3">
                <label for="content" class="form-label">Содержание *</label>
                <textarea class="form-control" id="content" name="content" rows="10" 
                          required minlength="10"><?= escape($content) ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a href="article.php?id=<?= $articleId ?>" class="btn btn-secondary">Отмена</a>
        </form>
    </div>
</body>
</html>