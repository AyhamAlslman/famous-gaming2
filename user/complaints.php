<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$page_title = t('complaints_page_title');
$success_msg = '';
$error_msg = '';

ensure_user_auth_schema($conn);
ensure_complaints_schema($conn);

$current_site_user = get_current_site_user($conn);
$current_site_user_id = $current_site_user ? (int)$current_site_user['id'] : 0;
$customer_session_token = get_customer_session_token();
close_stale_customer_support_threads($conn);

function customer_can_access_support_ticket($ticket, $site_user_id, $session_token) {
    if (!$ticket) {
        return false;
    }

    if ($site_user_id > 0 && (int)($ticket['user_id'] ?? 0) === $site_user_id) {
        return empty($ticket['closed_for_customer_at']);
    }

    return empty($ticket['closed_for_customer_at'])
        && $session_token !== ''
        && hash_equals((string)($ticket['customer_session_token'] ?? ''), (string)$session_token);
}

function load_customer_support_ticket($conn, $ticket_id, $site_user_id, $session_token) {
    $ticket_id = (int)$ticket_id;
    if ($ticket_id <= 0) {
        return null;
    }

    $stmt = mysqli_prepare($conn, "SELECT * FROM complaints WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, "i", $ticket_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $ticket = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return customer_can_access_support_ticket($ticket, $site_user_id, $session_token) ? $ticket : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $support_action = sanitize_input($_POST['support_action'] ?? 'create');
    $message = sanitize_input($_POST['message'] ?? '');

    if ($support_action === 'create') {
        $customer_name = $current_site_user ? $current_site_user['full_name'] : sanitize_input($_POST['customer_name'] ?? '');
        $customer_email = $current_site_user ? $current_site_user['email'] : strtolower(sanitize_input($_POST['customer_email'] ?? ''));
        $phone = $current_site_user ? ($current_site_user['phone'] ?? '') : sanitize_input($_POST['phone'] ?? '');

        if ($customer_name === '' || $message === '' || !validate_email($customer_email)) {
            $error_msg = $customer_email !== '' && !validate_email($customer_email) ? t('auth_email_invalid') : t('complaints_required');
        } else {
            $complaint_code = generate_support_ticket_code();
            $user_id_for_insert = $current_site_user_id > 0 ? $current_site_user_id : null;
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO complaints (complaint_code, user_id, customer_session_token, customer_name, customer_email, phone, message, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'Open')"
            );

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "sisssss", $complaint_code, $user_id_for_insert, $customer_session_token, $customer_name, $customer_email, $phone, $message);
                if (mysqli_stmt_execute($stmt)) {
                    $complaint_id = (int)mysqli_insert_id($conn);
                    add_complaint_message($conn, $complaint_id, 'customer', $message, $current_site_user_id > 0 ? $current_site_user_id : null, null);
                    create_admin_notification(
                        $conn,
                        'support_created',
                        'New support conversation',
                        $customer_name . ' opened support conversation ' . $complaint_code . '.',
                        'complaints',
                        $complaint_id,
                        'complaints_full_crud.php'
                    );
                    if ($current_site_user_id > 0) {
                        create_site_notification(
                            $conn,
                            $current_site_user_id,
                            'support_sent',
                            t('complaints_success'),
                            t('user_notification_support_sent'),
                            'user/complaints.php?ticket_id=' . $complaint_id
                        );
                    }
                    $success_msg = t('support_ticket_created', ['code' => $complaint_code]);
                    $_GET['ticket_id'] = $complaint_id;
                } else {
                    $error_msg = t('complaints_error');
                }
                mysqli_stmt_close($stmt);
            } else {
                $error_msg = t('complaints_error');
            }
        }
    } elseif ($support_action === 'reply') {
        $ticket_id = (int)($_POST['ticket_id'] ?? 0);
        $ticket = load_customer_support_ticket($conn, $ticket_id, $current_site_user_id, $customer_session_token);

        if (!$ticket || $message === '') {
            $error_msg = t('complaints_required');
        } elseif (add_complaint_message($conn, $ticket_id, 'customer', $message, $current_site_user_id > 0 ? $current_site_user_id : null, null)) {
            $stmt = mysqli_prepare($conn, "UPDATE complaints SET status = 'Open', closed_for_customer_at = NULL, updated_at = NOW() WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $ticket_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            create_admin_notification(
                $conn,
                'support_reply',
                'Support conversation updated',
                ($ticket['customer_name'] ?? 'Customer') . ' replied to ' . ($ticket['complaint_code'] ?? ('#' . $ticket_id)) . '.',
                'complaints',
                $ticket_id,
                'complaints_full_crud.php'
            );
            $success_msg = t('support_message_sent');
            $_GET['ticket_id'] = $ticket_id;
        } else {
            $error_msg = t('complaints_error');
        }
    }
}

$support_tickets = [];
if ($current_site_user_id > 0) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT * FROM complaints WHERE user_id = ? AND closed_for_customer_at IS NULL ORDER BY updated_at DESC, created_at DESC, id DESC LIMIT 30"
    );
    mysqli_stmt_bind_param($stmt, "i", $current_site_user_id);
} else {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT * FROM complaints WHERE customer_session_token = ? AND closed_for_customer_at IS NULL ORDER BY updated_at DESC, created_at DESC, id DESC LIMIT 30"
    );
    mysqli_stmt_bind_param($stmt, "s", $customer_session_token);
}

