<?php
require_once __DIR__ . '/../../config/config.php';
require_login();

$page_title = t('finance');
$active_page = 'finance';

$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
if ($from_date === '') { $from_date = date('Y-m-01'); }
if ($to_date === '') { $to_date = date('Y-m-t'); }

// Income from lending = interest received from borrowers in the period.
$lendingIncome = (float) (fetch_one(
    "SELECT COALESCE(SUM(t.amount), 0) AS total
     FROM transactions t
     JOIN persons p ON p.id = t.person_id AND p.person_type = 'Borrower'
     WHERE t.transaction_type = 'Interest' AND t.is_deleted = 0
       AND t.transaction_date BETWEEN ? AND ?",
    [$from_date, $to_date]
)['total'] ?? 0);

// Institution loans with principal / total paid per loan.
$loans = fetch_all(
    "SELECT l.*,
        COALESCE((SELECT SUM(p.amount) FROM institution_payments p
                   WHERE p.institution_loan_id = l.id AND p.payment_type = 'Principal' AND p.is_deleted = 0), 0) AS paid_principal,
        COALESCE((SELECT SUM(p.amount) FROM institution_payments p
                   WHERE p.institution_loan_id = l.id AND p.is_deleted = 0), 0) AS paid_total
     FROM institution_loans l
     WHERE l.is_deleted = 0
     ORDER BY l.taken_date DESC, l.id DESC"
);

$totalOutstanding = 0.0;
$totalPaidAllTime = 0.0;
foreach ($loans as &$loan) {
    $loan['outstanding'] = max(0.0, (float) $loan['loan_amount'] - (float) $loan['paid_principal']);
    if ($loan['status'] === 'Active') {
        $totalOutstanding += $loan['outstanding'];
    }
    $totalPaidAllTime += (float) $loan['paid_total'];
}
unset($loan);

// Payments made to institutions within the selected period.
$periodPayments = fetch_all(
    "SELECT p.*, l.institution_name
     FROM institution_payments p
     JOIN institution_loans l ON l.id = p.institution_loan_id AND l.is_deleted = 0
     WHERE p.is_deleted = 0 AND p.payment_date BETWEEN ? AND ?
     ORDER BY p.payment_date DESC, p.id DESC",
    [$from_date, $to_date]
);

$paidPrincipalPeriod = 0.0;
$paidInterestPeriod = 0.0;
foreach ($periodPayments as $p) {
    if ($p['payment_type'] === 'Principal') {
        $paidPrincipalPeriod += (float) $p['amount'];
    } else {
        $paidInterestPeriod += (float) $p['amount'];
    }
}
$paidTotalPeriod = $paidPrincipalPeriod + $paidInterestPeriod;
$netPeriod = $lendingIncome - $paidTotalPeriod;

// Recent payments (for the log table, latest 50 overall).
$recentPayments = fetch_all(
    "SELECT p.*, l.institution_name
     FROM institution_payments p
     JOIN institution_loans l ON l.id = p.institution_loan_id AND l.is_deleted = 0
     WHERE p.is_deleted = 0
     ORDER BY p.payment_date DESC, p.id DESC
     LIMIT 50"
);

$backParams = $_GET;
unset($backParams['export']);
$backUrl = BASE_URL . 'modules/finance/index.php' . ($backParams ? '?' . http_build_query($backParams) : '');

include __DIR__ . '/../../includes/header.php';
?>

