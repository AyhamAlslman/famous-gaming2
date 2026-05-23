<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

ensure_user_auth_schema($conn);
$current_site_user = get_current_site_user($conn);

if (!$current_site_user) {
    $_SESSION['post_login_redirect'] = 'user/user_dashboard.php';
    header('Location: ' . site_url('general/login.php?redirect=user/user_dashboard.php'));
    exit;
}

$site_user_id = (int)$current_site_user['id'];
$page_title = t('user_dashboard_page_title');
$profile_success_msg = '';
$profile_error_msg = '';
$feedback_success_msg = '';
$feedback_error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['profile_action'] ?? '') === 'update_profile') {
    $profile_name = sanitize_input($_POST['full_name'] ?? '');
    $profile_email = sanitize_input($_POST['email'] ?? '');
    $profile_phone = sanitize_input($_POST['phone'] ?? '');
    $profile_password = (string)($_POST['password'] ?? '');
    $profile_confirm_password = (string)($_POST['confirm_password'] ?? '');
    $profile_errors = [];

    if ($profile_name === '') {
        $profile_errors[] = t('profile_name_required');
    }

    if (!validate_email($profile_email)) {
        $profile_errors[] = t('profile_email_invalid');
    }

    if ($profile_phone !== '' && !validate_phone($profile_phone)) {
        $profile_errors[] = t('profile_phone_invalid');
    }

    if ($profile_password !== '' || $profile_confirm_password !== '') {
        if (strlen($profile_password) < 6) {
            $profile_errors[] = t('auth_password_short');
        }

        if ($profile_password !== $profile_confirm_password) {
            $profile_errors[] = t('auth_password_mismatch');
        }
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $profile_errors[] = t('payment_session_expired');
    }

    if (empty($profile_errors)) {
        $email_stmt = mysqli_prepare($conn, "SELECT id FROM site_users WHERE email = ? AND id != ? LIMIT 1");
        mysqli_stmt_bind_param($email_stmt, "si", $profile_email, $site_user_id);
        mysqli_stmt_execute($email_stmt);
        $email_result = mysqli_stmt_get_result($email_stmt);
        $email_exists = $email_result && mysqli_fetch_assoc($email_result);
        mysqli_stmt_close($email_stmt);

        if ($email_exists) {
            $profile_errors[] = t('profile_email_exists');
        }
    }

    if (empty($profile_errors)) {
        if ($profile_password !== '') {
            $password_hash = password_hash($profile_password, PASSWORD_DEFAULT);
            $update_stmt = mysqli_prepare($conn, "UPDATE site_users SET full_name = ?, email = ?, phone = ?, password = ? WHERE id = ?");
            mysqli_stmt_bind_param($update_stmt, "ssssi", $profile_name, $profile_email, $profile_phone, $password_hash, $site_user_id);
        } else {
            $update_stmt = mysqli_prepare($conn, "UPDATE site_users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
            mysqli_stmt_bind_param($update_stmt, "sssi", $profile_name, $profile_email, $profile_phone, $site_user_id);
        }

        if (mysqli_stmt_execute($update_stmt)) {
            $profile_success_msg = t('profile_update_success');
            $current_site_user = get_current_site_user($conn);
            $_SESSION['site_user_name'] = $current_site_user['full_name'];
            $_SESSION['site_user_loyalty_points'] = (int)$current_site_user['loyalty_points'];
        } else {
            $profile_error_msg = t('profile_update_error');
        }

        mysqli_stmt_close($update_stmt);
    }

    if (!empty($profile_errors)) {
        $profile_error_msg = implode('<br>', $profile_errors);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['profile_action'] ?? '') === 'dashboard_feedback') {
    $feedback_message = sanitize_input($_POST['feedback_message'] ?? '');

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $feedback_error_msg = t('payment_session_expired');
    } elseif ($feedback_message === '') {
        $feedback_error_msg = t('complaints_required');
    } else {
        $feedback_stmt = mysqli_prepare($conn, "INSERT INTO complaints (user_id, customer_name, phone, message) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($feedback_stmt, "isss", $site_user_id, $current_site_user['full_name'], $current_site_user['phone'], $feedback_message);

        if (mysqli_stmt_execute($feedback_stmt)) {
            $complaint_id = mysqli_insert_id($conn);
            create_admin_notification(
                $conn,
                'feedback_created',
                'New feedback submitted',
                $current_site_user['full_name'] . ' submitted new dashboard feedback.',
                'complaints',
                $complaint_id,
                'complaints_full_crud.php'
            );
            create_site_notification(
                $conn,
                $site_user_id,
                'support_sent',
                t('complaints_success'),
                t('user_notification_support_sent'),
                'user/complaints.php'
            );
            $feedback_success_msg = t('complaints_success');
        } else {
            $feedback_error_msg = t('complaints_error');
        }

        mysqli_stmt_close($feedback_stmt);
    }
}

$rooms = [];
$rooms_result = mysqli_query($conn, "SELECT id, room_name, room_type, price_per_hour, status, image_path FROM rooms ORDER BY FIELD(status, 'Available', 'Busy'), room_name ASC");
if ($rooms_result) {
    $rooms = mysqli_fetch_all($rooms_result, MYSQLI_ASSOC);
}

$loyalty_settings = get_loyalty_settings($conn);
$loyalty_earn_display = rtrim(rtrim(number_format((float)$loyalty_settings['earn_per_jod'], 2), '0'), '.');
$loyalty_redeem_display = rtrim(rtrim(number_format((float)$loyalty_settings['redeem_points_per_jod'], 2), '0'), '.');
$dashboard_initial = function_exists('mb_substr') ? mb_substr($current_site_user['full_name'], 0, 1, 'UTF-8') : substr($current_site_user['full_name'], 0, 1);
$dashboard_room_cards = $rooms;
$available_rooms_count = count(array_filter($rooms, static function ($room) {
    return ($room['status'] ?? '') === 'Available';
}));

include dirname(__DIR__) . '/includes/header.php';
?>

<main class="user-dashboard-v2" id="dashboard-home">
    <section class="user-dashboard-stage">
        <div class="user-dashboard-inner">
            <?php if ($profile_success_msg): ?>
                <div class="message success"><?php echo $profile_success_msg; ?></div>
            <?php endif; ?>

            <?php if ($profile_error_msg): ?>
                <div class="message error"><?php echo $profile_error_msg; ?></div>
            <?php endif; ?>

            <?php if ($feedback_success_msg): ?>
                <div class="message success"><?php echo $feedback_success_msg; ?></div>
            <?php endif; ?>

            <?php if ($feedback_error_msg): ?>
                <div class="message error"><?php echo $feedback_error_msg; ?></div>
            <?php endif; ?>

            <section class="user-dashboard-v2-hero">
                <div class="user-dashboard-v2-avatar"><?php echo htmlspecialchars($dashboard_initial, ENT_QUOTES, 'UTF-8'); ?></div>
                <div>
                    <span><?php echo t('nav_account'); ?></span>
                    <h1><?php echo t('user_dashboard_heading', ['name' => htmlspecialchars($current_site_user['full_name'])]); ?></h1>
                    <p><?php echo t('user_dashboard_subtitle'); ?></p>
                </div>
            </section>

            <div class="user-dashboard-v2-layout web-dashboard-container">
                <div class="user-dashboard-v2-main">
                    <section class="dashboard-v2-card dashboard-v2-rooms" id="dashboard-rooms">
                        <div class="dashboard-v2-card-head">
                            <div>
                                <span><?php echo t('home_rooms_title'); ?></span>
                                <h2><?php echo t('user_dashboard_pick_room'); ?></h2>
                                <p><?php echo t('user_dashboard_pick_room_text'); ?></p>
                            </div>
                            <div class="dashboard-v2-room-count">
                                <strong><?php echo $available_rooms_count; ?></strong>
                                <span><?php echo htmlspecialchars(t('status_available', [], 'Available')); ?></span>
                            </div>
                        </div>

                        <?php if (empty($dashboard_room_cards)): ?>
                            <div class="dashboard-v2-empty"><?php echo t('booking_addons_empty'); ?></div>
                        <?php else: ?>
                            <div class="dashboard-v2-room-grid">
                                <?php foreach ($dashboard_room_cards as $room): ?>
                                    <?php
                                    $room_status_key = strtolower((string)$room['status']);
                                    $is_available = $room['status'] === 'Available';
                                    $room_image = site_asset_url($room['image_path'] ?? '', 'images/home-hero-background.png');
                                    ?>
                                    <article class="dashboard-v2-room-card">
                                        <div class="dashboard-v2-room-main">
                                            <img src="<?php echo htmlspecialchars($room_image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($room['room_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <div>
                                                <span><?php echo htmlspecialchars($room['room_type']); ?></span>
                                                <h3><?php echo htmlspecialchars($room['room_name']); ?></h3>
                                                <em class="<?php echo $is_available ? 'is-live' : 'is-busy'; ?>"><?php echo htmlspecialchars(t('status_' . $room_status_key, [], $room['status'])); ?></em>
                                            </div>
                                        </div>
                                        <?php if ($is_available): ?>
                                            <a href="<?php echo htmlspecialchars(site_url('user/room_booking.php?room_id=' . (int)$room['id'] . '#booking-form'), ENT_QUOTES, 'UTF-8'); ?>" class="dashboard-v2-book-btn"><?php echo t('home_book_room'); ?></a>
                                        <?php else: ?>
                                            <span class="dashboard-v2-hold"><?php echo number_format((float)$room['price_per_hour'], 2); ?> <?php echo t('home_room_price_suffix'); ?></span>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="dashboard-v2-card dashboard-v2-quick-links">
                        <div class="dashboard-v2-card-head">
                            <div>
                                <span><?php echo t('nav_account'); ?></span>
                                <h2><?php echo t('common_actions'); ?></h2>
                            </div>
                        </div>
                        <div class="dashboard-v2-link-grid">
                            <a href="<?php echo htmlspecialchars(site_url('user/my_bookings.php'), ENT_QUOTES, 'UTF-8'); ?>">
                                <div>
                                    <strong><?php echo t('nav_my_bookings'); ?></strong>
                                    <span><?php echo t('user_dashboard_history_text'); ?></span>
                                </div>
                            </a>
                            <a href="<?php echo htmlspecialchars(site_url('user/store.php'), ENT_QUOTES, 'UTF-8'); ?>">
                                <div>
                                    <strong><?php echo t('user_dashboard_store'); ?></strong>
                                    <span><?php echo t('user_dashboard_store_text'); ?></span>
                                </div>
                            </a>
                            <a href="<?php echo htmlspecialchars(site_url('user/menu.php'), ENT_QUOTES, 'UTF-8'); ?>">
                                <div>
                                    <strong><?php echo t('dashboard_menu_title'); ?></strong>
                                    <span><?php echo t('dashboard_menu_text'); ?></span>
                                </div>
                            </a>
                            <a href="<?php echo htmlspecialchars(site_url('user/profile.php'), ENT_QUOTES, 'UTF-8'); ?>">
                                <div>
                                    <strong><?php echo t('profile_menu_edit'); ?></strong>
                                    <span><?php echo t('profile_loyalty_text'); ?></span>
                                </div>
                            </a>
                        </div>
                    </section>

                    <section class="dashboard-v2-card dashboard-v2-loyalty">
                        <div class="dashboard-v2-card-head">
                            <div>
                                <span><?php echo t('loyalty_points'); ?></span>
                                <h2><?php echo (int)$current_site_user['loyalty_points']; ?></h2>
                                <p><?php echo t('loyalty_calculation_earn', ['points' => $loyalty_earn_display]); ?></p>
                            </div>
                            <a href="<?php echo htmlspecialchars(site_url('user/my_bookings.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('common_view'); ?></a>
                        </div>
                    </section>
                </div>

            </div>
        </div>
    </section>
</main>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
