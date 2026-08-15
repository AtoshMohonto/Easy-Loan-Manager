<?php
require_once __DIR__ . '/../../config/config.php';
require_login();
require_csrf();

// Optional return URL so the page can bounce back to where it came from.
$redirectTo = $_POST['redirect'] ?? '';
$target = ($redirectTo !== '' && str_starts_with($redirectTo, BASE_URL))
    ? $redirectTo
    : BASE_URL . 'modules/finance/index.php';

require_role([ROLE_ADMIN, ROLE_MANAGER]);

$action = $_POST['action'] ?? 'save_loan';
$id = (int) ($_POST['id'] ?? 0);

$valid_methods = ['Cash', 'Bank', 'Mobile Banking', 'Cheque', 'Other'];

// ---- Delete an institution loan -------------------------------------------------
if ($action === 'delete_loan') {
    require_role([ROLE_ADMIN]);
    $loan = fetch_one('SELECT institution_name FROM institution_loans WHERE id = ?', [$id]);
    execute('UPDATE institution_loans SET is_deleted = 1 WHERE id = ?', [$id]);
    log_activity('delete_institution_loan', 'institution_loan', $id, $loan['institution_name'] ?? '');
    redirect($target, t('loan_deleted'));
}

// ---- Delete a payment -----------------------------------------------------------
if ($action === 'delete_payment') {
    require_role([ROLE_ADMIN]);
    $payment = fetch_one('SELECT institution_loan_id FROM institution_payments WHERE id = ?', [$id]);
    execute('UPDATE institution_payments SET is_deleted = 1 WHERE id = ?', [$id]);
    if ($payment) {
        log_activity('delete_institution_payment', 'institution_payment', $id);
    }
    redirect($target, t('payment_deleted'));
}

// ---- Record a payment against an institution loan --------------------------------
if ($action === 'add_payment') {
    $loan_id = (int) ($_POST['loan_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $payment_type = in_array($_POST['payment_type'] ?? '', ['Principal', 'Interest'], true) ? $_POST['payment_type'] : 'Principal';
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $payment_method = in_array($_POST['payment_method'] ?? '', $valid_methods, true) ? $_POST['payment_method'] : 'Cash';
    $description = trim($_POST['description'] ?? '');

    $loan = fetch_one('SELECT id FROM institution_loans WHERE id = ? AND is_deleted = 0', [$loan_id]);
    if (!$loan) {
        redirect($target, t('no_records_found'), 'danger');
    }
    if ($amount <= 0) {
        redirect($target, t('amount_required'), 'danger');
    }

    execute(
        'INSERT INTO institution_payments (institution_loan_id, amount, payment_type, payment_date, payment_method, description, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$loan_id, $amount, $payment_type, $payment_date, $payment_method, $description, current_user_id()]
    );
    log_activity('create_institution_payment', 'institution_payment', (int) db()->lastInsertId());
    redirect($target, t('payment_saved'));
}

// ---- Save / update an institution loan ------------------------------------------
$institution_name = trim($_POST['institution_name'] ?? '');
$loan_amount = (float) ($_POST['loan_amount'] ?? 0);
$interest_rate = ($_POST['interest_rate'] ?? '') !== '' ? (float) $_POST['interest_rate'] : null;
$taken_date = $_POST['taken_date'] ?? date('Y-m-d');
$due_date = ($_POST['due_date'] ?? '') !== '' ? $_POST['due_date'] : null;
$status = in_array($_POST['status'] ?? '', ['Active', 'Closed'], true) ? $_POST['status'] : 'Active';
$notes = trim($_POST['notes'] ?? '');

if ($institution_name === '') {
    redirect($target, t('name_required'), 'danger');
}
if ($loan_amount <= 0) {
    redirect($target, t('amount_required'), 'danger');
}

if ($id > 0) {
    execute(
        'UPDATE institution_loans SET institution_name=?, loan_amount=?, interest_rate=?, taken_date=?, due_date=?, status=?, notes=? WHERE id=?',
        [$institution_name, $loan_amount, $interest_rate, $taken_date, $due_date, $status, $notes, $id]
    );
    log_activity('update_institution_loan', 'institution_loan', $id, $institution_name);
} else {
    execute(
        'INSERT INTO institution_loans (institution_name, loan_amount, interest_rate, taken_date, due_date, status, notes, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$institution_name, $loan_amount, $interest_rate, $taken_date, $due_date, $status, $notes, current_user_id()]
    );
    log_activity('create_institution_loan', 'institution_loan', (int) db()->lastInsertId(), $institution_name);
}

redirect($target, t('loan_saved'));
