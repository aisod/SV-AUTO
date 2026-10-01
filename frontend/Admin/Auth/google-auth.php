<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

/**
 * Google OAuth 2.0 — credentials live in backend/config/google.local.php
 * (see google.local.example.php). Do not hardcode secrets here.
 */

$clientId = GOOGLE_CLIENT_ID;
$clientSecret = GOOGLE_CLIENT_SECRET;
$redirectUri = GOOGLE_REDIRECT_URI;

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

// RFC3986 encodes spaces as %20. The default (RFC1738) uses +, which Google
// compares literally against the registered redirect URI and which resolves to
// a different path when the browser follows it.
$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

// Redirect to Google
header('Location: ' . $authUrl);
exit;
