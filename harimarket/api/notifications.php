<?php
// JSON endpoint polled by js/notifications.js (Fetch API).
// GET ?since=<id>  -> unread count + notifications newer than <id>.
// since=-1 only returns the current latest id (first poll, so old items don't pop up as toasts).
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$u = currentUser();
if (!$u) { http_response_code(401); echo json_encode(['error' => 'Login required']); exit; }

$since = (int) ($_GET['since'] ?? -1);
$uid   = (int) $u['id'];

$latest = $pdo->prepare('SELECT COALESCE(MAX(id), 0) FROM notifications WHERE user_id = ?');
$latest->execute([$uid]);
$latestId = (int) $latest->fetchColumn();

$unread = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$unread->execute([$uid]);

$items = [];
if ($since >= 0) {
    $s = $pdo->prepare('SELECT id, message, link, created_at FROM notifications
                        WHERE user_id = ? AND id > ? ORDER BY id ASC LIMIT 5');
    $s->execute([$uid, $since]);
    $items = $s->fetchAll();
}
echo json_encode(['unread' => (int) $unread->fetchColumn(), 'latest_id' => $latestId, 'items' => $items]);
