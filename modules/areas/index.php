<?php
require_once __DIR__ . '/../../config/config.php';
require_login();

$page_title = t('areas');
$active_page = 'areas';

$location_id = ($_GET['location_id'] ?? '') !== '' ? (int) $_GET['location_id'] : null;
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

if ($from_date === '') { $from_date = date('Y-m-01'); }
if ($to_date === '') { $to_date = date('Y-m-t'); }

$locations = active_locations();
$locWhere = $location_id ? 'AND location_id = ?' : '';
$locParam = $location_id ? [$location_id] : [];

// Person / loan status statistics for the current area selection.
$stats = fetch_one(
    "SELECT
        COUNT(CASE WHEN person_type='Borrower' THEN 1 END) AS borrower_count,
        COUNT(CASE WHEN person_type='Lender' THEN 1 END) AS lender_count,
        COUNT(CASE WHEN person_type='Borrower' AND status='Active' AND balance > 0 THEN 1 END) AS active_loans,
        COUNT(CASE WHEN person_type='Borrower' AND balance > 0 THEN 1 END) AS borrowers_with_dues,
        COALESCE(SUM(CASE WHEN person_type='Borrower' AND balance > 0 THEN balance ELSE 0 END), 0) AS total_dues,
        COALESCE(SUM(CASE WHEN person_type='Lender' AND balance > 0 THEN balance ELSE 0 END), 0) AS total_payable
     FROM persons
     WHERE is_deleted = 0 $locWhere",
    $locParam
);

// Collections / loans given / interest income for the selected period.
$period = fetch_one(
    "SELECT
        COALESCE(SUM(CASE WHEN t.transaction_type = 'Amount Received' AND p.person_type = 'Borrower' THEN t.amount ELSE 0 END), 0) AS collected,
        COALESCE(SUM(CASE WHEN t.transaction_type = 'Amount Given' AND p.person_type = 'Borrower' THEN t.amount ELSE 0 END), 0) AS given,
        COALESCE(SUM(CASE WHEN t.transaction_type = 'Interest' AND p.person_type = 'Borrower' THEN t.amount ELSE 0 END), 0) AS interest_income
     FROM transactions t
     JOIN persons p ON p.id = t.person_id AND p.is_deleted = 0
     WHERE t.is_deleted = 0 AND t.transaction_date BETWEEN ? AND ?
       " . ($location_id ? 'AND p.location_id = ?' : '') . "",
    $location_id ? [$from_date, $to_date, $location_id] : [$from_date, $to_date]
);

// Per-area overview table.
$areaRows = fetch_all(
    "SELECT l.id, l.name,
        COUNT(CASE WHEN p.person_type = 'Borrower' AND p.status = 'Active' AND p.balance > 0 THEN 1 END) AS active_loans,
        COALESCE(SUM(CASE WHEN p.person_type = 'Borrower' AND p.balance > 0 THEN p.balance ELSE 0 END), 0) AS dues,
        COALESCE(SUM(CASE WHEN p.person_type = 'Lender' AND p.balance > 0 THEN p.balance ELSE 0 END), 0) AS payable
     FROM locations l
     LEFT JOIN persons p ON p.location_id = l.id AND p.is_deleted = 0
     WHERE l.is_deleted = 0
     GROUP BY l.id, l.name
     ORDER BY l.name"
);

$areaPeriod = fetch_all(
    "SELECT p.location_id AS loc_id,
        COALESCE(SUM(CASE WHEN t.transaction_type = 'Amount Received' AND p.person_type = 'Borrower' THEN t.amount ELSE 0 END), 0) AS collected,
        COALESCE(SUM(CASE WHEN t.transaction_type = 'Amount Given' AND p.person_type = 'Borrower' THEN t.amount ELSE 0 END), 0) AS given
     FROM transactions t
     JOIN persons p ON p.id = t.person_id AND p.is_deleted = 0
     WHERE t.is_deleted = 0 AND t.transaction_date BETWEEN ? AND ?
     GROUP BY p.location_id",
    [$from_date, $to_date]
);
$areaPeriodMap = [];
foreach ($areaPeriod as $r) {
    $areaPeriodMap[(int) $r['loc_id']] = $r;
}

// Person-level detail rows (the "mark payments" target list).
$persons = fetch_all(
    "SELECT p.id, p.name, p.person_type, p.status, p.balance, p.phone, p.location_id, l.name AS location_name,
        (SELECT MAX(t.transaction_date) FROM transactions t
          WHERE t.person_id = p.id AND t.is_deleted = 0 AND t.transaction_type = 'Amount Received') AS last_collection,
        (SELECT MAX(t.transaction_date) FROM transactions t
          WHERE t.person_id = p.id AND t.is_deleted = 0 AND t.transaction_type = 'Amount Given') AS last_disbursement
     FROM persons p
     LEFT JOIN locations l ON l.id = p.location_id
     WHERE p.is_deleted = 0 " . ($location_id ? 'AND p.location_id = ?' : '') . "
     ORDER BY l.name, p.person_type, p.name",
    $locParam
);

