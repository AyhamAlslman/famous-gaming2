<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$page_title = t('complaints_page_title');
$success_msg = '';
$error_msg = '';

ensure_user_auth_schema($conn);
$current_site_user = get_current_site_user($conn);
$current_site_user_id = $current_site_user ? (int)$current_site_user['id'] : null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_name = sanitize_input($_POST['customer_name'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $message = sanitize_input($_POST['message'] ?? '');

    if (!empty($customer_name) && !empty($message)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO complaints (user_id, customer_name, phone, message) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isss", $current_site_user_id, $customer_name, $phone, $message);

        if (mysqli_stmt_execute($stmt)) {
            $complaint_id = mysqli_insert_id($conn);
            create_admin_notification(
                $conn,
                'feedback_created',
                'New feedback submitted',
                $customer_name . ' submitted new feedback for review.',
                'complaints',
                $complaint_id,
                'complaints_full_crud.php'
            );
            if ($current_site_user_id) {
                create_site_notification(
                    $conn,
                    $current_site_user_id,
                    'support_sent',
                    t('complaints_success'),
                    t('user_notification_support_sent'),
                    'user/complaints.php'
                );
            }
            $success_msg = t('complaints_success');
        } else {
            $error_msg = t('complaints_error');
        }

        mysqli_stmt_close($stmt);
    } else {
        $error_msg = t('complaints_required');
    }
}

include dirname(__DIR__) . '/includes/header.php';
?>

<section class="hero feedback-clean-hero">
    <div class="container">
        <h1><?php echo t('complaints_hero_title'); ?></h1>
        <p><?php echo t('complaints_hero_text'); ?></p>
    </div>
</section>

<section class="content feedback-clean-content">
    <div class="container">
        <?php if ($success_msg): ?>
            <div class="message success"><?php echo $success_msg; ?></div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="message error"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="feedback-clean-layout">
            <aside class="feedback-clean-intro">
                <span class="ticket-label"><?php echo t('footer_support'); ?></span>
                <h2><?php echo t('complaints_listen_title'); ?></h2>
                <p><?php echo t('complaints_intro'); ?></p>
                <div class="feedback-type-list">
                    <span><?php echo t('complaints_suggestions'); ?></span>
                    <span><?php echo t('complaints_complaints'); ?></span>
                    <span><?php echo t('complaints_compliments'); ?></span>
                </div>
            </aside>

            <form method="POST" action="<?php echo htmlspecialchars(site_url('user/complaints.php'), ENT_QUOTES, 'UTF-8'); ?>" class="form-container feedback-clean-form">
                <div class="form-group">
                    <label class="form-label"><?php echo t('complaints_name'); ?></label>
                    <input type="text" name="customer_name" class="form-control" value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ($current_site_user['full_name'] ?? '')); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label"><?php echo t('complaints_phone'); ?></label>
                    <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($_POST['phone'] ?? ($current_site_user['phone'] ?? '')); ?>" placeholder="07XXXXXXXX">
                    <small class="booking-time-hint form-text"><?php echo t('complaints_phone_hint'); ?></small>
                </div>

                <div class="form-group">
                    <label class="form-label"><?php echo t('complaints_message'); ?></label>
                    <textarea name="message" class="form-control" required rows="7" placeholder="<?php echo htmlspecialchars(t('complaints_message_placeholder'), ENT_QUOTES, 'UTF-8'); ?>"></textarea>
                </div>

                <button type="submit" class="btn feedback-submit-btn w-100"><?php echo t('complaints_submit'); ?></button>
            </form>
        </div>
    </div>
</section>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
