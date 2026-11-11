<?php
  require_once 'config.php';
  $user = requireLogin();
  $groups = cartGroups($pdo, $user['id']);
  if (!$groups) redirect('cart.php');
  $total = array_sum(array_map(fn($g) => $g['subtotal'], $groups));
  $error = '';

  $blocked = [];
  foreach ($groups as $g) {
      if (!$g['pickup_location_id'] || !$g['pickup_active']) {
          $blocked[] = $g['store_name'] . ' has no active pick-up point. Remove their items or try again later.';
      }
      if ($g['seller_id'] === (int) $user['id']) {
          $blocked[] = 'You cannot order your own products. Remove them from the cart.';
      }
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$blocked) {
      $phone = trim($_POST['phone'] ?? '');
      if ($phone === '' || !preg_match('/^[0-9+()\-\s]{7,20}$/', $phone)) {
          $error = 'Please enter a valid contact number.';
      } else {
          $newIds = [];
          $pdo->beginTransaction();
          try {
              foreach ($groups as $g) {
                  $orderTotal = 0.0;
                  $lines = [];
                  foreach ($g['items'] as $item) {
                      // Lock the product row so two buyers cannot take the same last item.
                      $lock = $pdo->prepare('SELECT stock, price, status, name FROM products WHERE id = ? FOR UPDATE');
                      $lock->execute([$item['product_id']]);
                      $p = $lock->fetch();
                      if (!$p || $p['status'] !== 'approved' || $p['stock'] < $item['quantity']) {
                          throw new RuntimeException(($p['name'] ?? 'An item') . ' does not have enough stock.');
                      }
                      $orderTotal += $p['price'] * $item['quantity'];
                      $lines[] = [$item['product_id'], $item['quantity'], $p['price']];
                  }
                  $code = generatePickupCode($pdo);
                  $pdo->prepare('INSERT INTO orders (buyer_id, seller_id, pickup_location_id, pickup_note, pickup_code, status, total)
                                 VALUES (?,?,?,?,?,?,?)')
                      ->execute([$user['id'], $g['seller_id'], $g['pickup_location_id'],
                                 trim(($g['pickup_hours'] ? $g['pickup_hours'] . '. ' : '') . ($g['pickup_note'] ?? '')) ?: null,
                                 $code, 'placed', $orderTotal]);
                  $oid = (int) $pdo->lastInsertId();
                  $newIds[] = $oid;
                  $oi  = $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?,?,?,?)');
                  $upd = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
                  foreach ($lines as [$pid, $qty, $price]) {
                      $oi->execute([$oid, $pid, $qty, $price]);
                      $upd->execute([$qty, $pid]);
                  }
                  notify($pdo, (int) $user['id'], "Your order #$oid has been placed. Pick-up: {$g['pickup_name']}.", 'orders.php');
                  notify($pdo, (int) $g['seller_id'], "New order #$oid from {$user['full_name']}.", 'seller-dashboard.php#orders');
              }
              $pdo->prepare('DELETE FROM cart WHERE user_id = ?')->execute([$user['id']]);
              $pdo->prepare('UPDATE users SET phone = ? WHERE id = ?')->execute([$phone, $user['id']]);
              $pdo->commit();
              redirect('thankyou.php?orders=' . implode(',', $newIds));
          } catch (RuntimeException $e) {
              $pdo->rollBack();
              $error = $e->getMessage();
          } catch (Throwable $e) {
              if ($pdo->inTransaction()) $pdo->rollBack();
              error_log('Checkout failed: ' . $e->getMessage());
              $error = 'Something went wrong. Please try again.';
          }
      }
  }
  $me = $pdo->prepare('SELECT phone FROM users WHERE id = ?');
  $me->execute([$user['id']]);
  $buyerPhone = $me->fetchColumn() ?: '';
  $pageTitle = 'Checkout';
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
        <h2>Checkout</h2>
        <span class="page-meta"><?= count($groups) ?> pick-up order<?= count($groups) !== 1 ? 's' : '' ?></span>
      </div>
      <?php require 'partials/alerts.php'; ?>
      <?php foreach ($blocked as $b): ?>
        <p style="color:#c00;background:#fdd;padding:.6rem 1rem;border-radius:6px;margin-bottom:1rem;"><?= e($b) ?></p>
      <?php endforeach; ?>

      <?php foreach ($groups as $i => $g): ?>
        <section class="seller-group">
          <div class="seller-group-head">
            <h3>Order <?= $i + 1 ?>: <?= e($g['store_name']) ?></h3>
            <?php if ($g['pickup_name']): ?>
              <span class="pickup-tag"><i class="iconoir-pin-alt"></i> <?= e($g['pickup_name']) ?></span>
            <?php endif; ?>
          </div>
          <?php if ($g['pickup_hours'] || $g['pickup_note']): ?>
            <p class="pickup-help"><?= e(trim(($g['pickup_hours'] ?? '') . ' ' . ($g['pickup_note'] ?? ''))) ?></p>
          <?php endif; ?>
          <div class="card-list">
            <?php foreach ($g['items'] as $item): ?>
              <div class="page-card">
                <div class="cart-row">
                  <img src="<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>" class="cart-thumb">
                  <div class="cart-details">
                    <h3><?= e($item['name']) ?></h3>
                    <div class="cart-price">₱<?= number_format($item['price'], 2) ?></div>
                    <div class="cart-qty-row">
                      <span class="qty-label">Qty: <?= (int) $item['quantity'] ?></span>
                      <span class="cart-line-total">₱<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="seller-group-total">Order total: <strong>₱<?= number_format($g['subtotal'], 2) ?></strong></div>
        </section>
      <?php endforeach; ?>

      <form method="POST" action="checkout.php" id="checkoutForm">
        <div class="summary-box">
          <h3>Order Summary</h3>
          <div class="summary-row total"><span>Total</span><span>₱<?= number_format($total, 2) ?></span></div>
          <div class="checkout-details">
            <h4>Contact Details</h4>
            <div class="checkout-field">
              <label for="phone">Contact Number</label>
              <input type="tel" id="phone" name="phone" required maxlength="20"
                     value="<?= e($_POST['phone'] ?? $buyerPhone) ?>" placeholder="e.g. +63 912 345 6789">
            </div>
            <h4 style="margin-top:1rem;">Payment</h4>
            <label class="payment-option">
              <input type="radio" name="payment" value="cod" checked>
              <span>Pay upon pick-up (no online payment, no delivery)</span>
            </label>
          </div>
          <div class="summary-actions">
            <button type="submit" class="btn primary" <?= $blocked ? 'disabled' : '' ?>>Place Pick-up Order<?= count($groups) > 1 ? 's' : '' ?></button>
            <button type="button" class="btn outline" onclick="window.location.href='cart.php'">Back to Cart</button>
          </div>
        </div>
      </form>
    </div>
  </main>
  <?php require 'partials/footer.php'; ?>
</div>
<?php require 'partials/bottomnav.php'; ?>
</body>
</html>