// Return URL used after a recorded payment so the user lands back here.
$backParams = $_GET;
unset($backParams['export']);
$backUrl = BASE_URL . 'modules/areas/index.php' . ($backParams ? '?' . http_build_query($backParams) : '');

include __DIR__ . '/../../includes/header.php';
?>

<form class="mb-3 no-print" method="get">
    <div class="d-flex gap-2 align-items-end flex-wrap">
        <div>
            <label class="form-label small mb-0 text-muted"><?= e(t('area')) ?>:</label>
            <select name="location_id" class="form-select form-select-sm" style="width:220px" onchange="this.form.submit()">
                <option value=""><?= e(t('all_areas')) ?></option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?= $loc['id'] ?>" <?= $location_id === (int) $loc['id'] ? 'selected' : '' ?>><?= e($loc['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label small mb-0 text-muted"><?= e(t('from_date')) ?></label>
            <input type="date" name="from_date" class="form-control form-control-sm" style="width:160px" value="<?= e($from_date) ?>">
        </div>
        <div>
            <label class="form-label small mb-0 text-muted"><?= e(t('to_date')) ?></label>
            <input type="date" name="to_date" class="form-control form-control-sm" style="width:160px" value="<?= e($to_date) ?>">
        </div>
        <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="fa-solid fa-filter"></i> <?= e(t('filter')) ?></button>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-2">
        <div class="kpi-card">
            <div class="kpi-icon bg-icon-green"><i class="fa-solid fa-hand-holding-dollar"></i></div>
            <div class="kpi-value"><?= (int) $stats['active_loans'] ?></div>
            <div class="kpi-label"><?= e(t('active_loans')) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="kpi-card">
            <div class="kpi-icon bg-icon-amber"><i class="fa-solid fa-users"></i></div>
            <div class="kpi-value"><?= (int) $stats['borrowers_with_dues'] ?></div>
            <div class="kpi-label"><?= e(t('borrowers_with_dues')) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="kpi-card">
            <div class="kpi-icon bg-icon-red"><i class="fa-solid fa-sack-dollar"></i></div>
            <div class="kpi-value"><?= format_currency((float) $stats['total_dues']) ?></div>
            <div class="kpi-label"><?= e(t('total_dues')) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="kpi-card">
            <div class="kpi-icon bg-icon-purple"><i class="fa-solid fa-money-bill-wave"></i></div>
            <div class="kpi-value"><?= format_currency((float) $stats['total_payable']) ?></div>
            <div class="kpi-label"><?= e(t('total_payable')) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="kpi-card">
            <div class="kpi-icon bg-icon-blue"><i class="fa-solid fa-circle-dollar-to-slot"></i></div>
            <div class="kpi-value"><?= format_currency((float) $period['collected']) ?></div>
            <div class="kpi-label"><?= e(t('collected_in_period')) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="kpi-card">
            <div class="kpi-icon bg-icon-green"><i class="fa-solid fa-chart-line"></i></div>
            <div class="kpi-value"><?= format_currency((float) $period['interest_income']) ?></div>
            <div class="kpi-label"><?= e(t('interest_income')) ?></div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><?= e(t('areas_overview')) ?></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th><?= e(t('area')) ?></th>
                    <th class="text-center"><?= e(t('active_loans')) ?></th>
                    <th class="text-end"><?= e(t('area_dues')) ?></th>
                    <th class="text-end"><?= e(t('area_payable')) ?></th>
                    <th class="text-end"><?= e(t('collected_in_period')) ?></th>
                    <th class="text-end"><?= e(t('given_in_period')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($areaRows)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?= e(t('no_areas_defined')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($areaRows as $a): ?>
                <?php $ap = $areaPeriodMap[(int) $a['id']] ?? ['collected' => 0, 'given' => 0]; ?>
                <tr class="<?= $location_id === (int) $a['id'] ? 'table-active' : '' ?>">
                    <td>
                        <?php if ($location_id === (int) $a['id']): ?>
                            <strong><?= e($a['name']) ?></strong>
                        <?php else: ?>
                            <a href="?location_id=<?= (int) $a['id'] ?>&from_date=<?= e($from_date) ?>&to_date=<?= e($to_date) ?>"><?= e($a['name']) ?></a>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?= (int) $a['active_loans'] ?></td>
                    <td class="text-end"><?= format_currency((float) $a['dues']) ?></td>
                    <td class="text-end"><?= format_currency((float) $a['payable']) ?></td>
                    <td class="text-end"><?= format_currency((float) $ap['collected']) ?></td>
                    <td class="text-end"><?= format_currency((float) $ap['given']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><?= e(t('area_details')) ?></span>
        <?php if ($location_id): ?>
            <a href="?from_date=<?= e($from_date) ?>&to_date=<?= e($to_date) ?>" class="small"><?= e(t('all_areas')) ?></a>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th><?= e(t('name')) ?></th>
                    <?php if (!$location_id): ?><th><?= e(t('area')) ?></th><?php endif; ?>
                    <th><?= e(t('person_type')) ?></th>
                    <th class="text-end"><?= e(t('balance')) ?></th>
                    <th><?= e(t('status')) ?></th>
                    <th><?= e(t('last_collection')) ?></th>
                    <th><?= e(t('last_disbursement')) ?></th>
                    <th class="no-print"><?= e(t('actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($persons)): ?>
                <tr><td colspan="<?= $location_id ? 7 : 8 ?>" class="text-center text-muted py-4"><?= e(t('no_records_found')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($persons as $p): ?>
                <tr>
                    <td><a href="../persons/view.php?id=<?= (int) $p['id'] ?>"><?= e($p['name']) ?></a></td>
                    <?php if (!$location_id): ?><td><?= e($p['location_name'] ?? '-') ?></td><?php endif; ?>
                    <td><span class="badge badge-type-<?= e($p['person_type']) ?>"><?= e(t(strtolower($p['person_type']))) ?></span></td>
                    <td class="text-end <?= $p['balance'] > 0 ? 'balance-positive' : ($p['balance'] < 0 ? 'balance-negative' : 'balance-zero') ?>">
                        <?= format_currency((float) $p['balance']) ?>
                    </td>
                    <td><span class="badge bg-<?= $p['status'] === 'Active' ? 'success' : 'secondary' ?>"><?= e(t(strtolower($p['status']))) ?></span></td>
                    <td class="small"><?= e(format_date($p['last_collection'])) ?></td>
                    <td class="small"><?= e(format_date($p['last_disbursement'])) ?></td>
                    <td class="no-print">
                        <?php if (is_manager_or_above()): ?>
                        <button class="btn btn-sm btn-outline-success" title="<?= e(t('record_payment')) ?>"
                            onclick='openPaymentModal(<?= json_encode($p, JSON_UNESCAPED_UNICODE) ?>)'>
                            <i class="fa-solid fa-hand-holding-dollar"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Record Payment Modal (writes an Amount Received / Amount Given transaction) -->
<div class="modal fade" id="paymentModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="../transactions/save.php">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="pm_id" value="0">
      <input type="hidden" name="person_id" id="pm_person_id">
      <input type="hidden" name="transaction_type" id="pm_type">
      <input type="hidden" name="redirect" value="<?= e($backUrl) ?>">
      <input type="hidden" name="mark_payment" value="1">
      <div class="modal-header">
        <h5 class="modal-title" id="pm_title"><?= e(t('record_payment')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
            <label class="form-label"><?= e(t('name')) ?></label>
            <input type="text" id="pm_person_name" class="form-control" readonly>
        </div>
        <div class="row">
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('payment_amount')) ?></label>
                <input type="number" step="0.01" name="amount" id="pm_amount" class="form-control" required min="0.01">
            </div>
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('transaction_date')) ?></label>
                <input type="date" name="transaction_date" id="pm_date" class="form-control" required value="<?= date('Y-m-d') ?>">
            </div>
        </div>
        <div class="mb-2">
            <label class="form-label"><?= e(t('payment_method')) ?></label>
            <select name="payment_method" id="pm_method" class="form-select">
                <option value="Cash"><?= e(t('cash')) ?></option>
                <option value="Bank"><?= e(t('bank')) ?></option>
                <option value="Mobile Banking"><?= e(t('mobile_banking')) ?></option>
                <option value="Cheque"><?= e(t('cheque')) ?></option>
                <option value="Other"><?= e(t('other')) ?></option>
            </select>
        </div>
        <div class="mb-2">
            <label class="form-label"><?= e(t('payment_note')) ?></label>
            <textarea name="description" id="pm_desc" class="form-control" rows="2"></textarea>
        </div>
        <div class="form-text">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="pm_full_btn" onclick="fillFullPayment()">
                <i class="fa-solid fa-circle-check"></i> <?= e(t('mark_full_payment')) ?>
            </button>
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
var pmBalance = 0;
function openPaymentModal(p) {
    pmBalance = Math.abs(parseFloat(p.balance) || 0);
    document.getElementById('pm_person_id').value = p.id;
    document.getElementById('pm_person_name').value = p.name;
    document.getElementById('pm_type').value = (p.person_type === 'Lender') ? 'Amount Given' : 'Amount Received';
    document.getElementById('pm_amount').value = '';
    document.getElementById('pm_date').value = new Date().toISOString().slice(0,10);
    document.getElementById('pm_method').value = 'Cash';
    document.getElementById('pm_desc').value = '';
    document.getElementById('pm_title').textContent = (p.person_type === 'Lender' ? " . json_encode(t('payment')) . " : " . json_encode(t('record_payment')) . ");
    bootstrap.Modal.getOrCreateInstance(document.getElementById('paymentModal')).show();
}
function fillFullPayment() {
    document.getElementById('pm_amount').value = pmBalance.toFixed(2);
}
";
include __DIR__ . '/../../includes/footer.php';
