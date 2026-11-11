<?php
require_once 'config.php';
$user = currentUser();
if (!$user) redirect('login.php');
$badge = unreadNotifCount($pdo);
$stmt = $pdo->prepare('
    SELECT
        u.id        AS partner_id,
        u.full_name AS partner_name,
        u.avatar    AS partner_avatar,
        m.body      AS last_message,
        m.created_at,
        m.sender_id,
        SUM(CASE WHEN m2.receiver_id = ? AND m2.is_read = 0 AND m2.sender_id = u.id THEN 1 ELSE 0 END) AS unread_count
    FROM (
        SELECT
            IF(sender_id = ?, receiver_id, sender_id) AS partner_id,
            MAX(id) AS max_id
        FROM messages
        WHERE sender_id = ? OR receiver_id = ?
        GROUP BY partner_id
    ) AS latest
    JOIN messages m  ON m.id = latest.max_id
    JOIN users    u  ON u.id = latest.partner_id
    LEFT JOIN messages m2 ON m2.sender_id = u.id AND m2.receiver_id = ?
    GROUP BY u.id, u.full_name, u.avatar, m.body, m.created_at, m.sender_id
    ORDER BY m.created_at DESC
');
$stmt->execute([$user['id'], $user['id'], $user['id'], $user['id'], $user['id']]);
$conversations = $stmt->fetchAll();
$unread_stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0');
$unread_stmt->execute([$user['id']]);
$total_unread = (int)$unread_stmt->fetchColumn();
$pageTitle = 'Messages';
?>
<!DOCTYPE html>
<html lang="en">
<?php require 'partials/head.php'; ?>
<body>
<div class="page-root">
  <?php require 'partials/navbar-no-search.php'; ?>
  <main class="site-content">
    <div class="content-wrapper">
      <div class="inbox-header">
        <h2><i style="margin-right:.4rem;"></i> Messages</h2>
        <?php if ($total_unread > 0): ?>
          <span style="font-size:.85rem;color:#4f6ef7;font-weight:600;"><?= $total_unread ?> unread</span>
        <?php endif; ?>
      </div>
      <?php if (empty($conversations)): ?>
        <div class="inbox-empty">
          <i class="iconoir-message-alert"></i>
          <p style="font-weight:600;font-size:1rem;color:#555;">No messages yet</p>
          <p style="font-size:.88rem;">Start a conversation by messaging a seller on any product page.</p>
          <a href="index.php" class="btn primary" style="margin-top:1rem;display:inline-block;">Browse Products</a>
        </div>
      <?php else: ?>
        <div class="convo-list">
          <?php foreach ($conversations as $c): ?>
            <?php
              $isUnread = $c['unread_count'] > 0;
              $initial  = strtoupper(substr($c['partner_name'], 0, 1));
              $preview  = ($c['sender_id'] == $user['id'] ? 'You: ' : '') . htmlspecialchars($c['last_message']);
              $ts   = strtotime($c['created_at']);
              $time = (date('Y-m-d') === date('Y-m-d', $ts))
                    ? date('g:i A', $ts)
                    : date('M j', $ts);
            ?>
            <a href="conversation.php?with=<?= $c['partner_id'] ?>"
               class="convo-card <?= $isUnread ? 'unread' : '' ?>">
              <?php if (!empty($c['partner_avatar'])): ?>
                <img src="<?= htmlspecialchars($c['partner_avatar']) ?>"
                     alt="<?= htmlspecialchars($c['partner_name']) ?>"
                     class="convo-avatar">
              <?php else: ?>
                <div class="convo-avatar-placeholder"><?= $initial ?></div>
              <?php endif; ?>
              <div class="convo-body">
                <div class="convo-name"><?= htmlspecialchars($c['partner_name']) ?></div>
                <div class="convo-preview"><?= $preview ?></div>
              </div>
              <div class="convo-meta">
                <span class="convo-time"><?= $time ?></span>
                <?php if ($isUnread): ?>
                  <span class="unread-badge"><?= $c['unread_count'] ?></span>
                <?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>
  <?php require 'partials/footer.php'; ?>
</div>
<?php require 'partials/bottomnav.php'; ?>
</body>
</html>