if ($stmt) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        $support_tickets = mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
    mysqli_stmt_close($stmt);
}

$selected_ticket_id = isset($_GET['new']) ? 0 : (int)($_GET['ticket_id'] ?? ($support_tickets[0]['id'] ?? 0));
$selected_ticket = load_customer_support_ticket($conn, $selected_ticket_id, $current_site_user_id, $customer_session_token);
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

include dirname(__DIR__) . '/includes/header.php';
?>

<section class="hero feedback-clean-hero">
    <div class="container">
        <h1><?php echo t('complaints_hero_title'); ?></h1>
        <p><?php echo t('support_page_intro'); ?></p>
    </div>
</section>

<section class="content feedback-clean-content">
    <div class="container">
        <?php if ($success_msg): ?>
            <div class="message success"><?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="message error"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <div class="feedback-clean-layout">
            <aside class="feedback-clean-intro">
                <span class="ticket-label"><?php echo t('footer_support'); ?></span>
                <h2><?php echo t('support_chat_title'); ?></h2>
                <p><?php echo t('support_chat_page_text'); ?></p>
                <?php if (!empty($support_tickets)): ?>
                    <div class="feedback-type-list">
                        <?php foreach ($support_tickets as $ticket): ?>
                            <a class="btn btn-small" href="<?php echo htmlspecialchars(site_url('user/complaints.php?ticket_id=' . (int)$ticket['id']), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($ticket['complaint_code'] ?: ('SUP-' . str_pad($ticket['id'], 6, '0', STR_PAD_LEFT))); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </aside>

            <div class="form-container feedback-clean-form">
                <?php if ($selected_ticket): ?>
                    <h3><?php echo htmlspecialchars($selected_ticket['complaint_code'] ?: ('SUP-' . str_pad($selected_ticket['id'], 6, '0', STR_PAD_LEFT))); ?></h3>
                    <div class="support-thread-messages">
                        <?php foreach ($selected_messages as $chat_message): ?>
                            <?php $is_admin_message = ($chat_message['sender_type'] ?? '') === 'admin'; ?>
                            <article class="support-thread-message <?php echo $is_admin_message ? 'is-admin' : 'is-customer'; ?>">
                                <strong><?php echo htmlspecialchars($is_admin_message ? t('support_admin_reply') : ($selected_ticket['customer_name'] ?? t('common_customer'))); ?></strong>
                                <p><?php echo nl2br(htmlspecialchars($chat_message['message_text'])); ?></p>
                                <span><?php echo htmlspecialchars(date('Y-m-d h:i A', strtotime($chat_message['created_at']))); ?></span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <form method="POST" action="<?php echo htmlspecialchars(site_url('user/complaints.php?ticket_id=' . (int)$selected_ticket['id']), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="support_action" value="reply">
                        <input type="hidden" name="ticket_id" value="<?php echo (int)$selected_ticket['id']; ?>">
                        <div class="form-group">
                            <label class="form-label"><?php echo t('complaints_message'); ?></label>
                            <textarea name="message" class="form-control" required rows="5"></textarea>
                        </div>
                        <button type="submit" class="btn feedback-submit-btn w-100"><?php echo t('support_send_message'); ?></button>
                    </form>
                <?php else: ?>
                    <h3><?php echo htmlspecialchars(t('support_new_conversation'), ENT_QUOTES, 'UTF-8'); ?></h3>
                    <form method="POST" action="<?php echo htmlspecialchars(site_url('user/complaints.php'), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="support_action" value="create">
                        <?php if (!$current_site_user): ?>
                            <div class="form-group">
                                <label class="form-label"><?php echo t('complaints_name'); ?></label>
                                <input type="text" name="customer_name" class="form-control" value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php echo t('auth_email'); ?></label>
                                <input type="email" name="customer_email" class="form-control" value="<?php echo htmlspecialchars($_POST['customer_email'] ?? ''); ?>" required>
                                <small class="booking-time-hint form-text"><?php echo t('support_guest_email_hint'); ?></small>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php echo t('complaints_phone'); ?></label>
                                <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" placeholder="07XXXXXXXX">
                            </div>
                        <?php else: ?>
                            <p class="booking-time-hint form-text">
                                <?php echo htmlspecialchars($current_site_user['full_name'] . ' - ' . $current_site_user['email']); ?>
                            </p>
                        <?php endif; ?>
                        <div class="form-group">
                            <label class="form-label"><?php echo t('complaints_message'); ?></label>
                            <textarea name="message" class="form-control" required rows="7" placeholder="<?php echo htmlspecialchars(t('complaints_message_placeholder'), ENT_QUOTES, 'UTF-8'); ?>"></textarea>
                        </div>
                        <button type="submit" class="btn feedback-submit-btn w-100"><?php echo t('support_send_message'); ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($selected_ticket): ?>
            <div style="margin-top: 1rem;">
                <a class="btn btn-secondary" href="<?php echo htmlspecialchars(site_url('user/complaints.php?new=1'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(t('support_new_conversation'), ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
