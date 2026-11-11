<?php
require_once 'config.php';
$user = requireLogin();
$ids = array_values(array_filter(array_map('intval', explode(',', $_GET['orders'] ?? ($_GET['order'] ?? '')))));
$orders = [];
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT o.id, o.total, o.pickup_code, o.pickup_note, sp.store_name, pl.name AS pickup_name
        FROM orders o
        JOIN seller_profiles sp ON sp.user_id = o.seller_id
        LEFT JOIN pickup_locations pl ON pl.id = o.pickup_location_id
        WHERE o.buyer_id = ? AND o.id IN ($in)
        ORDER BY o.id");
    $stmt->execute(array_merge([$user['id']], $ids));
    $orders = $stmt->fetchAll();
}
$pageTitle = 'Order placed';
?>
<!DOCTYPE html>
<html lang="en">
<?php require 'partials/head.php'; ?>
<body>
<div class="page-root">
<?php require 'partials/navbar-no-search.php'; ?>
  <main class="site-content">
    <div class="page-wrapper">
      <div class="thankyou-box">
        <i class="iconoir-check-circle thankyou-icon"></i>
        <h1>Order<?= count($orders) > 1 ? 's' : '' ?> placed!</h1>
        <p class="order-note">Pay in person at the pick-up point. Show the QR code to the seller when you collect your order. Use the order chat to agree on a time.</p>
        <div class="qr-grid">
          <?php foreach ($orders as $o): ?>
            <div class="qr-card">
              <div class="qr-target" data-code="<?= e($o['pickup_code']) ?>"></div>
              <h3>Order #<?= (int) $o['id'] ?></h3>
              <p><?= e($o['store_name']) ?></p>
              <p class="pickup-tag"><i class="iconoir-pin-alt"></i> <?= e($o['pickup_name'] ?? 'Pick-up point') ?></p>
              <?php if ($o['pickup_note']): ?><p class="muted-small"><?= e($o['pickup_note']) ?></p><?php endif; ?>
              <p class="pickup-code"><?= e($o['pickup_code']) ?></p>
              <p>Total: <strong>₱<?= number_format($o['total'], 2) ?></strong></p>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="thankyou-actions">
          <button class="btn primary" onclick="window.location.href='orders.php'"><i class="iconoir-shopping-bag"></i> View My Orders</button>
          <button class="btn outline" onclick="window.location.href='index.php'">Continue Shopping</button>
        </div>
      </div>
    </div>
  </main>
  <?php require 'partials/footer.php'; ?>
</div>
<script src="js/vendor/qrcode.js"></script>
<script src="js/pickup-qr.js"></script>
</body>
</html>
