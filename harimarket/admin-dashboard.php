<?php
require_once 'config.php';
if (!isAdmin()) redirect('admin-login.php');

$success = '';
$error   = '';
if (!empty($_SESSION['flash_success'])) { $success = $_SESSION['flash_success']; unset($_SESSION['flash_success']); }

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Product Approval
    if ($action === 'approve_product') {
        $pid = (int)$_POST['product_id'];
        $pdo->prepare("UPDATE products SET status = 'approved' WHERE id = ?")->execute([$pid]);
        $success = 'Product approved.';
        
        // Notify Seller
        $p = $pdo->prepare("SELECT seller_id, name FROM products WHERE id = ?");
        $p->execute([$pid]);
        $prod = $p->fetch();
        if ($prod) {
            $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)")
                ->execute([$prod['seller_id'], "Your product '{$prod['name']}' has been approved!", "product.php?id=$pid"]);
        }
    }
    if ($action === 'reject_product') {
        $pid = (int)$_POST['product_id'];
        $pdo->prepare("UPDATE products SET status = 'rejected' WHERE id = ?")->execute([$pid]);
        $success = 'Product rejected.';
    }

    // Seller Approval
    if ($action === 'approve_seller') {
        $sid = (int)$_POST['seller_profile_id'];
        $pdo->prepare("UPDATE seller_profiles SET approved = 1 WHERE id = ?")->execute([$sid]);
        $success = 'Seller account approved.';
        
        $s = $pdo->prepare("SELECT user_id FROM seller_profiles WHERE id = ?");
        $s->execute([$sid]);
        $uid = $s->fetchColumn();
        if ($uid) {
            $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)")
                ->execute([$uid, "Your seller account has been approved! You can now start posting products.", "seller-dashboard.php"]);
        }
    }
    if ($action === 'reject_seller') {
        $sid = (int)$_POST['seller_profile_id'];
        $pdo->prepare("DELETE FROM seller_profiles WHERE id = ?")->execute([$sid]);
        $success = 'Seller application removed.';
    }

    // User Management
    if ($action === 'approve_user') {
        $uid = (int)$_POST['user_id'];
        $pdo->prepare("UPDATE users SET is_approved = 1 WHERE id = ?")->execute([$uid]);
        
        // If user is a seller, also approve their seller profile
        $ud = $pdo->prepare("SELECT role, full_name FROM users WHERE id = ?");
        $ud->execute([$uid]);
        $userData = $ud->fetch();
        
        if ($userData && $userData['role'] === 'seller') {
            $pdo->prepare("UPDATE seller_profiles SET approved = 1 WHERE user_id = ?")->execute([$uid]);
        }
        
        // Notify User
        $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)")
            ->execute([$uid, "Welcome to HariMarket! Your account has been approved by the administrator.", "login.php"]);
            
        $success = 'User account approved.';
    }
    if ($action === 'reject_user') {
        $uid = (int)$_POST['user_id'];
        if ($uid !== currentUser()['id']) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
            $success = 'Registration rejected and user removed.';
        }
    }
    if ($action === 'delete_user') {
        $uid = (int)$_POST['user_id'];
        if ($uid !== currentUser()['id']) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
            $success = 'User deleted.';
        } else {
            $error = 'You cannot delete yourself.';
        }
    }
    if ($action === 'set_temp_password') {
        $uid = (int)$_POST['user_id'];
        if ($uid !== currentUser()['id']) {
            // Generate a random temporary password with a secure generator
            $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $temp_password = '';
            for ($i = 0; $i < 12; $i++) $temp_password .= $chars[random_int(0, strlen($chars) - 1)];
            $temp_password_hash = password_hash($temp_password, PASSWORD_DEFAULT);
            $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours')); // Expires in 24 hours

            $pdo->prepare("UPDATE users SET temp_password = ?, temp_password_expires = ? WHERE id = ?")
                ->execute([$temp_password_hash, $expires_at, $uid]);

            $nm = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
            $nm->execute([$uid]);
            // The password is shown once to the admin, who hands it to the user in person.
            $success = 'Temporary password for ' . $nm->fetchColumn() . ': ' . $temp_password . '  (valid 24 hours; give it to the user, who must change it at next login)';
        } else {
            $error = 'You cannot set a temporary password for yourself.';
        }
    }

    // Report Management
    if ($action === 'resolve_report') {
        $rid = (int)$_POST['report_id'];
        $pdo->prepare("UPDATE reports SET status = 'resolved' WHERE id = ?")->execute([$rid]);
        $success = 'Report marked as resolved.';
    }
    if ($action === 'dismiss_report') {
        $rid = (int)$_POST['report_id'];
        $pdo->prepare("UPDATE reports SET status = 'dismissed' WHERE id = ?")->execute([$rid]);
        $success = 'Report dismissed.';
    }
    // Post/Redirect/Get: a refresh must not repeat the approval.
    if ($success && !$error) { $_SESSION['flash_success'] = $success; redirect('admin-dashboard.php'); }
}

