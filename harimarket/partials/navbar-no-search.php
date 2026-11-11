<header class="navbar">
    <div class="logo">
      <a href="index.php"><img src="img/elements/header.png" alt="HariMarket Logo"></a>
    </div>
    <nav class="nav-icons">
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