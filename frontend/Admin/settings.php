<?php
session_start();
require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: Auth/login.php');
    exit;
}

$profileMessage = '';
$profileAlertType = '';
$adminUserId = (int) ($_SESSION['user_id'] ?? 0);

erp_ensure_user_avatar_column();
$userProfile = erp_get_user_profile($adminUserId);

if (!$userProfile) {
    header('Location: Auth/logout.php');
    exit;
}

$adminUsername = (string) ($userProfile['username'] ?? 'User');
$adminEmail = (string) ($userProfile['email'] ?? '');
$adminRole = ucfirst((string) ($userProfile['role_name'] ?? 'User'));
$memberSince = !empty($userProfile['created_at'])
    ? date('j M Y', strtotime((string) $userProfile['created_at']))
    : '';

$adminAvatarWeb = erp_get_user_avatar_path($adminUserId);
$adminAvatarSrc = erp_user_avatar_src($adminAvatarWeb, '');
$adminAvatarVer = erp_user_avatar_cache_version($adminAvatarWeb);

$settingsHashByForm = [
    'profile_avatar' => '#my-profile',
    'profile_avatar_remove' => '#my-profile',
    'profile_account' => '#account-details',
    'profile_password' => '#security',
];

if (!empty($_SESSION['settings_flash']) && is_array($_SESSION['settings_flash'])) {
    $profileMessage = (string) ($_SESSION['settings_flash']['message'] ?? '');
    $profileAlertType = (string) ($_SESSION['settings_flash']['type'] ?? 'success');
    unset($_SESSION['settings_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type'])) {
    $formType = (string) $_POST['form_type'];

    if ($formType === 'profile_avatar') {
        $result = erp_save_user_avatar_upload($adminUserId, $_FILES['profile_avatar'] ?? []);
        $profileMessage = $result['message'];
        $profileAlertType = $result['ok'] ? 'success' : 'danger';
        if ($result['ok'] && !empty($result['path'])) {
            $adminAvatarWeb = $result['path'];
            $adminAvatarSrc = erp_user_avatar_src($adminAvatarWeb, '');
            $adminAvatarVer = erp_user_avatar_cache_version($adminAvatarWeb);
        }
    } elseif ($formType === 'profile_avatar_remove') {
        $result = erp_remove_user_avatar($adminUserId);
        $profileMessage = $result['message'];
        $profileAlertType = $result['ok'] ? 'success' : 'danger';
        $adminAvatarWeb = null;
        $adminAvatarSrc = null;
        $adminAvatarVer = 0;
    } elseif ($formType === 'profile_account') {
        $result = erp_update_user_profile(
            $adminUserId,
            (string) ($_POST['username'] ?? ''),
            (string) ($_POST['email'] ?? '')
        );
        $profileMessage = $result['message'];
        $profileAlertType = $result['ok'] ? 'success' : 'danger';
        if ($result['ok']) {
            $userProfile = erp_get_user_profile($adminUserId) ?: $userProfile;
            $adminUsername = (string) ($userProfile['username'] ?? $adminUsername);
            $adminEmail = (string) ($userProfile['email'] ?? $adminEmail);
            $_SESSION['username'] = $adminUsername;
        }
    } elseif ($formType === 'profile_password') {
        $result = erp_change_user_password(
            $adminUserId,
            (string) ($_POST['current_password'] ?? ''),
            (string) ($_POST['new_password'] ?? ''),
            (string) ($_POST['confirm_password'] ?? '')
        );
        $profileMessage = $result['message'];
        $profileAlertType = $result['ok'] ? 'success' : 'danger';
    }

    $_SESSION['settings_flash'] = [
        'message' => $profileMessage,
        'type' => $profileAlertType,
    ];
    $redirectHash = $settingsHashByForm[$formType] ?? '#my-profile';
    header('Location: settings.php' . $redirectHash);
    exit;
}

include 'includes/header.php';
?>

<div class="erp-page-header erp-profile-page-header">
    <div class="erp-page-header-main">
        <h1 class="erp-page-title">Settings</h1>
        <p class="erp-page-subtitle">Your account — profile photo, sign-in details, and password.</p>
    </div>
</div>

<nav class="erp-profile-nav" role="tablist" aria-label="Settings sections">
    <a href="settings.php#my-profile" class="erp-profile-nav-link is-active" role="tab" id="tab-my-profile" aria-controls="my-profile" aria-selected="true">Profile photo</a>
    <a href="settings.php#account-details" class="erp-profile-nav-link" role="tab" id="tab-account-details" aria-controls="account-details" aria-selected="false">Account details</a>
    <a href="settings.php#security" class="erp-profile-nav-link" role="tab" id="tab-security" aria-controls="security" aria-selected="false">Password</a>
