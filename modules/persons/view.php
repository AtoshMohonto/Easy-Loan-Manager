<?php
require_once __DIR__ . '/../../config/config.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$person = fetch_one('SELECT p.*, l.name AS location_name FROM persons p LEFT JOIN locations l ON l.id = p.location_id WHERE p.id = ? AND p.is_deleted = 0', [$id]);

if (!$person) {
    redirect(BASE_URL . 'modules/persons/index.php', t('no_records_found'), 'danger');
}

$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

$where = ['person_id = ?', 'is_deleted = 0'];
$params = [$id];
if ($from_date !== '') { $where[] = 'transaction_date >= ?'; $params[] = $from_date; }
if ($to_date !== '') { $where[] = 'transaction_date <= ?'; $params[] = $to_date; }

$txns = fetch_all(
    'SELECT * FROM transactions WHERE ' . implode(' AND ', $where) . ' ORDER BY transaction_date, id',
    $params
);

$running = (float) $person['opening_balance'];
foreach ($txns as &$tx) {
    $running += balance_delta($person['person_type'], $tx['transaction_type'], (float) $tx['amount']);
    $tx['running_balance'] = $running;
}
unset($tx);

if (($_GET['export'] ?? '') === 'csv') {
    $rows = [];
    foreach ($txns as $tx) {
        $rows[] = [format_date($tx['transaction_date']), t(strtolower(str_replace(' ', '_', $tx['transaction_type']))), $tx['amount'], $tx['description'], $tx['running_balance']];
    }
    output_csv('statement_' . preg_replace('/\W+/', '_', $person['name']) . '.csv',
        [t('transaction_date'), t('transaction_type'), t('amount'), t('description'), t('balance')], $rows);
}

$page_title = $person['name'];
$active_page = 'persons';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2 no-print">
    <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-left"></i> <?= e(t('back')) ?></a>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>"><i class="fa-solid fa-file-csv"></i> <?= e(t('export_csv')) ?></a>
        <button class="btn btn-sm btn-outline-secondary btn-print"><i class="fa-solid fa-print"></i> <?= e(t('print')) ?></button>
        <?php if (is_manager_or_above()): ?>
        <a class="btn btn-sm btn-success" href="../transactions/index.php?add=1&person_id=<?= $id ?>"><i class="fa-solid fa-plus"></i> <?= e(t('add_transaction')) ?></a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h4 class="mb-1"><?= e($person['name']) ?> <span class="badge badge-type-<?= e($person['person_type']) ?>"><?= e(t(strtolower($person['person_type']))) ?></span></h4>
                        <p class="text-muted mb-0"><i class="fa-solid fa-phone me-1"></i><?= e($person['phone'] ?? '-') ?> &nbsp; <i class="fa-solid fa-envelope me-1"></i><?= e($person['email'] ?? '-') ?></p>
                    </div>
                    <span class="badge bg-<?= $person['status'] === 'Active' ? 'success' : 'secondary' ?>"><?= e(t(strtolower($person['status']))) ?></span>
                </div>
                <hr>
                <p class="mb-1"><strong><?= e(t('address')) ?>:</strong> <?= nl2br(e($person['address'] ?? '-')) ?></p>
                <p class="mb-1"><strong><?= e(t('location')) ?>:</strong> <?= e($person['location_name'] ?? '-') ?></p>
                <?php if ($person['notes']): ?><p class="mb-0"><strong><?= e(t('notes')) ?>:</strong> <?= nl2br(e($person['notes'])) ?></p><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="kpi-card text-center">
            <div class="kpi-icon bg-icon-green mx-auto"><i class="fa-solid fa-scale-balanced"></i></div>
            <div class="kpi-value <?= $person['balance'] > 0 ? 'balance-positive' : ($person['balance'] < 0 ? 'balance-negative' : 'balance-zero') ?>"><?= format_currency((float) $person['balance']) ?></div>
            <div class="kpi-label"><?= e(t('balance')) ?></div>
        </div>
    </div>
</div>

<div class="card no-print mb-3">
    <div class="card-body py-2">
        <form class="d-flex gap-2 flex-wrap align-items-end" method="get">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div>
                <label class="form-label small mb-0"><?= e(t('from_date')) ?></label>
                <input type="date" name="from_date" class="form-control form-control-sm" value="<?= e($from_date) ?>">
            </div>
            <div>
                <label class="form-label small mb-0"><?= e(t('to_date')) ?></label>
                <input type="date" name="to_date" class="form-control form-control-sm" value="<?= e($to_date) ?>">
            </div>
            <button class="btn btn-sm btn-outline-secondary" type="submit"><?= e(t('filter')) ?></button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><?= e(t('statement')) ?></div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th><?= e(t('transaction_date')) ?></th>
                    <th><?= e(t('transaction_type')) ?></th>
                    <th><?= e(t('description')) ?></th>
                    <th class="text-end"><?= e(t('amount')) ?></th>
                    <th class="text-end"><?= e(t('balance')) ?></th>
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
                    <td><?= e(t(strtolower(str_replace(' ', '_', $tx['transaction_type'])))) ?></td>
                    <td><?= e($tx['description'] ?? '') ?></td>
                    <td class="text-end"><?= format_currency((float) $tx['amount']) ?></td>
                    <td class="text-end"><?= format_currency((float) $tx['running_balance']) ?></td>
                    <td class="no-print">
                        <a class="btn btn-sm btn-outline-secondary" href="../transactions/receipt.php?id=<?= $tx['id'] ?>" target="_blank"><i class="fa-solid fa-receipt"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
