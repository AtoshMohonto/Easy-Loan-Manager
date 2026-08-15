<?php
require_once __DIR__ . '/../../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role([ROLE_ADMIN, ROLE_MANAGER]);
    require_csrf();
    $year_month = $_POST['year_month'] ?? '';
    $note = trim($_POST['note'] ?? '');
    $target = $_POST['target_profit'] !== '' ? (float) $_POST['target_profit'] : null;

    if (preg_match('/^\d{4}-\d{2}$/', $year_month)) {
        execute(
            'INSERT INTO monthly_profit_notes (`year_month`, note, target_profit, created_by) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE note = VALUES(note), target_profit = VALUES(target_profit)',
            [$year_month, $note, $target, current_user_id()]
        );
        log_activity('update_monthly_note', 'monthly_profit_notes', 0, $year_month);
    }
    redirect(BASE_URL . 'modules/reports/monthly_profit.php?year=' . substr($year_month, 0, 4));
}

$page_title = t('monthly_profit_management');
$active_page = 'reports';

$year = (int) ($_GET['year'] ?? date('Y'));
$location_id = ($_GET['location_id'] ?? '') !== '' ? (int) $_GET['location_id'] : null;
$locations = active_locations();

$rows = [];
for ($m = 1; $m <= 12; $m++) {
    $ym = sprintf('%04d-%02d', $year, $m);
    $start = $ym . '-01';
    $end = date('Y-m-t', strtotime($start));
    $p = calculate_profit($start, $end, $location_id);
    $noteRow = fetch_one('SELECT * FROM monthly_profit_notes WHERE `year_month` = ?', [$ym]);
    $rows[] = [
        'year_month' => $ym,
        'label' => date('M Y', strtotime($start)),
        'income' => $p['income'],
        'expense_interest' => $p['expense_interest'],
        'other_expense' => $p['other_expense'],
        'net' => $p['net'],
        'note' => $noteRow['note'] ?? '',
        'target_profit' => $noteRow['target_profit'] ?? null,
    ];
}

if (($_GET['export'] ?? '') === 'csv') {
    $csvRows = [];
    foreach ($rows as $r) {
        $csvRows[] = [$r['label'], $r['income'], $r['expense_interest'], $r['other_expense'], $r['net']];
    }
    output_csv('monthly_profit_' . $year . '.csv', [t('month'), t('income_interest'), t('expense_interest'), t('other_expense'), t('net_profit')], $csvRows);
}

$yearTotal = array_sum(array_column($rows, 'net'));
$use_chartjs = true;
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
    <form class="d-flex gap-2" method="get">
        <select name="year" class="form-select form-select-sm" style="width:120px" onchange="this.form.submit()">
            <?php for ($y = (int) date('Y') + 1; $y >= (int) date('Y') - 5; $y--): ?>
                <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
        <select name="location_id" class="form-select form-select-sm" style="width:170px" onchange="this.form.submit()">
            <option value=""><?= e(t('all_locations')) ?></option>
            <?php foreach ($locations as $loc): ?>
                <option value="<?= $loc['id'] ?>" <?= $location_id === (int) $loc['id'] ? 'selected' : '' ?>><?= e($loc['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <a class="btn btn-sm btn-outline-secondary" href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>"><i class="fa-solid fa-file-csv"></i> <?= e(t('export_csv')) ?></a>
</div>

<div class="card mb-3">
    <div class="card-header"><?= e(t('year')) ?> <?= $year ?> — <?= e(t('net_profit')) ?>: <strong><?= format_currency($yearTotal) ?></strong></div>
    <div class="card-body">
        <canvas id="monthlyProfitChart" height="200"></canvas>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th><?= e(t('month')) ?></th>
                    <th class="text-end"><?= e(t('income_interest')) ?></th>
                    <th class="text-end"><?= e(t('expense_interest')) ?></th>
                    <th class="text-end"><?= e(t('other_expense')) ?></th>
                    <th class="text-end"><?= e(t('net_profit')) ?></th>
                    <th><?= e(t('note')) ?></th>
                    <?php if (is_manager_or_above()): ?><th class="no-print"><?= e(t('actions')) ?></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['label']) ?></td>
                    <td class="text-end"><?= format_currency($r['income']) ?></td>
                    <td class="text-end"><?= format_currency($r['expense_interest']) ?></td>
                    <td class="text-end"><?= format_currency($r['other_expense']) ?></td>
                    <td class="text-end <?= $r['net'] >= 0 ? 'balance-positive' : 'balance-negative' ?>"><?= format_currency($r['net']) ?></td>
                    <td class="small text-muted"><?= e($r['note']) ?></td>
                    <?php if (is_manager_or_above()): ?>
                    <td class="no-print">
                        <button class="btn btn-sm btn-outline-secondary" onclick='openNoteModal(<?= json_encode($r, JSON_UNESCAPED_UNICODE) ?>)'><i class="fa-solid fa-pen"></i></button>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="fw-semibold">
                    <td><?= e(t('total')) ?></td>
                    <td class="text-end"><?= format_currency(array_sum(array_column($rows, 'income'))) ?></td>
                    <td class="text-end"><?= format_currency(array_sum(array_column($rows, 'expense_interest'))) ?></td>
                    <td class="text-end"><?= format_currency(array_sum(array_column($rows, 'other_expense'))) ?></td>
                    <td class="text-end"><?= format_currency($yearTotal) ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="modal fade" id="noteModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="year_month" id="nf_ym">
      <div class="modal-header">
        <h5 class="modal-title" id="nf_title"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
            <label class="form-label"><?= e(t('target_profit')) ?></label>
            <input type="number" step="0.01" name="target_profit" id="nf_target" class="form-control">
        </div>
        <div class="mb-2">
            <label class="form-label"><?= e(t('note')) ?></label>
            <textarea name="note" id="nf_note" class="form-control" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(t('cancel')) ?></button>
        <button type="submit" class="btn btn-success"><?= e(t('save_note')) ?></button>
      </div>
    </form>
  </div>
</div>

<?php
$extra_js = "
function openNoteModal(r) {
    document.getElementById('nf_ym').value = r.year_month;
    document.getElementById('nf_title').textContent = r.label;
    document.getElementById('nf_target').value = r.target_profit || '';
    document.getElementById('nf_note').value = r.note || '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('noteModal')).show();
}
var mctx = document.getElementById('monthlyProfitChart');
if (mctx) {
    new Chart(mctx, {
        type: 'bar',
        data: {
            labels: " . json_encode(array_column($rows, 'label')) . ",
            datasets: [{
                label: " . json_encode(t('net_profit')) . ",
                data: " . json_encode(array_map(fn($r) => round($r['net'], 2), $rows)) . ",
                backgroundColor: '#2f6f4f',
                borderRadius: 4,
                maxBarThickness: 34,
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                y: { grid: { color: '#eef1ef' } },
                x: { grid: { display: false } }
            }
        }
    });
}
";
include __DIR__ . '/../../includes/footer.php';
