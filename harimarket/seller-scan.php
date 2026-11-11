<?php
require_once 'config.php';
$user = requireLogin('seller');

// JSON endpoint used by the camera scanner and the manual code form (called with fetch()).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $code   = (string) ($_POST['code'] ?? '');
    $action = $_POST['action'] ?? 'lookup';
    if ($action === 'confirm') {
        [$o, $err] = completePickup($pdo, (int) $user['id'], $code);
    } else {
        [$o, $err] = lookupPickup($pdo, (int) $user['id'], $code);
    }
    echo json_encode($err ? ['ok' => false, 'error' => $err] : ['ok' => true, 'order' => $o, 'confirmed' => $action === 'confirm']);
    exit;
}
$pageTitle = 'Scan QR';
?>
<!DOCTYPE html>
<html lang="en">
<?php require 'partials/head.php'; ?>
<body>
<div class="page-root">
  <header class="navbar navbar--no-search">
    <div class="logo"><a href="seller-dashboard.php"><img src="img/elements/header.png" alt="HariMarket Logo"></a></div>
    <nav class="nav-icons"><a href="seller-dashboard.php" aria-label="Back to dashboard"><i class="iconoir-home"></i></a><a href="logout.php" aria-label="Log Out"><i class="iconoir-log-out"></i></a></nav>
  </header>
  <main class="site-content">
    <div class="page-wrapper">
      <div class="page-heading"><h2>Confirm pick-up</h2><a href="seller-dashboard.php#orders" class="btn outline">Back to orders</a></div>
      <p class="pickup-help">Scan the buyer's QR code with your camera, or type the code shown under the QR. Only orders that are <strong>Ready for Pick-up</strong> and belong to your store can be confirmed.</p>
      <div class="scan-grid">
        <div class="page-card">
          <div id="reader" class="scan-reader" aria-label="Camera preview"></div>
          <div class="order-actions">
            <button type="button" class="btn primary" id="startScan"><i class="iconoir-camera"></i> Start camera</button>
            <button type="button" class="btn outline" id="stopScan" hidden>Stop camera</button>
          </div>
          <p class="muted-small" id="camNote">Your browser will ask for permission to use the camera.</p>
        </div>
        <div class="page-card">
          <form id="codeForm" autocomplete="off">
            <label for="codeInput"><strong>Or type the pick-up code</strong></label>
            <input type="text" id="codeInput" maxlength="12" placeholder="8F3K-92QX" style="width:100%;padding:.6rem;margin:.4rem 0;border:1px solid #ccc;border-radius:6px;font-family:monospace;text-transform:uppercase">
            <button type="submit" class="btn outline" style="width:100%">Verify code</button>
          </form>
          <div id="result" class="scan-result" hidden aria-live="polite"></div>
        </div>
      </div>
    </div>
  </main>
  <?php require 'partials/footer.php'; ?>
</div>
<script src="js/vendor/html5-qrcode.min.js"></script>
<script src="js/seller-scan.js"></script>
</body>
</html>
