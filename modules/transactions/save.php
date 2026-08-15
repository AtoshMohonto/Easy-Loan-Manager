<?php
require_once __DIR__ . '/../../config/config.php';
require_login();
require_csrf();

$action = $_POST['action'] ?? 'save';
$id = (int) ($_POST['id'] ?? 0);

// Optional return URL so in-app flows (e.g. Area Analytics) can redirect
// the user back to the page they came from after saving.
$redirectTo = $_POST['redirect'] ?? '';
$target = ($redirectTo !== '' && str_starts_with($redirectTo, BASE_URL))
    ? $redirectTo
    : BASE_URL . 'modules/transactions/index.php';

if ($action === 'delete') {
    require_role([ROLE_ADMIN]);
    $tx = fetch_one('SELECT person_id FROM transactions WHERE id = ?', [$id]);
    execute('UPDATE transactions SET is_deleted = 1 WHERE id = ?', [$id]);
    if ($tx && $tx['person_id']) {
        recalc_person_balance((int) $tx['person_id']);
    }
    log_activity('delete_transaction', 'transaction', $id);
    redirect($target, t('transaction_deleted'));
}

require_role([ROLE_ADMIN, ROLE_MANAGER]);

$valid_types = ['Amount Given', 'Amount Received', 'Interest', 'Expense', 'Adjustment'];
$valid_methods = ['Cash', 'Bank', 'Mobile Banking', 'Cheque', 'Other'];

$transaction_type = in_array($_POST['transaction_type'] ?? '', $valid_types, true) ? $_POST['transaction_type'] : 'Amount Given';
$payment_method = in_array($_POST['payment_method'] ?? '', $valid_methods, true) ? $_POST['payment_method'] : 'Cash';
$amount = (float) ($_POST['amount'] ?? 0);
$transaction_date = $_POST['transaction_date'] ?? date('Y-m-d');
$description = trim($_POST['description'] ?? '');
$person_id = ($_POST['person_id'] ?? '') !== '' ? (int) $_POST['person_id'] : null;

if ($amount <= 0 && $transaction_type !== 'Adjustment') {
    redirect($target, t('amount_required'), 'danger');
}
if ($transaction_type !== 'Expense' && !$person_id) {
    redirect($target, t('person_required'), 'danger');
}

$words_en = number_to_words_en(abs($amount));
$words_bn = number_to_words_bn(abs($amount));

$pdo = db();
$pdo->beginTransaction();
try {
    $old_person_id = null;

    if ($id > 0) {
        $existing = fetch_one('SELECT person_id FROM transactions WHERE id = ?', [$id]);
        $old_person_id = $existing['person_id'] ?? null;

        execute(
            'UPDATE transactions SET person_id=?, transaction_type=?, amount=?, amount_words_en=?, amount_words_bn=?, transaction_date=?, payment_method=?, description=? WHERE id=?',
            [$person_id, $transaction_type, $amount, $words_en, $words_bn, $transaction_date, $payment_method, $description, $id]
        );
        log_activity('update_transaction', 'transaction', $id);
    } else {
        execute(
            'INSERT INTO transactions (person_id, transaction_type, amount, amount_words_en, amount_words_bn, transaction_date, payment_method, description, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$person_id, $transaction_type, $amount, $words_en, $words_bn, $transaction_date, $payment_method, $description, current_user_id()]
        );
        $id = (int) $pdo->lastInsertId();
        log_activity('create_transaction', 'transaction', $id);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    redirect($target, t('transaction_failed'), 'danger');
}

if ($old_person_id && $old_person_id != $person_id) {
    recalc_person_balance((int) $old_person_id);
}
if ($person_id) {
    recalc_person_balance($person_id);

    if ($amount >= 50000) {
        $person = fetch_one('SELECT name FROM persons WHERE id = ?', [$person_id]);
        notify(null, t('add_transaction'), ($person['name'] ?? '') . ': ' . t(strtolower(str_replace(' ', '_', $transaction_type))) . ' ' . format_currency($amount), 'transaction', $person_id);
    }
}

$successMsg = !empty($_POST['mark_payment']) ? t('payment_success') : t('transaction_saved');
redirect($target, $successMsg);
