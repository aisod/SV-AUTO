<?php
session_start();

// Unified Login - Works for Admin, Manager, Client
require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/../backend/config/functions.php';

// If already logged in
$alreadyLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['role_name']);
if ($alreadyLoggedIn && !isset($_GET['force'])) {
    $currentRole = ucfirst($_SESSION['role_name']);
    $currentEmail = $_SESSION['email'] ?? 'Unknown';
}

$message = '';

// CSRF token for login form (public page — prevents cross-site login attempts)
if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}
$loginCsrf = $_SESSION['login_csrf'];

// Simple brute-force throttle (session-based; use server/WAF limits in production too)
$maxAttempts = 8;
$lockoutSeconds = 900;
if (!isset($_SESSION['login_throttle']) || !is_array($_SESSION['login_throttle'])) {
    $_SESSION['login_throttle'] = ['count'  => 0, 'locked_until' => 0];
}
$throttle = &$_SESSION['login_throttle'];
$isLockedOut = time() < (int) ($throttle['locked_until'] ?? 0);

if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $message = "Password reset successful! You can now sign in with your new password.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOk = isset($_POST['csrf_token']) && hash_equals($loginCsrf, (string) $_POST['csrf_token']);

    if (!$csrfOk) {
        $message = 'Your session expired. Please refresh the page and try again.';
    } elseif ($isLockedOut) {
        $mins = max(1, (int) ceil(((int) $throttle['locked_until'] - time()) / 60));
        $message = "Too many sign-in attempts. Please wait {$mins} minute(s) and try again.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $message = "Please enter both email and password.";
        } else {
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, u.email, u.password, u.role_id, r.name AS role_name
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.username = ? OR u.email = ?
                LIMIT 1
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['login_throttle'] = ['count' => 0, 'locked_until' => 0];
                $_SESSION['user_id']     = $user['id'];
                $_SESSION['username']    = $user['username'];
                $_SESSION['email']       = $user['email'];
                $_SESSION['role_id']     = $user['role_id'];
                $_SESSION['role_name']   = strtolower($user['role_name']);

                try {
                    $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type) VALUES (?, ?, ?)");
                    $log->execute([$user['id'], 'Login successful', 'auth']);
                } catch (Exception $e) {
                    // Silently fail logging
                }

                header('Location: ' . getRoleRedirect($_SESSION['role_name']));
                exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM clients WHERE email = ? LIMIT 1");
            $stmt->execute([$username]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($client && password_verify($password, $client['password_hash'])) {
                if ($client['status'] === 'blocked') {
                    $message = "Your account has been suspended. Please contact SV Auto Services for assistance.";
                } elseif ($client['status'] === 'pending') {
                    session_regenerate_id(true);
                    $_SESSION['login_throttle'] = ['count' => 0, 'locked_until' => 0];
                    $_SESSION['user_id']    = $client['id'];
                    $_SESSION['role_name']  = 'client';
                    $_SESSION['status']     = 'pending';
                    $_SESSION['first_name'] = $client['first_name'];
                    $_SESSION['email']      = $client['email'];
                    header('Location: Client/pending-approval.php');
                    exit;
                } elseif ($client['status'] === 'approved') {
                    session_regenerate_id(true);
                    $_SESSION['login_throttle'] = ['count' => 0, 'locked_until' => 0];
                    $_SESSION['user_id']    = $client['id'];
                    $_SESSION['role_name']  = 'client';
                    $_SESSION['status']     = 'approved';
                    $_SESSION['title']      = $client['title'];
                    $_SESSION['first_name'] = $client['first_name'];
                    $_SESSION['last_name']  = $client['last_name'];
                    $_SESSION['email']      = $client['email'];
                    header('Location: Client/dashboard.php');
                    exit;
                } else {
                    $message = "Account status error. Please contact support.";
                }
            } else {
                $throttle['count'] = (int) ($throttle['count'] ?? 0) + 1;
                if ($throttle['count'] >= $maxAttempts) {
                    $throttle['locked_until'] = time() + $lockoutSeconds;
                    $throttle['count'] = 0;
                }
                $message = "Invalid email or password.";
            }
        }
    }
}

function getRoleRedirect($roleName) {
    $roleName = strtolower($roleName);

    if (strpos($roleName, 'admin') !== false) {
        return 'Admin/dashboard.php';
    }
    if (strpos($roleName, 'manager') !== false) {
        return 'Manager/manager_dashboard.php';
    }
    if (strpos($roleName, 'tech') !== false) {
        return 'Technician/technician_dashboard.php';
    }
    if (strpos($roleName, 'finance') !== false) {
        return 'Admin/finance_dashboard.php';
    }
    if (strpos($roleName, 'hr') !== false) {
        return 'Hr/hr_dashboard.php';
    }
    if (strpos($roleName, 'client') !== false) {
        return 'Client/dashboard.php';
    }

    return 'Admin/dashboard.php';
}

