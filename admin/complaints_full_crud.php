<?php
require_once 'auth_check.php';

$success_message = '';
$error_message = '';

ensure_complaints_schema($conn);
close_stale_customer_support_threads($conn);

function load_admin_support_ticket($conn, $ticket_id) {
    $ticket_id = (int)$ticket_id;
    if ($ticket_id <= 0) {
        return null;
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT c.*, u.full_name AS site_user_name, u.email AS site_user_email, a.full_name AS replied_by_name
         FROM complaints c
         LEFT JOIN site_users u ON c.user_id = u.id
         LEFT JOIN admins a ON c.replied_by_admin_id = a.id
         WHERE c.id = ?
         LIMIT 1"
    );

    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, "i", $ticket_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $ticket = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $ticket;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_input($_POST['action'] ?? '');

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = mysqli_prepare($conn, "DELETE FROM complaints WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        $success_message = mysqli_stmt_execute($stmt) ? 'Complaint deleted successfully' : 'Error deleting complaint';
        mysqli_stmt_close($stmt);
    } elseif ($action === 'reply') {
        $id = (int)($_POST['id'] ?? 0);
        $reply = sanitize_input($_POST['admin_reply'] ?? '');
        $admin_id = (int)($_SESSION['admin_id'] ?? 0);
        $ticket = load_admin_support_ticket($conn, $id);

        if (!$ticket || $reply === '') {
            $error_message = t('admin_reply_required');
        } elseif (add_complaint_message($conn, $id, 'admin', $reply, null, $admin_id)) {
            $customer_email = $ticket['customer_email'] ?: ($ticket['site_user_email'] ?? '');
            $mail_error = null;
            $email_sent = true;

            if ($customer_email !== '') {
                $email_ticket = $ticket;
                $email_ticket['customer_email'] = $customer_email;
                $email_sent = send_guest_support_reply_email($email_ticket, $reply, $mail_error);
            }

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE complaints
                 SET admin_reply = ?, replied_by_admin_id = ?, replied_at = NOW(), closed_for_customer_at = NULL, status = 'Answered', updated_at = NOW()
                 WHERE id = ?"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "sii", $reply, $admin_id, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            if (!empty($ticket['user_id'])) {
                create_site_notification(
                    $conn,
                    (int)$ticket['user_id'],
                    'support_replied',
                    t('support_admin_reply'),
                    'Your support conversation ' . ($ticket['complaint_code'] ?: ('#' . $id)) . ' has a new admin reply.',
                    'user/complaints.php?ticket_id=' . $id
                );
            }

            log_admin_action($conn, $admin_id, 'REPLY', 'complaints', $id);
            $success_message = $email_sent ? t('admin_reply_email_sent') : (t('admin_reply_saved') . ' ' . ($mail_error ?: ''));
        } else {
            $error_message = t('admin_reply_error');
        }
    }
}

$complaints = mysqli_query(
    $conn,
    "SELECT c.*, u.full_name AS site_user_name, u.email AS site_user_email, a.full_name AS replied_by_name
     FROM complaints c
     LEFT JOIN site_users u ON c.user_id = u.id
     LEFT JOIN admins a ON c.replied_by_admin_id = a.id
     ORDER BY c.updated_at DESC, c.created_at DESC, c.id DESC"
);

$selected_ticket_id = (int)($_GET['ticket_id'] ?? 0);
$selected_ticket = $selected_ticket_id > 0 ? load_admin_support_ticket($conn, $selected_ticket_id) : null;
$selected_messages = $selected_ticket ? get_complaint_messages($conn, (int)$selected_ticket['id']) : [];
if ($selected_ticket && empty($selected_messages) && !empty($selected_ticket['message'])) {
    $selected_messages[] = [
        'sender_type' => 'customer',
        'message_text' => $selected_ticket['message'],
        'created_at' => $selected_ticket['created_at'],
        'site_user_name' => $selected_ticket['customer_name'],
        'admin_name' => null,
    ];
}

$page_title = t('admin_complaints_management');
$active_page = 'complaints';
include 'includes/header.php';
?>