</nav>

<?php if ($profileMessage !== ''): ?>
    <div class="erp-alert erp-alert-<?php echo htmlspecialchars($profileAlertType, ENT_QUOTES, 'UTF-8'); ?> erp-mb-4" role="status">
        <div class="erp-alert-icon">
            <i class="fas fa-<?php echo $profileAlertType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        </div>
        <div class="erp-alert-content">
            <div class="erp-alert-title"><?php echo $profileAlertType === 'success' ? 'Success' : 'Error'; ?></div>
            <?php echo htmlspecialchars($profileMessage, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    </div>
<?php endif; ?>

<div class="erp-profile-panels">

    <section class="erp-card erp-profile-card erp-profile-panel is-active" id="my-profile" role="tabpanel" aria-labelledby="tab-my-profile profile-photo-heading" tabindex="0">
        <div class="erp-card-header">
            <h2 class="erp-card-title" id="profile-photo-heading"><i class="fas fa-user-circle"></i> Profile photo</h2>
        </div>
        <div class="erp-card-body">
            <div class="erp-profile-photo-block">
                <div class="erp-profile-upload-preview<?php echo $adminAvatarSrc ? ' erp-profile-upload-preview--photo' : ''; ?>" aria-hidden="true">
                    <?php if ($adminAvatarSrc): ?>
                        <img src="<?php echo htmlspecialchars($adminAvatarSrc, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo $adminAvatarVer; ?>" alt="Profile photo for <?php echo htmlspecialchars($adminUsername, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php else: ?>
                        <span class="erp-profile-upload-initial"><?php echo htmlspecialchars(erp_user_avatar_initial($adminUsername), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="erp-profile-photo-details">
                    <p class="erp-profile-upload-name"><?php echo htmlspecialchars($adminUsername, ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="erp-profile-upload-role"><?php echo htmlspecialchars($adminRole, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php if ($memberSince !== ''): ?>
                        <p class="erp-profile-upload-meta-line"><i class="fas fa-calendar-alt"></i> Member since <?php echo htmlspecialchars($memberSince, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                    <p class="erp-profile-upload-hint">JPG, PNG, GIF, or WebP · max 2MB. This photo appears in the top header on every admin page.</p>
                    <div class="erp-profile-upload-actions">
                        <form method="POST" enctype="multipart/form-data" class="erp-profile-upload-form">
                            <input type="hidden" name="form_type" value="profile_avatar">
                            <input type="file" name="profile_avatar" id="profileAvatarFile" accept="image/jpeg,image/png,image/gif,image/webp" class="erp-sr-only">
                            <button type="button" class="erp-btn erp-btn-primary" onclick="document.getElementById('profileAvatarFile').click()">
                                <i class="fas fa-camera"></i> Upload photo
                            </button>
                            <button type="submit" class="erp-btn erp-btn-secondary" id="profileAvatarSubmit" hidden>Save photo</button>
                        </form>
                        <?php if ($adminAvatarSrc): ?>
                        <form method="POST" class="erp-profile-remove-form" onsubmit="return confirm('Remove your profile photo?');">
                            <input type="hidden" name="form_type" value="profile_avatar_remove">
                            <button type="submit" class="erp-btn erp-btn-ghost erp-btn-sm">Remove photo</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

        <section class="erp-card erp-profile-card erp-profile-panel" id="account-details" role="tabpanel" aria-labelledby="tab-account-details account-details-heading" tabindex="0" hidden>
            <div class="erp-card-header">
                <h2 class="erp-card-title" id="account-details-heading"><i class="fas fa-id-card"></i> Account details</h2>
            </div>
            <div class="erp-card-body">
                <form method="POST" class="erp-profile-form">
                    <input type="hidden" name="form_type" value="profile_account">
                    <div class="erp-profile-form-grid">
                        <div class="erp-profile-form-field erp-profile-form-field--full">
                            <label class="erp-label" for="profileUsername">Display name</label>
                            <input type="text" id="profileUsername" name="username" class="erp-input" required maxlength="50"
                                   value="<?php echo htmlspecialchars($adminUsername, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="erp-profile-form-field erp-profile-form-field--full">
                            <label class="erp-label" for="profileEmail">Email</label>
                            <input type="email" id="profileEmail" name="email" class="erp-input" required
                                   value="<?php echo htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8'); ?>">
                            <p class="erp-profile-field-hint">Used to sign in and for system notifications.</p>
                        </div>
                        <div class="erp-profile-form-field">
                            <label class="erp-label" for="profileRole">Role</label>
                            <input type="text" id="profileRole" class="erp-input" value="<?php echo htmlspecialchars($adminRole, ENT_QUOTES, 'UTF-8'); ?>" disabled>
                            <p class="erp-profile-field-hint">Contact a super admin to change your role.</p>
                        </div>
                    </div>
                    <button type="submit" class="erp-btn erp-btn-primary">
                        <i class="fas fa-save"></i> Save account details
                    </button>
                </form>
            </div>
        </section>

        <section class="erp-card erp-profile-card erp-profile-panel" id="security" role="tabpanel" aria-labelledby="tab-security security-heading" tabindex="0" hidden>
            <div class="erp-card-header">
                <h2 class="erp-card-title" id="security-heading"><i class="fas fa-lock"></i> Password</h2>
            </div>
            <div class="erp-card-body">
                <p class="erp-profile-panel-intro">
                    Change your password while signed in. Locked out?
                    <a href="Auth/forgot-password.php">Reset via email</a>.
                </p>
                <form method="POST" class="erp-profile-form" autocomplete="off">
                    <input type="hidden" name="form_type" value="profile_password">
                    <div class="erp-profile-form-grid erp-profile-form-grid--single">
                        <div class="erp-profile-form-field">
                            <label class="erp-label" for="currentPassword">Current password</label>
                            <input type="password" id="currentPassword" name="current_password" class="erp-input" autocomplete="current-password" required>
                        </div>
                        <div class="erp-profile-form-field">
                            <label class="erp-label" for="newPassword">New password</label>
                            <input type="password" id="newPassword" name="new_password" class="erp-input" autocomplete="new-password" required minlength="8">
                        </div>
                        <div class="erp-profile-form-field">
                            <label class="erp-label" for="confirmPassword">Confirm new password</label>
                            <input type="password" id="confirmPassword" name="confirm_password" class="erp-input" autocomplete="new-password" required minlength="8">
                            <p class="erp-profile-field-hint">Minimum 8 characters.</p>
                        </div>
                    </div>
                    <button type="submit" class="erp-btn erp-btn-primary">
                        <i class="fas fa-key"></i> Update password
                    </button>
                </form>
            </div>
        </section>

</div>

<script>
(function () {
    const fileInput = document.getElementById('profileAvatarFile');
    const submitBtn = document.getElementById('profileAvatarSubmit');
    if (fileInput && submitBtn) {
        fileInput.addEventListener('change', function () {
            if (fileInput.files && fileInput.files.length > 0) {
                submitBtn.hidden = false;
                submitBtn.click();
            }
        });
    }

    const navLinks = document.querySelectorAll('.erp-profile-nav-link');
    const panels = document.querySelectorAll('.erp-profile-panel');
    const validHashes = ['#my-profile', '#account-details', '#security'];

    function navHashFromHref(href) {
        if (!href) return '#my-profile';
        const i = href.indexOf('#');
        return i >= 0 ? href.slice(i) : '#my-profile';
    }

    function panelIdFromHash(hash) {
        const h = hash && validHashes.indexOf(hash) >= 0 ? hash : '#my-profile';
        return h.slice(1);
    }

    function showPanel(hash, scrollToPanel) {
        const panelId = panelIdFromHash(hash);
        panels.forEach(function (panel) {
            const active = panel.id === panelId;
            panel.classList.toggle('is-active', active);
            panel.hidden = !active;
        });
        navLinks.forEach(function (link) {
            const linkHash = navHashFromHref(link.getAttribute('href'));
            const active = linkHash === '#' + panelId;
            link.classList.toggle('is-active', active);
            link.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        if (scrollToPanel) {
            const panel = document.getElementById(panelId);
            if (panel) {
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    }

    navLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
            const hash = navHashFromHref(link.getAttribute('href'));
            if (window.location.pathname.indexOf('settings.php') >= 0) {
                e.preventDefault();
                if (window.location.hash !== hash) {
                    history.pushState(null, '', 'settings.php' + hash);
                }
                showPanel(hash, false);
            }
        });
    });

    window.addEventListener('hashchange', function () {
        showPanel(window.location.hash, false);
    });

    showPanel(window.location.hash, false);
})();
</script>

<?php include 'includes/footer.php'; ?>
