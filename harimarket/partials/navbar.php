<?php
  $search      = htmlspecialchars($_GET['q']   ?? '');
  $category_id = (int)($_GET['cat'] ?? 0);
?>
<header class="navbar">
  <div class="logo">
    <a href="index.php"><img src="img/elements/header.png" alt="HariMarket Logo"></a>
  </div>
  <div class="search-bar">
    <form method="GET" action="index.php">
      <?php if ($category_id): ?>
        <input type="hidden" name="cat" value="<?= $category_id ?>">
      <?php endif; ?>
      <input type="text" name="q" placeholder="Search products..."
              value="<?= htmlspecialchars($search) ?>" aria-label="Search products">
      <button type="submit" aria-label="Search"><i class="iconoir-search"></i></button>
    </form>
  </div>
  <nav class="nav-icons">
    <?php if (in_array($user['role'] ?? '', ['seller', 'admin'], true)): ?>
    <a href="<?= $user['role'] === 'admin' ? 'admin-dashboard.php' : 'seller-dashboard.php' ?>" aria-label="Dashboard" title="Dashboard"><i class="iconoir-home"></i></a>
    <?php endif; ?>
    <a href="cart.php" aria-label="Cart"><i class="iconoir-cart"></i></a>
    <a href="messages.php" aria-label="Messages"><i class="iconoir-message"></i></a>
    <a href="notifications.php" aria-label="Notifications" class="notif-icon-wrap">
      <i class="iconoir-bell-notification"></i>
      <?php
        if ($badge > 0): ?>
        <span class="notif-badge"><?= $badge > 9 ? '9+' : $badge ?></span>
      <?php endif; ?>
    </a>
    <div class="login dropdown" id="userMenu">
      <button class="dropdown-toggle" id="userMenuButton" aria-haspopup="true"
              aria-expanded="false" aria-label="Account menu">
        <i class="iconoir-profile-circle"></i>
      </button>
      <ul class="dropdown-menu" role="menu" aria-labelledby="userMenuButton">
        <?php if ($user): ?>
          <?php if (($user['role'] ?? '') === 'seller' || ($user['role'] ?? '') === 'admin'): ?>
            <li><a role="menuitem" href="<?= $user['role'] === 'admin' ? 'admin-dashboard.php' : 'seller-dashboard.php' ?>" class="menu-item"><i class="iconoir-home"></i> Dashboard</a></li>
          <?php endif; ?>
          <li><a role="menuitem" href="profile.php"  class="menu-item"><i class="iconoir-user"></i> Profile</a></li>
          <li><a role="menuitem" href="orders.php"   class="menu-item"><i class="iconoir-shopping-bag"></i> Orders</a></li>
          <li><a role="menuitem" href="logout.php"   class="menu-item danger"><i class="iconoir-log-out"></i> Log Out</a></li>
        <?php else: ?>
          <li><a role="menuitem" href="login.php"    class="menu-item"><i class="iconoir-log-in"></i> Login</a></li>
          <li><a role="menuitem" href="registration.php" class="menu-item"><i class="iconoir-user-plus"></i> Register</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </nav>
</header>