<?php
require_once 'config.php';

// Check if user is logged in
if (!currentUser()) {
    redirect('login.php');
}
$st = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$st->execute([$_SESSION['user']['id']]);
$user = $st->fetch();
$error = '';
$success = '';

// True when the user signed in with an admin-issued temporary password.
$has_temp_password = !empty($_SESSION['force_password_change'])
    && !empty($user['temp_password_expires'])
    && strtotime($user['temp_password_expires']) > time();

// Force password change if using temporary password
if ($has_temp_password) {
    // User must change password
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        if ($new_password === '' || $confirm_password === '') {
            $error = 'Please fill in all fields.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($new_password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } else {
            // Update password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

            $pdo->prepare("UPDATE users
                          SET password_hash = ?, temp_password = NULL, temp_password_expires = NULL
                          WHERE id = ?")
                ->execute([$new_password_hash, $user['id']]);

            // Clear session and force re-login
            unset($_SESSION['force_password_change']);
            session_destroy();
            redirect('login.php?password_changed=1');
        }
    }
} else {
    // Regular password change
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $current_password = trim($_POST['current_password'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        if ($current_password === '' || $new_password === '' || $confirm_password === '') {
            $error = 'Please fill in all fields.';
        } elseif (!password_verify($current_password, $user['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($new_password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } else {
            // Update password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                ->execute([$new_password_hash, $user['id']]);

            $success = 'Password changed successfully.';
        }
    }
}
?>

<!DOCTYPE html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Change Password - HariMarket</title>
  <link rel="icon" type="image/x-icon" href="img/elements/favicon.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/auth.css">
  <style>
    .change-password-container {
      max-width: 500px;
      margin: 50px auto;
      padding: 40px;
      background: white;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .change-password-container h2 {
      text-align: center;
      margin-bottom: 30px;
      color: #333;
    }
    .form-group {
      margin-bottom: 20px;
    }
    .form-group label {
      display: block;
      margin-bottom: 5px;
      font-weight: 500;
      color: #555;
    }
    .form-group input {
      width: 100%;
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 16px;
    }
    .btn {
      width: 100%;
      padding: 12px;
      background: #007bff;
      color: white;
      border: none;
      border-radius: 5px;
      font-size: 16px;
      cursor: pointer;
      transition: background 0.3s;
    }
    .btn:hover {
      background: #0056b3;
    }
    .back-link {
      text-align: center;
      margin-top: 20px;
    }
    .back-link a {
      color: #007bff;
      text-decoration: none;
    }
    .back-link a:hover {
      text-decoration: underline;
    }
    .alert {
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 5px;
    }
    .alert.error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    .alert.success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    .temp-password-notice {
      background: #fff3cd;
      color: #856404;
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 20px;
      border: 1px solid #ffeaa7;
    }
  </style>
</head>
<body>
<div class="change-password-container">
  <h2>Change Password</h2>

  <?php if ($has_temp_password): ?>
    <div class="temp-password-notice">
      <strong>⚠️ Using Temporary Password</strong><br>
      Please create a new password for your account.
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <form method="POST" action="change-password.php">
    <?php if ($has_temp_password): ?>
      <!-- Temporary password change form -->
      <div class="form-group">
        <label for="new_password">New Password</label>
        <input type="password" id="new_password" name="new_password" required
               minlength="8" placeholder="Enter new password">
      </div>
      <div class="form-group">
        <label for="confirm_password">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required
               minlength="8" placeholder="Confirm new password">
      </div>
    <?php else: ?>
      <!-- Regular password change form -->
      <div class="form-group">
        <label for="current_password">Current Password</label>
        <input type="password" id="current_password" name="current_password" required
               placeholder="Enter current password">
      </div>
      <div class="form-group">
        <label for="new_password">New Password</label>
        <input type="password" id="new_password" name="new_password" required
               minlength="8" placeholder="Enter new password">
      </div>
      <div class="form-group">
        <label for="confirm_password">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required
               minlength="8" placeholder="Confirm new password">
      </div>
    <?php endif; ?>

    <button type="submit" class="btn">
      <?= $has_temp_password ? 'Set New Password' : 'Change Password' ?>
    </button>
  </form>

  <div class="back-link">
    <a href="<?= $user['role'] === 'seller' ? 'seller-dashboard.php' : 'index.php' ?>">
      ← Back to Dashboard
    </a>
  </div>
</div>
</body>
</html>