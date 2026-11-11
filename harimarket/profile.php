<?php
require_once 'config.php';
$user = currentUser();
if (!$user) redirect('login.php');
$success = '';
$error   = '';
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$buyer = $stmt->fetch();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Avatar: camera capture or file, validated on the server (type, size, real image)
    [$up, $upErr] = saveUpload($_FILES['avatar'] ?? [], 'uploads/avatars', 'avatar_' . $user['id']);
    if ($upErr) {
        $error = $upErr;
    } elseif ($up) {
        $pdo->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$up, $user['id']]);
        $buyer['avatar'] = $up;
        $success = 'Avatar updated successfully.';
    }
    if (isset($_POST['fullname'])) {
    $full_name    = trim($_POST['fullname']      ?? '');
    $email        = trim($_POST['email']         ?? '');
    $phone        = trim($_POST['phone']         ?? '');
    $new_password = $_POST['password']           ?? '';
    $confirm      = $_POST['confirm_password']   ?? '';
    if (!$full_name || !$email) {
        $error = 'Full name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email.';
    } elseif ($new_password && strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new_password && $new_password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $chk->execute([$email, $user['id']]);
        if ($chk->fetch()) {
            $error = 'That email is already in use.';
        } else {
            $hash = $new_password ? password_hash($new_password, PASSWORD_BCRYPT) : $buyer['password_hash'];
            $upd = $pdo->prepare('UPDATE users SET full_name=?, email=?, phone=?, password_hash=? WHERE id=?');
            $upd->execute([$full_name, $email, $phone, $hash, $user['id']]);
            $_SESSION['user']['full_name'] = $full_name;
            $_SESSION['user']['email']     = $email;
            $buyer['full_name'] = $full_name;
            $buyer['email']     = $email;
            $buyer['phone']     = $phone;
            $success = 'Profile updated successfully.';
        }
    }
    }
}
$pageTitle = 'Profile & Settings';
?>
<!DOCTYPE html>
<html lang="en">
<?php require 'partials/head.php'; ?>
<body>
<div class="page-root">
<?php require 'partials/navbar-no-search.php'; ?>
  <main class="site-content">
    <div class="page-wrapper">
      <div class="page-heading">
        <h2>My Profile</h2>
      </div>
      <?php require 'partials/alerts.php'; ?>

      <!-- Avatar Card -->
      <div class="page-card profile-hero">
        <div class="profile-hero-inner">
          <div class="profile-avatar-wrap">
            <img src="<?= htmlspecialchars($buyer['avatar']) ?>" alt="Avatar" class="profile-avatar" id="avatarPreview">
            <label for="avatarInput" class="avatar-cam-btn" title="Replace photo">
              <i class="iconoir-camera"></i>
            </label>
          </div>
          <form method="POST" action="profile.php" enctype="multipart/form-data" id="avatarForm">
            <input type="file" name="avatar" id="avatarInput" accept="image/*" style="display:none;">
          </form>
          <div class="profile-hero-info">
            <h3><?= htmlspecialchars($buyer['full_name']) ?></h3>
            <p class="profile-hero-email"><?= htmlspecialchars($buyer['email']) ?></p>
            <div class="profile-meta-row">
              <?php if (!empty($buyer['phone'])): ?>
                <span class="profile-meta-chip"><i class="iconoir-phone"></i> <?= htmlspecialchars($buyer['phone']) ?></span>
              <?php endif; ?>
              <span class="profile-meta-chip"><i class="iconoir-calendar"></i> Joined <?= date('F Y', strtotime($buyer['created_at'])) ?></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit Form -->
      <form class="profile-edit-form" method="POST" action="profile.php">
        <!-- Personal Info -->
        <div class="page-card profile-section">
          <h4 class="profile-section-title"><i class="iconoir-user"></i> Personal Information</h4>
          <div class="profile-fields">
            <div class="profile-field">
              <label for="fullname">Full Name</label>
              <input type="text" id="fullname" name="fullname" required
                     value="<?= htmlspecialchars($buyer['full_name']) ?>">
            </div>
            <div class="profile-field">
              <label for="email">Email Address</label>
              <input type="email" id="email" name="email" required
                     value="<?= htmlspecialchars($buyer['email']) ?>">
            </div>
            <div class="profile-field">
              <label for="phone">Phone Number</label>
              <input type="tel" id="phone" name="phone"
                     value="<?= htmlspecialchars($buyer['phone'] ?? '') ?>"
                     placeholder="e.g. +63 912 345 6789">
            </div>
          </div>
        </div>

        <!-- Security -->
        <div class="page-card profile-section">
          <h4 class="profile-section-title"><i class="iconoir-lock"></i> Security</h4>
          <div class="profile-fields">
            <div class="profile-field">
              <label for="password">New Password <small>leave blank to keep current</small></label>
              <input type="password" id="password" name="password" placeholder="Enter new password">
            </div>
            <div class="profile-field">
              <label for="confirm_password">Confirm Password</label>
              <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat new password">
            </div>
          </div>
        </div>

        <div class="profile-form-actions">
          <button type="submit" class="btn primary">Save Changes</button>
          <button type="reset" class="btn outline">Cancel</button>
        </div>
      </form>

    </div>
  </main>
<?php require 'partials/footer.php'; ?>
</div>
<?php require 'partials/bottomnav.php'; ?>
<script>
  const avatarInput = document.getElementById('avatarInput');
  const avatarPreview = document.getElementById('avatarPreview');
  if (avatarInput) {
    avatarInput.addEventListener('change', function () {
      if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { avatarPreview.src = e.target.result; };
        reader.readAsDataURL(this.files[0]);
        document.getElementById('avatarForm').submit();
      }
    });
  }
</script>
</body>
</html>