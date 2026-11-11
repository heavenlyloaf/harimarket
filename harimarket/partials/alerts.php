<?php if (!empty($success)): ?>
  <p style="color:#155724;background:#d4edda;padding:.6rem 1rem;border-radius:6px;margin-bottom:1rem;">
    <?= htmlspecialchars($success) ?>
  </p>
<?php endif; ?>
<?php if (!empty($error)): ?>
  <p style="color:#c00;background:#fdd;padding:.6rem 1rem;border-radius:6px;margin-bottom:1rem;">
    <?= htmlspecialchars($error) ?>
  </p>
<?php endif; ?>