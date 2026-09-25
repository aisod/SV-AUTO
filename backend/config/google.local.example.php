<?php
/**
 * Google OAuth credentials (copy to google.local.php — do not commit the real file).
 *
 * Only Client ID and Secret go here. Redirect URIs are auto-detected in config.php.
 *
 * In Google Cloud Console (OAuth 2.0 Web client), add ALL redirect URIs you use:
 *
 * Production (https://svautotruckrepair.com/ — site at domain root, no subfolder):
 *   https://svautotruckrepair.com/Admin/Auth/google-callback.php
 *   https://svautotruckrepair.com/Admin/Auth/register.php
 *
 * Local XAMPP (folder "SV Auto Truck Repair"):
 *   http://localhost/SV Auto Truck Repair/Admin/Auth/google-callback.php
 *   http://localhost/SV Auto Truck Repair/Admin/Auth/register.php
 */
define('GOOGLE_CLIENT_ID', 'your-client-id.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'your-client-secret');
