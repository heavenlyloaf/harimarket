<?php
require_once 'config.php';
$user       = currentUser();
$product_id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('
    SELECT p.*, u.full_name AS seller_name, u.email AS seller_email,
           u.id AS seller_user_id,
           sp.store_name, sp.rating AS seller_rating, sp.pickup_note, sp.pickup_hours,
           pl.name AS pickup_name
    FROM   products p
    JOIN   users u          ON u.id = p.seller_id
    LEFT JOIN seller_profiles sp ON sp.user_id = u.id
    LEFT JOIN pickup_locations pl ON pl.id = sp.pickup_location_id
    WHERE  p.id = ?
');
$stmt->execute([$product_id]);
$product = $stmt->fetch();
if (!$product) abort('Product not found.', 404);

// Check approval status
$isOwner = $user && $user['id'] == $product['seller_user_id'];
if ($product['status'] !== 'approved' && !isAdmin() && !$isOwner) {
    abort('This product is pending approval.', 403);
}

$revs = $pdo->prepare('
    SELECT r.*, u.full_name
    FROM   reviews r
    JOIN   users u ON u.id = r.user_id
    WHERE  r.product_id = ?
    ORDER BY r.created_at DESC
');
$revs->execute([$product_id]);
$reviews = $revs->fetchAll();
$msg = '';
$canBuy = $user && $user['role'] === 'buyer' && $product['status'] === 'approved' && $product['stock'] > 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!$user) redirect('login.php');
    $action = $_POST['action'];

    if (in_array($action, ['add_cart', 'buy_now'], true)) {
        if (!$canBuy) {
            $msg = $user['role'] !== 'buyer' ? 'Only buyer accounts can place orders.' : 'This product is not available right now.';
        } else {
            $qty = $action === 'buy_now' ? 1 : max(1, (int) ($_POST['qty'] ?? 1));
            $inCart = $pdo->prepare('SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?');
            $inCart->execute([$user['id'], $product_id]);
            $have = (int) $inCart->fetchColumn();
            $qty = min($qty, max(1, (int) $product['stock'] - $have));
            if ($have >= $product['stock']) {
                $msg = 'You already have the maximum available quantity in your cart.';
            } else {
                $pdo->prepare('INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)
                               ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)')
                    ->execute([$user['id'], $product_id, $qty]);
                if ($action === 'buy_now') redirect('checkout.php');
                $msg = 'Added to cart!';
            }
        }
    }
    if ($action === 'add_review') {
        // Only buyers with a Picked Up order that contains this product may review it, once.
        $elig = $pdo->prepare("SELECT o.id FROM orders o JOIN order_items oi ON oi.order_id = o.id
                               WHERE o.buyer_id = ? AND oi.product_id = ? AND o.status = 'picked_up' LIMIT 1");
        $elig->execute([$user['id'], $product_id]);
        $orderForReview = $elig->fetchColumn();
        $rating  = (int) ($_POST['rating'] ?? 0);
        $comment = mb_substr(trim($_POST['comment'] ?? ''), 0, 1000);
        $dup = $pdo->prepare('SELECT id FROM reviews WHERE product_id = ? AND user_id = ?');
        $dup->execute([$product_id, $user['id']]);
        if (!$orderForReview) {
            $msg = 'You can review a product after your order has been picked up.';
        } elseif ($dup->fetch()) {
            $msg = 'You have already reviewed this product.';
        } elseif ($rating < 1 || $rating > 5) {
            $msg = 'Please select a star rating.';
        } else {
            $pdo->prepare('INSERT INTO reviews (product_id, user_id, order_id, rating, comment) VALUES (?, ?, ?, ?, ?)')
                ->execute([$product_id, $user['id'], $orderForReview, $rating, $comment]);
            $pdo->prepare('UPDATE products
                           SET review_count = (SELECT COUNT(*) FROM reviews WHERE product_id = ?),
                               rating       = (SELECT AVG(rating) FROM reviews WHERE product_id = ?)
                           WHERE id = ?')->execute([$product_id, $product_id, $product_id]);
            $pdo->prepare('UPDATE seller_profiles SET rating = COALESCE((SELECT AVG(r.rating) FROM reviews r JOIN products p ON p.id = r.product_id WHERE p.seller_id = ?), 0) WHERE user_id = ?')
                ->execute([$product['seller_user_id'], $product['seller_user_id']]);
            $msg = 'Review submitted!';
            $revs->execute([$product_id]);
            $reviews = $revs->fetchAll();
        }
    }
}
function stars(float $r): string {
    $f = (int) round($r);
    return str_repeat('★ ', $f) . str_repeat('☆ ', 5 - $f);
}
$pageTitle = $product['name'];
?>
<!DOCTYPE html>
<html lang="en">
<?php require 'partials/head.php'; ?>
<body>
<div class="page-root">
  <?php require 'partials/navbar.php'; ?>
  <main class="site-content">
    <section class="section">
      <?php if (!empty($msg)): ?>
        <div class="alert success">
          <span><?= htmlspecialchars($msg) ?></span>
          <button class="close-btn" onclick="this.parentElement.remove()">×</button>
        </div>
      <?php endif; ?>
      <div class="btn-back">
        <button class="btn outline btn-back iconoir-undo" onclick="history.back()"> Back</button>
      </div>
      <div class="page-product-card">
        <div class="page-product-img">
          <img src="<?= htmlspecialchars($product['image']) ?>"
               alt="<?= htmlspecialchars($product['name']) ?>">
        </div>
        <div class="page-product-info">
          <h1 class="page-product-name"><?= htmlspecialchars($product['name']) ?></h1>
          <div class="page-product-price">₱<?= number_format($product['price'], 2) ?></div>
          <div class="page-product-rating" aria-label="Rating">
            <?= stars((float)$product['rating']) ?>
            <small>(<?= $product['review_count'] ?> reviews)</small>
          </div>
          <?php if ($product['stock'] <= 5 && $product['stock'] > 0): ?>
            <p style="color:#856404;">Only <?= $product['stock'] ?> left!</p>
          <?php elseif ($product['stock'] == 0): ?>
            <p style="color:#c00;">Out of stock</p>
          <?php endif; ?>
          <p class="page-product-description"><?= nl2br(htmlspecialchars($product['description'] ?? '')) ?></p>
          <div class="pickup-help">
            <strong><i class="iconoir-pin"></i> Pick-up point:</strong>
            <?= e($product['pickup_name'] ?? 'Not set by the seller yet') ?>
            <?= $product['pickup_hours'] ? '<br>Hours: ' . e($product['pickup_hours']) : '' ?>
            <?= $product['pickup_note'] ? '<br>' . e($product['pickup_note']) : '' ?>
            <br><span class="muted-small">Pick-up only. Pay in person. Message the seller to agree on a time.</span>
          </div>
          <?php if ($canBuy): ?>
          <form method="POST" action="">
            <input type="hidden" name="action" value="">
            <div class="page-product-actions">
              <button class="btn primary"   type="submit"
                      onclick="this.form.action.value='buy_now'">Buy Now</button>
              <button class="btn outline"   type="submit"
                      onclick="this.form.action.value='add_cart'">Add to Cart</button>
            </div>
          </form>
        </div>
      </div>
    </section>
    <section class="section">
      <div class="profile-card">
        <div class="profile-header" style="display: flex; align-items: center; width: 100%;">
          <a href="seller.php?id=<?= $product['seller_user_id'] ?>">
            <img src="img/elements/placeholder.png" alt="Seller Avatar" class="profile-avatar-product">
          </a>
          <div>
             <p href="seller.php?id=<?= $product['seller_user_id'] ?>" class="seller-link">
                <?= htmlspecialchars($product['store_name'] ?? $product['seller_name']) ?>
            </p>
            <p class="profile-email">&#9733; <?= number_format((float) $product['seller_rating'], 1) ?> seller rating</p>
          </div>
            <div style="margin-left: auto;">
            <?php endif; ?>
            <?php if ($user && $user['id'] != $product['seller_id']): ?>
              <div style="display:flex;gap:.5rem;flex-wrap:wrap;justify-content:flex-end;">
                <a href="conversation.php?with=<?= $product['seller_id'] ?>&product_id=<?= $product['id'] ?>" 
                  class="btn outline">
                  Message Seller
                </a>
                <a href="report.php?product_id=<?= $product['id'] ?>" class="btn delete-btn" style="background:#fff;border-color:#e0e0e0;color:#c00;">
                  <i class="iconoir-warning-triangle"></i> Report
                </a>
              </div>
            <?php endif; ?>
            </div>
        </div>
      </div>
    </section>
    <section class="section">
      <h2>Customer Reviews</h2>
      <div class="orders-list">
        <?php if (empty($reviews)): ?>
          <div class="alert normal">
            <span>No reviews yet. Be the first!</span>
          </div>
        <?php else: ?>
          <?php foreach ($reviews as $r): ?>
            <div class="order-card">
              <p><strong><?= htmlspecialchars($r['full_name']) ?>:</strong>
                 <?= htmlspecialchars($r['comment'] ?? '') ?></p>
              <p>Rating: <?= stars((float)$r['rating']) ?></p>
              <small><?= date('M d, Y', strtotime($r['created_at'])) ?></small>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>  
      <?php
        $alreadyReviewed = false;
        $canReview = false;
        if ($user) {
            $el = $pdo->prepare("SELECT 1 FROM orders o JOIN order_items oi ON oi.order_id = o.id
                                 WHERE o.buyer_id = ? AND oi.product_id = ? AND o.status = 'picked_up' LIMIT 1");
            $el->execute([$user['id'], $product_id]);
            $canReview = (bool) $el->fetchColumn();
            $chkRev = $pdo->prepare('SELECT id FROM reviews WHERE product_id = ? AND user_id = ?');
            $chkRev->execute([$product_id, $user['id']]);
            $alreadyReviewed = (bool) $chkRev->fetch();
        }
      ?>
    <span id="reviews"></span>
    <?php if ($user && $canReview && !$alreadyReviewed): ?>
    <div>
      <div class="leavereview-card">
        <h3>Leave a Review</h3>
        <form method="POST" action="">
          <input type="hidden" name="action" value="add_review">
          <div class="star-picker">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <label style="cursor:pointer;">
                <input type="radio" name="rating" value="<?= $i ?>"
                      style="display:none;" required>
                <span class="star" data-val="<?= $i ?>"
                      style="color:#ccc;transition:color .15s;">&#9733;</span>
              </label>
            <?php endfor; ?>
          </div>
          <textarea name="comment" placeholder="Write your review (optional)"
                    rows="3" class="text-box review"></textarea>
          <button type="submit" class="btn primary">Submit Review</button>
        </form>
  </div>
</div>
      <?php elseif ($user && !$canReview && !$alreadyReviewed && $user['id'] != $product['seller_user_id']): ?>
        <p class="muted-small" style="margin-top:1rem;">You can leave a review after your order for this product has been picked up.</p>
      <?php elseif (!$user): ?>
        <p style="margin-top:1rem;"><a href="login.php">Log in</a> to leave a review.</p>
      <?php elseif ($alreadyReviewed): ?>
        <div class="alert success">You've already reviewed this product.</div>
      <?php endif; ?>
    </section>
  </main>
<?php require 'partials/footer.php'; ?>
</div>
<?php require 'partials/bottomnav.php'; ?>
</body>
</html>