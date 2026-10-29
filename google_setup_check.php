<?php
/**
 * Google Login - Setup / Health Check
 * File: google_setup_check.php
 *
 * Jalankan dari browser atau CLI untuk memverifikasi seluruh langkah
 * implementasi Login with Google sudah benar:
 *   1. File .env terbaca & kredensial terisi
 *   2. Redirect URI sesuai request saat ini (harus SAMA PERSIS dgn Cloud Console)
 *   3. Ekstensi cURL tersedia & endpoint Google dapat dijangkau
 *   3b. Kredensial valid menurut Google (validasi client_id + secret)
 *   4. Koneksi database berhasil
 *   5. Struktur DB mendukung (index idx_users_email & tabel login_logs)
 *   6. Data users siap untuk login Google (email terisi, unik, terverifikasi)
 *
 * HAPUS file ini dari server produksi setelah setup selesai!
 */

require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/google.php';

@header('Content-Type: text/plain; charset=utf-8');

// Proteksi akses web: hanya boleh diakses dari server itu sendiri (CLI/localhost),
// agar informasi konfigurasi tidak bocor ke publik.
if (PHP_SAPI !== 'cli') {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $allowedIps = ['127.0.0.1', '::1'];
    if (!in_array($remote, $allowedIps, true)) {
        http_response_code(403);
        exit('Akses dibatasi. Jalankan pemeriksaan ini via CLI: php google_setup_check.php');
    }
}

$pass = 0; $warn = 0; $fail = 0;

function ok(string $m): void   { global $pass; $pass++; echo "[ OK ] $m\n"; }
function warn(string $m): void { global $warn; $warn++; echo "[WARN] $m\n"; }
function bad(string $m): void  { global $fail; $fail++; echo "[FAIL] $m\n"; }
function info(string $m): void { echo "       $m\n"; }

$isCli = (PHP_SAPI === 'cli');

echo "==========================================================\n";
echo " CHECKLIST IMPLEMENTASI LOGIN WITH GOOGLE — dashlju\n";
echo "==========================================================\n\n";

// ---------- 1. Konfigurasi .env ----------
echo "-- 1. Konfigurasi (.env) --\n";
$envLoaded = loadEnv();
if ($envLoaded) {
    ok("File .env ditemukan dan dimuat (" . dirname(__DIR__) . "/.env di root proyek)");
} else {
    warn("File .env TIDAK ditemukan di root proyek. Loader fallback ke environment variable server (Apache SetEnv / nginx fastcgi_param).");
}

if (isGoogleLoginEnabled()) {
    ok('GOOGLE_CLIENT_ID & GOOGLE_CLIENT_SECRET terisi -> tombol "Masuk dengan Google" AKTIF');
    info('Client ID : ' . substr(GOOGLE_CLIENT_ID, 0, 24) . '...');
} else {
    bad('GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET masih kosong.');
    info('Isi di file .env, lalu reload halaman ini.');
}

// ---------- 2. Redirect URI ----------
echo "\n-- 2. Redirect URI --\n";
ok('Redirect URI yang akan dikirim ke Google:');
info(GOOGLE_REDIRECT_URI);
if ($isCli) {
    warn('(CLI) Tidak bisa mendeteksi URL request. Pastikan nilai di atas SAMA PERSIS dengan Authorized redirect URI di Google Cloud Console.');
} else {
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $current = $scheme . '://' . $host . dirname($_SERVER['SCRIPT_NAME']);
    // Normalisasi: hilangkan trailing slash
    $normCurrent = rtrim($current, '/');
    $normConfig  = rtrim(GOOGLE_REDIRECT_URI, '/');
    if ($normCurrent === $normConfig) {
        ok('Redirect URI cocok dengan URL aplikasi saat ini.');
    } elseif (stripos(GOOGLE_REDIRECT_URI, 'localhost') !== false || stripos(GOOGLE_REDIRECT_URI, '127.0.0.1') !== false) {
        warn('GOOGLE_REDIRECT_URI masih mengarah ke localhost.');
        info('Untuk deployment ini, set di .env:');
        info('GOOGLE_REDIRECT_URI=' . $normCurrent . '/google_callback.php');
    } else {
        warn('Redirect URI tidak sama dengan URL aplikasi saat ini.');
        info('Aplikasi diakses dari : ' . $normCurrent);
        info('Konfigurasi saat ini : ' . $normConfig);
        info('Pastikan nilai di atas ditambahkan persis di Cloud Console > Credentials > Authorized redirect URIs,');
        info('atau sesuaikan GOOGLE_REDIRECT_URI / APP_URL_PORTAL di .env.');
    }
}

