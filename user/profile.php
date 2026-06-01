<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

ensure_user_auth_schema($conn);
$current_site_user = get_current_site_user($conn);

if (!$current_site_user) {
    $_SESSION['post_login_redirect'] = 'user/profile.php';
    header('Location: ' . site_url('general/login.php?redirect=user/profile.php'));
    exit;
}

$site_user_id = (int)$current_site_user['id'];
$page_title = t('profile_title') . ' - FAMOUS GAMING';
$profile_success_msg = '';
$profile_error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['profile_action'] ?? '') === 'update_avatar') {
    $profile_errors = [];

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $profile_errors[] = t('payment_session_expired');
    }

    if (!isset($_FILES['profile_image']) || ($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $profile_errors[] = t('profile_update_error');
    }

    if (empty($profile_errors)) {
        $upload_result = upload_site_user_profile_image($_FILES['profile_image'], $site_user_id);

        if (!$upload_result['success']) {
            $profile_errors[] = $upload_result['message'] ?: t('profile_update_error');
        } else {
            $new_profile_image = $upload_result['file_path'];
            $old_profile_image = $current_site_user['profile_image'] ?? '';
            $avatar_stmt = mysqli_prepare($conn, "UPDATE site_users SET profile_image = ? WHERE id = ?");
            mysqli_stmt_bind_param($avatar_stmt, "si", $new_profile_image, $site_user_id);

            if (mysqli_stmt_execute($avatar_stmt)) {
                if (!empty($old_profile_image) && $old_profile_image !== $new_profile_image) {
                    delete_image($old_profile_image);
                }

                $profile_success_msg = t('profile_update_success');
                clear_current_site_user_cache($site_user_id);
                $current_site_user = get_current_site_user($conn, true);
                $_SESSION['site_user_name'] = $current_site_user['full_name'];
                $_SESSION['site_user_loyalty_points'] = (int)$current_site_user['loyalty_points'];
            } else {
                delete_image($new_profile_image);
                $profile_errors[] = t('profile_update_error');
            }

            mysqli_stmt_close($avatar_stmt);
        }
    }

    if (!empty($profile_errors)) {
        $profile_error_msg = implode('<br>', $profile_errors);
    }
}

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
            clear_current_site_user_cache($site_user_id);
            $current_site_user = get_current_site_user($conn, true);
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

$profile_initial = function_exists('mb_substr') ? mb_substr($current_site_user['full_name'], 0, 1, 'UTF-8') : substr($current_site_user['full_name'], 0, 1);
$profile_avatar_url = site_asset_url($current_site_user['profile_image'] ?? '', '');
$profile_has_avatar = $profile_avatar_url !== '';
$loyalty_settings = get_loyalty_settings($conn);
$loyalty_earn_display = rtrim(rtrim(number_format((float)$loyalty_settings['earn_per_jod'], 2), '0'), '.');
$loyalty_redeem_display = rtrim(rtrim(number_format((float)$loyalty_settings['redeem_points_per_jod'], 2), '0'), '.');

include dirname(__DIR__) . '/includes/header.php';
?>

