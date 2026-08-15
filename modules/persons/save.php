<?php
require_once __DIR__ . '/../../config/config.php';
require_login();
require_csrf();

$action = $_POST['action'] ?? 'save';
$id = (int) ($_POST['id'] ?? 0);

if ($action === 'delete') {
    require_role([ROLE_ADMIN]);
    $person = fetch_one('SELECT name FROM persons WHERE id = ?', [$id]);
    execute('UPDATE persons SET is_deleted = 1 WHERE id = ?', [$id]);
    log_activity('delete_person', 'person', $id, $person['name'] ?? '');
    redirect(BASE_URL . 'modules/persons/index.php', t('person_deleted'));
}

require_role([ROLE_ADMIN, ROLE_MANAGER]);

$name = trim($_POST['name'] ?? '');
$person_type = in_array($_POST['person_type'] ?? '', ['Lender', 'Borrower'], true) ? $_POST['person_type'] : 'Borrower';
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');
$location_id = ($_POST['location_id'] ?? '') !== '' ? (int) $_POST['location_id'] : null;
$opening_balance = (float) ($_POST['opening_balance'] ?? 0);
$status = in_array($_POST['status'] ?? '', ['Active', 'Inactive'], true) ? $_POST['status'] : 'Active';
$notes = trim($_POST['notes'] ?? '');

if ($name === '') {
    redirect(BASE_URL . 'modules/persons/index.php', t('name_required'), 'danger');
}

if ($id > 0) {
    execute(
        'UPDATE persons SET name=?, person_type=?, phone=?, email=?, address=?, location_id=?, opening_balance=?, status=?, notes=? WHERE id=?',
        [$name, $person_type, $phone, $email, $address, $location_id, $opening_balance, $status, $notes, $id]
    );
    recalc_person_balance($id);
    log_activity('update_person', 'person', $id, $name);
} else {
    execute(
        'INSERT INTO persons (name, person_type, phone, email, address, location_id, opening_balance, balance, status, notes, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$name, $person_type, $phone, $email, $address, $location_id, $opening_balance, $opening_balance, $status, $notes, current_user_id()]
    );
    $id = (int) db()->lastInsertId();
    log_activity('create_person', 'person', $id, $name);
    notify(null, t('add_person'), $name . ' (' . t(strtolower($person_type)) . ') ' . t('person_saved'), 'person', $id);
}

redirect(BASE_URL . 'modules/persons/index.php', t('person_saved'));