<div class="content">
    <div class="container">
        <div class="page-header">
            <h1><?php echo t('admin_complaints_management'); ?></h1>
        </div>

        <?php if ($success_message): ?>
            <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if ($selected_ticket): ?>
            <div class="table-container" style="margin-bottom: 1rem;">
                <h2><?php echo htmlspecialchars($selected_ticket['complaint_code'] ?: ('SUP-' . str_pad($selected_ticket['id'], 6, '0', STR_PAD_LEFT))); ?></h2>
                <p class="admin-muted">
                    <?php echo htmlspecialchars($selected_ticket['customer_name']); ?>
                    -
                    <?php echo htmlspecialchars($selected_ticket['customer_email'] ?: ($selected_ticket['site_user_email'] ?? 'N/A')); ?>
                    <?php if (!empty($selected_ticket['closed_for_customer_at'])): ?>
                        - <?php echo htmlspecialchars(t('support_closed_for_user')); ?>
                    <?php endif; ?>
                </p>
                <div class="support-thread-messages">
                    <?php foreach ($selected_messages as $chat_message): ?>
                        <?php $is_admin_message = ($chat_message['sender_type'] ?? '') === 'admin'; ?>
                        <article class="support-thread-message <?php echo $is_admin_message ? 'is-admin' : 'is-customer'; ?>">
                            <strong><?php echo htmlspecialchars($is_admin_message ? ($_SESSION['admin_full_name'] ?? 'Admin') : $selected_ticket['customer_name']); ?></strong>
                            <p><?php echo nl2br(htmlspecialchars($chat_message['message_text'])); ?></p>
                            <span><?php echo htmlspecialchars(date('Y-m-d h:i A', strtotime($chat_message['created_at']))); ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
                <form method="POST" action="complaints_full_crud.php?ticket_id=<?php echo (int)$selected_ticket['id']; ?>">
                    <input type="hidden" name="action" value="reply">
                    <input type="hidden" name="id" value="<?php echo (int)$selected_ticket['id']; ?>">
                    <?php echo admin_csrf_input(); ?>
                    <div class="form-group">
                        <label class="form-label"><?php echo t('admin_field_reply'); ?></label>
                        <textarea name="admin_reply" class="form-control" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn"><?php echo t('admin_reply_send'); ?></button>
                    <a href="complaints_full_crud.php" class="btn btn-secondary"><?php echo t('common_close'); ?></a>
                </form>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <?php if ($complaints && mysqli_num_rows($complaints) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th><?php echo t('support_ticket_code'); ?></th>
                            <th><?php echo t('admin_field_customer_name'); ?></th>
                            <th><?php echo t('auth_email'); ?></th>
                            <th><?php echo t('common_status'); ?></th>
                            <th><?php echo t('admin_field_message'); ?></th>
                            <th><?php echo t('admin_field_submitted'); ?></th>
                            <th><?php echo t('admin_field_actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($complaint = mysqli_fetch_assoc($complaints)): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($complaint['complaint_code'] ?: ('SUP-' . str_pad($complaint['id'], 6, '0', STR_PAD_LEFT))); ?></strong><br>
                                    <span class="admin-muted">#<?php echo (int)$complaint['id']; ?></span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($complaint['customer_name']); ?>
                                    <?php if (!empty($complaint['site_user_email'])): ?>
                                        <br><span class="admin-muted"><?php echo htmlspecialchars($complaint['site_user_name']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($complaint['customer_email'] ?: ($complaint['site_user_email'] ?? 'N/A')); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo htmlspecialchars(normalize_status_class($complaint['status'] ?? 'Open')); ?>">
                                        <?php echo htmlspecialchars(t('support_status_' . normalize_status_key($complaint['status'] ?? 'Open'), [], $complaint['status'] ?? 'Open')); ?>
                                    </span>
                                    <?php if (!empty($complaint['closed_for_customer_at'])): ?>
                                        <br><span class="admin-muted"><?php echo htmlspecialchars(t('support_closed_for_user')); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><div class="complaint-text"><?php echo htmlspecialchars($complaint['message']); ?></div></td>
                                <td><?php echo date('Y-m-d h:i A', strtotime($complaint['created_at'])); ?></td>
                                <td>
                                    <a class="btn btn-small btn-info" href="complaints_full_crud.php?ticket_id=<?php echo (int)$complaint['id']; ?>"><?php echo t('common_view'); ?></a>
                                    <button class="btn btn-small btn-danger" onclick="confirmDelete(<?php echo (int)$complaint['id']; ?>)"><?php echo t('admin_action_delete'); ?></button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data"><?php echo t('admin_no_complaints'); ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<form id="deleteForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
    <?php echo admin_csrf_input(); ?>
</form>

<script>
    function confirmDelete(id) {
        showAdminConfirm('<?php echo addslashes(t('admin_delete_confirm')); ?>', function() {
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteForm').submit();
        });
    }
</script>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
