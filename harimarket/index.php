<?php
  require_once 'config.php';
  $user = requireLogin();
  $search      = trim($_GET['q']  ?? '');
  $category_id = (int) ($_GET['cat']   ?? 0);
  $sort        = $_GET['sort'] ?? 'newest';
  $orderBy = ['newest' => 'p.created_at DESC', 'price_asc' => 'p.price ASC', 'price_desc' => 'p.price DESC',
              'rating' => 'p.rating DESC, p.review_count DESC'][$sort] ?? 'p.created_at DESC';
  $params = [];
  $sql    = '
      SELECT p.*, sp.store_name, c.name AS category_name
      FROM   products p
      JOIN   seller_profiles sp ON sp.user_id = p.seller_id AND sp.approved = 1
      LEFT JOIN categories c  ON c.id = p.category_id
      WHERE  p.stock > 0 AND p.status = "approved" AND sp.pickup_location_id IS NOT NULL
  ';
  if ($search !== '') {
      $like = '%' . addcslashes($search, '%_\\') . '%';
      $sql     .= ' AND (p.name LIKE ? OR p.description LIKE ? OR sp.store_name LIKE ?)';
      array_push($params, $like, $like, $like);
  }
  if ($category_id) {
      $sql     .= ' AND p.category_id = ?';
      $params[] = $category_id;
  }
  $sql .= " ORDER BY $orderBy";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $products = $stmt->fetchAll();
  $cats = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
  function stars(float $r): string {
      $full  = (int) round($r);
      $empty = 5 - $full;
      return str_repeat('★ ', $full) . str_repeat('☆ ', $empty);
  }
?>
<!DOCTYPE html>
<html lang="en">
<?php require 'partials/head.php'; ?>
<body>
<div class="page-root">
  <?php require 'partials/navbar.php'; ?>
  <main class="site-content">
    <section class="section categories-bar">
      <div class="categories dropdown" id="categoriesMenu">
        <button class="dropdown-toggle" id="categoriesMenuButton"
                aria-haspopup="true" aria-expanded="false" aria-label="Categories menu">
          <i class="iconoir-list"></i> Categories
        </button>
        <ul class="dropdown-menu" role="menu" aria-labelledby="categoriesMenuButton">
          <li><a role="menuitem" href="index.php" class="menu-item">All</a></li>
          <?php foreach ($cats as $c): ?>
            <li>
              <a role="menuitem" href="index.php?cat=<?= $c['id'] ?>" class="menu-item">
                <?= htmlspecialchars($c['name']) ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>
    <section class="section">
      <div class="sort-bar">
        <label for="sortSelect">Sort by</label>
        <select id="sortSelect" name="sort">
          <option value="newest"     <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
          <option value="price_asc"  <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
          <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
          <option value="rating"     <?= $sort === 'rating' ? 'selected' : '' ?>>Top rated</option>
        </select>
      </div>
      <p id="noResults" <?= $products ? 'hidden' : '' ?>>No products found<?= $search ? ' for "' . e($search) . '"' : '' ?>.</p>
      <?php if (empty($products)): ?>
        <div class="product-row" id="productGrid"></div>
      <?php else: ?>
        <div class="product-row" id="productGrid">
          <?php foreach ($products as $p): ?>
            <div class="product-card" data-link="product.php?id=<?= $p['id'] ?>"
                 style="cursor:pointer;" onclick="window.location='product.php?id=<?= $p['id'] ?>'">
              <div class="product-img">
                <img src="<?= htmlspecialchars($p['image']) ?>"
                     alt="<?= htmlspecialchars($p['name']) ?>">
              </div>
              <h4><?= htmlspecialchars($p['name']) ?></h4>
              <div class="product-footer">
                <div class="product-price">₱<?= number_format($p['price'], 2) ?></div>
                <div class="product-rating"><?= stars((float)$p['rating']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>
  <?php require 'partials/footer.php'; ?>
</div>
  <?php require 'partials/bottomnav.php'; ?>
<script src="js/search.js"></script>
</body>
</html>