// Fetch Data
$stats = [
    'users'    => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'sellers'  => $pdo->query("SELECT COUNT(*) FROM seller_profiles WHERE approved = 1")->fetchColumn(),
    'products' => $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'approved'")->fetchColumn(),
    'pending_products' => $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'pending'")->fetchColumn(),
    'pending_sellers'  => $pdo->query("SELECT COUNT(*) FROM seller_profiles WHERE approved = 0")->fetchColumn(),
    'reports'  => $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn(),
    'registrations' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_approved = 0")->fetchColumn(),
];

$pendingProducts = $pdo->query("
    SELECT p.*, u.full_name AS seller_name 
    FROM products p 
    JOIN users u ON u.id = p.seller_id 
    WHERE p.status = 'pending' 
    ORDER BY p.created_at DESC
")->fetchAll();

$pendingSellers = $pdo->query("
    SELECT sp.*, u.full_name, u.email 
    FROM seller_profiles sp 
    JOIN users u ON u.id = sp.user_id 
    WHERE sp.approved = 0 AND u.is_approved = 1
    ORDER BY sp.created_at DESC
")->fetchAll();

$pendingRegistrations = $pdo->query("SELECT * FROM users WHERE is_approved = 0 ORDER BY created_at DESC")->fetchAll();

$allUsers = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 50")->fetchAll();

$reports = $pdo->query("
    SELECT r.*, 
           u_rep.full_name AS reporter_name,
           u_target.full_name AS reported_user_name,
           p.name AS product_name
    FROM reports r
    JOIN users u_rep ON u_rep.id = r.reporter_id
    LEFT JOIN users u_target ON u_target.id = r.reported_user_id
    LEFT JOIN products p ON p.id = r.product_id
    WHERE r.status = 'pending'
    ORDER BY r.created_at DESC
")->fetchAll();

$pageTitle = 'Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<?php require 'partials/head.php'; ?>
<body class="admin-override">
<div class="page-root">
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
  <header class="navbar navbar--no-search">
    <div class="logo">
      <a href="index.php"><img src="img/elements/header.png" alt="HariMarket Logo"></a>
    </div>
    <nav class="nav-icons">
      <a href="logout.php" aria-label="Log Out"><i class="iconoir-log-out"></i></a>
    </nav>
  </header>

  <?php require 'partials/alerts.php'; ?>

  <main class="dashboard">
    <aside class="dashboard-sidebar">
      <h2>Admin Panel</h2>
      <nav>
        <a href="#overview" class="active" onclick="showSection('overview')"><i class="iconoir-home"></i> Overview</a>
        <a href="#products" onclick="showSection('products')">
            <i class="iconoir-package"></i> Approvals
            <?php if($stats['pending_products']): ?><span class="sidebar-msg-badge"><?= $stats['pending_products'] ?></span><?php endif; ?>
        </a>
        <a href="#sellers" onclick="showSection('sellers')">
            <i class="iconoir-shop"></i> Sellers
            <?php if($stats['pending_sellers']): ?><span class="badge"><?= $stats['pending_sellers'] ?></span><?php endif; ?>
        </a>
        <a href="#registrations" onclick="showSection('registrations')">
            <i class="iconoir-user-plus"></i> Registrations
            <?php if($stats['registrations']): ?><span class="badge"><?= $stats['registrations'] ?></span><?php endif; ?>
        </a>
        <a href="#users" onclick="showSection('users')"><i class="iconoir-group"></i> Users</a>
        <a href="admin-locations.php"><i class="iconoir-pin"></i> Pick-up locations</a>
        <a href="#reports" onclick="showSection('reports')">
            <i class="iconoir-warning-triangle"></i> Reports
            <?php if($stats['reports']): ?><span class="badge"><?= $stats['reports'] ?></span><?php endif; ?>
        </a>
      </nav>
    </aside>

    <section class="dashboard-content">
        <!-- Error/Success Alerts -->
        <?php if ($success): ?>
            <div class="alert success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

      <!-- OVERVIEW -->
      <div class="dashboard-section active" id="overview">
        <h3>System Overview</h3>
        <div class="dashboard-row">
          <div class="dashboard-card">
            <h3>Total Users</h3>
            <p><?= $stats['users'] ?></p>
          </div>
          <div class="dashboard-card">
            <h3>Active Sellers</h3>
            <p><?= $stats['sellers'] ?></p>
          </div>
          <div class="dashboard-card">
            <h3>Approved Products</h3>
            <p><?= $stats['products'] ?></p>
          </div>
        </div>
        <div class="chart-grid" id="chartGrid" aria-live="polite"></div>
      </div>

      <!-- PRODUCTS APPROVAL -->
      <div class="dashboard-section" id="products">
        <h3>Pending Product Approvals</h3>
        <div class="orders-list">
          <?php if (empty($pendingProducts)): ?>
            <p>No products pending approval.</p>
          <?php else: ?>
            <?php foreach ($pendingProducts as $p): ?>
              <div class="order-card" style="display:flex; gap:1.5rem; align-items:center;">
                <img src="<?= htmlspecialchars($p['image']) ?>" style="width:80px;height:80px;object-fit:cover;border-radius:8px;">
                <div style="flex:1;">
                  <h3><?= htmlspecialchars($p['name']) ?></h3>
                  <p>Seller: <?= htmlspecialchars($p['seller_name']) ?></p>
                  <p>Price: ₱<?= number_format($p['price'], 2) ?></p>
                </div>
                <div style="display:flex; gap:0.5rem;">
                  <form method="POST">
                    <input type="hidden" name="action" value="approve_product">
                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                    <button type="submit" class="btn primary">Approve</button>
                  </form>
                  <form method="POST">
                    <input type="hidden" name="action" value="reject_product">
                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                    <button type="submit" class="btn outline danger-text" onclick="return confirm('Reject this product?')">Reject</button>
                  </form>
                  <a href="product.php?id=<?= $p['id'] ?>" target="_blank" class="btn outline">View</a>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- SELLER APPROVAL -->
      <div class="dashboard-section" id="sellers">
        <h3>Pending Seller Applications</h3>
        <div class="orders-list">
          <?php if (empty($pendingSellers)): ?>
            <p>No pending applications.</p>
          <?php else: ?>
            <?php foreach ($pendingSellers as $s): ?>
              <div class="order-card" style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                  <h3><?= htmlspecialchars($s['store_name']) ?></h3>
                  <p>User: <?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['email']) ?>)</p>
                  <p>Applied on: <?= date('M d, Y', strtotime($s['created_at'])) ?></p>
                </div>
                <div style="display:flex; gap:0.5rem;">
                  <form method="POST">
                    <input type="hidden" name="action" value="approve_seller">
                    <input type="hidden" name="seller_profile_id" value="<?= $s['id'] ?>">
                    <button type="submit" class="btn primary">Approve</button>
                  </form>
                  <form method="POST">
                    <input type="hidden" name="action" value="reject_seller">
                    <input type="hidden" name="seller_profile_id" value="<?= $s['id'] ?>">
                    <button type="submit" class="btn outline danger-text" onclick="return confirm('Reject this seller?')">Reject</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
      <!-- REGISTRATIONS APPROVAL -->
      <div class="dashboard-section" id="registrations">
        <h3>Pending User Registrations</h3>
        <div class="orders-list">
          <?php if (empty($pendingRegistrations)): ?>
            <p>No new registrations pending approval.</p>
          <?php else: ?>
            <?php foreach ($pendingRegistrations as $reg): ?>
              <div class="order-card" style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                  <h3><?= htmlspecialchars($reg['full_name']) ?></h3>
                  <p>Email: <?= htmlspecialchars($reg['email']) ?></p>
                  <p>Role: <strong><?= ucfirst($reg['role']) ?></strong></p>
                  <p>Student ID: <?= htmlspecialchars($reg['student_number']) ?></p>
                  <p>Registered on: <?= date('M d, Y', strtotime($reg['created_at'])) ?></p>
                </div>
                <div style="display:flex; gap:0.5rem;">
                  <form method="POST">
                    <input type="hidden" name="action" value="approve_user">
                    <input type="hidden" name="user_id" value="<?= $reg['id'] ?>">
                    <button type="submit" class="btn primary">Approve</button>
                  </form>
                  <form method="POST">
                    <input type="hidden" name="action" value="reject_user">
                    <input type="hidden" name="user_id" value="<?= $reg['id'] ?>">
                    <button type="submit" class="btn outline danger-text" onclick="return confirm('Reject this registration? The account will be deleted.')">Reject</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- USERS -->
      <div class="dashboard-section" id="users">
        <h3>Registered Users</h3>
        <div class="orders-list">
          <?php foreach ($allUsers as $u): ?>
            <div class="order-card" style="display:flex; justify-content:space-between; align-items:center;">
              <div>
                <h3><?= htmlspecialchars($u['full_name']) ?></h3>
                <p><?= htmlspecialchars($u['email']) ?> | Role: <strong><?= ucfirst($u['role']) ?></strong> | Status: 
                  <?php if ($u['is_approved']): ?>
                    <span class="status delivered">Approved</span>
                  <?php else: ?>
                    <span class="status processing">Pending</span>
                  <?php endif; ?>
                </p>
                <p>Joined: <?= date('M d, Y', strtotime($u['created_at'])) ?></p>
              </div>
              <div>
                <?php if ($u['id'] !== currentUser()['id']): ?>
                  <form method="POST" style="display: inline-block;">
                    <input type="hidden" name="action" value="set_temp_password">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <button type="submit" class="btn" style="background: #28a745; color: white; margin-right: 10px;" onclick="return confirm('Set a temporary password for this user?')">
                      <i class="iconoir-key"></i> Set Temp Password
                    </button>
                  </form>
                  <form method="POST" style="display: inline-block;">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <button type="submit" class="btn delete-btn" onclick="return confirm('Terminate this user account? This action cannot be undone.')">
                      <i class="iconoir-user-xmark"></i> Terminate Account
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- REPORTS -->
      <div class="dashboard-section" id="reports">
        <h3>Pending Reports</h3>
        <div class="orders-list">
          <?php if (empty($reports)): ?>
            <p>No pending reports.</p>
          <?php else: ?>
            <?php foreach ($reports as $r): ?>
              <div class="order-card">
                <div style="display:flex; justify-content:space-between;">
                   <div>
                        <h3 style="color:#c00;"><i class="iconoir-warning-triangle"></i> <?= htmlspecialchars($r['reason']) ?></h3>
                        <p>Reporter: <strong><?= htmlspecialchars($r['reporter_name']) ?></strong></p>
                        <p>Target: 
                            <?php if($r['product_name']): ?>
                                Product [<?= htmlspecialchars($r['product_name']) ?>]
                            <?php else: ?>
                                User [<?= htmlspecialchars($r['reported_user_name']) ?>]
                            <?php endif; ?>
                        </p>
                        <p style="background:#f8f9fa; padding:10px; border-radius:6px; margin-top:10px; font-style:italic;">
                            "<?= htmlspecialchars($r['details']) ?>"
                        </p>
                   </div>
                   <div style="display:flex; flex-direction:column; gap:0.5rem;">
                        <form method="POST">
                            <input type="hidden" name="action" value="resolve_report">
                            <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                            <button type="submit" class="btn primary">Resolve</button>
                        </form>
                        <form method="POST">
                            <input type="hidden" name="action" value="dismiss_report">
                            <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                            <button type="submit" class="btn outline">Dismiss</button>
                        </form>
                   </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    </section>
  </main>
  
  <?php require 'partials/footer.php'; ?>
</div>

<style>
  .dashboard-section { display: none; }
  .dashboard-section.active { display: block; animation: fadeIn 0.3s ease; }
  @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
  .danger-text { color: #c00 !important; border-color: #c00 !important; }
  .danger-text:hover { background: #fee !important; }
</style>

<script>
  function showSection(id) {
    // Hide all sections
    document.querySelectorAll('.dashboard-section').forEach(s => s.classList.remove('active'));
    // Show target section
    const target = document.getElementById(id);
    if (target) target.classList.add('active');
    
    // Update sidebar links
    document.querySelectorAll('.dashboard-sidebar nav a').forEach(l => {
      l.classList.remove('active');
      if (l.getAttribute('href') === '#' + id) l.classList.add('active');
    });

    // Mobile: close sidebar if open
    const backdrop = document.getElementById('sidebarBackdrop');
    if (backdrop && backdrop.classList.contains('active')) {
      backdrop.click();
    }
  }

  // Handle hash on load
  window.addEventListener('load', () => {
    const hash = window.location.hash.slice(1);
    if (hash && document.getElementById(hash)) {
      showSection(hash);
    }
  });
</script>
<script src="js/vendor/chart.umd.js"></script>
<script src="js/charts.js"></script>
</body>
</html>
