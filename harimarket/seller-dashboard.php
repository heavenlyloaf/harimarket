<?php
  require_once 'config.php';
  $user = requireLogin('seller');
  $success = '';
  $error   = '';
  $sp = $pdo->prepare('SELECT * FROM seller_profiles WHERE user_id = ?');
  $sp->execute([$user['id']]);
  $profile = $sp->fetch();
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $action = $_POST['action'] ?? '';
      if ($action === 'add_product') {
          $name        = trim($_POST['product_name']  ?? '');
          $price       = (float) ($_POST['price']     ?? 0);
          $stock       = (int)   ($_POST['stock']     ?? 0);
          $category_id = (int)   ($_POST['category']  ?? 0) ?: null;
          $desc        = trim($_POST['description']   ?? '');
          if (!$name || $price <= 0) {
              $error = 'Product name and valid price are required.';
          } else {
              $image = 'img/elements/placeholder.png';
              [$up, $upErr] = saveUpload($_FILES['product_image'] ?? [], 'uploads/products', 'prod_' . $user['id']);
              if ($upErr) { $error = $upErr; }
              elseif ($up) { $image = $up; }
            }
            if (!$error) {
               $ins = $pdo->prepare('INSERT INTO products (seller_id, category_id, name, description, price, stock, image, status) VALUES (?,?,?,?,?,?,?,?)');
              $ins->execute([$user['id'], $category_id, $name, $desc, $price, $stock, $image, 'pending']);
              $success = 'Product added! It will be visible once approved by an admin.';
          }
      }
      if ($action === 'delete_product') {
          $pid = (int) $_POST['product_id'];
          $pdo->prepare('DELETE FROM products WHERE id = ? AND seller_id = ?')->execute([$pid, $user['id']]);
          $success = 'Product deleted.';
      }
      if ($action === 'edit_product') {
          $pid   = (int) ($_POST['product_id'] ?? 0);
          $name  = trim($_POST['product_name'] ?? '');
          $price = (float) ($_POST['price'] ?? 0);
          $stock = (int) ($_POST['stock'] ?? 0);
          $catId = (int) ($_POST['category'] ?? 0) ?: null;
          $desc  = trim($_POST['description'] ?? '');
          $cur = $pdo->prepare('SELECT * FROM products WHERE id = ? AND seller_id = ?');
          $cur->execute([$pid, $user['id']]);
          $old = $cur->fetch();
          if (!$old) {
              $error = 'Product not found.';
          } elseif (!$name || $price <= 0 || $stock < 0) {
              $error = 'Product name, a valid price and stock are required.';
          } else {
              $image = $old['image'];
              [$up, $upErr] = saveUpload($_FILES['product_image'] ?? [], 'uploads/products', 'prod_' . $user['id']);
              if ($upErr) { $error = $upErr; }
              elseif ($up) { $image = $up; }
              if (!$error) {
                  // Changing what buyers see sends the listing back to the admin for review.
                  $changed = $name !== $old['name'] || $desc !== (string) $old['description']
                          || abs($price - (float) $old['price']) > 0.001 || $image !== $old['image'];
                  $status = $changed ? 'pending' : $old['status'];
                  $pdo->prepare('UPDATE products SET name=?, description=?, price=?, stock=?, category_id=?, image=?, status=? WHERE id=? AND seller_id=?')
                      ->execute([$name, $desc, $price, $stock, $catId, $image, $status, $pid, $user['id']]);
                  $success = $changed ? 'Product updated. It will be reviewed by an admin again.' : 'Product updated.';
              }
          }
      }
      if ($action === 'update_order') {
          // Seller moves an order forward (placed > preparing > ready) or cancels it.
          // "Picked Up" is only set by confirming the buyer's QR code on the Scan QR page.
          $oid    = (int) ($_POST['order_id'] ?? 0);
          $status = $_POST['status'] ?? '';
          $next   = ['placed' => ['preparing', 'cancelled'], 'preparing' => ['ready_for_pickup', 'cancelled'], 'ready_for_pickup' => ['cancelled']];
          $pdo->beginTransaction();
          try {
              $o = $pdo->prepare('SELECT id, buyer_id, status FROM orders WHERE id = ? AND seller_id = ? FOR UPDATE');
              $o->execute([$oid, $user['id']]);
              $ord = $o->fetch();
              if (!$ord || !in_array($status, $next[$ord['status']] ?? [], true)) {
                  throw new RuntimeException('That status change is not allowed.');
              }
              $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $oid]);
              if ($status === 'cancelled') {
                  $pdo->prepare('UPDATE products p JOIN order_items oi ON oi.product_id = p.id
                                 SET p.stock = p.stock + oi.quantity WHERE oi.order_id = ?')->execute([$oid]);
              }
              $texts = [
                  'preparing'        => "Your order #$oid is being prepared.",
                  'ready_for_pickup' => "Your order #$oid is ready for pick-up! Show your QR code to the seller.",
                  'cancelled'        => "Your order #$oid was cancelled by the seller.",
              ];
              notify($pdo, (int) $ord['buyer_id'], $texts[$status], 'orders.php');
              $pdo->commit();
              $success = "Order #$oid updated.";
          } catch (RuntimeException $e) {
              $pdo->rollBack();
              $error = $e->getMessage();
          }
      }
      if ($action === 'save_pickup') {
          $locId = (int) ($_POST['pickup_location_id'] ?? 0);
          $note  = mb_substr(trim($_POST['pickup_note'] ?? ''), 0, 255);
          $hours = mb_substr(trim($_POST['pickup_hours'] ?? ''), 0, 100);
          $chk = $pdo->prepare('SELECT 1 FROM pickup_locations WHERE id = ? AND is_active = 1');
          $chk->execute([$locId]);
          if (!$chk->fetch()) {
              $error = 'Please choose a valid pick-up point.';
          } else {
              $pdo->prepare('UPDATE seller_profiles SET pickup_location_id=?, pickup_note=?, pickup_hours=? WHERE user_id=?')
                  ->execute([$locId, $note ?: null, $hours ?: null, $user['id']]);
              $profile['pickup_location_id'] = $locId;
              $profile['pickup_note'] = $note;
              $profile['pickup_hours'] = $hours;
              $success = 'Pick-up point saved. New orders will use it.';
          }
      }
      if ($action === 'send_message') {
          $body        = trim($_POST['body']        ?? '');
          $receiver_id = (int)($_POST['receiver_id'] ?? 0);
          if ($body !== '' && $receiver_id) {
              $ins = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, body) VALUES (?, ?, ?)');
              $ins->execute([$user['id'], $receiver_id, $body]);
          }
          header('Location: seller-dashboard.php?chat_with=' . $receiver_id . '#messages');
          exit;
      }
      if ($action === 'update_avatar') {
          [$up, $upErr] = saveUpload($_FILES['avatar'] ?? [], 'uploads/avatars', 'avatar_' . $user['id']);
          if ($upErr) { $error = $upErr; }
          elseif ($up) {
              $pdo->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$up, $user['id']]);
              $success = 'Photo updated.';
          }
      }
      if ($action === 'save_settings') {
          $full_name  = trim($_POST['fullname']          ?? '');
          $phone      = trim($_POST['phone']             ?? '');
          $store_name = trim($_POST['store_name']        ?? '');
          $new_pass   = $_POST['password']               ?? '';
          $confirm    = $_POST['confirm_password']       ?? '';
          if ($new_pass && strlen($new_pass) < 8) {
              $error = 'Password must be at least 8 characters.';
          } elseif ($new_pass && $new_pass !== $confirm) {
              $error = 'Passwords do not match.';
          } else {
              $hash = $new_pass ? password_hash($new_pass, PASSWORD_BCRYPT) : null;
              if ($hash) {
                  $pdo->prepare('UPDATE users SET full_name=?, phone=?, password_hash=? WHERE id=?')
                      ->execute([$full_name, $phone, $hash, $user['id']]);
              } else {
                  $pdo->prepare('UPDATE users SET full_name=?, phone=? WHERE id=?')
                      ->execute([$full_name, $phone, $user['id']]);
              }
              if ($store_name) {
                  $pdo->prepare('UPDATE seller_profiles SET store_name=? WHERE user_id=?')
                      ->execute([$store_name, $user['id']]);
                  $profile['store_name'] = $store_name;
              }
              $_SESSION['user']['full_name'] = $full_name;
              $success = 'Settings saved.';
          }
      }
  }
  $selected_user = isset($_GET['chat_with']) ? (int)$_GET['chat_with'] : null;
  if ($selected_user) {
      $pdo->prepare('UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?')
          ->execute([$selected_user, $user['id']]);
  }
  $conv_stmt = $pdo->prepare('
      SELECT
          u.id        AS partner_id,
          u.full_name AS partner_name,
          u.avatar    AS partner_avatar,
          m.body      AS last_message,
          m.created_at,
          m.sender_id,
          (
              SELECT COUNT(*) FROM messages unread
              WHERE unread.sender_id = u.id
                AND unread.receiver_id = ?
                AND unread.is_read = 0
          ) AS unread_count
      FROM (
          SELECT
              IF(sender_id = ?, receiver_id, sender_id) AS partner_id,
              MAX(id) AS max_id
          FROM messages
          WHERE sender_id = ? OR receiver_id = ?
          GROUP BY IF(sender_id = ?, receiver_id, sender_id)
      ) AS latest
      JOIN messages m ON m.id = latest.max_id
      JOIN users    u ON u.id = latest.partner_id
      ORDER BY m.created_at DESC
  ');
  $conv_stmt->execute([
      $user['id'],
      $user['id'], $user['id'], $user['id'], $user['id']
  ]);
  $conversations = $conv_stmt->fetchAll();
  $chatMessages   = [];
  $chatPartner    = null;
  if ($selected_user) {
      $pt = $pdo->prepare('SELECT id, full_name, avatar, role FROM users WHERE id = ?');
      $pt->execute([$selected_user]);
      $chatPartner = $pt->fetch();
      $thread = $pdo->prepare('
          SELECT m.*, u.full_name, u.avatar
          FROM   messages m
          JOIN   users u ON u.id = m.sender_id
          WHERE  (m.sender_id = ? AND m.receiver_id = ?)
              OR (m.sender_id = ? AND m.receiver_id = ?)
          ORDER  BY m.created_at ASC
      ');
      $thread->execute([$user['id'], $selected_user, $selected_user, $user['id']]);
      $chatMessages = $thread->fetchAll();
  }
  $unread_stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0');
  $unread_stmt->execute([$user['id']]);
  $total_unread = (int)$unread_stmt->fetchColumn();
  $products_stmt = $pdo->prepare('SELECT * FROM products WHERE seller_id = ? ORDER BY created_at DESC');
  $products_stmt->execute([$user['id']]);
  $myProducts = $products_stmt->fetchAll();
  $orders_stmt = $pdo->prepare('
      SELECT o.id, o.total, o.status, o.created_at, u.full_name AS buyer_name, u.phone AS buyer_phone,
             pl.name AS pickup_name
      FROM   orders o
      JOIN   users u ON u.id = o.buyer_id
      LEFT JOIN pickup_locations pl ON pl.id = o.pickup_location_id
      WHERE  o.seller_id = ?
      ORDER  BY o.created_at DESC, o.id DESC
      LIMIT  30
  ');
  $orders_stmt->execute([$user['id']]);
  $myOrders = $orders_stmt->fetchAll();
  $orderItems = [];
  if ($myOrders) {
      $in = implode(',', array_fill(0, count($myOrders), '?'));
      $its = $pdo->prepare("SELECT oi.order_id, oi.quantity, p.name FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id IN ($in)");
      $its->execute(array_column($myOrders, 'id'));
      foreach ($its->fetchAll() as $r) $orderItems[$r['order_id']][] = $r;
  }
  // Earnings count only orders that were actually picked up (paid in person).
  $earn = $pdo->prepare('
      SELECT COALESCE(SUM(total), 0) AS total_all,
             COALESCE(SUM(CASE WHEN MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW()) THEN total ELSE 0 END), 0) AS total_month
      FROM   orders
      WHERE  seller_id = ? AND status = "picked_up"
  ');
  $earn->execute([$user['id']]);
  $earnings = $earn->fetch();
  $locations = $pdo->query('SELECT id, name FROM pickup_locations WHERE is_active = 1 ORDER BY name')->fetchAll();
  $cats = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
  $sellerUser = $pdo->prepare('SELECT * FROM users WHERE id = ?');
  $sellerUser->execute([$user['id']]);
  $sellerUser = $sellerUser->fetch();
  $pageTitle = 'Seller Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<?php require 'partials/head.php'; ?>
<body class="seller-override">
<div class="page-root">
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
  <header class="navbar navbar--no-search">
    <div class="logo">
      <img src="img/elements/header.png" alt="HariMarket Logo">
    </div>
    <nav class="nav-icons">
      <a href="logout.php" aria-label="Log Out"><i class="iconoir-log-out"></i></a>
    </nav>
  </header>
  <?php require 'partials/alerts.php'; ?>
  <main class="dashboard">
    <aside class="dashboard-sidebar">
      <h2><?= htmlspecialchars($profile['store_name'] ?? 'Seller Panel') ?></h2>
      <nav>
        <a href="#overview"><i class="iconoir-home"></i> Overview</a>
        <a href="#products"><i class="iconoir-package"></i> Products</a>
        <a href="#orders"><i class="iconoir-shopping-bag"></i> Orders</a>
        <a href="seller-scan.php"><i class="iconoir-qr-code"></i> Scan QR</a>
        <a href="#earnings"><i class="iconoir-wallet"></i> Earnings</a>
        <a href="#messages">
          <i class="iconoir-message"></i> Messages
          <?php if ($total_unread > 0): ?>
            <span class="sidebar-msg-badge"><?= $total_unread ?></span>
          <?php endif; ?>
        </a>
        <a href="#settings"><i class="iconoir-settings"></i> Settings</a>
      </nav>
    </aside>
    <section class="dashboard-content">
      <div class="dashboard-row" id="overview">
        <div class="dashboard-card">
          <h3>Total Products</h3>
          <p><?= count($myProducts) ?></p>
        </div>
        <div class="dashboard-card">
          <h3>Open Orders</h3>
          <p><?= count(array_filter($myOrders, fn($o) => in_array($o['status'], ['placed','preparing','ready_for_pickup'], true))) ?></p>
        </div>
        <div class="dashboard-card">
          <h3>Total Earnings</h3>
          <p>₱<?= number_format($earnings['total_all'], 2) ?></p>
        </div>
      </div>
      <div class="chart-grid" id="chartGrid" aria-live="polite"></div>
      <div class="dashboard-section" id="products">
        <h3>My Products</h3>
        <form method="POST" enctype="multipart/form-data" class="seller-add-product-form">
          <input type="hidden" name="action" value="add_product">
          <div class="seller-form-fields">
            <div class="seller-field">
              <label>Product Name</label>
              <input type="text" name="product_name" placeholder="e.g. Handmade Bracelet" required>
            </div>
            <div class="seller-field">
              <label>Price (₱)</label>
              <input type="number" name="price" placeholder="0.00" required min="1" step="0.01">
            </div>
            <div class="seller-field">
              <label>Stock Qty</label>
              <input type="number" name="stock" placeholder="0" required min="0">
            </div>
            <div class="seller-field">
              <label>Category</label>
              <select name="category">
                <option value="">— Select Category —</option>
                <?php foreach ($cats as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="seller-field seller-field-full">
              <label>Description <span style="font-weight:400;color:#9aa0b5;">(optional)</span></label>
              <textarea name="description" rows="2" placeholder="Brief description of your product…"></textarea>
            </div>
            <div class="seller-field seller-field-full">
              <label>Product Image</label>
              <div class="seller-upload-box" id="productUploadBox">
                <i class="iconoir-upload" style="font-size:1.4rem;color:#c0392b;"></i>
                <span class="seller-upload-text">Click to choose image</span>
                <span class="seller-upload-hint">JPG, PNG, WEBP accepted</span>
                <input type="file" name="product_image" id="productImageInput" accept="image/jpeg,image/png,image/webp" style="display:none;">
              </div>
              <div class="seller-upload-preview" id="productPreviewWrap" style="display:none;">
                <img id="productPreviewImg" src="" alt="Preview">
                <button type="button" class="seller-upload-remove" id="productRemoveBtn">
                  <i class="iconoir-xmark"></i> Remove
                </button>
              </div>
            </div>
          </div>
          <button type="submit" class="btn primary" style="margin-top:.75rem;">+ Add Product</button>
        </form>
        <div class="product-row">
          <?php if (empty($myProducts)): ?>
            <p>No products yet.</p>
          <?php else: ?>
            <?php foreach ($myProducts as $p): ?>
              <div class="product-card">
                <div class="product-img">
                  <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                </div>
                <h4><?= htmlspecialchars($p['name']) ?></h4>
                <div class="product-footer">
                  <div class="product-price">₱<?= number_format($p['price'], 2) ?></div>
                  <div style="margin-top:.25rem;">
                    <small>Stock: <?= $p['stock'] ?></small>
                    <span class="status-badge <?= $p['status'] ?>" style="font-size:0.7rem; padding: 2px 6px; border-radius: 4px; margin-left: 5px;">
                      <?= ucfirst($p['status']) ?>
                    </span>
                  </div>
                  <details class="edit-product">
                    <summary class="btn outline" style="display:inline-block;padding:.3rem .8rem;font-size:.85rem;">Edit</summary>
                    <form method="POST" enctype="multipart/form-data" class="edit-product-form">
                      <input type="hidden" name="action" value="edit_product">
                      <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                      <label>Name <input type="text" name="product_name" required value="<?= e($p['name']) ?>"></label>
                      <label>Price (₱) <input type="number" name="price" min="1" step="0.01" required value="<?= e($p['price']) ?>"></label>
                      <label>Stock <input type="number" name="stock" min="0" required value="<?= (int) $p['stock'] ?>"></label>
                      <label>Category
                        <select name="category"><option value="">None</option>
                          <?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $p['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                        </select></label>
                      <label>Description <textarea name="description" rows="2"><?= e($p['description']) ?></textarea></label>
                      <label>New photo <input type="file" name="product_image" accept="image/jpeg,image/png,image/webp"></label>
                      <button type="submit" class="btn primary" style="padding:.35rem .9rem;">Save</button>
                    </form>
                  </details>
                  <form method="POST" style="margin-top:.5rem;">
                    <input type="hidden" name="action"     value="delete_product">
                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                    <button type="submit" class="btn delete-btn" onclick="return confirm('Delete this product?')">
                      <i class="iconoir-trash-solid"></i> Delete
                    </button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="dashboard-section" id="orders">
        <h3>Orders</h3>
        <p class="pickup-help">Move an order to Preparing, then Ready for Pick-up. When the buyer arrives, confirm the hand-off on the <a href="seller-scan.php">Scan QR</a> page. That marks the order Picked Up.</p>
        <div class="orders-list">
          <?php if (empty($myOrders)): ?>
            <p>No orders yet.</p>
          <?php else: ?>
            <?php foreach ($myOrders as $o): ?>
              <div class="order-card">
                <h3>Order #<?= (int) $o['id'] ?> <span class="status-badge <?= e($o['status']) ?>"><?= e(orderStatusLabel($o['status'])) ?></span></h3>
                <p>Buyer: <?= e($o['buyer_name']) ?><?= $o['buyer_phone'] ? ' (' . e($o['buyer_phone']) . ')' : '' ?></p>
                <p><?php foreach ($orderItems[$o['id']] ?? [] as $it): ?><?= (int) $it['quantity'] ?> &times; <?= e($it['name']) ?><br><?php endforeach; ?></p>
                <p>Total: ₱<?= number_format($o['total'], 2) ?> &middot; <?= date('M d, Y', strtotime($o['created_at'])) ?></p>
                <p class="pickup-tag"><i class="iconoir-pin"></i> <?= e($o['pickup_name'] ?? 'Pick-up point') ?></p>
                <div class="order-actions">
                  <?php $nextBtns = ['placed' => [['preparing', 'Start preparing']], 'preparing' => [['ready_for_pickup', 'Mark ready for pick-up']]][$o['status']] ?? []; ?>
                  <?php foreach ($nextBtns as [$val, $label]): ?>
                    <form method="POST">
                      <input type="hidden" name="action" value="update_order">
                      <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                      <input type="hidden" name="status" value="<?= e($val) ?>">
                      <button type="submit" class="btn primary"><?= e($label) ?></button>
                    </form>
                  <?php endforeach; ?>
                  <?php if (in_array($o['status'], ['placed', 'preparing', 'ready_for_pickup'], true)): ?>
                    <form method="POST" onsubmit="return confirm('Cancel this order?');">
                      <input type="hidden" name="action" value="update_order">
                      <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                      <input type="hidden" name="status" value="cancelled">
                      <button type="submit" class="btn outline danger">Cancel</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($o['status'] === 'ready_for_pickup'): ?>
                    <a class="btn outline" href="seller-scan.php">Scan QR</a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="dashboard-section" id="earnings">
        <h3>Earnings Summary</h3>
        <p>Total this month: <strong>₱<?= number_format($earnings['total_month'], 2) ?></strong></p>
        <p>Total overall:    <strong>₱<?= number_format($earnings['total_all'],   2) ?></strong></p>
      </div>
      <div class="dashboard-section" id="messages">
        <h3>
          Messages
          <?php if ($total_unread > 0): ?>
            <span style="font-size:.8rem;color:#4f6ef7;font-weight:600;margin-left:.5rem;"><?= $total_unread ?> unread</span>
          <?php endif; ?>
        </h3>
        <div class="msg-panel">
          <div class="msg-sidebar">
            <div class="msg-sidebar-header">Conversations</div>
            <?php if (empty($conversations)): ?>
              <p style="padding:1rem;font-size:.83rem;color:#aaa;">No messages yet.</p>
            <?php else: ?>
              <?php foreach ($conversations as $c):
                $isActive  = ($selected_user == $c['partner_id']);
                $isUnread  = $c['unread_count'] > 0;
                $initial   = strtoupper(substr($c['partner_name'], 0, 1));
                $ts        = strtotime($c['created_at']);
                $timeLabel = (date('Y-m-d') === date('Y-m-d', $ts)) ? date('g:i A', $ts) : date('M j', $ts);
                $preview   = ($c['sender_id'] == $user['id'] ? 'You: ' : '') . mb_strimwidth($c['last_message'], 0, 35, '…');
              ?>
                <a href="seller-dashboard.php?chat_with=<?= $c['partner_id'] ?>#messages"
                   class="msg-convo-item <?= $isActive ? 'active' : '' ?> <?= $isUnread ? 'unread' : '' ?>">
                  <?php if (!empty($c['partner_avatar'])): ?>
                    <img src="<?= htmlspecialchars($c['partner_avatar']) ?>"
                         alt="<?= htmlspecialchars($c['partner_name']) ?>"
                         class="msg-convo-avatar">
                  <?php else: ?>
                    <div class="msg-convo-placeholder"><?= $initial ?></div>
                  <?php endif; ?>
                  <div class="msg-convo-body">
                    <div class="msg-convo-name"><?= htmlspecialchars($c['partner_name']) ?></div>
                    <div class="msg-convo-preview"><?= htmlspecialchars($preview) ?></div>
                  </div>
                  <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.25rem;flex-shrink:0;">
                    <span class="msg-convo-time"><?= $timeLabel ?></span>
                    <?php if ($isUnread): ?>
                      <span class="msg-unread-dot"></span>
                    <?php endif; ?>
                  </div>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <div class="msg-chat">
            <?php if (!$selected_user || !$chatPartner): ?>
              <div class="msg-empty-state">
                <i class="iconoir-message"></i>
                <p>Select a conversation to start chatting</p>
              </div>
            <?php else: ?>
              <div class="msg-chat-header">
                <?php $pi = strtoupper(substr($chatPartner['full_name'], 0, 1)); ?>
                <?php if (!empty($chatPartner['avatar'])): ?>
                  <img src="<?= htmlspecialchars($chatPartner['avatar']) ?>"
                       alt="<?= htmlspecialchars($chatPartner['full_name']) ?>"
                       class="msg-bav" style="width:36px;height:36px;">
                <?php else: ?>
                  <div class="msg-bav-placeholder" style="width:36px;height:36px;"><?= $pi ?></div>
                <?php endif; ?>
                <div>
                  <div class="name"><?= htmlspecialchars($chatPartner['full_name']) ?></div>
                  <?php if ($chatPartner['role'] === 'buyer'): ?>
                    <span class="role-badge">Buyer</span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="msg-thread" id="sellerChatThread">
                <?php if (empty($chatMessages)): ?>
                  <div class="msg-empty-state">
                    <i class="iconoir-chat-bubble"></i>
                    <p>No messages yet. Say hello!</p>
                  </div>
                <?php else: ?>
                  <?php
                    $prev_date = null;
                    foreach ($chatMessages as $msg):
                      $isMine   = ($msg['sender_id'] == $user['id']);
                      $msg_date = date('Y-m-d', strtotime($msg['created_at']));
                      $today    = date('Y-m-d');
                      $yesterday = date('Y-m-d', strtotime('-1 day'));
                      if ($msg_date !== $prev_date):
                        if ($msg_date === $today)         $sep = 'Today';
                        elseif ($msg_date === $yesterday) $sep = 'Yesterday';
                        else                              $sep = date('M j, Y', strtotime($msg['created_at']));
                        $prev_date = $msg_date;
                  ?>
                        <div class="msg-date-sep"><?= $sep ?></div>
                  <?php endif; ?>
                      <div class="msg-bubble-row <?= $isMine ? 'mine' : '' ?>">
                        <?php if (!$isMine): ?>
                          <?php if (!empty($chatPartner['avatar'])): ?>
                            <img src="<?= htmlspecialchars($chatPartner['avatar']) ?>"
                                 alt="<?= htmlspecialchars($chatPartner['full_name']) ?>"
                                 class="msg-bav">
                          <?php else: ?>
                            <div class="msg-bav-placeholder"><?= $pi ?></div>
                          <?php endif; ?>
                        <?php endif; ?>
                        <div class="msg-bubble">
                          <?= nl2br(htmlspecialchars($msg['body'])) ?>
                          <span class="msg-bubble-time"><?= date('g:i A', strtotime($msg['created_at'])) ?></span>
                        </div>
                      </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              <form class="msg-compose" method="POST"
                    action="seller-dashboard.php?chat_with=<?= $selected_user ?>#messages">
                <input type="hidden" name="action"      value="send_message">
                <input type="hidden" name="receiver_id" value="<?= $selected_user ?>">
                <textarea name="body" id="sellerMsgInput" rows="1"
                          placeholder="Type a message…" required></textarea>
                <button type="submit" aria-label="Send">
                  <i class="iconoir-send-diagonal"></i>
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="dashboard-section" id="settings">
        <h3>Account Settings</h3>

        <!-- Pick-up point -->
        <form method="POST">
          <input type="hidden" name="action" value="save_pickup">
          <div class="seller-settings-card">
            <div class="profile-section-title"><i class="iconoir-pin"></i> Pick-up Point</div>
            <p class="muted-small">Buyers collect their orders here. It is shown on your products, in the cart and on every order.</p>
            <div class="profile-fields">
              <div class="profile-field">
                <label for="pickupLoc">Campus pick-up point</label>
                <select id="pickupLoc" name="pickup_location_id" required>
                  <option value="">Choose a location</option>
                  <?php foreach ($locations as $loc): ?>
                    <option value="<?= (int) $loc['id'] ?>" <?= (int) ($profile['pickup_location_id'] ?? 0) === (int) $loc['id'] ? 'selected' : '' ?>><?= e($loc['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="profile-field">
                <label for="pickupHours">Pick-up hours</label>
                <input type="text" id="pickupHours" name="pickup_hours" maxlength="100" value="<?= e($profile['pickup_hours'] ?? '') ?>" placeholder="e.g. 10am-4pm, Mon-Fri">
              </div>
              <div class="profile-field">
                <label for="pickupNote">Pick-up note</label>
                <input type="text" id="pickupNote" name="pickup_note" maxlength="255" value="<?= e($profile['pickup_note'] ?? '') ?>" placeholder="e.g. Beside the guard house">
              </div>
            </div>
            <div class="page-product-actions"><button type="submit" class="btn primary">Save pick-up point</button></div>
          </div>
        </form>

        <!-- Avatar -->
        <div class="seller-settings-card">
          <div class="profile-section-title"><i class="iconoir-user-circle"></i> Profile Photo</div>
          <div style="display:flex;align-items:center;gap:1rem;margin-top:.75rem;">
            <div style="position:relative;display:inline-block;">
              <img src="<?= htmlspecialchars($sellerUser['avatar']) ?>" alt="Avatar"
                   id="sellerAvatarPreview"
                   style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:2px solid #e8d5d5;">
              <label for="sellerAvatarInput" title="Replace Avatar"
                     style="position:absolute;bottom:2px;right:2px;background:#c0392b;color:#fff;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 2px 5px rgba(0,0,0,.3);">
                <i class="iconoir-camera" style="font-size:13px;"></i>
              </label>
            </div>
            <form method="POST" enctype="multipart/form-data" id="sellerAvatarForm">
              <input type="hidden" name="action" value="update_avatar">
              <input type="file" name="avatar" id="sellerAvatarInput" accept="image/jpeg,image/png,image/webp" style="display:none;">
              <span style="font-size:.83rem;color:#888;">Click the camera icon to replace your photo</span>
            </form>
          </div>
        </div>

        <!-- Account Info -->
        <form method="POST">
          <input type="hidden" name="action" value="save_settings">
          <div class="seller-settings-card">
            <div class="profile-section-title"><i class="iconoir-edit-pencil"></i> Store & Personal Info</div>
            <div class="profile-fields">
              <div class="profile-field">
                <label for="storeName">Store Name</label>
                <input type="text" id="storeName" name="store_name"
                       value="<?= htmlspecialchars($profile['store_name'] ?? '') ?>">
              </div>
              <div class="profile-field">
                <label for="fullname">Full Name</label>
                <input type="text" id="fullname" name="fullname" required
                       value="<?= htmlspecialchars($sellerUser['full_name']) ?>">
              </div>
              <div class="profile-field">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone"
                       value="<?= htmlspecialchars($sellerUser['phone'] ?? '') ?>"
                       placeholder="e.g. +63 912 345 6789">
              </div>
            </div>
          </div>
          <div class="seller-settings-card">
            <div class="profile-section-title"><i class="iconoir-lock"></i> Security</div>
            <div class="profile-fields">
              <div class="profile-field">
                <label for="password">New Password <small>(leave blank to keep current)</small></label>
                <input type="password" id="password" name="password" placeholder="Enter new password">
              </div>
              <div class="profile-field">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password">
              </div>
            </div>
          </div>
          <div class="page-product-actions">
            <button type="submit" class="btn primary">Save Changes</button>
          </div>
        </form>
      </div>
    </section>
  </main>
  <?php require 'partials/footer.php'; ?>
  <nav class="bottom-nav">
    <a href="#overview"  class="nav-item"><i class="iconoir-home"></i><span>Overview</span></a>
    <a href="#products"  class="nav-item"><i class="iconoir-package"></i><span>Products</span></a>
    <a href="#orders"    class="nav-item"><i class="iconoir-shopping-bag"></i><span>Orders</span></a>
    <a href="seller-scan.php" class="nav-item"><i class="iconoir-qr-code"></i><span>Scan</span></a>
    <a href="#messages"  class="nav-item">
      <span class="notif-icon-wrap">
        <i class="iconoir-message"></i>
        <?php if ($total_unread > 0): ?>
          <span class="notif-badge"><?= $total_unread > 9 ? '9+' : $total_unread ?></span>
        <?php endif; ?>
      </span>
      <span>Messages</span>
    </a>
    <a href="#settings"  class="nav-item"><i class="iconoir-settings"></i><span>Settings</span></a>
  </nav>
</div>
<script>
  const thread = document.getElementById('sellerChatThread');
  if (thread) thread.scrollTop = thread.scrollHeight;

  const ta = document.getElementById('sellerMsgInput');
  if (ta) {
    ta.addEventListener('input', function () {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 100) + 'px';
    });
    ta.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (this.value.trim()) this.closest('form').submit();
      }
    });
  }

  document.querySelectorAll('.dashboard-sidebar nav active').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();

      const targetId = link.getAttribute('href').slice(1);
      const target = document.getElementById(targetId);
      if (!target) return;

      const navbarHeight = document.querySelector('header.navbar')?.offsetHeight ?? 65;
      const rect = target.getBoundingClientRect();
      const absoluteTop = rect.top + window.pageYOffset - navbarHeight - 16;

      window.scrollTo({ top: absoluteTop, behavior: 'smooth' });

      document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
      link.classList.add('active');
    });

    
});

  // Product image upload preview
  const productInput   = document.getElementById('productImageInput');
  const productBox     = document.getElementById('productUploadBox');
  const previewWrap    = document.getElementById('productPreviewWrap');
  const previewImg     = document.getElementById('productPreviewImg');
  const removeBtn      = document.getElementById('productRemoveBtn');
  if (productInput) {
    productBox.addEventListener('click', () => productInput.click());
    productInput.addEventListener('change', function () {
      if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
          previewImg.src = e.target.result;
          productBox.style.display = 'none';
          previewWrap.style.display = 'flex';
        };
        reader.readAsDataURL(this.files[0]);
      }
    });
    removeBtn.addEventListener('click', () => {
      productInput.value = '';
      previewImg.src = '';
      previewWrap.style.display = 'none';
      productBox.style.display = 'flex';
    });
  }

  // Seller avatar replace
  const sellerAvatarInput = document.getElementById('sellerAvatarInput');
  const sellerAvatarPreview = document.getElementById('sellerAvatarPreview');
  if (sellerAvatarInput) {
    sellerAvatarInput.addEventListener('change', function () {
      if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { sellerAvatarPreview.src = e.target.result; };
        reader.readAsDataURL(this.files[0]);
        document.getElementById('sellerAvatarForm').submit();
      }
    });
  }

</script>
<script src="js/vendor/chart.umd.js"></script>
<script src="js/charts.js"></script>
</body>
</html>