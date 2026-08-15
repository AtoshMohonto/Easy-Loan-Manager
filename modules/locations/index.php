<?php
require_once __DIR__ . '/../../config/config.php';
require_role([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? 'save';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete') {
        $inUse = fetch_one('SELECT COUNT(*) AS c FROM persons WHERE location_id = ? AND is_deleted = 0', [$id]);
        if (($inUse['c'] ?? 0) > 0) {
            redirect(BASE_URL . 'modules/locations/index.php', 'Cannot delete: location is assigned to one or more persons.', 'danger');
        }
        execute('UPDATE locations SET is_deleted = 1 WHERE id = ?', [$id]);
        log_activity('delete_location', 'location', $id);
        redirect(BASE_URL . 'modules/locations/index.php', t('person_deleted'));
    }

    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        redirect(BASE_URL . 'modules/locations/index.php', 'Name is required.', 'danger');
    }

    if ($id > 0) {
        execute('UPDATE locations SET name = ? WHERE id = ?', [$name, $id]);
        log_activity('update_location', 'location', $id, $name);
    } else {
        execute('INSERT INTO locations (name) VALUES (?)', [$name]);
        log_activity('create_location', 'location', (int) db()->lastInsertId(), $name);
    }
    redirect(BASE_URL . 'modules/locations/index.php', t('person_saved'));
}

$page_title = t('locations');
$active_page = 'locations';
$locations = fetch_all(
    "SELECT l.*, (SELECT COUNT(*) FROM persons p WHERE p.location_id = l.id AND p.is_deleted = 0) AS person_count
     FROM locations l WHERE l.is_deleted = 0 ORDER BY l.name"
);
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-end mb-3 no-print">
    <button class="btn btn-sm btn-success" onclick="openLocModal()"><i class="fa-solid fa-plus"></i> <?= e(t('add_location')) ?></button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th><?= e(t('location_name')) ?></th><th><?= e(t('total_persons')) ?></th><th class="no-print"><?= e(t('actions')) ?></th></tr></thead>
            <tbody>
            <?php if (empty($locations)): ?>
                <tr><td colspan="3" class="text-center text-muted py-4"><?= e(t('no_records_found')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($locations as $loc): ?>
                <tr>
                    <td><?= e($loc['name']) ?></td>
                    <td><?= (int) $loc['person_count'] ?></td>
                    <td class="no-print">
                        <button class="btn btn-sm btn-outline-secondary" onclick='openLocModal(<?= json_encode($loc, JSON_UNESCAPED_UNICODE) ?>)'><i class="fa-solid fa-pen"></i></button>
                        <form method="post" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $loc['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="<?= e(t('confirm_delete')) ?>"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="locModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="lf_id">
      <div class="modal-header">
        <h5 class="modal-title" id="locModalTitle"><?= e(t('add_location')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label"><?= e(t('location_name')) ?></label>
        <input type="text" name="name" id="lf_name" class="form-control" required>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(t('cancel')) ?></button>
        <button type="submit" class="btn btn-success"><?= e(t('save')) ?></button>
      </div>
    </form>
  </div>
</div>

<?php
$extra_js = "
function openLocModal(loc) {
    document.getElementById('lf_id').value = loc ? loc.id : '';
    document.getElementById('lf_name').value = loc ? loc.name : '';
    document.getElementById('locModalTitle').textContent = loc ? " . json_encode(t('edit')) . " : " . json_encode(t('add_location')) . ";
    bootstrap.Modal.getOrCreateInstance(document.getElementById('locModal')).show();
}
";
include __DIR__ . '/../../includes/footer.php';
