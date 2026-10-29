<?php
/**
 * Google OAuth - Initiator
 * File: google_auth.php
 *
 * Tugas: membuat state token (CSRF), menyimpan target redirect ke session,
 * lalu mengarahkan user ke halaman consent Google.
 */

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/google.php';

if (!isGoogleLoginEnabled()) {
    http_response_code(503);
    exit('Konfigurasi Google Login belum diisi. Set GOOGLE_CLIENT_ID dan GOOGLE_CLIENT_SECRET.');
}

// Simpan tujuan redirect setelah login (kompatibel dengan portal)
$_SESSION['google_target_system']        = trim($_GET['target_system'] ?? $_POST['target_system'] ?? '');
$_SESSION['google_redirect_after_login'] = trim($_GET['redirect_after_login'] ?? $_POST['redirect_after_login'] ?? '');

// State token untuk mencegah CSRF pada flow OAuth
$state = bin2hex(random_bytes(32));
$_SESSION['google_oauth_state'] = $state;

header('Location: ' . buildGoogleAuthUrl($state), true, 302);
exit;
