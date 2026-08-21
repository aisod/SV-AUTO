<?php
ob_start(); // Start output buffering to prevent header errors
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';

// ============================================================================
// GOOGLE OAUTH HANDLING - INITIATION
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'google_register') {
    // Google OAuth credentials (from google.local.php / env)
    $clientId = GOOGLE_CLIENT_ID;
    $clientSecret = GOOGLE_CLIENT_SECRET;
    $redirectUri = defined('GOOGLE_REGISTER_REDIRECT_URI')
        ? GOOGLE_REGISTER_REDIRECT_URI
        : app_url('Admin/register.php');

    if ($clientId === '' || $clientSecret === '') {
        die('Google OAuth is not configured. Copy backend/config/google.local.example.php to google.local.php.');
    }
    
    // Generate state token for CSRF protection
    $_SESSION['google_state'] = bin2hex(random_bytes(16));
    
    // Build Google OAuth URL
    $params = [
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'email profile',
        'state' => $_SESSION['google_state'],
        'access_type' => 'online',
        'prompt' => 'select_account'
    ];
    
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    
    // Redirect to Google
    header('Location: ' . $authUrl);
    exit;
}

// ============================================================================
// GOOGLE OAUTH HANDLING - CALLBACK
// ============================================================================
if (isset($_GET['code']) && isset($_GET['state'])) {
    // Verify state token
    if (!isset($_SESSION['google_state']) || $_GET['state'] !== $_SESSION['google_state']) {
        die('Invalid state parameter. Possible CSRF attack.');
    }
    
    // Google OAuth credentials (from google.local.php / env)
    $clientId = GOOGLE_CLIENT_ID;
    $clientSecret = GOOGLE_CLIENT_SECRET;
    $redirectUri = defined('GOOGLE_REGISTER_REDIRECT_URI')
        ? GOOGLE_REGISTER_REDIRECT_URI
        : app_url('Admin/register.php');
    
    $code = $_GET['code'];
    
    // Exchange code for access token
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $tokenData = [
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code'
    ];
    
    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
    $response = curl_exec($ch);
    curl_close($ch);
    
    $tokenResponse = json_decode($response, true);
    
    if (!isset($tokenResponse['access_token'])) {
        die('Failed to obtain access token from Google.');
    }
    
    $accessToken = $tokenResponse['access_token'];
    
    // Fetch user info from Google
    $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
    $ch = curl_init($userInfoUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
    $userInfoResponse = curl_exec($ch);
    curl_close($ch);
    
    $userInfo = json_decode($userInfoResponse, true);
    
    if (!isset($userInfo['email'])) {
        die('Failed to retrieve user information from Google.');
    }
    
    $googleId = $userInfo['id'];
    $email = $userInfo['email'];
    $firstName = $userInfo['given_name'] ?? '';
    $lastName = $userInfo['family_name'] ?? '';
    
    // Check if user already exists
    $stmt = $pdo->prepare("SELECT id, status FROM clients WHERE email = ? OR google_id = ?");
    $stmt->execute([$email, $googleId]);
    $existingClient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingClient) {
        // Account already exists - redirect to login
        $_SESSION['google_message'] = "An account with this Google email already exists. Please sign in instead.";
        header('Location: ../../login.php');
        exit;
    }
    
    // New Google user - insert into database
    try {
        // Generate random password for Google users
        $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        
        // Insert client with pending status
        $stmt = $pdo->prepare("
            INSERT INTO clients (
                google_id, first_name, last_name, email, password_hash, role, status, created_at
            ) VALUES (?, ?, ?, ?, ?, 'client', 'pending', NOW())
        ");
        
        $stmt->execute([$googleId, $firstName, $lastName, $email, $passwordHash]);
        
        $clientId = $pdo->lastInsertId();
        
        // Set session variables - CRITICAL: include google_new_user
        $_SESSION['user_id'] = (int) $clientId;
        $_SESSION['role_name'] = 'client';
        $_SESSION['first_name'] = $firstName;
        $_SESSION['google_new_user'] = true;  // CRITICAL for complete-profile.php
        
        // Redirect to complete-profile.php
        header('Location: ../Client/complete-profile.php');
        exit;
        
    } catch (Exception $e) {
        die('Registration failed: ' . $e->getMessage());
    }
}

// Redirect if already logged in
if (isset($_SESSION['user_id']) && $_SESSION['role_name'] === 'client') {
    ob_end_clean(); // Clear any output buffer
    header('Location: ../Client/dashboard.php');
    exit;
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Grab and clear any messages
$googleMessage = $_SESSION['google_message'] ?? null;
unset($_SESSION['google_message']);

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Invalid security token. Please try again.";
    } else {
        // Sanitize inputs
        $title = htmlspecialchars(trim($_POST['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $firstName = htmlspecialchars(trim($_POST['first_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $lastName = htmlspecialchars(trim($_POST['last_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $dob = htmlspecialchars(trim($_POST['date_of_birth'] ?? ''), ENT_QUOTES, 'UTF-8');
        $gender = htmlspecialchars(trim($_POST['gender'] ?? ''), ENT_QUOTES, 'UTF-8');
        $idPassport = htmlspecialchars(trim($_POST['id_passport'] ?? ''), ENT_QUOTES, 'UTF-8');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $phone = htmlspecialchars(trim($_POST['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
        $whatsapp = htmlspecialchars(trim($_POST['whatsapp'] ?? ''), ENT_QUOTES, 'UTF-8');
        $address = htmlspecialchars(trim($_POST['address'] ?? ''), ENT_QUOTES, 'UTF-8');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $termsAccepted = isset($_POST['terms_accepted']);
        $googleId = $_POST['google_id'] ?? null;

        // Validation
        if (empty($title) || empty($firstName) || empty($lastName) || empty($email) || empty($phone)) {
            $message = "Please fill in all required fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
        } elseif (!$termsAccepted) {
            $message = "You must agree to the Terms of Service and Privacy Policy.";
        } elseif (empty($googleId) && (empty($password) || empty($confirmPassword))) {
            $message = "Password is required.";
        } elseif (empty($googleId) && $password !== $confirmPassword) {
            $message = "Passwords do not match.";
        } elseif (empty($googleId) && strlen($password) < 8) {
            $message = "Password must be at least 8 characters.";
        } else {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $message = "An account with this email already exists. <a href='../../login.php' style='font-weight:400;text-decoration:underline'>Sign in instead?</a>";
            } else {
                try {
                    // Hash password (random for Google users)
                    $passwordHash = empty($googleId) 
                        ? password_hash($password, PASSWORD_DEFAULT)
                        : password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

                    // Insert client with pending status
                    $stmt = $pdo->prepare("
                        INSERT INTO clients (
                            google_id, title, first_name, last_name, date_of_birth, gender, 
                            id_passport, email, phone, whatsapp, address, password_hash, role, status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'client', 'pending', NOW())
                    ");
                    
                    $stmt->execute([
                        $googleId, $title, $firstName, $lastName, $dob ?: null, $gender,
                        $idPassport, $email, $phone, $whatsapp, $address, $passwordHash
                    ]);
                    
                    $clientId = $pdo->lastInsertId();
                    
                    // Set session
                    $_SESSION['user_id'] = $clientId;
                    $_SESSION['role_name'] = 'client';
                    $_SESSION['title'] = $title;
                    $_SESSION['first_name'] = $firstName;
                    $_SESSION['last_name'] = $lastName;
                    $_SESSION['email'] = $email;
                    $_SESSION['status'] = 'pending';
                    
                    // Redirect to client dashboard (will be redirected to pending page by header check)
                    header('Location: ../Client/dashboard.php');
                    exit;
                    
                } catch (Exception $e) {
                    $message = "Registration failed. Please try again.";
                    error_log("Registration error: " . $e->getMessage());
                }
            }
        }
    }
}

$projectRoot = dirname(__DIR__, 2);

$registerSlideAlts = [
    'assets/images/svlanding.jpeg' => 'SV Auto — fleet and workshop',
    'assets/images/about.jpeg'        => 'SV Auto workshop — service bay',
];
$registerSlides = [];
foreach (array_keys($registerSlideAlts) as $candidate) {
    if (!is_file($projectRoot . '/' . $candidate)) {
        continue;
    }
    $src = '../../' . $candidate;
    $mtime = @filemtime($projectRoot . '/' . $candidate);
    if ($mtime) {
        $src .= '?v=' . $mtime;
    }
    $registerSlides[] = [
        'src' => $src,
        'alt' => $registerSlideAlts[$candidate],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <script src="../../assets/js/site-theme-init.js"></script>
    <title>Create Account | SV Auto Truck Repair</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/site-theme.css?v=6">
    <link rel="stylesheet" href="../../assets/css/auth-pages.css?v=5">
</head>
<body class="auth-register">
    <div class="login-theme-bar">
        <div class="site-theme-toggle" role="group" aria-label="Theme">
            <button type="button" class="site-theme-btn" data-site-theme="light" aria-label="Light mode" title="Light"><i class="fas fa-sun" aria-hidden="true"></i></button>
            <button type="button" class="site-theme-btn" data-site-theme="dark" aria-label="Dark mode" title="Dark"><i class="fas fa-moon" aria-hidden="true"></i></button>
        </div>
    </div>
    <div class="login-page">
        <div class="login-shell login-shell--register">
        <div class="login-card login-card--register">
            <section class="login-form-panel login-form-panel--register">
                <div class="login-form-inner login-form-inner--register">
                    <?php renderAuthLogoLink('../../index.php'); ?>
                    <p class="login-kicker">Windhoek · Namibia</p>
                    <h1>Create your account</h1>
                    <p class="auth-sub">Join SV Auto Truck Repair — Windhoek&rsquo;s trusted fleet &amp; auto experts</p>

                    <div class="reg-stepper" aria-label="Registration progress">
                        <div class="step active" data-step="1">
                            <div class="step-circle">1</div>
                            <span class="step-label">Personal</span>
                        </div>
                        <div class="step-line" aria-hidden="true"></div>
                        <div class="step" data-step="2">
                            <div class="step-circle">2</div>
                            <span class="step-label">Contact</span>
                        </div>
                        <div class="step-line" aria-hidden="true"></div>
                        <div class="step" data-step="3">
                            <div class="step-circle">3</div>
                            <span class="step-label">Security</span>
                        </div>
                    </div>

                <?php if ($googleMessage): ?>
                <div class="alert alert-notice"><?= htmlspecialchars($googleMessage) ?></div>
                <?php endif; ?>

                <?php if ($message): ?>
                <div class="alert alert-error"><?= $message ?></div>
                <?php endif; ?>

                <a href="register.php?action=google_register" class="google-btn">
                    <svg width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Continue with Google
                </a>
                
                <div class="login-divider" role="separator">Or register with email</div>

                <form method="POST" id="registrationForm" novalidate autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                    <div class="form-step active" data-step="1">
                        <div class="form-row">
                            <div class="field">
                                <label>Title <span class="required">*</span></label>
                                <select name="title" required>
                                    <option value="">Select</option>
                                    <option value="Mr">Mr</option>
                                    <option value="Mrs">Mrs</option>
                                    <option value="Ms">Ms</option>
                                    <option value="Dr">Dr</option>
                                    <option value="Prof">Prof</option>
                                    <option value="Eng">Eng</option>
                                    <option value="Rev">Rev</option>
                                </select>
                            </div>
                            <div class="field">
                                <label>Gender <span class="required">*</span></label>
                                <select name="gender" required>
                                    <option value="">Select</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Prefer not to say">Prefer not to say</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="field">
                                <label>First Name <span class="required">*</span></label>
                                <input type="text" name="first_name" placeholder="Enter first name" required autocomplete="given-name">
                            </div>
                            <div class="field">
                                <label>Last Name <span class="required">*</span></label>
                                <input type="text" name="last_name" placeholder="Enter last name" required autocomplete="family-name">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="field">
                                <label>Date of Birth</label>
                                <input type="date" name="date_of_birth" max="<?= date('Y-m-d') ?>" autocomplete="bday">
                            </div>
                            <div class="field">
                                <label>ID / Passport Number</label>
                                <input type="text" name="id_passport" placeholder="Optional" autocomplete="off" autocorrect="off" autocapitalize="characters" spellcheck="false" inputmode="text" data-lpignore="true" data-1p-ignore readonly onfocus="this.removeAttribute('readonly')">
                            </div>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-primary" onclick="nextStep()">Next →</button>
                        </div>
                    </div>

                    <div class="form-step" data-step="2">
                        <div class="field">
                            <label>Email Address <span class="required">*</span></label>
                            <input type="email" name="email" placeholder="your@email.com" required autocomplete="email">
                        </div>
                        <div class="field">
                            <label>Phone Number <span class="required">*</span></label>
                            <input type="tel" name="phone" placeholder="+264 81 234 5678" required autocomplete="tel">
                        </div>
                        <div class="field">
                            <label>WhatsApp Number</label>
                            <input type="tel" name="whatsapp" id="whatsapp" placeholder="+264 81 234 5678">
                            <div class="checkbox-group">
                                <input type="checkbox" id="sameAsPhone" onchange="copyPhone()">
                                <label for="sameAsPhone">Same as phone number</label>
                            </div>
                        </div>
                        <div class="field">
                            <label>Physical Address / Residence</label>
                            <textarea name="address" placeholder="Street, Suburb, City&#10;e.g., 123 Independence Ave, Klein Windhoek, Windhoek"></textarea>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-secondary" onclick="prevStep()">← Back</button>
                            <button type="button" class="btn btn-primary" onclick="nextStep()">Next →</button>
                        </div>
                    </div>

                    <div class="form-step" data-step="3">
                        <div class="field">
                            <label for="password">Password <span class="required">*</span></label>
                            <div class="pw-wrap">
                                <input type="password" name="password" id="password" placeholder="Minimum 8 characters" minlength="8" required>
                                <button type="button" class="pw-toggle" onclick="togglePassword('password', this)" aria-label="Show password">
                                    <i class="fa-regular fa-eye-slash" aria-hidden="true"></i>
                                </button>
                            </div>
                            <span class="field-hint">Minimum 8 characters. Use letters, numbers and symbols for a stronger password.</span>
                            <div id="strength-bar" aria-hidden="true"></div>
                            <small id="strength-text"></small>
                        </div>
                        <div class="field">
                            <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                            <div class="pw-wrap">
                                <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-enter password" minlength="8" required>
                                <button type="button" class="pw-toggle" onclick="togglePassword('confirm_password', this)" aria-label="Show password">
                                    <i class="fa-regular fa-eye-slash" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="terms_accepted" id="terms" required>
                            <label for="terms">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a> <span class="required">*</span></label>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-secondary" onclick="prevStep()">← Back</button>
                            <button type="submit" class="btn btn-primary">Create Account →</button>
                        </div>
                    </div>
                </form>

                    <p class="auth-footer">
                        Already have an account? <a href="../../login.php">Sign in</a>
                    </p>
                    <a href="../../index.php" class="back-home">← Back to home</a>
                </div>
            </section>

            <section class="login-visual-panel" aria-label="Workshop photos">
                <?php if (!empty($registerSlides)): ?>
                <div class="login-visual-frame">
                    <div class="login-slider" id="registerSlider">
                        <div class="login-slider-track" id="registerSliderTrack">
                            <?php foreach ($registerSlides as $i => $slide): ?>
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
                        <p class="login-slide-caption" id="registerSlideCaption"><?= htmlspecialchars($registerSlides[0]['alt'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if (count($registerSlides) > 1): ?>
                        <div class="login-slider-dots" id="registerSliderDots" role="tablist" aria-label="Photo slides">
                            <?php foreach ($registerSlides as $i => $slide): ?>
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
        let currentStep = 1;
        const totalSteps = 3;

        function updateStepUI() {
            document.querySelectorAll('.form-step').forEach(step => {
                step.classList.remove('active');
            });
            document.querySelector(`.form-step[data-step="${currentStep}"]`).classList.add('active');

            document.querySelectorAll('.step').forEach(step => {
                const stepNum = parseInt(step.dataset.step);
                step.classList.remove('active', 'completed');
                if (stepNum === currentStep) {
                    step.classList.add('active');
                } else if (stepNum < currentStep) {
                    step.classList.add('completed');
                }
            });
        }

        function nextStep() {
            if (currentStep < totalSteps) {
                currentStep++;
                updateStepUI();
            }
        }

        function prevStep() {
            if (currentStep > 1) {
                currentStep--;
                updateStepUI();
            }
        }

        function copyPhone() {
            const phone = document.querySelector('input[name="phone"]').value;
            const whatsapp = document.getElementById('whatsapp');
            if (document.getElementById('sameAsPhone').checked) {
                whatsapp.value = phone;
            } else {
                whatsapp.value = '';
            }
        }

        function togglePassword(fieldId, btn) {
            const field = document.getElementById(fieldId);
            const icon = btn.querySelector('i');
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

        document.getElementById('password').addEventListener('input', function() {
            const val = this.value;
            const bar = document.getElementById('strength-bar');
            const text = document.getElementById('strength-text');
            let strength = 0;
            if (val.length >= 8) strength++;
            if (/[A-Z]/.test(val)) strength++;
            if (/[0-9]/.test(val)) strength++;
            if (/[^A-Za-z0-9]/.test(val)) strength++;
            const colors = ['#e74c3c','#e67e22','#f1c40f','#2ecc71'];
            const labels = ['Weak','Fair','Good','Strong'];
            const widths = ['25%','50%','75%','100%'];
            if (val.length > 0) {
                bar.style.width = widths[strength-1] || '25%';
                bar.style.background = colors[strength-1] || '#e74c3c';
                text.textContent = 'Password strength: ' + (labels[strength-1] || 'Weak');
                text.style.color = colors[strength-1] || '#e74c3c';
            } else {
                bar.style.width = '0%';
                text.textContent = '';
            }
        });

        updateStepUI();
    </script>
    
    <script>
    setTimeout(function () {
        document.querySelectorAll('.alert').forEach(function (msg) {
            msg.style.transition = 'opacity .5s';
            msg.style.opacity = '0';
            setTimeout(function () { msg.style.display = 'none'; }, 500);
        });
    }, 6000);

    (function () {
        var track = document.getElementById('registerSliderTrack');
        var dotsWrap = document.getElementById('registerSliderDots');
        if (!track) return;

        var total = track.children.length;
        if (total < 2) return;

        var index = 0;
        var intervalMs = 5500;
        var timer = null;
        var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        var caption = document.getElementById('registerSlideCaption');

        function goTo(i) {
            index = ((i % total) + total) % total;
            track.style.transform = 'translate3d(-' + (index * 100) + '%, 0, 0)';
            if (caption && track.children[index]) {
                caption.textContent = track.children[index].getAttribute('data-caption') || '';
            }
            if (dotsWrap) {
                dotsWrap.querySelectorAll('.login-slider-dot').forEach(function (dot, n) {
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
            if (timer) { clearInterval(timer); timer = null; }
        }

        if (dotsWrap) {
            dotsWrap.querySelectorAll('.login-slider-dot').forEach(function (dot) {
                dot.addEventListener('click', function () {
                    goTo(parseInt(dot.getAttribute('data-index'), 10) || 0);
                    start();
                });
            });
        }

        var slider = document.getElementById('registerSlider');
        if (slider) {
            slider.addEventListener('mouseenter', stop);
            slider.addEventListener('mouseleave', start);
        }

        goTo(0);
        start();
    })();

    (function () {
        var form = document.getElementById('registrationForm');
        if (!form) return;
        form.addEventListener('submit', function () {
            var btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.classList.add('is-loading');
                btn.textContent = 'Creating account…';
            }
        });
    })();
    </script>
    <script src="../../assets/js/site-theme.js?v=1"></script>
</body>
</html>
