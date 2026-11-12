<?php
require_once 'config.php';
$user = requireLogin();
$error = '';
$success = '';

// Buyer cancels an order that the seller has not finished preparing.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_order') {
    $oid = (int) ($_POST['order_id'] ?? 0);
    $pdo->beginTransaction();
    try {
        $o = $pdo->prepare('SELECT id, seller_id, status FROM orders WHERE id = ? AND buyer_id = ? FOR UPDATE');
        $o->execute([$oid, $user['id']]);
        $ord = $o->fetch();
        if (!$ord || !in_array($ord['status'], ['placed', 'preparing'], true)) {
            throw new RuntimeException('This order can no longer be cancelled.');
        }
        $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$oid]);
        $pdo->prepare('UPDATE products p JOIN order_items oi ON oi.product_id = p.id
                       SET p.stock = p.stock + oi.quantity WHERE oi.order_id = ?')->execute([$oid]);
        notify($pdo, (int) $ord['seller_id'], "Order #$oid was cancelled by the buyer.", 'seller-dashboard.php#orders');
        $pdo->commit();
        $success = "Order #$oid cancelled.";
    } catch (RuntimeException $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

$stmt = $pdo->prepare('
    SELECT o.id, o.total, o.status, o.created_at, o.pickup_code, o.pickup_note, o.seller_id,
           sp.store_name, pl.name AS pickup_name
    FROM   orders o
    JOIN   seller_profiles sp ON sp.user_id = o.seller_id
    LEFT JOIN pickup_locations pl ON pl.id = o.pickup_location_id
    WHERE  o.buyer_id = ?
    ORDER  BY o.created_at DESC, o.id DESC
');
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();
$itemsBy = [];
if ($orders) {
    $in = implode(',', array_fill(0, count($orders), '?'));
    $it = $pdo->prepare("SELECT oi.order_id, oi.quantity, oi.unit_price, p.id AS product_id, p.name
                         FROM order_items oi JOIN products p ON p.id = oi.product_id
                         WHERE oi.order_id IN ($in)");
    $it->execute(array_column($orders, 'id'));
    foreach ($it->fetchAll() as $row) $itemsBy[$row['order_id']][] = $row;
}
$steps = ['placed' => 'Placed', 'preparing' => 'Preparing', 'ready_for_pickup' => 'Ready', 'picked_up' => 'Picked Up'];
$pageTitle = 'Orders';
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
        <h2>My Orders</h2>
        <?php if ($orders): ?><span class="page-meta"><?= count($orders) ?> order<?= count($orders) !== 1 ? 's' : '' ?></span><?php endif; ?>
      </div>
      <?php require 'partials/alerts.php'; ?>
      <?php if (!$orders): ?>
        <div class="empty-state">
          <i class="iconoir-shopping-bag"></i>
          <p class="empty-title">No orders yet</p>
          <p class="empty-sub">Your purchases will show up here once you place an order.</p>
          <a href="index.php" class="btn primary" style="display:inline-block;">Browse Products</a>
        </div>
      <?php else: ?>
        <div class="card-list">
          <?php foreach ($orders as $o): $cur = array_search($o['status'], array_keys($steps), true); ?>
            <div class="page-card">
              <div class="order-row">
                <div class="order-info">
                  <h3>Order #<?= (int) $o['id'] ?> &middot; <?= e($o['store_name']) ?></h3>
                  <p><?php foreach ($itemsBy[$o['id']] ?? [] as $it): ?>
                    <?= (int) $it['quantity'] ?> &times; <a href="product.php?id=<?= (int) $it['product_id'] ?>"><?= e($it['name']) ?></a><br>
                  <?php endforeach; ?></p>
                  <p><strong>Date:</strong> <?= date('M d, Y', strtotime($o['created_at'])) ?></p>
                  <p class="pickup-tag"><i class="iconoir-pin"></i> <?= e($o['pickup_name'] ?? 'Pick-up point') ?></p>
                  <?php if ($o['pickup_note']): ?><p class="muted-small"><?= e($o['pickup_note']) ?></p><?php endif; ?>
                </div>
                <div class="order-meta">
                  <span class="order-total">₱<?= number_format($o['total'], 2) ?></span>
                  <span class="status-badge <?= e($o['status']) ?>"><?= e(orderStatusLabel($o['status'])) ?></span>
                </div>
              </div>
              <?php if ($o['status'] !== 'cancelled'): ?>
                <ol class="order-steps" aria-label="Order progress">
                  <?php $n = 0; foreach ($steps as $k => $label): ?>
                    <li class="<?= $n < $cur ? 'done' : ($n === $cur ? 'current' : '') ?>"><?= e($label) ?></li>
                  <?php $n++; endforeach; ?>
                </ol>
              <?php endif; ?>
              <div class="order-actions">
                <?php if (in_array($o['status'], ['placed', 'preparing', 'ready_for_pickup'], true)): ?>
                  <button type="button" class="btn primary" data-qr-open="qr-<?= (int) $o['id'] ?>"><i class="iconoir-qr-code"></i> Show QR code</button>
                <?php endif; ?>
                <a class="btn outline" href="conversation.php?with=<?= (int) $o['seller_id'] ?>&amp;order=<?= (int) $o['id'] ?>">Message seller</a>
                <?php if (in_array($o['status'], ['placed', 'preparing'], true)): ?>
                  <form method="POST" action="orders.php" onsubmit="return confirm('Cancel this order?');" style="display:inline">
                    <input type="hidden" name="action" value="cancel_order">
                    <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                    <button type="submit" class="btn outline danger">Cancel order</button>
                  </form>
                <?php endif; ?>
                <?php if ($o['status'] === 'picked_up' && !empty($itemsBy[$o['id']])): ?>
                  <a class="btn outline" href="product.php?id=<?= (int) $itemsBy[$o['id']][0]['product_id'] ?>#reviews">Write a review</a>
                <?php endif; ?>
              </div>
            </div>
            <div class="qr-modal" id="qr-<?= (int) $o['id'] ?>" hidden role="dialog" aria-modal="true" aria-label="Pick-up QR code for order <?= (int) $o['id'] ?>">
              <div class="qr-modal-box">
                <div class="qr-target" data-code="<?= e($o['pickup_code']) ?>"></div>
                <p class="pickup-code"><?= e($o['pickup_code']) ?></p>
                <p class="muted-small">Show this to the seller at <?= e($o['pickup_name'] ?? 'the pick-up point') ?>.</p>
                <button type="button" class="btn outline" data-qr-close>Close</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>
  <?php require 'partials/footer.php'; ?>
</div>
<?php require 'partials/bottomnav.php'; ?>
<script src="js/vendor/qrcode.js"></script>
<script src="js/pickup-qr.js"></script>
</body>
</html>