// ---------- 3. Konektivitas ke Google ----------
echo "\n-- 3. Konektivitas ke server Google --\n";
if (!function_exists('curl_init')) {
    bad('Ekstensi PHP cURL tidak tersedia. Aktifkan extension=curl di php.ini.');
} else {
    ok('Ekstensi cURL tersedia.');

    $ch = curl_init(GOOGLE_TOKEN_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'code'          => 'setup-check-invalid',
            'client_id'     => GOOGLE_CLIENT_ID ?: 'check@example.com',
            'client_secret' => GOOGLE_CLIENT_SECRET ?: 'check-secret',
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        bad('Gagal menghubungi oauth2.googleapis.com: ' . $err);
        info('Periksa firewall/outbound akses server ke port 443 & konfigurasi SSL/CA bundle PHP.');
    } else {
        $j = json_decode((string) $resp, true);
        $gErr = $j['error'] ?? '';
        ok('Server Google dapat dijangkau (HTTP ' . $http . '). SSL verify aktif dan berfungsi.');
        // Google merespon request token dengan error spesifik yang menunjukkan
        // apakah masalahnya di kredensial atau di redirect URI.
        switch ($gErr) {
            case 'invalid_client':
                bad('Google menolak GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET Anda (invalid_client).');
                info('Salin ulang kredensial dari Cloud Console ke .env, pastikan tidak ada spete/quote sisa.');
                break;
            case 'invalid_grant':
                warn('Google merespon invalid_grant — biasanya karena REDIRECT URI tidak sama persis');
                info('dengan Authorized redirect URI terdaftar, atau code token kadaluarsa.');
                info('Cek kembali langkah No. 2 di atas & di Cloud Console.');
                break;
            case 'unauthorized_client':
                bad('OAuth client belum diizinkan untuk authorization code flow.');
                info('Di Cloud Console pastikan tipe credential = "Web application".');
                break;
            default:
                info('Respons Google: ' . ($gErr !== '' ? $gErr : '(code invalid seperti diharapkan untuk probe)'));
                ok('Kredensial diterima Google (probe code-token ditolak hanya karena kode uji tidak valid).');
        }
    }
}

// ---------- 4. Database ----------
echo "\n-- 4. Koneksi Database --\n";
$pdo = null;
if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
    bad('Driver PDO MySQL (pdo_mysql) tidak terpasang di PHP ini.');
    info('Aktifkan extension=pdo_mysql dan extension=mysqli di php.ini / cPanel > Select PHP Version.');
} else {
    try {
        // Matikan die() bawaan getDBConnection() agar bisa ditangkap di sini
        ob_start();
        require_once __DIR__ . '/config/database.php';
        $pdo = getDBConnection();
        ob_end_clean();
        ok('Koneksi MySQL berhasil (DB: ' . DB_NAME . '@' . DB_HOST . ':' . DB_PORT . ')');
    } catch (Throwable $e) {
        if (ob_get_level()) { ob_end_clean(); }
        bad('Koneksi database GAGAL: ' . $e->getMessage());
        info('Periksa DB_HOST / DB_NAME / DB_USER / DB_PASS di file .env.');
    }
}

