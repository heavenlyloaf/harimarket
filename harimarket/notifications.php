<?php
require_once 'config.php';
$user = currentUser();
if (!$user) redirect('login.php');
$pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')
    ->execute([$user['id']]);
$stmt = $pdo->prepare('
    SELECT * FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 50
');
$stmt->execute([$user['id']]);
$notifs = $stmt->fetchAll();
$pageTitle = 'All Notifications';
?>
<!DOCTYPE html>
<html lang="en">
<?php require 'partials/head.php'; ?>
<body>
<div class="page-root">
  <?php require 'partials/navbar-no-search.php'; ?>
  <main class="site-content">
    <div class="page-wrapper">
      <div class="page-heading">
        <h2>Notifications</h2>
        <?php if (!empty($notifs)): ?>
          <span class="page-meta"><?= count($notifs) ?> total</span>
        <?php endif; ?>
      </div>
      <?php if (empty($notifs)): ?>
        <div class="empty-state">
          <i class="iconoir-bell-off"></i>
          <p class="empty-title">No notifications yet</p>
          <p class="empty-sub">You'll see order updates and alerts here.</p>
        </div>
      <?php else: ?>
        <div class="card-list">
          <?php foreach ($notifs as $n): ?>
            <div class="page-card notif-card <?= !$n['is_read'] ? 'unread' : '' ?>">
              <div class="notif-icon-col">
                <i class="iconoir-bell"></i>
              </div>
              <div class="notif-body">
                <p><?= htmlspecialchars($n['message']) ?></p>
                <small><?= date('M d, Y · g:i A', strtotime($n['created_at'])) ?></small>
              </div>
              <?php if ($n['link']): ?>
                <div class="notif-action">
                  <a href="<?= htmlspecialchars($n['link']) ?>" class="btn outline"
                     style="padding:.3rem .75rem;font-size:.8rem;white-space:nowrap;">View</a>
                </div>
              <?php endif; ?>
            </div>
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