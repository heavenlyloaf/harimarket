<?php
require_once 'config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password - HariMarket</title>
  <link rel="icon" type="image/x-icon" href="img/elements/favicon.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/auth.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css">
  <style>
    body {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        min-height: 100vh;
        margin: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        padding-top: 0;
    }
    .forgot-password-container {
      max-width: 440px;
      width: 90%;
      padding: 56px 40px;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(12px);
      border-radius: 32px;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
      text-align: center;
      border: 1px solid rgba(255, 255, 255, 0.6);
      position: relative;
      overflow: hidden;
    }
    .forgot-password-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 6px;
        background: linear-gradient(90deg, #3b82f6, #6366f1);
    }
    .icon-box {
        width: 84px;
        height: 84px;
        background: #f0f7ff;
        color: #3b82f6;
        border-radius: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 32px;
        font-size: 42px;
        transform: rotate(-3deg);
        box-shadow: 0 12px 24px rgba(59, 130, 246, 0.15);
    }
    .forgot-password-container h2 {
      margin-bottom: 16px;
      color: #0f172a;
      font-weight: 800;
      font-size: 1.85rem;
      letter-spacing: -0.025em;
    }
    .instruction-text {
        color: #64748b;
        line-height: 1.7;
        margin-bottom: 40px;
        font-size: 1.1rem;
    }
    .instruction-text strong {
        color: #1e293b;
        font-weight: 700;
    }
    .action-step {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 32px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }
    .action-step i {
        font-size: 1.5rem;
        color: #3b82f6;
    }
    .action-step span {
        font-weight: 600;
        color: #334155;
        font-size: 0.95rem;
    }
    .back-link {
      padding-top: 24px;
      border-top: 1px solid #f1f5f9;
    }
    .back-link a {
      color: #94a3b8;
      text-decoration: none;
      font-size: 1rem;
      font-weight: 500;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      transition: all 0.2s;
    }
    .back-link a:hover {
      color: #3b82f6;
    }
    .back-link a i {
        transition: transform 0.2s;
    }
    .back-link a:hover i {
        transform: translateX(-5px);
    }
  </style>
</head>
<body>
<div class="forgot-password-container">
  <div class="icon-box">
    <i class="iconoir-group"></i>
  </div>
  <h2>Need a Reset?</h2>
  <p class="instruction-text">
    For security reasons, password resets must be done <strong>face-to-face</strong> with the administrator.
  </p>

  <div class="action-step">
    <i class="iconoir-walking"></i>
    <span>Please proceed to the Administrator's Office</span>
  </div>

  <div class="back-link">
    <a href="login.php"><i class="iconoir-arrow-left"></i> Back to Login</a>
  </div>
</div>
</body>
</html>