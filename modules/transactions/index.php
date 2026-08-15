<?php
require_once __DIR__ . '/../../config/config.php';
require_login();

$page_title = t('transactions');
$active_page = 'transactions';

$person_filter = $_GET['person_id'] ?? '';
$type_filter = $_GET['type'] ?? '';
$location_filter = $_GET['location_id'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

$where = ['t.is_deleted = 0'];
$params = [];

if ($person_filter !== '' && ctype_digit((string) $person_filter)) {
    $where[] = 't.person_id = ?';
    $params[] = (int) $person_filter;
}
if ($type_filter !== '') {
    $where[] = 't.transaction_type = ?';
    $params[] = $type_filter;
}
if ($location_filter !== '' && ctype_digit((string) $location_filter)) {
    $where[] = 'p.location_id = ?';
    $params[] = (int) $location_filter;
}
if ($from_date !== '') { $where[] = 't.transaction_date >= ?'; $params[] = $from_date; }
if ($to_date !== '') { $where[] = 't.transaction_date <= ?'; $params[] = $to_date; }

$sql = 'SELECT t.*, p.name AS person_name, p.person_type, l.name AS location_name
        FROM transactions t
        LEFT JOIN persons p ON p.id = t.person_id
        LEFT JOIN locations l ON l.id = p.location_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY t.transaction_date DESC, t.id DESC
        LIMIT 500';

$txns = fetch_all($sql, $params);

if (($_GET['export'] ?? '') === 'csv') {
    $rows = [];
    foreach ($txns as $tx) {
        $rows[] = [
            'TXN-' . str_pad((string) $tx['id'], 6, '0', STR_PAD_LEFT),
            format_date($tx['transaction_date']),
            $tx['person_name'] ?? t('general_expense'),
            $tx['transaction_type'],
            $tx['amount'],
            $tx['payment_method'],
            $tx['description'],
        ];
    }
    output_csv('transactions.csv', [t('transaction_type'), t('transaction_date'), t('name'), t('transaction_type'), t('amount'), t('payment_method'), t('description')], $rows);
}

$persons = fetch_all("SELECT id, name, person_type FROM persons WHERE is_deleted = 0 AND status = 'Active' ORDER BY name");
$locations = active_locations();
$preselect_person = (int) ($_GET['person_id'] ?? 0);
$auto_open = isset($_GET['add']);

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
    <form class="d-flex gap-2 flex-wrap" method="get">
        <select name="person_id" class="form-select form-select-sm" style="width:180px" onchange="this.form.submit()">
            <option value=""><?= e(t('select_person')) ?></option>
            <?php foreach ($persons as $p): ?>
                <option value="<?= $p['id'] ?>" <?= (string) $person_filter === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="type" class="form-select form-select-sm" style="width:160px" onchange="this.form.submit()">
            <option value=""><?= e(t('transaction_type')) ?></option>
            <?php foreach (['Amount Given','Amount Received','Interest','Expense','Adjustment'] as $tt): ?>
                <option value="<?= $tt ?>" <?= $type_filter === $tt ? 'selected' : '' ?>><?= e(t(strtolower(str_replace(' ', '_', $tt)))) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="location_id" class="form-select form-select-sm" style="width:160px" onchange="this.form.submit()">
            <option value=""><?= e(t('all_locations')) ?></option>
            <?php foreach ($locations as $loc): ?>
                <option value="<?= $loc['id'] ?>" <?= (string) $location_filter === (string) $loc['id'] ? 'selected' : '' ?>><?= e($loc['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="from_date" class="form-control form-control-sm" style="width:150px" value="<?= e($from_date) ?>">
        <input type="date" name="to_date" class="form-control form-control-sm" style="width:150px" value="<?= e($to_date) ?>">
        <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="fa-solid fa-filter"></i> <?= e(t('filter')) ?></button>
    </form>

    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>"><i class="fa-solid fa-file-csv"></i> <?= e(t('export_csv')) ?></a>
        <?php if (is_manager_or_above()): ?>
        <button class="btn btn-sm btn-success" onclick="openTxnModal()">
            <i class="fa-solid fa-plus"></i> <?= e(t('add_transaction')) ?>
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th><?= e(t('transaction_date')) ?></th>
                    <th><?= e(t('name')) ?></th>
                    <th><?= e(t('transaction_type')) ?></th>
                    <th class="text-end"><?= e(t('amount')) ?></th>
                    <th><?= e(t('payment_method')) ?></th>
                    <th class="no-print"><?= e(t('actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($txns)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?= e(t('no_records_found')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($txns as $tx): ?>
                <tr>
                    <td><?= e(format_date($tx['transaction_date'])) ?></td>
                    <td><?= $tx['person_id'] ? '<a href="../persons/view.php?id=' . $tx['person_id'] . '">' . e($tx['person_name']) . '</a>' : e(t('general_expense')) ?></td>
                    <td><?= e(t(strtolower(str_replace(' ', '_', $tx['transaction_type'])))) ?></td>
                    <td class="text-end"><?= format_currency((float) $tx['amount']) ?></td>
                    <td><?= e(t(strtolower(str_replace(' ', '_', $tx['payment_method'])))) ?></td>
                    <td class="no-print">
                        <a class="btn btn-sm btn-outline-secondary" href="receipt.php?id=<?= $tx['id'] ?>" target="_blank" title="<?= e(t('receipt')) ?>"><i class="fa-solid fa-receipt"></i></a>
                        <?php if (is_manager_or_above()): ?>
                        <button class="btn btn-sm btn-outline-secondary" title="<?= e(t('edit')) ?>" onclick='openTxnModal(<?= json_encode($tx, JSON_UNESCAPED_UNICODE) ?>)'><i class="fa-solid fa-pen"></i></button>
                        <?php endif; ?>
                        <?php if (is_admin()): ?>
                        <form method="post" action="save.php" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $tx['id'] ?>">
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

<!-- Add / Edit Transaction Modal -->
<div class="modal fade" id="txnModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="save.php">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="tf_id">
      <div class="modal-header">
        <h5 class="modal-title" id="txnModalTitle"><?= e(t('add_transaction')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
            <label class="form-label"><?= e(t('select_person')) ?></label>
            <select name="person_id" id="tf_person" class="form-select">
                <option value=""><?= e(t('general_expense')) ?></option>
                <?php foreach ($persons as $p): ?>
                    <option value="<?= $p['id'] ?>" data-type="<?= e($p['person_type']) ?>"><?= e($p['name']) ?> (<?= e(t(strtolower($p['person_type']))) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="row">
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('transaction_type')) ?></label>
                <select name="transaction_type" id="tf_type" class="form-select" required>
                    <option value="Amount Given"><?= e(t('amount_given')) ?></option>
                    <option value="Amount Received"><?= e(t('amount_received')) ?></option>
                    <option value="Interest"><?= e(t('interest')) ?></option>
                    <option value="Adjustment"><?= e(t('adjustment')) ?></option>
                    <option value="Expense"><?= e(t('expense')) ?></option>
                </select>
            </div>
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('amount')) ?></label>
                <input type="number" step="0.01" name="amount" id="tf_amount" class="form-control" required min="0.01">
            </div>
        </div>
        <div class="alert alert-light border small py-2" id="tf_words"></div>
        <div class="row">
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('transaction_date')) ?></label>
                <input type="date" name="transaction_date" id="tf_date" class="form-control" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('payment_method')) ?></label>
                <select name="payment_method" id="tf_method" class="form-select">
                    <option value="Cash"><?= e(t('cash')) ?></option>
                    <option value="Bank"><?= e(t('bank')) ?></option>
                    <option value="Mobile Banking"><?= e(t('mobile_banking')) ?></option>
                    <option value="Cheque"><?= e(t('cheque')) ?></option>
                    <option value="Other"><?= e(t('other')) ?></option>
                </select>
            </div>
        </div>
        <div class="mb-2">
            <label class="form-label"><?= e(t('description')) ?></label>
            <textarea name="description" id="tf_desc" class="form-control" rows="2"></textarea>
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
function openTxnModal(tx) {
    document.getElementById('txnModal').querySelector('form').reset();
    document.getElementById('txnModalTitle').textContent = tx ? " . json_encode(t('edit_transaction')) . " : " . json_encode(t('add_transaction')) . ";
    document.getElementById('tf_id').value = tx ? tx.id : '';
    document.getElementById('tf_person').value = tx ? (tx.person_id || '') : " . json_encode((string) $preselect_person) . ";
    document.getElementById('tf_type').value = tx ? tx.transaction_type : 'Amount Given';
    document.getElementById('tf_amount').value = tx ? tx.amount : '';
    document.getElementById('tf_date').value = tx ? tx.transaction_date : new Date().toISOString().slice(0,10);
    document.getElementById('tf_method').value = tx ? tx.payment_method : 'Cash';
    document.getElementById('tf_desc').value = tx ? (tx.description || '') : '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('txnModal')).show();
}
" . ($auto_open ? "document.addEventListener('DOMContentLoaded', function(){ openTxnModal(); });" : '') . "
";
include __DIR__ . '/../../includes/footer.php';
