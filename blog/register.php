<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$username = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Валидация
    if (empty($username) || strlen($username) < 3) {
        $errors[] = 'Имя пользователя должно содержать минимум 3 символа';
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Некорректный email';
    }
    
    if (strlen($password) < 6) {
        $errors[] = 'Пароль должен содержать минимум 6 символов';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Пароли не совпадают';
    }
    
    // Проверка уникальности
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        $errors[] = 'Пользователь с таким именем или email уже существует';
    }
    
    // Загрузка аватара
    $avatar = 'default.png';
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
            $avatar = uniqid() . '.' . $ext;
            $upload_path = UPLOAD_DIR . $avatar;
            
            if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                $errors[] = 'Ошибка при загрузке файла';
            }
        }
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("
            INSERT INTO users (username, email, password, avatar) 
            VALUES (?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$username, $email, $hashed_password, $avatar])) {
            // Автоматический вход
            $userId = $db->lastInsertId();
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['avatar'] = $user['avatar'];
            
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Ошибка при регистрации';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Регистрация</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= $error ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Имя пользователя *</label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?= escape($username) ?>" required minlength="3">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?= escape($email) ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Пароль *</label>
                                        <input type="password" class="form-control" id="password" name="password" 
                                               required minlength="6">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Подтверждение пароля *</label>
                                        <input type="password" class="form-control" id="confirm_password" 
                                               name="confirm_password" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="avatar" class="form-label">Аватар (опционально)</label>
                                <input type="file" class="form-control" id="avatar" name="avatar" 
                                       accept="image/jpeg,image/png,image/gif">
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Зарегистрироваться</button>
                        </form>
                        
                        <div class="mt-3 text-center">
                            <p>Уже есть аккаунт? <a href="login.php">Войдите</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>