<form class="mb-3 no-print" method="get">
    <div class="d-flex gap-2 align-items-end flex-wrap">
        <div>
            <label class="form-label small mb-0 text-muted"><?= e(t('from_date')) ?></label>
            <input type="date" name="from_date" class="form-control form-control-sm" style="width:170px" value="<?= e($from_date) ?>">
        </div>
        <div>
            <label class="form-label small mb-0 text-muted"><?= e(t('to_date')) ?></label>
            <input type="date" name="to_date" class="form-control form-control-sm" style="width:170px" value="<?= e($to_date) ?>">
        </div>
        <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="fa-solid fa-filter"></i> <?= e(t('filter')) ?></button>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-2">
        <div class="kpi-card">
            <div class="kpi-icon bg-icon-red"><i class="fa-solid fa-building-columns"></i></div>
            <div class="kpi-value"><?= format_currency($totalOutstanding) ?></div>
            <div class="kpi-label"><?= e(t('total_liabilities')) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="kpi-card">
            <div class="kpi-icon bg-icon-amber"><i class="fa-solid fa-sack-dollar"></i></div>
            <div class="kpi-value"><?= format_currency($totalPaidAllTime) ?></div>
            <div class="kpi-label"><?= e(t('total_paid_institutions')) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="kpi-card">
            <div class="kpi-icon bg-icon-green"><i class="fa-solid fa-chart-line"></i></div>
            <div class="kpi-value"><?= format_currency($lendingIncome) ?></div>
            <div class="kpi-label"><?= e(t('income_period')) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="kpi-card">
            <div class="kpi-icon bg-icon-blue"><i class="fa-solid fa-money-bill-transfer"></i></div>
            <div class="kpi-value"><?= format_currency($paidTotalPeriod) ?></div>
            <div class="kpi-label"><?= e(t('paid_period')) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="kpi-card">
            <div class="kpi-icon bg-icon-purple"><i class="fa-solid fa-scale-balanced"></i></div>
            <div class="kpi-value <?= $netPeriod >= 0 ? 'balance-positive' : 'balance-negative' ?>"><?= format_currency($netPeriod) ?></div>
            <div class="kpi-label"><?= e(t('net_period')) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2 d-flex align-items-stretch">
        <div class="kpi-card w-100 d-flex flex-column justify-content-center">
            <div class="text-muted small mb-1"><?= e(t('institution_loans')) ?></div>
            <div class="kpi-value"><?= count($loans) ?></div>
            <?php if (is_manager_or_above()): ?>
            <div class="mt-1">
                <button class="btn btn-sm btn-success" onclick="openLoanModal()"><i class="fa-solid fa-plus"></i> <?= e(t('add_institution_loan')) ?></button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><?= e(t('institution_loans')) ?></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th><?= e(t('institution_name')) ?></th>
                            <th class="text-end"><?= e(t('loan_amount')) ?></th>
                            <th class="text-end"><?= e(t('paid_so_far')) ?></th>
                            <th class="text-end"><?= e(t('outstanding')) ?></th>
                            <th><?= e(t('status')) ?></th>
                            <th class="no-print"><?= e(t('actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($loans)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4"><?= e(t('no_loans_found')) ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($loans as $loan): ?>
                        <tr>
                            <td>
                                <a href="#" onclick="openPaymentModal(<?= json_encode($loan, JSON_UNESCAPED_UNICODE) ?>); return false;"><?= e($loan['institution_name']) ?></a>
                                <div class="text-muted small"><?= e(t('taken_date')) ?>: <?= e(format_date($loan['taken_date'])) ?></div>
                            </td>
                            <td class="text-end"><?= format_currency((float) $loan['loan_amount']) ?></td>
                            <td class="text-end"><?= format_currency((float) $loan['paid_principal']) ?></td>
                            <td class="text-end <?= $loan['outstanding'] > 0 ? 'balance-negative' : 'balance-zero' ?>"><?= format_currency($loan['outstanding']) ?></td>
                            <td><span class="badge bg-<?= $loan['status'] === 'Active' ? 'success' : 'secondary' ?>"><?= e(t(strtolower($loan['status']))) ?></span></td>
                            <td class="no-print">
                                <?php if (is_manager_or_above()): ?>
                                <button class="btn btn-sm btn-outline-success" title="<?= e(t('record_payment')) ?>" onclick='openPaymentModal(<?= json_encode($loan, JSON_UNESCAPED_UNICODE) ?>)'><i class="fa-solid fa-hand-holding-dollar"></i></button>
                                <button class="btn btn-sm btn-outline-secondary" title="<?= e(t('edit')) ?>" onclick='openLoanModal(<?= json_encode($loan, JSON_UNESCAPED_UNICODE) ?>)'><i class="fa-solid fa-pen"></i></button>
                                <?php endif; ?>
                                <?php if (is_admin()): ?>
                                <form method="post" action="save.php" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_loan">
                                    <input type="hidden" name="id" value="<?= (int) $loan['id'] ?>">
                                    <input type="hidden" name="redirect" value="<?= e($backUrl) ?>">
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
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><?= e(t('financial_summary')) ?> — <?= e(format_date($from_date, 'M Y')) ?></div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tbody>
                        <tr>
                            <td><?= e(t('lending_income')) ?></td>
                            <td class="text-end fw-semibold balance-positive"><?= format_currency($lendingIncome) ?></td>
                        </tr>
                        <tr>
                            <td class="ps-4 text-muted small">— <?= e(t('principal')) ?></td>
                            <td class="text-end text-muted small"><?= format_currency($paidPrincipalPeriod) ?></td>
                        </tr>
                        <tr>
                            <td class="ps-4 text-muted small">— <?= e(t('interest_paid')) ?></td>
                            <td class="text-end text-muted small"><?= format_currency($paidInterestPeriod) ?></td>
                        </tr>
                        <tr>
                            <td><?= e(t('institutional_payments')) ?></td>
                            <td class="text-end fw-semibold balance-negative"><?= format_currency($paidTotalPeriod) ?></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-top fw-bold">
                            <td><?= e(t('net_after_institutions')) ?></td>
                            <td class="text-end <?= $netPeriod >= 0 ? 'balance-positive' : 'balance-negative' ?>"><?= format_currency($netPeriod) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><?= e(t('institutional_payments')) ?></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th><?= e(t('transaction_date')) ?></th>
                    <th><?= e(t('institution_name')) ?></th>
                    <th><?= e(t('payment_type')) ?></th>
                    <th><?= e(t('payment_method')) ?></th>
                    <th class="text-end"><?= e(t('amount')) ?></th>
                    <th class="no-print"><?= e(t('actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($recentPayments)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?= e(t('no_records_found')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($recentPayments as $p): ?>
                <tr>
                    <td><?= e(format_date($p['payment_date'])) ?></td>
                    <td><?= e($p['institution_name']) ?></td>
                    <td><span class="badge bg-<?= $p['payment_type'] === 'Principal' ? 'primary' : 'warning' ?>"><?= e(t(strtolower($p['payment_type']))) ?></span></td>
                    <td><?= e(t(strtolower(str_replace(' ', '_', $p['payment_method'])))) ?></td>
                    <td class="text-end"><?= format_currency((float) $p['amount']) ?></td>
                    <td class="no-print">
                        <?php if (is_admin()): ?>
                        <form method="post" action="save.php" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_payment">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <input type="hidden" name="redirect" value="<?= e($backUrl) ?>">
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

<!-- Add / Edit Institution Loan Modal -->
<div class="modal fade" id="loanModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="save.php">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="lf_id">
      <input type="hidden" name="redirect" value="<?= e($backUrl) ?>">
      <div class="modal-header">
        <h5 class="modal-title" id="loanModalTitle"><?= e(t('add_institution_loan')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
            <label class="form-label"><?= e(t('institution_name')) ?></label>
            <input type="text" name="institution_name" id="lf_name" class="form-control" required>
        </div>
        <div class="row">
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('loan_amount')) ?></label>
                <input type="number" step="0.01" name="loan_amount" id="lf_amount" class="form-control" required min="0.01">
            </div>
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('interest_rate')) ?></label>
                <input type="number" step="0.01" name="interest_rate" id="lf_rate" class="form-control" placeholder="e.g. 10">
            </div>
        </div>
        <div class="row">
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('taken_date')) ?></label>
                <input type="date" name="taken_date" id="lf_taken" class="form-control" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('due_date')) ?></label>
                <input type="date" name="due_date" id="lf_due" class="form-control">
            </div>
        </div>
        <div class="row">
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('status')) ?></label>
                <select name="status" id="lf_status" class="form-select">
                    <option value="Active"><?= e(t('active')) ?></option>
                    <option value="Closed"><?= e(t('closed')) ?></option>
                </select>
            </div>
        </div>
        <div class="mb-2">
            <label class="form-label"><?= e(t('notes')) ?></label>
            <textarea name="notes" id="lf_notes" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(t('cancel')) ?></button>
        <button type="submit" class="btn btn-success"><?= e(t('save')) ?></button>
      </div>
    </form>
  </div>
