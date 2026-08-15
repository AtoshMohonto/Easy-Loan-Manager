<?php
require_once __DIR__ . '/../../config/config.php';
require_login();

$page_title = t('persons');
$active_page = 'persons';

$type_filter = $_GET['type'] ?? '';
$location_filter = $_GET['location_id'] ?? '';
$search = trim($_GET['q'] ?? '');

$where = ['p.is_deleted = 0'];
$params = [];

if (in_array($type_filter, ['Lender', 'Borrower'], true)) {
    $where[] = 'p.person_type = ?';
    $params[] = $type_filter;
}
if ($location_filter !== '' && ctype_digit((string) $location_filter)) {
    $where[] = 'p.location_id = ?';
    $params[] = (int) $location_filter;
}
if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.phone LIKE ? OR p.email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql = 'SELECT p.*, l.name AS location_name
        FROM persons p
        LEFT JOIN locations l ON l.id = p.location_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY p.name';

$persons = fetch_all($sql, $params);

if (($_GET['export'] ?? '') === 'csv') {
    $rows = [];
    foreach ($persons as $p) {
        $rows[] = [$p['name'], $p['person_type'], $p['phone'], $p['address'], $p['location_name'], $p['balance']];
    }
    output_csv('persons.csv', [t('name'), t('person_type'), t('phone'), t('address'), t('location'), t('balance')], $rows);
}

