<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$db = getDB();

// Пагинация
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? min(max(1, (int)$_GET['limit']), 100) : API_ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Параметры фильтрации
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$authorId = isset($_GET['author_id']) ? (int)$_GET['author_id'] : null;

// Построение запроса
$sql = "SELECT a.*, u.username, u.avatar FROM articles a JOIN users u ON a.user_id = u.id";
$params = [];
$conditions = [];

if (!empty($search)) {
    $conditions[] = "a.title LIKE ?";
    $params[] = "%$search%";
}

if ($authorId !== null) {
    $conditions[] = "a.user_id = ?";
    $params[] = $authorId;
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll();

// Получение общего количества
$countSql = "SELECT COUNT(*) as total FROM articles a";
if (!empty($conditions)) {
    $countSql .= " WHERE " . implode(" AND ", $conditions);
}

$countStmt = $db->prepare($countSql);
$countStmt->execute(array_slice($params, 0, -2));
$total = $countStmt->fetch()['total'];

// Добавление дополнительной информации
foreach ($articles as &$article) {
    $rating = getArticleRating($article['id']);
    $article['rating'] = [
        'total' => $rating['total'],
        'likes' => $rating['likes'],
        'dislikes' => $rating['dislikes']
    ];
    
    // Количество комментариев
    $stmt = $db->prepare("SELECT COUNT(*) FROM comments WHERE article_id = ?");
    $stmt->execute([$article['id']]);
    $article['comment_count'] = $stmt->fetchColumn();
    
    // Удаление лишних полей
    unset($article['user_id']);
}

$response = [
    'success' => true,
    'data' => $articles,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'pages' => ceil($total / $limit)
    ]
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>