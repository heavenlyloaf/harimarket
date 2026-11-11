<?php
require_once 'config.php';
$user = currentUser();
if (!$user) redirect('login.php');

$product_id = (int)($_GET['product_id'] ?? 0);
$user_id    = (int)($_GET['user_id']    ?? 0);

$product = null;
$reported_user = null;

if ($product_id) {
    $stmt = $pdo->prepare('SELECT p.*, u.full_name AS seller_name FROM products p JOIN users u ON u.id = p.seller_id WHERE p.id = ?');
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
}

if ($user_id) {
    $stmt = $pdo->prepare('SELECT full_name FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $reported_user = $stmt->fetch();
}

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason  = trim($_POST['reason'] ?? '');
    $details = trim($_POST['details'] ?? '');
    
    if (!$reason) {
        $error = 'Please select a reason for reporting.';
    } else {
        $ins = $pdo->prepare('INSERT INTO reports (reporter_id, reported_user_id, product_id, reason, details) VALUES (?, ?, ?, ?, ?)');
        $ins->execute([$user['id'], $user_id ?: null, $product_id ?: null, $reason, $details]);
        $success = 'Report submitted. Our moderators will review it shortly.';
    }
}

$pageTitle = 'File a Report';
?>
<!DOCTYPE html>
<html lang="en">
<?php require 'partials/head.php'; ?>
<body>
<div class="page-root">
  <?php require 'partials/navbar.php'; ?>
  <main class="site-content">
    <section class="section" style="max-width: 600px; margin: 0 auto;">
      <h1 class="page-title">File a Report</h1>
      
      <?php if ($success): ?>
        <div class="alert success">
          <p><?= htmlspecialchars($success) ?></p>
          <a href="index.php" class="btn primary" style="margin-top: 1rem; display: inline-block;">Back to Home</a>
        </div>
      <?php else: ?>
        <div class="report-form-card" style="background: #fff; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
          <?php if ($error): ?>
            <div class="alert error" style="margin-bottom: 1rem; color: #c00;"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <?php if ($product): ?>
            <div style="margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #eee;">
              <p>Reporting Product: <strong><?= htmlspecialchars($product['name']) ?></strong></p>
              <p style="font-size: 0.9rem; color: #666;">Seller: <?= htmlspecialchars($product['seller_name']) ?></p>
            </div>
          <?php elseif ($reported_user): ?>
            <div style="margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #eee;">
              <p>Reporting User: <strong><?= htmlspecialchars($reported_user['full_name']) ?></strong></p>
            </div>
          <?php endif; ?>

          <form method="POST">
            <div class="field" style="margin-bottom: 1.2rem;">
              <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Reason</label>
              <select name="reason" required style="width: 100%; padding: 0.8rem; border-radius: 8px; border: 1px solid #ddd;">
                <option value="">-- Select a Reason --</option>
                <option value="Prohibited Items">Prohibited Items</option>
                <option value="Misleading Description">Misleading Description</option>
                <option value="Counterfeit">Counterfeit / Fake</option>
                <option value="Offensive Content">Offensive Content</option>
                <option value="Scam / Fraud">Scam / Fraud</option>
                <option value="Other">Other</option>
              </select>
            </div>
            
            <div class="field" style="margin-bottom: 1.5rem;">
              <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Additional Details</label>
              <textarea name="details" rows="4" placeholder="Please provide more information..." style="width: 100%; padding: 0.8rem; border-radius: 8px; border: 1px solid #ddd;"></textarea>
            </div>

            <button type="submit" class="btn primary" style="width: 100%; padding: 1rem;">Submit Report</button>
            <a href="javascript:history.back()" style="display: block; text-align: center; margin-top: 1rem; color: #666; font-size: 0.9rem;">Cancel</a>
          </form>
        </div>
      <?php endif; ?>
    </section>
  </main>
  <?php require 'partials/footer.php'; ?>
</div>
</body>
</html>
