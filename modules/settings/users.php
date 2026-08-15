<?php
require_once __DIR__ . '/../../config/config.php';
require_role([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? 'save';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete') {
        if ($id === current_user_id()) {
            redirect(BASE_URL . 'modules/settings/users.php', t('cannot_delete_own_account'), 'danger');
        }
        execute('UPDATE users SET is_deleted = 1 WHERE id = ?', [$id]);
        log_activity('delete_user', 'user', $id);
        redirect(BASE_URL . 'modules/settings/users.php', t('user_deleted'));
    }

    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = in_array($_POST['role'] ?? '', [ROLE_ADMIN, ROLE_MANAGER, ROLE_VIEWER], true) ? $_POST['role'] : ROLE_VIEWER;
    $status = in_array($_POST['status'] ?? '', ['Active', 'Inactive'], true) ? $_POST['status'] : 'Active';
    $password = (string) ($_POST['password'] ?? '');

    if ($full_name === '' || $username === '') {
        redirect(BASE_URL . 'modules/settings/users.php', t('user_fields_required'), 'danger');
    }

    $dupe = fetch_one('SELECT id FROM users WHERE username = ? AND id != ? AND is_deleted = 0', [$username, $id]);
    if ($dupe) {
        redirect(BASE_URL . 'modules/settings/users.php', t('username_taken'), 'danger');
    }

    if ($id > 0) {
        if ($password !== '') {
            execute('UPDATE users SET full_name=?, username=?, email=?, role=?, status=?, password=? WHERE id=?',
                [$full_name, $username, $email, $role, $status, password_hash($password, PASSWORD_DEFAULT), $id]);
        } else {
            execute('UPDATE users SET full_name=?, username=?, email=?, role=?, status=? WHERE id=?',
                [$full_name, $username, $email, $role, $status, $id]);
        }
        log_activity('update_user', 'user', $id, $username);
    } else {
        if ($password === '') {
            redirect(BASE_URL . 'modules/settings/users.php', t('password_required'), 'danger');
        }
        execute('INSERT INTO users (full_name, username, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?)',
            [$full_name, $username, $email, password_hash($password, PASSWORD_DEFAULT), $role, $status]);
        log_activity('create_user', 'user', (int) db()->lastInsertId(), $username);
    }
    redirect(BASE_URL . 'modules/settings/users.php', t('user_saved'));
}

$page_title = t('users');
$active_page = 'users';
$users = fetch_all('SELECT * FROM users WHERE is_deleted = 0 ORDER BY full_name');
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-end mb-3 no-print">
    <button class="btn btn-sm btn-success" onclick="openUserModal()"><i class="fa-solid fa-plus"></i> <?= e(t('add_user')) ?></button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th><?= e(t('full_name')) ?></th><th><?= e(t('username')) ?></th><th><?= e(t('role')) ?></th><th><?= e(t('status')) ?></th><th class="no-print"><?= e(t('actions')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= e($u['full_name']) ?></td>
                    <td><?= e($u['username']) ?></td>
                    <td><?= e(t(strtolower($u['role']))) ?></td>
                    <td><span class="badge bg-<?= $u['status'] === 'Active' ? 'success' : 'secondary' ?>"><?= e(t(strtolower($u['status']))) ?></span></td>
                    <td class="no-print">
                        <button class="btn btn-sm btn-outline-secondary" onclick='openUserModal(<?= json_encode($u, JSON_UNESCAPED_UNICODE) ?>)'><i class="fa-solid fa-pen"></i></button>
                        <?php if ((int) $u['id'] !== current_user_id()): ?>
                        <form method="post" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="<?= e(t('confirm_delete')) ?>"><i class="fa-solid fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="uf_id">
      <div class="modal-header">
        <h5 class="modal-title" id="userModalTitle"><?= e(t('add_user')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
            <label class="form-label"><?= e(t('full_name')) ?></label>
            <input type="text" name="full_name" id="uf_full_name" class="form-control" required>
        </div>
        <div class="row">
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('username')) ?></label>
                <input type="text" name="username" id="uf_username" class="form-control" required>
            </div>
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('email')) ?></label>
                <input type="email" name="email" id="uf_email" class="form-control">
            </div>
        </div>
        <div class="row">
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('role')) ?></label>
                <select name="role" id="uf_role" class="form-select">
                    <option value="Admin"><?= e(t('admin')) ?></option>
                    <option value="Manager"><?= e(t('manager')) ?></option>
                    <option value="Viewer"><?= e(t('viewer')) ?></option>
                </select>
            </div>
            <div class="col-6 mb-2">
                <label class="form-label"><?= e(t('status')) ?></label>
                <select name="status" id="uf_status" class="form-select">
                    <option value="Active"><?= e(t('active')) ?></option>
                    <option value="Inactive"><?= e(t('inactive')) ?></option>
                </select>
            </div>
        </div>
        <div class="mb-2">
            <label class="form-label"><?= e(t('password')) ?></label>
            <input type="password" name="password" id="uf_password" class="form-control">
            <div class="form-text" id="uf_password_hint"></div>
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
function openUserModal(u) {
    document.getElementById('userModal').querySelector('form').reset();
    document.getElementById('userModalTitle').textContent = u ? " . json_encode(t('edit')) . " : " . json_encode(t('add_user')) . ";
    document.getElementById('uf_id').value = u ? u.id : '';
    document.getElementById('uf_full_name').value = u ? u.full_name : '';
    document.getElementById('uf_username').value = u ? u.username : '';
    document.getElementById('uf_email').value = u ? (u.email || '') : '';
    document.getElementById('uf_role').value = u ? u.role : 'Viewer';
    document.getElementById('uf_status').value = u ? u.status : 'Active';
    document.getElementById('uf_password').required = !u;
    document.getElementById('uf_password_hint').textContent = u ? " . json_encode(t('leave_blank_keep_password')) . " : '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('userModal')).show();
}
";
include __DIR__ . '/../../includes/footer.php';
