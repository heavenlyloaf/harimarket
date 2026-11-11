<?php
/**
 * HariMarket bootstrap: configuration, database, session, CSRF and shared helpers.
 * Database credentials come from config/config.php (git-ignored) or environment variables.
 */
$cfgFile = __DIR__ . '/config/config.php';
$cfg = is_file($cfgFile) ? require $cfgFile : [];
$cfg = [
    'db_host' => $cfg['db_host'] ?? getenv('DB_HOST') ?: 'localhost',
    'db_port' => $cfg['db_port'] ?? getenv('DB_PORT') ?: '3306',
    'db_name' => $cfg['db_name'] ?? getenv('DB_NAME') ?: 'harimarket',
    'db_user' => $cfg['db_user'] ?? getenv('DB_USER') ?: 'root',
    'db_pass' => $cfg['db_pass'] ?? (getenv('DB_PASS') !== false ? getenv('DB_PASS') : ''),
];

try {
    $pdo = new PDO(
        'mysql:host=' . $cfg['db_host'] . ';port=' . $cfg['db_port'] . ';dbname=' . $cfg['db_name'] . ';charset=utf8mb4',
        $cfg['db_user'],
        $cfg['db_pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Check config/config.php.');
}
unset($cfg);

if (PHP_SAPI !== 'cli') {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
}

/* ---------- Escaping and small helpers ---------- */
function e($v): string {
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}
function abort(string $msg, int $code = 403): void {
    http_response_code($code);
    echo "<p style='font-family:sans-serif;color:#c00;padding:2rem'>" . e($msg) . "</p>";
    exit;
}

/* ---------- CSRF protection ----------
 * A token is stored in the session. Every POST must send it (hidden "csrf" field or
 * X-CSRF-Token header). An output filter adds the hidden field to every POST form
 * and a <meta name="csrf-token"> tag so fetch() calls can send the header. */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
if (PHP_SAPI !== 'cli') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
            abort('Your session expired or the request was invalid. Go back, refresh the page and try again.', 419);
        }
    }
    ob_start(function (string $html): string {
        if (stripos($html, '<form') === false && stripos($html, '</head>') === false) return $html;
        $t = csrf_token();
        $html = preg_replace(
            '/(<form\b[^>]*\bmethod\s*=\s*["\']?post["\']?[^>]*>)/i',
            '$1<input type="hidden" name="csrf" value="' . $t . '">',
            $html
        );
        return preg_replace('/<\/head>/i', '<meta name="csrf-token" content="' . $t . '"></head>', $html, 1);
    });
}

/* ---------- Authentication helpers ---------- */
function currentUser(): ?array {
    global $pdo;
    $user = $_SESSION['user'] ?? null;
    if ($user && !empty($_SESSION['force_password_change'])) {
        $script = basename($_SERVER['PHP_SELF'] ?? '');
        if (!in_array($script, ['change-password.php', 'logout.php'], true)) {
            $stmt = $pdo->prepare('SELECT temp_password_expires FROM users WHERE id = ?');
            $stmt->execute([$user['id']]);
            $row = $stmt->fetch();
            if ($row && $row['temp_password_expires'] && strtotime($row['temp_password_expires']) > time()) {
                redirect('change-password.php');
            }
            session_destroy();
            redirect('login.php?session_expired=1');
        }
    }
    return $user;
}
function isAdmin(): bool {
    $u = currentUser();
    return $u && $u['role'] === 'admin';
}
/** Redirect to login unless signed in; optionally require a role (403 otherwise). */
function requireLogin(?string $role = null): array {
    $u = currentUser();
    if (!$u) redirect('login.php');
    if ($role !== null && $u['role'] !== $role) abort('You do not have access to this page.', 403);
    return $u;
}
function unreadNotifCount(PDO $pdo): int {
    $u = currentUser();
    if (!$u) return 0;
    $s = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $s->execute([$u['id']]);
    return (int) $s->fetchColumn();
}
function notify(PDO $pdo, int $userId, string $message, string $link = 'notifications.php'): void {
    $pdo->prepare('INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)')
        ->execute([$userId, mb_substr($message, 0, 255), $link]);
}

/* ---------- Orders ---------- */
const ORDER_STATUS_LABELS = [
    'placed'           => 'Placed',
    'preparing'        => 'Preparing',
    'ready_for_pickup' => 'Ready for Pick-up',
    'picked_up'        => 'Picked Up',
    'cancelled'        => 'Cancelled',
];
function orderStatusLabel(string $s): string {
    return ORDER_STATUS_LABELS[$s] ?? ucfirst($s);
}
/** Random pick-up code shown to the buyer as text and as a QR code, e.g. 8F3K-92QX. */
function generatePickupCode(PDO $pdo): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $check = $pdo->prepare('SELECT 1 FROM orders WHERE pickup_code = ?');
    do {
        $c = '';
        for ($i = 0; $i < 8; $i++) $c .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $code = substr($c, 0, 4) . '-' . substr($c, 4);
        $check->execute([$code]);
    } while ($check->fetch());
    return $code;
}

/* ---------- Safe image / document upload ----------
 * Checks upload errors, size limit, real MIME type (not the file name) and image content,
 * then stores the file under a random name. Returns [path, error]. */