<main class="user-profile-page">
    <section class="profile-page-hero">
        <div class="container profile-page-hero-inner">
            <form
                method="POST"
                action="<?php echo htmlspecialchars(site_url('user/profile.php'), ENT_QUOTES, 'UTF-8'); ?>"
                enctype="multipart/form-data"
                class="profile-avatar-upload-form"
                id="profileAvatarUploadForm"
            >
                <input type="hidden" name="profile_action" value="update_avatar">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="file" name="profile_image" id="profileAvatarInput" accept="image/png,image/jpeg,image/gif" hidden>
                <button
                    type="button"
                    class="profile-page-avatar profile-page-avatar-button<?php echo $profile_has_avatar ? ' has-image' : ''; ?>"
                    id="profileAvatarTrigger"
                    aria-label="<?php echo htmlspecialchars(t('profile_label'), ENT_QUOTES, 'UTF-8'); ?>"
                    title="<?php echo htmlspecialchars(t('profile_menu_edit'), ENT_QUOTES, 'UTF-8'); ?>"
                >
                    <?php if ($profile_has_avatar): ?>
                        <img src="<?php echo htmlspecialchars($profile_avatar_url, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($current_site_user['full_name'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php else: ?>
                        <?php echo htmlspecialchars($profile_initial, ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                </button>
            </form>
            <div>
                <span class="ticket-label"><?php echo t('nav_profile'); ?></span>
                <h1><?php echo t('profile_title'); ?></h1>
                <p><?php echo t('profile_text'); ?></p>
            </div>
        </div>
    </section>

    <section class="content profile-page-content">
        <div class="container profile-page-grid">
            <div class="profile-page-main">
                <?php if ($profile_success_msg): ?>
                    <div class="message success"><?php echo $profile_success_msg; ?></div>
                <?php endif; ?>

                <?php if ($profile_error_msg): ?>
                    <div class="message error"><?php echo $profile_error_msg; ?></div>
                <?php endif; ?>

                <form method="POST" action="<?php echo htmlspecialchars(site_url('user/profile.php'), ENT_QUOTES, 'UTF-8'); ?>" class="form-container profile-card user-profile-form">
                    <input type="hidden" name="profile_action" value="update_profile">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="profile-card-head">
                        <div>
                            <span class="ticket-label"><?php echo t('profile_label'); ?></span>
                            <h2><?php echo t('profile_menu_edit'); ?></h2>
                            <p><?php echo t('profile_text'); ?></p>
                        </div>
                    </div>
                    <div class="profile-form-grid">
                        <div class="form-group">
                            <label class="form-label"><?php echo t('auth_full_name'); ?></label>
                            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($_POST['full_name'] ?? $current_site_user['full_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo t('auth_email'); ?></label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? $current_site_user['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo t('common_phone'); ?></label>
                            <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($_POST['phone'] ?? ($current_site_user['phone'] ?? '')); ?>" placeholder="07XXXXXXXX">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo t('profile_new_password'); ?></label>
                            <input type="password" name="password" class="form-control" autocomplete="new-password" placeholder="<?php echo htmlspecialchars(t('profile_password_placeholder'), ENT_QUOTES, 'UTF-8'); ?>">
                            <small class="form-text"><?php echo t('profile_new_password_help'); ?></small>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo t('auth_confirm_password'); ?></label>
                            <input type="password" name="confirm_password" class="form-control" autocomplete="new-password">
                        </div>
                        <div class="form-group profile-form-actions">
                            <button type="submit" class="btn"><?php echo t('profile_update_button'); ?></button>
                        </div>
                    </div>
                </form>
            </div>

            <aside class="profile-page-summary">
                <div class="profile-side-card">
                    <span><?php echo t('loyalty_points_short'); ?></span>
                    <strong><?php echo (int)$current_site_user['loyalty_points']; ?></strong>
                    <p><?php echo t('profile_loyalty_text'); ?></p>
                </div>
                <div class="user-loyalty-rules">
                    <b><?php echo t('loyalty_calculation_title'); ?></b>
                    <span><?php echo t('loyalty_calculation_earn', ['points' => $loyalty_earn_display]); ?></span>
                    <span><?php echo t('loyalty_calculation_redeem', ['points' => $loyalty_redeem_display]); ?></span>
                </div>
            </aside>
        </div>
    </section>
</main>

<script>
    (function () {
        const avatarForm = document.getElementById('profileAvatarUploadForm');
        const avatarTrigger = document.getElementById('profileAvatarTrigger');
        const avatarInput = document.getElementById('profileAvatarInput');

        if (!avatarForm || !avatarTrigger || !avatarInput) {
            return;
        }

        avatarTrigger.addEventListener('click', function () {
            avatarInput.click();
        });

        avatarInput.addEventListener('change', function () {
            const file = avatarInput.files && avatarInput.files[0];

            if (!file) {
                return;
            }

            if (typeof URL !== 'undefined' && typeof URL.createObjectURL === 'function' && file.type.indexOf('image/') === 0) {
                const currentImage = avatarTrigger.querySelector('img');
                const previewUrl = URL.createObjectURL(file);

                if (currentImage) {
                    currentImage.src = previewUrl;
                } else {
                    avatarTrigger.textContent = '';
                    const previewImage = document.createElement('img');
                    previewImage.src = previewUrl;
                    previewImage.alt = <?php echo json_encode($current_site_user['full_name'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                    avatarTrigger.appendChild(previewImage);
                }

                avatarTrigger.classList.add('has-image');
            }

            avatarForm.submit();
        });
    })();
</script>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