</div>

<!-- Record Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="save.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_payment">
      <input type="hidden" name="loan_id" id="pf_loan_id">
      <input type="hidden" name="redirect" value="<?= e($backUrl) ?>">
      <div class="modal-header">
        <h5 class="modal-title" id="paymentModalTitle"><?= e(t('record_payment')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
            <label class="form-label"><?= e(t('institution_name')) ?></label>
            <input type="text" id="pf_loan_name" class="form-control" readonly>
        </div>
        <div class="row">
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('payment_type')) ?></label>
                <select name="payment_type" id="pf_type" class="form-select">
                    <option value="Principal"><?= e(t('principal')) ?></option>
                    <option value="Interest"><?= e(t('interest')) ?></option>
                </select>
            </div>
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('amount')) ?></label>
                <input type="number" step="0.01" name="amount" id="pf_amount" class="form-control" required min="0.01">
            </div>
        </div>
        <div class="row">
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('transaction_date')) ?></label>
                <input type="date" name="payment_date" id="pf_date" class="form-control" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('payment_method')) ?></label>
                <select name="payment_method" id="pf_method" class="form-select">
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
            <textarea name="description" id="pf_desc" class="form-control" rows="2"></textarea>
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
function openLoanModal(l) {
    document.getElementById('loanModal').querySelector('form').reset();
    document.getElementById('loanModalTitle').textContent = l ? " . json_encode(t('edit_institution_loan')) . " : " . json_encode(t('add_institution_loan')) . ";
    document.getElementById('lf_id').value = l ? l.id : '';
    document.getElementById('lf_name').value = l ? l.institution_name : '';
    document.getElementById('lf_amount').value = l ? l.loan_amount : '';
    document.getElementById('lf_rate').value = l && l.interest_rate != null ? l.interest_rate : '';
    document.getElementById('lf_taken').value = l ? l.taken_date : new Date().toISOString().slice(0,10);
    document.getElementById('lf_due').value = l ? (l.due_date || '') : '';
    document.getElementById('lf_status').value = l ? l.status : 'Active';
    document.getElementById('lf_notes').value = l ? (l.notes || '') : '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('loanModal')).show();
}
function openPaymentModal(l) {
    document.getElementById('pf_loan_id').value = l.id;
    document.getElementById('pf_loan_name').value = l.institution_name;
    document.getElementById('pf_type').value = 'Principal';
    document.getElementById('pf_amount').value = '';
    document.getElementById('pf_date').value = new Date().toISOString().slice(0,10);
    document.getElementById('pf_method').value = 'Cash';
    document.getElementById('pf_desc').value = '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('paymentModal')).show();
}
";
include __DIR__ . '/../../includes/footer.php';
