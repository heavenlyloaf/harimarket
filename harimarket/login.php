<?php
require_once 'config.php';
if ($user = currentUser()) {
    redirect($user['role'] === 'seller' ? 'seller-dashboard.php' : 'index.php');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $intent   = $_POST['intent']        ?? 'buyer';
    if ($email === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        $usingTemp = false;
        $valid = $user && password_verify($password, $user['password_hash']);
        if ($user && !$valid && !empty($user['temp_password']) && !empty($user['temp_password_expires'])
            && strtotime($user['temp_password_expires']) > time()
            && password_verify($password, $user['temp_password'])) {
            $valid = true;
            $usingTemp = true;
        }
        if ($valid) {
            if ($user['role'] === 'admin') {
                // Admin can log in from anywhere
            } elseif ($intent === 'seller' && $user['role'] !== 'seller') {
                $error = 'This account is not a seller account.';
            } elseif ($intent === 'buyer' && $user['role'] !== 'buyer') {
                $error = 'Please use the Seller login tab.';
            }

            if (!$error) {
                // Check for admin approval
                if ($user['role'] !== 'admin' && !$user['is_approved']) {
                    $error = 'Your account is pending administrator approval. Please wait for an admin to review your registration.';
                    goto render;
                }
                
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id'        => $user['id'],
                    'full_name' => $user['full_name'],
                    'email'     => $user['email'],
                    'role'      => $user['role'],
                    'avatar'    => $user['avatar'],
                ];

                // Logged in with an admin-issued temporary password: force a new password first.
                if ($usingTemp) {
                    $_SESSION['force_password_change'] = true;
                    redirect('change-password.php');
                }

                if ($user['role'] === 'admin') {
                    redirect('admin-dashboard.php');
                }
                redirect($user['role'] === 'seller' ? 'seller-dashboard.php' : 'index.php');
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
render:
?>
<!DOCTYPE html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HariMarket Log In</title>
  <link rel="icon" type="image/x-icon" href="img/elements/favicon.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/auth.css">
  <script src="js/auth.js" defer></script>
</head>
<body>
<div class="auth-container">
  <div class="login-box">
    <img src="img/elements/header.png" alt="Logo">
    <?php if ($error): ?>
      <p style="color:#c00;font-size:.9rem;margin-bottom:12px;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <div class="toggle">
      <button id="buyerBtn" class="active" type="button">Buyer</button>
      <button id="sellerBtn" type="button">Seller</button>
    </div>
    <form method="POST" action="login.php">
      <input type="hidden" name="intent" id="intentField" value="buyer">
      <input type="email"    name="email"    placeholder="Email"    required
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      <input type="password" name="password" placeholder="Password" required>
      <button type="submit" class="login-btn" id="loginLabel">Login as Buyer</button>
    </form>
    <p class="register-link"><a href="registration.php">Don't have an account? Register</a></p>
    <p class="forgot-password-link"><a href="forgot-password.php">Forgot Password?</a></p>
  </div>
</div>
<script>
const intentFld = document.getElementById('intentField');
const loginLbl  = document.getElementById('loginLabel');
buyerBtn.addEventListener('click', () => {
  buyerBtn.classList.add('active');
  buyerBtn.classList.remove('seller');
  sellerBtn.classList.remove('active');
  intentFld.value = 'buyer';
  loginLbl.textContent = 'Login as Buyer';
  loginLbl.classList.remove('seller');
});
sellerBtn.addEventListener('click', () => {
  sellerBtn.classList.add('active', 'seller');
  buyerBtn.classList.remove('active');
  intentFld.value = 'seller';
  loginLbl.textContent = 'Login as Seller';
  loginLbl.classList.add('seller');
});

</script>
</body>
</html>