function saveUpload(array $file, string $dir, string $prefix, bool $allowPdf = false, int $maxBytes = 2097152): array {
    if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [null, null];
    if ($file['error'] !== UPLOAD_ERR_OK) return [null, 'The file could not be uploaded. Please try again.'];
    if ($file['size'] > $maxBytes) return [null, 'File is too large. Maximum size is ' . ($maxBytes / 1048576) . ' MB.'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $map  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if ($allowPdf) $map['application/pdf'] = 'pdf';
    if (!isset($map[$mime])) {
        return [null, 'Invalid file type. Allowed: JPG, PNG, WEBP' . ($allowPdf ? ', PDF' : '') . '.'];
    }
    if ($mime !== 'application/pdf' && @getimagesize($file['tmp_name']) === false) {
        return [null, 'The file is not a valid image.'];
    }
    $dir = rtrim($dir, '/') . '/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) return [null, 'Upload folder is not writable.'];
    $name = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $map[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) return [null, 'Could not save the file.'];
    return [$dir . $name, null];
}

/** Normalise typed or scanned pick-up codes: "8f3k92qx" and "8F3K-92QX" both work. */
function normalizePickupCode(string $raw): string {
    $c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw));
    return strlen($c) === 8 ? substr($c, 0, 4) . '-' . substr($c, 4) : $c;
}
/** Finds an order by pick-up code for this seller. Returns [order|null, error|null]. */
function lookupPickup(PDO $pdo, int $sellerId, string $raw): array {
    $code = normalizePickupCode($raw);
    $stmt = $pdo->prepare('SELECT o.id, o.status, o.total, o.buyer_id, u.full_name AS buyer_name
                           FROM orders o JOIN users u ON u.id = o.buyer_id WHERE o.pickup_code = ?');
    $stmt->execute([$code]);
    $o = $stmt->fetch();
    $stmt2 = $pdo->prepare('SELECT 1 FROM orders WHERE pickup_code = ? AND seller_id = ?');
    $stmt2->execute([$code, $sellerId]);
    if (!$o || !$stmt2->fetch()) return [null, 'Code not found for your store.'];
    if ($o['status'] !== 'ready_for_pickup') {
        return [null, 'Order #' . $o['id'] . ' is "' . orderStatusLabel($o['status']) . '", not Ready for Pick-up.'];
    }
    $it = $pdo->prepare('SELECT oi.quantity, p.name FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
    $it->execute([$o['id']]);
    $o['items'] = $it->fetchAll();
    return [$o, null];
}
/** Marks the order Picked Up after the seller confirms the buyer's code. Returns [order|null, error|null]. */
function completePickup(PDO $pdo, int $sellerId, string $raw): array {
    $pdo->beginTransaction();
    try {
        [$o, $err] = lookupPickup($pdo, $sellerId, $raw);
        if ($err) { $pdo->rollBack(); return [null, $err]; }
        $upd = $pdo->prepare("UPDATE orders SET status = 'picked_up' WHERE id = ? AND status = 'ready_for_pickup'");
        $upd->execute([$o['id']]);
        if ($upd->rowCount() !== 1) { $pdo->rollBack(); return [null, 'Order was already updated.']; }
        notify($pdo, (int) $o['buyer_id'], 'Your order #' . $o['id'] . ' was picked up. Thank you! You can now leave a review.', 'orders.php');
        $pdo->commit();
        return [$o, null];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('completePickup failed: ' . $e->getMessage());
        return [null, 'Something went wrong. Please try again.'];
    }
}

/** Cart items grouped by seller, each group with the seller's pick-up point. */
function cartGroups(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare('
        SELECT c.quantity, p.id AS product_id, p.name, p.price, p.stock, p.image, p.seller_id, p.status,
               sp.store_name, sp.pickup_note, sp.pickup_hours, sp.pickup_location_id,
               pl.name AS pickup_name, pl.is_active AS pickup_active
        FROM cart c
        JOIN products p            ON p.id = c.product_id
        JOIN seller_profiles sp    ON sp.user_id = p.seller_id
        LEFT JOIN pickup_locations pl ON pl.id = sp.pickup_location_id
        WHERE c.user_id = ?
        ORDER BY p.seller_id, c.added_at DESC
    ');
    $stmt->execute([$userId]);
    $groups = [];
    foreach ($stmt->fetchAll() as $row) {
        $sid = (int) $row['seller_id'];
        if (!isset($groups[$sid])) {
            $groups[$sid] = [
                'seller_id' => $sid, 'store_name' => $row['store_name'],
                'pickup_location_id' => $row['pickup_location_id'], 'pickup_name' => $row['pickup_name'],
                'pickup_active' => (bool) $row['pickup_active'],
                'pickup_note' => $row['pickup_note'], 'pickup_hours' => $row['pickup_hours'],
                'items' => [], 'subtotal' => 0.0,
            ];
        }
        $groups[$sid]['items'][] = $row;
        $groups[$sid]['subtotal'] += $row['price'] * $row['quantity'];
    }
    return array_values($groups);
}

$badge = unreadNotifCount($pdo);
