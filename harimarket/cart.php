<?php
  require_once 'config.php';
  $user = requireLogin();
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_id'])) {
      $del = $pdo->prepare('DELETE FROM cart WHERE user_id = ? AND product_id = ?');
      $del->execute([$user['id'], (int)$_POST['remove_id']]);
      redirect('cart.php');
  }
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_qty'])) {
      $pid = (int)$_POST['product_id'];
      $stk = $pdo->prepare('SELECT stock FROM products WHERE id = ?');
      $stk->execute([$pid]);
      $stock = (int) $stk->fetchColumn();
      $qty = min(max(1, (int)$_POST['qty']), max(1, $stock));
      $upd = $pdo->prepare('UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?');
      $upd->execute([$qty, $user['id'], $pid]);
      redirect('cart.php');
  }
  $groups   = cartGroups($pdo, $user['id']);
  $itemCount = array_sum(array_map(fn($g) => count($g['items']), $groups));
  $subtotal  = array_sum(array_map(fn($g) => $g['subtotal'], $groups));
  $pageTitle = 'Cart';
?>
<!DOCTYPE html>
<html lang="en">
<?php require 'partials/head.php'; ?>
<body>
<div class="page-root">
  <?php require 'partials/navbar.php'; ?>
  <main class="site-content">
    <div class="page-wrapper">
      <div class="page-heading">
        <h2>My Cart</h2>
        <?php if ($itemCount): ?>
          <span class="page-meta"><?= $itemCount ?> item<?= $itemCount !== 1 ? 's' : '' ?> &middot; <?= count($groups) ?> order<?= count($groups) !== 1 ? 's' : '' ?></span>
        <?php endif; ?>
      </div>
      <?php if (!$groups): ?>
        <div class="empty-state">
          <i class="iconoir-cart"></i>
          <p class="empty-title">Your cart is empty</p>
          <p class="empty-sub">Add some products and they'll show up here.</p>
          <a href="index.php" class="btn primary" style="display:inline-block;">Browse Products</a>
        </div>
      <?php else: ?>
        <p class="pickup-help">HariMarket is pick-up only. Items from different sellers become separate orders, each collected at that seller's pick-up point. Payment is made in person.</p>
        <?php foreach ($groups as $g): ?>
          <section class="seller-group">
            <div class="seller-group-head">
              <h3><i class="iconoir-shop"></i> <?= e($g['store_name']) ?></h3>
              <?php if ($g['pickup_name']): ?>
                <span class="pickup-tag"><i class="iconoir-pin"></i> <?= e($g['pickup_name']) ?><?= $g['pickup_hours'] ? ' &middot; ' . e($g['pickup_hours']) : '' ?></span>
              <?php else: ?>
                <span class="pickup-tag warn">This seller has not set a pick-up point yet</span>
              <?php endif; ?>
            </div>
            <div class="card-list">
              <?php foreach ($g['items'] as $item): ?>
                <div class="page-card">
                  <div class="cart-row">
                    <img src="<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>" class="cart-thumb">
                    <div class="cart-details">
                      <h3><?= e($item['name']) ?></h3>
                      <div class="cart-price">₱<?= number_format($item['price'], 2) ?></div>
                      <form method="POST" action="cart.php" class="cart-qty-form">
                        <input type="hidden" name="update_qty" value="1">
                        <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
                        <label>Qty:</label>
                        <input type="number" name="qty" value="<?= (int) $item['quantity'] ?>"
                               min="1" max="<?= max(1, (int) $item['stock']) ?>" class="cart-qty-input">
                        <button type="submit" class="btn outline" style="padding:3px 10px;font-size:.8rem;">Update</button>
                      </form>
                    </div>
                    <div class="cart-remove">
                      <form method="POST" action="cart.php">
                        <input type="hidden" name="remove_id" value="<?= (int) $item['product_id'] ?>">
                        <button type="submit" class="btn delete-btn" title="Remove"><i class="iconoir-trash-solid"></i></button>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="seller-group-total">Order total: <strong>₱<?= number_format($g['subtotal'], 2) ?></strong></div>
          </section>
        <?php endforeach; ?>
        <div class="summary-box">
          <h3>Order Summary</h3>
          <div class="summary-row"><span>Orders to place</span><span><?= count($groups) ?></span></div>
          <div class="summary-row total"><span>Total</span><span>₱<?= number_format($subtotal, 2) ?></span></div>
          <div class="summary-actions">
            <button class="btn primary" onclick="window.location.href='checkout.php'">Proceed to Checkout</button>
            <button class="btn outline" onclick="window.location.href='index.php'">Continue Shopping</button>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </main>
  <?php require 'partials/footer.php'; ?>
</div>
<?php require 'partials/bottomnav.php'; ?>
</body>
</html>
