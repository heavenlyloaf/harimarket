<?php
require_once 'config.php';
if (currentUser()) redirect('index.php');
$error   = '';
$success = '';
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
                    [$ser_path, $ser_err] = saveUpload($_FILES['serUpload'], 'uploads/ser', 'ser_' . $user_id, true);
                    if ($ser_err) throw new Exception('SER file: ' . $ser_err);

                    $sp = $pdo->prepare(
                        'INSERT INTO seller_profiles (user_id, store_name, description, ser_file)
                         VALUES (?, ?, ?, ?)'
                    );
                    $sp->execute([$user_id, $store_name, $description ?: null, $ser_path]);
                }

                $pdo->commit();
                $success = 'Registration submitted! Your account is pending administrator approval. Please wait for an admin to review your registration.' . ($role === 'seller' ? ' After you are approved, set your pick-up point in Settings before buyers can order from you.' : '');
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
  <link rel="stylesheet" href="css/auth.css?v=<?= (int) @filemtime(__DIR__ . '/css/auth.css') ?>">
  <script src="js/auth.js" defer></script>
  <style>.register-form[hidden]{display:none !important}</style>
</head>
<body>
<div class="login-container<?= (($_POST['role'] ?? '') === 'seller') ? ' show-seller' : '' ?>" id="regContainer">
  <div class="login-box">
    <img src="img/elements/header.png" alt="Logo">
    <?php if ($error):   ?><p class="msg" style="color:#c00;font-size:.9rem;margin-bottom:10px;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($success): ?><p class="msg" style="color:#155724;font-size:.9rem;margin-bottom:10px;"><?= $success ?></p><?php endif; ?>
    <div class="toggle">
      <button id="buyerBtn" class="active" type="button">Buyer</button>
      <button id="sellerBtn" type="button">Seller</button>
    </div>
    <?php $startSeller = (($_POST['role'] ?? '') === 'seller'); ?>
    <form class="register-form buyer-form" id="buyerForm" method="POST" action="registration.php" <?= $startSeller ? 'hidden' : '' ?>>
      <input type="hidden" name="role" value="buyer">
      <div class="form-col">
        <input type="text"     name="full_name"      placeholder="Full Name"      required autocomplete="name"
               value="<?= !$startSeller ? htmlspecialchars($_POST['full_name'] ?? '') : '' ?>">
        <input type="email"    name="email"          placeholder="Email"          required autocomplete="email"
               value="<?= !$startSeller ? htmlspecialchars($_POST['email'] ?? '') : '' ?>">
        <input type="password" name="password"       placeholder="Password"       required autocomplete="new-password">
        <input type="password" name="confirm"        placeholder="Confirm Password" required autocomplete="new-password">
        <input type="text"     name="student_number" placeholder="Student Number" required
               value="<?= !$startSeller ? htmlspecialchars($_POST['student_number'] ?? '') : '' ?>">
      </div>
      <button type="submit" class="register-btn">Register as Buyer</button>
    </form>

    <form class="register-form seller-form" id="sellerForm" method="POST" action="registration.php" enctype="multipart/form-data" <?= $startSeller ? '' : 'hidden' ?>>
      <input type="hidden" name="role" value="seller">
      <div class="form-col">
        <p class="form-col-title">Your account</p>
        <input type="text"     name="full_name"      placeholder="Full Name"      required autocomplete="name"
               value="<?= $startSeller ? htmlspecialchars($_POST['full_name'] ?? '') : '' ?>">
        <input type="email"    name="email"          placeholder="Email"          required autocomplete="email"
               value="<?= $startSeller ? htmlspecialchars($_POST['email'] ?? '') : '' ?>">
        <input type="password" name="password"       placeholder="Password"       required autocomplete="new-password">
        <input type="password" name="confirm"        placeholder="Confirm Password" required autocomplete="new-password">
        <input type="text"     name="student_number" placeholder="Student Number" required
               value="<?= $startSeller ? htmlspecialchars($_POST['student_number'] ?? '') : '' ?>">
      </div>
      <div class="form-col">
        <p class="form-col-title">Your store</p>
        <input type="text" name="store_name" placeholder="Store Name" required
               value="<?= $startSeller ? htmlspecialchars($_POST['store_name'] ?? '') : '' ?>">
        <input type="tel"  name="contact"    placeholder="Contact Number"
               value="<?= $startSeller ? htmlspecialchars($_POST['contact'] ?? '') : '' ?>">
        <textarea name="description" placeholder="Store description (optional)" rows="3"><?= $startSeller ? htmlspecialchars($_POST['description'] ?? '') : '' ?></textarea>
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
        <p class="form-hint">You will choose your pick-up point in Settings after you are approved, and you can change it any time.</p>
      </div>
      <button type="submit" class="register-btn seller">Register as Seller</button>
    </form>
    <p class="register-link"><a href="login.php">Already have an account? Login</a></p>
  </div>
</div>
<script>
const container  = document.getElementById('regContainer');
const buyerBtn   = document.getElementById('buyerBtn');
const sellerBtn  = document.getElementById('sellerBtn');
const buyerForm  = document.getElementById('buyerForm');
const sellerForm = document.getElementById('sellerForm');
const fileInput  = document.getElementById('serUpload');
const fileLabel  = document.getElementById('file-name');

const clearForm = (form) => {
  form.querySelectorAll('input:not([type=hidden]), textarea').forEach((el) => { el.value = ''; });
  if (form === sellerForm) fileLabel.textContent = ' No file chosen';
};

// Buyer and Seller are two separate forms: only one is shown, and switching starts the other one fresh.
const setRole = (role, { clear = true } = {}) => {
  const seller = role === 'seller';
  if (clear) { clearForm(buyerForm); clearForm(sellerForm); document.querySelectorAll('.login-box > p.msg').forEach((p) => p.remove()); }
  buyerForm.hidden = seller;
  sellerForm.hidden = !seller;
  buyerBtn.classList.toggle('active', !seller);
  sellerBtn.classList.toggle('active', seller);
  sellerBtn.classList.toggle('seller', seller);
  container.classList.toggle('show-seller', seller);
};

buyerBtn.addEventListener('click', () => { if (!buyerBtn.classList.contains('active')) setRole('buyer'); });
sellerBtn.addEventListener('click', () => { if (!sellerBtn.classList.contains('active')) setRole('seller'); });
<?php if ($startSeller): ?>setRole('seller', { clear: false });<?php endif; ?>

fileInput.addEventListener('change', () => {
  const name = fileInput.files[0]?.name ?? null;
  fileLabel.textContent = ' ' + (name ?? 'No file chosen');
});
</script>
</body>
</html>