$locations = active_locations();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
    <form class="d-flex gap-2 flex-wrap" method="get">
        <input type="text" name="q" class="form-control form-control-sm" style="width:200px" placeholder="<?= e(t('search')) ?>" value="<?= e($search) ?>">
        <select name="type" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
            <option value=""><?= e(t('person_type')) ?></option>
            <option value="Borrower" <?= $type_filter === 'Borrower' ? 'selected' : '' ?>><?= e(t('borrower')) ?></option>
            <option value="Lender" <?= $type_filter === 'Lender' ? 'selected' : '' ?>><?= e(t('lender')) ?></option>
        </select>
        <select name="location_id" class="form-select form-select-sm" style="width:170px" onchange="this.form.submit()">
            <option value=""><?= e(t('all_locations')) ?></option>
            <?php foreach ($locations as $loc): ?>
                <option value="<?= $loc['id'] ?>" <?= (string) $location_filter === (string) $loc['id'] ? 'selected' : '' ?>><?= e($loc['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="fa-solid fa-filter"></i> <?= e(t('filter')) ?></button>
    </form>

    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>"><i class="fa-solid fa-file-csv"></i> <?= e(t('export_csv')) ?></a>
        <?php if (is_manager_or_above()): ?>
        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#personModal" onclick="openPersonModal()">
            <i class="fa-solid fa-plus"></i> <?= e(t('add_person')) ?>
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="personsTable">
            <thead>
                <tr>
                    <th><?= e(t('name')) ?></th>
                    <th><?= e(t('person_type')) ?></th>
                    <th><?= e(t('phone')) ?></th>
                    <th><?= e(t('location')) ?></th>
                    <th class="text-end"><?= e(t('balance')) ?></th>
                    <th><?= e(t('status')) ?></th>
                    <th class="no-print"><?= e(t('actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($persons)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4"><?= e(t('no_records_found')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($persons as $p): ?>
                <tr>
                    <td><a href="view.php?id=<?= $p['id'] ?>"><?= e($p['name']) ?></a></td>
                    <td><span class="badge badge-type-<?= e($p['person_type']) ?>"><?= e(t(strtolower($p['person_type']))) ?></span></td>
                    <td><?= e($p['phone'] ?? '') ?></td>
                    <td><?= e($p['location_name'] ?? '') ?></td>
                    <td class="text-end <?= $p['balance'] > 0 ? 'balance-positive' : ($p['balance'] < 0 ? 'balance-negative' : 'balance-zero') ?>">
                        <?= format_currency((float) $p['balance']) ?>
                    </td>
                    <td><span class="badge bg-<?= $p['status'] === 'Active' ? 'success' : 'secondary' ?>"><?= e(t(strtolower($p['status']))) ?></span></td>
                    <td class="no-print">
                        <a class="btn btn-sm btn-outline-secondary" href="view.php?id=<?= $p['id'] ?>" title="<?= e(t('view_profile')) ?>"><i class="fa-solid fa-eye"></i></a>
                        <?php if (is_manager_or_above()): ?>
                        <button class="btn btn-sm btn-outline-secondary" title="<?= e(t('edit')) ?>"
                            onclick='openPersonModal(<?= json_encode($p, JSON_UNESCAPED_UNICODE) ?>)'>
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <?php endif; ?>
                        <?php if (is_admin()): ?>
                        <form method="post" action="save.php" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="<?= e(t('confirm_delete')) ?>" title="<?= e(t('delete')) ?>"><i class="fa-solid fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add / Edit Person Modal -->
<div class="modal fade" id="personModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="save.php">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="pf_id">
      <div class="modal-header">
        <h5 class="modal-title" id="personModalTitle"><?= e(t('add_person')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
            <label class="form-label"><?= e(t('name')) ?></label>
            <input type="text" name="name" id="pf_name" class="form-control" required>
        </div>
        <div class="row">
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('person_type')) ?></label>
                <select name="person_type" id="pf_type" class="form-select" required>
                    <option value="Borrower"><?= e(t('borrower')) ?></option>
                    <option value="Lender"><?= e(t('lender')) ?></option>
                </select>
            </div>
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('location')) ?></label>
                <select name="location_id" id="pf_location" class="form-select">
                    <option value=""></option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= $loc['id'] ?>"><?= e($loc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('phone')) ?></label>
                <input type="text" name="phone" id="pf_phone" class="form-control">
            </div>
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('email')) ?></label>
                <input type="email" name="email" id="pf_email" class="form-control">
            </div>
        </div>
        <div class="mb-2">
            <label class="form-label"><?= e(t('address')) ?></label>
            <textarea name="address" id="pf_address" class="form-control" rows="2"></textarea>
        </div>
        <div class="row">
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('opening_balance')) ?></label>
                <input type="number" step="0.01" name="opening_balance" id="pf_opening" class="form-control" value="0">
            </div>
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('status')) ?></label>
                <select name="status" id="pf_status" class="form-select">
                    <option value="Active"><?= e(t('active')) ?></option>
                    <option value="Inactive"><?= e(t('inactive')) ?></option>
                </select>
            </div>
        </div>
        <div class="mb-2">
            <label class="form-label"><?= e(t('notes')) ?></label>
            <textarea name="notes" id="pf_notes" class="form-control" rows="2"></textarea>
        </div>
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
function openPersonModal(p) {
    document.getElementById('personModal').querySelector('form').reset();
    document.getElementById('personModalTitle').textContent = p ? " . json_encode(t('edit_person')) . " : " . json_encode(t('add_person')) . ";
    document.getElementById('pf_id').value = p ? p.id : '';
    document.getElementById('pf_name').value = p ? p.name : '';
    document.getElementById('pf_type').value = p ? p.person_type : 'Borrower';
    document.getElementById('pf_location').value = p && p.location_id ? p.location_id : '';
    document.getElementById('pf_phone').value = p ? (p.phone || '') : '';
    document.getElementById('pf_email').value = p ? (p.email || '') : '';
    document.getElementById('pf_address').value = p ? (p.address || '') : '';
    document.getElementById('pf_opening').value = p ? p.opening_balance : 0;
    document.getElementById('pf_status').value = p ? p.status : 'Active';
    document.getElementById('pf_notes').value = p ? (p.notes || '') : '';
    var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('personModal'));
    modal.show();
}
";
include __DIR__ . '/../../includes/footer.php';
