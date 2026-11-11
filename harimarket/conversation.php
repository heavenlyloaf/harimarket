<?php
  require_once 'config.php';
  $user = requireLogin();
  $badge = unreadNotifCount($pdo);
  $partner_id = (int)($_GET['with'] ?? 0);
  if (!$partner_id || $partner_id === $user['id']) redirect('messages.php');
  $ps = $pdo->prepare('SELECT id, full_name, avatar, role FROM users WHERE id = ?');
  $ps->execute([$partner_id]);
  $partner = $ps->fetch();
  if (!$partner) redirect('messages.php');
  $send_error = '';
  // Optional order context (?order=ID): only for an order between these two users.
  $order_ctx = null;
  $order_id  = (int) ($_GET['order'] ?? 0);
  if ($order_id) {
      $oc = $pdo->prepare('SELECT o.id, o.status, pl.name AS pickup_name
                           FROM orders o LEFT JOIN pickup_locations pl ON pl.id = o.pickup_location_id
                           WHERE o.id = ? AND ((o.buyer_id = ? AND o.seller_id = ?) OR (o.buyer_id = ? AND o.seller_id = ?))');
      $oc->execute([$order_id, $user['id'], $partner_id, $partner_id, $user['id']]);
      $order_ctx = $oc->fetch() ?: null;
  }
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $body = trim($_POST['body'] ?? '');
      if ($body !== '') {
          $ins = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, order_id, body) VALUES (?, ?, ?, ?)');
          $ins->execute([$user['id'], $partner_id, $order_ctx['id'] ?? null, mb_substr($body, 0, 2000)]);
      }
      redirect('conversation.php?with=' . $partner_id . ($order_ctx ? '&order=' . $order_ctx['id'] : ''));
  }
  $pdo->prepare('UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?')
      ->execute([$partner_id, $user['id']]);
  $stmt = $pdo->prepare('
      SELECT m.id, m.sender_id, m.body, m.created_at, m.is_read,
            u.full_name, u.avatar
      FROM   messages m
      JOIN   users u ON u.id = m.sender_id
      WHERE  (m.sender_id = ? AND m.receiver_id = ?)
          OR (m.sender_id = ? AND m.receiver_id = ?)
      ORDER  BY m.created_at ASC
  ');
  $stmt->execute([$user['id'], $partner_id, $partner_id, $user['id']]);
  $messages = $stmt->fetchAll();
  $unread_stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0');
  $unread_stmt->execute([$user['id']]);
  $total_unread = (int)$unread_stmt->fetchColumn();
  $partner_label = htmlspecialchars($partner['full_name']);
  $partner_plain = $partner['full_name'];
  $partner_initial = strtoupper(substr($partner['full_name'], 0, 1));
  $pageTitle = 'Chat with ' . $partner_plain;
?>
<!DOCTYPE html>
<html lang="en">
<?php require 'partials/head.php'; ?>
<body class="page-conversation">
<div class="page-root">
  <?php require 'partials/navbar.php'; ?>
  <main class="site-content">
    <div class="chat-wrapper">
      <?php if ($order_ctx): ?>
        <div class="pickup-help" style="margin:0 0 .5rem">Order #<?= (int) $order_ctx['id'] ?> &middot; <?= e(orderStatusLabel($order_ctx['status'])) ?><?= $order_ctx['pickup_name'] ? ' &middot; Pick-up: ' . e($order_ctx['pickup_name']) : '' ?>. Agree on a time to meet here.</div>
      <?php endif; ?>
      <div class="chat-header">
        <a href="messages.php" class="chat-back" title="Back to inbox">
          <i class="iconoir-arrow-left"></i>
        </a>
        <?php if (!empty($partner['avatar'])): ?>
          <img src="<?= htmlspecialchars($partner['avatar']) ?>"
               alt="<?= $partner_label ?>" class="chat-partner-avatar">
        <?php else: ?>
          <div class="chat-partner-placeholder"><?= $partner_initial ?></div>
        <?php endif; ?>
        <div class="chat-partner-info">
          <div class="name"><?= $partner_label ?></div>
          <?php if ($partner['role'] === 'seller'): ?>
            <span class="role-badge">Seller</span>
          <?php endif; ?>
        </div>
        <div style="margin-left: auto;">
          <a href="report.php?user_id=<?= $partner_id ?>" title="Report this user" style="color:#c00; font-size:1.2rem;">
            <i class="iconoir-warning-triangle"></i>
          </a>
        </div>
      </div>
      <div class="chat-thread" id="chatThread">
        <?php if (empty($messages)): ?>
          <div class="chat-empty">
            <i class="iconoir-chat-bubble"></i>
            <p>No messages yet. Say hello!</p>
          </div>
        <?php else: ?>
          <?php
            $prev_date = null;
            foreach ($messages as $m):
              $is_mine = ($m['sender_id'] == $user['id']);
              $msg_date = date('Y-m-d', strtotime($m['created_at']));
              $today    = date('Y-m-d');
              $yesterday = date('Y-m-d', strtotime('-1 day'));

              if ($msg_date !== $prev_date):
                if ($msg_date === $today)          $sep_label = 'Today';
                elseif ($msg_date === $yesterday)  $sep_label = 'Yesterday';
                else                               $sep_label = date('M j, Y', strtotime($m['created_at']));
                $prev_date = $msg_date;
          ?>
            <div class="date-sep"><?= $sep_label ?></div>
          <?php endif; ?>

            <div class="bubble-row <?= $is_mine ? 'mine' : '' ?>">
              <?php if (!$is_mine): ?>
                <?php if (!empty($partner['avatar'])): ?>
                  <img src="<?= htmlspecialchars($partner['avatar']) ?>"
                       alt="<?= $partner_label ?>" class="bubble-avatar">
                <?php else: ?>
                  <div class="bubble-avatar-placeholder"><?= $partner_initial ?></div>
                <?php endif; ?>
              <?php endif; ?>

              <div class="bubble">
                <?= nl2br(htmlspecialchars($m['body'])) ?>
                <span class="bubble-time"><?= date('g:i A', strtotime($m['created_at'])) ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <form class="chat-compose" method="POST" action="conversation.php?with=<?= $partner_id ?>">
        <textarea
          name="body"
          id="msgInput"
          rows="1"
          placeholder="Type a message…"
          aria-label="Message input"
          required
        ></textarea>
        <button type="submit" aria-label="Send message">
          <i class="iconoir-send-diagonal"></i>
        </button>
      </form>
    </div>
  </main>
</div>
<script>
  const thread = document.getElementById('chatThread');
  if (thread) thread.scrollTop = thread.scrollHeight;
  const textarea = document.getElementById('msgInput');
  if (textarea) {
    textarea.addEventListener('input', function () {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
    textarea.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (this.value.trim()) this.closest('form').submit();
      }
    });
  }
</script>
<?php require 'partials/bottomnav.php'; ?>
</body>
</html>