<?php
/**
 * Konfigurasi Google OAuth 2.0 (Login with Google)
 * File: config/google.php
 *
 * Cara pakai:
 * 1. Buka https://console.cloud.google.com/apis/credentials
 * 2. Buat "OAuth client ID" tipe "Web application"
 * 3. Tambahkan Authorized redirect URI:
 *      https://lju.co.id/dashboard/google_callback.php
 * 4. Salin Client ID & Client Secret ke file ini
 *    (disarankan lewat environment variable, bukan hardcode)
 */

// Muat kredensial dari file .env (jika ada) — tanpa library eksternal
require_once __DIR__ . '/env.php';

// Ambil dari environment variable / .env jika ada, jika tidak pakai nilai default kosong.
define('GOOGLE_CLIENT_ID',     env('GOOGLE_CLIENT_ID',     ''));
define('GOOGLE_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET', ''));

// Harus SAMA persis dengan Authorized redirect URI di Google Cloud Console
if (!defined('APP_URL_PORTAL')) {
    // URL dasar portal SSO ini (sesuaikan jika path deployment berbeda)
    define('APP_URL_PORTAL', 'https://lju.co.id/dashboard');
}

// Redirect URI bisa dioverride lewat environment variable,
// HARUS SAMA PERSIS dengan Authorized redirect URI di Google Cloud Console.
define('GOOGLE_REDIRECT_URI', env('GOOGLE_REDIRECT_URI')
    ?: rtrim(APP_URL_PORTAL, '/') . '/google_callback.php');

define('GOOGLE_AUTH_ENDPOINT', 'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_ENDPOINT', 'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_ENDPOINT', 'https://openidconnect.googleapis.com/v1/userinfo');
define('GOOGLE_SCOPES', 'openid email profile');

/**
 * Cek apakah konfigurasi Google Login sudah diisi
 */
function isGoogleLoginEnabled(): bool
{
    return !empty(GOOGLE_CLIENT_ID) && !empty(GOOGLE_CLIENT_SECRET);
}

/**
 * Bangun URL halaman consent Google
 */
function buildGoogleAuthUrl(string $state): string
{
    $params = [
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => GOOGLE_SCOPES,
        'state'         => $state,
        'access_type'   => 'online',
        'prompt'        => 'select_account',
    ];

    return GOOGLE_AUTH_ENDPOINT . '?' . http_build_query($params);
}

/**
 * Tukar authorization code dengan token akses Google
 *
 * @return array|false ['access_token' => ..., 'id_token' => ...] atau false saat gagal
 */
function exchangeGoogleCodeForToken(string $code)
{
    $postData = http_build_query([
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ]);

    $ch = curl_init(GOOGLE_TOKEN_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postData,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        error_log('Google token exchange failed. HTTP: ' . $httpCode . ' ERR: ' . $curlErr . ' RESP: ' . $response);
        return false;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || empty($data['access_token'])) {
        error_log('Google token exchange: access_token tidak ditemukan. RESP: ' . $response);
        return false;
    }

    return $data;
}

/**
 * Ambil profil user (email, nama, picture) dari endpoint userinfo Google
 *
 * @return array|false ['email','name','picture','email_verified'] atau false saat gagal
 */
function fetchGoogleUserInfo(string $accessToken)
{
    $ch = curl_init(GOOGLE_USERINFO_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        error_log('Google userinfo failed. HTTP: ' . $httpCode . ' RESP: ' . $response);
        return false;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || empty($data['email'])) {
        return false;
    }

    return [
        'email'          => strtolower(trim($data['email'])),
        'name'           => $data['name'] ?? '',
        'picture'        => $data['picture'] ?? '',
        'email_verified' => !empty($data['email_verified']),
    ];
}
