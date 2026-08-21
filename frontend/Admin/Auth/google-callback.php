<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

// Google OAuth credentials (from google.local.php / env)
$clientId = GOOGLE_CLIENT_ID;
$clientSecret = GOOGLE_CLIENT_SECRET;
$redirectUri = GOOGLE_REDIRECT_URI;

if ($clientId === '' || $clientSecret === '') {
    die('Google OAuth is not configured. Copy backend/config/google.local.example.php to google.local.php.');
}

// Verify state token
if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['google_state']) {
    die('Invalid state parameter. Possible CSRF attack.');
}

// Check for authorization code
if (!isset($_GET['code'])) {
    die('Authorization code not received.');
}

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
    echo '<h2>Failed to obtain access token</h2>';
    echo '<h3>Debug Information:</h3>';
    echo '<pre>';
    echo 'Response from Google: ' . print_r($tokenResponse, true);
    echo "\n\nRedirect URI used: " . $redirectUri;
    echo "\n\nMake sure this EXACT URL is added to Google Cloud Console:";
    echo "\nAuthorized redirect URIs → Add URI → " . $redirectUri;
    echo '</pre>';
    die();
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
    die('Failed to retrieve user information.');
}

$googleId = $userInfo['id'];
$email = $userInfo['email'];
$firstName = $userInfo['given_name'] ?? '';
$lastName = $userInfo['family_name'] ?? '';

// Check if user already exists
$stmt = $pdo->prepare("SELECT id, title, first_name, last_name, phone, status FROM clients WHERE email = ? OR google_id = ?");
$stmt->execute([$email, $googleId]);
$existingClient = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existingClient) {
    // Always save google_id
    $pdo->prepare("UPDATE clients SET google_id = ? WHERE id = ?")->execute([$googleId, $existingClient['id']]);
    
    // FIRST: Check status
    if ($existingClient['status'] === 'pending') {
        $_SESSION['user_id']    = $existingClient['id'];
        $_SESSION['role_name']  = 'client';
        $_SESSION['status']     = 'pending';
        $_SESSION['first_name'] = $existingClient['first_name'];
        $_SESSION['email']      = $email;
        header('Location: ../Client/pending-approval.php');
        exit;
    }
    
    // Check if account is blocked
    if ($existingClient['status'] === 'blocked') {
        $_SESSION['google_message'] = "Your account has been suspended. Please contact SV Auto Services for assistance.";
        session_destroy();
        session_start();
        $_SESSION['google_message'] = "Your account has been suspended. Please contact SV Auto Services for assistance.";
        header('Location: ../Admin/login.php');
        exit;
    }
    
    // SECOND: Check profile complete
    if (empty($existingClient['phone'])) {
        $_SESSION['user_id']    = $existingClient['id'];
        $_SESSION['role_name']  = 'client';
        $_SESSION['status']     = $existingClient['status'];
        $_SESSION['first_name'] = $existingClient['first_name'];
        $_SESSION['email']      = $email;
        header('Location: ../Admin/register.php');
        exit;
    }
    
    // THIRD: All good, grant access
    $_SESSION['user_id']    = $existingClient['id'];
    $_SESSION['role_name']  = 'client';
    $_SESSION['status']     = 'approved';
    $_SESSION['title']      = $existingClient['title'];
    $_SESSION['first_name'] = $existingClient['first_name'];
    $_SESSION['last_name']  = $existingClient['last_name'];
    $_SESSION['email']      = $email;
    header('Location: ../Client/dashboard.php');
    exit;
} else {
    // Brand new Google user — create their account now
    try {
        $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO clients (
                google_id, first_name, last_name, email, password_hash, role, status, created_at
            ) VALUES (?, ?, ?, ?, ?, 'client', 'pending', NOW())
        ");
        
        $stmt->execute([$googleId, $firstName, $lastName, $email, $passwordHash]);
        
        $newClientId = $pdo->lastInsertId();
        
        // Set ALL required session variables
        $_SESSION['user_id']         = (int) $newClientId;
        $_SESSION['role_name']       = 'client';
        $_SESSION['first_name']      = $firstName;
        $_SESSION['status']          = 'pending';
        $_SESSION['email']           = $email;
        $_SESSION['google_new_user'] = true;  // CRITICAL for complete-profile.php
        
        // Send to complete their profile
        header('Location: ../Client/complete-profile.php');
        exit;
        
    } catch (Exception $e) {
        error_log("Google registration error: " . $e->getMessage());
        $_SESSION['google_message'] = "Registration failed. Please try again.";
        header('Location: ../Admin/register.php');
        exit;
    }
}