$loginSlideAlts = [
    'assets/images/svlanding.jpeg' => 'SV Auto — truck in the service bay',
    'assets/images/about.jpeg'        => 'SV Auto workshop — fleet service bay',
];
$loginSlides = [];
foreach (array_keys($loginSlideAlts) as $candidate) {
    if (!is_file(__DIR__ . '/' . $candidate)) {
        continue;
    }
    $src = $candidate;
    $mtime = @filemtime(__DIR__ . '/' . $candidate);
    if ($mtime) {
        $src .= '?v=' . $mtime;
    }
    $loginSlides[] = [
        'src' => $src,
        'alt' => $loginSlideAlts[$candidate],
    ];
}

$isSuccessMessage = isset($_GET['reset']) && $_GET['reset'] === 'success';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <script src="assets/js/site-theme-init.js"></script>
    <title>Sign In | SV Auto Truck Repair</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/site-theme.css?v=6">
    <link rel="stylesheet" href="assets/css/auth-pages.css?v=5">
</head>
<body>
    <div class="login-theme-bar">
        <div class="site-theme-toggle" role="group" aria-label="Theme">
            <button type="button" class="site-theme-btn" data-site-theme="light" aria-label="Light mode" title="Light"><i class="fas fa-sun" aria-hidden="true"></i></button>
            <button type="button" class="site-theme-btn" data-site-theme="dark" aria-label="Dark mode" title="Dark"><i class="fas fa-moon" aria-hidden="true"></i></button>
        </div>
    </div>
    <div class="login-page">
        <div class="login-shell">
        <div class="login-card">
            <section class="login-form-panel">
                <div class="login-form-inner">
                    <?php if ($alreadyLoggedIn): ?>
                    <div class="login-intro">
                        <?php renderAuthLogoLink('index.php'); ?>
                        <p class="login-kicker">Windhoek · Namibia</p>
                        <h1>Welcome back!</h1>
                        <p class="login-register">Signed in as <?= htmlspecialchars($currentEmail) ?> (<?= htmlspecialchars($currentRole) ?>).</p>
                    </div>
                    <div class="login-form-block">
                        <div class="alert alert-success">Continue to your dashboard or sign out to use another account.</div>
                        <a href="<?= htmlspecialchars(getRoleRedirect($_SESSION['role_name']), ENT_QUOTES, 'UTF-8') ?>" class="btn-continue">Continue to dashboard</a>
                        <a href="Admin/Auth/logout.php?redirect=login" class="btn-outline-light">Sign out</a>
                    </div>
                    <div class="login-footer-links">
                        <a href="index.php" class="back-home">← Back to home</a>
                    </div>

                    <?php else: ?>

                    <div class="login-intro">
                        <?php renderAuthLogoLink('index.php'); ?>
                        <p class="login-kicker">Windhoek · Namibia</p>
                        <h1>Welcome back!</h1>
                        <p class="login-register">Don&rsquo;t have an account yet? <a href="Admin/Auth/register.php">Register</a></p>
                        <div class="social-row" aria-label="Sign in with social accounts">
                            <a href="Admin/Auth/google-auth.php" class="social-btn social-btn--google" title="Sign in with Google" aria-label="Google"><span>G</span></a>
                            <span class="social-btn social-btn--disabled" title="Apple sign-in coming soon" aria-hidden="true"><i class="fab fa-apple"></i></span>
                            <span class="social-btn social-btn--disabled" title="Facebook sign-in coming soon" aria-hidden="true"><i class="fab fa-facebook-f"></i></span>
                            <span class="social-btn social-btn--disabled" title="Instagram sign-in coming soon" aria-hidden="true"><i class="fab fa-instagram"></i></span>
                        </div>
                    </div>

                    <div class="login-form-block">
                        <div class="login-divider" role="separator">Or</div>

                        <?php if ($message): ?>
                        <div class="alert <?= $isSuccessMessage ? 'alert-success' : 'alert-error' ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                        <?php endif; ?>

                        <form method="POST" action="" autocomplete="on" novalidate id="loginForm">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($loginCsrf, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="field">
                                <label for="username">Email</label>
                                <input type="email" id="username" name="username" placeholder="E.g. yourname@gmail.com" autocomplete="email" required autofocus<?= $isLockedOut ? ' disabled' : '' ?>>
                            </div>
                            <div class="field">
                                <label for="password">Password</label>
                                <div class="pw-wrap">
                                    <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required<?= $isLockedOut ? ' disabled' : '' ?>>
                                    <button type="button" class="pw-toggle" onclick="togglePassword('password', this)" aria-label="Show password">
                                        <i class="fa-regular fa-eye-slash" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" class="btn-signin-submit"<?= $isLockedOut ? ' disabled' : '' ?>>Sign in</button>
                        </form>
                    </div>

                    <div class="login-footer-links">
                        <a href="Admin/Auth/forgot-password.php" class="forgot-password">Forgot password</a>
                        <a href="index.php" class="back-home">← Back to home</a>
                    </div>

                    <?php endif; ?>
                </div>
            </section>

            <section class="login-visual-panel" aria-label="Workshop photos">
                <?php if (!empty($loginSlides)): ?>
                <div class="login-visual-frame">
                    <div class="login-slider" id="loginSlider">
                        <div class="login-slider-track" id="loginSliderTrack">
                            <?php foreach ($loginSlides as $i => $slide): ?>
                            <div class="login-slide" data-caption="<?= htmlspecialchars($slide['alt'], ENT_QUOTES, 'UTF-8') ?>">
                                <img
                                    src="<?= htmlspecialchars($slide['src'], ENT_QUOTES, 'UTF-8') ?>"
                                    alt="<?= htmlspecialchars($slide['alt'], ENT_QUOTES, 'UTF-8') ?>"
                                    loading="<?= $i === 0 ? 'eager' : 'lazy' ?>"
                                    decoding="async"
                                >
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="login-slide-caption" id="loginSlideCaption"><?= htmlspecialchars($loginSlides[0]['alt'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if (count($loginSlides) > 1): ?>
                        <div class="login-slider-dots" id="loginSliderDots" role="tablist" aria-label="Photo slides">
                            <?php foreach ($loginSlides as $i => $slide): ?>
                            <button
                                type="button"
                                class="login-slider-dot<?= $i === 0 ? ' is-active' : '' ?>"
                                role="tab"
                                aria-label="Slide <?= $i + 1 ?>"
                                aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                                data-index="<?= $i ?>"
                            ></button>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="login-visual-frame login-visual-frame--empty">Workshop image unavailable</div>
                <?php endif; ?>
            </section>
        </div>
        </div>
    </div>

    <script>
    function togglePassword(fieldId, btn) {
        var field = document.getElementById(fieldId);
        var icon = btn.querySelector('i');
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
            btn.setAttribute('aria-label', 'Hide password');
        } else {
            field.type = 'password';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
            btn.setAttribute('aria-label', 'Show password');
        }
    }
    setTimeout(function () {
        document.querySelectorAll('.alert').forEach(function (msg) {
            msg.style.transition = 'opacity .5s';
            msg.style.opacity = '0';
            setTimeout(function () { msg.style.display = 'none'; }, 500);
        });
    }, 6000);

    (function () {
        var track = document.getElementById('loginSliderTrack');
        var dotsWrap = document.getElementById('loginSliderDots');
        if (!track) return;

        var total = track.children.length;
        if (total < 2) return;

        var index = 0;
        var intervalMs = 5500;
        var timer = null;
        var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        var caption = document.getElementById('loginSlideCaption');

        function goTo(i) {
            index = ((i % total) + total) % total;
            track.style.transform = 'translate3d(-' + (index * 100) + '%, 0, 0)';
            if (caption && track.children[index]) {
                caption.textContent = track.children[index].getAttribute('data-caption') || '';
            }
            if (dotsWrap) {
                var dots = dotsWrap.querySelectorAll('.login-slider-dot');
                dots.forEach(function (dot, n) {
                    var on = n === index;
                    dot.classList.toggle('is-active', on);
                    dot.setAttribute('aria-selected', on ? 'true' : 'false');
                });
            }
        }

        function start() {
            if (reducedMotion) return;
            stop();
            timer = setInterval(function () { goTo(index + 1); }, intervalMs);
        }

        function stop() {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        }

        if (dotsWrap) {
            dotsWrap.querySelectorAll('.login-slider-dot').forEach(function (dot) {
                dot.addEventListener('click', function () {
                    goTo(parseInt(dot.getAttribute('data-index'), 10) || 0);
                    start();
                });
            });
        }

        var slider = document.getElementById('loginSlider');
        if (slider) {
            slider.addEventListener('mouseenter', stop);
            slider.addEventListener('mouseleave', start);
        }

        goTo(0);
        start();
    })();

    (function () {
        var form = document.getElementById('loginForm');
        if (!form) return;
        form.addEventListener('submit', function () {
            var btn = form.querySelector('.btn-signin-submit');
            if (btn && !btn.disabled) {
                btn.classList.add('is-loading');
                btn.textContent = 'Signing in…';
            }
        });
    })();
    </script>
    <script src="assets/js/site-theme.js?v=1"></script>
</body>
</html>
