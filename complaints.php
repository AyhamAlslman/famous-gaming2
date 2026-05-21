<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
$page_title = t('complaints_page_title');
include 'includes/header.php';

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_name = mysqli_real_escape_string($conn, $_POST['customer_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);

    if (!empty($customer_name) && !empty($message)) {
        $insert_query = "INSERT INTO complaints (customer_name, phone, message)
                        VALUES ('$customer_name', '$phone', '$message')";

        if (mysqli_query($conn, $insert_query)) {
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
            $success_msg = t('complaints_success');
        } else {
            $error_msg = t('complaints_error');
        }
    } else {
        $error_msg = t('complaints_required');
    }
}
?>

<section class="hero">
    <div class="container">
        <h1><?php echo t('complaints_hero_title'); ?></h1>
        <p><?php echo t('complaints_hero_text'); ?></p>
    </div>
</section>

<section class="content">
    <div class="container">
        <?php if ($success_msg): ?>
            <div class="message success">
                <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="message error">
                <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <div class="feedback-main-container">
            <div class="feedback-intro">
                <p class="feedback-intro-text">
                    <?php echo t('complaints_intro'); ?>
                </p>
            </div>

            <form method="POST" action="" class="form-container">
                <div class="form-group">
                    <label class="form-label"><?php echo t('complaints_name'); ?></label>
                    <input type="text" name="customer_name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label"><?php echo t('complaints_phone'); ?></label>
                    <input type="tel" name="phone" class="form-control" placeholder="07XXXXXXXX">
                    <small class="booking-time-hint form-text"><?php echo t('complaints_phone_hint'); ?></small>
                </div>

                <div class="form-group">
                    <label class="form-label"><?php echo t('complaints_message'); ?></label>
                    <textarea name="message" class="form-control" required rows="6"
                              placeholder="<?php echo htmlspecialchars(t('complaints_message_placeholder'), ENT_QUOTES, 'UTF-8'); ?>"></textarea>
                </div>

                <button type="submit" class="btn feedback-submit-btn w-100">
                    <?php echo t('complaints_submit'); ?>
                </button>
            </form>
        </div>

        <div class="feedback-categories-container">
            <h3 class="feedback-categories-title"><?php echo t('complaints_listen_title'); ?></h3>

            <div class="row g-3 feedback-categories-grid">
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="feedback-category-card h-100">
                        <div class="feedback-category-icon">💬</div>
                        <h4 class="feedback-category-title"><?php echo t('complaints_suggestions'); ?></h4>
                        <p class="feedback-category-text">
                            <?php echo t('complaints_suggestions_text'); ?>
                        </p>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-4">
                    <div class="feedback-category-card h-100">
                        <div class="feedback-category-icon">⚠️</div>
                        <h4 class="feedback-category-title"><?php echo t('complaints_complaints'); ?></h4>
                        <p class="feedback-category-text">
                            <?php echo t('complaints_complaints_text'); ?>
                        </p>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-4">
                    <div class="feedback-category-card h-100">
                        <div class="feedback-category-icon">⭐</div>
                        <h4 class="feedback-category-title"><?php echo t('complaints_compliments'); ?></h4>
                        <p class="feedback-category-text">
                            <?php echo t('complaints_compliments_text'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
