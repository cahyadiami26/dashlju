<?php
/**
 * Google OAuth - Callback
 * File: google_callback.php
 *
 * Tugas: validasi state, tukar code -> token, ambil profil Google,
 * cocokkan dengan kolom email di tabel users, buat session, redirect.
 */

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/apps.php';
require_once __DIR__ . '/config/google.php';

function googleLoginFail(string $message): void
{
    // Simpan pesan error lalu kembalikan user ke form login
    $_SESSION['google_login_error'] = $message;
    header('Location: index.php?google_error=1');
    exit;
}

// 0. Pastikan konfigurasi tersedia
if (!isGoogleLoginEnabled()) {
    googleLoginFail('Konfigurasi Google Login belum diisi.');
}

// 1. Error dari Google (user menolak akses, dsb.)
if (!empty($_GET['error'])) {
    error_log('Google OAuth error: ' . $_GET['error']);
    googleLoginFail('Anda membatalkan login dengan Google atau akses ditolak.');
}

$code  = trim($_GET['code'] ?? '');
$state = trim($_GET['state'] ?? '');

if ($code === '' || $state === '') {
    googleLoginFail('Parameter OAuth tidak lengkap.');
}

// 2. Validasi state (anti-CSRF) dan sekali pakai
if (empty($_SESSION['google_oauth_state']) || !hash_equals($_SESSION['google_oauth_state'], $state)) {
    unset($_SESSION['google_oauth_state']);
    googleLoginFail('Sesi verifikasi Google tidak valid. Silakan coba lagi.');
}
unset($_SESSION['google_oauth_state']); // anti replay

// 3. Tukar code dengan token akses
$tokenData = exchangeGoogleCodeForToken($code);
if ($tokenData === false) {
    googleLoginFail('Gagal menghubungi server Google. Silakan coba lagi.');
}

// 4. Ambil profil user dari Google
$profile = fetchGoogleUserInfo($tokenData['access_token']);
if ($profile === false) {
    googleLoginFail('Gagal membaca profil akun Google Anda.');
}

if (!$profile['email_verified']) {
    googleLoginFail('Email akun Google Anda belum terverifikasi.');
}

$googleEmail = $profile['email'];

// 5. Cari user di tabel users berdasarkan kolom email
try {
    $pdo  = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT id, nik, name, email, position, division, department,
               username, role, avatar, photo, signature, rfid, is_active,
               webmail_user, webmail_pass
        FROM users
        WHERE LOWER(email) = :email
        LIMIT 1
    ");
    $stmt->execute(['email' => $googleEmail]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Google Login DB Error: ' . $e->getMessage());
    googleLoginFail('Terjadi kesalahan sistem. Silakan coba lagi.');
}

// 6. Email Google tidak terdaftar di sistem
if (!$user) {
    googleLoginFail('Akun Google ' . $googleEmail . ' tidak terdaftar di sistem. Hubungi administrator.');
}

// 7. Cek status aktif akun
if ((int) $user['is_active'] !== 1) {
    googleLoginFail('Akun Anda telah dinonaktifkan. Hubungi administrator.');
}

// 8. Buat session (sama seperti login manual)
session_regenerate_id(true);

$_SESSION['user_id']      = $user['id'];
$_SESSION['username']     = $user['username'];
$_SESSION['user_name']    = $user['name'];
$_SESSION['user_nik']     = $user['nik'];
$_SESSION['role']         = $user['role'];
$_SESSION['user_email']   = $user['email'];
$_SESSION['department']   = $user['department'];
$_SESSION['division']     = $user['division'];
$_SESSION['position']     = $user['position'];
$_SESSION['avatar']       = $user['avatar'];
$_SESSION['photo']        = $user['photo'];
$_SESSION['signature']    = $user['signature'];
$_SESSION['rfid']         = $user['rfid'];
$_SESSION['webmail_user'] = $user['webmail_user'];
$_SESSION['webmail_pass'] = $user['webmail_pass'];
$_SESSION['login_method'] = 'google';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 9. Catat log aktivitas
try {
    $logStmt = $pdo->prepare("
        INSERT INTO login_logs (user_id, activity, ip_address, user_agent, created_at)
        VALUES (:user_id, 'login_google_success', :ip, :ua, NOW())
    ");
    $logStmt->execute([
        'user_id' => $user['id'],
        'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
        'ua'      => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
} catch (Throwable $e) {
    error_log('Google Login Log Error: ' . $e->getMessage());
}

// 10. Redirect sesuai target (sama dengan logika index.php)
$target_system        = trim($_SESSION['google_target_system'] ?? '');
$redirect_after_login = trim($_SESSION['google_redirect_after_login'] ?? '');
unset($_SESSION['google_target_system'], $_SESSION['google_redirect_after_login']);

$allowed_urls = function_exists('getAllowedRedirectUrls')
    ? getAllowedRedirectUrls()
    : array_values(array_column(getAppsConfig(), 'url'));

if (!empty($target_system) && in_array($target_system, $allowed_urls, true)) {
    if (!empty($redirect_after_login) && $target_system === APP_URL_PM) {
        header('Location: ' . APP_URL_PM . $redirect_after_login);
        exit;
    }
    if ($target_system === APP_URL_PM) {
        header('Location: ' . APP_URL_PM . '/?page=dashboard');
        exit;
    }
    header('Location: ' . $target_system);
    exit;
}

header('Location: index.php');
exit;
