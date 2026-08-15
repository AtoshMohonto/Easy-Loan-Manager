<?php
require_once __DIR__ . '/../../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $id = (int) ($_POST['id'] ?? 0);
        execute('UPDATE notifications SET is_read = 1 WHERE id = ? AND (user_id = ? OR user_id IS NULL)', [$id, current_user_id()]);
        redirect(BASE_URL . 'modules/notifications/index.php');
    }

    if ($action === 'mark_all_read') {
        execute('UPDATE notifications SET is_read = 1 WHERE user_id = ? OR user_id IS NULL', [current_user_id()]);
        redirect(BASE_URL . 'modules/notifications/index.php');
    }

    if ($action === 'broadcast') {
        require_role([ROLE_ADMIN, ROLE_MANAGER]);
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        if ($title !== '' && $message !== '') {
            notify(null, $title, $message, 'system');
        }
        redirect(BASE_URL . 'modules/notifications/index.php', t('transaction_saved'));
    }
}

$page_title = t('notifications');
$active_page = 'notifications';

$notifications = fetch_all(
    'SELECT * FROM notifications WHERE user_id = ? OR user_id IS NULL ORDER BY created_at DESC LIMIT 100',
    [current_user_id()]
);

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="mark_all_read">
        <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-check-double"></i> <?= e(t('mark_all_read')) ?></button>
    </form>
    <?php if (is_manager_or_above()): ?>
    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#broadcastModal"><i class="fa-solid fa-bullhorn"></i> <?= e(t('broadcast_notification')) ?></button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="list-group list-group-flush">
        <?php if (empty($notifications)): ?>
            <div class="text-center text-muted py-5"><?= e(t('no_notifications')) ?></div>
        <?php endif; ?>
        <?php foreach ($notifications as $n): ?>
            <div class="list-group-item d-flex justify-content-between align-items-start <?= $n['is_read'] ? '' : 'notif-item unread' ?>">
                <div>
                    <div class="fw-semibold"><?= e($n['title']) ?> <?php if (!$n['is_read']): ?><span class="badge bg-danger"><?= e(t('unread')) ?></span><?php endif; ?></div>
                    <div class="text-muted small"><?= e($n['message']) ?></div>
                    <div class="text-muted small mt-1"><?= e(format_datetime($n['created_at'])) ?></div>
                </div>
                <?php if (!$n['is_read']): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="mark_read">
                    <input type="hidden" name="id" value="<?= $n['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><?= e(t('mark_read')) ?></button>
                </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal fade" id="broadcastModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="broadcast">
      <div class="modal-header">
        <h5 class="modal-title"><?= e(t('broadcast_notification')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
            <label class="form-label"><?= e(t('title')) ?></label>
            <input type="text" name="title" class="form-control" required>
        </div>
        <div class="mb-2">
            <label class="form-label"><?= e(t('message')) ?></label>
            <textarea name="message" class="form-control" rows="3" required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(t('cancel')) ?></button>
        <button type="submit" class="btn btn-success"><?= e(t('send')) ?></button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
