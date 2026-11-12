<?php
// JSON chart data (Fetch API). Sellers get their own numbers, admins get system-wide numbers.
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$u = currentUser();
if (!$u) { http_response_code(401); echo json_encode(['error' => 'Login required']); exit; }
if (!in_array($u['role'], ['seller', 'admin'], true)) { http_response_code(403); echo json_encode(['error' => 'Not allowed']); exit; }

/** Fill missing days with 0 so the line chart has a continuous x axis. */
function lastDays(array $rows, int $days): array {
    $map = [];
    foreach ($rows as $r) $map[$r['d']] = (float) $r['v'];
    $labels = []; $values = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $labels[] = date('M j', strtotime($d));
        $values[] = $map[$d] ?? 0;
    }
    return ['labels' => $labels, 'values' => $values];
}

if ($u['role'] === 'seller') {
    $sid = (int) $u['id'];
    $st = $pdo->prepare('SELECT status, COUNT(*) c FROM orders WHERE seller_id = ? GROUP BY status');
    $st->execute([$sid]);
    $byStatus = array_fill_keys(array_keys(ORDER_STATUS_LABELS), 0);
    foreach ($st->fetchAll() as $r) $byStatus[$r['status']] = (int) $r['c'];

    $er = $pdo->prepare('SELECT DATE(created_at) d, SUM(total) v FROM orders
                         WHERE seller_id = ? AND status = "picked_up" AND created_at >= CURDATE() - INTERVAL 13 DAY
                         GROUP BY DATE(created_at)');
    $er->execute([$sid]);

    $tp = $pdo->prepare('SELECT p.name, SUM(oi.quantity) q FROM order_items oi
                         JOIN orders o ON o.id = oi.order_id JOIN products p ON p.id = oi.product_id
                         WHERE o.seller_id = ? AND o.status = "picked_up"
                         GROUP BY p.id, p.name ORDER BY q DESC LIMIT 5');
    $tp->execute([$sid]);
    $top = $tp->fetchAll();

    echo json_encode([
        'role' => 'seller',
        'status' => ['labels' => array_values(ORDER_STATUS_LABELS), 'values' => array_values($byStatus)],
        'earnings' => lastDays($er->fetchAll(), 14),
        'top' => ['labels' => array_column($top, 'name'), 'values' => array_map('intval', array_column($top, 'q'))],
    ]);
    exit;
}

// admin
$roles = $pdo->query('SELECT role, COUNT(*) c FROM users GROUP BY role')->fetchAll(PDO::FETCH_KEY_PAIR);
$orders = $pdo->query('SELECT DATE(created_at) d, COUNT(*) v FROM orders
                       WHERE created_at >= CURDATE() - INTERVAL 13 DAY GROUP BY DATE(created_at)')->fetchAll();
$cats = $pdo->query('SELECT c.name, COUNT(p.id) c FROM categories c
                     LEFT JOIN products p ON p.category_id = c.id AND p.status = "approved"
                     GROUP BY c.id, c.name ORDER BY c.name')->fetchAll();
echo json_encode([
    'role' => 'admin',
    'users' => ['labels' => ['Buyers', 'Sellers', 'Admins'],
                'values' => [(int) ($roles['buyer'] ?? 0), (int) ($roles['seller'] ?? 0), (int) ($roles['admin'] ?? 0)]],
    'orders' => lastDays($orders, 14),
    'categories' => ['labels' => array_column($cats, 'name'), 'values' => array_map('intval', array_column($cats, 'c'))],
]);
