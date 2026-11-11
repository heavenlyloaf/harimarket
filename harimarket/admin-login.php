<?php
require_once 'config.php';

// Redirect if already logged in as admin
if ($user = currentUser()) {
    if ($user['role'] === 'admin') {
        redirect('admin-dashboard.php');
    } else {
        // If logged in but not admin, log them out or redirect
        redirect($user['role'] === 'seller' ? 'seller-dashboard.php' : 'index.php');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['role'] !== 'admin') {
                $error = 'Access denied. You do not have admin privileges.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id'        => $user['id'],
                    'full_name' => $user['full_name'],
                    'email'     => $user['email'],
                    'role'      => $user['role'],
                    'avatar'    => $user['avatar'],
                ];
                redirect('admin-dashboard.php');
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HariMarket Admin Login</title>
    <link rel="icon" type="image/x-icon" href="img/elements/favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/auth.css">
    <style>
        :root {
            --admin-color: #2c3e50;
            --admin-dark: #1a252f;
        }
        .login-btn.admin {
            background: var(--admin-color);
        }
        .login-btn.admin:hover {
            background: var(--admin-dark);
        }
        .admin-badge {
            display: inline-block;
            background: #eef2f7;
            color: var(--admin-color);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 20px;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
<div class="auth-container">
    <div class="login-box">
        <img src="img/elements/header.png" alt="HariMarket Logo">
        
        <div class="admin-badge">Admin Portal</div>

        <?php if ($error): ?>
            <p style="color:#c00; font-size:.9rem; margin-bottom:15px;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST">
            <input type="email" name="email" placeholder="Admin Email" required 
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" class="login-btn admin">Login to Dashboard</button>
        </form>
        
        <p class="register-link"><a href="login.php">Go back to marketplace</a></p>
    </div>
</div>
</body>
</html>
