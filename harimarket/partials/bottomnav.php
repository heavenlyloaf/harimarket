<?php
?>
<nav class="bottom-nav">

  <a href="index.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
    <i class="iconoir-home"></i>
    <span>Home</span>
  </a>

  <a href="#" class="nav-item <?= isset($_GET['q']) ? 'active' : '' ?>" id="bottomSearchBtn">
    <i class="iconoir-search"></i>
    <span>Search</span>
  </a>

  <a href="cart.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'cart.php' ? 'active' : '' ?>">
    <i class="iconoir-cart"></i>
    <span>Cart</span>
  </a>

  <a href="messages.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'messages.php' ? 'active' : '' ?>">
    <i class="iconoir-chat-bubble"></i>
    <span>Messages</span>
  </a>

  <a href="notifications.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'notifications.php' ? 'active' : '' ?>">
    <span class="notif-icon-wrap">
      <i class="iconoir-bell-notification"></i>
      <?php if ($badge > 0): ?>
        <span class="notif-badge"><?= $badge > 9 ? '9+' : $badge ?></span>
      <?php endif; ?>
    </span>
    <span>Alerts</span>
  </a>

  <div class="nav-item dropdown dropdown--up" id="bottomUserMenu">
    <button class="dropdown-toggle" id="bottomUserMenuButton"
            aria-haspopup="true" aria-expanded="false" aria-label="Account menu"
            style="flex-direction:column; font-size:11px; gap:2px; background:transparent; border:none; cursor:pointer; color:var(--brand); padding:0; min-width:44px; min-height:44px; display:flex; align-items:center; justify-content:center;">
      <i class="iconoir-profile-circle" style="font-size:20px;"></i>
      <span>Account</span>
    </button>
    <ul class="dropdown-menu" role="menu" aria-labelledby="bottomUserMenuButton">
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