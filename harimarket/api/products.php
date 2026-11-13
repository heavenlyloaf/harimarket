<?php
// JSON search endpoint used by the home page (Fetch API). GET ?q=&cat=&sort=
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');
if (!currentUser()) { http_response_code(401); echo json_encode(['error' => 'Login required']); exit; }

$q    = trim($_GET['q'] ?? '');
$cat  = (int) ($_GET['cat'] ?? 0);
$sort = $_GET['sort'] ?? 'newest';
$orderBy = [
    'newest'     => 'p.created_at DESC',
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'rating'     => 'p.rating DESC, p.review_count DESC',
][$sort] ?? 'p.created_at DESC';

$sql = 'SELECT p.id, p.name, p.price, p.image, p.rating, sp.store_name, pl.name AS pickup_name
        FROM products p
        JOIN seller_profiles sp ON sp.user_id = p.seller_id AND sp.approved = 1
        LEFT JOIN pickup_locations pl ON pl.id = sp.pickup_location_id
        WHERE p.stock > 0 AND p.status = "approved" AND sp.pickup_location_id IS NOT NULL';
$params = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $sql .= ' AND (p.name LIKE ? OR p.description LIKE ? OR sp.store_name LIKE ?)';
    array_push($params, $like, $like, $like);
}
if ($cat) { $sql .= ' AND p.category_id = ?'; $params[] = $cat; }
$sql .= " ORDER BY $orderBy LIMIT 60";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
echo json_encode(['products' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
