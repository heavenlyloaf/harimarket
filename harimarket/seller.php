<?php
require_once 'config.php';
$user      = currentUser();
if (!$user) redirect('login.php');
$seller_id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('
    SELECT u.id, u.full_name, u.avatar, u.created_at,
           sp.store_name, sp.description, sp.rating, sp.pickup_note, sp.pickup_hours, pl.name AS pickup_name
    FROM   users u
    JOIN   seller_profiles sp ON sp.user_id = u.id
    LEFT JOIN pickup_locations pl ON pl.id = sp.pickup_location_id
    WHERE  u.id = ? AND u.role = "seller" AND sp.approved = 1
');
$stmt->execute([$seller_id]);
$seller = $stmt->fetch();
if (!$seller) abort('Seller not found.', 404);
$prods = $pdo->prepare('SELECT * FROM products WHERE seller_id = ? AND stock > 0 AND status = "approved" ORDER BY created_at DESC');
$prods->execute([$seller_id]);
$products = $prods->fetchAll();
$revs = $pdo->prepare('
    SELECT r.rating, r.comment, u.full_name, r.created_at
    FROM   reviews r
    JOIN   products p ON p.id = r.product_id
    JOIN   users u    ON u.id = r.user_id
    WHERE  p.seller_id = ?
    ORDER  BY r.created_at DESC
    LIMIT  10
');
$revs->execute([$seller_id]);
$reviews = $revs->fetchAll();
function stars(float $r): string {
    $f = (int) round($r);
    return str_repeat('★ ', $f) . str_repeat('☆ ', 5 - $f);
}
$pageTitle = $seller['store_name'];
?>
<!DOCTYPE html>
<html lang="en">
<?php require 'partials/head.php'; ?>
<body>
<div class="page-root">
  <?php require 'partials/navbar-no-search.php'; ?>
  <main class="site-content">
    <section class="section">
      <div class="profile-card">
        <div class="profile-header">
          <img src="<?= htmlspecialchars($seller['avatar']) ?>" alt="Seller Avatar" class="profile-avatar">
          <div>
            <h2><?= htmlspecialchars($seller['store_name'] ?? $seller['full_name']) ?></h2>
            <p class="pickup-tag"><i class="iconoir-pin-alt"></i> Pick-up: <?= e($seller['pickup_name'] ?? 'not set yet') ?><?= $seller['pickup_hours'] ? ' &middot; ' . e($seller['pickup_hours']) : '' ?></p>
            <?php if ($seller['pickup_note']): ?><p class="muted-small"><?= e($seller['pickup_note']) ?></p><?php endif; ?>
            <p><strong>Rating:</strong> <?= stars((float)($seller['rating'] ?? 0)) ?></p>
            <p><strong>Member Since:</strong> <?= date('Y', strtotime($seller['created_at'])) ?></p>
            <?php if ($seller['description']): ?>
              <p><?= htmlspecialchars($seller['description']) ?></p>
            <?php endif; ?>
            <?php if ($user && $user['id'] !== $seller['id']): ?>
              <a href="report.php?user_id=<?= $seller['id'] ?>" class="btn outline danger-text" style="margin-top:10px; font-size:0.85rem;">
                <i class="iconoir-warning-triangle"></i> Report Seller
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>
    <section class="section">
      <h3>Products by <?= htmlspecialchars($seller['store_name'] ?? $seller['full_name']) ?></h3>
      <?php if (empty($products)): ?>
        <p>No products available.</p>
      <?php else: ?>
        <div class="product-row">
          <?php foreach ($products as $p): ?>
            <div class="product-card" onclick="window.location='product.php?id=<?= $p['id'] ?>'" style="cursor:pointer;">
              <div class="product-img">
                <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
              </div>
              <h4><?= htmlspecialchars($p['name']) ?></h4>
              <div class="product-footer">
                <div class="product-price">₱<?= number_format($p['price'], 2) ?></div>
                <div class="product-rating"><?= stars((float)$p['rating']) ?></div>
                <button class="btn outline" onclick="event.stopPropagation();window.location.href='product.php?id=<?= $p['id'] ?>'">View</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
    <section class="section">
      <h3>Customer Reviews</h3>
      <div class="orders-list">
        <?php if (empty($reviews)): ?>
          <p>No reviews yet.</p>
        <?php else: ?>
          <?php foreach ($reviews as $r): ?>
            <div class="order-card">
              <p><strong><?= htmlspecialchars($r['full_name']) ?>:</strong>
                 <?= htmlspecialchars($r['comment'] ?? '') ?></p>
              <p>Rating: <?= stars((float)$r['rating']) ?></p>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>
  </main>
  <?php require 'partials/footer.php'; ?>
</div>
<?php require 'partials/bottomnav.php'; ?>
</body>
</html>