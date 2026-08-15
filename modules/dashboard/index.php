<?php
require_once __DIR__ . '/../../config/config.php';
require_login();

$page_title = t('dashboard');
$active_page = 'dashboard';

$location_id = ($_GET['location_id'] ?? '') !== '' ? (int) $_GET['location_id'] : null;
$locations = active_locations();

$locWhere = $location_id ? 'AND location_id = ?' : '';
$locParam = $location_id ? [$location_id] : [];

$totalDues = fetch_one("SELECT COALESCE(SUM(balance),0) AS v FROM persons WHERE is_deleted=0 AND person_type='Borrower' AND balance>0 $locWhere", $locParam)['v'];
$totalPayable = fetch_one("SELECT COALESCE(SUM(balance),0) AS v FROM persons WHERE is_deleted=0 AND person_type='Lender' AND balance>0 $locWhere", $locParam)['v'];
$activeBorrowers = fetch_one("SELECT COUNT(*) AS v FROM persons WHERE is_deleted=0 AND person_type='Borrower' AND status='Active' AND balance>0 $locWhere", $locParam)['v'];
$activeLenders = fetch_one("SELECT COUNT(*) AS v FROM persons WHERE is_deleted=0 AND person_type='Lender' AND status='Active' AND balance>0 $locWhere", $locParam)['v'];
$totalPersons = fetch_one("SELECT COUNT(*) AS v FROM persons WHERE is_deleted=0 $locWhere", $locParam)['v'];

$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$profit = calculate_profit($monthStart, $monthEnd, $location_id);

// Last 12 months profit trend
$trendLabels = [];
$trendValues = [];
for ($i = 11; $i >= 0; $i--) {
    $ts = strtotime("-$i months", strtotime(date('Y-m-01')));
    $s = date('Y-m-01', $ts);
    $e = date('Y-m-t', $ts);
    $p = calculate_profit($s, $e, $location_id);
    $trendLabels[] = date('M Y', $ts);
    $trendValues[] = round($p['net'], 2);
}

$recentTxns = fetch_all(
    "SELECT t.*, p.name AS person_name FROM transactions t
     LEFT JOIN persons p ON p.id = t.person_id
     " . ($location_id ? 'WHERE p.location_id = ? AND t.is_deleted = 0' : 'WHERE t.is_deleted = 0') . "
     ORDER BY t.transaction_date DESC, t.id DESC LIMIT 8",
    $locParam
);

$use_chartjs = true;
include __DIR__ . '/../../includes/header.php';
?>

<form class="mb-3 no-print" method="get">
    <div class="d-flex gap-2 align-items-center">
        <label class="form-label mb-0 small text-muted"><?= e(t('location')) ?>:</label>
        <select name="location_id" class="form-select form-select-sm" style="width:200px" onchange="this.form.submit()">
            <option value=""><?= e(t('all_locations')) ?></option>
            <?php foreach ($locations as $loc): ?>
                <option value="<?= $loc['id'] ?>" <?= $location_id === (int) $loc['id'] ? 'selected' : '' ?>><?= e($loc['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-2">
        <div class="kpi-card">
            <div class="kpi-icon bg-icon-green"><i class="fa-solid fa-hand-holding-dollar"></i></div>
            <div class="kpi-value"><?= format_currency((float) $totalDues) ?></div>
            <div class="kpi-label"><?= e(t('total_dues')) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="kpi-card">
            <div class="kpi-icon bg-icon-red"><i class="fa-solid fa-money-bill-wave"></i></div>
            <div class="kpi-value"><?= format_currency((float) $totalPayable) ?></div>
            <div class="kpi-label"><?= e(t('total_payable')) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="kpi-card">
            <div class="kpi-icon bg-icon-blue"><i class="fa-solid fa-chart-line"></i></div>
            <div class="kpi-value"><?= format_currency((float) $profit['net']) ?></div>
            <div class="kpi-label"><?= e(t('this_month_profit')) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="kpi-card">
            <div class="kpi-icon bg-icon-amber"><i class="fa-solid fa-users"></i></div>
            <div class="kpi-value"><?= (int) $activeBorrowers ?></div>
            <div class="kpi-label"><?= e(t('active_borrowers')) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="kpi-card">
            <div class="kpi-icon bg-icon-purple"><i class="fa-solid fa-people-group"></i></div>
            <div class="kpi-value"><?= (int) $activeLenders ?></div>
            <div class="kpi-label"><?= e(t('active_lenders')) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="kpi-card">
            <div class="kpi-icon bg-icon-green"><i class="fa-solid fa-address-book"></i></div>
            <div class="kpi-value"><?= (int) $totalPersons ?></div>
            <div class="kpi-label"><?= e(t('total_persons')) ?></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><?= e(t('profit_trend')) ?></div>
            <div class="card-body">
                <canvas id="profitTrendChart" height="220" role="img" aria-label="<?= e(t('profit_trend')) ?>"></canvas>
                <table class="visually-hidden" id="profitTrendTable">
                    <caption><?= e(t('profit_trend')) ?></caption>
                    <thead><tr><th><?= e(t('month')) ?></th><th><?= e(t('net_profit')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($trendLabels as $i => $label): ?>
                        <tr><td><?= e($label) ?></td><td><?= $trendValues[$i] ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between">
                <span><?= e(t('recent_transactions')) ?></span>
                <a href="../transactions/index.php" class="small"><?= e(t('view_all')) ?></a>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($recentTxns)): ?>
                    <div class="text-center text-muted py-4"><?= e(t('no_records_found')) ?></div>
                <?php endif; ?>
                <?php foreach ($recentTxns as $tx): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small fw-semibold"><?= e($tx['person_name'] ?? t('general_expense')) ?></div>
                            <div class="text-muted small"><?= e(t(strtolower(str_replace(' ', '_', $tx['transaction_type'])))) ?> &middot; <?= e(format_date($tx['transaction_date'])) ?></div>
                        </div>
                        <div class="fw-semibold"><?= format_currency((float) $tx['amount']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = "
var ctx = document.getElementById('profitTrendChart');
if (ctx) {
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: " . json_encode($trendLabels) . ",
            datasets: [{
                label: " . json_encode(t('net_profit')) . ",
                data: " . json_encode($trendValues) . ",
                borderColor: '#2f6f4f',
                backgroundColor: 'rgba(47,111,79,0.12)',
                borderWidth: 2,
                pointRadius: 3,
                pointBackgroundColor: '#2f6f4f',
                tension: 0.3,
                fill: true,
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                y: { grid: { color: '#eef1ef' }, ticks: { callback: function(v){ return v; } } },
                x: { grid: { display: false } }
            }
        }
    });
}
";
include __DIR__ . '/../../includes/footer.php';