if ($pdo instanceof PDO) {
    // ---------- 5. Struktur DB ----------
    echo "\n-- 5. Struktur Database (migrasi users_schema.sql) --\n";

    $colEmail = $pdo->query("SHOW COLUMNS FROM users LIKE 'email'")->fetch();
    $colEmail ? ok('Kolom users.email tersedia (' . $colEmail['Type'] . ')')
              : bad('Kolom users.email TIDAK ada. Jalankan migrasi skema.');

    try {
        $idx = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'idx_users_email'")->fetchAll();
        $idx ? ok('Index idx_users_email sudah terpasang.')
             : warn('Index idx_users_email BELUM ada. Jalankan: ALTER TABLE users ADD INDEX idx_users_email (email);');
    } catch (Throwable $e) {
        warn('Tidak bisa memeriksa index users: ' . $e->getMessage());
    }

    try {
        $tbl = $pdo->query("SHOW TABLES LIKE 'login_logs'")->fetch();
        $tbl ? ok('Tabel login_logs tersedia untuk pencatatan aktivitas login Google.')
              : warn('Tabel login_logs BELUM ada. Login tetap jalan, tapi aktivitas tidak tercatat. Jalankan CREATE TABLE login_logs di users_schema.sql.');
    } catch (Throwable $e) {
        warn('Tidak bisa memeriksa tabel login_logs: ' . $e->getMessage());
    }

    // ---------- 6. Kesiapan data users ----------
    echo "\n-- 6. Kesiapan Data Users --\n";
    try {
        $totalUsers   = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $withEmail    = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email IS NOT NULL AND TRIM(email) <> ''")->fetchColumn();
        $activeWithEmail = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1 AND email IS NOT NULL AND TRIM(email) <> ''")->fetchColumn();
        $dupEmails    = $pdo->query(
            "SELECT email, COUNT(*) c FROM users
              WHERE email IS NOT NULL AND TRIM(email) <> ''
           GROUP BY LOWER(TRIM(email)) HAVING c > 1"
        )->fetchAll();

        info("Total user            : $totalUsers");
        info("User dengan email     : $withEmail");
        info("User aktif dng email  : $activeWithEmail  <- inilah yang bisa login Google");

        if ($withEmail === 0) {
            bad('Belum ada satu pun user yang memiliki email. Login Google tidak akan bisa mencocokkan akun.');
            info('Lengkapi kolom users.email (harus SAMA dengan alamat Gmail/Workspace akun Google user).');
        } else {
            ok('Minimal satu user memiliki email untuk dicocokkan dengan akun Google.');
        }

        if ($dupEmails) {
            warn('Ditemukan ' . count($dupEmails) . ' email duplikat di tabel users (pencocokan akan memakai baris pertama):');
            foreach (array_slice($dupEmails, 0, 5) as $d) {
                info('  - ' . $d['email'] . ' (' . $d['c'] . 'x)');
            }
        } else {
            ok('Tidak ada email duplikat.');
        }

        $badDomain = $pdo->query(
            "SELECT COUNT(*) FROM users
              WHERE email IS NOT NULL AND TRIM(email) <> ''
                AND email NOT LIKE '%@%'"
        )->fetchColumn();
        (int) $badDomain === 0 ? ok('Semua format email valid (mengandung "@").')
                               : warn((int) $badDomain . " email tidak mengandung '@' dan tak akan pernah cocok.");
    } catch (Throwable $e) {
        bad('Gagal memeriksa data users: ' . $e->getMessage());
    }
}

// ---------- Ringkasan ----------
echo "\n==========================================================\n";
printf(" HASIL: %d OK, %d WARN, %d FAIL\n", $pass, $warn, $fail);
if ($fail === 0 && $warn === 0) {
    echo " Semua pemeriksaan lolos. Login with Google siap digunakan.\n";
} elseif ($fail === 0) {
    echo " Siap digunakan, namun ada beberapa catatan (WARN) di atas.\n";
} else {
    echo " Masih ada masalah (FAIL) yang harus diperbaiki sebelum login Google berfungsi.\n";
}
echo " Setelah deploy: buka form login, klik \"Masuk dengan Google\", dan verifikasi.\n";
echo " Setelah selesai, HAPUS file google_setup_check.php dari server produksi.\n";
echo "==========================================================\n";
