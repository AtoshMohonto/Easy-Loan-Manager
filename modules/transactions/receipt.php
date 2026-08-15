<?php
require_once __DIR__ . '/../../config/config.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$tx = fetch_one(
    'SELECT t.*, p.name AS person_name, p.person_type, p.address AS person_address
     FROM transactions t LEFT JOIN persons p ON p.id = t.person_id
     WHERE t.id = ? AND t.is_deleted = 0',
    [$id]
);

if (!$tx) {
    redirect(BASE_URL . 'modules/transactions/index.php', t('no_records_found'), 'danger');
}

$settings = fetch_one('SELECT * FROM settings WHERE id = 1');
$words = $CURRENT_LANG === 'bn' ? $tx['amount_words_bn'] : $tx['amount_words_en'];
?>
<!DOCTYPE html>
<html lang="<?= e($CURRENT_LANG) ?>">
<head>
<meta charset="UTF-8">
<title><?= e(t('receipt')) ?> — TXN-<?= str_pad((string) $tx['id'], 6, '0', STR_PAD_LEFT) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="print-sheet">
    <div class="text-center mb-4">
        <img class="app-logo app-logo-lg" src="<?= BASE_URL ?>assets/img/logo.svg" alt="<?= e(t('app_name')) ?>">
        <h3 class="mb-0"><?= e($settings['company_name'] ?? t('app_name')) ?></h3>
        <p class="text-muted mb-0"><?= e(t('receipt')) ?> — TXN-<?= str_pad((string) $tx['id'], 6, '0', STR_PAD_LEFT) ?></p>
    </div>
    <hr>
    <table class="table table-borderless mb-3">
        <tr>
            <th style="width:180px"><?= e(t('name')) ?></th>
            <td><?= e($tx['person_name'] ?? t('general_expense')) ?></td>
        </tr>
        <tr>
            <th><?= e(t('transaction_date')) ?></th>
            <td><?= e(format_date($tx['transaction_date'])) ?></td>
        </tr>
        <tr>
            <th><?= e(t('transaction_type')) ?></th>
            <td><?= e(t(strtolower(str_replace(' ', '_', $tx['transaction_type'])))) ?></td>
        </tr>
        <tr>
            <th><?= e(t('payment_method')) ?></th>
            <td><?= e(t(strtolower(str_replace(' ', '_', $tx['payment_method'])))) ?></td>
        </tr>
        <?php if ($tx['description']): ?>
        <tr>
            <th><?= e(t('description')) ?></th>
            <td><?= e($tx['description']) ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <div class="text-center my-4">
        <div class="text-muted small"><?= e(t('amount')) ?></div>
        <div class="display-6 fw-bold"><?= format_currency((float) $tx['amount']) ?></div>
        <div class="text-muted mt-1"><?= e(t('amount_in_words')) ?>: <em><?= e($words) ?></em></div>
    </div>

    <div class="row mt-5 pt-5">
        <div class="col-6 text-center">
            <div style="border-top:1px solid #999; width:80%; margin:0 auto;"></div>
            <small class="text-muted"><?= e(t('name')) ?> (<?= e(t('borrower')) ?>/<?= e(t('lender')) ?>)</small>
        </div>
        <div class="col-6 text-center">
            <div style="border-top:1px solid #999; width:80%; margin:0 auto;"></div>
            <small class="text-muted"><?= e(t('authorized_signature')) ?></small>
        </div>
    </div>

    <div class="text-center no-print mt-4">
        <button class="btn btn-success btn-print"><i class="fa-solid fa-print"></i> <?= e(t('print')) ?></button>
        <a href="index.php" class="btn btn-outline-secondary"><?= e(t('back')) ?></a>
    </div>
</div>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</body>
</html>
