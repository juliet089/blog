<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$userId = $_SESSION['user_id'];

// Получение данных пользователя
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Валидация
    if (empty($username) || strlen($username) < 3) {
        $errors[] = 'Имя пользователя должно содержать минимум 3 символа';
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Некорректный email';
    }
    
    // Проверка уникальности
    if ($username !== $user['username']) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $userId]);
        if ($stmt->fetch()) {
            $errors[] = 'Это имя пользователя уже занято';
        }
    }
    
    if ($email !== $user['email']) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            $errors[] = 'Этот email уже используется';
        }
    }
    
    // Смена пароля
    if (!empty($new_password)) {
        if (strlen($new_password) < 6) {
            $errors[] = 'Новый пароль должен содержать минимум 6 символов';
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = 'Пароли не совпадают';
        }
        
        if (empty($current_password)) {
            $errors[] = 'Для смены пароля введите текущий пароль';
        } else {
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $db_password = $stmt->fetchColumn();
            
            if (!password_verify($current_password, $db_password)) {
                $errors[] = 'Текущий пароль введен неверно';
            }
        }
    }
    
    // Загрузка аватара
    $avatar = $user['avatar'];
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        
        if ($file['size'] > MAX_FILE_SIZE) {
            $errors[] = 'Размер файла превышает допустимый';
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime, ALLOWED_TYPES)) {
            $errors[] = 'Недопустимый тип файла';
        }
        
        if (empty($errors)) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_avatar = uniqid() . '.' . $ext;
            $upload_path = UPLOAD_DIR . $new_avatar;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                if ($avatar !== 'default.png') {
                    @unlink(UPLOAD_DIR . $avatar);
                }
                $avatar = $new_avatar;
            } else {
                $errors[] = 'Ошибка при загрузке файла';
            }
        }
    }
    
    if (empty($errors)) {
        // Обновление данных
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("
                UPDATE users 
                SET username = ?, email = ?, password = ?, avatar = ? 
                WHERE id = ?
            ");
            $stmt->execute([$username, $email, $hashed_password, $avatar, $userId]);
        } else {
            $stmt = $db->prepare("
                UPDATE users 
                SET username = ?, email = ?, avatar = ? 
                WHERE id = ?
            ");
            $stmt->execute([$username, $email, $avatar, $userId]);
        }
        
        // Обновление сессии
        $_SESSION['username'] = $username;
        $_SESSION['avatar'] = $avatar;
        
        // Обновление данных пользователя
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        $success = 'Профиль успешно обновлен';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php require_once 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h1 class="mb-4">Профиль пользователя</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <img src="<?= UPLOAD_DIR . $user['avatar'] ?>" 
                             class="rounded-circle mb-3" 
                             style="width: 150px; height: 150px; object-fit: cover;">
                        <h5 class="card-title"><?= escape($user['username']) ?></h5>
                        <p class="text-muted"><?= escape($user['email']) ?></p>
                        <p class="card-text">
                            <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : 'primary' ?>">
                                <?= $user['role'] === 'admin' ? 'Администратор' : 'Пользователь' ?>
                            </span>
                        </p>
                        <p class="text-muted">
                            Зарегистрирован: <?= formatDate($user['created_at']) ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Редактирование профиля</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Имя пользователя</label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?= escape($user['username']) ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?= escape($user['email']) ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="avatar" class="form-label">Аватар</label>
                                <input type="file" class="form-control" id="avatar" name="avatar" 
                                       accept="image/jpeg,image/png,image/gif">
                            </div>
                            
                            <hr>
                            
                            <h5 class="mb-3">Смена пароля</h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Текущий пароль</label>
                                        <input type="password" class="form-control" id="current_password" 
                                               name="current_password">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">Новый пароль</label>
                                        <input type="password" class="form-control" id="new_password" 
                                               name="new_password">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Подтверждение</label>
                                        <input type="password" class="form-control" id="confirm_password" 
                                               name="confirm_password">
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                Сохранить изменения
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>