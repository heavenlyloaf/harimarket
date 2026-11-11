<?php
// Command-line only: php tools/create_admin.php
// Creates (or resets) an administrator account. The password is never stored in the repository.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config.php';
echo "Admin full name: ";  $name  = trim(fgets(STDIN));
echo "Admin email: ";      $email = trim(fgets(STDIN));
echo "Admin password (min 10 chars): "; $pass = trim(fgets(STDIN));
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 10) {
    fwrite(STDERR, "Invalid input.\n"); exit(1);
}
$hash = password_hash($pass, PASSWORD_DEFAULT);
$pdo->prepare("INSERT INTO users (full_name,email,password_hash,role,is_approved) VALUES (?,?,?,'admin',1)
               ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), role='admin', is_approved=1")
    ->execute([$name, $email, $hash]);
echo "Admin account ready: $email\n";
