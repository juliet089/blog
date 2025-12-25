<?php
// Настройки базы данных
define('DB_HOST', 'localhost');
define('DB_NAME', 'blog_db');
define('DB_USER', 'root');
define('DB_PASS', '280894vip');
define('SITE_NAME', 'Мой Блог');
define('SITE_URL', 'http://localhost/blog');

// Настройки сессии
session_start();

// Настройки загрузки файлов
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('UPLOAD_DIR', 'uploads/avatars/');

// Настройки пагинации
define('ITEMS_PER_PAGE', 5);
define('API_ITEMS_PER_PAGE', 10);

// Подключение к базе данных
function getDB() {
    static $db = null;
    
    if ($db === null) {
        try {
            $db = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Ошибка подключения к БД: " . $e->getMessage());
        }
    }
    
    return $db;
}

// Создание таблиц
function createTables() {
    $db = getDB();
    
    $sql = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            avatar VARCHAR(255) DEFAULT 'default.png',
            role ENUM('user', 'admin') DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS articles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            content TEXT NOT NULL,
            slug VARCHAR(200) UNIQUE NOT NULL,
            views INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            article_id INT NOT NULL,
            user_id INT NOT NULL,
            parent_id INT DEFAULT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS ratings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            article_id INT NOT NULL,
            user_id INT NOT NULL,
            value TINYINT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_rating (article_id, user_id),
            FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    ];
    
    foreach ($sql as $query) {
        try {
            $db->exec($query);
        } catch (PDOException $e) {
            // Таблица уже существует
        }
    }
}

// Вспомогательные функции
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function escape($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

function formatDate($date) {
    return date('d.m.Y H:i', strtotime($date));
}

function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    
    return $text ?: 'n-a';
}

function getArticleRating($articleId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(value), 0) as total,
            COUNT(CASE WHEN value = 1 THEN 1 END) as likes,
            COUNT(CASE WHEN value = -1 THEN 1 END) as dislikes
        FROM ratings 
        WHERE article_id = ?
    ");
    $stmt->execute([$articleId]);
    return $stmt->fetch();
}

function getUserRating($articleId, $userId) {
    if (!$userId) return 0;
    
    $db = getDB();
    $stmt = $db->prepare("
        SELECT value FROM ratings 
        WHERE article_id = ? AND user_id = ?
    ");
    $stmt->execute([$articleId, $userId]);
    $result = $stmt->fetch();
    return $result ? $result['value'] : 0;
}

function jsonResponse($data, $status = 200) {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Создание таблиц при первом запуске
createTables();
?>