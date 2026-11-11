<?php
require_once 'config.php';
if (currentUser()) redirect('index.php');
$error   = '';
$success = '';
$locations = $pdo->query('SELECT id, name FROM pickup_locations WHERE is_active = 1 ORDER BY name')->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role          = ($_POST['role'] ?? 'buyer') === 'seller' ? 'seller' : 'buyer';
    $full_name     = trim($_POST['full_name'] ?? '');
    $email         = trim($_POST['email']     ?? '');
    $password      = $_POST['password']       ?? '';
    $confirm       = $_POST['confirm']        ?? '';
    $student_no    = trim($_POST['student_number'] ?? '');

    $store_name    = trim($_POST['store_name']   ?? '');
    $contact       = trim($_POST['contact']      ?? '');
    $description   = trim($_POST['description']  ?? '');
    $pickup_id     = (int) ($_POST['pickup_location_id'] ?? 0);
    $pickup_note   = trim($_POST['pickup_note']  ?? '');
    $pickup_hours  = trim($_POST['pickup_hours'] ?? '');

    if (!$full_name || !$email || !$password || !$student_no) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif ($role === 'seller' && !$store_name) {
        $error = 'Store name is required for seller registration.';
    } elseif ($role === 'seller' && !$pickup_id) {
        $error = 'Please choose your campus pick-up point.';
    } elseif ($role === 'seller' && empty($_FILES['serUpload']['name'])) {
        $error = 'Please upload your SER (Student Enrollment Record).';
    } else {
        $chk = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $error = 'An account with that email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $pdo->beginTransaction();
            try {
                $ins = $pdo->prepare(
                    'INSERT INTO users (full_name, email, password_hash, phone, role, student_number)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $ins->execute([$full_name, $email, $hash, $contact ?: null, $role, $student_no]);
                $user_id = (int) $pdo->lastInsertId();

                if ($role === 'seller') {
                    $chkLoc = $pdo->prepare('SELECT 1 FROM pickup_locations WHERE id = ? AND is_active = 1');
                    $chkLoc->execute([$pickup_id]);
                    if (!$chkLoc->fetch()) throw new Exception('Please choose a valid pick-up point.');

                    [$ser_path, $ser_err] = saveUpload($_FILES['serUpload'], 'uploads/ser', 'ser_' . $user_id, true);
                    if ($ser_err) throw new Exception('SER file: ' . $ser_err);

                    $sp = $pdo->prepare(
                        'INSERT INTO seller_profiles (user_id, store_name, description, ser_file, pickup_location_id, pickup_note, pickup_hours)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $sp->execute([$user_id, $store_name, $description ?: null, $ser_path, $pickup_id,
                                  mb_substr($pickup_note, 0, 255) ?: null, mb_substr($pickup_hours, 0, 100) ?: null]);
                }

                $pdo->commit();
                $success = 'Registration submitted! Your account is pending administrator approval. Please wait for an admin to review your registration.';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = $e instanceof PDOException ? 'Something went wrong. Please try again.' : $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HariMarket Registration</title>
  <link rel="icon" type="image/x-icon" href="img/elements/favicon.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/auth.css">
  <script src="js/auth.js" defer></script>
</head>
<body>
<div class="login-container" id="regContainer">
  <div class="login-box">
    <img src="img/elements/header.png" alt="Logo">
    <?php if ($error):   ?><p style="color:#c00;font-size:.9rem;margin-bottom:10px;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($success): ?><p style="color:#155724;font-size:.9rem;margin-bottom:10px;"><?= $success ?></p><?php endif; ?>
    <div class="toggle">
      <button id="buyerBtn" class="active" type="button">Buyer</button>
      <button id="sellerBtn" type="button">Seller</button>
    </div>
    <form class="register-form" method="POST" action="registration.php" enctype="multipart/form-data">
      <input type="hidden" name="role" id="roleField" value="buyer">
       <div class="form-col buyer-col">
        <input type="text"     name="full_name"      placeholder="Full Name"      required
               value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
        <input type="email"    name="email"           placeholder="Email"          required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        <input type="password" name="password"        placeholder="Password"       required>
        <input type="password" name="confirm"         placeholder="Confirm Password" required>
        <input type="text"     name="student_number"  placeholder="Student Number" required
               value="<?= htmlspecialchars($_POST['student_number'] ?? '') ?>">
      </div>
      <div class="form-col seller-col" id="sellerFields">
        <input type="text" name="store_name"  placeholder="Store Name"
               value="<?= htmlspecialchars($_POST['store_name'] ?? '') ?>">
        <input type="tel"  name="contact"     placeholder="Contact Number"
               value="<?= htmlspecialchars($_POST['contact'] ?? '') ?>">
        <select name="pickup_location_id" aria-label="Campus pick-up point">
          <option value="">Pick-up point on campus</option>
          <?php foreach ($locations as $loc): ?>
            <option value="<?= (int) $loc['id'] ?>" <?= (int)($_POST['pickup_location_id'] ?? 0) === (int)$loc['id'] ? 'selected' : '' ?>><?= e($loc['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="pickup_hours" maxlength="100" placeholder="Pick-up hours (e.g. 10am-4pm)"
               value="<?= e($_POST['pickup_hours'] ?? '') ?>">
        <input type="text" name="pickup_note" maxlength="255" placeholder="Pick-up note (e.g. near the guard house)"
               value="<?= e($_POST['pickup_note'] ?? '') ?>">
        <textarea name="description" placeholder="Description (optional)" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        <div class="upload-wrapper">
          <p class="upload-label">Upload SER <span class="upload-hint">(Student Enrollment Record)</span></p>
          <label for="serUpload" class="upload-btn">
            <i class="iconoir-upload"></i> Choose File
          </label>
          <input type="file" id="serUpload" name="serUpload" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*" style="display:none;">
          <div class="upload-filename" id="file-name">
            <i class="iconoir-attachment"></i> No file chosen
          </div>
        </div>
      </div>
      <button type="submit" class="register-btn">Register</button>
    </form>
    <p class="register-link"><a href="login.php">Already have an account? Login</a></p>
  </div>
</div>
<script>
const container  = document.getElementById('regContainer');
const buyerBtn   = document.getElementById('buyerBtn');
const sellerBtn  = document.getElementById('sellerBtn');
const roleField  = document.getElementById('roleField');
const fileInput  = document.getElementById('serUpload');
const fileLabel  = document.getElementById('file-name');
buyerBtn.addEventListener('click', () => {
  buyerBtn.classList.add('active');
  buyerBtn.classList.remove('seller');
  sellerBtn.classList.remove('active');
  container.classList.remove('show-seller');
  roleField.value = 'buyer';
});
sellerBtn.addEventListener('click', () => {
  sellerBtn.classList.add('active', 'seller');
  buyerBtn.classList.remove('active');
  container.classList.add('show-seller');
  roleField.value = 'seller';
});
fileInput.addEventListener('change', () => {
  const name = fileInput.files[0]?.name ?? null;
  fileLabel.textContent = ' ' + (name ?? 'No file chosen');
});
</script>
</body>
</html>
