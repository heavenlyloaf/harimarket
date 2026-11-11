<?php
require_once 'config.php';
if (!isAdmin()) redirect('admin-login.php');
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);
    $name = mb_substr(trim($_POST['name'] ?? ''), 0, 100);
    $desc = mb_substr(trim($_POST['description'] ?? ''), 0, 255);
    try {
        if ($action === 'create') {
            if ($name === '') throw new RuntimeException('Location name is required.');
            $pdo->prepare('INSERT INTO pickup_locations (name, description) VALUES (?, ?)')->execute([$name, $desc ?: null]);
            $success = 'Pick-up location added.';
        } elseif ($action === 'update') {
            if ($name === '') throw new RuntimeException('Location name is required.');
            $pdo->prepare('UPDATE pickup_locations SET name = ?, description = ? WHERE id = ?')->execute([$name, $desc ?: null, $id]);
            $success = 'Pick-up location updated.';
        } elseif ($action === 'toggle') {
            $pdo->prepare('UPDATE pickup_locations SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
            $success = 'Location status changed.';
        } elseif ($action === 'delete') {
            $used = $pdo->prepare('SELECT (SELECT COUNT(*) FROM seller_profiles WHERE pickup_location_id = ?) + (SELECT COUNT(*) FROM orders WHERE pickup_location_id = ?)');
            $used->execute([$id, $id]);
            if ((int) $used->fetchColumn() > 0) {
                throw new RuntimeException('This location is used by sellers or past orders. Deactivate it instead of deleting.');
            }
            $pdo->prepare('DELETE FROM pickup_locations WHERE id = ?')->execute([$id]);
            $success = 'Pick-up location deleted.';
        }
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = $e->getCode() === '23000' ? 'A location with that name already exists.' : 'Something went wrong. Please try again.';
    }
}

$rows = $pdo->query('
    SELECT pl.*, (SELECT COUNT(*) FROM seller_profiles sp WHERE sp.pickup_location_id = pl.id) AS sellers
    FROM pickup_locations pl ORDER BY pl.name')->fetchAll();
$editId = (int) ($_GET['edit'] ?? 0);
$pageTitle = 'Pick-up locations';
?>
<!DOCTYPE html>
<html lang="en">
<?php require 'partials/head.php'; ?>
<body>
<div class="page-root">
  <header class="navbar navbar--no-search">
    <div class="logo"><a href="admin-dashboard.php"><img src="img/elements/header.png" alt="HariMarket Logo"></a></div>
    <nav class="nav-icons"><a href="admin-dashboard.php" aria-label="Back to admin"><i class="iconoir-home"></i></a><a href="logout.php" aria-label="Log Out"><i class="iconoir-log-out"></i></a></nav>
  </header>
  <main class="site-content">
    <div class="page-wrapper">
      <div class="page-heading"><h2>Pick-up locations</h2><a href="admin-dashboard.php" class="btn outline">Back to admin</a></div>
      <?php require 'partials/alerts.php'; ?>
      <p class="pickup-help">Sellers choose their pick-up point from this list. Deactivated locations cannot be chosen by sellers or used in new orders.</p>

      <div class="page-card">
        <h3>Add location</h3>
        <form method="POST" class="loc-form">
          <input type="hidden" name="action" value="create">
          <input type="text" name="name" maxlength="100" placeholder="Name (e.g. Main Gate)" required>
          <input type="text" name="description" maxlength="255" placeholder="Description (optional)">
          <button type="submit" class="btn primary">Add</button>
        </form>
      </div>

      <form method="POST" id="editForm"></form>
      <div class="table-scroll">
        <table class="loc-table">
          <thead><tr><th>Name</th><th>Description</th><th>Sellers</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <?php if ($editId === (int) $r['id']): ?>
              <tr>
                <td><input type="hidden" form="editForm" name="action" value="update"><input type="hidden" form="editForm" name="id" value="<?= (int) $r['id'] ?>">
                    <input type="text" form="editForm" name="name" maxlength="100" required value="<?= e($r['name']) ?>"></td>
                <td><input type="text" form="editForm" name="description" maxlength="255" value="<?= e($r['description']) ?>"></td>
                <td><?= (int) $r['sellers'] ?></td><td><?= $r['is_active'] ? 'Active' : 'Inactive' ?></td>
                <td><button type="submit" form="editForm" class="btn primary">Save</button> <a href="admin-locations.php" class="btn outline">Cancel</a></td>
              </tr>
            <?php else: ?>
              <tr>
                <td data-label="Name"><?= e($r['name']) ?></td>
                <td data-label="Description"><?= e($r['description']) ?></td>
                <td data-label="Sellers"><?= (int) $r['sellers'] ?></td>
                <td data-label="Status"><span class="pickup-tag <?= $r['is_active'] ? '' : 'warn' ?>"><?= $r['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                <td data-label="Actions" class="loc-actions">
                  <a href="admin-locations.php?edit=<?= (int) $r['id'] ?>" class="btn outline">Edit</a>
                  <form method="POST"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button type="submit" class="btn outline"><?= $r['is_active'] ? 'Deactivate' : 'Activate' ?></button></form>
                  <form method="POST" onsubmit="return confirm('Delete this location?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button type="submit" class="btn outline danger">Delete</button></form>
                </td>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
  <?php require 'partials/footer.php'; ?>
</div>
</body>
</html>
